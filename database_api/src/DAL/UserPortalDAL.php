<?php
/**
 * User Portal Data Access Layer
 * Handles all database operations for user portal requests
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class UserPortalDAL extends BaseDAL
{
    /**
     * Update or insert user portal request meta
     */
    public function upsertRequestMeta($caseId, $metaKey, $metaValue)
    {
        $query = "
            INSERT INTO wpk4_backend_user_portal_request_meta 
                (case_id, meta_key, meta_value)
            VALUES 
                (:case_id, :meta_key, :meta_value)
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
        ";
        
        return $this->execute($query, [
            'case_id' => $caseId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue
        ]);
    }

    /**
     * Get payment status by order ID
     */
    public function getPaymentStatus($orderId)
    {
        // Get total paid amount
        $paymentQuery = "
            SELECT trams_received_amount 
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = :order_id 
                AND pay_type IN ('deposit', 'balance', 'Balance', 'Unknown', 'deposit_adjustment', 'additional_payment')
        ";
        $payments = $this->query($paymentQuery, ['order_id' => $orderId]);
        
        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += (float)$payment['trams_received_amount'];
        }
        
        // Get booking total amount
        $bookingQuery = "
            SELECT total_amount 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        $booking = $this->queryOne($bookingQuery, ['order_id' => $orderId]);
        $totalAmount = $booking ? (float)$booking['total_amount'] : 0;
        
        // Check BPay status
        $bpayQuery = "
            SELECT meta_key_data 
            FROM wpk4_backend_travel_booking_update_history 
            WHERE order_id = :order_id 
                AND meta_key = 'G360Events' 
                AND meta_value IN ('bpay_receipt_awaiting_approval', 'bpay_receipt_approved')
            LIMIT 1
        ";
        $bpayStatus = $this->queryOne($bpayQuery, ['order_id' => $orderId]);
        
        $bpayStatusValue = 0;
        if ($bpayStatus) {
            $bpayStatusValue = 2; // awaiting approval
            if ($bpayStatus['meta_value'] === 'bpay_receipt_approved') {
                $bpayStatusValue = 1; // approved
            }
        }
        
        return [
            'order_id' => $orderId,
            'total_paid' => $totalPaid,
            'total_amount' => $totalAmount,
            'balance' => $totalAmount - $totalPaid,
            'bpay_status' => $bpayStatusValue
        ];
    }
}

