<?php

namespace App\Services;

use App\DAL\EmailManagerDAL;

class EmailManagerService
{
    private $dal;

    public function __construct(EmailManagerDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get orders with email status
     * Line: 75-220 (in template)
     */
    public function getOrdersWithEmailStatus($orderId = null, $limit = 100)
    {
        $bookings = $this->dal->getBookingsWithEmailStatus($orderId, $limit);
        
        $result = [];
        foreach ($bookings as $booking) {
            $orderIdValue = $booking['order_id'];
            
            // Get customer email
            $customerEmail = $this->dal->getCustomerEmail($orderIdValue);
            
            // Get email status
            $emailStatus = $this->dal->getEmailStatusSummary($orderIdValue);
            
            // Format payment status
            $paymentStatus = $this->formatPaymentStatus($booking['payment_status']);
            
            $result[] = [
                'order_id' => $orderIdValue,
                'customer_email' => $customerEmail,
                'payment_status' => $paymentStatus,
                'email_status' => $emailStatus
            ];
        }
        
        return $result;
    }

    /**
     * Get email status for a specific order
     * Line: 232-303 (in template)
     */
    public function getOrderEmailStatus($orderId)
    {
        // Get customer email
        $customerEmail = $this->dal->getCustomerEmail($orderId);
        if (!$customerEmail) {
            throw new \Exception('Order not found', 404);
        }
        
        // Get payment status
        $paymentStatus = $this->dal->getBookingPaymentStatus($orderId);
        
        // Get email status summary
        $emailStatus = $this->dal->getEmailStatusSummary($orderId);
        
        // Get full email history
        $emailHistory = $this->dal->getEmailHistory($orderId);
        
        return [
            'order_id' => $orderId,
            'customer_email' => $customerEmail,
            'payment_status' => $paymentStatus,
            'email_status' => $emailStatus,
            'email_history' => $emailHistory
        ];
    }

    /**
     * Record email history
     * Line: 386-393, 403-410, 465-472, 482-489 (in template)
     */
    public function recordEmailHistory($orderId, $emailType, $emailAddress, $emailSubject, $initiatedBy = 'api')
    {
        // Validate email type
        $validTypes = ['Booking Email', 'Itinerary Email', 'Tax Invoice', 'Payment Update'];
        if (!in_array($emailType, $validTypes)) {
            throw new \Exception('Invalid email type. Must be one of: ' . implode(', ', $validTypes), 400);
        }
        
        // Verify order exists
        $customerEmail = $this->dal->getCustomerEmail($orderId);
        if (!$customerEmail) {
            throw new \Exception('Order not found', 404);
        }
        
        // Use provided email or customer email
        if (!$emailAddress) {
            $emailAddress = $customerEmail;
        }
        
        // Insert email history
        $historyId = $this->dal->insertEmailHistory($orderId, $emailType, $emailAddress, $emailSubject, $initiatedBy);
        
        return [
            'id' => $historyId,
            'order_id' => $orderId,
            'email_type' => $emailType,
            'email_address' => $emailAddress,
            'email_subject' => $emailSubject,
            'initiated_by' => $initiatedBy,
            'initiated_date' => date("Y-m-d H:i:s")
        ];
    }

    /**
     * Format payment status text
     * Line: 166-201 (in template)
     */
    private function formatPaymentStatus($status)
    {
        $statusMap = [
            'pending' => 'Pending',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'canceled' => 'Xxln With Deposit',
            'N/A' => 'Failed',
            'refund' => 'Refund Done',
            'waiting_voucher' => 'Refund Under Process',
            'voucher_submited' => 'Rebooked'
        ];
        
        return $statusMap[$status] ?? 'Pending';
    }
}

