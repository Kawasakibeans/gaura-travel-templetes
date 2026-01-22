<?php
/**
 * Agent Incentive Data Access Layer
 * Handles database operations for agent incentive calculations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentIncentiveDAL extends BaseDAL
{
    /**
     * Get all incentive periods
     */
    public function getPeriods(): array
    {
        $sql = "SELECT DISTINCT period FROM wpk4_backend_incentive_criteria ORDER BY period DESC";
        $results = $this->query($sql);
        return array_column($results, 'period');
    }

    /**
     * Get incentive criteria for a period
     */
    public function getIncentiveCriteria(string $period): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_incentive_criteria 
            WHERE period = :period
            LIMIT 1
        ";

        return $this->queryOne($sql, [':period' => $period]);
    }

    /**
     * Get all incentive criteria rows for a period (for parsing)
     */
    public function getAllIncentiveCriteria(string $period): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_incentive_criteria 
            WHERE period = :period 
            ORDER BY type, priority, auto_id
        ";

        return $this->query($sql, [':period' => $period]);
    }

    /**
     * Get agent list with managers
     */
    public function getAgents(?string $managerFilter = null): array
    {
        $sql = "
            SELECT DISTINCT agent_name, sale_manager 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active' 
                AND agent_name IS NOT NULL 
                AND team_name NOT IN ('Sales Manager')
        ";

        $params = [];
        if ($managerFilter) {
            $sql .= " AND sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " ORDER BY agent_name";

        return $this->query($sql, $params);
    }

    /**
     * Get unique managers
     */
    public function getManagers(): array
    {
        $sql = "
            SELECT DISTINCT sale_manager 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active' 
                AND team_name NOT IN ('Sales Manager','Others')
            ORDER BY sale_manager
        ";

        $results = $this->query($sql);
        return array_column($results, 'sale_manager');
    }

    /**
     * Get agent performance data (GTIB, FCS, AHT, Conversion, PIF)
     */
    public function getAgentPerformance(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT
                combined.team_name AS team_name,
                combined.agent_name AS agent_name,
                combined.sale_manager AS sale_manager,
                SUM(combined.pif) AS pif,
                SUM(combined.gtib_count) AS gtib,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif) / SUM(combined.gtib_count), 2)
                    ELSE 0 
                END AS conversion,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 2)
                    ELSE 0 
                END AS fcs,
                CASE 
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.rec_duration) / SUM(combined.gtib_count), 2)
                    ELSE 0 
                END AS aht
            FROM (
                SELECT
                    a.agent_name,
                    c.role,
                    c.sale_manager,
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
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND a.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND c.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= "
                UNION ALL
                SELECT
                    a.agent_name,
                    c.role,
                    c.sale_manager,
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
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.order_date BETWEEN :start2 AND :end2
        ";

        $params[':start2'] = $startDate;
        $params[':end2'] = $endDate;

        if ($agentFilter) {
            $sql .= " AND a.agent_name = :agent2";
            $params[':agent2'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND c.sale_manager = :manager2";
            $params[':manager2'] = $managerFilter;
        }

        $sql .= "
            ) AS combined 
            GROUP BY combined.team_name, combined.agent_name, combined.sale_manager
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get daily breakdown with QA, shift time, noble login time
     */
    public function getDailyBreakdown(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT
                COALESCE(data.call_date, data.order_date) AS call_date,
                ac.team_name,
                ac.agent_name,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100,0) as qa,
                SUM(data.pif) AS pif,
                SUM(data.gtib_count) AS gtib,
                SUM(data.new_sale_made_count) as new_sale_made_count,
                ROUND(IFNULL(SUM(data.pif) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS conversion,
                ROUND(IFNULL(SUM(data.new_sale_made_count) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS fcs,
                ROUND(IFNULL(SUM(data.rec_duration) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS aht,
                ac.sale_manager,
                MAX(s.shift_time) AS shift_time,
                MAX(nl.noble_login_time) AS noble_login_time,    
                CASE 
                    WHEN MAX(s.call_date) BETWEEN '2025-05-01' AND '2025-10-04' 
                        AND CAST(MAX(nl.noble_login_time) AS FLOAT) - (43000 + CAST(MAX(s.shift_time) AS FLOAT)) <= 0 THEN 'On Time'
                    WHEN MAX(s.call_date) BETWEEN '2025-10-05' AND '2026-04-30' 
                        AND CAST(MAX(nl.noble_login_time) AS FLOAT) - (53000 + CAST(MAX(s.shift_time) AS FLOAT)) <= 0 THEN 'On Time'
                    ELSE 'Late'
                END AS on_time
            FROM (
                SELECT
                    ic.call_date,
                    NULL AS order_date,
                    ic.tsr,
                    ic.agent_name,
                    0 AS pif,
                    ic.gtib_count,
                    ic.new_sale_made_count,
                    ic.rec_duration
                FROM wpk4_backend_agent_inbound_call ic
                WHERE ic.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ic.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }

        $sql .= "
                UNION ALL
                SELECT
                    NULL AS call_date,
                    b.order_date,
                    b.tsr,
                    b.agent_name,
                    b.pif,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking b
                WHERE b.order_date BETWEEN :start2 AND :end2
        ";

        $params[':start2'] = $startDate;
        $params[':end2'] = $endDate;

        if ($agentFilter) {
            $sql .= " AND b.agent_name = :agent2";
            $params[':agent2'] = $agentFilter;
        }

        $sql .= "
            ) AS data
            JOIN wpk4_backend_agent_codes ac 
                ON data.tsr = ac.tsr 
                AND ac.status = 'active'
        ";

        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= "
            LEFT JOIN (
                SELECT 
                    ai.call_date, 
                    ai.agent_name, 
                    MAX(ai.shift_time) AS shift_time
                FROM wpk4_backend_agent_inbound_call ai
                WHERE ai.call_date BETWEEN :start3 AND :end3
                GROUP BY ai.call_date, ai.agent_name
            ) s 
                ON s.call_date = data.call_date AND s.agent_name = data.agent_name
            LEFT JOIN wpk4_backend_harmony_audited_call_summary cs 
                ON cs.recording_tsr = data.tsr AND cs.recording_date = data.call_date    
            LEFT JOIN (
                SELECT
                    ac.agent_name,
                    nd.call_date,
                    CASE 
                        WHEN MIN(CAST(logon_time AS FLOAT)) < 1000 THEN MAX(CAST(logon_time AS FLOAT))
                        ELSE MIN(CAST(logon_time AS FLOAT))
                    END AS noble_login_time
                FROM wpk4_backend_agent_nobel_data_tsktsrday nd
                JOIN wpk4_backend_agent_codes ac 
                    ON nd.tsr = ac.tsr
                WHERE nd.call_date BETWEEN :start4 AND :end4
                GROUP BY ac.agent_name, nd.call_date
            ) nl 
                ON nl.agent_name = data.agent_name AND nl.call_date = data.call_date
            GROUP BY call_date, ac.team_name, ac.agent_name, ac.sale_manager
            ORDER BY call_date ASC
        ";

        $params[':start3'] = $startDate;
        $params[':end3'] = $endDate;
        $params[':start4'] = $startDate;
        $params[':end4'] = $endDate;

        return $this->query($sql, $params);
    }

    /**
     * Get noble login time
     */
    public function getNobleLoginTime(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                ac.agent_name, 
                SUM(a.time_connect + a.time_waiting + a.time_deassigned + a.time_acw + a.time_paused) AS noble_login_time
            FROM wpk4_backend_agent_nobel_data_tsktsrday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr
            WHERE a.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ac.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ac.agent_name";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = (int)$row['noble_login_time'];
        }
        
        return $map;
    }

    /**
     * Get noble login deduction
     */
    public function getNobleLoginDeduction(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                ac.agent_name, 
                (CASE 
                    WHEN SUM(a.time_connect + a.time_waiting + a.time_deassigned + a.time_acw + a.time_paused) < 216000 
                    THEN 0.5 
                    ELSE 0 
                END) AS noble_login_deduction
            FROM wpk4_backend_agent_nobel_data_tsktsrday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr
            WHERE a.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ac.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ac.agent_name";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = (float)$row['noble_login_deduction'];
        }
        
        return $map;
    }

    /**
     * Get GTBK pause time
     */
    public function getGTBK(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                ac.agent_name, 
                SUM(a.pause_time) AS gtbk
            FROM wpk4_backend_agent_nobel_data_tskpauday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr
            WHERE a.call_date BETWEEN :start AND :end AND a.pause_code = 'GTBK'
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ac.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ac.agent_name";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = (int)$row['gtbk'];
        }
        
        return $map;
    }

    /**
     * Get GTBK deduction
     */
    public function getGTBKDeduction(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                ac.agent_name, 
                (CASE 
                    WHEN SUM(a.pause_time) > 32400 
                    THEN 0.5 
                    ELSE 0 
                END) as gtbk_deduction
            FROM wpk4_backend_agent_nobel_data_tskpauday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr
            WHERE a.call_date BETWEEN :start AND :end AND a.pause_code = 'GTBK'
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ac.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ac.agent_name";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = (float)$row['gtbk_deduction'];
        }
        
        return $map;
    }

    /**
     * Get QA compliance
     */
    public function getQACompliance(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                cs.recording_tsr,
                cs.agent_name,
                cs.team_name,
                ac.sale_manager,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS compliance_percentage
            FROM gaurat_gauratravel.wpk4_backend_harmony_audited_call_summary cs
            LEFT JOIN wpk4_backend_agent_codes ac ON cs.recording_tsr = ac.tsr
            WHERE cs.recording_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND cs.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY cs.recording_tsr, cs.agent_name, cs.team_name, ac.sale_manager";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = $row['compliance_percentage'];
        }
        
        return $map;
    }

    /**
     * Get zero-pax days and deductions
     */
    public function getZeroPaxDeductions(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT 
                ic.agent_name,
                ac.sale_manager,
                (COUNT(ic.call_date) - COUNT(ab.order_date)) AS zero_pax_day,
                (COUNT(ic.call_date) - COUNT(ab.order_date)) * 500 AS deduction_amount
            FROM wpk4_backend_agent_inbound_call ic
            LEFT JOIN wpk4_backend_agent_booking ab ON ic.call_date = ab.order_date AND ic.tsr = ab.tsr
            LEFT JOIN wpk4_backend_agent_codes ac ON ac.tsr = ic.tsr
            WHERE ic.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ic.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ic.agent_name, ac.sale_manager";

        $results = $this->query($sql, $params);
        
        $map = [];
        foreach ($results as $row) {
            $map[$row['agent_name']] = [
                'zero_pax_day' => (int)$row['zero_pax_day'],
                'deduction_amount' => (int)$row['deduction_amount']
            ];
        }
        
        return $map;
    }

    /**
     * Get agent on-time data
     */
    public function getAgentOnTime(string $startDate, string $endDate, ?string $agentFilter = null, ?string $managerFilter = null): array
    {
        $sql = "
            SELECT
                ac.agent_name,
                nd.call_date,
                CASE 
                    WHEN MIN(CAST(nd.logon_time AS FLOAT)) < 1000
                        THEN MAX(CAST(nd.logon_time AS FLOAT))
                    ELSE MIN(CAST(nd.logon_time AS FLOAT))
                END AS noble_login_time,
                CASE 
                    WHEN CAST(MAX(nd.logon_time) AS FLOAT) - (43000 + CAST(MAX(ib.shift_time) AS FLOAT)) <= 0 
                        THEN 'On Time'
                    ELSE 'Late'
                END AS on_time
            FROM wpk4_backend_agent_nobel_data_tsktsrday nd
            JOIN wpk4_backend_agent_codes ac ON nd.tsr = ac.tsr
            JOIN wpk4_backend_agent_inbound_call ib ON nd.tsr = ib.tsr AND nd.call_date = ib.call_date
            WHERE nd.call_date BETWEEN :start AND :end
        ";

        $params = [
            ':start' => $startDate,
            ':end' => $endDate
        ];

        if ($agentFilter) {
            $sql .= " AND ac.agent_name = :agent";
            $params[':agent'] = $agentFilter;
        }
        if ($managerFilter) {
            $sql .= " AND ac.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY ac.agent_name, nd.call_date";

        return $this->query($sql, $params);
    }

    /**
     * Get 10-day summary
     */
    public function get10DaySummary(string $startDate, string $endDate, ?string $managerFilter = null): array
    {
        $start10Day = date('Y-m-d', strtotime($startDate . ' -9 days'));
        
        $sql = "
            SELECT
                a.agent_name,
                a.team_name,
                c.sale_manager,
                SUM(a.gtib_count) AS total_gtib,
                SUM(COALESCE(b.pif, 0)) AS total_pif,
                ROUND(SUM(a.rec_duration) / SUM(a.gtib_count), 0) AS avg_aht,
                ROUND(SUM(a.new_sale_made_count) / SUM(a.gtib_count) * 100, 2) AS avg_fcs,
                ROUND(SUM(COALESCE(b.pif, 0)) / SUM(a.gtib_count) * 100, 2) AS avg_conversion,
                SUM(COALESCE(n.noble_login_time, 0)) AS total_noble_login_time
            FROM wpk4_backend_agent_inbound_call a
            LEFT JOIN (
                SELECT order_date, agent_name, SUM(pax) AS pax, SUM(pif) AS pif
                FROM wpk4_backend_agent_booking
                WHERE order_date BETWEEN :start AND :end
                GROUP BY order_date, agent_name
            ) b ON a.call_date = b.order_date AND a.agent_name = b.agent_name
            LEFT JOIN wpk4_backend_agent_codes c ON a.agent_name = c.agent_name AND c.status = 'active'
            LEFT JOIN (
                SELECT ac.agent_name, SUM(time_connect + time_waiting + time_deassigned + time_acw) AS noble_login_time
                FROM wpk4_backend_agent_nobel_data_tsktsrday n
                JOIN wpk4_backend_agent_codes ac ON n.tsr = ac.tsr
                WHERE n.call_date BETWEEN :start2 AND :end2
                GROUP BY ac.agent_name
            ) n ON a.agent_name = n.agent_name
            WHERE a.call_date BETWEEN :start3 AND :end3
        ";

        $params = [
            ':start' => $start10Day,
            ':end' => $endDate,
            ':start2' => $start10Day,
            ':end2' => $endDate,
            ':start3' => $start10Day,
            ':end3' => $endDate
        ];

        if ($managerFilter) {
            $sql .= " AND c.sale_manager = :manager";
            $params[':manager'] = $managerFilter;
        }

        $sql .= " GROUP BY a.agent_name, a.team_name, c.sale_manager ORDER BY a.agent_name";

        return $this->query($sql, $params);
    }

    /**
     * Get agent target pathway
     */
    public function getAgentTargetPathway(string $rosterCode, string $period): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_target_pathway 
            WHERE roster_code = :roster_code AND period = :period
        ";
    
        $result = $this->queryOne($sql, [
            ':roster_code' => $rosterCode,
            ':period' => $period
        ]);
    
        // Convert false to null to match return type ?array
        return $result === false ? null : $result;
    }

    /**
     * Get all agent target pathways
     */
    public function getAllAgentTargetPathways(?string $period = null): array
    {
        $sql = "SELECT * FROM wpk4_backend_agent_target_pathway";
        
        $params = [];
        if ($period) {
            $sql .= " WHERE period = :period";
            $params[':period'] = $period;
        }
        
        $sql .= " ORDER BY period DESC, roster_code ASC";

        return $this->query($sql, $params);
    }

    /**
     * Save agent target pathway (insert or update)
     */
    public function saveAgentTargetPathway(array $data): bool
    {
        // First check if record exists
        $existing = $this->getAgentTargetPathway($data['roster_code'], $data['period']);
        
        // If exists, copy to history before updating
        if ($existing) {
            $historySql = "
                INSERT INTO wpk4_backend_agent_target_pathway_history
                    (roster_code, target, period, conversion, rate, fcs_mult, rate_fcs, 
                     gtib_bonus, min_gtib, min_pif, daily_pif, total_estimate, created_at)
                VALUES
                    (:roster_code, :target, :period, :conversion, :rate, :fcs_mult, :rate_fcs,
                     :gtib_bonus, :min_gtib, :min_pif, :daily_pif, :total_estimate, :created_at)
            ";
            
            $this->execute($historySql, [
                ':roster_code' => $existing['roster_code'],
                ':target' => $existing['target'],
                ':period' => $existing['period'],
                ':conversion' => $existing['conversion'],
                ':rate' => $existing['rate'],
                ':fcs_mult' => $existing['fcs_mult'],
                ':rate_fcs' => $existing['rate_fcs'],
                ':gtib_bonus' => $existing['gtib_bonus'],
                ':min_gtib' => $existing['min_gtib'],
                ':min_pif' => $existing['min_pif'],
                ':daily_pif' => $existing['daily_pif'],
                ':total_estimate' => $existing['total_estimate'],
                ':created_at' => $existing['created_at']
            ]);
        }

        // Get agent name
        $agentSql = "SELECT agent_name FROM wpk4_backend_agent_codes WHERE roster_code = :roster_code";
        $agent = $this->queryOne($agentSql, [':roster_code' => $data['roster_code']]);
        
        if (!$agent || !$agent['agent_name']) {
            throw new \Exception('Agent not found');
        }

        // Insert/update pathway
        $sql = "
            INSERT INTO wpk4_backend_agent_target_pathway (
                roster_code, target, period,
                conversion, rate, fcs_mult, rate_fcs, gtib_bonus,
                min_gtib, min_pif, daily_pif, total_estimate, created_at
            ) VALUES (
                :roster_code, :target, :period,
                :conversion, :rate, :fcs_mult, :rate_fcs, :gtib_bonus,
                :min_gtib, :min_pif, :daily_pif, :total_estimate, NOW()
            )
            ON DUPLICATE KEY UPDATE
                target = VALUES(target),
                conversion = VALUES(conversion),
                rate = VALUES(rate),
                fcs_mult = VALUES(fcs_mult),
                rate_fcs = VALUES(rate_fcs),
                gtib_bonus = VALUES(gtib_bonus),
                min_gtib = VALUES(min_gtib),
                min_pif = VALUES(min_pif),
                daily_pif = VALUES(daily_pif),
                total_estimate = VALUES(total_estimate),
                created_at = NOW()
        ";

        try {
            $this->execute($sql, [
                ':roster_code' => $data['roster_code'],
                ':target' => $data['target'],
                ':period' => $data['period'],
                ':conversion' => $data['conversion'],
                ':rate' => $data['rate'],
                ':fcs_mult' => $data['fcs_mult'],
                ':rate_fcs' => $data['rate_fcs'],
                ':gtib_bonus' => $data['gtib_bonus'],
                ':min_gtib' => $data['min_gtib'],
                ':min_pif' => $data['min_pif'],
                ':daily_pif' => $data['daily_pif'],
                ':total_estimate' => $data['total_estimate']
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to save pathway: " . $e->getMessage());
            return false;
        }
    }
}

