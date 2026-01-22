<?php
/**
 * Sales Report Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class SalesReportDAL extends BaseDAL
{
    /**
     * Get sales data
     */
    public function getSalesData($reportDate, $team, $groupBy)
    {
        $whereParts = ["DATE(ic.call_date) = ?"];
        $params = [$reportDate];

        if ($team) {
            $whereParts[] = "ic.team_name = ?";
            $params[] = $team;
        }

        $whereSQL = implode(' AND ', $whereParts);
        $groupByField = ($groupBy === 'agent') ? 'ic.agent_name' : 'ic.team_name';

        $query = "
        SELECT
            MAX(ic.team_name) AS team_name,
            MAX(ic.agent_name) AS agent_name,
            SUM(ic.gtib_count) AS gtib,
            SUM(b.gdeals) AS gdeals,
            SUM(b.fit) AS fit,
            SUM(b.pif) AS pif,
            SUM(b.pax) AS pax,
            SUM(ic.new_sale_made_count) AS sale_made_count,
            SUM(ic.non_sale_made_count) AS non_sale_made_count,
            CASE WHEN SUM(ic.gtib_count) > 0 
                 THEN AVG(ic.rec_duration/ic.gtib_count) 
                 ELSE 0 END AS aht
        FROM wpk4_backend_agent_inbound_call AS ic
        LEFT JOIN wpk4_backend_agent_booking AS b 
            ON ic.call_date = b.order_date 
            AND ic.tsr = b.tsr
        WHERE $whereSQL
        GROUP BY $groupByField
        ORDER BY pax DESC";

        return $this->query($query, $params);
    }

    /**
     * Get distinct team names
     */
    public function getDistinctTeamNames()
    {
        $query = "SELECT DISTINCT team_name 
                  FROM wpk4_backend_agent_inbound_call 
                  WHERE team_name IS NOT NULL AND team_name != '' 
                  ORDER BY team_name";
        
        $results = $this->query($query);
        return array_column($results, 'team_name');
    }

    /**
     * Get agents TSR mapping
     */
    public function getAgentsTSRMapping()
    {
        $query = "SELECT DISTINCT tsr, agent_name 
                  FROM wpk4_backend_agent_codes 
                  WHERE agent_name != '' AND tsr != ''";
        
        $results = $this->query($query);
        
        $mapping = [];
        foreach ($results as $row) {
            $mapping[$row['agent_name']] = $row['tsr'];
        }
        
        return $mapping;
    }

    /**
     * Get top performers
     */
    public function getTopPerformers($fromDate, $toDate, $limit)
    {
        $query = "
        SELECT
            ic.agent_name,
            ic.team_name,
            SUM(b.pax) AS total_pax,
            SUM(ic.gtib_count) AS total_gtib,
            CASE WHEN SUM(ic.gtib_count) > 0 
                 THEN SUM(b.pax)/SUM(ic.gtib_count) 
                 ELSE 0 END AS conversion_rate
        FROM wpk4_backend_agent_inbound_call ic
        LEFT JOIN wpk4_backend_agent_booking b 
            ON ic.tsr = b.tsr 
            AND DATE(ic.call_date) = DATE(b.order_date)
        WHERE ic.call_date BETWEEN ? AND ?
        GROUP BY ic.agent_name, ic.team_name
        ORDER BY total_pax DESC, conversion_rate DESC
        LIMIT ?";

        return $this->query($query, [$fromDate, $toDate, $limit]);
    }

    /**
     * Get bottom performers
     */
    public function getBottomPerformers($fromDate, $toDate, $limit)
    {
        $query = "
        SELECT
            ic.agent_name,
            ic.team_name,
            SUM(b.pax) AS total_pax,
            SUM(ic.gtib_count) AS total_gtib,
            CASE WHEN SUM(ic.gtib_count) > 0 
                 THEN SUM(b.pax)/SUM(ic.gtib_count) 
                 ELSE 0 END AS conversion_rate
        FROM wpk4_backend_agent_inbound_call ic
        LEFT JOIN wpk4_backend_agent_booking b 
            ON ic.tsr = b.tsr 
            AND DATE(ic.call_date) = DATE(b.order_date)
        WHERE ic.call_date BETWEEN ? AND ?
        GROUP BY ic.agent_name, ic.team_name
        ORDER BY total_pax ASC, conversion_rate ASC
        LIMIT ?";

        return $this->query($query, [$fromDate, $toDate, $limit]);
    }

    /**
     * Get detailed sales data for export
     */
    public function getDetailedSalesData($fromDate, $toDate, $team)
    {
        $whereParts = ["ic.call_date BETWEEN ? AND ?"];
        $params = [$fromDate, $toDate];

        if ($team) {
            $whereParts[] = "ic.team_name = ?";
            $params[] = $team;
        }

        $whereSQL = implode(' AND ', $whereParts);

        $query = "
        SELECT
            ic.call_date,
            ic.team_name,
            ic.agent_name,
            ic.gtib_count,
            b.gdeals,
            b.fit,
            b.pif,
            b.pax,
            ic.new_sale_made_count,
            ic.non_sale_made_count,
            ic.rec_duration
        FROM wpk4_backend_agent_inbound_call ic
        LEFT JOIN wpk4_backend_agent_booking b 
            ON ic.call_date = b.order_date 
            AND ic.tsr = b.tsr
        WHERE $whereSQL
        ORDER BY ic.call_date DESC, ic.team_name, ic.agent_name";

        return $this->query($query, $params);
    }
}

