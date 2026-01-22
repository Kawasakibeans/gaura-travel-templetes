<?php
/**
 * Date Change Request Service - Business Logic Layer
 * Handles business logic for date change request metrics
 */

namespace App\Services;

use App\DAL\DateChangeRequestDAL;
use Exception;

class DateChangeRequestService
{
    private $dateChangeRequestDAL;

    public function __construct()
    {
        $this->dateChangeRequestDAL = new DateChangeRequestDAL();
    }

    /**
     * Get date change request metrics
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Formatted data with metrics
     */
    public function getDateChangeRequestMetrics($fromDate = null, $toDate = null, $agent = '')
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

        // Get all requests
        $requests = $this->dateChangeRequestDAL->getDateChangeRequests($fromDate, $toDate, $agent);

        // Calculate metrics
        $total_requests = count($requests);
        $success_count = count(array_filter($requests, function($r) { return $r['status'] === 'success'; }));
        $failure_count = count(array_filter($requests, function($r) { return $r['status'] === 'fail'; }));
        $in_progress_count = count(array_filter($requests, function($r) { return $r['status'] === 'open'; }));
        
        $total_cost_given = array_sum(array_column($requests, 'cost_given'));
        $total_cost_taken = array_sum(array_column($requests, 'cost_taken'));
        $total_revenue = array_sum(array_column($requests, 'total_revenue'));

        // Get monthly summary
        $monthly_summary = $this->dateChangeRequestDAL->getMonthlySummary($fromDate, $toDate);

        // Get distinct agents
        $agents = $this->dateChangeRequestDAL->getDistinctAgents();

        return [
            'success' => true,
            'date_range' => [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ],
            'agent' => $agent ?: 'all',
            'metrics' => [
                'total_requests' => $total_requests,
                'success_count' => $success_count,
                'failure_count' => $failure_count,
                'in_progress_count' => $in_progress_count,
                'total_cost_given' => $total_cost_given,
                'total_cost_taken' => $total_cost_taken,
                'total_revenue' => $total_revenue,
                'success_rate' => $total_requests > 0 ? round(($success_count / $total_requests) * 100, 2) : 0
            ],
            'requests' => $requests,
            'monthly_summary' => $monthly_summary,
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

        return $this->dateChangeRequestDAL->getAgentDetailsByDate($date, $agent);
    }

    /**
     * Get dashboard data with all summaries
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Formatted data with all dashboard metrics
     */
    public function getDashboardData($fromDate = null, $toDate = null)
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

        // Get all requests
        $requests = $this->dateChangeRequestDAL->getDateChangeRequests($fromDate, $toDate);

        // Calculate metrics
        $total_requests = count($requests);
        $success_count = count(array_filter($requests, function($r) { return $r['status'] === 'success'; }));
        $failure_count = count(array_filter($requests, function($r) { return $r['status'] === 'fail'; }));
        $in_progress_count = count(array_filter($requests, function($r) { return $r['status'] === 'open'; }));
        
        $total_cost_given = array_sum(array_column($requests, 'cost_given'));
        $total_cost_taken = array_sum(array_column($requests, 'cost_taken'));
        $total_revenue = array_sum(array_column($requests, 'total_revenue'));

        // Get all summaries
        $monthly_summary = $this->dateChangeRequestDAL->getMonthlySummary($fromDate, $toDate);
        $daily_summary = $this->dateChangeRequestDAL->getDailySummary($fromDate, $toDate);
        $agent_summary = $this->dateChangeRequestDAL->getAgentSummary($fromDate, $toDate);

        return [
            'success' => true,
            'date_range' => [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ],
            'metrics' => [
                'total_requests' => $total_requests,
                'success_count' => $success_count,
                'failure_count' => $failure_count,
                'in_progress_count' => $in_progress_count,
                'total_cost_given' => $total_cost_given,
                'total_cost_taken' => $total_cost_taken,
                'total_revenue' => $total_revenue,
                'success_rate' => $total_requests > 0 ? round(($success_count / $total_requests) * 100, 2) : 0
            ],
            'requests' => $requests,
            'monthly_summary' => $monthly_summary,
            'daily_summary' => $daily_summary,
            'agent_summary' => $agent_summary,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get daily summary
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Array of daily summary records
     */
    public function getDailySummary($fromDate = null, $toDate = null)
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

        return $this->dateChangeRequestDAL->getDailySummary($fromDate, $toDate);
    }

    /**
     * Get agent summary
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Array of agent summary records
     */
    public function getAgentSummary($fromDate = null, $toDate = null)
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

        return $this->dateChangeRequestDAL->getAgentSummary($fromDate, $toDate);
    }

    /**
     * Get distinct agents list
     * 
     * @return array Array of agent names
     */
    public function getDistinctAgents()
    {
        return $this->dateChangeRequestDAL->getDistinctAgents();
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

