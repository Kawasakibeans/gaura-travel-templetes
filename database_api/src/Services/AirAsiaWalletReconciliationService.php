<?php
/**
 * AirAsia Wallet Reconciliation Service
 * Business logic for AirAsia Wallet ↔ G360 reconciliation
 */

namespace App\Services;

use App\DAL\AirAsiaWalletReconciliationDAL;
use Exception;

class AirAsiaWalletReconciliationService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AirAsiaWalletReconciliationDAL();
    }

    /**
     * FR-1: Import wallet ledger
     */
    public function importWalletLedger(array $payload): array
    {
        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;
        $transactions = $payload['transactions'] ?? [];

        if (empty($startDate) || empty($endDate)) {
            throw new Exception('start_date and end_date are required', 400);
        }

        // FR-3: Validate required columns (check for AirAsia CSV column names)
        $requiredFields = ['Reference', 'TransactionDate', 'Amount'];
        $validationErrors = $this->validateRequiredColumns($transactions, $requiredFields);
        
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'missing_fields' => $validationErrors
            ];
        }

        // FR-4: Normalize data
        $normalizedTransactions = [];
        foreach ($transactions as $tx) {
            $normalizedTransactions[] = $this->normalizeWalletTransaction($tx);
        }

        // FR-1: Import to database
        $imported = $this->dal->importWalletLedger($normalizedTransactions, $startDate, $endDate);

        return [
            'success' => true,
            'imported' => $imported,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    /**
     * FR-2: Import G360 transactions
     */
    public function importG360Transactions(array $payload): array
    {
        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;

        if (empty($startDate) || empty($endDate)) {
            throw new Exception('start_date and end_date are required', 400);
        }

        $imported = $this->dal->importG360Transactions($startDate, $endDate);

        return [
            'success' => true,
            'imported' => $imported,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    /**
     * FR-3: Validate required columns
     */
    private function validateRequiredColumns(array $data, array $requiredFields): array
    {
        if (empty($data)) {
            return ['No data provided'];
        }

        $errors = [];
        $firstRow = $data[0] ?? [];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($firstRow[$field]) || $firstRow[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $errors[] = 'Missing required fields: ' . implode(', ', $missingFields);
        }

        return $errors;
    }

    /**
     * FR-4: Normalize wallet transaction
     * Maps AirAsia Wallet Ledger CSV columns to database fields
     */
    private function normalizeWalletTransaction(array $row): array
    {
        // Extract PNR from Reference column (PNR is typically 6 alphanumeric characters)
        // Reference might contain PNR or transaction reference
        $reference = trim($row['Reference'] ?? $row['reference'] ?? '');
        $pnr = '';
        
        // Try to extract PNR from Reference (look for 6 alphanumeric characters)
        if (preg_match('/\b([A-Z0-9]{6})\b/i', $reference, $matches)) {
            $pnr = strtoupper($matches[1]);
        }
        
        // If no PNR found in Reference, check if there's a separate PNR column
        if (empty($pnr)) {
            $pnr = strtoupper(trim($row['pnr'] ?? $row['PNR'] ?? ''));
        }
        
        return [
            'wallet_transaction_id' => trim($row['Reference'] ?? $row['reference'] ?? $row['AccountReference'] ?? $row['accountreference'] ?? ''),
            'transaction_date' => $this->normalizeDateTime($row['TransactionDate'] ?? $row['transactiondate'] ?? $row['Transaction Date'] ?? ''),
            'pnr' => $pnr,
            'transaction_type' => trim($row['AccountTransactionType'] ?? $row['accounttransactiontype'] ?? $row['Transaction Type'] ?? ''),
            'category' => $this->categorizeWalletTransaction($row), // FR-5
            'amount' => $this->normalizeAmount($row['Amount'] ?? $row['amount'] ?? 0, 'wallet'),
            'currency' => strtoupper(trim($row['CurrencyCode'] ?? $row['currencycode'] ?? $row['ACCurrencyCode'] ?? $row['accurrencycode'] ?? 'INR')),
            'balance_after' => isset($row['available']) ? (float)$row['available'] : (isset($row['ACAmount']) ? (float)$row['ACAmount'] : null),
            'description' => trim($row['Note'] ?? $row['note'] ?? $row['AccountTransactionType'] ?? '')
        ];
    }

    /**
     * FR-4: Normalize date/time
     */
    private function normalizeDateTime(string $dateTime): string
    {
        if (empty($dateTime)) {
            return date('Y-m-d H:i:s');
        }

        // Try various date formats (including Excel date formats)
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.v',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y',
            'Y/m/d H:i:s',
            'Y/m/d',
            'd-m-Y H:i:s',
            'd-m-Y',
            'm/d/Y H:i:s',
            'm/d/Y',
            'd.m.Y H:i:s',
            'd.m.Y'
        ];
        
        // Handle Excel numeric dates (like 45123)
        if (is_numeric($dateTime) && $dateTime > 25569) {
            // Excel's epoch starts from 1900-01-01
            $unixDate = ($dateTime - 25569) * 86400;
            return date('Y-m-d H:i:s', $unixDate);
        }

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateTime);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // If all else fails, try strtotime
        $timestamp = strtotime($dateTime);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * FR-4: Normalize amount
     */
    private function normalizeAmount($amount, string $type): float
    {
        if (is_numeric($amount)) {
            return (float)$amount;
        }

        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^0-9.-]/', '', str_replace(',', '', (string)$amount));
        
        // Extract numeric value
        preg_match('/-?\d+\.?\d*/', $cleaned, $matches);
        $value = isset($matches[0]) ? (float)$matches[0] : 0.0;

        // For wallet deposits, ensure positive; for charges, ensure negative
        if ($type === 'wallet') {
            // Deposits should be positive, charges negative
            // This might need adjustment based on your data
        }

        return $value;
    }

    /**
     * FR-5: Categorize wallet transaction
     */
    public function categorizeWalletTransaction(array $transaction): string
    {
        // Use AccountTransactionType and Note columns from AirAsia CSV
        $transactionType = strtoupper(trim($transaction['AccountTransactionType'] ?? $transaction['accounttransactiontype'] ?? ''));
        $note = strtoupper(trim($transaction['Note'] ?? $transaction['note'] ?? ''));
        $description = $transactionType . ' ' . $note;
        
        $amount = $this->normalizeAmount($transaction['Amount'] ?? $transaction['amount'] ?? 0, 'wallet');

        // Get mapping rules
        $rules = $this->dal->getCategoryMappingRules();

        // Apply rules in priority order
        foreach ($rules as $rule) {
            if ($rule['is_active'] && stripos($description, $rule['pattern']) !== false) {
                return $rule['category'];
            }
        }

        // Default categorization based on amount sign and common patterns
        if ($amount > 0) {
            return 'Deposit';
        }

        if (stripos($description, 'MEAL') !== false || stripos($description, 'SSR') !== false) {
            return 'Meal Request';
        }

        if (stripos($description, 'BSF') !== false) {
            return 'BSF';
        }

        if (stripos($description, 'PAYMENT') !== false || stripos($description, 'TICKET') !== false) {
            return 'Full Payment';
        }

        return 'Other';
    }

    /**
     * FR-6: Categorize G360 transaction
     */
    public function categorizeG360Transaction(array $transaction): string
    {
        $lineType = strtoupper(trim($transaction['line_type'] ?? $transaction['Line Type'] ?? ''));
        $description = strtoupper(trim($transaction['description'] ?? ''));

        $combined = $lineType . ' ' . $description;

        if (stripos($combined, 'MEAL') !== false || stripos($combined, 'SSR') !== false) {
            return 'Meal Request';
        }

        if (stripos($combined, 'BSF') !== false) {
            return 'BSF';
        }

        if (stripos($combined, 'DEPOSIT') !== false || stripos($combined, 'TOP-UP') !== false) {
            return 'Deposit';
        }

        if (stripos($combined, 'TKT') !== false || stripos($combined, 'TICKET') !== false || stripos($combined, 'PAYMENT') !== false) {
            return 'Full Payment';
        }

        return 'Other';
    }

    /**
     * FR-7: Get category mapping rules
     */
    public function getCategoryMappingRules(): array
    {
        return $this->dal->getCategoryMappingRules();
    }

    /**
     * FR-7: Update category mapping rules
     */
    public function updateCategoryMappingRules(array $rules): bool
    {
        return $this->dal->saveCategoryMappingRules($rules);
    }

    /**
     * FR-8: Get uncategorized records
     */
    public function getUncategorizedRecords(string $source = 'both'): array
    {
        $result = [];

        if ($source === 'wallet' || $source === 'both') {
            $result['wallet'] = $this->dal->getUncategorizedWalletRecords();
        }

        if ($source === 'g360' || $source === 'both') {
            $result['g360'] = $this->dal->getUncategorizedG360Records();
        }

        return $result;
    }

    /**
     * FR-9: Run reconciliation (Primary Match Logic)
     */
    public function runReconciliation(string $startDate, string $endDate): array
    {
        // Get transactions for matching
        $walletTransactions = $this->dal->getWalletTransactionsForMatching($startDate, $endDate);
        $g360Transactions = $this->dal->getG360TransactionsForMatching($startDate, $endDate);

        // Build G360 map by PNR+Category+Amount for faster lookup
        $g360Map = [];
        foreach ($g360Transactions as $g360) {
            $key = $this->buildMatchKey($g360['pnr'], $g360['category'], $g360['amount']);
            if (!isset($g360Map[$key])) {
                $g360Map[$key] = [];
            }
            $g360Map[$key][] = $g360;
        }

        $matches = [];
        $matchedG360Ids = [];

        // Match wallet transactions to G360
        foreach ($walletTransactions as $wallet) {
            $key = $this->buildMatchKey($wallet['pnr'], $wallet['category'], $wallet['amount']);
            
            $matched = false;
            if (isset($g360Map[$key]) && !empty($g360Map[$key])) {
                // FR-9: Match found (PNR + Category + Amount exact match)
                $g360 = array_shift($g360Map[$key]); // Take first available match
                $matchedG360Ids[] = $g360['id'];

                $variance = abs($wallet['amount']) - abs($g360['amount']);
                $matchStatus = abs($variance) < 0.01 ? 'Matched' : 'Mismatched';

                $matches[] = [
                    'wallet_id' => $wallet['id'],
                    'g360_id' => $g360['id'],
                    'pnr' => $wallet['pnr'],
                    'category' => $wallet['category'],
                    'wallet_amount' => $wallet['amount'],
                    'g360_amount' => $g360['amount'],
                    'variance' => $variance,
                    'match_status' => $matchStatus,
                    'reason' => $this->determineReason($wallet, $g360, $matchStatus),
                    'wallet_date' => $wallet['transaction_date'],
                    'g360_date' => $g360['transaction_date']
                ];
                $matched = true;
            }

            // If not matched, mark as unmatched
            if (!$matched) {
                $matches[] = [
                    'wallet_id' => $wallet['id'],
                    'g360_id' => null,
                    'pnr' => $wallet['pnr'],
                    'category' => $wallet['category'],
                    'wallet_amount' => $wallet['amount'],
                    'g360_amount' => 0,
                    'variance' => $wallet['amount'],
                    'match_status' => 'Unmatched',
                    'reason' => $this->determineReason($wallet, null, 'Unmatched'),
                    'wallet_date' => $wallet['transaction_date'],
                    'g360_date' => null
                ];
            }
        }

        // Find unmatched G360 transactions
        foreach ($g360Transactions as $g360) {
            if (!in_array($g360['id'], $matchedG360Ids)) {
                $matches[] = [
                    'wallet_id' => null,
                    'g360_id' => $g360['id'],
                    'pnr' => $g360['pnr'],
                    'category' => $g360['category'],
                    'wallet_amount' => 0,
                    'g360_amount' => $g360['amount'],
                    'variance' => -$g360['amount'],
                    'match_status' => 'Unmatched',
                    'reason' => 'Missing in wallet ledger',
                    'wallet_date' => null,
                    'g360_date' => $g360['transaction_date']
                ];
            }
        }

        // Save matches to database
        $this->dal->saveReconciliationMatch([
            'matches' => $matches,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        return [
            'success' => true,
            'total_wallet' => count($walletTransactions),
            'total_g360' => count($g360Transactions),
            'matched' => count(array_filter($matches, fn($m) => $m['match_status'] === 'Matched')),
            'unmatched' => count(array_filter($matches, fn($m) => $m['match_status'] !== 'Matched')),
            'records' => $matches
        ];
    }

    /**
     * Build match key (PNR + Category + Amount)
     */
    private function buildMatchKey(string $pnr, string $category, float $amount): string
    {
        // Round amount to 2 decimal places for matching
        $roundedAmount = round($amount, 2);
        return strtoupper(trim($pnr)) . '|' . strtoupper(trim($category)) . '|' . $roundedAmount;
    }

    /**
     * FR-11: Determine reason for match status
     */
    private function determineReason(?array $wallet, ?array $g360, string $matchStatus): string
    {
        if ($matchStatus === 'Matched') {
            return 'Exact match';
        }

        if ($matchStatus === 'Mismatched') {
            if ($wallet && $g360) {
                $dateDiff = abs(strtotime($wallet['transaction_date']) - strtotime($g360['transaction_date']));
                if ($dateDiff > 86400) { // More than 1 day difference
                    return 'Timing difference';
                }
                return 'Amount variance';
            }
        }

        if ($matchStatus === 'Unmatched') {
            if ($wallet && !$g360) {
                return 'Missing posting in G360';
            }
            if (!$wallet && $g360) {
                return 'Missing in wallet ledger';
            }
            if ($wallet && $g360) {
                if ($wallet['pnr'] !== $g360['pnr']) {
                    return 'Wrong PNR';
                }
                if ($wallet['category'] !== $g360['category']) {
                    return 'Category mismatch';
                }
            }
        }

        return 'Other';
    }

    /**
     * FR-11: Add match note
     */
    public function addMatchNote(int $matchId, string $reason, string $note): bool
    {
        return $this->dal->addMatchNote($matchId, $reason, $note);
    }

    /**
     * FR-12: Get summary by period
     */
    public function getSummary(string $startDate, string $endDate): array
    {
        $openingBalance = $this->dal->getOpeningBalance($startDate);
        $closingBalance = $this->dal->getClosingBalance($endDate);
        $totalDeposits = $this->dal->getTotalDeposits($startDate, $endDate);
        $totalChargesByCategory = $this->dal->getTotalChargesByCategory($startDate, $endDate);
        $totalMatched = $this->dal->getMatchedCount($startDate, $endDate);
        $totalUnmatched = $this->dal->getUnmatchedCount($startDate, $endDate);

        // Calculate total charges
        $totalCharges = 0;
        foreach ($totalChargesByCategory as $amount) {
            $totalCharges += abs($amount); // Charges are negative
        }

        // Calculate variance
        $walletTotal = $totalDeposits - $totalCharges;
        $g360Total = $this->getG360TotalForPeriod($startDate, $endDate);
        $variance = $walletTotal - $g360Total;

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'total_deposits' => $totalDeposits,
            'total_charges_by_category' => $totalChargesByCategory,
            'total_charges' => $totalCharges,
            'total_matched' => $totalMatched,
            'total_unmatched' => $totalUnmatched,
            'variance' => $variance,
            'total_wallet' => $walletTotal,
            'total_g360' => $g360Total
        ];
    }

    /**
     * FR-13: Get category breakdown
     */
    public function getCategoryBreakdown(string $startDate, string $endDate): array
    {
        $categoryTotals = $this->dal->getCategoryTotals($startDate, $endDate);
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category_totals' => $categoryTotals
        ];
    }

    /**
     * FR-14: Get PNR detail view
     */
    public function getPnrDetail(string $pnr, string $startDate, string $endDate): array
    {
        $walletTransactions = $this->dal->getWalletTransactionsByPnr($pnr, $startDate, $endDate);
        $g360Transactions = $this->dal->getG360TransactionsByPnr($pnr, $startDate, $endDate);

        // Calculate totals
        $walletTotal = 0;
        $g360Total = 0;
        foreach ($walletTransactions as $tx) {
            $walletTotal += (float)$tx['amount'];
        }
        foreach ($g360Transactions as $tx) {
            $g360Total += (float)$tx['amount'];
        }

        $variance = $walletTotal - $g360Total;
        $matchStatus = abs($variance) < 0.01 ? 'Matched' : 'Mismatched';

        return [
            'pnr' => $pnr,
            'wallet_transactions' => $walletTransactions,
            'g360_transactions' => $g360Transactions,
            'wallet_total' => $walletTotal,
            'g360_total' => $g360Total,
            'variance' => $variance,
            'match_status' => $matchStatus,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    /**
     * FR-15: Export summary
     */
    public function exportSummary(string $startDate, string $endDate, string $format = 'csv'): array
    {
        $summary = $this->getSummary($startDate, $endDate);
        $breakdown = $this->getCategoryBreakdown($startDate, $endDate);

        return [
            'format' => $format,
            'summary' => $summary,
            'category_breakdown' => $breakdown['category_totals'],
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * FR-15: Export exceptions
     */
    public function exportExceptions(string $startDate, string $endDate, string $format = 'csv'): array
    {
        $result = $this->runReconciliation($startDate, $endDate);
        $exceptions = array_filter($result['records'], fn($r) => $r['match_status'] !== 'Matched');

        return [
            'format' => $format,
            'exceptions' => array_values($exceptions),
            'total_exceptions' => count($exceptions),
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Helper: Get G360 total for period
     */
    private function getG360TotalForPeriod(string $startDate, string $endDate): float
    {
        $breakdown = $this->getCategoryBreakdown($startDate, $endDate);
        $total = 0;
        foreach ($breakdown['category_totals'] as $cat => $data) {
            $total += $data['g360'];
        }
        return $total;
    }
}
