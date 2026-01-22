<?php

namespace App\Services;

use App\DAL\DummyUserPortalBackendDAL;

class DummyUserPortalBackendService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new DummyUserPortalBackendDAL();
    }

    /**
     * Get requests
     */
    public function getRequests(array $params): array
    {
        $caseType = $params['case_type'] ?? null;
        $status = $params['status'] ?? null;
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $caseId = $params['case_id'] ?? null;
        $reservationRef = $params['reservation_ref'] ?? null;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 100;
        
        $requests = $this->dal->getRequests($caseType, $status, $dateFrom, $dateTo, $caseId, $reservationRef, $limit);
        
        // Enrich with user info and followup messages
        foreach ($requests as &$request) {
            $userId = $request['user_id'] ?? null;
            if ($userId && is_numeric($userId)) {
                $user = $this->dal->getUserById((int)$userId);
                $request['user_fullname'] = $user['fullname'] ?? '';
            } else {
                $request['user_fullname'] = '';
            }
            
            $requestType = $request['case_type'] ?? '';
            $followup = $this->dal->getFollowupMessage($request['case_id'], $requestType);
            $request['followup_message'] = $followup;
        }
        
        return [
            'requests' => $requests,
            'total' => count($requests)
        ];
    }
    
    /**
     * Get request chats
     */
    public function getRequestChats(array $params): array
    {
        $requestId = $params['request_id'] ?? null;
        $requestType = $params['request_type'] ?? null;
        
        if (!$requestId || !$requestType) {
            throw new \Exception('request_id and request_type are required');
        }
        
        $chats = $this->dal->getRequestChats($requestId, $requestType);
        
        // Enrich with user info
        foreach ($chats as &$chat) {
            $responseBy = $chat['response_by'] ?? null;
            if ($responseBy && is_numeric($responseBy)) {
                $user = $this->dal->getUserById((int)$responseBy);
                $chat['user_fullname'] = $user['fullname'] ?? $responseBy;
            }
        }
        
        return $chats;
    }
    
    /**
     * Send reply
     */
    public function sendReply(array $params): array
    {
        $requestId = $params['request_id'] ?? null;
        $response = $params['response'] ?? $params['replymessage'] ?? null;
        $responseBy = $params['response_by'] ?? null;
        $requestType = $params['request_type'] ?? null;
        $chatOrNote = $params['chat_or_note'] ?? 'chat';
        $memberName = $params['member_name'] ?? '';
        $userType = $params['usertype_u'] ?? 'admin';
        
        if (!$requestId || !$response || !$responseBy || !$requestType) {
            throw new \Exception('request_id, response, response_by, and request_type are required');
        }
        
        $chatId = $this->dal->insertChatMessage([
            'request_id' => $requestId,
            'response' => $response,
            'response_time' => date('Y-m-d H:i:s'),
            'response_by' => $responseBy,
            'status' => 'open',
            'request_type' => $requestType,
            'chat_or_note' => $chatOrNote,
            'member_name' => $memberName,
            'msg_type' => ''
        ]);
        
        // Update request
        $updates = [
            'last_response_on' => date('Y-m-d H:i:s')
        ];
        
        if ($userType === 'admin') {
            $updates['updated_by'] = $responseBy;
            $updates['last_response_by'] = 'admin';
        } else {
            $updates['last_response_by'] = $responseBy;
            $updates['is_seen_by_gt'] = 0;
        }
        
        if ($chatOrNote === 'chat') {
            $this->dal->updateRequest($requestId, $updates);
        }
        
        return [
            'chat_id' => $chatId,
            'status' => 'success'
        ];
    }
    
    /**
     * Upload attachment
     */
    public function uploadAttachment(array $params): array
    {
        // This would typically handle file upload
        // For now, we'll just insert the chat message with the filename
        $requestId = $params['request_id'] ?? null;
        $filename = $params['filename'] ?? null;
        $responseBy = $params['response_by'] ?? null;
        $requestType = $params['request_type'] ?? null;
        $chatOrNote = $params['chat_or_note'] ?? 'chat';
        $memberName = $params['member_name'] ?? '';
        $fileType = $params['file_type'] ?? 'unknown';
        
        if (!$requestId || !$filename || !$responseBy || !$requestType) {
            throw new \Exception('request_id, filename, response_by, and request_type are required');
        }
        
        $chatId = $this->dal->insertChatMessage([
            'request_id' => $requestId,
            'response' => $filename,
            'response_time' => date('Y-m-d H:i:s'),
            'response_by' => $responseBy,
            'status' => 'open',
            'request_type' => $requestType,
            'chat_or_note' => $chatOrNote,
            'member_name' => $memberName,
            'msg_type' => $fileType
        ]);
        
        return [
            'chat_id' => $chatId,
            'filename' => $filename,
            'status' => 'success'
        ];
    }
    
    /**
     * Check for new updates
     */
    public function checkNewUpdates(array $params): array
    {
        $requestId = $params['request_id'] ?? null;
        $requestType = $params['request_type'] ?? null;
        $lastRecordId = isset($params['last_record_id']) && is_numeric($params['last_record_id']) ? (int)$params['last_record_id'] : 0;
        
        if (!$requestId || !$requestType) {
            throw new \Exception('request_id and request_type are required');
        }
        
        $hasUpdate = $this->dal->checkNewUpdates($requestId, $requestType, $lastRecordId);
        
        return [
            'has_update' => $hasUpdate,
            'should_reload' => $hasUpdate
        ];
    }
    
    /**
     * Get request details
     */
    public function getRequestDetails(string $caseId): array
    {
        $requests = $this->dal->getRequests(null, null, null, null, $caseId, null, 1);
        
        if (empty($requests)) {
            throw new \Exception('Request not found');
        }
        
        $request = $requests[0];
        $requestType = $request['case_type'] ?? '';
        
        // Get meta data
        $meta = $this->dal->getRequestMeta($caseId);
        $request['meta'] = $meta;
        
        // Get user info
        $userId = $request['user_id'] ?? null;
        if ($userId && is_numeric($userId)) {
            $user = $this->dal->getUserById((int)$userId);
            $request['user_info'] = $user;
        } else {
            $request['user_info'] = null;
        }
        
        return $request;
    }
}

