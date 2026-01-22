<?php
/**
 * Agent calls service.
 */

namespace App\Services;

use App\DAL\AgentCallsDAL;
use DateTime;
use Exception;

class AgentCallsService
{
    private AgentCallsDAL $dal;

    public function __construct()
    {
        $this->dal = new AgentCallsDAL();
    }

    public function listAgents(): array
    {
        $rows = $this->dal->getAgents();
        return [
            'agents' => array_map(fn ($row) => $row['agent_name'] ?? null, $rows),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listCalls(array $filters): array
    {
        $agent = isset($filters['agent_name']) ? trim((string)$filters['agent_name']) : '';
        $dateFrom = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
        $dateTo = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
        $limit = isset($filters['limit']) ? max(1, (int)$filters['limit']) : 1000;

        if ($agent === '' && $dateFrom === '' && $dateTo === '') {
            throw new Exception('Provide at least one filter: agent_name, date_from, or date_to', 400);
        }

        if ($dateFrom !== '') {
            $this->assertDate($dateFrom, 'date_from');
        }
        if ($dateTo !== '') {
            $this->assertDate($dateTo, 'date_to');
        }

        $rows = $this->dal->getCalls([
            'agent_name' => $agent !== '' ? $agent : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'limit' => $limit,
        ]);

        return [
            'filters' => [
                'agent_name' => $agent !== '' ? $agent : null,
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
                'limit' => $limit,
            ],
            'total' => count($rows),
            'rows' => $rows,
        ];
    }

    public function clearLocalAudio(string $callId): array
    {
        $callId = trim(preg_replace('/\W+/', '', $callId));
        if ($callId === '') {
            throw new Exception('call_id is required', 400);
        }

        $paths = $this->dal->getLocalPaths($callId);
        $this->dal->clearLocalPaths($callId);

        return [
            'call_id' => $callId,
            'paths_cleared' => $paths,
        ];
    }

    private function assertDate(string $value, string $field): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt === false) {
            throw new Exception("$field must be formatted as YYYY-MM-DD", 400);
        }
    }
}

