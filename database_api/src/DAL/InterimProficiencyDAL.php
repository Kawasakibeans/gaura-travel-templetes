<?php
/**
 * Interim Proficiency Data Access Layer
 * Handles database operations for interim performance reports
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class InterimProficiencyDAL extends BaseDAL
{
    /**
     * Get agent performance matrix across intervals
     */
    public function getAgentPerformanceMatrix($intervals, $team = null)
    {
        $agentMatrix = [];

        foreach ($intervals as $label => [$from, $to]) {
            $whereParts = ["combined.team_name <> 'Others'"];
            $params = [$from, $to, $from, $to];

            if ($team) {
                $whereParts[] = "combined.team_name = ?";
                $params[] = $team;
            }

            $whereSQL = implode(' AND ', $whereParts);

            $query = "
            SELECT
                combined.sale_manager,
                combined.team_name,
                combined.agent_name,
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
                     ELSE 0 END AS AHT
            FROM (
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
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr 
                    AND c.status = 'active' 
                    AND c.role NOT IN ('TL','SM','Trainer')
                WHERE a.call_date BETWEEN ? AND ?
                
                UNION ALL
                
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
                    0 AS rec_duration
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

            $results = $this->query($query, $params);

            foreach ($results as $row) {
                $agentKey = $row['agent_name'];
                
                if (!isset($agentMatrix[$agentKey])) {
                    $agentMatrix[$agentKey] = [
                        'agent_name' => $row['agent_name'],
                        'team_name' => $row['team_name'],
                        'sale_manager' => $row['sale_manager'],
                        'tier' => $row['tier'],
                        'intervals' => []
                    ];
                }

                $agentMatrix[$agentKey]['intervals'][$label] = [
                    'pax' => (int)$row['pax'],
                    'fit' => (int)$row['fit'],
                    'pif' => (int)$row['pif'],
                    'gdeals' => (int)$row['gdeals'],
                    'gtib' => (int)$row['gtib'],
                    'conversion' => round((float)$row['conversion'], 4),
                    'fcs' => round((float)$row['fcs'], 4),
                    'aht' => round((float)$row['AHT'], 2)
                ];
            }
        }

        return array_values($agentMatrix);
    }

    /**
     * Get QA score matrix
     */
    public function getQAScoreMatrix($intervals, $team = null)
    {
        // Similar to performance matrix but focuses on QA scores
        $qaMatrix = [];

        foreach ($intervals as $label => [$from, $to]) {
            $whereParts = [];
            $params = [$from, $to];

            if ($team) {
                $whereParts[] = "c.team_name = ?";
                $params[] = $team;
            }

            $whereSQL = $whereParts ? ('AND ' . implode(' AND ', $whereParts)) : '';

            $query = "
            SELECT
                a.agent_name,
                c.team_name,
                c.tier,
                AVG(qa.qa_score) AS avg_qa_score,
                COUNT(qa.auto_id) AS qa_count
            FROM wpk4_backend_agent_codes c
            LEFT JOIN wpk4_backend_agent_inbound_call a 
                ON c.tsr = a.tsr
            LEFT JOIN wpk4_backend_agent_qa_scores qa 
                ON a.tsr = qa.tsr 
                AND qa.qa_date BETWEEN ? AND ?
            WHERE c.status = 'active' 
              AND c.role NOT IN ('TL','SM','Trainer')
              $whereSQL
            GROUP BY a.agent_name, c.team_name, c.tier
            ORDER BY a.agent_name";

            $results = $this->query($query, $params);

            foreach ($results as $row) {
                $agentKey = $row['agent_name'];
                
                if (!isset($qaMatrix[$agentKey])) {
                    $qaMatrix[$agentKey] = [
                        'agent_name' => $row['agent_name'],
                        'team_name' => $row['team_name'],
                        'tier' => $row['tier'],
                        'intervals' => []
                    ];
                }

                $qaMatrix[$agentKey]['intervals'][$label] = [
                    'avg_qa_score' => round((float)$row['avg_qa_score'], 2),
                    'qa_count' => (int)$row['qa_count']
                ];
            }
        }

        return array_values($qaMatrix);
    }

    /**
     * Get after sales matrix
     */
    public function getAfterSalesMatrix($intervals, $team = null)
    {
        // Similar structure for after sales metrics
        return $this->getAgentPerformanceMatrix($intervals, $team);
    }

    /**
     * Get performance remarks
     */
    public function getPerformanceRemarks($tsr = null)
    {
        if ($tsr) {
            $query = "SELECT tsr, date_range_start, remark, created_at, updated_at 
                      FROM wpk4_backend_interim_performance_remark 
                      WHERE tsr = ? 
                      ORDER BY date_range_start DESC";
            return $this->query($query, [$tsr]);
        }

        $query = "SELECT tsr, date_range_start, remark, created_at, updated_at 
                  FROM wpk4_backend_interim_performance_remark 
                  ORDER BY date_range_start DESC, tsr ASC";
        
        return $this->query($query);
    }

    /**
     * Create performance remark
     */
    public function createPerformanceRemark($data)
    {
        $query = "INSERT INTO wpk4_backend_interim_performance_remark 
                  (tsr, date_range_start, date_range_end, remark, created_at, updated_at)
                  VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $this->execute($query, [
            $data['tsr'], 
            $data['date_range_start'], 
            $data['date_range_end'],
            $data['remark']
        ]);
        return $this->lastInsertId();
    }

    /**
     * Update performance remark
     */
    public function updatePerformanceRemark($tsr, $dateRangeStart, $remark)
    {
        $query = "UPDATE wpk4_backend_interim_performance_remark 
                  SET remark = ?, updated_at = NOW() 
                  WHERE tsr = ? AND date_range_start = ?";
        
        return $this->execute($query, [$remark, $tsr, $dateRangeStart]);
    }

    /**
     * Delete performance remark
     */
    public function deletePerformanceRemark($tsr, $dateRangeStart)
    {
        $query = "DELETE FROM wpk4_backend_interim_performance_remark 
                  WHERE tsr = ? AND date_range_start = ?";
        
        return $this->execute($query, [$tsr, $dateRangeStart]);
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

