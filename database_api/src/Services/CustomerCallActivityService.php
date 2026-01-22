<?php
/**
 * Customer Call Activity Service - Business Logic Layer
 * Handles customer call activity updates
 */

namespace App\Services;

use App\DAL\CustomerCallActivityDAL;
use Exception;

class CustomerCallActivityService
{
    private $callActivityDAL;

    public function __construct()
    {
        $this->callActivityDAL = new CustomerCallActivityDAL();
    }

    /**
     * Update customer call activity for a date window
     */
    public function updateCallActivity($from, $to)
    {
        if (empty($from) || empty($to)) {
            throw new Exception('From and To dates are required', 400);
        }

        // Get column mappings
        $inboundColumns = $this->callActivityDAL->getInboundColumns();
        $callActivityColumns = $this->callActivityDAL->getCallActivityColumns();
        $hasCols = array_flip($callActivityColumns);

        // Get customer phone map
        $customerRows = $this->callActivityDAL->getCustomerPhoneMap();
        $exactMap = [];
        $last8Counts = [];
        $last8First = [];
        
        foreach ($customerRows as $r) {
            $pLocal = $this->normalizePhoneToLocal($r['phone']);
            if (!$pLocal) continue;
            
            if (!isset($exactMap[$pLocal])) {
                $exactMap[$pLocal] = (string)$r['crn'];
            }
            
            $k8 = (strlen($pLocal) >= 8) ? substr($pLocal, -8) : $pLocal;
            if ($k8 !== '') {
                $last8Counts[$k8] = ($last8Counts[$k8] ?? 0) + 1;
                if (!isset($last8First[$k8])) {
                    $last8First[$k8] = (string)$r['crn'];
                }
            }
        }
        
        $suffixMap = [];
        foreach ($last8Counts as $k => $c) {
            if ($c === 1) {
                $suffixMap[$k] = $last8First[$k];
            }
        }

        // Get inbound calls
        $inboundCalls = $this->callActivityDAL->getInboundCallsByDateWindow($from, $to, $inboundColumns);
        
        $stats = [
            'window' => ['from' => $from, 'to' => $to],
            'customers' => count($customerRows),
            'inbound_rows' => count($inboundCalls),
            'matched_exact' => 0,
            'matched_suffix' => 0,
            'unmatched' => 0,
            'inserted' => 0,
            'skipped_record_id' => 0
        ];

        $rowsOut = [];
        foreach ($inboundCalls as $r) {
            $rid = $r['record_id'] ?? null;
            if ($rid === null || $rid === '') {
                $stats['skipped_record_id']++;
                continue;
            }

            $fullPhoneDigits = preg_replace('/\D+/', '', 
                (string)($r['ani_country_id'] ?? '') . 
                (string)($r['ani_acode'] ?? '') . 
                (string)($r['ani_phone'] ?? '')
            );
            $local = $this->normalizePhoneToLocal($fullPhoneDigits);
            $crn = $local ? ($exactMap[$local] ?? null) : null;
            $wasExact = ($crn !== null);

            if ($crn === null && $local !== null) {
                $k8 = (strlen($local) >= 8) ? substr($local, -8) : $local;
                if ($k8 !== '' && isset($suffixMap[$k8])) {
                    $crn = $suffixMap[$k8];
                }
            }

            if ($crn === null) {
                $stats['unmatched']++;
                continue;
            }
            
            if ($wasExact) {
                $stats['matched_exact']++;
            } else {
                $stats['matched_suffix']++;
            }

            $duration = $this->sumSecs(
                $r['time_holding'] ?? 0,
                $r['time_connect'] ?? 0,
                $r['time_acwork'] ?? 0
            );

            $rowsOut[] = [
                'record_id' => $rid,
                'crn' => $crn,
                'phone' => $fullPhoneDigits ?: null,
                'call_date' => $r['call_date'] ? date('Y-m-d', strtotime($r['call_date'])) : null,
                'call_time' => !empty($r['call_time']) ? date('H:i:s', strtotime($r['call_time'])) : null,
                'appl' => $r['appl'] ?? null,
                'duration' => $duration,
                'tsr' => $r['tsr'] ?? null
            ];
        }

        // Filter to only columns that exist
        $useCols = array_values(array_filter([
            isset($hasCols['record_id']) ? 'record_id' : null,
            isset($hasCols['crn']) ? 'crn' : null,
            isset($hasCols['phone']) ? 'phone' : null,
            isset($hasCols['call_date']) ? 'call_date' : null,
            isset($hasCols['call_time']) ? 'call_time' : null,
            isset($hasCols['appl']) ? 'appl' : null,
            isset($hasCols['duration']) ? 'duration' : null,
            isset($hasCols['tsr']) ? 'tsr' : null
        ]));

        if (empty($useCols) || empty($rowsOut)) {
            return [
                'ok' => true,
                'stats' => $stats,
                'notes' => ['info' => 'No eligible rows to insert']
            ];
        }

        // Get existing record_ids
        $allRids = array_values(array_unique(array_column($rowsOut, 'record_id')));
        $existing = $this->callActivityDAL->getExistingRecordIds($allRids);
        $existingSet = array_flip($existing);

        // Filter to new rows only
        $toInsert = [];
        foreach ($rowsOut as $row) {
            $rid = (string)$row['record_id'];
            if (!isset($existingSet[$rid])) {
                $toInsert[] = $row;
            }
        }

        // Bulk insert
        if (!empty($toInsert)) {
            $stats['inserted'] = $this->callActivityDAL->bulkInsertCallActivity($toInsert, $useCols);
        }

        return [
            'ok' => true,
            'stats' => $stats,
            'notes' => [
                'window' => ['from' => $from, 'to' => $to],
                'duration' => 'time_holding + time_connect + time_acwork (seconds)',
                'phone_store' => 'digits of ani_country_id + ani_acode + ani_phone',
                'crn_match' => 'normalize to local, exact → unique last8',
                'upsert' => 'SKIP if record_id exists; no updates performed'
            ]
        ];
    }

    private function normalizePhoneToLocal($s)
    {
        if ($s === null) {
            return null;
        }
        $d = preg_replace('/\D+/', '', (string)$s);
        if ($d === '') {
            return null;
        }
        if (strpos($d, '61') === 0) {
            $d = substr($d, 2);
        } elseif ($d[0] === '0') {
            $d = substr($d, 1);
        }
        return $d !== '' ? $d : null;
    }

    private function sumSecs(...$vals)
    {
        $t = 0;
        foreach ($vals as $v) {
            if ($v === null) continue;
            $n = is_numeric($v) ? (float)$v : (is_string($v) ? (float)preg_replace('/[^\d.]+/', '', $v) : 0);
            $t += $n;
        }
        return (int)round($t);
    }
}

