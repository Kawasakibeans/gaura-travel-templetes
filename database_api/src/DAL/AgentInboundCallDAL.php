<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentInboundCallDAL extends BaseDAL
{
	/**
	 * Find auto_ids to update using optional filters.
	 * sinceDate: YYYY-MM-DD (inclusive lower bound)
	 * agentNameLike: optional substring match on agent_name
	 * filterDate: optional exact date YYYY-MM-DD
	 */
	public function findAutoIdsForUpdate(string $sinceDate, ?string $agentNameLike = null, ?string $filterDate = null): array
	{
		$where = ["call_date >= :since_date"];
		$params = ['since_date' => $sinceDate];

		if ($agentNameLike !== null && $agentNameLike !== '') {
			$where[] = "agent_name LIKE :agent_name_like";
			$params['agent_name_like'] = '%' . $agentNameLike . '%';
		}

		if ($filterDate !== null && $filterDate !== '') {
			$where[] = "call_date = :filter_date";
			$params['filter_date'] = $filterDate;
		}

		$sql = "
			SELECT auto_id
			FROM wpk4_backend_agent_inbound_call
			WHERE " . implode(' AND ', $where);

		$rows = $this->query($sql, $params);
		return array_map(static function ($row) { return (int)$row['auto_id']; }, $rows);
	}

	/**
	 * Update call flags by auto_id
	 */
	public function updateFlags(int $autoId, int $malpractice, int $profanity, int $misbehavior): bool
	{
		$sql = "
			UPDATE wpk4_backend_agent_inbound_call
			SET malpractice = :malpractice, profanity = :profanity, misbehavior = :misbehavior
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'malpractice' => $malpractice,
			'profanity' => $profanity,
			'misbehavior' => $misbehavior,
			'auto_id' => $autoId
		]);
	}
}


