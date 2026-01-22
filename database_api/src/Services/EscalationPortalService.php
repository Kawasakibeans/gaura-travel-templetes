<?php
/**
 * Service for travel escalation portal operations.
 */

namespace App\Services;

use App\DAL\EscalationPortalDAL;
use DateTime;
use Exception;

class EscalationPortalService
{
    private EscalationPortalDAL $dal;

    public function __construct()
    {
        $this->dal = new EscalationPortalDAL();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createEscalation(array $payload): array
    {
        $required = ['order_id', 'escalation_type', 'note', 'escalated_by'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                throw new Exception("{$field} is required", 400);
            }
        }

        $data = [
            'order_id' => (string)$payload['order_id'],
            'escalation_type' => (string)$payload['escalation_type'],
            'note' => (string)$payload['note'],
            'status' => $payload['status'] ?? 'open',
            'escalate_to' => $payload['escalate_to'] ?? null,
            'escalated_by' => (string)$payload['escalated_by'],
            'escalated_on' => (new DateTime('now'))->format('Y-m-d H:i:s'),
            'followup_date' => $payload['followup_date'] ?? null,
            'airline' => $payload['airline'] ?? null,
            'fare_difference' => $payload['fare_difference'] ?? null,
            'new_option' => $payload['new_option'] ?? null,
            'existing_pnr_screenshot' => $payload['existing_pnr_screenshot'] ?? null,
            'new_option_screenshot' => $payload['new_option_screenshot'] ?? null,
            'other_note' => $payload['other_note'] ?? null,
        ];

        $data = array_filter(
            $data,
            static fn ($value) => $value !== null && $value !== ''
        );

        $id = $this->dal->createEscalation($data);

        return [
            'status' => 'success',
            'escalation_id' => $id,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listEscalations(array $filters): array
    {
        $normalized = [
            'status' => isset($filters['status']) ? trim((string)$filters['status']) : null,
            'escalate_to' => isset($filters['escalate_to']) ? trim((string)$filters['escalate_to']) : null,
            'order_id' => isset($filters['order_id']) ? trim((string)$filters['order_id']) : null,
            'escalated_from' => isset($filters['escalated_from']) ? $this->normalizeDate($filters['escalated_from']) : null,
            'escalated_to' => isset($filters['escalated_to']) ? $this->normalizeDate($filters['escalated_to']) : null,
            'limit' => isset($filters['limit']) ? max(0, (int)$filters['limit']) : 50,
        ];

        $rows = $this->dal->getEscalations(array_filter($normalized, static fn ($v) => $v !== null));

        return [
            'filters' => $normalized,
            'total' => count($rows),
            'escalations' => $rows,
        ];
    }

    public function getEscalation(int $id): array
    {
        $record = $this->dal->getEscalationById($id);
        if (!$record) {
            throw new Exception('Escalation not found', 404);
        }

        $remarks = null;
        if (($record['escalation_type'] ?? '') === 'Amadeus Name Update Issue' && !empty($record['order_id'])) {
            $remarks = $this->dal->getAmadeusComment((string)$record['order_id']);
        }

        $chats = $this->dal->getChats($id);

        return [
            'escalation' => $record,
            'amadeus_comment' => $remarks,
            'chats' => $chats,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function addChat(int $id, array $payload): array
    {
        $escalation = $this->dal->getEscalationById($id);
        if (!$escalation) {
            throw new Exception('Escalation not found', 404);
        }

        $addedBy = isset($payload['added_by']) ? trim((string)$payload['added_by']) : '';
        $response = isset($payload['response']) ? trim((string)$payload['response']) : '';
        $type = isset($payload['response_type']) ? trim((string)$payload['response_type']) : 'text';

        if ($addedBy === '' || $response === '') {
            throw new Exception('added_by and response are required', 400);
        }

        $this->dal->addChat($id, $addedBy, $response, $type);

        $fields = [];
        if ($type === 'attachment' && empty($escalation['ho_response_on']) && $this->isHoResponder($addedBy)) {
            $fields['ho_response_on'] = (new DateTime('now'))->format('Y-m-d H:i:s');
        }

        if ($fields) {
            $this->dal->updateEscalation($id, $fields);
        }

        return [
            'status' => 'success',
            'escalation_id' => $id,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function assignEscalation(int $id, array $payload): array
    {
        $assignedTo = isset($payload['escalate_to']) ? trim((string)$payload['escalate_to']) : '';
        $updatedBy = isset($payload['updated_by']) ? trim((string)$payload['updated_by']) : '';

        if ($assignedTo === '' || $updatedBy === '') {
            throw new Exception('escalate_to and updated_by are required', 400);
        }

        $fields = [
            'escalate_to' => $assignedTo,
            'escalate_to_updated_by' => $updatedBy,
            'escalate_to_updated_on' => (new DateTime('now'))->format('Y-m-d H:i:s'),
        ];

        $this->dal->updateEscalation($id, $fields);

        return [
            'status' => 'success',
            'escalation_id' => $id,
            'escalate_to' => $assignedTo,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function closeEscalation(int $id, array $payload): array
    {
        $closedBy = isset($payload['closed_by']) ? trim((string)$payload['closed_by']) : '';
        if ($closedBy === '') {
            throw new Exception('closed_by is required', 400);
        }

        $fields = [
            'status' => 'closed',
            'status_modified_by' => $closedBy,
            'status_modified_on' => (new DateTime('now'))->format('Y-m-d H:i:s'),
        ];

        $this->dal->updateEscalation($id, $fields);

        return [
            'status' => 'success',
            'escalation_id' => $id,
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d');
    }

    private function isHoResponder(string $user): bool
    {
        $user = strtolower($user);
        return str_contains($user, 'ho');
    }
}

