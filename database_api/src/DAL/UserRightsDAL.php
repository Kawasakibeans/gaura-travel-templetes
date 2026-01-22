<?php

namespace App\DAL;

/**
 * Data Access Layer for User Rights operations
 */
class UserRightsDAL extends BaseDAL
{
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
        $where = ["BINARY a.meta_key = 'wpk4_capabilities'", "BINARY c.agent_name != ''"];
        $params = [];

        if (!empty($filters['location'])) {
            $where[] = "BINARY c.location = :location";
            $params['location'] = $filters['location'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT a.meta_value, c.agent_name, c.sale_manager
            FROM wpk4_usermeta a
            LEFT JOIN wpk4_users b ON a.user_id = b.ID
            LEFT JOIN wpk4_backend_agent_codes c ON BINARY b.user_login = BINARY c.wordpress_user_name
            {$whereClause}
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get total count of agent access rights
     * 
     * @param array $filters
     * @return int
     */
    public function getAgentAccessRightsCount($filters = [])
    {
        $where = ["BINARY a.meta_key = 'wpk4_capabilities'", "BINARY c.agent_name != ''"];
        $params = [];

        if (!empty($filters['location'])) {
            $where[] = "BINARY c.location = :location";
            $params['location'] = $filters['location'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*) AS total
            FROM wpk4_usermeta a
            LEFT JOIN wpk4_users b ON a.user_id = b.ID
            LEFT JOIN wpk4_backend_agent_codes c ON BINARY b.user_login = BINARY c.wordpress_user_name
            {$whereClause}
        ";

        $result = $this->query($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }
}

