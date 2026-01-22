<?php
/**
 * Customer Data Access Layer
 * Handles all database operations for customer/payment data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerDAL extends BaseDAL
{
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }

    /**
     * Get payment history by order ID
     */
    public function getPaymentHistoryByOrderId($orderId)
    {
        $query = "
            SELECT 
                p.*,
                ba.account_name as payment_method_name
            FROM wpk4_backend_travel_payment_history p
            LEFT JOIN wpk4_backend_accounts_bank_account ba ON p.payment_method = ba.bank_id
            WHERE p.order_id = :order_id
            AND (p.pay_type IN ('deposit', 'balance', 'balance ', 'Balance', 'deposit_adjustment', 'Refund', 'additional_payment') 
                 OR p.pay_type IS NULL 
                 OR p.pay_type = '')
            ORDER BY p.process_date DESC
        ";
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get date change charges by order ID
     */
    public function getDateChangeChargesByOrderId($orderId)
    {
        $query = "
            SELECT 
                p.*,
                ba.account_name as payment_method_name
            FROM wpk4_backend_travel_payment_history p
            LEFT JOIN wpk4_backend_accounts_bank_account ba ON p.payment_method = ba.bank_id
            WHERE p.order_id = :order_id
            AND p.pay_type IN ('dc_charge', 'Datechange')
            ORDER BY p.process_date DESC
        ";
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get custom payment links by order ID
     */
    public function getCustomPaymentsByOrderId($orderId)
    {
        $query = "
            SELECT *
            FROM wpk4_backend_travel_booking_custom_payments
            WHERE order_id = :order_id
            AND status = 'waiting'
            ORDER BY requested_on DESC
        ";
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Create payment record
     */
    public function createPayment($orderId, $paymentData)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_payment_history 
            (order_id, process_date, trams_received_amount, pay_type, reference_no, trams_remarks, payment_method, added_on, modified_date)
            VALUES (:order_id, :process_date, :amount, :payment_type, :reference_no, :remarks, :payment_method, NOW(), NOW())
        ";
        
        $params = [
            'order_id' => $orderId,
            'process_date' => $paymentData['process_date'],
            'amount' => $paymentData['amount'],
            'payment_type' => $paymentData['payment_type'],
            'reference_no' => $paymentData['reference_no'] ?? null,
            'remarks' => $paymentData['remarks'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? null
        ];
        
        $this->execute($query, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Get payment by ID
     */
    public function getPaymentById($paymentId)
    {
        $query = "
            SELECT p.*, ba.account_name as payment_method_name
            FROM wpk4_backend_travel_payment_history p
            LEFT JOIN wpk4_backend_accounts_bank_account ba ON p.payment_method = ba.bank_id
            WHERE p.auto_id = :payment_id
            LIMIT 1
        ";
        return $this->queryOne($query, ['payment_id' => $paymentId]);
    }

    /**
     * Verify payment ownership
     */
    public function verifyPaymentOwnership($paymentId, $orderId)
    {
        $query = "
            SELECT COUNT(*) as count
            FROM wpk4_backend_travel_payment_history
            WHERE auto_id = :payment_id AND order_id = :order_id
        ";
        $result = $this->queryOne($query, [
            'payment_id' => $paymentId,
            'order_id' => $orderId
        ]);
        
        return (int)$result['count'] > 0;
    }

    /**
     * Delete payment
     */
    public function deletePayment($paymentId, $orderId)
    {
        $query = "
            DELETE FROM wpk4_backend_travel_payment_history
            WHERE auto_id = :payment_id AND order_id = :order_id
        ";
        return $this->execute($query, [
            'payment_id' => $paymentId,
            'order_id' => $orderId
        ]);
    }

    /**
     * Delete payment only if uncleared (cleared_date IS NULL)
     */
    public function deleteUnclearedPaymentById($paymentId)
    {
        $query = "
            DELETE FROM wpk4_backend_travel_payment_history
            WHERE auto_id = :payment_id AND cleared_date IS NULL
        ";
        return $this->execute($query, [
            'payment_id' => $paymentId
        ]);
    }

    /**
     * Update payment clearing fields
     */
    public function updatePaymentClearing($paymentId, $clearingData)
    {
        $fields = [];
        $params = [':payment_id' => $paymentId];
        
        if (isset($clearingData['cleared_date'])) {
            $fields[] = 'cleared_date = :cleared_date';
            $params[':cleared_date'] = $clearingData['cleared_date'];
        }
        
        if (isset($clearingData['cleared_by'])) {
            $fields[] = 'cleared_by = :cleared_by';
            $params[':cleared_by'] = $clearingData['cleared_by'];
        }
        
        if (isset($clearingData['is_reconciliated'])) {
            $fields[] = 'is_reconciliated = :is_reconciliated';
            $params[':is_reconciliated'] = $clearingData['is_reconciliated'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // Add modified_date
        $fields[] = 'modified_date = NOW()';
        
        $query = "
            UPDATE wpk4_backend_travel_payment_history 
            SET " . implode(', ', $fields) . "
            WHERE auto_id = :payment_id
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Sum partially paid pax (nonpaid) for WPT by trip_code+date patterns
     */
    public function getNonPaidPaxByTripDate($tripCodePattern, $datePattern)
    {
        $query = "
            SELECT 
                trip_code,
                travel_date,
                SUM(total_pax) as total_nonpaid_pax
            FROM wpk4_backend_travel_bookings
            WHERE payment_status = 'partially_paid'
                AND order_type = 'wpt'
                AND trip_code LIKE :trip_code_pattern
                AND DATE_FORMAT(travel_date, '%Y-%m-%d') LIKE :date_pattern
            GROUP BY trip_code, travel_date
            ORDER BY trip_code, travel_date
        ";
        
        return $this->query($query, [
            'trip_code_pattern' => $tripCodePattern,
            'date_pattern' => $datePattern
        ]);
    }

    /**
     * Sum paid pax for WPT by trip_code+date patterns
     */
    public function getPaidPaxByTripDate($tripCodePattern, $datePattern)
    {
        $query = "
            SELECT 
                trip_code,
                travel_date,
                SUM(total_pax) as total_paid_pax
            FROM wpk4_backend_travel_bookings
            WHERE payment_status = 'paid'
                AND order_type = 'wpt'
                AND trip_code LIKE :trip_code_pattern
                AND DATE_FORMAT(travel_date, '%Y-%m-%d') LIKE :date_pattern
            GROUP BY trip_code, travel_date
            ORDER BY trip_code, travel_date
        ";
        
        return $this->query($query, [
            'trip_code_pattern' => $tripCodePattern,
            'date_pattern' => $datePattern
        ]);
    }

    /**
     * Count paid pax for WPT by exact trip_code+date key
     */
    public function getPaidPaxCountByTripDateExact($tripCode, $travelDate)
    {
        $query = "
            SELECT 
                SUM(total_pax) as total_paid_pax
            FROM wpk4_backend_travel_bookings
            WHERE payment_status = 'paid'
                AND order_type = 'wpt'
                AND trip_code = :trip_code
                AND DATE(travel_date) = DATE(:travel_date)
        ";
        
        $result = $this->queryOne($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
        
        return (int)($result['total_paid_pax'] ?? 0);
    }

    /**
     * Count partially paid (nonpaid) pax for WPT by exact trip_code+date key
     */
    public function getNonPaidPaxCountByTripDateExact($tripCode, $travelDate)
    {
        $query = "
            SELECT 
                SUM(total_pax) as total_nonpaid_pax
            FROM wpk4_backend_travel_bookings
            WHERE payment_status = 'partially_paid'
                AND order_type = 'wpt'
                AND trip_code = :trip_code
                AND DATE(travel_date) = DATE(:travel_date)
        ";
        
        $result = $this->queryOne($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
        
        return (int)($result['total_nonpaid_pax'] ?? 0);
    }

    /**
     * List bookings by trip_code suffix and travel_date prefix with paid/partially_paid statuses
     */
    public function listBookingsByTripAndDatePrefix($tripCodeSuffix, $travelDatePrefix, $limit = 100)
    {
        $query = "
            SELECT 
                order_id,
                order_date,
                trip_code,
                travel_date,
                payment_status,
                total_pax,
                total_amount,
                order_type,
                agent_info
            FROM wpk4_backend_travel_bookings
            WHERE payment_status IN ('paid', 'partially_paid')
                AND trip_code LIKE :trip_code_suffix
                AND DATE_FORMAT(travel_date, '%Y-%m-%d') LIKE :travel_date_prefix
            ORDER BY travel_date ASC, order_date DESC
            LIMIT :limit
        ";
        
        return $this->query($query, [
            'trip_code_suffix' => '%' . $tripCodeSuffix,
            'travel_date_prefix' => $travelDatePrefix . '%',
            'limit' => (int)$limit
        ], [
            'limit' => \PDO::PARAM_INT
        ]);
    }

    /**
     * List paid bookings by trip_code suffix and travel_date prefix
     */
    public function listPaidBookingsByTripAndDatePrefix($tripCodeSuffix, $travelDatePrefix, $limit = 100)
    {
        $query = "
            SELECT 
                order_id,
                order_date,
                trip_code,
                travel_date,
                payment_status,
                total_pax,
                total_amount,
                order_type,
                agent_info
            FROM wpk4_backend_travel_bookings
            WHERE payment_status = 'paid'
                AND trip_code LIKE :trip_code_suffix
                AND DATE_FORMAT(travel_date, '%Y-%m-%d') LIKE :travel_date_prefix
            ORDER BY travel_date ASC, order_date DESC
            LIMIT :limit
        ";
        
        return $this->query($query, [
            'trip_code_suffix' => '%' . $tripCodeSuffix,
            'travel_date_prefix' => $travelDatePrefix . '%',
            'limit' => (int)$limit
        ], [
            'limit' => \PDO::PARAM_INT
        ]);
    }
}

