<?php

namespace App\Services;

use App\DAL\AfterSalesCallMetricsDAL;
use Exception;

class AfterSalesCallMetricsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AfterSalesCallMetricsDAL();
    }

    /**
     * Get after-sales call metrics
     */
    public function getAfterSalesCallMetrics(?string $startDate, ?string $endDate, ?string $agentName = null): array
    {
        // Validate date format if provided
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('start_date must be in YYYY-MM-DD format', 400);
        }

        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new Exception('end_date must be in YYYY-MM-DD format', 400);
        }

        return $this->dal->getAfterSalesCallMetrics($startDate, $endDate, $agentName);
    }

    /**
     * Get agent data for a specific date
     */
    public function getAgentDataByDate(string $date, ?string $agentName = null): array
    {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('date must be in YYYY-MM-DD format', 400);
        }

        return $this->dal->getAgentDataByDate($date, $agentName);
    }

    /**
     * Get distinct agents list
     */
    public function getDistinctAgents(): array
    {
        $agents = $this->dal->getDistinctAgents();
        return array_column($agents, 'agent_name');
    }
}