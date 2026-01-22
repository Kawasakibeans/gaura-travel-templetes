<?php
/**
 * Ticket Check Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TicketCheckDAL;
use Exception;

class TicketCheckService
{
    private $ticketCheckDAL;

    public function __construct()
    {
        $this->ticketCheckDAL = new TicketCheckDAL();
    }

    /**
     * Parse passenger name from format "LASTNAME/FIRSTNAME" or "LASTNAME/FIRSTNAME MIDDLENAME"
     */
    private function parsePassengerName($paxName)
    {
        $parts = explode('/', $paxName);
        
        if (count($parts) >= 2) {
            $lastName = trim($parts[0]);
            $firstNamePart = trim($parts[1]);
            
            // Get the first word after '/'
            $firstNameParts = explode(' ', $firstNamePart);
            $firstName = $firstNameParts[0];
            
            return ['first_name' => $firstName, 'last_name' => $lastName];
        }
        
        return ['first_name' => '', 'last_name' => ''];
    }

    /**
     * Preview ticket check CSV data
     */
    public function previewTicketCheck($csvData, $bankType)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        if (!in_array($bankType, ['IFNIATA', 'GKTIATA', 'Sabre'])) {
            throw new Exception('Invalid bank type. Must be one of: IFNIATA, GKTIATA, Sabre', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            $processed = null;
            
            if ($bankType === 'IFNIATA' || $bankType === 'GKTIATA') {
                // Skip header row
                if (($row[0] ?? '') === 'SEQ NO' && ($row[1] ?? '') === 'CONFIRMED') {
                    continue;
                }
                
                $processed = $this->processIATARow($row, $bankType, $autonumber);
            } elseif ($bankType === 'Sabre') {
                // Skip header row
                if (($row[0] ?? '') === 'No.' && ($row[1] ?? '') === 'PNR') {
                    continue;
                }
                
                $processed = $this->processSabreRow($row, $autonumber);
            }
            
            if ($processed) {
                $preview[] = $processed;
                $autonumber++;
            }
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Process IATA format row (IFN IATA or GKT IATA)
     */
    private function processIATARow($row, $bankType, $autonumber)
    {
        $seqNo = $row[0] ?? '';
        $confirmed = $row[1] ?? '';
        $aL = $row[2] ?? '';
        $docNumber = $row[3] ?? ''; // ticket number
        $totalDoc = $row[4] ?? '';
        $tax = $row[5] ?? '';
        $fee = $row[6] ?? '';
        $comm = $row[7] ?? '';
        $agent = $row[8] ?? '';
        $fp = $row[9] ?? '';
        $paxName = $row[10] ?? '';
        $aS = $row[11] ?? '';
        $trnc = $row[12] ?? '';
        $recloc = $row[13] ?? ''; // PNR
        
        if (empty($paxName) || empty($recloc) || empty($docNumber)) {
            return null; // Skip invalid rows
        }
        
        $nameParts = $this->parsePassengerName($paxName);
        $firstName = $nameParts['first_name'];
        $lastName = $nameParts['last_name'];
        
        // Find matching passenger
        $match = $this->ticketCheckDAL->findMatchingPassenger($recloc, $firstName, $lastName);
        
        $orderId = '';
        $paxId = '';
        $pnr = '';
        
        if ($match) {
            $orderId = $match['order_id'];
            $paxId = $match['auto_id'];
            $pnr = $match['pnr'];
        }
        
        // Check payment status
        $paymentStatus = '';
        if ($orderId) {
            $paymentStatus = $this->ticketCheckDAL->getPaymentStatus($orderId, $recloc);
        }
        
        // Check if ticket exists
        $ticketExists = $this->ticketCheckDAL->checkTicketExists($recloc, $docNumber);
        
        // Determine match status
        $matchHidden = 'New';
        $match = 'New Record';
        $checked = false;
        
        if ($orderId && $paxId && $paymentStatus === 'paid') {
            if (!$ticketExists) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            } else {
                $matchHidden = 'New';
                $match = 'Duplicate';
                $checked = false;
            }
        }
        
        return [
            'autonumber' => $autonumber,
            'pax_name' => $paxName,
            'pnr' => $recloc,
            'ticket' => $docNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'order_id' => $orderId,
            'pax_id' => $paxId,
            'status' => $matchHidden,
            'match' => $match,
            'checked' => $checked,
            'row_data' => [
                'seq_no' => $seqNo,
                'confirmed' => $confirmed,
                'a_l' => $aL,
                'total_doc' => $totalDoc,
                'tax' => $tax,
                'fee' => $fee,
                'comm' => $comm,
                'agent' => $agent,
                'fp' => $fp,
                'a_s' => $aS,
                'trnc' => $trnc,
                'gds_from' => 'Amadeus',
                'bank_type' => $bankType
            ]
        ];
    }

    /**
     * Process Sabre format row
     */
    private function processSabreRow($row, $autonumber)
    {
        $seqNo = $row[0] ?? '';
        $pnr = $row[1] ?? '';
        $paxName = $row[2] ?? '';
        $eticket = $row[3] ?? ''; // ticket number with prefix
        
        if (empty($paxName) || empty($pnr) || empty($eticket)) {
            return null; // Skip invalid rows
        }
        
        // Extract ticket number (first 3 digits are prefix, rest is ticket number)
        $prefix = substr($eticket, 0, 3);
        $tickNu = substr($eticket, 3);
        
        $airline = $row[4] ?? '';
        $fp = $row[5] ?? '';
        $amount = $row[6] ?? '';
        $agent = $row[7] ?? '';
        $dI = $row[8] ?? '';
        $commisionPercentage = $row[9] ?? '';
        $commision = $row[10] ?? '';
        $status = $row[11] ?? '';
        $time = $row[12] ?? '';
        
        $nameParts = $this->parsePassengerName($paxName);
        $firstName = $nameParts['first_name'];
        $lastName = $nameParts['last_name'];
        
        // Find matching passenger
        $match = $this->ticketCheckDAL->findMatchingPassenger($pnr, $firstName, $lastName);
        
        $orderId = '';
        $paxId = '';
        
        if ($match) {
            $orderId = $match['order_id'];
            $paxId = $match['auto_id'];
        }
        
        // Check payment status
        $paymentStatus = '';
        if ($orderId) {
            $paymentStatus = $this->ticketCheckDAL->getPaymentStatus($orderId, $pnr);
        }
        
        // Check if ticket exists (use ticket number without prefix)
        $ticketExists = $this->ticketCheckDAL->checkTicketExists($pnr, $tickNu);
        
        // Determine match status
        $matchHidden = 'New';
        $match = 'New Record';
        $checked = false;
        
        if ($orderId && $paxId && $paymentStatus === 'paid') {
            if (!$ticketExists) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            } else {
                $matchHidden = 'New';
                $match = 'Duplicate';
                $checked = false;
            }
        }
        
        // Clean amount and commission (remove "AUD " prefix)
        $amount = str_replace("AUD ", "", $amount);
        $commision = str_replace("AUD ", "", $commision);
        
        return [
            'autonumber' => $autonumber,
            'pax_name' => $paxName,
            'pnr' => $pnr,
            'ticket' => $tickNu,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'order_id' => $orderId,
            'pax_id' => $paxId,
            'status' => $matchHidden,
            'match' => $match,
            'checked' => $checked,
            'row_data' => [
                'seq_no' => $seqNo,
                'confirmed' => '',
                'prefix' => $prefix,
                'amount' => $amount,
                'tax' => '',
                'fee' => '',
                'comm' => $commision,
                'agent' => $agent,
                'fp' => $fp,
                'a_s' => '',
                'trnc' => 'TKTT',
                'gds_from' => 'Sabre',
                'bank_type' => 'Sabre'
            ]
        ];
    }

    /**
     * Import ticket numbers
     */
    public function importTicketNumbers($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            // Only import records with status 'Existing'
            if (($record['match_hidden'] ?? '') !== 'Existing') {
                continue;
            }
            
            $orderId = $record['order_id'] ?? '';
            $paxId = $record['pax_id'] ?? '';
            $pnr = $record['pnr'] ?? '';
            $document = $record['document'] ?? '';
            $bankType = $record['bank_type'] ?? '';
            
            if (empty($orderId) || empty($paxId) || empty($pnr) || empty($document)) {
                continue; // Skip invalid records
            }
            
            // Map vendor
            $vendor = '';
            if ($bankType === 'IFNIATA') {
                $vendor = 'IFN IATA';
            } elseif ($bankType === 'GKTIATA') {
                $vendor = 'GKT IATA';
            } elseif ($bankType === 'Sabre') {
                $vendor = 'Sabre';
            }
            
            // Prepare ticket number data
            $ticketData = [
                'order_id' => $orderId,
                'pax_id' => $paxId,
                'pnr' => $pnr,
                'document' => $document,
                'document_type' => $record['trnc'] ?? 'TKTT',
                'vendor' => $vendor,
                'seq_no' => $record['seq_no'] ?? '',
                'confirmed' => $record['confirmed'] ?? '',
                'a_l' => $record['a_l'] ?? '',
                'transaction_amount' => $record['total_doc'] ?? ($record['amount'] ?? ''),
                'tax' => $record['tax'] ?? '',
                'fee' => $record['fee'] ?? '',
                'comm' => $record['comm'] ?? '',
                'agent' => $record['agent'] ?? '',
                'fp' => $record['fp'] ?? '',
                'pax_fname' => $record['first_name'] ?? '',
                'pax_lname' => $record['last_name'] ?? '',
                'pax_ticket_name' => $record['pax_name'] ?? '',
                'a_s' => $record['a_s'] ?? '',
                'updated_on' => $now,
                'updated_by' => $updatedBy,
                'gds_from' => $record['gds_from'] ?? 'Amadeus'
            ];
            
            // Insert ticket number
            $this->ticketCheckDAL->insertTicketNumber($ticketData);
            
            // Update passenger status
            $this->ticketCheckDAL->updatePassengerStatus($paxId, $document, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

