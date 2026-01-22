<?php
/**
 * Service for booking count report.
 */

namespace App\Services;

use App\DAL\BookingCountReportDAL;
use DateInterval;
use DateTime;
use Exception;

class BookingCountReportService
{
    private const MAX_SPAN_DAYS = 31;

    private BookingCountReportDAL $dal;

    public function __construct()
    {
        $this->dal = new BookingCountReportDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getReport(array $filters): array
    {
        $start = isset($filters['start_date']) ? $this->parseDate($filters['start_date']) : null;
        $end = isset($filters['end_date']) ? $this->parseDate($filters['end_date']) : null;

        $today = new DateTime('today');

        if (!$start) {
            $start = clone $today;
        }
        if (!$end) {
            $end = clone $today;
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $spanDays = (int)$start->diff($end)->format('%a') + 1;
        $clamped = false;
        if ($spanDays > self::MAX_SPAN_DAYS) {
            $end = (clone $start)->add(new DateInterval('P' . (self::MAX_SPAN_DAYS - 1) . 'D'));
            $spanDays = self::MAX_SPAN_DAYS;
            $clamped = true;
        }

        $orderType = isset($filters['order_type']) ? strtolower((string)$filters['order_type']) : 'all';
        if (!in_array($orderType, ['all', 'wpt', 'gds'], true)) {
            $orderType = 'all';
        }

        $counts = $this->dal->getStatusCounts(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $orderType === 'all' ? null : $orderType
        );

        $paxDistinct = $this->dal->getUniquePaxCount(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $orderType === 'all' ? null : $orderType
        );

        $remappedUnknowns = 0;
        $totals = [
            'partially_paid' => 0,
            'paid' => 0,
            'canceled' => 0,
            'failed' => 0,
            'refund' => 0,
            'waiting_voucher' => 0,
            'voucher_submited' => 0,
            'receipt_received' => 0,
            'fit' => 0,
            'wpt' => 0,
            'total' => 0,
        ];

        if ($counts) {
            $totals['paid'] = (int)($counts['paid'] ?? 0);
            $totals['partially_paid'] = (int)($counts['partially_paid'] ?? 0);
            $totals['canceled'] = (int)($counts['canceled'] ?? 0);
            $totals['failed'] = (int)($counts['na_failed'] ?? 0);
            $totals['refund'] = (int)($counts['refund'] ?? 0);
            $totals['waiting_voucher'] = (int)($counts['waiting_voucher'] ?? 0);
            $totals['voucher_submited'] = (int)($counts['voucher_submited'] ?? 0);
            $totals['receipt_received'] = (int)($counts['receipt_received'] ?? 0);
            $totals['fit'] = (int)($counts['fit'] ?? 0);
            $totals['wpt'] = (int)($counts['wpt'] ?? 0);

            $remappedUnknowns = (int)($counts['unknowns'] ?? 0);
            if ($remappedUnknowns > 0) {
                $totals['partially_paid'] += $remappedUnknowns;
            }

            $totals['total'] = (int)($counts['pax_total'] ?? 0);
        }

        return [
            'filters' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'span_days' => $spanDays,
                'clamped' => $clamped,
                'order_type' => $orderType,
            ],
            'pax_distinct' => $paxDistinct,
            'remapped_unknown_status' => $remappedUnknowns,
            'status_counts' => $totals,
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

