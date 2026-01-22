<?php
/**
 * HO Operations Service - Business Logic Layer
 * Handles Head Office operations checklist management
 */

namespace App\Services;

use App\DAL\HOOperationsDAL;
use Exception;

class HOOperationsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new HOOperationsDAL();
    }

    /**
     * Get all checklists
     */
    public function getAllChecklists()
    {
        $checklists = $this->dal->getAllChecklists();

        // Enrich each checklist with daily tasks
        foreach ($checklists as &$checklist) {
            $checklistId = $checklist['id'];
            $checklist['daily_tasks'] = $this->dal->getChecklistDays($checklistId);
        }

        return [
            'checklists' => $checklists,
            'total_count' => count($checklists)
        ];
    }

    /**
     * Get checklist by ID
     */
    public function getChecklistById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid checklist ID is required', 400);
        }

        $checklist = $this->dal->getChecklistById($id);

        if (!$checklist) {
            throw new Exception('Checklist not found', 404);
        }

        // Get daily tasks
        $checklist['daily_tasks'] = $this->dal->getChecklistDays($id);

        return $checklist;
    }

    /**
     * Create new checklist
     */
    public function createChecklist($data)
    {
        // Validate required fields
        if (empty($data['checklist_name'])) {
            throw new Exception('Checklist name is required', 400);
        }

        $checklistId = $this->dal->createChecklist($data);

        return [
            'checklist_id' => $checklistId,
            'checklist_name' => $data['checklist_name'],
            'message' => 'Checklist created successfully'
        ];
    }

    /**
     * Update checklist
     */
    public function updateChecklist($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid checklist ID is required', 400);
        }

        $checklist = $this->dal->getChecklistById($id);
        if (!$checklist) {
            throw new Exception('Checklist not found', 404);
        }

        $this->dal->updateChecklist($id, $data);

        return [
            'checklist_id' => $id,
            'message' => 'Checklist updated successfully'
        ];
    }

    /**
     * Delete checklist
     */
    public function deleteChecklist($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid checklist ID is required', 400);
        }

        $checklist = $this->dal->getChecklistById($id);
        if (!$checklist) {
            throw new Exception('Checklist not found', 404);
        }

        // Delete associated daily tasks first
        $this->dal->deleteChecklistDays($id);
        
        // Delete checklist
        $this->dal->deleteChecklist($id);

        return [
            'checklist_id' => $id,
            'message' => 'Checklist and associated tasks deleted successfully'
        ];
    }

    /**
     * Get checklist days/tasks
     */
    public function getChecklistDays($checklistId)
    {
        if (empty($checklistId) || !is_numeric($checklistId)) {
            throw new Exception('Valid checklist ID is required', 400);
        }

        $tasks = $this->dal->getChecklistDays($checklistId);

        return [
            'checklist_id' => $checklistId,
            'tasks' => $tasks,
            'total_count' => count($tasks)
        ];
    }

    /**
     * Create checklist day task
     */
    public function createChecklistDay($data)
    {
        // Validate required fields
        $requiredFields = ['ho_checklist_id', 'day', 'task_description'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $taskId = $this->dal->createChecklistDay($data);

        return [
            'task_id' => $taskId,
            'checklist_id' => $data['ho_checklist_id'],
            'day' => $data['day'],
            'message' => 'Task created successfully'
        ];
    }

    /**
     * Update checklist day task
     */
    public function updateChecklistDay($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid task ID is required', 400);
        }

        $this->dal->updateChecklistDay($id, $data);

        return [
            'task_id' => $id,
            'message' => 'Task updated successfully'
        ];
    }

    /**
     * Delete checklist day task
     */
    public function deleteChecklistDay($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid task ID is required', 400);
        }

        $this->dal->deleteChecklistDay($id);

        return [
            'task_id' => $id,
            'message' => 'Task deleted successfully'
        ];
    }
}

