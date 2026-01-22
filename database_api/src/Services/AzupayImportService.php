<?php

namespace App\Services;

use App\DAL\AzupayImportDAL;
use Exception;

class AzupayImportService
{
    private $azupayImportDAL;
    
    public function __construct()
    {
        $this->azupayImportDAL = new AzupayImportDAL();
    }
    
    /**
     * Parse and validate CSV file
     */
    public function parseAndValidateCsv($csvContent)
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
            if (!$headerSkipped && isset($columns[0]) && $columns[0] === 'id' && isset($columns[1]) && $columns[1] === 'payment_request_id') {
                $headerSkipped = true;
                continue;
            }
            
            if (count($columns) < 2) {
                continue;
            }
            
            $autoId = trim($columns[0]);
            $paymentRequestId = trim($columns[1]);
            
            if (empty($autoId) || !is_numeric($autoId)) {
                continue;
            }
            
            $data[] = [
                'auto_id' => (int)$autoId,
                'payment_request_id' => $paymentRequestId,
                'line_number' => $lineNum + 1
            ];
        }
        
        return $data;
    }
    
    /**
     * Check CSV data against database
     */
    public function checkCsvData($csvData)
    {
        if (empty($csvData)) {
            throw new Exception("No data to check", 400);
        }
        
        $autoIds = array_column($csvData, 'auto_id');
        $existingRecords = $this->azupayImportDAL->checkPaymentHistoryRecords($autoIds);
        
        // Create a map of existing records
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
                'payment_request_id' => $row['payment_request_id'],
                'exists' => $exists,
                'current_payment_request_id' => $exists ? ($existingMap[$autoId]['payment_request_id'] ?? null) : null,
                'line_number' => $row['line_number']
            ];
        }
        
        return $results;
    }
    
    /**
     * Update payment request IDs
     */
    public function updatePaymentRequestIds($updates, $updatedBy = 'api')
    {
        if (empty($updates)) {
            throw new Exception("No updates provided", 400);
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $paymentRequestId = $update['payment_request_id'] ?? null;
            
            if (empty($autoId) || !is_numeric($autoId)) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Invalid auto_id'
                ];
                continue;
            }
            
            // Check if record exists
            $existing = $this->azupayImportDAL->getPaymentHistoryByAutoId((int)$autoId);
            if (!$existing) {
                $errorCount++;
                $results[] = [
                    'auto_id' => $autoId,
                    'success' => false,
                    'message' => 'Record not found'
                ];
                continue;
            }
            
            // Update payment request ID
            $updated = $this->azupayImportDAL->updatePaymentRequestId((int)$autoId, $paymentRequestId);
            
            if ($updated !== false) {
                // Insert history
                $this->azupayImportDAL->insertHistoryOfUpdates(
                    (string)$autoId,
                    'payment payment_request_id',
                    $paymentRequestId,
                    $updatedBy
                );
                
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
     * Process CSV file upload
     */
    public function processCsvUpload($csvContent, $updatedBy = 'api')
    {
        // Parse CSV
        $csvData = $this->parseAndValidateCsv($csvContent);
        
        if (empty($csvData)) {
            throw new Exception("No valid data found in CSV", 400);
        }
        
        // Check data
        $checkedData = $this->checkCsvData($csvData);
        
        // Filter only existing records for update
        $updates = [];
        foreach ($checkedData as $row) {
            if ($row['exists']) {
                $updates[] = [
                    'auto_id' => $row['auto_id'],
                    'payment_request_id' => $row['payment_request_id']
                ];
            }
        }
        
        // Perform updates
        $updateResults = null;
        if (!empty($updates)) {
            $updateResults = $this->updatePaymentRequestIds($updates, $updatedBy);
        }
        
        return [
            'csv_data' => $checkedData,
            'updates' => $updateResults,
            'summary' => [
                'total_rows' => count($csvData),
                'existing_records' => count($updates),
                'new_records' => count($csvData) - count($updates)
            ]
        ];
    }
}

