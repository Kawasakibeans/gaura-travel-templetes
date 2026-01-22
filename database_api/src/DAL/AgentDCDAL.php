<?php
/**
 * Agent Date Change (DC) Data Access Layer
 * Handles all database operations for date change metrics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentDCDAL extends BaseDAL
{
    /**
     * Get date change metrics grouped by date for a given date range
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Array of date change records grouped by date
     */
    public function getMetricsByDate($startDate, $endDate, $agent = '')
    {
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        $sql = "
            SELECT 
                DATE(`date`) AS day,
                SUM(dc_request) AS dc_request,
                SUM(dc_case_success) AS dc_case_success,
                SUM(dc_case_fail) AS dc_case_fail,
                SUM(dc_case_pending) AS dc_case_pending,
                SUM(total_revenue) AS total_revenue
            FROM wpk4_agent_after_sale_productivity_report
            WHERE `date` >= :start_date 
              AND `date` < DATE_ADD(:end_date, INTERVAL 1 DAY)
              AND agent_name <> 'ABDN'
        ";

        if ($agent !== '') {
            $sql .= " AND agent_name = :agent_name";
            $params['agent_name'] = $agent;
        }

        $sql .= " GROUP BY day ORDER BY day ASC";

        $results = $this->query($sql, $params);
        
        // Process results to calculate success rate
        $data = [];
        foreach ($results as $row) {
            $dc_request = (int)$row['dc_request'];
            $success_rate = ($dc_request > 0) ? round(($row['dc_case_success'] / $dc_request) * 100, 2) : 0;
            
            $data[] = [
                'day' => $row['day'],
                'dc_request' => $dc_request,
                'dc_case_success' => (int)$row['dc_case_success'],
                'dc_case_fail' => (int)$row['dc_case_fail'],
                'dc_case_pending' => (int)$row['dc_case_pending'],
                'success_rate' => $success_rate,
                'total_revenue' => (float)$row['total_revenue']
            ];
        }
        
        return $data;
    }

    /**
     * Get agent-wise details for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $agent Agent name filter (empty string for all agents)
     * @return array Array of agent records for the date
     */
    public function getAgentDetailsByDate($date, $agent = '')
    {
        $params = [
            'date' => $date
        ];

        $sql = "
            SELECT 
                agent_name,
                dc_request,
                dc_case_success,
                dc_case_fail,
                dc_case_pending,
                total_revenue
            FROM wpk4_agent_after_sale_productivity_report
            WHERE DATE(`date`) = :date 
              AND dc_request > 0
              AND agent_name <> 'ABDN'
        ";

        if ($agent !== '') {
            $sql .= " AND agent_name = :agent_name";
            $params['agent_name'] = $agent;
        }

        $sql .= " ORDER BY agent_name ASC";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'agent_name' => $row['agent_name'],
                'dc_request' => (int)$row['dc_request'],
                'dc_case_success' => (int)$row['dc_case_success'],
                'dc_case_fail' => (int)$row['dc_case_fail'],
                'dc_case_pending' => (int)$row['dc_case_pending'],
                'total_revenue' => (float)$row['total_revenue']
            ];
        }
        
        return $data;
    }

    /**
     * Get distinct agents for dropdown
     * 
     * @return array Array of agent names
     */
    public function getDistinctAgents()
    {
        $query = "
            SELECT DISTINCT agent_name
            FROM wpk4_backend_agent_codes
            WHERE location = 'BOM' AND status = 'active'
            ORDER BY agent_name ASC
        ";
        
        $results = $this->query($query);
        $agents = [];
        foreach ($results as $row) {
            $agents[] = $row['agent_name'];
        }
        
        return $agents;
    }
}

