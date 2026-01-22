<?php

namespace App\Services;

use App\DAL\AfterSaleAbdnCallStatusLogDAL;
use Exception;

class AfterSaleAbdnCallStatusLogService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new AfterSaleAbdnCallStatusLogDAL();
	}

	public function getLogs(array $queryParams): array
	{
		$today = date('Y-m-d');
		$startDate = $queryParams['start_date'] ?? $today;
		$endDate = $queryParams['end_date'] ?? $today;

		if (!empty($startDate) && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDate)) {
			throw new Exception('Invalid start_date. Expected YYYY-MM-DD', 400);
		}
		if (!empty($endDate) && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDate)) {
			throw new Exception('Invalid end_date. Expected YYYY-MM-DD', 400);
		}

		return $this->dal->getByDateRange($startDate, $endDate);
	}
}


