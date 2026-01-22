<?php

namespace App\Services;

use App\DAL\IncentiveDAL;

/**
 * Service layer for Incentive operations
 */
class IncentiveService
{
    private $incentiveDAL;

    public function __construct()
    {
        $this->incentiveDAL = new IncentiveDAL();
    }

    /**
     * Get daily performance data for incentive period
     * 
     * @param string $fromDate Start date (YYYY-MM-DD)
     * @param string $toDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getDailyPerformance($fromDate, $toDate)
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new \Exception('from_date and to_date are required', 400);
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            throw new \Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }

        return $this->incentiveDAL->getDailyPerformance($fromDate, $toDate);
    }

    /**
     * Get agent-wise data for a specific date
     * 
     * @param string $date Date (YYYY-MM-DD)
     * @return array
     */
    public function getAgentDataByDate($date)
    {
        if (empty($date)) {
            throw new \Exception('date is required', 400);
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \Exception('Invalid date format. Use YYYY-MM-DD', 400);
        }

        return $this->incentiveDAL->getAgentDataByDate($date);
    }
}

