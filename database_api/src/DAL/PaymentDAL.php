<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentDAL extends BaseDAL
{
    /**
     * Check order remark status
     */
    public function checkOrderRemark($orderId)
    {
        $query = "
            SELECT is_checked FROM wpk4_backend_travel_booking_payment_recheck
            WHERE order_id = :order_id
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Get booking by order ID (FIT/G360)
     */
    public function getFITBookingByOrderId($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_bookings_g360_booking
            WHERE order_id = :order_id
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Update FIT booking payment status
     */
    public function updateFITBookingPaymentStatus($orderId, $status, $modifiedDate = null)
    {
        $setParts = [];
        $params = ['order_id' => $orderId];
        
        $setParts[] = 'payment_status = :payment_status';
        $params['payment_status'] = $status;
        
        if ($modifiedDate) {
            $setParts[] = 'payment_modified = :payment_modified';
            $params['payment_modified'] = $modifiedDate;
        }
        
        $query = "UPDATE wpk4_backend_travel_bookings_g360_booking SET " . implode(', ', $setParts) . " WHERE order_id = :order_id";
        return $this->execute($query, $params);
    }
    
    /**
     * Update FIT booking pax payment status
     */
    public function updateFITBookingPaxPaymentStatus($orderId, $status)
    {
        $query = "UPDATE wpk4_backend_travel_booking_pax_g360_booking SET payment_status = :payment_status WHERE order_id = :order_id";
        return $this->execute($query, [
            'payment_status' => $status,
            'order_id' => $orderId
        ]);
    }
    
    /**
     * Get FIT booking total amount
     */
    public function getFITBookingTotalAmount($orderId)
    {
        $query = "
            SELECT total_amount FROM wpk4_backend_travel_bookings_g360_booking
            WHERE order_id = :order_id
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['total_amount'] : null;
    }
    
    /**
     * Insert FIT payment history
     */
    public function insertFITPaymentHistory($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history_g360_booking
            (order_id, source, total_amount, trams_received_amount, reference_no, payment_method, process_date, payment_change_deadline)
            VALUES (:order_id, :source, :total_amount, :trams_received_amount, :reference_no, :payment_method, :process_date, :payment_change_deadline)
        ";
        
        $params = [
            'order_id' => $data['order_id'],
            'source' => $data['source'] ?? 'fit',
            'total_amount' => $data['total_amount'],
            'trams_received_amount' => $data['trams_received_amount'],
            'reference_no' => $data['reference_no'],
            'payment_method' => $data['payment_method'] ?? '8',
            'process_date' => $data['process_date'] ?? date('Y-m-d H:i:s'),
            'payment_change_deadline' => $data['payment_change_deadline'] ?? date('Y-m-d H:i:s', strtotime('+4 days'))
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
    
    /**
     * Get WPT booking by order ID
     */
    public function getWPTBookingByOrderId($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Insert AsiaPay callback record
     */
    public function insertAsiaPayCallback($paymentRef, $amount, $status, $addedBy = null)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_asiapay_callback
            (payment_ref, amount, status, added_by)
            VALUES (:payment_ref, :amount, :status, :added_by)
        ";
        
        $params = [
            'payment_ref' => $paymentRef,
            'amount' => $amount,
            'status' => $status,
            'added_by' => $addedBy ?? 'asiapay_callback'
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
    
    /**
     * Get payment history by order ID and reference
     */
    public function getPaymentHistoryByOrderAndRef($orderId, $referenceNo, $paymentMethod = '8', $payType = 'deposit', $amounts = [])
    {
        $query = "
            SELECT auto_id, trams_received_amount FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
            AND payment_method = :payment_method
            AND reference_no = :reference_no
            AND pay_type = :pay_type
        ";
        
        $params = [
            'order_id' => $orderId,
            'payment_method' => $paymentMethod,
            'reference_no' => $referenceNo,
            'pay_type' => $payType
        ];
        
        if (!empty($amounts)) {
            $placeholders = [];
            foreach ($amounts as $idx => $amount) {
                $key = 'amount' . $idx;
                $placeholders[] = ':' . $key;
                $params[$key] = $amount;
            }
            $query .= " AND trams_received_amount IN (" . implode(',', $placeholders) . ")";
        }
        
        return $this->queryOne($query, $params);
    }
    
    /**
     * Update payment history
     */
    public function updatePaymentHistory($autoId, $data)
    {
        $setParts = [];
        $params = ['auto_id' => $autoId];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $query = "UPDATE wpk4_backend_travel_payment_history SET " . implode(', ', $setParts) . " WHERE auto_id = :auto_id";
        return $this->execute($query, $params);
    }
    
    /**
     * Insert payment history
     */
    public function insertPaymentHistory($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history
            (order_id, source, profile_no, total_amount, trams_received_amount, reference_no, balance_amount, payment_method, pay_type, process_date, payment_change_deadline, gaura_invoice_id, added_on, modified_by, added_by)
            VALUES (:order_id, :source, :profile_no, :total_amount, :trams_received_amount, :reference_no, :balance_amount, :payment_method, :pay_type, :process_date, :payment_change_deadline, :gaura_invoice_id, :added_on, :modified_by, :added_by)
        ";
        
        $params = [
            'order_id' => $data['order_id'],
            'source' => $data['source'] ?? 'WPT',
            'profile_no' => $data['profile_no'] ?? '',
            'total_amount' => $data['total_amount'],
            'trams_received_amount' => $data['trams_received_amount'],
            'reference_no' => $data['reference_no'] ?? '',
            'balance_amount' => $data['balance_amount'] ?? 0,
            'payment_method' => $data['payment_method'] ?? '8',
            'pay_type' => $data['pay_type'] ?? 'deposit',
            'process_date' => $data['process_date'] ?? date('Y-m-d H:i:s'),
            'payment_change_deadline' => $data['payment_change_deadline'] ?? date('Y-m-d H:i:s', strtotime('+96 hours')),
            'gaura_invoice_id' => $data['gaura_invoice_id'] ?? '',
            'added_on' => $data['added_on'] ?? date('Y-m-d H:i:s'),
            'modified_by' => $data['modified_by'] ?? '',
            'added_by' => $data['added_by'] ?? 'wptcronpayment'
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
    
    /**
     * Update WPT booking payment status
     */
    public function updateWPTBookingPaymentStatus($orderId, $data)
    {
        $setParts = [];
        $params = ['order_id' => $orderId];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $query = "UPDATE wpk4_backend_travel_bookings SET " . implode(', ', $setParts) . " WHERE order_id = :order_id";
        return $this->execute($query, $params);
    }
    
    /**
     * Get orders for payment recheck
     */
    public function getOrdersForPaymentRecheck($orderId = null)
    {
        $query = "
            SELECT order_id FROM wpk4_backend_travel_booking_payment_recheck
            WHERE LENGTH(order_id) = 6 AND is_checked = '0'
        ";
        
        $params = [];
        if ($orderId) {
            $query .= " AND order_id = :order_id";
            $params['order_id'] = $orderId;
        }
        
        return $this->query($query, $params);
    }
    
    /**
     * Update payment recheck status
     */
    public function updatePaymentRecheckStatus($orderId, $isChecked = '1')
    {
        $query = "UPDATE wpk4_backend_travel_booking_payment_recheck SET is_checked = :is_checked WHERE order_id = :order_id";
        return $this->execute($query, [
            'is_checked' => $isChecked,
            'order_id' => $orderId
        ]);
    }
    
    /**
     * Insert postmeta record
     */
    public function insertPostmeta($postId, $metaKey, $metaValue)
    {
        $query = "
            INSERT INTO wpk4_postmeta (post_id, meta_key, meta_value)
            VALUES (:post_id, :meta_key, :meta_value)
        ";
        
        $params = [
            'post_id' => $postId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
    
    /**
     * Get postmeta value
     */
    public function getPostmeta($postId, $metaKey)
    {
        $query = "
            SELECT meta_value FROM wpk4_postmeta
            WHERE post_id = :post_id AND meta_key = :meta_key
        ";
        
        $result = $this->queryOne($query, [
            'post_id' => $postId,
            'meta_key' => $metaKey
        ]);
        
        return $result ? $result['meta_value'] : null;
    }
    
    /**
     * Check if booking exists
     */
    public function bookingExists($orderId)
    {
        $query = "
            SELECT auto_id FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
        ";
        
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result !== null;
    }
    
    /**
     * Check if payment history exists by order, reference, and amount
     */
    public function paymentHistoryExists($orderId, $referenceNo, $amount)
    {
        $query = "
            SELECT auto_id FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
            AND reference_no = :reference_no
            AND trams_received_amount = :amount
        ";
        
        $result = $this->queryOne($query, [
            'order_id' => $orderId,
            'reference_no' => $referenceNo,
            'amount' => $amount
        ]);
        
        return $result !== null;
    }
    
    /**
     * Insert amadeus name update payment status log
     */
    public function insertAmadeusNameUpdatePaymentStatusLog($orderId, $orderType, $updatedBy, $updatedOn, $isProcessedAmadeus, $amadeusProcessedOn, $pageTitle)
    {
        $query = "
            INSERT INTO wpk4_amadeus_name_update_payment_status_log
            (order_id, order_type, updated_by, updated_on, is_processed_amadeus, amadeus_processed_on, page_title)
            VALUES (:order_id, :order_type, :updated_by, :updated_on, :is_processed_amadeus, :amadeus_processed_on, :page_title)
        ";
        
        $params = [
            'order_id' => $orderId,
            'order_type' => $orderType,
            'updated_by' => $updatedBy,
            'updated_on' => $updatedOn,
            'is_processed_amadeus' => $isProcessedAmadeus,
            'amadeus_processed_on' => $amadeusProcessedOn,
            'page_title' => $pageTitle
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
}

