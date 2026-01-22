<?php
/**
 * Service for schedule change cases.
 */

namespace App\Services;

use App\DAL\ScheduleChangeCasesDAL;
use DateTime;
use Exception;

class ScheduleChangeCasesService
{
    private ScheduleChangeCasesDAL $dal;

    public function __construct()
    {
        $this->dal = new ScheduleChangeCasesDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listCases(array $filters): array
    {
        $where = [];
        $params = [];

        $from = isset($filters['from_date']) ? $this->parseDate($filters['from_date']) : null;
        $to = isset($filters['to_date']) ? $this->parseDate($filters['to_date']) : null;

        if ($from && $to) {
            $where[] = 'travel_date BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
        }

        $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
        if ($status === 'not_closed') {
            $where[] = "COALESCE(status,'') <> 'Closed'";
        } elseif ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        } else {
            $where[] = "COALESCE(status,'') <> 'Closed'";
        }

        $pnr = isset($filters['pnr']) ? trim((string)$filters['pnr']) : '';
        if ($pnr !== '') {
            $where[] = 'pnr LIKE ?';
            $params[] = '%' . $pnr . '%';
        }

        $agent = isset($filters['agent']) ? trim((string)$filters['agent']) : '';
        if ($agent !== '') {
            $where[] = 'agent = ?';
            $params[] = $agent;
        }

        $sql = 'SELECT * FROM ' . $this->table();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';

        $cases = $this->dal->fetchCases($sql, $params);

        return [
            'filters' => [
                'from_date' => $from,
                'to_date' => $to,
                'status' => $status ?: 'not_closed',
                'pnr' => $pnr !== '' ? $pnr : null,
                'agent' => $agent !== '' ? $agent : null,
            ],
            'total' => count($cases),
            'cases' => $cases,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updateCase(array $payload): array
    {
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            throw new Exception('id is required', 400);
        }

        $data = [];
        foreach (['stage_in_pnr', 'status', 'agent', 'final_status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string)$payload[$field]);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if (empty($data)) {
            throw new Exception('No fields provided for update', 400);
        }

        $data['applied_on'] = (new DateTime('now'))->format('Y-m-d H:i:s');
        $data['applied_by'] = isset($payload['applied_by']) && $payload['applied_by'] !== ''
            ? (string)$payload['applied_by']
            : 'api';

        $this->dal->updateCase($id, $data);

        return [
            'status' => 'success',
            'id' => $id,
            'updated_fields' => array_keys($data),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function addCase(array $payload): array
    {
        $required = ['platform', 'oid', 'travel_date', 'pnr'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                throw new Exception($field . ' is required', 400);
            }
        }

        $travelDate = $this->parseDate($payload['travel_date']);
        if (!$travelDate) {
            throw new Exception('travel_date must be YYYY-MM-DD', 400);
        }

        $now = (new DateTime('now'))->format('Y-m-d H:i:s');
        $user = isset($payload['added_by']) && $payload['added_by'] !== ''
            ? (string)$payload['added_by']
            : 'api';

        $data = [
            'platform' => (string)$payload['platform'],
            'oid' => (string)$payload['oid'],
            'travel_date' => $travelDate,
            'pnr' => (string)$payload['pnr'],
            'action_date' => $now,
            'stage_in_pnr' => null,
            'status' => 'Open',
            'agent' => null,
            'final_status' => null,
            'added_on' => $now,
            'added_by' => $user,
            'applied_on' => null,
            'applied_by' => null,
        ];

        $id = $this->dal->insertCase($data);

        return [
            'status' => 'success',
            'id' => $id,
        ];
    }

    public function listAgents(): array
    {
        return [
            'agents' => $this->dal->getDistinctAgents(),
        ];
    }

    private function table(): string
    {
        return ($_ENV['DB_TABLE_PREFIX'] ?? 'wpk4_') . 'schedule_change_cases';
    }

    private function parseDate(mixed $value): ?string
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
}

