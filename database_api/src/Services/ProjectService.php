<?php
/**
 * Project Service
 * Business logic for G360 Dashboard Project Management endpoints
 */

namespace App\Services;

use App\DAL\ProjectDAL;

class ProjectService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ProjectDAL();
    }

    /**
     * Recalculate project progress and status
     */
    private function recalculateProject(int $projectId): array
    {
        $counts = $this->dal->getTaskCountsForProject($projectId);
        
        if (!$counts) {
            return ['project_id' => $projectId, 'progress_pct' => 0, 'status' => 'Not Started', 'total_tasks' => 0, 'done' => 0];
        }
        
        $total = (int)($counts['total'] ?? 0);
        $done = (int)($counts['done_cnt'] ?? 0);
        $blocked = (int)($counts['blocked_cnt'] ?? 0);
        $ip = (int)($counts['ip_cnt'] ?? 0);
        $oh = (int)($counts['oh_cnt'] ?? 0);
        $rv = (int)($counts['rv_cnt'] ?? 0);
        
        $progress = $total > 0 ? (int)round(100 * $done / $total) : 0;
        
        if ($total > 0 && $done === $total) {
            $status = 'Done';
        } elseif ($blocked > 0) {
            $status = 'Blocked';
        } elseif ($ip > 0 || $oh > 0 || $rv > 0) {
            $status = 'In Progress';
        } else {
            $status = 'Not Started';
        }
        
        $this->dal->updateProjectProgress($projectId, $progress, $status);
        
        return [
            'project_id' => $projectId,
            'progress_pct' => $progress,
            'status' => $status,
            'total_tasks' => $total,
            'done' => $done
        ];
    }

    /**
     * Get departments
     */
    public function getDepartments(): array
    {
        $departments = $this->dal->getDepartments();
        return [
            'departments' => $departments,
            'count' => count($departments)
        ];
    }

    /**
     * Get members
     */
    public function getMembers(): array
    {
        $members = $this->dal->getMembers();
        return [
            'members' => $members,
            'count' => count($members)
        ];
    }

    /**
     * Get project months
     */
    public function getProjectMonths(): array
    {
        $months = $this->dal->getProjectMonths();
        return [
            'months' => $months,
            'count' => count($months)
        ];
    }

    /**
     * Get projects
     */
    public function getProjects(array $params): array
    {
        $month = $params['month'] ?? '';
        $projects = $this->dal->getProjects($month);
        return [
            'projects' => $projects,
            'count' => count($projects)
        ];
    }

    /**
     * Get tasks
     */
    public function getTasks(array $params): array
    {
        $projectId = (int)($params['project_id'] ?? 0);
        
        if (!$projectId) {
            return ['tasks' => [], 'count' => 0];
        }
        
        $tasks = $this->dal->getTasks($projectId);
        return [
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    }

    /**
     * Get single task
     */
    public function getTask(array $params): array
    {
        $taskId = (int)($params['id'] ?? 0);
        
        if (!$taskId) {
            throw new \Exception('Task ID is required', 400);
        }
        
        $task = $this->dal->getTask($taskId);
        
        if (!$task) {
            throw new \Exception('Task not found', 404);
        }
        
        return $task;
    }

    /**
     * Update task
     */
    public function updateTask(array $params): array
    {
        $taskId = (int)($params['task_id'] ?? 0);
        
        if (!$taskId) {
            throw new \Exception('task_id is required', 400);
        }
        
        $data = [
            'title' => trim($params['title'] ?? ''),
            'status' => $params['status'] ?? '',
            'priority' => $params['priority'] ?? '',
            'assignee_member_id' => (int)($params['assignee_member_id'] ?? 0),
            'due_date' => $params['due_date'] ?? '',
            'notes' => $params['notes'] ?? ''
        ];
        
        $this->dal->updateTask($taskId, $data);
        
        // Recalculate project (wrap in try-catch to prevent failures from blocking task update)
        $projectId = null;
        $project = null;
        try {
            $projectId = $this->dal->getProjectIdForTask($taskId);
            if ($projectId) {
                $project = $this->recalculateProject($projectId);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the task update
            error_log("ProjectService::updateTask - Failed to recalculate project: " . $e->getMessage());
        }
        
        return [
            'updated' => true,
            'project_id' => $projectId,
            'project' => $project
        ];
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(array $params): array
    {
        $taskId = (int)($params['task_id'] ?? 0);
        $status = $params['status'] ?? '';
        
        $validStatuses = ['Not Started', 'In Progress', 'On Hold', 'Review', 'Blocked', 'Done'];
        
        if (!$taskId || !in_array($status, $validStatuses, true)) {
            throw new \Exception('Invalid task_id or status', 400);
        }
        
        $this->dal->updateTaskStatus($taskId, $status);
        
        // Recalculate project (wrap in try-catch to prevent failures from blocking task update)
        $projectId = null;
        $project = null;
        try {
            $projectId = $this->dal->getProjectIdForTask($taskId);
            if ($projectId) {
                $project = $this->recalculateProject($projectId);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the task update
            error_log("ProjectService::updateTaskStatus - Failed to recalculate project: " . $e->getMessage());
        }
        
        return [
            'updated' => true,
            'project' => $project
        ];
    }

    /**
     * Create task
     */
    public function createTask(array $params): array
    {
        $projectId = (int)($params['project_id'] ?? 0);
        $title = trim($params['title'] ?? '');
        
        if (!$projectId || $title === '') {
            throw new \Exception('project_id and title are required', 400);
        }
        
        $data = [
            'project_id' => $projectId,
            'title' => $title,
            'assignee_member_id' => (int)($params['assignee_member_id'] ?? 0),
            'due_date' => $params['due_date'] ?? ''
        ];
        
        $taskId = $this->dal->createTask($data);
        
        // Recalculate project
        $project = $this->recalculateProject($projectId);
        
        return [
            'task_id' => $taskId,
            'project' => $project
        ];
    }

    /**
     * Create project
     */
    public function createProject(array $params): array
    {
        $projectName = trim($params['project_name'] ?? '');
        
        if ($projectName === '') {
            throw new \Exception('project_name is required', 400);
        }
        
        $data = [
            'project_name' => $projectName,
            'dept_id' => (int)($params['dept_id'] ?? 0),
            'owner_member_id' => (int)($params['owner_member_id'] ?? 0),
            'priority' => $params['priority'] ?? 'Medium',
            'start_date' => $params['start_date'] ?? null,
            'due_date' => $params['due_date'] ?? null
        ];
        
        $projectId = $this->dal->createProject($data);
        
        return [
            'project_id' => $projectId
        ];
    }

    /**
     * Update project
     */
    public function updateProject(array $params): array
    {
        $projectId = (int)($params['project_id'] ?? 0);
        
        if (!$projectId) {
            throw new \Exception('project_id is required', 400);
        }
        
        $data = [
            'progress_pct' => isset($params['progress_pct']) ? (int)$params['progress_pct'] : null,
            'status' => $params['status'] ?? null,
            'priority' => $params['priority'] ?? null
        ];
        
        $updated = $this->dal->updateProject($projectId, $data);
        
        return [
            'updated' => $updated
        ];
    }

    /**
     * Get KPIs
     */
    public function getKPIs(): array
    {
        return $this->dal->getKPIs();
    }
}

