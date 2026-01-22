<?php
/**
 * Outbound Payment Service
 * Business logic for outbound payment operations (Azupay)
 */

namespace App\Services;

use App\DAL\OutboundPaymentDAL;
use Exception;

class OutboundPaymentService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new OutboundPaymentDAL();
    }

    /**
     * Check IP address access
     */
    public function checkIpAccess(string $ipAddress): array
    {
        if (empty($ipAddress)) {
            throw new Exception('IP address is required', 400);
        }

        try {
            $result = $this->dal->checkIpAddress($ipAddress);
            // fetch() returns false when no rows found, or array when found
            $hasAccess = ($result !== null && is_array($result));
            
            return [
                'has_access' => $hasAccess,
                'ip_address' => $ipAddress,
                'ip_details' => $hasAccess ? $result : null
            ];
        } catch (Exception $e) {
            // Re-throw with more context
            throw new Exception('Failed to check IP address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get outbound payments with filters
     */
    public function getOutboundPayments(array $filters = []): array
    {
        $paymentDate = $filters['payment_date'] ?? null;
        $orderId = $filters['order_id'] ?? null;
        $status = $filters['status'] ?? null;
        $authorizedBy = $filters['authorized_by'] ?? null;
        $limit = (int)($filters['limit'] ?? 30);
        $offset = (int)($filters['offset'] ?? 0);

        $payments = $this->dal->getOutboundPayments(
            $paymentDate,
            $orderId,
            $status,
            $authorizedBy,
            $limit,
            $offset
        );

        // Transform status for frontend
        foreach ($payments as &$payment) {
            $payment['frontend_status'] = $this->getFrontendStatus($payment['request_base_status']);
        }

        return [
            'payments' => $payments,
            'total_count' => count($payments),
            'filters' => [
                'payment_date' => $paymentDate,
                'order_id' => $orderId,
                'status' => $status,
                'authorized_by' => $authorizedBy,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get outbound payment by ID
     */
    public function getOutboundPaymentById(int $autoId): array
    {
        if (empty($autoId) || !is_numeric($autoId)) {
            throw new Exception('Valid payment ID is required', 400);
        }

        $payment = $this->dal->getOutboundPaymentById((int)$autoId);

        if (!$payment) {
            throw new Exception('Payment request not found', 404);
        }

        $payment['frontend_status'] = $this->getFrontendStatus($payment['request_base_status']);

        return $payment;
    }

    /**
     * Create outbound payment request
     */
    public function createOutboundPayment(array $data): array
    {
        // Validate required fields
        $requiredFields = ['order_id', 'payee_name', 'payment_description', 'payment_amount', 'payment_option_type', 'added_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Generate unique client payment ID
        $clientPaymentId = $data['order_id'] . date("is");

        // Prepare data
        $paymentData = [
            'order_id' => $data['order_id'],
            'client_payment_id' => $clientPaymentId,
            'payee_name' => $data['payee_name'],
            'payment_description' => $data['payment_description'],
            'customer_reference' => $data['customer_reference'] ?? null,
            'payment_amount' => number_format((float)$data['payment_amount'], 2, '.', ''),
            'payment_option_type' => $data['payment_option_type'],
            'pay_id_type' => $data['pay_id_type'] ?? null,
            'payid' => $data['payid'] ?? null,
            'bsb' => $data['bsb'] ?? null,
            'account' => $data['account'] ?? null,
            'added_by' => $data['added_by'],
            'request_base_status' => 'pending'
        ];

        $autoId = $this->dal->createOutboundPayment($paymentData);

        return [
            'auto_id' => $autoId,
            'client_payment_id' => $clientPaymentId,
            'message' => 'Payment request created successfully'
        ];
    }

    /**
     * Update initial approval
     */
    public function updateInitialApproval(int $autoId, string $confirmedBy): array
    {
        $payment = $this->dal->getOutboundPaymentById($autoId);
        if (!$payment) {
            throw new Exception('Payment request not found', 404);
        }

        if ($payment['request_base_status'] !== 'pending') {
            throw new Exception('Payment request is not in pending status', 400);
        }

        $confirmedOn = date("Y-m-d H:i:s");
        $this->dal->updateInitialApproval($autoId, $confirmedBy, $confirmedOn);

        return [
            'auto_id' => $autoId,
            'status' => 'firstapproved',
            'message' => 'Initial approval granted successfully'
        ];
    }

    /**
     * Update final approval and process payment
     */
    public function updateFinalApproval(int $autoId, array $azupayData, string $confirmedBy): array
    {
        $payment = $this->dal->getOutboundPaymentById($autoId);
        if (!$payment) {
            throw new Exception('Payment request not found', 404);
        }

        if ($payment['request_base_status'] !== 'firstapproved' && $payment['request_base_status'] !== 'failed') {
            throw new Exception('Payment request is not in approved or failed status', 400);
        }

        $confirmedOn = date("Y-m-d H:i:s");
        $this->dal->updateFinalApproval($autoId, $azupayData, $confirmedBy, $confirmedOn);

        // Insert payment history for refund
        $paymentAmountNegative = '-' . $payment['payment_amount'];
        $this->dal->insertPaymentHistory([
            'order_id' => $payment['order_id'],
            'reference_no' => $payment['client_payment_id'],
            'trams_remarks' => $payment['payment_description'],
            'payment_method' => '7',
            'trams_received_amount' => $paymentAmountNegative,
            'process_date' => $confirmedOn,
            'added_on' => $confirmedOn,
            'added_by' => $confirmedBy,
            'payment_request_id' => $azupayData['paymentId'],
            'pay_type' => 'Refund'
        ]);

        return [
            'auto_id' => $autoId,
            'status' => 'processed',
            'azupay_payment_id' => $azupayData['paymentId'],
            'message' => 'Payment processed successfully'
        ];
    }

    /**
     * Update failed status
     */
    public function updateFailedStatus(int $autoId, string $errorResponse, string $confirmedBy): array
    {
        $payment = $this->dal->getOutboundPaymentById($autoId);
        if (!$payment) {
            throw new Exception('Payment request not found', 404);
        }

        $confirmedOn = date("Y-m-d H:i:s");
        $this->dal->updateFailedStatus($autoId, $errorResponse, $confirmedBy, $confirmedOn);

        return [
            'auto_id' => $autoId,
            'status' => 'failed',
            'message' => 'Payment processing failed'
        ];
    }

    /**
     * Update declined status
     */
    public function updateDeclinedStatus(int $autoId, string $confirmedBy): array
    {
        $payment = $this->dal->getOutboundPaymentById($autoId);
        if (!$payment) {
            throw new Exception('Payment request not found', 404);
        }

        $confirmedOn = date("Y-m-d H:i:s");
        $this->dal->updateDeclinedStatus($autoId, $confirmedBy, $confirmedOn);

        return [
            'auto_id' => $autoId,
            'status' => 'declined',
            'message' => 'Payment request declined'
        ];
    }

    /**
     * Get frontend status label
     */
    private function getFrontendStatus(string $status): string
    {
        $statusMap = [
            'pending' => 'Pending',
            'firstapproved' => 'First Approved',
            'processed' => 'Processed',
            'failed' => 'Failed',
            'declined' => 'Declined'
        ];

        return $statusMap[$status] ?? 'Unknown';
    }
}

