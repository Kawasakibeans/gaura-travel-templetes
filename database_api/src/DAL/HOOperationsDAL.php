<?php
/**
 * HO Operations Data Access Layer
 * Handles database operations for HO operations checklists
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class HOOperationsDAL extends BaseDAL
{
    /**
     * Get all checklists
     */
    public function getAllChecklists()
    {
        $query = "SELECT * FROM wpk4_backend_ho_checklist ORDER BY id ASC";
        return $this->query($query);
    }

    /**
     * Get checklist by ID
     */
    public function getChecklistById($id)
    {
        $query = "SELECT * FROM wpk4_backend_ho_checklist WHERE id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get checklist days/tasks
     */
    public function getChecklistDays($checklistId)
    {
        $query = "SELECT * FROM wpk4_backend_ho_checklist_days 
                  WHERE ho_checklist_id = ? 
                  ORDER BY day ASC";
        
        return $this->query($query, [$checklistId]);
    }

    /**
     * Create new checklist
     */
    public function createChecklist($data)
    {
        $query = "INSERT INTO wpk4_backend_ho_checklist 
                  (checklist_name, description, created_at) 
                  VALUES (?, ?, NOW())";
        
        $params = [
            $data['checklist_name'],
            $data['description'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update checklist
     */
    public function updateChecklist($id, $data)
    {
        $setParts = [];
        $params = [];

        if (isset($data['checklist_name'])) {
            $setParts[] = "checklist_name = ?";
            $params[] = $data['checklist_name'];
        }

        if (isset($data['description'])) {
            $setParts[] = "description = ?";
            $params[] = $data['description'];
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_backend_ho_checklist SET $setSQL WHERE id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete checklist
     */
    public function deleteChecklist($id)
    {
        $query = "DELETE FROM wpk4_backend_ho_checklist WHERE id = ?";
        return $this->execute($query, [$id]);
    }

    /**
     * Delete checklist days
     */
    public function deleteChecklistDays($checklistId)
    {
        $query = "DELETE FROM wpk4_backend_ho_checklist_days WHERE ho_checklist_id = ?";
        return $this->execute($query, [$checklistId]);
    }

    /**
     * Create checklist day task
     */
    public function createChecklistDay($data)
    {
        $query = "INSERT INTO wpk4_backend_ho_checklist_days 
                  (ho_checklist_id, day, task_description, task_details) 
                  VALUES (?, ?, ?, ?)";
        
        $params = [
            $data['ho_checklist_id'],
            $data['day'],
            $data['task_description'],
            $data['task_details'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update checklist day task
     */
    public function updateChecklistDay($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = ['day', 'task_description', 'task_details'];

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
        $query = "UPDATE wpk4_backend_ho_checklist_days SET $setSQL WHERE id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete checklist day task
     */
    public function deleteChecklistDay($id)
    {
        $query = "DELETE FROM wpk4_backend_ho_checklist_days WHERE id = ?";
        return $this->execute($query, [$id]);
    }
}

