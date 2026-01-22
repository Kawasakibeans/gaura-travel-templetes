<?php
/**
 * Sales Report Service - Business Logic Layer
 * Handles EOD sales reports and dashboards
 */

namespace App\Services;

use App\DAL\SalesReportDAL;
use Exception;

class SalesReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SalesReportDAL();
    }

    /**
     * Get sales dashboard
     */
    public function getSalesDashboard($date = null, $team = null, $groupBy = 'team')
    {
        $reportDate = $date ?? date('Y-m-d', strtotime('yesterday'));

        $salesData = $this->dal->getSalesData($reportDate, $team, $groupBy);

        return [
            'report_date' => $reportDate,
            'team' => $team ?? 'ALL',
            'group_by' => $groupBy,
            'sales_data' => $salesData,
            'total_count' => count($salesData)
        ];
    }

    /**
     * Get team names for filter
     */
    public function getTeamNames()
    {
        return $this->dal->getDistinctTeamNames();
    }

    /**
     * Get agent names and TSRs
     */
    public function getAgentsTSRMapping()
    {
        return $this->dal->getAgentsTSRMapping();
    }

    /**
     * Get top performers
     */
    public function getTopPerformers($fromDate, $toDate, $limit = 10)
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $performers = $this->dal->getTopPerformers($fromDate, $toDate, $limit);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'top_performers' => $performers,
            'total_count' => count($performers)
        ];
    }

    /**
     * Get bottom performers
     */
    public function getBottomPerformers($fromDate, $toDate, $limit = 10)
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $performers = $this->dal->getBottomPerformers($fromDate, $toDate, $limit);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'bottom_performers' => $performers,
            'total_count' => count($performers)
        ];
    }

    /**
     * Export sales data
     */
    public function exportSalesData($fromDate, $toDate, $team = null)
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $salesData = $this->dal->getDetailedSalesData($fromDate, $toDate, $team);

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'team' => $team ?? 'ALL',
            'sales_data' => $salesData,
            'total_count' => count($salesData)
        ];
    }
}

