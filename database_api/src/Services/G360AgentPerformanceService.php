<?php
/**
 * Airline Deposit Loss Service
 * Encapsulates business logic for airline deposit analytics
 */

namespace App\Services;

use App\DAL\AirlineDepositLossDAL;
use Exception;

class AirlineDepositLossService
{
    private AirlineDepositLossDAL $dal;

    public function __construct()
    {
        $this->dal = new AirlineDepositLossDAL();
    }

    /**
     * Retrieve airline deposit loss records with filters, totals, and metadata
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getDepositLossData(array $filters = []): array
    {
        $airline = isset($filters['airline']) ? trim((string)$filters['airline']) : null;
        $period = isset($filters['period']) ? trim((string)$filters['period']) : null;

        $limit = $filters['limit'] ?? 500;
        $offset = $filters['offset'] ?? 0;

        $limit = is_numeric($limit) ? max(1, min(1000, (int)$limit)) : 500;
        $offset = is_numeric($offset) ? max(0, (int)$offset) : 0;

        $records = $this->dal->getDepositLossRecords($airline, $period, $limit, $offset);
        $totals = $this->dal->getDepositLossTotals($airline, $period);
        $totalCount = $this->dal->getTotalCount($airline, $period);

        $filtersPayload = [
            'airlines' => $this->dal->getDistinctAirlines(),
            'periods' => $this->dal->getDistinctPeriods(),
        ];

        return [
            'query' => [
                'airline' => $airline,
                'period' => $period,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'meta' => [
                'total_records' => $totalCount,
                'returned_records' => count($records),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($records)) < $totalCount,
            ],
            'totals' => $totals,
            'filters' => $filtersPayload,
            'records' => $records,
        ];
    }
}

