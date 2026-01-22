<?php
namespace App\Services;

use App\DAL\ImportCustomerIdDAL;
use Exception;

class ImportCustomerIdService
{
    private $importCustomerIdDAL;

    public function __construct()
    {
        $this->importCustomerIdDAL = new ImportCustomerIdDAL();
    }

    /**
     * Validate CSV data
     */
    public function validateCsvData($csvData)
    {
        if (empty($csvData) || !is_array($csvData)) {
            throw new Exception('CSV data is required and must be an array', 400);
        }

        $results = $this->importCustomerIdDAL->validateCsvData($csvData);
        
        return [
            'success' => true,
            'data' => $results
        ];
    }

    /**
     * Update customer ID and family ID
     */
    public function updateCustomerAndFamilyId($updates)
    {
        if (empty($updates) || !is_array($updates)) {
            throw new Exception('Updates array is required', 400);
        }

        // Validate each update
        foreach ($updates as $update) {
            if (empty($update['auto_id']) || empty($update['order_id']) || 
                empty($update['family_id']) || empty($update['customer_id'])) {
                throw new Exception('Each update must contain auto_id, order_id, family_id, and customer_id', 400);
            }
        }

        $updatedCount = $this->importCustomerIdDAL->batchUpdate($updates);
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Get pax record by auto_id
     */
    public function getPaxByAutoId($autoId)
    {
        if (empty($autoId)) {
            throw new Exception('Auto ID is required', 400);
        }

        $result = $this->importCustomerIdDAL->getPaxByAutoId($autoId);
        
        if (!$result) {
            throw new Exception('Pax record not found', 404);
        }
        
        return $result;
    }
}

