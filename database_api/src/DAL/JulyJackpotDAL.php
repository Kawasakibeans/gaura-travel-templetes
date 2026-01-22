<?php
/**
 * July Jackpot Data Access Layer
 * Handles all database operations for July Jackpot incentive tracking
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class JulyJackpotDAL extends BaseDAL
{
    /**
     * Get monthly agent-wise data for July Jackpot incentive
     * Returns agents who meet eligibility criteria for the month
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Agent performance data
     */
    public function getMonthlyAgentData($startDate, $endDate)
    {
        $query = "
            SELECT
                combined.agent_name,
                COUNT(DISTINCT combined.date) AS days_worked,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                SUM(combined.abandoned) AS abandoned,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.pif) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS conversion,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS fcs,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.pif) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS pif_percent,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.rec_duration) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS AHT,
                COALESCE(garland_scores.garland, 0) AS garland_score
            FROM (
                -- Inbound Call Data
                SELECT
                    a.agent_name,
                    c.role,
                    a.team_name,
                    a.call_date AS date,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.gtib_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration,
                    0 AS abandoned
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN (
                    SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
                ) c 
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN ? AND ?
                UNION ALL
                SELECT
                    a.agent_name,
                    c.role,
                    a.team_name,
                    a.order_date AS date,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration,
                    0 AS abandoned
                FROM wpk4_backend_agent_booking a
                LEFT JOIN (
                    SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
                ) c 
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.order_date BETWEEN ? AND ?
                UNION ALL
                SELECT
                    '' AS agent_name,
                    '' AS role,
                    'Abandoned' AS team_name,
                    w.call_date AS date,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration,
                    1 AS abandoned
                FROM wpk4_backend_agent_nobel_data_inboundcall_rec w
                WHERE w.tsr = ''
                AND w.call_date BETWEEN ? AND ?
            ) AS combined
            LEFT JOIN (
                SELECT
                    cs.agent_name,
                    cs.team_name,
                    ROUND(SUM(cs.compliant_count) / SUM(cs.audited_call) * 100, 0) AS garland
                FROM wpk4_backend_harmony_audited_call_summary cs
                LEFT JOIN wpk4_backend_agent_codes ac 
                    ON cs.recording_tsr = ac.tsr
                WHERE ac.status = 'active' 
                  AND ac.department = 'Sales'
                  AND cs.recording_date BETWEEN ? AND ?
                GROUP BY cs.agent_name, cs.team_name
            ) AS garland_scores 
                ON combined.agent_name = garland_scores.agent_name
                AND combined.team_name = garland_scores.team_name
            WHERE combined.team_name <> 'Others'
            GROUP BY combined.agent_name, garland_scores.garland
            HAVING 
                COUNT(DISTINCT combined.date) >= 15
                AND COALESCE(garland_scores.garland, 0) >= 75
                AND SUM(combined.pax) > 50 
                AND (SUM(combined.pif) / NULLIF(SUM(combined.gtib_count), 0) >= 0.4)
                AND (SUM(combined.new_sale_made_count) / NULLIF(SUM(combined.gtib_count), 0) >= 0.22)
                AND (SUM(combined.rec_duration) / NULLIF(SUM(combined.gtib_count), 0)) <= 1440
            ORDER BY SUM(combined.pax) DESC
        ";

        $params = [
            $startDate, $endDate,  // Inbound call date range
            $startDate, $endDate,  // Booking date range
            $startDate, $endDate,  // Abandoned call date range
            $startDate, $endDate   // Garland score date range
        ];

        return $this->query($query, $params);
    }

    /**
     * Get daily performance data for July Jackpot incentive
     * Returns aggregated daily metrics for the date range
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Daily performance data
     */
    public function getDailyPerformanceData($startDate, $endDate)
    {
        $query = "
            SELECT
                combined.date,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                SUM(combined.abandoned) AS abandoned,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.pax) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS conversion,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS fcs,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.pif) / SUM(combined.gtib_count) * 100, 2) 
                    ELSE 0 
                END AS pif_percent,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 
                    THEN ROUND(SUM(combined.rec_duration) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS AHT
            FROM (
                -- Inbound Call Data
                SELECT
                    a.agent_name,
                    c.role,
                    a.team_name,
                    a.call_date AS date,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.gtib_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration,
                    0 AS abandoned
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN (
                    SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
                ) c 
                    ON a.tsr = c.tsr AND c.status = 'active'

                UNION ALL

                -- Booking Data
                SELECT
                    a.agent_name,
                    c.role,
                    a.team_name,
                    a.order_date AS date,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration,
                    0 AS abandoned
                FROM wpk4_backend_agent_booking a
                LEFT JOIN (
                    SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
                ) c 
                    ON a.tsr = c.tsr AND c.status = 'active'

                UNION ALL

                -- Abandoned Call Data (no TSR)
                SELECT
                    '' AS agent_name,
                    '' AS role,
                    'Abandoned' AS team_name,
                    w.call_date AS date,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration,
                    1 AS abandoned
                FROM wpk4_backend_agent_nobel_data_inboundcall_rec w
                WHERE w.tsr = ''
            ) AS combined
            WHERE combined.team_name <> 'Others'
            AND combined.date BETWEEN ? AND ?
            GROUP BY combined.date
            ORDER BY combined.date ASC
        ";

        return $this->query($query, [$startDate, $endDate]);
    }
}

