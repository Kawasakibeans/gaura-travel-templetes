<?php
/**
 * Account Dashboard DAL
 * Data access for account dashboard aggregates and drill-down queries
 */

namespace App\DAL;

class AccountDashboardDAL extends BaseDAL
{
    /**
     * Payment & Ticket monthly summary
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentTicketSummary(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                DATE_FORMAT(l.created_at, '%Y-%m') AS ticket_month,
                SUM(l.debit) AS income,
                SUM(l.credit) AS expense,
                SUM(l.debit) - SUM(l.credit) AS net_profit
            FROM wpk4_backend_travel_payment_journal_line l
            JOIN wpk4_backend_ticket_reconciliation r ON l.pax_id = r.auto_id
            WHERE l.account_id IN ('2000','1100')
              AND DATE(l.created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE_FORMAT(l.created_at, '%Y-%m')
            ORDER BY ticket_month
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
    }

    /**
     * Deferred revenue monthly summary
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDeferredRevenueSummary(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                DATE_FORMAT(l.created_at, '%Y-%m') AS month,
                SUM(l.credit) AS income
            FROM wpk4_backend_travel_payment_journal_line l
            WHERE DATE(l.created_at) BETWEEN :start_date AND :end_date
              AND l.created_by NOT IN ('GDeal Cron','Ypsilon Cron','GDeals Payment Cron')
              AND l.account_id = '1200'
              AND EXISTS (
                    SELECT 1
                    FROM wpk4_backend_travel_payment_journal_line b
                    WHERE b.order_id = l.order_id
                      AND b.account_id = '1200'
                      AND CAST(b.credit AS DECIMAL(18,2)) > 0
                )
              AND NOT EXISTS (
                    SELECT 1
                    FROM wpk4_backend_travel_payment_journal_line x
                    JOIN wpk4_backend_ticket_reconciliation r ON x.pax_id = r.auto_id
                    WHERE x.id = l.id
                )
            GROUP BY DATE_FORMAT(l.created_at, '%Y-%m')
            ORDER BY month
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
    }

    /**
     * Trade debtors monthly summary
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTradeDebtorsSummary(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                e.journal_date,
                l.order_id,
                SUM(l.debit) AS cost_of_sales,
                SUM(l.credit) AS revenue,
                SUM(l.debit) - SUM(l.credit) AS balance
            FROM wpk4_backend_travel_payment_journal_line l
            INNER JOIN wpk4_backend_travel_payment_journal_entry e ON l.journal_id = e.id
            WHERE e.source_type = 'ticket'
              AND l.account_id = '5000'
              AND DATE(e.journal_date) BETWEEN :start_date AND :end_date
              AND NOT EXISTS (
                    SELECT 1
                    FROM wpk4_backend_travel_payment_journal_line p
                    WHERE p.order_id = l.order_id
                      AND p.account_id = '1200'
                      AND CAST(p.credit AS DECIMAL(10,2)) <> 0
                )
            GROUP BY e.journal_date, l.order_id
            ORDER BY e.journal_date
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
    }

    /**
     * Payment & Ticket drill-down for a specific month range
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentTicketDrilldown(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                l.order_id,
                SUM(l.debit) AS income,
                SUM(l.credit) AS expense,
                SUM(l.debit) - SUM(l.credit) AS net_profit,
                MIN(l.created_at) AS first_txn_at,
                MAX(l.created_at) AS last_txn_at
            FROM wpk4_backend_travel_payment_journal_line l
            JOIN wpk4_backend_ticket_reconciliation r ON l.pax_id = r.auto_id
            WHERE l.account_id IN ('2000','1100')
              AND DATE(l.created_at) BETWEEN :start_date AND :end_date
            GROUP BY l.order_id
            ORDER BY net_profit DESC, l.order_id ASC
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
    }

    /**
     * Deferred revenue drill-down for a month range
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDeferredRevenueDrilldown(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                l.order_id,
                SUM(l.credit) AS income,
                MIN(l.created_at) AS first_txn_at,
                MAX(l.created_at) AS last_txn_at
            FROM wpk4_backend_travel_payment_journal_line l
            WHERE DATE(l.created_at) BETWEEN :start_date AND :end_date
              AND l.created_by NOT IN ('GDeal Cron','Ypsilon Cron','GDeals Payment Cron')
              AND l.account_id = '1200'
              AND EXISTS (
                    SELECT 1 
                    FROM wpk4_backend_travel_payment_journal_line b
                    WHERE b.order_id = l.order_id
                      AND b.account_id = '1200'
                      AND CAST(b.credit AS DECIMAL(18,2)) > 0
                )
              AND NOT EXISTS (
                    SELECT 1
                    FROM wpk4_backend_travel_payment_journal_line x
                    JOIN wpk4_backend_ticket_reconciliation r ON x.pax_id = r.auto_id
                    WHERE x.id = l.id
                )
            GROUP BY l.order_id
            ORDER BY income DESC, l.order_id ASC
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
    }

    /**
     * Trade debtors journal entry drill-down
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTradeDebtorsDrilldown(string $orderId, string $journalDate): array
    {
        $sql = "
            SELECT
                l.account_id,
                l.debit,
                l.credit,
                l.created_at
            FROM wpk4_backend_travel_payment_journal_line l
            INNER JOIN wpk4_backend_travel_payment_journal_entry e ON l.journal_id = e.id
            WHERE l.order_id = :order_id
              AND DATE(e.journal_date) = :journal_date
            ORDER BY l.created_at ASC, l.id ASC
        ";

        return $this->query($sql, [
            ':order_id' => $orderId,
            ':journal_date' => $journalDate,
        ]);
    }

    /**
     * Order ledger drill-down (optionally date bounded)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrderLedger(string $orderId, ?string $startDate, ?string $endDate): array
    {
        $params = [
            ':order_id' => $orderId,
        ];

        $dateClause = '';
        if ($startDate !== null && $endDate !== null) {
            $dateClause = " AND DATE(l.created_at) BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }

        $sql = "
            SELECT
                e.journal_date,
                l.journal_id,
                a.account_subtype AS account_id,
                a.account_subtype AS account_name,
                CAST(l.debit AS DECIMAL(18,2)) AS debit,
                CAST(l.credit AS DECIMAL(18,2)) AS credit,
                l.created_at
            FROM wpk4_backend_travel_payment_journal_line l
            LEFT JOIN wpk4_backend_travel_payment_journal_entry e ON l.journal_id = e.id
            LEFT JOIN wpk4_backend_travel_payment_chart_of_account a ON a.account_number = l.account_id
            WHERE l.order_id = :order_id
            {$dateClause}
            ORDER BY l.created_at ASC, l.id ASC
        ";

        return $this->query($sql, $params);
    }
}

