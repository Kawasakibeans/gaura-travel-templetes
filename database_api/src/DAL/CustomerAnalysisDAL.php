<?php
/**
 * Customer Analysis Data Access Layer
 * Handles database operations for customer analytics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerAnalysisDAL extends BaseDAL
{
    /**
     * Build CRN set for date range (union of activity/call/booking)
     */
    public function buildCRNUnion(string $startFull, string $endFull): void
    {
        try {
            // Drop table if exists (for cleanup)
            $this->execute("DROP TEMPORARY TABLE IF EXISTS crn_in_range");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }
        
        // Create temporary table with utf8mb4 charset
        $this->execute("CREATE TEMPORARY TABLE crn_in_range (crn VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY) ENGINE=MEMORY");
        
        $sql = "
            INSERT IGNORE INTO crn_in_range (crn)
            SELECT DISTINCT CAST(crn AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            FROM wpk4_backend_customer_website_activity
            WHERE activity_date BETWEEN :start1 AND :end1
            UNION DISTINCT
            SELECT DISTINCT CAST(crn AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            FROM wpk4_backend_customer_call_activity
            WHERE call_date BETWEEN :start2 AND :end2
            UNION DISTINCT
            SELECT DISTINCT CAST(crn AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            FROM wpk4_backend_customer_booking_activity
            WHERE order_date >= :start3 AND order_date < :end3
        ";
        
        $this->execute($sql, [
            ':start1' => $startFull,
            ':end1' => $endFull,
            ':start2' => $startFull,
            ':end2' => $endFull,
            ':start3' => $startFull,
            ':end3' => $endFull
        ]);
    }

    /**
     * Get customers from history table
     */
    public function getCustomersFromHistory(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                ci.crn,
                ci.fname, ci.lname, ci.email, ci.phone, ci.gender, ci.dob,
                cp.first_utm_source,
                cp.first_utm_final_source,
                cp.persona,
                cp.traits_json,
                cp.create_date,
                cp.last_updated_date
            FROM (
                SELECT h.crn, MAX(h.last_updated_date) AS last_ud
                FROM wpk4_backend_customer_profile_hst h
                JOIN crn_in_range r ON BINARY r.crn = BINARY h.crn
                WHERE h.last_updated_date BETWEEN :start AND :end
                GROUP BY h.crn
            ) u
            JOIN wpk4_backend_customer_profile_hst cp
              ON BINARY cp.crn = BINARY u.crn AND cp.last_updated_date = u.last_ud
            JOIN wpk4_backend_customer_info ci
              ON BINARY ci.crn = BINARY u.crn
        ";
        
        return $this->query($sql, [
            ':start' => $startDate,
            ':end' => $endDate
        ]);
    }

    /**
     * Get activities in range
     */
    public function getActivities(string $startFull, string $endFull): array
    {
        $sql = "
            SELECT a.crn, a.activity_type, a.activity_date
            FROM wpk4_backend_customer_website_activity a
            JOIN crn_in_range r ON BINARY r.crn = BINARY a.crn
            WHERE a.activity_date BETWEEN :start AND :end
        ";
        
        return $this->query($sql, [
            ':start' => $startFull,
            ':end' => $endFull
        ]);
    }

    /**
     * Get calls in range
     */
    public function getCalls(string $startFull, string $endFull): array
    {
        $sql = "
            SELECT c.crn, c.call_date
            FROM wpk4_backend_customer_call_activity c
            JOIN crn_in_range r ON BINARY r.crn = BINARY c.crn
            WHERE c.call_date BETWEEN :start AND :end
        ";
        
        return $this->query($sql, [
            ':start' => $startFull,
            ':end' => $endFull
        ]);
    }

    /**
     * Get bookings in range
     */
    public function getBookings(string $startFull, string $endFull): array
    {
        $sql = "
            SELECT b.crn, b.order_id, b.order_date, b.departure, b.arrival, 
                   b.total_pax, b.total_amount, b.utm_final_source, b.airlines
            FROM wpk4_backend_customer_booking_activity b
            JOIN crn_in_range r ON BINARY r.crn = BINARY b.crn
            WHERE b.order_date >= :start AND b.order_date < :end
        ";
        
        return $this->query($sql, [
            ':start' => $startFull,
            ':end' => $endFull
        ]);
    }

    /**
     * Get last booking date per CRN (all time)
     */
    public function getLastBookingDates(): array
    {
        $sql = "
            SELECT b.crn, MAX(b.order_date) AS last_order_date
            FROM wpk4_backend_customer_booking_activity b
            JOIN crn_in_range r ON BINARY r.crn = BINARY b.crn
            GROUP BY b.crn
        ";
        
        return $this->query($sql);
    }

    /**
     * Get ad spend data
     */
    public function getAdSpend(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                `Source` AS source,
                SUM(`Spends`) AS spend,
                SUM(`Impression`) AS impressions,
                SUM(`Clicks`) AS clicks
            FROM wpk4_backend_marketing_master_data
            WHERE `Date` BETWEEN :start AND :end
            GROUP BY `Source`
        ";
        
        return $this->query($sql, [
            ':start' => $startDate,
            ':end' => $endDate
        ]);
    }
}

