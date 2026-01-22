<?php
/**
 * GDS API Booking Manual Import Data Access Layer
 * Handles database operations for manual GDS booking import
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDSAPIBookingManualImportDAL extends BaseDAL
{
    /**
     * Get last order ID
     */
    public function getLastOrderId(): ?int
    {
        try {
            $sql = "
                SELECT order_id 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id > 90009895 
                AND order_id < 100009895 
                ORDER BY order_id DESC 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, []);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return (int)($result['order_id'] ?? null);
        } catch (\Exception $e) {
            error_log("GDSAPIBookingManualImportDAL::getLastOrderId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if passenger exists by PNR and name
     */
    public function checkPassengerExists(string $pnr, string $lastName, string $firstName): ?array
    {
        try {
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_booking_pax 
                WHERE pnr = :pnr 
                AND lname LIKE :last_name 
                AND fname LIKE :first_name 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [
                ':pnr' => $pnr,
                ':last_name' => '%' . $lastName . '%',
                ':first_name' => '%' . $firstName . '%'
            ]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            error_log("GDSAPIBookingManualImportDAL::checkPassengerExists error: " . $e->getMessage());
            return null;
        }
    }
}

