<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AfterSaleAbdnCallStatusLogDAL extends BaseDAL
{
	/**
	 * Fetch rows from wpk4_agent_after_sale_abdn_call_status_log filtered by optional start/end dates.
	 * Dates are inclusive. Ordered by `date` DESC.
	 */
	public function getByDateRange(?string $startDate, ?string $endDate): array
	{
		$conditions = [];
		$params = [];

		if (!empty($startDate)) {
			$conditions[] = "`date` >= :start_date";
			$params['start_date'] = $startDate;
		}
		if (!empty($endDate)) {
			$conditions[] = "`date` <= :end_date";
			$params['end_date'] = $endDate;
		}

		$whereSql = '';
		if (!empty($conditions)) {
			$whereSql = 'WHERE ' . implode(' AND ', $conditions);
		}

		$sql = "SELECT * FROM wpk4_agent_after_sale_abdn_call_status_log {$whereSql} ORDER BY `date` DESC";
		return $this->query($sql, $params);
	}
}


