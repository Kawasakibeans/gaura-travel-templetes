<?php

namespace App\Services;

use App\DAL\DateChangeSummaryDAL;
use Exception;

class DateChangeSummaryService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new DateChangeSummaryDAL();
	}

	public function getSummary(array $queryParams): array
	{
		$month = $queryParams['month'] ?? null;           // YYYY-MM
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

		return $this->dal->getSummaryByMonthAndDayRange($month, $startDay, $endDay);
	}

		public function getSalesSummary(array $queryParams): array
		{
			$month = $queryParams['month'] ?? null;           // YYYY-MM
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

			return $this->dal->getSalesSummaryByMonthAndDayRange($month, $startDay, $endDay);
		}
}


