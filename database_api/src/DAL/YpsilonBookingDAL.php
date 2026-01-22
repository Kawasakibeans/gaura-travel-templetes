<?php
/**
 * Data access for Ypsilon booking synchronisation.
 */

namespace App\DAL;

use Exception;

class YpsilonBookingDAL extends BaseDAL
{
    public function getLatestPnrRecord(string $pnr): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_booking_pax
            WHERE pnr = ?
            ORDER BY auto_id DESC
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$pnr]);
        return $row ?: null;
    }

    public function getPassengerByGdsId(int $orderId, string $gdsPaxId): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_booking_pax
            WHERE order_id = ? AND gds_pax_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$orderId, $gdsPaxId]);
        return $row ?: null;
    }

    public function updatePaxBaggageAndMeal(int $orderId, ?string $gdsPaxId, ?string $baggage, ?string $meal): void
    {
        $set = [];
        $params = [];

        if ($baggage !== null) {
            $set[] = "baggage = ?";
            $params[] = $baggage;
        }

        if ($meal !== null) {
            $set[] = "meal = ?";
            $params[] = $meal;
        }

        if (empty($set)) {
            return;
        }

        $params[] = $orderId;
        $sql = "
            UPDATE wpk4_backend_travel_booking_pax
            SET " . implode(', ', $set) . "
            WHERE order_id = ?
        ";

        if ($gdsPaxId !== null) {
            $sql .= " AND gds_pax_id = ?";
            $params[] = $gdsPaxId;
        }

        $sql .= " AND (ticket_number = '' OR ticket_number IS NULL OR ticket_number = 'NULL')";

        $this->execute($sql, $params);
    }

    public function updateTicketNumberIfEmpty(int $orderId, ?string $gdsPaxId, string $ticketNumber): void
    {
        if ($ticketNumber === '') {
            return;
        }

        $params = [$ticketNumber, $orderId];
        $sql = "
            UPDATE wpk4_backend_travel_booking_pax
            SET ticket_number = ?, pax_status = 'Ticketed'
            WHERE order_id = ?
              AND (ticket_number = '' OR ticket_number IS NULL OR ticket_number = 'NULL')
        ";

        if ($gdsPaxId !== null) {
            $sql .= " AND gds_pax_id = ?";
            $params[] = $gdsPaxId;
        }

        $this->execute($sql, $params);
    }

    public function updatePaxContactIfEmpty(int $orderId, ?string $email, ?string $phone): void
    {
        if ($email) {
            $this->execute(
                "
                UPDATE wpk4_backend_travel_booking_pax
                SET email_pax = ?
                WHERE order_id = ? AND (email_pax = '' OR email_pax IS NULL)
                ",
                [$email, $orderId]
            );
        }

        if ($phone) {
            $this->execute(
                "
                UPDATE wpk4_backend_travel_booking_pax
                SET phone_pax = ?
                WHERE order_id = ? AND (phone_pax = '' OR phone_pax IS NULL)
                ",
                [$phone, $orderId]
            );
        }
    }

    public function insertGdsPnrCheckup(string $pnr, string $status, string $requestCode, string $requestedBy, string $timestamp): void
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_gds_pnr_checkup
                (pnr, status, request_code, requested_by, requested_on)
            VALUES (?, ?, ?, ?, ?)
        ";

        $this->execute($sql, [$pnr, $status, $requestCode, $requestedBy, $timestamp]);
    }

    public function getAirlineCode(?string $iata): ?string
    {
        if (!$iata) {
            return null;
        }

        $row = $this->queryOne(
            "
            SELECT airline_code
            FROM wpk4_backend_travel_booking_airline_code
            WHERE iata_code = ?
            LIMIT 1
            ",
            [$iata]
        );

        if (!$row) {
            return null;
        }

        $code = (string)$row['airline_code'];
        return $code === '-' ? null : ($code . '-');
    }

    public function insertHistoryUpdate(int $orderId, string $metaKey, string $metaValue, string $updatedBy, string $timestamp): void
    {
        $sql = "
            INSERT INTO wpk4_backend_history_of_updates
                (type_id, meta_key, meta_value, updated_by, updated_on)
            VALUES (?, ?, ?, ?, ?)
        ";

        $this->execute($sql, [$orderId, $metaKey, $metaValue, $updatedBy, $timestamp]);
    }
}

