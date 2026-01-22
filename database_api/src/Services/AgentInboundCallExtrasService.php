<?php

namespace App\Services;

use App\DAL\AgentInboundCallDAL;
use Exception;

class AgentInboundCallExtrasService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new AgentInboundCallDAL();
	}

	/**
	 * Get auto_ids for update with filters.
	 * Accepts since_days (default 10) or since_date (YYYY-MM-DD), plus optional agent_name and date.
	 */
	public function getAutoIdsForUpdate(array $queryParams): array
	{
		$sinceDate = $queryParams['since_date'] ?? null;
		$sinceDays = isset($queryParams['since_days']) ? (int)$queryParams['since_days'] : 10;

		if (!$sinceDate) {
			if ($sinceDays <= 0) {
				$sinceDays = 10;
			}
			$sinceDate = date('Y-m-d', strtotime('-' . $sinceDays . ' day'));
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $sinceDate)) {
				throw new Exception('since_date must be YYYY-MM-DD', 400);
			}
		}

		$agentName = $queryParams['agent_name'] ?? null;
		$filterDate = $queryParams['date'] ?? null;

		if ($filterDate !== null && $filterDate !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $filterDate)) {
			throw new Exception('date must be YYYY-MM-DD', 400);
		}

		$autoIds = $this->dal->findAutoIdsForUpdate($sinceDate, $agentName ? trim($agentName) : null, $filterDate ? trim($filterDate) : null);
		return ['since_date' => $sinceDate, 'count' => count($autoIds), 'auto_ids' => $autoIds];
	}

	/**
	 * Update flags for a single auto_id
	 */
	public function updateFlags(array $input): array
	{
		$autoId = isset($input['auto_id']) ? (int)$input['auto_id'] : 0;
		$malpractice = isset($input['malpractice']) ? (int)$input['malpractice'] : 0;
		$profanity = isset($input['profanity']) ? (int)$input['profanity'] : 0;
		$misbehavior = isset($input['misbehavior']) ? (int)$input['misbehavior'] : 0;

		if ($autoId <= 0) {
			throw new Exception('auto_id must be a positive integer', 400);
		}

		$ok = $this->dal->updateFlags($autoId, $malpractice, $profanity, $misbehavior);
		return ['auto_id' => $autoId, 'updated' => (bool)$ok];
	}
}


