<?php
/**
 * Employee Profile Data Access Layer
 * Aggregates metrics previously calculated in employee profile templates.
 */

namespace App\DAL;

class EmployeeProfileDAL extends BaseDAL
{
    private static ?string $gauraMilesTable = null;
    private static array $gauraMilesColumns = [];

    private function resolveGauraMilesTable(): string
    {
        if (self::$gauraMilesTable !== null) {
            return self::$gauraMilesTable;
        }

        $rows = $this->query("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('wpk4_backend_gaura_miles', 'wpk4_backend_gaura_points')
            ORDER BY FIELD(TABLE_NAME, 'wpk4_backend_gaura_miles', 'wpk4_backend_gaura_points')
            LIMIT 1
        ");

        if (empty($rows)) {
            throw new \Exception('Gaura miles table not found (checked wpk4_backend_gaura_miles and wpk4_backend_gaura_points)', 500);
        }

        $table = $rows[0]['TABLE_NAME'];
        self::$gauraMilesTable = $table;
        self::$gauraMilesColumns[$table] = $this->fetchTableColumns($table);

        return self::$gauraMilesTable;
    }

    private function fetchTableColumns(string $table): array
    {
        $columns = [];
        $rows = $this->query("SHOW COLUMNS FROM {$table}");

        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $columns[] = $row['Field'];
            }
        }

        return $columns;
    }

    /**
     * Monthly performance metrics (aggregated by agent + month).
     */
    public function getMonthlyPerformance(): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(combined.call_date, '%Y-%m') AS month,
                combined.team_name,
                combined.agent_name,
                COALESCE(combined.tsr, 'N/A') AS tsr,
                COALESCE(combined.team_leader, 'N/A') AS team_leader,
                COALESCE(combined.sale_manager, 'N/A') AS sale_manager,
                COALESCE(combined.role, 'N/A') AS role,
                COUNT(DISTINCT combined.call_date) AS days_present,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                SUM(combined.pif_sale_made_count) AS pif_sale_made_count,
                SUM(combined.new_sale_made_count) AS new_sale_made_count,
                SUM(combined.rec_duration) AS rec_duration,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pax)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS conversion,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.new_sale_made_count)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS fcs,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.rec_duration)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS aht,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS conv_pif_percent,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.new_sale_made_count)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS fcs_percent,
                COALESCE(garland_scores.garland, 0) AS garland_score
            FROM (
                SELECT
                    a.agent_name,
                    a.call_date,
                    c.role,
                    c.tsr,
                    c.team_leader,
                    c.sale_manager,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.pif_sale_made_count,
                    a.new_sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                    AND c.status = 'active'
                    AND c.department = 'Sales'

                UNION ALL

                SELECT
                    a.agent_name,
                    a.order_date AS call_date,
                    c.role,
                    c.tsr,
                    c.team_leader,
                    c.sale_manager,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS pif_sale_made_count,
                    0 AS new_sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                    AND c.status = 'active'
                    AND c.department = 'Sales'
            ) AS combined
            LEFT JOIN (
                SELECT
                    cs.agent_name,
                    cs.team_name,
                    DATE_FORMAT(cs.recording_date, '%Y-%m') AS score_month,
                    ROUND(SUM(cs.compliant_count)/SUM(cs.audited_call)*100, 0) AS garland
                FROM wpk4_backend_harmony_audited_call_summary cs
                LEFT JOIN wpk4_backend_agent_codes ac
                    ON cs.recording_tsr = ac.tsr
                WHERE ac.status = 'active'
                  AND ac.department = 'Sales'
                GROUP BY cs.agent_name, cs.team_name, DATE_FORMAT(cs.recording_date, '%Y-%m')
            ) AS garland_scores
                ON combined.agent_name = garland_scores.agent_name
               AND combined.team_name = garland_scores.team_name
               AND DATE_FORMAT(combined.call_date, '%Y-%m') = garland_scores.score_month
            GROUP BY
                DATE_FORMAT(combined.call_date, '%Y-%m'),
                combined.team_name,
                combined.agent_name,
                combined.role,
                combined.tsr,
                combined.team_leader,
                combined.sale_manager,
                garland_score
            ORDER BY
                combined.agent_name ASC,
                month DESC
        ";

        return $this->query($sql);
    }

    /**
     * Retrieve agent metadata (tsr keyed).
     */
    public function getAgentMetadata(): array
    {
        $sql = "
            SELECT
                tsr,
                agent_name,
                team_name,
                team_leader,
                sale_manager,
                role,
                doj
            FROM wpk4_backend_agent_codes
        ";

        return $this->query($sql);
    }

    /**
     * Daily performance metrics grouped by date.
     */
    public function getDailyPerformance(): array
    {
        $sql = "
            SELECT
                combined.tsr,
                combined.agent_name,
                combined.team_name,
                DATE(combined.call_date) AS call_date,
                DATE_FORMAT(combined.call_date, '%Y-%m') AS month,
                DAY(combined.call_date) AS day,
                SUM(combined.pax) AS pax,
                SUM(combined.fit) AS fit,
                SUM(combined.pif) AS pif,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.gtib_count) AS gtib,
                SUM(combined.pif_sale_made_count) AS pif_sale_made_count,
                SUM(combined.new_sale_made_count) AS new_sale_made_count,
                SUM(combined.rec_duration) AS rec_duration,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS conversion,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif_sale_made_count)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS fcs,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.rec_duration)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS aht,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS conv_pif_percent,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.new_sale_made_count)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS fcs_percent,
                CASE
                    WHEN SUM(combined.gtib_count) > 0 THEN ROUND(SUM(combined.pif_sale_made_count)/SUM(combined.gtib_count), 2)
                    ELSE 0
                END AS fcs_pif_percent
            FROM (
                SELECT
                    a.agent_name,
                    a.call_date,
                    c.tsr,
                    a.team_name,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.gtib_count,
                    a.pif_sale_made_count,
                    a.new_sale_made_count,
                    a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                INNER JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                WHERE c.status = 'active'
                  AND c.role <> 'SM'
                  AND c.department = 'Sales'

                UNION ALL

                SELECT
                    a.agent_name,
                    a.order_date AS call_date,
                    c.tsr,
                    a.team_name,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    0 AS gtib_count,
                    0 AS pif_sale_made_count,
                    0 AS new_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                INNER JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                WHERE c.status = 'active'
                  AND c.role <> 'SM'
                  AND c.department = 'Sales'
            ) AS combined
            GROUP BY
                combined.call_date,
                combined.tsr,
                combined.agent_name,
                combined.team_name
        ";

        return $this->query($sql);
    }

    /**
     * Gaura Miles transactions with optional filtering.
     */
    public function getGauraMilesTransactions(array $filters = []): array
    {
        $table = $this->resolveGauraMilesTable();
        $columns = self::$gauraMilesColumns[$table] ?? [];

        $selectParts = [];
        if (in_array('transaction_id', $columns)) {
            $selectParts[] = 'transaction_id';
        } elseif (in_array('id', $columns)) {
            $selectParts[] = 'id AS transaction_id';
        } else {
            $selectParts[] = 'NULL AS transaction_id';
        }

        $selectParts[] = in_array('tsr', $columns) ? 'tsr' : 'NULL AS tsr';

        if (in_array('transaction_date', $columns)) {
            $selectParts[] = 'transaction_date';
        } elseif (in_array('call_date', $columns)) {
            $selectParts[] = 'call_date AS transaction_date';
        } else {
            $selectParts[] = 'NULL AS transaction_date';
        }

        if (in_array('call_date', $columns)) {
            $selectParts[] = 'call_date';
        } elseif (in_array('transaction_date', $columns)) {
            $selectParts[] = 'transaction_date AS call_date';
        } else {
            $selectParts[] = 'NULL AS call_date';
        }

        $selectParts[] = in_array('description', $columns) ? 'description' : 'NULL AS description';
        $selectParts[] = in_array('value', $columns) ? 'value' : 'NULL AS value';

        if (in_array('miles', $columns)) {
            $selectParts[] = 'miles';
        } elseif (in_array('points', $columns)) {
            $selectParts[] = 'points AS miles';
        } else {
            $selectParts[] = 'NULL AS miles';
        }

        if (in_array('transaction_type', $columns)) {
            $selectParts[] = 'transaction_type';
        } elseif (in_array('type', $columns)) {
            $selectParts[] = 'type AS transaction_type';
        }

        $selectList = implode(', ', $selectParts);

        $orderField = in_array('transaction_date', $columns) ? 'transaction_date' :
            (in_array('call_date', $columns) ? 'call_date' :
                (in_array('created_at', $columns) ? 'created_at' :
                    (in_array('updated_at', $columns) ? 'updated_at' : null)));

        $sql = "
            SELECT
                {$selectList}
            FROM {$table}
            WHERE 1 = 1
        ";
        $params = [];

        if (!empty($filters['tsr'])) {
            $sql .= " AND tsr = ? ";
            $params[] = $filters['tsr'];
        }

        if ($orderField) {
            $sql .= " ORDER BY tsr, {$orderField} DESC ";
        } else {
            $sql .= " ORDER BY tsr ";
        }

        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $sql .= " LIMIT {$limit} ";
        }

        return $this->query($sql, $params);
    }

    /**
     * Fun facts content keyed by employee name.
     */
    public function getFunFacts(): array
    {
        $sql = "
            SELECT
                employee_name AS agent_name,
                fun_facts
            FROM wpk4_employee_profiles_fun_facts
        ";

        return $this->query($sql);
    }
}


