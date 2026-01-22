<?php
/**
 * Account Dashboard Service
 * Provides business logic for dashboard aggregates and drilldowns
 */

namespace App\Services;

use App\DAL\AccountDashboardDAL;
use Exception;

class AccountDashboardService
{
    private AccountDashboardDAL $dal;

    public function __construct()
    {
        $this->dal = new AccountDashboardDAL();
    }

    /**
     * Summary aggregates (payment ticket, deferred revenue, trade debtors)
     *
     * @param array<string, mixed> $filters
     */
    public function getSummary(array $filters): array
    {
        $dateRange = $this->resolveSummaryRange($filters);

        $paymentTicket = $this->dal->getPaymentTicketSummary($dateRange['start_date'], $dateRange['end_date']);
        $deferredRevenue = $this->dal->getDeferredRevenueSummary($dateRange['start_date'], $dateRange['end_date']);
        $tradeDebtors = $this->dal->getTradeDebtorsSummary($dateRange['start_date'], $dateRange['end_date']);

        $paymentTotals = [
            'income' => 0.0,
            'expense' => 0.0,
            'net_profit' => 0.0,
        ];
        foreach ($paymentTicket as $row) {
            $paymentTotals['income'] += (float)$row['income'];
            $paymentTotals['expense'] += (float)$row['expense'];
            $paymentTotals['net_profit'] += (float)$row['net_profit'];
        }
        foreach ($paymentTotals as $key => $value) {
            $paymentTotals[$key] = round($value, 2);
        }

        $deferredTotals = ['income' => 0.0];
        foreach ($deferredRevenue as $row) {
            $deferredTotals['income'] += (float)$row['income'];
        }
        $deferredTotals['income'] = round($deferredTotals['income'], 2);

        $tradeTotals = [
            'cost_of_sales' => 0.0,
            'revenue' => 0.0,
            'balance' => 0.0,
        ];
        foreach ($tradeDebtors as $row) {
            $tradeTotals['cost_of_sales'] += (float)$row['cost_of_sales'];
            $tradeTotals['revenue'] += (float)$row['revenue'];
            $tradeTotals['balance'] += (float)$row['balance'];
        }
        foreach ($tradeTotals as $key => $value) {
            $tradeTotals[$key] = round($value, 2);
        }

        return [
            'query' => $dateRange,
            'payment_ticket' => [
                'totals' => $paymentTotals,
                'records' => $this->castNumeric($paymentTicket, ['income', 'expense', 'net_profit']),
            ],
            'deferred_revenue' => [
                'totals' => $deferredTotals,
                'records' => $this->castNumeric($deferredRevenue, ['income']),
            ],
            'trade_debtors' => [
                'totals' => $tradeTotals,
                'records' => $this->castNumeric($tradeDebtors, ['cost_of_sales', 'revenue', 'balance']),
            ],
        ];
    }

    /**
     * Payment & Ticket drilldown by month
     */
    public function getPaymentTicketDrilldown(string $month): array
    {
        $range = $this->monthToRange($month);

        $rows = $this->dal->getPaymentTicketDrilldown($range['start_date'], $range['end_date']);

        $totals = [
            'income' => 0.0,
            'expense' => 0.0,
            'net_profit' => 0.0,
        ];
        foreach ($rows as $row) {
            $totals['income'] += (float)$row['income'];
            $totals['expense'] += (float)$row['expense'];
            $totals['net_profit'] += (float)$row['net_profit'];
        }
        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return [
            'query' => [
                'month' => $month,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
            ],
            'totals' => $totals,
            'records' => $this->castNumeric($rows, ['income', 'expense', 'net_profit']),
        ];
    }

    /**
     * Deferred revenue drilldown by month
     */
    public function getDeferredRevenueDrilldown(string $month): array
    {
        $range = $this->monthToRange($month);

        $rows = $this->dal->getDeferredRevenueDrilldown($range['start_date'], $range['end_date']);

        $totalIncome = 0.0;
        foreach ($rows as $row) {
            $totalIncome += (float)$row['income'];
        }

        return [
            'query' => [
                'month' => $month,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
            ],
            'totals' => ['income' => round($totalIncome, 2)],
            'records' => $this->castNumeric($rows, ['income']),
        ];
    }

    /**
     * Trade debtors drilldown for an order and journal date
     */
    public function getTradeDebtorsDrilldown(string $orderId, string $journalDate): array
    {
        $this->assertDate($journalDate, 'journal_date');
        if ($orderId === '') {
            throw new Exception('order_id is required', 400);
        }

        $rows = $this->dal->getTradeDebtorsDrilldown($orderId, $journalDate);

        $totals = ['debit' => 0.0, 'credit' => 0.0];
        foreach ($rows as $row) {
            $totals['debit'] += (float)$row['debit'];
            $totals['credit'] += (float)$row['credit'];
        }

        return [
            'query' => [
                'order_id' => $orderId,
                'journal_date' => $journalDate,
            ],
            'totals' => [
                'debit' => round($totals['debit'], 2),
                'credit' => round($totals['credit'], 2),
            ],
            'records' => $this->castNumeric($rows, ['debit', 'credit']),
        ];
    }

    /**
     * Order ledger drilldown
     */
    public function getOrderLedger(string $orderId, ?string $startDate, ?string $endDate): array
    {
        if ($orderId === '') {
            throw new Exception('order_id is required', 400);
        }

        if ($startDate !== null) {
            $this->assertDate($startDate, 'start');
        }
        if ($endDate !== null) {
            $this->assertDate($endDate, 'end');
        }
        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw new Exception('start must be before or equal to end', 400);
        }

        $rows = $this->dal->getOrderLedger($orderId, $startDate, $endDate);

        $totals = ['debit' => 0.0, 'credit' => 0.0];
        foreach ($rows as $row) {
            $totals['debit'] += (float)$row['debit'];
            $totals['credit'] += (float)$row['credit'];
        }

        return [
            'query' => [
                'order_id' => $orderId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'totals' => [
                'debit' => round($totals['debit'], 2),
                'credit' => round($totals['credit'], 2),
            ],
            'records' => $this->castNumeric($rows, ['debit', 'credit']),
        ];
    }

    /**
     * Convert numeric columns to float with rounding
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     * @return array<int, array<string, mixed>>
     */
    private function castNumeric(array $rows, array $columns): array
    {
        return array_map(function ($row) use ($columns) {
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = round((float)$row[$column], 2);
                }
            }
            return $row;
        }, $rows);
    }

    /**
     * Resolve summary date range with defaults
     *
     * @param array<string, mixed> $filters
     * @return array{start_date: string, end_date: string}
     */
    private function resolveSummaryRange(array $filters): array
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if ($start !== null) {
            $this->assertDate($start, 'start_date');
        }
        if ($end !== null) {
            $this->assertDate($end, 'end_date');
        }

        if ($start === null || $end === null) {
            $today = new \DateTimeImmutable('today');
            $currentYear = (int)$today->format('Y');
            $currentMonth = (int)$today->format('n');

            if ($start === null) {
                if ($currentMonth >= 7) {
                    $start = sprintf('%04d-07-01', $currentYear);
                } else {
                    $start = sprintf('%04d-07-01', $currentYear - 1);
                }
            }
            if ($end === null) {
                $end = $today->format('Y-m-d');
            }
        }

        if ($start > $end) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    /**
     * Convert month string to first/last day range
     *
     * @return array{start_date: string, end_date: string}
     */
    private function monthToRange(string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new Exception('month must be in YYYY-MM format', 400);
        }

        $firstDay = \DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01');
        if (!$firstDay) {
            throw new Exception('Invalid month provided', 400);
        }

        $startDate = $firstDay->format('Y-m-d');
        $endDate = $firstDay->modify('last day of this month')->format('Y-m-d');

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
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

