<?php
/**
 * Duplicate Passenger Bookings Data Access Layer
 * Handles all database operations for duplicate passenger bookings
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class DupePaxDAL extends BaseDAL
{
    /**
     * Get duplicate passenger bookings
     * 
     * @param string $odFrom Order date from (Y-m-d format)
     * @param string $odTo Order date to (Y-m-d format)
     * @param string $pmFrom Payment modified from (Y-m-d format)
     * @param string $pmTo Payment modified to (Y-m-d format)
     * @return array Array of duplicate booking groups
     */
    public function getDuplicateBookings($odFrom, $odTo, $pmFrom, $pmTo)
    {
        $params = [
            'od_from' => $odFrom . ' 00:00:00',
            'od_to' => $odTo . ' 23:59:59',
            'pm_from' => $pmFrom . ' 00:00:00',
            'pm_to' => $pmTo . ' 23:59:59'
        ];

        $sql = "
            SELECT
                b.order_id           AS bookingid,
                b.travel_date        AS travel_date,
                b.trip_code          AS trip_code,
                b.order_date         AS order_date,
                b.payment_modified   AS payment_modified,
                p.fname              AS fname,
                p.lname              AS lname,
                p.email_pax          AS email_pax
            FROM wpk4_backend_travel_bookings b
            JOIN wpk4_backend_travel_booking_pax p
              ON b.order_id    = p.order_id
             AND b.co_order_id = p.co_order_id
             AND b.product_id  = p.product_id
            WHERE p.email_pax != ''
              AND b.order_date BETWEEN :od_from AND :od_to
              AND b.payment_modified BETWEEN :pm_from AND :pm_to
            ORDER BY b.auto_id DESC
        ";

        $results = $this->query($sql, $params);

        // Group duplicates: Pax Name + trip_code + travel_date with >=2 distinct order_ids
        $groups = [];
        foreach ($results as $r) {
            $norm_name = $this->normalizeName($r['fname'] ?? '', $r['lname'] ?? '');
            $trip_code = (string)($r['trip_code'] ?? '');
            $travel_date = (string)($r['travel_date'] ?? '');
            $key = $norm_name . '|' . mb_strtolower($trip_code, 'UTF-8') . '|' . $travel_date;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'pax_name_display' => trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')),
                    'trip_code' => $trip_code,
                    'travel_date' => $travel_date,
                    'order_ids' => [],
                    'items' => [],
                ];
            }
            $groups[$key]['order_ids'][$r['bookingid']] = true;
            $groups[$key]['items'][] = [
                'bookingid' => $r['bookingid'],
                'email_pax' => $r['email_pax'] ?? '',
                'fname' => $r['fname'] ?? '',
                'lname' => $r['lname'] ?? '',
                'order_date' => $r['order_date'] ?? '',
                'payment_modified' => $r['payment_modified'] ?? '',
            ];
        }

        // Filter to only groups with 2+ distinct order_ids
        $dupes = [];
        foreach ($groups as $g) {
            if (count($g['order_ids']) >= 2) {
                $g['distinct_orders'] = array_keys($g['order_ids']);
                unset($g['order_ids']);
                $dupes[] = $g;
            }
        }

        // Sort by travel_date desc, then pax name asc
        usort($dupes, function($a, $b) {
            $ad = strtotime($a['travel_date'] ?? '1970-01-01');
            $bd = strtotime($b['travel_date'] ?? '1970-01-01');
            if ($ad === $bd) {
                return strcmp(
                    mb_strtolower($a['pax_name_display'] ?? '', 'UTF-8'),
                    mb_strtolower($b['pax_name_display'] ?? '', 'UTF-8')
                );
            }
            return $bd <=> $ad;
        });

        return $dupes;
    }

    /**
     * Normalize passenger name for comparison
     * 
     * @param string $fname First name
     * @param string $lname Last name
     * @return string Normalized full name
     */
    private function normalizeName($fname, $lname)
    {
        $full = trim(preg_replace('/\s+/', ' ', ($fname ?? '') . ' ' . ($lname ?? '')));
        return mb_strtolower($full, 'UTF-8');
    }
}

