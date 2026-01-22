<?php
/**
 * Agent Dashboard Service - Business Logic Layer
 * Handles agent and team performance statistics
 */

namespace App\Services;

use App\DAL\AgentDashboardDAL;
use Exception;

class AgentDashboardService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AgentDashboardDAL();
    }

    /**
     * Get team performance statistics
     */
    public function getTeamStats($filters)
    {
        $fromDate = $filters['from_date'] ?? date('Y-m-d');
        $toDate = $filters['to_date'] ?? date('Y-m-d');
        $fromTime = $filters['from_time'] ?? '00:00:00';
        $toTime = $filters['to_time'] ?? '23:59:59';
        $teamName = $filters['team_name'] ?? null;

        // Get all teams or specific team
        $teams = $this->dal->getTeams($teamName);
        
        if (empty($teams)) {
            return [
                'teams' => [],
                'summary' => $this->getEmptySummary(),
                'filters' => $filters
            ];
        }

        $teamStats = [];
        $summary = [
            'total_gtib_calls' => 0,
            'total_other_calls' => 0,
            'total_pax_gdeals' => 0,
            'total_pax_fit' => 0,
            'total_pax' => 0,
            'total_sl_calls' => 0,
            'total_non_sl_calls' => 0
        ];

        foreach ($teams as $team) {
            $teamName = $team['team_name'];
            
            // Get agents in team
            $agents = $this->dal->getAgentsByTeam($teamName);
            $tsrList = array_column($agents, 'tsr');
            $agentList = array_column($agents, 'sales_id');

            if (empty($tsrList)) {
                continue;
            }

            // Get call statistics
            $callStats = $this->dal->getTeamCallStats($tsrList, $fromDate, $toDate, $fromTime, $toTime);
            
            // Get booking statistics
            $bookingStats = $this->dal->getTeamBookingStats($agentList, $fromDate, $toDate);

            // Calculate metrics
            $totalCalls = $callStats['gtib_calls'];
            $slCalls = $callStats['sl_calls'];
            $totalPax = $bookingStats['pax_gdeals'] + $bookingStats['pax_fit'];

            $fcrPercentage = $totalCalls > 0 ? ($slCalls / $totalCalls) * 100 : 0;
            $conversionRate = $totalCalls > 0 ? ($totalPax / $totalCalls) * 100 : 0;

            $teamStats[] = [
                'team_name' => $teamName,
                'gtib_calls' => $callStats['gtib_calls'],
                'other_calls' => $callStats['other_calls'],
                'pax_gdeals' => $bookingStats['pax_gdeals'],
                'pax_fit' => $bookingStats['pax_fit'],
                'total_pax' => $totalPax,
                'conversion_rate' => number_format($conversionRate, 2, '.', ''),
                'fcr_percentage' => number_format($fcrPercentage, 2, '.', ''),
                'sl_calls' => $slCalls,
                'non_sl_calls' => $callStats['non_sl_calls']
            ];

            // Update summary
            $summary['total_gtib_calls'] += $callStats['gtib_calls'];
            $summary['total_other_calls'] += $callStats['other_calls'];
            $summary['total_pax_gdeals'] += $bookingStats['pax_gdeals'];
            $summary['total_pax_fit'] += $bookingStats['pax_fit'];
            $summary['total_pax'] += $totalPax;
            $summary['total_sl_calls'] += $slCalls;
            $summary['total_non_sl_calls'] += $callStats['non_sl_calls'];
        }

        // Calculate summary percentages
        if ($summary['total_gtib_calls'] > 0) {
            $summary['fcr_percentage'] = number_format(($summary['total_sl_calls'] / $summary['total_gtib_calls']) * 100, 2, '.', '');
            $summary['conversion_rate'] = number_format(($summary['total_pax'] / $summary['total_gtib_calls']) * 100, 2, '.', '');
        } else {
            $summary['fcr_percentage'] = '0.00';
            $summary['conversion_rate'] = '0.00';
        }

        return [
            'teams' => $teamStats,
            'summary' => $summary,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'from_time' => $fromTime,
                'to_time' => $toTime,
                'team_name' => $filters['team_name'] ?? null
            ]
        ];
    }

    /**
     * Get individual agent performance statistics
     */
    public function getAgentStats($filters)
    {
        $fromDate = $filters['from_date'] ?? date('Y-m-d');
        $toDate = $filters['to_date'] ?? date('Y-m-d');
        $fromTime = $filters['from_time'] ?? '00:00:00';
        $toTime = $filters['to_time'] ?? '23:59:59';
        $agentId = $filters['agent_id'] ?? null;
        $teamName = $filters['team_name'] ?? null;

        // Get agents
        $agents = $this->dal->getAgents($agentId, $teamName);

        if (empty($agents)) {
            return [
                'agents' => [],
                'summary' => $this->getEmptySummary(),
                'filters' => $filters
            ];
        }

        $agentStats = [];
        $summary = [
            'total_gtib_calls' => 0,
            'total_other_calls' => 0,
            'total_pax_gdeals' => 0,
            'total_pax_fit' => 0,
            'total_pax' => 0,
            'total_sl_calls' => 0,
            'total_non_sl_calls' => 0
        ];

        foreach ($agents as $agent) {
            $tsr = $agent['tsr'];
            $salesId = $agent['sales_id'];

            // Get call statistics
            $callStats = $this->dal->getAgentCallStats($tsr, $fromDate, $toDate, $fromTime, $toTime);
            
            // Get booking statistics
            $bookingStats = $this->dal->getAgentBookingStats($salesId, $fromDate, $toDate);

            // Calculate metrics
            $totalCalls = $callStats['gtib_calls'];
            $slCalls = $callStats['sl_calls'];
            $totalPax = $bookingStats['pax_gdeals'] + $bookingStats['pax_fit'];

            $fcrPercentage = $totalCalls > 0 ? ($slCalls / $totalCalls) * 100 : 0;
            $conversionRate = $totalCalls > 0 ? ($totalPax / $totalCalls) * 100 : 0;

            $agentStats[] = [
                'agent_id' => $salesId,
                'agent_name' => $agent['agent_name'] ?? $salesId,
                'team_name' => $agent['team_name'],
                'tsr' => $tsr,
                'gtib_calls' => $callStats['gtib_calls'],
                'other_calls' => $callStats['other_calls'],
                'pax_gdeals' => $bookingStats['pax_gdeals'],
                'pax_fit' => $bookingStats['pax_fit'],
                'total_pax' => $totalPax,
                'conversion_rate' => number_format($conversionRate, 2, '.', ''),
                'fcr_percentage' => number_format($fcrPercentage, 2, '.', ''),
                'sl_calls' => $slCalls,
                'non_sl_calls' => $callStats['non_sl_calls']
            ];

            // Update summary
            $summary['total_gtib_calls'] += $callStats['gtib_calls'];
            $summary['total_other_calls'] += $callStats['other_calls'];
            $summary['total_pax_gdeals'] += $bookingStats['pax_gdeals'];
            $summary['total_pax_fit'] += $bookingStats['pax_fit'];
            $summary['total_pax'] += $totalPax;
            $summary['total_sl_calls'] += $slCalls;
            $summary['total_non_sl_calls'] += $callStats['non_sl_calls'];
        }

        // Calculate summary percentages
        if ($summary['total_gtib_calls'] > 0) {
            $summary['fcr_percentage'] = number_format(($summary['total_sl_calls'] / $summary['total_gtib_calls']) * 100, 2, '.', '');
            $summary['conversion_rate'] = number_format(($summary['total_pax'] / $summary['total_gtib_calls']) * 100, 2, '.', '');
        } else {
            $summary['fcr_percentage'] = '0.00';
            $summary['conversion_rate'] = '0.00';
        }

        return [
            'agents' => $agentStats,
            'summary' => $summary,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'from_time' => $fromTime,
                'to_time' => $toTime,
                'agent_id' => $filters['agent_id'] ?? null,
                'team_name' => $filters['team_name'] ?? null
            ]
        ];
    }

    /**
     * Get agent call history
     */
    public function getAgentCallHistory($agentId, $filters)
    {
        if (empty($agentId)) {
            throw new Exception('Agent ID is required', 400);
        }

        $fromDate = $filters['from_date'] ?? date('Y-m-d');
        $toDate = $filters['to_date'] ?? date('Y-m-d');
        $fromTime = $filters['from_time'] ?? '00:00:00';
        $toTime = $filters['to_time'] ?? '23:59:59';
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        // Get agent TSR
        $agent = $this->dal->getAgentByCode($agentId);
        
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }

        $tsr = $agent['tsr'];

        // Get call history
        $calls = $this->dal->getAgentCallHistory($tsr, $fromDate, $toDate, $fromTime, $toTime, $limit, $offset);
        
        // Get total count
        $totalCount = $this->dal->getAgentCallHistoryCount($tsr, $fromDate, $toDate, $fromTime, $toTime);

        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['agent_name'] ?? $agentId,
            'tsr' => $tsr,
            'calls' => $calls,
            'total_count' => $totalCount,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'from_time' => $fromTime,
                'to_time' => $toTime,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get team list
     */
    public function getTeamList()
    {
        $teams = $this->dal->getDistinctTeams();
        return array_column($teams, 'team_name');
    }

    /**
     * Get agent list
     */
    public function getAgentList($teamName = null)
    {
        return $this->dal->getAgentsList($teamName);
    }

    /**
     * Private helper methods
     */
    private function getEmptySummary()
    {
        return [
            'total_gtib_calls' => 0,
            'total_other_calls' => 0,
            'total_pax_gdeals' => 0,
            'total_pax_fit' => 0,
            'total_pax' => 0,
            'total_sl_calls' => 0,
            'total_non_sl_calls' => 0,
            'fcr_percentage' => '0.00',
            'conversion_rate' => '0.00'
        ];
    }
}

