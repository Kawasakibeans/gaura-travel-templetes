<?php
/**
 * Payment Status Update DAL
 * Data Access Layer for payment status update operations (CSV upload)
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentStatusUpdateDAL extends BaseDAL
{
    /**
     * Get booking by order ID and co_order_id
     */
    public function getBookingByOrderIdAndCoOrderId(string $orderId, string $coOrderId = ''): ?array
    {
        if ($coOrderId) {
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = ? AND co_order_id = ?
                LIMIT 1
            ";
            $result = $this->queryOne($sql, [$orderId, $coOrderId]);
            return ($result === false) ? null : $result;
        } else {
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = ?
                LIMIT 1
            ";
            $result = $this->queryOne($sql, [$orderId]);
            return ($result === false) ? null : $result;
        }
    }

    /**
     * Update booking payment status
     */
    public function updateBookingPaymentStatus(array $data): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_bookings 
            SET payment_status = ?, balance = ?, payment_modified_by = ?, payment_modified = ?
            WHERE order_id = ? AND co_order_id = ? AND payment_status = 'partially_paid'
        ";

        $params = [
            $data['payment_status'],
            $data['balance'],
            $data['payment_modified_by'],
            $data['payment_modified'],
            $data['order_id'],
            $data['co_order_id']
        ];

        return $this->execute($sql, $params);
    }

    /**
     * Insert booking update history
     */
    public function insertBookingUpdateHistory(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_update_history 
            (order_id, co_order_id, merging_id, meta_key, meta_value, meta_key_data, updated_time, updated_user) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['co_order_id'] ?? '',
            $data['merging_id'] ?? '',
            $data['meta_key'],
            $data['meta_value'],
            $data['meta_key_data'] ?? '',
            $data['updated_time'],
            $data['updated_user']
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Validate order exists and get current status
     */
    public function validateOrderForUpdate(string $orderId, string $coOrderId = ''): ?array
    {
        try {
            $booking = $this->getBookingByOrderIdAndCoOrderId($orderId, $coOrderId);
            
            if (!$booking) {
                return null;
            }

            return [
                'order_id' => $booking['order_id'] ?? $orderId,
                'co_order_id' => $booking['co_order_id'] ?? $coOrderId ?? '',
                'current_payment_status' => $booking['payment_status'] ?? null,
                'current_balance' => $booking['balance'] ?? $booking['total_amount'] ?? 0,
                'total_amount' => $booking['total_amount'] ?? 0
            ];
        } catch (\Exception $e) {
            // Log error and return null to indicate order not found
            error_log("PaymentStatusUpdateDAL::validateOrderForUpdate error: " . $e->getMessage());
            return null;
        }
    }
}

