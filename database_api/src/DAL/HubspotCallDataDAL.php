<?php
/**
 * Data access for HubSpot call sync.
 */

namespace App\DAL;

class HubspotCallDataDAL extends BaseDAL
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCallsByDate(string $date): array
    {
        $sql = "
            SELECT phone, call_date
            FROM wpk4_backend_agent_nobel_data_call_rec
            WHERE DATE(call_date) = ?
        ";

        return $this->query($sql, [$date]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBookingEmail(string $pattern): ?array
    {
        $sql = "
            SELECT email_pax
            FROM wpk4_backend_travel_booking_pax
            WHERE phone_pax LIKE ?
            ORDER BY auto_id DESC
            LIMIT 1
        ";

        return $this->queryOne($sql, [$pattern]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findPassengerEmail(string $pattern): ?array
    {
        $sql = "
            SELECT email_address
            FROM wpk4_backend_travel_passenger
            WHERE phone_number LIKE ?
            ORDER BY customer_id DESC
            LIMIT 1
        ";

        return $this->queryOne($sql, [$pattern]);
    }
}

