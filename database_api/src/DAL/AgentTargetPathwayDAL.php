<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentTargetPathwayDAL extends BaseDAL
{
	/**
	 * Get single agent target pathway row by roster_code and period
	 */
	public function getByRosterCodeAndPeriod(string $rosterCode, string $period): ?array
	{
		$sql = "
			SELECT *
			FROM wpk4_backend_agent_target_pathway
			WHERE roster_code = :roster_code AND period = :period
			LIMIT 1
		";

		$row = $this->queryOne($sql, [
			'roster_code' => $rosterCode,
			'period' => $period
		]);

		return $row ?: null;
	}

		/**
		 * Get latest agent target pathway by roster_code (ordered by created_at DESC)
		 */
		public function getLatestByRosterCode(string $rosterCode): ?array
		{
			$sql = "
				SELECT *
				FROM wpk4_backend_agent_target_pathway
				WHERE roster_code = :roster_code
				ORDER BY created_at DESC
				LIMIT 1
			";

			$row = $this->queryOne($sql, [
				'roster_code' => $rosterCode
			]);

			return $row ?: null;
		}

	/**
	 * Get all agent target pathway rows by roster_code and period
	 */
	public function getAllByRosterCodeAndPeriod(string $rosterCode, string $period): array
	{
		$sql = "
			SELECT *
			FROM wpk4_backend_agent_target_pathway
			WHERE roster_code = :roster_code AND period = :period
		";

		return $this->query($sql, [
			'roster_code' => $rosterCode,
			'period' => $period
		]);
	}

	/**
	 * Insert a history record into wpk4_backend_agent_target_pathway_history
	 * Returns inserted ID
	 */
	public function insertHistory(array $data): int
	{
		$sql = "
			INSERT INTO wpk4_backend_agent_target_pathway_history
			(roster_code, target, period, conversion, rate, fcs_mult, rate_fcs, 
			 gtib_bonus, min_gtib, min_pif, daily_pif, total_estimate, created_at)
			VALUES
			(:roster_code, :target, :period, :conversion, :rate, :fcs_mult, :rate_fcs,
			 :gtib_bonus, :min_gtib, :min_pif, :daily_pif, :total_estimate, :created_at)
		";

		$params = [
			'roster_code' => $data['roster_code'],
			'target' => $data['target'],
			'period' => $data['period'],
			'conversion' => $data['conversion'],
			'rate' => $data['rate'],
			'fcs_mult' => $data['fcs_mult'],
			'rate_fcs' => $data['rate_fcs'],
			'gtib_bonus' => $data['gtib_bonus'],
			'min_gtib' => $data['min_gtib'],
			'min_pif' => $data['min_pif'],
			'daily_pif' => $data['daily_pif'],
			'total_estimate' => $data['total_estimate'],
			'created_at' => $data['created_at'],
		];

		$this->execute($sql, $params);
		return (int)$this->db->lastInsertId();
	}

	/**
	 * Upsert agent target pathway (insert or update on duplicate key)
	 */
	public function upsertPathway(array $data): bool
	{
		$sql = "
			INSERT INTO wpk4_backend_agent_target_pathway (
				roster_code, target, period,
				conversion, rate, fcs_mult, rate_fcs, gtib_bonus,
				min_gtib, min_pif, daily_pif, total_estimate, created_at
			) VALUES (
				:roster_code, :target, :period,
				:conversion, :rate, :fcs_mult, :rate_fcs, :gtib_bonus,
				:min_gtib, :min_pif, :daily_pif, :total_estimate, NOW()
			)
			ON DUPLICATE KEY UPDATE
				target = VALUES(target),
				conversion = VALUES(conversion),
				rate = VALUES(rate),
				fcs_mult = VALUES(fcs_mult),
				rate_fcs = VALUES(rate_fcs),
				gtib_bonus = VALUES(gtib_bonus),
				min_gtib = VALUES(min_gtib),
				min_pif = VALUES(min_pif),
				daily_pif = VALUES(daily_pif),
				total_estimate = VALUES(total_estimate),
				created_at = NOW()
		";

		$params = [
			'roster_code' => $data['roster_code'],
			'target' => $data['target'],
			'period' => $data['period'],
			'conversion' => $data['conversion'],
			'rate' => $data['rate'],
			'fcs_mult' => $data['fcs_mult'],
			'rate_fcs' => $data['rate_fcs'],
			'gtib_bonus' => $data['gtib_bonus'],
			'min_gtib' => $data['min_gtib'],
			'min_pif' => $data['min_pif'],
			'daily_pif' => $data['daily_pif'],
			'total_estimate' => $data['total_estimate']
		];

		return $this->execute($sql, $params);
	}
}


