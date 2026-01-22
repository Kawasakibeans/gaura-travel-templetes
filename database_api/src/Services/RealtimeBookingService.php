<?php
/**
 * Realtime Booking Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\RealtimeBookingDAL;
use Exception;

class RealtimeBookingService
{
    private $realtimeDAL;
    
    public function __construct()
    {
        $this->realtimeDAL = new RealtimeBookingDAL();
    }
    
    /**
     * Wipe realtime bookings for a specific date (defaults to yesterday)
     */
    public function wipeRealtimeBookings($targetDate = null)
    {
        // Set timezone
        date_default_timezone_set("Australia/Melbourne");
        
        // If no date provided, use yesterday
        if (empty($targetDate)) {
            $targetDate = date('Y-m-d', strtotime('yesterday'));
        } else {
            // Validate date format
            $dateObj = \DateTime::createFromFormat('Y-m-d', $targetDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $targetDate) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
            }
        }
        
        // Perform deletion in transaction
        $result = $this->realtimeDAL->wipeRealtimeBookings($targetDate);
        
        return [
            'success' => true,
            'message' => 'Realtime bookings wiped successfully',
            'date' => $targetDate,
            'deleted' => $result
        ];
    }
}

