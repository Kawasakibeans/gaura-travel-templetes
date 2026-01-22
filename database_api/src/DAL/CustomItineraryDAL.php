<?php

namespace App\DAL;

use PDO;

class CustomItineraryDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get requests by case type and user_id
     * Line: 145, 286 (in template)
     */
    public function getRequestsByCaseType($caseType, $userId, $limit = 10)
    {
        $query = "SELECT * FROM wpk4_backend_staff_portal_requests 
                 WHERE case_type = :case_type AND user_id = :user_id 
                 ORDER BY case_id DESC 
                 LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':case_type', $caseType);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all requests by user_id
     * Line: 259 (in template)
     */
    public function getRequestsByUserId($userId)
    {
        $query = "SELECT * FROM wpk4_backend_staff_portal_requests 
                 WHERE user_id = :user_id 
                 ORDER BY case_type ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get request by case_id
     * Line: 615 (in template)
     */
    public function getRequestByCaseId($caseId)
    {
        $query = "SELECT * FROM wpk4_backend_staff_portal_requests 
                 WHERE case_id = :case_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':case_id', $caseId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get requests with filters
     * Line: 1237 (in template)
     */
    public function getRequestsWithFilters($filters = [])
    {
        $whereConditions = [];
        $params = [];
        
        if (isset($filters['case_type']) && $filters['case_type'] !== '') {
            $whereConditions[] = "case_type = :case_type";
            $params[':case_type'] = $filters['case_type'];
        }
        
        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $whereConditions[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereConditions[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['case_id']) && $filters['case_id'] !== '') {
            $whereConditions[] = "case_id = :case_id";
            $params[':case_id'] = $filters['case_id'];
        }
        
        if (isset($filters['reservation_ref']) && $filters['reservation_ref'] !== '') {
            $whereConditions[] = "reservation_ref = :reservation_ref";
            $params[':reservation_ref'] = $filters['reservation_ref'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT * FROM wpk4_backend_staff_portal_requests 
                 $whereClause 
                 ORDER BY last_response_on DESC 
                 LIMIT :limit";
        
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $params[':limit'] = $limit;
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get last case_id to generate new one
     * Line: 448-460 (in template)
     */
    public function getLastCaseId()
    {
        $query = "SELECT * FROM wpk4_backend_staff_portal_requests 
                 ORDER BY case_id DESC 
                 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return (int)$result['case_id'];
        }
        return 0;
    }

    /**
     * Insert new request
     * Line: 464-465 (in template)
     */
    public function insertRequest($caseId, $caseType, $reservationRef, $userId, $priority = 'P4')
    {
        $currentDateTime = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO wpk4_backend_staff_portal_requests 
                 (case_id, case_type, reservation_ref, last_response_by, last_response_on, 
                  is_seen_by_gt, user_id, status, case_date, priority) 
                 VALUES 
                 (:case_id, :case_type, :reservation_ref, 'customer', :last_response_on, 
                  '0', :user_id, 'open', :case_date, :priority)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':case_id', $caseId);
        $stmt->bindValue(':case_type', $caseType);
        $stmt->bindValue(':reservation_ref', $reservationRef);
        $stmt->bindValue(':last_response_on', $currentDateTime);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':case_date', $currentDateTime);
        $stmt->bindValue(':priority', $priority);
        $stmt->execute();
        
        return $caseId;
    }

    /**
     * Update request status
     * Line: 589 (in template)
     */
    public function updateRequestStatus($caseId, $status, $subStatus = null)
    {
        if ($subStatus !== null) {
            $query = "UPDATE wpk4_backend_staff_portal_requests 
                     SET status = :status, sub_status = :sub_status 
                     WHERE case_id = :case_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':sub_status', $subStatus);
            $stmt->bindValue(':case_id', $caseId);
        } else {
            $query = "UPDATE wpk4_backend_staff_portal_requests 
                     SET status = :status 
                     WHERE case_id = :case_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':case_id', $caseId);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Insert request chat/note
     * Line: 594, 1545, 1562 (in template)
     */
    public function insertRequestChat($requestId, $response, $responseBy, $status, $requestType, $chatOrNote = 'chat')
    {
        $currentDateTime = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO wpk4_backend_staff_portal_request_chats 
                 (request_id, response, response_time, response_by, status, request_type, chat_or_note) 
                 VALUES 
                 (:request_id, :response, :response_time, :response_by, :status, :request_type, :chat_or_note)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':request_id', $requestId);
        $stmt->bindValue(':response', $response);
        $stmt->bindValue(':response_time', $currentDateTime);
        $stmt->bindValue(':response_by', $responseBy);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':request_type', $requestType);
        $stmt->bindValue(':chat_or_note', $chatOrNote);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }
}

