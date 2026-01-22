<?php
/**
 * Agent Records Service - Business Logic Layer
 * Handles agent personal data management (CRUD operations)
 */

namespace App\Services;

use App\DAL\AgentRecordsDAL;
use Exception;

class AgentRecordsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AgentRecordsDAL();
    }

    /**
     * Get all agents
     */
    public function getAllAgents($filters = [])
    {
        $teamName = $filters['team_name'] ?? null;
        $employeeStatus = $filters['employee_status'] ?? null;
        $saleManager = $filters['sale_manager'] ?? null;
        $status = $filters['status'] ?? null;
        $location = $filters['location'] ?? null;
        $tsr = $filters['tsr'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $agents = $this->dal->getAllAgents($teamName, $employeeStatus, $saleManager, $status, $location, $tsr, $limit, $offset);
        $totalCount = $this->dal->getAgentCount($teamName, $employeeStatus, $saleManager, $status, $location, $tsr);

        return [
            'agents' => $agents,
            'total_count' => $totalCount,
            'filters' => [
                'team_name' => $teamName,
                'employee_status' => $employeeStatus,
                'sale_manager' => $saleManager,
                'status' => $status,
                'location' => $location,
                'tsr' => $tsr,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get agent by ID
     */
    public function getAgentById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid agent ID is required', 400);
        }

        $agent = $this->dal->getAgentById($id);

        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }

        return $agent;
    }

    /**
     * Create new agent
     */
    public function createAgent($data)
    {
        // Validate required fields
        $requiredFields = ['sales_id', 'agent_name', 'team_name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Check if sales_id already exists
        if ($this->dal->salesIdExists($data['sales_id'])) {
            throw new Exception("Sales ID '{$data['sales_id']}' already exists", 400);
        }

        // Create agent
        $agentId = $this->dal->createAgent($data);

        return [
            'agent_id' => $agentId,
            'sales_id' => $data['sales_id'],
            'message' => 'Agent created successfully'
        ];
    }

    /**
     * Update agent
     */
    public function updateAgent($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid agent ID is required', 400);
        }

        // Check if agent exists
        $existingAgent = $this->dal->getAgentById($id);
        if (!$existingAgent) {
            throw new Exception('Agent not found', 404);
        }

        // If updating sales_id, check if new sales_id already exists
        if (!empty($data['sales_id']) && $data['sales_id'] !== $existingAgent['sales_id']) {
            if ($this->dal->salesIdExists($data['sales_id'])) {
                throw new Exception("Sales ID '{$data['sales_id']}' already exists", 400);
            }
        }

        // Update agent
        $this->dal->updateAgent($id, $data);

        return [
            'agent_id' => $id,
            'sales_id' => $data['sales_id'] ?? $existingAgent['sales_id'],
            'message' => 'Agent updated successfully'
        ];
    }

    /**
     * Delete agent
     */
    public function deleteAgent($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid agent ID is required', 400);
        }

        // Check if agent exists
        $agent = $this->dal->getAgentById($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }

        // Delete agent
        $this->dal->deleteAgent($id);

        return [
            'agent_id' => $id,
            'sales_id' => $agent['sales_id'],
            'message' => 'Agent deleted successfully'
        ];
    }

    /**
     * Get dropdown options for form fields
     */
    public function getDropdownOptions()
    {
        return [
            'team_names' => $this->dal->getDistinctTeamNames(),
            'team_leaders' => $this->dal->getDistinctTeamLeaders(),
            'sale_managers' => $this->dal->getDistinctSaleManagers(),
            'employee_statuses' => $this->dal->getDistinctEmployeeStatuses(),
            'shift_times' => $this->dal->getDistinctShiftTimes(),
            'roles' => $this->dal->getDistinctRoles(),
            'wordpress_users' => $this->dal->getDistinctWordpressUserNames()
        ];
    }

    /**
     * Get team details by team name
     */
    public function getTeamDetails($teamName)
    {
        if (empty($teamName)) {
            throw new Exception('Team name is required', 400);
        }

        $teamDetails = $this->dal->getTeamDetails($teamName);

        if (!$teamDetails) {
            throw new Exception('Team not found', 404);
        }

        return $teamDetails;
    }

    /**
     * Get agents by team name
     */
    public function getAgentsByTeam($teamName)
    {
        if (empty($teamName)) {
            throw new Exception('Team name is required', 400);
        }

        $agents = $this->dal->getAgentsByTeamName($teamName);

        return [
            'team_name' => $teamName,
            'agents' => $agents,
            'agent_count' => count($agents)
        ];
    }

    /**
     * Get teams with team leaders
     */
    public function getTeamsWithLeaders(): array
    {
        $teams = $this->dal->getTeamsWithLeaders();
        
        // Convert to associative array format
        $teamsDict = [];
        foreach ($teams as $team) {
            $teamsDict[$team['team_name']] = $team['team_leader'];
        }
        
        return [
            'teams' => $teams,
            'teams_dict' => $teamsDict,
            'total_count' => count($teams)
        ];
    }

    /**
     * Get TSRs with agent names
     */
    public function getTsrsWithAgentNames(): array
    {
        $tsrs = $this->dal->getTsrsWithAgentNames();
        
        // Convert to associative array format
        $tsrsDict = [];
        foreach ($tsrs as $tsr) {
            if (!empty($tsr['agent_name']) && !empty($tsr['tsr'])) {
                $tsrsDict[$tsr['agent_name']] = $tsr['tsr'];
            }
        }
        
        return [
            'tsrs' => $tsrs,
            'tsrs_dict' => $tsrsDict,
            'total_count' => count($tsrs)
        ];
    }

    /**
     * Get team names from inbound call table
     */
    public function getTeamNamesFromInboundCall(): array
    {
        $teamNames = $this->dal->getTeamNamesFromInboundCall();
        
        return [
            'team_names' => $teamNames,
            'total_count' => count($teamNames)
        ];
    }

    /**
     * Get combined agent records (inbound call + booking data)
     */
    public function getCombinedAgentRecords(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $teamName = $filters['team_name'] ?? null;
        $groupBy = $filters['group_by'] ?? 'team_name';
        $orderBy = $filters['order_by'] ?? 'sale_manager, team_name';

        // Validate dates
        if (empty($startDate) || empty($endDate)) {
            throw new Exception('start_date and end_date are required', 400);
        }

        // Validate group_by
        $allowedGroupBy = ['team_name', 'agent_name'];
        if (!in_array($groupBy, $allowedGroupBy)) {
            throw new Exception("group_by must be one of: " . implode(', ', $allowedGroupBy), 400);
        }

        // Determine order_by based on group_by
        if ($groupBy === 'agent_name') {
            $orderBy = 'agent_name';
        }

        $records = $this->dal->getCombinedAgentRecords(
            $startDate,
            $endDate,
            $teamName,
            $groupBy,
            $orderBy
        );

        return [
            'records' => $records,
            'total_count' => count($records),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'team_name' => $teamName,
                'group_by' => $groupBy,
                'order_by' => $orderBy
            ]
        ];
    }
}

