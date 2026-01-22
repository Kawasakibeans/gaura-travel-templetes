<?php
/**
 * Agent Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AgentDAL;
use Exception;

class AgentService
{
    private $agentDAL;

    public function __construct()
    {
        $this->agentDAL = new AgentDAL();
    }

    /**
     * Search agents by name
     */
    public function searchAgents($term)
    {
        if (empty($term)) {
            return [];
        }

        return $this->agentDAL->searchAgentsByName($term);
    }

    /**
     * Get team performance data
     */
    public function getTeamPerformance($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getTeamPerformance($fromDate, $toDate, $team);
        
        // Format results
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'team' => $row['team_name'],
                'GTIB' => (int)$row['gtib'],
                'PAX' => (int)$row['pax'],
                'FIT' => (int)$row['fit'],
                'PIF' => (int)$row['pif'],
                'GDEALS' => (int)$row['gdeals'],
                'Conversion' => round((float)$row['conversion'], 4),
                'FCS' => round((float)$row['fcs'], 4),
                'AHT' => gmdate("H:i:s", (int)$row['AHT'])
            ];
        }
        
        return $formatted;
    }

    /**
     * Get agent-level performance data
     */
    public function getAgentPerformance($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getAgentPerformance($fromDate, $toDate, $team);
        
        // Format results
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'team' => $row['team_name'],
                'agent_name' => $row['agent_name'],
                'role' => $row['role'],
                'GTIB' => (int)$row['gtib'],
                'PAX' => (int)$row['pax'],
                'Conversion' => round((float)$row['conversion'], 4),
                'FCS' => round((float)$row['fcs'], 4),
                'AHT' => gmdate("H:i:s", (int)$row['AHT'])
            ];
        }
        
        return $formatted;
    }

    /**
     * Get GTMD (total calls) by team
     */
    public function getGTMDByTeam($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getGTMDByTeam($fromDate, $toDate, $team);
        
        // Format results
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'team_name' => $row['team_name'],
                'gtmd' => (int)$row['gtmd']
            ];
        }
        
        return $formatted;
    }

    /**
     * Get team trends (last 7 days)
     */
    public function getTeamTrends($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getTeamTrends($fromDate, $toDate, $team);
        
        // Format results - group by team
        $formatted = [];
        foreach ($results as $row) {
            $teamName = $row['team_name'];
            if (!isset($formatted[$teamName])) {
                $formatted[$teamName] = [
                    'team_name' => $teamName,
                    'labels' => [],
                    'gtib' => [],
                    'pax' => []
                ];
            }
            $formatted[$teamName]['labels'][] = $row['day'];
            $formatted[$teamName]['gtib'][] = (int)$row['gtib'];
            $formatted[$teamName]['pax'][] = (int)$row['pax'];
        }
        
        return array_values($formatted);
    }

    /**
     * Get latest booking
     */
    public function getLatestBooking($minDate = '2025-05-09')
    {
        $results = $this->agentDAL->getLatestBooking($minDate);
        
        if (empty($results)) {
            return null;
        }
        
        $row = $results[0];
        return [
            'agent_name' => $row['agent_name'],
            'total_pax' => (int)$row['total_pax'],
            'booked_at' => $row['booked_at']
        ];
    }

    /**
     * Get team QA compliance (Garland)
     */
    public function getTeamQACompliance($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getTeamQACompliance($fromDate, $toDate, $team);
        
        // Format results
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'team_name' => $row['team_name'],
                'compliance_percentage' => (int)$row['compliance_percentage']
            ];
        }
        
        return $formatted;
    }

    /**
     * Get agent QA compliance (Garland)
     */
    public function getAgentQACompliance($fromDate, $toDate, $team = 'ALL')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('Date range is required', 400);
        }

        $results = $this->agentDAL->getAgentQACompliance($fromDate, $toDate, $team);
        
        // Format results
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'agent_name' => $row['agent_name'],
                'team_name' => $row['team_name'],
                'compliance_percentage' => (int)$row['compliance_percentage']
            ];
        }
        
        return $formatted;
    }
}

