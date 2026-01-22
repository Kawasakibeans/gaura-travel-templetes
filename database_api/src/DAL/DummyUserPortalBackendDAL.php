<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class DummyUserPortalBackendDAL extends BaseDAL
{
    /**
     * Get requests with filters
     */
    public function getRequests(?string $caseType = null, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null, ?string $caseId = null, ?string $reservationRef = null, int $limit = 100): array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_requests WHERE 1=1";
        $params = [];
        
        if ($caseType) {
            $sql .= " AND case_type = :case_type";
            $params[':case_type'] = $caseType;
        }
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        if ($dateFrom) {
            $sql .= " AND case_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND case_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if ($caseId) {
            $sql .= " AND case_id = :case_id";
            $params[':case_id'] = $caseId;
        }
        
        if ($reservationRef) {
            $sql .= " AND reservation_ref = :reservation_ref";
            $params[':reservation_ref'] = $reservationRef;
        }
        
        $sql .= " ORDER BY last_response_on DESC LIMIT " . (int)$limit;
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get request chats
     */
    public function getRequestChats(string $requestId, string $requestType): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :request_id 
            AND request_type = :request_type
            ORDER BY response_time ASC
        ";
        
        return $this->query($sql, [
            ':request_id' => $requestId,
            ':request_type' => $requestType
        ]);
    }
    
    /**
     * Get request meta
     */
    public function getRequestMeta(string $caseId): array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_request_meta WHERE case_id = :case_id";
        return $this->query($sql, [':case_id' => $caseId]);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_login WHERE auto_id = :user_id LIMIT 1";
        $result = $this->queryOne($sql, [':user_id' => $userId]);
        return $result ?: null;
    }
    
    /**
     * Insert chat message
     */
    public function insertChatMessage(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_user_portal_request_chats 
            (request_id, response, response_time, response_by, status, request_type, chat_or_note, member_name, msg_type)
            VALUES (:request_id, :response, :response_time, :response_by, :status, :request_type, :chat_or_note, :member_name, :msg_type)
        ";
        
        $this->execute($sql, [
            ':request_id' => $data['request_id'] ?? '',
            ':response' => $data['response'] ?? '',
            ':response_time' => $data['response_time'] ?? date('Y-m-d H:i:s'),
            ':response_by' => $data['response_by'] ?? '',
            ':status' => $data['status'] ?? 'open',
            ':request_type' => $data['request_type'] ?? '',
            ':chat_or_note' => $data['chat_or_note'] ?? 'chat',
            ':member_name' => $data['member_name'] ?? '',
            ':msg_type' => $data['msg_type'] ?? ''
        ]);
        
        return $this->lastInsertId();
    }
    
    /**
     * Update request
     */
    public function updateRequest(string $caseId, array $updates): bool
    {
        if (empty($updates)) {
            return false;
        }
        
        $setParts = [];
        $params = [':case_id' => $caseId];
        
        foreach ($updates as $key => $value) {
            $paramKey = ':' . $key;
            $setParts[] = "{$key} = {$paramKey}";
            $params[$paramKey] = $value;
        }
        
        $sql = "UPDATE wpk4_backend_user_portal_requests SET " . implode(', ', $setParts) . " WHERE case_id = :case_id";
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Get followup message
     */
    public function getFollowupMessage(string $caseId, string $requestType): ?array
    {
        $sql = "
            SELECT * FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :case_id 
            AND chat_or_note = 'followup' 
            AND request_type = :request_type
            ORDER BY auto_id DESC 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [
            ':case_id' => $caseId,
            ':request_type' => $requestType
        ]);
        
        return $result ?: null;
    }
    
    /**
     * Check for new updates
     */
    public function checkNewUpdates(string $requestId, string $requestType, int $lastRecordId): bool
    {
        $sql = "
            SELECT auto_id FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :request_id 
            AND request_type = :request_type
            ORDER BY auto_id DESC 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [
            ':request_id' => $requestId,
            ':request_type' => $requestType
        ]);
        
        if (!$result) {
            return false;
        }
        
        return (int)$result['auto_id'] !== $lastRecordId;
    }
    
    /**
     * Get generic users
     */
    public function getGenericUsers(string $genericId): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_user_portal_generic_users 
            WHERE generic_id = :generic_id
            ORDER BY user_name ASC
        ";
        
        return $this->query($sql, [':generic_id' => $genericId]);
    }
}

