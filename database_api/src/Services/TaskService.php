<?php
/**
 * Task Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TaskDAL;
use Exception;

class TaskService
{
    private $taskDAL;

    public function __construct()
    {
        $this->taskDAL = new TaskDAL();
    }

    /**
     * List tasks with filters
     */
    public function listTasks($filters = [], $username = null, $isAdmin = false)
    {
        $tasks = $this->taskDAL->listTasks($filters, $username, $isAdmin);
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    }

    /**
     * Get task by ID
     */
    public function getTaskById($autoId, $username = null, $isAdmin = false)
    {
        $task = $this->taskDAL->getTaskById($autoId, $username, $isAdmin);
        
        if (!$task) {
            throw new Exception('Task not found', 404);
        }
        
        return [
            'success' => true,
            'task' => $task
        ];
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        $options = $this->taskDAL->getFilterOptions();
        
        return [
            'success' => true,
            'filter_options' => $options
        ];
    }

    /**
     * Create task
     */
    public function createTask($data, $username)
    {
        if (empty($data['category'])) {
            throw new Exception('Category is required', 400);
        }
        
        if (empty($data['task_name'])) {
            throw new Exception('Task name is required', 400);
        }
        
        $now = date('Y-m-d H:i:s');
        
        $taskData = [
            'category' => $data['category'],
            'task_name' => $data['task_name'],
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? '',
            'created_by' => $data['created_by'] ?? $username,
            'created_on' => $now
        ];
        
        $taskId = $this->taskDAL->createTask($taskData);
        
        return [
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ];
    }

    /**
     * Start task timer
     */
    public function startTimer($autoId, $username)
    {
        // Check if task exists
        $task = $this->taskDAL->getTaskById($autoId, $username, true);
        if (!$task) {
            throw new Exception('Task not found', 404);
        }
        
        // Check if timer already started
        if (!empty($task['start_time'])) {
            throw new Exception('Timer already started', 400);
        }
        
        $dateAndTime = date('Y-m-d H:i:s');
        $this->taskDAL->startTimer($autoId, $dateAndTime, $username);
        
        return [
            'success' => true,
            'message' => 'Timer started successfully',
            'start_time' => $dateAndTime
        ];
    }

    /**
     * Stop task timer
     */
    public function stopTimer($autoId, $username)
    {
        // Check if task exists
        $task = $this->taskDAL->getTaskById($autoId, $username, true);
        if (!$task) {
            throw new Exception('Task not found', 404);
        }
        
        // Get start time
        $startTime = $this->taskDAL->getTaskStartTime($autoId);
        if (empty($startTime)) {
            throw new Exception('Timer not started', 400);
        }
        
        // Check if timer already stopped
        if (!empty($task['end_time'])) {
            throw new Exception('Timer already stopped', 400);
        }
        
        $dateAndTime = date('Y-m-d H:i:s');
        
        // Calculate duration
        $datetime1 = new \DateTime($startTime);
        $datetime2 = new \DateTime($dateAndTime);
        $interval = $datetime1->diff($datetime2);
        
        // Build difference string
        $differenceParts = [];
        
        if ($interval->d > 0) {
            $differenceParts[] = $interval->d . ' days';
        }
        if ($interval->h > 0) {
            $differenceParts[] = $interval->h . ' hours';
        }
        if ($interval->i > 0) {
            $differenceParts[] = $interval->i . ' minutes';
        }
        if ($interval->s > 0) {
            $differenceParts[] = $interval->s . ' seconds';
        }
        
        $duration = implode(', ', $differenceParts);
        
        $this->taskDAL->stopTimer($autoId, $dateAndTime, $duration, $username);
        
        return [
            'success' => true,
            'message' => 'Timer stopped successfully',
            'end_time' => $dateAndTime,
            'duration' => $duration
        ];
    }
}

