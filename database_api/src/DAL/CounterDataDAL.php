<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class CounterDataDAL extends BaseDAL
{
    /**
     * Get booking details by product and date
     */
    public function getBookingDetails(int $productId, string $arrivalDate, ?array $paymentStatuses = null, int $limit = 1000, int $offset = 0): array
    {
        $sql = "
            SELECT
                pm.post_id AS OrderNo,
                pm.meta_value AS Product_ID,
                pm1.meta_value AS traveldate,
                pm2.meta_value AS payment_status
            FROM wpk4_postmeta pm
            LEFT JOIN wpk4_postmeta pm1 ON pm1.post_id = pm.post_id AND pm1.meta_key = 'wp_travel_arrival_date'
            LEFT JOIN wpk4_postmeta pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = 'wp_travel_payment_status'
            WHERE pm.meta_value = :product_id 
            AND pm.meta_key = 'wp_travel_post_id'
            AND pm1.meta_value = :arrival_date
        ";
        
        $params = [
            ':product_id' => $productId,
            ':arrival_date' => $arrivalDate
        ];
        
        if ($paymentStatuses && !empty($paymentStatuses)) {
            $placeholders = [];
            foreach ($paymentStatuses as $i => $status) {
                $key = ':status' . $i;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $sql .= " AND pm2.meta_value IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get booking details count
     */
    public function getBookingDetailsCount(int $productId, string $arrivalDate, ?array $paymentStatuses = null): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_postmeta pm
            LEFT JOIN wpk4_postmeta pm1 ON pm1.post_id = pm.post_id AND pm1.meta_key = 'wp_travel_arrival_date'
            LEFT JOIN wpk4_postmeta pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = 'wp_travel_payment_status'
            WHERE pm.meta_value = :product_id 
            AND pm.meta_key = 'wp_travel_post_id'
            AND pm1.meta_value = :arrival_date
        ";
        
        $params = [
            ':product_id' => $productId,
            ':arrival_date' => $arrivalDate
        ];
        
        if ($paymentStatuses && !empty($paymentStatuses)) {
            $placeholders = [];
            foreach ($paymentStatuses as $i => $status) {
                $key = ':status' . $i;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $sql .= " AND pm2.meta_value IN (" . implode(',', $placeholders) . ")";
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }
}

