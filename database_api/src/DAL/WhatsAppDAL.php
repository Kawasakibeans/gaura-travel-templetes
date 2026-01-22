<?php
/**
 * WhatsApp Data Access Layer
 * Handles database operations for WhatsApp messages and contacts
 */

namespace App\DAL;

use Exception;
use PDOException;

class WhatsAppDAL extends BaseDAL
{
    /**
     * Check if contact exists
     */
    public function contactExists($phone)
    {
        try {
            $query = "
                SELECT * 
                FROM whatsapp_messages 
                WHERE sender_id = :phone1 OR recipient_id = :phone2 
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'phone1' => $phone,
                'phone2' => $phone
            ]);
            return $result !== null;
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::contactExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if column exists in table
     */
    private function columnExists($tableName, $columnName)
    {
        try {
            $query = "
                SELECT COUNT(*) as count
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
            ";
            
            $result = $this->queryOne($query, [
                'table_name' => $tableName,
                'column_name' => $columnName
            ]);
            
            return $result && $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::columnExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add in_progress column if it doesn't exist
     */
    private function ensureInProgressColumn()
    {
        if (!$this->columnExists('whatsapp_messages', 'in_progress')) {
            try {
                $query = "
                    ALTER TABLE whatsapp_messages 
                    ADD COLUMN in_progress VARCHAR(255) NULL DEFAULT NULL
                    AFTER agent_id
                ";
                
                $this->db->exec($query);
                error_log("Added in_progress column to whatsapp_messages table");
            } catch (PDOException $e) {
                error_log("WhatsAppDAL::ensureInProgressColumn error: " . $e->getMessage());
                // Don't throw - column might already exist or we don't have ALTER permissions
            }
        }
    }
    
    /**
     * Update in progress status
     */
    public function updateInProgress($customer, $userOrNull)
    {
        try {
            // Ensure column exists
            $this->ensureInProgressColumn();
            
            $query = "
                UPDATE whatsapp_messages 
                SET in_progress = :in_progress 
                WHERE sender_id = :customer1 OR recipient_id = :customer2
            ";
            
            return $this->execute($query, [
                'in_progress' => $userOrNull,
                'customer1' => $customer,
                'customer2' => $customer
            ]);
        } catch (PDOException $e) {
            // If column still doesn't exist, provide helpful error message
            if (strpos($e->getMessage(), "Unknown column 'in_progress'") !== false) {
                throw new Exception("The 'in_progress' column does not exist in the whatsapp_messages table. Please run: ALTER TABLE whatsapp_messages ADD COLUMN in_progress VARCHAR(255) NULL DEFAULT NULL AFTER agent_id", 500);
            }
            error_log("WhatsAppDAL::updateInProgress error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get contacts list with booking information
     */
    public function getContactsList($whatsappPhoneNumber, $limit = 50)
    {
        try {
            $limit = (int)$limit;
            // Escape phone number for SQL (it's a config value, not user input)
            $escapedPhoneNumber = $this->db->quote($whatsappPhoneNumber);
            
            $query = "
                WITH latest_whatsapp AS (
                    SELECT 
                        contact,
                        MAX(updated_on) AS updated_on,
                        MAX(id) AS id
                    FROM (
                        SELECT sender_id AS contact, updated_on, id
                        FROM whatsapp_messages
                        WHERE sender_id != {$escapedPhoneNumber} AND sender_id REGEXP '^[0-9]+$'
                        
                        UNION ALL
                        
                        SELECT recipient_id AS contact, updated_on, id
                        FROM whatsapp_messages
                        WHERE recipient_id != {$escapedPhoneNumber} AND recipient_id REGEXP '^[0-9]+$'
                    ) AS messages
                    GROUP BY contact
                )
                
                SELECT 
                    lw.contact,
                    lw.updated_on AS last_activity,
                    lw.id AS last_id,
                    (
                        SELECT COUNT(*) 
                        FROM whatsapp_messages wm 
                        WHERE wm.sender_type = 'customer' 
                          AND wm.sender_id = lw.contact 
                          AND (wm.msg_read_agent IS NULL OR wm.msg_read_agent = 0)
                    ) AS unread_count,
                    (
                        SELECT wm2.message 
                        FROM whatsapp_messages wm2 
                        WHERE (wm2.sender_id = lw.contact OR wm2.recipient_id = lw.contact)
                        ORDER BY wm2.updated_on DESC 
                        LIMIT 1
                    ) AS last_message,
                    b.order_id,
                    b.trip_code,
                    b.co_order_id,
                    b.product_id,
                    b.order_type,
                    b.source,
                    b.travel_date,
                    b.payment_status,
                    p.pnr,
                    p.auto_id,
                    p.ticket_number,
                    p.phone_pax,
                    p.fname,
                    p.lname
                FROM latest_whatsapp lw
                LEFT JOIN wpk4_backend_travel_booking_pax p ON lw.contact = p.phone_pax
                LEFT JOIN wpk4_backend_travel_bookings b ON 
                    b.order_id = p.order_id AND 
                    b.co_order_id = p.co_order_id AND 
                    b.product_id = p.product_id AND 
                    b.payment_status = 'paid'
                ORDER BY lw.updated_on DESC, b.travel_date ASC
                LIMIT {$limit}
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getContactsList error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get latest update timestamp
     */
    public function getLatestUpdate()
    {
        try {
            $query = "
                SELECT MAX(updated_on) as latest_update 
                FROM whatsapp_messages
            ";
            
            $result = $this->queryOne($query);
            return $result ? $result['latest_update'] : null;
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getLatestUpdate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get latest update for customer conversation
     */
    public function getCustomerLatestUpdate($customer)
    {
        try {
            $query = "
                SELECT MAX(updated_on) as last_updated
                FROM whatsapp_messages
                WHERE sender_id = :customer1 OR recipient_id = :customer2
            ";
            
            $result = $this->queryOne($query, [
                'customer1' => $customer,
                'customer2' => $customer
            ]);
            return $result ? $result['last_updated'] : null;
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getCustomerLatestUpdate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get unread message IDs for customer
     */
    public function getUnreadMessageIds($customer)
    {
        try {
            $query = "
                SELECT message_id 
                FROM whatsapp_messages 
                WHERE sender_type = 'customer' 
                  AND sender_id = :customer 
                  AND msg_read_agent IS NULL
            ";
            
            $results = $this->query($query, ['customer' => $customer]);
            return array_column($results, 'message_id');
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getUnreadMessageIds error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Mark messages as read by agent
     */
    public function markMessagesAsRead($customer)
    {
        try {
            $query = "
                UPDATE whatsapp_messages 
                SET msg_read_agent = 1, status = 'read', updated_on = NOW() 
                WHERE sender_type = 'customer' 
                  AND sender_id = :customer 
                  AND msg_read_agent IS NULL
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['customer' => $customer]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::markMessagesAsRead error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get conversation messages
     */
    public function getConversationMessages($customer)
    {
        try {
            $query = "
                SELECT * 
                FROM whatsapp_messages 
                WHERE sender_id = :customer1 OR recipient_id = :customer2 
                ORDER BY created_at ASC
            ";
            
            return $this->query($query, [
                'customer1' => $customer,
                'customer2' => $customer
            ]);
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getConversationMessages error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert message
     */
    public function insertMessage($senderType, $senderId, $recipientId, $message, $messageId = null, $status = 'sent', $agentId = null, $msgReadAgent = null)
    {
        try {
            $query = "
                INSERT INTO whatsapp_messages 
                (sender_type, sender_id, recipient_id, message, message_id, status, agent_id, created_at, msg_read_agent) 
                VALUES (:sender_type, :sender_id, :recipient_id, :message, :message_id, :status, :agent_id, NOW(), :msg_read_agent)
            ";
            
            return $this->execute($query, [
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'message' => $message,
                'message_id' => $messageId,
                'status' => $status,
                'agent_id' => $agentId,
                'msg_read_agent' => $msgReadAgent
            ]);
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::insertMessage error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get passenger details by auto_id
     */
    public function getPassengerDetails($autoId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_travel_booking_pax 
                WHERE auto_id = :auto_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("WhatsAppDAL::getPassengerDetails error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

