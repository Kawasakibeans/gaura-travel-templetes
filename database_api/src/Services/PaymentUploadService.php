<?php
/**
 * Payment Upload Service
 * Business logic for payment upload and matching operations
 */

namespace App\Services;

use App\DAL\PaymentUploadDAL;
use Exception;

class PaymentUploadService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentUploadDAL();
    }

    /**
     * Get payment information by order ID
     */
    public function getPaymentInfo(int $orderId): array
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            throw new Exception('Valid order ID is required', 400);
        }

        $orderId = (int)$orderId;

        $paymentInfo = $this->dal->getPaymentInfoByOrderId($orderId);
        
        if (!$paymentInfo) {
            // Check if any postmeta exists for this order ID (to determine if order exists)
            $hasAnyMeta = $this->dal->hasAnyPostmeta($orderId);
            
            if (!$hasAnyMeta) {
                throw new Exception('Order not found', 404);
            }
            
            // Order has some meta but not payment info
            return [
                'order_id' => $orderId,
                'payable' => null,
                'status' => null,
                'message' => 'Order exists but payment information is not set up. The order may not have wp_travel_trip_price meta_key.'
            ];
        }

        return [
            'order_id' => (int)$paymentInfo['order_id'],
            'payable' => $paymentInfo['payable'],
            'status' => $paymentInfo['status'] ?? null
        ];
    }

    /**
     * Get payment information for multiple order IDs
     */
    public function getPaymentInfoBatch(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [
                'orders' => [],
                'count' => 0
            ];
        }

        // Filter and sanitize order IDs
        $orderIds = array_filter(array_map('intval', $orderIds));
        
        if (empty($orderIds)) {
            throw new Exception('Valid order IDs are required', 400);
        }

        $paymentInfo = $this->dal->getPaymentInfoByOrderIds($orderIds);

        return [
            'orders' => $paymentInfo,
            'count' => count($paymentInfo)
        ];
    }

    /**
     * Update payment status for an order
     */
    public function updatePaymentStatus(int $orderId, string $status): array
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            throw new Exception('Valid order ID is required', 400);
        }

        if (empty($status)) {
            throw new Exception('Payment status is required', 400);
        }

        // Validate status values
        $validStatuses = ['paid', 'pending', 'partially_paid', 'cancelled'];
        if (!in_array(strtolower($status), $validStatuses)) {
            throw new Exception('Invalid payment status. Valid values: ' . implode(', ', $validStatuses), 400);
        }

        $success = $this->dal->updatePaymentStatus((int)$orderId, strtolower($status));
        
        if (!$success) {
            throw new Exception('Failed to update payment status', 500);
        }

        return [
            'order_id' => (int)$orderId,
            'status' => strtolower($status),
            'message' => 'Payment status updated successfully'
        ];
    }

    /**
     * Update payment status for multiple orders
     */
    public function updatePaymentStatusBatch(array $orderIds, string $status): array
    {
        if (empty($orderIds) || !is_array($orderIds)) {
            throw new Exception('Order IDs array is required', 400);
        }

        if (empty($status)) {
            throw new Exception('Payment status is required', 400);
        }

        // Validate status values
        $validStatuses = ['paid', 'pending', 'partially_paid', 'cancelled'];
        if (!in_array(strtolower($status), $validStatuses)) {
            throw new Exception('Invalid payment status. Valid values: ' . implode(', ', $validStatuses), 400);
        }

        // Filter and sanitize order IDs
        $orderIds = array_filter(array_map('intval', $orderIds));
        
        if (empty($orderIds)) {
            throw new Exception('Valid order IDs are required', 400);
        }

        $updated = $this->dal->updatePaymentStatusBatch($orderIds, strtolower($status));

        return [
            'status' => strtolower($status),
            'total_orders' => count($orderIds),
            'updated_orders' => $updated,
            'message' => "Payment status updated for $updated out of " . count($orderIds) . " orders"
        ];
    }

    /**
     * Update payment status for both order and payment post
     */
    public function updatePaymentStatusComplete(int $orderId, string $status): array
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            throw new Exception('Valid order ID is required', 400);
        }

        if (empty($status)) {
            throw new Exception('Payment status is required', 400);
        }

        // Validate status values
        $validStatuses = ['paid', 'pending', 'partially_paid', 'cancelled'];
        if (!in_array(strtolower($status), $validStatuses)) {
            throw new Exception('Invalid payment status. Valid values: ' . implode(', ', $validStatuses), 400);
        }

        $orderId = (int)$orderId;
        $status = strtolower($status);

        // Update order payment status
        $orderUpdated = $this->dal->updatePaymentStatus($orderId, $status);
        
        // Get payment ID and update payment post status
        $paymentId = $this->dal->getPaymentIdByOrderId($orderId);
        $paymentUpdated = false;
        
        if ($paymentId) {
            $paymentUpdated = $this->dal->updatePaymentPostStatus($paymentId, $status);
        }

        return [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'status' => $status,
            'order_updated' => $orderUpdated,
            'payment_updated' => $paymentUpdated,
            'message' => 'Payment status updated successfully'
        ];
    }
}

