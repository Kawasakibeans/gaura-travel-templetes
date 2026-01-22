<?php
/**
 * Payment Followup DAL
 * Data Access Layer for payment followup operations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentFollowupDAL extends BaseDAL
{
    /**
     * Get payment followup list with filters
     */
    public function getPaymentFollowups(string $fromDate, string $toDate, int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT
              b.order_id,
              MIN(b.order_date)   AS order_date,
              MIN(b.travel_date)  AS travel_date,
              f.pax_preferred_pay_mode,
              f.does_pax_wants_to_pay,
              f.ttl,
              f.ptl,
              (
                SELECT TRIM(CONCAT(COALESCE(pp.fname,''),' ',COALESCE(pp.lname,'')))
                FROM wpk4_backend_travel_booking_pax pp
                WHERE pp.order_id = b.order_id
                ORDER BY pp.auto_id ASC
                LIMIT 1
              ) AS pax_first,
              (
                SELECT pp.phone_pax
                FROM wpk4_backend_travel_booking_pax pp
                WHERE pp.order_id = b.order_id
                ORDER BY pp.auto_id ASC
                LIMIT 1
              ) AS phone_first,
              (
                SELECT pp.email_pax
                FROM wpk4_backend_travel_booking_pax pp
                WHERE pp.order_id = b.order_id
                ORDER BY pp.auto_id ASC
                LIMIT 1
              ) AS email_first,
              (
                SELECT rr.comment
                FROM wpk4_backend_payment_followup_comments rr
                WHERE rr.order_id = b.order_id
                ORDER BY rr.added_on DESC, rr.id DESC
                LIMIT 1
              ) AS latest_comment
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_payment_followup f
              ON f.order_id = b.order_id
            WHERE
              b.order_date >= ?
              AND b.order_date <= ?
              AND b.payment_status = 'partially_paid'
            GROUP BY
              b.order_id,
              f.pax_preferred_pay_mode, f.does_pax_wants_to_pay, f.ttl, f.ptl
            ORDER BY travel_date ASC
            LIMIT ? OFFSET ?
        ";

        return $this->query($sql, [$fromDate, $toDate, $limit, $offset]);
    }

    /**
     * Get payment followup by order ID
     */
    public function getPaymentFollowupByOrderId(string $orderId): ?array
    {
        $sql = "
            SELECT pax_preferred_pay_mode, does_pax_wants_to_pay, ttl, ptl
            FROM wpk4_backend_travel_payment_followup
            WHERE order_id = ?
            LIMIT 1
        ";
    
        $result = $this->queryOne($sql, [$orderId]);
        
        // Convert false to null to match return type ?array
        return $result === false ? null : $result;
    }

    /**
     * Check if payment followup exists
     */
    public function paymentFollowupExists(string $orderId): bool
    {
        $sql = "SELECT order_id FROM wpk4_backend_travel_payment_followup WHERE order_id = ? LIMIT 1";
        $result = $this->queryOne($sql, [$orderId]);
        return ($result !== false && $result !== null);
    }

    /**
     * Create payment followup
     */
    public function createPaymentFollowup(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_payment_followup 
            (order_id, pax_preferred_pay_mode, does_pax_wants_to_pay, ttl, ptl, updated_on) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['pax_preferred_pay_mode'] ?? null,
            $data['does_pax_wants_to_pay'] ?? null,
            $data['ttl'] ?? null,
            $data['ptl'] ?? null,
            $data['updated_on']
        ];

        return $this->execute($sql, $params);
    }

    /**
     * Update payment followup
     */
    public function updatePaymentFollowup(array $data): bool
    {
        $sets = [];
        $params = [];

        if (isset($data['pax_preferred_pay_mode'])) {
            $sets[] = "pax_preferred_pay_mode = ?";
            $params[] = $data['pax_preferred_pay_mode'];
        }

        if (isset($data['does_pax_wants_to_pay'])) {
            $sets[] = "does_pax_wants_to_pay = ?";
            $params[] = $data['does_pax_wants_to_pay'];
        }

        if (isset($data['ttl'])) {
            if ($data['ttl'] === null || $data['ttl'] === '') {
                $sets[] = "ttl = NULL";
            } else {
                $sets[] = "ttl = ?";
                $params[] = (string)$data['ttl'];
            }
        }

        if (isset($data['ptl'])) {
            if ($data['ptl'] === null || $data['ptl'] === '') {
                $sets[] = "ptl = NULL";
            } else {
                $sets[] = "ptl = ?";
                $params[] = (string)$data['ptl'];
            }
        }

        if (isset($data['updated_on'])) {
            $sets[] = "updated_on = ?";
            $params[] = $data['updated_on'];
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $data['order_id'];

        $sql = "UPDATE wpk4_backend_travel_payment_followup SET " . implode(', ', $sets) . " WHERE order_id = ?";
        return $this->execute($sql, $params);
    }

    /**
     * Get remarks by order ID
     */
    public function getRemarksByOrderId(string $orderId): array
    {
        $sql = "
            SELECT id, comment, added_by, added_on
            FROM wpk4_backend_payment_followup_comments
            WHERE order_id = ?
            ORDER BY added_on DESC, id DESC
        ";

        return $this->query($sql, [$orderId]);
    }

    /**
     * Create remark
     */
    public function createRemark(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_payment_followup_comments 
            (order_id, comment, added_by, added_on) 
            VALUES (?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['comment'],
            $data['added_by'],
            $data['added_on']
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Ensure remarks table exists
     */
    public function ensureRemarksTableExists(): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS wpk4_backend_payment_followup_comments (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              order_id VARCHAR(64) NOT NULL,
              comment TEXT NOT NULL,
              added_by VARCHAR(191) DEFAULT NULL,
              added_on DATETIME NOT NULL,
              PRIMARY KEY (id),
              KEY order_id_idx (order_id),
              KEY added_on_idx (added_on)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        return $this->execute($sql, []);
    }
}

