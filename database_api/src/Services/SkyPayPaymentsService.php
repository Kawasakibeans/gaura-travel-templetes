<?php

namespace App\Services;

use App\DAL\SkyPayPaymentsDAL;

class SkyPayPaymentsService
{
    private $dal;

    public function __construct(SkyPayPaymentsDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get SkyPay callbacks with booking details
     * Line: 169-227 (in template)
     */
    public function getSkyPayCallbacks($orderId = null, $date = null, $limit = 100)
    {
        $callbacks = $this->dal->getSkyPayCallbacks($orderId, $date, $limit);
        
        $result = [];
        foreach ($callbacks as $callback) {
            $callbackOrderId = $callback['order_id'];
            $dateTime = $callback['date_time'];
            
            // Get booking payment details
            $bookingDetails = $this->dal->getBookingPaymentDetails($callbackOrderId);
            
            $result[] = [
                'id' => $callback['id'],
                'order_id' => $callbackOrderId,
                'date_time' => $dateTime,
                'balance' => $bookingDetails ? $bookingDetails['balance'] : null,
                'payment_status' => $bookingDetails ? $bookingDetails['payment_status'] : null,
                'total_amount' => $bookingDetails ? $bookingDetails['total_amount'] : null,
                'deposit_amount' => $bookingDetails ? $bookingDetails['deposit_amount'] : null,
                'booking_details' => $bookingDetails
            ];
        }
        
        return $result;
    }

    /**
     * Get booking payment details for an order
     * Line: 206-214 (in template)
     */
    public function getBookingPaymentDetails($orderId)
    {
        $details = $this->dal->getBookingPaymentDetails($orderId);
        
        if (!$details) {
            throw new \Exception('Booking not found for order ID: ' . $orderId, 404);
        }
        
        return $details;
    }
}

