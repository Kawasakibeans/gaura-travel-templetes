<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationEvery30MinsDAL extends BaseDAL
{
    /**
     * Get bookings eligible for deposit deadline cancellation
     */
    public function getBookingsForDepositDeadlineCancellation(): array
    {
        $sql = "
            SELECT 
                MIN(bookings.auto_id) AS auto_id,
                bookings.order_id, 
                MIN(bookings.order_date) AS order_date, 
                MIN(bookings.payment_status) AS payment_status,
                MIN(bookings.source) AS source,
                MIN(bookings.deposit_deadline) AS deposit_deadline,
                COALESCE(SUM(pays.trams_received_amount), 0.00) AS trams_received_amount
            FROM wpk4_backend_travel_bookings bookings 
            LEFT JOIN wpk4_backend_travel_booking_pax pax 
                ON bookings.order_id = pax.order_id 
                AND bookings.co_order_id = pax.co_order_id 
                AND bookings.product_id = pax.product_id 
            LEFT JOIN wpk4_backend_travel_payment_history pays 
                ON bookings.order_id = pays.order_id 
            WHERE 
                bookings.payment_status = 'partially_paid' 
                AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                AND bookings.deposit_deadline <= NOW() 
                AND bookings.source <> 'import'
                AND bookings.order_type IN ('gds', 'WPT')
                AND NOT EXISTS (
                    SELECT 1 
                    FROM wpk4_backend_travel_payment_history p
                    WHERE p.order_id = bookings.order_id
                    GROUP BY p.order_id
                    HAVING SUM(p.trams_received_amount) > 0
                )
            GROUP BY 
                bookings.order_id
            ORDER BY 
                MIN(bookings.auto_id) ASC 
            LIMIT 100
        ";

        return $this->query($sql, []);
    }

    /**
     * Get paid amount for an order
     */
    public function getPaidAmount(string $orderId): float
    {
        $sql = "
            SELECT SUM(trams_received_amount) as deposit_amount 
            FROM wpk4_backend_travel_payment_history 
            WHERE order_id = ? 
                AND CAST(trams_received_amount AS DECIMAL(10,2)) != '0.00'
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return $result ? (float)$result['deposit_amount'] : 0.00;
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

