<?php
/**
 * Diwali Incentive Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\DiwaliIncentiveDAL;
use Exception;

class DiwaliIncentiveService
{
    private DiwaliIncentiveDAL $dal;

    private const DEFAULT_START_DATE = '2025-07-12';
    private const DEFAULT_END_DATE = '2025-10-20';

    public function __construct()
    {
        $this->dal = new DiwaliIncentiveDAL();
    }

    /**
     * Retrieve daily performance data and summary for Diwali incentive
     *
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function getDailyPerformance(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? self::DEFAULT_START_DATE;
        $toDate = $filters['to_date'] ?? self::DEFAULT_END_DATE;
        $teamName = $filters['team_name'] ?? null;

        $this->validateDateRange($fromDate, $toDate);

        $rows = $this->dal->getDailyPerformance($fromDate, $toDate, $teamName);
        $dailyData = $this->normalizeDailyRows($rows);
        $summary = $this->buildSummary($dailyData);

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'team_name' => $teamName
            ],
            'summary' => $summary,
            'daily_data' => $dailyData,
            'total_days' => count($dailyData)
        ];
    }

    /**
     * Retrieve daily performance data for a specific date (and optional team)
     *
     * @param string $date
     * @param string|null $teamName
     * @return array
     * @throws Exception
     */
    public function getDailyPerformanceByDate(string $date, ?string $teamName = null): array
    {
        return $this->getDailyPerformance([
            'from_date' => $date,
            'to_date' => $date,
            'team_name' => $teamName,
        ]);
    }

    /**
     * Validate date inputs
     *
     * @param string $fromDate
     * @param string $toDate
     * @throws Exception
     */
    private function validateDateRange(string $fromDate, string $toDate): void
    {
        if (!$this->isValidDate($fromDate) || !$this->isValidDate($toDate)) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }

        if (strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception('from_date must be earlier than or equal to to_date', 400);
        }
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Normalize database rows to proper data types
     *
     * @param array $rows
     * @return array
     */
    private function normalizeDailyRows(array $rows): array
    {
        return array_map(function ($row) {
            return [
                'date' => $row['date'],
                'pax' => (int)($row['pax'] ?? 0),
                'fit' => (int)($row['fit'] ?? 0),
                'pif' => (int)($row['pif'] ?? 0),
                'gdeals' => (int)($row['gdeals'] ?? 0),
                'gtib' => (int)($row['gtib'] ?? 0),
                'abandoned' => (int)($row['abandoned'] ?? 0),
                'conversion' => (float)($row['conversion'] ?? 0),
                'fcs' => (float)($row['fcs'] ?? 0),
                'AHT' => (float)($row['AHT'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Build summary statistics from daily data
     *
     * @param array $dailyData
     * @return array
     */
    private function buildSummary(array $dailyData): array
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
                'avg_aht' => 0,
            ];
        }

        $totalDays = count($dailyData);
        $totals = [
            'total_pax' => array_sum(array_column($dailyData, 'pax')),
            'total_fit' => array_sum(array_column($dailyData, 'fit')),
            'total_pif' => array_sum(array_column($dailyData, 'pif')),
            'total_gdeals' => array_sum(array_column($dailyData, 'gdeals')),
            'total_gtib' => array_sum(array_column($dailyData, 'gtib')),
            'total_abandoned' => array_sum(array_column($dailyData, 'abandoned')),
        ];

        $averages = [
            'avg_conversion' => round(array_sum(array_column($dailyData, 'conversion')) / $totalDays, 2),
            'avg_fcs' => round(array_sum(array_column($dailyData, 'fcs')) / $totalDays, 2),
            'avg_aht' => round(array_sum(array_column($dailyData, 'AHT')) / $totalDays, 2),
        ];

        return array_merge($totals, $averages);
    }

    /**
     * Create a new comment entry
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function createComment(array $payload): array
    {
        $userId = $payload['user_id'] ?? null;
        $displayName = isset($payload['user_display_name']) ? trim((string)$payload['user_display_name']) : null;
        $message = isset($payload['message']) ? trim((string)$payload['message']) : null;
        $parentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : null;

        if (!is_numeric($userId)) {
            throw new Exception('user_id is required', 400);
        }

        if ($displayName === null || $displayName === '') {
            throw new Exception('user_display_name is required', 400);
        }

        if ($message === null || $message === '') {
            throw new Exception('message is required', 400);
        }

        $commentId = $this->dal->insertComment((int)$userId, $displayName, $message, $parentId);

        return [
            'comment_id' => $commentId,
            'message' => 'Comment created successfully',
        ];
    }

    /**
     * Retrieve comments for a date range
     *
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function listComments(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? self::DEFAULT_START_DATE;
        $toDate = $filters['to_date'] ?? self::DEFAULT_END_DATE;

        $this->validateDateRange($fromDate, $toDate);

        $rows = $this->dal->getComments($fromDate, $toDate);
        $comments = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'user_display_name' => $row['user_display_name'],
                'message' => $row['message'],
                'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'total_comments' => count($comments),
            'comments' => $comments,
        ];
    }
}


