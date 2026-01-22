<?php
/**
 * Escalation Agentwise Service - Business Logic Layer
 * Handles business logic for escalation metrics grouped by user/agent
 */

namespace App\Services;

use App\DAL\EscalationAgentwiseDAL;
use Exception;

class EscalationAgentwiseService
{
    private $escalationAgentwiseDAL;

    public function __construct()
    {
        $this->escalationAgentwiseDAL = new EscalationAgentwiseDAL();
    }

    /**
     * Get escalation metrics grouped by user/agent
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Formatted data with escalation metrics by user
     */
    public function getEscalationMetricsByUser($startDate = null, $endDate = null)
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

        // Get data grouped by user
        $data = $this->escalationAgentwiseDAL->getEscalationDataByUser($startDate, $endDate);

        // Get escalation types for reference
        $escalationTypes = $this->escalationAgentwiseDAL->getDistinctEscalationTypesByDateRange($startDate, $endDate);

        return [
            'success' => true,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => $data,
            'escalation_types' => $escalationTypes,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get escalation details for a specific user
     * 
     * @param string $user User name (escalated_by)
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByUser($user, $startDate = null, $endDate = null)
    {
        if (empty($user)) {
            throw new Exception('User name is required', 400);
        }

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

        return $this->escalationAgentwiseDAL->getEscalationDetailsByUser($user, $startDate, $endDate);
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

