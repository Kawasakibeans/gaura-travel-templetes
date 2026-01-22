<?php
/**
 * Accounting Payment Data Access Layer
 * Handles database operations for payment history management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AccountingPaymentDAL extends BaseDAL
{
    /**
     * Get payment history by order ID
     */
    public function getPaymentHistory(?string $orderId = null): array
    {
        if (empty($orderId)) {
            return [];
        }
        
        $sql = "
            SELECT 
                auto_id,
                process_date,
                trams_received_amount,
                reference_no,
                trams_remarks,
                cleared_date,
                order_id,
                is_reconciliated
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = :order_id
            ORDER BY process_date DESC
        ";
        
        return $this->query($sql, [':order_id' => $orderId]);
    }
    
    /**
     * Update payment record
     */
    public function updatePaymentRecord(int $autoId, array $updates): bool
    {
        $fields = [];
        $params = [':auto_id' => $autoId];
        
        if (isset($updates['cleared_date'])) {
            $fields[] = 'cleared_date = :cleared_date';
            $params[':cleared_date'] = $updates['cleared_date'];
        }
        
        if (isset($updates['is_reconciliated'])) {
            $fields[] = 'is_reconciliated = :is_reconciliated';
            $params[':is_reconciliated'] = $updates['is_reconciliated'] ? 'yes' : 'no';
        }
        
        if (isset($updates['cleared_by'])) {
            $fields[] = 'cleared_by = :cleared_by';
            $params[':cleared_by'] = $updates['cleared_by'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "
            UPDATE wpk4_backend_travel_payment_history 
            SET " . implode(', ', $fields) . "
            WHERE auto_id = :auto_id
        ";
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Delete payment record
     */
    public function deletePaymentRecord(int $autoId): bool
    {
        $sql = "
            DELETE FROM wpk4_backend_travel_payment_history 
            WHERE auto_id = :auto_id AND cleared_date IS NULL
        ";
        
        return $this->execute($sql, [':auto_id' => $autoId]);
    }
}

