<?php
/**
 * Finance Console Service
 * Provides P&L, balance sheet, and journal entry business logic
 */

namespace App\Services;

use App\DAL\FinanceConsoleDAL;
use Exception;

class FinanceConsoleService
{
    private FinanceConsoleDAL $dal;

    public function __construct()
    {
        $this->dal = new FinanceConsoleDAL();
    }

    /**
     * Profit & Loss report by month
     *
     * @param array<string, mixed> $filters
     */
    public function getProfitAndLoss(array $filters): array
    {
        $start = $filters['start'] ?? null;
        $end = $filters['end'] ?? null;
        $version = strtolower((string)($filters['version'] ?? 'v1'));

        if ($start !== null) {
            $this->assertMonth($start, 'start');
        }
        if ($end !== null) {
            $this->assertMonth($end, 'end');
        }

        if ($start !== null && $end !== null && $start > $end) {
            throw new Exception('start month must be before or equal to end month', 400);
        }

        $rows = $this->dal->getProfitAndLoss($start, $end);

        $records = [];
        $summary = ['income_total' => 0.0, 'expense_total' => 0.0, 'net_profit' => 0.0];

        foreach ($rows as $row) {
            $record = [
                'period' => $row['period'],
                'income_total' => (float)$row['income_total'],
                'expense_total' => (float)$row['expense_total'],
                'net_profit' => (float)$row['net_profit'],
            ];
            $records[] = $record;

            $summary['income_total'] += $record['income_total'];
            $summary['expense_total'] += $record['expense_total'];
            $summary['net_profit'] += $record['net_profit'];
        }

        foreach ($summary as $key => $value) {
            $summary[$key] = round($value, 2);
        }

        return [
            'query' => [
                'start' => $start,
                'end' => $end,
                'version' => $version,
            ],
            'summary' => $summary,
            'records' => $records,
        ];
    }

    /**
     * Balance sheet snapshot for a month
     */
    public function getBalanceSheet(string $asOf): array
    {
        $this->assertMonth($asOf, 'as_of');

        $data = $this->dal->getBalanceSheet($asOf);

        return [
            'query' => ['as_of' => $asOf],
            'totals' => $data['totals'],
            'assets' => $data['assets'],
            'liabilities' => $data['liabilities'],
            'equity' => $data['equity'],
        ];
    }

    /**
     * Journal entries with pagination
     *
     * @param array<string, mixed> $filters
     */
    public function getJournalEntries(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if ($startDate === null) {
            throw new Exception('start_date is required', 400);
        }
        if ($endDate === null) {
            throw new Exception('end_date is required', 400);
        }

        $this->assertDate($startDate, 'start_date');
        $this->assertDate($endDate, 'end_date');

        if ($startDate > $endDate) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        $limit = $filters['limit'] ?? 200;
        $offset = $filters['offset'] ?? 0;
        $limit = is_numeric($limit) ? max(1, min(1000, (int)$limit)) : 200;
        $offset = is_numeric($offset) ? max(0, (int)$offset) : 0;

        $rows = $this->dal->getJournalEntries($startDate, $endDate, $limit, $offset);
        $total = $this->dal->countJournalEntries($startDate, $endDate);

        return [
            'query' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'meta' => [
                'total_records' => $total,
                'returned_records' => count($rows),
                'has_more' => ($offset + count($rows)) < $total,
            ],
            'records' => $rows,
        ];
    }

    /**
     * Validate YYYY-MM format
     */
    private function assertMonth(string $value, string $label): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            throw new Exception("{$label} must be in YYYY-MM format", 400);
        }
    }

    /**
     * Validate YYYY-MM-DD format
     */
    private function assertDate(string $value, string $label): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new Exception("{$label} must be in YYYY-MM-DD format", 400);
        }
    }
}

