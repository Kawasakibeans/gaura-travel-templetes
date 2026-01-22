<?php
/**
 * Customer Search Service - Business Logic Layer
 * Handles customer search by multiple criteria
 */

namespace App\Services;

use App\DAL\CustomerSearchDAL;
use Exception;

class CustomerSearchService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CustomerSearchDAL();
    }

    /**
     * Search customers by multiple criteria
     */
    public function searchCustomers($filters)
    {
        $customerId = $filters['customer_id'] ?? null;
        $familyId = $filters['family_id'] ?? null;
        $profileId = $filters['profile_id'] ?? null;
        $orderId = $filters['order_id'] ?? null;
        $email = $filters['email'] ?? null;
        $phone = $filters['phone'] ?? null;
        $limit = (int)($filters['limit'] ?? 10);

        // Validate at least one filter is provided
        if (empty($customerId) && empty($familyId) && empty($profileId) && 
            empty($orderId) && empty($email) && empty($phone)) {
            return [
                'customers' => [],
                'message' => 'Please provide at least one search filter',
                'filters' => $filters
            ];
        }

        $customers = $this->dal->searchCustomers(
            $customerId, $familyId, $profileId, $orderId, $email, $phone, $limit
        );

        return [
            'customers' => $customers,
            'total_count' => count($customers),
            'filters' => $filters
        ];
    }

    /**
     * Search bookings by phone number
     */
    public function searchBookingsByPhone($phone, $limit = 100)
    {
        if (empty($phone)) {
            throw new Exception('Phone number is required', 400);
        }

        // Get last 8 digits for comparison
        $phoneNums = substr($phone, -8);

        $bookings = $this->dal->getBookingsByPhone($phoneNums, $limit);

        return [
            'phone' => $phone,
            'phone_search' => $phoneNums,
            'bookings' => $bookings,
            'total_count' => count($bookings)
        ];
    }

    /**
     * Search quotes by phone number
     */
    public function searchQuotesByPhone($phone, $limit = 50)
    {
        if (empty($phone)) {
            throw new Exception('Phone number is required', 400);
        }

        $quotes = $this->dal->getQuotesByPhone($phone, $limit);

        return [
            'phone' => $phone,
            'quotes' => $quotes,
            'total_count' => count($quotes)
        ];
    }

    /**
     * Get complete customer profile by phone
     */
    public function getCustomerProfileByPhone($phone)
    {
        if (empty($phone)) {
            throw new Exception('Phone number is required', 400);
        }

        $phoneNums = substr($phone, -8);

        // Get bookings
        $bookings = $this->dal->getBookingsByPhone($phoneNums, 100);
        
        // Get quotes
        $quotes = $this->dal->getQuotesByPhone($phone, 50);

        // Get customer info from first booking or quote
        $customerInfo = null;
        if (!empty($bookings)) {
            $customerInfo = [
                'source' => 'booking',
                'phone' => $bookings[0]['phone_pax'] ?? $phone
            ];
        } else if (!empty($quotes)) {
            $customerInfo = [
                'source' => 'quote',
                'name' => $quotes[0]['name'] ?? null,
                'email' => $quotes[0]['email'] ?? null,
                'phone' => $quotes[0]['phone_num'] ?? $phone
            ];
        }

        return [
            'phone' => $phone,
            'customer_info' => $customerInfo,
            'bookings' => $bookings,
            'bookings_count' => count($bookings),
            'quotes' => $quotes,
            'quotes_count' => count($quotes)
        ];
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById($customerId)
    {
        if (empty($customerId)) {
            throw new Exception('Customer ID is required', 400);
        }

        $customer = $this->dal->getCustomerById($customerId);

        if (!$customer) {
            throw new Exception('Customer not found', 404);
        }

        // Get customer's bookings
        $bookings = $this->dal->getCustomerBookings($customerId);

        return [
            'customer' => $customer,
            'bookings' => $bookings,
            'bookings_count' => count($bookings)
        ];
    }
}

