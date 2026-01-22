<?php

namespace App\Services;

use App\DAL\AfterSaleProductivityDAL;
use Exception;

class AfterSaleProductivityService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new AfterSaleProductivityDAL();
	}

	public function getDistinctAgentNames(array $queryParams): array
	{
		$location = $queryParams['location'] ?? '';
		if (trim($location) === '') {
			throw new Exception('location is required', 400);
		}
		$status = $queryParams['status'] ?? 'Active';
		return $this->dal->getDistinctAgentNamesByLocation(trim($location), trim($status));
	}

	public function getProductivitySummary(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;
		$location = $queryParams['location'] ?? 'BOM';
		$status = $queryParams['status'] ?? 'active';

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}

		$limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : null;
		$offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;

		if ($limit !== null && $limit <= 0) $limit = null;
		if ($offset < 0) $offset = 0;

		return $this->dal->getProductivitySummary(
			$startDate,
			$endDate,
			$agentName ? trim($agentName) : null,
			trim($location),
			trim($status),
			$limit,
			$offset
		);
	}

	public function getProductivitySummaryByAgent(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;
		$location = $queryParams['location'] ?? 'BOM';
		$status = $queryParams['status'] ?? 'active';

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}

		$limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : null;
		$offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;

		if ($limit !== null && $limit <= 0) $limit = null;
		if ($offset < 0) $offset = 0;

		return $this->dal->getProductivitySummaryByAgent(
			$startDate,
			$endDate,
			$agentName ? trim($agentName) : null,
			trim($location),
			trim($status),
			$limit,
			$offset
		);
	}

	public function getAgentMetrics(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;
		$startDay = isset($queryParams['start_day']) ? (int)$queryParams['start_day'] : null;
		$endDay = isset($queryParams['end_day']) ? (int)$queryParams['end_day'] : null;

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}
		if (($startDay !== null && ($startDay < 1 || $startDay > 31)) || ($endDay !== null && ($endDay < 1 || $endDay > 31))) {
			throw new Exception('start_day/end_day must be 1..31', 400);
		}
		if (($startDay !== null && $endDay === null) || ($startDay === null && $endDay !== null)) {
			throw new Exception('Both start_day and end_day must be provided together', 400);
		}
		if ($startDay !== null && $endDay !== null && $endDay < $startDay) {
			throw new Exception('end_day must be >= start_day', 400);
		}

		return $this->dal->getAgentMetrics(
			$startDate,
			$endDate,
			$startDay,
			$endDay,
			$agentName ? trim($agentName) : null
		);
	}

	public function getMonthlyAgentCallSummary(array $queryParams): array
	{
		$month = $queryParams['month'] ?? null; // YYYY-MM
		$startDay = isset($queryParams['start_day']) ? (int)$queryParams['start_day'] : null;
		$endDay = isset($queryParams['end_day']) ? (int)$queryParams['end_day'] : null;

		if (!$month || !preg_match('/^\\d{4}-\\d{2}$/', $month)) {
			throw new Exception('month (YYYY-MM) is required', 400);
		}
		if ($startDay === null || $endDay === null) {
			throw new Exception('start_day and end_day are required', 400);
		}
		if ($startDay < 1 || $startDay > 31 || $endDay < 1 || $endDay > 31) {
			throw new Exception('start_day and end_day must be in range 1..31', 400);
		}
		if ($endDay < $startDay) {
			throw new Exception('end_day must be >= start_day', 400);
		}

		return $this->dal->getMonthlyAgentCallSummary($month, $startDay, $endDay);
	}

	public function getAgentSuccessSummary(array $queryParams): array
	{
		$month = $queryParams['month'] ?? null; // YYYY-MM (optional)
		$startDay = isset($queryParams['start_day']) ? (int)$queryParams['start_day'] : null;
		$endDay = isset($queryParams['end_day']) ? (int)$queryParams['end_day'] : null;

		if ($month !== null && !preg_match('/^\\d{4}-\\d{2}$/', $month)) {
			throw new Exception('month must be YYYY-MM when provided', 400);
		}
		if (($startDay !== null && ($startDay < 1 || $startDay > 31)) || ($endDay !== null && ($endDay < 1 || $endDay > 31))) {
			throw new Exception('start_day/end_day must be 1..31', 400);
		}
		if (($startDay !== null && $endDay === null) || ($startDay === null && $endDay !== null)) {
			throw new Exception('Both start_day and end_day must be provided together', 400);
		}
		if ($startDay !== null && $endDay !== null && $endDay < $startDay) {
			throw new Exception('end_day must be >= start_day', 400);
		}

		return $this->dal->getAgentSuccessSummary($month, $startDay, $endDay);
	}

	public function getDistinctMonths(array $queryParams): array
	{
		$year = $queryParams['year'] ?? null;
		if ($year === null || !preg_match('/^\\d{4}$/', (string)$year)) {
			throw new Exception('year (YYYY) is required', 400);
		}
		return $this->dal->getDistinctMonthsByYear((int)$year);
	}

	public function getMonthlyGt(array $queryParams): array
	{
		$year = $queryParams['year'] ?? null;
		if ($year === null || !preg_match('/^\\d{4}$/', (string)$year)) {
			throw new Exception('year (YYYY) is required', 400);
		}
		return $this->dal->getMonthlyGtByYear((int)$year);
	}

	public function getYearlyAgentConnectSummary(array $queryParams): array
	{
		$year = $queryParams['year'] ?? null;
		if ($year === null || !preg_match('/^\\d{4}$/', (string)$year)) {
			throw new Exception('year (YYYY) is required', 400);
		}
		return $this->dal->getYearlyAgentConnectSummary((int)$year);
	}

	public function getAgentConnectSummaryAll(array $queryParams): array
	{
		return $this->dal->getAgentConnectSummaryAllTime();
	}

	public function getAgentTicketSummary(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}

		return $this->dal->getAgentTicketSummary(
			$startDate,
			$endDate,
			$agentName ? trim($agentName) : null
		);
	}

	public function getAgentTicketSummaryActive(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}

		return $this->dal->getAgentTicketSummaryActive(
			$startDate,
			$endDate,
			$agentName ? trim($agentName) : null
		);
	}

	public function getAgentDcSummaryByDateRangeExcludeAbdn(array $queryParams): array
	{
		$startDate = $queryParams['start_date'] ?? null;
		$endDate = $queryParams['end_date'] ?? null;
		$agentName = $queryParams['agent_name'] ?? null;

		if (!$startDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('start_date (YYYY-MM-DD) is required', 400);
		}
		if (!$endDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('end_date (YYYY-MM-DD) is required', 400);
		}

		return $this->dal->getAgentDcSummaryByDateRangeExcludeAbdn(
			$startDate,
			$endDate,
			$agentName ? trim($agentName) : null
		);
	}
	public function getAgentSuccessSummaryByYearMonth(array $queryParams): array
	{
		$year = $queryParams['year'] ?? null;
		$monthNum = isset($queryParams['month']) ? (int)$queryParams['month'] : null;
		$startDay = isset($queryParams['start_day']) ? (int)$queryParams['start_day'] : null;
		$endDay = isset($queryParams['end_day']) ? (int)$queryParams['end_day'] : null;

		if ($year === null || !preg_match('/^\\d{4}$/', (string)$year)) {
			throw new Exception('year (YYYY) is required', 400);
		}
		if ($monthNum === null || $monthNum < 1 || $monthNum > 12) {
			throw new Exception('month (1..12) is required', 400);
		}
		if ($startDay === null || $endDay === null) {
			throw new Exception('start_day and end_day are required', 400);
		}
		if ($startDay < 1 || $startDay > 31 || $endDay < 1 || $endDay > 31) {
			throw new Exception('start_day and end_day must be in range 1..31', 400);
		}
		if ($endDay < $startDay) {
			throw new Exception('end_day must be >= start_day', 400);
		}

		return $this->dal->getAgentSuccessSummaryByYearMonth((int)$year, (int)$monthNum, (int)$startDay, (int)$endDay);
	}

	public function getAgentSuccessSummaryByYearMonthNoDay(array $queryParams): array
	{
		$year = $queryParams['year'] ?? null;
		$monthNum = isset($queryParams['month']) ? (int)$queryParams['month'] : null;

		if ($year === null || !preg_match('/^\\d{4}$/', (string)$year)) {
			throw new Exception('year (YYYY) is required', 400);
		}
		if ($monthNum === null || $monthNum < 1 || $monthNum > 12) {
			throw new Exception('month (1..12) is required', 400);
		}

		return $this->dal->getAgentSuccessSummaryByYearMonthNoDay((int)$year, (int)$monthNum);
	}
}


