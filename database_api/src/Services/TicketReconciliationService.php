<?php
/**
 * Ticket Reconciliation Service
 * Provides business logic for ticket reconciliation data, logs, and updates
 */

namespace App\Services;

use App\DAL\TicketReconciliationDAL;
use Exception;

class TicketReconciliationService
{
    private const ALLOWED_UPDATE_COLUMNS = [
        'client_status',
        'confirmed',
        'fare',
        'tax',
        'comm',
        'transaction_amount',
        'vendor',
        'document_type',
        'delete_request',
        'fare_inr',
        'tax_inr',
        'comm_inr',
        'transaction_amount_inr',
        'order_amnt',
    ];

    private TicketReconciliationDAL $dal;

    public function __construct()
    {
        $this->dal = new TicketReconciliationDAL();
    }

    /**
     * Retrieve ticket reconciliation dataset with filters
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getTickets(array $filters): array
    {
        $issueStart = $this->resolveDate($filters['issue_start_date'] ?? null, '-31 days');
        $issueEnd = $this->resolveDate($filters['issue_end_date'] ?? null, 'now');

        if ($issueStart > $issueEnd) {
            throw new Exception('issue_start_date must be before issue_end_date', 400);
        }

        $limit = $filters['limit'] ?? 500;
        $offset = $filters['offset'] ?? 0;
        $limit = is_numeric($limit) ? max(1, min(1000, (int)$limit)) : 500;
        $offset = is_numeric($offset) ? max(0, (int)$offset) : 0;

        $normalizedFilters = [
            'issue_start_date' => $issueStart,
            'issue_end_date' => $issueEnd,
            'ticket_numbers' => $this->parseMulti($filters['ticket_no'] ?? null),
            'issued_vias' => $this->parseMulti($filters['issued_via'] ?? null),
            'airlines' => $this->parseMulti($filters['airline'] ?? null),
            'order_numbers' => $this->parseMulti($filters['order_no'] ?? null),
            'travel_dates' => $this->parseMulti($filters['travel_date'] ?? null),
            'pnrs' => $this->parseMulti($filters['pnr'] ?? null),
            'confirmed_statuses' => $this->parseMulti($filters['confirmed'] ?? null),
        ];

        $rows = $this->dal->fetchTickets($normalizedFilters, $limit, $offset);
        $total = $this->dal->countTickets($normalizedFilters);

        $records = array_map([$this, 'normalizeTicketRow'], $rows);

        $filterOptions = $this->buildFilterOptions($records);
        $summary = $this->buildSummary($records);

        $includeFilters = !isset($filters['include_filters']) || filter_var($filters['include_filters'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;

        return [
            'query' => [
                'issue_start_date' => $issueStart,
                'issue_end_date' => $issueEnd,
                'ticket_no' => $normalizedFilters['ticket_numbers'],
                'issued_via' => $normalizedFilters['issued_vias'],
                'airline' => $normalizedFilters['airlines'],
                'order_no' => $normalizedFilters['order_numbers'],
                'travel_date' => $normalizedFilters['travel_dates'],
                'pnr' => $normalizedFilters['pnrs'],
                'confirmed' => $normalizedFilters['confirmed_statuses'],
                'limit' => $limit,
                'offset' => $offset,
            ],
            'meta' => [
                'total_records' => $total,
                'returned_records' => count($records),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($records)) < $total,
            ],
            'summary' => $summary,
            'filters' => $includeFilters ? $filterOptions : [],
            'records' => $records,
        ];
    }

    /**
     * Retrieve ticket update history
     */
    public function getHistory(int $autoId, int $limit = 100): array
    {
        if ($autoId <= 0) {
            throw new Exception('autoId must be positive', 400);
        }

        return [
            'auto_id' => $autoId,
            'limit' => max(1, min(500, $limit)),
            'logs' => $this->dal->getHistory($autoId, $limit),
        ];
    }

    /**
     * Add a remark log entry
     *
     * @param array<string, mixed> $payload
     */
    public function addRemark(int $autoId, array $payload): array
    {
        $remark = trim((string)($payload['remark'] ?? ''));
        if ($remark === '') {
            throw new Exception('remark is required', 400);
        }

        $userId = isset($payload['user_id']) && is_numeric($payload['user_id'])
            ? (int)$payload['user_id']
            : 0;
        $userName = trim((string)($payload['user_name'] ?? 'system'));
        if ($userName === '') {
            $userName = 'system';
        }

        $this->dal->addRemark($autoId, $remark, $userId, $userName);

        return [
            'auto_id' => $autoId,
            'user_id' => $userId,
            'user_name' => $userName,
            'remark' => $remark,
            'message' => 'Remark saved successfully',
        ];
    }

    /**
     * Update a ticket column and log the change
     *
     * @param array<string, mixed> $payload
     */
    public function updateTicket(int $autoId, array $payload): array
    {
        $column = (string)($payload['column'] ?? '');
        if (!in_array($column, self::ALLOWED_UPDATE_COLUMNS, true)) {
            throw new Exception("Column '{$column}' cannot be updated", 400);
        }

        if (!array_key_exists('value', $payload)) {
            throw new Exception('value is required', 400);
        }

        $value = $payload['value'];
        // Normalise numeric values
        if ($value !== null && is_string($value)) {
            $value = trim($value);
        }
        if (is_numeric($value)) {
            $value = (float)$value;
        }

        $userId = isset($payload['user_id']) && is_numeric($payload['user_id'])
            ? (int)$payload['user_id']
            : 0;
        $userName = trim((string)($payload['user_name'] ?? 'system'));
        if ($userName === '') {
            $userName = 'system';
        }

        $result = $this->dal->updateTicketColumn($autoId, $column, $value, $userId, $userName);

        return array_merge($result, [
            'auto_id' => $autoId,
            'column' => $column,
            'user_id' => $userId,
            'user_name' => $userName,
            'message' => $result['updated'] ? 'Update successful' : $result['message'],
        ]);
    }

    /**
     * Retrieve payment history by order IDs
     *
     * @param array<string> $orderIds
     */
    public function getOrderPayments(array $orderIds): array
    {
        $orderIds = $this->cleanList($orderIds);
        $data = $this->dal->getOrderPayments($orderIds);

        return [
            'order_ids' => $orderIds,
            'total_received' => $data['total_received'],
            'count' => count($data['rows']),
            'payments' => $data['rows'],
        ];
    }

    /**
     * Retrieve payment history by profile numbers
     *
     * @param array<string> $profileNos
     */
    public function getProfilePayments(array $profileNos, int $limit = 500): array
    {
        $profileNos = $this->cleanList($profileNos);
        $data = $this->dal->getProfilePayments($profileNos, $limit);

        return [
            'profile_nos' => $profileNos,
            'total_received' => $data['total_received'],
            'count' => count($data['rows']),
            'limit' => max(1, min(500, $limit)),
            'payments' => $data['rows'],
        ];
    }

    /**
     * Parse comma-separated or array input into unique trimmed values
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function parseMulti($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string)$value);
        }

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Build filter options map from records
     *
     * @param array<int, array<string, mixed>> $records
     * @return array<string, array<int, string>>
     */
    private function buildFilterOptions(array $records): array
    {
        $sets = [
            'ticket_no' => [],
            'issued_via' => [],
            'airline' => [],
            'order_no' => [],
            'travel_date' => [],
            'pnr' => [],
            'confirmed' => [],
        ];

        foreach ($records as $row) {
            if (!empty($row['ticket_no'])) {
                $sets['ticket_no'][$row['ticket_no']] = true;
            }
            if (!empty($row['issued_by'])) {
                $sets['issued_via'][$row['issued_by']] = true;
            }
            if (!empty($row['airline_code'])) {
                $sets['airline'][$row['airline_code']] = true;
            }
            if (!empty($row['order_id'])) {
                $sets['order_no'][$row['order_id']] = true;
            }
            if (!empty($row['travel_date'])) {
                $sets['travel_date'][$row['travel_date']] = true;
            }
            if (!empty($row['pnr'])) {
                $sets['pnr'][$row['pnr']] = true;
            }
            if (!empty($row['confirmed_status'])) {
                $sets['confirmed'][$row['confirmed_status']] = true;
            }
        }

        $options = [];
        foreach ($sets as $key => $values) {
            $list = array_keys($values);
            sort($list);
            $options[$key] = $list;
        }

        return $options;
    }

    /**
     * Build quick summary totals
     *
     * @param array<int, array<string, mixed>> $records
     * @return array<string, float>
     */
    private function buildSummary(array $records): array
    {
        $totals = [
            'total_ticket_amount' => 0.0,
            'total_service_fee' => 0.0,
            'total_commission_amount' => 0.0,
            'total_net_due' => 0.0,
        ];

        foreach ($records as $row) {
            $totals['total_ticket_amount'] += (float)($row['total_amount'] ?? 0);
            $totals['total_service_fee'] += (float)($row['service_fee'] ?? 0);
            $totals['total_commission_amount'] += (float)($row['commission_amt'] ?? 0);
            $totals['total_net_due'] += (float)($row['net_due'] ?? 0);
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    /**
     * Normalise numeric columns on a ticket row
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeTicketRow(array $row): array
    {
        $floatColumns = [
            'fare_inr',
            'comm_inr',
            'tax_inr',
            'transaction_amount_inr',
            'base_fare',
            'tax',
            'fee',
            'total_amount',
            'net_due3',
            'commission_amt',
            'service_fee',
            'net_due',
            'order_amnt',
            'fare',
            'tax',
            'comm',
            'transaction_amount',
        ];

        foreach ($floatColumns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = round((float)$row[$column], 2);
            }
        }

        $row['auto_id'] = (int)$row['auto_id'];
        $row['pax_id'] = isset($row['pax_id']) ? (int)$row['pax_id'] : null;

        return $row;
    }

    /**
     * Resolve Y-m-d date value or default relative interval
     */
    private function resolveDate(?string $value, string $relativeFallback): string
    {
        if (!empty($value) && $this->isValidDate($value)) {
            return $value;
        }

        $date = new \DateTime($relativeFallback);
        return $date->format('Y-m-d');
    }

    /**
     * Validate Y-m-d formatted string
     */
    private function isValidDate(string $value): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }

    /**
     * Clean string list
     *
     * @param array<string> $values
     * @return array<int, string>
     */
    private function cleanList(array $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Recalculate order amounts for ticket reconciliation rows
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws Exception
     */
    public function recalculateOrderAmounts(array $input): array
    {
        $start = $input['start'] ?? '';
        $end = $input['end'] ?? '';
        $paxId = isset($input['pax_id']) && is_numeric($input['pax_id']) ? (int)$input['pax_id'] : null;

        if (empty($start) || empty($end)) {
            throw new Exception('start and end (YYYY-MM-DD) are required', 400);
        }

        // Validate date format
        $startDate = $this->parseDateString($start);
        $endDate = $this->parseDateString($end);

        if (!$startDate || !$endDate) {
            throw new Exception('start and end must be valid dates in YYYY-MM-DD format', 400);
        }

        if ($startDate > $endDate) {
            throw new Exception('start date must be before or equal to end date', 400);
        }

        // Use the existing DAL instance
        $affectedRows = $this->dal->recalculateOrderAmounts(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $paxId
        );

        return [
            'affected_rows' => $affectedRows,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'pax_id' => $paxId
        ];
    }

    /**
     * Parse date string to DateTime object
     */
    private function parseDateString(string $dateString): ?\DateTime
    {
        try {
            return new \DateTime($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Import ticket reconciliation rows from hotfile
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws Exception
     */
    public function importFromHotfile(array $input): array
    {
        $documentNumbers = $input['document_numbers'] ?? [];

        if (empty($documentNumbers) || !is_array($documentNumbers)) {
            throw new Exception('document_numbers (non-empty array) is required', 400);
        }

        // Filter out empty values and trim
        $documents = array_filter(array_map('trim', $documentNumbers), function($doc) {
            return !empty($doc);
        });

        if (empty($documents)) {
            throw new Exception('document_numbers must contain at least one valid document number', 400);
        }

        // Use the existing DAL instance
        $importedCount = $this->dal->importFromHotfileByDocuments(array_values($documents));

        return [
            'imported_count' => $importedCount,
            'document_numbers' => array_values($documents),
            'total_requested' => count($documents)
        ];
    }

    /**
     * Get existing document numbers from ticket reconciliation
     *
     * @param array<int, string> $documentNumbers
     * @return array<int, string>
     */
    public function getExistingDocuments(array $documentNumbers): array
    {
        return $this->dal->getExistingDocuments($documentNumbers);
    }
}

