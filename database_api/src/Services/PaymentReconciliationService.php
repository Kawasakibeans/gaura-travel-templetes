<?php

namespace App\Services;

use App\DAL\PaymentReconciliationDAL;
use Exception;

class PaymentReconciliationService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentReconciliationDAL();
    }

    /**
     * Create unique keys for matching (like Excel COUNTIF)
     */
    private function createUniqueKeys(array &$records, string $dateField, string $amountField): void
    {
        $occurrences = [];
        foreach ($records as &$row) {
            $key = $row[$dateField] . '|' . $row[$amountField];
            if (!isset($occurrences[$key])) {
                $occurrences[$key] = 1;
            } else {
                $occurrences[$key]++;
            }
            $row['unique_key'] = $key . ' ' . $occurrences[$key];
        }
        unset($row);
    }

    /**
     * Get payment reconciliation data
     */
    public function getPaymentReconciliation(?string $startDate = null, ?string $endDate = null): array
    {
        // Get tram and bank records
        $tramRecords = $this->dal->getTramRecords($startDate, $endDate);
        $bankRecords = $this->dal->getBankRecords($startDate, $endDate);

        // Create unique keys for matching
        $this->createUniqueKeys($tramRecords, 'payment_date', 'amount');
        $this->createUniqueKeys($bankRecords, 'date', 'amount');

        // Build associative arrays for FULL OUTER JOIN simulation
        $tramMap = [];
        foreach ($tramRecords as $t) {
            $tramMap[$t['unique_key']] = $t;
        }

        $matchedData = [];
        foreach ($bankRecords as $b) {
            $key = $b['unique_key'];
            $t = $tramMap[$key] ?? null;
            $matchedData[] = [
                'paymentno' => $t['paymentno'] ?? '',
                'payment_date' => $b['date'],
                'trams_remark' => $t['trams_remark'] ?? '',
                'customer_name' => $t['customer_name'] ?? '',
                'description' => $b['description'] ?? '',
                'bank_type' => $b['type'] ?? '',
                'tram_amount' => $t['amount'] ?? '',
                'bank_amount' => $b['amount'] ?? '',
                'voucher_linkno' => $t['voucher_linkno'] ?? '',
                'match_status' => ($t) ? 'Match' : 'No Match'
            ];
        }

        // Calculate totals
        $total = count($matchedData);
        $matched = count(array_filter($matchedData, function($r) {
            return $r['match_status'] === 'Match';
        }));
        $unmatched = $total - $matched;

        $bankAmount = 0.0;
        $tramAmount = 0.0;
        if (!empty($startDate) && !empty($endDate)) {
            $bankAmount = $this->dal->getTotalBankAmount($startDate, $endDate);
            $tramAmount = $this->dal->getTotalTramAmount($startDate, $endDate);
        }

        $balance = $tramAmount - $bankAmount;

        return [
            'records' => $matchedData,
            'summary' => [
                'total' => $total,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'bank_amount' => $bankAmount,
                'tram_amount' => $tramAmount,
                'balance' => $balance
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ];
    }
}

