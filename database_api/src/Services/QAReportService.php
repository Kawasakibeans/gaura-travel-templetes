<?php

namespace App\Services;

use App\DAL\QAReportDAL;
use Exception;

class QAReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new QAReportDAL();
    }

    /**
     * Get filter options
     */
    public function getFilters(?string $location = null): array
    {
        return [
            'qa_users' => array_column($this->dal->getQaUsers(), 'qa_user'),
            'teams' => array_column($this->dal->getTeams($location), 'team_name'),
            'agents' => $this->dal->getAgents($location)
        ];
    }

    /**
     * Get auditor view data
     */
    public function getAuditorViewData(array $params): array
    {
        $date = $params['date'] ?? $params['qa_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format (use YYYY-MM-DD)');
        }

        $qa_user = $params['qa_user'] ?? null;
        $team_name = $params['team_name'] ?? null;
        $agent_id = $params['agent_id'] ?? null;
        $agent_name = $params['agent_name'] ?? null;

        $sl_gtib = $this->dal->getSlGtibCount($date, $team_name, $agent_id);
        $total_gtib = $this->dal->getTotalGtibCount($date, $team_name, $agent_id);
        $sl_audited = $this->dal->getSlAuditedCount($date, $qa_user, $team_name, $agent_id);
        $total_audited = $this->dal->getTotalAudited($date, $qa_user, $team_name, $agent_id, 'GTIB');

        $qa_view = $params['qa_view'] ?? '';
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 100;
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : 0;

        $detailed_records = ($qa_view === 'by_qa_date')
            ? [] // TODO: Implement getDetailedRecordsByQaDate if needed
            : $this->dal->getDetailedRecords($date, $qa_user, $team_name, $agent_name, null, 'GTIB', $limit, $offset);

        $agent_summary = $this->dal->getAgentQaSummary($date, $qa_user, $team_name, $agent_id, 'GTIB');
        $insights = $this->dal->getInsights($date, $qa_user, $team_name, $agent_id, 'GTIB');
        $auditor_summary = $this->dal->getAuditorSummary($date, $qa_user, $team_name, $agent_id, 'GTIB');
        $auditor_summary_count = $this->dal->getAuditorSummaryCount($date);
        $total_detailed_records = $this->dal->getDetailedRecordsCount($date, $qa_user, $team_name, $agent_name, null, 'GTIB');

        $non_sl_gtib = $total_gtib - $sl_gtib;
        $non_sl_audited = $total_audited - $sl_audited;
        $coverage_rate = $total_gtib > 0 ? round(($total_audited / $total_gtib) * 100, 1) : 0;

        // Add coverage percentage to auditor summary
        foreach ($auditor_summary as &$row) {
            $row['coverage'] = $total_gtib > 0
                ? round(($row['total_audited'] / $total_gtib) * 100, 1) . '%'
                : '0%';
        }

        return [
            'sl_gtib' => $sl_gtib,
            'total_gtib' => $total_gtib,
            'non_sl_gtib' => $non_sl_gtib,
            'sl_audited' => $sl_audited,
            'total_audited' => $total_audited,
            'non_sl_audited' => $non_sl_audited,
            'coverage_rate' => $coverage_rate,
            'detailed_records' => $detailed_records,
            'agent_summary' => $agent_summary,
            'insights' => $insights,
            'auditor_summary' => $auditor_summary,
            'auditor_summary_count' => $auditor_summary_count,
            'total_detailed_records' => $total_detailed_records
        ];
    }

    /**
     * Get after-sales auditor view data
     */
    public function getAfterSalesAuditorViewData(array $params): array
    {
        $date = $params['date'] ?? $params['qa_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format (use YYYY-MM-DD)');
        }

        $qa_user = $params['qa_user'] ?? null;
        $team_name = $params['team_name'] ?? null;
        $agent_id = $params['agent_id'] ?? null;
        $agent_name = $params['agent_name'] ?? null;

        $sl_gtib = $this->dal->getSlGtibCount($date, $team_name, $agent_id);
        $total_gtib = $this->dal->getTotalGtibCount($date, $team_name, $agent_id);
        $total_call_count = $this->dal->getTotalCallCount($date, $team_name, $agent_id);
        $sl_audited = $this->dal->getSlAuditedCount($date, $qa_user, $team_name, $agent_id);

        $apps = ['GTCS', 'GTPY', 'GTET', 'GTDC', 'GTRF'];
        $gtpy_audited = $this->dal->getAppAuditedCount($date, 'GTPY', $qa_user);
        $gtcs_audited = $this->dal->getAppAuditedCount($date, 'GTCS', $qa_user);
        $gtet_audited = $this->dal->getAppAuditedCount($date, 'GTET', $qa_user);
        $gtdc_audited = $this->dal->getAppAuditedCount($date, 'GTDC', $qa_user);
        $gtrf_audited = $this->dal->getAppAuditedCount($date, 'GTRF', $qa_user);
        $total_audited = $this->dal->getTotalAudited($date, $qa_user, null, null, $apps);

        $qa_view = $params['qa_view'] ?? '';
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 100;
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : 0;

        $detailed_records = ($qa_view === 'by_qa_date')
            ? [] // TODO: Implement getDetailedRecordsByQaDate if needed
            : $this->dal->getDetailedRecords($date, $qa_user, $team_name, $agent_name, null, $apps, $limit, $offset);

        $dcmd_detailed_records = ($qa_view === 'by_qa_date')
            ? [] // TODO: Implement getDetailedRecordsByQaDate if needed
            : $this->dal->getDetailedRecords($date, $qa_user, $team_name, $agent_name, null, 'DCMD', $limit, $offset);

        $agent_summary = $this->dal->getAgentQaSummary($date, $qa_user, $team_name, $agent_id, $apps);
        $dcmd_summary = $this->dal->getDcmdSummaryByQA($date, $qa_user);
        $insights = $this->dal->getInsights($date, $qa_user, $team_name, $agent_id, $apps);
        $auditor_summary = $this->dal->getAuditorSummary($date, $qa_user, null, null, $apps);
        $auditor_summary_count = $this->dal->getAuditorSummaryCount($date);
        $total_detailed_records = $this->dal->getDetailedRecordsCount($date, $qa_user, $team_name, $agent_name, null, $apps);
        $dcmd_total_detailed_records = $this->dal->getDetailedRecordsCount($date, $qa_user, $team_name, $agent_name, null, 'DCMD');

        $non_sl_gtib = $total_gtib - $sl_gtib;
        $non_sl_audited = $total_audited - $sl_audited;
        $coverage_rate = $total_call_count > 0 ? round(($total_audited / $total_call_count) * 100, 1) : 0;

        return [
            'sl_gtib' => $sl_gtib,
            'total_gtib' => $total_gtib,
            'non_sl_gtib' => $non_sl_gtib,
            'total_call_count' => $total_call_count,
            'sl_audited' => $sl_audited,
            'gtpy_audited' => $gtpy_audited,
            'gtcs_audited' => $gtcs_audited,
            'gtet_audited' => $gtet_audited,
            'gtdc_audited' => $gtdc_audited,
            'gtrf_audited' => $gtrf_audited,
            'total_audited' => $total_audited,
            'non_sl_audited' => $non_sl_audited,
            'coverage_rate' => $coverage_rate,
            'detailed_records' => $detailed_records,
            'dcmd_detailed_records' => $dcmd_detailed_records,
            'agent_summary' => $agent_summary,
            'dcmd_summary' => $dcmd_summary,
            'insights' => $insights,
            'auditor_summary' => $auditor_summary,
            'auditor_summary_count' => $auditor_summary_count,
            'total_detailed_records' => $total_detailed_records,
            'dcmd_total_detailed_records' => $dcmd_total_detailed_records
        ];
    }

    /**
     * Get after-sales agent view data
     */
    public function getAfterSalesAgentViewData(array $params): array
    {
        $date = $params['date'] ?? $params['qa_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format (use YYYY-MM-DD)');
        }

        $qa_user = $params['qa_user'] ?? null;
        $team_name = $params['team_name'] ?? null;
        $agent_id = $params['agent_id'] ?? null;
        $agent_name = $params['agent_name'] ?? null;
        $recording_tsr = $params['recording_tsr'] ?? null;

        $sl_gtib = $this->dal->getSlGtibCount($date, $team_name, $agent_id);
        $total_gtib = $this->dal->getTotalGtibCount($date, $team_name, $agent_id);
        $total_gtcs = $this->dal->getTotalGtibCount($date, $team_name, $agent_id); // Using GTIB query for GTCS/GTPY/GTET/GTRF
        $total_gtdc = $this->dal->getTotalGtdcCount($date, $team_name, $agent_id);
        $total_dcmd = $this->dal->getTotalDcmdCount($date, $team_name, $agent_id);
        $total_calls = $this->dal->getTotalCallCount($date, $team_name, $agent_id);

        $sl_audited = $this->dal->getSlAuditedCount($date, $qa_user, $team_name, $agent_id);
        $apps = ['GTCS', 'GTPY', 'GTET', 'GTDC', 'GTRF', 'DCMD'];
        $total_audited = $this->dal->getTotalAudited($date, $qa_user, $team_name, $agent_id, $apps);
        $total_gtcs_audited = $this->dal->getAppAuditedCountWithFilters($date, 'GTCS', $qa_user, $team_name, $agent_id);
        $total_gtdc_audited = $this->dal->getAppAuditedCountWithFilters($date, 'GTDC', $qa_user, $team_name, $agent_id);
        $total_dcmd_audited = $this->dal->getAppAuditedCountWithFilters($date, 'DCMD', $qa_user, $team_name, $agent_id);

        $qa_view = $params['qa_view'] ?? '';
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 100;
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : 0;

        $detailed_records = ($qa_view === 'by_qa_date')
            ? [] // TODO: Implement getDetailedRecordsByQaDate if needed
            : $this->dal->getDetailedRecords($date, $qa_user, $team_name, $agent_name, $recording_tsr, $apps, $limit, $offset);

        $dcmd_detailed_records = ($qa_view === 'by_qa_date')
            ? [] // TODO: Implement getDetailedRecordsByQaDate if needed
            : $this->dal->getDetailedRecords($date, $qa_user, $team_name, $agent_name, $recording_tsr, 'DCMD', $limit, $offset);

        $agent_summary = $this->dal->getAgentQaSummary($date, $qa_user, $team_name, $agent_id, $apps);
        $dcmd_summary = $this->dal->getDcmdSummary($date, $qa_user, $team_name, $agent_id);
        $insights = $this->dal->getInsights($date, $qa_user, $team_name, $agent_id, $apps);
        $auditor_summary = $this->dal->getAuditorSummary($date, $qa_user, null, null, $apps);
        $total_detailed_records = $this->dal->getDetailedRecordsCount($date, $qa_user, $team_name, $agent_name, $recording_tsr, $apps);
        $dcmd_total_detailed_records = $this->dal->getDetailedRecordsCount($date, $qa_user, $team_name, $agent_name, $recording_tsr, 'DCMD');

        $non_sl_gtib = $total_gtib - $sl_gtib;
        $non_sl_audited = $total_audited - $sl_audited;
        $coverage_rate = $total_calls > 0 ? round(($total_audited / $total_calls) * 100, 1) : 0;

        // Add coverage percentage to auditor summary
        foreach ($auditor_summary as &$row) {
            $row['coverage'] = $total_gtib > 0
                ? round(($row['total_audited'] / $total_gtib) * 100, 1) . '%'
                : '0%';
        }

        return [
            'sl_gtib' => $sl_gtib,
            'total_gtib' => $total_gtib,
            'non_sl_gtib' => $non_sl_gtib,
            'total_gtcs' => $total_gtcs,
            'total_gtdc' => $total_gtdc,
            'total_dcmd' => $total_dcmd,
            'total_calls' => $total_calls,
            'sl_audited' => $sl_audited,
            'total_audited' => $total_audited,
            'total_gtcs_audited' => $total_gtcs_audited,
            'total_gtdc_audited' => $total_gtdc_audited,
            'total_dcmd_audited' => $total_dcmd_audited,
            'non_sl_audited' => $non_sl_audited,
            'coverage_rate' => $coverage_rate,
            'detailed_records' => $detailed_records,
            'dcmd_detailed_records' => $dcmd_detailed_records,
            'agent_summary' => $agent_summary,
            'dcmd_summary' => $dcmd_summary,
            'insights' => $insights,
            'auditor_summary' => $auditor_summary,
            'total_detailed_records' => $total_detailed_records,
            'dcmd_total_detailed_records' => $dcmd_total_detailed_records
        ];
    }
}

