<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationNonPaymentDAL extends BaseDAL
{
    /**
     * Get bookings for payment reminder (20 mins to 600 mins after booking)
     */
    public function getBookingsForPaymentReminder(): array
    {
        $sql = "
            SELECT 
                bookings.auto_id, 
                bookings.order_id, 
                bookings.order_date, 
                bookings.travel_date, 
                bookings.payment_status, 
                COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
            FROM wpk4_backend_travel_bookings AS bookings 
            LEFT JOIN wpk4_backend_travel_booking_pax AS pax 
                ON bookings.order_id = pax.order_id 
                AND bookings.co_order_id = pax.co_order_id 
                AND bookings.product_id = pax.product_id 
            LEFT JOIN wpk4_backend_travel_payment_history AS pays 
                ON bookings.order_id = pays.order_id 
            WHERE 
                bookings.payment_status = 'partially_paid' 
                AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                AND bookings.order_date <= NOW() - INTERVAL 20 MINUTE 
                AND bookings.order_date >= NOW() - INTERVAL 600 MINUTE 
                AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = '0.00')
            ORDER BY 
                bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql, []);
    }

    /**
     * Get bookings for zero payment cancellation (3 hours after reminder sent)
     */
    public function getBookingsForZeroPaymentCancellation3Hours(): array
    {
        $sql = "
            SELECT 
                bookings.auto_id, 
                bookings.order_id, 
                bookings.order_date, 
                bookings.travel_date, 
                bookings.payment_status, 
                pays.trams_received_amount 
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
                AND bookings.order_date <= NOW() - INTERVAL 3 HOUR 
                AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = '0.00')
                AND EXISTS (
                    SELECT 1 
                    FROM wpk4_backend_order_email_history email 
                    WHERE email.order_id = bookings.order_id 
                    AND email.email_type = 'Payment reminder'
                )
            ORDER BY 
                bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql, []);
    }

    /**
     * Get FIT bookings for cancellation (25 hours after order)
     */
    public function getFitBookingsForCancellation25Hours(): array
    {
        $sql = "
            SELECT 
                bookings.auto_id, 
                bookings.order_id, 
                bookings.order_date, 
                bookings.travel_date, 
                bookings.payment_status, 
                COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
            FROM wpk4_backend_travel_bookings AS bookings 
            LEFT JOIN wpk4_backend_travel_booking_pax AS pax 
                ON bookings.order_id = pax.order_id 
                AND bookings.co_order_id = pax.co_order_id 
                AND bookings.product_id = pax.product_id 
            LEFT JOIN wpk4_backend_travel_payment_history AS pays 
                ON bookings.order_id = pays.order_id 
            WHERE 
                bookings.payment_status = 'partially_paid' 
                AND bookings.order_type = 'gds' 
                AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                AND bookings.order_date <= NOW() - INTERVAL 25 HOUR
                AND (pays.order_id IS NULL OR pays.trams_received_amount >= 0.00)
            ORDER BY 
                bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql, []);
    }

    /**
     * Get bookings for BPAY cancellation (96 hours after order)
     */
    public function getBookingsForBpayCancellation96Hours(): array
    {
        $sql = "
            SELECT 
                bookings.auto_id, 
                bookings.order_id, 
                bookings.order_date, 
                bookings.travel_date, 
                bookings.payment_status, 
                COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
            FROM wpk4_backend_travel_bookings AS bookings 
            LEFT JOIN wpk4_backend_travel_booking_pax AS pax 
                ON bookings.order_id = pax.order_id 
                AND bookings.co_order_id = pax.co_order_id 
                AND bookings.product_id = pax.product_id 
            LEFT JOIN wpk4_backend_travel_payment_history AS pays 
                ON bookings.order_id = pays.order_id 
            WHERE 
                bookings.payment_status = 'partially_paid' 
                AND bookings.sub_payment_status IN ('BPAY Paid', 'BPAY Received')
                AND bookings.order_date <= NOW() - INTERVAL 96 HOUR 
            ORDER BY 
                bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql, []);
    }

    /**
     * Get booking details for seat availability update
     */
    public function getBookingForSeatAvailability(string $orderId): ?array
    {
        $sql = "
            SELECT order_id, trip_code, travel_date, total_pax 
            FROM wpk4_backend_travel_bookings 
            WHERE order_type = 'WPT' AND order_id = ?
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
            SELECT pax 
            FROM wpk4_backend_manage_seat_availability 
            WHERE trip_code = ? AND DATE(travel_date) = ?
        ";

        $result = $this->queryOne($sql, [$tripCode, $travelDate]);
        return ($result === false) ? null : $result;
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
}

