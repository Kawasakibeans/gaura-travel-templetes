<?php
/**
 * Realtime Booking Data Access Layer
 * Handles database operations for realtime booking cleanup
 */

namespace App\DAL;

use Exception;
use PDOException;

class RealtimeBookingDAL extends BaseDAL
{
    /**
     * Delete bookings by date
     */
    public function deleteBookingsByDate($targetDate)
    {
        try {
            $query = "
                DELETE FROM wpk4_backend_travel_bookings_realtime 
                WHERE date(order_date) = :target_date
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['target_date' => $targetDate]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("RealtimeBookingDAL::deleteBookingsByDate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete passengers by date
     */
    public function deletePassengersByDate($targetDate)
    {
        try {
            $query = "
                DELETE FROM wpk4_backend_travel_booking_pax_realtime 
                WHERE date(order_date) = :target_date
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['target_date' => $targetDate]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("RealtimeBookingDAL::deletePassengersByDate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Wipe realtime bookings for a specific date (transactional)
     */
    public function wipeRealtimeBookings($targetDate)
    {
        try {
            $this->beginTransaction();
            
            $bookingsDeleted = $this->deleteBookingsByDate($targetDate);
            $passengersDeleted = $this->deletePassengersByDate($targetDate);
            
            $this->commit();
            
            return [
                'bookings' => $bookingsDeleted,
                'passengers' => $passengersDeleted
            ];
        } catch (Exception $e) {
            $this->rollback();
            error_log("RealtimeBookingDAL::wipeRealtimeBookings error: " . $e->getMessage());
            throw $e;
        }
    }
}

