<?php
/**
 * Incentive Payout Data Access Layer
 * Handles all database operations for incentive payout data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class IncentivePayoutDAL extends BaseDAL
{
    /**
     * Get incentive information (names and dates)
     */
    public function getIncentiveInfo()
    {
        $query = "SELECT incentive_name, payment_due_date, start_date, end_date 
                  FROM wpk4_incentive_data";
        
        $results = $this->query($query);
        $info = [];
        
        foreach ($results as $row) {
            $info[$row['incentive_name']] = $row;
        }
        
        return $info;
    }

    /**
     * Get agent incentive data with filters
     */
    public function getAgentIncentiveData($filters, $selectedIncentives)
    {
        $whereParts = [];
        $params = [];
        
        // Incentive name filter
        if (!empty($selectedIncentives)) {
            $placeholders = implode(',', array_fill(0, count($selectedIncentives), '?'));
            $whereParts[] = "incentive_name IN ($placeholders)";
            $params = array_merge($params, $selectedIncentives);
        } else {
            $whereParts[] = "0"; // No results if no incentives match
        }
        
        // Other filters
        if (!empty($filters['agent_name'])) {
            $whereParts[] = "agent_name = ?";
            $params[] = $filters['agent_name'];
        }
        
        if (!empty($filters['status'])) {
            $whereParts[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['sales_manager'])) {
            $whereParts[] = "sales_manager = ?";
            $params[] = $filters['sales_manager'];
        }
        
        if (!empty($filters['team_name'])) {
            $whereParts[] = "team_name = ?";
            $params[] = $filters['team_name'];
        }
        
        if (!empty($filters['search'])) {
            $whereParts[] = "(agent_name LIKE ? OR status LIKE ? OR incentive_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_agent_incentive_data $whereSQL";
        
        // Add limit/offset if provided
        if (isset($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }
        if (isset($filters['offset'])) {
            $query .= " OFFSET " . (int)$filters['offset'];
        }
        
        return $this->query($query, $params);
    }

    /**
     * Get detailed information for a specific agent
     */
    public function getAgentDetails($agentName)
    {
        // Trim whitespace and normalize the agent name
        $agentName = trim($agentName);
        
        // Try exact match first
        $query = "
            SELECT a.*, i.payment_due_date
            FROM wpk4_agent_incentive_data a
            LEFT JOIN wpk4_incentive_data i ON a.incentive_name = i.incentive_name
            WHERE a.agent_name = ?
            ORDER BY a.incentive_name
        ";
        
        $results = $this->query($query, [$agentName]);
        
        // If no results, try with trimmed comparison
        if (empty($results)) {
            $query = "
                SELECT a.*, i.payment_due_date
                FROM wpk4_agent_incentive_data a
                LEFT JOIN wpk4_incentive_data i ON a.incentive_name = i.incentive_name
                WHERE TRIM(a.agent_name) = ?
                ORDER BY a.incentive_name
            ";
            $results = $this->query($query, [$agentName]);
        }
        
        // If still no results, try normalizing spaces (handles multiple spaces)
        if (empty($results)) {
            $query = "
                SELECT a.*, i.payment_due_date
                FROM wpk4_agent_incentive_data a
                LEFT JOIN wpk4_incentive_data i ON a.incentive_name = i.incentive_name
                WHERE REPLACE(REPLACE(a.agent_name, '  ', ' '), ' ', '') = REPLACE(REPLACE(?, '  ', ' '), ' ', '')
                ORDER BY a.incentive_name
            ";
            $results = $this->query($query, [$agentName]);
        }
        
        return $results;
    }

    /**
     * Approve incentives for an agent (set status='confirm')
     */
    public function approveIncentiveByAgent($agentName, $timestamp)
    {
        $query = "UPDATE wpk4_agent_incentive_data 
                  SET status = 'confirm', last_updated = ? 
                  WHERE agent_name = ?";
        
        return $this->execute($query, [$timestamp, $agentName]);
    }

    /**
     * Approve single incentive by ID
     */
    public function approveIncentiveById($id, $timestamp)
    {
        $query = "UPDATE wpk4_agent_incentive_data 
                  SET status = 'confirm', last_updated = ? 
                  WHERE id = ?";
        
        return $this->execute($query, [$timestamp, $id]);
    }

    /**
     * Release funds for an agent
     */
    public function releaseFundsByAgent($agentName, $timestamp)
    {
        $query = "UPDATE wpk4_agent_incentive_data 
                  SET release_status = 1, released_date = ?, last_updated = ? 
                  WHERE agent_name = ?";
        
        return $this->execute($query, [$timestamp, $timestamp, $agentName]);
    }

    /**
     * Release funds for single incentive by ID
     */
    public function releaseFundsById($id, $timestamp)
    {
        $query = "UPDATE wpk4_agent_incentive_data 
                  SET release_status = 1, released_date = ?, last_updated = ? 
                  WHERE id = ?";
        
        return $this->execute($query, [$timestamp, $timestamp, $id]);
    }

    /**
     * Confirm incentive (set status='confirm')
     * Note: Table doesn't have 'confirm' field, using 'status' field instead
     */
    public function confirmIncentive($id)
    {
        $timestamp = date('Y-m-d');
        $query = "UPDATE wpk4_agent_incentive_data 
                  SET status = 'confirm', last_updated = ? 
                  WHERE id = ?";
        return $this->execute($query, [$timestamp, $id]);
    }

    /**
     * Get distinct agent names for filters
     */
    public function getDistinctAgentNames(): array
    {
        $sql = "SELECT DISTINCT agent_name FROM wpk4_agent_incentive_data ORDER BY agent_name";
        $results = $this->query($sql);
        return array_column($results, 'agent_name');
    }

    /**
     * Get distinct statuses for filters
     */
    public function getDistinctStatuses(): array
    {
        $sql = "SELECT DISTINCT status FROM wpk4_agent_incentive_data ORDER BY status";
        $results = $this->query($sql);
        return array_column($results, 'status');
    }

    /**
     * Get distinct sales managers for filters
     */
    public function getDistinctSalesManagers(): array
    {
        $sql = "SELECT DISTINCT sales_manager FROM wpk4_agent_incentive_data 
                WHERE sales_manager IS NOT NULL AND sales_manager != '' 
                ORDER BY sales_manager";
        $results = $this->query($sql);
        return array_column($results, 'sales_manager');
    }

    /**
     * Get distinct team names for filters
     */
    public function getDistinctTeamNames(): array
    {
        $sql = "SELECT DISTINCT team_name FROM wpk4_agent_incentive_data 
                WHERE team_name IS NOT NULL AND team_name != '' 
                ORDER BY team_name";
        $results = $this->query($sql);
        return array_column($results, 'team_name');
    }

    /**
     * Get distinct incentive names
     */
    public function getDistinctIncentiveNames(): array
    {
        $sql = "SELECT DISTINCT incentive_name FROM wpk4_incentive_data ORDER BY incentive_name";
        $results = $this->query($sql);
        return array_column($results, 'incentive_name');
    }
}
