<?php
namespace App\Services;

use App\DAL\GTIBAgentProductivityDAL;
use DateTime;
use Exception;

class GTIBAgentProductivityService
{
    private GTIBAgentProductivityDAL $dal;

    public function __construct()
    {
        $this->dal = new GTIBAgentProductivityDAL();
    }

    public function getReport(array $filters = []): array
    {
        $date = $this->resolveDate($filters['start_date'] ?? null);
        $team = isset($filters['team']) && trim((string)$filters['team']) !== '' ? trim((string)$filters['team']) : null;
        $manager = isset($filters['manager']) && trim((string)$filters['manager']) !== '' ? trim((string)$filters['manager']) : null;

        $criteriaRow = $this->dal->getKeyMetrics($date) ?? [];
        $conversionRatio = (float)($criteriaRow['conversion'] ?? 0);
        $fcsRatio = (float)($criteriaRow['fcs'] ?? 0);
        $ahtString = $criteriaRow['aht'] ?? '00:00:00';
        $ahtSeconds = $this->timeToSeconds($ahtString);

        $agentRows = $this->dal->getAgentRecords($date, $team, $manager);

        $agents = [];
        $teamStats = [];
        $managerStats = [];
        $companyStats = [
            'total_pax' => 0,
            'total_pax_pif' => 0,
            'total_quotes' => 0,
            'total_fcs' => 0,
            'total_aht' => 0,
            'count' => 0,
        ];

        foreach ($agentRows as $row) {
            $totalPax = (int)($row['Total_pax'] ?? 0);
            $totalPaxPif = (int)($row['Total_pax_PIF'] ?? 0);
            $gtib = (int)($row['GTIB_call_count'] ?? 0);
            $totalQuotes = (int)($row['Total_quotes'] ?? 0);
            $fcsPercent = $this->parsePercent($row['FCS'] ?? 0);
            $agentAhtSeconds = $this->timeToSeconds($row['AHT'] ?? '00:00:00');

            $conversionPercent = $gtib > 0 ? round(($totalPax / $gtib) * 100, 2) : 0.0;
            $category = 'neutral';
            if ($conversionPercent > ($conversionRatio * 100)) {
                $category = 'above';
            } elseif ($conversionPercent < ($conversionRatio * 100)) {
                $category = 'below';
            }

            $agent = [
                'agent_name' => $row['Agent_name'] ?? null,
                'team_name' => $row['Team_name'] ?? null,
                'manager_name' => $row['SM_name'] ?? null,
                'total_pax' => $totalPax,
                'total_pax_pif' => $totalPaxPif,
                'gtib' => $gtib,
                'total_quotes' => $totalQuotes,
                'pax_conversion_percent' => $conversionPercent,
                'fcs_percent' => $fcsPercent,
                'aht_seconds' => $agentAhtSeconds,
                'category' => $category,
                'date' => $row['Date'] ?? $date,
            ];

            $agents[] = $agent;

            $teamKey = $agent['team_name'] ?? 'Unassigned';
            if (!isset($teamStats[$teamKey])) {
                $teamStats[$teamKey] = [
                    'team_name' => $teamKey,
                    'total_pax' => 0,
                    'total_pax_pif' => 0,
                    'total_fcs' => 0,
                    'total_aht' => 0,
                    'count' => 0,
                ];
            }
            $teamStats[$teamKey]['total_pax'] += $totalPax;
            $teamStats[$teamKey]['total_pax_pif'] += $totalPaxPif;
            $teamStats[$teamKey]['total_fcs'] += $fcsPercent;
            $teamStats[$teamKey]['total_aht'] += $agentAhtSeconds;
            $teamStats[$teamKey]['count']++;

            $managerKey = $agent['manager_name'] ?? 'Unassigned';
            if (!isset($managerStats[$managerKey])) {
                $managerStats[$managerKey] = [
                    'manager_name' => $managerKey,
                    'team_name' => $agent['team_name'] ?? null,
                    'total_pax' => 0,
                    'total_pax_pif' => 0,
                    'total_fcs' => 0,
                    'total_aht' => 0,
                    'count' => 0,
                ];
            }
            $managerStats[$managerKey]['total_pax'] += $totalPax;
            $managerStats[$managerKey]['total_pax_pif'] += $totalPaxPif;
            $managerStats[$managerKey]['total_fcs'] += $fcsPercent;
            $managerStats[$managerKey]['total_aht'] += $agentAhtSeconds;
            $managerStats[$managerKey]['count']++;

            $companyStats['total_pax'] += $totalPax;
            $companyStats['total_pax_pif'] += $totalPaxPif;
            $companyStats['total_quotes'] += $totalQuotes;
            $companyStats['total_fcs'] += $fcsPercent;
            $companyStats['total_aht'] += $agentAhtSeconds;
            $companyStats['count']++;
        }

        $teamSummary = [];
        foreach ($teamStats as $stats) {
            $teamSummary[] = [
                'team_name' => $stats['team_name'],
                'avg_pax_conv_percent' => $stats['total_pax'] > 0 ? round(($stats['total_pax_pif'] / $stats['total_pax']) * 100, 2) : 0.0,
                'avg_fcs_percent' => $stats['count'] > 0 ? round($stats['total_fcs'] / $stats['count'], 2) : 0.0,
                'avg_aht_seconds' => $stats['count'] > 0 ? (int)round($stats['total_aht'] / $stats['count']) : 0,
            ];
        }

        $managerSummary = [];
        foreach ($managerStats as $stats) {
            $managerSummary[] = [
                'manager_name' => $stats['manager_name'],
                'team_name' => $stats['team_name'],
                'avg_pax_conv_percent' => $stats['total_pax'] > 0 ? round(($stats['total_pax_pif'] / $stats['total_pax']) * 100, 2) : 0.0,
                'avg_fcs_percent' => $stats['count'] > 0 ? round($stats['total_fcs'] / $stats['count'], 2) : 0.0,
                'avg_aht_seconds' => $stats['count'] > 0 ? (int)round($stats['total_aht'] / $stats['count']) : 0,
            ];
        }

        $companySummary = [
            'avg_pax_conv_percent' => $companyStats['total_pax'] > 0 ? round(($companyStats['total_pax_pif'] / $companyStats['total_pax']) * 100, 2) : 0.0,
            'avg_fcs_percent' => $companyStats['count'] > 0 ? round($companyStats['total_fcs'] / $companyStats['count'], 2) : 0.0,
            'avg_aht_seconds' => $companyStats['count'] > 0 ? (int)round($companyStats['total_aht'] / $companyStats['count']) : 0,
            'total_pax' => $companyStats['total_pax'],
            'total_gtib' => array_sum(array_column($agents, 'gtib')),
        ];

        $teams = array_column($this->dal->getTeams(), 'Team_name');
        $managers = array_column($this->dal->getManagers(), 'SM_name');

        return [
            'filters' => [
                'date' => $date,
                'team' => $team,
                'manager' => $manager,
            ],
            'criteria' => [
                'total_pax' => (int)($criteriaRow['total_pax'] ?? 0),
                'total_gtib' => (int)($criteriaRow['total_gtib'] ?? 0),
                'gdeals' => (int)($criteriaRow['gdeals'] ?? 0),
                'fit' => (int)($criteriaRow['fit'] ?? 0),
                'conversion_ratio' => $conversionRatio,
                'conversion_percent' => round($conversionRatio * 100, 2),
                'fcs_ratio' => $fcsRatio,
                'fcs_percent' => round($fcsRatio * 100, 2),
                'aht' => $ahtString,
                'aht_seconds' => $ahtSeconds,
            ],
            'agents' => $agents,
            'team_summary' => $teamSummary,
            'manager_summary' => $managerSummary,
            'company_summary' => $companySummary,
            'teams' => $teams,
            'managers' => $managers,
        ];
    }

    private function resolveDate(?string $date): string
    {
        if (!$date) {
            return date('Y-m-d', strtotime('-1 day'));
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt) {
            throw new Exception('start_date must be in Y-m-d format', 400);
        }

        return $dt->format('Y-m-d');
    }

    private function parsePercent($value): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string)$value);
        return $normalized === '' ? 0.0 : (float)$normalized;
    }

    private function timeToSeconds(string $time): int
    {
        if ($time === '' || $time === null) {
            return 0;
        }

        $parts = explode(':', $time);
        if (count($parts) < 3) {
            return 0;
        }

        return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
    }
}
