<?php
/**
 * Payment Upload Data Access Layer
 * Handles database operations for payment upload and matching
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentUploadDAL extends BaseDAL
{
    /**
     * Check if order exists in posts table
     */
    public function orderExists(int $orderId): bool
    {
        $sql = "
            SELECT ID 
            FROM wpk4_posts 
            WHERE ID = ? 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$orderId]);
        return ($result !== false && $result !== null);
    }

    /**
     * Check if any postmeta exists for this order ID
     */
    public function hasAnyPostmeta(int $orderId): bool
    {
        $sql = "
            SELECT meta_id 
            FROM wpk4_postmeta 
            WHERE post_id = ? 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$orderId]);
        return ($result !== false && $result !== null);
    }

    /**
     * Get payment information by order ID from postmeta
     */
    public function getPaymentInfoByOrderId(int $orderId): ?array
    {
        $sql = "
            SELECT 
                pm1.post_id as order_id,
                pm1.meta_value as payable,
                pm2.meta_value as status
            FROM wpk4_postmeta pm1 
            LEFT JOIN wpk4_postmeta pm2 
                ON pm1.post_id = pm2.post_id 
                AND pm2.meta_key = 'wp_travel_payment_status'
            WHERE pm1.post_id = ? 
                AND pm1.meta_key = 'wp_travel_trip_price'
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$orderId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get payment information for multiple order IDs
     */
    public function getPaymentInfoByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $sql = "
            SELECT 
                pm1.post_id as order_id,
                pm1.meta_value as payable,
                pm2.meta_value as status
            FROM wpk4_postmeta pm1 
            LEFT JOIN wpk4_postmeta pm2 
                ON pm1.post_id = pm2.post_id 
                AND pm2.meta_key = 'wp_travel_payment_status'
            WHERE pm1.post_id IN ($placeholders)
                AND pm1.meta_key = 'wp_travel_trip_price'
        ";
        
        return $this->query($sql, $orderIds);
    }

    /**
     * Update payment status for an order
     */
    public function updatePaymentStatus(int $orderId, string $status): bool
    {
        // Check if payment status meta exists
        $checkSql = "
            SELECT meta_id 
            FROM wpk4_postmeta 
            WHERE post_id = ? 
                AND meta_key = 'wp_travel_payment_status'
            LIMIT 1
        ";
        
        $existing = $this->queryOne($checkSql, [$orderId]);
        
        if ($existing) {
            // Update existing
            $updateSql = "
                UPDATE wpk4_postmeta 
                SET meta_value = ? 
                WHERE post_id = ? 
                    AND meta_key = 'wp_travel_payment_status'
            ";
            return $this->execute($updateSql, [$status, $orderId]);
        } else {
            // Insert new
            $insertSql = "
                INSERT INTO wpk4_postmeta (post_id, meta_key, meta_value) 
                VALUES (?, 'wp_travel_payment_status', ?)
            ";
            return $this->execute($insertSql, [$orderId, $status]);
        }
    }

    /**
     * Update payment status for multiple orders
     */
    public function updatePaymentStatusBatch(array $orderIds, string $status): int
    {
        if (empty($orderIds)) {
            return 0;
        }
        
        $updated = 0;
        foreach ($orderIds as $orderId) {
            if ($this->updatePaymentStatus($orderId, $status)) {
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Get payment ID from postmeta (for payment post)
     */
    public function getPaymentIdByOrderId(int $orderId): ?int
    {
        $sql = "
            SELECT meta_value as payment_id
            FROM wpk4_postmeta 
            WHERE post_id = ? 
                AND meta_key = 'wp_travel_payment_id'
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$orderId]);
        return $result ? (int)$result['payment_id'] : null;
    }

    /**
     * Update payment status for payment post
     */
    public function updatePaymentPostStatus(int $paymentId, string $status): bool
    {
        // Check if payment status meta exists
        $checkSql = "
            SELECT meta_id 
            FROM wpk4_postmeta 
            WHERE post_id = ? 
                AND meta_key = 'wp_travel_payment_status'
            LIMIT 1
        ";
        
        $existing = $this->queryOne($checkSql, [$paymentId]);
        
        if ($existing) {
            // Update existing
            $updateSql = "
                UPDATE wpk4_postmeta 
                SET meta_value = ? 
                WHERE post_id = ? 
                    AND meta_key = 'wp_travel_payment_status'
            ";
            return $this->execute($updateSql, [$status, $paymentId]);
        } else {
            // Insert new
            $insertSql = "
                INSERT INTO wpk4_postmeta (post_id, meta_key, meta_value) 
                VALUES (?, 'wp_travel_payment_status', ?)
            ";
            return $this->execute($insertSql, [$paymentId, $status]);
        }
    }
}

