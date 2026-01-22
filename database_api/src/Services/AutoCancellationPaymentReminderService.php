<?php

namespace App\Services;

use App\DAL\AutoCancellationPaymentReminderDAL;
use Exception;

class AutoCancellationPaymentReminderService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationPaymentReminderDAL();
    }

    /**
     * Get bookings eligible for payment reminder and log them
     */
    public function processPaymentReminders(): array
    {
        $bookings = $this->dal->getBookingsForPaymentReminder();
        $processedOrders = [];
        $emailLog = [];
        $currentDate = date('Y-m-d H:i:s');

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];

            // Skip duplicates
            if (in_array($orderId, $processedOrders)) {
                continue;
            }
            $processedOrders[] = $orderId;

            // Insert email history
            $insertResult = $this->dal->insertEmailHistory($orderId, $currentDate);

            $emailLog[] = [
                'order_id' => $orderId,
                'order_date' => $booking['order_date'],
                'travel_date' => $booking['travel_date'],
                'payment_status' => $booking['payment_status'],
                'trams_received_amount' => $booking['trams_received_amount'] ?? '0.00',
                'email_status' => $insertResult ? 'recorded' : 'failed'
            ];
        }

        return [
            'status' => 'success',
            'timestamp' => $currentDate,
            'total_checked' => count($bookings),
            'emails_logged' => count($emailLog),
            'details' => $emailLog
        ];
    }
}

