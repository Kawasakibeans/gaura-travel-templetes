<?php
/**
 * Marketing performance data access
 */

namespace App\DAL;

class MarketingPerformanceDAL extends BaseDAL
{
    /**
     * Fetch all marketing categories.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        $sql = "
            SELECT
                auto_id AS category_id,
                category_name
            FROM wpk4_backend_marketing_category
            ORDER BY category_name
        ";

        return $this->query($sql);
    }

    /**
     * Fetch marketing channels, optionally filtered by status.
     *
     * @param string|null $status
     * @return array<int, array<string, mixed>>
     */
    public function getChannels(?string $status = 'Active'): array
    {
        $sql = "
            SELECT
                c.channel_name,
                c.status,
                cat.auto_id AS category_id,
                cat.category_name
            FROM wpk4_backend_marketing_channel c
            LEFT JOIN wpk4_backend_marketing_category cat
                ON c.category_id = cat.auto_id
        ";

        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= " WHERE c.status = ? ";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.channel_name ";

        return $this->query($sql, $params);
    }
}

