<?php
/**
 * Ticketing Message Data Access Layer
 * Handles database operations for ticketing screen issue messages
 */

namespace App\DAL;

use Exception;
use PDOException;

class TicketingMessageDAL extends BaseDAL
{
    /**
     * List active messages
     */
    public function listMessages($limit = 500, $status = 'active')
    {
        try {
            $limit = (int)$limit;
            $query = "
                SELECT * FROM wpk4_ticketing_screen_issue_message 
                WHERE status = :status 
                ORDER BY auto_id DESC 
                LIMIT {$limit}
            ";
            
            return $this->query($query, ['status' => $status]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::listMessages error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get message by ID
     */
    public function getMessageById($autoId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_ticketing_screen_issue_message 
                WHERE auto_id = :auto_id 
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::getMessageById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create message
     */
    public function createMessage($date, $message, $updatedOn, $updatedBy, $type, $status = 'active')
    {
        try {
            $query = "
                INSERT INTO wpk4_ticketing_screen_issue_message 
                (date, message, updated_on, updated_by, type, status) 
                VALUES (:date, :message, :updated_on, :updated_by, :type, :status)
            ";
            
            $this->execute($query, [
                'date' => $date,
                'message' => $message,
                'updated_on' => $updatedOn,
                'updated_by' => $updatedBy,
                'type' => $type,
                'status' => $status
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::createMessage error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get original message for logging
     */
    public function getOriginalMessage($autoId)
    {
        try {
            $query = "
                SELECT date, message, updated_on, updated_by, type, status 
                FROM wpk4_ticketing_screen_issue_message 
                WHERE auto_id = :auto_id 
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::getOriginalMessage error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Log message to log table
     */
    public function logMessage($messageId, $date, $message, $updatedOn, $updatedBy, $type, $status, $addedBy)
    {
        try {
            $query = "
                INSERT INTO wpk4_ticketing_screen_issue_message_log
                (message_id, date, message, updated_on, updated_by, type, status, added_by)
                VALUES (:message_id, :date, :message, :updated_on, :updated_by, :type, :status, :added_by)
            ";
            
            return $this->execute($query, [
                'message_id' => $messageId,
                'date' => $date,
                'message' => $message,
                'updated_on' => $updatedOn,
                'updated_by' => $updatedBy,
                'type' => $type,
                'status' => $status,
                'added_by' => $addedBy
            ]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::logMessage error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update message
     */
    public function updateMessage($autoId, $date, $message, $type, $updatedBy, $updatedOn)
    {
        try {
            $query = "
                UPDATE wpk4_ticketing_screen_issue_message
                SET date = :date, message = :message, type = :type, updated_by = :updated_by, updated_on = :updated_on
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'date' => $date,
                'message' => $message,
                'type' => $type,
                'updated_by' => $updatedBy,
                'updated_on' => $updatedOn
            ]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::updateMessage error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Soft delete message
     */
    public function softDeleteMessage($autoId, $status = 'deleted')
    {
        try {
            $query = "
                UPDATE wpk4_ticketing_screen_issue_message 
                SET status = :status 
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::softDeleteMessage error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Bulk update date for all active messages
     */
    public function bulkUpdateDate($date, $updatedOn, $updatedBy)
    {
        try {
            $query = "
                UPDATE wpk4_ticketing_screen_issue_message 
                SET date = :date, updated_on = :updated_on, updated_by = :updated_by
                WHERE status = 'active'
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'date' => $date,
                'updated_on' => $updatedOn,
                'updated_by' => $updatedBy
            ]);
            
            // Get affected rows count
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("TicketingMessageDAL::bulkUpdateDate error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Begin transaction (public wrapper)
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction (public wrapper)
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction (public wrapper)
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}

