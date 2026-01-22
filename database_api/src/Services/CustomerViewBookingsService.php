<?php

namespace App\Services;

use App\DAL\CustomerViewBookingsDAL;

class CustomerViewBookingsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CustomerViewBookingsDAL();
    }

    /**
     * Search bookings
     */
    public function searchBookings(array $params): array
    {
        $searchId = $params['id'] ?? $params['search_id'] ?? null;
        $email = $params['email'] ?? null;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 20;
        
        if (!$searchId || !$email) {
            return [
                'bookings' => [],
                'total' => 0
            ];
        }
        
        $bookings = $this->dal->searchBookings($searchId, $email, $limit);
        
        // Process bookings to get unique orders
        $processedOrders = [];
        $uniqueBookings = [];
        
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                
                // Get contact info
                $contactInfo = $this->dal->getContactInfo($orderId);
                $booking['contact_phone'] = $contactInfo['phone'];
                $booking['contact_email'] = $contactInfo['email'];
                
                $uniqueBookings[] = $booking;
            }
        }
        
        return [
            'bookings' => $uniqueBookings,
            'total' => count($uniqueBookings)
        ];
    }
    
    /**
     * Get booking details
     */
    public function getBookingDetails(string $orderId): array
    {
        $booking = $this->dal->getBookingByOrderId($orderId);
        
        if (!$booking) {
            throw new \Exception('Booking not found');
        }
        
        $summary = $this->dal->getBookingSummary($orderId);
        $contactInfo = $this->dal->getContactInfo($orderId);
        
        return [
            'booking' => $booking,
            'summary' => $summary,
            'contact' => $contactInfo
        ];
    }
    
    /**
     * Get pax details
     */
    public function getPaxDetails(array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        if (!$orderId) {
            throw new \Exception('order_id is required');
        }
        
        $coOrderId = $params['co_order_id'] ?? null;
        $productId = $params['product_id'] ?? null;
        
        return $this->dal->getPaxDetails($orderId, $coOrderId, $productId);
    }
    
    /**
     * Get payment history
     */
    public function getPaymentHistory(string $orderId): array
    {
        return $this->dal->getPaymentHistory($orderId);
    }
    
    /**
     * Get payment attachments
     */
    public function getPaymentAttachments(array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        if (!$orderId) {
            throw new \Exception('order_id is required');
        }
        
        $coOrderId = $params['co_order_id'] ?? null;
        $productId = $params['product_id'] ?? null;
        
        return $this->dal->getPaymentAttachments($orderId, $coOrderId, $productId);
    }
    
    /**
     * Get portal requests
     */
    public function getPortalRequests(string $orderId, ?array $pnrs = null): array
    {
        return $this->dal->getPortalRequests($orderId, $pnrs);
    }
}

