<?php
/**
 * GDeals Missing Order Search Service
 * Handles business logic for checking if GDeals bookings exist
 */

namespace App\Services;

use App\DAL\GDealsMissingOrderSearchDAL;

class GDealsMissingOrderSearchService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GDealsMissingOrderSearchDAL();
    }
    
    /**
     * Check if booking exists
     */
    public function checkBookingExists(array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        
        if (!$orderId) {
            throw new \Exception('order_id is required');
        }
        
        if (!is_numeric($orderId)) {
            throw new \Exception('order_id must be numeric');
        }
        
        $exists = $this->dal->checkBookingExists((int)$orderId);
        $booking = null;
        
        if ($exists) {
            $booking = $this->dal->getBookingByOrderId((int)$orderId);
        }
        
        return [
            'order_id' => (int)$orderId,
            'exists' => $exists,
            'booking' => $booking
        ];
    }
}

