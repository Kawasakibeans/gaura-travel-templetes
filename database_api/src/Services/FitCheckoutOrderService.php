<?php
/**
 * Service for FIT checkout order lookups and updates.
 */

namespace App\Services;

use App\DAL\FitCheckoutDAL;
use DateTime;
use Exception;

class FitCheckoutOrderService
{
    private FitCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new FitCheckoutDAL();
    }

    public function getBookPayData(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new Exception('order_id must be a positive integer', 400);
        }

        return [
            'order_id' => $orderId,
            'passengers' => $this->dal->getG360PassengersByOrderId($orderId),
            'billing' => $this->dal->getYpsilonAddressByOrderId($orderId),
        ];
    }

    public function getVerifyPayData(int $orderId): array
    {
        return $this->getBookPayData($orderId);
    }

    public function getThankYouData(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new Exception('order_id must be a positive integer', 400);
        }

        $booking = $this->dal->getG360BookingByOrderId($orderId);
        if (!$booking) {
            throw new Exception('Booking not found', 404);
        }

        return [
            'order_id' => $orderId,
            'booking' => $booking,
            'passengers' => $this->dal->getG360PassengersByOrderId($orderId),
            'pnr' => $this->dal->getDistinctPnrByOrderId($orderId),
        ];
    }

    public function logHistory(int $orderId, string $metaKey, string $metaValue, string $updatedBy): array
    {
        if ($orderId <= 0) {
            throw new Exception('order_id must be a positive integer', 400);
        }
        if ($metaKey === '') {
            throw new Exception('meta_key is required', 400);
        }

        $timestamp = (new DateTime('now'))->format('Y-m-d H:i:s');
        $this->dal->logHistoryUpdate($orderId, $metaKey, $metaValue, $updatedBy ?: 'fit_checkout_by_agent', $timestamp);

        return [
            'status' => 'success',
            'order_id' => $orderId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_on' => $timestamp,
            'updated_by' => $updatedBy ?: 'fit_checkout_by_agent',
        ];
    }
}

