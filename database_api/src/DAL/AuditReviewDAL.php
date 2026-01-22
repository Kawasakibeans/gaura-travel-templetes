<?php
/**
 * Audit Review Data Access Layer
 * Handles all database operations for audit review metrics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AuditReviewDAL extends BaseDAL
{
    /**
     * Fetch date summary for a slice of the month (by day-of-month) within a given overall range
     * 
     * @param int $startDay Start day of month (1-31)
     * @param int $endDay End day of month (1-31)
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string $agentName Agent name filter (empty string for all agents)
     * @return array Array of date summary records
     */
    public function getDateSummary($startDay, $endDay, $fromDate, $toDate, $agentName = '')
    {
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'start_day' => $startDay,
            'end_day' => $endDay
        ];

        $sql = "
            SELECT DATE(`date`) AS report_date,
                   SUM(fit_audit) AS fit_audit,
                   SUM(gdeal_audit) AS gdeal_audit,
                   SUM(ticket_audited) AS ticket_audited
            FROM wpk4_agent_after_sale_productivity_report
            WHERE `date` >= :from_date 
              AND `date` < DATE_ADD(:to_date, INTERVAL 1 DAY)
              AND DAY(`date`) BETWEEN :start_day AND :end_day
              AND agent_name <> 'ABDN'
        ";

        if ($agentName !== '') {
            $sql .= " AND agent_name = :agent_name";
            $params['agent_name'] = $agentName;
        }

        $sql .= " GROUP BY report_date ORDER BY report_date ASC";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'report_date' => $row['report_date'],
                'fit_audit' => (int)$row['fit_audit'],
                'gdeal_audit' => (int)$row['gdeal_audit'],
                'ticket_audited' => (int)$row['ticket_audited']
            ];
        }
        
        return $data;
    }

    /**
     * Get agent-wise details for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $agentName Agent name filter (empty string for all agents)
     * @return array Array of agent records for the date
     */
    public function getAgentDetailsByDate($date, $agentName = '')
    {
        $params = [
            'date' => $date
        ];

        $sql = "
            SELECT agent_name,
                   SUM(fit_audit) AS fit_audit,
                   SUM(gdeal_audit) AS gdeal_audit,
                   SUM(ticket_audited) AS ticket_audited
            FROM wpk4_agent_after_sale_productivity_report
            WHERE DATE(`date`) = :date
              AND agent_name <> 'ABDN' 
              AND ticket_audited > 0
        ";

        if ($agentName !== '') {
            $sql .= " AND agent_name = :agent_name";
            $params['agent_name'] = $agentName;
        }

        $sql .= " GROUP BY agent_name ORDER BY agent_name ASC";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'agent_name' => $row['agent_name'],
                'fit_audit' => (int)$row['fit_audit'],
                'gdeal_audit' => (int)$row['gdeal_audit'],
                'ticket_audited' => (int)$row['ticket_audited']
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

