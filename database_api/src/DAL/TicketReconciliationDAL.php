<?php
/**
 * Ticket Reconciliation DAL
 * Handles database access for ticket reconciliation datasets, logs, and payments
 */

namespace App\DAL;

use Exception;

class TicketReconciliationDAL extends BaseDAL
{
    private const TICKET_TABLE = 'wpk4_backend_ticket_reconciliation';
    private const LOG_TABLE = 'wpk4_ticket_update_logs';
    private const ORDER_PAYMENT_TABLE = 'wpk4_backend_travel_payment_history';
    private const PROFILE_PAYMENT_TABLE = 'wpk4_backend_account_trams_payments';
    private const BANK_TABLE = 'wpk4_backend_accounts_bank_account';

    /**
     * Fetch ticket reconciliation rows with filters and optional pagination
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchTickets(array $filters, ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = $this->buildTicketQuery($filters, false, $limit, $offset);
        return $this->query($sql, $params);
    }

    /**
     * Count ticket reconciliation rows for pagination metadata
     *
     * @param array<string, mixed> $filters
     */
    public function countTickets(array $filters): int
    {
        [$sql, $params] = $this->buildTicketQuery($filters, true);
        $row = $this->queryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Fetch history logs for an auto_id
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $autoId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        try {
            $sql = "
                SELECT 
                    user_id,
                    user_name,
                    action,
                    field,
                    COALESCE(old_value, '') AS old_value,
                    COALESCE(new_value, '') AS new_value,
                    COALESCE(description, '') AS description,
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at
                FROM " . self::LOG_TABLE . "
                WHERE auto_id = :auto_id
                ORDER BY created_at DESC
                LIMIT {$limit}
            ";

            return $this->query($sql, [':auto_id' => $autoId]);
        } catch (Exception $e) {
            // Log table doesn't exist - return empty array
            return [];
        }
    }

    /**
     * Insert a remark into the update log
     */
    public function addRemark(int $autoId, string $remark, int $userId, string $userName): bool
    {
        try {
            $sql = "
                INSERT INTO " . self::LOG_TABLE . " 
                (auto_id, user_id, user_name, action, field, old_value, new_value, description, created_at)
                VALUES (:auto_id, :user_id, :user_name, 'remark', 'remark', '', :remark, :description, NOW())
            ";

            return $this->execute($sql, [
                ':auto_id' => $autoId,
                ':user_id' => $userId,
                ':user_name' => $userName,
                ':remark' => $remark,
                ':description' => "Remark added by {$userName}",
            ]);
        } catch (Exception $e) {
            // Log table doesn't exist - return false but don't throw exception
            // The remark functionality will still work, just won't be logged
            return false;
        }
    }

    /**
     * Update a ticket column and write to log
     */
    public function updateTicketColumn(
        int $autoId,
        string $column,
        $newValue,
        int $userId,
        string $userName
    ): array {
        $previous = $this->getPreviousValue($autoId, $column);

        if ($previous !== null && (string)$previous === (string)$newValue) {
            return [
                'updated' => false,
                'message' => 'No change in value',
                'previous_value' => $previous,
                'new_value' => $newValue,
            ];
        }

        $this->beginTransaction();

        try {
            $updateSql = "UPDATE " . self::TICKET_TABLE . " SET {$column} = :value WHERE auto_id = :auto_id";
            $this->execute($updateSql, [
                ':value' => $newValue,
                ':auto_id' => $autoId,
            ]);

            // Try to log the update, but don't fail if the log table doesn't exist
            try {
                $logSql = "
                    INSERT INTO " . self::LOG_TABLE . "
                    (auto_id, user_id, user_name, action, field, old_value, new_value, description, created_at)
                    VALUES (:auto_id, :user_id, :user_name, 'update', :field, :old_value, :new_value, :description, NOW())
                ";

                $this->execute($logSql, [
                    ':auto_id' => $autoId,
                    ':user_id' => $userId,
                    ':user_name' => $userName,
                    ':field' => $column,
                    ':old_value' => $previous === null ? '' : (string)$previous,
                    ':new_value' => (string)$newValue,
                    ':description' => "Edited by {$userName}",
                ]);
            } catch (Exception $logException) {
                // Log table doesn't exist or logging failed - continue without logging
                // The main update was successful, so we don't throw the exception
            }

            $this->commit();

            return [
                'updated' => true,
                'previous_value' => $previous,
                'new_value' => $newValue,
            ];
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Retrieve payment history by order IDs
     *
     * @param array<int, string> $orderIds
     * @return array{rows: array<int, array<string, mixed>>, total_received: float}
     */
    public function getOrderPayments(array $orderIds): array
    {
        if (empty($orderIds)) {
            return ['rows' => [], 'total_received' => 0.0];
        }

        $placeholders = [];
        $params = [];
        foreach ($orderIds as $idx => $orderId) {
            $key = ':order_id_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $sql = "
            SELECT 
                p.order_id,
                CAST(p.trams_received_amount AS DECIMAL(18,6)) AS trams_received_amount,
                b.account_name AS payment_method,
                DATE_FORMAT(p.process_date, '%Y-%m-%d %H:%i:%s') AS process_date
            FROM " . self::ORDER_PAYMENT_TABLE . " p
            LEFT JOIN " . self::BANK_TABLE . " b 
                ON p.payment_method = b.bank_id
            WHERE CAST(p.trams_received_amount AS DECIMAL(18,6)) <> 0
              AND p.order_id IN (" . implode(',', $placeholders) . ")
            ORDER BY p.process_date DESC
        ";

        $rows = $this->query($sql, $params);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['trams_received_amount'];
        }

        return ['rows' => $rows, 'total_received' => round($total, 2)];
    }

    /**
     * Retrieve payment history by profile numbers
     *
     * @param array<int, string> $profileNos
     * @return array{rows: array<int, array<string, mixed>>, total_received: float}
     */
    public function getProfilePayments(array $profileNos, int $limit = 500): array
    {
        if (empty($profileNos)) {
            return ['rows' => [], 'total_received' => 0.0];
        }

        $limit = max(1, min(500, $limit));

        $placeholders = [];
        $params = [];
        foreach ($profileNos as $idx => $profile) {
            $key = ':profile_no_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $profile;
        }

        $sql = "
            SELECT
                p.profile_linkno AS profile_no,
                CAST(p.amount / 100 AS DECIMAL(18,6)) AS trams_received_amount,
                b.account_name AS payment_method,
                DATE_FORMAT(p.paymentdate, '%Y-%m-%d %H:%i:%s') AS process_date
            FROM " . self::PROFILE_PAYMENT_TABLE . " p
            LEFT JOIN " . self::BANK_TABLE . " b
                ON p.PAYMETHOD_LINKNO = b.bank_id
            WHERE CAST(p.amount / 100 AS DECIMAL(18,6)) <> 0
              AND p.profile_linkno IN (" . implode(',', $placeholders) . ")
            ORDER BY p.PAYMENTNO DESC
            LIMIT {$limit}
        ";

        $rows = $this->query($sql, $params);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['trams_received_amount'];
        }

        return ['rows' => $rows, 'total_received' => round($total, 2)];
    }

    /**
     * Retrieve the previous value of a column for comparison
     */
    public function getPreviousValue(int $autoId, string $column)
    {
        $sql = "SELECT {$column} AS value FROM " . self::TICKET_TABLE . " WHERE auto_id = :auto_id";
        $row = $this->queryOne($sql, [':auto_id' => $autoId]);
        return $row['value'] ?? null;
    }

    /**
     * Build the ticket query (select or count)
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildTicketQuery(
        array $filters,
        bool $forCount = false,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $params = [
            ':issue_start' => $filters['issue_start_date'],
            ':issue_end' => $filters['issue_end_date'],
        ];

        if ($forCount) {
            $select = "SELECT COUNT(*) AS total";
        } else {
            $select = "
                SELECT
                    t.auto_id,
                    t.profile_no,
                    t.invoice_no,
                    COALESCE(CONCAT(RIGHT(t.document, 10), t.remark), RIGHT(t.document, 10)) AS ticket_no,
                    t.issue_date,
                    t.remark,
                    t.pax_id,
                    t.document_type,
                    t.a_l AS airline_code,
                    CASE 
                        WHEN LOWER(t.confirmed) IN ('confirmed','paid','reconciled') THEN 'Paid'
                        ELSE 'Due'
                    END AS client_status,
                    t.order_date,
                    t.travel_date,
                    t.order_id,
                    t.net_due,
                    t.lname,
                    t.fname,
                    t.order_amnt,
                    t.pnr,
                    CASE 
                        WHEN LOWER(t.confirmed) IN ('confirmed','paid','reconciled') THEN 'Confirmed'
                        WHEN LOWER(t.confirmed) IN ('pending','due','unpaid') THEN 'Pending'
                        ELSE t.confirmed
                    END AS confirmed_status,
                    t.fare_inr,
                    t.comm_inr,
                    t.tax_inr,
                    t.transaction_amount_inr,
                    t.delete_request,
                    CASE 
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN 'INR'
                        ELSE 'AUD'
                    END AS currency,
                    CASE 
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN CAST(t.fare AS DECIMAL(18,6))/55
                        ELSE CAST(t.fare AS DECIMAL(18,6))
                    END AS base_fare,
                    CASE 
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN CAST(t.tax AS DECIMAL(18,6))/55
                        ELSE CAST(t.tax AS DECIMAL(18,6))
                    END AS tax,
                    CASE 
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN CAST(t.fee AS DECIMAL(18,6))/55
                        ELSE CAST(t.fee AS DECIMAL(18,6))
                    END AS fee,
                    CASE
                        WHEN t.issue_date BETWEEN '2021-07-01' AND '2022-06-30' THEN CAST(t.transaction_amount AS DECIMAL(18,6))
                        WHEN t.issue_date BETWEEN '2022-07-01' AND '2023-06-30' THEN CAST(t.transaction_amount AS DECIMAL(18,6))
                        WHEN t.issue_date BETWEEN '2023-07-01' AND '2024-06-30' THEN CAST(t.transaction_amount AS DECIMAL(18,6))
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN CAST(t.transaction_amount AS DECIMAL(18,6))/55
                        ELSE CAST(t.transaction_amount AS DECIMAL(18,6))
                    END AS total_amount,
                    CAST(t.transaction_amount AS DECIMAL(18,6)) - CAST(t.comm AS DECIMAL(18,6)) AS net_due3,
                    CASE
                        WHEN CAST(t.transaction_amount_inr AS DECIMAL(18,6)) <> 0 THEN CAST(t.comm AS DECIMAL(18,6))/55
                        ELSE CAST(t.comm AS DECIMAL(18,6))
                    END AS commission_amt,
                    t.order_amnt - (CASE WHEN t.document_type IN ('RFND','REF') THEN 0 ELSE t.net_due END) AS service_fee,
                    t.vendor AS issued_by
            ";
        }

        $sql = $select . "
            FROM " . self::TICKET_TABLE . " t
            WHERE t.issue_date BETWEEN :issue_start AND :issue_end
        ";

        $sql .= $this->buildTicketFilterClauses($filters, $params);

        if (!$forCount) {
            $sql .= " ORDER BY t.issue_date DESC, t.order_id ASC, t.auto_id DESC";

            if ($limit !== null) {
                $limit = max(1, min(2000, (int)$limit));
                $offset = max(0, (int)($offset ?? 0));
                $sql .= " LIMIT {$limit} OFFSET {$offset}";
            }
        }

        return [$sql, $params];
    }

    /**
     * Append dynamic filter clauses to ticket query
     *
     * @param array<string, mixed> $filters
     */
    private function buildTicketFilterClauses(array $filters, array &$params): string
    {
        $sql = '';

        if (!empty($filters['ticket_numbers'])) {
            $clauses = [];
            foreach ($filters['ticket_numbers'] as $idx => $ticketNo) {
                $key = ':ticket_no_' . $idx;
                $clauses[] = "RIGHT(t.document, 10) LIKE {$key}";
                $params[$key] = '%' . $ticketNo . '%';
            }
            if ($clauses) {
                $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }

        if (!empty($filters['issued_vias'])) {
            $sql .= $this->buildInClause($filters['issued_vias'], $params, 't.vendor', ':issued_via_');
        }

        if (!empty($filters['airlines'])) {
            $sql .= $this->buildInClause($filters['airlines'], $params, 't.a_l', ':airline_');
        }

        if (!empty($filters['order_numbers'])) {
            $sql .= $this->buildInClause($filters['order_numbers'], $params, 't.order_id', ':order_no_');
        }

        if (!empty($filters['travel_dates'])) {
            $sql .= $this->buildInClause($filters['travel_dates'], $params, 't.travel_date', ':travel_date_');
        }

        if (!empty($filters['pnrs'])) {
            $clauses = [];
            foreach ($filters['pnrs'] as $idx => $pnr) {
                $key = ':pnr_' . $idx;
                $clauses[] = "t.pnr LIKE {$key}";
                $params[$key] = '%' . $pnr . '%';
            }
            if ($clauses) {
                $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }

        if (!empty($filters['confirmed_statuses'])) {
            $normalizedStatus = "
                CASE 
                    WHEN LOWER(t.confirmed) IN ('confirmed','paid','reconciled') THEN 'Confirmed'
                    WHEN LOWER(t.confirmed) IN ('pending','due','unpaid') THEN 'Pending'
                    ELSE t.confirmed
                END
            ";
            $clauses = [];
            foreach ($filters['confirmed_statuses'] as $idx => $status) {
                $key = ':confirmed_' . $idx;
                $clauses[] = $key;
                $params[$key] = $status;
            }
            if ($clauses) {
                $sql .= ' AND ' . $normalizedStatus . ' IN (' . implode(',', $clauses) . ')';
            }
        }

        return $sql;
    }

    /**
     * Helper to build IN clause with placeholders
     *
     * @param array<int, string> $values
     */
    private function buildInClause(array $values, array &$params, string $column, string $placeholderPrefix): string
    {
        $placeholders = [];
        foreach ($values as $idx => $value) {
            $key = $placeholderPrefix . $idx;
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        if (empty($placeholders)) {
            return '';
        }

        return ' AND ' . $column . ' IN (' . implode(',', $placeholders) . ')';
    }

    /**
     * Recalculate and update order_amnt for ticket reconciliation rows within date range,
     * optionally filtered by pax_id. Returns affected row count.
     */
    public function recalculateOrderAmounts(string $startDate, string $endDate, ?int $paxId = null): int
    {
        $paxFilterSql = '';
        $params = [
            ':start' => $startDate,
            ':end' => $endDate,
        ];

        if ($paxId !== null) {
            $paxFilterSql = " AND r.pax_id = :pax_id";
            $params[':pax_id'] = $paxId;
        }

        $sql = "
            UPDATE " . self::TICKET_TABLE . " AS r
            JOIN (
                SELECT
                    p.auto_id AS pax_id,
                    COALESCE(
                        tax.tax_amt + f.PriceSell,
                        CASE 
                            WHEN b.total_pax > 0 
                                THEN p.trip_price_individual - (IFNULL(b.discount_given, 0) / b.total_pax)
                            ELSE p.trip_price_individual
                        END
                    ) AS order_amnt
                FROM wpk4_backend_travel_booking_pax p
                LEFT JOIN wpk4_backend_travel_bookings b
                  ON p.order_id = b.order_id
                 AND p.co_order_id = b.co_order_id
                 AND p.product_id = b.product_id
                LEFT JOIN wpk4_ypsilon_bookings_table_fare f
                  ON p.gds_pax_id = f.PaxId
                LEFT JOIN (
                    SELECT PaxId, SUM(Amount) AS tax_amt
                    FROM wpk4_ypsilon_bookings_table_tax
                    GROUP BY PaxId
                ) AS tax
                  ON p.gds_pax_id = tax.PaxId
            ) AS b
              ON r.pax_id = b.pax_id
            SET r.order_amnt = CASE
                  WHEN r.document_type IN ('RFND','RFE') AND r.net_due IS NOT NULL
                       THEN r.net_due
                  ELSE b.order_amnt
                END
            WHERE r.issue_date BETWEEN :start AND :end
              AND (r.order_amnt IS NULL OR r.order_amnt = 0)
              {$paxFilterSql}
        ";

        // Count rows that will be affected before updating
        $countSql = "
            SELECT COUNT(*) as count
            FROM " . self::TICKET_TABLE . " AS r
            JOIN (
                SELECT
                    p.auto_id AS pax_id
                FROM wpk4_backend_travel_booking_pax p
                LEFT JOIN wpk4_backend_travel_bookings b
                  ON p.order_id = b.order_id
                 AND p.co_order_id = b.co_order_id
                 AND p.product_id = b.product_id
                LEFT JOIN wpk4_ypsilon_bookings_table_fare f
                  ON p.gds_pax_id = f.PaxId
                LEFT JOIN (
                    SELECT PaxId, SUM(Amount) AS tax_amt
                    FROM wpk4_ypsilon_bookings_table_tax
                    GROUP BY PaxId
                ) AS tax
                  ON p.gds_pax_id = tax.PaxId
            ) AS b
              ON r.pax_id = b.pax_id
            WHERE r.issue_date BETWEEN :start AND :end
              AND (r.order_amnt IS NULL OR r.order_amnt = 0)
              {$paxFilterSql}
        ";
        
        $result = $this->queryOne($countSql, $params);
        $affectedCount = (int)($result['count'] ?? 0);
        
        // Execute the update
        $this->execute($sql, $params);
        
        return $affectedCount;
    }

    /**
     * Import ticket reconciliation rows from hotfile by document numbers
     * Uses INSERT IGNORE to avoid duplicates. Returns affected row count.
     */
    public function importFromHotfileByDocuments(array $documents): int
    {
        if (empty($documents)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($documents as $doc) {
            $placeholders[] = '?';
            $params[] = $doc;
        }
        $in = implode(',', $placeholders);

        // Duplicate params for the two IN clauses in the query
        $allParams = array_merge($params, $params);

        $sql = "
            INSERT IGNORE INTO " . self::TICKET_TABLE . "
            (
              order_id, pax_id, pnr, document, document_type, reason, previous_ticket_number,
              transaction_amount, net_due, vendor, issue_date, confirmed, a_l, fare, tax, fee, comm,
              agent, added_on, added_by, remark, delete_request,
              fare_inr, tax_inr, comm_inr, transaction_amount_inr,
              modified_by, order_amnt, order_date, travel_date, fname, lname,
              Invoicelink_no, invoice_no
            )
            SELECT
              m.order_id,
              m.pax_id,
              h.pnr,
              h.document,
              h.document_type,
              h.reason,
              h.previous_ticket_number,
              h.transaction_amount,
              h.net_due,
              h.vendor,
              h.issue_date,
              h.confirmed,
              h.a_l,
              COALESCE(NULLIF(h.fare,0), h.gross_fare, h.cash_fare, 0) AS fare,
              h.tax,
              COALESCE(h.fee,0) AS fee,
              h.comm,
              h.agent,
              h.added_on,
              h.added_by,
              NULL AS remark,
              0 AS delete_request,
              CASE WHEN h.vendor IN ('IFN IATA','GILPIN')
                   THEN COALESCE(NULLIF(h.fare,0), h.gross_fare, h.cash_fare, 0)
                   ELSE NULL END AS fare_inr,
              CASE WHEN h.vendor IN ('IFN IATA','GILPIN')
                   THEN h.tax ELSE NULL END AS tax_inr,
              CASE WHEN h.vendor IN ('IFN IATA','GILPIN')
                   THEN h.comm ELSE NULL END AS comm_inr,
              CASE WHEN h.vendor IN ('IFN IATA','GILPIN')
                   THEN h.transaction_amount ELSE NULL END AS transaction_amount_inr,
              NULL AS modified_by,
              NULL AS order_amnt,
              NULL AS order_date,
              h.departure_date AS travel_date,
              CASE
                WHEN h.pax_ticket_name LIKE '%/%' THEN
                  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(h.pax_ticket_name,'/',-1),' ',1))
                ELSE NULL
              END AS fname,
              CASE
                WHEN h.pax_ticket_name LIKE '%/%' THEN
                  TRIM(SUBSTRING_INDEX(h.pax_ticket_name,'/',1))
                ELSE NULL
              END AS lname,
              NULL AS Invoicelink_no,
              NULL AS invoice_no
            FROM wpk4_backend_travel_booking_ticket_number_hotfile h
            JOIN (
                SELECT
                    document,
                    MAX(order_id) AS order_id,
                    MAX(pax_id)   AS pax_id
                FROM wpk4_backend_travel_booking_ticket_number
                WHERE document IN ($in)
                GROUP BY document
            ) m
              ON m.document = h.document
            WHERE h.document IN ($in)
        ";

        $this->execute($sql, $allParams);
        
        // Get affected rows by counting inserted records
        // Note: INSERT IGNORE doesn't return rowCount easily, so we count matching documents
        $countSql = "
            SELECT COUNT(DISTINCT h.document) as count
            FROM wpk4_backend_travel_booking_ticket_number_hotfile h
            JOIN (
                SELECT document
                FROM wpk4_backend_travel_booking_ticket_number
                WHERE document IN ($in)
                GROUP BY document
            ) m ON m.document = h.document
            WHERE h.document IN ($in)
        ";
        
        $result = $this->queryOne($countSql, $allParams);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get existing document numbers from ticket reconciliation table
     *
     * @param array<int, string> $documentNumbers
     * @return array<int, string>
     */
    public function getExistingDocuments(array $documentNumbers): array
    {
        if (empty($documentNumbers)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($documentNumbers as $doc) {
            $placeholders[] = '?';
            $params[] = $doc;
        }
        $in = implode(',', $placeholders);

        $sql = "SELECT document FROM " . self::TICKET_TABLE . " WHERE document IN ($in)";
        
        $rows = $this->query($sql, $params);
        return array_map(static function ($row) {
            return (string)$row['document'];
        }, $rows);
    }
}

