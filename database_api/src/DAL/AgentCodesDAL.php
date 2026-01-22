<?php

namespace App\DAL;

class AgentCodesDAL extends BaseDAL
{
    // Get distinct sale managers
    public function getDistinctSaleManagers()
    {
        return $this->query("SELECT DISTINCT sale_manager FROM wpk4_backend_agent_codes WHERE status = 'Active' AND sale_manager IS NOT NULL ORDER BY sale_manager");
    }

    // Get distinct agent names by location and status
    public function getDistinctAgentNamesByLocation(string $location, string $status = 'Active')
    {
        $sql = "SELECT DISTINCT agent_name
                FROM wpk4_backend_agent_codes
                WHERE location = :location AND status = :status
                ORDER BY agent_name ASC";

        return $this->query($sql, ['location' => $location, 'status' => $status]);
    }

	// Get active roster codes and agent names
	public function getActiveRosterCodes(): array
	{
		$sql = "SELECT roster_code, agent_name
				FROM wpk4_backend_agent_codes
				WHERE UPPER(status) = 'ACTIVE'
				ORDER BY agent_name ASC";
		return $this->query($sql);
	}

	// Get active roster code by agent name
	public function getActiveRosterCodeByAgentName(string $agentName): ?string
	{
		$sql = "SELECT roster_code
				FROM wpk4_backend_agent_codes
				WHERE agent_name = :agent_name AND UPPER(status) = 'ACTIVE'
				LIMIT 1";
		$row = $this->queryOne($sql, ['agent_name' => $agentName]);
		return $row['roster_code'] ?? null;
	}

	// Get agent name by roster code (no status filter)
	public function getAgentNameByRosterCode(string $rosterCode): ?string
	{
		$sql = "SELECT agent_name
				FROM wpk4_backend_agent_codes
				WHERE roster_code = :roster_code
				LIMIT 1";
		$row = $this->queryOne($sql, ['roster_code' => $rosterCode]);
		return $row['agent_name'] ?? null;
	}

	// Get distinct agent names (no filters)
	public function getDistinctAgentNames(): array
	{
		$sql = "SELECT DISTINCT agent_name FROM wpk4_backend_agent_codes ORDER BY agent_name ASC";
		return $this->query($sql);
	}

	// Get distinct team names (no filters)
	public function getDistinctTeamNames(): array
	{
		$sql = "SELECT DISTINCT team_name FROM wpk4_backend_agent_codes ORDER BY team_name ASC";
		return $this->query($sql);
	}

	// CRUD Methods
	public function getAll($limit = 100, $offset = 0, $filters = [])
	{
		$limit = (int)$limit;
		$offset = (int)$offset;
		
		$whereParts = [];
		$params = [];
		
		// Build WHERE clause from filters
		if (!empty($filters['status'])) {
			$whereParts[] = "status = :status";
			$params['status'] = $filters['status'];
		}
		
		if (!empty($filters['location'])) {
			$whereParts[] = "location = :location";
			$params['location'] = $filters['location'];
		}
		
		if (!empty($filters['tsr'])) {
			$whereParts[] = "tsr = :tsr";
			$params['tsr'] = $filters['tsr'];
		}
		
		if (!empty($filters['team_name'])) {
			$whereParts[] = "team_name = :team_name";
			$params['team_name'] = $filters['team_name'];
		}
		
		if (!empty($filters['employee_status'])) {
			$whereParts[] = "employee_status = :employee_status";
			$params['employee_status'] = $filters['employee_status'];
		}
		
		if (!empty($filters['sale_manager'])) {
			$whereParts[] = "sale_manager = :sale_manager";
			$params['sale_manager'] = $filters['sale_manager'];
		}
		
		$whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
		
		$sql = "SELECT * FROM wpk4_backend_agent_codes 
		        {$whereSQL}
		        ORDER BY auto_id DESC 
		        LIMIT " . $limit . " OFFSET " . $offset;
		
		return $this->query($sql, $params);
	}

	public function getById($id)
	{
		$sql = "SELECT * FROM wpk4_backend_agent_codes WHERE auto_id = :id LIMIT 1";
		$result = $this->queryOne($sql, ['id' => $id]);
		if (!$result) {
			throw new \Exception('Agent code not found', 404);
		}
		return $result;
	}

	public function create($data)
	{
		$sql = "INSERT INTO wpk4_backend_agent_codes 
				(agent_name, roster_code, sales_id, tsr, team_name, team_leader, sale_manager, location, status, employee_status)
				VALUES 
				(:agent_name, :roster_code, :sales_id, :tsr, :team_name, :team_leader, :sale_manager, :location, :status, :employee_status)";
		
		$params = [
			'agent_name' => $data['agent_name'] ?? null,
			'roster_code' => $data['roster_code'] ?? null,
			'sales_id' => $data['sales_id'] ?? null,
			'tsr' => $data['tsr'] ?? null,
			'team_name' => $data['team_name'] ?? null,
			'team_leader' => $data['team_leader'] ?? null,
			'sale_manager' => $data['sale_manager'] ?? null,
			'location' => $data['location'] ?? null,
			'status' => $data['status'] ?? 'Active',
			'employee_status' => $data['employee_status'] ?? null
		];
		
		$this->execute($sql, $params);
		return $this->lastInsertId();
	}

	public function update($id, $data)
	{
		$fields = [];
		$params = ['id' => $id];
		
		$allowedFields = ['agent_name', 'roster_code', 'sales_id', 'tsr', 'team_name', 'team_leader', 'sale_manager', 'location', 'status', 'employee_status'];
		
		foreach ($allowedFields as $field) {
			if (isset($data[$field])) {
				$fields[] = "$field = :$field";
				$params[$field] = $data[$field];
			}
		}
		
		if (empty($fields)) {
			throw new \Exception('No fields to update', 400);
		}
		
		$sql = "UPDATE wpk4_backend_agent_codes SET " . implode(', ', $fields) . " WHERE auto_id = :id";
		$this->execute($sql, $params);
		return true;
	}

	public function delete($id)
	{
		$sql = "DELETE FROM wpk4_backend_agent_codes WHERE auto_id = :id";
		$result = $this->execute($sql, ['id' => $id]);
		if ($result === false || $this->rowCount() === 0) {
			throw new \Exception('Agent code not found', 404);
		}
		return true;
	}
}