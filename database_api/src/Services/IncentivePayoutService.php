<?php
/**
 * Incentive Payout Service - Business Logic Layer
 * Handles incentive payout operations including filtering, approval, and fund release
 */

namespace App\Services;

use App\DAL\IncentivePayoutDAL;
use Exception;

class IncentivePayoutService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new IncentivePayoutDAL();
    }

    /**
     * Get payouts with filters and aggregation
     */
    public function getPayouts($filters)
    {
        // Get incentive info for date filtering
        $incentiveInfo = $this->dal->getIncentiveInfo();
        
        // Determine selected incentives based on date range
        $selectedIncentives = $this->filterIncentivesByDate(
            $incentiveInfo,
            $filters['start_date'],
            $filters['end_date']
        );
        
        if (empty($selectedIncentives)) {
            return [
                'agents' => [],
                'total_rows' => 0,
                'total_payable' => 0,
                'incentive_names' => []
            ];
        }
        
        // Get raw data
        $agents = $this->dal->getAgentIncentiveData($filters, $selectedIncentives);
        
        // Aggregate by agent
        $agentRows = $this->aggregateByAgent($agents);
        
        // Calculate totals
        $totalPayable = 0;
        foreach ($agentRows as $agent) {
            $totalPayable += (float)$agent['payable_amount'];
        }
        
        return [
            'agents' => array_values($agentRows),
            'total_rows' => count($agentRows),
            'total_payable' => number_format($totalPayable, 2, '.', ''),
            'incentive_names' => $selectedIncentives,
            'incentive_info' => $incentiveInfo
        ];
    }

    /**
     * Get detailed information for a specific agent
     */
    public function getAgentDetails($agentName)
    {
        $details = $this->dal->getAgentDetails($agentName);
        
        if (empty($details)) {
            throw new Exception('Agent not found', 404);
        }
        
        return $details;
    }

    /**
     * Approve incentives for multiple agents (bulk)
     */
    public function approveBulkIncentives($agents)
    {
        $successCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($agents as $agentName) {
            if ($this->dal->approveIncentiveByAgent($agentName, $now)) {
                $successCount++;
            }
        }
        
        return [
            'updated_agents' => $successCount,
            'last_updated' => $now
        ];
    }

    /**
     * Approve single incentive by ID
     */
    public function approveIncentive($id)
    {
        if ($id <= 0) {
            throw new Exception('Invalid ID', 400);
        }
        
        $now = date('Y-m-d H:i:s');
        $success = $this->dal->approveIncentiveById($id, $now);
        
        if (!$success) {
            throw new Exception('Failed to approve incentive', 500);
        }
        
        return [
            'id' => $id,
            'last_updated' => $now
        ];
    }

    /**
     * Release funds for multiple agents (bulk)
     */
    public function releaseBulkFunds($agents)
    {
        $successCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($agents as $agentName) {
            if ($this->dal->releaseFundsByAgent($agentName, $now)) {
                $successCount++;
            }
        }
        
        return [
            'updated_agents' => $successCount,
            'released_date' => $now
        ];
    }

    /**
     * Release funds for single incentive by ID
     */
    public function releaseFunds($id)
    {
        if ($id <= 0) {
            throw new Exception('Invalid ID', 400);
        }
        
        $now = date('Y-m-d H:i:s');
        $success = $this->dal->releaseFundsById($id, $now);
        
        if (!$success) {
            throw new Exception('Failed to release funds', 500);
        }
        
        return [
            'id' => $id,
            'released_date' => $now
        ];
    }

    // Private helper methods
    
    /**
     * Filter incentives by date range
     */
    private function filterIncentivesByDate($incentiveInfo, $startDate, $endDate)
    {
        $selected = [];
        
        if (empty($startDate) || empty($endDate)) {
            // Default to current month
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }
        
        foreach ($incentiveInfo as $name => $info) {
            $dueDate = $info['payment_due_date'] ?? '';
            if ($dueDate >= $startDate && $dueDate <= $endDate) {
                $selected[] = $name;
            }
        }
        
        return $selected;
    }

    /**
     * Aggregate agent incentive data by agent name
     */
    private function aggregateByAgent($agents)
    {
        $agentRows = [];
        
        foreach ($agents as $row) {
            $agentName = $row['agent_name'];
            
            if (!isset($agentRows[$agentName])) {
                $agentRows[$agentName] = [
                    'agent_name' => $agentName,
                    'emp_id' => $row['emp_id'] ?? $row['employee_id'] ?? '',
                    'sales_manager' => $row['sales_manager'] ?? '',
                    'team_name' => $row['team_name'] ?? '',
                    'payable_amount' => 0.0,
                    'incentives' => [],
                    'statuses' => [],
                    'release_flags' => [],
                    'last_updated' => ''
                ];
            }
            
            // Sum amounts
            $agentRows[$agentName]['payable_amount'] += (float)($row['payable_amount'] ?? 0);
            
            // Per-incentive amounts
            $incentiveName = $row['incentive_name'];
            if (!isset($agentRows[$agentName]['incentives'][$incentiveName])) {
                $agentRows[$agentName]['incentives'][$incentiveName] = 0.0;
            }
            $agentRows[$agentName]['incentives'][$incentiveName] += (float)($row['payable_amount'] ?? 0);
            
            // Track statuses
            $agentRows[$agentName]['statuses'][$incentiveName] = $row['status'] ?? '';
            
            // Track release flags
            $agentRows[$agentName]['release_flags'][] = (int)($row['release_status'] ?? 0);
            
            // Track latest update
            $newLast = $row['last_updated'] ?? '';
            if ($newLast && (!$agentRows[$agentName]['last_updated'] || 
                strtotime($newLast) > strtotime($agentRows[$agentName]['last_updated']))) {
                $agentRows[$agentName]['last_updated'] = $newLast;
            }
        }
        
        // Calculate display status
        foreach ($agentRows as &$agent) {
            $statuses = array_map('strtolower', $agent['statuses']);
            $allReleased = !empty($agent['release_flags']) && 
                          array_sum($agent['release_flags']) === count($agent['release_flags']);
            
            if (in_array('pending', $statuses)) {
                $agent['status'] = 'pending';
            } elseif ($allReleased) {
                $agent['status'] = 'released';
            } elseif (in_array('confirm', $statuses)) {
                $agent['status'] = 'confirm';
            } else {
                $agent['status'] = 'approved';
            }
            
            $agent['release_status'] = (int)$allReleased;
            $agent['payable_amount'] = number_format($agent['payable_amount'], 2, '.', '');
        }
        
        return $agentRows;
    }

    /**
     * Confirm incentive (set confirm=1)
     */
    public function confirmIncentive($id)
    {
        if ($id <= 0) {
            throw new Exception('Invalid ID', 400);
        }
        
        $success = $this->dal->confirmIncentive($id);
        
        if (!$success) {
            throw new Exception('Failed to confirm incentive', 500);
        }
        
        return [
            'id' => $id,
            'confirmed' => true
        ];
    }

    /**
     * Get filter options (agent names, statuses, sales managers, team names, incentive names)
     */
    public function getFilterOptions()
    {
        return [
            'agent_names' => $this->dal->getDistinctAgentNames(),
            'statuses' => $this->dal->getDistinctStatuses(),
            'sales_managers' => $this->dal->getDistinctSalesManagers(),
            'team_names' => $this->dal->getDistinctTeamNames(),
            'incentive_names' => $this->dal->getDistinctIncentiveNames()
        ];
    }
}

