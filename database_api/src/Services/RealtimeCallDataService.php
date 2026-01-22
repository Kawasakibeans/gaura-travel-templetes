<?php
/**
 * Realtime Call Data Service Layer
 * 
 * Handles business logic for realtime call data operations
 */

namespace App\Services;

use App\DAL\RealtimeCallDataDAL;

class RealtimeCallDataService {
    private $dal;

    public function __construct(RealtimeCallDataDAL $dal) {
        $this->dal = $dal;
    }

    /**
     * Get realtime call data with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array containing call data and metadata
     */
    public function getRealtimeCallData($filters = []) {
        $calls = $this->dal->getRealtimeCallData($filters);
        
        return [
            'calls' => $calls,
            'count' => count($calls),
            'limit' => $filters['limit'] ?? 1000
        ];
    }

    /**
     * Get distinct teams
     * 
     * @return array Array of distinct team names
     */
    public function getDistinctTeams() {
        return $this->dal->getDistinctTeams();
    }

    /**
     * Get distinct locations
     * 
     * @return array Array of distinct locations
     */
    public function getDistinctLocations() {
        return $this->dal->getDistinctLocations();
    }

    /**
     * Get distinct campaigns
     * 
     * @return array Array of distinct campaigns
     */
    public function getDistinctCampaigns() {
        return $this->dal->getDistinctCampaigns();
    }

    /**
     * Get realtime call data results (using realtime tables)
     * 
     * @return array Array containing call data
     */
    public function getRealtimeCallDataResults() {
        $calls = $this->dal->getRealtimeCallDataResults();
        
        return [
            'calls' => $calls,
            'count' => count($calls)
        ];
    }

    /**
     * Check if there are new requests
     * 
     * @param string $lastValue Last known added_on timestamp
     * @return array Array with check result
     */
    public function checkNewRequests($lastValue = null) {
        return $this->dal->checkNewRequests($lastValue);
    }
}

