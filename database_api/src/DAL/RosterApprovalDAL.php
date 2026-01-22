<?php
/**
 * Roster Approval Data Access Layer
 * Handles all database operations for roster approval requests
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class RosterApprovalDAL extends BaseDAL
{
    /**
     * Get all pending admin approval requests
     * 
     * @return array Array of pending requests
     */
    public function getPendingAdminApprovals()
    {
        $query = "
            SELECT * FROM wpk4_manage_roster_requests 
            WHERE status = 'Provision Approve'
            ORDER BY auto_id DESC
        ";
        
        return $this->query($query);
    }

    /**
     * Get all processed requests (approved/rejected by admin)
     * 
     * @return array Array of processed requests
     */
    public function getProcessedRequests()
    {
        $query = "
            SELECT * FROM wpk4_manage_roster_requests 
            WHERE (status LIKE 'Approved%' OR status = 'Rejected by Admin')
            ORDER BY auto_id DESC
        ";
        
        return $this->query($query);
    }

    /**
     * Get request by ID
     * 
     * @param int $requestId Request ID
     * @return array|null Request data or null if not found
     */
    public function getRequestById($requestId)
    {
        $query = "
            SELECT * FROM wpk4_manage_roster_requests 
            WHERE auto_id = :request_id
            LIMIT 1
        ";
        
        return $this->queryOne($query, ['request_id' => $requestId]);
    }

    /**
     * Update request status
     * 
     * @param int $requestId Request ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateRequestStatus($requestId, $status)
    {
        $query = "
            UPDATE wpk4_manage_roster_requests 
            SET status = :status 
            WHERE auto_id = :request_id
        ";
        
        return $this->execute($query, [
            'status' => $status,
            'request_id' => $requestId
        ]);
    }

    /**
     * Update agent shift time in agent codes table
     * 
     * @param string $agentName Agent name
     * @param string $shiftTime Shift time in H:i:s format
     * @return bool Success status
     */
    public function updateAgentShiftTime($agentName, $shiftTime)
    {
        $query = "
            UPDATE wpk4_backend_agent_codes 
            SET shift_start_time = :shift_time,
                shift_rep_time = :shift_time
            WHERE agent_name = :agent_name
        ";
        
        return $this->execute($query, [
            'shift_time' => $shiftTime,
            'agent_name' => $agentName
        ]);
    }

    /**
     * Update employee roster shift time
     * 
     * @param string $employeeName Employee name
     * @param string $shiftTime Shift time in 24hr format (e.g., "0400")
     * @return bool Success status
     */
    public function updateEmployeeRosterShiftTime($employeeName, $shiftTime)
    {
        $query = "
            UPDATE wpk4_backend_employee_roster 
            SET shift_time = :shift_time
            WHERE employee_name = :employee_name
        ";
        
        return $this->execute($query, [
            'shift_time' => $shiftTime,
            'employee_name' => $employeeName
        ]);
    }

    /**
     * Get employee roster records by employee name
     * 
     * @param string $employeeName Employee name
     * @return array Array of roster records
     */
    public function getEmployeeRosterRecords($employeeName)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster 
            WHERE employee_name = :employee_name
        ";
        
        return $this->query($query, ['employee_name' => $employeeName]);
    }

    /**
     * Update specific day column in employee roster
     * 
     * @param string $employeeName Employee name
     * @param string $month Month name
     * @param array $dayUpdates Array of ['day_X' => 'value'] pairs
     * @return bool Success status
     */
    public function updateEmployeeRosterDays($employeeName, $month, $dayUpdates)
    {
        if (empty($dayUpdates)) {
            return true;
        }
        
        $setParts = [];
        $params = ['employee_name' => $employeeName, 'month' => $month];
        
        foreach ($dayUpdates as $column => $value) {
            $setParts[] = "$column = :$column";
            $params[$column] = $value;
        }
        
        $query = "
            UPDATE wpk4_backend_employee_roster 
            SET " . implode(', ', $setParts) . "
            WHERE employee_name = :employee_name AND month = :month
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Get shift time for employee in specific month
     * 
     * @param string $employeeName Employee name
     * @param string $month Month name
     * @return string|null Shift time or null
     */
    public function getEmployeeShiftTime($employeeName, $month)
    {
        $query = "
            SELECT shift_time FROM wpk4_backend_employee_roster 
            WHERE employee_name = :employee_name AND month = :month
            LIMIT 1
        ";
        
        $result = $this->queryOne($query, [
            'employee_name' => $employeeName,
            'month' => $month
        ]);
        
        return $result['shift_time'] ?? null;
    }

    /**
     * Update specific day in employee roster to a value
     * 
     * @param string $employeeName Employee name
     * @param string $month Month name
     * @param int $day Day number (1-31)
     * @param string $value Value to set (e.g., 'RDO', 'Leave', shift time)
     * @return bool Success status
     */
    public function updateEmployeeRosterDay($employeeName, $month, $day, $value)
    {
        $query = "
            UPDATE wpk4_backend_employee_roster 
            SET day_$day = :value
            WHERE employee_name = :employee_name AND month = :month
        ";
        
        return $this->execute($query, [
            'value' => $value,
            'employee_name' => $employeeName,
            'month' => $month
        ]);
    }
}

