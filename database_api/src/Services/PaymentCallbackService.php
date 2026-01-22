<?php
/**
 * Payment Callback Service - Business Logic Layer
 * Handles SkyPay payment callback processing
 */

namespace App\Services;

use App\DAL\PaymentCallbackDAL;
use Exception;

class PaymentCallbackService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentCallbackDAL();
    }

    /**
     * Process payment callback
     */
    public function processCallback($callbackData)
    {
        // Validate required callback data
        $requiredFields = ['order_id', 'transaction_id', 'amount', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($callbackData[$field])) {
                throw new Exception("Field '$field' is required in callback data", 400);
            }
        }

        $orderId = $callbackData['order_id'];

        // Get booking info
        $booking = $this->dal->getBookingInfo($orderId);
        
        if (!$booking) {
            throw new Exception('Order not found', 404);
        }

        // Process based on payment status
        if ($callbackData['status'] === 'success' || $callbackData['status'] === 'approved') {
            // Log successful payment
            $paymentId = $this->dal->logPayment($orderId, $callbackData);

            // Update booking payment status
            $this->dal->updateBookingPaymentStatus($orderId, $callbackData['amount']);

            // Log history
            $this->dal->logPaymentHistory($orderId, 'payment_completed', $callbackData['amount']);

            return [
                'status' => 'success',
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'transaction_id' => $callbackData['transaction_id'],
                'amount' => $callbackData['amount'],
                'message' => 'Payment processed successfully'
            ];
        } else {
            // Log failed payment
            $this->dal->logFailedPayment($orderId, $callbackData);

            return [
                'status' => 'failed',
                'order_id' => $orderId,
                'transaction_id' => $callbackData['transaction_id'],
                'message' => 'Payment failed or declined'
            ];
        }
    }

    /**
     * Get payment callback history
     */
    public function getCallbackHistory($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        $history = $this->dal->getPaymentHistory($orderId);

        return [
            'order_id' => $orderId,
            'history' => $history,
            'total_count' => count($history)
        ];
    }
}

