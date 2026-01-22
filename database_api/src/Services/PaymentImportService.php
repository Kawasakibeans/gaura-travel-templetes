<?php

namespace App\Services;

use App\DAL\PaymentImportDAL;
use Exception;

class PaymentImportService
{
    private $paymentImportDAL;
    
    public function __construct()
    {
        $this->paymentImportDAL = new PaymentImportDAL();
    }
    
    /**
     * Parse CSV for process date import
     */
    public function parseProcessDateCsv($csvContent)
    {
        if (empty($csvContent)) {
            throw new Exception("CSV content is empty", 400);
        }
        
        $lines = explode("\n", $csvContent);
        $data = [];
        $headerSkipped = false;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, ",");
            
            // Skip header row
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'auto_id' && isset($columns[1]) && $columns[1] === 'process_date') {
                $headerSkipped = true;
                continue;
            }
            
            if (count($columns) < 3) {
                continue;
            }
            
            $autoId = trim($columns[0]);
            $processDate = trim($columns[1]);
            $paymentMethod = trim($columns[2]);
            
            if (empty($autoId) || !is_numeric($autoId)) {
                continue;
            }
            
            $data[] = [
                'auto_id' => (int)$autoId,
                'process_date' => $processDate,
                'payment_method' => $paymentMethod,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check process date CSV data
     */
    public function checkProcessDateCsv($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $autoIds = array_column($csvData, 'auto_id');
        $existingRecords = $this->paymentImportDAL->checkPaymentHistoryRecords($autoIds);
        
        $existingMap = [];
        foreach ($existingRecords as $record) {
            $existingMap[$record['auto_id']] = $record;
        }
        
        $results = [];
        foreach ($csvData as $row) {
            $autoId = $row['auto_id'];
            $exists = isset($existingMap[$autoId]);
            
            $results[] = [
                'auto_id' => $autoId,
                'process_date' => $row['process_date'],
                'payment_method' => $row['payment_method'],
                'exists' => $exists,
                'current_process_date' => $exists ? ($existingMap[$autoId]['process_date'] ?? null) : null,
                'current_payment_method' => $exists ? ($existingMap[$autoId]['payment_method'] ?? null) : null,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Update process dates
     */
    public function updateProcessDates($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $processDate = $update['process_date'] ?? null;
            $paymentMethod = $update['payment_method'] ?? null;
            
            if (empty($autoId) || !is_numeric($autoId)) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Invalid auto_id'
                ];
                continue;
            }
            
            $existing = $this->paymentImportDAL->getPaymentHistoryByAutoId((int)$autoId);
            if (!$existing) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Record not found'
                ];
                continue;
            }
            
            $updated = $this->paymentImportDAL->updatePaymentProcessDate(
                (int)$autoId,
                $processDate,
                $paymentMethod
            );
            
            if ($updated !== false) {
                $successCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => true,
                    'message' => 'Updated successfully'
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Update failed'
                ];
            }
        }
        
        return [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
    
    /**
     * Parse CSV for settlement date import
     */
    public function parseSettlementDateCsv($csvContent)
    {
        if (empty($csvContent)) {
            throw new Exception("CSV content is empty", 400);
        }
        
        $lines = explode("\n", $csvContent);
        $data = [];
        $headerSkipped = false;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, ",");
            
            // Skip header row
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'AutoID' && isset($columns[1]) && $columns[1] === 'Date') {
                $headerSkipped = true;
                continue;
            }
            
            if (count($columns) < 2) {
                continue;
            }
            
            $autoId = trim($columns[0]);
            $date = trim($columns[1]);
            
            if (empty($autoId) || !is_numeric($autoId)) {
                continue;
            }
            
            // Convert date format from d/m/Y to Y-m-d H:i:s
            $settlementDate = strtotime(str_replace('/', '-', $date));
            $newSettlementDate = date('Y-m-d H:i:s', $settlementDate);
            
            $data[] = [
                'auto_id' => (int)$autoId,
                'date' => $date,
                'settlement_date' => $newSettlementDate,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check settlement date CSV data
     */
    public function checkSettlementDateCsv($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $autoIds = array_column($csvData, 'auto_id');
        $existingRecords = $this->paymentImportDAL->checkPaymentHistoryRecords($autoIds);
        
        $existingMap = [];
        foreach ($existingRecords as $record) {
            $existingMap[$record['auto_id']] = $record;
        }
        
        $results = [];
        foreach ($csvData as $row) {
            $autoId = $row['auto_id'];
            $exists = isset($existingMap[$autoId]);
            
            $results[] = [
                'auto_id' => $autoId,
                'settlement_date' => $row['settlement_date'],
                'exists' => $exists,
                'current_cleared_date' => $exists ? ($existingMap[$autoId]['cleared_date'] ?? null) : null,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Update settlement dates
     */
    public function updateSettlementDates($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $clearedDate = $update['cleared_date'] ?? $update['settlement_date'] ?? null;
            
            if (empty($autoId) || !is_numeric($autoId)) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Invalid auto_id'
                ];
                continue;
            }
            
            $existing = $this->paymentImportDAL->getPaymentHistoryByAutoId((int)$autoId);
            if (!$existing) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Record not found'
                ];
                continue;
            }
            
            $updated = $this->paymentImportDAL->updatePaymentSettlementDate(
                (int)$autoId,
                $clearedDate
            );
            
            if ($updated !== false) {
                $successCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => true,
                    'message' => 'Updated successfully'
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Update failed'
                ];
            }
        }
        
        return [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
    
    /**
     * Parse CSV for payment status by PNR
     */
    public function parsePaymentStatusByPnrCsv($csvContent)
    {
        if (empty($csvContent)) {
            throw new Exception("CSV content is empty", 400);
        }
        
        $lines = explode("\n", $csvContent);
        $data = [];
        $headerSkipped = false;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, ",");
            
            // Skip header row
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'pnr' && isset($columns[1]) && $columns[1] === 'payment_status') {
                $headerSkipped = true;
                continue;
            }
            
            if (count($columns) < 2) {
                continue;
            }
            
            $pnr = trim($columns[0]);
            $paymentStatus = trim($columns[1]);
            
            if (empty($pnr)) {
                continue;
            }
            
            $data[] = [
                'pnr' => $pnr,
                'payment_status' => $paymentStatus,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check payment status by PNR CSV data
     */
    public function checkPaymentStatusByPnrCsv($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $results = [];
        
        foreach ($csvData as $row) {
            $pnr = $row['pnr'];
            $paxRecord = $this->paymentImportDAL->getBookingPaxByPnr($pnr);
            
            $exists = !empty($paxRecord);
            
            $results[] = [
                'pnr' => $pnr,
                'payment_status' => $row['payment_status'],
                'exists' => $exists,
                'order_id' => $exists ? ($paxRecord['order_id'] ?? null) : null,
                'auto_id' => $exists ? ($paxRecord['auto_id'] ?? null) : null,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Update payment status by PNR
     */
    public function updatePaymentStatusByPnr($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            $pnr = $update['pnr'] ?? null;
            $paymentStatus = $update['payment_status'] ?? null;
            
            if (empty($pnr) || empty($paymentStatus)) {
                $errorCount++;
                $results[] = [
                    'pnr' => $pnr,
                    'success' => false,
                    'message' => 'PNR and payment_status are required'
                ];
                continue;
            }
            
            $paxRecord = $this->paymentImportDAL->getBookingPaxByPnr($pnr);
            if (!$paxRecord) {
                $errorCount++;
                $results[] = [
                    'pnr' => $pnr,
                    'success' => false,
                    'message' => 'PNR not found'
                ];
                continue;
            }
            
            $orderId = $paxRecord['order_id'];
            
            $updated = $this->paymentImportDAL->updateBookingPaymentStatus($orderId, $paymentStatus);
            
            if ($updated !== false) {
                // Insert history
                $this->paymentImportDAL->insertHistoryOfUpdates(
                    $orderId,
                    'payment_status',
                    $paymentStatus,
                    $updatedBy
                );
                
                $successCount++;
                $results[] = [
                    'pnr' => $pnr,
                    'order_id' => $orderId,
                    'success' => true,
                    'message' => 'Updated successfully'
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'pnr' => $pnr,
                    'success' => false,
                    'message' => 'Update failed'
                ];
            }
        }
        
        return [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
    
    /**
     * Parse CSV for payments import (complex)
     */
    public function parsePaymentsCsv($csvContent)
    {
        if (empty($csvContent)) {
            throw new Exception("CSV content is empty", 400);
        }
        
        $lines = explode("\n", $csvContent);
        $data = [];
        $headerSkipped = false;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, ",");
            
            // Skip header row
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'order_id') {
                $headerSkipped = true;
                continue;
            }
            
            if (empty($columns[0]) || !isset($columns[0])) {
                break;
            }
            
            $orderId = trim($columns[0]);
            $profileNo = trim($columns[1] ?? '');
            $referenceNo = trim($columns[2] ?? '');
            $amountPaid = isset($columns[3]) && $columns[3] !== '' ? number_format((float)$columns[3], 2, '.', '') : '';
            $paymentMethod = isset($columns[4]) && $columns[4] !== '' ? $columns[4] : '';
            $processedDate = isset($columns[5]) && $columns[5] !== '' ? $columns[5] : '';
            $remarks = isset($columns[6]) && $columns[6] !== '' ? $columns[6] : '';
            $paymentType = isset($columns[7]) && $columns[7] !== '' ? $columns[7] : '';
            
            // Special handling for payment method 9
            if ($paymentMethod == '9') {
                $orderId = substr($referenceNo, 0, -1);
                $orderId = ltrim($orderId, '0');
                if ($orderId == '') {
                    $orderId = trim($columns[0]);
                }
            }
            
            // Determine source
            $source = (ctype_digit($orderId) && strlen($orderId) <= 7) ? 'WPT' : 'gds';
            
            $data[] = [
                'order_id' => $orderId,
                'profile_no' => $profileNo,
                'reference_no' => $referenceNo,
                'amount_paid' => $amountPaid,
                'payment_method' => $paymentMethod,
                'processed_date' => $processedDate,
                'remarks' => $remarks,
                'payment_type' => $paymentType,
                'source' => $source,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check payments CSV data
     */
    public function checkPaymentsCsv($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $results = [];
        
        foreach ($csvData as $row) {
            $orderId = $row['order_id'];
            $amountPaid = $row['amount_paid'];
            $processedDate = $row['processed_date'];
            $referenceNo = $row['reference_no'];
            
            // Find booking
            $paxRecord = $this->paymentImportDAL->getBookingPaxByOrderIdOrPnr($orderId);
            
            if (!$paxRecord) {
                $results[] = [
                    'order_id' => $orderId,
                    'exists' => false,
                    'message' => 'Order/PNR not found',
                    'line_number' => $row['line_number']
                ];
                continue;
            }
            
            $bookingOrderId = $paxRecord['order_id'];
            $booking = $this->paymentImportDAL->getBookingByOrderId($bookingOrderId);
            
            if (!$booking) {
                $results[] = [
                    'order_id' => $orderId,
                    'exists' => false,
                    'message' => 'Booking not found',
                    'line_number' => $row['line_number']
                ];
                continue;
            }
            
            $totalAmount = number_format((float)($booking['total_amount'] ?? 0), 2, '.', '');
            $currentPaymentStatus = $booking['payment_status'] ?? '';
            
            // Get total received amount
            $totalReceived = $this->paymentImportDAL->getTotalReceivedAmount($orderId);
            $totalReceivedFormatted = number_format($totalReceived, 2, '.', '');
            
            // Check if payment already exists
            $existingPayment = $this->paymentImportDAL->getPaymentHistoryByDetails(
                $orderId,
                $amountPaid,
                $processedDate,
                $referenceNo
            );
            
            $exists = !empty($existingPayment);
            
            // Calculate balance
            $balance = number_format((float)($totalAmount - $totalReceived) - (float)$amountPaid, 2, '.', '');
            
            // Determine if fully paid
            $isPaidFully = '';
            if ($currentPaymentStatus != 'paid') {
                if ($balance == 0) {
                    $isPaidFully = 'paid';
                } elseif (abs($balance) < 1) {
                    $isPaidFully = 'paid';
                }
            }
            
            $results[] = [
                'order_id' => $orderId,
                'booking_order_id' => $bookingOrderId,
                'source' => $row['source'],
                'profile_no' => $row['profile_no'],
                'reference_no' => $referenceNo,
                'amount_paid' => $amountPaid,
                'payment_method' => $row['payment_method'],
                'processed_date' => $processedDate,
                'remarks' => $row['remarks'],
                'payment_type' => $row['payment_type'],
                'total_amount' => $totalAmount,
                'already_paid' => $totalReceivedFormatted,
                'balance' => $balance,
                'is_paid_fully' => $isPaidFully,
                'current_payment_status' => $currentPaymentStatus,
                'exists' => $exists,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Import payments (complex logic)
     */
    public function importPayments($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            try {
                $orderId = $update['order_id'] ?? null;
                $profileNo = $update['profile_no'] ?? '';
                $referenceNo = $update['reference_no'] ?? '';
                $amountPaid = $update['amount_paid'] ?? '0.00';
                $paymentMethod = $update['payment_method'] ?? '';
                $processedDate = $update['processed_date'] ?? date('Y-m-d H:i:s');
                $remarks = $update['remarks'] ?? '';
                $source = $update['source'] ?? 'gds';
                $paymentType = $update['payment_type'] ?? '';
                $bookingOrderId = $update['booking_order_id'] ?? $orderId;
                $paymentStatusForBooking = $update['payment_status_for_booking'] ?? '';
                $currentPaymentStatus = $update['current_payment_status'] ?? '';
                
                if (empty($orderId)) {
                    $errorCount++;
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => false,
                        'message' => 'Order ID is required'
                    ];
                    continue;
                }
                
                // Get booking order date
                $orderDate = $this->paymentImportDAL->getBookingOrderDate($bookingOrderId);
                if (!$orderDate) {
                    $orderDate = date('Y-m-d H:i:s');
                }
                
                // Calculate payment refund deadline (96 hours after order date)
                $paymentRefundDeadline = date('Y-m-d H:i:s', strtotime($orderDate . ' +96 hours'));
                
                // Get invoice ID
                $invoiceId = $this->paymentImportDAL->getPaymentInvoiceId($bookingOrderId);
                
                // Insert payment history
                $paymentData = [
                    'order_id' => $orderId,
                    'profile_no' => $profileNo,
                    'reference_no' => $referenceNo,
                    'trams_received_amount' => $amountPaid,
                    'payment_method' => $paymentMethod,
                    'process_date' => $processedDate,
                    'trams_remarks' => $remarks,
                    'source' => $source,
                    'pay_type' => $paymentType,
                    'added_by' => $updatedBy,
                    'added_on' => date('Y-m-d H:i:s'),
                    'payment_change_deadline' => $paymentRefundDeadline,
                    'gaura_invoice_id' => $invoiceId
                ];
                
                $inserted = $this->paymentImportDAL->insertPaymentHistory($paymentData);
                
                if ($inserted === false) {
                    $errorCount++;
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => false,
                        'message' => 'Failed to insert payment history'
                    ];
                    continue;
                }
                
                // Update booking payment status if needed
                if ($paymentStatusForBooking == 'paid' && $currentPaymentStatus != 'paid') {
                    $this->paymentImportDAL->updateBookingPaymentStatus($bookingOrderId, 'paid');
                    
                    // Insert booking update history
                    $this->paymentImportDAL->insertBookingUpdateHistory(
                        $bookingOrderId,
                        'payment_status by payment import',
                        'paid',
                        date('Y-m-d H:i:s'),
                        $updatedBy
                    );
                    
                    // Get customer email and send notification (this would require email service)
                    $customerEmail = $this->paymentImportDAL->getCustomerEmail($bookingOrderId);
                    
                    if ($customerEmail) {
                        $emailSubject = "Payment Information - " . $bookingOrderId;
                        $this->paymentImportDAL->insertOrderEmailHistory(
                            'Payment Update',
                            $bookingOrderId,
                            $customerEmail,
                            date('Y-m-d H:i:s'),
                            $updatedBy,
                            $emailSubject
                        );
                    }
                }
                
                $successCount++;
                $results[] = [
                    'order_id' => $orderId,
                    'booking_order_id' => $bookingOrderId,
                    'success' => true,
                    'message' => 'Payment imported successfully'
                ];
                
            } catch (Exception $e) {
                $errorCount++;
                $results[] = [
                    'order_id' => $update['order_id'] ?? null,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
    
    /**
     * Parse CSV for order ID and source import
     */
    public function parseOrderIdAndSourceCsv($csvContent)
    {
        if (empty($csvContent)) {
            throw new Exception("CSV content is empty", 400);
        }
        
        $lines = explode("\n", $csvContent);
        $data = [];
        $headerSkipped = false;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, ",");
            
            // Skip header row
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'id' && isset($columns[1]) && $columns[1] === 'order_id') {
                $headerSkipped = true;
                continue;
            }
            
            if (count($columns) < 2) {
                continue;
            }
            
            $autoId = trim($columns[0] ?? '');
            $orderId = trim($columns[1] ?? '');
            $source = trim($columns[2] ?? '');
            
            if (empty($autoId) || !is_numeric($autoId)) {
                continue;
            }
            
            $data[] = [
                'auto_id' => (int)$autoId,
                'order_id' => $orderId,
                'source' => $source,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check order ID and source CSV data
     */
    public function checkOrderIdAndSourceCsv($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $autoIds = array_column($csvData, 'auto_id');
        $existingRecords = $this->paymentImportDAL->checkPaymentHistoryRecords($autoIds);
        
        $existingMap = [];
        foreach ($existingRecords as $record) {
            $existingMap[$record['auto_id']] = $record;
        }
        
        $results = [];
        foreach ($csvData as $row) {
            $autoId = $row['auto_id'];
            $exists = isset($existingMap[$autoId]);
            
            $results[] = [
                'auto_id' => $autoId,
                'order_id' => $row['order_id'],
                'source' => $row['source'],
                'exists' => $exists,
                'current_order_id' => $exists ? ($existingMap[$autoId]['order_id'] ?? null) : null,
                'current_source' => $exists ? ($existingMap[$autoId]['source'] ?? null) : null,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Update order IDs and sources
     */
    public function updateOrderIdsAndSources($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $orderId = $update['order_id'] ?? null;
            $source = $update['source'] ?? '';
            
            if (empty($autoId) || !is_numeric($autoId)) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Invalid auto_id'
                ];
                continue;
            }
            
            $existing = $this->paymentImportDAL->getPaymentHistoryByAutoId((int)$autoId);
            if (!$existing) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Record not found'
                ];
                continue;
            }
            
            $updated = $this->paymentImportDAL->updatePaymentOrderIdAndSource(
                (int)$autoId,
                $orderId,
                $source
            );
            
            if ($updated !== false) {
                // Insert history for order_id
                $this->paymentImportDAL->insertHistoryOfUpdates(
                    (string)$autoId,
                    'payment order_id',
                    $orderId,
                    $updatedBy
                );
                
                // Insert history for source
                if (!empty($source)) {
                    $this->paymentImportDAL->insertHistoryOfUpdates(
                        (string)$autoId,
                        'payment source',
                        $source,
                        $updatedBy
                    );
                }
                
                $successCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => true,
                    'message' => 'Updated successfully'
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Update failed'
                ];
            }
        }
        
        return [
            'total' => count($updates),
            'success' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
}

