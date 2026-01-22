<?php

namespace App\Services;

use App\DAL\AutoCancellationEvery30MinsDAL;
use Exception;

class AutoCancellationEvery30MinsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationEvery30MinsDAL();
    }

    /**
     * Get bookings eligible for deposit deadline cancellation
     */
    public function getBookingsForDepositDeadlineCancellation(): array
    {
        $bookings = $this->dal->getBookingsForDepositDeadlineCancellation();

        // Remove duplicates
        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;

                // Get paid amount
                $paidAmount = $this->dal->getPaidAmount($orderId);

                // Only include if no payment received
                if ($paidAmount == 0.00 && $booking['source'] != 'import') {
                    $uniqueBookings[] = [
                        'order_id' => $orderId,
                        'order_date' => $booking['order_date'],
                        'payment_status' => $booking['payment_status'],
                        'source' => $booking['source'],
                        'deposit_deadline' => $booking['deposit_deadline'],
                        'trams_received_amount' => $booking['trams_received_amount'],
                        'paid_amount' => $paidAmount
                    ];
                }
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings)
        ];
    }

    /**
     * Update seat availability for cancelled booking
     */
    public function updateSeatAvailability(string $orderId, string $byUser): array
    {
        $log = [];
        $currentDate = date('Y-m-d H:i:s');
        $currentDateYmd = date('Y-m-d', strtotime($currentDate));

        $booking = $this->dal->getBookingForSeatAvailability($orderId);
        if (!$booking) {
            return $log;
        }

        $tripCode = $booking['trip_code'];
        $travelDate = date('Y-m-d', strtotime($booking['travel_date']));
        $totalPax = (int)$booking['total_pax'];

        $availability = $this->dal->getCurrentSeatAvailability($tripCode, $travelDate);
        if (!$availability) {
            error_log("Query params not found - AutoCancellationEvery30MinsService - trip_code: $tripCode, date: $travelDate");
            return $log;
        }

        $currentPax = (int)$availability['pax'];
        $pricingId = $availability['pricing_id'];
        $newPax = $currentPax - $totalPax;

        // Check if log already exists
        if (!$this->dal->checkSeatAvailabilityLogExists($pricingId, $byUser, $orderId, $currentDateYmd)) {
            if ($this->dal->updateSeatAvailability($tripCode, $travelDate, $newPax, $byUser, $currentDate)) {
                $this->dal->insertSeatAvailabilityLog(
                    $pricingId,
                    $currentPax,
                    $newPax,
                    $currentDate,
                    $byUser,
                    $orderId,
                    -$totalPax
                );

                $log[] = [
                    'order_id' => $orderId,
                    'trip_code' => $tripCode,
                    'updated' => true,
                    'original_pax' => $currentPax,
                    'new_pax' => $newPax
                ];
            }
        }

        return $log;
    }
}

