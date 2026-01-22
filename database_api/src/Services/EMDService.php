<?php
/**
 * EMD Service Layer
 * 
 * Handles business logic for EMD reconciliation operations
 */

namespace App\Services;

use App\DAL\EMDDAL;
use Exception;
use DateTime;
class EMDService {
    private $dal;

    public function __construct(EMDDAL $dal = null) {
        // If DAL is not provided, create it with default database connection
        if ($dal === null) {
            global $pdo;
            if (!isset($pdo)) {
                // Database connection
                $servername = "localhost";
                $username   = "gaurat_sriharan";
                $password   = "r)?2lc^Q0cAE";
                $dbname     = "gaurat_gauratravel";
                
                $pdo = new \PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]);
            }
            $this->dal = new EMDDAL($pdo);
        } else {
            $this->dal = $dal;
        }
    }

    /**
     * Normalize CSV header
     */
    private function normalizeHeader($s) {
        $s = strtolower((string)$s);
        $s = preg_replace('/\s+|[_\-\/\.\(\)\$]/', '', $s);
        return $s;
    }

    /**
     * Get value from CSV row by header
     */
    private function getByHeader(array $row, array $hmap, $labels, $default = '') {
        if (!is_array($labels)) $labels = [$labels];
        foreach ($labels as $label) {
            $key = $this->normalizeHeader($label);
            if (isset($hmap[$key])) {
                $idx = $hmap[$key];
                return $row[$idx] ?? $default;
            }
        }
        return $default;
    }

    /**
     * Extract numeric value from string
     */
    private function num($v) {
        if ($v === null) return 0.0;
        $clean = preg_replace('/[^0-9\.\-]/', '', str_replace(',', '', (string)$v));
        preg_match('/-?\d+\.?\d*/', $clean, $m);
        return isset($m[0]) ? (float)$m[0] : 0.0;
    }

    /**
     * Extract 10-digit document number
     */
    private function doc10($s) {
        if ($s === null) return '';
        $digits = preg_replace('/\D+/', '', (string)$s);
        return strlen($digits) >= 10 ? substr($digits, -10) : '';
    }

    /**
     * Parse date string
     */
    private function parseDate($s) {
        if (!$s) return null;
        $s = trim((string)$s);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        $dt = DateTime::createFromFormat('d/m/Y', $s);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    /**
     * Import EMD from CSV file
     */
    public function importEMDFromCSV($filePath, $addedBy = 'api') {
        $h = fopen($filePath, "r");
        if (!$h) {
            throw new Exception('Error opening CSV file');
        }

        $processed = 0;
        $ctg = [];
        $std = [];
        $hdr = fgetcsv($h, 100000, ",");
        
        if ($hdr === false) {
            fclose($h);
            throw new Exception('CSV file appears empty');
        }

        foreach ($hdr as $i => $c) {
            $ctg[$this->normalizeHeader($c)] = $i;
            $std[$this->normalizeHeader($c)] = $i;
        }

        while (($row = fgetcsv($h, 100000, ",")) !== false) {
            // vendor inference
            $vendorRaw = trim($row[1] ?? '');
            if ($vendorRaw === 'The Trustee for Gaura Katha Trust') {
                $vendor = 'GKT IATA';
            } elseif ($vendorRaw === 'IFLYNOW PRIVATE LIMITED') {
                $vendor = 'IFN IATA';
            } else {
                $vendor = $vendorRaw;
            }

            // defaults
            $group = '';
            $iata_no = null;
            $document = '';
            $a_l = '';
            $pax_ticket_name = '';
            $plated_carrier_code = '';
            $plated_carrier = '';
            $dom = '';
            $published_remit = '';
            $reporting_system_code = '';
            $pnr = '';
            $document_type = '';
            $issue_date = null;
            $departure_date = null;
            $currency = null;
            $tour_code = '';
            $conj_tkt_indicator = '';
            $emd_remarks = '';
            $cash_fare = 0.0;
            $credit_fare = 0.0;
            $gross_fare = 0.0;
            $comm = 0.0;
            $fare = 0.0;
            $cash_tax = 0.0;
            $credit_tax = 0.0;
            $yq_tax = 0.0;
            $yr_tax = 0.0;
            $tax = 0.0;
            $fee = 0.0;
            $transaction_amount = 0.0;
            $net_due = 0.0;

            $isCTG = isset($ctg['particularsair']) || isset($ctg['issued']) || isset($ctg['totaltax']);
            
            if ($isCTG) {
                $get = function($label) use($ctg, $row) {
                    $k = $this->normalizeHeader($label);
                    return isset($ctg[$k]) ? ($row[$ctg[$k]] ?? '') : '';
                };
                
                $ticket_raw = $get('Ticket Number');
                if ($ticket_raw === '') {
                    $ticket_raw = $get('Ticket');
                }
                
                $docDigits = preg_replace('/\D+/', '', (string)$ticket_raw);
                $document = substr($docDigits, -10);
                $a_l = substr($docDigits, 0, 3);
                
                $pnr = substr(trim($get('Your Ref')), 0, 6);
                $document_type = trim($get('Type'));
                $cash_fare = $this->num($get('Cash Fare($)'));
                $tax = $this->num($get('Total Tax ($)'));
                $fee = $this->num($get('Service Fee Amt($)'));
                $comm = $this->num($get('Amt ($)'));
                $transaction_amount = $this->num($get('Transaction'));
                $net_due = $this->num($get('Total Amount'));
            } else {
                $get = function($label) use($std, $row) {
                    $k = $this->normalizeHeader($label);
                    return isset($std[$k]) ? ($row[$std[$k]] ?? '') : '';
                };
                
                $group = trim($get('Group'));
                $iata_no_raw = $get('IATA No');
                $iata_no_digits = preg_replace('/\D+/', '', (string)$iata_no_raw);
                $iata_no = $iata_no_digits === '' ? null : (int)$iata_no_digits;
                
                $ticket_raw = $get('Ticket Number');
                if ($ticket_raw === '') {
                    $ticket_raw = $get('Ticket');
                }
                $docDigits = preg_replace('/\D+/', '', (string)$ticket_raw);
                $document = substr($docDigits, -10);
                $a_l = substr($docDigits, 0, 3);
                
                $pax_ticket_name = trim($get('Passenger Name'));
                $plated_carrier_code = trim($get('Plated Carrier Code'));
                $plated_carrier = trim($get('Plated Carrier'));
                $dom = trim($get('Dom/Int'));
                $published_remit = trim($get('Published/Net Remit'));
                $reporting_system_code = trim($get('Reporting System Code'));
                $pnr = substr(trim($get('PNR Reference') ?: $get('PNR')), 0, 6);
                $document_type = trim($get('Transaction Type') ?: $get('Type'));
                $currency = trim($get('Currency')) ?: null;
                $tour_code = trim($get('Tour Code'));
                $conj_tkt_indicator = trim($get('Conjunction Ticket Indicator'));
                $emd_remarks = trim($get('EMD Remarks') ?: $get('EMD Remarks Cash'));
                $cash_fare = $this->num($get('Cash Fare'));
                $credit_fare = $this->num($get('Credit Fare'));
                $gross_fare = $this->num($get('Gross Fare'));
                $comm = $this->num($get('Total Commission') ?: $get('Commission'));
                if ($comm != 0) $comm = -$comm;
                $fare = $this->num($get('Net Fare') ?: $get('Net'));
                $cash_tax = $this->num($get('Cash Tax'));
                $credit_tax = $this->num($get('Credit Tax'));
                $yq_tax = $this->num($get('YQ Tax'));
                $yr_tax = $this->num($get('YR Tax'));
                $tax = $this->num($get('Total Tax'));
                $transaction_amount = $gross_fare + $tax;
                $net_due = $this->num($get('Document Amount') ?: $get('Total Amount'));
            }

            $data = [
                ':group' => $group,
                ':vendor' => $vendor,
                ':iata_no' => $iata_no,
                ':document' => $document,
                ':a_l' => $a_l,
                ':pax_ticket_name' => $pax_ticket_name,
                ':plated_carrier_code' => $plated_carrier_code,
                ':plated_carrier' => $plated_carrier,
                ':dom' => $dom,
                ':published_remit' => $published_remit,
                ':reporting_system_code' => $reporting_system_code,
                ':pnr' => $pnr,
                ':document_type' => $document_type,
                ':issue_date' => null,
                ':departure_date' => null,
                ':currency' => $currency,
                ':tour_code' => $tour_code,
                ':conj_tkt_indicator' => $conj_tkt_indicator,
                ':emd_remarks' => $emd_remarks,
                ':cash_fare' => $cash_fare,
                ':credit_fare' => $credit_fare,
                ':gross_fare' => $gross_fare,
                ':comm' => $comm,
                ':fare' => $fare,
                ':cash_tax' => $cash_tax,
                ':credit_tax' => $credit_tax,
                ':yq_tax' => $yq_tax,
                ':yr_tax' => $yr_tax,
                ':tax' => $tax,
                ':transaction_amount' => $transaction_amount,
                ':net_due' => $net_due,
                ':fee' => 0,
                ':added_by' => $addedBy
            ];
            
            $this->dal->insertEMD($data);
            $processed++;
        }
        
        fclose($h);
        return $processed;
    }

    /**
     * Get flights for a travel date
     */
    public function getFlights($travelDate) {
        return $this->dal->getFlightsByTravelDate($travelDate);
    }

    /**
     * Get PAX list by travel date and flight
     */
    public function getPax($travelDate, $intFlight) {
        $rows = $this->dal->getPaxByTravelDateAndFlight($travelDate, $intFlight);
        
        $sum_net_due = 0.0;
        $sum_order_amnt = 0.0;
        
        foreach ($rows as $r) {
            $sum_net_due += (float)($r['net_due'] ?? 0);
            $sum_order_amnt += (float)($r['order_amnt'] ?? 0);
        }

        return [
            'rows' => $rows,
            'summary' => [
                'sum_net_due' => round($sum_net_due, 2),
                'sum_order_amnt' => round($sum_order_amnt, 2)
            ]
        ];
    }

    /**
     * Assign EMD to PAX
     */
    public function assignEMD($emdDocument, array $paxIds, $addedBy = 'api') {
        $emdDoc = $this->doc10($emdDocument);
        
        if ($emdDoc === '' || strlen($emdDoc) !== 10) {
            throw new Exception('Please provide a valid 10-digit EMD document');
        }
        
        if (empty($paxIds)) {
            throw new Exception('No PAX IDs provided');
        }

        $paxIds = array_unique(array_map('intval', $paxIds));
        
        // Verify the EMD exists
        $emdExists = $this->dal->emdExists($emdDoc);

        $this->dal->beginTransaction();
        
        try {
            $insCount = 0;
            $updCount = 0;

            foreach ($paxIds as $pid) {
                // Insert reconciliation record
                $remark = 'EMD ' . $emdDoc;
                if ($this->dal->insertReconciliation($pid, $remark, $addedBy)) {
                    $insCount++;
                }

                // Update order amount
                $paxData = $this->dal->getPaxOrderAmount($pid);
                if ($paxData && $this->dal->updateTicketOrderAmount($pid, (float)$paxData['order_amnt'])) {
                    $updCount++;
                }
            }

            $this->dal->commit();
            
            return [
                'emd_document' => $emdDoc,
                'pax_count' => count($paxIds),
                'pax_ids' => $paxIds,
                'details' => [
                    'reconciliation_inserted' => $insCount,
                    'order_amounts_updated' => $updCount,
                    'emd_exists_in_table' => $emdExists
                ]
            ];
        } catch (Exception $e) {
            $this->dal->rollback();
            throw $e;
        }
    }

    /**
     * Get recent EMDs
     */
    public function getRecentEMDs($limit = 30) {
        $limit = max(1, min(100, $limit));
        return $this->dal->getRecentEMDs($limit);
    }

    /**
     * Search EMDs with filters
     */
    public function searchEMDs($filters = []) {
        $emds = $this->dal->searchEMDs($filters);
        $total = $this->dal->countEMDs($filters);
        
        return [
            'emds' => $emds,
            'total' => $total,
            'count' => count($emds),
            'limit' => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0
        ];
    }
}
