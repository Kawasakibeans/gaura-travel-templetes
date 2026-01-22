<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class InternalEmailDAL extends BaseDAL
{
    /**
     * Get email by ID
     */
    public function getEmailById($emailId)
    {
        $query = "
            SELECT * FROM wpk4_internal_emails
            WHERE id = :id
        ";
        return $this->queryOne($query, ['id' => $emailId]);
    }
    
    /**
     * Mark email as read
     */
    public function markEmailAsRead($emailId)
    {
        return $this->update(
            'wpk4_internal_emails',
            ['is_read' => 1],
            ['id' => $emailId]
        );
    }
    
    /**
     * Get email thread (root email and all replies)
     */
    public function getEmailThread($emailId)
    {
        // First get the root email
        $rootEmail = $this->getEmailById($emailId);
        
        if (!$rootEmail) {
            return null;
        }
        
        $threadRootId = $rootEmail['parent_email_id'] ?: $rootEmail['id'];
        
        $query = "
            SELECT * FROM wpk4_internal_emails
            WHERE id = :thread_root_id OR parent_email_id = :thread_root_id
            ORDER BY created_at ASC
        ";
        
        return $this->query($query, ['thread_root_id' => $threadRootId]);
    }
    
    /**
     * Create new email
     */
    public function createEmail($senderId, $receiverId, $subject, $message, $isDraft = 0, $parentEmailId = null)
    {
        $query = "
            INSERT INTO wpk4_internal_emails
            (sender_id, receiver_id, parent_email_id, subject, message, created_at, is_read, is_draft)
            VALUES (:sender_id, :receiver_id, :parent_email_id, :subject, :message, :created_at, :is_read, :is_draft)
        ";
        
        $params = [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'parent_email_id' => $parentEmailId,
            'subject' => $subject,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'is_read' => 0,
            'is_draft' => $isDraft ? 1 : 0
        ];
        
        $result = $this->execute($query, $params);
        if ($result) {
            return $this->lastInsertId();
        }
        return false;
    }
    
    /**
     * Update draft email
     */
    public function updateDraft($draftId, $senderId, $receiverId, $subject, $message, $isDraft = 1)
    {
        return $this->update(
            'wpk4_internal_emails',
            [
                'receiver_id' => $receiverId,
                'subject' => $subject,
                'message' => $message,
                'is_draft' => $isDraft ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => $draftId,
                'sender_id' => $senderId
            ]
        );
    }
    
    /**
     * Search users by username or email
     */
    public function searchUsers($searchTerm, $limit = 20)
    {
        $limit = (int)$limit;
        $query = "
            SELECT wpk4_users.id, user_email, display_name 
            FROM wpk4_users 
            JOIN wpk4_usermeta ON wpk4_users.id = wpk4_usermeta.user_id 
            WHERE (wpk4_users.user_login LIKE :search_term OR wpk4_users.user_email LIKE :search_term) 
            AND wpk4_usermeta.meta_key = 'wpk4_capabilities' 
            AND (wpk4_usermeta.meta_value != 'a:0:{}' AND wpk4_usermeta.meta_value != 'a:1:{s:8:\"customer\";b:1;}')
            LIMIT {$limit}
        ";
        
        return $this->query($query, [
            'search_term' => '%' . $searchTerm . '%'
        ]);
    }
    
    /**
     * Get inbox emails for user
     */
    public function getInboxEmails($userId)
    {
        $query = "
            SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, 
                   DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as formatted_date 
            FROM wpk4_internal_emails
            WHERE parent_email_id IS NULL
            AND (
                receiver_id = :user_id
                OR (
                    sender_id = :user_id
                    AND EXISTS (
                        SELECT 1 FROM wpk4_internal_emails AS replies
                        WHERE replies.parent_email_id = wpk4_internal_emails.id
                    )
                )
            ) AND is_draft = 0
            ORDER BY created_at DESC
        ";
        
        return $this->query($query, ['user_id' => $userId]);
    }
    
    /**
     * Get sent emails for user
     */
    public function getSentEmails($userId)
    {
        $query = "
            SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, 
                   DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as formatted_date 
            FROM wpk4_internal_emails
            WHERE parent_email_id IS NULL
            AND sender_id = :user_id
            AND is_draft = 0
            ORDER BY created_at DESC
        ";
        
        return $this->query($query, ['user_id' => $userId]);
    }
    
    /**
     * Get draft emails for user
     */
    public function getDraftEmails($userId)
    {
        $query = "
            SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, 
                   DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as formatted_date 
            FROM wpk4_internal_emails 
            WHERE sender_id = :user_id
            AND is_draft = 1
            ORDER BY created_at DESC
        ";
        
        return $this->query($query, ['user_id' => $userId]);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId)
    {
        $query = "
            SELECT id, user_email, display_name, user_login
            FROM wpk4_users
            WHERE id = :user_id
        ";
        return $this->queryOne($query, ['user_id' => $userId]);
    }
}

