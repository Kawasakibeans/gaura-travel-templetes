<?php
/**
 * Agent GDeal checkout service.
 */

namespace App\Services;

use App\DAL\AgentGdealCheckoutDAL;
use DateTime;
use Exception;

class AgentGdealCheckoutService
{
    private AgentGdealCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new AgentGdealCheckoutDAL();
    }

    public function listDates(array $filters): array
    {
        $tripId = isset($filters['trip_id']) ? trim((string)$filters['trip_id']) : '';
        if ($tripId === '') {
            throw new Exception('trip_id is required', 400);
        }

        $exact = null;
        $like = null;

        if (!empty($filters['start_date'])) {
            $exact = $this->assertDatePattern((string)$filters['start_date'], true);
        }

        if (!empty($filters['month_prefix'])) {
            $like = $this->assertDatePattern((string)$filters['month_prefix'], false);
        }

        $rows = $this->dal->getDates($tripId, $exact, $like);
        return [
            'trip_id' => $tripId,
            'filters' => [
                'start_date' => $exact,
                'month_prefix' => $like,
            ],
            'dates' => $rows,
        ];
    }

    public function getPricing(int $pricingId): array
    {
        if ($pricingId <= 0) {
            throw new Exception('pricing_id must be a positive integer', 400);
        }

        $row = $this->dal->getPricingById($pricingId);
        if (!$row) {
            throw new Exception('Pricing record not found', 404);
        }

        return [
            'pricing_id' => $pricingId,
            'pricing' => $row,
        ];
    }

    public function getPriceCategory(int $pricingId, int $categoryId): array
    {
        if ($pricingId <= 0) {
            throw new Exception('pricing_id must be a positive integer', 400);
        }
        if ($categoryId <= 0) {
            throw new Exception('pricing_category_id must be a positive integer', 400);
        }

        $row = $this->dal->getPriceCategory($pricingId, $categoryId);
        if (!$row) {
            throw new Exception('Price category record not found', 404);
        }

        return [
            'pricing_id' => $pricingId,
            'pricing_category_id' => $categoryId,
            'prices' => $row,
        ];
    }

    private function assertDatePattern(string $value, bool $exact): string
    {
        if ($exact) {
            $dt = DateTime::createFromFormat('Y-m-d', $value);
            if ($dt === false) {
                throw new Exception('start_date must be formatted as YYYY-MM-DD', 400);
            }
            return $dt->format('Y-m-d');
        }

        $dt = DateTime::createFromFormat('Y-m', $value);
        if ($dt === false) {
            throw new Exception('month_prefix must be formatted as YYYY-MM', 400);
        }
        return $dt->format('Y-m');
    }
}

