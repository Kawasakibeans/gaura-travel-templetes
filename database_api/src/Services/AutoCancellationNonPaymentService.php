<?php

namespace App\Services;

use App\DAL\AutoCancellationNonPaymentDAL;
use Exception;

class AutoCancellationNonPaymentService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationNonPaymentDAL();
    }

    /**
     * Get bookings for payment reminder
     */
    public function getBookingsForPaymentReminder(): array
    {
        $bookings = $this->dal->getBookingsForPaymentReminder();

        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $uniqueBookings[] = $booking;
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings)
        ];
    }

    /**
     * Get bookings for zero payment cancellation (3 hours)
     */
    public function getBookingsForZeroPaymentCancellation3Hours(): array
    {
        $bookings = $this->dal->getBookingsForZeroPaymentCancellation3Hours();

        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $uniqueBookings[] = $booking;
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings)
        ];
    }

    /**
     * Get FIT bookings for cancellation (25 hours)
     */
    public function getFitBookingsForCancellation25Hours(): array
    {
        $bookings = $this->dal->getFitBookingsForCancellation25Hours();

        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $uniqueBookings[] = $booking;
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings)
        ];
    }

    /**
     * Get bookings for BPAY cancellation (96 hours)
     */
    public function getBookingsForBpayCancellation96Hours(): array
    {
        $bookings = $this->dal->getBookingsForBpayCancellation96Hours();

        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $uniqueBookings[] = $booking;
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings)
        ];
    }
}

