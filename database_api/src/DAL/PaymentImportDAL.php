<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentImportDAL extends BaseDAL
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
     * Update payment process date and payment method
     */
    public function updatePaymentProcessDate($autoId, $processDate, $paymentMethod)
    {
        return $this->update(
            'wpk4_backend_travel_payment_history',
            [
                'process_date' => $processDate,
                'payment_method' => $paymentMethod
            ],
            ['auto_id' => $autoId]
        );
    }
    
    /**
     * Update payment settlement date (cleared_date)
     */
    public function updatePaymentSettlementDate($autoId, $clearedDate)
    {
        return $this->update(
            'wpk4_backend_travel_payment_history',
            ['cleared_date' => $clearedDate],
            ['auto_id' => $autoId]
        );
    }
    
    /**
     * Get booking pax by PNR
     */
    public function getBookingPaxByPnr($pnr)
    {
        $query = "
            SELECT order_id, auto_id, pnr FROM wpk4_backend_travel_booking_pax
            WHERE pnr = :pnr
            LIMIT 1
        ";
        return $this->queryOne($query, ['pnr' => $pnr]);
    }
    
    /**
     * Update booking payment status by order ID
     */
    public function updateBookingPaymentStatus($orderId, $paymentStatus)
    {
        return $this->update(
            'wpk4_backend_travel_bookings',
            ['payment_status' => $paymentStatus],
            ['order_id' => $orderId]
        );
    }
    
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Get booking pax by order ID or PNR
     */
    public function getBookingPaxByOrderIdOrPnr($orderIdOrPnr)
    {
        $query = "
            SELECT order_id FROM wpk4_backend_travel_booking_pax
            WHERE order_id = :order_id OR pnr = :pnr
            LIMIT 1
        ";
        return $this->queryOne($query, [
            'order_id' => $orderIdOrPnr,
            'pnr' => $orderIdOrPnr
        ]);
    }
    
    /**
     * Get payment history by order ID, amount, date, and reference
     */
    public function getPaymentHistoryByDetails($orderId, $amount, $processDate, $referenceNo)
    {
        $query = "
            SELECT order_id FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
                AND trams_received_amount = :amount
                AND process_date = :process_date
                AND reference_no = :reference_no
        ";
        return $this->queryOne($query, [
            'order_id' => $orderId,
            'amount' => $amount,
            'process_date' => $processDate,
            'reference_no' => $referenceNo
        ]);
    }
    
    /**
     * Get total received amount for order
     */
    public function getTotalReceivedAmount($orderId)
    {
        $query = "
            SELECT SUM(trams_received_amount) as total FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
            ORDER BY process_date ASC
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? (float)($result['total'] ?? 0) : 0;
    }
    
    /**
     * Insert payment history
     */
    public function insertPaymentHistory($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history
            (order_id, profile_no, reference_no, trams_received_amount, payment_method, 
             process_date, trams_remarks, source, pay_type, added_by, added_on, 
             payment_change_deadline, gaura_invoice_id)
            VALUES
            (:order_id, :profile_no, :reference_no, :trams_received_amount, :payment_method,
             :process_date, :trams_remarks, :source, :pay_type, :added_by, :added_on,
             :payment_change_deadline, :gaura_invoice_id)
        ";
        
        return $this->insert($query, $data);
    }
    
    /**
     * Get booking order date
     */
    public function getBookingOrderDate($orderId)
    {
        $query = "
            SELECT order_date FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['order_date'] : null;
    }
    
    /**
     * Get payment invoice ID
     */
    public function getPaymentInvoiceId($orderId)
    {
        $query = "
            SELECT invoice_id FROM wpk4_backend_travel_payment_invoice
            WHERE order_id = :order_id
            ORDER BY invoice_id DESC
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['invoice_id'] : null;
    }
    
    /**
     * Insert booking update history
     */
    public function insertBookingUpdateHistory($orderId, $metaKey, $metaValue, $updatedTime, $updatedUser)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_update_history
            (order_id, meta_key, meta_value, updated_time, updated_user)
            VALUES (:order_id, :meta_key, :meta_value, :updated_time, :updated_user)
        ";
        
        return $this->insert($query, [
            'order_id' => $orderId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_time' => $updatedTime,
            'updated_user' => $updatedUser
        ]);
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
     * Get customer email from booking pax
     */
    public function getCustomerEmail($orderId)
    {
        $query = "
            SELECT email_pax FROM wpk4_backend_travel_booking_pax
            WHERE order_id = :order_id
            ORDER BY auto_id ASC
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['email_pax'] : null;
    }
    
    /**
     * Insert order email history
     */
    public function insertOrderEmailHistory($emailType, $orderId, $emailAddress, $initiatedDate, $initiatedBy, $emailSubject)
    {
        $query = "
            INSERT INTO wpk4_backend_order_email_history
            (email_type, order_id, email_address, initiated_date, initiated_by, email_subject)
            VALUES (:email_type, :order_id, :email_address, :initiated_date, :initiated_by, :email_subject)
        ";
        
        return $this->insert($query, [
            'email_type' => $emailType,
            'order_id' => $orderId,
            'email_address' => $emailAddress,
            'initiated_date' => $initiatedDate,
            'initiated_by' => $initiatedBy,
            'email_subject' => $emailSubject
        ]);
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
            SELECT auto_id, order_id, source, process_date, payment_method, cleared_date 
            FROM wpk4_backend_travel_payment_history
            WHERE auto_id IN (" . implode(',', $placeholders) . ")
        ";
        
        return $this->query($query, $params);
    }
    
    /**
     * Update payment order ID and source
     */
    public function updatePaymentOrderIdAndSource($autoId, $orderId, $source)
    {
        return $this->update(
            'wpk4_backend_travel_payment_history',
            [
                'order_id' => $orderId,
                'source' => $source
            ],
            ['auto_id' => $autoId]
        );
    }
}

