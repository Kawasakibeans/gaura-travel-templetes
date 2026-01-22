<?php
/**
 * AsiaPay Callback Data Access Layer
 * Handles database operations for AsiaPay payment callbacks
 */

namespace App\DAL;

use Exception;
use PDOException;

class AsiaPayCallbackDAL extends BaseDAL
{
    /**
     * Insert failed payment callback
     */
    public function insertFailedCallback($paymentRef)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_asiapay_callback 
                    (`payment_ref`, `amount`, `status`) 
                VALUES 
                    (:payment_ref, '', 'failed')
            ";
            
            return $this->execute($query, [
                'payment_ref' => $paymentRef
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::insertFailedCallback error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert successful payment callback
     */
    public function insertSuccessCallback($paymentRef, $amount)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_asiapay_callback 
                    (`payment_ref`, `amount`, `status`) 
                VALUES 
                    (:payment_ref, :amount, 'success')
            ";
            
            return $this->execute($query, [
                'payment_ref' => $paymentRef,
                'amount' => $amount
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::insertSuccessCallback error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get order total from postmeta
     */
    public function getOrderTotalFromPostmeta($orderId)
    {
        try {
            $query = "
                SELECT meta_value 
                FROM wpk4_postmeta 
                WHERE post_id = :order_id AND meta_key = 'order_totals'
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, ['order_id' => $orderId]);
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::getOrderTotalFromPostmeta error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get booking amount and total pax
     */
    public function getBookingAmount($orderId)
    {
        try {
            $query = "
                SELECT total_amount, total_pax 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = :order_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::getBookingAmount error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get order date
     */
    public function getOrderDate($orderId)
    {
        try {
            $query = "
                SELECT order_date 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = :order_id
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, ['order_id' => $orderId]);
            return $result ? $result['order_date'] : null;
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::getOrderDate error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get latest invoice ID for order
     */
    public function getLatestInvoiceId($orderId)
    {
        try {
            $query = "
                SELECT invoice_id 
                FROM wpk4_backend_travel_payment_invoice 
                WHERE order_id = :order_id 
                ORDER BY invoice_id DESC 
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, ['order_id' => $orderId]);
            return $result ? $result['invoice_id'] : '';
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::getLatestInvoiceId error: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Check if payment already exists
     */
    public function paymentExists($orderId, $amount, $paymentMethod = '8', $payType = 'deposit')
    {
        try {
            $query = "
                SELECT auto_id 
                FROM wpk4_backend_travel_payment_history 
                WHERE order_id = :order_id 
                  AND trams_received_amount = :amount 
                  AND payment_method = :payment_method 
                  AND pay_type = :pay_type
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'pay_type' => $payType
            ]);
            
            return $result !== null;
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::paymentExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert payment history
     */
    public function insertPaymentHistory($orderId, $amount, $paymentRef, $deadline, $invoiceId = '')
    {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $query = "
                INSERT INTO wpk4_backend_travel_payment_history 
                    (`order_id`, `payment_method`, `pay_type`, `trams_received_amount`, 
                     `process_date`, `reference_no`, `added_on`, `added_by`, 
                     `payment_change_deadline`, `gaura_invoice_id`) 
                VALUES 
                    (:order_id, '8', 'deposit', :amount, :current_time, :payment_ref, 
                     :current_time, 'customerportal_paydollar_callback', :deadline, :invoice_id)
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'amount' => $amount,
                'current_time' => $currentTime,
                'payment_ref' => $paymentRef,
                'deadline' => $deadline,
                'invoice_id' => $invoiceId
            ]);
        } catch (PDOException $e) {
            error_log("AsiaPayCallbackDAL::insertPaymentHistory error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

