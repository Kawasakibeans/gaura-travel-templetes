<?php
/**
 * Service layer for refund requests dashboard.
 */

namespace App\Services;

use App\DAL\RefundRequestsDashboardDAL;
use DateInterval;
use DateTime;
use Exception;

class RefundRequestsDashboardService
{
    private RefundRequestsDashboardDAL $dal;

    private const PAYMENT_LABELS = [
        '3'  => 'General Account',
        '5'  => 'BPOINT',
        '6'  => 'Contra for vendor payment',
        '7'  => 'AZUPAY',
        '8'  => 'Asiapay',
        '9'  => 'Bpay',
        '10' => 'IFN Holding Account',
        '11' => 'DKT Offset Account',
        '12' => 'CBA',
        '13' => 'Mintpay',
        '14' => 'Transfer',
        '15' => 'TDU Booking',
        '16' => 'SlicePay',
    ];

    public function __construct()
    {
        $this->dal = new RefundRequestsDashboardDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listCases(array $filters): array
    {
        $dates = $this->resolveDateRange($filters);

        // Extract status filter
        $status = isset($filters['status']) && $filters['status'] !== '' 
            ?trim((string)$filters['status']) 
            : null;

        $cases = $this->dal->getCases($dates['start'], $dates['end'], $status);
        $caseIds = array_column($cases, 'case_id');

        $metaGrouped = $this->dal->getMetaForCases($caseIds);

        $orders = $this->collectOrderIds($cases, $metaGrouped);

        $pnrMap = $this->dal->getPnrByOrderIds($orders);
        $ticketMap = $this->dal->getTicketsByOrderIds($orders);
        $paymentRows = $this->dal->getPaymentRowsByOrderIds($orders);

        $paymentSummary = $this->buildPaymentSummary($paymentRows);

        $casePayload = [];
        foreach ($cases as $case) {
            $caseId = (int)$case['case_id'];
            $metaItems = $metaGrouped[$caseId] ?? [];
            $metaMap = [];
            foreach ($metaItems as $item) {
                $metaMap[$item['meta_key']] = $item['meta_value'];
            }

            $orderId = $this->resolveOrderId($case, $metaMap);
            $pnr = $pnrMap[$orderId] ?? $this->resolveMetaFallback($metaMap, ['pnr', 'reservation_code', 'reservation_ref']);
            $tickets = $ticketMap[$orderId] ?? $this->resolveMetaFallback($metaMap, ['ticket_numbers', 'ticket_number']);

            $paymentInfo = $paymentSummary[$orderId] ?? ['total' => null, 'methods' => []];

            $casePayload[] = [
                'case_id' => $caseId,
                'case_date' => $case['case_date'],
                'status' => $case['status'],
                'sub_status' => $case['sub_status'],
                'priority' => $case['priority'],
                'order_id' => $orderId,
                'pnr' => $pnr,
                'ticket_numbers' => $tickets,
                'payment_methods' => $paymentInfo['methods'],
                'passenger_paid_total' => $paymentInfo['total'],
                'meta' => $metaMap,
            ];
        }

        return [
            'date_range' => $dates,
            'total_cases' => count($casePayload),
            'cases' => $casePayload,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function saveMeta(array $payload): array
    {
        $caseId = isset($payload['case_id']) ? (int)$payload['case_id'] : 0;
        $key = isset($payload['key']) ? trim((string)$payload['key']) : '';
        $value = isset($payload['value']) ? trim((string)$payload['value']) : '';
        $slug = isset($payload['slug']) ? trim((string)$payload['slug']) : '';

        if ($caseId <= 0) {
            throw new Exception('case_id is required', 400);
        }
        if ($key === '') {
            throw new Exception('key is required', 400);
        }

        $metaKey = $slug !== '' ? $key . '--' . $this->slugify($slug) : $key;

        $this->dal->deleteMeta($caseId, $metaKey);
        $this->dal->insertMeta($caseId, $metaKey, $value);

        return [
            'status' => 'success',
            'case_id' => $caseId,
            'meta_key' => $metaKey,
            'value' => $value,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{start:string,end:string}
     */
    private function resolveDateRange(array $filters): array
    {
        $today = new DateTime('today');
        $defaultStart = (clone $today)->modify('first day of this month');

        $start = isset($filters['start_date']) ? $this->parseDateOrNull($filters['start_date']) : null;
        $end = isset($filters['end_date']) ? $this->parseDateOrNull($filters['end_date']) : null;

        $startDate = $start ?? $defaultStart;
        $endDate = $end ?? $today;

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    private function parseDateOrNull(mixed $value): ?DateTime
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

    /**
     * @param array<int,array<string,mixed>> $cases
     * @param array<int,array<array<string,string>>> $metaGrouped
     * @return array<int,string>
     */
    private function collectOrderIds(array $cases, array $metaGrouped): array
    {
        $set = [];

        foreach ($cases as $case) {
            $raw = trim((string)($case['order_id_req'] ?? ''));
            if ($raw !== '') {
                $set[$raw] = true;
            }
        }

        foreach ($metaGrouped as $caseMeta) {
            foreach ($caseMeta as $meta) {
                if ($meta['meta_key'] === 'order_no' || $meta['meta_key'] === 'order_id') {
                    $value = trim($meta['meta_value']);
                    if ($value !== '') {
                        $set[$value] = true;
                    }
                }
            }
        }

        return array_keys($set);
    }

    /**
     * @param array<int,array<string,mixed>> $paymentRows
     * @return array<string,array{total:?string,methods:string}>
     */
    private function buildPaymentSummary(array $paymentRows): array
    {
        $summary = [];

        foreach ($paymentRows as $row) {
            $orderId = (string)$row['order_id'];
            $amountRaw = (string)($row['trams_received_amount'] ?? '');
            $label = trim((string)($row['payment_label'] ?? ''));

            if (!isset($summary[$orderId])) {
                $summary[$orderId] = [
                    'total' => 0.0,
                    'methods' => [],
                ];
            }

            $amount = $this->parseNumericAmount($amountRaw);
            if ($amount !== null) {
                $summary[$orderId]['total'] += $amount;
            }

            if ($label !== '') {
                $summary[$orderId]['methods'][$label] = true;
            }
        }

        foreach ($summary as $orderId => $data) {
            $summary[$orderId]['total'] = $data['total'] === 0.0
                ? null
                : number_format((float)$data['total'], 2, '.', '');
            $summary[$orderId]['methods'] = implode(', ', array_keys($data['methods']));
        }

        return $summary;
    }

    private function parseNumericAmount(string $value): ?float
    {
        $clean = preg_replace('/[^\d\.\-]/', '', $value);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }

        return (float)$clean;
    }

    /**
     * @param array<string,string> $meta
     */
    private function resolveOrderId(array $case, array $meta): string
    {
        $requestOrder = trim((string)($case['order_id_req'] ?? ''));
        if ($requestOrder !== '') {
            return $requestOrder;
        }

        foreach (['order_no', 'order_id'] as $key) {
            if (isset($meta[$key]) && trim($meta[$key]) !== '') {
                return trim($meta[$key]);
            }
        }

        return '';
    }

    /**
     * @param array<string,string> $meta
     * @param array<int,string> $keys
     */
    private function resolveMetaFallback(array $meta, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && trim($meta[$key]) !== '') {
                return trim($meta[$key]);
            }
        }
        return '';
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-');
    }
}
