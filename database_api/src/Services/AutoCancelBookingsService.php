<?php

namespace App\Services;

use App\DAL\AutoCancelBookingsDAL;
use Exception;

class AutoCancelBookingsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancelBookingsDAL();
    }

    /**
     * Check IP address access
     */
    public function checkIpAccess(string $ipAddress): array
    {
        if (empty($ipAddress)) {
            throw new Exception('IP address is required', 400);
        }

        try {
            $result = $this->dal->checkIpAddress($ipAddress);
            $hasAccess = ($result !== null && is_array($result));

            return [
                'has_access' => $hasAccess,
                'ip_address' => $ipAddress,
                'ip_details' => $hasAccess ? $result : null
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to check IP address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get bookings for cancellation view
     */
    public function getBookingsForCancellation(?string $previousDays = null): array
    {
        $bookings = $this->dal->getBookingsForCancellation($previousDays);

        // Remove duplicates by order_id
        $processedOrders = [];
        $uniqueBookings = [];

        foreach ($bookings as $booking) {
            if (!in_array($booking['order_id'], $processedOrders)) {
                $processedOrders[] = $booking['order_id'];
                $uniqueBookings[] = $booking;
            }
        }

        return [
            'bookings' => $uniqueBookings,
            'total_count' => count($uniqueBookings),
            'previous_days' => $previousDays ?? date('Y-m-d', strtotime('-3 days'))
        ];
    }
}

