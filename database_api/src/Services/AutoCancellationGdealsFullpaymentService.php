<?php

namespace App\Services;

use App\DAL\AutoCancellationGdealsFullpaymentDAL;
use Exception;

class AutoCancellationGdealsFullpaymentService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationGdealsFullpaymentDAL();
    }

    /**
     * Get GDeals bookings for full payment cancellation
     */
    public function getGdealsBookingsForFullPaymentCancellation(): array
    {
        $bookings = $this->dal->getGdealsBookingsForFullPaymentCancellation();

        $eligibleBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];

            $totalAmount = $this->dal->getTotalAmount($orderId);
            $paidAmount = $this->dal->getPaidAmount($orderId);

            if ($totalAmount && $totalAmount > $paidAmount) {
                $eligibleBookings[] = [
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'payment_status' => $booking['payment_status']
                ];
            }
        }

        return [
            'bookings' => $eligibleBookings,
            'total_count' => count($eligibleBookings)
        ];
    }

    /**
     * Cancel booking for full payment deadline
     */
    public function cancelBookingForFullPayment(string $orderId): array
    {
        $currentDate = date('Y-m-d H:i:s');
        $byUser = 'fullpayment_deadline_cancellation';

        // Update booking status
        $this->dal->updateBookingStatus($orderId, $byUser, $currentDate);

        // Insert history
        $this->dal->insertBookingUpdateHistory($orderId, $currentDate, $byUser);

        // Update seat availability
        $seatUpdateLog = $this->updateSeatAvailability($orderId, $byUser);

        return [
            'order_id' => $orderId,
            'status' => 'canceled',
            'seat_update' => $seatUpdateLog
        ];
    }

    /**
     * Update seat availability for cancelled booking
     */
    private function updateSeatAvailability(string $orderId, string $byUser): array
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
            error_log("No seat availability found for trip_code: $tripCode, date: $travelDate");
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
                    'updated' => true
                ];
            }
        }

        return $log;
    }
}

