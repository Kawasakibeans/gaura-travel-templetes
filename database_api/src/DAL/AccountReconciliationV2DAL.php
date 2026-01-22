<?php
/**
 * Account Reconciliation V2 DAL
 * Provides database access for the account reconciliation consolidation endpoint
 */

namespace App\DAL;

class AccountReconciliationV2DAL extends BaseDAL
{
    /**
     * Fetch ticket records with calculated amounts
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getTicketRows(array $filters): array
    {
        $params = [
            ':issue_start' => $filters['issue_start_date'],
            ':issue_end' => $filters['issue_end_date'],
        ];

        $sql = "
            SELECT
                RIGHT(t.document, 10) AS ticket_no,
                t.issue_date AS issued_date,
                t.pax_id,
                t.document_type,
                t.order_id,
                t.pnr,
                t.confirmed,
                CASE 
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin') THEN 'INR'
                    ELSE 'AUD'
                END AS currency,
                CASE 
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                        THEN (CAST(t.transaction_amount AS DECIMAL(18,6)) / 55) 
                             - (CAST(t.tax AS DECIMAL(18,6)) / 55) 
                             - (CAST(t.fee AS DECIMAL(18,6)) / 55)
                    ELSE CAST(t.transaction_amount AS DECIMAL(18,6))
                         - CAST(t.tax AS DECIMAL(18,6))
                         - CAST(t.fee AS DECIMAL(18,6))
                END AS base_fare,
                CASE 
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                        THEN CAST(t.tax AS DECIMAL(18,6)) / 55
                    ELSE CAST(t.tax AS DECIMAL(18,6))
                END AS tax,
                CASE 
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                        THEN CAST(t.fee AS DECIMAL(18,6)) / 55
                    ELSE CAST(t.fee AS DECIMAL(18,6))
                END AS fee,
                CASE
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                         AND t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                        THEN CAST(t.transaction_amount AS DECIMAL(18,6)) / 55
                    WHEN t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                        THEN CAST(t.transaction_amount AS DECIMAL(18,6))
                    ELSE 0
                END AS total_amount,
                CASE
                    WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                         AND t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                        THEN CAST(t.transaction_amount AS DECIMAL(18,6)) / 55 - CAST(t.comm AS DECIMAL(18,6))
                    WHEN t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                        THEN CAST(t.transaction_amount AS DECIMAL(18,6)) - CAST(t.comm AS DECIMAL(18,6))
                    ELSE 0
                END AS net_due_old,
                CAST(t.comm AS DECIMAL(18,6)) AS commission_amt,
                t.vendor AS issued_by
            FROM wpk4_backend_travel_booking_ticket_number t
            WHERE t.issue_date BETWEEN :issue_start AND :issue_end
        ";

        $sql .= $this->buildTicketFilterSql($filters, $params);
        $sql .= " ORDER BY t.issue_date DESC, t.order_id ASC";

        return $this->query($sql, $params);
    }

    /**
     * Fetch booking rows for order level data
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getBookingRows(array $filters): array
    {
        $params = [
            ':order_start' => $filters['order_start_date'],
            ':order_end' => $filters['order_end_date'],
        ];

        $sql = "
            SELECT
                MID(b.trip_code, 9, 2) AS airline_code,
                b.order_id,
                CASE 
                    WHEN b.payment_status = 'paid' THEN 'Confirmed'
                    ELSE 'Pending'
                END AS client_status,
                b.order_date,
                b.travel_date,
                b.agent_info AS booked_by,
                b.return_date
            FROM wpk4_backend_travel_bookings b
            WHERE b.order_date BETWEEN :order_start AND :order_end
        ";

        if (!empty($filters['order_no'])) {
            $sql .= " AND b.order_id LIKE :order_no";
            $params[':order_no'] = '%' . $filters['order_no'] . '%';
        }

        if (!empty($filters['airline'])) {
            $sql .= " AND MID(b.trip_code, 9, 2) LIKE :airline";
            $params[':airline'] = '%' . $filters['airline'] . '%';
        }

        if (!empty($filters['travel_date'])) {
            $sql .= " AND b.travel_date = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        }

        return $this->query($sql, $params);
    }

    /**
     * Fetch passenger rows keyed by pax id
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getPassengerRows(array $filters): array
    {
        $params = [
            ':order_start' => $filters['order_start_date'],
            ':order_end' => $filters['order_end_date'],
        ];

        $sql = "
            SELECT
                bp.auto_id AS pax_id,
                bp.lname AS pax_surname,
                bp.fname AS pax_firstname,
                bp.ticketed_by AS issued_by,
                bp.ticketed_on AS issued_date
            FROM wpk4_backend_travel_booking_pax bp
            WHERE bp.order_date BETWEEN :order_start AND :order_end
        ";

        if (!empty($filters['order_no'])) {
            $sql .= " AND bp.order_id LIKE :order_no";
            $params[':order_no'] = '%' . $filters['order_no'] . '%';
        }

        return $this->query($sql, $params);
    }

    /**
     * Fetch aggregated payment records by order
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentRows(array $filters): array
    {
        $params = [
            ':order_start' => $filters['order_start_date'],
            ':order_end' => $filters['order_end_date'],
        ];

        $sql = "
            SELECT
                p.order_id,
                SUM(CAST(p.trams_received_amount AS DECIMAL(18,6))) AS received_amount
            FROM wpk4_backend_travel_payment_history p
            WHERE p.process_date BETWEEN :order_start AND :order_end
        ";

        if (!empty($filters['order_no'])) {
            $sql .= " AND p.order_id LIKE :order_no";
            $params[':order_no'] = '%' . $filters['order_no'] . '%';
        }

        $sql .= " GROUP BY p.order_id";

        return $this->query($sql, $params);
    }

    /**
     * Fetch ticket totals per order (total transaction amount and unique count)
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getOrderTotals(array $filters): array
    {
        $params = [
            ':issue_start' => $filters['issue_start_date'],
            ':issue_end' => $filters['issue_end_date'],
        ];

        $sql = "
            SELECT
                t.order_id,
                SUM(
                    CASE
                        WHEN t.vendor IN ('IFN IATA', 'Gilpin')
                             AND t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                            THEN CAST(t.transaction_amount AS DECIMAL(18,6)) / 55
                        WHEN t.document_type IN ('TKTT','TKT','EMDS','EMDA','EMD','Ticket')
                            THEN CAST(t.transaction_amount AS DECIMAL(18,6))
                        ELSE 0
                    END
                ) AS total_transaction_amount,
                COUNT(DISTINCT CONCAT(t.document, ':', t.document_type, ':', t.pnr)) AS unique_count
            FROM wpk4_backend_travel_booking_ticket_number t
            WHERE t.issue_date BETWEEN :issue_start AND :issue_end
        ";

        $sql .= $this->buildTicketFilterSql($filters, $params, 't', '_totals');
        $sql .= " GROUP BY t.order_id";

        return $this->query($sql, $params);
    }

    /**
     * Build reusable ticket filter SQL fragments
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    private function buildTicketFilterSql(array $filters, array &$params, string $prefix = 't', string $suffix = ''): string
    {
        $sql = '';

        if (!empty($filters['ticket_no'])) {
            $param = ':ticket_no' . $suffix;
            $sql .= " AND RIGHT({$prefix}.document, 10) LIKE {$param}";
            $params[$param] = '%' . $filters['ticket_no'] . '%';
        }

        if (!empty($filters['issued_via'])) {
            $param = ':issued_via' . $suffix;
            $sql .= " AND {$prefix}.vendor = {$param}";
            $params[$param] = $filters['issued_via'];
        }

        if (!empty($filters['pnr'])) {
            $param = ':pnr' . $suffix;
            $sql .= " AND {$prefix}.pnr LIKE {$param}";
            $params[$param] = '%' . $filters['pnr'] . '%';
        }

        if (!empty($filters['confirmed'])) {
            $param = ':confirmed' . $suffix;
            $sql .= " AND {$prefix}.confirmed LIKE {$param}";
            $params[$param] = '%' . $filters['confirmed'] . '%';
        }

        if (!empty($filters['order_no'])) {
            $param = ':order_no_filter' . $suffix;
            $sql .= " AND {$prefix}.order_id LIKE {$param}";
            $params[$param] = '%' . $filters['order_no'] . '%';
        }

        return $sql;
    }
}

