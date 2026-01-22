<?php
/**
 * Marathon Incentive Service Layer
 */

namespace App\Services;

use App\DAL\MarathonIncentiveDAL;
use Exception;

class MarathonIncentiveService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new MarathonIncentiveDAL();
    }

    /**
     * Retrieve competition data for the supplied date range
     *
     * @param array $filters   Query parameters from the request
     * @param array $defaults  Default values such as start_date, end_date, and label
     */
    public function getCompetition(array $filters = [], array $defaults = []): array
    {
        $startInput = $filters['start_date'] ?? ($defaults['start_date'] ?? null);
        $endInput = $filters['end_date'] ?? ($defaults['end_date'] ?? null);

        if ($startInput === null || $endInput === null) {
            throw new Exception('start_date and end_date are required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }

        $team = $filters['team'] ?? null;

        $startDate = $this->normalizeDate($startInput, 'start_date');
        $endDate = $this->normalizeDate($endInput, 'end_date');

        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        $statsRows = $this->dal->getAgentStats($startDate, $endDate, $team);
        $garlandRows = $this->dal->getGarlandScores($startDate, $endDate);

        $garlandMap = [];
        foreach ($garlandRows as $row) {
            $key = $row['team_name'] . '|' . $row['agent_name'];
            $garlandMap[$key] = (float)$row['garland'];
        }

        $agents = array_map(function (array $row) use ($garlandMap) {
            $key = $row['team_name'] . '|' . $row['agent_name'];
            $garlandPercent = $garlandMap[$key] ?? 0.0;
            $ahtSeconds = (float)$row['aht_seconds'];

            return [
                'team_name' => $row['team_name'],
                'agent_name' => $row['agent_name'],
                'days_present' => (int)$row['days_present'],
                'role' => $row['role'],
                'department' => $row['department'],
                'pax' => (int)$row['pax'],
                'fit' => (int)$row['fit'],
                'pif' => (int)$row['pif'],
                'gdeals' => (int)$row['gdeals'],
                'gtib' => (int)$row['gtib'],
                'conversion' => round((float)$row['conversion'], 2),
                'fcs' => round((float)$row['fcs'], 2),
                'aht_seconds' => $ahtSeconds,
                'aht_minutes' => round($ahtSeconds / 60, 2),
                'garland_percent' => $garlandPercent,
                'garland_ratio' => round($garlandPercent / 100, 4),
            ];
        }, $statsRows);

        $eligible = array_values(array_filter($agents, function (array $agent) {
            return $agent['aht_minutes'] <= 24.0
                && $agent['gtib'] >= 100
                && $agent['days_present'] >= 15
                && $agent['conversion'] >= 0.40;
        }));

        $topPerformers = [
            'pif' => $this->topPerformers($agents, 'pif'),
            'gtib' => $this->topPerformers($agents, 'gtib'),
            'conversion' => $this->topPerformers($agents, 'conversion'),
            'fcs' => $this->topPerformers($agents, 'fcs'),
            'aht' => $this->topPerformers($agents, 'aht_minutes', false),
            'garland' => $this->topPerformers($agents, 'garland_percent'),
        ];

        return [
            'label' => $defaults['label'] ?? null,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'filters' => [
                'team' => $team,
            ],
            'thresholds' => [
                'minimum_gtib' => 100,
                'minimum_days_present' => 15,
                'minimum_conversion' => 0.40,
                'maximum_aht_minutes' => 24.0,
            ],
            'summary' => $this->calculateSummary($agents),
            'agents' => $agents,
            'total_agents' => count($agents),
            'eligible_agents' => $eligible,
            'eligible_count' => count($eligible),
            'top_performers' => $topPerformers,
        ];
    }

    /**
     * Return the top N performers for a metric
     */
    private function topPerformers(array $agents, string $field, bool $desc = true, int $limit = 3): array
    {
        $filtered = array_filter($agents, fn ($agent) => isset($agent[$field]));
        $sorted = array_values($filtered);

        usort($sorted, function ($a, $b) use ($field, $desc) {
            $valueA = $a[$field];
            $valueB = $b[$field];

            if ($valueA == $valueB) {
                return 0;
            }

            if ($desc) {
                return ($valueA < $valueB) ? 1 : -1;
            }

            return ($valueA < $valueB) ? -1 : 1;
        });

        $top = array_slice($sorted, 0, $limit);

        return array_map(function ($agent) use ($field) {
            return [
                'agent_name' => $agent['agent_name'],
                'team_name' => $agent['team_name'],
                'value' => $agent[$field],
            ];
        }, $top);
    }

    /**
     * Build summary totals across agents
     */
    private function calculateSummary(array $agents): array
    {
        if (empty($agents)) {
            return [
                'total_pax' => 0,
                'total_fit' => 0,
                'total_pif' => 0,
                'total_gdeals' => 0,
                'total_gtib' => 0,
                'average_conversion' => 0,
                'average_fcs' => 0,
                'average_aht_minutes' => 0,
                'average_garland_percent' => 0,
            ];
        }

        $count = count($agents);

        $sum = [
            'pax' => array_sum(array_column($agents, 'pax')),
            'fit' => array_sum(array_column($agents, 'fit')),
            'pif' => array_sum(array_column($agents, 'pif')),
            'gdeals' => array_sum(array_column($agents, 'gdeals')),
            'gtib' => array_sum(array_column($agents, 'gtib')),
            'conversion' => array_sum(array_column($agents, 'conversion')),
            'fcs' => array_sum(array_column($agents, 'fcs')),
            'aht_minutes' => array_sum(array_column($agents, 'aht_minutes')),
            'garland_percent' => array_sum(array_column($agents, 'garland_percent')),
        ];

        return [
            'total_pax' => (int)$sum['pax'],
            'total_fit' => (int)$sum['fit'],
            'total_pif' => (int)$sum['pif'],
            'total_gdeals' => (int)$sum['gdeals'],
            'total_gtib' => (int)$sum['gtib'],
            'average_conversion' => round($sum['conversion'] / $count, 2),
            'average_fcs' => round($sum['fcs'] / $count, 2),
            'average_aht_minutes' => round($sum['aht_minutes'] / $count, 2),
            'average_garland_percent' => round($sum['garland_percent'] / $count, 2),
        ];
    }

    /**
     * Normalise a date string to YYYY-MM-DD
     */
    private function normalizeDate(string $value, string $field): string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            [$day, $month, $year] = explode('/', $value);
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new Exception("{$field} must be a valid date", 400);
        }

        return date('Y-m-d', $timestamp);
    }
}

