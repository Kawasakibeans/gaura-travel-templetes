<?php
/**
 * Booking Issues Service - Business Logic Layer
 * Handles booking issue tracking, escalation, and resolution
 */

namespace App\Services;

use App\DAL\BookingIssuesDAL;
use Exception;

class BookingIssuesService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new BookingIssuesDAL();
    }

    /**
     * Get all booking issues with filters
     */
    public function getAllIssues($filters)
    {
        $category = $filters['category'] ?? null;
        $orderId = $filters['order_id'] ?? null;
        $status = $filters['status'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $issues = $this->dal->getIssues($category, $orderId, $status, $limit, $offset);
        $totalCount = $this->dal->getIssuesCount($category, $orderId, $status);

        return [
            'issues' => $issues,
            'total_count' => $totalCount,
            'filters' => [
                'category' => $category,
                'order_id' => $orderId,
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get single issue by ID
     */
    public function getIssueById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        $issue = $this->dal->getIssueById($id);

        if (!$issue) {
            throw new Exception('Issue not found', 404);
        }

        return $issue;
    }

    /**
     * Get distinct issue categories
     */
    public function getIssueCategories()
    {
        return $this->dal->getDistinctCategories();
    }

    /**
     * Create new booking issue
     */
    public function createIssue($data)
    {
        // Validate required fields
        $requiredFields = ['order_id', 'issue_category', 'issue_note'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $issueId = $this->dal->createIssue($data);

        return [
            'issue_id' => $issueId,
            'order_id' => $data['order_id'],
            'message' => 'Issue created successfully'
        ];
    }

    /**
     * Update booking issue
     */
    public function updateIssue($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        // Check if issue exists
        $existingIssue = $this->dal->getIssueById($id);
        if (!$existingIssue) {
            throw new Exception('Issue not found', 404);
        }

        $this->dal->updateIssue($id, $data);

        return [
            'issue_id' => $id,
            'message' => 'Issue updated successfully'
        ];
    }

    /**
     * Close an issue
     */
    public function closeIssue($id, $username)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        // Check if issue exists
        $issue = $this->dal->getIssueById($id);
        if (!$issue) {
            throw new Exception('Issue not found', 404);
        }

        $this->dal->closeIssue($id, $username);

        return [
            'issue_id' => $id,
            'order_id' => $issue['order_id'],
            'status' => 'closed',
            'message' => 'Issue closed successfully'
        ];
    }

    /**
     * Escalate issue to HO (Head Office)
     */
    public function escalateToHO($id, $username)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        // Check if issue exists
        $issue = $this->dal->getIssueById($id);
        if (!$issue) {
            throw new Exception('Issue not found', 404);
        }

        // Update escalation
        $this->dal->escalateToHO($id, $username);

        return [
            'issue_id' => $id,
            'order_id' => $issue['order_id'],
            'escalate_to' => 'HO',
            'escalated_by' => $username,
            'message' => 'Issue escalated to HO successfully',
            'issue_details' => $issue
        ];
    }

    /**
     * Transfer issue to escalation (creates escalation record)
     */
    public function transferToEscalation($id, $username)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        // Check if issue exists
        $issue = $this->dal->getIssueById($id);
        if (!$issue) {
            throw new Exception('Issue not found', 404);
        }

        // Update issue escalation
        $this->dal->escalateToHO($id, $username);

        // Create escalation record
        $escalationId = $this->dal->createEscalation([
            'order_id' => $issue['order_id'],
            'escalation_type' => $issue['issue_category'],
            'note' => $issue['issue_note'],
            'escalated_by' => $username,
            'escalate_to' => 'HO'
        ]);

        return [
            'issue_id' => $id,
            'escalation_id' => $escalationId,
            'order_id' => $issue['order_id'],
            'escalate_to' => 'HO',
            'message' => 'Issue transferred to escalation successfully'
        ];
    }

    /**
     * Delete issue
     */
    public function deleteIssue($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid issue ID is required', 400);
        }

        // Check if issue exists
        $issue = $this->dal->getIssueById($id);
        if (!$issue) {
            throw new Exception('Issue not found', 404);
        }

        $this->dal->deleteIssue($id);

        return [
            'issue_id' => $id,
            'order_id' => $issue['order_id'],
            'message' => 'Issue deleted successfully'
        ];
    }
}

