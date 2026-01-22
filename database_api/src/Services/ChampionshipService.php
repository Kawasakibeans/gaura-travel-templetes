<?php
/**
 * Championship Service
 * Business logic for championship endpoints
 */

namespace App\Services;

use App\DAL\ChampionshipDAL;

class ChampionshipService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ChampionshipDAL();
    }

    /**
     * Get comments for a date range
     */
    public function getComments(array $params): array
    {
        $fromDate = $params['from_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $toDate = $params['to_date'] ?? date('Y-m-d');
        
        $comments = $this->dal->getComments($fromDate, $toDate);
        
        return [
            'success' => true,
            'comments' => $comments
        ];
    }

    /**
     * Post a new comment
     */
    public function postComment(array $params, int $userId, string $displayName): array
    {
        $message = trim($params['message'] ?? '');
        $parentId = isset($params['parent_id']) ? (int)$params['parent_id'] : 0;
        
        if (empty($message)) {
            throw new \Exception('Message required', 400);
        }
        
        $commentId = $this->dal->insertComment($userId, $displayName, $message, $parentId);
        
        return [
            'success' => true,
            'comment_id' => $commentId
        ];
    }
    /**
     * Get team metrics for racing championship
     */
    public function getTeamMetrics(string $metric, string $startDate, string $endDate, string $subMetric = 'all'): array
    {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('start_date must be in YYYY-MM-DD format', 400);
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new Exception('end_date must be in YYYY-MM-DD format', 400);
        }
    
        $metrics = $this->dal->getTeamMetrics($metric, $startDate, $endDate, $subMetric);
    
        return [
            'metric' => $metric,
            'sub_metric' => $subMetric,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'teams' => $metrics,
            'count' => count($metrics)
        ];
    }
    
    /**
     * Get active teams for racing championship
     */
    public function getActiveTeams(): array
    {
        $teams = $this->dal->getActiveTeams();
        
        return [
            'teams' => $teams,
            'count' => count($teams)
        ];
    }
    
    /**
     * Get agent performance by team
     */
    public function getAgentPerformanceByTeam(string $teamName, string $startDate, string $endDate): array
    {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('start_date must be in YYYY-MM-DD format', 400);
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new Exception('end_date must be in YYYY-MM-DD format', 400);
        }
    
        if (empty($teamName)) {
            throw new Exception('Team name is required', 400);
        }
    
        $agents = $this->dal->getAgentPerformanceByTeam($teamName, $startDate, $endDate);
    
        return [
            'team_name' => $teamName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'agents' => $agents,
            'count' => count($agents)
        ];
    }
}

