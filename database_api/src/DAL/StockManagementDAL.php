<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class StockManagementDAL extends BaseDAL
{
	/**
	 * Fetch stock + payment rows with optional filters and pagination
	 */
	public function getStockWithPayments(array $filters, int $limit = 30, int $offset = 0): array
	{
		$select = "
			SELECT
				stock.auto_id AS stockautoid,
				payment.auto_id,
				stock.dep_date,
				payment.group_name,
				stock.trip_id,
				stock.route,
				stock.route_type,
				stock.source,
				payment.pnr,
				payment.emd,
				stock.original_stock,
				stock.stock_release,
				stock.stock_unuse,
				stock.current_stock,
				payment.payment_remarks,
				payment.group_id,
				payment.ticketing_deadline,
				payment.payment_deadline,
				payment.deposit_deadline,
				payment.deposit_amount,
				payment.outstanding_amount,
				payment.deposited_on,
				payment.paid_on,
				payment.refund_on,
				payment.split_on,
				payment.done_on,
				payment.overwrite_status,
				payment.ticketed_on
		";

		$sql = "
			$select
			FROM wpk4_backend_stock_management_sheet AS stock
			JOIN wpk4_backend_airlines_payment_details AS payment
				ON stock.pnr = payment.pnr
			WHERE 1=1
		";

		$params = [];

		// only_open (default true): when true, enforce open-stock condition
		$onlyOpen = true;
		if (array_key_exists('only_open', $filters)) {
			$onlyOpen = (bool)$filters['only_open'];
		}
		if ($onlyOpen) {
			$sql .= " AND (stock.original_stock - stock.stock_release - stock.stock_unuse) != 0";
		}

		// trip_id filter
		if (!empty($filters['trip_id'])) {
			$sql .= " AND stock.trip_id = :trip_id";
			$params['trip_id'] = $filters['trip_id'];
		}

		// exact dep_date
		if (!empty($filters['dep_date'])) {
			$sql .= " AND stock.dep_date = :dep_date";
			$params['dep_date'] = $filters['dep_date'];
		}

		// dep_date range
		if (!empty($filters['dep_start']) && !empty($filters['dep_end'])) {
			$sql .= " AND stock.dep_date BETWEEN :dep_start AND :dep_end";
			$params['dep_start'] = $filters['dep_start'];
			$params['dep_end'] = $filters['dep_end'];
		} elseif (!empty($filters['dep_start'])) {
			$sql .= " AND stock.dep_date >= :dep_start";
			$params['dep_start'] = $filters['dep_start'];
		} elseif (!empty($filters['dep_end'])) {
			$sql .= " AND stock.dep_date <= :dep_end";
			$params['dep_end'] = $filters['dep_end'];
		}

		// pnr filter
		if (!empty($filters['pnr'])) {
			$sql .= " AND payment.pnr = :pnr";
			$params['pnr'] = $filters['pnr'];
		}

		// filter: only rows where paid_on IS NOT NULL
		if (!empty($filters['paid_on_not_null'])) {
			$sql .= " AND payment.paid_on IS NOT NULL";
		}

		// filter: only rows where ticketed_on IS NULL
		if (!empty($filters['ticketed_is_null'])) {
			$sql .= " AND payment.ticketed_on IS NULL";
		}

		// filter: moved_to_soldout OR unticketed (NULL or empty string)
		if (!empty($filters['moved_or_unticketed'])) {
			$sql .= " AND (payment.overwrite_status = 'moved_to_soldout' OR payment.ticketed_on IS NULL OR payment.ticketed_on = '')";
		}

		// custom date range on whitelisted payment date fields
		if (!empty($filters['custom_date_field']) && (!empty($filters['custom_start']) || !empty($filters['custom_end']))) {
			$allowed = [
				'ticketing_deadline',
				'payment_deadline',
				'deposit_deadline',
				'deposited_on',
				'paid_on',
				'refund_on',
				'split_on',
				'done_on',
				'ticketed_on'
			];
			$field = $filters['custom_date_field'];
			if (in_array($field, $allowed, true)) {
				if (!empty($filters['custom_start']) && !empty($filters['custom_end'])) {
					$sql .= " AND payment.$field BETWEEN :custom_start AND :custom_end";
					$params['custom_start'] = $filters['custom_start'];
					$params['custom_end'] = $filters['custom_end'];
				} elseif (!empty($filters['custom_start'])) {
					$sql .= " AND payment.$field >= :custom_start";
					$params['custom_start'] = $filters['custom_start'];
				} else {
					$sql .= " AND payment.$field <= :custom_end";
					$params['custom_end'] = $filters['custom_end'];
				}
			}
		}

		$sql .= " ORDER BY stock.dep_date ASC, stock.trip_id ASC";
		$sql .= " LIMIT :offset, :limit";

		// Ensure integers for limit/offset
		$params['offset'] = (int)$offset;
		$params['limit'] = (int)$limit;

		return $this->query($sql, $params, [
			'offset' => \PDO::PARAM_INT,
			'limit' => \PDO::PARAM_INT
		]);
	}

	/**
	 * Update a single field for a stock row by auto_id.
	 * Column name is validated against a whitelist to prevent SQL injection.
	 */
	public function updateFieldById(int $autoId, string $columnName, $value): bool
	{
		$allowedColumns = [
			'dep_date',
			'trip_id',
			'route',
			'route_type',
			'source',
			'original_stock',
			'stock_release',
			'stock_unuse',
			'current_stock'
		];

		if (!in_array($columnName, $allowedColumns, true)) {
			throw new \Exception('Invalid column_name', 400);
		}

		$sql = "UPDATE wpk4_backend_stock_management_sheet SET `$columnName` = :val WHERE auto_id = :auto_id";
		return $this->execute($sql, [
			'val' => $value,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Get a single stock row by auto_id
	 */
	public function getById(int $autoId): ?array
	{
		$sql = "SELECT * FROM wpk4_backend_stock_management_sheet WHERE auto_id = :auto_id LIMIT 1";
		$row = $this->queryOne($sql, ['auto_id' => $autoId]);
		return $row ?: null;
	}

	/**
	 * Fetch stocks by trip_id LIKE (suffix match) and dep_date prefix
	 */
	public function getStocksByTripIdLikeAndDepDatePrefix(string $tripIdLike, string $depDatePrefix, int $limit = 50): array
	{
		$sql = "
			SELECT *
			FROM wpk4_backend_stock_management_sheet AS stock
			WHERE stock.trip_id LIKE :trip_like
			  AND stock.dep_date LIKE :dep_prefix
			ORDER BY stock.dep_date ASC, stock.trip_id ASC
			LIMIT :limit
		";
		$params = [
			'trip_like' => '%' . $tripIdLike,
			'dep_prefix' => $depDatePrefix . '%',
			'limit' => (int)$limit
		];
		return $this->query($sql, $params);
	}

	/**
	 * Fetch child stocks by trip_id LIKE (suffix), dep_date prefix, min PNR length, excluding a PNR list
	 */
	public function getChildStocksByTripLikeDatePrefixExcludePnrs(
		string $tripIdLike,
		string $depDatePrefix,
		array $excludePnrs = [],
		int $minPnrLength = 10,
		int $limit = 10
	): array {
		$limit = (int)$limit;
		if ($limit <= 0 || $limit > 200) {
			$limit = 10;
		}
		$sql = "
			SELECT *
			FROM wpk4_backend_stock_management_sheet_child
			WHERE trip_id LIKE :trip_like
			  AND dep_date LIKE :dep_prefix
			  AND CHAR_LENGTH(pnr) > :min_len
		";
		$params = [
			'trip_like' => '%' . $tripIdLike,
			'dep_prefix' => $depDatePrefix . '%',
			'min_len' => (int)$minPnrLength
		];

		if (!empty($excludePnrs)) {
			$placeholders = [];
			foreach ($excludePnrs as $idx => $pnr) {
				$key = 'pnr_' . $idx;
				$placeholders[] = ':' . $key;
				$params[$key] = $pnr;
			}
			$sql .= " AND pnr NOT IN (" . implode(',', $placeholders) . ")";
		}

		$sql .= " ORDER BY dep_date ASC, trip_id ASC LIMIT " . $limit;

		return $this->query($sql, $params);
	}
}


