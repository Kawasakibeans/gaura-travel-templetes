<?php
/**
 * Sales data service
 */

namespace App\Services;

use App\DAL\SalesDataDAL;
use DateTime;
use Exception;

class SalesDataService
{
    private SalesDataDAL $dal;

    public function __construct()
    {
        $this->dal = new SalesDataDAL();
    }

    /**
     * List upcoming seat availability.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listUpcomingSeats(array $filters = []): array
    {
        $travelDateFrom = $this->normaliseDate($filters['travel_date_from'] ?? 'today', 'travel_date_from');
        $travelDateTo = null;
        if (!empty($filters['travel_date_to'])) {
            $travelDateTo = $this->normaliseDate($filters['travel_date_to'], 'travel_date_to');
            if ($travelDateTo < $travelDateFrom) {
                throw new Exception('travel_date_to must be on or after travel_date_from', 400);
            }
        }

        $minRemaining = isset($filters['min_remaining']) ? (int)$filters['min_remaining'] : 0;
        if ($minRemaining < 0) {
            throw new Exception('min_remaining cannot be negative', 400);
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        if ($limit <= 0 || $limit > 500) {
            throw new Exception('limit must be between 1 and 500', 400);
        }

        $pricingCategoryId = $filters['pricing_category_id'] ?? '953';
        if ($pricingCategoryId !== null && !is_string($pricingCategoryId)) {
            throw new Exception('pricing_category_id must be a string', 400);
        }

        $airlineCode = null;
        if (!empty($filters['airline_code'])) {
            $airlineCode = strtoupper($filters['airline_code']);
            if (!preg_match('/^[A-Z0-9]{2}$/', $airlineCode)) {
                throw new Exception('airline_code must be a two-character alphanumeric code', 400);
            }
        }

        $tripCodeLike = null;
        if (!empty($filters['trip_code_like'])) {
            $tripCodeLike = '%' . $filters['trip_code_like'] . '%';
        }

        $dalFilters = [
            'travel_date_from' => $travelDateFrom->format('Y-m-d'),
            'travel_date_to' => $travelDateTo?->format('Y-m-d'),
            'min_remaining' => $minRemaining,
            'pricing_category_id' => $pricingCategoryId,
            'airline_code' => $airlineCode,
            'trip_code_like' => $tripCodeLike,
        ];

        $rows = $this->dal->getUpcomingSeats($dalFilters, $limit);

        $results = array_map(function (array $row) {
            return [
                'trip_code' => $row['trip_code'],
                'travel_date' => $row['travel_date'],
                'airline_code' => $row['airline_code'],
                'remaining' => (int)$row['remaining'],
                'sale_price' => $row['sale_price'] !== null ? (float)$row['sale_price'] : null,
            ];
        }, $rows);

        return [
            'filters' => [
                'travel_date_from' => $travelDateFrom->format('Y-m-d'),
                'travel_date_to' => $travelDateTo?->format('Y-m-d'),
                'min_remaining' => $minRemaining,
                'pricing_category_id' => $pricingCategoryId,
                'airline_code' => $airlineCode,
                'limit' => $limit,
            ],
            'total' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Normalise a date string.
     */
    private function normaliseDate(string $value, string $field): DateTime
    {
        $value = trim($value);
        if (strtolower($value) === 'today') {
            return new DateTime('today');
        }

        $date = DateTime::createFromFormat('Y-m-d', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($date === false) {
            throw new Exception(sprintf('%s must be a valid date (Y-m-d)', $field), 400);
        }

        return $date;
    }
}

