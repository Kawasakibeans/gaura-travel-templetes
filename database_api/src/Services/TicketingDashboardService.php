<?php
/**
 * Ticketing dashboard service.
 */

namespace App\Services;

use App\DAL\TicketingDashboardDAL;
use DateInterval;
use DateTime;
use Exception;

class TicketingDashboardService
{
    private TicketingDashboardDAL $dal;
    private const DATASETS = [
        'sq_pending',
        'mh_pending',
        'fit_pending',
        'penidng_name_to_upload',
        'pending_audit',
        'payment_received_canceled',
        'gdeals_empty_tkt',
    ];

    public function __construct()
    {
        $this->dal = new TicketingDashboardDAL();
    }

    public function getCounts(array $filters): array
    {
        [$start, $end] = $this->defaultedDateRange($filters);
        $counts = $this->dal->getCounts($start, $end);

        return [
            'date_range' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'counts' => $counts,
        ];
    }

    public function getDataset(string $dataset, array $filters): array
    {
        if (!in_array($dataset, self::DATASETS, true)) {
            throw new Exception('Unknown dataset requested', 400);
        }

        [$start, $end] = $this->defaultedDateRange($filters);
        $rows = $this->dal->getDataset($dataset, $start, $end);

        return [
            'dataset' => $dataset,
            'date_range' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Normalise date range with defaults (today to +3 months).
     *
     * @return array{string,string}
     */
    private function defaultedDateRange(array $filters): array
    {
        $today = new DateTime('today', new \DateTimeZone('Australia/Sydney'));
        $defaultStart = $today->format('Y-m-d');
        $defaultEnd = (clone $today)->add(new DateInterval('P3M'))->format('Y-m-d');

        $start = $filters['start_date'] ?? $defaultStart;
        $end = $filters['end_date'] ?? $defaultEnd;

        $startDate = $this->normaliseDate($start, 'start_date');
        $endDate = $this->normaliseDate($end, 'end_date');

        if ($startDate > $endDate) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        return [$startDate, $endDate];
    }

    private function normaliseDate(string $value, string $field): string
    {
        $value = trim($value);
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt === false) {
            throw new Exception("$field must be formatted as YYYY-MM-DD", 400);
        }

        return $dt->format('Y-m-d');
    }
}

