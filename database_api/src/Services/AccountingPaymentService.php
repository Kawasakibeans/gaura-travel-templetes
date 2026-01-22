<?php

namespace App\Services;

use App\DAL\AccountingPaymentDAL;

class AccountingPaymentService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AccountingPaymentDAL();
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        
        if (empty($orderId)) {
            return [
                'payments' => [],
                'total' => 0
            ];
        }
        
        $payments = $this->dal->getPaymentHistory($orderId);
        
        return [
            'payments' => $payments,
            'total' => count($payments)
        ];
    }
    
    /**
     * Update payment records
     */
    public function updatePaymentRecords(array $params): array
    {
        $updatedRecords = $params['updated_records'] ?? [];
        $clearedBy = $params['cleared_by'] ?? null;
        
        if (empty($updatedRecords)) {
            throw new \Exception('No records to update');
        }
        
        $successCount = 0;
        $errors = [];
        
        foreach ($updatedRecords as $autoId => $updates) {
            $autoIdInt = (int)$autoId;
            
            if ($clearedBy) {
                $updates['cleared_by'] = $clearedBy;
            }
            
            try {
                if ($this->dal->updatePaymentRecord($autoIdInt, $updates)) {
                    $successCount++;
                } else {
                    $errors[] = "Failed to update record ID: $autoId";
                }
            } catch (\Exception $e) {
                $errors[] = "Error updating record ID $autoId: " . $e->getMessage();
            }
        }
        
        return [
            'success' => $successCount > 0,
            'updated_count' => $successCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Delete payment record
     */
    public function deletePaymentRecord(array $params): array
    {
        $autoId = $params['auto_id'] ?? null;
        
        if (empty($autoId)) {
            throw new \Exception('auto_id is required');
        }
        
        $success = $this->dal->deletePaymentRecord((int)$autoId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Record deleted successfully' : 'Failed to delete record or record already cleared'
        ];
    }
}

