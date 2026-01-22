<?php
/**
 * AsiaPay Settlement Data Access Layer
 * Handles database operations for AsiaPay settlement
 */

namespace App\DAL;

use Exception;
use PDOException;

class AsiaPaySettlementDAL extends BaseDAL
{
    /**
     * Get booking payment status
     */
    public function getBookingPaymentStatus($orderId)
    {
        try {
            $query = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1";
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("AsiaPaySettlementDAL::getBookingPaymentStatus error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if payment exists (PAYID)
     */
    public function paymentExistsPayid($orderId, $referenceNo, $paymentMethod = '8')
    {
        try {
            $query = "
                SELECT order_id FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id 
                AND reference_no = :reference_no 
                AND payment_method = :payment_method
                LIMIT 1
            ";
            $result = $this->queryOne($query, [
                'order_id' => $orderId,
                'reference_no' => $referenceNo,
                'payment_method' => $paymentMethod
            ]);
            return $result !== null;
        } catch (PDOException $e) {
            error_log("AsiaPaySettlementDAL::paymentExistsPayid error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if payment exists (Card - VISA/Master)
     */
    public function paymentExistsCard($orderId, $referenceNo, $amount, $paymentMethod = '8')
    {
        try {
            $query = "
                SELECT order_id FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id 
                AND reference_no = :reference_no 
                AND (ABS(CAST(trams_received_amount AS DECIMAL(10,2)) - CAST(:amount AS DECIMAL(10,2))) < 0.5 
                     OR CAST(trams_received_amount AS DECIMAL(10,2)) = CAST(:amount AS DECIMAL(10,2))) 
                AND payment_method = :payment_method
                LIMIT 1
            ";
            $result = $this->queryOne($query, [
                'order_id' => $orderId,
                'reference_no' => $referenceNo,
                'amount' => $amount,
                'payment_method' => $paymentMethod
            ]);
            return $result !== null;
        } catch (PDOException $e) {
            error_log("AsiaPaySettlementDAL::paymentExistsCard error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update payment settlement status
     */
    public function updateSettlementStatus($orderId, $referenceNo, $amount, $settlementDate, $paymentMethod = '8')
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_payment_history 
                SET is_reconciliated = 'yes',
                    cleared_date = :cleared_date,
                    cleared_by = 'asiapay_settlement_api'
                WHERE order_id = :order_id 
                AND reference_no = :reference_no 
                AND (CAST(trams_received_amount AS DECIMAL(10,2)) = CAST(:amount AS DECIMAL(10,2))) 
                AND payment_method = :payment_method
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'reference_no' => $referenceNo,
                'amount' => $amount,
                'cleared_date' => $settlementDate,
                'payment_method' => $paymentMethod
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPaySettlementDAL::updateSettlementStatus error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

