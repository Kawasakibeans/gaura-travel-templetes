<?php
/**
 * TRAMS Payment Data Access Layer
 * Handles database operations for updating payments from TRAMS
 */

namespace App\DAL;

use Exception;
use PDOException;

class TramsPaymentDAL extends BaseDAL
{
    /**
     * Find unprocessed TRAMS payments
     */
    public function findUnprocessedPayments()
    {
        try {
            $query = "
                SELECT DISTINCT 
                    invoiceref, 
                    paymentno, 
                    paymentdate, 
                    profileno, 
                    amount, 
                    remarks, 
                    paymethod_linkno 
                FROM wpk4_backend_trams_info a 
                WHERE a.invoiceref != '' 
                  AND CAST(a.paymentno AS CHAR(100)) NOT IN (
                      SELECT DISTINCT b.reference_no 
                      FROM wpk4_backend_travel_payment_history b
                  ) 
                  AND a.paymethod_linkno <> '5' 
                  AND a.invoiceref <> ''
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::findUnprocessedPayments error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment history for order
     */
    public function getPaymentHistory($orderId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id
            ";
            
            return $this->query($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::getPaymentHistory error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get deposit payment
     */
    public function getDepositPayment($orderId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id AND pay_type = 'deposit'
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::getDepositPayment error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get first payment history entry
     */
    public function getFirstPaymentHistory($orderId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::getFirstPaymentHistory error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
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
                (order_id, source, profile_no, total_amount, trams_received_amount, reference_no, balance_amount, payment_method, process_date, trams_remarks)
                VALUES (:order_id, 'WPT', :profile_no, :total_amount, :trams_received_amount, :reference_no, :balance_amount, :payment_method, :process_date, :trams_remarks)
            ";
            
            $this->execute($query, $data);
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::insertPaymentHistory error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update booking profile_id
     */
    public function updateBookingProfileId($orderId, $profileNo)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_bookings 
                SET profile_id = :profile_no 
                WHERE order_id = :order_id
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'profile_no' => $profileNo
            ]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::updateBookingProfileId error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert booking update history
     */
    public function insertBookingUpdateHistory($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_update_history 
                (order_id, co_order_id, merging_id, meta_key, meta_value, meta_key_data, updated_time, updated_user)
                VALUES (:order_id, '', :merging_id, :meta_key, :meta_value, :meta_key_data, :updated_time, :updated_user)
            ";
            
            $this->execute($query, $data);
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::insertBookingUpdateHistory error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get BPay payment
     */
    public function getBPayPayment($orderId, $processDate)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_trams_info 
                WHERE invoiceref = :order_id 
                  AND paymethod_linkno = 5 
                  AND paymentdate = :process_date
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'order_id' => $orderId,
                'process_date' => $processDate
            ]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::getBPayPayment error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update BPay payment profile_no
     */
    public function updateBPayProfileNo($orderId, $profileNo, $bpayAmount)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_payment_history 
                SET profile_no = :profile_no 
                WHERE order_id = :order_id 
                  AND payment_method = '5' 
                  AND trams_received_amount = :bpay_amount
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'profile_no' => $profileNo,
                'bpay_amount' => $bpayAmount
            ]);
        } catch (PDOException $e) {
            error_log("TramsPaymentDAL::updateBPayProfileNo error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

