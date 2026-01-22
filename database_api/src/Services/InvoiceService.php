<?php
/**
 * Invoice Service - Business Logic Layer
 * Handles invoice generation and retrieval
 */

namespace App\Services;

use App\DAL\InvoiceDAL;
use Exception;

class InvoiceService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new InvoiceDAL();
    }

    /**
     * Get invoice data for order
     */
    public function getInvoiceData($orderId, $securityKey, $securityPass)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        // Security validation
        $booking = $this->dal->getBookingBasicInfo($orderId);
        
        if (!$booking) {
            throw new Exception('Order not found', 404);
        }

        // Verify security keys
        $orderDate = date('Y-m-d', strtotime($booking['order_date']));
        $md5KeyDate = md5($orderDate);
        $md5KeyOrderId = md5($orderId);

        if ($md5KeyDate !== $securityPass || $md5KeyOrderId !== $securityKey) {
            throw new Exception('Invalid security keys', 403);
        }

        // Determine source
        $source = $this->determineOrderSource($orderId, $booking['order_type']);

        // Get pax info
        $paxInfo = $this->dal->getPaxInfo($orderId);

        // Get booking details
        $bookingDetails = $this->dal->getBookingDetails($orderId);

        // Get payment details
        $payments = $this->dal->getPaymentDetails($orderId);

        return [
            'order_id' => $orderId,
            'source' => $source,
            'order_type' => $booking['order_type'],
            'order_date' => $booking['order_date'],
            'total_amount' => $booking['total_amount'],
            'payment_status' => $booking['payment_status'],
            'balance' => $booking['balance'],
            'pax_info' => $paxInfo,
            'booking_details' => $bookingDetails,
            'payments' => $payments
        ];
    }

    /**
     * Private helper methods
     */
    
    private function determineOrderSource($orderId, $orderType)
    {
        if ($orderType === 'Agent') {
            return 'WPT';
        }

        if (ctype_digit($orderId) && strlen($orderId) <= 7) {
            return 'WPT';
        } else if (ctype_alpha($orderId)) {
            return 'gds';
        } else {
            return 'gds';
        }
    }
}

