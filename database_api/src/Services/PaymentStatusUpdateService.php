<?php
/**
 * Payment Status Update Service
 * Business logic for payment status update operations (CSV upload)
 */

namespace App\Services;

use App\DAL\PaymentStatusUpdateDAL;
use Exception;

class PaymentStatusUpdateService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentStatusUpdateDAL();
    }

    /**
     * Validate CSV data and prepare update records
     */
    public function validateCsvData(array $csvRows): array
    {
        $validatedRows = [];
        $errors = [];

        foreach ($csvRows as $index => $row) {
            $rowNumber = $index + 1;

            // Expected CSV format: order_id, payment_status, amount, co_order_id (optional)
            if (count($row) < 3) {
                $errors[] = "Row {$rowNumber}: Insufficient columns (expected at least 3)";
                continue;
            }

            $orderId = $this->sanitizeOrderId((string)$row[0]);
            $paymentStatus = (string)($row[1] ?? '');
            $amount = $row[2] ?? 0;
            $coOrderId = isset($row[3]) ? (string)$row[3] : '';

            if (empty($orderId)) {
                $errors[] = "Row {$rowNumber}: Order ID is required";
                continue;
            }

            if (empty($paymentStatus)) {
                $errors[] = "Row {$rowNumber}: Payment status is required";
                continue;
            }

            if (!is_numeric($amount)) {
                $errors[] = "Row {$rowNumber}: Amount must be numeric";
                continue;
            }

            // Validate order exists
            try {
                $orderInfo = $this->dal->validateOrderForUpdate($orderId, $coOrderId);
                
                if (!$orderInfo) {
                    $validatedRows[] = [
                        'row_number' => $rowNumber,
                        'order_id' => $orderId,
                        'co_order_id' => $coOrderId,
                        'payment_status' => $paymentStatus,
                        'amount' => $amount,
                        'match_status' => 'New',
                        'can_update' => false,
                        'error' => 'Order not found'
                    ];
                    continue;
                }

                // Calculate balance difference
                $currentBalance = (float)($orderInfo['current_balance'] ?? 0);
                $newBalance = $currentBalance - (float)$amount;
                $balanceDiff = $newBalance;

                // Determine match status
                $matchStatus = 'Existing';
                $canUpdate = false;
                $error = null;

                $currentPaymentStatus = $orderInfo['current_payment_status'] ?? null;
                
                if ($currentPaymentStatus === 'paid') {
                    $matchStatus = 'Existing';
                    $canUpdate = false;
                    $error = 'Already paid';
                } elseif ($currentPaymentStatus !== $paymentStatus) {
                    if ($balanceDiff == 0 || ($balanceDiff < 1 && $balanceDiff > -1)) {
                        $matchStatus = 'Existing';
                        $canUpdate = true;
                    } else {
                        $matchStatus = 'Existing';
                        $canUpdate = false;
                        $error = '$' . number_format($balanceDiff, 2) . ' different from balance';
                    }
                } else {
                    $matchStatus = 'Existing';
                    $canUpdate = false;
                }

                $validatedRows[] = [
                    'row_number' => $rowNumber,
                    'order_id' => $orderId,
                    'co_order_id' => $coOrderId,
                    'payment_status' => strtolower($paymentStatus) === 'n/a' ? 'N/A' : strtolower($paymentStatus),
                    'amount' => $amount,
                    'current_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'balance_diff' => $balanceDiff,
                    'match_status' => $matchStatus,
                    'can_update' => $canUpdate,
                    'current_status' => $currentPaymentStatus,
                    'error' => $error
                ];
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: Error processing order - " . $e->getMessage();
                $validatedRows[] = [
                    'row_number' => $rowNumber,
                    'order_id' => $orderId,
                    'co_order_id' => $coOrderId,
                    'payment_status' => $paymentStatus,
                    'amount' => $amount,
                    'match_status' => 'Error',
                    'can_update' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'validated_rows' => $validatedRows,
            'errors' => $errors,
            'total_rows' => count($csvRows),
            'valid_rows' => count($validatedRows)
        ];
    }

    /**
     * Update payment statuses from validated CSV data
     */
    public function updatePaymentStatuses(array $validatedRows, string $updatedBy): array
    {
        $updated = [];
        $failed = [];

        foreach ($validatedRows as $row) {
            if (!$row['can_update']) {
                continue;
            }

            try {
                $orderInfo = $this->dal->validateOrderForUpdate($row['order_id'], $row['co_order_id']);

                if (!$orderInfo) {
                    $failed[] = [
                        'order_id' => $row['order_id'],
                        'error' => 'Order not found'
                    ];
                    continue;
                }

                // Check if already updated
                if ($orderInfo['current_payment_status'] === 'paid' && $row['payment_status'] === 'paid') {
                    continue;
                }

                $updatedTime = date('Y-m-d H:i:s');

                // Update booking
                $updateData = [
                    'order_id' => $row['order_id'],
                    'co_order_id' => $row['co_order_id'],
                    'payment_status' => $row['payment_status'],
                    'balance' => $row['new_balance'],
                    'payment_modified_by' => $updatedBy,
                    'payment_modified' => $updatedTime
                ];

                $this->dal->updateBookingPaymentStatus($updateData);

                // Insert history
                $historyData = [
                    'order_id' => $row['order_id'],
                    'co_order_id' => $row['co_order_id'],
                    'merging_id' => $orderInfo['merging_id'] ?? '',
                    'meta_key' => 'payment_status',
                    'meta_value' => $row['payment_status'],
                    'meta_key_data' => $row['amount'],
                    'updated_time' => $updatedTime,
                    'updated_user' => $updatedBy
                ];

                $this->dal->insertBookingUpdateHistory($historyData);

                $updated[] = [
                    'order_id' => $row['order_id'],
                    'co_order_id' => $row['co_order_id'],
                    'payment_status' => $row['payment_status'],
                    'balance' => $row['new_balance']
                ];
            } catch (Exception $e) {
                $failed[] = [
                    'order_id' => $row['order_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'total_updated' => count($updated),
            'total_failed' => count($failed)
        ];
    }

    /**
     * Sanitize order ID
     */
    private function sanitizeOrderId(string $orderId): string
    {
        // Remove non-alphanumeric characters except dots, slashes, and hyphens
        $orderId = preg_replace('#[^\pL\pN\./-]+#', '', $orderId);
        // Remove control characters
        $orderId = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $orderId);
        return trim($orderId);
    }
}

