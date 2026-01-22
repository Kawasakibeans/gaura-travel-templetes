<?php
/**
 * Account Reconciliation V2 Service
 * Aggregates ticket, booking, passenger, and payment data into a single report
 */

namespace App\Services;

use App\DAL\AccountReconciliationV2DAL;
use Exception;

class AccountReconciliationV2Service
{
    private AccountReconciliationV2DAL $dal;

    public function __construct()
    {
        $this->dal = new AccountReconciliationV2DAL();
    }

    /**
     * Build consolidated reconciliation dataset
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getReconciliationData(array $filters): array
    {
        $issueStart = $this->resolveDate($filters['issue_start_date'] ?? null, null, '-30 days');
        $issueEnd = $this->resolveDate($filters['issue_end_date'] ?? null, null, 'now');

        if ($issueStart > $issueEnd) {
            throw new Exception('issue_start_date must be before issue_end_date', 400);
        }

        $orderStart = $this->resolveDate($filters['order_start_date'] ?? null, $issueStart);
        $orderEnd = $this->resolveDate($filters['order_end_date'] ?? null, $issueEnd);

        if ($orderStart > $orderEnd) {
            throw new Exception('order_start_date must be before order_end_date', 400);
        }

        $normalizedFilters = [
            'issue_start_date' => $issueStart,
            'issue_end_date' => $issueEnd,
            'order_start_date' => $orderStart,
            'order_end_date' => $orderEnd,
            'ticket_no' => $filters['ticket_no'] ?? null,
            'issued_via' => $filters['issued_via'] ?? null,
            'airline' => $filters['airline'] ?? null,
            'order_no' => $filters['order_no'] ?? null,
            'travel_date' => $filters['travel_date'] ?? null,
            'pnr' => $filters['pnr'] ?? null,
            'confirmed' => $filters['confirmed'] ?? null,
        ];

        $ticketRows = $this->dal->getTicketRows($normalizedFilters);
        $bookingRows = $this->dal->getBookingRows($normalizedFilters);
        $passengerRows = $this->dal->getPassengerRows($normalizedFilters);
        $paymentRows = $this->dal->getPaymentRows($normalizedFilters);
        $orderTotalsRows = $this->dal->getOrderTotals($normalizedFilters);

        $bookingsByOrder = [];
        foreach ($bookingRows as $row) {
            $bookingsByOrder[$row['order_id']] = $row;
        }

        $passengersById = [];
        foreach ($passengerRows as $row) {
            $passengersById[$row['pax_id']] = $row;
        }

        $paymentsByOrder = [];
        foreach ($paymentRows as $row) {
            $paymentsByOrder[$row['order_id']] = [
                'received_amount' => (float)$row['received_amount']
            ];
        }

        $orderTotalsByOrder = [];
        foreach ($orderTotalsRows as $row) {
            $orderTotalsByOrder[$row['order_id']] = [
                'total_transaction_amount' => (float)$row['total_transaction_amount'],
                'unique_count' => (int)$row['unique_count'],
            ];
        }

        $combined = [];
        $totals = [
            'ticket_amount' => 0.0,
            'received_amount' => 0.0,
            'service_fee' => 0.0,
            'commission_amount' => 0.0,
        ];

        foreach ($ticketRows as $ticket) {
            $orderId = $ticket['order_id'] ?? null;
            $paxId = $ticket['pax_id'] ?? null;

            if (!$orderId || !$paxId) {
                continue;
            }

            $booking = $bookingsByOrder[$orderId] ?? null;
            $passenger = $passengersById[$paxId] ?? null;
            $payment = $paymentsByOrder[$orderId] ?? null;
            $orderTotals = $orderTotalsByOrder[$orderId] ?? null;

            // Match the original behaviour: require booking, passenger, and payment data to proceed
            if (!$booking || !$passenger || !$payment || !$orderTotals) {
                continue;
            }

            $uniqueCount = max(1, (int)$orderTotals['unique_count']);
            $receivedAmount = (float)$payment['received_amount'];
            $totalTransactionAmount = (float)$orderTotals['total_transaction_amount'];

            $serviceFee = $uniqueCount > 0
                ? ($receivedAmount - $totalTransactionAmount) / $uniqueCount
                : 0.0;

            $netDueCalculated = $uniqueCount > 0
                ? (($receivedAmount / $uniqueCount) - $serviceFee)
                : 0.0;

            $totalAmount = (float)$ticket['total_amount'];

            $record = [
                'ticket_no' => $ticket['ticket_no'],
                'issued_date' => $ticket['issued_date'],
                'pax_id' => (int)$paxId,
                'document_type' => $ticket['document_type'],
                'order_id' => $orderId,
                'pnr' => $ticket['pnr'],
                'confirmed' => $ticket['confirmed'],
                'currency' => $ticket['currency'],
                'base_fare' => $this->toFloat($ticket['base_fare']),
                'tax' => $this->toFloat($ticket['tax']),
                'fee' => $this->toFloat($ticket['fee']),
                'total_amount' => round($totalAmount, 2),
                'net_due_old' => $this->toFloat($ticket['net_due_old']),
                'commission_amt' => $this->toFloat($ticket['commission_amt']),
                'issued_by' => $ticket['issued_by'],
                'airline_code' => $booking['airline_code'],
                'client_status' => $booking['client_status'],
                'order_date' => $booking['order_date'],
                'travel_date' => $booking['travel_date'],
                'return_date' => $booking['return_date'],
                'booked_by' => $booking['booked_by'],
                'pax_surname' => $passenger['pax_surname'],
                'pax_firstname' => $passenger['pax_firstname'],
                'passenger_ticketed_by' => $passenger['issued_by'],
                'passenger_ticketed_on' => $passenger['issued_date'],
                'received_amount' => round($receivedAmount, 2),
                'total_transaction_amount' => round($totalTransactionAmount, 2),
                'unique_ticket_count' => $uniqueCount,
                'service_fee' => round($serviceFee, 2),
                'net_due' => round($netDueCalculated, 2),
            ];

            $combined[] = $record;

            $totals['ticket_amount'] += $totalAmount;
            $totals['received_amount'] += $receivedAmount;
            $totals['service_fee'] += $serviceFee;
            $totals['commission_amount'] += $this->toFloat($ticket['commission_amt']);
        }

        $totalRecords = count($combined);

        $limit = $filters['limit'] ?? 500;
        $offset = $filters['offset'] ?? 0;
        $limit = is_numeric($limit) ? max(1, min(1000, (int)$limit)) : 500;
        $offset = is_numeric($offset) ? max(0, (int)$offset) : 0;

        $pagedRecords = array_slice($combined, $offset, $limit);

        $filterOptions = $this->buildFilterOptions($combined);

        return [
            'query' => [
                'issue_start_date' => $issueStart,
                'issue_end_date' => $issueEnd,
                'order_start_date' => $orderStart,
                'order_end_date' => $orderEnd,
                'ticket_no' => $normalizedFilters['ticket_no'],
                'issued_via' => $normalizedFilters['issued_via'],
                'airline' => $normalizedFilters['airline'],
                'order_no' => $normalizedFilters['order_no'],
                'travel_date' => $normalizedFilters['travel_date'],
                'pnr' => $normalizedFilters['pnr'],
                'confirmed' => $normalizedFilters['confirmed'],
                'limit' => $limit,
                'offset' => $offset,
            ],
            'meta' => [
                'total_records' => $totalRecords,
                'returned_records' => count($pagedRecords),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($pagedRecords)) < $totalRecords,
            ],
            'summary' => [
                'total_ticket_amount' => round($totals['ticket_amount'], 2),
                'total_received_amount' => round($totals['received_amount'], 2),
                'total_service_fee' => round($totals['service_fee'], 2),
                'total_commission_amount' => round($totals['commission_amount'], 2),
            ],
            'filters' => $filterOptions,
            'records' => $pagedRecords,
        ];
    }

    /**
     * Build filter options map from combined data
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
            if (!empty($row['confirmed'])) {
                $sets['confirmed'][$row['confirmed']] = true;
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
     * Parse or default a date string (Y-m-d)
     */
    private function resolveDate(?string $value, ?string $fallbackDate = null, string $relativeFallback = 'now'): string
    {
        if (!empty($value) && $this->isValidDate($value)) {
            return $value;
        }

        if (!empty($fallbackDate) && $this->isValidDate($fallbackDate)) {
            return $fallbackDate;
        }

        $date = new \DateTime($relativeFallback);
        return $date->format('Y-m-d');
    }

    /**
     * Validate Y-m-d string
     */
    private function isValidDate(string $value): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }

    /**
     * Cast numeric-like values to float with 2 decimal precision
     */
    private function toFloat($value): float
    {
        return round((float)$value, 2);
    }
}

