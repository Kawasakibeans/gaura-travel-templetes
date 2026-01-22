<?php
/**
 * Auto Cancellation Service - Business Logic Layer
 * Handles booking auto-cancellation based on time and payment criteria
 */

namespace App\Services;

use App\DAL\AutoCancellationDAL;
use Exception;

class AutoCancellationService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationDAL();
    }

    /**
     * Get bookings pending email reminder (20 minutes after booking)
     */
    public function getPendingEmailReminders($limit = 100)
    {
        $bookings = $this->dal->getPendingEmailReminders($limit);
        
        $processed = $this->processBookings($bookings);

        return [
            'bookings' => $processed['bookings'],
            'total_count' => count($processed['bookings']),
            'type' => 'email_reminder_20min',
            'description' => 'Bookings pending payment reminder email (20 minutes after booking)'
        ];
    }

    /**
     * Get bookings for 3-hour cancellation (zero paid)
     */
    public function getPending3HourCancellation($limit = 100)
    {
        $bookings = $this->dal->getPending3HourCancellation($limit);
        
        $processed = $this->processBookings($bookings);

        return [
            'bookings' => $processed['bookings'],
            'total_count' => count($processed['bookings']),
            'type' => '3hour_zero_paid',
            'description' => 'GDeals/FIT bookings with zero payment after 3 hours'
        ];
    }

    /**
     * Get bookings for 25-hour cancellation (FIT partially paid)
     */
    public function getPending25HourCancellation($limit = 100)
    {
        $bookings = $this->dal->getPending25HourCancellation($limit);
        
        $processed = $this->processBookings($bookings);

        return [
            'bookings' => $processed['bookings'],
            'total_count' => count($processed['bookings']),
            'type' => '25hour_partially_paid',
            'description' => 'FIT bookings partially paid after 25 hours'
        ];
    }

    /**
     * Get bookings for 96-hour cancellation (partially paid)
     */
    public function getPending96HourCancellation($limit = 100)
    {
        $bookings = $this->dal->getPending96HourCancellation($limit);
        
        $processed = $this->processBookings($bookings);

        return [
            'bookings' => $processed['bookings'],
            'total_count' => count($processed['bookings']),
            'type' => '96hour_partially_paid',
            'description' => 'GDeals/FIT bookings partially paid after 96 hours'
        ];
    }

    /**
     * Get bookings past deposit deadline
     */
    public function getPendingDepositDeadline($limit = 100)
    {
        $bookings = $this->dal->getPendingDepositDeadline($limit);
        
        $processed = $this->processBookings($bookings);

        return [
            'bookings' => $processed['bookings'],
            'total_count' => count($processed['bookings']),
            'type' => 'deposit_deadline',
            'description' => 'Bookings past deposit deadline with zero payment'
        ];
    }

    /**
     * Get all pending cancellations summary
     */
    public function getAllPendingCancellations()
    {
        return [
            'email_reminders_20min' => [
                'count' => $this->dal->getPendingEmailRemindersCount(),
                'endpoint' => '/v1/auto-cancellation/pending-email-reminders'
            ],
            'cancellation_3hour_zero_paid' => [
                'count' => $this->dal->getPending3HourCancellationCount(),
                'endpoint' => '/v1/auto-cancellation/pending-3hour'
            ],
            'cancellation_25hour_partially_paid' => [
                'count' => $this->dal->getPending25HourCancellationCount(),
                'endpoint' => '/v1/auto-cancellation/pending-25hour'
            ],
            'cancellation_96hour_partially_paid' => [
                'count' => $this->dal->getPending96HourCancellationCount(),
                'endpoint' => '/v1/auto-cancellation/pending-96hour'
            ],
            'deposit_deadline_passed' => [
                'count' => $this->dal->getPendingDepositDeadlineCount(),
                'endpoint' => '/v1/auto-cancellation/pending-deposit-deadline'
            ]
        ];
    }

    /**
     * Process bookings - calculate payment amounts and filter
     */
    private function processBookings($bookings)
    {
        $processed = [];
        $processedOrderIds = [];

        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];

            // Skip duplicates
            if (in_array($orderId, $processedOrderIds)) {
                continue;
            }

            // Get payment amounts
            $paidAmount = $this->dal->getPaidAmount($orderId);
            $totalAmount = $this->dal->getTotalAmount($orderId);

            // Skip import bookings
            if ($booking['source'] === 'import') {
                continue;
            }

            $processed[] = [
                'auto_id' => $booking['auto_id'],
                'order_id' => $orderId,
                'order_date' => $booking['order_date'],
                'travel_date' => $booking['travel_date'] ?? null,
                'payment_status' => $booking['payment_status'],
                'sub_payment_status' => $booking['sub_payment_status'] ?? null,
                'order_type' => $booking['order_type'] ?? null,
                'source' => $booking['source'] ?? null,
                'total_amount' => number_format((float)$totalAmount, 2, '.', ''),
                'paid_amount' => number_format((float)$paidAmount, 2, '.', ''),
                'balance' => number_format((float)($totalAmount - $paidAmount), 2, '.', ''),
                'deposit_deadline' => $booking['deposit_deadline'] ?? null
            ];

            $processedOrderIds[] = $orderId;
        }

        return [
            'bookings' => $processed,
            'count' => count($processed)
        ];
    }

    /**
     * Process bulk cancellation (20 minutes)
     */
    public function processCancellation20Min($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'zeropaid_cancellation_20min';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', 'canceled_zero_payment', $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }

    /**
     * Process bulk cancellation (3 hours)
     */
    public function processCancellation3Hour($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'zeropaid_cancellation_3hr';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', 'canceled_zero_payment', $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }

    /**
     * Process bulk cancellation (25 hours)
     */
    public function processCancellation25Hour($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'zeropaid_cancellation_25hr';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', null, $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }

    /**
     * Process bulk cancellation (96 hours)
     */
    public function processCancellation96Hour($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'zeropaid_cancellation_96hr';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', null, $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }

    /**
     * Process bulk cancellation (deposit deadline)
     */
    public function processCancellationDepositDeadline($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'deposit_deadline_cancellation';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', null, $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }

    /**
     * Process bulk cancellation (full payment deadline)
     */
    public function processCancellationFullPaymentDeadline($orderIds)
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        $successCount = 0;
        $errors = [];
        $cancelledOrders = [];
        $byUser = 'fullpayment_deadline_cancellation';

        foreach ($orderIds as $orderId) {
            if (empty($orderId)) {
                continue;
            }

            try {
                $this->dal->cancelBooking($orderId, 'canceled', null, $byUser);
                $this->dal->updateAvailabilityForCancellation($orderId, $byUser);
                $successCount++;
                $cancelledOrders[] = $orderId;
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'cancelled_orders' => $cancelledOrders,
            'errors' => $errors,
            'total_requested' => count($orderIds)
        ];
    }
}

