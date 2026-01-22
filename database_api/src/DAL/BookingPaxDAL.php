<?php
/**
 * Data access for booking passenger lookups.
 */

namespace App\DAL;

class BookingPaxDAL extends BaseDAL
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function getPassengersByPhoneSuffix(string $suffix): array
    {
        $sql = "
            SELECT
                pax.customer_id,
                pax.salutation,
                pax.fname,
                pax.mname,
                pax.lname,
                pax.gender,
                pax.dob,
                pax.phone_pax,
                pax.email_pax,
                pax.phone_pax_cropped,
                booking.payment_status
            FROM wpk4_backend_travel_booking_pax pax
            JOIN wpk4_backend_travel_bookings booking
              ON pax.order_id = booking.order_id
            WHERE pax.phone_pax_cropped = ?
              AND booking.payment_status IN ('paid', 'partially_paid')
              AND pax.customer_id IS NOT NULL
            ORDER BY pax.auto_id DESC
        ";

        return $this->query($sql, [$suffix]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getBillingByPhoneLike(string $pattern): ?array
    {
        $sql = "
            SELECT p.*, a.*
            FROM wpk4_backend_travel_passenger p
            LEFT JOIN wpk4_backend_travel_passenger_address a
              ON p.address_id = a.address_id
            WHERE p.phone_number LIKE ?
              AND p.address_id <> ''
            ORDER BY p.customer_id DESC
            LIMIT 1
        ";
    
        $result = $this->queryOne($sql, [$pattern]);
        
        // Convert false to null to match return type ?array
        return $result === false ? null : $result;
}
}

