<?php
/**
 * Booking Admin Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\BookingAdminDAL;
use Exception;

class BookingAdminService
{
    private $bookingDAL;
    
    public function __construct()
    {
        $this->bookingDAL = new BookingAdminDAL();
    }
    
    /**
     * Unserialize meta value if needed
     */
    private function unserializeMeta($value)
    {
        if (empty($value)) {
            return null;
        }
        
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }
    
    /**
     * Extract traveler information from booking meta
     */
    private function extractTravelerInfo($bookingMeta, $index = 0)
    {
        $traveler = [];
        
        // Common traveler fields
        $fields = [
            'wp_travel_fname_traveller' => 'first_name',
            'wp_travel_lname_traveller' => 'last_name',
            'wp_travel_country_traveller' => 'country',
            'wp_travel_phone_traveller' => 'phone',
            'wp_travel_email_traveller' => 'email',
            'wp_travel_date_of_birth_traveller' => 'dob',
            'wp_travel_gender_traveller' => 'gender',
            'wp_travel_passport_num_traveller' => 'passport_num',
            'wp_travel_passport_exp_date_traveller' => 'passport_exp_date',
            'wp_travel_passport_type_traveller' => 'passport_type',
            'wp_travel_visa_type_traveller' => 'visa_type',
            'wp_travel_meal_traveller' => 'meal',
            'wp_travel_wheel_chair_traveller' => 'wheel_chair',
        ];
        
        foreach ($fields as $metaKey => $fieldName) {
            $value = $bookingMeta[$metaKey] ?? null;
            if ($value) {
                $unserialized = $this->unserializeMeta($value);
                if (is_array($unserialized)) {
                    // Handle array values - extract by index
                    $traveler[$fieldName] = $unserialized[$index] ?? (is_array($unserialized) ? array_shift($unserialized) : $unserialized);
                } else {
                    $traveler[$fieldName] = $unserialized;
                }
            }
        }
        
        return $traveler;
    }
    
    /**
     * Get booking by order number
     */
    public function getBookingByOrderNumber($orderNumber)
    {
        if (empty($orderNumber)) {
            throw new Exception('Order number is required', 400);
        }
        
        // Get post
        $post = $this->bookingDAL->getPostById($orderNumber);
        if (!$post || $post['post_type'] !== 'itinerary-booking') {
            throw new Exception('Booking not found', 404);
        }
        
        // Get all booking meta
        $bookingMeta = $this->bookingDAL->getAllBookingMeta($orderNumber);
        
        // Extract key information
        $travelDate = $bookingMeta['wp_travel_arrival_date'] ?? null;
        $tripId = $bookingMeta['wp_travel_post_id'] ?? null;
        $orderItemsData = $this->unserializeMeta($bookingMeta['order_items_data'] ?? null);
        
        // Get trip title
        $tripPost = $tripId ? $this->bookingDAL->getPostById($tripId) : null;
        $tripTitle = $tripPost ? $tripPost['post_title'] : null;
        
        // Get payment status
        $paymentId = $this->bookingDAL->getPaymentId($orderNumber);
        $paymentStatus = null;
        if ($paymentId) {
            $paymentStatus = $this->bookingDAL->getBookingMeta($paymentId, 'wp_travel_payment_status');
        }
        
        // Extract travelers
        $travelers = [];
        $fname = $this->unserializeMeta($bookingMeta['wp_travel_fname_traveller'] ?? null);
        
        if (is_array($fname)) {
            // Multiple travelers
            foreach ($fname as $cartId => $firstNames) {
                if (is_array($firstNames)) {
                    foreach ($firstNames as $key => $firstName) {
                        $traveler = $this->extractTravelerInfo($bookingMeta, $key);
                        $traveler['first_name'] = $firstName;
                        $travelers[] = $traveler;
                    }
                }
            }
        } else {
            // Single traveler
            $traveler = $this->extractTravelerInfo($bookingMeta, 0);
            if (!empty($traveler)) {
                $travelers[] = $traveler;
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'booking_id' => $orderNumber,
                'travel_date' => $travelDate,
                'trip_id' => $tripId,
                'trip_title' => $tripTitle,
                'payment_status' => $paymentStatus,
                'travelers' => $travelers,
                'order_items_data' => $orderItemsData
            ]
        ];
    }
    
    /**
     * Get bookings by date
     */
    public function getBookingsByDate($bookingDate)
    {
        if (empty($bookingDate)) {
            throw new Exception('Booking date is required', 400);
        }
        
        // Validate date format
        $dateObj = \DateTime::createFromFormat('Y-m-d', $bookingDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $bookingDate) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }
        
        $bookings = $this->bookingDAL->getBookingsByDate($bookingDate);
        
        $result = [];
        foreach ($bookings as $booking) {
            $bookingId = $booking['ID'];
            
            // Get payment status
            $paymentId = $this->bookingDAL->getPaymentId($bookingId);
            $paymentStatus = null;
            if ($paymentId) {
                $paymentStatus = $this->bookingDAL->getBookingMeta($paymentId, 'wp_travel_payment_status');
            }
            
            // Get booking meta for travelers
            $bookingMeta = $this->bookingDAL->getAllBookingMeta($bookingId);
            $travelers = [];
            
            $fname = $this->unserializeMeta($bookingMeta['wp_travel_fname_traveller'] ?? null);
            if (is_array($fname)) {
                foreach ($fname as $cartId => $firstNames) {
                    if (is_array($firstNames)) {
                        foreach ($firstNames as $key => $firstName) {
                            $traveler = $this->extractTravelerInfo($bookingMeta, $key);
                            $traveler['first_name'] = $firstName;
                            $travelers[] = $traveler;
                        }
                    }
                }
            } else {
                $traveler = $this->extractTravelerInfo($bookingMeta, 0);
                if (!empty($traveler)) {
                    $travelers[] = $traveler;
                }
            }
            
            $result[] = [
                'booking_id' => $bookingId,
                'travel_date' => $booking['travel_date'],
                'payment_status' => $paymentStatus,
                'travelers' => $travelers
            ];
        }
        
        return [
            'success' => true,
            'count' => count($result),
            'bookings' => $result
        ];
    }
    
    /**
     * Get Ypsilon updated bookings
     */
    public function getYpsilonUpdatedBookings($limit = 100)
    {
        $limit = max(1, min(1000, (int)$limit)); // Limit between 1 and 1000
        
        $bookings = $this->bookingDAL->getYpsilonUpdatedBookings($limit);
        
        $result = [];
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            
            // Get latest update time
            $metaChange = $this->bookingDAL->getLatestMetaChange($orderId);
            $updatedOn = $metaChange ? $metaChange['updated_on'] : null;
            
            $result[] = [
                'order_id' => $orderId,
                'trip_code' => $booking['trip_code'] ?? null,
                'updated_on' => $updatedOn,
                'view_url' => "https://gauratravel.com.au/manage-wp-orders/?option=search&type=reference&id=" . $orderId
            ];
        }
        
        return [
            'success' => true,
            'count' => count($result),
            'bookings' => $result
        ];
    }
}

