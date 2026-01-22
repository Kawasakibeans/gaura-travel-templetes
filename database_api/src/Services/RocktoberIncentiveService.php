<?php
/**
 * Rocktober incentive service
 */

namespace App\Services;

use App\DAL\RocktoberIncentiveDAL;
use DateTime;
use Exception;

class RocktoberIncentiveService
{
    private RocktoberIncentiveDAL $dal;

    public function __construct()
    {
        $this->dal = new RocktoberIncentiveDAL();
    }

    public function getAgentMetrics(array $filters = []): array
    {
        $date = $filters['date'] ?? null;
        if (empty($date)) {
            throw new Exception('date query parameter is required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }

        // Only pass through explicitly provided thresholds (no defaults)
        $thresholds = [];
        if (isset($filters['min_pif'])) {
            $thresholds['minimum_pif'] = (float)$filters['min_pif'];
        }
        if (isset($filters['min_fcs'])) {
            $thresholds['minimum_fcs'] = (float)$filters['min_fcs'];
        }
        if (isset($filters['min_pif_percent'])) {
            $thresholds['minimum_pif_percent'] = (float)$filters['min_pif_percent'];
        }
        if (isset($filters['max_aht'])) {
            $thresholds['maximum_aht_seconds'] = (int)$filters['max_aht'];
        }
        if (isset($filters['min_garland'])) {
            $thresholds['minimum_garland_ratio'] = (float)$filters['min_garland'];
        }
        if (isset($filters['require_on_time'])) {
            $thresholds['require_on_time'] = (bool)$filters['require_on_time'];
        }
        if (isset($filters['on_time_buffer'])) {
            $thresholds['on_time_buffer_seconds'] = (int)$filters['on_time_buffer'];
        }
        if (isset($filters['exclude_team_name'])) {
            $thresholds['exclude_team_name'] = $filters['exclude_team_name'];
        }

        $normalisedDate = $this->normaliseDate($date, 'date');
        $rows = $this->dal->getAgentMetricsByDate($normalisedDate, $thresholds);

        $agents = array_map(function (array $row) {
            $garland = $row['garland_ratio'] !== null ? (float)$row['garland_ratio'] : null;
            $ahtSeconds = (float)$row['aht_seconds'];

            return [
                'date' => $row['date'],
                'agent_name' => $row['agent_name'],
                'pif' => (float)$row['pif'],
                'gtib' => (int)$row['gtib'],
                'new_sale_made_count' => (int)$row['new_sale_made_count'],
                'pif_percent' => (float)$row['pif_percent'],
                'fcs' => (float)$row['fcs'],
                'aht_seconds' => $ahtSeconds,
                'aht_minutes' => round($ahtSeconds / 60, 2),
                'garland_ratio' => $garland,
                'garland_percent' => $garland !== null ? round($garland * 100, 2) : null,
                'shift_time' => $row['shift_time'],
                'noble_login_time' => $row['noble_login_time'],
                'on_time' => $row['on_time'],
            ];
        }, $rows);

        return [
            'date' => $normalisedDate,
            'total_agents' => count($agents),
            'agents' => $agents,
        ];
    }

    public function getDailyPerformance(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if (empty($startDate)) {
            throw new Exception('start_date query parameter is required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }
        if (empty($endDate)) {
            throw new Exception('end_date query parameter is required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }

        $start = $this->normaliseDate($startDate, 'start_date');
        $end = $this->normaliseDate($endDate, 'end_date');

        if ($start > $end) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        $department = isset($filters['department']) && !empty($filters['department']) ? $filters['department'] : null;
        $excludeTeamName = isset($filters['exclude_team_name']) && !empty($filters['exclude_team_name']) ? $filters['exclude_team_name'] : null;

        $rows = $this->dal->getDailyPerformance($start, $end, $department, $excludeTeamName);

        $daily = array_map(function (array $row) {
            $ahtSeconds = (float)$row['aht_seconds'];
            return [
                'date' => $row['date'],
                'pax' => (int)$row['pax'],
                'fit' => (int)$row['fit'],
                'pif' => (int)$row['pif'],
                'gdeals' => (int)$row['gdeals'],
                'gtib' => (int)$row['gtib'],
                'abandoned' => (int)$row['abandoned'],
                'conversion' => (float)$row['conversion'],
                'fcs' => (float)$row['fcs'],
                'pif_percent' => (float)$row['pif_percent'],
                'aht_seconds' => $ahtSeconds,
                'aht_minutes' => round($ahtSeconds / 60, 2),
            ];
        }, $rows);

        return [
            'date_range' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'totals' => $this->calculateSummary($daily),
            'daily' => $daily,
            'total_days' => count($daily),
        ];
    }

    private function calculateSummary(array $daily): array
    {
        if (empty($daily)) {
            return [
                'total_pax' => 0,
                'total_fit' => 0,
                'total_pif' => 0,
                'total_gdeals' => 0,
                'total_gtib' => 0,
                'total_abandoned' => 0,
                'average_conversion' => 0,
                'average_fcs' => 0,
                'average_pif_percent' => 0,
                'average_aht_minutes' => 0,
            ];
        }

        $count = count($daily);

        $sum = [
            'pax' => array_sum(array_column($daily, 'pax')),
            'fit' => array_sum(array_column($daily, 'fit')),
            'pif' => array_sum(array_column($daily, 'pif')),
            'gdeals' => array_sum(array_column($daily, 'gdeals')),
            'gtib' => array_sum(array_column($daily, 'gtib')),
            'abandoned' => array_sum(array_column($daily, 'abandoned')),
            'conversion' => array_sum(array_column($daily, 'conversion')),
            'fcs' => array_sum(array_column($daily, 'fcs')),
            'pif_percent' => array_sum(array_column($daily, 'pif_percent')),
            'aht_minutes' => array_sum(array_column($daily, 'aht_minutes')),
        ];

        return [
            'total_pax' => (int)$sum['pax'],
            'total_fit' => (int)$sum['fit'],
            'total_pif' => (int)$sum['pif'],
            'total_gdeals' => (int)$sum['gdeals'],
            'total_gtib' => (int)$sum['gtib'],
            'total_abandoned' => (int)$sum['abandoned'],
            'average_conversion' => round($sum['conversion'] / $count, 2),
            'average_fcs' => round($sum['fcs'] / $count, 2),
            'average_pif_percent' => round($sum['pif_percent'] / $count, 2),
            'average_aht_minutes' => round($sum['aht_minutes'] / $count, 2),
        ];
    }

    private function normaliseDate(string $value, string $field): string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            [$day, $month, $year] = explode('/', $value);
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $date = DateTime::createFromFormat('Y-m-d', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($date === false) {
            throw new Exception("{$field} must be a valid date", 400);
        }

        return $date->format('Y-m-d');
    }
}

