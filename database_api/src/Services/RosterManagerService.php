<?php
/**
 * Roster Manager Service - Business Logic Layer
 * Handles employee roster and timesheet management
 */

namespace App\Services;

use App\DAL\RosterManagerDAL;
use Exception;

class RosterManagerService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new RosterManagerDAL();
    }

    /**
     * Get all employees with filters
     */
    public function getAllEmployees($filters = [])
    {
        $empName = $filters['emp_name'] ?? null;
        $team = $filters['team'] ?? null;
        $department = $filters['department'] ?? null;
        $month = $filters['month'] ?? null;
        $year = $filters['year'] ?? date('Y');

        $employees = $this->dal->getAllEmployees($empName, $team, $department, $month, $year);

        return [
            'employees' => $employees,
            'total_count' => count($employees),
            'filters' => $filters
        ];
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById($empId)
    {
        if (empty($empId)) {
            throw new Exception('Valid employee ID is required', 400);
        }

        $employee = $this->dal->getEmployeeById($empId);

        if (!$employee) {
            throw new Exception('Employee not found', 404);
        }

        // Get timesheet data
        $timesheet = $this->dal->getEmployeeTimesheet($empId);

        return [
            'employee' => $employee,
            'timesheet' => $timesheet,
            'timesheet_count' => count($timesheet)
        ];
    }

    /**
     * Get employee timesheet for date range
     */
    public function getEmployeeTimesheet($empId, $fromDate = null, $toDate = null)
    {
        if (empty($empId)) {
            throw new Exception('Valid employee ID is required', 400);
        }

        // Validate date formats if provided
        if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            throw new Exception('from_date must be in YYYY-MM-DD format', 400);
        }

        if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            throw new Exception('to_date must be in YYYY-MM-DD format', 400);
        }

        // Validate date range
        if ($fromDate && $toDate && strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception('from_date must be before or equal to to_date', 400);
        }

        // First verify employee exists
        $employee = $this->dal->getEmployeeById($empId);
        if (!$employee) {
            throw new Exception("Employee with ID '{$empId}' not found", 404);
        }

        $timesheet = $this->dal->getEmployeeTimesheetByDate($empId, $fromDate, $toDate);

        return [
            'emp_id' => $empId,
            'emp_name' => $employee['emp_name'] ?? null,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'timesheet' => $timesheet,
            'total_days' => count($timesheet)
        ];
    }

    /**
     * Create employee
     */
    public function createEmployee($data)
    {
        $requiredFields = ['emp_name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $empId = $this->dal->createEmployee($data);

        return [
            'emp_id' => $empId,
            'emp_name' => $data['emp_name'],
            'message' => 'Employee created successfully'
        ];
    }

    /**
     * Update employee
     */
    public function updateEmployee($empId, $data)
    {
        if (!isset($empId) || $empId === '' || $empId === null) {
            throw new Exception('Valid employee ID is required', 400);
        }

        $employee = $this->dal->getEmployeeById($empId);
        if (!$employee) {
            throw new Exception('Employee not found', 404);
        }

        $this->dal->updateEmployee($empId, $data);

        return [
            'emp_id' => $empId,
            'message' => 'Employee updated successfully'
        ];
    }

    /**
     * Create timesheet entry
     */
    public function createTimesheetEntry($data)
    {
        $requiredFields = ['emp_id', 'dates'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $entryId = $this->dal->createTimesheetEntry($data);

        return [
            'entry_id' => $entryId,
            'emp_id' => $data['emp_id'],
            'dates' => $data['dates'],
            'message' => 'Timesheet entry created successfully'
        ];
    }

    /**
     * Get roster requests
     */
    public function getRosterRequests()
    {
        $requests = $this->dal->getRosterRequests();

        return [
            'requests' => $requests,
            'total_count' => count($requests)
        ];
    }
}

