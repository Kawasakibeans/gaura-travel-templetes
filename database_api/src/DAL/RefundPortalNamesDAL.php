<?php
/**
 * Refund Portal Names Data Access Layer
 * Handles database operations for refund portal names import
 */

namespace App\DAL;

use Exception;
use PDOException;

class RefundPortalNamesDAL extends BaseDAL
{
    /**
     * Check if refund record exists by refund_id
     */
    public function checkRefundExists($refundId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_travel_payment_refunds 
                WHERE refund_id = :refund_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['refund_id' => $refundId]);
        } catch (PDOException $e) {
            error_log("RefundPortalNamesDAL::checkRefundExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update refund record's canc_reason
     */
    public function updateRefundAccountName($refundId, $accountName)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_payment_refunds 
                SET canc_reason = :accountname
                WHERE refund_id = :refund_id
            ";
            
            return $this->execute($query, [
                'refund_id' => $refundId,
                'accountname' => $accountName
            ]);
        } catch (PDOException $e) {
            error_log("RefundPortalNamesDAL::updateRefundAccountName error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

