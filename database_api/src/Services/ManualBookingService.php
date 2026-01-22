<?php
namespace App\Services;

use App\DAL\ManualBookingDAL;
use DateTime;
use Exception;

class ManualBookingService
{
    private ManualBookingDAL $dal;

    public function __construct()
    {
        $this->dal = new ManualBookingDAL();
    }

    public function preview(array $payload): array
    {
        $passengers = $payload['passengers'] ?? null;
        if (!is_array($passengers) || empty($passengers)) {
            throw new Exception('passengers array is required', 400);
        }

        $latestOrderId = $this->dal->getLatestOrderId();
        $nextOrderId = ($latestOrderId ?? 0) + 1;
        $currentOrderId = $nextOrderId;

        $records = [];
        $duplicates = 0;
        $invalid = 0;

        foreach ($passengers as $index => $passenger) {
            try {
                $normalized = $this->normalizePassenger($passenger);
            } catch (Exception $e) {
                $invalid++;
                $records[] = [
                    'index' => $index,
                    'pnr' => $passenger['pnr'] ?? null,
                    'first_name' => $passenger['first_name'] ?? null,
                    'last_name' => $passenger['last_name'] ?? null,
                    'error' => $e->getMessage(),
                    'can_import' => false,
                    'duplicate' => false,
                ];
                continue;
            }

            $exists = $this->dal->passengerExists($normalized['pnr'], $normalized['first_name'], $normalized['last_name']);
            $canImport = !$exists;

            if ($exists) {
                $duplicates++;
            }

            $record = array_merge($normalized, [
                'index' => $index,
                'order_id' => $canImport ? $currentOrderId : null,
                'duplicate' => $exists,
                'can_import' => $canImport,
                'error' => null,
            ]);

            if ($canImport) {
                $currentOrderId++;
            }

            $records[] = $record;
        }

        return [
            'starting_order_id' => $nextOrderId,
            'records' => $records,
            'summary' => [
                'total' => count($passengers),
                'importable' => count(array_filter($records, fn($r) => $r['can_import'])),
                'duplicates' => $duplicates,
                'invalid' => $invalid,
            ],
        ];
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

        $importable = [];
        $skippedDuplicates = 0;
        $skippedInvalid = 0;

        foreach ($records as $record) {
            if (empty($record['can_import']) || empty($record['order_id'])) {
                $skippedInvalid++;
                continue;
            }

            try {
                $normalized = $this->normalizePassenger($record);
            } catch (Exception $e) {
                $skippedInvalid++;
                continue;
            }

            if ($this->dal->passengerExists($normalized['pnr'], $normalized['first_name'], $normalized['last_name'])) {
                $skippedDuplicates++;
                continue;
            }

            $importable[] = $this->prepareRecordForInsert($record, $normalized);
        }

        $inserted = 0;
        if (!empty($importable)) {
            $inserted = $this->dal->insertRecords($importable, $addedBy);
        }

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => $skippedDuplicates,
            'skipped_invalid' => $skippedInvalid,
        ];
    }

    private function normalizePassenger(array $passenger): array
    {
        $pnr = strtoupper(trim((string)($passenger['pnr'] ?? '')));
        $first = trim((string)($passenger['first_name'] ?? ''));
        $last = trim((string)($passenger['last_name'] ?? ''));

        if ($pnr === '' || $first === '' || $last === '') {
            throw new Exception('pnr, first_name, and last_name are required');
        }

        $dobRaw = $passenger['date_of_birth'] ?? '';
        $dob = $this->parseDate($dobRaw);

        return [
            'pnr' => $pnr,
            'first_name' => $first,
            'last_name' => $last,
            'salutation' => trim((string)($passenger['salutation'] ?? '')),
            'gender' => $this->normalizeGender($passenger['gender'] ?? ''),
            'dob' => $dob,
            'email' => trim((string)($passenger['email'] ?? '')),
            'total_pax' => max(1, (int)($passenger['total_pax'] ?? 1)),
            'journey_type' => $this->normalizeJourneyType($passenger['journey_type'] ?? ''),
        ];
    }

    private function prepareRecordForInsert(array $record, array $normalized): array
    {
        $orderDate = date('Y-m-d H:i:s');

        // Convert empty strings to null for date fields (database doesn't accept empty strings for date/datetime)
        $travelDate = $record['travel_date'] ?? null;
        if ($travelDate === '' || $travelDate === null) {
            $travelDate = null;
        }

        $returnDate = $record['return_date'] ?? null;
        if ($returnDate === '' || $returnDate === null) {
            $returnDate = null;
        }

        return [
            'order_id' => (int)$record['order_id'],
            'order_date' => $orderDate,
            'journey_type' => $normalized['journey_type'],
            'travel_date' => $travelDate,
            'return_date' => $returnDate,
            'total_pax' => $normalized['total_pax'],
            'salutation' => $normalized['salutation'],
            'first_name' => $normalized['first_name'],
            'last_name' => $normalized['last_name'],
            'gender' => $normalized['gender'],
            'dob' => $normalized['dob'] ?? '1970-01-01',
            'email' => $normalized['email'],
            'pnr' => $normalized['pnr'],
        ];
    }

    private function normalizeGender(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'm' || $value === 'male') {
            return 'male';
        }
        if ($value === 'f' || $value === 'female') {
            return 'female';
        }
        return '';
    }

    private function normalizeJourneyType(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'return') {
            return 'return';
        }
        return 'oneway';
    }

    private function parseDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim((string)$value);
        $value = str_replace('/', '-', $value);

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}


