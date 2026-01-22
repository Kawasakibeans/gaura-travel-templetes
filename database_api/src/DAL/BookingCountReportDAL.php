<?php
/**
 * Data access for booking count report.
 */

namespace App\DAL;

class BookingCountReportDAL extends BaseDAL
{
    /**
     * @return array<string,mixed>|null
     */
    public function getStatusCounts(string $start, string $end, ?string $orderType = null): ?array
    {
        $sql = "
            SELECT
              COUNT(DISTINCT CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax_total,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'paid'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS paid,
              COUNT(DISTINCT CASE WHEN a.payment_status IN ('partially_paid','pending')
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS partially_paid,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'canceled'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS canceled,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'N/A'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS na_failed,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'refund'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS refund,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'waiting_voucher'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS waiting_voucher,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'voucher_submited'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS voucher_submited,
              COUNT(DISTINCT CASE WHEN a.payment_status = 'receipt_received'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS receipt_received,
              COUNT(DISTINCT CASE WHEN a.payment_status NOT IN ('paid','partially_paid','pending','canceled','N/A','refund','waiting_voucher','voucher_submited','receipt_received')
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS unknowns,
              COUNT(DISTINCT CASE WHEN LOWER(a.order_type) = 'gds'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS fit,
              COUNT(DISTINCT CASE WHEN LOWER(a.order_type) = 'wpt'
                   THEN CONCAT_WS('-', b.fname, b.lname, a.agent_info, DATE(a.order_date)) END) AS wpt
            FROM wpk4_backend_travel_bookings a
            INNER JOIN wpk4_backend_travel_booking_pax b
              ON a.order_id = b.order_id
             AND TIMESTAMPDIFF(YEAR, b.dob, a.travel_date) >= 2
            WHERE DATE(a.order_date) BETWEEN ? AND ?
        ";

        $params = [$start, $end];
        if ($orderType) {
            $sql .= " AND LOWER(a.order_type) = ?";
            $params[] = strtolower($orderType);
        }

        return $this->queryOne($sql, $params);
    }

    public function getUniquePaxCount(string $start, string $end, ?string $orderType = null): int
    {
        $sql = "
            SELECT COUNT(*) AS pax_cnt
            FROM (
                SELECT DISTINCT p.order_id, p.fname, p.lname, p.dob
                FROM wpk4_backend_travel_booking_pax p
                INNER JOIN (
                    SELECT b.order_id, MIN(b.travel_date) AS travel_date
                    FROM wpk4_backend_travel_bookings b
                    WHERE DATE(b.order_date) BETWEEN ? AND ?
                      AND b.source <> 'import'
        ";

        $params = [$start, $end];
        if ($orderType) {
            $sql .= " AND LOWER(b.order_type) = ?";
            $params[] = strtolower($orderType);
        }

        $sql .= "
                    GROUP BY b.order_id
                ) bo ON bo.order_id = p.order_id
                WHERE bo.travel_date IS NOT NULL
                  AND p.dob IS NOT NULL
                  AND p.dob <> '0000-00-00'
                  AND TIMESTAMPDIFF(YEAR, p.dob, bo.travel_date) >= 2
            ) s
        ";

        $row = $this->queryOne($sql, $params);
        return (int)($row['pax_cnt'] ?? 0);
    }
}

