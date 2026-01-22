<?php
/**
 * Employee Roster Data Access Layer
 * Handles all database operations for employee roster management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EmployeeRosterDAL extends BaseDAL
{
    /**
     * Get agent data by WordPress username
     * 
     * @param string $wordpressUsername WordPress username
     * @return array|null Agent data or null if not found
     */
    public function getAgentByWordPressUsername($wordpressUsername)
    {
        $sql = "
            SELECT agent_name, roster_code, location, status
            FROM wpk4_backend_agent_codes
            WHERE wordpress_user_name = :username
            LIMIT 1
        ";

        $result = $this->queryOne($sql, ['username' => $wordpressUsername]);
        
        return $result;
    }

    /**
     * Get agent data by agent name
     * 
     * @param string $agentName Agent name
     * @return array|null Agent data or null if not found
     */
    public function getAgentByName($agentName)
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_agent_codes
            WHERE agent_name LIKE :agent_name
            LIMIT 1
        ";

        $result = $this->queryOne($sql, ['agent_name' => $agentName]);
        
        return $result;
    }

    /**
     * Get roster data for an employee
     * 
     * @param string $rosterCode Employee roster code
     * @param string $month Month name (e.g., 'January', 'February')
     * @return array|null Roster data or null if not found
     */
    public function getRosterData($rosterCode, $month)
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_employee_roster_bom
            WHERE employee_code = :roster_code
            AND month = :month
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [
            'roster_code' => $rosterCode,
            'month' => $month
        ]);
        
        return $result;
    }

    /**
     * Get approval history for an employee
     * 
     * @param string $rosterCode Employee roster code
     * @return array Array of approval history records
     */
    public function getApprovalHistory($rosterCode)
    {
        $sql = "
            SELECT *
            FROM wpk4_manage_roster_requests
            WHERE roster_code = :roster_code
            ORDER BY auto_id DESC
        ";

        $results = $this->query($sql, ['roster_code' => $rosterCode]);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'auto_id' => (int)$row['auto_id'],
                'type' => $row['type'] ?? '',
                'agent_name' => $row['agent_name'] ?? '',
                'sale_manager' => $row['sale_manager'] ?? '',
                'roster_code' => $row['roster_code'] ?? '',
                'status' => $row['status'] ?? '',
                'current_shift' => $row['current_shift'] ?? '',
                'requested_shift' => $row['requested_shift'] ?? '',
                'current_rdo' => $row['current_rdo'] ?? '',
                'requested_rdo' => $row['requested_rdo'] ?? '',
                'reason' => $row['reason'] ?? '',
                'created_date' => $row['created_date'] ?? ''
            ];
        }
        
        return $data;
    }

    /**
     * Create a roster request
     * 
     * @param array $requestData Request data
     * @return int Insert ID
     */
    public function createRosterRequest($requestData)
    {
        $sql = "
            INSERT INTO wpk4_manage_roster_requests 
            (type, agent_name, sale_manager, roster_code, status, current_shift, requested_shift, current_rdo, requested_rdo, reason, created_date)
            VALUES 
            (:type, :agent_name, :sale_manager, :roster_code, :status, :current_shift, :requested_shift, :current_rdo, :requested_rdo, :reason, :created_date)
        ";

        $params = [
            'type' => $requestData['type'] ?? '',
            'agent_name' => $requestData['agent_name'] ?? '',
            'sale_manager' => $requestData['sale_manager'] ?? '',
            'roster_code' => $requestData['roster_code'] ?? '',
            'status' => $requestData['status'] ?? 'Pending',
            'current_shift' => $requestData['current_shift'] ?? null,
            'requested_shift' => $requestData['requested_shift'] ?? null,
            'current_rdo' => $requestData['current_rdo'] ?? null,
            'requested_rdo' => $requestData['requested_rdo'] ?? null,
            'reason' => $requestData['reason'] ?? '',
            'created_date' => $requestData['created_date'] ?? date('Y-m-d H:i:s')
        ];

        $this->execute($sql, $params);
        
        return $this->lastInsertId();
    }

    /**
     * Confirm roster for an employee
     * 
     * @param string $rosterCode Employee roster code
     * @param string $month Month name
     * @return bool Success status
     */
    public function confirmRoster($rosterCode, $month)
    {
        $sql = "
            UPDATE wpk4_backend_employee_roster_bom
            SET confirm = 1
            WHERE employee_code = :roster_code
            AND month = :month
        ";

        return $this->execute($sql, [
            'roster_code' => $rosterCode,
            'month' => $month
        ]);
    }

    /**
     * Check if roster is confirmed
     * 
     * @param string $rosterCode Employee roster code
     * @param string $month Month name
     * @return bool True if confirmed, false otherwise
     */
    public function isRosterConfirmed($rosterCode, $month)
    {
        $sql = "
            SELECT confirm
            FROM wpk4_backend_employee_roster_bom
            WHERE employee_code = :roster_code
            AND month = :month
            LIMIT 1
        ";

        $result = $this->queryOne($sql, [
            'roster_code' => $rosterCode,
            'month' => $month
        ]);

        return isset($result['confirm']) && (int)$result['confirm'] === 1;
    }
}

