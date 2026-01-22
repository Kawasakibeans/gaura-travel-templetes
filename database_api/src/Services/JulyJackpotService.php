<?php
/**
 * July Jackpot Service - Business Logic Layer
 * Handles July Jackpot incentive tracking and reporting
 */

namespace App\Services;

use App\DAL\JulyJackpotDAL;
use Exception;

class JulyJackpotService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new JulyJackpotDAL();
    }

    /**
     * Get monthly agent-wise data for July Jackpot incentive
     * Returns agents who meet all eligibility criteria for the month
     * 
     * Eligibility Criteria:
     * - Days worked >= 15
     * - Garland compliance >= 75%
     * - PAX count > 50
     * - PIF conversion >= 40%
     * - FCS >= 22%
     * - AHT <= 24 minutes (1440 seconds)
     * 
     * @param array $filters Date range filters
     * @return array Monthly agent performance data
     */
    public function getMonthlyAgentData($filters = [])
    {
        $startDate = $filters['start_date'] ?? '2025-07-01';
        $endDate = $filters['end_date'] ?? '2025-07-31';

        // Validate date format
        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }

        // Validate date range
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        $data = $this->dal->getMonthlyAgentData($startDate, $endDate);

        return [
            'agents' => $data,
            'total_agents' => count($data),
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'eligibility_criteria' => [
                'min_days_worked' => 15,
                'min_garland_score' => 75,
                'min_pax' => 50,
                'min_pif_conversion' => 0.4,
                'min_fcs' => 0.22,
                'max_aht_seconds' => 1440
            ]
        ];
    }

    /**
     * Get daily performance data for July Jackpot incentive
     * Returns aggregated daily metrics for the date range
     * 
     * @param array $filters Date range filters
     * @return array Daily performance data
     */
    public function getDailyPerformanceData($filters = [])
    {
        $startDate = $filters['start_date'] ?? '2025-07-01';
        $endDate = $filters['end_date'] ?? '2025-07-31';

        // Validate date format
        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }

        // Validate date range
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        $data = $this->dal->getDailyPerformanceData($startDate, $endDate);

        // Calculate summary statistics
        $summary = $this->calculateDailySummary($data);

        return [
            'daily_data' => $data,
            'summary' => $summary,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'total_days' => count($data)
        ];
    }

    /**
     * Calculate summary statistics from daily data
     * 
     * @param array $dailyData Daily performance data
     * @return array Summary statistics
     */
    private function calculateDailySummary($dailyData)
    {
        if (empty($dailyData)) {
            return [
                'total_pax' => 0,
                'total_fit' => 0,
                'total_pif' => 0,
                'total_gdeals' => 0,
                'total_gtib' => 0,
                'total_abandoned' => 0,
                'avg_conversion' => 0,
                'avg_fcs' => 0,
                'avg_pif_percent' => 0,
                'avg_aht' => 0
            ];
        }

        $totalPax = array_sum(array_column($dailyData, 'pax'));
        $totalFit = array_sum(array_column($dailyData, 'fit'));
        $totalPif = array_sum(array_column($dailyData, 'pif'));
        $totalGdeals = array_sum(array_column($dailyData, 'gdeals'));
        $totalGtib = array_sum(array_column($dailyData, 'gtib'));
        $totalAbandoned = array_sum(array_column($dailyData, 'abandoned'));
        
        $avgConversion = count($dailyData) > 0 ? array_sum(array_column($dailyData, 'conversion')) / count($dailyData) : 0;
        $avgFcs = count($dailyData) > 0 ? array_sum(array_column($dailyData, 'fcs')) / count($dailyData) : 0;
        $avgPifPercent = count($dailyData) > 0 ? array_sum(array_column($dailyData, 'pif_percent')) / count($dailyData) : 0;
        $avgAht = count($dailyData) > 0 ? array_sum(array_column($dailyData, 'AHT')) / count($dailyData) : 0;

        return [
            'total_pax' => (int)$totalPax,
            'total_fit' => (int)$totalFit,
            'total_pif' => (int)$totalPif,
            'total_gdeals' => (int)$totalGdeals,
            'total_gtib' => (int)$totalGtib,
            'total_abandoned' => (int)$totalAbandoned,
            'avg_conversion' => round($avgConversion, 2),
            'avg_fcs' => round($avgFcs, 2),
            'avg_pif_percent' => round($avgPifPercent, 2),
            'avg_aht' => round($avgAht, 2)
        ];
    }

    /**
     * Validate date format (YYYY-MM-DD)
     * 
     * @param string $date Date string
     * @return bool True if valid
     */
    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

