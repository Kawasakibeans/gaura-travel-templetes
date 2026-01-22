<?php
/**
 * WhatsApp Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\WhatsAppDAL;
use Exception;

class WhatsAppService
{
    private $whatsappDAL;
    private $whatsappApiToken;
    private $whatsappPhoneId;
    private $whatsappPhoneNumber;
    
    public function __construct()
    {
        $this->whatsappDAL = new WhatsAppDAL();
        
        // Get WhatsApp API credentials from environment or config
        // These should be set via the credential pass endpoint
        $this->whatsappApiToken = $_ENV['WHATSAPP_API_TOKEN'] ?? $GLOBALS['WHATSAPP_API_TOKEN'] ?? null;
        $this->whatsappPhoneId = $_ENV['WHATSAPP_API_PHONE_ID'] ?? $GLOBALS['WHATSAPP_API_PHONE_ID'] ?? null;
        $this->whatsappPhoneNumber = $_ENV['WHATSAPP_API_PHONE_NUMBER'] ?? $GLOBALS['WHATSAPP_API_PHONE_NUMBER'] ?? null;
    }
    
    /**
     * Add contact
     */
    public function addContact($phone)
    {
        if (empty($phone)) {
            throw new Exception('Phone number is required', 400);
        }
        
        // Clean phone number (remove non-digits)
        $phone = preg_replace('/\D/', '', $phone);
        
        if (empty($phone)) {
            throw new Exception('Invalid phone number', 400);
        }
        
        // Check if contact already exists
        if ($this->whatsappDAL->contactExists($phone)) {
            return [
                'existing' => $phone
            ];
        }
        
        return [
            'contact' => $phone,
            'fname' => '',
            'lname' => '',
            'last_message' => '',
            'last_time' => '',
            'unread_count' => 0
        ];
    }
    
    /**
     * Toggle in progress
     */
    public function toggleInProgress($customer, $user, $checked)
    {
        if (empty($customer)) {
            throw new Exception('Customer phone number is required', 400);
        }
        
        if (empty($user)) {
            throw new Exception('User is required', 400);
        }
        
        $userOrNull = $checked ? $user : null;
        $this->whatsappDAL->updateInProgress($customer, $userOrNull);
        
        return [
            'success' => true,
            'in_progress' => $userOrNull
        ];
    }
    
    /**
     * Get contacts list
     */
    public function getContactsList($limit = 50)
    {
        if (empty($this->whatsappPhoneNumber)) {
            throw new Exception('WhatsApp phone number not configured', 500);
        }
        
        $contacts = $this->whatsappDAL->getContactsList($this->whatsappPhoneNumber, $limit);
        
        // Format dates
        $today = new \DateTime();
        $yesterday = new \DateTime('-1 day');
        
        foreach ($contacts as &$contact) {
            if (!empty($contact['last_activity'])) {
                try {
                    $date = new \DateTime($contact['last_activity']);
                    
                    $label = $date->format('Y-m-d') === $today->format('Y-m-d') ? 'Today' :
                             ($date->format('Y-m-d') === $yesterday->format('Y-m-d') ? 'Yesterday' :
                             $date->format('d M Y'));
                    
                    $contact['last_seen_label'] = $label;
                    $contact['last_time'] = $date->format('H:i');
                } catch (\Exception $e) {
                    $contact['last_seen_label'] = '';
                    $contact['last_time'] = '';
                }
            } else {
                $contact['last_seen_label'] = '';
                $contact['last_time'] = '';
            }
        }
        
        return $contacts;
    }
    
    /**
     * Check contact list update
     */
    public function checkContactUpdate()
    {
        $latestUpdate = $this->whatsappDAL->getLatestUpdate();
        return [
            'latest_update' => $latestUpdate
        ];
    }
    
    /**
     * Check conversation update
     */
    public function checkConversationUpdate($customer)
    {
        if (empty($customer)) {
            throw new Exception('Customer phone number is required', 400);
        }
        
        $lastUpdated = $this->whatsappDAL->getCustomerLatestUpdate($customer);
        return [
            'last_updated' => $lastUpdated
        ];
    }
    
    /**
     * Mark messages as read
     */
    public function markMessagesAsRead($customer)
    {
        if (empty($customer)) {
            throw new Exception('Customer phone number is required', 400);
        }
        
        if (empty($this->whatsappApiToken) || empty($this->whatsappPhoneId)) {
            throw new Exception('WhatsApp API credentials not configured', 500);
        }
        
        // Get unread message IDs
        $messageIds = $this->whatsappDAL->getUnreadMessageIds($customer);
        
        // Update database
        $updatedCount = $this->whatsappDAL->markMessagesAsRead($customer);
        
        // Send read receipts to WhatsApp API
        foreach ($messageIds as $messageId) {
            if (empty($messageId)) {
                continue;
            }
            
            $this->sendReadReceipt($messageId);
        }
        
        return [
            'success' => true,
            'updated' => $updatedCount
        ];
    }
    
    /**
     * Send read receipt to WhatsApp API
     */
    private function sendReadReceipt($messageId)
    {
        if (empty($this->whatsappApiToken) || empty($this->whatsappPhoneId)) {
            return;
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];
        
        $ch = curl_init("https://graph.facebook.com/v17.0/{$this->whatsappPhoneId}/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->whatsappApiToken}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Get conversation messages
     */
    public function getConversationMessages($customer)
    {
        if (empty($customer)) {
            return [];
        }
        
        return $this->whatsappDAL->getConversationMessages($customer);
    }
    
    /**
     * Send message (text or media)
     */
    public function sendMessage($recipient, $agent, $message = '', $filePath = null, $fileName = null, $mimeType = null)
    {
        if (empty($recipient)) {
            throw new Exception('Recipient is required', 400);
        }
        
        if (empty($message) && empty($filePath)) {
            throw new Exception('Message or file is required', 400);
        }
        
        if (empty($this->whatsappApiToken) || empty($this->whatsappPhoneId) || empty($this->whatsappPhoneNumber)) {
            throw new Exception('WhatsApp API credentials not configured', 500);
        }
        
        $agent = $agent ?: 'business';
        
        // Handle media message
        if (!empty($filePath)) {
            return $this->sendMediaMessage($recipient, $agent, $filePath, $fileName, $mimeType);
        }
        
        // Handle text message
        return $this->sendTextMessage($recipient, $agent, $message);
    }
    
    /**
     * Send media message
     */
    private function sendMediaMessage($recipient, $agent, $filePath, $fileName, $mimeType)
    {
        // Upload file to WhatsApp
        $ch = curl_init("https://graph.facebook.com/v22.0/{$this->whatsappPhoneId}/media");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->whatsappApiToken}"
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new \CURLFile($filePath, $mimeType ?: 'application/octet-stream', $fileName),
            'messaging_product' => 'whatsapp'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $uploadResponseRaw = curl_exec($ch);
        $uploadError = curl_error($ch);
        curl_close($ch);
        
        $uploadResponse = json_decode($uploadResponseRaw, true);
        
        if (!isset($uploadResponse['id'])) {
            error_log("WhatsApp media upload failed: " . $uploadResponseRaw);
            throw new Exception('WhatsApp file upload failed: ' . ($uploadError ?: 'Unknown error'), 500);
        }
        
        // Determine media type
        $type = strpos($mimeType, 'image/') === 0 ? 'image' : 'document';
        $mediaPayload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => $type,
            $type => [
                'id' => $uploadResponse['id'],
                'caption' => $fileName
            ]
        ];
        
        // Send media message
        $ch = curl_init("https://graph.facebook.com/v22.0/{$this->whatsappPhoneId}/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->whatsappApiToken}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mediaPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $sendResponseRaw = curl_exec($ch);
        $sendError = curl_error($ch);
        curl_close($ch);
        
        $mediaResponse = json_decode($sendResponseRaw, true);
        $waStatus = 'failed';
        $messageId = $mediaResponse['messages'][0]['id'] ?? null;
        
        if (!empty($mediaResponse['messages']) && empty($mediaResponse['error'])) {
            $waStatus = 'sent';
        }
        
        if (!empty($mediaResponse['errors'])) {
            $waStatus = 'failed';
        }
        
        // Insert message to database
        $this->whatsappDAL->insertMessage(
            'business',
            $this->whatsappPhoneNumber,
            $recipient,
            '[Media] ' . $fileName,
            $messageId,
            $waStatus,
            $agent
        );
        
        return [
            'success' => true,
            'whatsapp_response' => $mediaResponse,
            'raw_response' => $sendResponseRaw,
            'curl_error' => $sendError
        ];
    }
    
    /**
     * Send text message
     */
    private function sendTextMessage($recipient, $agent, $message)
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['body' => $message]
        ];
        
        $ch = curl_init("https://graph.facebook.com/v22.0/{$this->whatsappPhoneId}/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->whatsappApiToken}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseRaw = curl_exec($ch);
        $textError = curl_error($ch);
        curl_close($ch);
        
        $textResponse = json_decode($responseRaw, true);
        $waStatus = 'failed';
        $messageId = $textResponse['messages'][0]['id'] ?? null;
        
        if (!empty($textResponse['messages']) && empty($textResponse['error'])) {
            $waStatus = 'sent';
        }
        
        if (!empty($textResponse['errors'])) {
            $waStatus = 'failed';
        }
        
        // Insert message to database
        $this->whatsappDAL->insertMessage(
            'business',
            $this->whatsappPhoneNumber,
            $recipient,
            $message,
            $messageId,
            $waStatus,
            $agent,
            1 // msg_read_agent = 1 for business messages
        );
        
        return [
            'success' => true,
            'status' => $waStatus,
            'whatsapp_response' => $textResponse,
            'raw_response' => $responseRaw,
            'curl_error' => $textError
        ];
    }
    
    /**
     * Get passenger details by auto_id
     */
    public function getPassengerDetails($autoId)
    {
        if (empty($autoId)) {
            throw new Exception('Auto ID is required', 400);
        }
        
        $passenger = $this->whatsappDAL->getPassengerDetails($autoId);
        
        if (!$passenger) {
            throw new Exception('Passenger not found', 404);
        }
        
        return [
            'success' => true,
            'data' => $passenger
        ];
    }
}

