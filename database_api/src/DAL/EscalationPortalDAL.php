<?php
/**
 * Data access for travel escalation portal.
 */

namespace App\DAL;

class EscalationPortalDAL extends BaseDAL
{
    public function createEscalation(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO wpk4_backend_travel_escalations (%s) VALUES (%s)',
            implode(',', $columns),
            $placeholders
        );

        $this->execute($sql, array_values($data));
        return (int)$this->lastInsertId();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function getEscalations(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $where[] = "status <> 'closed'";
            } else {
                $where[] = 'status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['escalate_to'])) {
            $where[] = 'escalate_to = ?';
            $params[] = $filters['escalate_to'];
        }

        if (!empty($filters['order_id'])) {
            $where[] = 'order_id = ?';
            $params[] = $filters['order_id'];
        }

        if (!empty($filters['escalated_from'])) {
            $where[] = 'DATE(escalated_on) >= ?';
            $params[] = $filters['escalated_from'];
        }

        if (!empty($filters['escalated_to'])) {
            $where[] = 'DATE(escalated_on) <= ?';
            $params[] = $filters['escalated_to'];
        }

        $sql = "
            SELECT *
            FROM wpk4_backend_travel_escalations
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY escalated_on DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int)$filters['limit'];
        }

        return $this->query($sql, $params);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getEscalationById(int $id): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_escalations
            WHERE auto_id = ?
            LIMIT 1
        ";

        return $this->queryOne($sql, [$id]);
    }

    public function getAmadeusComment(string $orderId): ?string
    {
        $sql = "
            SELECT comments
            FROM wpk4_amadeus_name_update_log
            WHERE order_id = ?
            ORDER BY auto_id DESC
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [$orderId]);
        return $row['comments'] ?? null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getChats(int $escalationId): array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_travel_escalations_chat
            WHERE escalation_id = ?
            ORDER BY auto_id DESC
        ";

        return $this->query($sql, [$escalationId]);
    }

    public function addChat(int $escalationId, string $addedBy, string $response, string $responseType): int
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_escalations_chat
                (escalation_id, added_by, response, response_type, added_on)
            VALUES (?, ?, ?, ?, NOW())
        ";

        $this->execute($sql, [$escalationId, $addedBy, $response, $responseType]);
        return (int)$this->lastInsertId();
    }

    public function updateEscalation(int $id, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = "
            UPDATE wpk4_backend_travel_escalations
            SET " . implode(', ', $sets) . "
            WHERE auto_id = ?
        ";

        $this->execute($sql, $params);
    }
}

