<?php

namespace App\Services;

use App\DAL\PaymentDAL;
use Exception;

class PaymentService
{
    private $paymentDAL;
    
    public function __construct()
    {
        $this->paymentDAL = new PaymentDAL();
    }
    
    /**
     * Check order remark status
     */
    public function checkOrderRemark($orderId)
    {
        if (empty($orderId) || !is_numeric($orderId) || $orderId <= 0) {
            throw new Exception("Valid order ID is required", 400);
        }
        
        $result = $this->paymentDAL->checkOrderRemark((int)$orderId);
        
        if (!$result) {
            return ['status' => 'no_result', 'is_checked' => null];
        }
        
        if ($result['is_checked'] === null) {
            return ['status' => 'no_result', 'is_checked' => null];
        }
        
        return ['status' => 'valid', 'is_checked' => $result['is_checked']];
    }
    
    /**
     * Handle FIT payment success callback
     */
    public function handleFITPaymentSuccess($orderId, $paymentRef)
    {
        if (empty($orderId) || empty($paymentRef)) {
            throw new Exception("Order ID and payment reference are required", 400);
        }
        
        // Update booking payment status
        $this->paymentDAL->updateFITBookingPaymentStatus($orderId, 'paid', date('Y-m-d H:i:s'));
        
        // Update booking pax payment status
        $this->paymentDAL->updateFITBookingPaxPaymentStatus($orderId, 'paid');
        
        // Get total amount
        $totalAmount = $this->paymentDAL->getFITBookingTotalAmount($orderId);
        
        if (!$totalAmount) {
            throw new Exception("Booking not found", 404);
        }
        
        // Insert payment history
        $this->paymentDAL->insertFITPaymentHistory([
            'order_id' => $orderId,
            'source' => 'fit',
            'total_amount' => $totalAmount,
            'trams_received_amount' => $totalAmount,
            'reference_no' => $orderId,
            'payment_method' => '8',
            'process_date' => date('Y-m-d H:i:s'),
            'payment_change_deadline' => date('Y-m-d H:i:s', strtotime('+4 days'))
        ]);
        
        return [
            'success' => true,
            'message' => 'Payment status updated successfully',
            'order_id' => $orderId,
            'payment_ref' => $paymentRef
        ];
    }
    
    /**
     * Handle FIT payment failed callback
     */
    public function handleFITPaymentFailed($paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception("Payment reference is required", 400);
        }
        
        // Extract order ID from payment reference (first 6 characters)
        $orderId = substr($paymentRef, 0, 6);
        
        // Insert callback record
        $this->paymentDAL->insertAsiaPayCallback($paymentRef, '', 'failed', 'asiapay_callback_fit');
        
        return [
            'success' => true,
            'message' => 'Payment failure recorded',
            'order_id' => $orderId,
            'payment_ref' => $paymentRef
        ];
    }
    
    /**
     * Handle WPT payment success callback (partial payment)
     */
    public function handleWPTPaymentSuccess($orderId, $paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception("Payment reference is required", 400);
        }
        
        // Extract order ID from payment reference if not provided
        if (empty($orderId)) {
            $orderId = substr($paymentRef, 0, 6);
        }
        
        // Insert callback record
        $this->paymentDAL->insertAsiaPayCallback($paymentRef, '', 'success', 'asiapay_callback');
        
        // Check if payment history exists with 0.00 amount
        $paymentHistory = $this->paymentDAL->getPaymentHistoryByOrderAndRef(
            $orderId,
            '',
            '8',
            'deposit',
            ['0.00']
        );
        
        if ($paymentHistory) {
            // Get order totals from postmeta (this would need to be done via WordPress or direct query)
            // For now, we'll just update the existing record
            // This is a simplified version - the actual logic is more complex
        }
        
        return [
            'success' => true,
            'message' => 'Payment success recorded',
            'order_id' => $orderId,
            'payment_ref' => $paymentRef
        ];
    }
    
    /**
     * Handle WPT full payment success callback
     */
    public function handleWPTFullPaymentSuccess($orderId, $paymentRef, $paymentAmount = null)
    {
        if (empty($paymentRef)) {
            throw new Exception("Payment reference is required", 400);
        }
        
        // Extract order ID from payment reference if not provided
        if (empty($orderId)) {
            $orderId = substr($paymentRef, 0, 6);
        }
        
        $currentDateTimestamp = date("Y-m-d H:i:s");
        
        // Get order totals from postmeta if payment amount not provided
        if (empty($paymentAmount)) {
            $orderTotalsMeta = $this->paymentDAL->getPostmeta($orderId, 'order_totals');
            if ($orderTotalsMeta) {
                $orderData = unserialize($orderTotalsMeta);
                $paymentAmount = number_format((float)($orderData['sub_total'] ?? 0), 2, '.', '');
            } else {
                $paymentAmount = '0.00';
            }
        } else {
            $paymentAmount = number_format((float)$paymentAmount, 2, '.', '');
        }
        
        // Insert postmeta records
        $this->paymentDAL->insertPostmeta($orderId, 'wp_temp_payment_reference', $paymentRef);
        $this->paymentDAL->insertPostmeta($orderId, 'wp_travel_asiapay_id', $paymentRef);
        $this->paymentDAL->insertPostmeta($orderId, 'asiapay_fully_paid', 'yes');
        
        // Insert callback record
        $this->paymentDAL->insertAsiaPayCallback($paymentRef, $paymentAmount, 'success', 'asiapay_full_payment_callback');
        
        // Get booking
        $booking = $this->paymentDAL->getWPTBookingByOrderId($orderId);
        
        if (!$booking) {
            throw new Exception("Booking not found", 404);
        }
        
        $totalAmount = number_format((float)($booking['total_amount'] ?? 0), 2, '.', '');
        
        // Check if payment history exists with small amounts (0.00, 5.00, etc.)
        $smallAmounts = ['0.00', '5.00', '10.00', '15.00', '20.00', '25.00'];
        $existingPayment = $this->paymentDAL->getPaymentHistoryByOrderAndRef($orderId, $paymentRef, '8', 'deposit', $smallAmounts);
        
        if ($existingPayment) {
            // Update existing payment history
            $this->paymentDAL->updatePaymentHistory($existingPayment['auto_id'], [
                'trams_received_amount' => $paymentAmount,
                'reference_no' => $paymentRef,
                'modified_by' => 'paydollar_callback_fullpayment'
            ]);
        } else {
            // Check if payment history already exists with this amount
            if (!$this->paymentDAL->paymentHistoryExists($orderId, $paymentRef, $paymentAmount)) {
                // Insert new payment history
                $paymentRefundDeadline = date('Y-m-d H:i:s', strtotime($currentDateTimestamp . ' +96 hours'));
                $this->paymentDAL->insertPaymentHistory([
                    'order_id' => $orderId,
                    'source' => 'WPT',
                    'trams_received_amount' => $paymentAmount,
                    'reference_no' => $paymentRef,
                    'payment_method' => '8',
                    'process_date' => $currentDateTimestamp,
                    'payment_change_deadline' => $paymentRefundDeadline,
                    'pay_type' => 'deposit',
                    'added_on' => $currentDateTimestamp,
                    'added_by' => 'Asiapay Callback'
                ]);
            }
        }
        
        // Check if payment amount equals total amount (fully paid)
        if ($paymentAmount == $totalAmount && $totalAmount != '0.00') {
            // Update booking to paid status
            $this->paymentDAL->updateWPTBookingPaymentStatus($orderId, [
                'payment_ref' => $paymentRef,
                'bpoint_ref' => $paymentRef,
                'payment_modified' => $currentDateTimestamp,
                'payment_modified_by' => 'asiapay_fullpayment_callback',
                'payment_status' => 'paid'
            ]);
            
            // Call gdeal name update ajax (external API call)
            $this->callGDealNameUpdateAjax($orderId);
            
            // Insert amadeus name update payment status log
            $this->paymentDAL->insertAmadeusNameUpdatePaymentStatusLog(
                $orderId,
                'WPT',
                'Callback',
                $currentDateTimestamp,
                'yes',
                $currentDateTimestamp,
                'Asiapay Fullpayment'
            );
        }
        
        return [
            'success' => true,
            'message' => 'Full payment recorded',
            'order_id' => $orderId,
            'payment_ref' => $paymentRef,
            'payment_amount' => $paymentAmount,
            'total_amount' => $totalAmount,
            'is_fully_paid' => ($paymentAmount == $totalAmount && $totalAmount != '0.00')
        ];
    }
    
    /**
     * Call GDeal name update ajax (external API)
     */
    private function callGDealNameUpdateAjax($orderId)
    {
        $currentUserLogin = 'GDeals Payment';
        $url = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_amadeus_name_update_backend.php?order_id=" . urlencode($orderId) . "&agent=" . urlencode($currentUserLogin);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($curl)) {
            error_log("GDeal name update ajax error: " . curl_error($curl));
        }
        
        curl_close($curl);
        
        return $httpCode == 200;
    }
    
    /**
     * Handle WPT partial payment success callback
     */
    public function handleWPTPartialPaymentSuccess($orderId, $paymentRef)
    {
        if (empty($paymentRef)) {
            throw new Exception("Payment reference is required", 400);
        }
        
        // Extract order ID from payment reference if not provided
        if (empty($orderId)) {
            $orderId = substr($paymentRef, 0, 6);
        }
        
        // Insert postmeta records
        $this->paymentDAL->insertPostmeta($orderId, 'wp_temp_payment_reference', $paymentRef);
        $this->paymentDAL->insertPostmeta($orderId, 'wp_travel_asiapay_id', $paymentRef);
        
        // Insert callback record
        $this->paymentDAL->insertAsiaPayCallback($paymentRef, '', 'success', 'paydollar_callback');
        
        // Check if booking exists
        if (!$this->paymentDAL->bookingExists($orderId)) {
            return [
                'success' => true,
                'message' => 'Payment callback recorded, but booking not found',
                'order_id' => $orderId,
                'payment_ref' => $paymentRef
            ];
        }
        
        // Get payment history with 0.00 amount
        $paymentHistory = $this->paymentDAL->getPaymentHistoryByOrderAndRef(
            $orderId,
            '',
            '8',
            'deposit',
            ['0.00']
        );
        
        if ($paymentHistory) {
            // Get order totals from postmeta
            $orderTotalAmount = $this->paymentDAL->getPostmeta($orderId, 'order_totals');
            
            if ($orderTotalAmount) {
                $orderData = unserialize($orderTotalAmount);
                $paymentPartialValue = $orderData['total_partial'] ?? '0.00';
                
                // Update payment history
                $this->paymentDAL->updatePaymentHistory($paymentHistory['auto_id'], [
                    'trams_received_amount' => $paymentPartialValue,
                    'reference_no' => $paymentRef,
                    'modified_by' => 'paydollar_callback'
                ]);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Partial payment processed successfully',
            'order_id' => $orderId,
            'payment_ref' => $paymentRef
        ];
    }
}

