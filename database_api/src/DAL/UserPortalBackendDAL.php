<?php
/**
 * User Portal Backend Data Access Layer
 * Handles database operations for user portal backend operations
 */

namespace App\DAL;

use Exception;
use PDOException;

class UserPortalBackendDAL extends BaseDAL
{
    /**
     * Check for new chat messages
     */
    public function checkNewUpdate($requestId, $replyTypeCategory)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_user_portal_request_chats 
                WHERE request_id = :request_id 
                  AND request_type = :reply_type_category 
                ORDER BY auto_id DESC 
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'request_id' => $requestId,
                'reply_type_category' => $replyTypeCategory
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::checkNewUpdate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check for new requests
     */
    public function checkNewRequests()
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_user_portal_requests 
                ORDER BY last_response_on DESC 
                LIMIT 1
            ";
            
            return $this->queryOne($query);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::checkNewRequests error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get requests by query
     */
    public function getRequests($whereClause)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_user_portal_requests 
                WHERE {$whereClause}
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::getRequests error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get chat messages for a request
     */
    public function getChatMessages($requestId, $replyTypeCategory)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_user_portal_request_chats 
                WHERE request_id = :request_id 
                  AND request_type = :reply_type_category 
                ORDER BY auto_id ASC
            ";
            
            return $this->query($query, [
                'request_id' => $requestId,
                'reply_type_category' => $replyTypeCategory
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::getChatMessages error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert chat message
     */
    public function insertChatMessage($requestId, $requestType, $response, $responseBy, $chatOrNote = 'chat', $memberName = '', $attachment = '')
    {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            // Check if attachment is provided - if so, include it in response field
            // Note: The table doesn't have an 'attachment' column, so we store attachment path in response field
            if (!empty($attachment)) {
                $response = $attachment; // Store attachment path in response field
            }
            
            // Validate and limit chat_or_note length (database column is likely VARCHAR(20) or similar)
            // Allowed values: 'chat', 'followup', 'note', 'remark', 'status', or custom note types
            // Limit to 50 characters to be safe (adjust based on actual column size)
            $allowedValues = ['chat', 'followup', 'note', 'remark', 'status'];
            if (in_array($chatOrNote, $allowedValues)) {
                // Use as-is for standard values
                $chatOrNoteValue = $chatOrNote;
            } else {
                // For custom values (from responsetypenote_additional), limit length
                $chatOrNoteValue = substr($chatOrNote, 0, 50);
            }
            
            $query = "
                INSERT INTO wpk4_backend_user_portal_request_chats 
                    (`request_id`, `request_type`, `response`, `response_by`, 
                     `response_time`, `chat_or_note`, `member_name`, `status`) 
                VALUES 
                    (:request_id, :request_type, :response, :response_by, 
                     :current_time, :chat_or_note, :member_name, 'open')
            ";
            
            return $this->execute($query, [
                'request_id' => $requestId,
                'request_type' => $requestType,
                'response' => $response,
                'response_by' => $responseBy,
                'current_time' => $currentTime,
                'chat_or_note' => $chatOrNoteValue,
                'member_name' => $memberName
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::insertChatMessage error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update request last response
     */
    public function updateRequestLastResponse($requestId, $responseBy)
    {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $query = "
                UPDATE wpk4_backend_user_portal_requests 
                SET last_response_on = :current_time, 
                    last_response_by = :response_by 
                WHERE case_id = :request_id
            ";
            
            return $this->execute($query, [
                'request_id' => $requestId,
                'current_time' => $currentTime,
                'response_by' => $responseBy
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::updateRequestLastResponse error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get request meta value
     */
    public function getRequestMeta($caseId, $metaKey)
    {
        try {
            $query = "
                SELECT meta_value 
                FROM wpk4_backend_user_portal_request_meta 
                WHERE case_id = :case_id AND meta_key = :meta_key 
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'case_id' => $caseId,
                'meta_key' => $metaKey
            ]);
            
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::getRequestMeta error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update request status
     */
    public function updateRequestStatus($caseId, $mainStatus, $subStatus)
    {
        try {
            $query = "
                UPDATE wpk4_backend_user_portal_requests 
                SET status = :main_status, sub_status = :sub_status 
                WHERE case_id = :case_id
            ";
            
            return $this->execute($query, [
                'case_id' => $caseId,
                'main_status' => $mainStatus,
                'sub_status' => $subStatus
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::updateRequestStatus error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update request priority
     */
    public function updateRequestPriority($caseId, $priority)
    {
        try {
            $query = "
                UPDATE wpk4_backend_user_portal_requests 
                SET priority = :priority 
                WHERE case_id = :case_id
            ";
            
            return $this->execute($query, [
                'case_id' => $caseId,
                'priority' => $priority
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::updateRequestPriority error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get request by case ID
     */
    public function getRequestByCaseId($caseId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_user_portal_requests 
                WHERE case_id = :case_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'case_id' => $caseId
            ]);
        } catch (PDOException $e) {
            error_log("UserPortalBackendDAL::getRequestByCaseId error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

