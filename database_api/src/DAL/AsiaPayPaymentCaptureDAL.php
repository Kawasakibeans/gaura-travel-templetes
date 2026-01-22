<?php
/**
 * AsiaPay Payment Capture Data Access Layer
 * Handles database operations for AsiaPay payment capture
 */

namespace App\DAL;

use Exception;
use PDOException;

class AsiaPayPaymentCaptureDAL extends BaseDAL
{
    /**
     * Check if transaction exists
     */
    public function transactionExists($ref)
    {
        try {
            $query = "SELECT ref FROM wpk4_backend_travel_booking_asiapay_transactions WHERE ref = :ref LIMIT 1";
            $result = $this->queryOne($query, ['ref' => $ref]);
            return $result !== null;
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::transactionExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert transaction record
     */
    public function insertTransaction($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_asiapay_transactions 
                (orderStatus, ref, payRef, source, amt, cur, prc, src, ord, holder, authId, alertCode, remark, eci, payerAuth, sourceIp, ipCountry, payMethod, panFirst4, panLast4, cardIssuingCountry, channelType, txTime, successcode, MerchantId, errMsg, expected_orderid, current_orderid) 
                VALUES 
                (:orderStatus, :ref, :payRef, :source, :amt, :cur, :prc, :src, :ord, :holder, :authId, :alertCode, :remark, :eci, :payerAuth, :sourceIp, :ipCountry, :payMethod, :panFirst4, :panLast4, :cardIssuingCountry, :channelType, :txTime, :successcode, :MerchantId, :errMsg, :expected_orderid, :current_orderid)
            ";
            
            return $this->execute($query, [
                'orderStatus' => $data['orderStatus'] ?? '',
                'ref' => $data['ref'] ?? '',
                'payRef' => $data['payRef'] ?? '',
                'source' => $data['source'] ?? 'gds',
                'amt' => $data['amt'] ?? '0.00',
                'cur' => $data['cur'] ?? '',
                'prc' => $data['prc'] ?? '',
                'src' => $data['src'] ?? '',
                'ord' => $data['ord'] ?? '',
                'holder' => $data['holder'] ?? '',
                'authId' => $data['authId'] ?? '',
                'alertCode' => $data['alertCode'] ?? '',
                'remark' => $data['remark'] ?? '',
                'eci' => $data['eci'] ?? '',
                'payerAuth' => $data['payerAuth'] ?? '',
                'sourceIp' => $data['sourceIp'] ?? '',
                'ipCountry' => $data['ipCountry'] ?? '',
                'payMethod' => $data['payMethod'] ?? '',
                'panFirst4' => $data['panFirst4'] ?? '',
                'panLast4' => $data['panLast4'] ?? '',
                'cardIssuingCountry' => $data['cardIssuingCountry'] ?? '',
                'channelType' => $data['channelType'] ?? '',
                'txTime' => $data['txTime'] ?? '',
                'successcode' => $data['successcode'] ?? '',
                'MerchantId' => $data['MerchantId'] ?? '',
                'errMsg' => $data['errMsg'] ?? '',
                'expected_orderid' => $data['expected_orderid'] ?? '',
                'current_orderid' => $data['current_orderid'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::insertTransaction error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get order_id by PNR
     */
    public function getOrderIdByPnr($pnr)
    {
        try {
            $query = "SELECT order_id FROM wpk4_backend_travel_booking_pax WHERE pnr = :pnr LIMIT 1";
            $result = $this->queryOne($query, ['pnr' => $pnr]);
            return $result ? $result['order_id'] : null;
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getOrderIdByPnr error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if payment history exists
     */
    public function paymentHistoryExists($referenceNo, $orderId, $processDate, $amount, $paymentMethod = '8')
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history 
                WHERE (reference_no = :reference_no OR order_id = :order_id) 
                AND (DATE(process_date) = DATE(:process_date1) OR DATE(process_date) = DATE(:process_date2)) 
                AND trams_received_amount = :amount 
                AND payment_method = :payment_method
                LIMIT 1
            ";
            $result = $this->queryOne($query, [
                'reference_no' => $referenceNo,
                'order_id' => $referenceNo,
                'process_date1' => $processDate,
                'process_date2' => $processDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod
            ]);
            return $result !== null;
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::paymentHistoryExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert payment history
     */
    public function insertPaymentHistory($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_payment_history 
                (order_id, source, payment_method, process_date, added_by, added_on, pay_type, reference_no, trams_received_amount, payment_change_deadline, gaura_invoice_id) 
                VALUES 
                (:order_id, :source, :payment_method, :process_date, :added_by, :added_on, :pay_type, :reference_no, :trams_received_amount, :payment_change_deadline, :gaura_invoice_id)
            ";
            
            return $this->execute($query, [
                'order_id' => $data['order_id'] ?? '',
                'source' => $data['source'] ?? 'gds',
                'payment_method' => $data['payment_method'] ?? '8',
                'process_date' => $data['process_date'] ?? date('Y-m-d H:i:s'),
                'added_by' => $data['added_by'] ?? 'asiapay_cronjob_updator',
                'added_on' => $data['added_on'] ?? date('Y-m-d H:i:s'),
                'pay_type' => $data['pay_type'] ?? 'deposit',
                'reference_no' => $data['reference_no'] ?? '',
                'trams_received_amount' => $data['trams_received_amount'] ?? '0.00',
                'payment_change_deadline' => $data['payment_change_deadline'] ?? null,
                'gaura_invoice_id' => $data['gaura_invoice_id'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::insertPaymentHistory error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking order date
     */
    public function getBookingOrderDate($orderId)
    {
        try {
            $query = "SELECT order_date FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1";
            $result = $this->queryOne($query, ['order_id' => $orderId]);
            return $result ? $result['order_date'] : date('Y-m-d H:i:s');
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getBookingOrderDate error: " . $e->getMessage());
            return date('Y-m-d H:i:s');
        }
    }
    
    /**
     * Get latest invoice ID for order
     */
    public function getLatestInvoiceId($orderId)
    {
        try {
            $query = "
                SELECT invoice_id FROM wpk4_backend_travel_payment_invoice 
                WHERE order_id = :order_id 
                ORDER BY invoice_id DESC 
                LIMIT 1
            ";
            $result = $this->queryOne($query, ['order_id' => $orderId]);
            return $result ? $result['invoice_id'] : '';
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getLatestInvoiceId error: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get payment record for journal entry
     */
    public function getPaymentForJournal($orderId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id 
                AND trams_received_amount > '0' 
                AND payment_method = '8'
                LIMIT 1
            ";
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getPaymentForJournal error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Insert journal entry
     */
    public function insertJournalEntry($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_payment_journal_entry 
                (journal_date, period, source_type, status, notes, order_id, invoice_id, currency_code, created_by, created_at) 
                VALUES 
                (:journal_date, :period, 'deposit_payment', 'posted', :notes, :order_id, :invoice_id, 'AUD', 'Ypsilon Cron', :created_at)
            ";
            
            $this->execute($query, [
                'journal_date' => $data['journal_date'] ?? date('Y-m-d H:i:s'),
                'period' => $data['period'] ?? date('M-Y'),
                'notes' => $data['notes'] ?? '',
                'order_id' => $data['order_id'] ?? '',
                'invoice_id' => $data['invoice_id'] ?? '',
                'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::insertJournalEntry error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert journal line (debit)
     */
    public function insertJournalLineDebit($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_payment_journal_line 
                (journal_id, line_no, account_id, description, debit, credit, currency_code, order_id, created_at, created_by) 
                VALUES 
                (:journal_id, :line_no, '1105', 'Asiapay Deposit', :debit, '0', 'AUD', :order_id, :created_at, 'Ypsilon Cron')
            ";
            
            return $this->execute($query, [
                'journal_id' => $data['journal_id'],
                'line_no' => $data['line_no'] ?? 1,
                'debit' => $data['debit'] ?? '0.00',
                'order_id' => $data['order_id'] ?? '',
                'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::insertJournalLineDebit error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert journal line (credit)
     */
    public function insertJournalLineCredit($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_payment_journal_line 
                (journal_id, line_no, account_id, description, debit, credit, currency_code, order_id, pax_id, created_at, created_by) 
                VALUES 
                (:journal_id, :line_no, '1200', :description, '0', :credit, 'AUD', :order_id, :pax_id, :created_at, 'Ypsilon Cron')
            ";
            
            return $this->execute($query, [
                'journal_id' => $data['journal_id'],
                'line_no' => $data['line_no'],
                'description' => $data['description'] ?? '',
                'credit' => $data['credit'] ?? '0.00',
                'order_id' => $data['order_id'] ?? '',
                'pax_id' => $data['pax_id'] ?? null,
                'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::insertJournalLineCredit error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking with total pax
     */
    public function getBookingWithTotalPax($orderId)
    {
        try {
            $query = "
                SELECT order_id, total_pax 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = :order_id 
                ORDER BY auto_id 
                LIMIT 1
            ";
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getBookingWithTotalPax error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get passengers for order
     */
    public function getPassengersForOrder($orderId)
    {
        try {
            $query = "
                SELECT auto_id, lname, fname 
                FROM wpk4_backend_travel_booking_pax 
                WHERE order_id = :order_id
            ";
            return $this->query($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("AsiaPayPaymentCaptureDAL::getPassengersForOrder error: " . $e->getMessage());
            return [];
        }
    }
}

