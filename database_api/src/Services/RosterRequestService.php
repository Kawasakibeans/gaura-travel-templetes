<?php

namespace App\Services;

use App\DAL\RosterRequestDAL;
use Exception;

class RosterRequestService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new RosterRequestDAL();
    }

    /**
     * Get all roster requests with filters and pagination
     */
    public function getAll(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $records = $this->dal->getAll($filters, $limit, $offset);
        $total = $this->dal->getCount($filters);

        return [
            'records' => $records,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Get roster request by ID
     */
    public function getById(int $id): array
    {
        $record = $this->dal->getById($id);
        if (!$record) {
            throw new Exception('Roster request not found', 404);
        }
        return $record;
    }

    /**
     * Create a new roster request
     */
    public function create(array $data): int
    {
        // Validate required fields
        $requiredFields = ['type', 'roster_code'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Set default status if not provided
        if (empty($data['status'])) {
            $data['status'] = 'Pending';
        }

        return $this->dal->create($data);
    }

    /**
     * Update a roster request
     */
    public function update(int $id, array $data): void
    {
        // Check if record exists
        $existing = $this->dal->getById($id);
        if (!$existing) {
            throw new Exception('Roster request not found', 404);
        }

        $this->dal->update($id, $data);
    }

    /**
     * Delete a roster request
     */
    public function delete(int $id): void
    {
        // Check if record exists
        $existing = $this->dal->getById($id);
        if (!$existing) {
            throw new Exception('Roster request not found', 404);
        }

        $this->dal->delete($id);
    }
}