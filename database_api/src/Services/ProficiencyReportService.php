<?php
/**
 * Proficiency Report Service - Business Logic Layer
 * Handles comprehensive proficiency report generation
 */

namespace App\Services;

use App\DAL\ProficiencyReportDAL;
use Exception;

class ProficiencyReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ProficiencyReportDAL();
    }

    /**
     * Generate proficiency report
     */
    public function generateReport($team, $fromDate, $toDate)
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $agents = $this->dal->getAgentProficiencyData($team, $fromDate, $toDate);

        return [
            'team' => $team ?? 'ALL',
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'agents' => $agents,
            'total_count' => count($agents)
        ];
    }

    /**
     * Get available teams
     */
    public function getAvailableTeams()
    {
        return $this->dal->getActiveTeams();
    }
}

