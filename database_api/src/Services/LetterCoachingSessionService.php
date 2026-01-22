<?php
/**
 * Letter Coaching Session Service Layer
 */

namespace App\Services;

use App\DAL\LetterCoachingSessionDAL;
use Exception;

class LetterCoachingSessionService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new LetterCoachingSessionDAL();
    }

    /**
     * List agents with their corresponding codes
     */
    public function listAgents(): array
    {
        $rows = $this->dal->listAgents();

        $agents = [];
        foreach ($rows as $row) {
            if (!isset($row['name'])) {
                continue;
            }

            $parts = explode('|', $row['name']);
            if (count($parts) < 2) {
                continue;
            }

            $agents[] = [
                'name' => trim($parts[0]),
                'code' => trim($parts[1]),
            ];
        }

        usort($agents, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'total' => count($agents),
            'agents' => $agents,
        ];
    }

    /**
     * Fetch the review content for a specific agent code and date
     */
    public function getReview(array $filters = []): array
    {
        $code = $filters['agent_code'] ?? null;
        $dateInput = $filters['date'] ?? null;

        if (empty($code)) {
            throw new Exception('agent_code query parameter is required', 400);
        }

        if (empty($dateInput)) {
            throw new Exception('date query parameter is required (YYYY-MM-DD or DD/MM/YYYY)', 400);
        }

        $date = $this->normalizeDate($dateInput, 'date');

        $record = $this->dal->getReview($code, $date);
        if ($record === null) {
            throw new Exception('Performance review not found for the supplied agent and date', 404);
        }

        $parts = explode('|', $record['name'] ?? '');
        $agentName = $parts[0] ?? $record['name'];

        return [
            'agent' => [
                'code' => $code,
                'name' => trim($agentName),
            ],
            'review_date' => $date,
            'updated_at' => $record['updated_at'] ?? null,
            'content' => [
                'keep' => $record['keep'] ?? null,
                'stop' => $record['stop'] ?? null,
                'start' => $record['start'] ?? null,
                'action_item_4' => $record['action_item_4'] ?? null,
                'proficiency_traffic_light' => $record['proficiency_traffic_light'] ?? null,
                'proficiency_level' => $record['proficiencyLevel'] ?? null,
                'annual_goal' => $record['annual_goal'] ?? null,
                'monthly_run_rate' => $record['monthly_run_rate'] ?? null,
                'run_rate_status' => $record['run_rate_status'] ?? null,
                'mock_call_scores' => [
                    'greet' => $record['greet'] ?? null,
                    'ask' => $record['ask'] ?? null,
                    'repeat' => $record['repeatd'] ?? null,
                    'lead' => $record['lead'] ?? null,
                    'analyse' => $record['analyse'] ?? null,
                    'negotiate' => $record['negotiate'] ?? null,
                    'done_deal' => $record['donedeal'] ?? null,
                    'total_garland_score' => $record['total_garland_score'] ?? null,
                ],
                'strengths' => $record['strengths'] ?? null,
                'expectation_level' => $record['expectation_level'] ?? null,
            ],
        ];
    }

    /**
     * Normalise a date string to YYYY-MM-DD
     */
    private function normalizeDate(string $value, string $field): string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            [$day, $month, $year] = explode('/', $value);
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new Exception("{$field} must be a valid date", 400);
        }

        return date('Y-m-d', $timestamp);
    }
}

