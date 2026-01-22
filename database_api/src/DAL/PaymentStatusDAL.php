<?php

namespace App\DAL;

use PDO;

class PaymentStatusDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get payment history amounts for an order
     * Line: 54-59, 75-80, 164-169 (in template)
     */
    public function getPaymentHistory($orderId, $includeRefund = false)
    {
        $payTypes = $includeRefund 
            ? ['deposit', 'balance', 'Refund', 'Balance', 'Unknown', 'deposit_adjustment', 'additional_payment']
            : ['deposit', 'balance', 'Balance', 'Unknown', 'deposit_adjustment', 'additional_payment'];
        
        $placeholders = [];
        foreach ($payTypes as $index => $payType) {
            $placeholders[] = ':pay_type_' . $index;
        }
        $placeholdersStr = implode(',', $placeholders);
        
        $query = "SELECT trams_received_amount 
                  FROM wpk4_backend_travel_payment_history 
                  WHERE order_id = :order_id AND pay_type IN ($placeholdersStr)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        
        foreach ($payTypes as $index => $payType) {
            $stmt->bindValue(':pay_type_' . $index, $payType);
        }
        
        $stmt->execute();
        
        $amounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $amounts[] = (float)$row['trams_received_amount'];
        }
        
        return $amounts;
    }

    /**
     * Get booking total amount and payment status
     * Line: 60-63, 81-85, 171-175 (in template)
     */
    public function getBookingAmount($orderId)
    {
        $query = "SELECT total_amount, payment_status 
                  FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check BPAY receipt status
     * Line: 87-98 (in template)
     */
    public function checkBpayStatus($orderId, $status)
    {
        $query = "SELECT meta_key_data 
                  FROM wpk4_backend_travel_booking_update_history 
                  WHERE order_id = :order_id 
                  AND meta_key = 'G360Events' 
                  AND meta_value = :status 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Update booking payment status
     * Line: 151-152 (in template)
     */
    public function updatePaymentStatus($orderId, $paymentStatus, $paymentModified, $paymentModifiedBy)
    {
        $query = "UPDATE wpk4_backend_travel_bookings 
                  SET payment_status = :payment_status, 
                      payment_modified = :payment_modified, 
                      payment_modified_by = :payment_modified_by 
                  WHERE order_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':payment_status', $paymentStatus);
        $stmt->bindValue(':payment_modified', $paymentModified);
        $stmt->bindValue(':payment_modified_by', $paymentModifiedBy);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
}

