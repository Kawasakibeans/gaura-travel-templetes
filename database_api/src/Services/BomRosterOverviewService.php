<?php
/**
 * Service layer for BOM roster overview.
 */

namespace App\Services;

use App\DAL\BomRosterOverviewDAL;
use DateTime;
use Exception;

class BomRosterOverviewService
{
    private BomRosterOverviewDAL $dal;

    public function __construct()
    {
        $this->dal = new BomRosterOverviewDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getOverview(array $filters): array
    {
        $month = isset($filters['month']) ? (int)$filters['month'] : (int)date('n');
        $year = isset($filters['year']) ? (int)$filters['year'] : (int)date('Y');
        if ($month < 1 || $month > 12) {
            throw new Exception('month must be between 1 and 12', 400);
        }

        $monthName = DateTime::createFromFormat('!m', (string)$month)->format('F');
        $shiftFilter = isset($filters['shift_time']) ? trim((string)$filters['shift_time']) : null;
        if ($shiftFilter === '') {
            $shiftFilter = null;
        }

        $rosters = $this->dal->getRosterForMonth($monthName, $year);
        if ($shiftFilter !== null) {
            $rosters = array_values(array_filter($rosters, static function (array $row) use ($shiftFilter) {
                return isset($row['shift_time']) && (string)$row['shift_time'] === $shiftFilter;
            }));
        }

        $daysInMonth = (int)DateTime::createFromFormat('!Y-n', "{$year}-{$month}")->format('t');

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => $monthName,
            'days_in_month' => $daysInMonth,
            'shift_time' => $shiftFilter,
            'shift_times' => $this->dal->getDistinctShiftTimes(),
            'rosters' => $rosters,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updateEmployeeShift(array $payload): array
    {
        $employeeCode = isset($payload['employee_code']) ? trim((string)$payload['employee_code']) : '';
        $day = isset($payload['day']) ? (int)$payload['day'] : 0;
        $month = isset($payload['month']) ? (int)$payload['month'] : (int)date('n');
        $year = isset($payload['year']) ? (int)$payload['year'] : (int)date('Y');
        $newShift = isset($payload['new_shift']) ? trim((string)$payload['new_shift']) : '';

        if ($employeeCode === '') {
            throw new Exception('employee_code is required', 400);
        }
        if ($day < 1 || $day > 31) {
            throw new Exception('day must be between 1 and 31', 400);
        }
        if ($newShift === '') {
            throw new Exception('new_shift is required', 400);
        }

        $monthName = DateTime::createFromFormat('!m', (string)$month)->format('F');
        $column = $this->buildDayColumn($day);

        $this->dal->updateEmployeeShift($employeeCode, $column, $monthName, $year, $newShift);

        return [
            'status' => 'success',
            'employee_code' => $employeeCode,
            'column' => $column,
            'new_shift' => $newShift,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function bulkUpdate(array $payload): array
    {
        $employeeCode = isset($payload['employee_code']) ? trim((string)$payload['employee_code']) : '';
        $newStatus = isset($payload['new_status']) ? strtoupper(trim((string)$payload['new_status'])) : '';
        $day = isset($payload['day']) ? (int)$payload['day'] : 0;
        $month = isset($payload['month']) ? (int)$payload['month'] : (int)date('n');
        $year = isset($payload['year']) ? (int)$payload['year'] : (int)date('Y');

        if ($employeeCode === '') {
            throw new Exception('employee_code is required', 400);
        }
        if ($day < 1 || $day > 31) {
            throw new Exception('day must be between 1 and 31', 400);
        }
        if ($newStatus === '') {
            throw new Exception('new_status is required', 400);
        }

        $monthName = DateTime::createFromFormat('!m', (string)$month)->format('F');
        $daysInMonth = (int)DateTime::createFromFormat('!Y-n', "{$year}-{$month}")->format('t');

        if ($newStatus === 'RDO') {
            $this->applyRdoRotation($employeeCode, $monthName, $year, $day, $daysInMonth);
            $effect = 'rdo';
        } else {
            $this->applyStatusRotation($employeeCode, $monthName, $year, $newStatus, $daysInMonth);
            $effect = 'status';
        }

        return [
            'status' => 'success',
            'employee_code' => $employeeCode,
            'effect' => $effect,
        ];
    }

    private function applyRdoRotation(string $employeeCode, string $monthName, int $year, int $pivotDay, int $daysInMonth): void
    {
        $pivotDate = DateTime::createFromFormat('!j F Y', "{$pivotDay} {$monthName} {$year}");
        $targetWeekday = $pivotDate->format('w'); // 0 (Sun) - 6 (Sat)

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = DateTime::createFromFormat('!j F Y', "{$day} {$monthName} {$year}");
            if ($date->format('w') === $targetWeekday) {
                $this->dal->updateEmployeeShift($employeeCode, $this->buildDayColumn($day), $monthName, $year, 'RDO');
            }
        }
    }

    private function applyStatusRotation(string $employeeCode, string $monthName, int $year, string $status, int $daysInMonth): void
    {
        $row = $this->dal->getRosterRow($employeeCode, $monthName, $year);
        if (!$row) {
            throw new Exception('Roster entry not found for employee/month/year', 404);
        }

        $protected = ['RDO', 'PL', 'RH', 'LEAVE', 'CONVERT'];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $column = $this->buildDayColumn($day);
            $current = isset($row[$column]) ? strtoupper(trim((string)$row[$column])) : '';
            if ($current === '' || !in_array($current, $protected, true)) {
                $this->dal->updateEmployeeShift($employeeCode, $column, $monthName, $year, $status);
            }
        }
    }

    private function buildDayColumn(int $day): string
    {
        return 'day_' . $day;
    }
}

