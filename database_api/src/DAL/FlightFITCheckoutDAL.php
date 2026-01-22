<?php
/**
 * Flight FIT Checkout Data Access Layer
 * Handles database operations for FIT checkout itinerary backend
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class FlightFITCheckoutDAL extends BaseDAL
{
    /**
     * Get PNR by order ID
     */
    public function getPnrByOrderId(int $orderId): ?string
    {
        try {
            $sql = "
                SELECT pnr 
                FROM wpk4_backend_travel_booking_pax_g360_booking 
                WHERE order_id = :order_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result['pnr'] ?? null;
        } catch (\Exception $e) {
            error_log("FlightFITCheckoutDAL::getPnrByOrderId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get agent conso by order ID
     */
    public function getAgentConsoByOrderId(int $orderId): ?string
    {
        try {
            $sql = "
                SELECT agent_conso 
                FROM wpk4_backend_travel_bookings_g360_booking 
                WHERE order_id = :order_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result['agent_conso'] ?? null;
        } catch (\Exception $e) {
            error_log("FlightFITCheckoutDAL::getAgentConsoByOrderId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get order date by order ID
     */
    public function getOrderDateByOrderId(int $orderId): ?string
    {
        try {
            $sql = "
                SELECT order_date 
                FROM wpk4_backend_travel_bookings_g360_booking 
                WHERE order_id = :order_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result['order_date'] ?? null;
        } catch (\Exception $e) {
            error_log("FlightFITCheckoutDAL::getOrderDateByOrderId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get airport info by code
     */
    public function getAirportInfo(string $airportCode): ?array
    {
        $sql = "
            SELECT city, airpotname 
            FROM airport_list_bk 
            WHERE airpotcode = :airport_code 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':airport_code' => $airportCode]);
        if ($result === false || !is_array($result)) {
            return null;
        }
        return $result;
    }
}

