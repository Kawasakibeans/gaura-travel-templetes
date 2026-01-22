<?php
/**
 * Leadership Checklist Data Access Layer
 * Handles database operations for leadership checklists
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class LeadershipChecklistDAL extends BaseDAL
{
    /**
     * Get checklists by team leader and date
     */
    public function getChecklistsByTLAndDate($tlUsername, $date)
    {
        $query = "SELECT * FROM wpk4_backend_agent_leadership_checklist 
                  WHERE tl_username = ? 
                    AND DATE(added_on) = ? 
                  ORDER BY auto_id ASC";
        
        return $this->query($query, [$tlUsername, $date]);
    }

    /**
     * Get all checklist tasks
     */
    public function getAllChecklistTasks()
    {
        $query = "SELECT * FROM wpk4_backend_agent_leadership_checklist_tasks 
                  ORDER BY auto_id ASC";
        
        return $this->query($query);
    }

    /**
     * Get checklist task by ID
     */
    public function getChecklistTaskById($id)
    {
        $query = "SELECT * FROM wpk4_backend_agent_leadership_checklist_tasks 
                  WHERE auto_id = ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get checklist entry by ID
     */
    public function getChecklistEntryById($id)
    {
        $query = "SELECT * FROM wpk4_backend_agent_leadership_checklist 
                  WHERE auto_id = ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$id]);
    }

    /**
     * Create checklist entry
     */
    public function createChecklistEntry($data)
    {
        // Based on actual table structure:
        // Required: checklist, tl_username, agent_username, context (all NOT NULL)
        // Optional: mark_as_done, draft_on, done_on, action, result
        $query = "INSERT INTO wpk4_backend_agent_leadership_checklist 
                  (checklist, tl_username, agent_username, context, added_on, 
                   mark_as_done, draft_on, done_on, action, result)
                  VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
        
        $params = [
            $data['checklist'],
            $data['tl_username'],
            $data['agent_username'],
            $data['context'],
            $data['mark_as_done'] ?? null,
            $data['draft_on'] ?? null,
            $data['done_on'] ?? null,
            $data['action'] ?? null,
            $data['result'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update checklist entry
     */
    public function updateChecklistEntry($id, $data)
    {
        $setParts = [];
        $params = [];

        // All updateable fields based on actual database structure
        // Removed 'notes' and 'status' (they don't exist in the table)
        $updateableFields = [
            'checklist', 
            'agent_username', 
            'context',
            'action',
            'result',
            'mark_as_done',
            'draft_on',
            'done_on',
            'tl_username'
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
        $query = "UPDATE wpk4_backend_agent_leadership_checklist SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete checklist entry
     */
    public function deleteChecklistEntry($id)
    {
        $query = "DELETE FROM wpk4_backend_agent_leadership_checklist WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }

    /**
     * Get agents by WordPress username
     */
    public function getAgentsByWordPressUser($wordpressUsername)
    {
        $query = "SELECT * FROM wpk4_backend_agent_codes 
                  WHERE wordpress_user_name = ?";
        
        return $this->query($query, [$wordpressUsername]);
    }

    /**
     * Get agent by sales ID
     */
    public function getAgentBySalesId($salesId)
    {
        $query = "SELECT * FROM wpk4_backend_agent_codes 
                  WHERE sales_id = ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$salesId]);
    }
}

