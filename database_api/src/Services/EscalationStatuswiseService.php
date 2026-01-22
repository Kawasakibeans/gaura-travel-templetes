<?php
/**
 * Escalation Statuswise Service - Business Logic Layer
 * Handles business logic for escalation metrics grouped by status and escalated_to
 */

namespace App\Services;

use App\DAL\EscalationStatuswiseDAL;
use Exception;

class EscalationStatuswiseService
{
    private $escalationStatuswiseDAL;

    public function __construct()
    {
        $this->escalationStatuswiseDAL = new EscalationStatuswiseDAL();
    }

    /**
     * Get daily rollup data grouped by status and escalated_to
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Formatted data with daily rollup and totals
     */
    public function getDailyRollup($startDate = null, $endDate = null)
    {
        // Set default dates if not provided
        if ($startDate === null) {
            $startDate = date('Y-m-01');
        }
        if ($endDate === null) {
            $endDate = date('Y-m-t');
        }

        // Validate dates
        if (!$this->validateDate($startDate) || !$this->validateDate($endDate)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        // Get daily rollup data
        $daily = $this->escalationStatuswiseDAL->getDailyRollup($startDate, $endDate);

        // Calculate totals
        $totals = [
            'status' => [
                'open' => 0,
                'closed' => 0,
                'pending' => 0
            ],
            'escalated_to' => [
                'ho' => 0,
                'manager' => 0,
                'blank' => 0
            ]
        ];

        foreach ($daily as $row) {
            $totals['status']['open'] += $row['status']['open'];
            $totals['status']['closed'] += $row['status']['closed'];
            $totals['status']['pending'] += $row['status']['pending'];
            $totals['escalated_to']['ho'] += $row['escalated_to']['ho'];
            $totals['escalated_to']['manager'] += $row['escalated_to']['manager'];
            $totals['escalated_to']['blank'] += $row['escalated_to']['blank'];
        }

        return [
            'success' => true,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => $daily,
            'totals' => $totals,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get escalation details for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByDate($date)
    {
        // Validate date
        if (!$this->validateDate($date)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        return $this->escalationStatuswiseDAL->getEscalationDetailsByDate($date);
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

