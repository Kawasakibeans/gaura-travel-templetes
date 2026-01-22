<?php
/**
 * Agent Performance Service
 * Business logic for agent performance endpoints
 */

namespace App\Services;

use App\DAL\AgentPerformanceDAL;

class AgentPerformanceService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AgentPerformanceDAL();
    }

    /**
     * Get championship/floor data
     */
    public function getChampionshipData(array $params): array
    {
        $fromDate = $params['from_date'] ?? '';
        $toDate = $params['to_date'] ?? '';
        $team = $params['team'] ?? 'ALL';

        if (!$fromDate || !$toDate) {
            throw new \Exception('from_date and to_date are required');
        }

        // Calculate previous period
        $fromDt = new \DateTime($fromDate);
        $toDt = new \DateTime($toDate);
        $interval = $fromDt->diff($toDt)->days + 1;
        $prevFromDt = clone $fromDt;
        $prevToDt = clone $toDt;
        $prevFromDt->modify("-$interval days");
        $prevToDt->modify("-$interval days");
        $prevFromDate = $prevFromDt->format('Y-m-d');
        $prevToDate = $prevToDt->format('Y-m-d');

        // Calculate last 10 days
        $todayDt = new \DateTime($toDate);
        $yesterdayDt = clone $todayDt;
        $yesterdayDt->modify('-1 day');
        $last10FromDt = clone $yesterdayDt;
        $last10FromDt->modify('-9 days');
        $last10From = $last10FromDt->format('Y-m-d');
        $last10To = $yesterdayDt->format('Y-m-d');
        $days = (new \DateTime($last10To))->diff(new \DateTime($last10From))->days + 1;
        if ($days < 1) $days = 1;

        // Fetch data
        $current = $this->dal->getTeamData($fromDate, $toDate, $team);
        $previous = $this->dal->getTeamData($prevFromDate, $prevToDate, $team);
        $teamLast10 = $this->dal->getTeamData($last10From, $last10To, $team);
        $agentCurrent = $this->dal->getAgentData($fromDate, $toDate, $team);
        $agentPrevious = $this->dal->getAgentData($prevFromDate, $prevToDate, $team);
        $agentLast10 = $this->dal->getAgentData($last10From, $last10To, $team);
        $teamTrends = $this->dal->getTeamTrends($fromDate, $toDate, $team);
        $latestBooking = $this->dal->getLatestBooking();
        $gtmdData = $this->dal->getGTMDByTeam($fromDate, $toDate, $team);
        $teamQA = $this->dal->getTeamQACompliance($fromDate, $toDate, $team);
        $agentQA = $this->dal->getAgentQACompliance($fromDate, $toDate, $team);

        // Format team data
        $formattedCurrent = [];
        foreach ($current as $row) {
            $teamName = $row['team_name'];
            $formattedCurrent[] = [
                'team' => $teamName,
                'GTIB' => (int)$row['gtib'],
                'PAX' => (int)$row['pax'],
                'FIT' => (int)$row['fit'],
                'PIF' => (int)$row['pif'],
                'GDEALS' => (int)$row['gdeals'],
                'Conversion' => round((float)$row['conversion'], 4),
                'FCS' => round((float)$row['fcs'], 4),
                'AHT' => gmdate("H:i:s", (int)$row['AHT']),
                'QA' => isset($teamQA[$teamName]) ? $teamQA[$teamName] : null,
                'GTMD' => isset($gtmdData[$teamName]) ? $gtmdData[$teamName] : 0
            ];
        }

        // Format last 10 avg
        $formattedLast10 = [];
        foreach ($teamLast10 as $row) {
            $formattedLast10[] = [
                'team' => $row['team_name'],
                'GTIB' => round($row['gtib'] / $days, 2),
                'PAX' => round($row['pax'] / $days, 2),
                'FIT' => round($row['fit'] / $days, 2),
                'PIF' => round($row['pif'] / $days, 2),
                'GDEALS' => round($row['gdeals'] / $days, 2),
                'Conversion' => round((float)$row['conversion'], 4),
                'FCS' => round((float)$row['fcs'], 4),
                'AHT' => gmdate("H:i:s", (int)$row['AHT'])
            ];
        }

        // Format agent data
        $formattedAgentCurrent = [];
        foreach ($agentCurrent as $row) {
            $agentName = $row['agent_name'];
            $formattedAgentCurrent[] = [
                'team' => $row['team_name'],
                'agent_name' => $agentName,
                'role' => $row['role'],
                'GTIB' => (int)$row['gtib'],
                'PAX' => (int)$row['pax'],
                'Conversion' => round((float)$row['conversion'], 4),
                'FCS' => round((float)$row['fcs'], 4),
                'AHT' => gmdate("H:i:s", (int)$row['AHT']),
                'QA' => isset($agentQA[$agentName]) ? $agentQA[$agentName] : null
            ];
        }

        // Format team trends
        $formattedTrends = [];
        foreach ($teamTrends as $row) {
            $t = $row['team_name'];
            if (!isset($formattedTrends[$t])) {
                $formattedTrends[$t] = ['labels' => [], 'gtib' => [], 'pax' => []];
            }
            $formattedTrends[$t]['labels'][] = $row['day'];
            $formattedTrends[$t]['gtib'][] = (int)$row['gtib'];
            $formattedTrends[$t]['pax'][] = (int)$row['pax'];
        }

        return [
            'current' => $formattedCurrent,
            'previous' => $previous,
            'last10_avg' => $formattedLast10,
            'agent_current' => $formattedAgentCurrent,
            'agent_previous' => $agentPrevious,
            'agent_last10' => $agentLast10,
            'team_trends' => $formattedTrends,
            'latest_booking' => $latestBooking
        ];
    }

    /**
     * Get agent bookings
     */
    public function getAgentBookings(array $params): array
    {
        $agent = $params['agent'] ?? '';
        $from = $params['from_date'] ?? '';
        $to = $params['to_date'] ?? '';

        if (!$agent || !$from || !$to) {
            throw new \Exception('agent, from_date, and to_date are required');
        }

        $rows = $this->dal->getAgentBookings($agent, $from, $to);

        return ['rows' => $rows];
    }

    /**
     * Get agent working time
     */
    public function getAgentWorkingTime(array $params): array
    {
        $agentName = $params['agent_name'] ?? '';
        $teamName = $params['team_name'] ?? '';
        $date = $params['date'] ?? '';

        if (!$agentName || !$teamName || !$date) {
            throw new \Exception('agent_name, team_name, and date are required');
        }

        $data = $this->dal->getAgentWorkingTime($agentName, $teamName, $date);

        if (!$data) {
            throw new \Exception('No data found');
        }

        return ['data' => $data];
    }

    /**
     * Get agent FCS calls
     */
    public function getAgentFCSCalls(array $params): array
    {
        $agentName = $params['agent_name'] ?? '';
        $teamName = $params['team_name'] ?? '';
        $date = $params['date'] ?? '';

        if (!$agentName || !$teamName || !$date) {
            throw new \Exception('agent_name, team_name, and date are required');
        }

        $rows = $this->dal->getAgentFCSCalls($agentName, $teamName, $date);

        return ['rows' => $rows];
    }

    /**
     * Get agent detail
     */
    public function getAgentDetail(array $params): array
    {
        $date = $params['date'] ?? date('Y-m-d');
        $team = $params['team'] ?? 'ALL';

        $agentDetail = $this->dal->getAgentDetail($date, $team);

        return ['agent_detail' => $agentDetail];
    }

    /**
     * Get agent 10-day view data
     */
    public function getAgent10DayView(array $params): array
    {
        $fromDate = $params['from_date'] ?? '';
        $toDate = $params['to_date'] ?? '';
        $team = $params['team'] ?? 'ALL';

        if (!$fromDate || !$toDate) {
            throw new \Exception('from_date and to_date are required');
        }

        $data = $this->dal->getAgent10DayView($fromDate, $toDate, $team);

        // Calculate floor averages
        $totalGTIB = 0;
        $totalPIF = 0;
        $totalSL = 0;
        $totalDuration = 0;
        $totalAudited = 0;
        $totalCompliant = 0;
        $gtibCount = 0;

        foreach ($data as $entry) {
            $totalGTIB += $entry['GTIB'];
            $totalPIF += $entry['PIF'];
            $totalSL += $entry['SL'];
            $totalDuration += $entry['Duration'];
            $totalAudited += $entry['Audited'];
            $totalCompliant += $entry['Compliant'];
            
            if ($entry['GTIB'] > 0) {
                $gtibCount++;
            }
        }

        $floorAverages = [
            'GTIB_total' => $totalGTIB,
            'GTIB' => $gtibCount > 0 ? (int) ceil($totalGTIB / $gtibCount) : 0,
            'Total PIF' => $totalPIF,
            'Count' => $gtibCount,
            'Conversion' => $totalGTIB > 0 ? round($totalPIF / $totalGTIB, 4) : 0,
            'FCS' => $totalGTIB > 0 ? round($totalSL / $totalGTIB, 4) : 0,
            'AHT' => $totalGTIB > 0 ? round($totalDuration / $totalGTIB, 2) : 0,
            'Audited' => $totalAudited,
            'Compliant' => $totalCompliant,
            'GARLAND' => $totalAudited > 0 ? round($totalCompliant / $totalAudited, 4) : 0
        ];

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'data' => $data,
            'floor_averages' => $floorAverages
        ];
    }

    /**
     * Get remarks
     */
    public function getRemarks(array $params): array
    {
        $from = $params['from_date'] ?? date('Y-m-d', strtotime('-50 days'));
        $to = $params['to_date'] ?? date('Y-m-d');

        $remarks = $this->dal->getRemarks($from, $to);

        return ['remarks' => $remarks];
    }

    /**
     * Add remark
     */
    public function addRemark(array $params): array
    {
        $tsr = $params['tsr'] ?? '';
        $date = $params['date'] ?? '';
        $metric = $params['metric'] ?? '';
        $remark = $params['remark'] ?? '';

        if (!$tsr || !$date || !$metric || !$remark) {
            throw new \Exception('tsr, date, metric, and remark are required');
        }

        $success = $this->dal->addRemark($tsr, $date, $metric, $remark);

        if (!$success) {
            throw new \Exception('Failed to add remark');
        }

        return ['message' => 'Remark added successfully'];
    }

    /**
     * Get observation dashboard data
     */
    public function getObservationData(array $params): array
    {
        $date = $params['date'] ?? date('Y-m-d');

        $abandoned = $this->dal->getAbandonedCalls($date);
        $callCounts = $this->dal->getCallCounts($date);
        $durationBuckets = $this->dal->getDurationBuckets($date);
        $keyMetrics = $this->dal->getKeyMetrics($date);

        return [
            'abandoned' => $abandoned ?: [],
            'call_counts' => $callCounts ?: [],
            'duration_buckets' => $durationBuckets ?: [],
            'key_metrics' => $keyMetrics ?: []
        ];
    }
}

