<?php
/**
 * AirAsia Wallet Reconciliation DAL
 * Handles database operations for AirAsia Wallet ↔ G360 reconciliation
 */

namespace App\DAL;

use Exception;

class AirAsiaWalletReconciliationDAL extends BaseDAL
{
    private const WALLET_TABLE = 'wpk4_backend_airasia_wallet_ledger';
    private const G360_TABLE = 'wpk4_backend_airasia_g360_transactions';
    private const RECONCILIATION_TABLE = 'wpk4_backend_airasia_reconciliation';
    private const MAPPING_RULES_TABLE = 'wpk4_backend_airasia_category_mapping_rules';

    /**
     * FR-1: Import wallet ledger transactions
     */
    public function importWalletLedger(array $transactions, string $startDate, string $endDate): int
    {
        if (empty($transactions)) {
            return 0;
        }

        $this->beginTransaction();
        try {
            // Delete existing records for this period first (to allow re-import)
            $deleteSql = "
                DELETE FROM " . self::WALLET_TABLE . " 
                WHERE transaction_date BETWEEN ? AND ?
            ";
            $this->execute($deleteSql, [$startDate, $endDate]);

            $insertSql = "
                INSERT INTO " . self::WALLET_TABLE . " 
                (wallet_transaction_id, transaction_date, pnr, transaction_type, category, amount, currency, balance_after, description, imported_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $inserted = 0;
            foreach ($transactions as $tx) {
                $this->execute($insertSql, [
                    $tx['wallet_transaction_id'] ?? null,
                    $tx['transaction_date'] ?? null,
                    $tx['pnr'] ?? null,
                    $tx['transaction_type'] ?? null,
                    $tx['category'] ?? 'Other',
                    $tx['amount'] ?? 0,
                    $tx['currency'] ?? 'AUD',
                    $tx['balance_after'] ?? null,
                    $tx['description'] ?? null
                ]);
                $inserted++;
            }

            $this->commit();
            return $inserted;
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception("Failed to import wallet ledger: " . $e->getMessage(), 500);
        }
    }

    /**
     * FR-2: Import G360 transactions
     */
    public function importG360Transactions(string $startDate, string $endDate): int
    {
        $this->beginTransaction();
        try {
            // Delete existing records for this period
            $deleteSql = "
                DELETE FROM " . self::G360_TABLE . " 
                WHERE transaction_date BETWEEN ? AND ?
            ";
            $this->execute($deleteSql, [$startDate, $endDate]);

            // Fetch G360 transactions from various tables
            // This is a placeholder - you'll need to adjust based on your actual G360 database structure
            $sql = "
                INSERT INTO " . self::G360_TABLE . " 
                (g360_transaction_id, transaction_date, pnr, line_type, category, amount, currency, status, imported_at)
                SELECT 
                    CONCAT('G360-', COALESCE(t.auto_id, o.order_id), '-', COALESCE(t.pax_id, '')) AS g360_transaction_id,
                    COALESCE(t.issue_date, o.order_date) AS transaction_date,
                    COALESCE(t.pnr, o.pnr) AS pnr,
                    CASE 
                        WHEN t.document_type IS NOT NULL THEN t.document_type
                        WHEN p.payment_type IS NOT NULL THEN p.payment_type
                        ELSE 'Transaction'
                    END AS line_type,
                    CASE 
                        WHEN t.document_type IN ('TKT', 'TKTT') THEN 'Full Payment'
                        WHEN t.document_type LIKE '%BSF%' THEN 'BSF'
                        WHEN t.document_type LIKE '%MEAL%' OR t.document_type LIKE '%SSR%' THEN 'Meal Request'
                        WHEN p.payment_type = 'Deposit' THEN 'Deposit'
                        ELSE 'Other'
                    END AS category,
                    COALESCE(t.transaction_amount, p.amount, 0) AS amount,
                    'AUD' AS currency,
                    COALESCE(t.confirmed, 'Posted') AS status
                FROM wpk4_backend_travel_booking_ticket_number t
                LEFT JOIN wpk4_backend_travel_booking_order o ON t.order_id = o.order_id
                LEFT JOIN wpk4_backend_travel_payment_history p ON o.order_id = p.order_id
                WHERE (t.issue_date BETWEEN ? AND ? OR o.order_date BETWEEN ? AND ?)
                  AND (t.pnr IS NOT NULL OR o.pnr IS NOT NULL)
                GROUP BY t.auto_id, o.order_id, p.payment_id
            ";

            $this->execute($sql, [$startDate, $endDate, $startDate, $endDate]);
            $inserted = $this->db->rowCount();

            $this->commit();
            return $inserted;
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception("Failed to import G360 transactions: " . $e->getMessage(), 500);
        }
    }

    /**
     * FR-9: Get wallet transactions for matching
     */
    public function getWalletTransactionsForMatching(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                id,
                wallet_transaction_id,
                transaction_date,
                pnr,
                category,
                amount,
                currency,
                transaction_type,
                description
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
              AND pnr IS NOT NULL
              AND pnr != ''
            ORDER BY transaction_date, pnr, category
        ";

        return $this->query($sql, [$startDate, $endDate]);
    }

    /**
     * FR-9: Get G360 transactions for matching
     */
    public function getG360TransactionsForMatching(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                id,
                g360_transaction_id,
                transaction_date,
                pnr,
                category,
                amount,
                currency,
                line_type,
                status
            FROM " . self::G360_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
              AND pnr IS NOT NULL
              AND pnr != ''
            ORDER BY transaction_date, pnr, category
        ";

        return $this->query($sql, [$startDate, $endDate]);
    }

    /**
     * FR-9: Save reconciliation match results
     */
    public function saveReconciliationMatch(array $matchData): int
    {
        $this->beginTransaction();
        try {
            // Delete existing matches for this period
            $deleteSql = "
                DELETE FROM " . self::RECONCILIATION_TABLE . "
                WHERE wallet_id IN (
                    SELECT id FROM " . self::WALLET_TABLE . " 
                    WHERE transaction_date BETWEEN ? AND ?
                )
            ";
            $this->execute($deleteSql, [$matchData['start_date'], $matchData['end_date']]);

            $insertSql = "
                INSERT INTO " . self::RECONCILIATION_TABLE . "
                (wallet_id, g360_id, pnr, category, wallet_amount, g360_amount, variance, match_status, reason, reconciled_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $inserted = 0;
            foreach ($matchData['matches'] as $match) {
                $this->execute($insertSql, [
                    $match['wallet_id'] ?? null,
                    $match['g360_id'] ?? null,
                    $match['pnr'] ?? null,
                    $match['category'] ?? null,
                    $match['wallet_amount'] ?? 0,
                    $match['g360_amount'] ?? 0,
                    $match['variance'] ?? 0,
                    $match['match_status'] ?? 'Unmatched',
                    $match['reason'] ?? null
                ]);
                $inserted++;
            }

            $this->commit();
            return $inserted;
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception("Failed to save reconciliation matches: " . $e->getMessage(), 500);
        }
    }

    /**
     * FR-8: Get uncategorized wallet records
     */
    public function getUncategorizedWalletRecords(): array
    {
        $sql = "
            SELECT *
            FROM " . self::WALLET_TABLE . "
            WHERE category = 'Other' OR category IS NULL
            ORDER BY transaction_date DESC
            LIMIT 1000
        ";

        return $this->query($sql);
    }

    /**
     * FR-8: Get uncategorized G360 records
     */
    public function getUncategorizedG360Records(): array
    {
        $sql = "
            SELECT *
            FROM " . self::G360_TABLE . "
            WHERE category = 'Other' OR category IS NULL
            ORDER BY transaction_date DESC
            LIMIT 1000
        ";

        return $this->query($sql);
    }

    /**
     * FR-7: Get category mapping rules
     */
    public function getCategoryMappingRules(): array
    {
        try {
            $sql = "
                SELECT rule_id, pattern, category, priority, is_active
                FROM " . self::MAPPING_RULES_TABLE . "
                WHERE is_active = 1
                ORDER BY priority DESC, rule_id ASC
            ";
            return $this->query($sql);
        } catch (Exception $e) {
            // Table might not exist yet - return default rules
            return $this->getDefaultMappingRules();
        }
    }

    /**
     * FR-7: Save category mapping rules
     */
    public function saveCategoryMappingRules(array $rules): bool
    {
        $this->beginTransaction();
        try {
            // Clear existing rules
            $deleteSql = "DELETE FROM " . self::MAPPING_RULES_TABLE;
            $this->execute($deleteSql);

            $insertSql = "
                INSERT INTO " . self::MAPPING_RULES_TABLE . "
                (pattern, category, priority, is_active)
                VALUES (?, ?, ?, ?)
            ";

            foreach ($rules as $rule) {
                $this->execute($insertSql, [
                    $rule['pattern'] ?? '',
                    $rule['category'] ?? 'Other',
                    $rule['priority'] ?? 0,
                    $rule['is_active'] ?? 1
                ]);
            }

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception("Failed to save mapping rules: " . $e->getMessage(), 500);
        }
    }

    /**
     * FR-12: Get opening balance
     */
    public function getOpeningBalance(string $startDate): float
    {
        $sql = "
            SELECT balance_after
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date < ?
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$startDate]);
        return $result ? (float)($result['balance_after'] ?? 0) : 0.0;
    }

    /**
     * FR-12: Get closing balance
     */
    public function getClosingBalance(string $endDate): float
    {
        $sql = "
            SELECT balance_after
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date <= ?
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$endDate]);
        return $result ? (float)($result['balance_after'] ?? 0) : 0.0;
    }

    /**
     * FR-12: Get total deposits
     */
    public function getTotalDeposits(string $startDate, string $endDate): float
    {
        $sql = "
            SELECT SUM(amount) as total
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
              AND category = 'Deposit'
              AND amount > 0
        ";

        $result = $this->queryOne($sql, [$startDate, $endDate]);
        return $result ? (float)($result['total'] ?? 0) : 0.0;
    }

    /**
     * FR-12: Get total charges by category
     */
    public function getTotalChargesByCategory(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                category,
                SUM(amount) as total
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
              AND category IN ('Full Payment', 'BSF', 'Meal Request')
              AND amount < 0
            GROUP BY category
        ";

        $results = $this->query($sql, [$startDate, $endDate]);
        $totals = [];
        foreach ($results as $row) {
            $totals[$row['category']] = (float)$row['total'];
        }
        return $totals;
    }

    /**
     * FR-12: Get matched count
     */
    public function getMatchedCount(string $startDate, string $endDate): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM " . self::RECONCILIATION_TABLE . " r
            INNER JOIN " . self::WALLET_TABLE . " w ON r.wallet_id = w.id
            WHERE w.transaction_date BETWEEN ? AND ?
              AND r.match_status = 'Matched'
        ";

        $result = $this->queryOne($sql, [$startDate, $endDate]);
        return $result ? (int)($result['total'] ?? 0) : 0;
    }

    /**
     * FR-12: Get unmatched count
     */
    public function getUnmatchedCount(string $startDate, string $endDate): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM " . self::RECONCILIATION_TABLE . " r
            INNER JOIN " . self::WALLET_TABLE . " w ON r.wallet_id = w.id
            WHERE w.transaction_date BETWEEN ? AND ?
              AND r.match_status != 'Matched'
        ";

        $result = $this->queryOne($sql, [$startDate, $endDate]);
        return $result ? (int)($result['total'] ?? 0) : 0;
    }

    /**
     * FR-13: Get category totals (wallet vs G360)
     */
    public function getCategoryTotals(string $startDate, string $endDate): array
    {
        $walletSql = "
            SELECT 
                category,
                SUM(ABS(amount)) as total
            FROM " . self::WALLET_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY category
        ";

        $g360Sql = "
            SELECT 
                category,
                SUM(ABS(amount)) as total
            FROM " . self::G360_TABLE . "
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY category
        ";

        $walletTotals = $this->query($walletSql, [$startDate, $endDate]);
        $g360Totals = $this->query($g360Sql, [$startDate, $endDate]);

        $walletMap = [];
        foreach ($walletTotals as $row) {
            $walletMap[$row['category']] = (float)$row['total'];
        }

        $g360Map = [];
        foreach ($g360Totals as $row) {
            $g360Map[$row['category']] = (float)$row['total'];
        }

        $categories = ['Deposit', 'Full Payment', 'BSF', 'Meal Request', 'Other'];
        $result = [];

        foreach ($categories as $cat) {
            $walletTotal = $walletMap[$cat] ?? 0;
            $g360Total = $g360Map[$cat] ?? 0;
            $result[$cat] = [
                'wallet' => $walletTotal,
                'g360' => $g360Total,
                'difference' => $walletTotal - $g360Total
            ];
        }

        return $result;
    }

    /**
     * FR-14: Get wallet transactions by PNR
     */
    public function getWalletTransactionsByPnr(string $pnr, string $startDate, string $endDate): array
    {
        $sql = "
            SELECT *
            FROM " . self::WALLET_TABLE . "
            WHERE pnr = ?
              AND transaction_date BETWEEN ? AND ?
            ORDER BY transaction_date ASC
        ";

        return $this->query($sql, [$pnr, $startDate, $endDate]);
    }

    /**
     * FR-14: Get G360 transactions by PNR
     */
    public function getG360TransactionsByPnr(string $pnr, string $startDate, string $endDate): array
    {
        $sql = "
            SELECT *
            FROM " . self::G360_TABLE . "
            WHERE pnr = ?
              AND transaction_date BETWEEN ? AND ?
            ORDER BY transaction_date ASC
        ";

        return $this->query($sql, [$pnr, $startDate, $endDate]);
    }

    /**
     * FR-11: Add match note/reason
     */
    public function addMatchNote(int $matchId, string $reason, string $note): bool
    {
        $sql = "
            UPDATE " . self::RECONCILIATION_TABLE . "
            SET reason = ?,
                notes = CONCAT(COALESCE(notes, ''), '\n', ?)
            WHERE id = ?
        ";

        return $this->execute($sql, [$reason, $note, $matchId]);
    }

    /**
     * Get default mapping rules (fallback)
     */
    private function getDefaultMappingRules(): array
    {
        return [
            ['pattern' => 'MEAL', 'category' => 'Meal Request', 'priority' => 10, 'is_active' => 1],
            ['pattern' => 'BSF', 'category' => 'BSF', 'priority' => 10, 'is_active' => 1],
            ['pattern' => 'DEPOSIT', 'category' => 'Deposit', 'priority' => 10, 'is_active' => 1],
            ['pattern' => 'TOP-UP', 'category' => 'Deposit', 'priority' => 10, 'is_active' => 1],
            ['pattern' => 'PAYMENT', 'category' => 'Full Payment', 'priority' => 5, 'is_active' => 1],
            ['pattern' => 'TICKET', 'category' => 'Full Payment', 'priority' => 5, 'is_active' => 1],
        ];
    }
}
