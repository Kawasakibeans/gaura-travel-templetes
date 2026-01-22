<?php
/**
 * Rocktober incentive data access layer
 */

namespace App\DAL;

class RocktoberIncentiveDAL extends BaseDAL
{
    public function getAgentMetricsByDate(string $date, array $thresholds = []): array
    {
        // Only use thresholds if explicitly provided (no defaults)
        $minPif = $thresholds['minimum_pif'] ?? null;
        $minFcs = $thresholds['minimum_fcs'] ?? null;
        $minPifPercent = $thresholds['minimum_pif_percent'] ?? null;
        $maxAht = $thresholds['maximum_aht_seconds'] ?? null;
        $minGarland = $thresholds['minimum_garland_ratio'] ?? null;
        $requireOnTime = isset($thresholds['require_on_time']) ? (bool)$thresholds['require_on_time'] : null;
        $onTimeBuffer = $thresholds['on_time_buffer_seconds'] ?? 43000; // Default buffer for on-time calculation
        $excludeTeamName = $thresholds['exclude_team_name'] ?? null;

        // Build WHERE clause for team exclusion
        $teamFilter = '';
        if ($excludeTeamName !== null) {
            $teamFilter = "AND ac.team_name <> ?";
        }

        // Build HAVING clause dynamically based on provided thresholds
        $havingConditions = [];
        $params = [
            $date, // WHERE ic.call_date = ?
            $date, // WHERE DATE(b.order_date) = ?
        ];
        
        if ($excludeTeamName !== null) {
            $params[] = $excludeTeamName; // AND ac.team_name <> ?
        }
        
        $params[] = $date; // WHERE ai.call_date = ? (shift_time subquery)
        $params[] = $date; // WHERE ai.recording_date = ? (garland subquery)
        $params[] = $date; // WHERE nd.call_date = ? (noble login subquery)
        $params[] = $onTimeBuffer; // For on_time calculation in SELECT CASE

        // Add threshold conditions only if provided
        if ($minPif !== null) {
            $havingConditions[] = "SUM(data.pif) > ?";
            $params[] = $minPif;
        }

        if ($requireOnTime !== null) {
            $havingConditions[] = "(
                CASE
                    WHEN MAX(nl.noble_login_time) IS NULL OR MAX(s.shift_time) IS NULL
                    THEN 'Unknown'
                    WHEN CAST(MAX(nl.noble_login_time) AS FLOAT) - (? + CAST(MAX(s.shift_time) AS FLOAT)) <= 0
                    THEN 'On Time'
                    ELSE 'Late'
                END = 'On Time' OR ? = 0
            )";
            $params[] = $onTimeBuffer;
            $params[] = $requireOnTime ? 0 : 1; // If require_on_time is false, allow all (OR ? = 0 becomes true)
        }

        if ($minFcs !== null) {
            $havingConditions[] = "ROUND(IFNULL(SUM(data.new_sale_made_count) / NULLIF(SUM(data.gtib_count), 0), 0), 2) >= ?";
            $params[] = $minFcs;
        }

        if ($maxAht !== null) {
            $havingConditions[] = "ROUND(IFNULL(SUM(data.rec_duration) / NULLIF(SUM(data.gtib_count), 0), 0), 2) <= ?";
            $params[] = $maxAht;
        }

        if ($minPifPercent !== null) {
            $havingConditions[] = "ROUND(IFNULL(SUM(data.pif) / NULLIF(SUM(data.gtib_count), 0), 0), 2) > ?";
            $params[] = $minPifPercent;
        }

        if ($minGarland !== null) {
            $havingConditions[] = "(
                SUM(COALESCE(g.audited_call, 0)) = 0 
                OR (SUM(COALESCE(g.compliant_count, 0)) / NULLIF(SUM(COALESCE(g.audited_call, 0)), 0)) > ?
            )";
            $params[] = $minGarland;
        }

        $havingClause = '';
        if (!empty($havingConditions)) {
            $havingClause = 'HAVING ' . implode(' AND ', $havingConditions);
        }

        $sql = <<<SQL
SELECT
    COALESCE(data.call_date, data.order_date) AS date,
    data.agent_name,
    SUM(data.pif) AS pif,
    SUM(data.gtib_count) AS gtib,
    SUM(data.new_sale_made_count) AS new_sale_made_count,
    ROUND(IFNULL(SUM(data.pif) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS pif_percent,
    ROUND(IFNULL(SUM(data.new_sale_made_count) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS fcs,
    ROUND(IFNULL(SUM(data.rec_duration) / NULLIF(SUM(data.gtib_count), 0), 0), 2) AS aht_seconds,
    SUM(COALESCE(g.compliant_count, 0)) / NULLIF(SUM(COALESCE(g.audited_call, 0)), 0) AS garland_ratio,
    MAX(s.shift_time) AS shift_time,
    MAX(nl.noble_login_time) AS noble_login_time,
    CASE
        WHEN MAX(nl.noble_login_time) IS NULL OR MAX(s.shift_time) IS NULL
        THEN 'Unknown'
        WHEN CAST(MAX(nl.noble_login_time) AS FLOAT) - (? + CAST(MAX(s.shift_time) AS FLOAT)) <= 0
        THEN 'On Time'
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
    WHERE ic.call_date = ?
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
    WHERE DATE(b.order_date) = ?
) AS data
JOIN wpk4_backend_agent_codes ac
    ON data.tsr = ac.tsr
    AND ac.status = 'active'
    {$teamFilter}
LEFT JOIN (
    SELECT
        ai.call_date,
        ai.agent_name,
        MAX(ai.shift_time) AS shift_time
    FROM wpk4_backend_agent_inbound_call ai
    WHERE ai.call_date = ?
    GROUP BY ai.call_date, ai.agent_name
) s
    ON s.call_date = data.call_date AND s.agent_name = data.agent_name
LEFT JOIN (
    SELECT
        ai.recording_date,
        ac.agent_name,
        ai.compliant_count,
        ai.audited_call
    FROM wpk4_backend_harmony_audited_call_summary ai
    LEFT JOIN wpk4_backend_agent_codes ac ON ai.recording_tsr = ac.tsr
    WHERE ai.recording_date = ?
) g
    ON g.recording_date = data.call_date AND g.agent_name = data.agent_name
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
    WHERE nd.call_date = ?
    GROUP BY ac.agent_name, nd.call_date
) nl
    ON nl.agent_name = data.agent_name AND nl.call_date = data.call_date
GROUP BY date, data.agent_name
{$havingClause}
ORDER BY date ASC
SQL;
        
        return $this->query($sql, $params);
    }

    public function getDailyPerformance(string $startDate, string $endDate, string $department = null, string $excludeTeamName = null): array
    {
        // Build department filter
        $departmentFilter = '';
        if ($department !== null) {
            $departmentFilter = "WHERE department = ?";
        }

        // Build team exclusion filter
        $teamFilter = '';
        if ($excludeTeamName !== null) {
            $teamFilter = "AND combined.team_name <> ?";
        }

        $params = [];
        if ($department !== null) {
            $params[] = $department; // WHERE department = ?
            $params[] = $department; // Second occurrence in UNION
        }
        if ($excludeTeamName !== null) {
            $params[] = $excludeTeamName; // AND combined.team_name <> ?
        }
        $params[] = $startDate; // BETWEEN ? AND ?
        $params[] = $endDate;   // BETWEEN ? AND ?

        $sql = <<<SQL
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
    END AS aht_seconds
FROM (
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
        SELECT * FROM wpk4_backend_agent_codes {$departmentFilter}
    ) c
        ON a.tsr = c.tsr AND c.status = 'active'

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
        SELECT * FROM wpk4_backend_agent_codes {$departmentFilter}
    ) c
        ON a.tsr = c.tsr AND c.status = 'active'

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
) AS combined
WHERE 1=1
  {$teamFilter}
  AND combined.date BETWEEN ? AND ?
GROUP BY combined.date
ORDER BY combined.date ASC
SQL;

        return $this->query($sql, $params);
    }
}

