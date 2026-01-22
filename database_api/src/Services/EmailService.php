<?php
/**
 * Email Service
 * Business logic for email-related operations
 */

namespace App\Services;

use App\DAL\EmailDAL;
use Exception;

class EmailService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new EmailDAL();
    }

    /**
     * Get passenger and billing email for an order
     */
    public function getEmailRecipients($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $leadPassenger = $this->dal->getLeadPassenger($orderId);
        if (!$leadPassenger) {
            throw new Exception('Passenger not found for order', 404);
        }

        $customerEmail = $leadPassenger['email_pax'] ?? null;
        $billingEmail = $this->dal->getBillingEmail($orderId);
        
        // Use billing email if available, otherwise use customer email
        $email = !empty($billingEmail) ? $billingEmail : $customerEmail;

        return [
            'customer_email' => $customerEmail,
            'billing_email' => $billingEmail,
            'email' => $email,
            'passenger' => [
                'fname' => $leadPassenger['fname'] ?? '',
                'lname' => $leadPassenger['lname'] ?? '',
                'salutation' => $leadPassenger['salutation'] ?? ''
            ]
        ];
    }

    /**
     * Get booking information for email
     */
    public function getBookingInfo($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $booking = $this->dal->getBookingForEmail($orderId);
        if (!$booking) {
            throw new Exception('Booking not found', 404);
        }

        return $booking;
    }

    /**
     * Get booking and passenger data for tax invoice
     */
    public function getTaxInvoiceData($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $booking = $this->dal->getBookingForTaxInvoice($orderId);
        $passenger = $this->dal->getPassengerForTaxInvoice($orderId);

        if (empty($booking) || !$passenger) {
            throw new Exception('Booking or passenger not found', 404);
        }

        return [
            'booking' => $booking,
            'passenger' => $passenger
        ];
    }

    /**
     * Get booking data for reminder email (24h, 4d, 7d)
     */
    public function getReminderBookingData($orderId, $daysThreshold = null)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $booking = $this->dal->getBookingForReminder($orderId, $daysThreshold);
        if (!$booking) {
            throw new Exception('Booking not found for reminder', 404);
        }

        $leadPassenger = $this->dal->getLeadPassengerForReminder($orderId);
        $passengers = $this->dal->getPassengersForReminder($orderId, $booking['product_id']);
        $daysLeft = $this->dal->getDaysLeftUntilTravel($orderId, $daysThreshold);

        return [
            'booking' => $booking,
            'lead_passenger' => $leadPassenger,
            'passengers' => $passengers,
            'days_left' => $daysLeft
        ];
    }

    /**
     * Log email history
     */
    public function logEmailHistory($orderId, $emailType, $emailAddress, $emailSubject, $initiatedBy, $emailBody = null)
    {
        if (empty($orderId) || empty($emailType) || empty($emailAddress) || empty($emailSubject)) {
            throw new Exception('order_id, email_type, email_address, and email_subject are required', 400);
        }

        $data = [
            'order_id' => $orderId,
            'email_type' => $emailType,
            'email_address' => $emailAddress,
            'initiated_date' => date('Y-m-d H:i:s'),
            'initiated_by' => $initiatedBy ?? 'system',
            'email_body' => $emailBody,
            'email_subject' => $emailSubject
        ];

        return $this->dal->insertEmailHistory($data);
    }

    /**
     * Update e-ticket status and log file
     */
    public function updateEticketStatus($orderId, $status, $filePath = null, $createdBy = null)
    {
        if (empty($orderId) || empty($status)) {
            throw new Exception('order_id and status are required', 400);
        }

        $this->dal->updateEticketStatus($orderId, $status);

        if ($filePath) {
            $this->dal->insertEticketFileLog([
                'order_id' => $orderId,
                'file_path' => $filePath,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $createdBy
            ]);
        }

        return [
            'success' => true,
            'message' => 'E-ticket status updated successfully'
        ];
    }

    /**
     * Get all bookings for an order (ordered by travel_date)
     */
    public function getAllBookingsForOrder($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $bookings = $this->dal->getAllBookingsForOrder($orderId);
        
        return [
            'bookings' => $bookings,
            'total_count' => count($bookings)
        ];
    }

    /**
     * Get history of updates data
     */
    public function getHistoryOfUpdates($orderId, $metaKey = null)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $history = $this->dal->getHistoryOfUpdates($orderId, $metaKey);
        
        return [
            'history' => $history,
            'total_count' => count($history)
        ];
    }

    /**
     * Get passengers by product_id
     */
    public function getPassengersByProductId($orderId, $productId = null)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $passengers = $this->dal->getPassengersByProductId($orderId, $productId);
        
        return [
            'passengers' => $passengers,
            'total_count' => count($passengers)
        ];
    }

    /**
     * Get trip extras
     */
    public function getTripExtras($productId, $newProductId = null)
    {
        if (empty($productId)) {
            throw new Exception('product_id is required', 400);
        }

        $extras = $this->dal->getTripExtras($productId, $newProductId);
        
        return $extras;
    }

    /**
     * Get custom email itinerary data
     */
    public function getCustomEmailItinerary($orderId, $isEmailed = null)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $itinerary = $this->dal->getCustomEmailItinerary($orderId, $isEmailed);
        
        return [
            'itinerary' => $itinerary,
            'total_count' => count($itinerary)
        ];
    }

    /**
     * Get payment history from history_of_updates
     */
    public function getPaymentHistory($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $payments = $this->dal->getPaymentHistory($orderId);
        
        // Calculate total deposit amount
        $totalDeposit = 0;
        foreach ($payments as $payment) {
            $totalDeposit += (float)($payment['meta_value'] ?? 0);
        }
        
        return [
            'payments' => $payments,
            'total_deposit' => number_format($totalDeposit, 2, '.', ''),
            'total_count' => count($payments)
        ];
    }

    /**
     * Get flight leg details from history_of_updates
     */
    public function getFlightLegDetails($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $legs = $this->dal->getFlightLegDetails($orderId);
        
        // Group by meta_key for easier access
        $grouped = [];
        foreach ($legs as $leg) {
            $key = $leg['meta_key'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $leg['meta_value'];
        }
        
        return [
            'legs' => $legs,
            'grouped' => $grouped,
            'total_count' => count($legs)
        ];
    }

    /**
     * Get baggage information
     */
    public function getBaggageInfo($orderId, $gdsPaxId = null, $departureAirport = null)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $baggage = $this->dal->getBaggageInfo($orderId, $gdsPaxId, $departureAirport);
        
        return [
            'baggage' => $baggage,
            'total_count' => count($baggage)
        ];
    }
}

