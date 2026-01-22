<?php
/**
 * Escalation Service - Business Logic Layer
 * Handles business logic for escalation metrics
 */

namespace App\Services;

use App\DAL\EscalationDAL;
use Exception;

class EscalationService
{
    private $escalationDAL;

    public function __construct()
    {
        $this->escalationDAL = new EscalationDAL();
    }

    /**
     * Get escalation metrics
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string|null $status Status filter (null for all)
     * @param string|null $escalatedTo Escalated to filter (null for all)
     * @return array Formatted data with escalation metrics
     */
    public function getEscalationMetrics($startDate = null, $endDate = null, $status = null, $escalatedTo = null)
    {
        // Set default dates if not provided
        if ($startDate === null) {
            $startDate = date('Y-m-01');
        }
        if ($endDate === null) {
            $endDate = date('Y-m-t');
        }

        // Validate dates
        if (!$this->validateDate($startDate)) {
            throw new Exception("Invalid start_date format. Received: '{$startDate}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)", 400);
        }
        if (!$this->validateDate($endDate)) {
            throw new Exception("Invalid end_date format. Received: '{$endDate}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)", 400);
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        // Get data for different date ranges
        $data_1_10 = $this->escalationDAL->getEscalationData($startDate, $endDate, 1, 10, $status, $escalatedTo);
        $data_11_20 = $this->escalationDAL->getEscalationData($startDate, $endDate, 11, 20, $status, $escalatedTo);
        $data_21_end = $this->escalationDAL->getEscalationData($startDate, $endDate, 21, 31, $status, $escalatedTo);
        $data_total = $this->escalationDAL->getEscalationData($startDate, $endDate, null, null, $status, $escalatedTo);

        // Get filter options
        $escalationTypes = $this->escalationDAL->getDistinctEscalationTypes();
        $statuses = $this->escalationDAL->getDistinctStatuses();
        $escalatedToOptions = $this->escalationDAL->getDistinctEscalatedTo();

        return [
            'success' => true,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'filters' => [
                'status' => $status ?: 'all',
                'escalated_to' => $escalatedTo ?: 'all'
            ],
            'data' => [
                'day_1_10' => $data_1_10,
                'day_11_20' => $data_11_20,
                'day_21_end' => $data_21_end,
                'total' => $data_total
            ],
            'filter_options' => [
                'escalation_types' => $escalationTypes,
                'statuses' => $statuses,
                'escalated_to' => $escalatedToOptions
            ],
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
     * @param string|null $status Status filter (null for all)
     * @param string|null $escalatedTo Escalated to filter (null for all)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByDate($date, $status = null, $escalatedTo = null)
    {
        // Validate date
        if (!$this->validateDate($date)) {
            throw new Exception("Invalid date format. Received: '{$date}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)", 400);
        }

        return $this->escalationDAL->getEscalationDetailsByDate($date, $status, $escalatedTo);
    }

    /**
     * Get distinct escalation types
     * 
     * @return array Array of escalation types
     */
    public function getDistinctEscalationTypes()
    {
        return $this->escalationDAL->getDistinctEscalationTypes();
    }

    /**
     * Get distinct statuses
     * 
     * @return array Array of statuses
     */
    public function getDistinctStatuses()
    {
        return $this->escalationDAL->getDistinctStatuses();
    }

    /**
     * Get distinct escalated_to values
     * 
     * @return array Array of escalated_to values
     */
    public function getDistinctEscalatedTo()
    {
        return $this->escalationDAL->getDistinctEscalatedTo();
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

