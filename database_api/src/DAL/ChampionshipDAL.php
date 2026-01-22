<?php
/**
 * Championship Data Access Layer
 * Handles database operations for championship comments
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ChampionshipDAL extends BaseDAL
{
    /**
     * Get comments for a date range
     */
    public function getComments(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT c.id, c.user_id, c.user_display_name, c.message, 
                   c.parent_id, c.created_at
            FROM wpk4_backend_supercar_comments c
            WHERE c.created_at BETWEEN :start AND :end
            ORDER BY c.created_at DESC
        ";
        
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';
        
        return $this->query($sql, [
            ':start' => $start,
            ':end' => $end
        ]);
    }

    /**
     * Insert a new comment
     */
    public function insertComment(int $userId, string $displayName, string $message, int $parentId = 0): int
    {
        $sql = "
            INSERT INTO wpk4_backend_supercar_comments 
            (user_id, user_display_name, message, parent_id, created_at) 
            VALUES (:user_id, :display_name, :message, :parent_id, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':display_name' => $displayName,
            ':message' => $message,
            ':parent_id' => $parentId
        ]);
        
        return $this->lastInsertId();
    }
    public function getTeamMetrics(string $metric, string $startDate, string $endDate, string $subMetric = 'all'): array
    {
        $sql = "
            SELECT
                combined.team_name AS team_name,
                combined.department,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif)/SUM(combined.gtib_count) ELSE 0 END AS conversion,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif_sale_made_count)/SUM(combined.gtib_count) ELSE 0 END AS fcs,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) ELSE 0 END AS aht
            FROM (
                -- Subquery for Inbound Call Data
                SELECT
                    a.agent_name,
                    c.department,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.pif_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr
                WHERE a.call_date BETWEEN :start_date AND :end_date
                UNION ALL
                -- Subquery for Booking Data
                SELECT
                    a.agent_name,
                    c.department,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS pif_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr
                WHERE a.order_date BETWEEN :start_date2 AND :end_date2
            ) AS combined
            WHERE combined.department IN ('Sales','BOM-Sales') 
                AND combined.team_name <> 'Sales Manager' 
                AND combined.team_name <> 'Trainer'
            GROUP BY combined.team_name, combined.department
            ORDER BY combined.team_name
        ";
    
        $results = $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':start_date2' => $startDate,
            ':end_date2' => $endDate
        ]);
    
        // Format results based on metric parameter
        $formatted = [];
        foreach ($results as $row) {
            $teamData = [
                'team_name' => $row['team_name'],
                'department' => $row['department'],
                'gtib' => (int)$row['gtib'],
                'pax' => (int)$row['pax'],
                'fit' => (int)$row['fit'],
                'pif' => (int)$row['pif'],
                'gdeals' => (int)$row['gdeals'],
                'conversion' => round((float)$row['conversion'], 4),
                'fcs' => round((float)$row['fcs'], 4),
                'aht' => round((float)$row['aht'], 0)
            ];
    
            // Filter by metric if specified
            if ($subMetric !== 'all') {
                if ($subMetric === 'conversion') {
                    $teamData = ['team_name' => $teamData['team_name'], 'conversion' => $teamData['conversion']];
                } elseif ($subMetric === 'fcs') {
                    $teamData = ['team_name' => $teamData['team_name'], 'fcs' => $teamData['fcs']];
                } elseif ($subMetric === 'aht') {
                    $teamData = ['team_name' => $teamData['team_name'], 'aht' => $teamData['aht']];
                }
            }
    
            $formatted[] = $teamData;
        }
    
        return $formatted;
    }
    
    /**
     * Get active teams for racing championship
     */
    public function getActiveTeams(): array
    {
        $sql = "
            SELECT DISTINCT team_name
            FROM wpk4_backend_agent_codes
            WHERE status = 'active'
                AND team_name IS NOT NULL
                AND team_name <> ''
                AND team_name <> 'Sales Manager'
                AND team_name <> 'Trainer'
                AND department IN ('Sales', 'BOM-Sales')
            ORDER BY team_name
        ";
    
        $results = $this->query($sql, []);
        return array_column($results, 'team_name');
    }
    
    /**
     * Get agent performance by team
     */
    public function getAgentPerformanceByTeam(string $teamName, string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                combined.team_name AS team_name,
                combined.agent_name as agent_name,
                combined.role,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif)/SUM(combined.gtib_count) ELSE 0 END AS conversion,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif_sale_made_count)/SUM(combined.gtib_count) ELSE 0 END AS fcs,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) ELSE 0 END AS aht
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
                    a.pif_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :start_date AND :end_date
                    AND a.team_name = :team_name
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
                    0 AS pif_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.order_date BETWEEN :start_date2 AND :end_date2
                    AND a.team_name = :team_name2
            ) AS combined
            WHERE combined.team_name <> 'Others'
            GROUP BY combined.team_name, combined.agent_name, combined.role
            ORDER BY combined.team_name, combined.agent_name
        ";
    
        $results = $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':start_date2' => $startDate,
            ':end_date2' => $endDate,
            ':team_name' => $teamName,
            ':team_name2' => $teamName
        ]);
    
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'team_name' => $row['team_name'],
                'agent_name' => $row['agent_name'],
                'role' => $row['role'],
                'gtib' => (int)$row['gtib'],
                'pax' => (int)$row['pax'],
                'fit' => (int)$row['fit'],
                'pif' => (int)$row['pif'],
                'gdeals' => (int)$row['gdeals'],
                'conversion' => round((float)$row['conversion'], 4),
                'fcs' => round((float)$row['fcs'], 4),
                'aht' => round((float)$row['aht'], 0)
            ];
        }
    
        return $formatted;
    }
}

