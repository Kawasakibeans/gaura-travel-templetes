<?php

namespace App\Services;

use App\DAL\AzupaySettlementDAL;
use Exception;

class AzupaySettlementService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AzupaySettlementDAL();
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
     * Process Azupay settlement transaction
     */
    public function processSettlementTransaction(array $transaction): array
    {
        $transactionLocalDate = date('Y-m-d H:i:s', strtotime(substr($transaction['DateTime'], 0, 19)));
        $newTransactionDate = substr($transaction['LocalTime'], 0, 19);
        $amount = (float)$transaction['Amount'];
        $paymentTypeBlock = $transaction['TransactionType'];
        $payId = $transaction['PayId'];
        $paymentRequestId = $transaction['ParentTransactionId'] ?? null;
        $paymentRequestIdChild = $transaction['TransactionId'] ?? null;
        $crdr = $transaction['CRDR'] ?? 'CR';

        $paymentMethodNumber = '7';
        $newSettlementDate = date("Y-m-d", strtotime("+1 days", strtotime($newTransactionDate))) . ' ' . date("H:i:s");

        $orderId = null;
        $orderIdFromBookingTable = null;
        $paymentStatusFromBookingTable = null;
        $matchHidden = 'New';
        $isBookingExists = false;
        $match = [];

        // Handle PaymentRequest with ParentTransactionId
        if ($paymentTypeBlock == 'PaymentRequest' && !empty($paymentRequestId)) {
            // Get payment_request_id from payid if needed
            if (empty($paymentRequestId)) {
                $paymentRequestId = $this->dal->getPaymentRequestIdByPayId($payId);
            }

            if ($paymentRequestId) {
                $orderId = $this->dal->getOrderIdByPaymentRequestId($paymentRequestId);
            }

            if ($orderId) {
                $booking = $this->dal->getBookingByOrderId($orderId);
                if ($booking) {
                    $orderIdFromBookingTable = $booking['order_id'];
                    $paymentStatusFromBookingTable = $booking['payment_status'];
                    $isBookingExists = true;
                } else {
                    $match[] = 'Booking is not exist';
                }
            }

            // Check if payment exists
            $orderIdFromPaymentTable = $this->dal->checkPaymentExists($paymentRequestId, $amount, $paymentMethodNumber);

            if ($orderId && $orderId == $orderIdFromPaymentTable) {
                $matchHidden = 'Existing';
            } else {
                $match[] = 'Payment is not exist';
            }
        }
        // Handle PaymentRequest with TransactionId (child)
        elseif ($paymentTypeBlock == 'PaymentRequest' && !empty($paymentRequestIdChild) && empty($paymentRequestId)) {
            if (empty($paymentRequestIdChild)) {
                $paymentRequestIdChild = $this->dal->getPaymentRequestIdByPayId($payId);
            }

            if ($paymentRequestIdChild) {
                $orderId = $this->dal->getOrderIdByPaymentRequestId($paymentRequestIdChild);
            }

            if ($orderId) {
                $booking = $this->dal->getBookingByOrderId($orderId);
                if ($booking) {
                    $orderIdFromBookingTable = $booking['order_id'];
                    $paymentStatusFromBookingTable = $booking['payment_status'];
                    $isBookingExists = true;
                } else {
                    $match[] = 'Booking is not exist';
                }
            }

            $orderIdFromPaymentTable = $this->dal->checkPaymentExists($paymentRequestIdChild, $amount, $paymentMethodNumber);

            if ($orderId && $orderId == $orderIdFromPaymentTable) {
                $matchHidden = 'Existing';
            } else {
                $match[] = 'Payment is not exist';
            }
        }
        // Handle Payment type
        elseif ($paymentTypeBlock == 'Payment' && !empty($paymentRequestIdChild)) {
            $orderId = $this->dal->getOrderIdByPaymentRequestId($paymentRequestIdChild);

            if ($orderId) {
                $booking = $this->dal->getBookingByOrderId($orderId);
                if ($booking) {
                    $orderIdFromBookingTable = $booking['order_id'];
                    $paymentStatusFromBookingTable = $booking['payment_status'];
                    $isBookingExists = true;
                } else {
                    $match[] = 'Booking is not exist';
                }
            }

            // For DR (Debit), use negative amount
            $checkAmount = ($crdr == 'DR') ? -$amount : $amount;
            $orderIdFromPaymentTable = $this->dal->checkPaymentExists($paymentRequestIdChild, $checkAmount, $paymentMethodNumber);

            if ($orderId && $orderId == $orderIdFromPaymentTable) {
                $matchHidden = 'Existing';
            } else {
                $match[] = 'Payment is not exist';
            }
        }

        return [
            'order_id' => $orderId,
            'order_id_from_booking' => $orderIdFromBookingTable,
            'payment_status' => $paymentStatusFromBookingTable,
            'amount' => $amount,
            'transaction_date' => $newTransactionDate,
            'settlement_date' => $newSettlementDate,
            'payment_request_id' => $paymentRequestId ?? $paymentRequestIdChild,
            'match_status' => $matchHidden,
            'is_booking_exists' => $isBookingExists,
            'match_messages' => $match
        ];
    }

    /**
     * Update payment reconciliation
     */
    public function updatePaymentReconciliation(array $params): bool
    {
        $orderId = $params['order_id'] ?? null;
        $paymentRequestId = $params['payment_request_id'] ?? null;
        $transactionDate = $params['transaction_date'] ?? null;
        $amount = $params['amount'] ?? null;
        $settlementDate = $params['settlement_date'] ?? null;
        $paymentMethod = $params['payment_method'] ?? '7';

        if (empty($orderId) || empty($paymentRequestId) || empty($transactionDate) || $amount === null || empty($settlementDate)) {
            throw new Exception('Missing required parameters for reconciliation update', 400);
        }

        return $this->dal->updatePaymentReconciliation(
            $orderId,
            $paymentRequestId,
            $transactionDate,
            (float)$amount,
            $settlementDate,
            $paymentMethod
        );
    }
}

