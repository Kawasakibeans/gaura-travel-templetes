<?php
/**
 * Observation dashboard data-access layer
 */

namespace App\DAL;

class ObservationDashboardDAL extends BaseDAL
{
    /**
     * Abandoned call statistics for the supplied date.
     *
     * @param string $date
     * @return array<string, mixed>
     */
    public function getAbandonedCallStats(string $date): array
    {
        $sql = "
            SELECT
                COUNT(call_date) AS abandoned_calls,
                COUNT(CASE WHEN appl = 'GTIB' THEN call_date END) AS gtib_abandoned,
                COUNT(CASE WHEN appl = 'GTDC' THEN call_date END) AS gtdc_abandoned,
                COUNT(CASE WHEN appl = 'GTCS' THEN call_date END) AS gtcs_abandoned,
                COUNT(CASE WHEN appl = 'GTPY' THEN call_date END) AS gtpy_abandoned,
                COUNT(CASE WHEN appl = 'GTET' THEN call_date END) AS gtet_abandoned,
                COUNT(CASE WHEN appl = 'GTRF' THEN call_date END) AS gtrf_abandoned
            FROM wpk4_backend_agent_nobel_data_inboundcall_rec
            WHERE call_date = ?
              AND tsr = ''
        ";

        return $this->queryOne($sql, [$date]) ?: [];
    }

    /**
     * Call counts for the supplied date.
     */
    public function getCallCounts(string $date): array
    {
        $sql = "
            SELECT
                COUNT(CASE WHEN appl = 'GTIB' THEN call_date END) AS gtib_callcount,
                COUNT(CASE WHEN appl = 'GTDC' THEN call_date END) AS gtdc_callcount,
                COUNT(CASE WHEN appl = 'GTCS' THEN call_date END) AS gtcs_callcount,
                COUNT(CASE WHEN appl = 'GTPY' THEN call_date END) AS gtpy_callcount,
                COUNT(CASE WHEN appl = 'GTET' THEN call_date END) AS gtet_callcount,
                COUNT(CASE WHEN appl = 'GTRF' THEN call_date END) AS gtrf_callcount
            FROM wpk4_backend_agent_nobel_data_call_rec
            WHERE call_date = ?
              AND tsr <> ''
        ";

        return $this->queryOne($sql, [$date]) ?: [];
    }

    /**
     * Duration bucket stats for GTIB calls.
     */
    public function getDurationBuckets(string $date): array
    {
        $sql = "
            SELECT
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration <= 300 THEN rec.call_date END) AS le_5mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 300 AND rec.rec_duration <= 600 THEN rec.call_date END) AS gt_5_le_10,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 600 AND rec.rec_duration <= 900 THEN rec.call_date END) AS gt_10_le_15,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 900 AND rec.rec_duration <= 1200 THEN rec.call_date END) AS gt_15_le_20,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1200 AND rec.rec_duration <= 1500 THEN rec.call_date END) AS gt_20_le_25,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1500 AND rec.rec_duration <= 1800 THEN rec.call_date END) AS gt_25_le_30,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1800 AND rec.rec_duration <= 2100 THEN rec.call_date END) AS gt_30_le_35,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 2100 AND rec.rec_duration <= 2400 THEN rec.call_date END) AS gt_35_le_40,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 2400 AND rec.rec_duration <= 2700 THEN rec.call_date END) AS gt_40_le_45,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 2700 AND rec.rec_duration <= 3000 THEN rec.call_date END) AS gt_45_le_50,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 3000 THEN rec.call_date END) AS gt_50
            FROM wpk4_backend_agent_nobel_data_call_rec rec
            WHERE rec.call_date = ?
        ";

        return $this->queryOne($sql, [$date]) ?: [];
    }

    /**
     * Key performance metrics for the supplied date.
     */
    public function getKeyMetrics(string $date): array
    {
        $sql = "
            SELECT
                SUM(combined.pax) AS total_pax,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.fit) AS fit,
                SUM(combined.gtib_count) AS total_gtib,
                CASE
                    WHEN SUM(combined.gtib_count) > 0
                        THEN SUM(combined.pax) / SUM(combined.gtib_count)
                    ELSE 0
                END AS conversion_ratio,
                CASE
                    WHEN SUM(combined.gtib_count) > 0
                        THEN SUM(combined.new_sale_made_count) / SUM(combined.gtib_count)
                    ELSE 0
                END AS fcs_ratio,
                CASE
                    WHEN SUM(combined.gtib_count) > 0
                        THEN SUM(combined.rec_duration) / SUM(combined.gtib_count)
                    ELSE 0
                END AS aht_seconds
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
                WHERE a.call_date = ?

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
                WHERE DATE(a.order_date) = ?
            ) AS combined
        ";

        return $this->queryOne($sql, [$date, $date]) ?: [];
    }
}

