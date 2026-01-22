<?php

namespace App\Services;

use App\DAL\PaymentStatusDAL;

class PaymentStatusService
{
    private $dal;

    public function __construct(PaymentStatusDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get current payment details (total paid amount)
     * Line: 48-66 (in template)
     */
    public function getCurrentPaymentDetails($orderId)
    {
        $paymentAmounts = $this->dal->getPaymentHistory($orderId, false);
        $totalPaidAmount = array_sum($paymentAmounts);
        
        $booking = $this->dal->getBookingAmount($orderId);
        $totalBookingAmount = $booking ? (float)$booking['total_amount'] : 0;
        
        return [
            'order_id' => $orderId,
            'total_paid_amount' => $totalPaidAmount,
            'total_booking_amount' => $totalBookingAmount,
            'balance' => $totalBookingAmount - $totalPaidAmount
        ];
    }

    /**
     * Get current payment status (simple version)
     * Line: 159-202 (in template)
     */
    public function getCurrentPaymentStatus($orderId)
    {
        $paymentAmounts = $this->dal->getPaymentHistory($orderId, true);
        $totalPaidAmount = array_sum($paymentAmounts);
        
        $booking = $this->dal->getBookingAmount($orderId);
        $totalBookingAmount = $booking ? (float)$booking['total_amount'] : 0;
        
        if ($totalPaidAmount == 0) {
            return 'Zero Paid';
        }
        
        $balance = $totalBookingAmount - $totalPaidAmount;
        
        if ($balance < 1.00 && $balance > -1.00) {
            return ' Fully Paid';
        } else if ($balance <= -1.00) {
            return ' Over Paid';
        } else if ($balance >= 1.00) {
            return ' Partially Paid';
        } else {
            return ' Pending';
        }
    }

    /**
     * Get current payment status with BPAY checks and auto-update
     * Line: 69-157 (in template)
     */
    public function getCurrentPaymentStatus2($orderId, $autoUpdate = false)
    {
        $paymentAmounts = $this->dal->getPaymentHistory($orderId, false);
        $totalPaidAmount = array_sum($paymentAmounts);
        
        $booking = $this->dal->getBookingAmount($orderId);
        if (!$booking) {
            throw new \Exception('Booking not found', 404);
        }
        
        $totalBookingAmount = (float)$booking['total_amount'];
        $bookingPaymentStatus = $booking['payment_status'];
        
        // Check BPAY status
        $isBpayPaid = 0;
        if ($this->dal->checkBpayStatus($orderId, 'bpay_receipt_awaiting_approval')) {
            $isBpayPaid = 2;
        } elseif ($this->dal->checkBpayStatus($orderId, 'bpay_receipt_approved')) {
            $isBpayPaid = 1;
        }
        
        $finalPaymentStatus = '';
        
        if ($isBpayPaid == 1) {
            $balanceBpay = $totalBookingAmount - $totalPaidAmount;
            if ($balanceBpay < 1.00) {
                $finalPaymentStatus = 'BPAY Paid';
            } else {
                $finalPaymentStatus = 'BPAY Received';
            }
        } else if ($isBpayPaid == 2) {
            $finalPaymentStatus = 'BPAY Received';
        } else {
            if ($totalPaidAmount == 0) {
                $finalPaymentStatus = 'Zero Paid';
            } else {
                $balance = $totalBookingAmount - $totalPaidAmount;
                if ($balance < 1.00 && $balance > -1.00) {
                    $finalPaymentStatus = ' Fully Paid';
                } else if ($balance <= -1.00) {
                    $finalPaymentStatus = ' Over Paid';
                } else if ($balance >= 1.00) {
                    $finalPaymentStatus = ' Partially Paid';
                } else {
                    $finalPaymentStatus = ' Pending';
                }
            }
        }
        
        // Auto-update payment status if needed
        if ($autoUpdate && $bookingPaymentStatus == 'partially_paid' && $finalPaymentStatus != ' Partially Paid') {
            if ($finalPaymentStatus == ' Fully Paid' || $finalPaymentStatus == ' Over Paid') {
                $newPaymentStatus = 'paid';
                $currentDateAndTime = date('Y-m-d H:i:s');
                $this->dal->updatePaymentStatus($orderId, $newPaymentStatus, $currentDateAndTime, 'status_checker_cron');
            }
        }
        
        return [
            'order_id' => $orderId,
            'payment_status' => $finalPaymentStatus,
            'total_paid_amount' => $totalPaidAmount,
            'total_booking_amount' => $totalBookingAmount,
            'balance' => $totalBookingAmount - $totalPaidAmount,
            'is_bpay' => $isBpayPaid > 0,
            'bpay_status' => $isBpayPaid == 1 ? 'approved' : ($isBpayPaid == 2 ? 'awaiting_approval' : 'none')
        ];
    }
}

