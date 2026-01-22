<?php
/**
 * Service layer for ticketed review dashboard.
 */

namespace App\Services;

use App\DAL\TicketedReviewDAL;
use DateInterval;
use DateTime;
use Exception;

class TicketedReviewService
{
    private TicketedReviewDAL $dal;

    public function __construct()
    {
        $this->dal = new TicketedReviewDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getSummary(array $filters): array
    {
        $start = isset($filters['start_date']) ? $this->parseDate($filters['start_date']) : null;
        $end = isset($filters['end_date']) ? $this->parseDate($filters['end_date']) : null;
        $agent = isset($filters['agent_name']) ? trim((string)$filters['agent_name']) : '';

        if (!$start || !$end) {
            $start = new DateTime('first day of this month');
            $end = new DateTime('last day of this month');
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $agentFilter = $agent !== '' ? $agent : null;

        $overallRows = $this->dal->getAggregatedRange(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $agentFilter
        );

        $ranges = $this->buildRanges($start, $end);

        $rangeSummaries = [];
        foreach ($ranges as $label => $range) {
            if (!$range) {
                $rangeSummaries[$label] = null;
                continue;
            }

            [$rangeStart, $rangeEnd] = $range;
            $rows = $this->dal->getAggregatedRange(
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d'),
                $agentFilter
            );

            $rangeSummaries[$label] = [
                'from' => $rangeStart->format('Y-m-d'),
                'to' => $rangeEnd->format('Y-m-d'),
                'rows' => $rows,
                'totals' => $this->calculateTotals($rows),
            ];
        }

        return [
            'filters' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'agent_name' => $agentFilter,
            ],
            'overall' => [
                'rows' => $overallRows,
                'totals' => $this->calculateTotals($overallRows),
            ],
            'ranges' => $rangeSummaries,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getAgentDetails(array $filters): array
    {
        $date = isset($filters['date']) ? $this->parseDate($filters['date']) : null;
        if (!$date) {
            throw new Exception('date is required (YYYY-MM-DD)', 400);
        }

        $agent = isset($filters['agent_name']) ? trim((string)$filters['agent_name']) : '';

        $rows = $this->dal->getAgentDetailsForDate(
            $date->format('Y-m-d'),
            $agent === '' ? null : $agent
        );

        return [
            'date' => $date->format('Y-m-d'),
            'agent_name' => $agent !== '' ? $agent : null,
            'rows' => $rows,
            'totals' => $this->calculateTotals($rows),
        ];
    }

    public function listAgents(): array
    {
        return [
            'agents' => $this->dal->getAgents(),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,float>
     */
    private function calculateTotals(array $rows): array
    {
        $columns = [
            'fit_ticketed',
            'gdeal_ticketed',
            'ticket_issued',
            'ctg',
            'gkt_iata',
            'ifn_iata',
            'gilpin',
            'CCUVS32NQ',
            'MELA821CV',
            'I5FC',
            'MELA828FN',
            'CCUVS32MV',
        ];

        $totals = array_fill_keys($columns, 0.0);

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (isset($row[$column])) {
                    $totals[$column] += (float)$row[$column];
                }
            }
        }

        return $totals;
    }

    /**
     * @return array<string,?array{0:DateTime,1:DateTime}>
     */
    private function buildRanges(DateTime $start, DateTime $end): array
    {
        $firstOfMonth = (clone $start)->modify('first day of this month');
        $monthString = $firstOfMonth->format('Y-m');
        $lastDay = (int)$firstOfMonth->format('t');

        $ranges = [
            'days_1_10' => $this->clipRange("${monthString}-01", "${monthString}-10", $start, $end),
            'days_11_20' => $this->clipRange("${monthString}-11", "${monthString}-20", $start, $end),
            'days_21_end' => $this->clipRange("${monthString}-21", "${monthString}-{$lastDay}", $start, $end),
        ];

        return $ranges;
    }

    /**
     * @return ?array{0:DateTime,1:DateTime}
     */
    private function clipRange(string $rangeStart, string $rangeEnd, DateTime $filterStart, DateTime $filterEnd): ?array
    {
        $start = new DateTime($rangeStart);
        $end = new DateTime($rangeEnd);

        if ($start > $filterEnd || $end < $filterStart) {
            return null;
        }

        if ($start < $filterStart) {
            $start = clone $filterStart;
        }
        if ($end > $filterEnd) {
            $end = clone $filterEnd;
        }

        if ($start > $end) {
            return null;
        }

        return [$start, $end];
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

