<?php
/**
 * Incentive Management Data Access Layer
 * Handles database operations for incentive conditions and data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class IncentiveManagementDAL extends BaseDAL
{
    /**
     * Get all incentive conditions
     */
    public function getAllConditions($campaign = null, $limit = 100, $offset = 0)
    {
        $whereParts = [];
        $params = [];

        if ($campaign) {
            $whereParts[] = "incentive_title = ?";
            $params[] = $campaign;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_agent_data_incentive_conditions 
                  $whereSQL 
                  ORDER BY start_date DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get conditions count
     */
    public function getConditionsCount($campaign = null)
    {
        $whereParts = [];
        $params = [];

        if ($campaign) {
            $whereParts[] = "incentive_title = ?";
            $params[] = $campaign;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_agent_data_incentive_conditions $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get condition by ID
     */
    public function getConditionById($id)
    {
        $query = "SELECT * FROM wpk4_agent_data_incentive_conditions WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get incentive data by date and type
     */
    public function getIncentiveDataByDateAndType($date, $type)
    {
        $query = "SELECT * FROM wpk4_agent_data_incentive_data 
                  WHERE date = ? AND incentive_title = ?";
        
        return $this->query($query, [$date, $type]);
    }

    /**
     * Get distinct team names
     */
    public function getDistinctTeamNames()
    {
        $query = "SELECT DISTINCT team_name 
                  FROM wpk4_backend_agent_codes 
                  WHERE team_name IS NOT NULL AND team_name != '' 
                  ORDER BY team_name ASC";
        
        $results = $this->query($query);
        return array_column($results, 'team_name');
    }

    /**
     * Create incentive condition
     */
    public function createCondition($data)
    {
        // Based on actual table structure:
        // Required: incentive_title, start_date, end_date
        // Optional: team_or_agent, type, criteria_note, selection_criteria, conditions, condition_value, criteria_value
        $fields = ['incentive_title', 'start_date', 'end_date'];
        $placeholders = ['?', '?', '?'];
        $params = [
            $data['incentive_title'],
            $data['start_date'],
            $data['end_date']
        ];

        // Add optional fields if provided
        $optionalFields = [
            'team_or_agent',
            'type',
            'criteria_note',
            'selection_criteria',
            'conditions',
            'condition_value',
            'criteria_value'
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $fields[] = $field;
                $placeholders[] = '?';
                $params[] = $data[$field];
            }
        }

        $query = "INSERT INTO wpk4_agent_data_incentive_conditions 
                  (" . implode(', ', $fields) . ")
                  VALUES (" . implode(', ', $placeholders) . ")";

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update incentive condition
     */
    public function updateCondition($id, $data)
    {
        $setParts = [];
        $params = [];

        // Based on actual table structure
        $updateableFields = [
            'incentive_title',
            'start_date',
            'end_date',
            'team_or_agent',
            'type',
            'criteria_note',
            'selection_criteria',
            'conditions',
            'condition_value',
            'criteria_value'
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

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_agent_data_incentive_conditions SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete incentive condition
     */
    public function deleteCondition($id)
    {
        $query = "DELETE FROM wpk4_agent_data_incentive_conditions WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }
}

