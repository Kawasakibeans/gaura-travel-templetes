<?php
/**
 * Steptember incentive data access layer
 */

namespace App\DAL;

class SteptemberIncentiveDAL extends BaseDAL
{
    public function getAgentMetricsByDate(string $date): array
    {
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
    MAX(s.shift_time) AS shift_time,
    MAX(nl.noble_login_time) AS noble_login_time,
    CASE
        WHEN CAST(MAX(nl.noble_login_time) AS FLOAT) - (43000 + CAST(MAX(s.shift_time) AS FLOAT)) <= 0
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
    WHERE b.order_date = ?
) AS data
JOIN wpk4_backend_agent_codes ac
    ON data.tsr = ac.tsr
    AND ac.status = 'active'
    AND ac.team_name <> 'Others'
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
HAVING
    SUM(data.pif) > 0
    AND on_time = 'On Time'
    AND ROUND(IFNULL(SUM(data.new_sale_made_count) / NULLIF(SUM(data.gtib_count), 0), 0), 2) >= 0.25
    AND ROUND(IFNULL(SUM(data.rec_duration) / NULLIF(SUM(data.gtib_count), 0), 0), 2) <= 1440
    AND ROUND(IFNULL(SUM(data.pif) / NULLIF(SUM(data.gtib_count), 0), 0), 2) > 0.4
ORDER BY date ASC
SQL;

        $params = [$date, $date, $date, $date];
        return $this->query($sql, $params);
    }

    public function getDailyPerformance(string $startDate, string $endDate): array
    {
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
        SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
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
        SELECT * FROM wpk4_backend_agent_codes WHERE department = 'sales'
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
WHERE combined.team_name <> 'Others'
  AND combined.date BETWEEN ? AND ?
GROUP BY combined.date
ORDER BY combined.date ASC
SQL;

        return $this->query($sql, [$startDate, $endDate]);
    }
}

