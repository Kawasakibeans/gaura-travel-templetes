<?php
/**
 * Employee Roster Service - Business Logic Layer
 * Handles business logic for employee roster management
 */

namespace App\Services;

use App\DAL\EmployeeRosterDAL;
use Exception;

class EmployeeRosterService
{
    private $employeeRosterDAL;

    public function __construct()
    {
        $this->employeeRosterDAL = new EmployeeRosterDAL();
    }

    /**
     * Get roster data for an employee
     * 
     * @param string $wordpressUsername WordPress username (optional)
     * @param string $agentName Agent name (optional, used if wordpressUsername not provided)
     * @param string $month Month name (e.g., 'January', 'February')
     * @return array Formatted roster data
     */
    public function getRosterData($wordpressUsername = null, $agentName = null, $month = null)
    {
        // Set default month if not provided
        if ($month === null) {
            $month = date('F');
        }

        // Get agent data
        $agentData = null;
        if ($wordpressUsername) {
            $agentData = $this->employeeRosterDAL->getAgentByWordPressUsername($wordpressUsername);
        } elseif ($agentName) {
            $agentData = $this->employeeRosterDAL->getAgentByName($agentName);
        }

        if (!$agentData) {
            throw new Exception('Agent not found', 404);
        }

        $rosterCode = $agentData['roster_code'] ?? null;
        if (!$rosterCode) {
            throw new Exception('Roster code not found for agent', 404);
        }

        // Get roster data
        $rosterData = $this->employeeRosterDAL->getRosterData($rosterCode, $month);

        if (!$rosterData) {
            return [
                'success' => true,
                'agent' => [
                    'agent_name' => $agentData['agent_name'] ?? '',
                    'roster_code' => $rosterCode
                ],
                'month' => $month,
                'roster' => null,
                'is_confirmed' => false,
                'message' => 'No roster data found for this month'
            ];
        }

        // Format shift time
        $currentShift = $rosterData['shift_time'] ?? 'NA';
        if (preg_match('/^\d{4}$/', $currentShift)) {
            $currentShift = substr($currentShift, 0, 2) . ':' . substr($currentShift, 2);
        }

        // Format RDO
        $dayMapping = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
        ];
        $rdoDay = strtolower($rosterData['rdo'] ?? '');
        $currentRdo = isset($dayMapping[$rdoDay]) ? $dayMapping[$rdoDay] : 'NA';

        // Get shifts for all days
        $shifts = [];
        for ($i = 1; $i <= 31; $i++) {
            $dayKey = 'day_' . $i;
            $shifts[$i] = $rosterData[$dayKey] ?? '';
        }

        // Check if confirmed
        $isConfirmed = $this->employeeRosterDAL->isRosterConfirmed($rosterCode, $month);

        // Get approval history
        $approvalHistory = $this->employeeRosterDAL->getApprovalHistory($rosterCode);

        return [
            'success' => true,
            'agent' => [
                'agent_name' => $agentData['agent_name'] ?? '',
                'roster_code' => $rosterCode,
                'sale_manager' => $rosterData['sm'] ?? ''
            ],
            'month' => $month,
            'roster' => [
                'shift_time' => $currentShift,
                'rdo' => $currentRdo,
                'rdo_day' => $rdoDay,
                'shifts' => $shifts,
                'year' => $rosterData['year'] ?? date('Y'),
                'month' => $rosterData['month'] ?? $month
            ],
            'is_confirmed' => $isConfirmed,
            'approval_history' => $approvalHistory,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Get approval history for an employee
     * 
     * @param string $wordpressUsername WordPress username (optional)
     * @param string $agentName Agent name (optional)
     * @return array Approval history
     */
    public function getApprovalHistory($wordpressUsername = null, $agentName = null)
    {
        // Get agent data
        $agentData = null;
        if ($wordpressUsername) {
            $agentData = $this->employeeRosterDAL->getAgentByWordPressUsername($wordpressUsername);
        } elseif ($agentName) {
            $agentData = $this->employeeRosterDAL->getAgentByName($agentName);
        }

        if (!$agentData) {
            throw new Exception('Agent not found', 404);
        }

        $rosterCode = $agentData['roster_code'] ?? null;
        if (!$rosterCode) {
            throw new Exception('Roster code not found for agent', 404);
        }

        $history = $this->employeeRosterDAL->getApprovalHistory($rosterCode);

        return [
            'success' => true,
            'agent' => [
                'agent_name' => $agentData['agent_name'] ?? '',
                'roster_code' => $rosterCode
            ],
            'approval_history' => $history,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Create a shift change request
     * 
     * @param array $requestData Request data
     * @return array Created request data
     */
    public function createShiftChangeRequest($requestData)
    {
        // Validate required fields
        if (empty($requestData['roster_code']) || empty($requestData['agent_name'])) {
            throw new Exception('Roster code and agent name are required', 400);
        }

        // Get roster data to get sale manager and current shift
        $rosterData = $this->employeeRosterDAL->getRosterData(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if (!$rosterData) {
            throw new Exception('Roster data not found', 404);
        }

        // Check if roster is confirmed
        $isConfirmed = $this->employeeRosterDAL->isRosterConfirmed(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if ($isConfirmed) {
            throw new Exception('Roster is already confirmed. No changes can be requested.', 400);
        }

        // Format current shift
        $currentShift = $rosterData['shift_time'] ?? 'NA';
        if (preg_match('/^\d{4}$/', $currentShift)) {
            $hours = substr($currentShift, 0, 2);
            $minutes = substr($currentShift, 2);
            $formattedCurrentShift = date('g:i A', strtotime("$hours:$minutes"));
        } else {
            $formattedCurrentShift = $currentShift;
        }

        $requestData['type'] = 'Shift Change Request';
        $requestData['status'] = 'Pending';
        $requestData['sale_manager'] = $rosterData['sm'] ?? '';
        $requestData['current_shift'] = $formattedCurrentShift;
        $requestData['created_date'] = date('Y-m-d H:i:s');

        $requestId = $this->employeeRosterDAL->createRosterRequest($requestData);

        return [
            'success' => true,
            'request_id' => $requestId,
            'message' => 'Shift change request created successfully',
            'request' => $requestData
        ];
    }

    /**
     * Create an RDO change request
     * 
     * @param array $requestData Request data
     * @return array Created request data
     */
    public function createRDOChangeRequest($requestData)
    {
        // Validate required fields
        if (empty($requestData['roster_code']) || empty($requestData['agent_name'])) {
            throw new Exception('Roster code and agent name are required', 400);
        }

        // Get roster data
        $rosterData = $this->employeeRosterDAL->getRosterData(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if (!$rosterData) {
            throw new Exception('Roster data not found', 404);
        }

        // Check if roster is confirmed
        $isConfirmed = $this->employeeRosterDAL->isRosterConfirmed(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if ($isConfirmed) {
            throw new Exception('Roster is already confirmed. No changes can be requested.', 400);
        }

        // Format current RDO
        $dayMapping = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
        ];
        $rdoDay = strtolower($rosterData['rdo'] ?? '');
        $currentRdo = isset($dayMapping[$rdoDay]) ? $dayMapping[$rdoDay] : 'NA';

        // Parse requested day (format: "Monday (15/1)")
        $requestedDay = $requestData['requested_rdo'] ?? '';
        if (strpos($requestedDay, '(') !== false) {
            $requestedDay = trim(strtok($requestedDay, '('));
        }

        $requestData['type'] = 'RDO Change Request';
        $requestData['status'] = 'Pending';
        $requestData['sale_manager'] = $rosterData['sm'] ?? '';
        $requestData['current_rdo'] = $currentRdo;
        $requestData['requested_rdo'] = $requestedDay;
        $requestData['created_date'] = date('Y-m-d H:i:s');

        $requestId = $this->employeeRosterDAL->createRosterRequest($requestData);

        return [
            'success' => true,
            'request_id' => $requestId,
            'message' => 'RDO change request created successfully',
            'request' => $requestData
        ];
    }

    /**
     * Create a leave request
     * 
     * @param array $requestData Request data
     * @return array Created request data
     */
    public function createLeaveRequest($requestData)
    {
        // Validate required fields
        if (empty($requestData['roster_code']) || empty($requestData['agent_name'])) {
            throw new Exception('Roster code and agent name are required', 400);
        }

        // Get roster data
        $rosterData = $this->employeeRosterDAL->getRosterData(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if (!$rosterData) {
            throw new Exception('Roster data not found', 404);
        }

        // Check if roster is confirmed
        $isConfirmed = $this->employeeRosterDAL->isRosterConfirmed(
            $requestData['roster_code'],
            $requestData['month'] ?? date('F')
        );

        if ($isConfirmed) {
            throw new Exception('Roster is already confirmed. No changes can be requested.', 400);
        }

        // Parse requested day (format: "Monday (15/1)")
        $requestedDay = $requestData['requested_rdo'] ?? '';
        if (strpos($requestedDay, '(') !== false) {
            $requestedDay = trim(strtok($requestedDay, '('));
        }

        $requestData['type'] = 'Leave Request';
        $requestData['status'] = 'Pending';
        $requestData['sale_manager'] = $rosterData['sm'] ?? '';
        $requestData['current_rdo'] = $requestedDay;
        $requestData['created_date'] = date('Y-m-d H:i:s');

        $requestId = $this->employeeRosterDAL->createRosterRequest($requestData);

        return [
            'success' => true,
            'request_id' => $requestId,
            'message' => 'Leave request created successfully',
            'request' => $requestData
        ];
    }

    /**
     * Confirm roster for an employee
     * 
     * @param string $rosterCode Employee roster code
     * @param string $month Month name
     * @return array Confirmation result
     */
    public function confirmRoster($rosterCode, $month)
    {
        // Check if already confirmed
        $isConfirmed = $this->employeeRosterDAL->isRosterConfirmed($rosterCode, $month);
        
        if ($isConfirmed) {
            throw new Exception('Roster is already confirmed', 400);
        }

        $success = $this->employeeRosterDAL->confirmRoster($rosterCode, $month);

        if (!$success) {
            throw new Exception('Failed to confirm roster', 500);
        }

        return [
            'success' => true,
            'message' => 'Roster confirmed successfully',
            'roster_code' => $rosterCode,
            'month' => $month
        ];
    }
}

