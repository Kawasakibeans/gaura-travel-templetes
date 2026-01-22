<?php
/**
 * Agent booking DAL.
 */

namespace App\DAL;

class AgentBookingDAL extends BaseDAL
{
    public function getPaymentMethods(): array
    {
        $sql = "
            SELECT account_name, bank_id
            FROM wpk4_backend_accounts_bank_account
            WHERE bank_id IN (7, 8, 9, 5)
            ORDER BY account_name ASC
        ";

        return $this->query($sql);
    }

    public function insertBookingShell(string $addedBy): int
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_bookings
                (order_type, product_id, order_id, order_date, travel_date, total_pax, payment_status, added_on, added_by)
            SELECT
                'failed', '1234554321', COALESCE(MAX(order_id), 0) + 1, NOW(), NOW(), 1, 'partially_paid', NOW(), ?
            FROM wpk4_backend_travel_bookings
            WHERE order_type = 'failed'
        ";

        $this->execute($sql, [$addedBy]);
        return (int)$this->lastInsertId();
    }

    public function getBookingByAutoId(int $autoId): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_bookings
            WHERE auto_id = ?
        ";

        $row = $this->queryOne($sql, [$autoId]);
        return $row ?: null;
    }

    public function insertBookingPax(int $orderId, string $orderDate, string $email, string $addedBy): void
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_pax
                (order_type, product_id, order_id, order_date, email_pax, pax_status, added_on, added_by)
            VALUES ('failed', '1234554321', ?, ?, ?, 'New', ?, ?)
        ";

        $this->execute($sql, [$orderId, $orderDate, $email, $orderDate, $addedBy]);
    }

    public function insertPaymentHistory(int $orderId, string $remarks, float $amount, ?string $reference, string $paymentMethod, string $orderDate, string $addedBy, string $deadline): void
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_payment_history
                (order_id, source, trams_remarks, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, payment_change_deadline)
            VALUES (?, 'gds', ?, ?, ?, ?, ?, 'deposit', ?, ?, ?)
        ";

        $this->execute($sql, [$orderId, $remarks, $amount, $reference, $paymentMethod, $orderDate, $orderDate, $addedBy, $deadline]);
    }

    public function getStockProduct(string $tripCode, string $travelDate): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_stock_product_manager
            WHERE trip_code = ?
              AND travel_date = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$tripCode, $travelDate]);
        return $row ?: null;
    }

    public function getLastLargeOrder(): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_bookings
            WHERE order_id > 90000000
            ORDER BY order_id DESC
            LIMIT 1
        ";

        $row = $this->queryOne($sql);
        return $row ?: null;
    }

    public function getPnrForTrip(string $tripCode, string $travelDate): ?string
    {
        $sql = "
            SELECT pnr
            FROM wpk4_backend_stock_management_sheet
            WHERE trip_id = ?
              AND DATE(dep_date) = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$tripCode, $travelDate]);
        return $row['pnr'] ?? null;
    }
}

