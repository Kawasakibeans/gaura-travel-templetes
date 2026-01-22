<?php
/**
 * TRAMS Payment Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TramsPaymentDAL;
use Exception;

class TramsPaymentService
{
    private $tramsPaymentDAL;

    public function __construct()
    {
        $this->tramsPaymentDAL = new TramsPaymentDAL();
    }

    /**
     * Update payments from TRAMS
     */
    public function updatePaymentsFromTrams($username)
    {
        $payments = $this->tramsPaymentDAL->findUnprocessedPayments();
        
        if (empty($payments)) {
            return [
                'success' => true,
                'message' => 'No payments to process',
                'payments_processed' => 0,
                'details' => []
            ];
        }
        
        $processed = 0;
        $details = [];
        $currentDate = date('Y-m-d H:i:s');
        
        foreach ($payments as $payment) {
            $orderId = $payment['invoiceref'];
            $paymentNo = $payment['paymentno'];
            $paymentDate = $payment['paymentdate'];
            $profileNo = $payment['profileno'];
            $amount = $payment['amount'] / 100; // Convert from cents
            $amount = number_format((float)$amount, 2, '.', '');
            $remarks = $payment['remarks'] ?? '';
            $paymethodLinkno = $payment['paymethod_linkno'];
            
            $dateCleared = date('Y-m-d h:i:s', strtotime($paymentDate));
            
            // Get existing payment history
            $firstPayment = $this->tramsPaymentDAL->getFirstPaymentHistory($orderId);
            if (!$firstPayment) {
                continue; // Skip if no existing payment history
            }
            
            $processDate = $firstPayment['process_date'] ?? '';
            $processDate0 = date('Y-m-d', strtotime($processDate)) . ' 00:00:00';
            
            $tramsReceivedAmountBpoint = $firstPayment['trams_received_amount'] ?? 0;
            $totalAmountCalculated = $tramsReceivedAmountBpoint / 5 * 100;
            $totalAmountCalculated = number_format((float)$totalAmountCalculated, 2, '.', '');
            
            // Get deposit
            $deposit = $this->tramsPaymentDAL->getDepositPayment($orderId);
            $depositReceived = $deposit['trams_received_amount'] ?? 0;
            
            // Calculate total received
            $allPayments = $this->tramsPaymentDAL->getPaymentHistory($orderId);
            $totalReceived = 0;
            foreach ($allPayments as $p) {
                $totalReceived += (float)($p['trams_received_amount'] ?? 0);
            }
            
            $totalBalance = $totalAmountCalculated - $totalReceived - (float)$amount;
            $totalBalance = number_format((float)$totalBalance, 2, '.', '');
            
            // Only insert if amount != deposit_received
            if ($amount != $depositReceived) {
                try {
                    $this->tramsPaymentDAL->insertPaymentHistory([
                        'order_id' => $orderId,
                        'profile_no' => $profileNo,
                        'total_amount' => $totalAmountCalculated,
                        'trams_received_amount' => $amount,
                        'reference_no' => $paymentNo,
                        'balance_amount' => $totalBalance,
                        'payment_method' => $paymethodLinkno,
                        'process_date' => $dateCleared,
                        'trams_remarks' => $remarks
                    ]);
                    
                    // Update booking profile_id
                    $this->tramsPaymentDAL->updateBookingProfileId($orderId, $profileNo);
                    
                    // Insert booking update history
                    $this->tramsPaymentDAL->insertBookingUpdateHistory([
                        'order_id' => $orderId,
                        'merging_id' => $paymentNo,
                        'meta_key' => 'payment amount',
                        'meta_value' => $paymethodLinkno,
                        'meta_key_data' => $amount,
                        'updated_time' => $currentDate,
                        'updated_user' => $username
                    ]);
                    
                    $processed++;
                    $details[] = [
                        'order_id' => $orderId,
                        'paymentno' => $paymentNo,
                        'amount' => $amount,
                        'profile_no' => $profileNo
                    ];
                } catch (Exception $e) {
                    error_log("Failed to process payment {$paymentNo}: " . $e->getMessage());
                    // Continue with next payment
                }
            }
            
            // Handle BPay payments
            $bpayPayment = $this->tramsPaymentDAL->getBPayPayment($orderId, $processDate0);
            if ($bpayPayment) {
                $bpayPaidAmount = $bpayPayment['amount'] / 100;
                $bpayPaidAmount = number_format((float)$bpayPaidAmount, 2, '.', '');
                
                try {
                    // Update BPay payment profile_no
                    $this->tramsPaymentDAL->updateBPayProfileNo($orderId, $profileNo, $bpayPaidAmount);
                    
                    // Insert booking update history for profile
                    $this->tramsPaymentDAL->insertBookingUpdateHistory([
                        'order_id' => $orderId,
                        'merging_id' => $bpayPaidAmount,
                        'meta_key' => 'payment profile',
                        'meta_value' => $profileNo,
                        'meta_key_data' => '',
                        'updated_time' => $currentDate,
                        'updated_user' => $username
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to update BPay payment for order {$orderId}: " . $e->getMessage());
                    // Continue
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Payments updated from trams',
            'payments_processed' => $processed,
            'details' => $details
        ];
    }
}

