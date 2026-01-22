<?php
/**
 * Marketing Service
 * Business logic for marketing endpoints
 */

namespace App\Services;

use App\DAL\MarketingDAL;

class MarketingService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new MarketingDAL();
    }

    /**
     * Generate monthly intervals
     */
    private function generateMonthlyIntervals(string $fromDate, string $toDate): array
    {
        $start = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $intervals = [];

        while ($start <= $end) {
            $monthStart = $start->format('Y-m-01');
            $monthEnd = $start->format('Y-m-t');
            $label = $start->format('M-y');
            $intervals[$label] = [$monthStart, $monthEnd];
            $start->modify('+1 month');
        }

        return $intervals;
    }

    /**
     * Generate 10-day intervals
     */
    private function generate10DayIntervals(string $fromDate, string $toDate): array
    {
        $end = new \DateTime($toDate);
        $intervals = [];

        while ($end >= new \DateTime($fromDate)) {
            $intervalEnd = clone $end;
            $endDay = (int)$intervalEnd->format('j');
            $startDay = ($endDay >= 21) ? 21 : (($endDay >= 11) ? 11 : 1);
            $intervalStart = \DateTime::createFromFormat('Y-m-d', $intervalEnd->format("Y-m-") . str_pad($startDay, 2, '0', STR_PAD_LEFT));

            if ($intervalStart > $intervalEnd) {
                $intervalEnd = clone $intervalStart;
                $intervalEnd->modify('-1 day');
                $endDay = (int)$intervalEnd->format('j');
                $startDay = ($endDay >= 21) ? 21 : (($endDay >= 11) ? 11 : 1);
                $intervalStart = \DateTime::createFromFormat('Y-m-d', $intervalEnd->format("Y-m-") . str_pad($startDay, 2, '0', STR_PAD_LEFT));
            }

            $label = $intervalStart->format('j') . '-' . $intervalEnd->format('j') . ' ' . strtoupper($intervalEnd->format('M'));
            $intervals[$label] = [$intervalStart->format('Y-m-d'), $intervalEnd->format('Y-m-d')];

            $end = clone $intervalStart;
            $end->modify('-1 day');
        }

        return array_reverse($intervals);
    }

    /**
     * Calculate marketing metrics
     */
    private function calculateMetrics(array $row): array
    {
        $spends = (float)($row['Spends'] ?? 0);
        $impression = (float)($row['Impression'] ?? 0);
        $clicks = (float)($row['Clicks'] ?? 0);
        $users = (float)($row['Users'] ?? 0);
        $pax = (float)($row['Pax'] ?? 0);
        $pif = (float)($row['PIF'] ?? 0);
        $tickets = (float)($row['Tickets'] ?? 0);

        return [
            'CPM' => $impression ? round($spends / $impression * 1000, 2) : 0,
            'CPC' => $clicks ? round($spends / $clicks, 2) : 0,
            'CTR' => $impression ? round($clicks / $impression, 4) : 0,
            'UserRate' => $clicks ? round($users / $clicks, 4) : 0,
            'CostPerUser' => $users ? round($spends / $users, 2) : 0,
            'CostPerPax' => $pax ? round($spends / $pax, 2) : 0,
            'CostPerPIF' => $pif ? round($spends / $pif, 2) : 0,
            'CostPerTicket' => $tickets ? round($spends / $tickets, 2) : 0
        ];
    }

    /**
     * Get monthly marketing data
     */
    public function getMonthlyData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-01-01';
        $endDate = $params['end_date'] ?? '2025-06-30';
        $channels = isset($params['channels']) && is_array($params['channels']) 
            ? array_filter($params['channels']) 
            : [];

        $intervals = $this->generateMonthlyIntervals($startDate, $endDate);
        $intervalData = [];

        foreach ($intervals as $label => [$start, $end]) {
            $rows = $this->dal->getMonthlyData($start, $end, $channels);

            foreach ($rows as $row) {
                $source = $row['source'] ?? 'All';
                $channel = $row['channels'] ?? 'All';
                $spends = (float)($row['Spends'] ?? 0);
                $impression = (float)($row['Impression'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $users = (float)($row['Users'] ?? 0);
                $pax = (float)($row['Pax'] ?? 0);
                $pif = (float)($row['PIF'] ?? 0);
                $gtib = (float)($row['gtib'] ?? 0);
                $tickets = (float)($row['Tickets'] ?? 0);

                $metrics = $this->calculateMetrics($row);

                $intervalData[] = array_merge([
                    'Interval' => $label,
                    'Source' => $source,
                    'Channel' => $channel,
                    'Impression' => (int)$impression,
                    'Clicks' => (int)$clicks,
                    'Spends' => round($spends, 2),
                    'Users' => (int)$users,
                    'Pax' => (int)$pax,
                    'PIF' => (int)$pif,
                    'gtib' => (int)$gtib,
                    'Tickets' => (int)$tickets,
                    'Revenue' => round((float)($row['Revenue'] ?? 0), 2)
                ], $metrics);
            }
        }

        // Get total summary
        $summary = $this->dal->getMonthlySummary($startDate, $endDate, $channels);
        $totalSummary = ['Interval' => 'TOTAL'];
        
        if ($summary) {
            $spends = (float)($summary['Spends'] ?? 0);
            $impression = (float)($summary['Impression'] ?? 0);
            $clicks = (float)($summary['Clicks'] ?? 0);
            $users = (float)($summary['Users'] ?? 0);
            $pax = (float)($summary['Pax'] ?? 0);
            $pif = (float)($summary['PIF'] ?? 0);
            $gtib = (float)($summary['gtib'] ?? 0);
            $tickets = (float)($summary['Tickets'] ?? 0);

            $metrics = $this->calculateMetrics($summary);

            $totalSummary = array_merge($totalSummary, [
                'Impression' => (int)$impression,
                'Clicks' => (int)$clicks,
                'Spends' => round($spends, 2),
                'Users' => (int)$users,
                'Pax' => (int)$pax,
                'PIF' => (int)$pif,
                'gtib' => (int)$gtib,
                'Tickets' => (int)$tickets,
                'Revenue' => round((float)($summary['Revenue'] ?? 0), 2)
            ], $metrics);
        }

        return [
            'intervalData' => $intervalData,
            'totalSummary' => $totalSummary
        ];
    }

    /**
     * Get 10-day marketing data
     */
    public function get10DayData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-01-01';
        $endDate = $params['end_date'] ?? '2025-03-31';
        $channels = isset($params['channels']) && is_array($params['channels']) 
            ? array_filter($params['channels']) 
            : [];

        $intervals = $this->generate10DayIntervals($startDate, $endDate);
        $intervalData = [];

        foreach ($intervals as $label => [$start, $end]) {
            $rows = $this->dal->get10DayData($start, $end, $channels);

            foreach ($rows as $row) {
                $spends = (float)($row['Spends'] ?? 0);
                $impression = (float)($row['Impression'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $users = (float)($row['Users'] ?? 0);
                $pax = (float)($row['Pax'] ?? 0);
                $pif = (float)($row['PIF'] ?? 0);
                $tickets = (float)($row['Tickets'] ?? 0);

                $metrics = $this->calculateMetrics($row);

                $intervalData[] = array_merge([
                    'Interval' => $label,
                    'Source' => $row['source'] ?? '',
                    'Channel' => $row['channels'] ?? '',
                    'Impression' => (int)$impression,
                    'Clicks' => (int)$clicks,
                    'Spends' => round($spends, 2),
                    'Users' => (int)$users,
                    'Pax' => (int)$pax,
                    'PIF' => (int)$pif,
                    'Tickets' => (int)$tickets,
                    'Revenue' => round((float)($row['Revenue'] ?? 0), 2)
                ], $metrics);
            }
        }

        // Get total summary
        $summary = $this->dal->get10DaySummary($startDate, $endDate, $channels);
        $totalSummary = ['Interval' => 'TOTAL'];
        
        if ($summary) {
            $spends = (float)($summary['Spends'] ?? 0);
            $impression = (float)($summary['Impression'] ?? 0);
            $clicks = (float)($summary['Clicks'] ?? 0);
            $users = (float)($summary['Users'] ?? 0);
            $pax = (float)($summary['Pax'] ?? 0);
            $pif = (float)($summary['PIF'] ?? 0);
            $tickets = (float)($summary['Tickets'] ?? 0);

            $metrics = $this->calculateMetrics($summary);

            $totalSummary = array_merge($totalSummary, [
                'Impression' => (int)$impression,
                'Clicks' => (int)$clicks,
                'Spends' => round($spends, 2),
                'Users' => (int)$users,
                'Pax' => (int)$pax,
                'PIF' => (int)$pif,
                'Tickets' => (int)$tickets,
                'Revenue' => round((float)($summary['Revenue'] ?? 0), 2)
            ], $metrics);
        }

        return [
            'intervalData' => $intervalData,
            'totalSummary' => $totalSummary
        ];
    }

    /**
     * Get 10-day data by source
     */
    public function get10DayDataBySource(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-01-01';
        $endDate = $params['end_date'] ?? '2025-03-31';
        $source = $params['source'] ?? '';

        $intervals = $this->generate10DayIntervals($startDate, $endDate);
        $intervalData = [];

        foreach ($intervals as $label => [$start, $end]) {
            $rows = $this->dal->get10DayDataBySource($start, $end, $source);

            foreach ($rows as $row) {
                $spends = (float)($row['Spends'] ?? 0);
                $impression = (float)($row['Impression'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $users = (float)($row['Users'] ?? 0);
                $pax = (float)($row['Pax'] ?? 0);
                $pif = (float)($row['PIF'] ?? 0);
                $tickets = (float)($row['Tickets'] ?? 0);

                $metrics = $this->calculateMetrics($row);

                $intervalData[] = array_merge([
                    'Interval' => $label,
                    'Source' => $row['source'] ?? '',
                    'Impression' => (int)$impression,
                    'Clicks' => (int)$clicks,
                    'Spends' => round($spends, 2),
                    'Users' => (int)$users,
                    'Pax' => (int)$pax,
                    'PIF' => (int)$pif,
                    'Tickets' => (int)$tickets,
                    'Revenue' => round((float)($row['Revenue'] ?? 0), 2)
                ], $metrics);
            }
        }

        return ['intervalData' => $intervalData];
    }

    /**
     * Get monthly comparison data
     */
    public function getMonthlyComparison(array $params): array
    {
        $selectedMonth = $params['selected_month'] ?? date('Y-m');
        $channels = isset($params['channels']) && is_array($params['channels']) 
            ? array_filter($params['channels']) 
            : [];
        $category = $params['category'] ?? '';

        $base = new \DateTime($selectedMonth . '-01');
        $selectedLabel = $base->format('M-y');
        $prevLabel = (clone $base)->modify('-1 month')->format('M-y');
        $lastYearLabel = (clone $base)->modify('-1 year')->format('M-y');

        $results = $this->dal->getMonthlyComparison($selectedMonth, $channels, $category);
        $intervalData = [];

        $labels = [
            'selected' => $selectedLabel,
            'prev' => $prevLabel,
            'lastYear' => $lastYearLabel
        ];

        foreach ($results as $period => $rows) {
            foreach ($rows as $row) {
                $spends = (float)($row['Spends'] ?? 0);
                $impression = (float)($row['Impression'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $users = (float)($row['Users'] ?? 0);
                $pax = (float)($row['Pax'] ?? 0);
                $pif = (float)($row['PIF'] ?? 0);
                $tickets = (float)($row['Tickets'] ?? 0);

                $metrics = $this->calculateMetrics($row);

                $intervalData[] = array_merge([
                    'Interval' => $labels[$period] ?? $period,
                    'Source' => $row['source'] ?? '',
                    'Channel' => $row['channels'] ?? '',
                    'Impression' => (int)$impression,
                    'Clicks' => (int)$clicks,
                    'Spends' => round($spends, 2),
                    'Users' => (int)$users,
                    'Pax' => (int)$pax,
                    'PIF' => (int)$pif,
                    'Tickets' => (int)$tickets,
                    'Revenue' => round((float)($row['Revenue'] ?? 0), 2)
                ], $metrics);
            }
        }

        // Get total summary
        $summary = $this->dal->getMonthlyComparisonSummary($selectedMonth, $channels, $category);
        $totalSummary = ['Interval' => 'TOTAL'];
        
        if ($summary) {
            $spends = (float)($summary['Spends'] ?? 0);
            $impression = (float)($summary['Impression'] ?? 0);
            $clicks = (float)($summary['Clicks'] ?? 0);
            $users = (float)($summary['Users'] ?? 0);
            $pax = (float)($summary['Pax'] ?? 0);
            $pif = (float)($summary['PIF'] ?? 0);
            $tickets = (float)($summary['Tickets'] ?? 0);

            $metrics = $this->calculateMetrics($summary);

            $totalSummary = array_merge($totalSummary, [
                'Impression' => (int)$impression,
                'Clicks' => (int)$clicks,
                'Spends' => round($spends, 2),
                'Users' => (int)$users,
                'Pax' => (int)$pax,
                'PIF' => (int)$pif,
                'Tickets' => (int)$tickets,
                'Revenue' => round((float)($summary['Revenue'] ?? 0), 2)
            ], $metrics);
        }

        return [
            'intervalData' => $intervalData,
            'totalSummary' => $totalSummary
        ];
    }

    /**
     * Fetch marketing remarks
     */
    public function fetchRemarks(array $params): array
    {
        $channel = $params['channel'] ?? '';
        $metric = $params['metric'] ?? '';
        $period = $params['period'] ?? '';

        if (!$channel || !$metric || !$period) {
            throw new \Exception('channel, metric, and period are required', 400);
        }

        // Convert period to date range
        list($periodStart, $periodEnd) = $this->convertPeriodToRange($period);

        if (!$periodStart || !$periodEnd) {
            throw new \Exception('Invalid period format. Expected format: "11-20 MAR" or "Jan-25"', 400);
        }

        $remarks = $this->dal->fetchRemarks($channel, $metric, $periodStart, $periodEnd);

        if (empty($remarks)) {
            return ['remark' => 'No remarks found.'];
        }

        $combinedRemarks = '';
        foreach ($remarks as $r) {
            $author = htmlspecialchars($r['created_by'] ?? 'Unknown');
            $textObservation = htmlspecialchars($r['observation'] ?? '', ENT_QUOTES | ENT_HTML5);
            $text = htmlspecialchars($r['remark'] ?? '', ENT_QUOTES | ENT_HTML5);
            $remarkType = htmlspecialchars($r['remark_type'] ?? 'N/A');
            $impactType = htmlspecialchars($r['impact_type'] ?? 'N/A');
            $startDate = $r['start_date'] ?? '';
            $endDate = $r['end_date'] ?? '';

            $combinedRemarks .= "<strong>Date Range:</strong> $startDate to $endDate<br><br>";
            $combinedRemarks .= "<strong>Remark Type:</strong> $remarkType<br><br>";
            $combinedRemarks .= "<strong>Impact Type:</strong> $impactType<br><br>";
            $combinedRemarks .= "<div style='margin:5px 0 10px 0;'><strong>Observation:</strong> $textObservation<br><br>";
            $combinedRemarks .= "<div style='margin:5px 0 10px 0;'><strong>Remark:</strong> $text</div>";
            $combinedRemarks .= "<hr style='border:none; border-top:1px solid #ccc; margin:8px 0;'>";
        }

        return ['remark' => $combinedRemarks];
    }

    /**
     * Insert marketing remark
     */
    public function insertRemark(array $params): array
    {
        $required = ['channel', 'observation', 'remark', 'remark_type', 'metric_impact', 'impact_type', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \Exception("$field is required", 400);
            }
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['start_date'])) {
            throw new \Exception('start_date must be in YYYY-MM-DD format', 400);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['end_date'])) {
            throw new \Exception('end_date must be in YYYY-MM-DD format', 400);
        }

        $createdBy = $params['created_by'] ?? 'system';

        $remarkId = $this->dal->insertRemark(
            $params['channel'],
            $params['observation'],
            $params['remark'],
            $params['remark_type'],
            $params['metric_impact'],
            $params['impact_type'],
            $params['start_date'],
            $params['end_date'],
            $createdBy
        );

        return [
            'success' => true,
            'remark_id' => $remarkId,
            'message' => 'Remark inserted successfully'
        ];
    }

    /**
     * Fetch comparison remarks
     */
    public function fetchComparisonRemarks(array $params): array
    {
        $channel = $params['channel'] ?? '';
        $metric = $params['metric'] ?? '';
        $period = $params['period'] ?? '';

        if (!$channel || !$metric || !$period) {
            throw new \Exception('channel, metric, and period are required', 400);
        }

        // Convert monthly period to date range
        list($periodStart, $periodEnd) = $this->convertMonthlyPeriodToRange($period);

        if (!$periodStart || !$periodEnd) {
            throw new \Exception('Invalid period format. Expected format: "Jan-25"', 400);
        }

        $remarks = $this->dal->fetchComparisonRemarks($channel, $metric, $periodStart, $periodEnd);

        if (empty($remarks)) {
            return ['remark' => 'No remarks found.'];
        }

        $combinedRemarks = '';
        foreach ($remarks as $r) {
            $author = htmlspecialchars($r['created_by'] ?? 'Unknown');
            $text = htmlspecialchars($r['remark'] ?? '', ENT_QUOTES | ENT_HTML5);
            $remarkType = htmlspecialchars($r['remark_type'] ?? 'N/A');
            $impactType = htmlspecialchars($r['impact_type'] ?? 'N/A');
            $startDate = $r['start_date'] ?? '';
            $endDate = $r['end_date'] ?? '';
            $createdAt = $r['created_at'] ?? '';

            $combinedRemarks .= "<strong>Date Range:</strong> $startDate to $endDate<br>";
            $combinedRemarks .= "<strong>Created At:</strong> $createdAt<br>";
            $combinedRemarks .= "<strong>By:</strong> $author<br>";
            $combinedRemarks .= "<strong>Remark Type:</strong> $remarkType<br>";
            $combinedRemarks .= "<strong>Impact Type:</strong> $impactType<br>";
            $combinedRemarks .= "<div style='margin:5px 0 10px 0;'><strong>Remark:</strong> $text</div>";
            $combinedRemarks .= "<hr style='border:none; border-top:1px solid #ccc; margin:8px 0;'>";
        }

        return ['remark' => $combinedRemarks];
    }

    /**
     * Get Google Ads campaign data
     */
    public function getGoogleAdsCampaignData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-01-01';
        $endDate = $params['end_date'] ?? '2025-06-16';
        $category = $params['category'] ?? '';
        $campaign = $params['campaign'] ?? '';
        $channels = isset($params['channels']) && is_array($params['channels']) 
            ? array_filter($params['channels']) 
            : [];

        $intervals = $this->generate10DayIntervals($startDate, $endDate);
        $intervalData = [];

        foreach ($intervals as $label => [$intervalStart, $intervalEnd]) {
            $rows = $this->dal->getGoogleAdsCampaignData(
                $intervalStart,
                $intervalEnd,
                $category,
                $campaign,
                $channels
            );

            foreach ($rows as $row) {
                $impressions = (float)($row['Impressions'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $cost = (float)($row['Cost'] ?? 0);
                $engagements = (float)($row['Engagements'] ?? 0);
                $conversions = (float)($row['Conversions'] ?? 0);
                $convValue = (float)($row['Conversions_value'] ?? 0);

                $cpm = $impressions ? round($cost / $impressions * 1000, 2) : 0;
                $cpc = $clicks ? round($cost / $clicks, 2) : 0;
                $ctr = $impressions ? round($clicks / $impressions, 4) : 0;
                $roi = $cost ? round(($convValue - $cost) / $cost, 4) : 0;

                $intervalData[] = [
                    'Interval' => $label,
                    'Campaign' => $row['Campaign_name'] ?? '',
                    'Campaign Category' => $row['Campaign_Categories'] ?? '',
                    'Impressions' => (int)$impressions,
                    'Clicks' => (int)$clicks,
                    'Cost' => round($cost, 2),
                    'Engagements' => (int)$engagements,
                    'Conversions' => (int)$conversions,
                    'Conv. Value' => round($convValue, 2),
                    'CPM' => $cpm,
                    'CPC' => $cpc,
                    'CTR' => $ctr,
                    'ROI' => $roi
                ];
            }
        }

        return ['intervalData' => $intervalData];
    }

    /**
     * Get Google Ads ad group data
     */
    public function getGoogleAdsAdGroupData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-04-01';
        $endDate = $params['end_date'] ?? '2025-05-31';
        $category = $params['category'] ?? '';
        $campaign = $params['campaign'] ?? '';
        $adGroup = $params['ad_group'] ?? '';

        $intervals = $this->generate10DayIntervals($startDate, $endDate);
        $intervalData = [];

        foreach ($intervals as $label => [$intervalStart, $intervalEnd]) {
            $rows = $this->dal->getGoogleAdsAdGroupData(
                $intervalStart,
                $intervalEnd,
                $category,
                $campaign,
                $adGroup
            );

            foreach ($rows as $row) {
                $impressions = (float)($row['Impressions'] ?? 0);
                $clicks = (float)($row['Clicks'] ?? 0);
                $cost = (float)($row['Cost'] ?? 0);
                $engagements = (float)($row['Engagements'] ?? 0);
                $conversions = (float)($row['Conversions'] ?? 0);
                $convValue = (float)($row['Conversions_value'] ?? 0);

                $cpm = $impressions ? round($cost / $impressions * 1000, 2) : 0;
                $cpc = $clicks ? round($cost / $clicks, 2) : 0;
                $ctr = $impressions ? round($clicks / $impressions, 4) : 0;
                $roi = $cost ? round(($convValue - $cost) / $cost, 4) : 0;

                $intervalData[] = [
                    'Interval' => $label,
                    'Campaign' => $row['Campaign_name'] ?? '',
                    'Campaign Category' => $row['Campaign_Categories'] ?? '',
                    'Ad Group' => $row['Ad_group_name'] ?? '',
                    'Impressions' => (int)$impressions,
                    'Clicks' => (int)$clicks,
                    'Cost' => round($cost, 2),
                    'Engagements' => (int)$engagements,
                    'Conversions' => (int)$conversions,
                    'Conv. Value' => round($convValue, 2),
                    'CPM' => $cpm,
                    'CPC' => $cpc,
                    'CTR' => $ctr,
                    'ROI' => $roi
                ];
            }
        }

        return ['intervalData' => $intervalData];
    }

    /**
     * Convert period string to date range (for 10-day periods like "11-20 MAR")
     */
    private function convertPeriodToRange(string $period): array
    {
        if (preg_match('/(\d+)-(\d+)\s([A-Z]+)/', strtoupper($period), $m)) {
            $year = date('Y');
            $startDay = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $endDay = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $month = $m[3];

            $startDate = date("Y-m-d", strtotime("$startDay-$month-$year"));
            $endDate = date("Y-m-d", strtotime("$endDay-$month-$year"));
            return [$startDate, $endDate];
        }
        return [null, null];
    }

    /**
     * Convert monthly period string to date range (for monthly periods like "Jan-25")
     */
    private function convertMonthlyPeriodToRange(string $period): array
    {
        $date = \DateTime::createFromFormat('M-y', $period);
        if (!$date) {
            return [null, null];
        }
        $startDate = $date->format('Y-m-01');
        $endDate = $date->format('Y-m-t');
        return [$startDate, $endDate];
    }

    /**
     * Get all campaigns
     */
    public function getAllCampaigns(): array
    {
        return $this->dal->getAllCampaigns();
    }

    /**
     * Get campaign by ID
     */
    public function getCampaignById($campaignId): ?array
    {
        return $this->dal->getCampaignById($campaignId);
    }

    /**
     * Get campaigns by group ID
     */
    public function getCampaignsByGroupId($groupId): array
    {
        return $this->dal->getCampaignsByGroupId($groupId);
    }

    /**
     * Create new campaign
     */
    public function createCampaign(array $data): array
    {
        $required = ['campaign_name', 'start_date', 'end_date', 'campaign_owner'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("$field is required", 400);
            }
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) {
            throw new \Exception('start_date must be in YYYY-MM-DD format', 400);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['end_date'])) {
            throw new \Exception('end_date must be in YYYY-MM-DD format', 400);
        }

        $campaignId = $this->dal->createCampaign($data);
        
        return [
            'success' => true,
            'campaign_id' => $campaignId,
            'message' => 'Campaign created successfully'
        ];
    }

    /**
     * Update campaign field
     */
    public function updateCampaignField($campaignId, $field, $value): array
    {
        if (empty($campaignId) || empty($field)) {
            throw new \Exception('campaign_id and field are required', 400);
        }

        $success = $this->dal->updateCampaignField($campaignId, $field, $value);
        
        return [
            'success' => $success,
            'message' => $success ? 'Campaign updated successfully' : 'Failed to update campaign'
        ];
    }

    /**
     * Get campaign change log
     */
    public function getCampaignChangeLog($campaignId): array
    {
        if (empty($campaignId)) {
            throw new \Exception('campaign_id is required', 400);
        }

        return $this->dal->getCampaignChangeLog($campaignId);
    }

    /**
     * Create campaign change log entry
     */
    public function createCampaignChangeLog(array $data): array
    {
        $required = ['campaign_id', 'change_type_id', 'changed_by', 'notes'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("$field is required", 400);
            }
        }

        $logId = $this->dal->createCampaignChangeLog($data);
        
        return [
            'success' => true,
            'log_id' => $logId,
            'message' => 'Change log entry created successfully'
        ];
    }

    /**
     * Get all channels
     */
    public function getAllChannels(): array
    {
        return $this->dal->getAllChannels();
    }

    /**
     * Get groups by channel ID
     */
    public function getGroupsByChannelId($channelId): array
    {
        if (empty($channelId)) {
            throw new \Exception('channel_id is required', 400);
        }

        return $this->dal->getGroupsByChannelId($channelId);
    }

    /**
     * Get all change types
     */
    public function getAllChangeTypes(): array
    {
        $changeTypes = $this->dal->getAllChangeTypes();
        
        // Group by category
        $grouped = [];
        foreach ($changeTypes as $type) {
            $category = $type['category'] ?? 'Other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $type;
        }
        
        return $grouped;
    }
}

