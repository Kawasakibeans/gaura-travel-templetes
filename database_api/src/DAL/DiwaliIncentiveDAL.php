<?php
/**
 * Diwali Incentive Data Access Layer
 * Handles database operations for Diwali incentive tracking
 */

namespace App\DAL;

class DiwaliIncentiveDAL extends BaseDAL
{
    /**
     * Get aggregated daily performance metrics for Diwali incentive
     *
     * @param string $fromDate Start date (YYYY-MM-DD)
     * @param string $toDate End date (YYYY-MM-DD)
     * @param string|null $teamName Optional team filter
     * @return array
     */
    public function getDailyPerformance(string $fromDate, string $toDate, ?string $teamName = null): array
    {
        $query = "
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
                    THEN ROUND(SUM(combined.rec_duration) / SUM(combined.gtib_count), 2) 
                    ELSE 0 
                END AS AHT
            FROM (
                -- Inbound Call Data
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
                WHERE a.call_date BETWEEN ? AND ?

                UNION ALL

                -- Booking Data
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
                WHERE a.order_date BETWEEN ? AND ?

                UNION ALL

                -- Abandoned Call Data (no TSR)
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
                  AND w.appl = 'GTIB'
                  AND w.call_date BETWEEN ? AND ?
            ) AS combined
            WHERE combined.team_name <> 'Others'
              AND combined.date BETWEEN ? AND ?
        ";

        $params = [
            $fromDate, $toDate, // inbound
            $fromDate, $toDate, // booking
            $fromDate, $toDate, // abandoned
            $fromDate, $toDate  // combined filter
        ];

        if ($teamName !== null && $teamName !== '' && strtoupper($teamName) !== 'ALL') {
            $query .= " AND combined.team_name = ? ";
            $params[] = $teamName;
        }

        $query .= "
            GROUP BY combined.date
            ORDER BY combined.date DESC
        ";

        return $this->query($query, $params);
    }

    /**
     * Insert a Diwali incentive comment
     *
     * @param int $userId
     * @param string $displayName
     * @param string $message
     * @param int|null $parentId
     * @return int
     */
    public function insertComment(int $userId, string $displayName, string $message, ?int $parentId = null): int
    {
        $sql = "
            INSERT INTO wpk4_backend_diwali_comments
                (user_id, user_display_name, message, parent_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ";

        $this->execute($sql, [
            $userId,
            $displayName,
            $message,
            $parentId ?? 0,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Retrieve Diwali incentive comments within a date range
     *
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function getComments(string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT
                c.id,
                c.user_id,
                c.user_display_name,
                c.message,
                c.parent_id,
                c.created_at
            FROM wpk4_backend_diwali_comments c
            WHERE c.created_at BETWEEN ? AND ?
            ORDER BY c.created_at DESC
        ";

        return $this->query($sql, [
            $fromDate . ' 00:00:00',
            $toDate . ' 23:59:59',
        ]);
    }
}


