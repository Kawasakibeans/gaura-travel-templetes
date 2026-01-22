<?php
/**
 * Roster Approval Service - Business Logic Layer
 * Handles business logic for roster approval requests
 */

namespace App\Services;

use App\DAL\RosterApprovalDAL;
use Exception;

class RosterApprovalService
{
    private $rosterApprovalDAL;

    public function __construct()
    {
        $this->rosterApprovalDAL = new RosterApprovalDAL();
    }

    /**
     * Get all pending admin approval requests
     * 
     * @return array Formatted pending requests
     */
    public function getPendingAdminApprovals()
    {
        $requests = $this->rosterApprovalDAL->getPendingAdminApprovals();
        
        return [
            'requests' => $requests,
            'count' => count($requests),
            'status' => 'pending'
        ];
    }

    /**
     * Get all processed requests
     * 
     * @return array Formatted processed requests
     */
    public function getProcessedRequests()
    {
        $requests = $this->rosterApprovalDAL->getProcessedRequests();
        
        return [
            'requests' => $requests,
            'count' => count($requests),
            'status' => 'processed'
        ];
    }

    /**
     * Approve a roster request
     * 
     * @param int $requestId Request ID
     * @return array Approval result
     */
    public function approveRequest($requestId)
    {
        $request = $this->rosterApprovalDAL->getRequestById($requestId);
        
        if (!$request) {
            throw new Exception('Request not found', 404);
        }

        $currentDate = date('j/n');
        $newStatus = 'Approved (' . $currentDate . ')';

        // Update request status
        $this->rosterApprovalDAL->updateRequestStatus($requestId, $newStatus);

        // Process based on request type
        if ($request['type'] === 'Shift Change Request') {
            $this->processShiftChangeApproval($request);
        } elseif ($request['type'] === 'RDO Change Request') {
            $this->processRDOChangeApproval($request);
        } elseif ($request['type'] === 'Leave Request') {
            $this->processLeaveApproval($request);
        }

        return [
            'request_id' => $requestId,
            'status' => $newStatus,
            'approved' => true
        ];
    }

    /**
     * Reject a roster request
     * 
     * @param int $requestId Request ID
     * @return array Rejection result
     */
    public function rejectRequest($requestId)
    {
        $request = $this->rosterApprovalDAL->getRequestById($requestId);
        
        if (!$request) {
            throw new Exception('Request not found', 404);
        }

        $newStatus = 'Rejected by Admin';
        $this->rosterApprovalDAL->updateRequestStatus($requestId, $newStatus);

        return [
            'request_id' => $requestId,
            'status' => $newStatus,
            'rejected' => true
        ];
    }

    /**
     * Process shift change approval
     * 
     * @param array $request Request data
     */
    private function processShiftChangeApproval($request)
    {
        $time24hr = str_replace(':', '', date('Hi', strtotime($request['requested_shift'])));
        $time24hrWithSeconds = date('H:i:s', strtotime($request['requested_shift']));

        // Update agent codes table
        $this->rosterApprovalDAL->updateAgentShiftTime($request['agent_name'], $time24hrWithSeconds);

        // Update employee roster shift time
        $this->rosterApprovalDAL->updateEmployeeRosterShiftTime($request['agent_name'], $time24hr);

        // Update all day columns that don't have special values
        $rosterRecords = $this->rosterApprovalDAL->getEmployeeRosterRecords($request['agent_name']);

        foreach ($rosterRecords as $record) {
            $dayUpdates = [];
            
            for ($day = 1; $day <= 31; $day++) {
                $columnName = "day_$day";
                $currentValue = $record[$columnName];
                
                if (!in_array($currentValue, ['RDO', 'Leave', 'Convert', 'convert', 'leave'])) {
                    $dayUpdates[$columnName] = $time24hr;
                }
            }
            
            if (!empty($dayUpdates)) {
                $this->rosterApprovalDAL->updateEmployeeRosterDays(
                    $request['agent_name'],
                    $record['month'],
                    $dayUpdates
                );
            }
        }
    }

    /**
     * Process RDO change approval
     * 
     * @param array $request Request data
     */
    private function processRDOChangeApproval($request)
    {
        list($reqDay, $reqMonth) = $this->parseDayAndMonth($request['requested_rdo'], $request);
        list($curDay, $curMonth) = $this->parseDayAndMonth($request['current_rdo'], $request);

        if ($reqDay === null || $curDay === null) {
            throw new Exception('Invalid RDO format', 400);
        }

        // Get shift times
        $reqShift = $this->rosterApprovalDAL->getEmployeeShiftTime($request['agent_name'], $reqMonth);
        $curShift = ($reqMonth === $curMonth) 
            ? $reqShift 
            : $this->rosterApprovalDAL->getEmployeeShiftTime($request['agent_name'], $curMonth);

        if ($reqShift && $curShift) {
            // Update new RDO date to 'RDO'
            $this->rosterApprovalDAL->updateEmployeeRosterDay(
                $request['agent_name'],
                $reqMonth,
                $reqDay,
                'RDO'
            );

            // Update previous RDO date back to shift time
            $this->rosterApprovalDAL->updateEmployeeRosterDay(
                $request['agent_name'],
                $curMonth,
                $curDay,
                $curShift
            );
        }
    }

    /**
     * Process leave approval
     * 
     * @param array $request Request data
     */
    private function processLeaveApproval($request)
    {
        list($leaveDay, $leaveMonth) = $this->parseDayAndMonth($request['current_rdo']);

        if ($leaveDay === null) {
            throw new Exception('Invalid Leave date format', 400);
        }

        // Update the specific day column to 'Leave'
        $this->rosterApprovalDAL->updateEmployeeRosterDay(
            $request['agent_name'],
            $leaveMonth,
            $leaveDay,
            'Leave'
        );
    }

    /**
     * Parse day and month from format like "Wednesday (2/7)" or use day field from request
     * 
     * @param string $rdoString RDO string
     * @param array $request Full request data for fallback
     * @return array [day, month_name] or [null, null] if invalid
     */
    private function parseDayAndMonth($rdoString, $request = null)
    {
        // Try to parse from format "Wednesday (2/7)"
        if (preg_match('/\((\d{1,2})\/(\d{1,2})\)/', $rdoString, $matches)) {
            $day = (int)$matches[1];
            $monthNumber = (int)$matches[2];
            $monthName = date('F', mktime(0, 0, 0, $monthNumber, 1));
            return [$day, $monthName];
        }
        
        // Fallback: try to use day field from request if available
        if ($request && isset($request['day']) && $request['day'] !== null && $request['day'] !== '') {
            $day = (int)$request['day'];
            // Use created_date to determine month
            $monthName = date('F'); // Default to current month
            if (isset($request['created_date']) && $request['created_date']) {
                try {
                    $date = new \DateTime($request['created_date']);
                    $monthName = $date->format('F');
                } catch (\Exception $e) {
                    // If date parsing fails, use current month
                }
            }
            return [$day, $monthName];
        }
        
        // Fallback: try to infer date from day name and created_date
        if ($request && isset($request['created_date']) && $request['created_date']) {
            try {
                $createdDate = new \DateTime($request['created_date']);
                $monthName = $createdDate->format('F');
                $year = (int)$createdDate->format('Y');
                $month = (int)$createdDate->format('n');
                
                // Map day names to day of week (0=Sunday, 6=Saturday)
                $dayNameMap = [
                    'sunday' => 0, 'sun' => 0,
                    'monday' => 1, 'mon' => 1,
                    'tuesday' => 2, 'tue' => 2,
                    'wednesday' => 3, 'wed' => 3,
                    'thursday' => 4, 'thu' => 4,
                    'friday' => 5, 'fri' => 5,
                    'saturday' => 6, 'sat' => 6
                ];
                
                $rdoLower = strtolower(trim($rdoString));
                if (isset($dayNameMap[$rdoLower])) {
                    $targetDayOfWeek = $dayNameMap[$rdoLower];
                    
                    // Find the first occurrence of this day in the month
                    $firstDay = new \DateTime("$year-$month-01");
                    $firstDayOfWeek = (int)$firstDay->format('w');
                    $daysToAdd = ($targetDayOfWeek - $firstDayOfWeek + 7) % 7;
                    $targetDate = clone $firstDay;
                    $targetDate->modify("+$daysToAdd days");
                    
                    $day = (int)$targetDate->format('j');
                    return [$day, $monthName];
                }
            } catch (\Exception $e) {
                // If parsing fails, continue to return null
            }
        }
        
        // If RDO string is just a day name without date context, return null
        return [null, null];
    }
}

