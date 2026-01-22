<?php
/**
 * Customer AI Summarize Data Access Layer
 * Handles database operations for AI summary persistence
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerAISummarizeDAL extends BaseDAL
{
    /**
     * Ensure AI summaries table exists
     */
    public function ensureTable(string $tableName = 'wpk4_ai_customer_summaries'): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `model` VARCHAR(64) NOT NULL,
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `prev_start` DATE NULL,
                `prev_end` DATE NULL,
                `payload_json` LONGTEXT NULL,
                `summary_text` MEDIUMTEXT NULL,
                `prompt_tokens` INT NULL,
                `completion_tokens` INT NULL,
                `total_tokens` INT NULL,
                `request_ms` INT NULL,
                `avg_logprob` FLOAT NULL,
                `sum_logprob` FLOAT NULL,
                `summary_hash` CHAR(64) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_dates` (`start_date`,`end_date`),
                UNIQUE KEY `uniq_hash` (`summary_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ";
        
        $this->execute($sql);
    }

    /**
     * Insert AI summary
     */
    public function insertSummary(array $data, string $tableName = 'wpk4_ai_customer_summaries'): int
    {
        $this->ensureTable($tableName);
        
        $sql = "
            INSERT IGNORE INTO `{$tableName}`
            (`model`,`start_date`,`end_date`,`prev_start`,`prev_end`,
             `payload_json`,`summary_text`,
             `prompt_tokens`,`completion_tokens`,`total_tokens`,`request_ms`,
             `avg_logprob`,`sum_logprob`,`summary_hash`)
            VALUES (:model, :start_date, :end_date, :prev_start, :prev_end,
                    :payload_json, :summary_text,
                    :prompt_tokens, :completion_tokens, :total_tokens, :request_ms,
                    :avg_logprob, :sum_logprob, :summary_hash)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':model' => $data['model'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':prev_start' => $data['prev_start'],
            ':prev_end' => $data['prev_end'],
            ':payload_json' => $data['payload_json'],
            ':summary_text' => $data['summary_text'],
            ':prompt_tokens' => $data['prompt_tokens'],
            ':completion_tokens' => $data['completion_tokens'],
            ':total_tokens' => $data['total_tokens'],
            ':request_ms' => $data['request_ms'],
            ':avg_logprob' => $data['avg_logprob'],
            ':sum_logprob' => $data['sum_logprob'],
            ':summary_hash' => $data['summary_hash']
        ]);
        
        return $this->lastInsertId();
    }
}

