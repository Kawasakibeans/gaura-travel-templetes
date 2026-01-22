<?php
/**
 * Sale ID Update Service - Business Logic Layer
 * Handles business logic for sale ID update requests
 */

namespace App\Services;

use App\DAL\SaleIdUpdateDAL;
use Exception;

class SaleIdUpdateService
{
    private $saleIdUpdateDAL;

    public function __construct()
    {
        $this->saleIdUpdateDAL = new SaleIdUpdateDAL();
    }

    /**
     * Get pending records with filters
     * 
     * @param string $orderIdFilter Order ID filter
     * @param string $dateFilter Date filter
     * @return array Formatted records
     */
    public function getPendingRecords($orderIdFilter = '', $dateFilter = '')
    {
        $records = $this->saleIdUpdateDAL->getPendingRecords($orderIdFilter, $dateFilter);
        
        return [
            'success' => true,
            'records' => $records,
            'count' => count($records),
            'filters' => [
                'order_id' => $orderIdFilter ?: 'all',
                'date' => $dateFilter ?: 'all'
            ],
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get a single record by ID
     * 
     * @param int $recordId Record ID
     * @return array Record data
     * @throws Exception If record not found
     */
    public function getRecordById($recordId)
    {
        if (!is_numeric($recordId) || $recordId <= 0) {
            throw new Exception('Invalid record ID', 400);
        }

        $record = $this->saleIdUpdateDAL->getRecordById($recordId);
        
        if (!$record) {
            throw new Exception('Record not found', 404);
        }

        return [
            'success' => true,
            'record' => $record
        ];
    }

    /**
     * Approve a record
     * 
     * @param int $recordId Record ID
     * @param string $modifiedBy User who is approving
     * @return array Success response
     * @throws Exception If approval fails
     */
    public function approveRecord($recordId, $modifiedBy = 'system')
    {
        if (!is_numeric($recordId) || $recordId <= 0) {
            throw new Exception('Invalid record ID', 400);
        }

        // Get the record first
        $record = $this->saleIdUpdateDAL->getRecordById($recordId);
        
        if (!$record) {
            throw new Exception('Record not found', 404);
        }

        // Check if already processed
        if (strtolower($record['status']) !== 'pending') {
            throw new Exception('Record is already processed', 400);
        }

        // Approve the record
        $result = $this->saleIdUpdateDAL->approveRecord(
            $recordId,
            $record['new_sale_id'],
            $record['order_id'],
            $modifiedBy
        );

        if (!$result) {
            throw new Exception('Failed to approve record', 500);
        }

        return [
            'success' => true,
            'message' => 'Record approved successfully',
            'record_id' => $recordId
        ];
    }

    /**
     * Reject a record
     * 
     * @param int $recordId Record ID
     * @param string $modifiedBy User who is rejecting
     * @return array Success response
     * @throws Exception If rejection fails
     */
    public function rejectRecord($recordId, $modifiedBy = 'system')
    {
        if (!is_numeric($recordId) || $recordId <= 0) {
            throw new Exception('Invalid record ID', 400);
        }

        // Get the record first
        $record = $this->saleIdUpdateDAL->getRecordById($recordId);
        
        if (!$record) {
            throw new Exception('Record not found', 404);
        }

        // Check if already processed
        if (strtolower($record['status']) !== 'pending') {
            throw new Exception('Record is already processed', 400);
        }

        // Reject the record
        $result = $this->saleIdUpdateDAL->rejectRecord($recordId, $modifiedBy);

        if (!$result) {
            throw new Exception('Failed to reject record', 500);
        }

        return [
            'success' => true,
            'message' => 'Record rejected successfully',
            'record_id' => $recordId
        ];
    }

    /**
     * Update check status
     * 
     * @param int $recordId Record ID
     * @param int $isChecked Check status (0 or 1)
     * @return array Success response
     * @throws Exception If update fails
     */
    public function updateCheckStatus($recordId, $isChecked)
    {
        if (!is_numeric($recordId) || $recordId <= 0) {
            throw new Exception('Invalid record ID', 400);
        }

        $isChecked = (int)$isChecked;
        if ($isChecked !== 0 && $isChecked !== 1) {
            throw new Exception('Invalid check status. Must be 0 or 1', 400);
        }

        $result = $this->saleIdUpdateDAL->updateCheckStatus($recordId, $isChecked);

        if (!$result) {
            throw new Exception('Failed to update check status', 500);
        }

        return [
            'success' => true,
            'message' => 'Check status updated successfully',
            'record_id' => $recordId,
            'is_checked' => $isChecked
        ];
    }

    /**
     * Get unique order IDs for filters
     * 
     * @return array Array of order IDs
     */
    public function getUniqueOrderIds()
    {
        return $this->saleIdUpdateDAL->getUniqueOrderIds();
    }

    /**
     * Get unique dates for filters
     * 
     * @return array Array of dates
     */
    public function getUniqueDates()
    {
        return $this->saleIdUpdateDAL->getUniqueDates();
    }
}

