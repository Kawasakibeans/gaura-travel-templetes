<?php

namespace App\Services;

use App\DAL\CallCenterDAL;
use Exception;

class CallCenterService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new CallCenterDAL();
	}

	/**
	 * Get call center data using query parameters
	 */
	public function getCallCenterData(array $queryParams): array
	{
		$filterDate = $queryParams['filter_date'] ?? date('Y-m-d');

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
			throw new Exception('Invalid filter_date. Expected format: YYYY-MM-DD', 400);
		}

		$department = $queryParams['department'] ?? null;
		$category = $queryParams['category'] ?? null;

		$allRecords = filter_var($queryParams['all_records'] ?? false, FILTER_VALIDATE_BOOLEAN);
		$start = isset($queryParams['start']) ? (int)$queryParams['start'] : 0;
		$perPage = isset($queryParams['perPage']) ? (int)$queryParams['perPage'] : 10;

		if (!$allRecords) {
			if ($start < 0) $start = 0;
			if ($perPage <= 0 || $perPage > 1000) $perPage = 10;
		}

		return $this->dal->fetchCallCenterData($filterDate, $department, $category, $allRecords, $start, $perPage);
	}
}


