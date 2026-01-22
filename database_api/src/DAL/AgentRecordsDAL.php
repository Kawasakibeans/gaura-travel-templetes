<?php
/**
 * Agent Records Data Access Layer
 * Handles all database operations for agent personal data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentRecordsDAL extends BaseDAL
{
    /**
     * Get all agents with optional filters
     */
    public function getAllAgents($teamName = null, $employeeStatus = null, $saleManager = null, $status = null, $location = null, $tsr = null, $limit = 100, $offset = 0)
    {
        $whereParts = [];
        $params = [];

        if ($teamName) {
            $whereParts[] = "team_name = ?";
            $params[] = $teamName;
        }

        if ($employeeStatus) {
            $whereParts[] = "employee_status = ?";
            $params[] = $employeeStatus;
        }

        if ($saleManager) {
            $whereParts[] = "sale_manager = ?";
            $params[] = $saleManager;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        if ($location) {
            $whereParts[] = "location = ?";
            $params[] = $location;
        }

        if ($tsr) {
            $whereParts[] = "tsr = ?";
            $params[] = $tsr;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_backend_agent_codes 
                  $whereSQL 
                  ORDER BY team_name, sales_id 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get agent count with filters
     */
    public function getAgentCount($teamName = null, $employeeStatus = null, $saleManager = null, $status = null, $location = null, $tsr = null)
    {
        $whereParts = [];
        $params = [];

        if ($teamName) {
            $whereParts[] = "team_name = ?";
            $params[] = $teamName;
        }

        if ($employeeStatus) {
            $whereParts[] = "employee_status = ?";
            $params[] = $employeeStatus;
        }

        if ($saleManager) {
            $whereParts[] = "sale_manager = ?";
            $params[] = $saleManager;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        if ($location) {
            $whereParts[] = "location = ?";
            $params[] = $location;
        }

        if ($tsr) {
            $whereParts[] = "tsr = ?";
            $params[] = $tsr;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_backend_agent_codes $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get agent by ID
     */
    public function getAgentById($id)
    {
        $query = "SELECT * FROM wpk4_backend_agent_codes WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Check if sales_id exists
     */
    public function salesIdExists($salesId)
    {
        $query = "SELECT COUNT(*) as count FROM wpk4_backend_agent_codes WHERE sales_id = ?";
        $result = $this->queryOne($query, [$salesId]);
        return $result['count'] > 0;
    }

    /**
     * Create new agent
     */
    public function createAgent($data)
    {
        $query = "INSERT INTO wpk4_backend_agent_codes 
              (roster_code, sales_id, tsr, agent_name, team_name, team_leader, 
               wordpress_user_name, sale_manager, status, employee_status, 
               role, shift_rep_time, sort, department, location, shift_start_time, doj)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['roster_code'] ?? null,
            $data['sales_id'],
            $data['tsr'] ?? null,
            $data['agent_name'],
            $data['team_name'],
            $data['team_leader'] ?? null,
            $data['wordpress_user_name'] ?? null,
            $data['sale_manager'] ?? null,
            $data['status'] ?? 'active',
            $data['employee_status'] ?? null,
            $data['role'] ?? null,
            $data['shift_rep_time'] ?? null,
            $data['sort'] ?? null,
            $data['department'] ?? null,
            $data['location'] ?? null,
            $data['shift_start_time'] ?? null,
            $data['doj'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update agent
     */
    public function updateAgent($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = [
            'roster_code', 'sales_id', 'tsr', 'agent_name', 'team_name', 'team_leader',
            'wordpress_user_name', 'sale_manager', 'status', 'employee_status', 'role',
            'shift_rep_time', 'sort', 'department', 'location', 'shift_start_time', 'doj'
        ];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        // $setParts[] = "last_modified = NOW()";
        $setSQL = implode(', ', $setParts);
        
        $query = "UPDATE wpk4_backend_agent_codes SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete agent
     */
    public function deleteAgent($id)
    {
        $query = "DELETE FROM wpk4_backend_agent_codes WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }

    /**
     * Get distinct team names
     */
    public function getDistinctTeamNames()
    {
        $query = "SELECT DISTINCT team_name 
                  FROM wpk4_backend_agent_codes 
                  WHERE team_name IS NOT NULL AND team_name != '' 
                  ORDER BY team_name";
        $results = $this->query($query);
        return array_column($results, 'team_name');
    }

    /**
     * Get distinct team leaders
     */
    public function getDistinctTeamLeaders()
    {
        $query = "SELECT DISTINCT team_leader 
                  FROM wpk4_backend_agent_codes 
                  WHERE team_leader IS NOT NULL AND team_leader != '' 
                  ORDER BY team_leader";
        $results = $this->query($query);
        return array_column($results, 'team_leader');
    }

    /**
     * Get distinct sale managers
     */
    public function getDistinctSaleManagers()
    {
        $query = "SELECT DISTINCT sale_manager 
                  FROM wpk4_backend_agent_codes 
                  WHERE sale_manager IS NOT NULL AND sale_manager != '' 
                  ORDER BY sale_manager";
        $results = $this->query($query);
        return array_column($results, 'sale_manager');
    }

    /**
     * Get distinct employee statuses
     */
    public function getDistinctEmployeeStatuses()
    {
        $query = "SELECT DISTINCT employee_status 
                  FROM wpk4_backend_agent_codes 
                  WHERE employee_status IS NOT NULL AND employee_status != '' 
                  ORDER BY employee_status";
        $results = $this->query($query);
        return array_column($results, 'employee_status');
    }

    /**
     * Get distinct shift times
     */
    public function getDistinctShiftTimes()
    {
        $query = "SELECT DISTINCT shift_rep_time 
                  FROM wpk4_backend_agent_codes 
                  WHERE shift_rep_time IS NOT NULL AND shift_rep_time != '' 
                  ORDER BY shift_rep_time";
        $results = $this->query($query);
        return array_column($results, 'shift_rep_time');
    }

    /**
     * Get distinct roles
     */
    public function getDistinctRoles()
    {
        $query = "SELECT DISTINCT role 
                  FROM wpk4_backend_agent_codes 
                  WHERE role IS NOT NULL AND role != '' 
                  ORDER BY role";
        $results = $this->query($query);
        return array_column($results, 'role');
    }

    /**
     * Get distinct WordPress user names
     */
    public function getDistinctWordpressUserNames()
    {
        $query = "SELECT DISTINCT wordpress_user_name 
                  FROM wpk4_backend_agent_codes 
                  WHERE wordpress_user_name IS NOT NULL AND wordpress_user_name != '' 
                  ORDER BY wordpress_user_name";
        $results = $this->query($query);
        return array_column($results, 'wordpress_user_name');
    }

    /**
     * Get team details by team name
     */
    public function getTeamDetails($teamName)
    {
        $query = "SELECT team_leader, sale_manager, sort 
                  FROM wpk4_backend_agent_codes 
                  WHERE team_name = ? 
                  LIMIT 1";
        return $this->queryOne($query, [$teamName]);
    }

    /**
     * Get agents by team name
     */
    public function getAgentsByTeamName($teamName)
    {
        $query = "SELECT * FROM wpk4_backend_agent_codes 
                  WHERE team_name = ? 
                  ORDER BY sales_id";
        return $this->query($query, [$teamName]);
    }

    /**
     * Get teams with team leaders
     */
    public function getTeamsWithLeaders(): array
    {
        $query = "SELECT DISTINCT team_name, team_leader FROM wpk4_backend_agent_codes 
                  WHERE team_name IS NOT NULL AND team_name != '' 
                  ORDER BY team_name";
        return $this->query($query, []);
    }

    /**
     * Get TSRs with agent names
     */
    public function getTsrsWithAgentNames(): array
    {
        $query = "SELECT DISTINCT tsr, agent_name FROM wpk4_backend_agent_codes 
                  WHERE tsr IS NOT NULL AND tsr != '' 
                    AND agent_name IS NOT NULL AND agent_name != '' 
                  ORDER BY agent_name";
        return $this->query($query, []);
    }

    /**
     * Get team names from inbound call table
     */
    public function getTeamNamesFromInboundCall(): array
    {
        $query = "SELECT DISTINCT team_name 
                  FROM wpk4_backend_agent_inbound_call 
                  WHERE team_name IS NOT NULL AND team_name != '' 
                  ORDER BY team_name";
        $results = $this->query($query, []);
        return array_column($results, 'team_name');
    }

    /**
     * Get combined agent records (inbound call + booking data)
     */
    public function getCombinedAgentRecords(
        string $startDate,
        string $endDate,
        ?string $teamName = null,
        string $groupBy = 'team_name',
        string $orderBy = 'sale_manager, team_name'
    ): array {
        $params = [];
        
        // Build WHERE clause for inbound call
        $callWhereParts = ["a.call_date BETWEEN ? AND ?"];
        $params[] = $startDate;
        $params[] = $endDate;
        
        if ($teamName) {
            $callWhereParts[] = "a.team_name = ?";
            $params[] = $teamName;
        }
        
        $callWhereParts[] = "c.team_name != ''";
        $callWhereSQL = implode(' AND ', $callWhereParts);
        
        // Build WHERE clause for booking
        $bookingWhereParts = ["a.order_date BETWEEN ? AND ?"];
        $bookingParams = [$startDate, $endDate];
        
        if ($teamName) {
            $bookingWhereParts[] = "a.team_name = ?";
            $bookingParams[] = $teamName;
        }
        
        $bookingWhereParts[] = "c.team_name != ''";
        $bookingWhereSQL = implode(' AND ', $bookingWhereParts);
        
        // Combine all params
        $allParams = array_merge($params, $bookingParams);

        $sql = "
            SELECT
                MAX(sale_manager) AS sale_manager,
                MAX(team_name) AS team_name,
                MAX(agent_name) AS agent_name,
                SUM(pax) AS pax,
                SUM(fit) AS fit,
                SUM(pif) AS pif,
                SUM(gdeals) AS gdeals,
                SUM(gtib_count) AS gtib,
                SUM(sale_made_count) AS sale_made_count,
                SUM(non_sale_made_count) AS non_sale_made_count,
                SUM(rec_duration) AS rec_duration
            FROM (
                SELECT
                    a.agent_name,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    c.sale_manager,
                    a.gtib_count,
                    a.sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration AS rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE {$callWhereSQL}
               
                UNION ALL

                SELECT
                    a.agent_name,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    c.sale_manager,
                    0 AS gtib_count,
                    0 AS sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE {$bookingWhereSQL}
            ) AS combined_data
            GROUP BY {$groupBy}
            ORDER BY {$orderBy}
        ";

        return $this->query($sql, $allParams);
    }
}

