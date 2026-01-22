<?php
/**
 * Proficiency Report Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ProficiencyReportDAL extends BaseDAL
{
    /**
     * Get agent proficiency data
     */
    public function getAgentProficiencyData($team, $fromDate, $toDate)
    {
        $whereParts = [];
        $params = [$fromDate, $toDate, $fromDate, $toDate];

        if ($team) {
            $whereParts[] = "combined.team_name = ?";
            $params[] = $team;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $query = "
        SELECT
            combined.agent_name,
            combined.team_name,
            combined.tier,
            SUM(combined.pax) AS pax,
            SUM(combined.gtib_count) AS gtib,
            CASE WHEN SUM(combined.gtib_count) > 0 
                 THEN SUM(combined.pax)/SUM(combined.gtib_count) 
                 ELSE 0 END AS conversion
        FROM (
            SELECT a.agent_name, c.team_name, c.tier, 0 AS pax, a.gtib_count
            FROM wpk4_backend_agent_inbound_call a
            LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr
            WHERE a.call_date BETWEEN ? AND ?
            UNION ALL
            SELECT a.agent_name, c.team_name, c.tier, a.pax, 0 AS gtib_count
            FROM wpk4_backend_agent_booking a
            LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr
            WHERE a.order_date BETWEEN ? AND ?
        ) AS combined
        $whereSQL
        GROUP BY combined.agent_name, combined.team_name, combined.tier";

        return $this->query($query, $params);
    }

    /**
     * Get active teams
     */
    public function getActiveTeams()
    {
        $query = "SELECT DISTINCT team_name 
                  FROM wpk4_backend_agent_codes 
                  WHERE status = 'active' 
                  ORDER BY team_name";
        
        $results = $this->query($query);
        return array_column($results, 'team_name');
    }
}

