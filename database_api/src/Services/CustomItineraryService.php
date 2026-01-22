<?php

namespace App\Services;

use App\DAL\CustomItineraryDAL;

class CustomItineraryService
{
    private $dal;

    public function __construct(CustomItineraryDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get requests with filters
     * Line: 1237 (in template)
     */
    public function getRequests($filters = [])
    {
        return $this->dal->getRequestsWithFilters($filters);
    }

    /**
     * Get request by case_id
     * Line: 615 (in template)
     */
    public function getRequestByCaseId($caseId)
    {
        $request = $this->dal->getRequestByCaseId($caseId);
        
        if (!$request) {
            throw new \Exception('Request not found', 404);
        }
        
        return $request;
    }

    /**
     * Create new request
     * Line: 448-465 (in template)
     */
    public function createRequest($caseType, $reservationRef, $userId, $information = null, $priority = 'P4')
    {
        // Validate required fields
        if (!$caseType) {
            throw new \Exception('case_type is required', 400);
        }
        if (!$reservationRef) {
            throw new \Exception('reservation_ref is required', 400);
        }
        if (!$userId) {
            throw new \Exception('user_id is required', 400);
        }
        
        // Get last case_id and increment
        $lastCaseId = $this->dal->getLastCaseId();
        $newCaseId = $lastCaseId + 1;
        
        // Insert request
        $caseId = $this->dal->insertRequest($newCaseId, $caseType, $reservationRef, $userId, $priority);
        
        // If information provided, add as initial chat
        if ($information) {
            $this->dal->insertRequestChat($caseId, $information, $userId, 'open', $caseType, 'chat');
        }
        
        // Return the created request
        return $this->dal->getRequestByCaseId($caseId);
    }

    /**
     * Get requests by case type
     * Line: 145, 286 (in template)
     */
    public function getRequestsByCaseType($caseType, $userId, $limit = 10)
    {
        return $this->dal->getRequestsByCaseType($caseType, $userId, $limit);
    }

    /**
     * Get requests by user_id
     * Line: 259 (in template)
     */
    public function getRequestsByUserId($userId)
    {
        return $this->dal->getRequestsByUserId($userId);
    }

    /**
     * Update request status
     * Line: 589 (in template)
     */
    public function updateRequestStatus($caseId, $status, $subStatus = null)
    {
        $request = $this->dal->getRequestByCaseId($caseId);
        if (!$request) {
            throw new \Exception('Request not found', 404);
        }
        
        return $this->dal->updateRequestStatus($caseId, $status, $subStatus);
    }

    /**
     * Reopen request
     * Line: 589, 594 (in template)
     */
    public function reopenRequest($caseId, $userId, $caseType)
    {
        $request = $this->dal->getRequestByCaseId($caseId);
        if (!$request) {
            throw new \Exception('Request not found', 404);
        }
        
        // Update status to open
        $this->dal->updateRequestStatus($caseId, 'open');
        
        // Add reopen chat message
        $currentDateTime = date('Y-m-d H:i:s');
        $this->dal->insertRequestChat($caseId, 'reopenthis', $userId, 'open', $caseType, 'chat');
        
        return $this->dal->getRequestByCaseId($caseId);
    }
}

