<?php
/**
 * Escalations Data Access Layer
 * Handles database operations for escalation management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EscalationsDAL extends BaseDAL
{
    /**
     * Get all escalations with filters
     */
    public function getAllEscalations($escalationDate, $orderId, $responseDate, $status, $limit, $offset)
    {
        $whereParts = [];
        $params = [];

        if ($escalationDate) {
            $whereParts[] = "DATE(escalated_on) = ?";
            $params[] = $escalationDate;
        }

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($responseDate) {
            $whereParts[] = "DATE(ho_response_on) = ?";
            $params[] = $responseDate;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        } else {
            // Default: exclude closed
            $whereParts[] = "status != 'closed'";
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_backend_travel_escalations 
                  $whereSQL 
                  ORDER BY escalated_on DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get escalations count
     */
    public function getEscalationsCount($escalationDate, $orderId, $responseDate, $status)
    {
        $whereParts = [];
        $params = [];

        if ($escalationDate) {
            $whereParts[] = "DATE(escalated_on) = ?";
            $params[] = $escalationDate;
        }

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($responseDate) {
            $whereParts[] = "DATE(ho_response_on) = ?";
            $params[] = $responseDate;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        } else {
            $whereParts[] = "status != 'closed'";
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_backend_travel_escalations $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get escalation by ID
     */
    public function getEscalationById($id)
    {
        $query = "SELECT * FROM wpk4_backend_travel_escalations WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get escalation chat messages
     */
    public function getEscalationChat($escalationId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_escalations_chat 
                  WHERE escalation_id = ? 
                  ORDER BY auto_id ASC";
        
        return $this->query($query, [$escalationId]);
    }

    /**
     * Create new escalation
     */
    public function createEscalation($data)
    {
        $query = "INSERT INTO wpk4_backend_travel_escalations 
                  (order_id, escalation_type, note, status, escalated_by, escalated_on, 
                   escalate_to, followup_date, airline, fare_difference, new_option)
                  VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
        
        $params = [
            $data['order_id'],
            $data['escalation_type'],
            $data['note'],
            $data['status'] ?? 'pending',
            $data['escalated_by'] ?? 'system',
            $data['escalate_to'] ?? 'HO',
            $data['followup_date'] ?? null,
            $data['airline'] ?? null,
            $data['fare_difference'] ?? null,
            $data['new_option'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update escalation
     */
    public function updateEscalation($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = [
            'order_id', 'escalation_type', 'note', 'status', 'escalate_to',
            'followup_date', 'airline', 'fare_difference', 'new_option',
            'ho_response_on'
        ];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_backend_travel_escalations SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Update escalation status
     */
    public function updateStatus($id, $status, $username)
    {
        $currentDateTime = date('Y-m-d H:i:s');
        
        $query = "UPDATE wpk4_backend_travel_escalations 
                  SET status = ?, 
                      status_modified_by = ?, 
                      status_modified_on = ? 
                  WHERE auto_id = ?";
        
        return $this->execute($query, [$status, $username, $currentDateTime, $id]);
    }

    /**
     * Add chat message
     */
    public function addChatMessage($escalationId, $message, $sender)
    {
        $query = "INSERT INTO wpk4_backend_travel_escalations_chat 
                  (escalation_id, message, sender, created_at) 
                  VALUES (?, ?, ?, NOW())";
        
        $this->execute($query, [$escalationId, $message, $sender]);
        return $this->lastInsertId();
    }

    /**
     * Delete escalation
     */
    public function deleteEscalation($id)
    {
        $query = "DELETE FROM wpk4_backend_travel_escalations WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }
}

