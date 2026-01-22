<?php
/**
 * SMS Dispatcher Service
 * Business logic for SMS dispatching
 */

namespace App\Services;

use App\DAL\SmsDispatcherDAL;

class SmsDispatcherService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SmsDispatcherDAL();
    }

    /**
     * Check if SMS was already sent today
     */
    public function checkSmsSentToday(string $type, string $phone): bool
    {
        $currentDate = date('Y-m-d');
        return $this->dal->checkSmsSentToday($type, $phone, $currentDate);
    }

    /**
     * Log SMS history
     */
    public function logSmsHistory(string $orderId, string $message, string $phone, string $source, string $messageId, string $addedBy, string $type): array
    {
        $currentDate = date('Y-m-d H:i:s');
        
        $id = $this->dal->insertSmsHistory(
            $orderId,
            $message,
            $phone,
            $source,
            $messageId,
            $currentDate,
            $addedBy,
            $type
        );
        
        return [
            'id' => $id,
            'order_id' => $orderId,
            'phone' => $phone,
            'message_id' => $messageId,
            'type' => $type,
            'added_on' => $currentDate
        ];
    }
}

