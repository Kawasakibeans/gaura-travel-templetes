<?php

namespace App\DAL;

use App\DAL\BaseDAL;
use Exception;

class TicketNumberHotfileDAL extends BaseDAL
{
	/**
	 * Insert a record into wpk4_backend_travel_booking_ticket_number_hotfile
	 * Returns the inserted auto_id
	 */
	public function create(array $data): int
	{
		$sql = "
			INSERT INTO wpk4_backend_travel_booking_ticket_number_hotfile
			(`group`, vendor, iata_no, document, a_l, pax_ticket_name, plated_carrier_code, plated_carrier, dom, published_remit,
			 reporting_system_code, pnr, document_type, issue_date, departure_date, currency, tour_code, conj_tkt_indicator,
			 emd_remarks, cash_fare, credit_fare, gross_fare, comm, fare, cash_tax, credit_tax, yq_tax, yr_tax, tax, transaction_amount, net_due,
			 added_on, added_by, fee)
			VALUES
			(:group_name, :vendor, :iata_no, :document, :a_l, :pax_ticket_name, :plated_carrier_code, :plated_carrier, :dom, :published_remit,
			 :reporting_system_code, :pnr, :document_type, :issue_date, :departure_date, :currency, :tour_code, :conj_tkt_indicator,
			 :emd_remarks, :cash_fare, :credit_fare, :gross_fare, :comm, :fare, :cash_tax, :credit_tax, :yq_tax, :yr_tax, :tax, :transaction_amount, :net_due,
			 NOW(), :added_by, :fee)
		";

		$params = [
			'group_name' => $data['group'] ?? null,
			'vendor' => $data['vendor'] ?? null,
			'iata_no' => $data['iata_no'] ?? null,
			'document' => $data['document'] ?? null,
			'a_l' => $data['a_l'] ?? null,
			'pax_ticket_name' => $data['pax_ticket_name'] ?? null,
			'plated_carrier_code' => $data['plated_carrier_code'] ?? null,
			'plated_carrier' => $data['plated_carrier'] ?? null,
			'dom' => $data['dom'] ?? null,
			'published_remit' => $data['published_remit'] ?? null,
			'reporting_system_code' => $data['reporting_system_code'] ?? null,
			'pnr' => $data['pnr'] ?? null,
			'document_type' => $data['document_type'] ?? null,
			'issue_date' => $data['issue_date'] ?? null,
			'departure_date' => $data['departure_date'] ?? null,
			'currency' => $data['currency'] ?? null,
			'tour_code' => $data['tour_code'] ?? null,
			'conj_tkt_indicator' => $data['conj_tkt_indicator'] ?? null,
			'emd_remarks' => $data['emd_remarks'] ?? null,
			'cash_fare' => $data['cash_fare'] ?? null,
			'credit_fare' => $data['credit_fare'] ?? null,
			'gross_fare' => $data['gross_fare'] ?? null,
			'comm' => $data['comm'] ?? null,
			'fare' => $data['fare'] ?? null,
			'cash_tax' => $data['cash_tax'] ?? null,
			'credit_tax' => $data['credit_tax'] ?? null,
			'yq_tax' => $data['yq_tax'] ?? null,
			'yr_tax' => $data['yr_tax'] ?? null,
			'tax' => $data['tax'] ?? null,
			'transaction_amount' => $data['transaction_amount'] ?? null,
			'net_due' => $data['net_due'] ?? null,
			'added_by' => $data['added_by'] ?? null,
			'fee' => $data['fee'] ?? null,
		];

		$this->execute($sql, $params);
		return (int)$this->db->lastInsertId();
	}

	/**
	 * Fetch ticket hotfile display rows by date range and optional exact vendor
	 */
	public function getTicketsByDateAndVendor(string $fromDate, string $toDate, ?string $vendor = null): array
	{
		// Use DATE() function to ensure date-only comparison (ignores time component)
		// This ensures inclusive date range matching
		$where = [
			"DATE(h.issue_date) BETWEEN DATE(:from_date) AND DATE(:to_date)",
			"h.document_type IN ('TKTT','TKT','EMD','EMDs')"
		];
		$params = [
			'from_date' => $fromDate,
			'to_date' => $toDate
		];

		if (!empty($vendor)) {
			// Make vendor filter case-insensitive and handle variations
			// Handle common vendor variations
			$vendorUpper = strtoupper(trim($vendor));
			
			// If vendor is "GKT IATA", also match "GKT" and variations
			if ($vendorUpper === 'GKT IATA' || $vendorUpper === 'GKT') {
				$where[] = "(UPPER(TRIM(h.vendor)) = 'GKT IATA' OR UPPER(TRIM(h.vendor)) = 'GKT')";
			} 
			// If vendor is "IFN IATA", also match "IFN" and variations
			elseif ($vendorUpper === 'IFN IATA' || $vendorUpper === 'IFN') {
				$where[] = "(UPPER(TRIM(h.vendor)) = 'IFN IATA' OR UPPER(TRIM(h.vendor)) = 'IFN')";
			}
			// For other vendors, use case-insensitive match
			else {
				$where[] = "UPPER(TRIM(h.vendor)) = :vendor_exact";
				$params['vendor_exact'] = $vendorUpper;
			}
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		// Simplified query: Get one row per hotfile document using GROUP BY
		// This ensures consistent results regardless of JOIN outcomes
		$sql = "
			SELECT 
				h.issue_date AS ticket_issue_date,
				h.document AS ticket_no,
				h.document_type AS ticket_type,
				h.net_due AS ticket_amnt,
				CASE
					WHEN MAX(b.order_type) = 'gds' THEN 'FIT'
					WHEN MAX(b.order_type) IS NULL THEN 'N/A'
					ELSE 'Gdeals'
				END AS order_type,
				MAX(b.order_id) AS order_id,
				MAX(p.pnr) AS g360_pnr,
				h.pnr AS gds_pnr,
				MAX(t.oid) AS source,
				MAX(b.order_date) AS order_date,
				MAX(b.payment_modified) AS payment_modified,
				MAX(b.trip_code) AS trip_code,
				MAX(b.travel_date) AS travel_date,
				MAX(p.salutation) AS salutation,
				MAX(CONCAT(p.lname,'/',p.fname)) AS g360_pax_name,
				h.pax_ticket_name AS gds_pax_name,
				MAX(p.dob) AS dob,
				MAX(p.ticketed_by) AS ticketed_by,
				MAX(p.ticketed_on) AS ticketed_on
			FROM wpk4_backend_travel_booking_ticket_number_hotfile h
			LEFT JOIN wpk4_backend_travel_booking_ticket_number t
				   ON h.document = t.document
			LEFT JOIN wpk4_backend_travel_booking_pax p
				   ON t.pax_id = p.auto_id
			LEFT JOIN wpk4_backend_travel_bookings b
				   ON b.order_id = p.order_id
				  AND p.product_id = b.product_id
				  AND p.co_order_id = b.co_order_id
			{$whereSql}
			GROUP BY h.document, h.issue_date, h.document_type, h.net_due, h.pnr, h.pax_ticket_name
			ORDER BY h.issue_date, h.document
		";

		$result = $this->query($sql, $params);
		
		// Enhanced debug logging to help diagnose discrepancies
		error_log("TicketNumberHotfileDAL::getTicketsByDateAndVendor");
		error_log("  Date range: $fromDate to $toDate");
		error_log("  Vendor filter: " . ($vendor ?? 'ALL'));
		error_log("  Records returned: " . count($result));
		error_log("  SQL Query: " . $sql);
		error_log("  SQL Params: " . json_encode($params));
		
		// Count base records without JOINs to see actual difference
		$countSql = "
			SELECT COUNT(DISTINCT document) as total
			FROM wpk4_backend_travel_booking_ticket_number_hotfile
			WHERE DATE(issue_date) BETWEEN DATE(:from_date) AND DATE(:to_date)
			  AND document_type IN ('TKTT','TKT','EMD','EMDs')
		";
		
		$countParams = [
			'from_date' => $fromDate,
			'to_date' => $toDate
		];
		
		if (!empty($vendor)) {
			$vendorUpper = strtoupper(trim($vendor));
			if ($vendorUpper === 'GKT IATA' || $vendorUpper === 'GKT') {
				$countSql .= " AND (UPPER(TRIM(vendor)) = 'GKT IATA' OR UPPER(TRIM(vendor)) = 'GKT')";
			} elseif ($vendorUpper === 'IFN IATA' || $vendorUpper === 'IFN') {
				$countSql .= " AND (UPPER(TRIM(vendor)) = 'IFN IATA' OR UPPER(TRIM(vendor)) = 'IFN')";
			} else {
				$countSql .= " AND UPPER(TRIM(vendor)) = :vendor_exact";
				$countParams['vendor_exact'] = $vendorUpper;
			}
		}
		
		try {
			$countResult = $this->query($countSql, $countParams);
			$baseCount = (int)($countResult[0]['total'] ?? 0);
			error_log("  Base distinct documents in hotfile: $baseCount");
			
			// Also check vendor distribution
			$vendorSql = "
				SELECT UPPER(TRIM(vendor)) as vendor_name, COUNT(DISTINCT document) as count
				FROM wpk4_backend_travel_booking_ticket_number_hotfile
				WHERE DATE(issue_date) BETWEEN DATE(:from_date) AND DATE(:to_date)
				  AND document_type IN ('TKTT','TKT','EMD','EMDs')
				GROUP BY UPPER(TRIM(vendor))
			";
			$vendorResult = $this->query($vendorSql, ['from_date' => $fromDate, 'to_date' => $toDate]);
			error_log("  Vendor distribution: " . json_encode($vendorResult));
		} catch (Exception $e) {
			error_log("  Error counting records: " . $e->getMessage());
		}
		
		return $result;
	}

	/**
	 * Tickets present in ticket_number but not in hotfile, by date range
	 */
	public function getNonHotfileTicketsByDate(string $fromDate, string $toDate): array
	{
		$sql = "
			SELECT DISTINCT
				t.issue_date AS ticket_issue_date,
				t.document AS ticket_no,
				t.document_type AS ticket_type,
				NULL AS ticket_amnt,
				CASE
					WHEN b.order_type = 'gds' THEN 'FIT'
					WHEN b.order_type IS NULL THEN 'N/A'
					ELSE 'Gdeals'
				END AS order_type,
				b.order_id AS order_id,
				p.pnr AS g360_pnr,
				NULL AS gds_pnr,
				t.oid AS source,
				b.order_date AS order_date,
				b.payment_modified AS payment_modified,
				b.trip_code AS trip_code,
				b.travel_date AS travel_date,
				p.salutation AS salutation,
				CONCAT(p.lname,'/',p.fname) AS g360_pax_name,
				NULL AS gds_pax_name,
				p.dob AS dob,
				p.ticketed_by AS ticketed_by,
				p.ticketed_on AS ticketed_on
			FROM wpk4_backend_travel_booking_ticket_number t
			LEFT JOIN wpk4_backend_travel_booking_pax p
				   ON t.pax_id = p.auto_id
			LEFT JOIN wpk4_backend_travel_bookings b
				   ON b.order_id = p.order_id
				  AND p.product_id = b.product_id
				  AND p.co_order_id = b.co_order_id
			LEFT JOIN wpk4_backend_travel_booking_ticket_number_hotfile h
				   ON h.document = t.document
			WHERE DATE(t.issue_date) BETWEEN DATE(:from_dt) AND DATE(:to_dt)
			  AND t.document_type IN ('TKTT','TKT','EMD','EMDs')
			  AND h.document IS NULL
			ORDER BY t.issue_date, t.document, COALESCE(b.order_id, 0)
		";

		return $this->query($sql, [
			'from_dt' => $fromDate,
			'to_dt' => $toDate
		]);
	}
}


