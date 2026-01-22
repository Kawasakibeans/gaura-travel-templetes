<?php
/**
 * Data access for BOM roster overview.
 */

namespace App\DAL;

class BomRosterOverviewDAL extends BaseDAL
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRosterForMonth(string $monthName, int $year): array
    {
        $sql = "
            SELECT *
            FROM {$this->prefix()}backend_employee_roster_bom
            WHERE month = ? AND year = ?
        ";

        return $this->query($sql, [$monthName, $year]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getRosterRow(string $employeeCode, string $monthName, int $year): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->prefix()}backend_employee_roster_bom
            WHERE employee_code = ? AND month = ? AND year = ?
            LIMIT 1
        ";

        return $this->queryOne($sql, [$employeeCode, $monthName, $year]);
    }

    /**
     * @return array<int,string>
     */
    public function getDistinctShiftTimes(): array
    {
        $sql = "
            SELECT DISTINCT shift_time
            FROM {$this->prefix()}backend_employee_roster_bom
            WHERE COALESCE(shift_time, '') <> ''
            ORDER BY shift_time
        ";

        $rows = $this->query($sql);

        return array_map(
            static fn ($row) => (string)$row['shift_time'],
            $rows
        );
    }

    public function updateEmployeeShift(string $employeeCode, string $column, string $monthName, int $year, string $newShift): void
    {
        $sql = "
            UPDATE {$this->prefix()}backend_employee_roster_bom
            SET {$column} = ?
            WHERE employee_code = ? AND month = ? AND year = ?
        ";

        $this->execute($sql, [$newShift, $employeeCode, $monthName, $year]);
    }

    public function updateEmployeeShiftTime(string $employeeCode, string $monthName, int $year, string $shiftTime): void
    {
        $sql = "
            UPDATE {$this->prefix()}backend_employee_roster_bom
            SET shift_time = ?
            WHERE employee_code = ? AND month = ? AND year = ?
        ";

        $this->execute($sql, [$shiftTime, $employeeCode, $monthName, $year]);
    }

    public function executeRawUpdate(string $sql, array $params = []): void
    {
        $this->execute($sql, $params);
    }

    private function prefix(): string
    {
        return $_ENV['DB_TABLE_PREFIX'] ?? 'wpk4_';
    }
}

