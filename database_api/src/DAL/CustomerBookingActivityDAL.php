<?php
/**
 * Customer Booking Activity Data Access Layer
 * Handles database operations for customer booking activity updates
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerBookingActivityDAL extends BaseDAL
{
    /**
     * Get distinct CRN and order_id pairs from PAX within date window
     */
    public function getPaxPairsByDateWindow($from, $to)
    {
        $query = "
            SELECT DISTINCT crn, order_id
            FROM wpk4_backend_travel_booking_pax
            WHERE order_date BETWEEN :from AND :to
            AND crn IS NOT NULL AND crn <> ''
            AND order_id IS NOT NULL AND order_id <> ''
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get bookings by order IDs (chunked)
     */
    public function getBookingsByOrderIds($orderIds, $hasPaymentStatus = false)
    {
        if (empty($orderIds)) {
            return [];
        }

        $select = "order_id, order_type, t_type, trip_code, total_pax, total_amount, travel_date, return_date, order_date";
        if ($hasPaymentStatus) {
            $select .= ", payment_status";
        }

        $chunks = array_chunk($orderIds, 1000);
        $results = [];
        
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "SELECT {$select} FROM wpk4_backend_travel_bookings WHERE order_id IN ({$placeholders})";
            $results = array_merge($results, $this->query($query, $chunk));
        }
        
        return $results;
    }

    /**
     * Get GA4 UTM data by order IDs (chunked)
     */
    public function getGA4DataByOrderIds($orderIds)
    {
        if (empty($orderIds)) {
            return [];
        }

        $chunks = array_chunk($orderIds, 1000);
        $results = [];
        
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "
                SELECT
                    final_order_id,
                    first_user_campaign,
                    first_user_source,
                    first_user_source_medium,
                    first_user_content,
                    source
                FROM wpk4_backend_ga4_pax_source
                WHERE final_order_id IN ({$placeholders})
            ";
            $results = array_merge($results, $this->query($query, $chunk));
        }
        
        return $results;
    }

    /**
     * Check if order_id already exists in activity table
     */
    public function orderIdExists($orderId)
    {
        $query = "
            SELECT 1 FROM wpk4_backend_customer_booking_activity
            WHERE order_id = :order_id
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result !== false;
    }

    /**
     * Insert booking activity record
     */
    public function insertBookingActivity($data)
    {
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $columns[] = "`{$key}`";
            $values[] = ":{$key}";
            $params[$key] = $value;
        }
        
        $query = "
            INSERT INTO wpk4_backend_customer_booking_activity
            (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Get activity table columns
     */
    public function getActivityColumns()
    {
        $query = "SHOW COLUMNS FROM wpk4_backend_customer_booking_activity";
        $results = $this->query($query);
        return array_column($results, 'Field');
    }

    /**
     * Get bookings table columns
     */
    public function getBookingsColumns()
    {
        $query = "SHOW COLUMNS FROM wpk4_backend_travel_bookings";
        $results = $this->query($query);
        return array_column($results, 'Field');
    }
}

