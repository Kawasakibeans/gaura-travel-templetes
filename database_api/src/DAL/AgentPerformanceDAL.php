<?php
/**
 * Agent Performance Data Access Layer
 * Handles database operations for agent performance views
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentPerformanceDAL extends BaseDAL
{
    /**
     * Get team performance data (championship/floor view)
     */
    public function getTeamData(string $fromDate, string $toDate, ?string $team = null): array
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
                CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration)/SUM(combined.gtib_count) ELSE 0 END AS AHT
            FROM (
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
                WHERE a.call_date BETWEEN :from_date AND :to_date
                UNION ALL
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
                WHERE a.order_date BETWEEN :from_date2 AND :to_date2
            ) AS combined
            WHERE combined.department IN ('Sales','BOM-Sales') 
                AND combined.team_name <> 'Sales Manager' 
                AND combined.team_name <> 'Trainer'
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
            ':from_date2' => $fromDate,
            ':to_date2' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND combined.team_name = :team";
            $params[':team'] = $team;
        }

        $sql .= " GROUP BY combined.team_name, combined.department ORDER BY combined.team_name";

        return $this->query($sql, $params);
    }

    /**
     * Get agent performance data
     */
    public function getAgentData(string $fromDate, string $toDate, ?string $team = null): array
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
                    a.pif_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :from_date AND :to_date
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
                    ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.order_date BETWEEN :from_date2 AND :to_date2
            ) AS combined
            WHERE combined.team_name <> 'Others'
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
            ':from_date2' => $fromDate,
            ':to_date2' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND combined.team_name = :team";
            $params[':team'] = $team;
        }

        $sql .= " GROUP BY combined.team_name, combined.agent_name, combined.role ORDER BY combined.team_name, combined.agent_name";

        return $this->query($sql, $params);
    }

    /**
     * Get GTMD data by team
     */
    public function getGTMDByTeam(string $fromDate, string $toDate, ?string $team = null): array
    {
        $sql = "
            SELECT 
                ac.team_name,
                SUM(a.tot_calls) AS gtmd
            FROM wpk4_backend_agent_nobel_data_tsktsrday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr AND ac.status = 'active'
            WHERE a.call_date BETWEEN :from_date AND :to_date AND a.appl = 'GTMD'
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND ac.team_name = :team";
            $params[':team'] = $team;
        }

        $sql .= " GROUP BY ac.team_name";

        $results = $this->query($sql, $params);
        
        // Convert to associative array keyed by team_name
        $teamMap = [];
        foreach ($results as $row) {
            $teamMap[$row['team_name']] = (int)$row['gtmd'];
        }
        
        return $teamMap;
    }

    /**
     * Get team trends for last 7 days
     */
    public function getTeamTrends(string $fromDate, string $toDate, ?string $team = null): array
    {
        $sql = "
            SELECT 
                ac.team_name,
                DATE_FORMAT(ib.call_date, '%d-%b') as day,
                ib.call_date as days,
                SUM(ib.gtib_count) AS gtib,
                SUM(ab.pax) AS pax
            FROM wpk4_backend_agent_inbound_call ib
            LEFT JOIN wpk4_backend_agent_booking ab ON ib.tsr = ab.tsr AND ib.call_date = ab.order_date
            LEFT JOIN wpk4_backend_agent_codes ac ON ib.tsr = ac.tsr AND ac.status = 'active'
            WHERE ib.call_date BETWEEN :from_date AND :to_date AND ac.team_name IS NOT NULL
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND ac.team_name = :team";
            $params[':team'] = $team;
            $sql .= " GROUP BY ac.team_name, day ORDER BY ac.team_name, days";
        } else {
            $sql .= " GROUP BY ac.team_name, day, days ORDER BY ac.team_name, days";
        }

        return $this->query($sql, $params);
    }

    /**
     * Get latest booking
     */
    public function getLatestBooking(): ?array
    {
        $sql = "
            SELECT 
                ac.agent_name, 
                b.total_pax, 
                DATE_FORMAT(b.order_date, '%H:%i') as booked_at
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_agent_codes ac ON b.agent_info = ac.sales_id
            WHERE b.order_date >= '2025-05-09'
            ORDER BY b.auto_id DESC
            LIMIT 1
        ";

        $result = $this->queryOne($sql);
        return $result ?: null;
    }

    /**
     * Get team QA compliance
     */
    public function getTeamQACompliance(string $fromDate, string $toDate, ?string $team = null): array
    {
        $sql = "
            SELECT 
                cs.team_name,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS compliance_percentage
            FROM gaurat_gauratravel.wpk4_backend_harmony_audited_call_summary cs
            LEFT JOIN wpk4_backend_agent_codes ac ON cs.recording_tsr = ac.tsr
            WHERE cs.recording_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND cs.team_name = :team";
            $params[':team'] = $team;
        }

        $sql .= " GROUP BY cs.team_name";

        $results = $this->query($sql, $params);
        
        // Convert to associative array keyed by team_name
        $teamMap = [];
        foreach ($results as $row) {
            $teamMap[$row['team_name']] = (int)$row['compliance_percentage'];
        }
        
        return $teamMap;
    }

    /**
     * Get agent QA compliance
     */
    public function getAgentQACompliance(string $fromDate, string $toDate, ?string $team = null): array
    {
        $sql = "
            SELECT 
                cs.agent_name,
                cs.team_name,
                ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS compliance_percentage
            FROM gaurat_gauratravel.wpk4_backend_harmony_audited_call_summary cs
            LEFT JOIN wpk4_backend_agent_codes ac ON cs.recording_tsr = ac.tsr
            WHERE cs.recording_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            ':from_date' => $fromDate,
            ':to_date' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $sql .= " AND cs.team_name = :team";
            $params[':team'] = $team;
        }

        $sql .= " GROUP BY cs.agent_name, cs.team_name";

        $results = $this->query($sql, $params);
        
        // Convert to associative array keyed by agent_name
        $agentMap = [];
        foreach ($results as $row) {
            $agentMap[$row['agent_name']] = (int)$row['compliance_percentage'];
        }
        
        return $agentMap;
    }

    /**
     * Get agent bookings
     */
    public function getAgentBookings(string $agentName, string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT DISTINCT
                c.agent_name, 
                c.team_name, 
                a.order_id, 
                a.order_date, 
                b.fname, 
                b.lname, 
                b.dob, 
                a.payment_status
            FROM wpk4_backend_travel_bookings a
            LEFT JOIN (
                SELECT order_id
                FROM wpk4_backend_travel_payment_history
                GROUP BY order_id
                HAVING SUM(trams_received_amount) > 0
            ) p ON a.order_id = p.order_id
            LEFT JOIN wpk4_backend_travel_booking_pax b 
                ON a.order_id = b.order_id
            LEFT JOIN wpk4_backend_agent_codes c 
                ON a.agent_info = c.sales_id AND c.status = 'active'
            WHERE 
                DATE(a.order_date) BETWEEN :from_date AND :to_date
                AND DATE(b.order_date) BETWEEN :from_date2 AND :to_date2
                AND a.source <> 'import'
                AND c.agent_name = :agent_name
            ORDER BY a.order_date DESC
        ";

        return $this->query($sql, [
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
            ':from_date2' => $fromDate,
            ':to_date2' => $toDate,
            ':agent_name' => $agentName
        ]);
    }

    /**
     * Get agent working time
     */
    public function getAgentWorkingTime(string $agentName, string $teamName, string $date): ?array
    {
        $sql = "
            SELECT
                c.agent_name, 
                c.team_name,
                SUM(wbandt.time_connect) as time_connect,
                SUM(wbandt.time_waiting) as time_waiting,
                SUM(wbandt.time_deassigned) as time_deassigned,
                SUM(wbandt.time_acw) as time_acw
            FROM gaurat_gauratravel.wpk4_backend_agent_nobel_data_tsktsrday wbandt
            LEFT JOIN wpk4_backend_agent_codes c ON wbandt.tsr = c.tsr
            WHERE wbandt.call_date = :date 
                AND c.agent_name = :agent_name 
                AND c.team_name = :team_name
            GROUP BY c.agent_name, c.team_name
        ";

        return $this->queryOne($sql, [
            ':date' => $date,
            ':agent_name' => $agentName,
            ':team_name' => $teamName
        ]);
    }

    /**
     * Get agent FCS calls
     */
    public function getAgentFCSCalls(string $agentName, string $teamName, string $date): array
    {
        $sql = "
            SELECT 
                c.agent_name, 
                c.team_name, 
                wbandcr.file_num, 
                wbandcr.phone, 
                wbandcr.end_time as call_end_time, 
                wbandcr.fcs as is_fcs
            FROM gaurat_gauratravel.wpk4_backend_agent_nobel_data_call_rec wbandcr
            LEFT JOIN wpk4_backend_agent_codes c ON wbandcr.tsr = c.tsr
            WHERE wbandcr.call_date = :date 
                AND wbandcr.fcs = 'yes' 
                AND c.agent_name = :agent_name 
                AND c.team_name = :team_name 
                AND wbandcr.appl = 'GTIB'
            ORDER BY wbandcr.end_time DESC
        ";

        return $this->query($sql, [
            ':date' => $date,
            ':agent_name' => $agentName,
            ':team_name' => $teamName
        ]);
    }

    /**
     * Get agent detail (GTMD, connect time, etc.)
     */
    public function getAgentDetail(string $date, ?string $team = null): array
    {
        // Main query for GTMD/Connect Time
        $sql1 = "
            SELECT 
                ac.agent_name,
                ac.team_name,
                ac.sale_manager,
                SUM(CASE WHEN a.appl = 'GTMD' THEN a.tot_calls ELSE 0 END) as GTMD,
                SUM(CASE WHEN a.appl = 'GTIB' THEN a.time_connect ELSE 0 END) AS IB_connect_time,
                SUM(CASE WHEN a.appl = 'GTMD' THEN a.time_connect ELSE 0 END) AS MD_connect_time,
                SUM(CASE WHEN a.appl IN ('GTIB', 'GTMD') THEN a.time_connect ELSE 0 END) AS total_connect_time,
                SUM(a.time_connect) + 
                SUM(a.time_paused) + 
                SUM(a.time_waiting) + 
                SUM(a.time_deassigned) + 
                SUM(a.time_acw) AS total_login_time,
                ROUND(
                    SUM(CASE WHEN a.appl IN ('GTIB', 'GTMD') THEN a.time_connect ELSE 0 END) / 
                    NULLIF(
                        SUM(a.time_connect) + SUM(a.time_paused) + SUM(a.time_waiting) + SUM(a.time_deassigned) + SUM(a.time_acw),
                        0
                    ),
                    4
                ) AS Utilization_Rate
            FROM wpk4_backend_agent_nobel_data_tsktsrday a
            LEFT JOIN wpk4_backend_agent_codes ac ON a.tsr = ac.tsr
            WHERE ac.status = 'active' AND a.call_date = :date
        ";

        $params1 = [':date' => $date];
        if ($team && $team !== 'ALL') {
            $sql1 .= " AND ac.team_name = :team";
            $params1[':team'] = $team;
        }
        $sql1 .= " GROUP BY ac.agent_name, ac.team_name, ac.sale_manager";

        $mainData = $this->query($sql1, $params1);

        // Get pause time
        $sql2 = "
            SELECT 
                ac.agent_name, 
                ac.team_name, 
                ac.sale_manager, 
                SUM(pau.pause_time) as pause_time
            FROM wpk4_backend_agent_nobel_data_tskpauday pau
            LEFT JOIN wpk4_backend_agent_codes ac ON pau.tsr = ac.tsr
            WHERE ac.status = 'active' AND pau.call_date = :date AND pau.pause_code = 'GTBK'
        ";

        $params2 = [':date' => $date];
        if ($team && $team !== 'ALL') {
            $sql2 .= " AND ac.team_name = :team";
            $params2[':team'] = $team;
        }
        $sql2 .= " GROUP BY ac.agent_name, ac.team_name, ac.sale_manager";

        $pauseData = $this->query($sql2, $params2);

        // Get hold time
        $sql3 = "
            SELECT 
                ac.agent_name, 
                ac.team_name, 
                ac.sale_manager, 
                SUM(th.time_hold) as hold_time, 
                COUNT(th.time_hold) as count_hold_time
            FROM wpk4_backend_agent_nobel_data_callhisthold th
            LEFT JOIN wpk4_backend_agent_codes ac ON th.tsr = ac.tsr
            WHERE ac.status = 'active' AND th.act_date = :date AND th.appl = 'GTIB'
        ";

        $params3 = [':date' => $date];
        if ($team && $team !== 'ALL') {
            $sql3 .= " AND ac.team_name = :team";
            $params3[':team'] = $team;
        }
        $sql3 .= " GROUP BY ac.agent_name, ac.team_name, ac.sale_manager";

        $holdData = $this->query($sql3, $params3);

        // Merge data
        $result = [];
        foreach ($mainData as $row) {
            $key = $row['agent_name'] . '||' . $row['team_name'] . '||' . $row['sale_manager'];
            $result[$key] = $row;
        }

        foreach ($pauseData as $row) {
            $key = $row['agent_name'] . '||' . $row['team_name'] . '||' . $row['sale_manager'];
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key]['pause_time'] = $row['pause_time'];
        }

        foreach ($holdData as $row) {
            $key = $row['agent_name'] . '||' . $row['team_name'] . '||' . $row['sale_manager'];
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key]['hold_time'] = $row['hold_time'];
            $result[$key]['count_hold_time'] = $row['count_hold_time'];
        }

        return array_values($result);
    }

    /**
     * Get agent 10-day view data (compliance and performance)
     */
    public function getAgent10DayView(string $fromDate, string $toDate, ?string $team = null): array
    {
        // Compliance query
        $complianceSql = "
            SELECT
                e.recording_date AS date,
                a.agent_name,
                a.team_name,
                e.audited_call,
                e.compliant_count,
                (e.audited_call - e.compliant_count) AS non_compliant_count,
                ROUND(IFNULL(e.compliant_count / NULLIF(e.audited_call, 0), 0), 4) AS compliance_percentage
            FROM wpk4_backend_harmony_audited_call_summary e
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            WHERE e.recording_date BETWEEN :from AND :to
                AND a.status = 'active'
                AND a.team_name <> 'Others'
        ";

        $complianceParams = [
            ':from' => $fromDate,
            ':to' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $complianceSql .= " AND a.team_name = :team";
            $complianceParams[':team'] = $team;
        }

        $complianceData = $this->query($complianceSql, $complianceParams);

        // Performance query
        $performanceSql = "
            SELECT
                combined.agent_name,
                combined.team_name,
                combined.day AS date,
                SUM(combined.gtib_count) AS gtib,
                SUM(combined.pif) AS pif,
                SUM(combined.new_sale_made_count) AS sale_made,
                SUM(combined.rec_duration) AS rec_duration,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pax)/SUM(combined.gtib_count), 4) ELSE 0 END AS conversion,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.new_sale_made_count)/SUM(combined.gtib_count), 4) ELSE 0 END AS fcs,
                CASE WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.rec_duration)/SUM(combined.gtib_count), 2) ELSE 0 END AS aht
            FROM (
                SELECT
                    a.agent_name,
                    c.team_name,
                    DATE(a.call_date) AS day,
                    0 AS pax, 0 AS fit, 0 AS pif, 0 AS gdeals,
                    a.gtib_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr AND c.status = 'active' AND c.role NOT IN ('SM','Trainer')
                WHERE a.call_date BETWEEN :from AND :to

                UNION ALL

                SELECT
                    a.agent_name,
                    c.team_name,
                    DATE(a.order_date) AS day,
                    a.pax, a.fit, a.pif, a.gdeals,
                    0 AS gtib_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c 
                    ON a.tsr = c.tsr AND c.status = 'active' AND c.role NOT IN ('SM','Trainer')
                WHERE a.order_date BETWEEN :from2 AND :to2
            ) AS combined
            WHERE 1=1
        ";

        $performanceParams = [
            ':from' => $fromDate,
            ':to' => $toDate,
            ':from2' => $fromDate,
            ':to2' => $toDate
        ];

        if ($team && $team !== 'ALL') {
            $performanceSql .= " AND combined.team_name = :team";
            $performanceParams[':team'] = $team;
        }

        $performanceSql .= " GROUP BY combined.agent_name, combined.team_name, combined.day ORDER BY combined.day ASC, combined.agent_name ASC";

        $performanceData = $this->query($performanceSql, $performanceParams);

        // Index compliance data
        $complianceMap = [];
        foreach ($complianceData as $row) {
            $key = $row['agent_name'] . '|' . $row['date'];
            $complianceMap[$key] = $row;
        }

        // Merge data
        $final = [];
        foreach ($performanceData as $pRow) {
            if (empty(trim($pRow['agent_name']))) {
                continue;
            }

            $key = $pRow['agent_name'] . '|' . $pRow['date'];
            $cRow = $complianceMap[$key] ?? null;

            $final[] = [
                'Date' => $pRow['date'],
                'Interval' => date('j', strtotime($pRow['date'])) <= 10 
                    ? '1-10 ' . strtoupper(date('M', strtotime($pRow['date']))) 
                    : (date('j', strtotime($pRow['date'])) <= 20 
                        ? '11-20 ' . strtoupper(date('M', strtotime($pRow['date']))) 
                        : '21-End ' . strtoupper(date('M', strtotime($pRow['date'])))),
                'Agent' => $pRow['agent_name'],
                'Team' => $pRow['team_name'],
                'Audited' => $cRow['audited_call'] ?? 0,
                'Compliant' => $cRow['compliant_count'] ?? 0,
                'Non-Compliant' => $cRow['non_compliant_count'] ?? 0,
                'GARLAND' => isset($cRow['compliance_percentage']) ? $cRow['compliance_percentage'] : 0,
                'GTIB' => $pRow['gtib'],
                'PIF' => $pRow['pif'],
                'SL' => $pRow['sale_made'],
                'Duration' => $pRow['rec_duration'],
                'Conversion' => $pRow['conversion'],
                'FCS' => $pRow['fcs'],
                'AHT' => $pRow['aht']
            ];
        }

        return $final;
    }

    /**
     * Get remarks
     */
    public function getRemarks(string $fromDate, string $toDate): array
    {
        // Get agent mapping
        $agentMap = [];
        $agents = $this->query("SELECT tsr, agent_name FROM wpk4_backend_agent_codes WHERE status = 'active'");
        foreach ($agents as $agent) {
            $agentMap[$agent['tsr']] = $agent['agent_name'];
        }

        // Get remarks
        $sql = "
            SELECT * FROM wpk4_backend_agent_performance_remark
            WHERE date BETWEEN :from AND :to
        ";

        $remarks = $this->query($sql, [
            ':from' => $fromDate,
            ':to' => $toDate
        ]);

        $result = [];
        foreach ($remarks as $r) {
            $tsr = $r['tsr'];
            $agentName = $agentMap[$tsr] ?? null;
            if ($agentName) {
                $result[] = [
                    'agent' => $agentName,
                    'metric' => $r['metric'],
                    'date' => $r['date'],
                    'remark' => $r['remark'],
                ];
            }
        }

        return $result;
    }

    /**
     * Add remark
     */
    public function addRemark(string $tsr, string $date, string $metric, string $remark): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_agent_performance_remark 
                (tsr, date, metric, remark, created_at, updated_at) 
            VALUES (:tsr, :date, :metric, :remark, NOW(), NOW())
        ";

        try {
            $this->execute($sql, [
                ':tsr' => $tsr,
                ':date' => $date,
                ':metric' => $metric,
                ':remark' => $remark
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to add remark: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get observation dashboard data - abandoned calls
     */
    public function getAbandonedCalls(string $date): ?array
    {
        $sql = "
            SELECT 
                COUNT(call_date) AS abandoned_calls,
                COUNT(CASE WHEN appl = 'GTIB' THEN call_date END) AS GTIB_abandoned,
                COUNT(CASE WHEN appl = 'GTDC' THEN call_date END) AS GTDC_abandoned,
                COUNT(CASE WHEN appl = 'GTCS' THEN call_date END) AS GTCS_abandoned,
                COUNT(CASE WHEN appl = 'GTPY' THEN call_date END) AS GTPY_abandoned,
                COUNT(CASE WHEN appl = 'GTET' THEN call_date END) AS GTET_abandoned,
                COUNT(CASE WHEN appl = 'GTRF' THEN call_date END) AS GTRF_abandoned
            FROM wpk4_backend_agent_nobel_data_inboundcall_rec rec 
            WHERE call_date = :date AND tsr = ''
        ";

        return $this->queryOne($sql, [':date' => $date]);
    }

    /**
     * Get observation dashboard data - call counts
     */
    public function getCallCounts(string $date): ?array
    {
        $sql = "
            SELECT 
                COUNT(CASE WHEN appl = 'GTIB' THEN call_date END) AS GTIB_callcount,
                COUNT(CASE WHEN appl = 'GTDC' THEN call_date END) AS GTDC_callcount,
                COUNT(CASE WHEN appl = 'GTCS' THEN call_date END) AS GTCS_callcount,
                COUNT(CASE WHEN appl = 'GTPY' THEN call_date END) AS GTPY_callcount,
                COUNT(CASE WHEN appl = 'GTET' THEN call_date END) AS GTET_callcount,
                COUNT(CASE WHEN appl = 'GTRF' THEN call_date END) AS GTRF_callcount
            FROM wpk4_backend_agent_nobel_data_inboundcall_rec 
            WHERE call_date = :date AND tsr <> ''
        ";

        return $this->queryOne($sql, [':date' => $date]);
    }

    /**
     * Get observation dashboard data - duration buckets
     */
    public function getDurationBuckets(string $date): ?array
    {
        $sql = "
            SELECT 
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration <= 300 THEN rec.call_date END) as `<=5mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 300 and rec.rec_duration <= 600 THEN rec.call_date END) as `>5mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 600 and rec.rec_duration <= 900 THEN rec.call_date END) as `>10mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 900 and rec.rec_duration <= 1200 THEN rec.call_date END) as `>15mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 1200 and rec.rec_duration <= 1500 THEN rec.call_date END) as `>20mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 1500 and rec.rec_duration <= 1800 THEN rec.call_date END) as `>25mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 1800 and rec.rec_duration <= 2100 THEN rec.call_date END) as `>30mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 2100 and rec.rec_duration <= 2400 THEN rec.call_date END) as `>35mins`,
                COUNT(CASE WHEN rec.appl = 'GTIB' and rec.rec_duration > 2400 THEN rec.call_date END) as `>40mins`
            FROM wpk4_backend_agent_nobel_data_call_rec rec
            WHERE rec.call_date = :date
        ";

        return $this->queryOne($sql, [':date' => $date]);
    }

    /**
     * Get observation dashboard data - key metrics
     */
    public function getKeyMetrics(string $date): ?array
    {
        $sql = "
            SELECT
                SUM(combined.pax) AS total_pax,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.fit) AS fit,
                SUM(combined.gtib_count) AS total_gtib,
                ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.pax) / SUM(combined.gtib_count), 0), 4) AS conversion,
                ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 0), 4) AS fcs,
                SEC_TO_TIME(ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.rec_duration) / SUM(combined.gtib_count), 0))) AS AHT
            FROM (
                SELECT a.agent_name, 0 pax, 0 fit, 0 pif, 0 gdeals, a.team_name, a.gtib_count, a.new_sale_made_count, a.non_sale_made_count, a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date = :date
                UNION ALL
                SELECT a.agent_name, a.pax, a.fit, a.pif, a.gdeals, a.team_name, 0, 0, 0, 0
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE DATE(a.order_date) = :date2
            ) AS combined
        ";

        return $this->queryOne($sql, [
            ':date' => $date,
            ':date2' => $date
        ]);
    }
}

