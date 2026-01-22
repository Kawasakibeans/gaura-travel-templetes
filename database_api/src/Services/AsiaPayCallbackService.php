<?php
/**
 * AsiaPay Callback Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AsiaPayCallbackDAL;
use Exception;

class AsiaPayCallbackService
{
    private $asiaPayDAL;
    
    public function __construct()
    {
        $this->asiaPayDAL = new AsiaPayCallbackDAL();
    }
    
    /**
     * Extract order ID from payment reference
     */
    private function extractOrderId($paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception('Payment reference is required', 400);
        }
        
        $firstChar = substr($paymentRef, 0, 1);
        if ($firstChar < '5') {
            return substr($paymentRef, 0, 6);
        } else {
            return substr($paymentRef, 0, 9);
        }
    }
    
    /**
     * Calculate payment amount
     */
    private function calculatePaymentAmount($orderId)
    {
        // Try to get from postmeta first
        $orderTotalMeta = $this->asiaPayDAL->getOrderTotalFromPostmeta($orderId);
        $paymentPartialValue = '';
        
        if ($orderTotalMeta) {
            $orderData = @unserialize($orderTotalMeta);
            if (is_array($orderData) && isset($orderData['total_partial'])) {
                $paymentPartialValue = $orderData['total_partial'];
            }
        }
        
        // Fallback: calculate from booking
        if (empty($paymentPartialValue)) {
            $booking = $this->asiaPayDAL->getBookingAmount($orderId);
            if ($booking && isset($booking['total_pax'])) {
                $paymentPartialValue = $booking['total_pax'] * 5;
            } else {
                throw new Exception('Could not determine payment amount', 400);
            }
        }
        
        // Format to 2 decimal places
        return number_format((float)$paymentPartialValue, 2, '.', '');
    }
    
    /**
     * Calculate payment refund deadline
     */
    private function calculateRefundDeadline($orderId)
    {
        $orderDate = $this->asiaPayDAL->getOrderDate($orderId);
        if (!$orderDate) {
            // Use current date as fallback
            $orderDate = date('Y-m-d H:i:s');
        }
        
        // Add 96 hours
        return date('Y-m-d H:i:s', strtotime($orderDate . ' +96 hours'));
    }
    
    /**
     * Handle failed payment callback
     */
    public function handleFailedCallback($paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception('Payment reference is required', 400);
        }
        
        // Insert callback record
        $this->asiaPayDAL->insertFailedCallback($paymentRef);
        
        // Extract order ID
        $orderId = $this->extractOrderId($paymentRef);
        
        // Generate redirect URL
        $encOrderId = md5($orderId);
        $redirectUrl = "https://gauratravel.com.au/customer-portal/?p=booking&orderID=" . $orderId . "&key=" . $encOrderId;
        
        return [
            'success' => true,
            'status' => 'failed',
            'payment_ref' => $paymentRef,
            'order_id' => $orderId,
            'redirect_url' => $redirectUrl
        ];
    }
    
    /**
     * Handle successful payment callback
     */
    public function handleSuccessCallback($paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception('Payment reference is required', 400);
        }
        
        // Extract order ID
        $orderId = $this->extractOrderId($paymentRef);
        
        // Calculate payment amount
        $paymentAmount = $this->calculatePaymentAmount($orderId);
        
        // Get invoice ID
        $invoiceId = $this->asiaPayDAL->getLatestInvoiceId($orderId);
        
        // Calculate refund deadline
        $refundDeadline = $this->calculateRefundDeadline($orderId);
        
        // Check if payment already exists (prevent duplicates)
        if (!$this->asiaPayDAL->paymentExists($orderId, $paymentAmount, '8', 'deposit')) {
            // Insert payment history
            $this->asiaPayDAL->insertPaymentHistory(
                $orderId,
                $paymentAmount,
                $paymentRef,
                $refundDeadline,
                $invoiceId
            );
        }
        
        // Insert callback record
        $this->asiaPayDAL->insertSuccessCallback($paymentRef, $paymentAmount);
        
        // Generate redirect URL
        $encOrderId = md5($orderId);
        $redirectUrl = "https://gauratravel.com.au/customer-portal/?p=booking&orderID=" . $orderId . "&key=" . $encOrderId;
        
        return [
            'success' => true,
            'status' => 'success',
            'payment_ref' => $paymentRef,
            'order_id' => $orderId,
            'amount' => $paymentAmount,
            'invoice_id' => $invoiceId,
            'refund_deadline' => $refundDeadline,
            'redirect_url' => $redirectUrl
        ];
    }
}

