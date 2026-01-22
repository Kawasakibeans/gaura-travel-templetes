<?php
/**
 * ChatGPT API Data Access Layer
 * Handles database operations for AI analysis results
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ChatGPTAPIDAL extends BaseDAL
{
    /**
     * Ensure AI results table exists
     */
    public function ensureAIResultsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `ai_analysis_results` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `top_teams` TEXT NULL,
                `concern_areas` TEXT NULL,
                `efficiency` TEXT NULL,
                `impact` TEXT NULL,
                `record_date` DATE NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `u_record_date` (`record_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $this->execute($sql);
    }

    /**
     * Save AI analysis results
     */
    public function saveAIResults(string $date, array $sections): void
    {
        $this->ensureAIResultsTable();
        
        $sql = "
            INSERT INTO `ai_analysis_results`
              (top_teams, concern_areas, efficiency, impact, record_date)
            VALUES (:tt, :ca, :ef, :im, :d)
            ON DUPLICATE KEY UPDATE
              top_teams=VALUES(top_teams),
              concern_areas=VALUES(concern_areas),
              efficiency=VALUES(efficiency),
              impact=VALUES(impact)
        ";
        
        $this->execute($sql, [
            ':tt' => (string)($sections['top_performing_teams'] ?? ''),
            ':ca' => (string)($sections['areas_of_concern'] ?? ''),
            ':ef' => (string)($sections['operational_efficiency'] ?? ''),
            ':im' => (string)($sections['business_impact'] ?? ''),
            ':d' => $date
        ]);
    }

    /**
     * Load day rows from source table
     */
    public function loadDayRows(string $date, ?string $team = null, string $sourceTable = 'wpk4_agent_productivity_report_June_2025'): array
    {
        $sql = "
            SELECT * FROM `{$sourceTable}`
            WHERE `Date` = :d
              AND (SM_name IS NULL OR TRIM(SM_name) <> 'Sales Manager')
        ";
        
        $params = [':d' => $date];
        
        $isAllTeams = ($team !== null && preg_match('/^\s*all\s*teams\s*$/i', $team));
        if ($team !== null && $team !== '' && !$isAllTeams) {
            $sql .= " AND TRIM(`Team_name`) = :team";
            $params[':team'] = trim($team);
        }
        
        $sql .= " ORDER BY `Team_name`, `Name`";
        
        return $this->query($sql, $params);
    }

    /**
     * Get distinct teams from source table
     */
    public function getTeams(string $sourceTable = 'wpk4_agent_productivity_report_June_2025'): array
    {
        $sql = "
            SELECT DISTINCT `Team_name` 
            FROM `{$sourceTable}` 
            WHERE `Team_name` IS NOT NULL 
              AND TRIM(`Team_name`) <> '' 
            ORDER BY `Team_name`
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'Team_name');
    }
}

