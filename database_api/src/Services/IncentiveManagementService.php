<?php
/**
 * Incentive Management Service - Business Logic Layer
 * Handles incentive conditions and KPI data management
 */

namespace App\Services;

use App\DAL\IncentiveManagementDAL;
use Exception;

class IncentiveManagementService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new IncentiveManagementDAL();
    }

    /**
     * Get all incentive conditions
     */
    public function getAllConditions($filters = [])
    {
        $campaign = $filters['campaign'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $conditions = $this->dal->getAllConditions($campaign, $limit, $offset);
        $totalCount = $this->dal->getConditionsCount($campaign);

        return [
            'conditions' => $conditions,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get incentive condition by ID
     */
    public function getConditionById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid condition ID is required', 400);
        }

        $condition = $this->dal->getConditionById($id);

        if (!$condition) {
            throw new Exception('Condition not found', 404);
        }

        return $condition;
    }

    /**
     * Get incentive data by date and type
     */
    public function getIncentiveData($date, $type)
    {
        if (empty($date)) {
            throw new Exception('Date is required', 400);
        }

        if (empty($type)) {
            throw new Exception('Incentive type is required', 400);
        }

        $data = $this->dal->getIncentiveDataByDateAndType($date, $type);

        return [
            'date' => $date,
            'incentive_type' => $type,
            'data' => $data,
            'total_count' => count($data)
        ];
    }

    /**
     * Get team names
     */
    public function getTeamNames()
    {
        return $this->dal->getDistinctTeamNames();
    }

    /**
     * Create incentive condition
     */
    public function createCondition($data)
    {
        // Validate required fields based on actual table structure
        // incentive_title is the actual field name (not campaign_name)
        $requiredFields = ['incentive_title', 'start_date', 'end_date'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $conditionId = $this->dal->createCondition($data);

        return [
            'condition_id' => $conditionId,
            'incentive_title' => $data['incentive_title'],
            'message' => 'Incentive condition created successfully'
        ];
    }

    /**
     * Update incentive condition
     */
    public function updateCondition($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid condition ID is required', 400);
        }

        $condition = $this->dal->getConditionById($id);
        if (!$condition) {
            throw new Exception('Condition not found', 404);
        }

        $this->dal->updateCondition($id, $data);

        return [
            'condition_id' => $id,
            'message' => 'Incentive condition updated successfully'
        ];
    }

    /**
     * Delete incentive condition
     */
    public function deleteCondition($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid condition ID is required', 400);
        }

        $condition = $this->dal->getConditionById($id);
        if (!$condition) {
            throw new Exception('Condition not found', 404);
        }

        $this->dal->deleteCondition($id);

        return [
            'condition_id' => $id,
            'message' => 'Incentive condition deleted successfully'
        ];
    }
}

