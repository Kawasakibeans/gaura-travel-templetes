<?php
/**
 * Customer Cronjob Data Access Layer
 * Handles database operations for customer cronjob logging
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerCronjobDAL extends BaseDAL
{
    /**
     * Ensure log table exists
     */
    public function ensureLogTableExists()
    {
        $query = "
            CREATE TABLE IF NOT EXISTS wpk4_backend_customer_cron_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id CHAR(36) NOT NULL,
                step_key VARCHAR(32) NOT NULL,
                script_path VARCHAR(255) NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NOT NULL,
                duration_ms INT UNSIGNED NOT NULL,
                ok TINYINT(1) NOT NULL,
                exit_code INT NOT NULL,
                window_from DATETIME NULL,
                window_to DATETIME NULL,
                result_json LONGTEXT NULL,
                raw_output MEDIUMTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_run (run_id),
                KEY idx_step_time (step_key, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $this->execute($query);
    }

    /**
     * Log cronjob execution step
     */
    public function logStep($logData)
    {
        $query = "
            INSERT INTO wpk4_backend_customer_cron_log
            (run_id, step_key, script_path, started_at, finished_at, duration_ms, ok, exit_code, window_from, window_to, result_json, raw_output)
            VALUES (:run_id, :step_key, :script_path, :started_at, :finished_at, :duration_ms, :ok, :exit_code, :window_from, :window_to, :result_json, :raw_output)
        ";
        
        return $this->execute($query, [
            'run_id' => $logData['run_id'],
            'step_key' => $logData['step_key'],
            'script_path' => $logData['script_path'],
            'started_at' => $logData['started_at'],
            'finished_at' => $logData['finished_at'],
            'duration_ms' => $logData['duration_ms'],
            'ok' => $logData['ok'] ? 1 : 0,
            'exit_code' => $logData['exit_code'],
            'window_from' => $logData['window_from'] ?? null,
            'window_to' => $logData['window_to'] ?? null,
            'result_json' => $logData['result_json'] ?? null,
            'raw_output' => $logData['raw_output'] ?? null
        ]);
    }

    /**
     * Get cronjob execution logs by run_id
     */
    public function getLogsByRunId($runId)
    {
        $query = "
            SELECT * FROM wpk4_backend_customer_cron_log
            WHERE run_id = :run_id
            ORDER BY started_at ASC
        ";
        return $this->query($query, ['run_id' => $runId]);
    }

    /**
     * Get recent cronjob executions
     */
    public function getRecentExecutions($limit = 10)
    {
        $query = "
            SELECT run_id, MIN(started_at) as started_at, MAX(finished_at) as finished_at,
                   SUM(duration_ms) as total_duration_ms,
                   SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) as successful_steps,
                   COUNT(*) as total_steps
            FROM wpk4_backend_customer_cron_log
            GROUP BY run_id
            ORDER BY started_at DESC
            LIMIT :limit
        ";
        return $this->query($query, ['limit' => $limit]);
    }
}

