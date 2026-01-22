<?php
/**
 * WhatsApp message logging DAL.
 */

namespace App\DAL;

class WhatsAppMessageDAL extends BaseDAL
{
    public function logMessage(
        string $senderType,
        string $senderId,
        string $recipientId,
        string $message,
        string $messageId,
        string $status,
        int $msgReadCustomer,
        string $updatedOn
    ): void {
        $sql = "
            INSERT INTO whatsapp_messages
                (sender_type, sender_id, recipient_id, message, message_id, status, msg_read_customer, updated_on)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $this->execute($sql, [
            $senderType,
            $senderId,
            $recipientId,
            $message,
            $messageId,
            $status,
            $msgReadCustomer,
            $updatedOn,
        ]);
    }
}

