<?php
/**
 * Leadership Checklist Service - Business Logic Layer
 * Handles team leader daily checklist management
 */

namespace App\Services;

use App\DAL\LeadershipChecklistDAL;
use Exception;

class LeadershipChecklistService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new LeadershipChecklistDAL();
    }

    /**
     * Get checklist for team leader by date
     */
    public function getChecklistForTL($tlUsername, $date = null)
    {
        if (empty($tlUsername)) {
            throw new Exception('Team leader username is required', 400);
        }

        $checkDate = $date ?? date('Y-m-d');

        $checklists = $this->dal->getChecklistsByTLAndDate($tlUsername, $checkDate);

        return [
            'tl_username' => $tlUsername,
            'date' => $checkDate,
            'checklists' => $checklists,
            'total_count' => count($checklists)
        ];
    }

    /**
     * Get checklist tasks
     */
    public function getChecklistTasks()
    {
        $tasks = $this->dal->getAllChecklistTasks();

        return [
            'tasks' => $tasks,
            'total_count' => count($tasks)
        ];
    }

    /**
     * Get checklist entry by ID
     */
    public function getChecklistEntryById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid entry ID is required', 400);
        }

        $entry = $this->dal->getChecklistEntryById($id);
        if (!$entry) {
            throw new Exception('Checklist entry not found', 404);
        }

        return $entry;
    }

    /**
     * Create checklist entry
     */
    public function createChecklistEntry($data)
    {
        // Validate required fields
        $requiredFields = ['checklist', 'tl_username'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $entryId = $this->dal->createChecklistEntry($data);

        return [
            'entry_id' => $entryId,
            'tl_username' => $data['tl_username'],
            'checklist' => $data['checklist'],
            'message' => 'Checklist entry created successfully'
        ];
    }

    /**
     * Update checklist entry
     */
    public function updateChecklistEntry($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid entry ID is required', 400);
        }

        $entry = $this->dal->getChecklistEntryById($id);
        if (!$entry) {
            throw new Exception('Checklist entry not found', 404);
        }

        // Check if there are any updateable fields in the data
        $updateableFields = [
            'checklist', 
            'agent_username', 
            'notes', 
            'status',
            'context',
            'action',
            'result',
            'mark_as_done',
            'draft_on',
            'done_on',
            'tl_username'
        ];
        $hasUpdateableFields = false;
        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $hasUpdateableFields = true;
                break;
            }
        }

        if (!$hasUpdateableFields) {
            throw new Exception('No valid fields provided for update. Allowed fields: ' . implode(', ', $updateableFields), 400);
        }

        $result = $this->dal->updateChecklistEntry($id, $data);
        
        if ($result === false) {
            throw new Exception('Failed to update checklist entry', 500);
        }

        return [
            'entry_id' => $id,
            'message' => 'Checklist entry updated successfully'
        ];
    }

    /**
     * Delete checklist entry
     */
    public function deleteChecklistEntry($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid entry ID is required', 400);
        }

        $entry = $this->dal->getChecklistEntryById($id);
        if (!$entry) {
            throw new Exception('Checklist entry not found', 404);
        }

        $this->dal->deleteChecklistEntry($id);

        return [
            'entry_id' => $id,
            'message' => 'Checklist entry deleted successfully'
        ];
    }
}

