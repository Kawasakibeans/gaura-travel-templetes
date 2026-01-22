<?php
/**
 * Agent Data Access Layer
 * Handles all database operations for agent-related data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentDAL extends BaseDAL
{
    /**
     * Search agents by name
     */
    public function searchAgentsByName($term)
    {
        $query = "
            SELECT DISTINCT agent_name 
            FROM wpk4_backend_agent_codes 
            WHERE agent_name LIKE :term 
            ORDER BY agent_name ASC
        ";
        $results = $this->query($query, ['term' => '%' . $term . '%']);
        
        return array_map(function ($row) {
            return $row['agent_name'];
        }, $results);
    }

    /**
     * Get team performance data
     */
    public function getTeamPerformance($fromDate, $toDate, $team = 'ALL')
    {
        $query = "
            SELECT
                combined.team_name AS team_name,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pax)/SUM(combined.gtib_count) ELSE 0 END AS conversion,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.new_sale_made_count)/SUM(combined.gtib_count) ELSE 0 END AS fcs,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) ELSE 0 END AS AHT
            FROM (
                SELECT
                    a.agent_name,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr 
                    AND c.status = 'active'
                WHERE a.call_date BETWEEN :from_date1 AND :to_date1
                UNION ALL
                SELECT
                    a.agent_name,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr 
                    AND c.status = 'active'
                WHERE a.order_date BETWEEN :from_date2 AND :to_date2
            ) AS combined
            WHERE combined.team_name NOT IN ('Others', 'Sales Manager')
        ";
        
        $params = [
            'from_date1' => $fromDate,
            'to_date1' => $toDate,
            'from_date2' => $fromDate,
            'to_date2' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND combined.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY combined.team_name ORDER BY combined.team_name";
        
        return $this->query($query, $params);
    }

    /**
     * Get agent-level performance data
     */
    public function getAgentPerformance($fromDate, $toDate, $team = 'ALL')
    {
        $query = "
            SELECT
                combined.team_name AS team_name,
                combined.agent_name as agent_name,
                combined.role,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pax)/SUM(combined.gtib_count) ELSE 0 END AS conversion,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.new_sale_made_count)/SUM(combined.gtib_count) ELSE 0 END AS fcs,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) ELSE 0 END AS AHT
            FROM (
                SELECT
                    a.agent_name,
                    c.role,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr 
                    AND c.status = 'active'
                WHERE a.call_date BETWEEN :from_date1 AND :to_date1
                UNION ALL
                SELECT
                    a.agent_name,
                    c.role,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr 
                    AND c.status = 'active'
                WHERE a.order_date BETWEEN :from_date2 AND :to_date2
            ) AS combined
            WHERE combined.team_name <> 'Others'
        ";
        
        $params = [
            'from_date1' => $fromDate,
            'to_date1' => $toDate,
            'from_date2' => $fromDate,
            'to_date2' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND combined.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY combined.team_name, combined.agent_name, combined.role ORDER BY combined.team_name, combined.agent_name";
        
        return $this->query($query, $params);
    }

    /**
     * Get GTMD (total calls) by team
     */
    public function getGTMDByTeam($fromDate, $toDate, $team = 'ALL')
    {
        $query = "
            SELECT 
                ac.team_name,
                SUM(a.tot_calls) AS gtmd
            FROM wpk4_backend_agent_nobel_data_tsktsrday a
            LEFT JOIN wpk4_backend_agent_codes ac ON BINARY a.tsr = BINARY ac.tsr AND BINARY ac.status = 'active'
            WHERE a.call_date BETWEEN :from_date AND :to_date AND a.appl = 'GTMD'
        ";
        
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND BINARY ac.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY ac.team_name";
        
        return $this->query($query, $params);
    }

    /**
     * Get team trends (last 7 days)
     */
    public function getTeamTrends($fromDate, $toDate, $team = 'ALL')
    {
        $query = "
            SELECT 
                ac.team_name,
                DATE_FORMAT(ib.call_date, '%d-%b') as day,
                ib.call_date as days,
                SUM(ib.gtib_count) AS gtib,
                SUM(ab.pax) AS pax
            FROM wpk4_backend_agent_inbound_call ib
            LEFT JOIN wpk4_backend_agent_booking ab ON BINARY ib.tsr = BINARY ab.tsr AND ib.call_date = ab.order_date
            LEFT JOIN wpk4_backend_agent_codes ac ON BINARY ib.tsr = BINARY ac.tsr AND BINARY ac.status = 'active'
            WHERE ib.call_date BETWEEN :from_date AND :to_date AND ac.team_name IS NOT NULL
        ";
        
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND BINARY ac.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY ac.team_name, day, days ORDER BY ac.team_name, days";
        
        return $this->query($query, $params);
    }

    /**
     * Get latest booking
     */
    public function getLatestBooking($minDate = '2025-05-09')
    {
        $query = "
            SELECT 
                ac.agent_name,
                b.total_pax,
                DATE_FORMAT(b.order_date, '%H:%i') as booked_at
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_agent_codes ac ON BINARY b.agent_info = BINARY ac.sales_id
            WHERE b.order_date >= :min_date
            ORDER BY b.auto_id DESC
            LIMIT 1
        ";
        
        return $this->query($query, ['min_date' => $minDate]);
    }

    /**
     * Get team QA compliance (Garland)
     */
    public function getTeamQACompliance($fromDate, $toDate, $team = 'ALL')
    {
        // Try current database first, fallback to cross-database if needed
        $query = "
            SELECT 
                cs.team_name,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS compliance_percentage
            FROM wpk4_backend_harmony_audited_call_summary cs
            LEFT JOIN wpk4_backend_agent_codes ac ON BINARY cs.recording_tsr = BINARY ac.tsr
            WHERE cs.recording_date BETWEEN :from_date AND :to_date
        ";
        
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND BINARY cs.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY cs.team_name";
        
        return $this->query($query, $params);
    }

    /**
     * Get agent QA compliance (Garland)
     */
    public function getAgentQACompliance($fromDate, $toDate, $team = 'ALL')
    {
        // Try current database first, fallback to cross-database if needed
        $query = "
            SELECT 
                cs.agent_name,
                cs.team_name,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS compliance_percentage
            FROM wpk4_backend_harmony_audited_call_summary cs
            LEFT JOIN wpk4_backend_agent_codes ac ON BINARY cs.recording_tsr = BINARY ac.tsr
            WHERE cs.recording_date BETWEEN :from_date AND :to_date
        ";
        
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
        
        if ($team !== 'ALL' && !empty($team)) {
            $query .= " AND BINARY cs.team_name = :team";
            $params['team'] = $team;
        }
        
        $query .= " GROUP BY cs.agent_name, cs.team_name";
        
        return $this->query($query, $params);
    }
}

