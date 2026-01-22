<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class StockProductManagerDAL extends BaseDAL
{
	/**
	 * Get product_ids by trip_code and travel_date
	 */
	public function getProductIdsByTripAndDate(string $tripCode, string $travelDate): array
	{
		$sql = "
			SELECT product_id
			FROM wpk4_backend_stock_product_manager
			WHERE trip_code = :trip_code AND travel_date = :travel_date
		";
		$rows = $this->query($sql, [
			'trip_code' => $tripCode,
			'travel_date' => $travelDate
		]);
		return array_map(static function ($row) { return $row['product_id']; }, $rows);
	}

	/**
	 * Get trip_code by product_id and travel_date
	 */
	public function getTripCodeByProductAndDate(string $productId, string $travelDate): ?string
	{
		$sql = "
			SELECT trip_code
			FROM wpk4_backend_stock_product_manager
			WHERE product_id = :product_id AND travel_date = :travel_date
			LIMIT 1
		";
		$row = $this->queryOne($sql, [
			'product_id' => $productId,
			'travel_date' => $travelDate
		]);
		return $row['trip_code'] ?? null;
	}
}


