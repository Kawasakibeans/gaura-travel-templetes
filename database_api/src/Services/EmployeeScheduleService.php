<?php
/**
 * Employee Schedule Service
 * Business logic for employee schedule endpoints
 */

namespace App\Services;

use App\DAL\EmployeeScheduleDAL;

class EmployeeScheduleService
{
    private $dal;
    private $timeSlots = [
        '1_00_AM', '4_00_AM', '5_00_AM', '6_00_AM',
        '7_00_AM', '8_00_AM', '9_00_AM', '10_00_AM',
        '11_00_AM', '12_00_PM', '1_00_PM', '4_00_PM'
    ];

    public function __construct()
    {
        $this->dal = new EmployeeScheduleDAL();
    }

    /**
     * Get agent by sales ID
     */
    public function getAgentBySalesId(array $params): array
    {
        $salesId = $params['sales_id'] ?? '';
        
        if (empty($salesId)) {
            throw new \Exception('Sales ID is required', 400);
        }
        
        $agent = $this->dal->getAgentBySalesId($salesId);
        
        if (!$agent) {
            throw new \Exception('No matching agent record found', 404);
        }
        
        return [
            'agent' => $agent,
            'emp_id' => $agent['roster_code']
        ];
    }

    /**
     * Get all active agents
     */
    public function getAllActiveAgents(): array
    {
        $agents = $this->dal->getAllActiveAgents();
        
        return [
            'agents' => $agents,
            'count' => count($agents)
        ];
    }

    /**
     * Get availability for employee
     */
    public function getAvailability(array $params): array
    {
        $empId = $params['emp_id'] ?? '';
        
        if (empty($empId)) {
            throw new \Exception('Employee ID is required', 400);
        }
        
        $availability = $this->dal->getAvailability($empId, $this->timeSlots);
        
        return [
            'emp_id' => $empId,
            'availability' => $availability ?: [],
            'time_slots' => $this->timeSlots
        ];
    }

    /**
     * Retrieve availability record, agent details, and optionally team info.
     *
     * Options:
     * - include_team_members (bool)
     * - include_direct_reports (bool)
     */
    public function getAvailabilityDetails(string $empId, array $options = []): array
    {
        $agent = $this->dal->getAgentByRosterCode($empId);
        if (!$agent) {
            throw new \Exception('Active agent not found', 404);
        }

        $availability = $this->dal->getAvailability($empId, $this->timeSlots);

        $response = [
            'agent' => $agent,
            'availability' => $availability ?: [],
        ];

        if (!empty($options['include_team_members']) && !empty($agent['team_leader'])) {
            $response['team_members'] = $this->dal->getTeamMembersByLeader($agent['team_leader']);
        }

        if (!empty($options['include_direct_reports'])) {
            $response['direct_reports'] = $this->dal->getAgentsBySalesManagerLike($agent['agent_name']);
        }

        return $response;
    }

    /**
     * List agents with filter support (status, sales_manager, team_name, etc).
     */
    public function listAgents(array $filters = []): array
    {
        return $this->dal->getAgents($filters);
    }
    
    /**
     * Get agent by WordPress username
     */
    public function getAgentByWordpressUsername(string $username): array
    {
        $agent = $this->dal->getAgentByWordpressUsername($username);
        
        if (!$agent) {
            throw new \Exception('No matching agent record found', 404);
        }
        
        return [
            'agent' => $agent,
            'emp_id' => $agent['roster_code']
        ];
    }
    
    /**
     * List direct reports by manager name
     */
    public function listDirectReports(string $managerName): array
    {
        if (empty($managerName)) {
            throw new \Exception('Manager name is required', 400);
        }
        
        $reports = $this->dal->getDirectReports($managerName);
        
        return $reports;
    }
    
    /**
     * List sales managers
     */
    public function listSalesManagers(): array
    {
        return $this->dal->getDistinctSalesManagers();
    }
    
     /**
     * List team names for sales manager
     */
    public function listTeamNamesForSalesManager(string $salesManager): array
    {
        if (empty($salesManager)) {
            throw new \Exception('Sales manager is required', 400);
        }
        
        $teamNames = $this->dal->getTeamNamesBySalesManager($salesManager);
        
        return $teamNames;
    }

    /**
     * Save availability
     */
    public function saveAvailability(array $params): array
    {
        $empId = $params['emp_id'] ?? '';
        $availability = $params['availability'] ?? [];
        
        if (empty($empId)) {
            throw new \Exception('Employee ID is required', 400);
        }
        
        // Validate and normalize availability data
        $normalizedAvailability = [];
        foreach ($this->timeSlots as $slot) {
            $normalizedAvailability[$slot] = isset($availability[$slot]) && $availability[$slot] === 'Yes' ? 'Yes' : 'No';
        }
        
        // Check if record exists
        $exists = $this->dal->availabilityExists($empId);
        
        if ($exists) {
            // Update existing
            $this->dal->updateAvailability($empId, $normalizedAvailability);
        } else {
            // Insert new - need agent name
            $agent = $this->dal->getAgentByRosterCode($empId);
            if (!$agent) {
                throw new \Exception('Active agent not found', 404);
            }
            
            $this->dal->insertAvailability($empId, $agent['agent_name'], $normalizedAvailability);
        }
        
        return [
            'success' => true,
            'emp_id' => $empId,
            'message' => 'Availability updated successfully'
        ];
    }

    /**
     * Get team members
     */
    public function getTeamMembers(array $params): array
    {
        $teamLeaderName = $params['team_leader'] ?? '';
        
        if (empty($teamLeaderName)) {
            throw new \Exception('Team leader name is required', 400);
        }
        
        $members = $this->dal->getTeamMembers($teamLeaderName);
        
        return [
            'team_leader' => $teamLeaderName,
            'members' => $members,
            'count' => count($members)
        ];
    }

    /**
     * Get direct reports
     */
    public function getDirectReports(array $params): array
    {
        $salesManagerName = $params['sales_manager'] ?? '';
        
        if (empty($salesManagerName)) {
            throw new \Exception('Sales manager name is required', 400);
        }
        
        $reports = $this->dal->getDirectReports($salesManagerName);
        
        return [
            'sales_manager' => $salesManagerName,
            'direct_reports' => $reports,
            'count' => count($reports)
        ];
    }

    /**
     * Get employee schedules filtered by Emp_ID and department
     */
    public function getEmployeeSchedules(array $filters = []): array
    {
        $empId = isset($filters['emp_id']) && $filters['emp_id'] !== '' ? trim($filters['emp_id']) : null;
        $department = isset($filters['department']) && $filters['department'] !== '' ? trim($filters['department']) : null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $schedules = $this->dal->getEmployeeSchedules($empId, $department, $limit, $offset);
        $totalCount = $this->dal->getEmployeeScheduleCount($empId, $department);

        return [
            'schedules' => $schedules,
            'total_count' => $totalCount,
            'filters' => [
                'emp_id' => $empId,
                'department' => $department,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get employee schedule lock status
     */
    public function getLockStatus(): array
    {
        $isLocked = $this->dal->getLockStatus();
        
        return [
            'is_locked' => $isLocked ?? false,
            'locked' => $isLocked ?? false // Alias for compatibility
        ];
    }

    /**
     * Set employee schedule lock status
     */
    public function setLockStatus(bool $isLocked): array
    {
        $result = $this->dal->setLockStatus($isLocked);
        
        if (!$result) {
            throw new \Exception('Failed to update lock status', 500);
        }
        
        return [
            'is_locked' => $isLocked,
            'locked' => $isLocked, // Alias for compatibility
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get all employee schedule records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        return $this->dal->getAll($limit, $offset, $filters);
    }
}
