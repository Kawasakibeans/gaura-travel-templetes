<?php
/**
 * Project Manager Service - Business Logic Layer
 * Handles project management, boards, tasks, and time tracking
 */

namespace App\Services;

use App\DAL\ProjectManagerDAL;
use Exception;

class ProjectManagerService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ProjectManagerDAL();
    }

    /**
     * Get all projects
     */
    public function getAllProjects()
    {
        $projects = $this->dal->getAllProjects();
        
        return [
            'projects' => $projects,
            'total_count' => count($projects)
        ];
    }

    /**
     * Get all boards
     */
    public function getAllBoards()
    {
        $boards = $this->dal->getAllBoards();
        
        return [
            'boards' => $boards,
            'total_count' => count($boards)
        ];
    }

    /**
     * Get time tracker for task
     */
    public function getTimeTracker($taskId)
    {
        if (empty($taskId) || !is_numeric($taskId)) {
            throw new Exception('Valid task ID is required', 400);
        }

        $tracker = $this->dal->getTimeTracker($taskId);

        return [
            'task_id' => $taskId,
            'tracker' => $tracker
        ];
    }

    /**
     * Get agent by WordPress username
     */
    public function getAgentByWordPressUser($username)
    {
        if (empty($username)) {
            throw new Exception('WordPress username is required', 400);
        }

        $agent = $this->dal->getAgentByWordPressUser($username);

        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }

        return $agent;
    }

    /**
     * Create project
     */
    public function createProject($data)
    {
        if (empty($data['title'])) {
            throw new Exception('Project title is required', 400);
        }

        $projectId = $this->dal->createProject($data);

        return [
            'project_id' => $projectId,
            'title' => $data['title'],
            'message' => 'Project created successfully'
        ];
    }

    /**
     * Create board
     */
    public function createBoard($data)
    {
        if (empty($data['title'])) {
            throw new Exception('Board title is required', 400);
        }

        $boardId = $this->dal->createBoard($data);

        return [
            'board_id' => $boardId,
            'title' => $data['title'],
            'message' => 'Board created successfully'
        ];
    }

    /**
     * Create time tracker entry
     */
    public function createTimeEntry($data)
    {
        $requiredFields = ['task_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $entryId = $this->dal->createTimeEntry($data);

        return [
            'entry_id' => $entryId,
            'task_id' => $data['task_id'],
            'message' => 'Time entry created successfully'
        ];
    }
}

