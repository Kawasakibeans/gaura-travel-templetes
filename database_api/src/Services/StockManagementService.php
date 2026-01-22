<?php

namespace App\Services;

use App\DAL\StockManagementDAL;
use Exception;

class StockManagementService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new StockManagementDAL();
	}

	public function listStockWithPayments(array $query): array
	{
		$filters = [];

		// only_open (optional, default true). Accepts 0/1, true/false, yes/no
		$onlyOpen = true;
		if (isset($query['only_open'])) {
			$val = strtolower(trim((string)$query['only_open']));
			if (in_array($val, ['0','false','no'], true)) {
				$onlyOpen = false;
			}
		}
		$filters['only_open'] = $onlyOpen ? 1 : 0;

		// trip_id (optional)
		if (isset($query['trip_id'])) {
			$filters['trip_id'] = trim((string)$query['trip_id']);
		}

		// dep_date (optional, exact match)
		if (isset($query['dep_date'])) {
			$depDate = trim((string)$query['dep_date']);
			if ($depDate !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $depDate)) {
				throw new Exception('dep_date must be YYYY-MM-DD', 400);
			}
			if ($depDate !== '') $filters['dep_date'] = $depDate;
		}

		// dep_start / dep_end (optional, YYYY-MM-DD)
		$depStart = isset($query['dep_start']) ? trim((string)$query['dep_start']) : '';
		$depEnd = isset($query['dep_end']) ? trim((string)$query['dep_end']) : '';
		if ($depStart !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $depStart)) {
			throw new Exception('dep_start must be YYYY-MM-DD', 400);
		}
		if ($depEnd !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $depEnd)) {
			throw new Exception('dep_end must be YYYY-MM-DD', 400);
		}
		if ($depStart !== '') $filters['dep_start'] = $depStart;
		if ($depEnd !== '') $filters['dep_end'] = $depEnd;

		// pnr (optional)
		if (isset($query['pnr'])) {
			$filters['pnr'] = trim((string)$query['pnr']);
		}

		// paid_on_not_null (optional): include only rows with payment.paid_on IS NOT NULL
		if (isset($query['paid_on_not_null'])) {
			$val = strtolower(trim((string)$query['paid_on_not_null']));
			if (in_array($val, ['1','true','yes'], true)) {
				$filters['paid_on_not_null'] = 1;
			}
		}

		// ticketed_is_null (optional): include only rows with payment.ticketed_on IS NULL
		if (isset($query['ticketed_is_null'])) {
			$val = strtolower(trim((string)$query['ticketed_is_null']));
			if (in_array($val, ['1','true','yes'], true)) {
				$filters['ticketed_is_null'] = 1;
			}
		}

		// moved_or_unticketed (optional): overwrite_status='moved_to_soldout' OR ticketed_on IS NULL OR ticketed_on=''
		if (isset($query['moved_or_unticketed'])) {
			$val = strtolower(trim((string)$query['moved_or_unticketed']));
			if (in_array($val, ['1','true','yes'], true)) {
				$filters['moved_or_unticketed'] = 1;
			}
		}

		// custom date on payment.* field (optional)
		$customField = isset($query['custom_date_field']) ? trim((string)$query['custom_date_field']) : '';
		$customStart = isset($query['custom_start']) ? trim((string)$query['custom_start']) : '';
		$customEnd = isset($query['custom_end']) ? trim((string)$query['custom_end']) : '';
		if ($customField !== '') {
			$allowed = [
				'ticketing_deadline','payment_deadline','deposit_deadline','deposited_on',
				'paid_on','refund_on','split_on','done_on','ticketed_on'
			];
			if (!in_array($customField, $allowed, true)) {
				throw new Exception('Invalid custom_date_field', 400);
			}
			if ($customStart !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $customStart)) {
				throw new Exception('custom_start must be YYYY-MM-DD', 400);
			}
			if ($customEnd !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $customEnd)) {
				throw new Exception('custom_end must be YYYY-MM-DD', 400);
			}
			if ($customStart === '' && $customEnd === '') {
				throw new Exception('Provide custom_start or custom_end with custom_date_field', 400);
			}
			$filters['custom_date_field'] = $customField;
			if ($customStart !== '') $filters['custom_start'] = $customStart;
			if ($customEnd !== '') $filters['custom_end'] = $customEnd;
		}

		// pagination
		$limit = isset($query['limit']) ? (int)$query['limit'] : 30;
		$offset = isset($query['offset']) ? (int)$query['offset'] : 0;
		if ($limit <= 0 || $limit > 200) $limit = 30;
		if ($offset < 0) $offset = 0;

		return $this->dal->getStockWithPayments($filters, $limit, $offset);
	}

	public function updateStockField(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$column = isset($body['column_name']) ? trim((string)$body['column_name']) : '';
		if ($column === '') {
			throw new Exception('column_name is required', 400);
		}
		if (!array_key_exists('value', $body)) {
			throw new Exception('value is required', 400);
		}
		$value = $body['value'];

		// Basic format validation for known fields
		if (in_array($column, ['dep_date'], true)) {
			if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
				throw new Exception('dep_date must be YYYY-MM-DD', 400);
			}
		}

		$ok = $this->dal->updateFieldById($autoId, $column, $value);
		return [
			'auto_id' => $autoId,
			'column_name' => $column,
			'value' => $value,
			'updated' => (bool)$ok
		];
	}

	public function getStockById(int $autoId): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$row = $this->dal->getById($autoId);
		if (!$row) {
			throw new Exception('Stock row not found', 404);
		}
		return $row;
	}

	public function listStocksByTripAndDatePrefix(array $query): array
	{
		$tripLike = isset($query['trip_code_like']) ? trim((string)$query['trip_code_like']) : '';
		$depPrefix = isset($query['dep_date_prefix']) ? trim((string)$query['dep_date_prefix']) : '';
		$limit = isset($query['limit']) ? (int)$query['limit'] : 50;
		if ($limit <= 0 || $limit > 200) $limit = 50;

		if ($tripLike === '') {
			throw new Exception('trip_code_like is required', 400);
		}
		// Accept YYYY-MM or YYYY-MM-DD prefix
		if ($depPrefix === '' || !preg_match('/^\\d{4}-\\d{2}(?:-\\d{2})?$/', $depPrefix)) {
			throw new Exception('dep_date_prefix must be YYYY-MM or YYYY-MM-DD', 400);
		}

		return $this->dal->getStocksByTripIdLikeAndDepDatePrefix($tripLike, $depPrefix, $limit);
	}

	public function listChildStocksByTripDateExcludePnrs(array $query): array
	{
		$tripLike = isset($query['trip_code_like']) ? trim((string)$query['trip_code_like']) : '';
		$depPrefix = isset($query['dep_date_prefix']) ? trim((string)$query['dep_date_prefix']) : '';
		$limit = isset($query['limit']) ? (int)$query['limit'] : 10;
		if ($limit <= 0 || $limit > 200) $limit = 10;

		if ($tripLike === '') {
			throw new Exception('trip_code_like is required', 400);
		}
		if ($depPrefix === '' || !preg_match('/^\\d{4}-\\d{2}(?:-\\d{2})?$/', $depPrefix)) {
			throw new Exception('dep_date_prefix must be YYYY-MM or YYYY-MM-DD', 400);
		}

		// exclude_pnrs can be a comma-separated string
		$excludePnrsRaw = isset($query['exclude_pnrs']) ? (string)$query['exclude_pnrs'] : '';
		$excludePnrs = [];
		if ($excludePnrsRaw !== '') {
			foreach (explode(',', $excludePnrsRaw) as $pnr) {
				$pnr = trim($pnr);
				if ($pnr !== '') {
					$excludePnrs[] = $pnr;
				}
			}
		}

		// Enforce min PNR length > 9 as per requirement
		$minPnrLength = 10;

		return $this->dal->getChildStocksByTripLikeDatePrefixExcludePnrs(
			$tripLike,
			$depPrefix,
			$excludePnrs,
			$minPnrLength,
			$limit
		);
	}
}


