<?php
namespace App\Services;

use App\DAL\HotfileIataDAL;
use Exception;

class HotfileIataService
{
    private HotfileIataDAL $dal;

    public function __construct()
    {
        $this->dal = new HotfileIataDAL();
    }

    public function import(array $payload): array
    {
        $records = $payload['records'] ?? null;
        if (!is_array($records) || empty($records)) {
            throw new Exception('records array is required', 400);
        }

        $addedBy = isset($payload['added_by']) && trim((string)$payload['added_by']) !== ''
            ? trim((string)$payload['added_by'])
            : 'api';

        $normalized = array_map(function (array $record) {
            return $this->normalizeRecord($record);
        }, $records);

        $inserted = $this->dal->insertRecords($normalized, $addedBy);

        return [
            'inserted' => $inserted,
            'added_by' => $addedBy,
        ];
    }

    public function list(array $filters): array
    {
        $fromDate = isset($filters['from_date']) ? $this->validateDate($filters['from_date']) : null;
        $toDate = isset($filters['to_date']) ? $this->validateDate($filters['to_date']) : null;

        if (!$fromDate || !$toDate) {
            $toDate = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime('-7 days'));
        }

        $rows = $this->dal->getRecords($fromDate, $toDate);

        $records = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($rows as $row) {
            $isMatched = ((int)($row['match_count'] ?? 0)) > 0;
            $isMatched ? $matched++ : $unmatched++;

            $records[] = array_merge($row, [
                'matched' => $isMatched,
            ]);
        }

        return [
            'summary' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'total' => $matched + $unmatched,
            ],
            'records' => $records,
        ];
    }

    private function normalizeRecord(array $record): array
    {
        $numericFields = [
            'cash_fare',
            'credit_fare',
            'gross_fare',
            'comm',
            'fare',
            'cash_tax',
            'credit_tax',
            'yq_tax',
            'yr_tax',
            'tax',
            'transaction_amount',
        ];

        foreach ($numericFields as $field) {
            if (isset($record[$field])) {
                $record[$field] = $this->toFloat($record[$field]);
            }
        }

        return $record;
    }

    private function toFloat($value): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9\.\-]/', '', $value);
            if ($clean === '' || $clean === '-' || $clean === '.') {
                return 0.0;
            }
            return (float)$clean;
        }

        return 0.0;
    }

    private function validateDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        $ts = strtotime($date);
        if ($ts === false) {
            throw new Exception('Invalid date format: ' . $date, 400);
        }

        return date('Y-m-d', $ts);
    }
}


