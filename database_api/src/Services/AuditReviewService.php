<?php
/**
 * Audit Review Service - Business Logic Layer
 * Handles business logic for audit review metrics
 */

namespace App\Services;

use App\DAL\AuditReviewDAL;
use Exception;

class AuditReviewService
{
    private $auditReviewDAL;

    public function __construct()
    {
        $this->auditReviewDAL = new AuditReviewDAL();
    }

    /**
     * Get audit review metrics grouped by date ranges
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Formatted data with grouped metrics
     */
    public function getAuditReviewMetrics($fromDate = null, $toDate = null, $agent = '')
    {
        // Set default dates if not provided
        if ($fromDate === null) {
            $fromDate = date('Y-m-01');
        }
        if ($toDate === null) {
            $toDate = date('Y-m-t');
        }

        // Validate dates
        if (!$this->validateDate($fromDate) || !$this->validateDate($toDate)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        if (strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        // Calculate last day of end date month
        $endDateObj = new \DateTime($toDate);
        $lastDay = (int)$endDateObj->format('d');

        // Build date ranges
        $ranges = [
            "1 - 10" => [1, 10],
            "11 - 20" => [11, 20],
            "21 - $lastDay" => [21, $lastDay],
            "Total" => [1, $lastDay]
        ];

        // Fetch data for each range
        $rangeData = [];
        foreach ($ranges as $title => [$startDay, $endDay]) {
            $data = $this->auditReviewDAL->getDateSummary($startDay, $endDay, $fromDate, $toDate, $agent);
            
            // Calculate totals for the range
            $total_fit = 0;
            $total_gdeal = 0;
            $total_audit = 0;
            
            foreach ($data as $row) {
                $total_fit += $row['fit_audit'];
                $total_gdeal += $row['gdeal_audit'];
                $total_audit += $row['ticket_audited'];
            }
            
            $rangeData[] = [
                'title' => $title,
                'start_day' => $startDay,
                'end_day' => $endDay,
                'data' => $data,
                'totals' => [
                    'fit_audit' => $total_fit,
                    'gdeal_audit' => $total_gdeal,
                    'ticket_audited' => $total_audit
                ]
            ];
        }

        // Get distinct agents
        $agents = $this->auditReviewDAL->getDistinctAgents();

        return [
            'success' => true,
            'date_range' => [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ],
            'agent' => $agent ?: 'all',
            'ranges' => $rangeData,
            'agents' => $agents,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get agent data for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Array of agent records for the date
     */
    public function getAgentDetailsByDate($date, $agent = '')
    {
        // Validate date
        if (!$this->validateDate($date)) {
            throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)', 400);
        }

        return $this->auditReviewDAL->getAgentDetailsByDate($date, $agent);
    }

    /**
     * Get distinct agents list
     * 
     * @return array Array of agent names
     */
    public function getDistinctAgents()
    {
        return $this->auditReviewDAL->getDistinctAgents();
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

