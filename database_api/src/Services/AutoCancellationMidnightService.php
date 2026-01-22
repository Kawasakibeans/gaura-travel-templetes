<?php

namespace App\Services;

use App\DAL\AutoCancellationMidnightDAL;
use Exception;

class AutoCancellationMidnightService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationMidnightDAL();
    }

    /**
     * Get bookings for full payment deadline cancellation
     */
    public function getBookingsForFullPaymentCancellation(): array
    {
        $bookings = $this->dal->getBookingsForFullPaymentCancellation();

        $processedOrders = [];
        $eligibleBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (in_array($orderId, $processedOrders)) {
                continue;
            }
            $processedOrders[] = $orderId;

            $totalAmount = $this->dal->getTotalAmount($orderId);
            $paidAmount = $this->dal->getPaidAmount($orderId);

            if ($totalAmount && $totalAmount > $paidAmount && $booking['source'] != 'import') {
                $eligibleBookings[] = [
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'payment_status' => $booking['payment_status'],
                    'source' => $booking['source'],
                    'full_payment_deadline' => $booking['full_payment_deadline'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount
                ];
            }
        }

        return [
            'bookings' => $eligibleBookings,
            'total_count' => count($eligibleBookings)
        ];
    }
}

