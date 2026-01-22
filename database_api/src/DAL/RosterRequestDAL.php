<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class RosterRequestDAL extends BaseDAL
{
    /**
     * Get all roster requests with filters and pagination
     */
    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE 1=1
        ";

        $params = [];

        // Filter by type
        if (!empty($filters['type'])) {
            $sql .= " AND r.type = :type";
            $params['type'] = $filters['type'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        // Filter by roster_code
        if (!empty($filters['roster_code'])) {
            $sql .= " AND BINARY r.roster_code = :roster_code";
            $params['roster_code'] = $filters['roster_code'];
        }

        // Filter by sale_manager
        if (!empty($filters['sale_manager'])) {
            $sql .= " AND BINARY r.sale_manager = :sale_manager";
            $params['sale_manager'] = $filters['sale_manager'];
        }

        // Filter by agent_name
        if (!empty($filters['agent_name'])) {
            $sql .= " AND BINARY r.agent_name = :agent_name";
            $params['agent_name'] = $filters['agent_name'];
        }

        // Filter by date range (if created_date column exists)
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(r.created_date) >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(r.created_date) <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $sql .= " ORDER BY r.auto_id DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->query($sql, $params);
    }

    /**
     * Get total count of roster requests with filters
     */
    public function getCount(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_manage_roster_requests r
            WHERE 1=1
        ";

        $params = [];

        // Apply same filters as getAll
        if (!empty($filters['type'])) {
            $sql .= " AND r.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['roster_code'])) {
            $sql .= " AND BINARY r.roster_code = :roster_code";
            $params['roster_code'] = $filters['roster_code'];
        }

        if (!empty($filters['sale_manager'])) {
            $sql .= " AND BINARY r.sale_manager = :sale_manager";
            $params['sale_manager'] = $filters['sale_manager'];
        }

        if (!empty($filters['agent_name'])) {
            $sql .= " AND BINARY r.agent_name = :agent_name";
            $params['agent_name'] = $filters['agent_name'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(r.created_date) >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(r.created_date) <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $result = $this->queryOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Get roster request by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE r.auto_id = :id
        ";

        $result = $this->queryOne($sql, ['id' => $id]);
        return $result ?: null;
    }

    /**
     * Create a new roster request
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_manage_roster_requests 
            (type, agent_name, sale_manager, roster_code, status, current_shift, requested_shift, current_rdo, requested_rdo, reason, created_date)
            VALUES 
            (:type, :agent_name, :sale_manager, :roster_code, :status, :current_shift, :requested_shift, :current_rdo, :requested_rdo, :reason, :created_date)
        ";

        $params = [
            'type' => $data['type'] ?? '',
            'agent_name' => $data['agent_name'] ?? '',
            'sale_manager' => $data['sale_manager'] ?? '',
            'roster_code' => $data['roster_code'] ?? '',
            'status' => $data['status'] ?? 'Pending',
            'current_shift' => $data['current_shift'] ?? null,
            'requested_shift' => $data['requested_shift'] ?? null,
            'current_rdo' => $data['current_rdo'] ?? null,
            'requested_rdo' => $data['requested_rdo'] ?? null,
            'reason' => $data['reason'] ?? '',
            'created_date' => $data['created_date'] ?? date('Y-m-d H:i:s')
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Update a roster request
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'type', 'agent_name', 'sale_manager', 'roster_code', 'status',
            'current_shift', 'requested_shift', 'current_rdo', 'requested_rdo', 'reason'
        ];

        $updates = [];
        $params = ['id' => $id];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "
            UPDATE wpk4_manage_roster_requests 
            SET " . implode(', ', $updates) . "
            WHERE auto_id = :id
        ";

        $this->execute($sql, $params);
        return true;
    }

    /**
     * Delete a roster request
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM wpk4_manage_roster_requests WHERE auto_id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }
}