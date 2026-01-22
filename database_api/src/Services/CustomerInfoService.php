<?php
/**
 * Customer Info Service - Business Logic Layer
 * Handles customer info ETL operations
 */

namespace App\Services;

use App\DAL\CustomerInfoDAL;
use Exception;

class CustomerInfoService
{
    private $customerInfoDAL;

    public function __construct()
    {
        $this->customerInfoDAL = new CustomerInfoDAL();
    }

    /**
     * Update customer info for a date window
     * Note: This is a simplified version. Full implementation would include
     * chunking, staging tables, and complex merge logic.
     */
    public function updateCustomerInfo($from, $to, $chunkHours = 6, $batchRows = 500)
    {
        if (empty($from) || empty($to)) {
            throw new Exception('From and To dates are required', 400);
        }

        $stats = [
            'window' => ['from' => $from, 'to' => $to],
            'chunks' => ['hours' => $chunkHours, 'count' => 0],
            'staged' => 0,
            'merged' => 0,
            'inserted_info' => 0,
            'orders' => [
                'considered' => 0,
                'crn_backfilled' => 0
            ],
            'event' => ['elog_backfilled' => 0],
            'inbound' => ['backfilled' => 0],
            'quote' => ['backfilled' => 0]
        ];

        // Get PAX records
        $paxRecords = $this->customerInfoDAL->getPaxByDateWindow($from, $to);
        
        // Get event log records
        $eventLogRecords = $this->customerInfoDAL->getEventLogByDateWindow($from, $to);
        $stats['event']['rows'] = count($eventLogRecords);
        
        // Get inbound records
        $inboundRecords = $this->customerInfoDAL->getInboundCallsByDateWindow($from, $to);
        $stats['inbound']['rows'] = count($inboundRecords);
        
        // Get quote records
        $quoteRecords = $this->customerInfoDAL->getQuotesByDateWindow($from, $to);
        $stats['quote']['rows'] = count($quoteRecords);

        // Process and create/update customer info records
        // This is simplified - full version would have complex merge logic
        $processed = [];
        
        foreach ($paxRecords as $pax) {
            $email = $this->cleanEmail($pax['email_pax'] ?? '');
            $phone = $this->normalizePhoneAU($pax['phone_pax'] ?? '');
            
            if ($email === '' && $phone === '') {
                continue;
            }
            
            $crn = $this->customerInfoDAL->insertContactStrict([
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'fname' => trim($pax['fname'] ?? '') ?: null,
                'lname' => trim($pax['lname'] ?? '') ?: null,
                'gender' => trim($pax['gender'] ?? '') ?: null,
                'dob' => $pax['dob'] ?? null,
                'lifecycle' => 'customer'
            ]);
            
            if ($crn && $crn !== false) {
                $orderId = $pax['order_id'] ?? null;
                if ($orderId) {
                    $this->customerInfoDAL->updatePaxCrnByOrderId($crn, $orderId);
                    $stats['orders']['crn_backfilled']++;
                }
                $stats['inserted_info']++;
            }
        }

        return [
            'ok' => true,
            'window' => [$from, $to],
            'chunk_hours' => $chunkHours,
            'batch_rows' => $batchRows,
            'stats' => $stats,
            'notes' => [
                'simplified' => 'This is a simplified version. Full implementation includes complex merge logic, staging tables, and chunking.'
            ]
        ];
    }

    private function cleanEmail($raw)
    {
        $email = strtolower(trim((string)$raw));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function normalizePhoneAU($raw)
    {
        if ($raw === null) return '';
        $digits = preg_replace('/\D+/', '', (string)$raw);
        if ($digits === '') return '';
        if (strpos($digits, '61') === 0) {
            $rest = substr($digits, 2);
            return strlen($rest) >= 8 ? '61' . $rest : '';
        }
        if ($digits[0] === '0') {
            $rest = substr($digits, 1);
            return strlen($rest) >= 8 ? '61' . $rest : '';
        }
        return (strlen($digits) >= 8) ? '61' . $digits : '';
    }
}

