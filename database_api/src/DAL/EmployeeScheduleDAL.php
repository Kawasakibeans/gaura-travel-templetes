<?php
/**
 * Employee Schedule Data Access Layer
 * Handles database operations for employee availability/schedule management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EmployeeScheduleDAL extends BaseDAL
{
    /**
     * Get agent by sales_id
     */
    public function getAgentBySalesId(string $salesId): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE sales_id = :sales_id AND status = 'active' 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':sales_id' => $salesId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get agent by roster_code
     */
    public function getAgentByRosterCode(string $rosterCode): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE roster_code = :roster_code AND status = 'active' 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':roster_code' => $rosterCode]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get all active agents
     */
    public function getAllActiveAgents(): array
    {
        $sql = "
            SELECT roster_code AS Emp_ID, agent_name, team_leader, sale_manager 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active' 
            ORDER BY agent_name
        ";
        
        return $this->query($sql);
    }

    /**
     * Check if availability record exists
     */
    public function availabilityExists(string $empId): bool
    {
        $sql = "SELECT 1 FROM wpk4_backend_employee_schedule WHERE Emp_ID = :emp_id LIMIT 1";
        $result = $this->queryOne($sql, [':emp_id' => $empId]);
        return $result !== false && $result !== null;
    }

    /**
     * Get availability data for employee
     */
    public function getAvailability(string $empId, array $timeSlots): ?array
    {
        $placeholders = implode(',', array_map(function($slot) {
            return "`{$slot}`";
        }, $timeSlots));
        
        $sql = "
            SELECT {$placeholders}, Role, Gender 
            FROM wpk4_backend_employee_schedule 
            WHERE Emp_ID = :emp_id
        ";
        
        $result = $this->queryOne($sql, [':emp_id' => $empId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Update availability
     */
    public function updateAvailability(string $empId, array $availability): bool
    {
        $updateParts = [];
        $params = [];
        
        foreach ($availability as $slot => $value) {
            $updateParts[] = "`{$slot}` = :{$slot}";
            $params[":{$slot}"] = $value === 'Yes' ? 'Yes' : 'No';
        }
        
        $params[':emp_id'] = $empId;
        
        $sql = "
            UPDATE wpk4_backend_employee_schedule 
            SET " . implode(', ', $updateParts) . " 
            WHERE Emp_ID = :emp_id
        ";
        
        return $this->execute($sql, $params);
    }

    /**
     * Insert availability
     */
    public function insertAvailability(string $empId, string $employeeName, array $availability): int
    {
        $columns = ['Employee_Name', 'Emp_ID'];
        $placeholders = [':employee_name', ':emp_id'];
        $params = [
            ':employee_name' => $employeeName,
            ':emp_id' => $empId
        ];
        
        foreach ($availability as $slot => $value) {
            $columns[] = "`{$slot}`";
            $placeholders[] = ":{$slot}";
            $params[":{$slot}"] = $value === 'Yes' ? 'Yes' : 'No';
        }
        
        $sql = "
            INSERT INTO wpk4_backend_employee_schedule 
            (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $placeholders) . ")
        ";
        
        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Get team members by team leader
     */
    public function getTeamMembers(string $teamLeaderName): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE team_leader = :team_leader AND status = 'active'
        ";
        
        $results = $this->query($sql, [':team_leader' => $teamLeaderName]);
        
        // Normalize to ensure Emp_ID key exists
        foreach ($results as &$result) {
            $result['Emp_ID'] = $result['roster_code'];
        }
        
        return $results;
    }

    /**
     * Get direct reports by sales manager
     */
    public function getDirectReports(string $salesManagerName): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE sale_manager LIKE :sales_manager AND status = 'active'
        ";
        
        $results = $this->query($sql, [':sales_manager' => "%{$salesManagerName}%"]);
        
        // Normalize to ensure Emp_ID key exists
        foreach ($results as &$result) {
            $result['Emp_ID'] = $result['roster_code'];
        }
        
        return $results;
    }
    
        /**
     * Get distinct team names by sales manager
     */
    public function getTeamNamesBySalesManager(string $salesManagerName): array
    {
        $sql = "
            SELECT DISTINCT team_name
            FROM wpk4_backend_agent_codes 
            WHERE sale_manager LIKE :sales_manager 
              AND status = 'active'
              AND team_name IS NOT NULL
              AND team_name <> ''
            ORDER BY team_name
        ";
        
        $results = $this->query($sql, [':sales_manager' => "%{$salesManagerName}%"]);
        return array_column($results, 'team_name');
    }

    /**
     * Get employee schedules filtered by Emp_ID and department
     */
    public function getEmployeeSchedules(?string $empId = null, ?string $department = null, int $limit = 100, int $offset = 0): array
    {
        $whereParts = [];
        $params = [];

        if ($empId) {
            $whereParts[] = "es.Emp_ID = ?";
            $params[] = $empId;
        }

        if ($department) {
            // Filter by Department column directly from employee_schedule table
            // Use case-insensitive comparison for department
            $whereParts[] = "UPPER(es.Department) = UPPER(?)";
            $params[] = $department;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        // No JOIN needed - Department column exists in employee_schedule table
        $joinSQL = "";
        
        $sql = "
            SELECT es.* 
            FROM wpk4_backend_employee_schedule es
            {$joinSQL}
            {$whereSQL}
            ORDER BY es.Emp_ID
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->query($sql, $params);
    }

    /**
     * Get employee schedule count with filters
     */
    public function getEmployeeScheduleCount(?string $empId = null, ?string $department = null): int
    {
        $whereParts = [];
        $params = [];

        if ($empId) {
            $whereParts[] = "es.Emp_ID = ?";
            $params[] = $empId;
        }

        if ($department) {
            // Filter by Department column directly from employee_schedule table
            // Use case-insensitive comparison for department
            $whereParts[] = "UPPER(es.Department) = UPPER(?)";
            $params[] = $department;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        // No JOIN needed - Department column exists in employee_schedule table
        $joinSQL = "";
        
        $sql = "
            SELECT COUNT(*) as total 
            FROM wpk4_backend_employee_schedule es
            {$joinSQL}
            {$whereSQL}
        ";
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get agents with filters
     */
    public function getAgents(array $filters = []): array
    {
        $sql = "
            SELECT
                roster_code AS Emp_ID,
                agent_name,
                team_name,
                sale_manager,
                team_leader,
                role,
                status,
                wordpress_user_name
            FROM wpk4_backend_agent_codes
            WHERE 1 = 1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status ";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['sales_manager'])) {
            $sql .= " AND sale_manager = :sales_manager ";
            $params[':sales_manager'] = $filters['sales_manager'];
        }

        if (!empty($filters['team_name'])) {
            $sql .= " AND team_name = :team_name ";
            $params[':team_name'] = $filters['team_name'];
        }

        if (!empty($filters['role_exclude']) && is_array($filters['role_exclude'])) {
            $placeholders = [];
            foreach ($filters['role_exclude'] as $idx => $role) {
                $key = ":role_exclude_{$idx}";
                $placeholders[] = $key;
                $params[$key] = $role;
            }
            $sql .= " AND role NOT IN (" . implode(',', $placeholders) . ") ";
        }

        if (!empty($filters['team_not'])) {
            $sql .= " AND team_name <> :team_not ";
            $params[':team_not'] = $filters['team_not'];
        }

        $sql .= " ORDER BY agent_name";

        return $this->query($sql, $params);
    }
    
    /**
     * Get agent by WordPress username
     */
    public function getAgentByWordpressUsername(string $username): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE wordpress_user_name = :username AND status = 'active' 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':username' => $username]);
        return ($result === false) ? null : $result;
    }
    

    /**
     * Get distinct sales managers
     */
    public function getDistinctSalesManagers(): array
    {
        $sql = "
            SELECT DISTINCT sale_manager
            FROM wpk4_backend_agent_codes
            WHERE status = 'active'
              AND sale_manager IS NOT NULL
              AND sale_manager NOT IN ('Others', 'Sales Manager', 'Trainer')
            ORDER BY sale_manager
        ";

        $results = $this->query($sql);
        return array_column($results, 'sale_manager');
    }

    /**
     * Get team members by team leader (alias for getTeamMembers)
     */
    public function getTeamMembersByLeader(string $teamLeader): array
    {
        return $this->getTeamMembers($teamLeader);
    }

    /**
     * Get agents by sales manager (LIKE search)
     */
    public function getAgentsBySalesManagerLike(string $managerName): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_agent_codes 
            WHERE sale_manager LIKE :sales_manager AND status = 'active'
            ORDER BY agent_name
        ";
        
        $results = $this->query($sql, [':sales_manager' => "%{$managerName}%"]);
        
        // Normalize to ensure Emp_ID key exists
        foreach ($results as &$result) {
            $result['Emp_ID'] = $result['roster_code'];
        }
        
        return $results;
    }

    /**
     * Get employee schedule lock status
     */
    public function getLockStatus(): ?bool
    {
        $sql = "
            SELECT meta_value 
            FROM wpk4_g360_settings 
            WHERE meta_key = 'employee_schedule_lock_status' 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql);
        if ($result === false || !isset($result['meta_value'])) {
            return false; // Default to unlocked
        }
        
        return $result['meta_value'] === '1' || $result['meta_value'] === 'true' || $result['meta_value'] === 'yes';
    }

    /**
     * Set employee schedule lock status
     */
    public function setLockStatus(bool $isLocked): bool
    {
        $lockValue = $isLocked ? '1' : '0';
        
        // Check if setting exists
        $sqlCheck = "
            SELECT meta_value 
            FROM wpk4_g360_settings 
            WHERE meta_key = 'employee_schedule_lock_status' 
            LIMIT 1
        ";
        $existing = $this->queryOne($sqlCheck);
        
        if ($existing === false || !isset($existing['meta_value'])) {
            // Insert new setting
            $sql = "
                INSERT INTO wpk4_g360_settings (meta_key, meta_value, updated_by, updated_on)
                VALUES ('employee_schedule_lock_status', :meta_value, 'system', NOW())
            ";
            $params = [':meta_value' => $lockValue];
        } else {
            // Update existing setting
            $sql = "
                UPDATE wpk4_g360_settings 
                SET meta_value = :meta_value, 
                    updated_by = 'system',
                    updated_on = NOW()
                WHERE meta_key = 'employee_schedule_lock_status'
            ";
            $params = [':meta_value' => $lockValue];
        }
        
        return $this->execute($sql, $params);
    }

    /**
     * Get all employee schedule records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $whereParts = [];
        $params = [];
        
        // Build WHERE clause from filters using positional parameters
        if (!empty($filters['emp_id'])) {
            $whereParts[] = "es.Emp_ID = ?";
            $params[] = $filters['emp_id'];
        }
        
        if (!empty($filters['employee_name'])) {
            $whereParts[] = "es.Employee_Name LIKE ?";
            $params[] = '%' . $filters['employee_name'] . '%';
        }
        
        if (!empty($filters['department'])) {
            $whereParts[] = "UPPER(es.Department) = UPPER(?)";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['role'])) {
            $whereParts[] = "es.Role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['gender'])) {
            $whereParts[] = "es.Gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (isset($filters['is_locked']) && $filters['is_locked'] !== '') {
            $whereParts[] = "es.is_locked = ?";
            $params[] = (int)$filters['is_locked'];
        }
        
        // Filter by time slot availability (e.g., '8_00_AM' = 'Yes')
        $timeSlots = [
            '1_00_AM', '4_00_AM', '5_00_AM', '6_00_AM',
            '7_00_AM', '8_00_AM', '9_00_AM', '10_00_AM',
            '11_00_AM', '12_00_PM', '1_00_PM', '4_00_PM'
        ];
        
        foreach ($timeSlots as $slot) {
            if (isset($filters[$slot]) && $filters[$slot] !== '') {
                $whereParts[] = "es.`{$slot}` = ?";
                $params[] = $filters[$slot];
            }
        }
        
        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $sql = "
            SELECT es.* 
            FROM wpk4_backend_employee_schedule es
            {$whereSQL}
            ORDER BY es.Emp_ID ASC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->query($sql, $params);
    }
}
