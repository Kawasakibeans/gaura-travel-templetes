<?php
/**
 * Booking Issues Data Access Layer
 * Handles all database operations for booking issue tracking
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class BookingIssuesDAL extends BaseDAL
{
    /**
     * Get issues with optional filters
     */
    public function getIssues($category = null, $orderId = null, $status = null, $limit = 100, $offset = 0)
    {
        $whereParts = [];
        $params = [];

        if ($category) {
            $whereParts[] = "issue_category = ?";
            $params[] = $category;
        }

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_backend_travel_booking_issue_log 
                  $whereSQL 
                  ORDER BY auto_id ASC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get issues count with filters
     */
    public function getIssuesCount($category = null, $orderId = null, $status = null)
    {
        $whereParts = [];
        $params = [];

        if ($category) {
            $whereParts[] = "issue_category = ?";
            $params[] = $category;
        }

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_backend_travel_booking_issue_log $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get issue by ID
     */
    public function getIssueById($id)
    {
        $query = "SELECT * FROM wpk4_backend_travel_booking_issue_log WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get distinct issue categories
     */
    public function getDistinctCategories()
    {
        $query = "SELECT DISTINCT issue_category 
                  FROM wpk4_backend_travel_booking_issue_log 
                  WHERE issue_category IS NOT NULL AND issue_category != ''
                  ORDER BY issue_category";
        $results = $this->query($query);
        return array_column($results, 'issue_category');
    }

    /**
     * Create new issue
     */
    public function createIssue($data)
    {
        $query = "INSERT INTO wpk4_backend_travel_booking_issue_log 
                  (order_id, issue_category, issue_note, status, added_by, added_on, 
                   escalate_to, updated_by, updated_on)
                  VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW())";
        
        $params = [
            $data['order_id'],
            $data['issue_category'],
            $data['issue_note'],
            $data['status'] ?? 'active',
            $data['added_by'] ?? 'system',
            $data['escalate_to'] ?? null,
            $data['added_by'] ?? 'system'
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update issue
     */
    public function updateIssue($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = [
            'order_id', 'issue_category', 'issue_note', 'status', 
            'escalate_to', 'updated_by'
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

        $setParts[] = "updated_on = NOW()";
        $setSQL = implode(', ', $setParts);
        
        $query = "UPDATE wpk4_backend_travel_booking_issue_log SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Close an issue
     */
    public function closeIssue($id, $username)
    {
        $query = "UPDATE wpk4_backend_travel_booking_issue_log 
                  SET status = 'closed', 
                      updated_by = ?, 
                      updated_on = NOW() 
                  WHERE auto_id = ?";
        
        return $this->execute($query, [$username, $id]);
    }

    /**
     * Escalate issue to HO
     */
    public function escalateToHO($id, $username)
    {
        $query = "UPDATE wpk4_backend_travel_booking_issue_log 
                  SET escalate_to = 'HO', 
                      escalate_to_updated_by = ?, 
                      escalate_to_updated_on = NOW() 
                  WHERE auto_id = ?";
        
        return $this->execute($query, [$username, $id]);
    }

    /**
     * Create escalation record
     */
    public function createEscalation($data)
    {
        $query = "INSERT INTO wpk4_backend_travel_escalations 
                  (order_id, escalation_type, note, status, escalated_by, escalated_on, escalate_to)
                  VALUES (?, ?, ?, 'open', ?, NOW(), ?)";
        
        $params = [
            $data['order_id'],
            $data['escalation_type'],
            $data['note'],
            $data['escalated_by'],
            $data['escalate_to']
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Delete issue
     */
    public function deleteIssue($id)
    {
        $query = "DELETE FROM wpk4_backend_travel_booking_issue_log WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }
}

