<?php
/**
 * Ticketing dashboard data access.
 */

namespace App\DAL;

class TicketingDashboardDAL extends BaseDAL
{
    public function getCounts(string $startDate, string $endDate): array
    {
        $counts = [];

        $counts['pending_fit'] = (int)($this->queryOne("
            SELECT COUNT(p.auto_id) AS cnt
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_booking_pax p
                ON b.order_id = p.order_id
               AND b.co_order_id = p.co_order_id
               AND b.product_id = p.product_id
            WHERE p.ticketed_by IS NULL
              AND b.travel_date >= ?
              AND LENGTH(p.pnr) <> 9
              AND (LENGTH(p.ticket_number) IS NULL OR LENGTH(p.ticket_number) < 10)
              AND b.payment_status = 'paid'
              AND b.order_type = 'gds'
        ", [$startDate])['cnt'] ?? 0);

        $counts['pending_sq'] = (int)($this->queryOne("
            SELECT COUNT(p.auto_id) AS cnt
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_booking_pax p
                ON b.order_id = p.order_id
               AND b.co_order_id = p.co_order_id
               AND b.product_id = p.product_id
            WHERE p.ticketed_by IS NULL
              AND b.travel_date BETWEEN ? AND ?
              AND b.payment_status = 'paid'
              AND b.trip_code LIKE '%SQ%'
              AND b.order_type <> 'gds'
        ", [$startDate, $endDate])['cnt'] ?? 0);

        $counts['pending_mh'] = (int)($this->queryOne("
            SELECT COUNT(p.auto_id) AS cnt
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_booking_pax p
                ON b.order_id = p.order_id
               AND b.co_order_id = p.co_order_id
               AND b.product_id = p.product_id
            WHERE p.ticketed_by IS NULL
              AND b.travel_date BETWEEN ? AND ?
              AND b.payment_status = 'paid'
              AND b.trip_code LIKE '%MH%'
              AND b.order_type <> 'gds'
        ", [$startDate, $endDate])['cnt'] ?? 0);

        $counts['pending_names'] = (int)($this->queryOne("
            SELECT COUNT(DISTINCT p.auto_id) AS cnt
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_booking_pax p
                ON b.order_id = p.order_id
               AND b.co_order_id = p.co_order_id
               AND b.product_id = p.product_id
            WHERE p.ticketed_on IS NULL
              AND b.payment_status = 'paid'
              AND b.travel_date >= ?
              AND b.order_type <> 'gds'
              AND (b.trip_code LIKE '%SQ%' OR b.trip_code LIKE '%MH%')
              AND p.name_update_check_on IS NULL
        ", [$startDate])['cnt'] ?? 0);

        $counts['pending_audit'] = (int)($this->queryOne("
            SELECT COUNT(*) AS cnt
            FROM wpk4_backend_travel_bookings b
            LEFT JOIN wpk4_backend_travel_booking_pax p
                ON b.order_id = p.order_id
               AND b.co_order_id = p.co_order_id
               AND b.product_id = p.product_id
            WHERE p.ticketed_on IS NOT NULL
              AND b.travel_date >= ?
              AND p.ticketing_audit_on IS NULL
        ", [$startDate])['cnt'] ?? 0);

        $counts['payment_received_canceled'] = (int)($this->queryOne("
            SELECT COUNT(*) AS cnt FROM (
                SELECT b.order_id
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_payment_history p
                    ON b.order_id = p.order_id
                WHERE b.travel_date >= ?
                  AND b.payment_status = 'canceled'
                GROUP BY b.order_id, b.order_date, b.payment_status, b.total_amount
                HAVING CAST(b.total_amount AS FLOAT) = SUM(CAST(p.trams_received_amount AS FLOAT))
                    OR CAST(b.total_amount AS FLOAT) < (SUM(CAST(p.trams_received_amount AS FLOAT)) + 50)
                    OR CAST(b.total_amount AS FLOAT) + 50 < SUM(CAST(p.trams_received_amount AS FLOAT))
            ) matching_orders
        ", [$startDate])['cnt'] ?? 0);

        $counts['gdeals_empty_tkt'] = (int)($this->queryOne("
            SELECT COUNT(p.auto_id) AS cnt
            FROM wpk4_backend_travel_booking_pax p
            JOIN wpk4_backend_travel_bookings b
                ON p.order_id = b.order_id
               AND p.co_order_id = b.co_order_id
               AND p.product_id = b.product_id
            WHERE b.order_type = 'GDeals'
              AND (p.ticket_number IS NULL OR LENGTH(TRIM(p.ticket_number)) = 0)
              AND b.travel_date >= ?
        ", [$startDate])['cnt'] ?? 0);

        return $counts;
    }

    public function getDataset(string $dataset, string $startDate, string $endDate): array
    {
        $sqlMap = [
            'sq_pending' => "
                SELECT
                    p.auto_id AS pax_id,
                    b.order_id,
                    b.payment_modified AS payment_updated_date,
                    b.trip_code,
                    b.travel_date,
                    DATEDIFF(b.travel_date, CURDATE()) AS days_to_departure,
                    CONCAT(p.fname, ' ', p.lname) AS passenger_name,
                    p.ppn AS ptc,
                    p.pnr,
                    p.remarks,
                    p.ticketed_by
                FROM wpk4_backend_travel_booking_pax p
                JOIN wpk4_backend_travel_bookings b
                    ON p.order_id = b.order_id
                   AND b.product_id = p.product_id
                   AND b.co_order_id = p.co_order_id
                WHERE (p.ticketed_by IS NULL OR p.ticketed_by = '')
                  AND b.travel_date BETWEEN ? AND ?
                  AND b.payment_status = 'paid'
                  AND b.trip_code LIKE '%SQ%'
                  AND b.order_type <> 'gds'
                ORDER BY b.travel_date ASC
            ",
            'mh_pending' => "
                SELECT
                    p.auto_id AS pax_id,
                    b.order_id,
                    b.order_date,
                    b.payment_modified AS payment_updated_date,
                    b.trip_code,
                    DATEDIFF(b.travel_date, CURDATE()) AS days_to_departure,
                    UPPER(CONCAT(p.lname, '/', p.fname)) AS passenger_name,
                    CASE WHEN b.product_id IN ('60116', '60107') THEN 'INF' ELSE p.ppn END AS ptc,
                    b.travel_date,
                    p.pnr,
                    p.remarks,
                    p.ticketed_by
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_booking_pax p
                    ON b.order_id = p.order_id
                   AND b.co_order_id = p.co_order_id
                   AND b.product_id = p.product_id
                WHERE (p.ticketed_by IS NULL OR p.ticketed_by = '')
                  AND b.travel_date BETWEEN ? AND ?
                  AND b.payment_status = 'paid'
                  AND b.trip_code LIKE '%MH%'
                  AND b.order_type <> 'gds'
                ORDER BY b.travel_date ASC
            ",
            'fit_pending' => "
                SELECT
                    p.auto_id AS pax_id,
                    b.order_id,
                    b.order_date,
                    b.payment_modified AS payment_updated_date,
                    b.trip_code,
                    DATEDIFF(b.travel_date, CURDATE()) AS days_to_departure,
                    UPPER(CONCAT(p.lname, '/', p.fname)) AS passenger_name,
                    CASE WHEN b.product_id IN ('60116', '60107') THEN 'INF' ELSE p.ppn END AS ptc,
                    b.travel_date,
                    p.pnr,
                    p.additional_remark AS remarks
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_booking_pax p
                    ON b.order_id = p.order_id
                   AND b.co_order_id = p.co_order_id
                   AND b.product_id = p.product_id
                WHERE p.ticketed_by IS NULL
                  AND b.travel_date >= ?
                  AND LENGTH(p.pnr) <> 9
                  AND (LENGTH(p.ticket_number) IS NULL OR LENGTH(p.ticket_number) < 10)
                  AND b.payment_status = 'paid'
                  AND b.order_type = 'gds'
                ORDER BY b.travel_date ASC
            ",
            'penidng_name_to_upload' => "
                SELECT
                    p.auto_id AS pax_id,
                    b.order_id,
                    b.order_date,
                    b.payment_modified AS payment_updated_date,
                    b.trip_code,
                    UPPER(CONCAT(p.lname, '/', p.fname)) AS passenger_name,
                    CASE WHEN b.product_id IN ('60116', '60107') THEN 'INF' END AS ptc,
                    b.travel_date,
                    p.pnr,
                    p.dob,
                    p.adult_order
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_booking_pax p
                    ON b.order_id = p.order_id
                   AND b.co_order_id = p.co_order_id
                   AND b.product_id = p.product_id
                WHERE p.name_update_check_on IS NULL
                  AND p.ticketed_on IS NULL
                  AND b.travel_date >= ?
                  AND b.payment_status = 'paid'
                  AND (b.trip_code LIKE '%MH%' OR b.trip_code LIKE '%SQ%')
                  AND b.order_type <> 'gds'
                ORDER BY b.travel_date ASC
            ",
            'pending_audit' => "
                SELECT
                    CASE WHEN p.order_type = 'gds' THEN 'FIT' ELSE 'Gdeals' END AS order_type,
                    p.order_id,
                    b.order_date,
                    p.pnr,
                    p.ticket_number,
                    CONCAT(p.fname, '/', p.lname) AS passenger_name,
                    b.payment_status,
                    b.trip_code,
                    b.travel_date,
                    p.ticketed_by,
                    p.ticketed_on
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_booking_pax p
                    ON b.order_id = p.order_id
                   AND b.co_order_id = p.co_order_id
                   AND b.product_id = p.product_id
                WHERE p.ticketed_on IS NOT NULL
                  AND b.travel_date >= ?
                  AND p.ticketing_audit_on IS NULL
                ORDER BY b.travel_date ASC
            ",
            'payment_received_canceled' => "
                SELECT
                    b.order_date,
                    b.order_id,
                    b.payment_status,
                    b.total_amount,
                    b.travel_date,
                    SUM(p.trams_received_amount) AS amount_received
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN wpk4_backend_travel_payment_history p
                    ON b.order_id = p.order_id
                WHERE b.travel_date >= ?
                  AND b.payment_status = 'canceled'
                GROUP BY b.order_date, b.order_id, b.payment_status, b.total_amount, b.travel_date
                HAVING CAST(b.total_amount AS FLOAT) = SUM(CAST(p.trams_received_amount AS FLOAT))
                    OR CAST(b.total_amount AS FLOAT) < (SUM(CAST(p.trams_received_amount AS FLOAT)) + 50)
                    OR CAST(b.total_amount AS FLOAT) + 50 < SUM(CAST(p.trams_received_amount AS FLOAT))
                ORDER BY b.order_date ASC
            ",
            'gdeals_empty_tkt' => "
                SELECT
                    p.auto_id AS pax_id,
                    b.order_id,
                    b.order_date,
                    b.payment_modified AS payment_updated_date,
                    b.trip_code,
                    b.travel_date,
                    DATEDIFF(b.travel_date, CURDATE()) AS days_to_departure,
                    CONCAT(p.fname, ' ', p.lname) AS passenger_name,
                    p.ppn AS ptc,
                    p.pnr,
                    p.ticketed_on,
                    p.ticketed_by,
                    p.remarks
                FROM wpk4_backend_travel_booking_pax p
                JOIN wpk4_backend_travel_bookings b
                    ON p.order_id = b.order_id
                   AND p.co_order_id = b.co_order_id
                   AND p.product_id = b.product_id
                WHERE b.order_type = 'GDeals'
                  AND (p.ticket_number IS NULL OR LENGTH(TRIM(p.ticket_number)) = 0)
                  AND b.travel_date >= ?
                ORDER BY b.travel_date ASC
            ",
        ];

        if (!isset($sqlMap[$dataset])) {
            return [];
        }

        $sql = $sqlMap[$dataset];
        $params = match ($dataset) {
            'sq_pending', 'mh_pending' => [$startDate, $endDate],
            'penidng_name_to_upload', 'pending_audit', 'payment_received_canceled', 'gdeals_empty_tkt', 'pending_fit', 'fit_pending' => [$startDate],
            default => [$startDate],
        };

        return $this->query($sql, $params);
    }
}

