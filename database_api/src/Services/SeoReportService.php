<?php
/**
 * SEO Report Service
 * Business logic for SEO performance report
 */

namespace App\Services;

use App\DAL\SeoReportDAL;
use Exception;

class SeoReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SeoReportDAL();
    }

    /**
     * Get quarterly performance data
     *
     * @param int $year
     * @return array<int, array<string, mixed>>
     */
    public function getQuarterlyPerformance(int $year = 2025): array
    {
        return $this->dal->getQuarterlyPerformance($year);
    }

    /**
     * Get period-based SEO metrics
     *
     * @param string|null $period
     * @return array<int, array<string, mixed>>
     */
    public function getPeriodMetrics(?string $period = null): array
    {
        return $this->dal->getPeriodMetrics($period);
    }

    /**
     * Get country traffic breakdown
     *
     * @param string|null $period
     * @return array<int, array<string, mixed>>
     */
    public function getCountryTraffic(?string $period = null): array
    {
        return $this->dal->getCountryTraffic($period);
    }

    /**
     * Get backlink analytics time series
     *
     * @param int $months
     * @return array<int, array<string, mixed>>
     */
    public function getBacklinkAnalytics(int $months = 12): array
    {
        return $this->dal->getBacklinkAnalytics($months);
    }

    /**
     * Get period growth comparison
     *
     * @param string $period1
     * @param string $period2
     * @return array<string, mixed>
     */
    public function getPeriodGrowth(string $period1, string $period2): array
    {
        return $this->dal->getPeriodGrowth($period1, $period2);
    }

    /**
     * Get complete report data
     *
     * @param int $year
     * @return array<string, mixed>
     */
    public function getCompleteReport(int $year = 2025): array
    {
        return [
            'quarterly_performance' => $this->getQuarterlyPerformance($year),
            'period_metrics' => $this->getPeriodMetrics(),
            'country_traffic' => $this->getCountryTraffic(),
            'backlink_analytics' => $this->getBacklinkAnalytics(12),
            'growth' => $this->getPeriodGrowth('Q2 2025', 'Q3 2025')
        ];
    }
}