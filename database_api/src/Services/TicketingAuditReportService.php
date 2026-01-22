<?php
/**
 * Ticketing & audit report service layer.
 */

namespace App\Services;

use App\DAL\TicketingAuditReportDAL;
use DateTime;
use Exception;

class TicketingAuditReportService
{
    private TicketingAuditReportDAL $dal;

    public function __construct()
    {
        $this->dal = new TicketingAuditReportDAL();
    }

    public function getIssuedDetail(array $filters): array
    {
        [$start, $end] = $this->normaliseDateRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(200, max(1, (int)($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $orderColumn = $filters['order_by'] ?? 'ticketed_on';
        $orderDirection = $filters['order_dir'] ?? 'DESC';
        $search = isset($filters['search']) ? trim((string)$filters['search']) : null;

        $result = $this->dal->getIssuedDetail([
            'start_date' => $start,
            'end_date' => $end,
            'offset' => $offset,
            'limit' => $perPage,
            'order_column' => $orderColumn,
            'order_direction' => $orderDirection,
            'search' => $search,
        ]);

        return [
            'date_range' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'page' => $page,
            'per_page' => $perPage,
            'records_total' => $result['records_total'],
            'records_filtered' => $result['records_filtered'],
            'data' => $result['data'],
        ];
    }

    public function getTicketIssuedSummary(array $filters): array
    {
        return $this->paginatedSummary($filters, fn (...$args) => $this->dal->getTicketIssuedSummary(...$args));
    }

    public function getTicketAuditedSummary(array $filters): array
    {
        return $this->paginatedSummary($filters, fn (...$args) => $this->dal->getTicketAuditedSummary(...$args));
    }

    public function getAuditedTickets(array $filters): array
    {
        return $this->paginatedSummary($filters, fn (...$args) => $this->dal->getAuditedTickets(...$args));
    }

    public function getNameUpdatesSummary(array $filters): array
    {
        return $this->paginatedSummary($filters, fn (...$args) => $this->dal->getNameUpdatesSummary(...$args));
    }

    public function getUpdatedNames(array $filters): array
    {
        return $this->paginatedSummary($filters, fn (...$args) => $this->dal->getUpdatedNames(...$args));
    }

    /**
     * @param callable(string,string,int,int):array $callback
     */
    private function paginatedSummary(array $filters, callable $callback): array
    {
        [$start, $end] = $this->normaliseDateRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(500, max(1, (int)($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $result = $callback($start, $end, $offset, $perPage);

        return [
            'date_range' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'data' => $result['data'],
        ];
    }

    /**
     * @return array{string,string}
     * @throws Exception
     */
    private function normaliseDateRange(?string $start, ?string $end): array
    {
        if ($start === null || $end === null) {
            throw new Exception('start_date and end_date are required', 400);
        }

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
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            throw new Exception("$field must be formatted as YYYY-MM-DD", 400);
        }

        return $date->format('Y-m-d');
    }
}

