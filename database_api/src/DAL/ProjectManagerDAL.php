<?php
/**
 * Project Manager Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ProjectManagerDAL extends BaseDAL
{
    /**
     * Get all projects
     */
    public function getAllProjects()
    {
        $query = "SELECT id, title, description, status, created_at 
                  FROM wpk4_pm_projects 
                  ORDER BY id DESC";
        
        return $this->query($query);
    }

    /**
     * Get all boards
     */
    public function getAllBoards()
    {
        $query = "SELECT id, title, description, project_id, created_at 
                  FROM wpk4_pm_boards 
                  ORDER BY id DESC";
        
        return $this->query($query);
    }

    /**
     * Get time tracker for task
     */
    public function getTimeTracker($taskId)
    {
        $query = "SELECT * FROM wpk4_pm_time_tracker 
                  WHERE task_id = ? 
                  ORDER BY id DESC 
                  LIMIT 1";
        
        return $this->queryOne($query, [$taskId]);
    }

    /**
     * Get agent by WordPress username
     */
    public function getAgentByWordPressUser($username)
    {
        $query = "SELECT sales_id FROM wpk4_backend_agent_codes 
                  WHERE wordpress_user_name = ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$username]);
    }

    /**
     * Create project
     */
    public function createProject($data)
    {
        $query = "INSERT INTO wpk4_pm_projects 
                  (title, description, status, created_at) 
                  VALUES (?, ?, ?, NOW())";
        
        // Convert status string to integer: 'active' = 1, others = 0
        $status = 0;
        if (isset($data['status'])) {
            if (is_numeric($data['status'])) {
                $status = (int)$data['status'];
            } elseif (is_string($data['status']) && strtolower($data['status']) === 'active') {
                $status = 1;
            }
        } else {
            // Default to 1 (active) if not provided
            $status = 1;
        }
        
        $this->execute($query, [
            $data['title'],
            $data['description'] ?? null,
            $status
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Create board
     */
    public function createBoard($data)
    {
        // project_id is NOT NULL in database, so it's required
        if (!isset($data['project_id']) || $data['project_id'] === null) {
            throw new \Exception('project_id is required', 400);
        }
        
        $query = "INSERT INTO wpk4_pm_boards 
                  (title, description, project_id, status, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        
        // status is tinyint(3) unsigned, NOT NULL, default 1
        // Convert status string to integer: 'active' = 1, others = 0
        $status = 1; // default to 1 (active)
        if (isset($data['status'])) {
            if (is_numeric($data['status'])) {
                $status = (int)$data['status'];
            } elseif (is_string($data['status']) && strtolower($data['status']) === 'active') {
                $status = 1;
            } else {
                $status = 0;
            }
        }
        
        $this->execute($query, [
            $data['title'],
            $data['description'] ?? null,
            (int)$data['project_id'],
            $status
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Create time entry
     */
    public function createTimeEntry($data)
    {
        $query = "INSERT INTO wpk4_pm_time_tracker 
                  (task_id, start_time, end_time, duration, notes, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        $this->execute($query, [
            $data['task_id'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            $data['duration'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return $this->lastInsertId();
    }
}

