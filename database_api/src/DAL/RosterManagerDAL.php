<?php
/**
 * Roster Manager Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class RosterManagerDAL extends BaseDAL
{
    /**
     * Get all employees with filters
     */
    public function getAllEmployees($empName, $team, $department, $month, $year)
    {
        $whereParts = ["team_leader != '0'"];
        $params = [];

        if ($empName) {
            $whereParts[] = "emp_name LIKE ?";
            $params[] = '%' . $empName . '%';
        }

        if ($team) {
            $whereParts[] = "team LIKE ?";
            $params[] = '%' . $team . '%';
        }

        if ($department) {
            $whereParts[] = "department = ?";
            $params[] = $department;
        }

        // Month filter via timesheet join
        if ($month) {
            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $whereParts[] = "emp.emp_id IN (SELECT emp_id FROM wpk4_backend_gtx_employee_timesheet time WHERE emp.emp_id = time.emp_id AND time.dates >= ? AND time.dates <= ?)";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT * FROM wpk4_backend_gtx_employee AS emp 
                  WHERE $whereSQL 
                  ORDER BY emp.team_leader DESC";
        
        return $this->query($query, $params);
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById($empId)
    {
        $query = "SELECT * FROM wpk4_backend_gtx_employee WHERE emp_id = ? LIMIT 1";
        return $this->queryOne($query, [$empId]);
    }

    /**
     * Get employee timesheet
     */
    public function getEmployeeTimesheet($empId)
    {
        $query = "SELECT * FROM wpk4_backend_gtx_employee_timesheet 
                  WHERE emp_id = ? 
                  ORDER BY dates DESC";
        
        return $this->query($query, [$empId]);
    }

    /**
     * Get employee timesheet by date range
     */
    public function getEmployeeTimesheetByDate($empId, $fromDate, $toDate)
    {
        $whereParts = ["emp_id = ?"];
        $params = [$empId];

        if ($fromDate) {
            $whereParts[] = "dates >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $whereParts[] = "dates <= ?";
            $params[] = $toDate;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT * FROM wpk4_backend_gtx_employee_timesheet 
                  WHERE $whereSQL 
                  ORDER BY dates ASC";
        
        return $this->query($query, $params);
    }

    /**
     * Create employee
     */
    public function createEmployee($data)
    {
        $query = "INSERT INTO wpk4_backend_gtx_employee 
                  (emp_id, emp_name, team, team_leader, department, branch, wordpress_code, 
                   effective_date, sales_code, noble_code, rdo, shift_type)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->execute($query, [
            $data['emp_id'],
            $data['emp_name'],
            $data['team'] ?? null,
            $data['team_leader'] ?? null,
            $data['department'] ?? null,
            $data['branch'] ?? null,
            $data['wordpress_code'] ?? null,
            $data['effective_date'] ?? null,
            $data['sales_code'] ?? null,
            $data['noble_code'] ?? null,
            $data['rdo'] ?? null,
            $data['shift_type'] ?? null
        ]);
        
        return $data['emp_id'];
    }

    /**
     * Update employee
     */
    public function updateEmployee($empId, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = ['emp_name', 'team', 'team_leader', 'department', 'status'];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_backend_gtx_employee SET $setSQL WHERE emp_id = ?";
        $params[] = $empId;

        return $this->execute($query, $params);
    }

    /**
     * Create timesheet entry
     */
    public function createTimesheetEntry($data)
    {
        $query = "INSERT INTO wpk4_backend_gtx_employee_timesheet 
                  (emp_id, dates, shift_code, shift_begin_time)
                  VALUES (?, ?, ?, ?)";
        
        $this->execute($query, [
            $data['emp_id'],
            $data['dates'],
            $data['shift_code'] ?? $data['shift_start'] ?? null,
            $data['shift_begin_time'] ?? $data['shift_end'] ?? null
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Get roster requests
     */
    public function getRosterRequests()
    {
        $query = "SELECT * FROM wpk4_backend_gtx_employee_request 
                  LIMIT 100";
        
        return $this->query($query);
    }
}

