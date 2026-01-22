<?php
/**
 * Marketing Data Access Layer
 * Handles database operations for marketing data, remarks, and Google Ads
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class MarketingDAL extends BaseDAL
{
    /**
     * Get monthly marketing data with intervals
     */
    public function getMonthlyData(string $startDate, string $endDate, array $channels = []): array
    {
        $channelFilter = '';
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND channels IN (" . implode(',', $placeholders) . ")";
        }
        
        $selectFields = !empty($channels) ? "source, channels," : "";
        $groupBy = !empty($channels) ? "GROUP BY source, channels" : "";
        
        $sql = "
            SELECT 
                {$selectFields}
                SUM(impression) AS Impression,
                SUM(clicks) AS Clicks,
                SUM(spends) AS Spends,
                MAX(overall_monthly_users) AS Users,
                MAX(monthly_pax) AS Pax,
                MAX(monthly_pif) AS PIF,
                MAX(monthly_call) AS gtib,
                SUM(tickets) AS Tickets,
                SUM(revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data
            WHERE date BETWEEN :start AND :end
            {$channelFilter}
            {$groupBy}
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get monthly summary totals
     */
    public function getMonthlySummary(string $startDate, string $endDate, array $channels = []): ?array
    {
        $channelFilter = '';
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND channels IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql = "
            SELECT 
                SUM(impression) AS Impression,
                SUM(clicks) AS Clicks,
                SUM(spends) AS Spends,
                SUM(DISTINCT monthly_user) AS Users,
                MAX(monthly_pax) AS Pax,
                MAX(monthly_pif) AS PIF,
                MAX(monthly_call) AS gtib,
                SUM(tickets) AS Tickets,
                SUM(revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data
            WHERE date BETWEEN :start AND :end
            {$channelFilter}
        ";
        
        $result = $this->queryOne($sql, $params);
        return ($result === false) ? null : $result;
    }

    /**
     * Get 10-day interval marketing data
     */
    public function get10DayData(string $startDate, string $endDate, array $channels = []): array
    {
        $channelFilter = '';
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND channels IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql = "
            SELECT 
                source,
                channels,
                SUM(impression) AS Impression,
                SUM(clicks) AS Clicks,
                SUM(spends) AS Spends,
                SUM(users) AS Users,
                SUM(pax) AS Pax,
                SUM(pif) AS PIF,
                SUM(tickets) AS Tickets,
                SUM(revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data
            WHERE date BETWEEN :start AND :end
            {$channelFilter}
            GROUP BY source, channels
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get 10-day summary totals
     */
    public function get10DaySummary(string $startDate, string $endDate, array $channels = []): ?array
    {
        $channelFilter = '';
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND channels IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql = "
            SELECT 
                SUM(impression) AS Impression,
                SUM(clicks) AS Clicks,
                SUM(spends) AS Spends,
                SUM(users) AS Users,
                SUM(pax) AS Pax,
                SUM(pif) AS PIF,
                SUM(tickets) AS Tickets,
                SUM(revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data
            WHERE date BETWEEN :start AND :end
            {$channelFilter}
        ";
        
        $result = $this->queryOne($sql, $params);
        return ($result === false) ? null : $result;
    }

    /**
     * Get 10-day data by source/category
     */
    public function get10DayDataBySource(string $startDate, string $endDate, string $source = ''): array
    {
        $params = [':start' => $startDate, ':end' => $endDate];
        $sourceFilter = '';
        
        if (!empty($source)) {
            $sourceFilter = " AND c.category_name = :source";
            $params[':source'] = $source;
        }
        
        $sql = "
            SELECT 
                c.category_name AS source,
                SUM(b.impression) AS Impression,
                SUM(b.clicks) AS Clicks,
                SUM(b.spends) AS Spends,
                SUM(b.users) AS Users,
                SUM(b.pax) AS Pax,
                SUM(b.pif) AS PIF,
                SUM(b.tickets) AS Tickets,
                SUM(b.revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data b
            LEFT JOIN wpk4_backend_marketing_channel a ON a.channel_name = b.Channels
            LEFT JOIN wpk4_backend_marketing_category c ON a.category_id = c.id
            WHERE b.date BETWEEN :start AND :end
            {$sourceFilter}
            GROUP BY c.category_name
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get monthly comparison data
     */
    public function getMonthlyComparison(string $selectedMonth, array $channels = [], string $category = ''): array
    {
        $base = new \DateTime($selectedMonth . '-01');
        $selectedStart = $base->format('Y-m-01');
        $selectedEnd = $base->format('Y-m-t');
        
        $prev = (clone $base)->modify('-1 month');
        $prevStart = $prev->format('Y-m-01');
        $prevEnd = $prev->format('Y-m-t');
        
        $lastYear = (clone $base)->modify('-1 year');
        $lastYearStart = $lastYear->format('Y-m-01');
        $lastYearEnd = $lastYear->format('Y-m-t');
        
        $channelFilter = '';
        $params = [];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND md.channels IN (" . implode(',', $placeholders) . ")";
        } elseif (!empty($category)) {
            $channelFilter = " AND md.channels IN (
                SELECT channel_name FROM wpk4_backend_marketing_channel WHERE category_id = :category
            )";
            $params[':category'] = $category;
        }
        
        $intervals = [
            'selected' => [$selectedStart, $selectedEnd],
            'prev' => [$prevStart, $prevEnd],
            'lastYear' => [$lastYearStart, $lastYearEnd]
        ];
        
        $results = [];
        foreach ($intervals as $period => [$start, $end]) {
            $sql = "
                SELECT 
                    md.source,
                    md.channels,
                    SUM(md.impression) AS Impression,
                    SUM(md.clicks) AS Clicks,
                    SUM(md.spends) AS Spends,
                    SUM(DISTINCT md.monthly_user) AS Users,
                    SUM(md.pax) AS Pax,
                    SUM(md.pif) AS PIF,
                    SUM(md.tickets) AS Tickets,
                    SUM(md.revenue) AS Revenue
                FROM wpk4_backend_marketing_master_data md
                WHERE md.date BETWEEN :start AND :end
                {$channelFilter}
                GROUP BY md.source, md.channels
            ";
            
            $intervalParams = array_merge([':start' => $start, ':end' => $end], $params);
            $results[$period] = $this->query($sql, $intervalParams);
        }
        
        return $results;
    }

    /**
     * Get monthly comparison summary
     */
    public function getMonthlyComparisonSummary(string $selectedMonth, array $channels = [], string $category = ''): ?array
    {
        $base = new \DateTime($selectedMonth . '-01');
        $lastYearStart = (clone $base)->modify('-1 year')->format('Y-m-01');
        $selectedEnd = $base->format('Y-m-t');
        
        $channelFilter = '';
        $params = [];
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $channelFilter = " AND md.channels IN (" . implode(',', $placeholders) . ")";
        } elseif (!empty($category)) {
            $channelFilter = " AND md.channels IN (
                SELECT channel_name FROM wpk4_backend_marketing_channel WHERE category_id = :category
            )";
            $params[':category'] = $category;
        }
        
        $sql = "
            SELECT 
                SUM(md.impression) AS Impression,
                SUM(md.clicks) AS Clicks,
                SUM(md.spends) AS Spends,
                SUM(DISTINCT md.monthly_user) AS Users,
                SUM(md.pax) AS Pax,
                SUM(md.pif) AS PIF,
                SUM(md.tickets) AS Tickets,
                SUM(md.revenue) AS Revenue
            FROM wpk4_backend_marketing_master_data md
            WHERE md.date BETWEEN :start AND :end
            {$channelFilter}
        ";
        
        $finalParams = array_merge([':start' => $lastYearStart, ':end' => $selectedEnd], $params);
        $result = $this->queryOne($sql, $finalParams);
        return ($result === false) ? null : $result;
    }

    /**
     * Fetch marketing remarks
     */
    public function fetchRemarks(string $channel, string $metric, string $periodStart, string $periodEnd): array
    {
        $sql = "
            SELECT observation, remark, created_by, remark_type, impact_type, start_date, end_date, created_at 
            FROM wpk4_backend_marketing_remarks
            WHERE channel = :channel
              AND metric_impact = :metric
              AND start_date <= :periodEnd
              AND end_date >= :periodStart
            ORDER BY created_at DESC
        ";
        
        return $this->query($sql, [
            ':channel' => $channel,
            ':metric' => $metric,
            ':periodStart' => $periodStart,
            ':periodEnd' => $periodEnd
        ]);
    }

    /**
     * Insert marketing remark
     */
    public function insertRemark(
        string $channel,
        string $observation,
        string $remark,
        string $remarkType,
        string $metricImpact,
        string $impactType,
        string $startDate,
        string $endDate,
        string $createdBy = 'system'
    ): int {
        $sql = "
            INSERT INTO wpk4_backend_marketing_remarks 
            (channel, observation, remark, remark_type, metric_impact, impact_type, start_date, end_date, created_by, created_at, updated_at)
            VALUES (:channel, :observation, :remark, :remark_type, :metric_impact, :impact_type, :start_date, :end_date, :created_by, NOW(), NOW())
        ";
        
        $this->execute($sql, [
            ':channel' => $channel,
            ':observation' => $observation,
            ':remark' => $remark,
            ':remark_type' => $remarkType,
            ':metric_impact' => $metricImpact,
            ':impact_type' => $impactType,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':created_by' => $createdBy
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Fetch comparison remarks
     */
    public function fetchComparisonRemarks(string $channel, string $metric, string $periodStart, string $periodEnd): array
    {
        $sql = "
            SELECT remark, created_by, remark_type, impact_type, start_date, end_date, created_at 
            FROM wpk4_backend_marketing_remarks
            WHERE channel = :channel
              AND metric_impact = :metric
              AND start_date <= :periodEnd
              AND end_date >= :periodStart
            ORDER BY created_at DESC
        ";
        
        return $this->query($sql, [
            ':channel' => $channel,
            ':metric' => $metric,
            ':periodStart' => $periodStart,
            ':periodEnd' => $periodEnd
        ]);
    }

    /**
     * Get Google Ads campaign data
     */
    public function getGoogleAdsCampaignData(
        string $startDate,
        string $endDate,
        string $category = '',
        string $campaign = '',
        array $channels = []
    ): array {
        $filters = [];
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($category)) {
            $filters[] = "md.Campaign_Categories = :category";
            $params[':category'] = $category;
        }
        
        if (!empty($campaign)) {
            $filters[] = "md.Campaign_name = :campaign";
            $params[':campaign'] = $campaign;
        }
        
        if (!empty($channels)) {
            $placeholders = [];
            foreach ($channels as $i => $channel) {
                $key = ':channel' . $i;
                $placeholders[] = $key;
                $params[$key] = $channel;
            }
            $filters[] = "md.Campaign_status IN (" . implode(',', $placeholders) . ")";
        }
        
        $whereClause = !empty($filters) ? " AND " . implode(' AND ', $filters) : '';
        
        $sql = "
            SELECT 
                md.Campaign_status,
                md.Campaign_Categories,
                md.Campaign_name,
                SUM(md.Impression) AS Impressions,
                SUM(md.Clicks) AS Clicks,
                SUM(md.Cost) AS Cost,
                SUM(md.Engagements) AS Engagements,
                SUM(md.Conversions) AS Conversions,
                SUM(md.Conversions_value) AS Conversions_value
            FROM wpk4_backend_marketing_google_ads_master_campaign_data md
            WHERE md.Date BETWEEN :start AND :end
            {$whereClause}
            GROUP BY md.Campaign_name, md.Campaign_Categories, md.Campaign_status
            ORDER BY md.Campaign_name
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get Google Ads ad group data
     */
    public function getGoogleAdsAdGroupData(
        string $startDate,
        string $endDate,
        string $category = '',
        string $campaign = '',
        string $adGroup = ''
    ): array {
        $filters = [];
        $params = [':start' => $startDate, ':end' => $endDate];
        
        if (!empty($category)) {
            $filters[] = "md.Campaign_Categories = :category";
            $params[':category'] = $category;
        }
        
        if (!empty($campaign)) {
            $filters[] = "md.Campaign_name = :campaign";
            $params[':campaign'] = $campaign;
        }
        
        if (!empty($adGroup)) {
            $filters[] = "md.Ad_group_name = :ad_group";
            $params[':ad_group'] = $adGroup;
        }
        
        $whereClause = !empty($filters) ? " AND " . implode(' AND ', $filters) : '';
        
        $sql = "
            SELECT 
                md.Campaign_status,
                md.Campaign_Categories,
                md.Campaign_name,
                md.Ad_group_name,
                SUM(md.Impression) AS Impressions,
                SUM(md.Clicks) AS Clicks,
                SUM(md.Cost) AS Cost,
                SUM(md.Engagements) AS Engagements,
                SUM(md.Conversions) AS Conversions,
                SUM(md.Conversions_value) AS Conversions_value
            FROM wpk4_backend_marketing_google_ads_ad_group_data md
            WHERE md.Date BETWEEN :start AND :end
            {$whereClause}
            GROUP BY md.Ad_group_name, md.Campaign_name, md.Campaign_Categories, md.Campaign_status
            ORDER BY md.Campaign_name, md.Ad_group_name
        ";
        
        return $this->query($sql, $params);
    }

    /**
     * Get all campaigns
     */
    public function getAllCampaigns(): array
    {
        $query = "
            SELECT * FROM wpk4_backend_marketing_campaign_master
            ORDER BY campaign_id DESC
        ";
        return $this->query($query);
    }

    /**
     * Get campaign by ID
     */
    public function getCampaignById($campaignId): ?array
    {
        $query = "
            SELECT * FROM wpk4_backend_marketing_campaign_master
            WHERE campaign_id = :campaign_id
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['campaign_id' => $campaignId]);
        return $result === false ? null : $result;
    }

    /**
     * Get campaigns by group ID
     */
    public function getCampaignsByGroupId($groupId): array
    {
        $query = "
            SELECT campaign_id, campaign_name
            FROM wpk4_backend_marketing_campaign_master
            WHERE group_id = :group_id
            ORDER BY campaign_name
        ";
        return $this->query($query, ['group_id' => $groupId]);
    }

    /**
     * Create new campaign
     */
    public function createCampaign($data): int
    {
        // campaign_owner is int(11) NOT NULL in database, so it's required
        if (!isset($data['campaign_owner']) || $data['campaign_owner'] === null || $data['campaign_owner'] === '') {
            throw new \Exception('campaign_owner is required and must be a numeric user ID', 400);
        }
        
        // Convert campaign_owner to integer
        $campaignOwner = $data['campaign_owner'];
        if (!is_numeric($campaignOwner)) {
            throw new \Exception('campaign_owner must be a numeric user ID', 400);
        }
        
        // status is varchar(20) in database, default 'active'
        $status = $data['status'] ?? 'active';
        
        // Build query with optional group_id
        $fields = ['campaign_name', 'start_date', 'end_date', 'initial_budget', 'target_channel', 
                   'key_message', 'target_audience', 'campaign_owner', 'objective', 'target_KPI', 'status'];
        $placeholders = [':campaign_name', ':start_date', ':end_date', ':initial_budget', ':target_channel',
                        ':key_message', ':target_audience', ':campaign_owner', ':objective', ':target_KPI', ':status'];
        
        // Add group_id if provided
        if (isset($data['group_id']) && $data['group_id'] !== null && $data['group_id'] !== '') {
            $fields[] = 'group_id';
            $placeholders[] = ':group_id';
        }
        
        $query = "
            INSERT INTO wpk4_backend_marketing_campaign_master
            (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ";
        
        $params = [
            'campaign_name' => $data['campaign_name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'initial_budget' => $data['initial_budget'] ?? null,
            'target_channel' => $data['target_channel'] ?? null,
            'key_message' => $data['key_message'] ?? null,
            'target_audience' => $data['target_audience'] ?? null,
            'campaign_owner' => (int)$campaignOwner,
            'objective' => $data['objective'] ?? null,
            'target_KPI' => $data['target_KPI'] ?? null,
            'status' => $status
        ];
        
        if (isset($data['group_id']) && $data['group_id'] !== null && $data['group_id'] !== '') {
            $params['group_id'] = (int)$data['group_id'];
        }
        
        $this->execute($query, $params);
        
        return $this->lastInsertId();
    }

    /**
     * Update campaign field
     */
    public function updateCampaignField($campaignId, $field, $value): bool
    {
        $allowedFields = [
            'campaign_name', 'start_date', 'end_date', 'initial_budget', 'target_channel',
            'key_message', 'target_audience', 'campaign_owner', 'objective', 'target_KPI', 'status'
        ];
        
        if (!in_array($field, $allowedFields)) {
            throw new \Exception('Invalid field', 400);
        }
        
        $query = "
            UPDATE wpk4_backend_marketing_campaign_master
            SET {$field} = :value
            WHERE campaign_id = :campaign_id
        ";
        
        $result = $this->execute($query, [
            'value' => $value,
            'campaign_id' => $campaignId
        ]);
        
        return $result !== false;
    }

    /**
     * Get campaign change log
     */
    public function getCampaignChangeLog($campaignId): array
    {
        $query = "
            SELECT change_date, change_type_id, changed_by, previous_value, new_value, notes, impact, channel
            FROM wpk4_backend_marketing_campaign_change_log
            WHERE campaign_id = :campaign_id
            ORDER BY change_date DESC
        ";
        return $this->query($query, ['campaign_id' => $campaignId]);
    }

    /**
     * Create campaign change log entry
     */
    public function createCampaignChangeLog($data): int
    {
        $query = "
            INSERT INTO wpk4_backend_marketing_campaign_change_log
            (campaign_id, change_type_id, changed_by, previous_value, new_value, notes, impact, channel)
            VALUES (:campaign_id, :change_type_id, :changed_by, :previous_value, :new_value, :notes, :impact, :channel)
        ";
        
        // Convert required integer fields to int
        // changed_by is int(11) NOT NULL in database
        $changedBy = $data['changed_by'];
        if (!is_numeric($changedBy)) {
            // If changed_by is a string (username), we might need to look it up
            // For now, throw an error if it's not numeric
            throw new \Exception('changed_by must be a numeric user ID', 400);
        }
        
        $this->execute($query, [
            'campaign_id' => (int)$data['campaign_id'],
            'change_type_id' => (int)$data['change_type_id'],
            'changed_by' => (int)$changedBy,
            'previous_value' => $data['previous_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'notes' => $data['notes'] ?? null,
            'impact' => $data['impact'] ?? null,
            'channel' => $data['channel'] ?? null
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Get all channels
     */
    public function getAllChannels(): array
    {
        $query = "
            SELECT channel_id, channel_name
            FROM wpk4_backend_marketing_channel
            ORDER BY channel_name
        ";
        return $this->query($query);
    }

    /**
     * Get groups by channel ID
     */
    public function getGroupsByChannelId($channelId): array
    {
        $query = "
            SELECT group_id, group_name
            FROM wpk4_backend_marketing_campaign_group
            WHERE channel_id = :channel_id
            ORDER BY group_name
        ";
        return $this->query($query, ['channel_id' => $channelId]);
    }

    /**
     * Get all change types
     */
    public function getAllChangeTypes(): array
    {
        $query = "
            SELECT change_type_id, change_type_name, category
            FROM wpk4_backend_marketing_change_type
            ORDER BY category, change_type_name
        ";
        return $this->query($query);
    }
}

