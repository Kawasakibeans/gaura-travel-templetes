<?php
/**
 * Agent Notes Service
 * Business logic for agent notes operations
 */

namespace App\Services;

use App\DAL\AgentNotesDAL;
use Exception;

class AgentNotesService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AgentNotesDAL();
    }

    /**
     * Get agent notes with filters
     */
    public function getAgentNotes(array $filters = []): array
    {
        $updatedDateStart = $filters['updated_date_start'] ?? null;
        $updatedDateEnd = $filters['updated_date_end'] ?? null;
        $callDate = $filters['call_date'] ?? null;
        $department = $filters['department'] ?? null;
        $category = $filters['category'] ?? null;

        // Parse updated_date if provided as range (format: "YYYY-MM-DD - YYYY-MM-DD")
        if (isset($filters['updated_date']) && !empty($filters['updated_date'])) {
            $updatedDate = $filters['updated_date'];
            if (strpos($updatedDate, ' - ') !== false) {
                $parts = explode(' - ', $updatedDate);
                $updatedDateStart = trim($parts[0]);
                $updatedDateEnd = isset($parts[1]) ? trim($parts[1]) : $updatedDateStart;
            } else {
                $updatedDateStart = $updatedDate;
                $updatedDateEnd = $updatedDate;
            }
        }

        $notes = $this->dal->getAgentNotes(
            $updatedDateStart,
            $updatedDateEnd,
            $callDate,
            $department,
            $category
        );

        // Format call duration (convert seconds to minutes)
        foreach ($notes as &$note) {
            if (isset($note['call_duration']) && $note['call_duration'] !== null) {
                $note['call_duration_minutes'] = round($note['call_duration'] / 60, 2);
            }
        }

        return [
            'notes' => $notes,
            'total_count' => count($notes),
            'filters' => [
                'updated_date_start' => $updatedDateStart,
                'updated_date_end' => $updatedDateEnd,
                'call_date' => $callDate,
                'department' => $department,
                'category' => $category
            ]
        ];
    }
}

