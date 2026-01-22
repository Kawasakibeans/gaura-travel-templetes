<?php
/**
 * Finance Console DAL
 * Provides database access for P&L, balance sheet, and journal endpoints
 */

namespace App\DAL;

use Exception;

class FinanceConsoleDAL extends BaseDAL
{
    private const JOURNAL_LINE_TABLE = 'wpk4_backend_travel_payment_journal_line';
    private const JOURNAL_ENTRY_TABLE = 'wpk4_backend_travel_payment_journal_entry';
    private const COA_CANDIDATES = [
        'wpk4_backend_travel_payment_chart_of_account',
        'wpk4_backend_travel_payment_chat_of_account',
    ];
    private const DATE_COLUMN_CANDIDATES = [
        'journal_date',
        'posted_at',
        'created_at',
        'entry_date',
        'date',
    ];
    private const JOIN_COLUMN_COMBINATIONS = [
        ['line' => 'account_id', 'coa' => 'id'],
        ['line' => 'account_id', 'coa' => 'account_number'],
        ['line' => 'account_number', 'coa' => 'account_number'],
        ['line' => 'account_number', 'coa' => 'id'],
    ];

    private ?string $coaTable = null;
    private ?string $journalDateColumn = null;
    private ?array $joinColumns = null; // ['line' => '...', 'coa' => '...']

    /**
     * Retrieve profit & loss data grouped by month
     *
     * @param string|null $startMonth YYYY-MM
     * @param string|null $endMonth YYYY-MM
     * @return array<int, array<string, mixed>>
     */
    public function getProfitAndLoss(?string $startMonth, ?string $endMonth): array
    {
        $this->ensureSchema();

        $dateColumn = $this->journalDateColumn;
        $join = $this->joinColumns;
        $coaTable = $this->coaTable;

        $classExpr = $this->normalizeClassSql('a.classification');

        $sql = "
            SELECT
                DATE_FORMAT(l.`{$dateColumn}`,'%Y-%m') AS period,
                ROUND(SUM(CASE WHEN {$classExpr}='INCOME' THEN COALESCE(l.credit,0) ELSE 0 END), 2) AS income_total,
                ROUND(SUM(CASE WHEN {$classExpr}='EXPENSE' THEN COALESCE(l.debit,0) ELSE 0 END), 2) AS expense_total,
                ROUND(
                    SUM(CASE WHEN {$classExpr}='INCOME' THEN COALESCE(l.credit,0) ELSE 0 END) -
                    SUM(CASE WHEN {$classExpr}='EXPENSE' THEN COALESCE(l.debit,0) ELSE 0 END)
                , 2) AS net_profit
            FROM " . self::JOURNAL_LINE_TABLE . " l
            JOIN {$coaTable} a ON a.`{$join['coa']}` = l.`{$join['line']}`
            WHERE 1=1
        ";

        $params = [];

        if ($startMonth && $endMonth) {
            $sql .= " AND DATE_FORMAT(l.`{$dateColumn}`,'%Y-%m') BETWEEN :start_month AND :end_month";
            $params[':start_month'] = $startMonth;
            $params[':end_month'] = $endMonth;
        } elseif ($startMonth) {
            $sql .= " AND DATE_FORMAT(l.`{$dateColumn}`,'%Y-%m') >= :start_month";
            $params[':start_month'] = $startMonth;
        } elseif ($endMonth) {
            $sql .= " AND DATE_FORMAT(l.`{$dateColumn}`,'%Y-%m') <= :end_month";
            $params[':end_month'] = $endMonth;
        }

        $sql .= " GROUP BY DATE_FORMAT(l.`{$dateColumn}`,'%Y-%m') ORDER BY period ASC";

        return $this->query($sql, $params);
    }

    /**
     * Retrieve balance sheet snapshot for the given month
     *
     * @param string $asOf YYYY-MM
     * @return array<string, mixed>
     */
    public function getBalanceSheet(string $asOf): array
    {
        $this->ensureSchema();

        $dateColumn = $this->journalDateColumn;
        $join = $this->joinColumns;
        $coaTable = $this->coaTable;

        $classExpr = $this->normalizeClassSql('a.classification');

        $sql = "
            WITH joined AS (
                SELECT
                    a.`account_number`,
                    a.`name` AS account_name,
                    {$classExpr} AS norm_class,
                    COALESCE(l.debit,0) AS debit,
                    COALESCE(l.credit,0) AS credit
                FROM " . self::JOURNAL_LINE_TABLE . " l
                JOIN {$coaTable} a ON a.`{$join['coa']}` = l.`{$join['line']}`
                WHERE l.`{$dateColumn}` <= LAST_DAY(STR_TO_DATE(:as_of, '%Y-%m'))
            ),
            rolled AS (
                SELECT
                    account_number,
                    account_name,
                    norm_class,
                    ROUND(SUM(
                        CASE
                            WHEN norm_class = 'Asset' THEN (debit - credit)
                            WHEN norm_class IN ('Liability','Equity') THEN (credit - debit)
                            ELSE 0
                        END
                    ), 2) AS balance
                FROM joined
                GROUP BY account_number, account_name, norm_class
            )
            SELECT * FROM rolled
            WHERE balance <> 0
            ORDER BY FIELD(norm_class,'Asset','Liability','Equity'), account_number
        ";

        $rows = $this->query($sql, [':as_of' => $asOf]);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiab = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $row) {
            $balance = (float)$row['balance'];
            switch ($row['norm_class']) {
                case 'Asset':
                    $assets[] = $row;
                    $totalAssets += $balance;
                    break;
                case 'Liability':
                    $liabilities[] = $row;
                    $totalLiab += $balance;
                    break;
                case 'Equity':
                    $equity[] = $row;
                    $totalEquity += $balance;
                    break;
            }
        }

        return [
            'totals' => [
                'assets' => round($totalAssets, 2),
                'liabilities' => round($totalLiab, 2),
                'equity' => round($totalEquity, 2),
                'liabilities_plus_equity' => round($totalLiab + $totalEquity, 2),
            ],
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
        ];
    }

    /**
     * Retrieve journal entries with pagination
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array<int, array<string, mixed>>
     */
    public function getJournalEntries(string $startDate, string $endDate, int $limit, int $offset): array
    {
        $this->ensureSchema();

        $dateColumn = $this->journalDateColumn;
        $join = $this->joinColumns;
        $coaTable = $this->coaTable;

        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT
                l.`{$dateColumn}` AS created_at,
                l.`{$join['line']}` AS account_id,
                a.`name` AS account_name,
                a.`classification` AS classification,
                ROUND(COALESCE(l.debit, 0), 2) AS debit,
                ROUND(COALESCE(l.credit, 0), 2) AS credit
            FROM " . self::JOURNAL_LINE_TABLE . " l
            JOIN {$coaTable} a ON a.`{$join['coa']}` = l.`{$join['line']}`
            WHERE l.`{$dateColumn}` >= STR_TO_DATE(:start_date, '%Y-%m-%d')
              AND l.`{$dateColumn}` <= LEAST(
                    STR_TO_DATE(:end_date_1, '%Y-%m-%d'),
                    LAST_DAY(STR_TO_DATE(:end_date_2, '%Y-%m-%d'))
                )
            ORDER BY l.`{$dateColumn}` ASC, account_id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->query($sql, [
            ':start_date' => $startDate,
            ':end_date_1' => $endDate,
            ':end_date_2' => $endDate,
        ]);
    }

    /**
     * Count journal entries for pagination metadata
     */
    public function countJournalEntries(string $startDate, string $endDate): int
    {
        $this->ensureSchema();

        $dateColumn = $this->journalDateColumn;

        $sql = "
            SELECT COUNT(*) AS total
            FROM " . self::JOURNAL_LINE_TABLE . "
            WHERE `{$dateColumn}` >= STR_TO_DATE(:start_date, '%Y-%m-%d')
              AND `{$dateColumn}` <= LEAST(
                    STR_TO_DATE(:end_date_1, '%Y-%m-%d'),
                    LAST_DAY(STR_TO_DATE(:end_date_2, '%Y-%m-%d'))
                )
        ";

        $row = $this->queryOne($sql, [
            ':start_date' => $startDate,
            ':end_date_1' => $endDate,
            ':end_date_2' => $endDate,
        ]);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Ensure schema detection has been performed
     */
    private function ensureSchema(): void
    {
        if ($this->coaTable === null) {
            $this->coaTable = $this->pickCoaTable();
        }
        if ($this->journalDateColumn === null) {
            $this->journalDateColumn = $this->pickJournalDateColumn();
        }
        if ($this->joinColumns === null) {
            $this->joinColumns = $this->pickJoinColumns();
        }
    }

    /**
     * Attempt to resolve the chart of account table
     */
    private function pickCoaTable(): string
    {
        foreach (self::COA_CANDIDATES as $table) {
            try {
                $this->query("SELECT 1 FROM {$table} LIMIT 1");
                return $table;
            } catch (Exception $e) {
                // Try next
            }
        }

        // Fallback: scan information_schema for tables containing "chart_of_account" or variant
        $sql = "
            SELECT TABLE_NAME 
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND (TABLE_NAME LIKE '%chart%of%account%' OR TABLE_NAME LIKE '%chat%of%account%')
            ORDER BY TABLE_NAME
        ";

        $tables = $this->query($sql);
        foreach ($tables as $row) {
            $table = $row['TABLE_NAME'];
            try {
                $this->query("SELECT 1 FROM {$table} LIMIT 1");
                return $table;
            } catch (Exception $e) {
                // Continue
            }
        }

        throw new Exception('Unable to locate chart of account table');
    }

    /**
     * Attempt to find a usable date column in the journal line table
     */
    private function pickJournalDateColumn(): string
    {
        foreach (self::DATE_COLUMN_CANDIDATES as $column) {
            try {
                $this->query("SELECT `{$column}` FROM " . self::JOURNAL_LINE_TABLE . " LIMIT 1");
                $range = $this->queryOne("
                    SELECT MIN(`{$column}`) AS min_date, MAX(`{$column}`) AS max_date
                    FROM " . self::JOURNAL_LINE_TABLE
                );
                if (!empty($range['min_date']) || !empty($range['max_date'])) {
                    return $column;
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        // Fallback: inspect information_schema for any date/datetime column
        $sql = "
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '" . self::JOURNAL_LINE_TABLE . "'
              AND DATA_TYPE IN ('date','datetime','timestamp')
            ORDER BY ORDINAL_POSITION
            LIMIT 1
        ";

        $row = $this->queryOne($sql);
        if (!empty($row['COLUMN_NAME'])) {
            return $row['COLUMN_NAME'];
        }

        throw new Exception('Unable to locate a usable journal date column');
    }

    /**
     * Attempt to determine join columns between journal line and chart of account
     *
     * @return array{line: string, coa: string}
     */
    private function pickJoinColumns(): array
    {
        $coaTable = $this->coaTable;

        foreach (self::JOIN_COLUMN_COMBINATIONS as $combo) {
            try {
                $this->query("
                    SELECT l.`{$combo['line']}` 
                    FROM " . self::JOURNAL_LINE_TABLE . " l 
                    LIMIT 1
                ");
                $this->query("
                    SELECT a.`{$combo['coa']}` 
                    FROM {$coaTable} a 
                    LIMIT 1
                ");

                $countRow = $this->queryOne("
                    SELECT COUNT(*) AS total
                    FROM " . self::JOURNAL_LINE_TABLE . " l
                    JOIN {$coaTable} a ON a.`{$combo['coa']}` = l.`{$combo['line']}`
                ");

                if (!empty($countRow['total'])) {
                    return $combo;
                }
            } catch (Exception $e) {
                // Continue to next combination
            }
        }

        throw new Exception('Unable to determine join columns between journal lines and chart of accounts');
    }

    /**
     * Normalise classification to canonical buckets
     */
    private function normalizeClassSql(string $expression): string
    {
        return "
            CASE
                WHEN UPPER({$expression}) LIKE 'ASSET%' THEN 'Asset'
                WHEN UPPER({$expression}) LIKE 'LIAB%' THEN 'Liability'
                WHEN UPPER({$expression}) LIKE 'EQUIT%' THEN 'Equity'
                WHEN UPPER({$expression}) LIKE 'CAPITAL%' THEN 'Equity'
                WHEN UPPER({$expression}) LIKE '%RESERVE%' THEN 'Equity'
                WHEN UPPER({$expression}) LIKE 'OWNER%S% EQUITY%' THEN 'Equity'
                WHEN UPPER({$expression}) LIKE 'INC%' THEN 'INCOME'
                WHEN UPPER({$expression}) LIKE 'REV%' THEN 'INCOME'
                WHEN UPPER({$expression}) LIKE 'EXP%' THEN 'EXPENSE'
                ELSE UPPER({$expression})
            END
        ";
    }
}

