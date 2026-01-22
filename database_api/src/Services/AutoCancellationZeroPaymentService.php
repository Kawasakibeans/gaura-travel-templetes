<?php

namespace App\Services;

use App\DAL\AutoCancellationZeroPaymentDAL;
use Exception;

class AutoCancellationZeroPaymentService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AutoCancellationZeroPaymentDAL();
    }

    /**
     * Get bookings for zero payment cancellation (all queries)
     * Returns all 4 query results matching tpl_auto_cancellation_for_zeropayment_cron.php
     */
    public function getBookingsForZeroPaymentCancellation(?string $previousDays = null): array
    {
        $allResults = $this->dal->getBookingsForZeroPaymentCancellation($previousDays);

        $processedOrders = [];
        $result = [
            'reminder' => [
                'bookings' => [],
                'total_count' => 0
            ],
            'zero_payment_3hours' => [
                'bookings' => [],
                'total_count' => 0
            ],
            'fit_25hours' => [
                'bookings' => [],
                'total_count' => 0
            ],
            'bpay_96hours' => [
                'bookings' => [],
                'total_count' => 0
            ]
        ];

        // Process reminder bookings (query 1)
        foreach ($allResults['reminder'] as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $result['reminder']['bookings'][] = [
                    'auto_id' => $booking['auto_id'],
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'travel_date' => $booking['travel_date'],
                    'payment_status' => $booking['payment_status'],
                    'trams_received_amount' => $booking['trams_received_amount'] ?? '0.00',
                    'new_status' => 'email sent'
                ];
            }
        }
        $result['reminder']['total_count'] = count($result['reminder']['bookings']);

        // Process zero payment 3 hours bookings (query 2)
        $processedOrders = [];
        foreach ($allResults['zero_payment_3hours'] as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $result['zero_payment_3hours']['bookings'][] = [
                    'auto_id' => $booking['auto_id'],
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'travel_date' => $booking['travel_date'],
                    'payment_status' => $booking['payment_status'],
                    'trams_received_amount' => $booking['trams_received_amount'] ?? '0.00',
                    'new_status' => 'cancel'
                ];
            }
        }
        $result['zero_payment_3hours']['total_count'] = count($result['zero_payment_3hours']['bookings']);

        // Process FIT 25 hours bookings (query 3)
        $processedOrders = [];
        foreach ($allResults['fit_25hours'] as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $result['fit_25hours']['bookings'][] = [
                    'auto_id' => $booking['auto_id'],
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'travel_date' => $booking['travel_date'],
                    'payment_status' => $booking['payment_status'],
                    'trams_received_amount' => $booking['trams_received_amount'] ?? '0.00',
                    'new_status' => 'cancel'
                ];
            }
        }
        $result['fit_25hours']['total_count'] = count($result['fit_25hours']['bookings']);

        // Process BPAY 96 hours bookings (query 4)
        $processedOrders = [];
        foreach ($allResults['bpay_96hours'] as $booking) {
            $orderId = $booking['order_id'];
            if (!in_array($orderId, $processedOrders)) {
                $processedOrders[] = $orderId;
                $result['bpay_96hours']['bookings'][] = [
                    'auto_id' => $booking['auto_id'],
                    'order_id' => $orderId,
                    'order_date' => $booking['order_date'],
                    'travel_date' => $booking['travel_date'],
                    'payment_status' => $booking['payment_status'],
                    'trams_received_amount' => $booking['trams_received_amount'] ?? '0.00',
                    'new_status' => 'cancel'
                ];
            }
        }
        $result['bpay_96hours']['total_count'] = count($result['bpay_96hours']['bookings']);

        return $result;
    }
}