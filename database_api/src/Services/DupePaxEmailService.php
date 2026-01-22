<?php
/**
 * Duplicate Passenger Bookings by Email Service - Business Logic Layer
 * Handles business logic for duplicate passenger bookings grouped by email
 */

namespace App\Services;

use App\DAL\DupePaxEmailDAL;
use Exception;

class DupePaxEmailService
{
    private $dupePaxEmailDAL;

    public function __construct()
    {
        $this->dupePaxEmailDAL = new DupePaxEmailDAL();
    }

    /**
     * Get duplicate passenger bookings by email
     * 
     * @param string $travelFrom Travel date from (Y-m-d format)
     * @param string $travelTo Travel date to (Y-m-d format)
     * @param string $email Email filter (optional, empty string for all)
     * @return array Formatted data with duplicate bookings
     */
    public function getDuplicateBookingsByEmail($travelFrom = null, $travelTo = null, $email = '')
    {
        // Set default dates if not provided (today)
        $today = date('Y-m-d');
        if ($travelFrom === null) {
            $travelFrom = $today;
        }
        if ($travelTo === null) {
            $travelTo = $today;
        }

        // Validate dates
        if (!$this->validateDate($travelFrom) || !$this->validateDate($travelTo)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        if (strtotime($travelFrom) > strtotime($travelTo)) {
            throw new Exception('Travel date from must be before or equal to travel date to', 400);
        }

        // Get duplicate bookings
        $dupes = $this->dupePaxEmailDAL->getDuplicateBookingsByEmail($travelFrom, $travelTo, $email);

        // Calculate summary metrics
        $total_groups = count($dupes);
        $total_bookings = 0;
        foreach ($dupes as $group) {
            $total_bookings += count($group['distinct_orders'] ?? []);
        }

        return [
            'success' => true,
            'date_range' => [
                'travel_date' => [
                    'from' => $travelFrom,
                    'to' => $travelTo
                ]
            ],
            'email_filter' => $email ?: 'all',
            'summary' => [
                'total_duplicate_groups' => $total_groups,
                'total_duplicate_bookings' => $total_bookings,
                'source_rows_scanned' => 0 // This would need to be tracked separately if needed
            ],
            'duplicate_groups' => $dupes,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Expected format
     * @return bool True if valid, false otherwise
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

