<?php
/**
 * TRAMS Reconciliation Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TramsReconciliationDAL;
use Exception;

class TramsReconciliationService
{
    private $reconciliationDAL;
    
    public function __construct()
    {
        $this->reconciliationDAL = new TramsReconciliationDAL();
    }
    
    /**
     * Compare two amounts with tolerance
     */
    private function amountsEqual($a, $b, $tolerance = 0.005)
    {
        return abs(round((float)$a, 2) - round((float)$b, 2)) < $tolerance;
    }
    
    /**
     * Normalize numeric value
     */
    private function num($v)
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }
    
    /**
     * Get reconciliation data
     */
    public function getReconciliationData($startDate, $endDate, $filterType = 'all', $maxDays = 365)
    {
        // Validate and normalize dates
        date_default_timezone_set('Australia/Melbourne');
        
        if (empty($startDate) || empty($endDate)) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }
        
        $startDT = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDT = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$startDT || !$endDT) {
            $startDT = new \DateTime(date('Y-m-01'));
            $endDT = new \DateTime(date('Y-m-t'));
            $startDate = $startDT->format('Y-m-d');
            $endDate = $endDT->format('Y-m-d');
        } else {
            // Ensure start <= end
            if ($endDT < $startDT) {
                $tmp = $startDT;
                $startDT = $endDT;
                $endDT = $tmp;
                $startDate = $startDT->format('Y-m-d');
                $endDate = $endDT->format('Y-m-d');
            }
            
            // Enforce max days
            $diffDays = (int)$startDT->diff($endDT)->days;
            if ($diffDays > $maxDays) {
                $endDT = (clone $startDT)->modify('+' . $maxDays . ' days');
                $endDate = $endDT->format('Y-m-d');
            }
        }
        
        // Get all records
        $matches = $this->reconciliationDAL->getMatchedRecords($startDate, $endDate);
        $tramsOnly = $this->reconciliationDAL->getTramsOnlyRecords($startDate, $endDate);
        $g360Only = $this->reconciliationDAL->getG360OnlyRecords($startDate, $endDate);
        
        // Merge and calculate diffs
        $rows = array_merge($matches, $tramsOnly, $g360Only);
        
        foreach ($rows as &$r) {
            $amtT = $this->num($r['order_amnt_trams'] ?? 0);
            $amtG = $this->num($r['order_amnt_g360'] ?? 0);
            $netT = $this->num($r['net_due_trams'] ?? 0);
            $netG = $this->num($r['net_due_g360'] ?? 0);
            
            $r['order_amnt_trams'] = $amtT;
            $r['order_amnt_g360'] = $amtG;
            $r['order_amnt_diff'] = $amtG - $amtT;
            
            $r['net_due_trams'] = $netT;
            $r['net_due_g360'] = $netG;
            $r['net_due_diff'] = $netG - $netT;
            
            // Determine status
            if ($r['invoicelink_no_trams'] && $r['invoicelink_no_g360']) {
                $ordOK = $this->amountsEqual($amtG, $amtT);
                $netOK = $this->amountsEqual($netG, $netT);
                $r['row_status'] = (!$ordOK || !$netOK) ? 'mismatch' : 'match';
            } elseif ($r['invoicelink_no_trams']) {
                $r['row_status'] = 'trams_only';
            } elseif ($r['invoicelink_no_g360']) {
                $r['row_status'] = 'g360_only';
            } else {
                $r['row_status'] = 'unknown';
            }
        }
        unset($r);
        
        // Apply filter
        if ($filterType !== 'all') {
            $rows = array_values(array_filter($rows, function($r) use ($filterType) {
                $amtRaw = ($r['order_amnt_trams'] ?? null);
                if (!is_numeric($amtRaw) || (float)$amtRaw === 0.0) {
                    $amtRaw = ($r['order_amnt_g360'] ?? null);
                }
                $amt = is_numeric($amtRaw) ? (float)$amtRaw : 0.0;
                $eps = 1e-9;
                
                switch ($filterType) {
                    case 'mismatch':
                    case 'match':
                    case 'trams_only':
                    case 'g360_only':
                        return ($r['row_status'] ?? '') === $filterType;
                    
                    case 'amt_lt_100':
                        return $amt > $eps && $amt < (100 - $eps);
                    
                    case 'amt_ge_100':
                        return $amt >= (100 - $eps);
                    
                    case 'amt_lt_100_mismatch':
                        return ($r['row_status'] ?? '') === 'mismatch'
                               && $amt > $eps
                               && $amt < (100 - $eps);
                    
                    case 'amt_ge_100_mismatch':
                        return ($r['row_status'] ?? '') === 'mismatch'
                               && $amt >= (100 - $eps);
                    
                    default:
                        return true;
                }
            }));
        }
        
        // Calculate summary
        $summary = [
            'matched' => 0,
            'mismatched' => 0,
            'trams_only' => 0,
            'g360_only' => 0
        ];
        
        foreach ($rows as $r) {
            $status = $r['row_status'] ?? '';
            if ($status === 'match') {
                $summary['matched']++;
            } elseif ($status === 'mismatch') {
                $summary['mismatched']++;
            } elseif ($status === 'trams_only') {
                $summary['trams_only']++;
            } elseif ($status === 'g360_only') {
                $summary['g360_only']++;
            }
        }
        
        return [
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filter' => $filterType,
            'total_records' => count($rows),
            'summary' => $summary,
            'data' => $rows
        ];
    }
    
    /**
     * Overwrite G360 with TRAMS data
     */
    public function overwriteG360WithTrams($invoices, $startDate, $endDate)
    {
        if (empty($invoices) || !is_array($invoices)) {
            throw new Exception('Invoices array is required', 400);
        }
        
        if (empty($startDate) || empty($endDate)) {
            throw new Exception('Start date and end date are required', 400);
        }
        
        // Deduplicate invoices
        $invoices = array_values(array_unique(array_filter(array_map('strval', $invoices))));
        
        if (empty($invoices)) {
            throw new Exception('No valid invoices provided', 400);
        }
        
        $this->reconciliationDAL->beginTransaction();
        
        try {
            $totalUpdated = 0;
            
            foreach ($invoices as $invoice) {
                // Get TRAMS aggregates
                $tramsData = $this->reconciliationDAL->getTramsAggregates($invoice, $startDate, $endDate);
                $orderSum = $tramsData ? (float)$tramsData['order_sum'] : 0.0;
                $netSum = $tramsData ? (float)$tramsData['net_sum'] : 0.0;
                
                // Update G360
                $updated = $this->reconciliationDAL->updateG360WithTrams($invoice, $orderSum, $netSum, $startDate, $endDate);
                $totalUpdated += $updated;
            }
            
            $this->reconciliationDAL->commit();
            
            return [
                'success' => true,
                'message' => 'Overwrite complete',
                'invoices_processed' => count($invoices),
                'rows_updated' => $totalUpdated
            ];
        } catch (Exception $e) {
            $this->reconciliationDAL->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate CSV data
     */
    public function generateCsvData($reconciliationData)
    {
        $csv = [];
        
        // Header
        $csv[] = [
            'invoicelink_no_trams',
            'order_amnt_trams',
            'net_due_trams',
            'invoicelink_no_g360',
            'order_amnt_g360',
            'net_due_g360',
            'order_amnt_diff',
            'net_due_diff',
            'row_status'
        ];
        
        // Data rows
        foreach ($reconciliationData['data'] as $r) {
            $csv[] = [
                $this->csvSafe($r['invoicelink_no_trams'] ?? ''),
                $this->formatMoney($r['order_amnt_trams']),
                $this->formatMoney($r['net_due_trams']),
                $this->csvSafe($r['invoicelink_no_g360'] ?? ''),
                $this->formatMoney($r['order_amnt_g360']),
                $this->formatMoney($r['net_due_g360']),
                $this->formatMoney($r['order_amnt_diff']),
                $this->formatMoney($r['net_due_diff']),
                $r['row_status'] ?? ''
            ];
        }
        
        return $csv;
    }
    
    /**
     * CSV safe value (prevent formula injection)
     */
    private function csvSafe($v)
    {
        $v = (string)$v;
        if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'])) {
            return "'" . $v;
        }
        return $v;
    }
    
    /**
     * Format money value
     */
    private function formatMoney($v)
    {
        if ($v === '' || $v === null) {
            return '';
        }
        if (!is_numeric($v)) {
            return (string)$v;
        }
        return number_format((float)$v, 2, '.', '');
    }
}

