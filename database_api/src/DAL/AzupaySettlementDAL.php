<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AzupaySettlementDAL extends BaseDAL
{
    /**
     * Check IP address access
     */
    public function checkIpAddress(string $ipAddress): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address = ? LIMIT 1";
        $result = $this->queryOne($sql, [$ipAddress]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get payment request ID by Azupay PayID
     */
    public function getPaymentRequestIdByPayId(string $payId): ?string
    {
        $sql = "
            SELECT payment_request_id 
            FROM wpk4_backend_travel_booking_custom_payments 
            WHERE azupay_payid = ? 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$payId]);
        return $result ? $result['payment_request_id'] : null;
    }

    /**
     * Get order ID by payment request ID
     */
    public function getOrderIdByPaymentRequestId(string $paymentRequestId): ?string
    {
        $sql = "
            SELECT order_id 
            FROM wpk4_backend_travel_payment_history 
            WHERE payment_request_id = ? 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$paymentRequestId]);
        return $result ? $result['order_id'] : null;
    }

    /**
     * Get booking info by order ID
     */
    public function getBookingByOrderId(string $orderId): ?array
    {
        $sql = "
            SELECT order_id, payment_status 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = ? 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Check if payment exists in payment history
     */
    public function checkPaymentExists(string $paymentRequestId, float $amount, string $paymentMethod): ?string
    {
        $sql = "
            SELECT order_id 
            FROM wpk4_backend_travel_payment_history 
            WHERE payment_request_id = ? 
                AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST(? AS DECIMAL(10,2)) 
                AND payment_method = ? 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$paymentRequestId, $amount, $paymentMethod]);
        return $result ? $result['order_id'] : null;
    }

    /**
     * Update payment history reconciliation status
     */
    public function updatePaymentReconciliation(
        string $orderId,
        string $paymentRequestId,
        string $transactionDate,
        float $amount,
        string $settlementDate,
        string $paymentMethod
    ): bool
    {
        $transactionDateWithoutSeconds = substr($transactionDate, 0, 10);

        $sql = "
            UPDATE wpk4_backend_travel_payment_history 
            SET 
                is_reconciliated = 'yes',
                cleared_date = ?,
                cleared_by = 'azupay_settlement_api'
            WHERE 
                CAST(trams_received_amount AS DECIMAL(10,2)) = CAST(? AS DECIMAL(10,2)) 
                AND cleared_date IS NULL 
                AND cleared_by IS NULL 
                AND payment_method = ? 
                AND order_id = ? 
                AND payment_request_id = ? 
                AND DATE(process_date) = ?
        ";

        return $this->execute($sql, [
            $settlementDate,
            $amount,
            $paymentMethod,
            $orderId,
            $paymentRequestId,
            $transactionDateWithoutSeconds
        ]);
    }
}

