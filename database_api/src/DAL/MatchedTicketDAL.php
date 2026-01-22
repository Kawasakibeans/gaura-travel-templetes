<?php
/**
 * Matched Ticket Data Access Layer
 * Handles all database operations for ticket reconciliation
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class MatchedTicketDAL extends BaseDAL
{
    /**
     * Get matched tickets with filters
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string|null $vendor Vendor filter (optional)
     * @return array Array of matched/unmatched ticket records
     */
    public function getMatchedTickets($fromDate, $toDate, $vendor = null)
    {
        $vendorSql = '';
        $vendorBind = [];
        
        if ($vendor !== null && $vendor !== '') {
            $vendorSql = " AND h.vendor = :vendor_exact ";
            $vendorBind['vendor_exact'] = $vendor;
        }

        $sql = "
            SELECT
                h.pnr,
                h.document AS doc10,
                h.document_type,
                h.issue_date,
                h.vendor,
                h.a_l,
                h.transaction_amount,
                h.net_due,
                h.pax_ticket_name,
                h.fare,
                h.gross_fare,
                h.cash_fare,
                h.tax,
                h.fee,
                h.comm,
                h.agent,
                h.added_on,
                h.added_by,
                h.reason,
                h.previous_ticket_number,
                h.updated_on,
                h.updated_by,
                h.seq_no,
                h.confirmed,
                h.total_doc,
                h.plated_carrier_code,
                h.plated_carrier,
                h.dom,
                h.published_remit,
                h.reporting_system_code,
                h.departure_date,
                h.currency,
                h.tour_code,
                h.conj_tkt_indicator,
                h.emd_remarks,
                h.yq_tax,
                h.yr_tax,
                h.document AS hotfile_document_raw,
                m.order_id AS order_id,
                m.pax_id   AS pax_id
            FROM wpk4_backend_travel_booking_ticket_number_hotfile h
            LEFT JOIN (
                SELECT
                    document AS doc10,
                    MIN(order_id) AS order_id,
                    MIN(pax_id)   AS pax_id
                FROM wpk4_backend_travel_booking_ticket_number
                GROUP BY document
            ) m
              ON m.doc10 = h.document
            WHERE h.issue_date BETWEEN :from_date AND :to_date
            {$vendorSql}
            ORDER BY h.issue_date, h.document, h.pnr
        ";

        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
        
        $params = array_merge($params, $vendorBind);
        
        $results = $this->query($sql, $params);
        
        // Process results to identify matched/unmatched
        $processed = [];
        $dupCounts = [];
        
        // Count duplicates per doc10
        foreach ($results as $r) {
            $d = $r['doc10'] ?? '';
            if ($d !== '') {
                $dupCounts[$d] = ($dupCounts[$d] ?? 0) + 1;
            }
        }
        
        $runningIndex = [];
        foreach ($results as $r) {
            $doc10 = (string)($r['doc10'] ?? '');
            $isMatched = !empty($r['order_id']);
            
            $displayDoc = $doc10;
            if ($doc10 !== '' && ($dupCounts[$doc10] ?? 0) > 1) {
                $runningIndex[$doc10] = ($runningIndex[$doc10] ?? 0) + 1;
                $displayDoc = $doc10 . ' - ' . $runningIndex[$doc10];
            }
            
            $processed[] = [
                'data' => $r,
                'matched' => $isMatched,
                'doc10' => $doc10,
                'display_document' => $displayDoc
            ];
        }
        
        return $processed;
    }

    /**
     * Insert matched tickets into reconciliation table
     * 
     * @param array $documents Array of document numbers to insert
     * @return int Number of rows inserted
     */
    public function insertMatchedTickets($documents)
    {
        if (empty($documents)) {
            return 0;
        }

        $chunkSize = 400;
        $totalInserted = 0;

        foreach (array_chunk($documents, $chunkSize) as $chunk) {
            if (empty($chunk)) {
                continue;
            }
            
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            
            $sql = "
                INSERT IGNORE INTO wpk4_backend_ticket_reconciliation
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
                INNER JOIN (
                    SELECT
                        document AS doc10,
                        MIN(order_id) AS order_id,
                        MIN(pax_id)   AS pax_id
                    FROM wpk4_backend_travel_booking_ticket_number
                    GROUP BY document
                ) m
                  ON m.doc10 = h.document
                WHERE h.document IN ($placeholders)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($chunk);
            $totalInserted += $stmt->rowCount();
        }

        return $totalInserted;
    }

    /**
     * Update order amounts for ticket reconciliation
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param int|null $paxId Optional pax_id filter
     * @return int Number of rows updated
     */
    public function updateOrderAmounts($startDate, $endDate, $paxId = null)
    {
        $paxFilter = '';
        $params = [
            'start' => $startDate,
            'end' => $endDate
        ];
        
        if ($paxId !== null) {
            $paxFilter = ' AND b.pax_id = :pax_id ';
            $params['pax_id'] = $paxId;
        }

        $sql = "
            UPDATE wpk4_backend_ticket_reconciliation AS r
            JOIN (
                SELECT
                    p.auto_id AS pax_id,
                    b.source,
                    COALESCE(
                        tax.tax_amt + f.PriceSell,
                        CASE 
                            WHEN b.total_pax > 0 THEN p.trip_price_individual - (IFNULL(b.discount_given, 0) / b.total_pax)
                            WHEN b.total_pax = 0 THEN p.trip_price_individual
                            ELSE 0
                        END,
                        0
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
                  WHEN r.document_type IN ('RFND','REF') AND r.net_due IS NOT NULL
                       THEN 0
                  ELSE b.order_amnt
                END
            WHERE r.issue_date BETWEEN :start AND :end
              AND (r.order_amnt IS NULL OR r.order_amnt = 0) 
              AND b.source <> 'datechange'
              {$paxFilter}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->rowCount();
    }

    /**
     * Get ticket reconciliation records
     * 
     * @param string|null $fromDate Start date (Y-m-d format, optional)
     * @param string|null $toDate End date (Y-m-d format, optional)
     * @param string|null $vendor Vendor filter (optional)
     * @param int $limit Limit number of records
     * @param int $offset Offset for pagination
     * @return array Array of reconciliation records
     */
    public function getTicketReconciliation($fromDate = null, $toDate = null, $vendor = null, $limit = 100, $offset = 0)
    {
        $where = [];
        $positionalParams = [];

        if ($fromDate !== null) {
            $where[] = "issue_date >= ?";
            $positionalParams[] = $fromDate;
        }
        if ($toDate !== null) {
            $where[] = "issue_date <= ?";
            $positionalParams[] = $toDate;
        }
        if ($vendor !== null && $vendor !== '') {
            $where[] = "vendor = ?";
            $positionalParams[] = $vendor;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT *
            FROM wpk4_backend_ticket_reconciliation
            {$whereClause}
            ORDER BY issue_date DESC, document
            LIMIT ? OFFSET ?
        ";

        $positionalParams[] = $limit;
        $positionalParams[] = $offset;

        return $this->query($sql, $positionalParams);
    }
}

