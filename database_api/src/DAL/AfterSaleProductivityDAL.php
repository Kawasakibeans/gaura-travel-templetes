<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AfterSaleProductivityDAL extends BaseDAL
{
	/**
	 * Get distinct agent names from after-sale productivity joined with agent codes
	 */
	public function getDistinctAgentNamesByLocation(string $location, string $status = 'Active'): array
	{
		$sql = "
			SELECT DISTINCT a.agent_name
			FROM wpk4_agent_after_sale_productivity_report a
			JOIN wpk4_backend_agent_codes c ON a.agent_name = c.agent_name
			WHERE a.agent_name <> 'ABDN'
			  AND c.status = :status
			  AND c.location = :location
			ORDER BY a.agent_name ASC
		";

		return $this->query($sql, [
			'location' => $location,
			'status' => $status
		]);
	}

	/**
	 * Get productivity summary grouped by date with optional agent filter and pagination
	 */
	public function getProductivitySummary(
		string $startDate,
		string $endDate,
		?string $agentName = null,
		string $location = 'BOM',
		string $status = 'active',
		?int $limit = null,
		int $offset = 0
	): array {
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate,
			'location' => $location,
			'status' => $status
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date",
			"a.agent_name <> 'ABDN'"
		];

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		// Simplified query: Remove agent_codes JOIN entirely to ensure all data is included
		// This works for both AI and GT1 servers regardless of agent_codes table state
		// Use COALESCE to handle NULL values and ensure 0 instead of NULL
		$sql = "
			SELECT 
				a.`date`,
				COALESCE(SUM(a.ssr), 0) AS ssr,
				COALESCE(SUM(a.gdeal_ticketed), 0) AS gdeals_ticket_issued,
				COALESCE(SUM(a.fit_ticketed), 0) AS fit_tickets_issued,
				COALESCE(SUM(a.gdeal_audit), 0) AS gdeals_audit,
				COALESCE(SUM(a.fit_audit), 0) AS fit_audit,
				COALESCE(SUM(a.pre_departure), 0) AS pre_departure_checklist,
				COALESCE(SUM(a.inb_call_count), 0) AS inbound_calls,
				COALESCE(SUM(a.otb_call_count), 0) AS outbound_calls,
				COALESCE(SUM(a.escalate), 0) AS escalation_raised,
				COALESCE(ROUND(SUM(a.inb_call_count_duration) / NULLIF(SUM(a.inb_call_count), 0), 2), 0) AS inbound_calls_aht,
				COALESCE(ROUND(SUM(a.otb_call_count_duration) / NULLIF(SUM(a.otb_call_count), 0), 2), 0) AS outbound_calls_aht,
				COALESCE(SUM(a.dc_request), 0) AS dc_handle,
				COALESCE(SUM(a.sc_case_handle), 0) AS sc_handle
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.`date`
			HAVING COUNT(*) > 0
			ORDER BY a.`date` ASC
		";

		if ($limit !== null) {
			$offset = max(0, (int)$offset);
			$limit = max(1, (int)$limit);
			$sql .= " LIMIT {$offset}, {$limit}";
		}

		return $this->query($sql, params: $params);
	}

	/**
	 * Get productivity summary grouped by agent_name (for agent-wise report)
	 */
	public function getProductivitySummaryByAgent(
		string $startDate,
		string $endDate,
		?string $agentName = null,
		string $location = 'BOM',
		string $status = 'active',
		?int $limit = null,
		int $offset = 0
	): array {
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate,
			'location' => $location,
			'status' => $status
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date",
			"a.agent_name <> 'ABDN'"
		];

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		// Query grouped by agent_name instead of date
		$sql = "
			SELECT 
				a.agent_name,
				COALESCE(SUM(a.ssr), 0) AS ssr,
				COALESCE(SUM(a.gdeal_ticketed), 0) AS gdeals_ticket_issued,
				COALESCE(SUM(a.fit_ticketed), 0) AS fit_tickets_issued,
				COALESCE(SUM(a.gdeal_audit), 0) AS gdeals_audit,
				COALESCE(SUM(a.fit_audit), 0) AS fit_audit,
				COALESCE(SUM(a.pre_departure), 0) AS pre_departure_checklist,
				COALESCE(SUM(a.inb_call_count), 0) AS inbound_calls,
				COALESCE(SUM(a.otb_call_count), 0) AS outbound_calls,
				COALESCE(SUM(a.escalate), 0) AS escalation_raised,
				COALESCE(ROUND(SUM(a.inb_call_count_duration) / NULLIF(SUM(a.inb_call_count), 0), 2), 0) AS inbound_calls_aht,
				COALESCE(ROUND(SUM(a.otb_call_count_duration) / NULLIF(SUM(a.otb_call_count), 0), 2), 0) AS outbound_calls_aht,
				COALESCE(SUM(a.dc_request), 0) AS dc_handle,
				COALESCE(SUM(a.sc_case_handle), 0) AS sc_handle
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.agent_name
			ORDER BY a.agent_name ASC
		";

		if ($limit !== null) {
			$offset = max(0, (int)$offset);
			$limit = max(1, (int)$limit);
			$sql .= " LIMIT {$offset}, {$limit}";
		}

		$result = $this->query($sql, params: $params);
		
		// Log only if result is empty (for debugging)
		if (empty($result)) {
			error_log("ProductivitySummaryByAgent: Query returned empty result. Query: " . $sql . " Params: " . json_encode($params));
		}
		
		return $result;
	}

	/**
	 * Get agent-level metrics grouped by agent with optional day-range and agent filter
	 */
	public function getAgentMetrics(
		string $startDate,
		string $endDate,
		?int $startDay = null,
		?int $endDay = null,
		?string $agentName = null
	): array {
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date"
		];

		if ($startDay !== null && $endDay !== null) {
			$where[] = "DAY(a.`date`) BETWEEN :start_day AND :end_day";
			$params['start_day'] = (int)$startDay;
			$params['end_day'] = (int)$endDay;
		}

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.inb_call_count) AS inb_call_count,
				SUM(a.inb_call_count_duration) AS inb_call_count_duration,
				SUM(a.gtcs) AS gtcs,
				SUM(a.gtpy) AS gtpy,
				SUM(a.gtet) AS gtet,
				SUM(a.gtdc) AS gtdc,
				SUM(a.gtrf) AS gtrf,
				SUM(CAST(SUBSTRING_INDEX(a.total_acw, ' ', 1) AS UNSIGNED)) AS total_acw_seconds
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.agent_name
			HAVING SUM(a.inb_call_count) > 0
			ORDER BY a.agent_name ASC
		";

		return $this->query($sql, $params);
	}

	/**
	 * Monthly per-agent call summary for a given month and day range
	 */
	public function getMonthlyAgentCallSummary(string $monthY, int $startDay, int $endDay): array
	{
		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.inb_call_count) AS total_calls,
				CASE WHEN a.agent_name <> 'ABDN' THEN SUM(a.inb_call_count) ELSE 0 END AS avg_calls_mum,
				SUM(a.total_connected_time) / NULLIF(SUM(a.inb_call_count), 0) AS mum_aht_ex_acw,
				CASE WHEN a.agent_name = 'ABDN' THEN SUM(a.inb_call_count) ELSE 0 END AS abnd_calls
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE DATE_FORMAT(a.`date`, '%Y-%m') = :month
			  AND DAY(a.`date`) BETWEEN :start_day AND :end_day
			GROUP BY a.agent_name
			ORDER BY a.agent_name ASC
		";

		$params = [
			'month' => $monthY,
			'start_day' => (int)$startDay,
			'end_day' => (int)$endDay
		];

		return $this->query($sql, $params);
	}

	/**
	 * Agent success summary grouped by agent, optional month/day filters
	 */
	public function getAgentSuccessSummary(?string $monthY = null, ?int $startDay = null, ?int $endDay = null): array
	{
		$params = [];
		$where = [];

		if (!empty($monthY)) {
			$where[] = "DATE_FORMAT(a.`date`, '%Y-%m') = :month";
			$params['month'] = $monthY;
		}

		if ($startDay !== null && $endDay !== null) {
			$where[] = "DAY(a.`date`) BETWEEN :start_day AND :end_day";
			$params['start_day'] = (int)$startDay;
			$params['end_day'] = (int)$endDay;
		}

		$whereSql = '';
		if (!empty($where)) {
			$whereSql = 'WHERE ' . implode(' AND ', $where);
		}

		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_case_completed) AS cases_worked,
				SUM(a.dc_case_success) AS success_case,
				CASE 
					WHEN SUM(a.dc_case_completed) > 0 THEN ROUND((SUM(a.dc_case_success) / SUM(a.dc_case_completed)) * 100, 2)
					ELSE 0
				END AS success_percent,
				SUM(a.total_revenue) AS total_revenue
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.agent_name
			ORDER BY total_revenue DESC
		";

		return $this->query($sql, $params);
	}

	/**
	 * Agent success summary filtered by year, month (numeric) and day range
	 */
	public function getAgentSuccessSummaryByYearMonth(int $year, int $monthNum, int $startDay, int $endDay): array
	{
		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_case_completed) AS cases_worked,
				SUM(a.dc_case_success) AS success_case,
				CASE 
					WHEN SUM(a.dc_case_completed) > 0 THEN ROUND((SUM(a.dc_case_success) / SUM(a.dc_case_completed)) * 100, 2)
					ELSE 0
				END AS success_percent,
				SUM(a.total_revenue) AS total_revenue
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE YEAR(a.`date`) = :year
			  AND MONTH(a.`date`) = :month
			  AND DAY(a.`date`) BETWEEN :start_day AND :end_day
			GROUP BY a.agent_name
			ORDER BY total_revenue DESC
		";

		return $this->query($sql, [
			'year' => $year,
			'month' => $monthNum,
			'start_day' => $startDay,
			'end_day' => $endDay
		]);
	}

	/**
	 * Agent success summary filtered by year and month (no day filter)
	 */
	public function getAgentSuccessSummaryByYearMonthNoDay(int $year, int $monthNum): array
	{
		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_case_completed) AS cases_worked,
				SUM(a.dc_case_success) AS success_case,
				CASE 
					WHEN SUM(a.dc_case_completed) > 0 THEN ROUND((SUM(a.dc_case_success) / SUM(a.dc_case_completed)) * 100, 2)
					ELSE 0
				END AS success_percent,
				SUM(a.total_revenue) AS total_revenue
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE YEAR(a.`date`) = :year
			  AND MONTH(a.`date`) = :month
			GROUP BY a.agent_name
			ORDER BY total_revenue DESC
		";

		return $this->query($sql, [
			'year' => $year,
			'month' => $monthNum
		]);
	}

	/**
	 * DC summary by agent for date range, excluding ABDN, optional agent filter
	 */
	public function getAgentDcSummaryByDateRangeExcludeAbdn(string $startDate, string $endDate, ?string $agentName = null): array
	{
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date",
			"a.agent_name <> 'ABDN'"
		];

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_request) AS dc_request,
				SUM(a.dc_case_success) AS dc_case_success,
				SUM(a.dc_case_fail) AS dc_case_fail,
				SUM(a.dc_case_pending) AS dc_case_pending,
				SUM(a.total_revenue) AS total_revenue
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.agent_name
			HAVING SUM(a.dc_request) > 0
			ORDER BY a.agent_name ASC
		";

		return $this->query($sql, $params);
	}
	/**
	 * Distinct months (1..12) for a given year
	 */
	public function getDistinctMonthsByYear(int $year): array
	{
		$sql = "
			SELECT DISTINCT MONTH(a.`date`) AS month
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE YEAR(a.`date`) = :year
			ORDER BY month ASC
		";
		return $this->query($sql, ['year' => $year]);
	}

	/**
	 * Monthly GT aggregates (gtcs, gtpy, gtet, gtdc, gtrf) grouped by month for a given year
	 */
	public function getMonthlyGtByYear(int $year): array
	{
		$sql = "
			SELECT 
				MONTH(a.`date`) AS month,
				SUM(a.gtcs) AS gtcs,
				SUM(a.gtpy) AS gtpy,
				SUM(a.gtet) AS gtet,
				SUM(a.gtdc) AS gtdc,
				SUM(a.gtrf) AS gtrf
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE YEAR(a.`date`) = :year
			GROUP BY MONTH(a.`date`)
			ORDER BY MONTH(a.`date`) ASC
		";

		return $this->query($sql, ['year' => $year]);
	}

	/**
	 * Yearly agent connect summary grouped by agent for a given year
	 */
	public function getYearlyAgentConnectSummary(int $year): array
	{
		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_case_completed) AS calls_answered,
				SEC_TO_TIME(SUM(TIME_TO_SEC(a.total_connected_time))) AS total_call_connect,
				SUM(a.ticket_issued) AS ticket_total,
				SEC_TO_TIME(SUM(TIME_TO_SEC(a.total_gtbk))) AS total_time_connect,
				SUM(a.ticket_audited) AS call_total
			FROM wpk4_agent_after_sale_productivity_report a
			WHERE YEAR(a.`date`) = :year
			GROUP BY a.agent_name
			ORDER BY calls_answered DESC
		";

		return $this->query($sql, ['year' => $year]);
	}

	/**
	 * Agent ticket summary by date range (optional agent), excluding ABDN
	 */
	public function getAgentTicketSummary(
		string $startDate,
		string $endDate,
		?string $agentName = null
	): array {
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate,
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date",
			"a.agent_name <> 'ABDN'"
		];

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT 
				a.agent_name, 
				SUM(a.fit_ticketed) AS fit_ticketed,
				SUM(a.gdeal_ticketed) AS gdeal_ticketed,
				SUM(a.ticket_issued) AS ticket_issued,
				SUM(a.ctg) AS ctg,
				SUM(a.gkt_iata) AS gkt_iata,
				SUM(a.ifn_iata) AS ifn_iata,
				SUM(a.gilpin) AS gilpin,
				SUM(a.CCUVS32NQ) AS CCUVS32NQ,
				SUM(a.MELA821CV) AS MELA821CV,
				SUM(a.I5FC) AS I5FC,
				SUM(a.MELA828FN) AS MELA828FN,
				SUM(a.CCUVS32MV) AS CCUVS32MV
			FROM wpk4_agent_after_sale_productivity_report a
			{$whereSql}
			GROUP BY a.agent_name
			HAVING SUM(a.ticket_issued) > 0
			ORDER BY a.agent_name ASC
		";

		return $this->query($sql, $params);
	}

	/**
	 * Agent ticket summary by date range with active agent code filter (joins agent_codes on TSR)
	 */
	public function getAgentTicketSummaryActive(
		string $startDate,
		string $endDate,
		?string $agentName = null
	): array {
		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate,
		];

		$where = [
			"a.`date` BETWEEN :start_date AND :end_date",
			"a.agent_name <> 'ABDN'"
		];

		if (!empty($agentName)) {
			$where[] = "a.agent_name = :agent_name";
			$params['agent_name'] = $agentName;
		}

		$whereSql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT 
				a.agent_name, 
				SUM(a.fit_ticketed) AS fit_ticketed,
				SUM(a.gdeal_ticketed) AS gdeal_ticketed,
				SUM(a.ticket_issued) AS ticket_issued,
				SUM(a.ctg) AS ctg,
				SUM(a.gkt_iata) AS gkt_iata,
				SUM(a.ifn_iata) AS ifn_iata,
				SUM(a.gilpin) AS gilpin,
				SUM(a.CCUVS32NQ) AS CCUVS32NQ,
				SUM(a.MELA821CV) AS MELA821CV,
				SUM(a.I5FC) AS I5FC,
				SUM(a.MELA828FN) AS MELA828FN,
				SUM(a.CCUVS32MV) AS CCUVS32MV
			FROM wpk4_agent_after_sale_productivity_report a
			JOIN wpk4_backend_agent_codes b
			  ON a.tsr = b.tsr
			 AND UPPER(b.status) = 'ACTIVE'
			{$whereSql}
			GROUP BY a.agent_name
			HAVING SUM(a.ticket_issued) > 0
			ORDER BY a.agent_name ASC
		";

		return $this->query($sql, $params);
	}
	/**
	 * All-time agent connect summary grouped by agent
	 */
	public function getAgentConnectSummaryAllTime(): array
	{
		$sql = "
			SELECT 
				a.agent_name,
				SUM(a.dc_case_completed) AS calls_answered,
				SEC_TO_TIME(SUM(TIME_TO_SEC(a.total_connected_time))) AS total_call_connect,
				SUM(a.ticket_issued) AS ticket_total,
				SEC_TO_TIME(SUM(TIME_TO_SEC(a.total_gtbk))) AS total_time_connect,
				SUM(a.ticket_audited) AS call_total
			FROM wpk4_agent_after_sale_productivity_report a
			GROUP BY a.agent_name
			ORDER BY calls_answered DESC
		";

		return $this->query($sql);
	}
}


