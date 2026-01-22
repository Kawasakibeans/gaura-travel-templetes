<?php

namespace App\Services;

use App\DAL\TicketNumberUpdatorDAL;

class TicketNumberUpdatorService
{
    private $dal;

    public function __construct(TicketNumberUpdatorDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Split ticket number "NNN-NNNNNNNNNN" into [a_l, document]
     */
    private function splitTicketNumber($ticketNumber)
    {
        $a_l = '';
        $doc = '';
        if (preg_match('/^(\d{3})-(\d+)$/', trim($ticketNumber), $m)) {
            $a_l = $m[1];
            $doc = $m[2];
        }
        return [$a_l, $doc];
    }

    /**
     * Parse DDMONYY (e.g., 03AUG25) -> Y-m-d
     */
    private function parseDdmonyyToYmd($token)
    {
        $token = strtoupper(trim($token));
        if (preg_match('/^(\d{2})([A-Z]{3})(\d{2})$/', $token, $m)) {
            $dd = (int)$m[1];
            $mon = $m[2];
            $yy = (int)$m[3];
            $months = [
                'JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
                'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12
            ];
            if (isset($months[$mon])) {
                $year = 2000 + $yy;
                return sprintf('%04d-%02d-%02d', $year, $months[$mon], $dd);
            }
        }
        return '';
    }

    /**
     * Get vendor name based on airline code
     */
    private function getVendorName($airlineCode)
    {
        if ($airlineCode == 'SQ') {
            return 'IFN IATA';
        } else if ($airlineCode == 'MH') {
            return 'GKT IATA';
        }
        return '';
    }

    /**
     * Normalize column name for SQL
     */
    private function normalizeColumn($k)
    {
        $col = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
        return $col === '' ? 'col' : $col;
    }

    /**
     * Update ticket numbers
     * Main method that orchestrates all operations
     */
    public function updateTicketNumbers($autoid, $fname, $lname, $airlineCode, $updatedBy, $tickets)
    {
        $now = date('Y-m-d H:i:s');
        $vendorName = $this->getVendorName($airlineCode);
        
        // Get passenger info
        $paxInfo = $this->dal->getPaxByAutoId($autoid);
        if (!$paxInfo) {
            throw new \Exception('Passenger not found with auto_id: ' . $autoid, 404);
        }
        
        $orderId = $paxInfo['order_id'];
        $pnr = $paxInfo['pnr'];
        
        $insertedTicketNumbers = 0;
        $insertedCheckup = 0;
        $ticketNumbers = [];
        
        // Process each ticket
        foreach ($tickets as $t) {
            $raw = trim((string)($t['raw'] ?? ''));
            $ticketNo = trim((string)($t['ticketNumber'] ?? ''));
            $amountStr = trim((string)($t['amount'] ?? ''));
            $currency = trim((string)($t['currency'] ?? ''));
            $officeId = trim((string)($t['officeId'] ?? ''));
            $ticketedOn = trim((string)($t['ticketedOn'] ?? ''));
            $iataId = trim((string)($t['ticketingIata'] ?? ''));
            
            // Defaults
            $document_type = '';
            $vendor = '';
            $issue_date = $ticketedOn;
            $transaction_amount = $amountStr;
            
            // Parse raw string if present
            if ($raw !== '') {
                $parts = array_map('trim', explode('/', $raw));
                
                if ($ticketNo === '' && isset($parts[0])) {
                    if (preg_match('/(\d{3}-\d+)/', $parts[0], $mtn)) {
                        $ticketNo = $mtn[1];
                    }
                }
                
                if (isset($parts[1]) && $parts[1] !== '') {
                    $document_type = $parts[1];
                    $vendor = $parts[1];
                }
                if ($transaction_amount === '' && isset($parts[2])) {
                    $transaction_amount = preg_replace('/\D+/', '', $parts[2]);
                }
                if ($issue_date === '' && isset($parts[3])) {
                    $issue_date = $this->parseDdmonyyToYmd($parts[3]);
                }
                if ($officeId === '' && isset($parts[4])) {
                    $officeId = $parts[4];
                }
                if ($iataId === '' && isset($parts[5])) {
                    $iataId = $parts[5];
                }
            }
            
            // Split ticket number
            [$a_l, $document] = $this->splitTicketNumber($ticketNo);
            
            // Try to parse from raw if still missing
            if (($a_l === '' || $document === '') && !empty($raw)) {
                if (preg_match('/(\d{3})-(\d+)/', $raw, $mm)) {
                    $a_l = $a_l ?: $mm[1];
                    $document = $document ?: $mm[2];
                }
            }
            
            // Skip if required fields are missing
            if ($autoid <= 0 || $document === '' || $issue_date === '') {
                continue;
            }
            
            // Insert ticket number
            $doc_type = 'TKTT';
            $amount_val = (string)$transaction_amount;
            $vendor_val = ($vendor !== '') ? $vendor : $document_type;
            
            $success = $this->dal->insertTicketNumber(
                $autoid,
                $document,
                $doc_type,
                $amount_val,
                $vendorName,
                $issue_date,
                $now,
                $updatedBy,
                $a_l,
                $fname,
                $lname,
                $now,
                $updatedBy,
                $orderId,
                $pnr,
                $officeId
            );
            
            if ($success) {
                $insertedTicketNumbers++;
            }
            
            // Collect ticket numbers for main table update
            if (!empty($ticketNo)) {
                $ticketNumbers[] = $ticketNo;
            } elseif (!empty($t['ticketnum'])) {
                $ticketNumbers[] = trim((string)$t['ticketnum']);
            }
            
            // Insert checkup record
            $checkupData = [
                'pax_id' => (int)$autoid,
                'fname' => $fname,
                'lname' => $lname,
                'added_on' => $now,
                'added_by' => $updatedBy,
            ];
            
            // Add ticket keys as columns
            foreach ($t as $k => $v) {
                $col = $this->normalizeColumn($k);
                if (array_key_exists($col, $checkupData)) {
                    $col = 't_' . $col;
                }
                if (is_scalar($v) || $v === null) {
                    $checkupData[$col] = (string)$v;
                } else {
                    $checkupData[$col] = json_encode($v, JSON_UNESCAPED_SLASHES);
                }
            }
            
            if ($this->dal->insertTicketCheckup($checkupData)) {
                $insertedCheckup++;
            }
        }
        
        // Update main passenger table
        $ticketNumbers = array_values(array_unique(array_filter($ticketNumbers)));
        $ticket_num_str = implode(',', $ticketNumbers);
        
        $parts_ticket = explode('-', $ticket_num_str);
        $ticket_num_str_after_dash = isset($parts_ticket[1]) ? $parts_ticket[1] : '';
        
        $updateResult = $this->dal->updatePaxTicketNumber($autoid, $ticket_num_str_after_dash, 'Ticketed');
        
        return [
            'success' => true,
            'updated_rows' => $updateResult['affected_rows'],
            'inserted_ticket_numbers' => $insertedTicketNumbers,
            'inserted_checkup' => $insertedCheckup,
            'ticket_num' => $ticket_num_str,
            'ticket_num_after_dash' => $ticket_num_str_after_dash
        ];
    }
}

