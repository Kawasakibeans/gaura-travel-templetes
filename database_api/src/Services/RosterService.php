<?php
namespace App\Services;

use App\DAL\RosterDAL;
use Exception;

class RosterService
{
    private $rosterDAL;

    public function __construct()
    {
        $this->rosterDAL = new RosterDAL();
    }

    /**
     * Get pending roster requests
     */
    public function getPendingRequests($startDate = null, $endDate = null, $saleManager = '')
    {
        return $this->rosterDAL->getPendingRequests($startDate, $endDate, $saleManager);
    }

    /**
     * Get processed roster requests
     */
    public function getProcessedRequests($startDate = null, $endDate = null, $saleManager = '')
    {
        return $this->rosterDAL->getProcessedRequests($startDate, $endDate, $saleManager);
    }

    /**
     * Get leave requests
     */
    public function getLeaveRequests($saleManager = '')
    {
        return $this->rosterDAL->getLeaveRequests($saleManager);
    }

    /**
     * Get processed leave requests
     */
    public function getProcessedLeaveRequests($saleManager = '')
    {
        return $this->rosterDAL->getProcessedLeaveRequests($saleManager);
    }

    /**
     * Get sales managers list
     */
    public function getSalesManagers()
    {
        $results = $this->rosterDAL->getSalesManagers();
        return array_column($results, 'sale_manager');
    }

    /**
     * Approve roster request
     */
    public function approveRosterRequest($requestId)
    {
        $request = $this->rosterDAL->getRequestById($requestId);
        
        if (!$request) {
            throw new Exception('Request not found', 404);
        }

        // Update request status
        $this->rosterDAL->updateRequestStatus($requestId, 'Provision Approve');

        // If approved, update the relevant system records
        // Wrap in try-catch to prevent failures from blocking the approval
        try {
            if ($request['type'] === 'RDO Change Request') {
                $newRdo = strtolower(substr($request['requested_rdo'], 0, 3));
                $this->rosterDAL->updateAvailabilityRDO($request['roster_code'], $newRdo);
            } elseif ($request['type'] === 'Shift Change Request') {
                $newShift = preg_replace('/[^0-9:]/', '', $request['requested_shift']);
                $newShift = date('Hi', strtotime($newShift));
                $this->rosterDAL->updateRosterShiftTime($request['roster_code'], $newShift);
            }
        } catch (Exception $e) {
            // Log the error but don't fail the approval
            error_log("RosterService::approveRosterRequest - Failed to update related records: " . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Request approved successfully'];
    }

    /**
     * Reject roster request
     */
    public function rejectRosterRequest($requestId)
    {
        $request = $this->rosterDAL->getRequestById($requestId);
        
        if (!$request) {
            throw new Exception('Request not found', 404);
        }

        $this->rosterDAL->updateRequestStatus($requestId, 'Rejected');

        return ['success' => true, 'message' => 'Request rejected successfully'];
    }

    /**
     * Approve leave request
     */
    public function approveLeaveRequest($leaveId)
    {
        $this->rosterDAL->updateLeaveRequestStatus($leaveId, 'Approved');
        return ['success' => true, 'message' => 'Leave request approved successfully'];
    }

    /**
     * Reject leave request
     */
    public function rejectLeaveRequest($leaveId)
    {
        $this->rosterDAL->updateLeaveRequestStatus($leaveId, 'Rejected');
        return ['success' => true, 'message' => 'Leave request rejected successfully'];
    }

    /**
     * Get employee roster data
     */
    public function getEmployeeRoster($rosterCode, $month)
    {
        if (empty($rosterCode) || empty($month)) {
            throw new Exception('Roster code and month are required', 400);
        }

        return $this->rosterDAL->getEmployeeRoster($rosterCode, $month);
    }

    /**
     * Get employee roster data by employee_code, month, and year
     */
    public function getEmployeeRosterByCodeMonthYear($employeeCode, $month, $year)
    {
        if (empty($employeeCode)) {
            throw new Exception('Employee code is required', 400);
        }

        if (empty($month)) {
            throw new Exception('Month is required', 400);
        }

        if (empty($year)) {
            throw new Exception('Year is required', 400);
        }

        // Validate year
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            throw new Exception('Invalid year. Must be between 2000 and 2100', 400);
        }

        // Validate month (can be month name like "January" or numeric 1-12)
        $monthName = $month;
        if (is_numeric($month)) {
            $monthNum = (int)$month;
            if ($monthNum < 1 || $monthNum > 12) {
                throw new Exception('Invalid month. Must be between 1 and 12', 400);
            }
            $monthName = date('F', mktime(0, 0, 0, $monthNum, 1, $year));
        }

        $result = $this->rosterDAL->getEmployeeRosterByCodeMonthYear($employeeCode, $monthName, $year);
        
        if (!$result) {
            throw new Exception('Roster data not found', 404);
        }

        return $result;
    }

    /**
     * Get employee approval history
     */
    public function getEmployeeApprovalHistory($agentName)
    {
        if (empty($agentName)) {
            throw new Exception('Agent name is required', 400);
        }

        return $this->rosterDAL->getEmployeeApprovalHistory($agentName);
    }

    /**
     * Submit shift change request
     */
    public function submitShiftChangeRequest($data)
    {
        $required = ['agent_name', 'sale_manager', 'roster_code', 'current_shift', 'requested_shift', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }

        $formattedCurrentShift = date('g:i A', strtotime($data['current_shift']));
        $data['current_shift'] = $formattedCurrentShift;

        $requestId = $this->rosterDAL->insertShiftChangeRequest($data);

        return [
            'success' => true,
            'message' => 'Shift change request submitted successfully',
            'request_id' => $requestId
        ];
    }

    /**
     * Submit RDO change request
     */
    public function submitRDOChangeRequest($data)
    {
        $required = ['agent_name', 'sale_manager', 'roster_code', 'current_rdo', 'requested_rdo', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }

        // Extract day name from format like "Monday (15/12)"
        if (strpos($data['requested_rdo'], '(') !== false) {
            $data['requested_rdo'] = trim(strtok($data['requested_rdo'], '('));
        }

        $requestId = $this->rosterDAL->insertRDOChangeRequest($data);

        return [
            'success' => true,
            'message' => 'RDO change request submitted successfully',
            'request_id' => $requestId
        ];
    }

    /**
     * Submit leave request
     */
    public function submitLeaveRequest($data)
    {
        $required = ['agent_name', 'sale_manager', 'roster_code', 'requested_day', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }

        // Extract day name from format like "Monday (15/12)"
        if (strpos($data['requested_day'], '(') !== false) {
            $data['requested_day'] = trim(strtok($data['requested_day'], '('));
        }

        $data['current_rdo'] = $data['requested_day'];
        unset($data['requested_day']);

        $requestId = $this->rosterDAL->insertLeaveRequest($data);

        return [
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'request_id' => $requestId
        ];
    }

    /**
     * Confirm roster
     */
    public function confirmRoster($rosterCode, $month)
    {
        if (empty($rosterCode) || empty($month)) {
            throw new Exception('Roster code and month are required', 400);
        }

        $this->rosterDAL->confirmRoster($rosterCode, $month);

        return ['success' => true, 'message' => 'Roster confirmed successfully'];
    }

    /**
     * Get roster data with filters
     */
    public function getRosterData($month, $year, $team = '', $department = '', $saleManager = '', $shiftTime = '')
    {
        if (empty($month) || empty($year)) {
            throw new Exception('Month and year are required', 400);
        }

        if ($month < 1 || $month > 12) {
            throw new Exception('Invalid month. Must be between 1 and 12', 400);
        }

        return $this->rosterDAL->getRosterData($month, $year, $team, $department, $saleManager, $shiftTime);
    }

    /**
     * Get roster filter options
     */
    public function getFilterOptions()
    {
        return $this->rosterDAL->getFilterOptions();
    }

    /**
     * Get attendance data
     */
    public function getAttendanceData($employeeCode, $date)
    {
        if (empty($employeeCode) || empty($date)) {
            throw new Exception('Employee code and date are required', 400);
        }

        return $this->rosterDAL->getAttendanceData($employeeCode, $date);
    }

    /**
     * Update employee shift
     */
    public function updateEmployeeShift($employeeCode, $day, $month, $year, $newShift)
    {
        if (empty($employeeCode) || empty($day) || empty($month) || empty($year) || empty($newShift)) {
            throw new Exception('All fields are required', 400);
        }

        if ($day < 1 || $day > 31) {
            throw new Exception('Invalid day. Must be between 1 and 31', 400);
        }

        if ($month < 1 || $month > 12) {
            throw new Exception('Invalid month. Must be between 1 and 12', 400);
        }

        $this->rosterDAL->updateEmployeeShift($employeeCode, $day, $month, $year, $newShift);

        return ['success' => true, 'message' => 'Shift updated successfully'];
    }

    /**
     * Get roster requests by employee_code
     */
    public function getRosterRequestsByEmployeeCode($employeeCode)
    {
        if (empty($employeeCode)) {
            throw new Exception('Employee code is required', 400);
        }

        $result = $this->rosterDAL->getRosterRequestsByEmployeeCode($employeeCode);
        
        if (empty($result)) {
            throw new Exception('No roster requests found for this employee code', 404);
        }

        return $result;
    }

    /**
     * Get roster request by ID
     */
    public function getRosterRequestById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid request ID is required', 400);
        }

        $result = $this->rosterDAL->getRosterRequestById((int)$id);
        
        if (!$result) {
            throw new Exception('Roster request not found', 404);
        }

        return $result;
    }

    /**
     * Get roster requests by employee_code or ID
     * Supports both query methods in a single endpoint
     */
    public function getRosterRequestsByCodeOrId($employeeCode = null, $id = null)
    {
        if (empty($employeeCode) && empty($id)) {
            throw new Exception('Either employee_code or id is required', 400);
        }

        if (!empty($id) && !is_numeric($id)) {
            throw new Exception('Valid request ID is required', 400);
        }

        $result = $this->rosterDAL->getRosterRequestsByCodeOrId($employeeCode, $id);
        
        if (empty($result)) {
            if (!empty($id)) {
                throw new Exception('Roster request not found', 404);
            } else {
                throw new Exception('No roster requests found for this employee code', 404);
            }
        }

        return $result;
    }

    /**
     * Get leave requests by employee_code
     */
    public function getLeaveRequestsByEmployeeCode($employeeCode)
    {
        if (empty($employeeCode)) {
            throw new Exception('Employee code is required', 400);
        }

        $result = $this->rosterDAL->getLeaveRequestsByEmployeeCode($employeeCode);
        
        if (empty($result)) {
            throw new Exception('No leave requests found for this employee code', 404);
        }

        return $result;
    }

    /**
     * Get leave request by ID
     */
    public function getLeaveRequestById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid leave request ID is required', 400);
        }

        $result = $this->rosterDAL->getLeaveRequestById((int)$id);
        
        if (!$result) {
            throw new Exception('Leave request not found', 404);
        }

        return $result;
    }

    /**
     * Get leave requests by doc_no
     */
    public function getLeaveRequestsByDocNo($docNo)
    {
        if (empty($docNo)) {
            throw new Exception('Document number is required', 400);
        }

        $result = $this->rosterDAL->getLeaveRequestsByDocNo($docNo);
        
        if (empty($result)) {
            throw new Exception('No leave requests found for this document number', 404);
        }

        return $result;
    }

    /**
     * Get leave requests by employee_code, id, and/or doc_no
     * Supports all three query methods in a single endpoint
     */
    public function getLeaveRequestsByCodeOrIdOrDocNo($employeeCode = null, $id = null, $docNo = null)
    {
        if (empty($employeeCode) && empty($id) && empty($docNo)) {
            throw new Exception('Either employee_code, id, or doc_no is required', 400);
        }

        if (!empty($id) && !is_numeric($id)) {
            throw new Exception('Valid leave request ID is required', 400);
        }

        $result = $this->rosterDAL->getLeaveRequestsByCodeOrIdOrDocNo($employeeCode, $id, $docNo);
        
        if (empty($result)) {
            $searchCriteria = [];
            if (!empty($employeeCode)) $searchCriteria[] = "employee_code: {$employeeCode}";
            if (!empty($id)) $searchCriteria[] = "id: {$id}";
            if (!empty($docNo)) $searchCriteria[] = "doc_no: {$docNo}";
            $criteriaStr = implode(', ', $searchCriteria);
            throw new Exception("No leave requests found for {$criteriaStr}", 404);
        }

        return $result;
    }

    /**
     * Get all employee roster records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        return $this->rosterDAL->getAll($limit, $offset, $filters);
    }
    /**
     * Create a new employee roster record
     */
    public function create($data)
    {
        // Validate required fields
        if (empty($data['employee_code'])) {
            throw new Exception('employee_code is required', 400);
        }
        if (empty($data['month'])) {
            throw new Exception('month is required', 400);
        }
        if (empty($data['year'])) {
            throw new Exception('year is required', 400);
        }
        
        return $this->rosterDAL->create($data);
    }
    
    /**
     * Update an existing employee roster record
     */
    public function update($employeeCode, $month, $year, $data)
    {
        // Validate required fields
        if (empty($employeeCode)) {
            throw new Exception('employee_code is required', 400);
        }
        if (empty($month)) {
            throw new Exception('month is required', 400);
        }
        if (empty($year)) {
            throw new Exception('year is required', 400);
        }
        
        // Check if record exists
        $existing = $this->rosterDAL->getEmployeeRosterByCodeMonthYear($employeeCode, $month, $year);
        if (!$existing) {
            throw new Exception('Roster record not found', 404);
        }
        
        return $this->rosterDAL->update($employeeCode, $month, $year, $data);
    }
}

