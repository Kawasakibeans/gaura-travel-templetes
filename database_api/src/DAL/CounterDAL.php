<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class CounterDAL extends BaseDAL
{
    /**
     * Get trip summary
     */
    public function getTripSummary(): array
    {
        $sql = "
            SELECT DISTINCT
                pm.meta_value AS Product_ID,
                p.post_title,
                pm1.meta_value AS arrival_date,
                COALESCE(SUM(CAST(pm2.meta_value AS UNSIGNED)), 0) AS Total_Pax,
                COALESCE(SUM(CAST(pm6.meta_value AS UNSIGNED)), 0) AS Partial_Paid,
                COALESCE(SUM(CAST(pm7.meta_value AS UNSIGNED)), 0) AS Booked,
                COALESCE(SUM(CAST(pm9.meta_value AS UNSIGNED)), 0) AS Cancelled,
                COALESCE(SUM(CAST(pm11.meta_value AS UNSIGNED)), 0) AS Refund
            FROM wpk4_postmeta pm
            LEFT JOIN wpk4_posts p ON pm.meta_value = p.ID
            LEFT JOIN wpk4_postmeta pm1 ON pm1.post_id = pm.post_id AND pm1.meta_key = 'wp_travel_arrival_date'
            LEFT JOIN wpk4_postmeta pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = 'wp_travel_pax'
            LEFT JOIN wpk4_postmeta pm4 ON pm4.post_id = pm.post_id AND pm4.meta_key = 'wp_travel_payment_status' AND pm4.meta_value = 'partially_paid'
            LEFT JOIN wpk4_postmeta pm6 ON pm6.post_id = pm4.post_id AND pm6.meta_key = 'wp_travel_pax'
            LEFT JOIN wpk4_postmeta pm5 ON pm5.post_id = pm.post_id AND pm5.meta_key = 'wp_travel_payment_status' AND pm5.meta_value = 'paid'
            LEFT JOIN wpk4_postmeta pm7 ON pm7.post_id = pm5.post_id AND pm7.meta_key = 'wp_travel_pax'
            LEFT JOIN wpk4_postmeta pm8 ON pm8.post_id = pm.post_id AND pm8.meta_key = 'wp_travel_payment_status' AND pm8.meta_value = 'canceled'
            LEFT JOIN wpk4_postmeta pm9 ON pm9.post_id = pm8.post_id AND pm9.meta_key = 'wp_travel_pax'
            LEFT JOIN wpk4_postmeta pm10 ON pm10.post_id = pm.post_id AND pm10.meta_key = 'wp_travel_payment_status' AND pm10.meta_value = 'refund'
            LEFT JOIN wpk4_postmeta pm11 ON pm11.post_id = pm10.post_id AND pm11.meta_key = 'wp_travel_pax'
            WHERE pm.meta_key = 'wp_travel_post_id'
            GROUP BY pm1.meta_value, Product_ID, p.post_title
            ORDER BY pm1.meta_value ASC
        ";
        
        return $this->query($sql);
    }
}

