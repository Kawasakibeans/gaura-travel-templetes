<?php
/**
 * Escalations Service - Business Logic Layer
 * Handles escalation tracking and management
 */

namespace App\Services;

use App\DAL\EscalationsDAL;
use Exception;

class EscalationsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new EscalationsDAL();
    }

    /**
     * Get all escalations with filters
     */
    public function getAllEscalations($filters)
    {
        $escalationDate = $filters['escalation_date'] ?? null;
        $orderId = $filters['order_id'] ?? null;
        $responseDate = $filters['response_date'] ?? null;
        $status = $filters['status'] ?? null;
        $limit = (int)($filters['limit'] ?? 50);
        $offset = (int)($filters['offset'] ?? 0);

        $escalations = $this->dal->getAllEscalations(
            $escalationDate, $orderId, $responseDate, $status, $limit, $offset
        );

        $totalCount = $this->dal->getEscalationsCount(
            $escalationDate, $orderId, $responseDate, $status
        );

        return [
            'escalations' => $escalations,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get escalation by ID
     */
    public function getEscalationById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid escalation ID is required', 400);
        }

        $escalation = $this->dal->getEscalationById($id);

        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        // Get chat messages for this escalation
        $chatMessages = $this->dal->getEscalationChat($id);

        return [
            'escalation' => $escalation,
            'chat_messages' => $chatMessages,
            'chat_count' => count($chatMessages)
        ];
    }

    /**
     * Create new escalation
     */
    public function createEscalation($data)
    {
        // Validate required fields
        $requiredFields = ['order_id', 'escalation_type', 'note'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $escalationId = $this->dal->createEscalation($data);

        return [
            'escalation_id' => $escalationId,
            'order_id' => $data['order_id'],
            'message' => 'Escalation created successfully'
        ];
    }

    /**
     * Update escalation
     */
    public function updateEscalation($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid escalation ID is required', 400);
        }

        $escalation = $this->dal->getEscalationById($id);
        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        $this->dal->updateEscalation($id, $data);

        return [
            'escalation_id' => $id,
            'message' => 'Escalation updated successfully'
        ];
    }

    /**
     * Update escalation status
     */
    public function updateStatus($id, $status, $username)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid escalation ID is required', 400);
        }

        $validStatuses = ['pending', 'in progress', 'closed'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status. Must be: pending, in progress, or closed', 400);
        }

        $escalation = $this->dal->getEscalationById($id);
        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        $this->dal->updateStatus($id, $status, $username);

        return [
            'escalation_id' => $id,
            'status' => $status,
            'updated_by' => $username,
            'message' => 'Status updated successfully'
        ];
    }

    /**
     * Close escalation
     */
    public function closeEscalation($id, $username)
    {
        return $this->updateStatus($id, 'closed', $username);
    }

    /**
     * Add chat message to escalation
     */
    public function addChatMessage($escalationId, $message, $sender)
    {
        if (empty($escalationId) || !is_numeric($escalationId)) {
            throw new Exception('Valid escalation ID is required', 400);
        }

        if (empty($message)) {
            throw new Exception('Message is required', 400);
        }

        $escalation = $this->dal->getEscalationById($escalationId);
        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        $chatId = $this->dal->addChatMessage($escalationId, $message, $sender);

        return [
            'chat_id' => $chatId,
            'escalation_id' => $escalationId,
            'message' => 'Chat message added successfully'
        ];
    }

    /**
     * Delete escalation
     */
    public function deleteEscalation($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid escalation ID is required', 400);
        }

        $escalation = $this->dal->getEscalationById($id);
        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        $this->dal->deleteEscalation($id);

        return [
            'escalation_id' => $id,
            'order_id' => $escalation['order_id'],
            'message' => 'Escalation deleted successfully'
        ];
    }
}

