<?php
/**
 * Total miles data access layer
 */

namespace App\DAL;

class TotalMilesDAL extends BaseDAL
{
    private static ?string $pointsTable = null;
    private static array $tableColumns = [];

    public function getTotals(): array
    {
        $table = $this->resolvePointsTable();
        $columns = self::$tableColumns[$table] ?? [];

        $pointsColumn = $this->resolveColumn($columns, ['points', 'miles'], 'Gaura points/miles');

        $sql = <<<SQL
SELECT
    m.tsr,
    ac.agent_name,
    SUM(m.{$pointsColumn}) AS total_miles
FROM {$table} m
LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
GROUP BY m.tsr, ac.agent_name
ORDER BY total_miles DESC
SQL;

        return $this->query($sql);
    }

    public function getTransactions(?string $tsr = null, ?string $agent = null): array
    {
        $table = $this->resolvePointsTable();
        $columns = self::$tableColumns[$table] ?? [];

        $pointsColumn = $this->resolveColumn($columns, ['points', 'miles'], 'Gaura points/miles');

        $selectParts = [];
        if (in_array('id', $columns, true)) {
            $selectParts[] = 'm.id';
        } elseif (in_array('auto_id', $columns, true)) {
            $selectParts[] = 'm.auto_id AS id';
        } else {
            $selectParts[] = 'NULL AS id';
        }

        $selectParts[] = in_array('tsr', $columns, true) ? 'm.tsr' : 'NULL AS tsr';
        $selectParts[] = 'ac.agent_name';
        $selectParts[] = "m.{$pointsColumn} AS points";

        if (in_array('transaction_type', $columns, true)) {
            $selectParts[] = 'm.transaction_type';
        } elseif (in_array('type', $columns, true)) {
            $selectParts[] = 'm.type AS transaction_type';
        } else {
            $selectParts[] = 'NULL AS transaction_type';
        }

        if (in_array('transaction_date', $columns, true)) {
            $selectParts[] = 'm.transaction_date';
            $orderDateColumn = 'm.transaction_date';
        } elseif (in_array('call_date', $columns, true)) {
            $selectParts[] = 'm.call_date AS transaction_date';
            $orderDateColumn = 'm.call_date';
        } elseif (in_array('created_at', $columns, true)) {
            $selectParts[] = 'm.created_at AS transaction_date';
            $orderDateColumn = 'm.created_at';
        } elseif (in_array('updated_at', $columns, true)) {
            $selectParts[] = 'm.updated_at AS transaction_date';
            $orderDateColumn = 'm.updated_at';
        } else {
            $selectParts[] = 'NULL AS transaction_date';
            $orderDateColumn = null;
        }

        $selectParts[] = in_array('reference', $columns, true) ? 'm.reference' : 'NULL AS reference';
        $selectParts[] = in_array('created_at', $columns, true) ? 'm.created_at' : 'NULL AS created_at';
        $selectParts[] = in_array('updated_at', $columns, true) ? 'm.updated_at' : 'NULL AS updated_at';

        $selectList = implode(', ', $selectParts);

        $sql = <<<SQL
SELECT
    {$selectList}
FROM {$table} m
LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
SQL;

        $conditions = [];
        $params = [];

        if ($tsr !== null && $tsr !== '' && in_array('tsr', $columns, true)) {
            $conditions[] = 'm.tsr = ?';
            $params[] = $tsr;
        }

        if ($agent !== null && $agent !== '') {
            $conditions[] = 'ac.agent_name LIKE ?';
            $params[] = '%' . $agent . '%';
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($orderDateColumn !== null) {
            $sql .= " ORDER BY m.tsr, {$orderDateColumn} DESC";
        } else {
            $sql .= ' ORDER BY m.tsr';
        }

        return $this->query($sql, $params);
    }

    private function resolvePointsTable(): string
    {
        if (self::$pointsTable !== null) {
            return self::$pointsTable;
        }

        $rows = $this->query("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('wpk4_backend_gaura_points', 'wpk4_backend_gaura_miles')
            ORDER BY FIELD(TABLE_NAME, 'wpk4_backend_gaura_points', 'wpk4_backend_gaura_miles')
            LIMIT 1
        ");

        if (empty($rows)) {
            throw new \Exception('Gaura points/miles table not found (checked wpk4_backend_gaura_points and wpk4_backend_gaura_miles)', 500);
        }

        $table = $rows[0]['TABLE_NAME'];
        self::$pointsTable = $table;
        self::$tableColumns[$table] = $this->fetchTableColumns($table);

        return $table;
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
     * @param array<int,string> $columns
     * @param array<int,string> $candidates
     */
    private function resolveColumn(array $columns, array $candidates, string $label): string
    {
        foreach ($candidates as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        throw new \Exception("{$label} column not found in Gaura points/miles table", 500);
    }
}

