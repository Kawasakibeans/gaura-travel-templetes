<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationPaymentReminderDAL extends BaseDAL
{
    /**
     * Get bookings eligible for payment reminder
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

        return $this->query($sql, []);
    }

    /**
     * Insert email history for payment reminder
     */
    public function insertEmailHistory(string $orderId, string $currentDate): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_order_email_history 
            (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
            VALUES (?, 'Payment reminder', '', ?, 'n8n_auto', '', 'Payment Reminder')
        ";

        return $this->execute($sql, [$orderId, $currentDate]);
    }
}

