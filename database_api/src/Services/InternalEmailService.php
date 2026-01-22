<?php

namespace App\Services;

use App\DAL\InternalEmailDAL;
use Exception;

class InternalEmailService
{
    private $internalEmailDAL;
    
    public function __construct()
    {
        $this->internalEmailDAL = new InternalEmailDAL();
    }
    
    /**
     * Get email by ID and mark as read
     */
    public function getEmailById($emailId)
    {
        if (empty($emailId) || !is_numeric($emailId)) {
            throw new Exception("Valid email ID is required", 400);
        }
        
        $email = $this->internalEmailDAL->getEmailById((int)$emailId);
        
        if (!$email) {
            throw new Exception("Email not found", 404);
        }
        
        // Mark as read
        $this->internalEmailDAL->markEmailAsRead((int)$emailId);
        
        // Get sender and receiver info
        $sender = $this->internalEmailDAL->getUserById($email['sender_id']);
        $receiver = $this->internalEmailDAL->getUserById($email['receiver_id']);
        
        return [
            'subject' => $email['subject'],
            'message' => nl2br(htmlspecialchars($email['message'])),
            'sender' => $sender ? $sender['display_name'] : 'Unknown',
            'sender_id' => $email['sender_id'],
            'receiver' => $receiver ? $receiver['display_name'] : 'Unknown',
            'receiver_id' => $email['receiver_id'],
            'date' => date('d M Y, H:i', strtotime($email['created_at'])),
            'email' => $sender ? $sender['user_email'] : ''
        ];
    }
    
    /**
     * Get email thread (root email and all replies)
     */
    public function getEmailThread($emailId)
    {
        if (empty($emailId) || !is_numeric($emailId)) {
            throw new Exception("Valid email ID is required", 400);
        }
        
        // Mark root email as read
        $this->internalEmailDAL->markEmailAsRead((int)$emailId);
        
        $threadEmails = $this->internalEmailDAL->getEmailThread((int)$emailId);
        
        if (empty($threadEmails)) {
            throw new Exception("Thread not found", 404);
        }
        
        $threadData = [];
        foreach ($threadEmails as $email) {
            $sender = $this->internalEmailDAL->getUserById($email['sender_id']);
            $receiver = $this->internalEmailDAL->getUserById($email['receiver_id']);
            
            $threadData[] = [
                'id' => $email['id'],
                'sender' => $sender ? $sender['display_name'] : 'Unknown',
                'receiver' => $receiver ? $receiver['display_name'] : 'Unknown',
                'subject' => htmlspecialchars($email['subject']),
                'message' => nl2br(htmlspecialchars($email['message'])),
                'created_at' => date('d M Y, H:i', strtotime($email['created_at'])),
                'parent_email_id' => $email['parent_email_id'],
                'sender_id' => $email['sender_id'],
                'sender_email' => $receiver ? $receiver['user_email'] : ''
            ];
        }
        
        return $threadData;
    }
    
    /**
     * Create or update email
     */
    public function createOrUpdateEmail($senderId, $receiverId, $subject, $message, $isDraft = 0, $parentEmailId = null, $draftId = null)
    {
        if (empty($senderId) || !is_numeric($senderId)) {
            throw new Exception("Valid sender ID is required", 400);
        }
        
        if (empty($receiverId) || !is_numeric($receiverId)) {
            throw new Exception("Valid receiver ID is required", 400);
        }
        
        if (empty($subject)) {
            throw new Exception("Subject is required", 400);
        }
        
        if (empty($message)) {
            throw new Exception("Message is required", 400);
        }
        
        // If draft_id is provided, update the draft
        if ($draftId) {
            $updated = $this->internalEmailDAL->updateDraft(
                (int)$draftId,
                (int)$senderId,
                (int)$receiverId,
                $subject,
                $message,
                $isDraft
            );
            
            if ($updated !== false) {
                return [
                    'status' => 'success',
                    'type' => 'updated',
                    'id' => $draftId
                ];
            } else {
                throw new Exception("Failed to update draft", 500);
            }
        }
        
        // Create new email
        $emailId = $this->internalEmailDAL->createEmail(
            (int)$senderId,
            (int)$receiverId,
            $subject,
            $message,
            $isDraft ? 1 : 0,
            $parentEmailId ? (int)$parentEmailId : null
        );
        
        if ($emailId === false) {
            throw new Exception("Failed to create email", 500);
        }
        
        return [
            'status' => 'success',
            'type' => 'created',
            'id' => $emailId
        ];
    }
    
    /**
     * Search users
     */
    public function searchUsers($searchTerm, $limit = 20)
    {
        if (empty($searchTerm) || strlen($searchTerm) < 2) {
            return [];
        }
        
        $users = $this->internalEmailDAL->searchUsers($searchTerm, $limit);
        
        return $users;
    }
    
    /**
     * Get inbox emails
     */
    public function getInboxEmails($userId)
    {
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception("Valid user ID is required", 400);
        }
        
        $emails = $this->internalEmailDAL->getInboxEmails((int)$userId);
        
        // Add sender and receiver names
        foreach ($emails as &$email) {
            $sender = $this->internalEmailDAL->getUserById($email['sender_id']);
            $receiver = $this->internalEmailDAL->getUserById($email['receiver_id']);
            
            $email['sender_name'] = $sender ? $sender['display_name'] : 'Unknown';
            $email['receiver_name'] = $receiver ? $receiver['display_name'] : 'Unknown';
        }
        
        return $emails;
    }
    
    /**
     * Get sent emails
     */
    public function getSentEmails($userId)
    {
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception("Valid user ID is required", 400);
        }
        
        $emails = $this->internalEmailDAL->getSentEmails((int)$userId);
        
        // Add sender and receiver names
        foreach ($emails as &$email) {
            $sender = $this->internalEmailDAL->getUserById($email['sender_id']);
            $receiver = $this->internalEmailDAL->getUserById($email['receiver_id']);
            
            $email['sender_name'] = $sender ? $sender['display_name'] : 'Unknown';
            $email['receiver_name'] = $receiver ? $receiver['display_name'] : 'Unknown';
        }
        
        return $emails;
    }
    
    /**
     * Get draft emails
     */
    public function getDraftEmails($userId)
    {
        if (empty($userId) || !is_numeric($userId)) {
            throw new Exception("Valid user ID is required", 400);
        }
        
        $emails = $this->internalEmailDAL->getDraftEmails((int)$userId);
        
        // Add receiver names
        foreach ($emails as &$email) {
            $receiver = $email['receiver_id'] ? $this->internalEmailDAL->getUserById($email['receiver_id']) : null;
            $email['receiver_name'] = $receiver ? $receiver['display_name'] : '(Not Set)';
        }
        
        return $emails;
    }
}

