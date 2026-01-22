<?php
/**
 * Payment Manager DAL
 * Data Access Layer for payment manager operations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentManagerDAL extends BaseDAL
{
    /**
     * Get bookings with payment filters
     */
    public function getBookingsWithFilters(array $filters): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['order_id'])) {
            $whereParts[] = "booking.order_id = ?";
            $params[] = $filters['order_id'];
        }

        if (!empty($filters['profile_id'])) {
            $whereParts[] = "booking.profile_id = ?";
            $params[] = $filters['profile_id'];
        }

        if (!empty($filters['order_date_start']) && !empty($filters['order_date_end'])) {
            $whereParts[] = "booking.order_date >= ? AND booking.order_date <= ?";
            $params[] = $filters['order_date_start'];
            $params[] = $filters['order_date_end'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'all') {
                // No filter
            } else {
                $whereParts[] = "booking.payment_status = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['order_type'])) {
            $whereParts[] = "booking.order_type = ?";
            $params[] = $filters['order_type'];
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        // Determine ORDER BY
        $orderBy = 'ORDER BY booking.travel_date ASC, booking.order_date ASC';
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'partially_paid') {
                $orderBy = 'ORDER BY booking.order_date DESC';
            } elseif ($filters['status'] === 'canceled') {
                $orderBy = 'ORDER BY booking.payment_modified DESC';
            }
        }

        $limit = $filters['limit'] ?? 100;
        $params[] = $limit;

        $sql = "
            SELECT booking.*, pax.pnr 
            FROM wpk4_backend_travel_bookings booking
            JOIN wpk4_backend_travel_booking_pax pax ON pax.order_id = booking.order_id
            {$whereSQL}
            {$orderBy}
            LIMIT ?
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get payment history by order ID
     */
    public function getPaymentHistoryByOrderId(string $orderId): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = ? 
            ORDER BY process_date DESC
        ";

        return $this->query($sql, [$orderId]);
    }

    /**
     * Get passenger contact by order ID
     */
    public function getPassengerContactByOrderId(string $orderId): ?array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = ? 
            ORDER BY auto_id DESC 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$orderId]);
        
        // queryOne() returns false when no rows found, or array when found
        return ($result === false) ? null : $result;
    }

    /**
     * Get payment conversation by order ID and message type
     */
    public function getPaymentConversation(string $orderId, string $msgType): ?array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_conversations 
            WHERE order_id = ? AND msg_type = ? 
            ORDER BY updated_on DESC 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$orderId, $msgType]);
        
        // queryOne() returns false when no rows found, or array when found
        return ($result === false) ? null : $result;
    }

    /**
     * Create or update payment conversation
     */
    public function upsertPaymentConversation(array $data): int
    {
        // Check if exists
        $existing = $this->getPaymentConversation($data['order_id'], $data['msg_type']);

        if ($existing) {
            // Update logic could be added here if needed
            // For now, we always insert new records
        }

        $sql = "
            INSERT INTO wpk4_backend_travel_payment_conversations 
            (order_id, msg_type, message, updated_on, updated_by) 
            VALUES (?, ?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['msg_type'],
            $data['message'],
            $data['updated_on'],
            $data['updated_by']
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Get booking notes by order ID
     */
    public function getBookingNotesByOrderId(string $orderId): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_history_of_updates 
            WHERE type_id = ? AND meta_key = 'Booking Note Category' 
            ORDER BY updated_on DESC
        ";

        return $this->query($sql, [$orderId]);
    }

    /**
     * Get booking notes by order ID and updated time
     */
    public function getBookingNotesByOrderIdAndTime(string $orderId, string $updatedOn): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_history_of_updates 
            WHERE type_id = ? AND updated_on = ?
        ";

        return $this->query($sql, [$orderId, $updatedOn]);
    }

    /**
     * Get matched payments (for connect_payments)
     */
    public function getMatchedPayments(string $dateFrom, int $limit = 100): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE (order_type = '' OR order_type = 'WPT') 
            AND payment_status = 'partially_paid' 
            AND order_date >= ? 
            ORDER BY order_date ASC
            LIMIT ?
        ";

        return $this->query($sql, [$dateFrom, $limit]);
    }

    /**
     * Get non-matched payments with complex filters
     */
    public function getNonMatchedPayments(array $filters): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['order_type'])) {
            $whereParts[] = "booking.order_type = ?";
            $params[] = $filters['order_type'];
        }

        if (!empty($filters['order_id'])) {
            $whereParts[] = "booking.order_id = ?";
            $params[] = $filters['order_id'];
        }

        if (!empty($filters['status'])) {
            $whereParts[] = "booking.payment_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['profile_id'])) {
            // Subquery for profile_id
            $whereParts[] = "booking.order_id IN (SELECT order_id FROM wpk4_backend_travel_payment_history WHERE profile_no = ?)";
            $params[] = $filters['profile_id'];
        }

        if (!empty($filters['order_date_start']) && !empty($filters['order_date_end'])) {
            $whereParts[] = "DATE(booking.order_date) >= ? AND DATE(booking.order_date) <= ?";
            $params[] = $filters['order_date_start'];
            $params[] = $filters['order_date_end'];
        }

        // Default status filter for non-matched
        if (empty($filters['status'])) {
            $whereParts[] = "(booking.payment_status = 'receipt_received' OR booking.payment_status = 'partially_paid' OR booking.payment_status = 'voucher_submited' OR booking.payment_status = 'canceled')";
        }

        if (!empty($filters['date_from'])) {
            $whereParts[] = "booking.order_date >= ?";
            $params[] = $filters['date_from'];
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        $limit = $filters['limit'] ?? 150;
        $params[] = $limit;

        $sql = "
            SELECT booking.*, pax.pnr 
            FROM wpk4_backend_travel_bookings booking
            JOIN wpk4_backend_travel_booking_pax pax ON pax.order_id = booking.order_id
            {$whereSQL}
            ORDER BY booking.order_date ASC
            LIMIT ?
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get payments by order ID for balance calculation
     */
    public function getPaymentsForBalance(string $orderId): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = ? 
            ORDER BY process_date ASC
        ";

        return $this->query($sql, [$orderId]);
    }

    /**
     * Get orders for 72-hour cancellation
     */
    public function getOrdersFor72HourCancellation(): array
    {
        $sql = "
            SELECT DISTINCT order_id, order_date, payment_status, total_amount
            FROM wpk4_backend_travel_bookings 
            WHERE payment_status = 'partially_paid'
            AND order_date >= NOW() - INTERVAL 72 HOUR
            ORDER BY order_date ASC
        ";

        return $this->query($sql, []);
    }

    /**
     * Check if receipt uploaded (G360Events)
     */
    public function hasReceiptUploaded(string $orderId): bool
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_update_history 
            WHERE order_id = ?
            AND meta_key LIKE 'G360Events' 
            AND meta_value LIKE 'g360receiptattachments'
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return ($result !== false && $result !== null);
    }
}

