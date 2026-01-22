<?php
/**
 * Customer Booking Activity Service - Business Logic Layer
 * Handles customer booking activity updates
 */

namespace App\Services;

use App\DAL\CustomerBookingActivityDAL;
use Exception;

class CustomerBookingActivityService
{
    private $bookingActivityDAL;

    public function __construct()
    {
        $this->bookingActivityDAL = new CustomerBookingActivityDAL();
    }

    /**
     * Update customer booking activity for a date window
     */
    public function updateBookingActivity($from, $to)
    {
        if (empty($from) || empty($to)) {
            throw new Exception('From and To dates are required', 400);
        }

        // Get activity table columns
        $activityColumns = $this->bookingActivityDAL->getActivityColumns();
        $can = function($col) use ($activityColumns) {
            return in_array($col, $activityColumns, true);
        };

        // Get bookings table columns
        $bookColumns = $this->bookingActivityDAL->getBookingsColumns();
        $hasPaymentStatus = in_array('payment_status', $bookColumns, true);

        // Get PAX pairs
        $pairs = $this->bookingActivityDAL->getPaxPairsByDateWindow($from, $to);
        $stats = [
            'window' => ['from' => $from, 'to' => $to],
            'pax_pairs' => count($pairs),
            'orders_distinct' => 0,
            'bookings_rows' => 0,
            'ga4_rows' => 0,
            'upsert' => [
                'inserted' => 0,
                'updated' => 0,
                'missing_booking' => 0,
                'skipped_existing_order_id' => 0
            ]
        ];

        if (empty($pairs)) {
            return ['ok' => true, 'stats' => $stats];
        }

        // Extract order IDs
        $orderIds = [];
        $orderToCrn = [];
        foreach ($pairs as $p) {
            $oid = (string)$p['order_id'];
            $crn = (string)$p['crn'];
            if ($oid !== '') {
                $orderIds[$oid] = true;
            }
            if ($oid !== '' && $crn !== '' && !isset($orderToCrn[$oid])) {
                $orderToCrn[$oid] = $crn;
            }
        }
        $orderIds = array_keys($orderIds);
        $stats['orders_distinct'] = count($orderIds);

        // Get bookings
        $bookings = $this->bookingActivityDAL->getBookingsByOrderIds($orderIds, $hasPaymentStatus);
        $stats['bookings_rows'] = count($bookings);
        $bookingsByOrder = [];
        foreach ($bookings as $r) {
            $oid = (string)$r['order_id'];
            if (!isset($bookingsByOrder[$oid])) {
                $bookingsByOrder[$oid] = [];
            }
            $bookingsByOrder[$oid][] = $r;
        }

        // Get GA4 data
        $ga4Data = $this->bookingActivityDAL->getGA4DataByOrderIds($orderIds);
        $stats['ga4_rows'] = count($ga4Data);
        $ga4ByOrder = [];
        foreach ($ga4Data as $r) {
            $oid = (string)($r['final_order_id'] ?? '');
            if ($oid === '' || isset($ga4ByOrder[$oid])) {
                continue;
            }
            $ga4ByOrder[$oid] = [
                'utm_campaign' => trim($r['first_user_campaign'] ?? '') ?: null,
                'utm_source' => trim($r['first_user_source'] ?? '') ?: null,
                'utm_medium' => $this->parseMediumFromPair($r['first_user_source_medium'] ?? null),
                'utm_content' => trim($r['first_user_content'] ?? '') ?: null,
                'utm_final_source' => trim($r['source'] ?? '') ?: null
            ];
        }

        // Build rows to insert
        $rowsToWrite = [];
        foreach ($orderToCrn as $oid => $crn) {
            $bookingRows = $bookingsByOrder[$oid] ?? [];
            
            if (empty($bookingRows)) {
                $utm = $ga4ByOrder[$oid] ?? [
                    'utm_campaign' => null,
                    'utm_source' => null,
                    'utm_medium' => null,
                    'utm_content' => null,
                    'utm_final_source' => null
                ];
                
                $row = [
                    'crn' => $crn,
                    'order_id' => $oid,
                    't_type' => null,
                    'order_type' => null,
                    'travel_date' => null,
                    'return_date' => null,
                    'trip_code' => null,
                    'departure' => null,
                    'arrival' => null,
                    'airlines' => null,
                    'total_pax' => null,
                    'total_amount' => null,
                    'order_date' => null,
                    'payment_status' => null
                ];
                
                foreach ($utm as $key => $value) {
                    $row[$key] = $value;
                }
                
                $rowsToWrite[] = $row;
                $stats['upsert']['missing_booking']++;
                continue;
            }

            // Use first booking row (simplified - original has collapse logic)
            $chosen = $bookingRows[0];
            $tripCode = $chosen['trip_code'] ?? '';
            [$dep, $arr, $carrier] = $this->deriveFromTripCode($tripCode);
            $utm = $ga4ByOrder[$oid] ?? [
                'utm_campaign' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_content' => null,
                'utm_final_source' => null
            ];

            $row = [
                'crn' => $crn,
                'order_id' => $oid,
                't_type' => $chosen['t_type'] ?? null,
                'order_type' => $chosen['order_type'] ?? null,
                'travel_date' => $this->parseDateSoft($chosen['travel_date'] ?? null),
                'return_date' => $this->parseDateSoft($chosen['return_date'] ?? null),
                'trip_code' => $this->cleanTripCode($tripCode) ?: null,
                'departure' => $dep ?: null,
                'arrival' => $arr ?: null,
                'airlines' => $carrier ?: null,
                'total_pax' => isset($chosen['total_pax']) ? (int)$chosen['total_pax'] : null,
                'total_amount' => isset($chosen['total_amount']) ? (string)$chosen['total_amount'] : null,
                'order_date' => $this->parseDateSoft($chosen['order_date'] ?? null),
                'payment_status' => $hasPaymentStatus ? ($chosen['payment_status'] ?? null) : null
            ];
            
            foreach ($utm as $key => $value) {
                $row[$key] = $value;
            }
            
            $rowsToWrite[] = $row;
        }

        // Insert rows (skip if order_id exists)
        foreach ($rowsToWrite as $row) {
            $oid = $row['order_id'] ?? '';
            if ($oid === '') {
                continue;
            }

            if ($this->bookingActivityDAL->orderIdExists($oid)) {
                $stats['upsert']['skipped_existing_order_id']++;
                continue;
            }

            // Filter columns that exist
            $filteredRow = [];
            foreach ($row as $key => $value) {
                if ($can($key)) {
                    $filteredRow[$key] = $value;
                }
            }

            if (!empty($filteredRow)) {
                $this->bookingActivityDAL->insertBookingActivity($filteredRow);
                $stats['upsert']['inserted']++;
            }
        }

        return [
            'ok' => true,
            'stats' => $stats,
            'activity_columns_used' => array_filter($activityColumns, $can)
        ];
    }

    // Helper methods
    private function parseDateSoft($x)
    {
        if ($x === null || $x === '') {
            return null;
        }
        $t = strtotime((string)$x);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private function cleanTripCode($tc)
    {
        $s = is_string($tc) ? strtoupper($tc) : strtoupper((string)$tc);
        $s = preg_replace('/[^A-Z0-9\-]+/', '', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-');
    }

    private function deriveFromTripCode($tc)
    {
        $s = $this->cleanTripCode($tc);
        $dep = '';
        $arr = '';
        $carrier = '';
        
        if (strlen($s) >= 10 && isset($s[3]) && $s[3] === '-' && isset($s[7]) && $s[7] === '-') {
            $dep = substr($s, 0, 3);
            $arr = substr($s, 4, 3);
            $carrier = substr($s, 8, 2);
        } else {
            $parts = explode('-', $s);
            if (count($parts) >= 1) {
                $dep = substr($parts[0], 0, 3);
            }
            if (count($parts) >= 2) {
                $arr = substr($parts[1], 0, 3);
            }
            if (count($parts) >= 3) {
                $carrier = substr($parts[2], 0, 2);
            }
        }
        
        return [$dep, $arr, $carrier];
    }

    private function parseMediumFromPair($pair)
    {
        if (!$pair) {
            return null;
        }
        $s = trim((string)$pair);
        $s = str_replace(' / ', '/', $s);
        $s = preg_replace('#\s*/\s*#', '/', $s);
        $parts = explode('/', $s, 3);
        return isset($parts[1]) && $parts[1] !== '' ? trim($parts[1]) : null;
    }
}

