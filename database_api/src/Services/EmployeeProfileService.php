<?php
/**
 * Employee Profile Service
 * Provides structured performance data for employee profiles.
 */

namespace App\Services;

use App\DAL\EmployeeProfileDAL;

class EmployeeProfileService
{
    private EmployeeProfileDAL $dal;

    public function __construct()
    {
        $this->dal = new EmployeeProfileDAL();
    }

    /**
     * Get monthly performance metrics with optional filters:
     * - month (YYYY-MM)
     * - tsr
     */
    public function getMonthlyPerformance(array $filters = []): array
    {
        $rows = $this->dal->getMonthlyPerformance();
        $metadata = $this->buildMetadataMap();

        $monthFilter = $filters['month'] ?? null;
        $tsrFilter = $filters['tsr'] ?? null;

        $results = [];
        foreach ($rows as $row) {
            if ($monthFilter && $row['month'] !== $monthFilter) {
                continue;
            }

            if ($tsrFilter && strcasecmp($row['tsr'], $tsrFilter) !== 0) {
                continue;
            }

            $tsrKey = strtoupper($row['tsr']);
            if (isset($metadata[$tsrKey])) {
                $row['joined_date'] = $metadata[$tsrKey]['doj'] ?? null;
            } else {
                $row['joined_date'] = null;
            }

            $results[] = $row;
        }

        return $results;
    }

    /**
     * Daily performance stats with optional filters:
     * - month (YYYY-MM)
     * - tsr
     */
    public function getDailyPerformance(array $filters = []): array
    {
        $rows = $this->dal->getDailyPerformance();

        $monthFilter = $filters['month'] ?? null;
        $tsrFilter = $filters['tsr'] ?? null;

        $results = [];
        foreach ($rows as $row) {
            if ($monthFilter && $row['month'] !== $monthFilter) {
                continue;
            }

            if ($tsrFilter && strcasecmp($row['tsr'], $tsrFilter) !== 0) {
                continue;
            }

            $results[] = $row;
        }

        return $results;
    }

    /**
     * Gaura Miles transactions (filters: tsr, limit).
     */
    public function getGauraMilesTransactions(array $filters = []): array
    {
        return $this->dal->getGauraMilesTransactions($filters);
    }

    /**
     * Fun facts keyed by agent name.
     */
    public function getFunFacts(): array
    {
        $facts = $this->dal->getFunFacts();
        $map = [];
        foreach ($facts as $row) {
            $key = strtolower(trim($row['agent_name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = $row['fun_facts'];
        }

        return $map;
    }

    /**
     * Build metadata map keyed by TSR for quick lookups.
     */
    private function buildMetadataMap(): array
    {
        $metadataRows = $this->dal->getAgentMetadata();
        $map = [];
        foreach ($metadataRows as $row) {
            if (empty($row['tsr'])) {
                continue;
            }
            $map[strtoupper($row['tsr'])] = $row;
        }

        return $map;
    }
}


