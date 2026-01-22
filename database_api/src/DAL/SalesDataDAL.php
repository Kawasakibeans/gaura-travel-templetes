<?php
/**
 * Sales data data-access layer
 */

namespace App\DAL;

class SalesDataDAL extends BaseDAL
{
    /**
     * Fetch upcoming seat availability with optional filters.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingSeats(array $filters, int $limit = 100): array
    {
        $sql = "
            SELECT
                sa.trip_code,
                sa.travel_date,
                (sa.stock - sa.pax) AS remaining,
                wp.sale_price,
                MID(sa.trip_code, 9, 2) AS airline_code
            FROM wpk4_backend_manage_seat_availability sa
            LEFT JOIN wpk4_wt_price_category_relation wp
                ON sa.pricing_id = wp.pricing_id
            WHERE sa.travel_date > ?
              AND (sa.stock - sa.pax) > ?
              AND wp.sale_price IS NOT NULL
        ";

        $params = [
            $filters['travel_date_from'],
            $filters['min_remaining'],
        ];

        if (!empty($filters['travel_date_to'])) {
            $sql .= " AND sa.travel_date <= ? ";
            $params[] = $filters['travel_date_to'];
        }

        if (!empty($filters['pricing_category_id'])) {
            $sql .= " AND wp.pricing_category_id = ? ";
            $params[] = $filters['pricing_category_id'];
        }

        if (!empty($filters['airline_code'])) {
            $sql .= " AND MID(sa.trip_code, 9, 2) = ? ";
            $params[] = strtoupper($filters['airline_code']);
        }

        if (!empty($filters['trip_code_like'])) {
            $sql .= " AND sa.trip_code LIKE ? ";
            $params[] = $filters['trip_code_like'];
        }

        $sql .= "
            ORDER BY
                CAST(wp.sale_price AS DECIMAL(10, 2)) ASC,
                sa.travel_date ASC,
                sa.trip_code ASC
            LIMIT ?
        ";

        $params[] = $limit;

        return $this->query($sql, $params);
    }
}

