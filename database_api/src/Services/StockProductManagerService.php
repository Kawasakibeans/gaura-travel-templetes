<?php

namespace App\Services;

use App\DAL\StockProductManagerDAL;
use Exception;

class StockProductManagerService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new StockProductManagerDAL();
	}

	public function getProductIdsByTripAndDate(array $queryParams): array
	{
		$tripCode = trim($queryParams['trip_code'] ?? '');
		$travelDate = trim($queryParams['travel_date'] ?? '');

		if ($tripCode === '') {
			throw new Exception('trip_code is required', 400);
		}
		if ($travelDate === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $travelDate)) {
			throw new Exception('travel_date (YYYY-MM-DD) is required', 400);
		}

		$productIds = $this->dal->getProductIdsByTripAndDate($tripCode, $travelDate);
		return ['trip_code' => $tripCode, 'travel_date' => $travelDate, 'product_ids' => $productIds];
	}

	public function getTripCodeByProductAndDate(array $queryParams): array
	{
		$productId = trim($queryParams['product_id'] ?? '');
		$travelDate = trim($queryParams['travel_date'] ?? '');

		if ($productId === '') {
			throw new Exception('product_id is required', 400);
		}
		if ($travelDate === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $travelDate)) {
			throw new Exception('travel_date (YYYY-MM-DD) is required', 400);
		}

		$tripCode = $this->dal->getTripCodeByProductAndDate($productId, $travelDate);
		return ['product_id' => $productId, 'travel_date' => $travelDate, 'trip_code' => $tripCode];
	}
}


