<?php
/**
 * Task Data Access Layer
 * Handles database operations for task management
 */

namespace App\DAL;

use Exception;
use PDOException;

class TaskDAL extends BaseDAL
{
    /**
     * List tasks with filters
     */
    public function listTasks($filters = [], $username = null, $isAdmin = false)
    {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Permission check: non-admins can only see their own tasks
            if (!$isAdmin && $username) {
                $where[] = "created_by = :username";
                $params['username'] = $username;
            }
            
            if (!empty($filters['category'])) {
                $where[] = "category LIKE :category";
                $params['category'] = '%' . $filters['category'] . '%';
            }
            
            if (!empty($filters['task_name'])) {
                $where[] = "task_name LIKE :task_name";
                $params['task_name'] = '%' . $filters['task_name'] . '%';
            }
            
            if (!empty($filters['priority'])) {
                $where[] = "priority LIKE :priority";
                $params['priority'] = '%' . $filters['priority'] . '%';
            }
            
            if (!empty($filters['created_by'])) {
                $where[] = "created_by LIKE :created_by";
                $params['created_by'] = '%' . $filters['created_by'] . '%';
            }
            
            if (!empty($filters['task_date'])) {
                $where[] = "DATE(created_on) LIKE :task_date";
                $params['task_date'] = '%' . $filters['task_date'] . '%';
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "
                SELECT * FROM wpk4_backend_task_dashboard 
                WHERE {$whereClause}
                ORDER BY auto_id DESC
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("TaskDAL::listTasks error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get task by ID
     */
    public function getTaskById($autoId, $username = null, $isAdmin = false)
    {
        try {
            $where = ['auto_id = :auto_id'];
            $params = ['auto_id' => $autoId];
            
            // Permission check: non-admins can only see their own tasks
            if (!$isAdmin && $username) {
                $where[] = "created_by = :username";
                $params['username'] = $username;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "
                SELECT * FROM wpk4_backend_task_dashboard 
                WHERE {$whereClause}
                LIMIT 1
            ";
            
            return $this->queryOne($query, $params);
        } catch (PDOException $e) {
            error_log("TaskDAL::getTaskById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get filter options (unique values)
     */
    public function getFilterOptions()
    {
        try {
            $categories = $this->query("
                SELECT DISTINCT category 
                FROM wpk4_backend_task_dashboard 
                WHERE category IS NOT NULL AND category != ''
                ORDER BY category ASC
            ");
            
            $taskNames = $this->query("
                SELECT DISTINCT task_name 
                FROM wpk4_backend_task_dashboard 
                WHERE task_name IS NOT NULL AND task_name != ''
                ORDER BY task_name ASC
            ");
            
            $createdBy = $this->query("
                SELECT DISTINCT created_by 
                FROM wpk4_backend_task_dashboard 
                WHERE created_by IS NOT NULL AND created_by != ''
                ORDER BY created_by ASC
            ");
            
            return [
                'categories' => array_column($categories, 'category'),
                'task_names' => array_column($taskNames, 'task_name'),
                'created_by' => array_column($createdBy, 'created_by'),
                'priorities' => ['Low', 'Medium', 'High']
            ];
        } catch (PDOException $e) {
            error_log("TaskDAL::getFilterOptions error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create task
     */
    public function createTask($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_task_dashboard 
                (category, task_name, description, priority, created_by, created_on) 
                VALUES 
                (:category, :task_name, :description, :priority, :created_by, :created_on)
            ";
            
            $this->execute($query, $data);
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("TaskDAL::createTask error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Start task timer
     */
    public function startTimer($autoId, $dateAndTime, $username)
    {
        try {
            $query = "
                UPDATE wpk4_backend_task_dashboard 
                SET start_time = :date_and_time,
                    start_on = :date_and_time,
                    start_by = :username
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'date_and_time' => $dateAndTime,
                'username' => $username
            ]);
        } catch (PDOException $e) {
            error_log("TaskDAL::startTimer error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get task start time
     */
    public function getTaskStartTime($autoId)
    {
        try {
            $query = "
                SELECT start_time 
                FROM wpk4_backend_task_dashboard 
                WHERE auto_id = :auto_id
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, ['auto_id' => $autoId]);
            return $result['start_time'] ?? null;
        } catch (PDOException $e) {
            error_log("TaskDAL::getTaskStartTime error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Stop task timer
     */
    public function stopTimer($autoId, $dateAndTime, $duration, $username)
    {
        try {
            $query = "
                UPDATE wpk4_backend_task_dashboard 
                SET end_time = :date_and_time,
                    duration = :duration,
                    end_on = :date_and_time,
                    end_by = :username
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'date_and_time' => $dateAndTime,
                'duration' => $duration,
                'username' => $username
            ]);
        } catch (PDOException $e) {
            error_log("TaskDAL::stopTimer error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

