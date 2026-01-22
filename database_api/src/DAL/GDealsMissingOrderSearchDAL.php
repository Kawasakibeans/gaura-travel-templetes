<?php
/**
 * GDeals Missing Order Search Data Access Layer
 * Handles database operations for checking if GDeals bookings exist
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDealsMissingOrderSearchDAL extends BaseDAL
{
    /**
     * Check if booking exists by order ID
     */
    public function checkBookingExists(int $orderId): bool
    {
        try {
            $sql = "
                SELECT COUNT(*) as count 
                FROM wpk4_backend_travel_bookings 
                WHERE order_type = 'WPT' 
                AND order_id = :order_id
            ";
            
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            if ($result === false || !is_array($result)) {
                return false;
            }
            return ((int)$result['count']) > 0;
        } catch (\Exception $e) {
            error_log("GDealsMissingOrderSearchDAL::checkBookingExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId(int $orderId): ?array
    {
        try {
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_bookings 
                WHERE order_type = 'WPT' 
                AND order_id = :order_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            error_log("GDealsMissingOrderSearchDAL::getBookingByOrderId error: " . $e->getMessage());
            return null;
        }
    }
}

