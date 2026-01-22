<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AzupayImportDAL extends BaseDAL
{
    /**
     * Get payment history by auto ID
     */
    public function getPaymentHistoryByAutoId($autoId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_payment_history
            WHERE auto_id = :auto_id
        ";
        return $this->queryOne($query, ['auto_id' => $autoId]);
    }
    
    /**
     * Update payment request ID
     */
    public function updatePaymentRequestId($autoId, $paymentRequestId)
    {
        return $this->update(
            'wpk4_backend_travel_payment_history',
            ['payment_request_id' => $paymentRequestId],
            ['auto_id' => $autoId]
        );
    }
    
    /**
     * Batch update payment request IDs
     */
    public function batchUpdatePaymentRequestIds($updates)
    {
        $updated = 0;
        foreach ($updates as $update) {
            $result = $this->updatePaymentRequestId($update['auto_id'], $update['payment_request_id']);
            if ($result !== false) {
                $updated++;
            }
        }
        return $updated;
    }
    
    /**
     * Insert history of updates
     */
    public function insertHistoryOfUpdates($orderId, $metaKey, $metaValue, $updatedBy)
    {
        $query = "
            INSERT INTO wpk4_backend_history_of_updates
            (type_id, meta_key, meta_value, updated_by, updated_on)
            VALUES (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
        ";
        
        $params = [
            'type_id' => $orderId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($query, $params);
    }
    
    /**
     * Check multiple payment history records
     */
    public function checkPaymentHistoryRecords($autoIds)
    {
        if (empty($autoIds)) {
            return [];
        }
        
        $placeholders = [];
        $params = [];
        foreach ($autoIds as $idx => $autoId) {
            $key = 'auto_id' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $autoId;
        }
        
        $query = "
            SELECT auto_id, payment_request_id FROM wpk4_backend_travel_payment_history
            WHERE auto_id IN (" . implode(',', $placeholders) . ")
        ";
        
        return $this->query($query, $params);
    }
}

