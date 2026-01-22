<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AzupayManagementDAL extends BaseDAL
{
    /**
     * Get custom payment by payment client ID
     */
    public function getCustomPaymentByClientId($clientTransactionId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_booking_custom_payments
            WHERE payment_client_id = :client_transaction_id
        ";
        return $this->queryOne($query, ['client_transaction_id' => $clientTransactionId]);
    }
    
    /**
     * Get custom payment by order ID and client ID
     */
    public function getCustomPaymentByOrderAndClientId($orderId, $clientTransactionId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_booking_custom_payments
            WHERE order_id = :order_id AND payment_client_id = :client_transaction_id
        ";
        return $this->queryOne($query, [
            'order_id' => $orderId,
            'client_transaction_id' => $clientTransactionId
        ]);
    }
    
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId($orderId)
    {
        $query = "
            SELECT payment_status, total_amount, order_type, source, previous_order_id, order_date, balance
            FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
            ORDER BY auto_id
            LIMIT 1
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Get total paid for payment request ID
     */
    public function getTotalPaidForRequestId($paymentRequestId)
    {
        $query = "
            SELECT SUM(trams_received_amount) as total_paid
            FROM wpk4_backend_travel_payment_history
            WHERE payment_request_id = :payment_request_id
            AND payment_method = '7'
            AND payment_request_id IS NOT NULL
        ";
        $result = $this->queryOne($query, ['payment_request_id' => $paymentRequestId]);
        return $result ? (float)($result['total_paid'] ?? 0) : 0;
    }
    
    /**
     * Get total paid for order ID
     */
    public function getTotalPaidForOrderId($orderId)
    {
        $query = "
            SELECT trams_received_amount, payment_request_id
            FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
            AND (pay_type = 'deposit' OR pay_type = 'balance' 
                 OR pay_type = 'Balance' OR pay_type = 'deposit_adjustment' 
                 OR pay_type = 'additional_payment')
        ";
        return $this->query($query, ['order_id' => $orderId]);
    }
    
    /**
     * Insert payment history backup
     */
    public function insertPaymentHistoryBackup($orderId, $source, $totalAmount, $receivedAmount, $balanceAmount, $paymentMethod, $processDate, $payType, $referenceNo = null)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history_backup
            (order_id, source, total_amount, trams_received_amount, balance_amount, payment_method, process_date, pay_type, reference_no)
            VALUES (:order_id, :source, :total_amount, :trams_received_amount, :balance_amount, :payment_method, :process_date, :pay_type, :reference_no)
        ";
        
        return $this->execute($query, [
            'order_id' => $orderId,
            'source' => $source,
            'total_amount' => $totalAmount,
            'trams_received_amount' => $receivedAmount,
            'balance_amount' => $balanceAmount,
            'payment_method' => $paymentMethod,
            'process_date' => $processDate,
            'pay_type' => $payType,
            'reference_no' => $referenceNo
        ]);
    }
    
    /**
     * Insert Azupay status checkup
     */
    public function insertAzupayStatusCheckup($orderId, $transactionId, $status, $addedBy)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_azupay_status_checkup
            (order_id, transaction_id, status, added_by)
            VALUES (:order_id, :transaction_id, :status, :added_by)
        ";
        
        return $this->execute($query, [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'status' => $status,
            'added_by' => $addedBy
        ]);
    }
    
    /**
     * Insert booking update history
     */
    public function insertBookingUpdateHistory($orderId, $metaKey, $metaValue, $updatedTime, $updatedUser, $mergingId = null, $paxAutoId = null)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_update_history
            (order_id, merging_id, pax_auto_id, meta_key, meta_value, updated_time, updated_user)
            VALUES (:order_id, :merging_id, :pax_auto_id, :meta_key, :meta_value, :updated_time, :updated_user)
        ";
        
        return $this->execute($query, [
            'order_id' => $orderId,
            'merging_id' => $mergingId,
            'pax_auto_id' => $paxAutoId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_time' => $updatedTime,
            'updated_user' => $updatedUser
        ]);
    }
    
    /**
     * Check if payment history record exists
     */
    public function checkPaymentHistoryExists($orderId, $receivedAmount, $processDate, $paymentMethod)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_payment_history
            WHERE order_id = :order_id
            AND trams_received_amount = :trams_received_amount
            AND process_date = :process_date
            AND payment_method = :payment_method
        ";
        $result = $this->queryOne($query, [
            'order_id' => $orderId,
            'trams_received_amount' => $receivedAmount,
            'process_date' => $processDate,
            'payment_method' => $paymentMethod
        ]);
        return $result !== false;
    }
    
    /**
     * Delete existing payment history
     */
    public function deletePaymentHistory($orderId, $paymentMethod, $payType = null, $referenceNo = null)
    {
        $conditions = [
            'order_id' => $orderId,
            'payment_method' => $paymentMethod
        ];
        
        if ($payType !== null) {
            $conditions['pay_type'] = $payType;
        }
        
        if ($referenceNo !== null) {
            $conditions['reference_no'] = $referenceNo;
        }
        
        $whereClause = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $whereClause[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $query = "
            DELETE FROM wpk4_backend_travel_payment_history
            WHERE " . implode(' AND ', $whereClause)
        ;
        
        return $this->execute($query, $params);
    }
    
    /**
     * Get date change request by order ID and case ID
     */
    public function getDateChangeRequest($orderId, $caseId)
    {
        $query = "
            SELECT original_tripcode, new_tripcode, new_travel_date
            FROM wpk4_backend_travel_datechange_request
            WHERE order_id = :order_id
            AND portal_case_id = :case_id
        ";
        return $this->queryOne($query, [
            'order_id' => $orderId,
            'case_id' => $caseId
        ]);
    }
    
    /**
     * Get invoice by order ID
     */
    public function getInvoiceByOrderId($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_payment_invoice
            WHERE order_id = :order_id
            ORDER BY invoice_id DESC
            LIMIT 1
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Insert payment history
     */
    public function insertPaymentHistory($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history
            (order_id, source, total_amount, trams_received_amount, balance_amount, payment_method, process_date, pay_type, reference_no, added_on, added_by, payment_request_id, payment_change_deadline, gaura_invoice_id)
            VALUES (:order_id, :source, :total_amount, :trams_received_amount, :balance_amount, :payment_method, :process_date, :pay_type, :reference_no, :added_on, :added_by, :payment_request_id, :payment_change_deadline, :gaura_invoice_id)
        ";
        
        $params = [
            'order_id' => $data['order_id'],
            'source' => $data['source'] ?? '',
            'total_amount' => $data['total_amount'],
            'trams_received_amount' => $data['trams_received_amount'],
            'balance_amount' => $data['balance_amount'],
            'payment_method' => $data['payment_method'] ?? '7',
            'process_date' => $data['process_date'],
            'pay_type' => $data['pay_type'],
            'reference_no' => $data['reference_no'] ?? null,
            'added_on' => $data['added_on'] ?? date('Y-m-d H:i:s'),
            'added_by' => $data['added_by'] ?? 'azupay_automation',
            'payment_request_id' => $data['payment_request_id'] ?? null,
            'payment_change_deadline' => $data['payment_change_deadline'] ?? null,
            'gaura_invoice_id' => $data['gaura_invoice_id'] ?? null
        ];
        
        $result = $this->execute($query, $params);
        if ($result) {
            return $this->lastInsertId();
        }
        return false;
    }
    
    /**
     * Insert payment history to removed records
     */
    public function insertPaymentHistoryRemoved($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history_removed_records
            (order_id, source, total_amount, trams_received_amount, balance_amount, payment_method, process_date, pay_type, reference_no, added_on, added_by, payment_request_id)
            VALUES (:order_id, :source, :total_amount, :trams_received_amount, :balance_amount, :payment_method, :process_date, :pay_type, :reference_no, :added_on, :added_by, :payment_request_id)
        ";
        
        $params = [
            'order_id' => $data['order_id'],
            'source' => $data['source'] ?? '',
            'total_amount' => $data['total_amount'],
            'trams_received_amount' => $data['trams_received_amount'],
            'balance_amount' => $data['balance_amount'],
            'payment_method' => $data['payment_method'] ?? '7',
            'process_date' => $data['process_date'],
            'pay_type' => $data['pay_type'],
            'reference_no' => $data['reference_no'] ?? null,
            'added_on' => $data['added_on'] ?? date('Y-m-d H:i:s'),
            'added_by' => $data['added_by'] ?? 'azupay_automation',
            'payment_request_id' => $data['payment_request_id'] ?? null
        ];
        
        return $this->execute($query, $params);
    }
    
    /**
     * Update booking payment status
     */
    public function updateBookingPaymentStatus($orderId, $status, $balance = null, $modifiedTime = null, $modifiedBy = null)
    {
        $data = [
            'payment_status' => $status,
            'payment_modified' => $modifiedTime ?? date('Y-m-d H:i:s'),
            'payment_modified_by' => $modifiedBy ?? 'azupay_automation'
        ];
        
        if ($balance !== null) {
            $data['balance'] = $balance;
        }
        
        return $this->update(
            'wpk4_backend_travel_bookings',
            $data,
            ['order_id' => $orderId]
        );
    }
    
    /**
     * Update custom payment status
     */
    public function updateCustomPaymentStatus($clientTransactionId, $status, $paidOn = null, $amountPaid = null, $cronCheckup = null)
    {
        $data = [
            'status' => $status
        ];
        
        if ($paidOn !== null) {
            $data['paid_on'] = $paidOn;
        }
        
        if ($amountPaid !== null) {
            $data['amount_paid'] = $amountPaid;
        }
        
        if ($cronCheckup !== null) {
            $data['cron_checkup'] = $cronCheckup;
        }
        
        return $this->update(
            'wpk4_backend_travel_booking_custom_payments',
            $data,
            ['payment_client_id' => $clientTransactionId]
        );
    }
    
    /**
     * Update booking travel date and trip code
     */
    public function updateBookingTravelDate($orderId, $travelDate, $tripCode, $modifiedBy = null, $modifiedTime = null)
    {
        return $this->update(
            'wpk4_backend_travel_bookings',
            [
                'travel_date' => $travelDate,
                'trip_code' => $tripCode,
                'modified_by' => $modifiedBy ?? 'azupay_dc_automation',
                'late_modified' => $modifiedTime ?? date('Y-m-d H:i:s')
            ],
            ['order_id' => $orderId]
        );
    }
    
    /**
     * Update invoice status
     */
    public function updateInvoiceStatus($orderId, $status, $invoiceStatus, $modifiedTime = null, $modifiedBy = null)
    {
        return $this->update(
            'wpk4_backend_travel_payment_invoice',
            [
                'status' => $status,
                'invoice_status' => $invoiceStatus,
                'modified_on' => $modifiedTime ?? date('Y-m-d H:i:s'),
                'modified_by' => $modifiedBy ?? 'azupay_custom_automation'
            ],
            ['order_id' => $orderId]
        );
    }
    
    /**
     * Get customer email by order ID
     */
    public function getCustomerEmailByOrderId($orderId)
    {
        $query = "
            SELECT email_pax FROM wpk4_backend_travel_booking_pax
            WHERE order_id = :order_id
            ORDER BY auto_id
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? ($result['email_pax'] ?? null) : null;
    }
    
    /**
     * Check email history exists
     */
    public function checkEmailHistoryExists($orderId, $emailType, $date = null, $emailSubject = null)
    {
        $query = "
            SELECT * FROM wpk4_backend_order_email_history
            WHERE order_id = :order_id
            AND email_type = :email_type
        ";
        
        $params = [
            'order_id' => $orderId,
            'email_type' => $emailType
        ];
        
        if ($date !== null) {
            $query .= " AND DATE(initiated_date) = :date";
            $params['date'] = $date;
        }
        
        if ($emailSubject !== null) {
            $query .= " AND email_subject LIKE :email_subject";
            $params['email_subject'] = '%' . $emailSubject . '%';
        }
        
        $result = $this->queryOne($query, $params);
        return $result !== false;
    }
    
    /**
     * Insert email history
     */
    public function insertEmailHistory($emailType, $orderId, $emailAddress, $initiatedDate, $initiatedBy, $emailSubject)
    {
        $query = "
            INSERT INTO wpk4_backend_order_email_history
            (email_type, order_id, email_address, initiated_date, initiated_by, email_subject)
            VALUES (:email_type, :order_id, :email_address, :initiated_date, :initiated_by, :email_subject)
        ";
        
        return $this->execute($query, [
            'email_type' => $emailType,
            'order_id' => $orderId,
            'email_address' => $emailAddress,
            'initiated_date' => $initiatedDate,
            'initiated_by' => $initiatedBy,
            'email_subject' => $emailSubject
        ]);
    }
}

