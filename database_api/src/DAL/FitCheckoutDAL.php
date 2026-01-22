<?php
/**
 * FIT checkout agent DAL
 */

namespace App\DAL;

class FitCheckoutDAL extends BaseDAL
{
    public function getBillingDataByCustomerId(int $customerId): ?array
    {
        $sql = "
            SELECT p.*, a.*
            FROM wpk4_backend_travel_passenger p
            LEFT JOIN wpk4_backend_travel_passenger_address a
                ON p.address_id = a.address_id
            WHERE p.customer_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$customerId]);
        return $row ?: null;
    }

    /**
     * @param array<int,int> $customerIds
     * @return array<int,array<string,mixed>>
     */
    public function getPassengersByCustomerIds(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_passenger
            WHERE customer_id IN ($placeholders)
            ORDER BY customer_id DESC
        ";

        return $this->query($sql, array_values($customerIds));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getG360PassengersByOrderId(int $orderId): array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_booking_pax_g360_booking
            WHERE order_id = ?
            ORDER BY auto_id ASC
        ";

        return $this->query($sql, [$orderId]);
    }

    public function getYpsilonAddressByOrderId(int $orderId): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_address_ypsilon
            WHERE order_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$orderId]);
        return $row ?: null;
    }

    public function getG360BookingByOrderId(int $orderId): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_bookings_g360_booking
            WHERE order_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$orderId]);
        return $row ?: null;
    }

    public function getDistinctPnrByOrderId(int $orderId): ?string
    {
        $sql = "
            SELECT DISTINCT pnr
            FROM wpk4_backend_travel_booking_pax_g360_booking
            WHERE order_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$orderId]);
        return $row['pnr'] ?? null;
    }

    public function getMaxG360OrderId(): int
    {
        $sql = "
            SELECT COALESCE(MAX(order_id), 0) AS max_order
            FROM wpk4_backend_travel_bookings_g360_booking
            WHERE order_id < 900000000
        ";

        $row = $this->queryOne($sql);
        return (int)($row['max_order'] ?? 0);
    }

    public function insertG360Booking(array $data): void
    {
        $this->insert('wpk4_backend_travel_bookings_g360_booking', $data);
    }

    public function insertYpsilonAddress(array $data): int
    {
        $this->insert('wpk4_backend_travel_address_ypsilon', $data);
        return (int)$this->lastInsertId();
    }

    public function insertG360BookingPax(array $data): void
    {
        $this->insert('wpk4_backend_travel_booking_pax_g360_booking', $data);
    }

    public function logHistoryUpdate(int $typeId, string $metaKey, string $metaValue, string $updatedBy, string $updatedOn): void
    {
        $this->insert('wpk4_backend_history_of_updates', [
            'type_id' => $typeId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => $updatedOn,
        ]);
    }

    public function insertUrlRedirect(string $timestamp, string $url): int
    {
        $this->insert('wpk4_backend_travel_agent_booking_url_redirect', [
            'timestamp' => $timestamp,
            'url' => $url,
        ]);

        return (int)$this->lastInsertId();
    }

    public function getUrlRedirect(int $id, string $timestamp): ?string
    {
        $sql = "
            SELECT url
            FROM wpk4_backend_travel_agent_booking_url_redirect
            WHERE id = ? AND timestamp = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$id, $timestamp]);
        return $row['url'] ?? null;
    }

    public function insertSmsHistory(string $message, string $phone, string $source, string $messageId, string $addedBy): void
    {
        $this->insert('wpk4_backend_order_sms_history', [
            'message' => $message,
            'phone' => $phone,
            'source' => $source,
            'message_id' => $messageId,
            'added_by' => $addedBy,
            'type' => 'agent_fit_booking',
        ]);
    }

    private function insert(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', $columns),
            $placeholders
        );

        $this->execute($sql, array_values($data));
    }
}


