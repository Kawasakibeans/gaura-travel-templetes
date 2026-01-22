<?php
/**
 * Customer Profile Data Access Layer
 * Handles database operations for customer profile aggregation
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerProfileDAL extends BaseDAL
{
    /**
     * Get distinct CRNs with activity in date window
     */
    public function getActiveCrnsInWindow($from, $to)
    {
        // Create temp table approach would be better, but for API we'll use UNION
        $query = "
            SELECT DISTINCT crn FROM (
                SELECT DISTINCT crn FROM wpk4_backend_customer_website_activity
                WHERE activity_date BETWEEN :from AND :to AND crn IS NOT NULL AND crn <> ''
                UNION
                SELECT DISTINCT crn FROM wpk4_backend_customer_booking_activity
                WHERE order_date BETWEEN :from AND :to AND crn IS NOT NULL AND crn <> ''
                UNION
                SELECT DISTINCT crn FROM wpk4_backend_customer_call_activity
                WHERE TIMESTAMP(call_date, call_time) BETWEEN :from AND :to AND crn IS NOT NULL AND crn <> ''
                UNION
                SELECT DISTINCT crn FROM wpk4_quote
                WHERE quoted_at BETWEEN :from AND :to AND crn IS NOT NULL AND crn <> ''
                UNION
                SELECT DISTINCT crn FROM wpk4_backend_customer_attribution_sessions
                WHERE session_start BETWEEN :from AND :to AND crn IS NOT NULL AND crn <> ''
            ) AS combined_crns
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get website activity aggregates by CRN
     */
    public function getWebsiteActivityAggregates($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   MIN(activity_date) AS first_activity,
                   MAX(activity_date) AS last_activity,
                   SUM(CASE WHEN activity_type = 'login' THEN 1 ELSE 0 END) AS total_logins,
                   SUM(CASE WHEN activity_type = 'search' THEN 1 ELSE 0 END) AS total_search,
                   SUM(CASE WHEN activity_type = 'checkout' THEN 1 ELSE 0 END) AS total_checkout
            FROM wpk4_backend_customer_website_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get booking activity aggregates by CRN
     */
    public function getBookingActivityAggregates($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   COUNT(DISTINCT order_id) AS total_bookings,
                   SUM(COALESCE(total_amount, 0)) AS total_value,
                   MIN(order_date) AS first_booking,
                   MAX(order_date) AS last_booking,
                   MIN(travel_date) AS first_travel,
                   MAX(travel_date) AS last_travel,
                   SUM(CASE WHEN UPPER(order_type) = 'GDS' THEN 1 ELSE 0 END) AS fit,
                   SUM(CASE WHEN UPPER(order_type) = 'WPT' THEN 1 ELSE 0 END) AS gdeals
            FROM wpk4_backend_customer_booking_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get preferred route by CRN
     */
    public function getPreferredRoute($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   CONCAT(UPPER(COALESCE(departure, '')), '-', UPPER(COALESCE(arrival, ''))) AS route,
                   COUNT(*) AS c
            FROM wpk4_backend_customer_booking_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn, route
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get preferred airline by CRN
     */
    public function getPreferredAirline($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   UPPER(COALESCE(airlines, '')) AS airlines,
                   COUNT(*) AS c
            FROM wpk4_backend_customer_booking_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn, airlines
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get preferred month by CRN
     */
    public function getPreferredMonth($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   DATE_FORMAT(travel_date, '%M') AS mname,
                   COUNT(*) AS c
            FROM wpk4_backend_customer_booking_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn, mname
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get call activity aggregates by CRN
     */
    public function getCallActivityAggregates($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   SUM(CASE WHEN appl = 'GTIB' THEN 1 ELSE 0 END) AS total_gtib_calls,
                   SUM(CASE WHEN appl <> 'GTIB' THEN 1 ELSE 0 END) AS total_non_gtib_calls,
                   MIN(TIMESTAMP(COALESCE(call_date, '1970-01-01'), COALESCE(call_time, '00:00:00'))) AS first_call_dt,
                   MAX(TIMESTAMP(COALESCE(call_date, '1970-01-01'), COALESCE(call_time, '00:00:00'))) AS last_call_dt
            FROM wpk4_backend_customer_call_activity
            WHERE crn IN ({$placeholders})
            GROUP BY crn
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get quote aggregates by CRN
     */
    public function getQuoteAggregates($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   COUNT(*) AS total_quotes,
                   MIN(quoted_at) AS first_quote_dt,
                   MAX(quoted_at) AS last_quote_dt
            FROM wpk4_quote
            WHERE crn IN ({$placeholders})
            GROUP BY crn
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get UTM sources from bookings by CRN
     */
    public function getBookingUTMSources($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn, order_date AS dt,
                   NULLIF(utm_campaign, '') AS utm_campaign,
                   NULLIF(utm_source, '') AS utm_source,
                   NULLIF(utm_medium, '') AS utm_medium,
                   NULLIF(utm_final_source, '') AS utm_final_source
            FROM wpk4_backend_customer_booking_activity
            WHERE crn IN ({$placeholders})
        ";
        return $this->query($query, $crns);
    }

    /**
     * Get UTM sources from attribution sessions by CRN
     */
    public function getAttributionUTMSources($crns)
    {
        if (empty($crns)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($crns), '?'));
        $query = "
            SELECT crn,
                   COALESCE(session_start, created_at) AS dt,
                   NULLIF(utm_campaign, '') AS utm_campaign,
                   NULLIF(utm_source, '') AS utm_source,
                   NULLIF(utm_medium, '') AS utm_medium
            FROM wpk4_backend_customer_attribution_sessions
            WHERE crn IN ({$placeholders})
        ";
        return $this->query($query, $crns);
    }

    /**
     * Check if profile exists for CRN
     */
    public function profileExists($crn)
    {
        $query = "SELECT 1 FROM wpk4_backend_customer_profile WHERE crn = :crn LIMIT 1";
        $result = $this->queryOne($query, ['crn' => $crn]);
        return $result !== false;
    }

    /**
     * Insert customer profile
     */
    public function insertProfile($data)
    {
        $query = "
            INSERT INTO wpk4_backend_customer_profile
            (crn, total_logins, total_search, total_checkout,
             total_bookings, fit, gdeals, total_value,
             prefered_route, preferred_airlines, prefered_month,
             first_utm_campaign, first_utm_source, first_utm_medium, first_utm_final_source,
             last_utm_campaign, last_utm_source, last_utm_medium, last_utm_final_source,
             first_register_date, last_activity_date,
             first_booking_date, last_booking_date,
             first_travel_date, last_travel_date,
             cutomer_tier, booking_frequency,
             booking_score, marketing_score, orm_score,
             total_gtib_calls, total_non_gtib_calls, first_call_date, last_call_date,
             total_quotes, first_quote_date, last_quote_date,
             create_date, last_updated_date)
            VALUES
            (:crn, :total_logins, :total_search, :total_checkout,
             :total_bookings, :fit, :gdeals, :total_value,
             :prefered_route, :preferred_airlines, :prefered_month,
             :first_utm_campaign, :first_utm_source, :first_utm_medium, :first_utm_final_source,
             :last_utm_campaign, :last_utm_source, :last_utm_medium, :last_utm_final_source,
             :first_register_date, :last_activity_date,
             :first_booking_date, :last_booking_date,
             :first_travel_date, :last_travel_date,
             :cutomer_tier, :booking_frequency,
             :booking_score, :marketing_score, :orm_score,
             :total_gtib_calls, :total_non_gtib_calls, :first_call_date, :last_call_date,
             :total_quotes, :first_quote_date, :last_quote_date,
             :create_date, :last_updated_date)
        ";
        
        return $this->execute($query, $data);
    }

    /**
     * Update customer profile
     */
    public function updateProfile($crn, $data)
    {
        $query = "
            UPDATE wpk4_backend_customer_profile
            SET total_logins = :total_logins,
                total_search = :total_search,
                total_checkout = :total_checkout,
                total_bookings = :total_bookings,
                fit = :fit,
                gdeals = :gdeals,
                total_value = :total_value,
                prefered_route = :prefered_route,
                preferred_airlines = :preferred_airlines,
                prefered_month = :prefered_month,
                first_utm_campaign = :first_utm_campaign,
                first_utm_source = :first_utm_source,
                first_utm_medium = :first_utm_medium,
                first_utm_final_source = :first_utm_final_source,
                last_utm_campaign = :last_utm_campaign,
                last_utm_source = :last_utm_source,
                last_utm_medium = :last_utm_medium,
                last_utm_final_source = :last_utm_final_source,
                first_register_date = :first_register_date,
                last_activity_date = :last_activity_date,
                first_booking_date = :first_booking_date,
                last_booking_date = :last_booking_date,
                first_travel_date = :first_travel_date,
                last_travel_date = :last_travel_date,
                cutomer_tier = :cutomer_tier,
                booking_frequency = :booking_frequency,
                booking_score = COALESCE(booking_score, 0),
                marketing_score = COALESCE(marketing_score, 0),
                orm_score = COALESCE(orm_score, 0),
                total_gtib_calls = :total_gtib_calls,
                total_non_gtib_calls = :total_non_gtib_calls,
                first_call_date = :first_call_date,
                last_call_date = :last_call_date,
                total_quotes = :total_quotes,
                first_quote_date = :first_quote_date,
                last_quote_date = :last_quote_date,
                create_date = :create_date,
                last_updated_date = :last_updated_date
            WHERE crn = :crn
        ";
        
        $data['crn'] = $crn;
        return $this->execute($query, $data);
    }
}

