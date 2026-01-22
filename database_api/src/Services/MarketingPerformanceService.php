<?php
/**
 * Marketing performance service layer
 */

namespace App\Services;

use App\DAL\MarketingPerformanceDAL;
use Exception;

class MarketingPerformanceService
{
    private MarketingPerformanceDAL $dal;

    public function __construct()
    {
        $this->dal = new MarketingPerformanceDAL();
    }

    /**
     * List categories with their associated channels.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listCategories(array $filters = []): array
    {
        $status = $filters['status'] ?? 'Active';
        $includeEmpty = filter_var($filters['include_empty'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($status !== null && !is_string($status)) {
            throw new Exception('status must be a string or omitted', 400);
        }

        $categories = $this->dal->getCategories();
        $channels = $this->dal->getChannels($status);

        $channelMap = [];
        foreach ($channels as $channel) {
            $categoryId = (int)($channel['category_id'] ?? 0);
            $channelMap[$categoryId][] = [
                'channel_name' => $channel['channel_name'],
                'status' => $channel['status'],
            ];
        }

        $result = [];
        foreach ($categories as $category) {
            $categoryId = (int)$category['category_id'];
            $entry = [
                'category_id' => $categoryId,
                'category_name' => $category['category_name'],
                'channels' => $channelMap[$categoryId] ?? [],
            ];

            if (!$includeEmpty && empty($entry['channels'])) {
                continue;
            }

            $result[] = $entry;
        }

        return [
            'filters' => [
                'status' => $status,
                'include_empty' => $includeEmpty,
            ],
            'total_categories' => count($result),
            'total_channels' => array_sum(array_map(fn ($c) => count($c['channels']), $result)),
            'categories' => $result,
        ];
    }
}

