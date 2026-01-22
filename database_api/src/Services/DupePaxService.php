<?php
/**
 * Duplicate Passenger Bookings Service - Business Logic Layer
 * Handles business logic for duplicate passenger bookings
 */

namespace App\Services;

use App\DAL\DupePaxDAL;
use Exception;

class DupePaxService
{
    private $dupePaxDAL;

    public function __construct()
    {
        $this->dupePaxDAL = new DupePaxDAL();
    }

    /**
     * Get duplicate passenger bookings
     * 
     * @param string $odFrom Order date from (Y-m-d format)
     * @param string $odTo Order date to (Y-m-d format)
     * @param string $pmFrom Payment modified from (Y-m-d format)
     * @param string $pmTo Payment modified to (Y-m-d format)
     * @return array Formatted data with duplicate bookings
     */
    public function getDuplicateBookings($odFrom = null, $odTo = null, $pmFrom = null, $pmTo = null)
    {
        // Set default dates if not provided (today)
        $today = date('Y-m-d');
        if ($odFrom === null) {
            $odFrom = $today;
        }
        if ($odTo === null) {
            $odTo = $today;
        }
        if ($pmFrom === null) {
            $pmFrom = $today;
        }
        if ($pmTo === null) {
            $pmTo = $today;
        }

        // Validate dates
        if (!$this->validateDate($odFrom) || !$this->validateDate($odTo) ||
            !$this->validateDate($pmFrom) || !$this->validateDate($pmTo)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        if (strtotime($odFrom) > strtotime($odTo)) {
            throw new Exception('Order date from must be before or equal to order date to', 400);
        }

        if (strtotime($pmFrom) > strtotime($pmTo)) {
            throw new Exception('Payment modified from must be before or equal to payment modified to', 400);
        }

        // Get duplicate bookings
        $dupes = $this->dupePaxDAL->getDuplicateBookings($odFrom, $odTo, $pmFrom, $pmTo);

        // Calculate summary metrics
        $total_groups = count($dupes);
        $total_bookings = 0;
        foreach ($dupes as $group) {
            $total_bookings += count($group['distinct_orders'] ?? []);
        }

        return [
            'success' => true,
            'date_ranges' => [
                'order_date' => [
                    'from' => $odFrom,
                    'to' => $odTo
                ],
                'payment_modified' => [
                    'from' => $pmFrom,
                    'to' => $pmTo
                ]
            ],
            'summary' => [
                'total_duplicate_groups' => $total_groups,
                'total_duplicate_bookings' => $total_bookings
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

