<?php
/**
 * Agent Date Change (DC) Service - Business Logic Layer
 * Handles business logic for date change metrics
 */

namespace App\Services;

use App\DAL\AgentDCDAL;
use Exception;

class AgentDCService
{
    private $agentDCDAL;

    public function __construct()
    {
        $this->agentDCDAL = new AgentDCDAL();
    }

    /**
     * Get date change metrics grouped by date ranges
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Formatted data with grouped metrics
     */
    public function getDateChangeMetrics($startDate = null, $endDate = null, $agent = '')
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

        // Build date segments
        $startY = date('Y', strtotime($startDate));
        $startM = date('m', strtotime($startDate));
        $endY = date('Y', strtotime($endDate));
        $endM = date('m', strtotime($endDate));
        $sameMonth = ($startY . $startM) === ($endY . $endM);

        $segments = [
            ['label' => 'Total', 'start' => $startDate, 'end' => $endDate]
        ];

        if ($sameMonth) {
            $month = date('Y-m', strtotime($startDate));
            $lastDay = date('t', strtotime($startDate));

            // Clamp slices to selection
            $s1s = max($startDate, "$month-01");
            $s1e = min($endDate, "$month-10");
            $s2s = max($startDate, "$month-11");
            $s2e = min($endDate, "$month-20");
            $s3s = max($startDate, "$month-21");
            $s3e = min($endDate, "$month-$lastDay");

            $segments = [
                ['label' => '1 - 10', 'start' => $s1s, 'end' => $s1e],
                ['label' => '11 - 20', 'start' => $s2s, 'end' => $s2e],
                ['label' => "21 - $lastDay", 'start' => $s3s, 'end' => $s3e],
                ['label' => 'Total', 'start' => $startDate, 'end' => $endDate]
            ];
        }

        // Fetch data for each segment
        $segmentData = [];
        foreach ($segments as $segment) {
            $data = $this->agentDCDAL->getMetricsByDate($segment['start'], $segment['end'], $agent);
            
            // Calculate totals for the segment
            $total_dc_request = 0;
            $total_dc_case_success = 0;
            $total_dc_case_fail = 0;
            $total_dc_case_pending = 0;
            $total_revenue = 0;
            
            foreach ($data as $row) {
                $total_dc_request += $row['dc_request'];
                $total_dc_case_success += $row['dc_case_success'];
                $total_dc_case_fail += $row['dc_case_fail'];
                $total_dc_case_pending += $row['dc_case_pending'];
                $total_revenue += $row['total_revenue'];
            }
            
            $total_success_rate = ($total_dc_request > 0) ? round(($total_dc_case_success / $total_dc_request) * 100, 2) : 0;
            
            $segmentData[] = [
                'label' => $segment['label'],
                'start_date' => $segment['start'],
                'end_date' => $segment['end'],
                'data' => $data,
                'totals' => [
                    'dc_request' => $total_dc_request,
                    'dc_case_success' => $total_dc_case_success,
                    'dc_case_fail' => $total_dc_case_fail,
                    'dc_case_pending' => $total_dc_case_pending,
                    'success_rate' => $total_success_rate,
                    'total_revenue' => $total_revenue
                ]
            ];
        }

        // Get distinct agents
        $agents = $this->agentDCDAL->getDistinctAgents();

        return [
            'success' => true,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'agent' => $agent ?: 'all',
            'segments' => $segmentData,
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

        return $this->agentDCDAL->getAgentDetailsByDate($date, $agent);
    }

    /**
     * Get distinct agents list
     * 
     * @return array Array of agent names
     */
    public function getDistinctAgents()
    {
        return $this->agentDCDAL->getDistinctAgents();
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

