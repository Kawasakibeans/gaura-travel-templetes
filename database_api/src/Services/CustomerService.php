<?php
/**
 * Customer Service - Business Logic Layer
 * Adapted from existing CustomerService.php
 */

namespace App\Services;

use App\DAL\CustomerDAL;
use Exception;

class CustomerService
{
    private $customerDAL;

    public function __construct()
    {
        $this->customerDAL = new CustomerDAL();
    }

    /**
     * Get payments by order ID
     * Retrieves payment information including summary and history
     */
    public function getPaymentsByOrderId($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        // Get booking details
        $booking = $this->customerDAL->getBookingByOrderId($orderId);
        
        if (!$booking) {
            throw new Exception('Order not found', 404);
        }

        // Calculate payment summary
        $totalAmount = (float)$booking['total_amount'];
        $depositAmount = (float)$booking['deposit_amount'];
        $orderType = $booking['order_type'];
        
        // Get payment history
        $paymentHistory = $this->customerDAL->getPaymentHistoryByOrderId($orderId);
        
        // Calculate total paid (deduplicate)
        $processedPayments = [];
        $totalPaid = 0;
        foreach ($paymentHistory as $payment) {
            $paymentIdentifier = $payment['process_date'] . '^' . 
                                $payment['trams_received_amount'] . '^' . 
                                $payment['reference_no'];
            if (!in_array($paymentIdentifier, $processedPayments)) {
                $processedPayments[] = $paymentIdentifier;
                $totalPaid += (float)$payment['trams_received_amount'];
            }
        }
        
        $balance = $totalAmount - $totalPaid;
        
        // Get date change charges
        $dateChangeCharges = $this->customerDAL->getDateChangeChargesByOrderId($orderId);
        
        // Get custom payment links
        $customPayments = $this->customerDAL->getCustomPaymentsByOrderId($orderId);

        // Format payment history
        $formattedPaymentHistory = $this->formatPaymentHistory($paymentHistory);
        $formattedDateCharges = $this->formatDateCharges($dateChangeCharges);
        $formattedCustomPayments = $this->formatCustomPayments($customPayments);

        return [
            'summary' => [
                'order_id' => $orderId,
                'order_type' => $orderType,
                'payment_status' => $booking['payment_status'],
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'amount_paid' => number_format($totalPaid, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
                'deposit_amount' => number_format($depositAmount, 2, '.', '')
            ],
            'booking_payments' => $formattedPaymentHistory,
            'other_payments' => $formattedDateCharges,
            'custom_payment_links' => $formattedCustomPayments
        ];
    }

    /**
     * Create new payment record
     */
    public function createPayment($orderId, $paymentData)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        // Validate required fields
        $requiredFields = ['amount', 'process_date', 'payment_type'];
        foreach ($requiredFields as $field) {
            if (!isset($paymentData[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Verify order exists
        $booking = $this->customerDAL->getBookingByOrderId($orderId);
        if (!$booking) {
            throw new Exception('Order not found', 404);
        }

        // Insert payment
        $paymentId = $this->customerDAL->createPayment($orderId, $paymentData);

        // Get created payment details
        $payment = $this->customerDAL->getPaymentById($paymentId);

        return [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => number_format((float)$payment['trams_received_amount'], 2),
            'process_date' => $payment['process_date'],
            'payment_type' => $payment['pay_type'],
            'created_at' => $payment['added_on']
        ];
    }

    /**
     * Delete payment record
     */
    public function deletePayment($orderId, $paymentId)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }
        if (empty($paymentId)) {
            throw new Exception('Payment ID is required', 400);
        }

        // Verify payment exists and belongs to order
        if (!$this->customerDAL->verifyPaymentOwnership($paymentId, $orderId)) {
            throw new Exception('Payment not found or does not belong to this order', 404);
        }

        // Delete the payment
        $this->customerDAL->deletePayment($paymentId, $orderId);

        return [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'deleted' => true
        ];
    }

    /**
     * Update payment clearing fields
     */
    public function updatePaymentClearing($paymentId, $clearingData)
    {
        if (empty($paymentId)) {
            throw new Exception('Payment ID is required', 400);
        }
        
        // Verify payment exists
        $payment = $this->customerDAL->getPaymentById($paymentId);
        if (!$payment) {
            throw new Exception('Payment not found', 404);
        }
        
        // Update clearing fields
        $success = $this->customerDAL->updatePaymentClearing($paymentId, $clearingData);
        
        if (!$success) {
            throw new Exception('No fields to update or update failed', 400);
        }
        
        // Get updated payment
        $updatedPayment = $this->customerDAL->getPaymentById($paymentId);
        
        return [
            'payment_id' => $paymentId,
            'order_id' => $updatedPayment['order_id'],
            'cleared_date' => $updatedPayment['cleared_date'] ?? null,
            'cleared_by' => $updatedPayment['cleared_by'] ?? null,
            'is_reconciliated' => $updatedPayment['is_reconciliated'] ?? null,
            'updated' => true
        ];
    }

    /**
     * Delete payment only if uncleared (cleared_date IS NULL)
     */
    public function deleteUnclearedPaymentById($paymentId)
    {
        if (empty($paymentId)) {
            throw new Exception('Payment ID is required', 400);
        }
        
        // Verify payment exists and is uncleared
        $payment = $this->customerDAL->getPaymentById($paymentId);
        if (!$payment) {
            throw new Exception('Payment not found', 404);
        }
        
        if (!empty($payment['cleared_date'])) {
            throw new Exception('Payment is already cleared and cannot be deleted', 400);
        }
        
        // Delete the payment
        $success = $this->customerDAL->deleteUnclearedPaymentById($paymentId);
        
        if (!$success) {
            throw new Exception('Payment deletion failed or payment was already cleared', 400);
        }
        
        return [
            'payment_id' => $paymentId,
            'order_id' => $payment['order_id'],
            'deleted' => true
        ];
    }

    /**
     * Sum partially paid pax (nonpaid) for WPT by trip_code+date patterns
     */
    public function getNonPaidPaxByTripDate($params)
    {
        $tripCodePattern = isset($params['trip_code_pattern']) ? trim((string)$params['trip_code_pattern']) : '%';
        $datePattern = isset($params['date_pattern']) ? trim((string)$params['date_pattern']) : '%';
        
        // If trip_code provided without pattern, use exact match
        if (isset($params['trip_code']) && !isset($params['trip_code_pattern'])) {
            $tripCodePattern = trim((string)$params['trip_code']);
        }
        
        // If travel_date provided without pattern, use exact match
        if (isset($params['travel_date']) && !isset($params['date_pattern'])) {
            $datePattern = trim((string)$params['travel_date']);
        }
        
        // Ensure patterns have wildcards if they don't already
        if (strpos($tripCodePattern, '%') === false && strpos($tripCodePattern, '_') === false) {
            $tripCodePattern = '%' . $tripCodePattern . '%';
        }
        
        if (strpos($datePattern, '%') === false && strpos($datePattern, '_') === false) {
            $datePattern = '%' . $datePattern . '%';
        }
        
        $results = $this->customerDAL->getNonPaidPaxByTripDate($tripCodePattern, $datePattern);
        
        $totalNonPaidPax = 0;
        foreach ($results as $row) {
            $totalNonPaidPax += (int)($row['total_nonpaid_pax'] ?? 0);
        }
        
        return [
            'trip_code_pattern' => $tripCodePattern,
            'date_pattern' => $datePattern,
            'results' => $results,
            'total_nonpaid_pax' => $totalNonPaidPax,
            'count' => count($results)
        ];
    }

    /**
     * Sum paid pax for WPT by trip_code+date patterns
     */
    public function getPaidPaxByTripDate($params)
    {
        $tripCodePattern = isset($params['trip_code_pattern']) ? trim((string)$params['trip_code_pattern']) : '%';
        $datePattern = isset($params['date_pattern']) ? trim((string)$params['date_pattern']) : '%';
        
        // If trip_code provided without pattern, use exact match
        if (isset($params['trip_code']) && !isset($params['trip_code_pattern'])) {
            $tripCodePattern = trim((string)$params['trip_code']);
        }
        
        // If travel_date provided without pattern, use exact match
        if (isset($params['travel_date']) && !isset($params['date_pattern'])) {
            $datePattern = trim((string)$params['travel_date']);
        }
        
        // Ensure patterns have wildcards if they don't already
        if (strpos($tripCodePattern, '%') === false && strpos($tripCodePattern, '_') === false) {
            $tripCodePattern = '%' . $tripCodePattern . '%';
        }
        
        if (strpos($datePattern, '%') === false && strpos($datePattern, '_') === false) {
            $datePattern = '%' . $datePattern . '%';
        }
        
        $results = $this->customerDAL->getPaidPaxByTripDate($tripCodePattern, $datePattern);
        
        $totalPaidPax = 0;
        foreach ($results as $row) {
            $totalPaidPax += (int)($row['total_paid_pax'] ?? 0);
        }
        
        return [
            'trip_code_pattern' => $tripCodePattern,
            'date_pattern' => $datePattern,
            'results' => $results,
            'total_paid_pax' => $totalPaidPax,
            'count' => count($results)
        ];
    }

    /**
     * Count paid pax for WPT by exact trip_code+date key
     */
    public function getPaidPaxCountByTripDateExact($params)
    {
        $tripCode = isset($params['trip_code']) ? trim((string)$params['trip_code']) : '';
        $travelDate = isset($params['travel_date']) ? trim((string)$params['travel_date']) : '';
        
        if ($tripCode === '') {
            throw new Exception('trip_code is required', 400);
        }
        
        if ($travelDate === '') {
            throw new Exception('travel_date is required', 400);
        }
        
        $count = $this->customerDAL->getPaidPaxCountByTripDateExact($tripCode, $travelDate);
        
        return [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate,
            'paid_pax_count' => $count
        ];
    }

    /**
     * Count partially paid (nonpaid) pax for WPT by exact trip_code+date key
     */
    public function getNonPaidPaxCountByTripDateExact($params)
    {
        $tripCode = isset($params['trip_code']) ? trim((string)$params['trip_code']) : '';
        $travelDate = isset($params['travel_date']) ? trim((string)$params['travel_date']) : '';
        
        if ($tripCode === '') {
            throw new Exception('trip_code is required', 400);
        }
        
        if ($travelDate === '') {
            throw new Exception('travel_date is required', 400);
        }
        
        $count = $this->customerDAL->getNonPaidPaxCountByTripDateExact($tripCode, $travelDate);
        
        return [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate,
            'nonpaid_pax_count' => $count
        ];
    }

    /**
     * List bookings by trip_code suffix and travel_date prefix with paid/partially_paid statuses
     */
    public function listBookingsByTripAndDatePrefix($params)
    {
        $tripCodeSuffix = isset($params['trip_code_suffix']) ? trim((string)$params['trip_code_suffix']) : '';
        $travelDatePrefix = isset($params['travel_date_prefix']) ? trim((string)$params['travel_date_prefix']) : '';
        $limit = isset($params['limit']) ? (int)$params['limit'] : 100;
        
        // Validate limit
        if ($limit <= 0 || $limit > 1000) {
            $limit = 100;
        }
        
        // If trip_code provided without suffix, use it as suffix
        if (isset($params['trip_code']) && !isset($params['trip_code_suffix'])) {
            $tripCodeSuffix = trim((string)$params['trip_code']);
        }
        
        // If travel_date provided without prefix, use it as prefix
        if (isset($params['travel_date']) && !isset($params['travel_date_prefix'])) {
            $travelDatePrefix = trim((string)$params['travel_date']);
        }
        
        // Default to empty strings if not provided (will match all)
        if ($tripCodeSuffix === '') {
            $tripCodeSuffix = '';
        }
        
        if ($travelDatePrefix === '') {
            $travelDatePrefix = '';
        }
        
        $bookings = $this->customerDAL->listBookingsByTripAndDatePrefix($tripCodeSuffix, $travelDatePrefix, $limit);
        
        return [
            'trip_code_suffix' => $tripCodeSuffix,
            'travel_date_prefix' => $travelDatePrefix,
            'bookings' => $bookings,
            'count' => count($bookings),
            'limit' => $limit
        ];
    }

    /**
     * List paid bookings by trip_code suffix and travel_date prefix
     */
    public function listPaidBookingsByTripAndDatePrefix($params)
    {
        $tripCodeSuffix = isset($params['trip_code_suffix']) ? trim((string)$params['trip_code_suffix']) : '';
        $travelDatePrefix = isset($params['travel_date_prefix']) ? trim((string)$params['travel_date_prefix']) : '';
        $limit = isset($params['limit']) ? (int)$params['limit'] : 100;
        
        // Validate limit
        if ($limit <= 0 || $limit > 1000) {
            $limit = 100;
        }
        
        // If trip_code provided without suffix, use it as suffix
        if (isset($params['trip_code']) && !isset($params['trip_code_suffix'])) {
            $tripCodeSuffix = trim((string)$params['trip_code']);
        }
        
        // If travel_date provided without prefix, use it as prefix
        if (isset($params['travel_date']) && !isset($params['travel_date_prefix'])) {
            $travelDatePrefix = trim((string)$params['travel_date']);
        }
        
        // Default to empty strings if not provided (will match all)
        if ($tripCodeSuffix === '') {
            $tripCodeSuffix = '';
        }
        
        if ($travelDatePrefix === '') {
            $travelDatePrefix = '';
        }
        
        $bookings = $this->customerDAL->listPaidBookingsByTripAndDatePrefix($tripCodeSuffix, $travelDatePrefix, $limit);
        
        return [
            'trip_code_suffix' => $tripCodeSuffix,
            'travel_date_prefix' => $travelDatePrefix,
            'bookings' => $bookings,
            'count' => count($bookings),
            'limit' => $limit
        ];
    }

    // Private helper methods
    
    private function formatPaymentHistory($paymentHistory)
    {
        $formatted = [];
        foreach ($paymentHistory as $payment) {
            $paymentType = $payment['pay_type'];
            $type = $this->getPaymentTypeName($paymentType);

            $formatted[] = [
                'payment_id' => $payment['auto_id'],
                'payment_date' => $payment['process_date'],
                'payment_type' => $type,
                'paid_amount' => number_format((float)$payment['trams_received_amount'], 2, '.', ''),
                'reference_no' => $payment['reference_no'],
                'remarks' => $payment['trams_remarks'],
                'payment_method' => $payment['payment_method_name'],
                'original_type' => $paymentType
            ];
        }
        return $formatted;
    }

    private function formatDateCharges($dateChangeCharges)
    {
        $formatted = [];
        foreach ($dateChangeCharges as $charge) {
            $formatted[] = [
                'charge_id' => $charge['auto_id'],
                'charge_date' => $charge['process_date'],
                'amount' => number_format((float)$charge['trams_received_amount'], 2, '.', ''),
                'reference_no' => $charge['reference_no'],
                'remarks' => $charge['trams_remarks'],
                'payment_method' => $charge['payment_method_name']
            ];
        }
        return $formatted;
    }

    private function formatCustomPayments($customPayments)
    {
        $formatted = [];
        foreach ($customPayments as $custom) {
            $formatted[] = [
                'custom_payment_id' => $custom['auto_id'],
                'type_of_payment' => $custom['type_of_payment'],
                'amount' => $custom['amount'],
                'status' => $custom['status'],
                'requested_on' => $custom['requested_on'],
                'azupay_link' => $custom['azupay_link'],
                'azupay_bsb' => $custom['azupay_bsb'],
                'azupay_account_number' => $custom['azupay_account_number'],
                'bpay_crn' => $custom['bpay_crn'],
                'bpay_expiry_date' => $custom['bpay_expiry_date']
            ];
        }
        return $formatted;
    }

    private function getPaymentTypeName($paymentType)
    {
        $types = [
            'deposit' => 'Booking Deposit',
            'balance' => 'Booking Balance',
            'Balance' => 'Booking Balance',
            'deposit_adjustment' => 'Booking Deposit Adjustment',
            'Refund' => 'Refund',
            'additional_payment' => 'Additional Payment'
        ];

        return $types[$paymentType] ?? 'Unknown';
    }
}

