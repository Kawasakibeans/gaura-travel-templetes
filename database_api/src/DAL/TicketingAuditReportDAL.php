<?php
/**
 * Ticketing & audit report data access.
 */

namespace App\DAL;

class TicketingAuditReportDAL extends BaseDAL
{
    private function issuedDetailBase(): string
    {
        return "
            FROM wpk4_backend_travel_bookings orderpax
            LEFT JOIN wpk4_backend_travel_booking_pax Pax
                ON orderpax.order_id = Pax.order_id
               AND orderpax.co_order_id = Pax.co_order_id
               AND orderpax.product_id = Pax.product_id
        ";
    }

    private function issuedDetailConditions(): array
    {
        return [
            "Pax.ticketed_on IS NOT NULL",
            "Pax.ticketed_on BETWEEN ? AND ?",
            "Pax.ticketed_by <> 'joyce'",
            "orderpax.payment_status = 'paid'",
        ];
    }

    private function buildSearchClause(?string $search, array &$params): string
    {
        if ($search === null || $search === '') {
            return '';
        }

        $like = '%' . $search . '%';
        // orderpax.order_id, Pax.pnr, orderpax.source, orderpax.trip_code, CONCAT(lname,'/',fname)
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;

        return "
            AND (
                orderpax.order_id LIKE ?
                OR Pax.pnr LIKE ?
                OR orderpax.source LIKE ?
                OR orderpax.trip_code LIKE ?
                OR CONCAT(Pax.lname,'/',Pax.fname) LIKE ?
            )
        ";
    }

    /**
     * Detailed issued tickets (DataTables style).
     *
     * @return array{records_total:int, records_filtered:int, data:array<int,array<string,mixed>>}
     */
    public function getIssuedDetail(array $filters): array
    {
        $start = $filters['start_date'] . ' 00:00:00';
        $end = $filters['end_date'] . ' 23:59:59';
        $offset = (int)$filters['offset'];
        $limit = (int)$filters['limit'];
        $search = $filters['search'] ?? null;

        $baseConditions = $this->issuedDetailConditions();
        $baseParams = [$start, $end];

        $baseWhere = 'WHERE ' . implode(' AND ', $baseConditions);

        // Total records (without search)
        $totalSql = "
            SELECT COUNT(DISTINCT Pax.auto_id) AS total
            " . $this->issuedDetailBase() . "
            $baseWhere
        ";
        $totalRow = $this->queryOne($totalSql, $baseParams);
        $recordsTotal = (int)($totalRow['total'] ?? 0);

        // Apply search for filtered/data
        $filteredParams = $baseParams;
        $searchClause = $this->buildSearchClause($search, $filteredParams);

        $whereWithSearch = $baseWhere . $searchClause;

        $filteredSql = "
            SELECT COUNT(DISTINCT Pax.auto_id) AS total
            " . $this->issuedDetailBase() . "
            $whereWithSearch
        ";
        $filteredRow = $this->queryOne($filteredSql, $filteredParams);
        $recordsFiltered = (int)($filteredRow['total'] ?? 0);

        $orderMap = [
            'ticketed_on' => 'Pax.ticketed_on',
            'order_type' => 'order_type',
            'order_id' => 'orderpax.order_id',
            'pnr' => 'Pax.pnr',
            'source' => 'source_alias',
            'order_date' => 'orderpax.order_date',
            'payment_modified' => 'orderpax.payment_modified',
            'trip_code' => 'orderpax.trip_code',
            'travel_date' => 'orderpax.travel_date',
            'salutation' => 'Pax.salutation',
            'fname' => 'fname_alias',
            'dob' => 'Pax.dob',
            'ticket_number' => 'Pax.ticket_number',
            'ticketed_by' => 'Pax.ticketed_by',
            'ticketing_audit_on' => 'Pax.ticketing_audit_on',
        ];

        $orderColumn = $orderMap[$filters['order_column']] ?? 'Pax.ticketed_on';
        $orderDirection = strtoupper($filters['order_direction']) === 'DESC' ? 'DESC' : 'ASC';

        $dataSql = "
            SELECT DISTINCT
                DATE_FORMAT(Pax.ticketed_on, '%d/%m/%Y') AS ticketed_on,
                CASE WHEN orderpax.order_type = 'gds' THEN 'FIT' ELSE 'Gdeals' END AS order_type,
                orderpax.order_id,
                Pax.pnr,
                CASE
                    WHEN orderpax.source = 'gaurainn' THEN 'CCUVS32NQ'
                    WHEN orderpax.source = 'gaura' THEN 'I5FC'
                    WHEN orderpax.source = 'gauraaws' THEN 'MELA821FN'
                    WHEN orderpax.source = 'gaurandc' THEN 'MELA821FN'
                    WHEN orderpax.source = 'wpwebsite' AND MID(orderpax.trip_code,9,2) = 'MH' THEN 'MELA821CV'
                    WHEN orderpax.source = 'wpwebsite' AND MID(orderpax.trip_code,9,2) = 'SQ' THEN 'CCUVS32MV'
                    WHEN orderpax.order_type = 'Agent' AND MID(orderpax.trip_code,9,2) = 'SQ' THEN 'CCUVS32MV'
                    WHEN orderpax.order_type = 'Agent' AND MID(orderpax.trip_code,9,2) = 'MH' THEN 'MELA821CV'
                    ELSE orderpax.source
                END AS source_alias,
                DATE_FORMAT(orderpax.order_date, '%d/%m/%Y') AS order_date,
                DATE_FORMAT(orderpax.payment_modified, '%d/%m/%Y %H:%i:%s') AS payment_modified,
                orderpax.trip_code,
                DATE_FORMAT(orderpax.travel_date, '%d/%m/%Y') AS travel_date,
                Pax.salutation,
                CONCAT(Pax.lname,'/',Pax.fname) AS fname_alias,
                Pax.dob,
                Pax.ticket_number,
                Pax.ticketed_by,
                Pax.ticketing_audit_on
            " . $this->issuedDetailBase() . "
            $whereWithSearch
            ORDER BY $orderColumn $orderDirection
            LIMIT ?, ?
        ";

        $dataParams = array_merge($filteredParams, [$offset, $limit]);
        $data = $this->query($dataSql, $dataParams);

        return [
            'records_total' => $recordsTotal,
            'records_filtered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    public function getTicketIssuedSummary(string $start, string $end, int $offset, int $limit): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $where = "WHERE Pax.ticketed_on BETWEEN ? AND ?";

        $select = "
            SELECT
                DATE_FORMAT(Pax.ticketed_on, '%d/%m/%Y') AS ticketed_on,
                Pax.ticketed_by,
                SUM(CASE WHEN orderpax.order_type = 'gds' THEN 1 ELSE 0 END) AS fit,
                SUM(CASE WHEN orderpax.order_type != 'gds' THEN 1 ELSE 0 END) AS gdeals,
                COUNT(Pax.auto_id) AS total
            " . $this->issuedDetailBase() . "
            $where
            GROUP BY DATE_FORMAT(Pax.ticketed_on, '%d/%m/%Y'), Pax.ticketed_by, DATE(Pax.ticketed_on)
            ORDER BY DATE(Pax.ticketed_on) ASC, COUNT(Pax.auto_id) DESC
            LIMIT ?, ?
        ";

        $data = $this->query($select, array_merge($params, [$offset, $limit]));

        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                " . $this->issuedDetailBase() . "
                $where
                GROUP BY DATE_FORMAT(Pax.ticketed_on, '%d/%m/%Y'), Pax.ticketed_by
            ) t
        ";
        $totalRow = $this->queryOne($countSql, $params);

        return [
            'data' => $data,
            'total' => (int)($totalRow['cnt'] ?? 0),
        ];
    }

    public function getTicketAuditedSummary(string $start, string $end, int $offset, int $limit): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $where = "WHERE Pax.ticketing_audit_on BETWEEN ? AND ?";

        $select = "
            SELECT
                DATE_FORMAT(Pax.ticketing_audit_on, '%d/%m/%Y') AS ticketing_audit_on,
                Pax.ticketing_audit AS ticketing_audit_by,
                SUM(CASE WHEN orderpax.order_type = 'gds' THEN 1 ELSE 0 END) AS fit,
                SUM(CASE WHEN orderpax.order_type != 'gds' THEN 1 ELSE 0 END) AS gdeals,
                COUNT(Pax.auto_id) AS total
            " . $this->issuedDetailBase() . "
            $where
            GROUP BY DATE_FORMAT(Pax.ticketing_audit_on, '%d/%m/%Y'), Pax.ticketing_audit, DATE(Pax.ticketing_audit_on)
            ORDER BY DATE(Pax.ticketing_audit_on) ASC, COUNT(Pax.auto_id) DESC
            LIMIT ?, ?
        ";

        $data = $this->query($select, array_merge($params, [$offset, $limit]));

        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                " . $this->issuedDetailBase() . "
                $where
                GROUP BY DATE_FORMAT(Pax.ticketing_audit_on, '%d/%m/%Y'), Pax.ticketing_audit
            ) t
        ";
        $totalRow = $this->queryOne($countSql, $params);

        return [
            'data' => $data,
            'total' => (int)($totalRow['cnt'] ?? 0),
        ];
    }

    public function getAuditedTickets(string $start, string $end, int $offset, int $limit): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $where = "WHERE Pax.ticketing_audit_on IS NOT NULL
                  AND Pax.ticketing_audit_on BETWEEN ? AND ?";

        $select = "
            SELECT
                Pax.ticketing_audit,
                Pax.ticketing_audit_on,
                Pax.order_id,
                orderpax.order_date,
                CASE WHEN orderpax.order_type = 'gds' THEN 'FIT' ELSE 'Gdeals' END AS order_type,
                Pax.pnr,
                Pax.ticket_number,
                CONCAT(Pax.fname,'/',Pax.lname) AS passenger_name,
                orderpax.payment_status,
                orderpax.trip_code,
                orderpax.travel_date AS travel_date,
                Pax.ticketed_by,
                Pax.ticketed_on
            " . $this->issuedDetailBase() . "
            $where
            ORDER BY Pax.ticketing_audit_on ASC
            LIMIT ?, ?
        ";

        $data = $this->query($select, array_merge($params, [$offset, $limit]));

        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                " . $this->issuedDetailBase() . "
                $where
            ) t
        ";
        $totalRow = $this->queryOne($countSql, $params);

        return [
            'data' => $data,
            'total' => (int)($totalRow['cnt'] ?? 0),
        ];
    }

    public function getNameUpdatesSummary(string $start, string $end, int $offset, int $limit): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $where = "WHERE Pax.name_update_check_on BETWEEN ? AND ?";

        $select = "
            SELECT
                DATE_FORMAT(Pax.name_update_check_on, '%d/%m/%Y') AS name_updated_on,
                Pax.name_update_check AS name_updated_by,
                SUM(CASE WHEN orderpax.order_type = 'gds' THEN 1 ELSE 0 END) AS fit,
                SUM(CASE WHEN orderpax.order_type != 'gds' THEN 1 ELSE 0 END) AS gdeals,
                COUNT(Pax.auto_id) AS total
            " . $this->issuedDetailBase() . "
            $where
            GROUP BY DATE_FORMAT(Pax.name_update_check_on, '%d/%m/%Y'), Pax.name_update_check, DATE(Pax.name_update_check_on)
            ORDER BY DATE(Pax.name_update_check_on) ASC, COUNT(Pax.auto_id) DESC
            LIMIT ?, ?
        ";

        $data = $this->query($select, array_merge($params, [$offset, $limit]));

        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                " . $this->issuedDetailBase() . "
                $where
                GROUP BY DATE_FORMAT(Pax.name_update_check_on, '%d/%m/%Y'), Pax.name_update_check
            ) t
        ";
        $totalRow = $this->queryOne($countSql, $params);

        return [
            'data' => $data,
            'total' => (int)($totalRow['cnt'] ?? 0),
        ];
    }

    public function getUpdatedNames(string $start, string $end, int $offset, int $limit): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $where = "WHERE Pax.name_update_check_on IS NOT NULL
                  AND Pax.name_update_check_on BETWEEN ? AND ?
                  AND orderpax.order_type <> 'gds'";

        $select = "
            SELECT
                Pax.name_update_check AS name_updated_by,
                Pax.name_update_check_on AS name_updated_on,
                Pax.order_id,
                orderpax.order_date,
                'Gdeals' AS order_type,
                Pax.pnr,
                Pax.ticket_number,
                CONCAT(Pax.fname,'/',Pax.lname) AS passenger_name,
                orderpax.payment_status,
                orderpax.trip_code,
                orderpax.travel_date AS travel_date,
                Pax.ticketed_by,
                Pax.ticketed_on
            " . $this->issuedDetailBase() . "
            $where
            ORDER BY Pax.name_update_check_on ASC
            LIMIT ?, ?
        ";

        $data = $this->query($select, array_merge($params, [$offset, $limit]));

        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT 1
                " . $this->issuedDetailBase() . "
                $where
            ) t
        ";
        $totalRow = $this->queryOne($countSql, $params);

        return [
            'data' => $data,
            'total' => (int)($totalRow['cnt'] ?? 0),
        ];
    }
}

