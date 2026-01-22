<?php

namespace App\Services;

use App\DAL\TicketNumberHotfileDAL;
use Exception;

class TicketNumberHotfileService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new TicketNumberHotfileDAL();
	}

	public function create(array $input): array
	{
		// Minimal validation - require document and added_by
		if (empty($input['document'])) {
			throw new Exception('document is required', 400);
		}
		if (!isset($input['added_by'])) {
			throw new Exception('added_by is required', 400);
		}

		$id = $this->dal->create($input);
		return ['id' => $id];
	}

	public function listByDateVendor(array $queryParams): array
	{
		$from = $queryParams['from_date'] ?? null;
		$to = $queryParams['to_date'] ?? null;
		$vendor = $queryParams['vendor'] ?? null; // exact value

		if (!$from || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) {
			throw new Exception('from_date (YYYY-MM-DD) is required', 400);
		}
		if (!$to || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
			throw new Exception('to_date (YYYY-MM-DD) is required', 400);
		}

		return $this->dal->getTicketsByDateAndVendor($from, $to, $vendor ? trim($vendor) : null);
	}

	public function listNonHotfileByDate(array $queryParams): array
	{
		$from = $queryParams['from_date'] ?? null;
		$to = $queryParams['to_date'] ?? null;

		if (!$from || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) {
			throw new Exception('from_date (YYYY-MM-DD) is required', 400);
		}
		if (!$to || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
			throw new Exception('to_date (YYYY-MM-DD) is required', 400);
		}

		return $this->dal->getNonHotfileTicketsByDate($from, $to);
	}
}


