<?php
/**
 * Service for TRAMS matched records.
 */

namespace App\Services;

use App\DAL\TramsMatchedRecordsDAL;
use DateTime;
use Exception;

class TramsMatchedRecordsService
{
    private TramsMatchedRecordsDAL $dal;

    /**
     * @var array<string,string>
     */
    private const FIELD_MAP = [
        'client_link_no' => 'client_linkno',
        'issue_date' => 'issuedate',
        'branch_link_no' => 'branch_linkno',
        'record_locator' => 'recordlocator',
        'pay_status' => 'paystatus_linkcode',
        'invoice_type' => 'invoicetype_linkcode',
        'partial_payment_amount' => 'partpayamt',
        'invoice_group' => 'invoicegroup',
        'first_inside_agent' => 'firstinsideagentbkg_linkno',
        'first_outside_agent' => 'firstoutsideagentbkg_linkno',
        'calculated_invoice_number' => 'calcinvoicenumber',
        'alternate_invoice_number' => 'altinvoicenumber',
        'arc_link_no' => 'arc_linkno',
        'pnr_creation_date' => 'pnrcreationdate',
        'received_by' => 'receivedby',
        'factura_no' => 'facturano',
        'servicio_no' => 'serviciono',
        'itinerary_remarks' => 'itininvremarks',
        'home_host_link_no' => 'homehost_linkno',
        'market_id' => 'marketid',
        'agency_link_no' => 'agency_linkno',
        'accounting_remarks' => 'accountingremarks',
        'remarks' => 'remarks',
    ];

    public function __construct()
    {
        $this->dal = new TramsMatchedRecordsDAL();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function checkInvoices(array $payload): array
    {
        $numbers = isset($payload['invoice_numbers']) && is_array($payload['invoice_numbers'])
            ? array_values(array_filter(array_map('trim', $payload['invoice_numbers'])))
            : [];

        if (empty($numbers)) {
            throw new Exception('invoice_numbers must be a non-empty array', 400);
        }

        $existing = $this->dal->getExistingInvoiceNumbers($numbers);
        $existingLookup = array_flip($existing);

        $missing = array_values(array_filter($numbers, static function ($num) use ($existingLookup) {
            return !isset($existingLookup[$num]);
        }));

        return [
            'existing' => $existing,
            'missing' => $missing,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updateInvoice(array $payload): array
    {
        $invoiceNumber = isset($payload['invoice_number']) ? trim((string)$payload['invoice_number']) : '';
        if ($invoiceNumber === '') {
            throw new Exception('invoice_number is required', 400);
        }

        $fields = isset($payload['fields']) && is_array($payload['fields']) ? $payload['fields'] : null;
        if (!$fields) {
            throw new Exception('fields must be provided as an object', 400);
        }

        $data = [];
        foreach (self::FIELD_MAP as $input => $column) {
            if (!array_key_exists($input, $fields)) {
                continue;
            }
            $value = $fields[$input];

            if (in_array($column, ['issuedate', 'pnrcreationdate'], true)) {
                if ($value === null || $value === '') {
                    $data[$column] = null;
                } else {
                    $dt = $this->parseDate((string)$value);
                    if (!$dt) {
                        throw new Exception("Invalid date format for {$input}", 400);
                    }
                    $data[$column] = $dt->format('Y-m-d');
                }
                continue;
            }

            if ($column === 'partpayamt') {
                $data[$column] = $value === null || $value === ''
                    ? 0
                    : (float)$value;
                continue;
            }

            $data[$column] = $value;
        }

        if (empty($data)) {
            throw new Exception('No valid fields supplied for update', 400);
        }

        $this->dal->updateInvoice($invoiceNumber, $data);

        return [
            'status' => 'success',
            'invoice_number' => $invoiceNumber,
            'updated_columns' => array_keys($data),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listInvoices(array $filters): array
    {
        $start = isset($filters['start_date']) ? $this->parseDate($filters['start_date']) : null;
        $end = isset($filters['end_date']) ? $this->parseDate($filters['end_date']) : null;

        if (!$start || !$end) {
            throw new Exception('start_date and end_date (YYYY-MM-DD) are required', 400);
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $rows = $this->dal->getInvoicesByIssueDate(
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59')
        );

        return [
            'filters' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ],
            'total' => count($rows),
            'invoices' => $rows,
        ];
    }

    private function parseDate(mixed $value): ?DateTime
    {
        if (!is_string($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt instanceof DateTime) {
            return $dt;
        }

        return null;
    }
}

