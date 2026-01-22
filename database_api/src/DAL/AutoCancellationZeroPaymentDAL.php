<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationZeroPaymentDAL extends BaseDAL
{
    /**
     * Get bookings for payment reminder (20 minutes to 600 minutes after booking)
     * Matches query 1 from tpl_auto_cancellation_for_zeropayment_cron.php
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
                AND bookings.order_date <= NOW() - INTERVAL 20 MINUTE 
                AND bookings.order_date >= NOW() - INTERVAL 600 MINUTE 
                AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = '0.00')
                AND NOT EXISTS (
                    SELECT 1 
                    FROM wpk4_backend_order_email_history email 
                    WHERE email.order_id = bookings.order_id 
                    AND email.email_type = 'Payment reminder'
                )
            ORDER BY bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql);
    }

    /**
     * Get bookings for zero payment cancellation (3 hours)
     * Matches query 2 from tpl_auto_cancellation_for_zeropayment_cron.php
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
            ORDER BY bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql);
    }

    /**
     * Get FIT bookings for cancellation (25 hours)
     * Matches query 3 from tpl_auto_cancellation_for_zeropayment_cron.php
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
            ORDER BY bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql);
    }

    /**
     * Get bookings for BPAY cancellation (96 hours)
     * Matches query 4 from tpl_auto_cancellation_for_zeropayment_cron.php
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
            ORDER BY bookings.auto_id ASC 
            LIMIT 100
        ";

        return $this->query($sql);
    }

    /**
     * Get bookings for zero payment cancellation (all queries combined)
     * Returns all 4 query results
     */
    public function getBookingsForZeroPaymentCancellation(?string $previousDays = null): array
    {
        return [
            'reminder' => $this->getBookingsForPaymentReminder(),
            'zero_payment_3hours' => $this->getBookingsForZeroPaymentCancellation3Hours(),
            'fit_25hours' => $this->getFitBookingsForCancellation25Hours(),
            'bpay_96hours' => $this->getBookingsForBpayCancellation96Hours()
        ];
    }

    /**
     * Get phone number for an order
     */
    public function getPhoneNumber(string $orderId): ?string
    {
        $sql = "
            SELECT phone_pax 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = ? 
                AND phone_pax IS NOT NULL 
            ORDER BY auto_id ASC 
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [$orderId]);
        return $result ? $result['phone_pax'] : null;
    }
}