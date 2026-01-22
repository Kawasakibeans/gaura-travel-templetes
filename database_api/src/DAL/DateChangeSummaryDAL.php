<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class DateChangeSummaryDAL extends BaseDAL
{
	/**
	 * Get monthly summary within a day window (inclusive) for wpk4_backend_datechange_summary
	 */
	public function getSummaryByMonthAndDayRange(string $monthYmd, int $startDay, int $endDay): array
	{
		$sql = "
			SELECT 
				SUM(dc_request) AS dc_request,
				SUM(cases_worked) AS cases_worked,
				SUM(converted_case) AS converted_case,
				SUM(converted_pax) AS converted_pax,
				SUM(revenue) AS revenue
			FROM wpk4_backend_datechange_summary
			WHERE DATE_FORMAT(request_date, '%Y-%m') = :month
			  AND DAY(request_date) BETWEEN :start_day AND :end_day
		";

		$params = [
			'month' => $monthYmd,
			'start_day' => $startDay,
			'end_day' => $endDay,
		];

		$result = $this->queryOne($sql, $params);
		return $result ?: [
			'dc_request' => 0,
			'cases_worked' => 0,
			'converted_case' => 0,
			'converted_pax' => 0,
			'revenue' => 0,
		];
	}

		/**
		 * Get monthly sales summary (gdeals/fit and revenue) for given month/day range
		 */
		public function getSalesSummaryByMonthAndDayRange(string $monthYmd, int $startDay, int $endDay): array
		{
			$sql = "
				SELECT 
					SUM(dc_request) AS dc_request,
					SUM(cases_worked) AS cases_worked,
					SUM(gdeals) AS gdeals,
					SUM(fit) AS fit,
					SUM(gdeals_revenue) AS gdeals_revenue,
					SUM(fit_revenue) AS fit_revenue
				FROM wpk4_backend_datechange_summary
				WHERE DATE_FORMAT(request_date, '%Y-%m') = :month
				  AND DAY(request_date) BETWEEN :start_day AND :end_day
			";

			$params = [
				'month' => $monthYmd,
				'start_day' => $startDay,
				'end_day' => $endDay,
			];

			$result = $this->queryOne($sql, $params);
			return $result ?: [
				'dc_request' => 0,
				'cases_worked' => 0,
				'gdeals' => 0,
				'fit' => 0,
				'gdeals_revenue' => 0,
				'fit_revenue' => 0,
			];
		}
}


