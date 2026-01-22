<?php
/**
 * Payment Callback Data Access Layer
 * Handles database operations for payment callbacks
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentCallbackDAL extends BaseDAL
{
    /**
     * Get booking info
     */
    public function getBookingInfo($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = ? LIMIT 1";
        return $this->queryOne($query, [$orderId]);
    }

    /**
     * Get booking details with travel info
     */
    public function getBookingDetails($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  WHERE order_id = ? 
                  ORDER BY travel_date ASC";
        
        return $this->query($query, [$orderId]);
    }

    /**
     * Get PAX info
     */
    public function getPaxInfo($orderId, $productId = null)
    {
        if ($productId) {
            $query = "SELECT * FROM wpk4_backend_travel_booking_pax 
                      WHERE order_id = ? AND product_id = ?";
            return $this->query($query, [$orderId, $productId]);
        }

        $query = "SELECT * FROM wpk4_backend_travel_booking_pax 
                  WHERE order_id = ? 
                  ORDER BY auto_id ASC 
                  LIMIT 1";
        
        return $this->queryOne($query, [$orderId]);
    }

    /**
     * Log payment
     */
    public function logPayment($orderId, $callbackData)
    {
        $query = "INSERT INTO wpk4_backend_travel_payment_history 
                  (order_id, trams_received_amount, process_date, pay_type, 
                   reference_no, payment_method, trams_remarks, added_on)
                  VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())";
        
        $params = [
            $orderId,
            $callbackData['amount'],
            $callbackData['pay_type'] ?? 'online',
            $callbackData['transaction_id'],
            $callbackData['payment_method'] ?? 'skypay',
            $callbackData['remarks'] ?? 'SkyPay payment'
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update booking payment status
     */
    public function updateBookingPaymentStatus($orderId, $amount)
    {
        $currentTime = date('Y-m-d H:i:s');
        
        $query = "UPDATE wpk4_backend_travel_bookings 
                  SET payment_status = 'paid', 
                      payment_modified = ?, 
                      payment_modified_by = 'skypay_callback'
                  WHERE order_id = ?";
        
        return $this->execute($query, [$currentTime, $orderId]);
    }

    /**
     * Log payment history
     */
    public function logPaymentHistory($orderId, $metaKey, $metaValue)
    {
        $currentTime = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO wpk4_backend_history_of_updates 
                  (type_id, meta_key, meta_value, updated_on, updated_by)
                  VALUES (?, ?, ?, ?, 'skypay_callback')";
        
        return $this->execute($query, [$orderId, $metaKey, $metaValue, $currentTime]);
    }

    /**
     * Log failed payment
     */
    public function logFailedPayment($orderId, $callbackData)
    {
        $query = "INSERT INTO wpk4_backend_payment_callback_log 
                  (order_id, transaction_id, amount, status, callback_data, created_at)
                  VALUES (?, ?, ?, 'failed', ?, NOW())";
        
        $params = [
            $orderId,
            $callbackData['transaction_id'],
            $callbackData['amount'],
            json_encode($callbackData)
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_payment_history 
                  WHERE order_id = ? 
                  ORDER BY process_date DESC";
        
        return $this->query($query, [$orderId]);
    }
}

