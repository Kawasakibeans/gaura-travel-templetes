<?php
/**
 * Data access for schedule change cases.
 */

namespace App\DAL;

class ScheduleChangeCasesDAL extends BaseDAL
{
    private string $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = ($_ENV['DB_TABLE_PREFIX'] ?? 'wpk4_') . 'schedule_change_cases';
    }

    /**
     * @param string $sql
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function fetchCases(string $sql, array $params): array
    {
        return $this->query($sql, $params);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insertCase(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            $placeholders
        );

        $this->execute($sql, array_values($data));
        return (int)$this->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateCase(int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = ?',
            $this->table,
            implode(', ', $sets)
        );

        $this->execute($sql, $params);
    }

    /**
     * @return array<int,string>
     */
    public function getDistinctAgents(): array
    {
        $sql = "
            SELECT DISTINCT added_by
            FROM {$this->table}
            WHERE COALESCE(added_by, '') <> ''
            ORDER BY added_by ASC
        ";

        return array_map(static fn ($row) => (string)$row['added_by'], $this->query($sql));
    }
}

