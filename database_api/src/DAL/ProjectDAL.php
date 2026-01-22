<?php
/**
 * Project Data Access Layer
 * Handles database operations for G360 Dashboard Project Management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ProjectDAL extends BaseDAL
{
    /**
     * Get all departments
     */
    public function getDepartments(): array
    {
        $sql = "SELECT dept_id, dept_name FROM wpk4_backend_pm_department WHERE is_active=1 ORDER BY dept_name";
        return $this->query($sql);
    }

    /**
     * Get all members
     */
    public function getMembers(): array
    {
        $sql = "SELECT member_id, full_name FROM wpk4_backend_pm_member WHERE is_active=1 ORDER BY full_name";
        return $this->query($sql);
    }

    /**
     * Get project months
     */
    public function getProjectMonths(): array
    {
        $sql = "
            SELECT DATE_FORMAT(due_date,'%Y-%m') AS ym,
                   DATE_FORMAT(due_date,'%M %Y') AS label,
                   MIN(due_date) AS min_d
            FROM wpk4_backend_project_management
            WHERE due_date IS NOT NULL
            GROUP BY ym, label
            ORDER BY min_d DESC
        ";
        return $this->query($sql);
    }

    /**
     * Get projects (optionally filtered by month)
     */
    public function getProjects(string $month = ''): array
    {
        $params = [];
        $where = '';
        
        if (!empty($month)) {
            $base = new \DateTime($month . '-01');
            $d1 = $base->format('Y-m-01');
            $d2 = (clone $base)->modify('first day of next month')->format('Y-m-d');
            $where = "WHERE (p.due_date >= :d1 AND p.due_date < :d2)";
            $params[':d1'] = $d1;
            $params[':d2'] = $d2;
        }
        
        $sql = "
            SELECT p.auto_id AS project_id,
                   p.project_name,
                   p.owner_member_id AS owner_id,
                   p.dept_id AS dept_id,
                   IFNULL(m.full_name,'') AS owner_name,
                   IFNULL(d.dept_name,'') AS department,
                   p.priority,
                   p.status,
                   DATE_FORMAT(p.start_date,'%d/%m/%Y') AS start_date,
                   DATE_FORMAT(p.due_date,'%d/%m/%Y') AS due_date,
                   p.progress_pct AS progress
            FROM wpk4_backend_project_management p
            LEFT JOIN wpk4_backend_pm_member m ON m.member_id=p.owner_member_id
            LEFT JOIN wpk4_backend_pm_department d ON d.dept_id=p.dept_id
            {$where}
            ORDER BY p.due_date ASC, p.auto_id DESC
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get tasks for a project
     */
    public function getTasks(int $projectId): array
    {
        $sql = "
            SELECT t.task_id, t.project_id, p.project_name, t.title, t.status, t.priority,
                   DATE_FORMAT(t.due_date,'%Y-%m-%d') AS due_date,
                   IFNULL(m.full_name,'') AS assignee
            FROM wpk4_backend_pm_task t
            JOIN wpk4_backend_project_management p ON p.auto_id=t.project_id
            LEFT JOIN wpk4_backend_pm_member m ON m.member_id=t.assignee_member_id
            WHERE t.project_id = :project_id
            ORDER BY FIELD(t.status,'Not Started','In Progress','On Hold','Review','Blocked','Done'), 
                     t.due_date IS NULL, t.due_date ASC, t.task_id DESC
        ";
        
        return $this->query($sql, [':project_id' => $projectId]);
    }

    /**
     * Get single task
     */
    public function getTask(int $taskId): ?array
    {
        $sql = "
            SELECT t.task_id, t.project_id, p.project_name, t.title, t.status, t.priority,
                   t.assignee_member_id, IFNULL(m.full_name,'') AS assignee_name,
                   DATE_FORMAT(t.due_date,'%Y-%m-%d') AS due_date, t.notes
            FROM wpk4_backend_pm_task t
            JOIN wpk4_backend_project_management p ON p.auto_id=t.project_id
            LEFT JOIN wpk4_backend_pm_member m ON m.member_id=t.assignee_member_id
            WHERE t.task_id = :task_id
        ";
        
        $result = $this->queryOne($sql, [':task_id' => $taskId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get project ID for a task
     */
    public function getProjectIdForTask(int $taskId): ?int
    {
        $sql = "SELECT project_id FROM wpk4_backend_pm_task WHERE task_id = :task_id LIMIT 1";
        $result = $this->queryOne($sql, [':task_id' => $taskId]);
        if ($result === false || $result === null) {
            return null;
        }
        return (int)($result['project_id'] ?? 0);
    }

    /**
     * Update task
     */
    public function updateTask(int $taskId, array $data): bool
    {
        $setParts = [];
        $params = [':task_id' => $taskId];
        
        // Only update fields that are provided and not empty (except for notes which can be empty)
        if (isset($data['title']) && $data['title'] !== '') {
            $setParts[] = 'title = :title';
            $params[':title'] = $data['title'];
        }
        
        if (isset($data['status']) && $data['status'] !== '') {
            $setParts[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        
        if (isset($data['priority']) && $data['priority'] !== '') {
            $setParts[] = 'priority = :priority';
            $params[':priority'] = $data['priority'];
        }
        
        if (isset($data['assignee_member_id'])) {
            $setParts[] = 'assignee_member_id = NULLIF(:assignee_member_id, 0)';
            $params[':assignee_member_id'] = (int)($data['assignee_member_id'] ?? 0);
        }
        
        if (isset($data['due_date'])) {
            $setParts[] = 'due_date = NULLIF(:due_date, \'\')';
            $params[':due_date'] = $data['due_date'] ?? '';
        }
        
        if (isset($data['notes'])) {
            $setParts[] = 'notes = :notes';
            $params[':notes'] = $data['notes'] ?? '';
        }
        
        if (empty($setParts)) {
            // No fields to update
            return false;
        }
        
        $setSQL = implode(', ', $setParts);
        $sql = "UPDATE wpk4_backend_pm_task SET $setSQL WHERE task_id = :task_id";
        
        return $this->execute($sql, $params);
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(int $taskId, string $status): bool
    {
        $completedOn = ($status === 'Done') ? 'CURDATE()' : 'NULL';
        $sql = "
            UPDATE wpk4_backend_pm_task 
            SET status = :status, 
                completed_on = CASE WHEN :status = 'Done' THEN CURDATE() ELSE NULL END 
            WHERE task_id = :task_id
        ";
        
        return $this->execute($sql, [
            ':status' => $status,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Create task
     */
    public function createTask(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_pm_task
            (project_id, title, status, assignee_member_id, due_date)
            VALUES
            (:project_id, :title, 'Not Started', NULLIF(:assignee_member_id, 0), NULLIF(:due_date, ''))
        ";
        
        $this->execute($sql, [
            ':project_id' => $data['project_id'],
            ':title' => $data['title'],
            ':assignee_member_id' => $data['assignee_member_id'] ?? 0,
            ':due_date' => $data['due_date'] ?? ''
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Get task counts for project recalculation
     */
    public function getTaskCountsForProject(int $projectId): ?array
    {
        $sql = "
            SELECT COUNT(*) AS total,
                   SUM(status='Done') AS done_cnt,
                   SUM(status='Blocked') AS blocked_cnt,
                   SUM(status='In Progress') AS ip_cnt,
                   SUM(status='On Hold') AS oh_cnt,
                   SUM(status='Review') AS rv_cnt
            FROM wpk4_backend_pm_task 
            WHERE project_id = :project_id
        ";
        
        $result = $this->queryOne($sql, [':project_id' => $projectId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Update project progress and status
     */
    public function updateProjectProgress(int $projectId, int $progressPct, string $status): bool
    {
        $sql = "
            UPDATE wpk4_backend_project_management 
            SET progress_pct = :progress_pct, status = :status 
            WHERE auto_id = :project_id
        ";
        
        return $this->execute($sql, [
            ':progress_pct' => $progressPct,
            ':status' => $status,
            ':project_id' => $projectId
        ]);
    }

    /**
     * Create project
     */
    public function createProject(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_project_management
            (project_name, dept_id, owner_member_id, status, priority, start_date, due_date, progress_pct)
            VALUES
            (:project_name, :dept_id, :owner_member_id, 'Not Started', :priority, :start_date, :due_date, 0)
        ";
        
        $this->execute($sql, [
            ':project_name' => $data['project_name'],
            ':dept_id' => $data['dept_id'] ?? 0,
            ':owner_member_id' => $data['owner_member_id'] ?? 0,
            ':priority' => $data['priority'] ?? 'Medium',
            ':start_date' => $data['start_date'] ?? null,
            ':due_date' => $data['due_date'] ?? null
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Update project
     */
    public function updateProject(int $projectId, array $data): bool
    {
        $sql = "
            UPDATE wpk4_backend_project_management 
            SET progress_pct = COALESCE(:progress_pct, progress_pct),
                status = COALESCE(:status, status),
                priority = COALESCE(:priority, priority)
            WHERE auto_id = :project_id
        ";
        
        return $this->execute($sql, [
            ':progress_pct' => $data['progress_pct'] ?? null,
            ':status' => $data['status'] ?? null,
            ':priority' => $data['priority'] ?? null,
            ':project_id' => $projectId
        ]);
    }

    /**
     * Get KPIs
     */
    public function getKPIs(): array
    {
        $kpis = [];
        
        // Overdue and blocked
        $sql1 = "SELECT SUM(status<>'Done' AND due_date<CURDATE()) AS overdue, SUM(status='Blocked') AS blocked FROM wpk4_backend_pm_task";
        $result1 = $this->queryOne($sql1);
        if ($result1 !== false) {
            $kpis = array_merge($kpis, $result1);
        }
        
        // On-time percentage
        $sql2 = "SELECT ROUND(100 * SUM(status='Done' AND completed_on IS NOT NULL AND completed_on<=due_date) / NULLIF(SUM(status='Done' AND completed_on IS NOT NULL),0),0) AS on_time_pct FROM wpk4_backend_pm_task";
        $result2 = $this->queryOne($sql2);
        if ($result2 !== false) {
            $kpis = array_merge($kpis, $result2);
        }
        
        // Average utilization
        $sql3 = "
            SELECT ROUND(AVG(ip/util_cap)*100,0) AS util_avg FROM (
                SELECT m.member_id, m.weekly_capacity AS util_cap,
                       SUM(t.status='In Progress') AS ip
                FROM wpk4_backend_pm_member m
                LEFT JOIN wpk4_backend_pm_task t ON t.assignee_member_id = m.member_id
                GROUP BY m.member_id
            ) x
        ";
        $result3 = $this->queryOne($sql3);
        if ($result3 !== false) {
            $kpis = array_merge($kpis, $result3);
        }
        
        return $kpis;
    }
}

