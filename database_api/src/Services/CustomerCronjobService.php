<?php
/**
 * Customer Cronjob Service - Business Logic Layer
 * Handles customer cronjob execution and logging
 */

namespace App\Services;

use App\DAL\CustomerCronjobDAL;
use Exception;

class CustomerCronjobService
{
    private $cronjobDAL;

    public function __construct()
    {
        $this->cronjobDAL = new CustomerCronjobDAL();
    }

    /**
     * Log cronjob execution step
     */
    public function logStep($logData)
    {
        $this->cronjobDAL->ensureLogTableExists();
        
        $required = ['run_id', 'step_key', 'script_path', 'started_at', 'finished_at', 'duration_ms', 'ok', 'exit_code'];
        foreach ($required as $field) {
            if (!isset($logData[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }
        
        return $this->cronjobDAL->logStep($logData);
    }

    /**
     * Get logs by run ID
     */
    public function getLogsByRunId($runId)
    {
        if (empty($runId)) {
            throw new Exception('Run ID is required', 400);
        }
        
        return $this->cronjobDAL->getLogsByRunId($runId);
    }

    /**
     * Get recent executions
     */
    public function getRecentExecutions($limit = 10)
    {
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }
        
        return $this->cronjobDAL->getRecentExecutions($limit);
    }
}

