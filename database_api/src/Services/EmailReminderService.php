<?php

namespace App\Services;

use App\DAL\EmailReminderDAL;

class EmailReminderService
{
    private $dal;

    public function __construct(EmailReminderDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get bookings due in X days
     * Line: 34-38, 68-72, 103-107 (in template)
     */
    public function getBookingsDueInDays($days, $limit = null)
    {
        if (!in_array($days, [1, 4, 7])) {
            throw new \Exception('days must be 1, 4, or 7', 400);
        }
        
        return $this->dal->getBookingsDueInDays($days, $limit);
    }

    /**
     * Get all upcoming reminders (7, 4, 1 days)
     * Line: 34-134 (in template)
     */
    public function getAllUpcomingReminders()
    {
        $bookings = $this->dal->getBookingsDueInDayRanges([7, 4, 1]);
        
        // Group by days
        $result = [
            '7_days' => [],
            '4_days' => [],
            '1_day' => []
        ];
        
        foreach ($bookings as $booking) {
            $daysLeft = (int)$booking['total_days_left'];
            if ($daysLeft === 7) {
                $result['7_days'][] = $booking;
            } elseif ($daysLeft === 4) {
                $result['4_days'][] = $booking;
            } elseif ($daysLeft === 1) {
                $result['1_day'][] = $booking;
            }
        }
        
        return $result;
    }

    /**
     * Process reminders for specific days
     * Line: 40-64, 74-99, 109-134 (in template)
     */
    public function processRemindersForDays($days, $insertHistory = true)
    {
        if (!in_array($days, [1, 4, 7])) {
            throw new \Exception('days must be 1, 4, or 7', 400);
        }
        
        $bookings = $this->dal->getBookingsDueInDays($days);
        $processed = [];
        
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            $emailAddress = $booking['email'] ?: null;
            
            if (!$emailAddress) {
                continue; // Skip if no email
            }
            
            // Check if already sent
            $alreadySent = $this->dal->checkReminderEmailSent($orderId, $days);
            
            if ($alreadySent && $insertHistory) {
                continue; // Skip if already sent
            }
            
            // Insert email history if requested
            if ($insertHistory && !$alreadySent) {
                $historyId = $this->dal->insertReminderEmailHistory($orderId, $emailAddress, $days);
            }
            
            $processed[] = [
                'order_id' => $orderId,
                'email' => $emailAddress,
                'days_until_travel' => $days,
                'email_sent' => !$alreadySent,
                'history_inserted' => $insertHistory && !$alreadySent
            ];
        }
        
        return [
            'days' => $days,
            'total_bookings' => count($bookings),
            'processed' => count($processed),
            'bookings' => $processed
        ];
    }

    /**
     * Process all reminders (7, 4, 1 days)
     * Line: 34-134 (in template)
     */
    public function processAllReminders($insertHistory = true)
    {
        $result = [
            '7_days' => $this->processRemindersForDays(7, $insertHistory),
            '4_days' => $this->processRemindersForDays(4, $insertHistory),
            '1_day' => $this->processRemindersForDays(1, $insertHistory)
        ];
        
        $totalProcessed = $result['7_days']['processed'] + 
                          $result['4_days']['processed'] + 
                          $result['1_day']['processed'];
        
        return [
            'total_processed' => $totalProcessed,
            'details' => $result
        ];
    }
}

