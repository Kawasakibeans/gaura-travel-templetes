<?php
/**
 * Customer Website Activity Service - Business Logic Layer
 * Handles customer website activity updates
 */

namespace App\Services;

use App\DAL\CustomerWebsiteActivityDAL;
use Exception;

class CustomerWebsiteActivityService
{
    private $websiteActivityDAL;

    public function __construct()
    {
        $this->websiteActivityDAL = new CustomerWebsiteActivityDAL();
    }

    /**
     * Update customer website activity for a date window
     */
    public function updateWebsiteActivity($from, $to)
    {
        if (empty($from) || empty($to)) {
            throw new Exception('From and To dates are required', 400);
        }

        // Get event log records
        $elogRows = $this->websiteActivityDAL->getEventLogByDateWindow($from, $to);
        
        // Extract emails
        $emailsSeen = [];
        foreach ($elogRows as $r) {
            $e = $this->getCleanEmailFromRow($r);
            if ($e !== '') {
                $emailsSeen[strtolower($e)] = true;
            }
        }
        
        // Get CRN mapping
        $emailToCrn = [];
        if (!empty($emailsSeen)) {
            $emails = array_keys($emailsSeen);
            $emailToCrn = $this->websiteActivityDAL->getCrnByEmail($emails);
        }

        $stats = [
            'window' => ['from' => $from, 'to' => $to],
            'elog_count' => count($elogRows),
            'classified' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'skipped_activity_id' => 0
        ];

        $records = [];
        
        foreach ($elogRows as $row) {
            $activity = $this->classifyActivity($row);
            if (!$activity) {
                $stats['skipped']++;
                continue;
            }
            
            $stats['classified']++;
            $email = $this->getCleanEmailFromRow($row);
            $crn = ($email !== '') ? ($emailToCrn[strtolower($email)] ?? null) : null;
            
            $records[] = [
                'auto_id' => null,
                'crn' => $crn,
                'activity_type' => $activity['type'],
                'activity_id' => $row['auto_id'] ?? null,
                'activity_date' => $this->toMysqlDatetime($row['added_on'] ?? null) ?: date('Y-m-d H:i:s'),
                'departure' => $activity['departure'] ?? null,
                'arrival' => $activity['arrival'] ?? null,
                'travel_date' => $activity['travel_date'] ?? null,
                'return_date' => $activity['return_date'] ?? null,
                'related_product_id' => $activity['related_product_id'] ?? null,
                'related_order_id' => $activity['related_order_id'] ?? null
            ];
        }

        // Upsert records
        foreach ($records as $row) {
            if (!empty($row['activity_id'])) {
                if ($this->websiteActivityDAL->activityIdExists($row['activity_id'])) {
                    $stats['skipped_activity_id']++;
                    continue;
                }
            }
            
            $exists = null;
            if (empty($row['activity_id'])) {
                $exists = $this->websiteActivityDAL->activityExists(
                    $row['crn'],
                    $row['activity_type'],
                    $row['activity_date']
                );
            }
            
            if ($exists) {
                $this->websiteActivityDAL->updateActivity(
                    $row['crn'],
                    $row['activity_type'],
                    $row['activity_date'],
                    $row
                );
                $stats['updated']++;
            } else {
                $this->websiteActivityDAL->insertActivity($row);
                $stats['inserted']++;
            }
        }

        // Update sessions CRN
        $sessionsResult = $this->websiteActivityDAL->updateSessionsCrnFromEventLog($from, $to);

        return [
            'ok' => true,
            'stats' => array_merge($stats, [
                'sessions_crn_update' => $sessionsResult
            ]),
            'notes' => [
                'source' => 'wpk4_customer_event_log',
                'crn_lookup' => 'website_activity via email→customer_info'
            ]
        ];
    }

    private function classifyActivity($row)
    {
        $event = strtolower(trim($row['event'] ?? ''));
        $metaKey = strtolower(trim($row['meta_key'] ?? ''));
        $metaVal = (string)($row['meta_value'] ?? '');
        $page = strtolower(trim($row['page'] ?? ''));
        
        [$path, $qs] = $this->parseUrlPathAndQs($metaVal);
        
        if ($event === 'login' || $metaKey === 'login') {
            return ['type' => 'login'];
        }
        
        if ((strpos($path, 'thank-you') !== false && (isset($qs['booked']) || isset($qs['order_id'])))
            || (preg_match('#^payment(/|$)#', $path) && isset($qs['booked']))
            || (strpos($path, 'order-success') !== false)) {
            $orderId = $this->extractOrderIdFromMeta($metaVal);
            return [
                'type' => 'order',
                'related_order_id' => $orderId ?: null
            ];
        }
        
        if ($event === 'checkout') {
            [$td, $rd, $pid] = $this->extractCheckout($metaVal);
            return [
                'type' => 'checkout',
                'travel_date' => $td ? ($td . ' 00:00:00') : null,
                'return_date' => $rd ? ($rd . ' 00:00:00') : null,
                'related_product_id' => $pid ?: null
            ];
        }
        
        if ((strpos($path, 'flights/') !== false || $page === 'search results')
            && (isset($qs['depapt1']) || isset($qs['fromc']) || isset($qs['dstapt1']) || isset($qs['toc']))) {
            $dep = $this->pickAirportCode($qs, ['depapt1', 'from_code'], ['fromc']);
            $arr = $this->pickAirportCode($qs, ['dstapt1', 'to_code'], ['toc']);
            $td = $this->toMysqlDate($qs['depdate1'] ?? '');
            $rd = $this->toMysqlDate($qs['retdate1'] ?? '');
            
            return [
                'type' => 'search',
                'departure' => $dep ?: null,
                'arrival' => $arr ?: null,
                'travel_date' => $td ? ($td . ' 00:00:00') : null,
                'return_date' => $rd ? ($rd . ' 00:00:00') : null
            ];
        }
        
        return null;
    }

    private function getCleanEmailFromRow($row)
    {
        $e1 = $this->cleanEmail($row['email_id'] ?? '');
        if ($e1) return $e1;
        
        $event = strtolower(trim($row['event'] ?? ''));
        $mkey = strtolower(trim($row['meta_key'] ?? ''));
        if ($event === 'login' || $mkey === 'login') {
            $e2 = $this->cleanEmail($row['meta_value'] ?? '');
            if ($e2) return $e2;
        }
        return '';
    }

    private function cleanEmail($raw)
    {
        if ($raw === null) return '';
        $s = strtolower(trim((string)$raw));
        if ($s === '' || $s === 'nan' || $s === 'none') return '';
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
    }

    private function parseUrlPathAndQs($urlLike)
    {
        if ($urlLike === '') return ['', []];
        $u = (strpos($urlLike, '://') === false) ? ('https://dummy/' . ltrim($urlLike, '/')) : $urlLike;
        $parts = @parse_url($u);
        $path = isset($parts['path']) ? ltrim(strtolower($parts['path']), '/') : '';
        $qs = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $qs);
        $norm = [];
        foreach ($qs as $k => $v) {
            $norm[strtolower($k)] = is_array($v) ? reset($v) : $v;
        }
        return [$path, $norm];
    }

    private function extractOrderIdFromMeta($metaValue)
    {
        [, $qs] = $this->parseUrlPathAndQs($metaValue);
        $v = isset($qs['order_id']) ? preg_replace('/\D/', '', (string)$qs['order_id']) : '';
        if ($v !== '') return $v;
        if (preg_match('/(?:^|[?&])order_id=(\d+)/', $metaValue, $m)) return $m[1];
        return '';
    }

    private function extractCheckout($metaValue)
    {
        $pid = '';
        if (preg_match('/"trip_id";s:\d+:"(\d+)"/', $metaValue, $m)) $pid = $m[1];
        $arr = '';
        $dep = '';
        if (preg_match('/"arrival_date";s:\d+:"(\d{4}-\d{2}-\d{2})"/', $metaValue, $m1)) {
            $arr = $this->toMysqlDate($m1[1]);
            if (preg_match('/"departure_date";s:\d+:"(\d{4}-\d{2}-\d{2})"/', $metaValue, $m2)) {
                $dep = $this->toMysqlDate($m2[1]);
            }
        } elseif (preg_match('/"trip_start_date";s:\d+:"(\d{4}-\d{2}-\d{2})"/', $metaValue, $m3)) {
            $arr = $this->toMysqlDate($m3[1]);
        }
        return [$dep ?: null, $arr ?: null, $pid ?: null];
    }

    private function pickAirportCode($qs, $codeKeys, $nameKeys)
    {
        foreach ($codeKeys as $k) {
            if (isset($qs[$k])) {
                $c = $this->iataFromString((string)$qs[$k]);
                if ($c) return $c;
            }
        }
        foreach ($nameKeys as $k) {
            if (isset($qs[$k])) {
                $c = $this->iataFromString((string)$qs[$k]);
                if ($c) return $c;
            }
        }
        return '';
    }

    private function iataFromString($raw)
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        if (preg_match('/^[A-Za-z]{3}$/', $raw)) return strtoupper($raw);
        if (preg_match('/\(([A-Za-z]{3})\)/', $raw, $m)) return strtoupper($m[1]);
        if (preg_match('/\b[A-Z]{3}\b/', strtoupper($raw), $m)) return $m[0];
        return '';
    }

    private function toMysqlDate($s)
    {
        if ($s === null) return null;
        $s2 = trim(str_replace(['_', '/'], '-', $s));
        if ($s2 === '') return null;
        $ts = strtotime($s2);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }

    private function toMysqlDatetime($s)
    {
        if ($s === null) return null;
        $ts = strtotime($s);
        if ($ts === false) return null;
        return date('Y-m-d H:i:s', $ts);
    }
}

