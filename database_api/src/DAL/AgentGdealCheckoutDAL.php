<?php
/**
 * Agent GDeal checkout DAL.
 */

namespace App\DAL;

class AgentGdealCheckoutDAL extends BaseDAL
{
    public function getDates(string $tripId, ?string $exactDate = null, ?string $likeDate = null): array
    {
        $conditions = ['dates.trip_id = ?'];
        $params = [$tripId];

        if ($exactDate) {
            $conditions[] = 'DATE(dates.start_date) > CURRENT_DATE';
            $conditions[] = 'dates.start_date LIKE ?';
            $params[] = $exactDate;
        } elseif ($likeDate) {
            $conditions[] = 'DATE(dates.start_date) > CURRENT_DATE';
            $conditions[] = 'dates.start_date LIKE ?';
            $params[] = $likeDate . '%';
        } else {
            $conditions[] = 'DATE(dates.start_date) > CURRENT_DATE';
        }

        $sql = "
            SELECT dates.start_date, dates.id, dates.pricing_ids
            FROM wpk4_wt_dates dates
            LEFT JOIN wpk4_wt_excluded_dates_times AS exclude
                ON dates.trip_id = exclude.trip_id
               AND dates.start_date = exclude.start_date
            WHERE " . implode(' AND ', $conditions) . "
              AND exclude.start_date IS NULL
            ORDER BY dates.start_date ASC
        ";

        return $this->query($sql, $params);
    }

    public function getPricingById(int $pricingId): ?array
    {
        $sql = "
            SELECT min_pax, max_pax, trip_extras
            FROM wpk4_wt_pricings
            WHERE id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$pricingId]);
        return $row ?: null;
    }

    public function getPriceCategory(int $pricingId, int $categoryId = 953): ?array
    {
        $sql = "
            SELECT regular_price, sale_price
            FROM wpk4_wt_price_category_relation
            WHERE pricing_id = ?
              AND pricing_category_id = ?
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$pricingId, $categoryId]);
        return $row ?: null;
    }
}

