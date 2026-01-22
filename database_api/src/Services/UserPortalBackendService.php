<?php
/**
 * User Portal Backend Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\UserPortalBackendDAL;
use Exception;

class UserPortalBackendService
{
    private $backendDAL;
    
    public function __construct()
    {
        $this->backendDAL = new UserPortalBackendDAL();
    }
    
    /**
     * Check for new updates
     */
    public function checkNewUpdate($requestId, $replyTypeCategory, $lastRecordNumber)
    {
        if (empty($requestId)) {
            throw new Exception('Request ID is required', 400);
        }
        
        if (empty($replyTypeCategory)) {
            throw new Exception('Reply type category is required', 400);
        }
        
        $lastRecord = $this->backendDAL->checkNewUpdate($requestId, $replyTypeCategory);
        
        if ($lastRecord && isset($lastRecord['auto_id']) && $lastRecord['auto_id'] != $lastRecordNumber) {
            return ['reload' => true, 'last_record_id' => $lastRecord['auto_id']];
        }
        
        return ['reload' => false];
    }
    
    /**
     * Check for new requests
     */
    public function checkNewRequests($lastCaseId)
    {
        $lastRequest = $this->backendDAL->checkNewRequests();
        
        if ($lastRequest && isset($lastRequest['case_id']) && $lastRequest['case_id'] != $lastCaseId) {
            return ['reload' => true, 'last_case_id' => $lastRequest['case_id']];
        }
        
        return ['reload' => false];
    }
    
    /**
     * Get requests by query
     */
    public function getRequests($whereClause)
    {
        // Decode query parameter (replace |@| with &&)
        $whereClause = str_replace('|@|', '&&', $whereClause);
        
        // Basic sanitization - only allow WHERE clause patterns
        // In production, use proper parameterized queries
        if (stripos($whereClause, 'DROP') !== false || 
            stripos($whereClause, 'DELETE') !== false ||
            stripos($whereClause, 'UPDATE') !== false ||
            stripos($whereClause, 'INSERT') !== false) {
            throw new Exception('Invalid query clause', 400);
        }
        
        $requests = $this->backendDAL->getRequests($whereClause);
        
        return [
            'success' => true,
            'count' => count($requests),
            'requests' => $requests
        ];
    }
    
    /**
     * Get chat messages
     */
    public function getChatMessages($requestId, $replyTypeCategory)
    {
        if (empty($requestId)) {
            throw new Exception('Request ID is required', 400);
        }
        
        if (empty($replyTypeCategory)) {
            throw new Exception('Reply type category is required', 400);
        }
        
        $messages = $this->backendDAL->getChatMessages($requestId, $replyTypeCategory);
        
        return [
            'success' => true,
            'count' => count($messages),
            'messages' => $messages
        ];
    }
    
    /**
     * Create chat reply
     */
    public function createReply($requestId, $requestType, $replyMessage, $responseBy, $userType, $memberName = '', $responseTypeNote = '', $responseTypeNoteAdditional = '')
    {
        if (empty($requestId)) {
            throw new Exception('Request ID is required', 400);
        }
        
        if (empty($requestType)) {
            throw new Exception('Request type is required', 400);
        }
        
        if (empty($replyMessage)) {
            throw new Exception('Reply message is required', 400);
        }
        
        if (empty($responseBy)) {
            throw new Exception('Response by is required', 400);
        }
        
        // Determine chat_or_note based on response type
        // Logic matches original code: if responsetypenote == 'note' and responsetypenote_additional exists, use it
        // Otherwise use responsetypenote, or default to 'chat'
        $chatOrNote = 'chat';
        if (!empty($responseTypeNote)) {
            if ($responseTypeNote === 'note' && !empty($responseTypeNoteAdditional)) {
                $chatOrNote = $responseTypeNoteAdditional;
            } else {
                $chatOrNote = $responseTypeNote;
            }
        }
        
        // Insert chat message
        $this->backendDAL->insertChatMessage(
            $requestId,
            $requestType,
            $replyMessage,
            $responseBy,
            $chatOrNote,
            $memberName
        );
        
        // Update request last response
        $this->backendDAL->updateRequestLastResponse($requestId, $responseBy);
        
        return [
            'success' => true,
            'message' => 'Reply created successfully',
            'request_id' => $requestId
        ];
    }
    
    /**
     * Upload attachment and create chat message
     */
    public function uploadAttachment($requestId, $requestType, $responseBy, $userType, $filePath, $memberName = '', $responseTypeNote = '', $responseTypeNoteAdditional = '')
    {
        if (empty($requestId)) {
            throw new Exception('Request ID is required', 400);
        }
        
        if (empty($requestType)) {
            throw new Exception('Request type is required', 400);
        }
        
        if (empty($filePath)) {
            throw new Exception('File path is required', 400);
        }
        
        // Determine chat_or_note
        $chatOrNote = 'chat';
        if (!empty($responseTypeNote)) {
            $chatOrNote = $responseTypeNote;
        }
        
        // Insert chat message with attachment
        $this->backendDAL->insertChatMessage(
            $requestId,
            $requestType,
            '', // Empty response for attachment
            $responseBy,
            $chatOrNote,
            $memberName,
            $filePath
        );
        
        // Update request last response
        $this->backendDAL->updateRequestLastResponse($requestId, $responseBy);
        
        return [
            'success' => true,
            'message' => 'Attachment uploaded successfully',
            'request_id' => $requestId,
            'attachment' => $filePath
        ];
    }
    
    /**
     * Update request status
     */
    public function updateRequestStatus($caseId, $statusSelector, $responseBy, $requestType, $memberName = '')
    {
        if (empty($caseId)) {
            throw new Exception('Case ID is required', 400);
        }
        
        if (empty($statusSelector)) {
            throw new Exception('Status selector is required', 400);
        }
        
        if (empty($responseBy)) {
            throw new Exception('Response by is required', 400);
        }
        
        if (empty($requestType)) {
            throw new Exception('Request type is required', 400);
        }
        
        // Determine main status and sub status based on status selector
        $mainStatus = '';
        $subStatus = '';
        $remarkMessage = '';
        $responseTypeNote = 'status';
        
        $openStatuses = ['open', 'Processing', 'Following up', 'Follow-up rejected', 'Follow-up accepted', 
                         'Awaiting HO', 'Refund Applied', 'Refund FUP with Airline', 'Refund Received'];
        
        if (in_array($statusSelector, $openStatuses)) {
            $mainStatus = 'open';
            $subStatus = $statusSelector;
            if ($statusSelector == 'open') {
                $remarkMessage = 'reopenthis';
                $responseTypeNote = 'chat';
            } else {
                $remarkMessage = 'Status changed as ' . $statusSelector;
                $responseTypeNote = 'status';
            }
        } else if ($statusSelector == 'fail') {
            $mainStatus = 'fail';
            $subStatus = '';
            $remarkMessage = 'closethis';
            $responseTypeNote = 'chat';
        } else if ($statusSelector == 'success') {
            $mainStatus = 'success';
            $subStatus = '';
            $remarkMessage = 'closethis';
            $responseTypeNote = 'chat';
        } else {
            // Unknown status - set empty but still log
            $mainStatus = '';
            $subStatus = '';
            $remarkMessage = '';
            $responseTypeNote = 'status';
        }
        
        // Update request status
        $this->backendDAL->updateRequestStatus($caseId, $mainStatus, $subStatus);
        
        // Insert status change chat message if remark message is set
        if (!empty($remarkMessage)) {
            $this->backendDAL->insertChatMessage(
                $caseId,
                $requestType,
                $remarkMessage,
                $responseBy,
                $responseTypeNote,
                $memberName
            );
            
            // Update request last response
            $this->backendDAL->updateRequestLastResponse($caseId, $responseBy);
        }
        
        // Get updated request
        $request = $this->backendDAL->getRequestByCaseId($caseId);
        
        return [
            'success' => true,
            'message' => 'Request status updated successfully',
            'data' => [
                'case_id' => $caseId,
                'status' => $request['status'] ?? $mainStatus,
                'sub_status' => $request['sub_status'] ?? $subStatus
            ]
        ];
    }
    
    /**
     * Update request priority
     */
    public function updateRequestPriority($caseId, $priorityType, $responseBy, $requestType)
    {
        if (empty($caseId)) {
            throw new Exception('Case ID is required', 400);
        }
        
        if (empty($priorityType)) {
            throw new Exception('Priority type is required', 400);
        }
        
        if (empty($responseBy)) {
            throw new Exception('Response by is required', 400);
        }
        
        if (empty($requestType)) {
            throw new Exception('Request type is required', 400);
        }
        
        // Update request priority
        $this->backendDAL->updateRequestPriority($caseId, $priorityType);
        
        // Insert priority change chat message
        $replyMessage = 'Case priority updated to ' . $priorityType;
        $this->backendDAL->insertChatMessage(
            $caseId,
            $requestType,
            $replyMessage,
            $responseBy,
            'status'
        );
        
        // Update request last response
        $this->backendDAL->updateRequestLastResponse($caseId, $responseBy);
        
        // Get updated request
        $request = $this->backendDAL->getRequestByCaseId($caseId);
        
        return [
            'success' => true,
            'message' => 'Request priority updated successfully',
            'data' => [
                'case_id' => $caseId,
                'priority' => $request['priority'] ?? $priorityType
            ]
        ];
    }
}

