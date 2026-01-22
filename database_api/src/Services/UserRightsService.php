<?php

namespace App\Services;

use App\DAL\UserRightsDAL;

/**
 * Service layer for User Rights operations
 */
class UserRightsService
{
    private $userRightsDAL;

    public function __construct()
    {
        $this->userRightsDAL = new UserRightsDAL();
    }

    /**
     * Get agent access rights with pagination
     * 
     * @param int $limit
     * @param int $offset
     * @param array $filters
     * @return array
     */
    public function getAgentAccessRights($limit = 20, $offset = 0, $filters = [])
    {
        $rows = $this->userRightsDAL->getAgentAccessRights($limit, $offset, $filters);
        $total = $this->userRightsDAL->getAgentAccessRightsCount($filters);

        $agents = [];
        $allKeys = [];

        // Process results
        foreach ($rows as $row) {
            $metaArray = @unserialize($row['meta_value']);
            if (!is_array($metaArray)) {
                $metaArray = [];
            }

            // Collect all capability keys
            foreach (array_keys($metaArray) as $key) {
                if (!in_array($key, $allKeys)) {
                    $allKeys[] = $key;
                }
            }

            $agents[] = [
                'agent_name' => $row['agent_name'],
                'sale_manager' => $row['sale_manager'],
                'meta_array' => $metaArray
            ];
        }

        sort($allKeys);

        return [
            'agents' => $agents,
            'all_keys' => $allKeys,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}

