<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationGdealsFullpaymentDAL extends BaseDAL
{
    /**
     * Get GDeals (WPT) bookings for full payment deadline cancellation
     */
    public function getGdealsBookingsForFullPaymentCancellation(): array
    {
        $sql = "
            SELECT DISTINCT
                b.order_id,
                b.order_date,
                b.total_amount,
                COALESCE(ph.total_received, 0) AS payment,
                b.payment_status
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN (
                SELECT order_id, SUM(trams_received_amount) AS total_received
                FROM wpk4_backend_travel_payment_history
                GROUP BY order_id
            ) ph ON b.order_id = ph.order_id
            WHERE b.full_payment_deadline <= NOW()
                AND b.order_type IN ('WPT')
                AND b.payment_status = 'partially_paid'
                AND (b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received') OR b.sub_payment_status IS NULL)
                AND COALESCE(ph.total_received, 0) <> b.total_amount
        ";

        return $this->query($sql, []);
    }

    /**
     * Get total amount for an order
     */
    public function getTotalAmount(string $orderId): ?float
    {
        $sql = "SELECT total_amount FROM wpk4_backend_travel_bookings WHERE order_id = ?";
        $result = $this->queryOne($sql, [$orderId]);
        return $result ? (float)$result['total_amount'] : null;
    }

    /**
     * Get paid amount for an order
     */
    public function getPaidAmount(string $orderId): float
    {
        $sql = "
            SELECT SUM(trams_received_amount) AS deposit_amount 
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = ?
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return $result ? (float)$result['deposit_amount'] : 0;
    }

    /**
     * Update booking payment status to canceled
     */
    public function updateBookingStatus(string $orderId, string $byUser, string $currentDate): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_bookings 
            SET payment_status = 'canceled', 
                payment_modified = ?, 
                payment_modified_by = ? 
            WHERE order_id = ?
        ";

        return $this->execute($sql, [$currentDate, $byUser, $orderId]);
    }

    /**
     * Insert booking update history
     */
    public function insertBookingUpdateHistory(string $orderId, string $currentDate, string $byUser): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_update_history 
            (order_id, meta_key, meta_value, updated_time, updated_user) 
            VALUES (?, 'payment_status', 'canceled', ?, ?)
        ";

        return $this->execute($sql, [$orderId, $currentDate, $byUser]);
    }

    /**
     * Get booking details for seat availability update
     */
    public function getBookingForSeatAvailability(string $orderId): ?array
    {
        $sql = "
            SELECT order_id, trip_code, travel_date, total_pax 
            FROM wpk4_backend_travel_bookings 
            WHERE order_type != 'gds' AND order_id = ?
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get current seat availability
     */
    public function getCurrentSeatAvailability(string $tripCode, string $travelDate): ?array
    {
        $sql = "
            SELECT pax, pricing_id 
            FROM wpk4_backend_manage_seat_availability 
            WHERE trip_code = ? AND DATE(travel_date) = ?
        ";

        $result = $this->queryOne($sql, [$tripCode, $travelDate]);
        return ($result === false) ? null : $result;
    }

    /**
     * Check if seat availability log already exists
     */
    public function checkSeatAvailabilityLogExists(string $pricingId, string $byUser, string $orderId, string $date): bool
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_manage_seat_availability_log 
            WHERE pricing_id = ? 
                AND updated_by = ? 
                AND order_id = ? 
                AND DATE(updated_on) = ?
        ";

        $result = $this->queryOne($sql, [$pricingId, $byUser, $orderId, $date]);
        return ($result !== false && $result !== null);
    }

    /**
     * Update seat availability
     */
    public function updateSeatAvailability(string $tripCode, string $travelDate, int $newPax, string $byUser, string $updatedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_manage_seat_availability 
            SET pax = ?, pax_updated_by = ?, pax_updated_on = ?
            WHERE trip_code = ? AND DATE(travel_date) = ?
        ";

        return $this->execute($sql, [$newPax, $byUser, $updatedOn, $tripCode, $travelDate]);
    }

    /**
     * Insert seat availability log
     */
    public function insertSeatAvailabilityLog(string $pricingId, int $originalPax, int $newPax, string $updatedOn, string $byUser, string $orderId, int $changedPaxCount): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_manage_seat_availability_log 
            (pricing_id, original_pax, new_pax, updated_on, updated_by, order_id, changed_pax_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        return $this->execute($sql, [$pricingId, $originalPax, $newPax, $updatedOn, $byUser, $orderId, $changedPaxCount]);
    }
}

