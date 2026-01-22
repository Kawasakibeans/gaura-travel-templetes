<?php
/**
 * ChatGPT API Service
 * Business logic for AI analysis endpoints
 */

namespace App\Services;

use App\DAL\ChatGPTAPIDAL;

class ChatGPTAPIService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ChatGPTAPIDAL();
    }

    /**
     * Get teams list
     */
    public function getTeams(array $params): array
    {
        $sourceTable = $params['source_table'] ?? 'wpk4_agent_productivity_report_June_2025';
        $teams = $this->dal->getTeams($sourceTable);
        
        return [
            'teams' => $teams
        ];
    }

    /**
     * Save AI analysis results
     */
    public function saveAIResults(array $params): array
    {
        $date = $params['date'] ?? date('Y-m-d');
        $sections = [
            'top_performing_teams' => $params['top_performing_teams'] ?? '',
            'areas_of_concern' => $params['areas_of_concern'] ?? '',
            'operational_efficiency' => $params['operational_efficiency'] ?? '',
            'business_impact' => $params['business_impact'] ?? ''
        ];
        
        $this->dal->saveAIResults($date, $sections);
        
        return [
            'success' => true,
            'message' => 'AI results saved successfully'
        ];
    }

    /**
     * Load day rows for analysis
     */
    public function loadDayRows(array $params): array
    {
        $date = $params['date'] ?? date('Y-m-d');
        $team = $params['team'] ?? null;
        $sourceTable = $params['source_table'] ?? 'wpk4_agent_productivity_report_June_2025';
        
        $rows = $this->dal->loadDayRows($date, $team, $sourceTable);
        
        return [
            'rows' => $rows,
            'count' => count($rows)
        ];
    }
}

