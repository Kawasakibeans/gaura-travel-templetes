<?php
/**
 * Steptember incentive service
 */

namespace App\Services;

use App\DAL\SteptemberIncentiveDAL;
use DateTime;
use Exception;

class SteptemberIncentiveService
{
    private SteptemberIncentiveDAL $dal;

    public function __construct()
    {
        $this->dal = new SteptemberIncentiveDAL();
    }

    public function getAgentMetrics(array $filters = []): array
    {
        $date = $filters['date'] ?? null;
        if (empty($date)) {
            throw new Exception('date query parameter is required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }

        $normalised = $this->normaliseDate($date, 'date');
        $rows = $this->dal->getAgentMetricsByDate($normalised);

        $agents = array_map(function (array $row) {
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
                'shift_time' => $row['shift_time'],
                'noble_login_time' => $row['noble_login_time'],
                'on_time' => $row['on_time'],
            ];
        }, $rows);

        return [
            'date' => $normalised,
            'thresholds' => [
                'minimum_pif' => 0,
                'minimum_fcs' => 0.25,
                'minimum_pif_percent' => 0.4,
                'maximum_aht_seconds' => 1440,
                'attendance_requirement' => 'On Time Noble login',
            ],
            'total_agents' => count($agents),
            'agents' => $agents,
        ];
    }

    public function getDailyPerformance(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? '2025-09-01';
        $endDate = $filters['end_date'] ?? '2025-09-30';

        $start = $this->normaliseDate($startDate, 'start_date');
        $end = $this->normaliseDate($endDate, 'end_date');

        if ($start > $end) {
            throw new Exception('start_date must be before or equal to end_date', 400);
        }

        $rows = $this->dal->getDailyPerformance($start, $end);
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

        $days = count($daily);

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
            'average_conversion' => round($sum['conversion'] / $days, 2),
            'average_fcs' => round($sum['fcs'] / $days, 2),
            'average_pif_percent' => round($sum['pif_percent'] / $days, 2),
            'average_aht_minutes' => round($sum['aht_minutes'] / $days, 2),
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

