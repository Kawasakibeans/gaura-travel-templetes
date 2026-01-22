<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class CallCenterDAL extends BaseDAL
{
	/**
	 * Fetch call center booking/note data with filters and pagination
	 */
	public function fetchCallCenterData(string $filterDate, ?string $department, ?string $category, bool $allRecords, int $start, int $perPage): array
	{
		$prefix = $this->tablePrefix();
		$startDate = $filterDate . ' 00:00:00';
		$endDate = $filterDate . ' 23:59:59';

		$where = [
			"(bns.updated_on BETWEEN :start_date AND :end_date AND orderpax.order_id IS NOT NULL AND bns.additional_note IS NOT NULL)"
		];

		$params = [
			'start_date' => $startDate,
			'end_date' => $endDate
		];

		if (!empty($department)) {
			$where[] = "(bns.note_department = :department)";
			$params['department'] = $department;
		}

		if (!empty($category)) {
			$where[] = "(bns.note_category = :category)";
			$params['category'] = $category;
		}

		$whereClause = implode(' AND ', $where);

		$sql = "
			SELECT
				orderpax.order_id AS Booking_ID,
				orderpax.order_date AS Booking_Date,
				orderpax.agent_info AS Sales_Agent,
				CASE 
					WHEN MIN(orderpax.travel_date) > CURRENT_DATE THEN MIN(orderpax.travel_date)
					ELSE MAX(orderpax.travel_date)
				END AS Travel_Date,
				orderpax.order_type AS Booking_Type,
				SUM(orderpax.total_pax) AS Total_Pax,
				bns.type_id,
				MAX(bns.auto_id) AS Note_ID,
				MAX(bns.updated_on) AS Note_Added_On,
				MAX(CASE WHEN bns.meta_key = 'Booking Note Category' THEN bns.meta_value END) AS Note_Category,
				MAX(CASE WHEN bns.meta_key = 'Booking Note Description' THEN bns.meta_value END) AS Note_Description,
				MAX(CASE WHEN bns.meta_key = 'Booking Note Department' THEN bns.meta_value END) AS Note_Department,
				MAX(bns.updated_by) AS cs_agent
			FROM 
				(
					SELECT *
					FROM {$prefix}backend_history_of_updates
					WHERE updated_on >= NOW() - INTERVAL 7 DAY
					  AND additional_note IS NOT NULL
				) AS bns
			JOIN 
				{$prefix}backend_travel_bookings orderpax
				ON bns.type_id = orderpax.order_id
			WHERE {$whereClause}
			GROUP BY 
				orderpax.order_id, 
				orderpax.order_date, 
				orderpax.agent_info, 
				orderpax.order_type, 
				bns.type_id
		";

		if (!$allRecords) {
			$start = max(0, (int)$start);
			$perPage = max(1, (int)$perPage);
			$sql .= " LIMIT {$start}, {$perPage}";
		}

		return $this->query($sql, $params);
	}
}


