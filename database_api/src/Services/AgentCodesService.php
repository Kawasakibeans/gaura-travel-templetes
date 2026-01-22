<?php

namespace App\Services;

use App\DAL\AgentCodesDAL;

class AgentCodesService{
    private $agentCodesDAL;

    public function __construct()
    {
        $this->agentCodesDAL = new AgentCodesDAL();
    }

    public function getDistinctSaleManagers(): array
    {
        return $this->agentCodesDAL->getDistinctSaleManagers();
    }

    public function getDistinctAgentNamesByLocation(string $location, ?string $status = 'Active'): array
    {
        $location = trim($location);
        if ($location === '') {
            throw new \Exception('location is required', 400);
        }
        $status = $status ? trim($status) : 'Active';
        return $this->agentCodesDAL->getDistinctAgentNamesByLocation($location, $status);
    }

    public function getActiveRosterCodes(): array
    {
        return $this->agentCodesDAL->getActiveRosterCodes();
    }

    public function getActiveRosterCodeByAgentName(string $agentName): array
    {
        $agentName = trim($agentName);
        if ($agentName === '') {
            throw new \Exception('agent_name is required', 400);
        }
        $code = $this->agentCodesDAL->getActiveRosterCodeByAgentName($agentName);
        return ['agent_name' => $agentName, 'roster_code' => $code];
    }

    public function getAgentNameByRosterCode(string $rosterCode): array
    {
        $rosterCode = trim($rosterCode);
        if ($rosterCode === '') {
            throw new \Exception('roster_code is required', 400);
        }
        $name = $this->agentCodesDAL->getAgentNameByRosterCode($rosterCode);
        return ['roster_code' => $rosterCode, 'agent_name' => $name];
    }

    public function getDistinctAgentNames(): array
    {
        return $this->agentCodesDAL->getDistinctAgentNames();
    }

    public function getDistinctTeamNames(): array
    {
        return $this->agentCodesDAL->getDistinctTeamNames();
    }

    // CRUD Methods
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        return $this->agentCodesDAL->getAll($limit, $offset, $filters);
    }

    public function getById($id)
    {
        return $this->agentCodesDAL->getById($id);
    }

    public function create($data)
    {
        // Validate required fields
        if (empty($data['agent_name'])) {
            throw new \Exception('agent_name is required', 400);
        }
        return $this->agentCodesDAL->create($data);
    }

    public function update($id, $data)
    {
        // Verify record exists
        $this->agentCodesDAL->getById($id);
        return $this->agentCodesDAL->update($id, $data);
    }

    public function delete($id)
    {
        // Verify record exists
        $this->agentCodesDAL->getById($id);
        return $this->agentCodesDAL->delete($id);
    }
}