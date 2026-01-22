<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancelBookingsDAL extends BaseDAL
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
     * Get bookings for cancellation view (partially paid from last 3 days)
     */
    public function getBookingsForCancellation(?string $previousDays = null): array
    {
        if ($previousDays === null) {
            $previousDays = date('Y-m-d', strtotime('-3 days'));
        }

        $sql = "
            SELECT DISTINCT
                bookings.auto_id,
                bookings.order_id,
                bookings.trip_code,
                bookings.order_type,
                bookings.source,
                bookings.order_date,
                bookings.payment_status
            FROM wpk4_backend_travel_bookings bookings
            JOIN wpk4_backend_travel_booking_pax pax ON
                bookings.order_id = pax.order_id
                AND bookings.co_order_id = pax.co_order_id
                AND bookings.product_id = pax.product_id
            WHERE bookings.payment_status = 'partially_paid'
                AND DATE(bookings.order_date) >= ?
            ORDER BY bookings.auto_id DESC
            LIMIT 20
        ";

        return $this->query($sql, [$previousDays]);
    }
}

