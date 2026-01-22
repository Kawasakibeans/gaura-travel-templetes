<?php
namespace App\Services;

use App\DAL\LeaveRosterDAL;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

class LeaveRosterService
{
    private LeaveRosterDAL $dal;

    public function __construct()
    {
        $this->dal = new LeaveRosterDAL();
    }

    public function import(array $payload): array
    {
        $rows = $payload['rows'] ?? null;
        if (!is_array($rows) || empty($rows)) {
            throw new Exception('rows array is required', 400);
        }

        $dryRun = isset($payload['dry_run']) ? (bool)$payload['dry_run'] : true;

        $records = [];
        $preview = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);
            if (!$normalized) {
                $skipped++;
                $preview[] = [
                    'doc_no' => $row['doc_no'] ?? null,
                    'employee_code' => $row['employee_code'] ?? null,
                    'error' => 'Missing required fields',
                ];
                continue;
            }

            [$fromDate, $toDate] = $this->parseDateRange($normalized['from_raw'], $normalized['till_raw']);
            if (!$fromDate || !$toDate) {
                $skipped++;
                $preview[] = [
                    'doc_no' => $normalized['doc_no'],
                    'employee_code' => $normalized['employee_code'],
                    'error' => "Unparseable dates: From='{$normalized['from_raw']}' To='{$normalized['till_raw']}'",
                ];
                continue;
            }

            $dates = $this->expandRange($fromDate, $toDate);
            $seq = 0;

            foreach ($dates as $day) {
                $seq++;
                $record = [
                    'doc_no' => $normalized['doc_no'],
                    'employee_code' => $normalized['employee_code'],
                    'employee_name' => $normalized['employee_name'],
                    'leave_type' => $normalized['leave_type'],
                    'from_date' => $day . ' 00:00:00',
                    'from_date_value' => $normalized['from_date_value'],
                    'till_date' => $day . ' 00:00:00',
                    'till_date_value' => $normalized['till_date_value'],
                    'remarks' => $normalized['remarks'],
                    'current_status' => $normalized['current_status'],
                    'day_seq' => $seq,
                ];

                $records[] = $record;
                $preview[] = array_merge($record, [
                    'error' => null,
                ]);
            }
        }

        $inserted = 0;
        if (!$dryRun && !empty($records)) {
            $inserted = $this->dal->insertRecords($records);
        }

        return [
            'dry_run' => $dryRun,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'preview' => $preview,
        ];
    }

    private function normalizeRow(array $row): ?array
    {
        $required = ['doc_no', 'employee_code', 'employee_name', 'leave_type', 'from_date', 'till_date'];
        foreach ($required as $field) {
            if (!isset($row[$field]) || trim((string)$row[$field]) === '') {
                return null;
            }
        }

        return [
            'doc_no' => trim((string)$row['doc_no']),
            'employee_code' => trim((string)$row['employee_code']),
            'employee_name' => trim((string)$row['employee_name']),
            'leave_type' => trim((string)$row['leave_type']),
            'from_raw' => trim((string)$row['from_date']),
            'from_date_value' => isset($row['from_date_value']) && trim((string)$row['from_date_value']) !== ''
                ? trim((string)$row['from_date_value'])
                : 'FD',
            'till_raw' => trim((string)$row['till_date']),
            'till_date_value' => isset($row['till_date_value']) && trim((string)$row['till_date_value']) !== ''
                ? trim((string)$row['till_date_value'])
                : 'FD',
            'remarks' => trim((string)($row['remarks'] ?? '')),
            'current_status' => trim((string)($row['current_status'] ?? '')),
        ];
    }

    private function parseDateRange(string $fromRaw, string $toRaw): array
    {
        return [
            $this->parseDate($fromRaw),
            $this->parseDate($toRaw),
        ];
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $value = trim($value);
        $candidates = [
            'd/m/Y H:i:s',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d',
            'd-m-Y',
            'm/d/Y',
            'j/n/Y',
            'j/m/Y',
        ];

        foreach ($candidates as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function expandRange(string $from, string $to): array
    {
        $start = new DateTime($from);
        $end = new DateTime($to);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Get all leave roster approval records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        return $this->dal->getAll($limit, $offset, $filters);
    }
}


