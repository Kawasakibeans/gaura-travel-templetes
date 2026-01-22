<?php
/**
 * Duplicate Passenger Bookings by Email Data Access Layer
 * Handles all database operations for duplicate passenger bookings grouped by email
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class DupePaxEmailDAL extends BaseDAL
{
    /**
     * Get duplicate passenger bookings by email
     * 
     * @param string $travelFrom Travel date from (Y-m-d format)
     * @param string $travelTo Travel date to (Y-m-d format)
     * @param string $email Email filter (optional, empty string for all)
     * @return array Array of duplicate booking groups
     */
    public function getDuplicateBookingsByEmail($travelFrom, $travelTo, $email = '')
    {
        $params = [
            'travel_from' => $travelFrom . ' 00:00:00',
            'travel_to' => $travelTo . ' 23:59:59'
        ];

        $sql = "
            SELECT
                b.order_id            AS bookingid,
                b.travel_date         AS travel_date,
                b.trip_code           AS trip_code,
                b.order_date          AS order_date,
                b.payment_modified    AS payment_modified,
                p.fname               AS fname,
                p.lname               AS lname,
                p.email_pax           AS email_pax
            FROM wpk4_backend_travel_bookings b
            JOIN wpk4_backend_travel_booking_pax p
              ON b.order_id    = p.order_id
             AND b.co_order_id = p.co_order_id
             AND b.product_id  = p.product_id
            WHERE TRIM(COALESCE(p.email_pax, '')) <> ''
              AND b.travel_date BETWEEN :travel_from AND :travel_to
        ";

        // Optional email filter
        if ($email !== '') {
            $normalizedEmail = $this->normalizeEmail($email);
            if (strpos($normalizedEmail, '@') !== false) {
                // Exact match if contains '@'
                $sql .= " AND LOWER(TRIM(p.email_pax)) = :email";
                $params['email'] = $normalizedEmail;
            } else {
                // LIKE search if no '@'
                $sql .= " AND LOWER(TRIM(p.email_pax)) LIKE :email";
                $params['email'] = '%' . $normalizedEmail . '%';
            }
        }

        $sql .= " ORDER BY b.auto_id DESC";

        $results = $this->query($sql, $params);

        // Group by normalized email & keep distinct order_ids
        $groups = [];
        foreach ($results as $r) {
            $email = (string)($r['email_pax'] ?? '');
            $ekey = $this->normalizeEmail($email);
            if ($ekey === '') continue;

            if (!isset($groups[$ekey])) {
                $groups[$ekey] = [
                    'email_display' => $email,
                    'order_ids' => [],
                    'items' => [],
                ];
            }
            $groups[$ekey]['order_ids'][$r['bookingid']] = true;
            $groups[$ekey]['items'][] = [
                'bookingid' => $r['bookingid'],
                'travel_date' => $r['travel_date'] ?? '',
                'trip_code' => $r['trip_code'] ?? '',
                'order_date' => $r['order_date'] ?? '',
                'payment_modified' => $r['payment_modified'] ?? '',
                'fname' => $r['fname'] ?? '',
                'lname' => $r['lname'] ?? '',
                'email_pax' => $r['email_pax'] ?? ''
            ];
        }

        // Keep only groups with >= 2 distinct bookings
        $dupes = [];
        foreach ($groups as $g) {
            if (count($g['order_ids']) >= 2) {
                $g['distinct_orders'] = array_keys($g['order_ids']);
                unset($g['order_ids']);
                $dupes[] = $g;
            }
        }

        // Sort dupes by newest travel_date desc
        usort($dupes, function($a, $b) {
            $aMax = 0;
            $bMax = 0;
            foreach ($a['items'] as $i) {
                $aMax = max($aMax, strtotime($i['travel_date'] ?: '1970-01-01'));
            }
            foreach ($b['items'] as $i) {
                $bMax = max($bMax, strtotime($i['travel_date'] ?: '1970-01-01'));
            }
            return $bMax <=> $aMax;
        });

        return $dupes;
    }

    /**
     * Normalize email for comparison
     * 
     * @param string $email Email string
     * @return string Normalized email (lowercase, trimmed)
     */
    private function normalizeEmail($email)
    {
        $e = trim((string)$email);
        $e = mb_strtolower($e, 'UTF-8');
        return $e;
    }
}

