<?php

namespace App\Services;

use App\DAL\AuditorDAL;

/**
 * Service layer for Auditor/QA operations
 */
class AuditorService
{
    private $auditorDAL;

    public function __construct()
    {
        $this->auditorDAL = new AuditorDAL();
    }

    /**
     * Get filter options
     * 
     * @return array
     */
    public function getFilterOptions()
    {
        return $this->auditorDAL->getFilterOptions();
    }

    /**
     * Get dashboard data (scorecard, summaries, detailed records)
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getDashboardData($filters = [], $limit = 25, $offset = 0)
    {
        $scorecard = $this->auditorDAL->getScorecard($filters);
        $auditorSummary = $this->auditorDAL->getAuditorSummary($filters);
        $auditorSummaryCount = $this->auditorDAL->getAuditorSummaryCount($filters);
        $agentSummary = $this->auditorDAL->getAgentSummary($filters);
        $detailedRecords = $this->auditorDAL->getDetailedRecords($filters, $limit, $offset);
        $totalDetailedRecords = $this->auditorDAL->getDetailedRecordsCount($filters);

        // Format scorecard data
        $formattedScorecard = [
            'sl_audited' => (int)($scorecard['sl_audited'] ?? 0),
            'non_sl_audited' => (int)($scorecard['non_sl_audited'] ?? 0),
            'total_audited' => (int)($scorecard['total_audited'] ?? 0),
            'fatal_calls' => (int)($scorecard['fatal_count'] ?? 0),
            'insights' => [
                'fatal_count' => (int)($scorecard['fatal_count'] ?? 0),
                'non_compliant_count' => (int)($scorecard['non_compliant_count'] ?? 0),
                'no_good_call_count' => (int)($scorecard['no_good_call_count'] ?? 0)
            ],
            'coverage_rate' => $scorecard['total_audited'] > 0 
                ? round(($scorecard['total_audited'] / max(1, $scorecard['total_audited'])) * 100, 2)
                : 0
        ];

        return [
            'scorecard' => $formattedScorecard,
            'auditor_summary' => $auditorSummary,
            'auditor_summary_count' => $auditorSummaryCount,
            'agent_summary' => $agentSummary,
            'detailed_records' => $detailedRecords,
            'total_detailed_records' => $totalDetailedRecords
        ];
    }
}

