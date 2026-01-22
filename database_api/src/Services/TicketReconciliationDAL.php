<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class TicketReconciliationDAL extends BaseDAL
{
	/**
	 * Return the subset of provided document numbers that already exist in reconciliation
	 */
	public function getExistingDocuments(array $documentNumbers): array
	{

		if (empty($documentNumbers)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($documentNumbers), '?'));
		$sql = "SELECT document FROM wpk4_backend_ticket_reconciliation WHERE document IN ({$placeholders})";

		$rows = $this->query($sql, array_values($documentNumbers));
		return array_map(static function ($row) { return $row['document']; }, $rows);
	}

	/**
	 * Recalculate and update order_amnt for ticket reconciliation rows within date range,
	 * optionally filtered by pax_id. Returns affected row count.
	 */
	public function recalculateOrderAmounts(string $startDate, string $endDate, ?int $paxId = null): int
	{
		$paxFilterSql = '';
		$params = [
			'start' => $startDate,
			'end' => $endDate,
		];

		if ($paxId !== null) {
			$paxFilterSql = " AND r.pax_id = :pax_id";
			$params['pax_id'] = $paxId;
		}

		$sql = "
			UPDATE wpk4_backend_ticket_reconciliation AS r
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

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return (int)$stmt->rowCount();
	}

	/**
	 * Insert missing ticket reconciliation rows from hotfile for the given document numbers.
	 * Uses INSERT IGNORE to avoid duplicates. Returns affected row count.
	 */
	public function importFromHotfileByDocuments(array $documents): int
	{
		if (empty($documents)) {
			return 0;
		}

		$in = implode(',', array_fill(0, count($documents), '?'));

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

		$stmt = $this->db->prepare($sql);
		$params = array_merge(array_values($documents), array_values($documents));
		$stmt->execute($params);
		return (int)$stmt->rowCount();
	}
}


