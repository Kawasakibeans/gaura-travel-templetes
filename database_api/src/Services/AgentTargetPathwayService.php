<?php

namespace App\Services;

use App\DAL\AgentTargetPathwayDAL;
use Exception;

class AgentTargetPathwayService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new AgentTargetPathwayDAL();
	}

	public function getByRosterCodeAndPeriod(array $queryParams): array
	{
		$rosterCode = $queryParams['roster_code'] ?? '';
		$period = $queryParams['period'] ?? '';

		$rosterCode = trim($rosterCode);
		$period = trim($period);

		if ($rosterCode === '') {
			throw new Exception('roster_code is required', 400);
		}
		if ($period === '') {
			throw new Exception('period is required', 400);
		}

		$row = $this->dal->getByRosterCodeAndPeriod($rosterCode, $period);
		return $row ?? [];
	}

		public function getLatestByRosterCode(array $queryParams): array
		{
			$rosterCode = $queryParams['roster_code'] ?? '';
			$rosterCode = trim($rosterCode);
			if ($rosterCode === '') {
				throw new Exception('roster_code is required', 400);
			}

			$row = $this->dal->getLatestByRosterCode($rosterCode);
			return $row ?? [];
		}

	public function listByRosterCodeAndPeriod(array $queryParams): array
	{
		$rosterCode = trim($queryParams['roster_code'] ?? '');
		$period = trim($queryParams['period'] ?? '');
		if ($rosterCode === '') {
			throw new Exception('roster_code is required', 400);
		}
		if ($period === '') {
			throw new Exception('period is required', 400);
		}
		return $this->dal->getAllByRosterCodeAndPeriod($rosterCode, $period);
	}

	/**
	 * Create a history record from provided payload
	 */
	public function createHistory(array $input): array
	{
		$required = ['roster_code','target','period','conversion','rate','fcs_mult','rate_fcs','gtib_bonus','min_gtib','min_pif','daily_pif','total_estimate','created_at'];
		foreach ($required as $key) {
			if (!array_key_exists($key, $input)) {
				throw new Exception("{$key} is required", 400);
			}
		}
		$id = $this->dal->insertHistory($input);
		return ['id' => $id];
	}

	/**
	 * Upsert (insert/update) agent target pathway
	 */
	public function upsertPathway(array $input): array
	{
		$required = ['roster_code','target','period','conversion','rate','fcs_mult','rate_fcs','gtib_bonus','min_gtib','min_pif','daily_pif','total_estimate'];
		foreach ($required as $key) {
			if (!array_key_exists($key, $input)) {
				throw new Exception("{$key} is required", 400);
			}
		}
		$success = $this->dal->upsertPathway($input);
		return ['success' => (bool)$success];
	}
}


