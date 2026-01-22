<?php
/**
 * SMS Dispatcher Data Access Layer
 * Handles database operations for SMS dispatching and history
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class SmsDispatcherDAL extends BaseDAL
{
    /**
     * Check if SMS was already sent today for a given type and phone
     */
    public function checkSmsSentToday(string $type, string $phone, string $date): bool
    {
        $sql = "
            SELECT auto_id 
            FROM wpk4_backend_order_sms_history 
            WHERE type = ? AND phone = ? AND DATE(added_on) <= ?
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$type, $phone, $date]);
        return $result !== false && $result !== null;
    }

    /**
     * Insert SMS history record
     */
    public function insertSmsHistory(string $orderId, string $message, string $phone, string $source, string $messageId, string $addedOn, string $addedBy, string $type): int
    {
        $sql = "
            INSERT INTO wpk4_backend_order_sms_history 
            (order_id, message, phone, source, message_id, added_on, added_by, type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $this->execute($sql, [
            $orderId,
            $message,
            $phone,
            $source,
            $messageId,
            $addedOn,
            $addedBy,
            $type
        ]);
        
        return $this->lastInsertId();
    }
}

