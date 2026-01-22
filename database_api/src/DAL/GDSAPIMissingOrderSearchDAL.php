<?php
/**
 * GDS API Missing Order Search Data Access Layer
 * Handles database operations for checking if GDS bookings exist by PNR
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDSAPIMissingOrderSearchDAL extends BaseDAL
{
    /**
     * Check if PNR exists
     */
    public function checkPnrExists(string $pnr, string $date): bool
    {
        try {
            $sql = "
                SELECT COUNT(*) as count 
                FROM wpk4_backend_travel_booking_pax 
                WHERE pnr = :pnr 
                AND DATE(order_date) = :date
            ";
            
            $result = $this->queryOne($sql, [
                ':pnr' => $pnr,
                ':date' => $date
            ]);
            if ($result === false || !is_array($result)) {
                return false;
            }
            return ((int)$result['count']) > 0;
        } catch (\Exception $e) {
            error_log("GDSAPIMissingOrderSearchDAL::checkPnrExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order info by PNR and date
     */
    public function getOrderInfoByPnr(string $pnr, string $date): ?array
    {
        try {
            $sql = "
                SELECT p.*, b.* 
                FROM wpk4_backend_travel_booking_pax p
                LEFT JOIN wpk4_backend_travel_bookings b ON p.order_id = b.order_id
                WHERE p.pnr = :pnr 
                AND DATE(p.order_date) = :date
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [
                ':pnr' => $pnr,
                ':date' => $date
            ]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            error_log("GDSAPIMissingOrderSearchDAL::getOrderInfoByPnr error: " . $e->getMessage());
            return null;
        }
    }
}

