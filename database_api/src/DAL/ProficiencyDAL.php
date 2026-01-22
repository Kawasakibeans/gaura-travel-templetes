<?php
/**
 * Proficiency Data Access Layer
 * Handles database operations for proficiency reports
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ProficiencyDAL extends BaseDAL
{
    /**
     * Get proficiency data by tier and team
     */
    public function getProficiencyData($tier, $team, $fromDate, $toDate)
    {
        $whereParts = ["combined.tier = ?"];
        $params = [$fromDate, $toDate, $fromDate, $toDate, $tier];

        if ($team !== 'ALL') {
            $whereParts[] = "combined.team_name = ?";
            $params[] = $team;
        }

        $whereParts[] = "combined.team_name <> 'Others'";
        $whereSQL = implode(' AND ', $whereParts);

        $query = "
        SELECT
            combined.sale_manager AS sale_manager,
            combined.team_name AS team_name,
            combined.agent_name as agent_name,
            combined.tier,
            SUM(combined.pax) AS pax,
            SUM(combined.fit) AS fit,
            SUM(combined.pif) AS pif,
            SUM(combined.gdeals) AS gdeals,
            SUM(combined.gtib_count) AS gtib,
            CASE WHEN SUM(combined.gtib_count) > 0 
                 THEN SUM(combined.pax)/SUM(combined.gtib_count) 
                 ELSE 0 END AS conversion,
            CASE WHEN SUM(combined.gtib_count) > 0 
                 THEN SUM(combined.new_sale_made_count)/SUM(combined.gtib_count) 
                 ELSE 0 END AS fcs,
            CASE WHEN SUM(combined.gtib_count) > 0 
                 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) 
                 ELSE 0 END AS AHT,
            MAX(combined.behavioural) AS behavioural
        FROM (
            -- Subquery for Inbound Call Data
            SELECT
                a.agent_name,
                c.tier,
                0 AS pax,
                0 AS fit,
                0 AS pif,
                0 AS gdeals,
                c.team_name,
                c.sale_manager,
                a.gtib_count,
                a.new_sale_made_count,
                a.non_sale_made_count,
                a.rec_duration,
                0 AS behavioural
            FROM wpk4_backend_agent_inbound_call a
            LEFT JOIN wpk4_backend_agent_codes c 
                ON a.tsr = c.tsr 
                AND c.status = 'active' 
                AND c.role NOT IN ('TL','SM','Trainer')
            WHERE a.call_date BETWEEN ? AND ?
            
            UNION ALL
            
            -- Subquery for Booking Data
            SELECT
                a.agent_name,
                c.tier,
                a.pax,
                a.fit,
                a.pif,
                a.gdeals,
                c.team_name,
                c.sale_manager,
                0 AS gtib_count,
                0 AS new_sale_made_count,
                0 AS non_sale_made_count,
                0 AS rec_duration,
                0 AS behavioural
            FROM wpk4_backend_agent_booking a
            LEFT JOIN wpk4_backend_agent_codes c 
                ON a.tsr = c.tsr 
                AND c.status = 'active' 
                AND c.role NOT IN ('TL','SM','Trainer')
            WHERE a.order_date BETWEEN ? AND ?
        ) AS combined
        WHERE $whereSQL
        GROUP BY combined.agent_name, combined.team_name, combined.sale_manager, combined.tier
        ORDER BY combined.agent_name";

        return $this->query($query, $params);
    }
}

