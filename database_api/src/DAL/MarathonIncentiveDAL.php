<?php
/**
 * Marathon Incentive Data Access Layer
 */

namespace App\DAL;

class MarathonIncentiveDAL extends BaseDAL
{
    /**
     * Fetch combined agent statistics for a date range
     */
    public function getAgentStats(string $startDate, string $endDate, ?string $team = null): array
    {
        $sql = <<<SQL
SELECT
    combined.team_name AS team_name,
    combined.agent_name AS agent_name,
    COUNT(DISTINCT combined.call_date) AS days_present,
    combined.role,
    combined.department,
    SUM(combined.pax) AS pax,
    SUM(combined.fit) AS fit,
    SUM(combined.pif) AS pif,
    SUM(combined.gdeals) AS gdeals,
    SUM(combined.gtib_count) AS gtib,
    CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif) / SUM(combined.gtib_count) ELSE 0 END AS conversion,
    CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.pif_sale_made_count) / SUM(combined.gtib_count) ELSE 0 END AS fcs,
    CASE WHEN SUM(combined.gtib_count) > 0 THEN SUM(combined.rec_duration) / SUM(combined.gtib_count) ELSE 0 END AS aht_seconds
FROM (
    SELECT
        a.agent_name,
        a.call_date,
        c.role,
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
        ON a.tsr = c.tsr AND c.status = 'active'
    WHERE a.call_date BETWEEN ? AND ?

    UNION ALL

    SELECT
        a.agent_name,
        a.order_date,
        c.role,
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
        ON a.tsr = c.tsr AND c.status = 'active'
    WHERE a.order_date BETWEEN ? AND ?
) AS combined
WHERE combined.department = 'Sales'
  AND combined.role <> 'SM'
  AND combined.role <> 'Trainer'
  AND combined.team_name <> 'Bugatti'
SQL;

        $params = [$startDate, $endDate, $startDate, $endDate];

        if ($team !== null && $team !== '') {
            $sql .= "  AND combined.team_name = ?\n";
            $params[] = $team;
        }

        $sql .= "GROUP BY combined.team_name, combined.agent_name, combined.role, combined.department\n";
        $sql .= "ORDER BY combined.team_name, combined.agent_name\n";

        return $this->query($sql, $params);
    }

    /**
     * Fetch garland compliance scores for agents
     */
    public function getGarlandScores(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    cs.agent_name,
    cs.team_name,
    ROUND(SUM(cs.compliant_count) / NULLIF(SUM(cs.audited_call), 0) * 100, 0) AS garland
FROM wpk4_backend_harmony_audited_call_summary cs
LEFT JOIN wpk4_backend_agent_codes ac ON cs.recording_tsr = ac.tsr
WHERE cs.recording_date BETWEEN ? AND ?
GROUP BY cs.agent_name, cs.team_name
SQL;

        return $this->query($sql, [$startDate, $endDate]);
    }
}

