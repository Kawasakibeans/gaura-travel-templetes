<?php
/**
 * Payment Followup Service
 * Business logic for payment followup operations
 */

namespace App\Services;

use App\DAL\PaymentFollowupDAL;
use Exception;

class PaymentFollowupService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentFollowupDAL();
    }

    /**
     * Get payment followups with filters
     */
    public function getPaymentFollowups(array $filters = []): array
    {
        $from = $filters['from'] ?? date('Y-m-d', strtotime('-1 day'));
        $to = $filters['to'] ?? date('Y-m-d');
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        // Validate date range (max 3 days)
        $fromDate = new \DateTime($from);
        $toDate = new \DateTime($to);
        $daysDiff = (int)$fromDate->diff($toDate)->days + 1;

        if ($daysDiff > 3) {
            throw new Exception('Date range cannot exceed 3 days', 400);
        }

        $fromDateTime = $from . ' 00:00:00';
        $toDateTime = $to . ' 23:59:59';

        $followups = $this->dal->getPaymentFollowups($fromDateTime, $toDateTime, $limit, $offset);

        return [
            'followups' => $followups,
            'total_count' => count($followups),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get payment followup by order ID
     */
    public function getPaymentFollowupByOrderId(string $orderId): array
    {
        $result = $this->dal->getPaymentFollowupByOrderId($orderId);
        
        if ($result === null) {
            // Handle case when no record found
            // Either throw exception or return empty/default data
            throw new Exception('Payment followup not found for order ID: ' . $orderId, 404);
            // OR return default structure:
            // return [
            //     'pax_preferred_pay_mode' => null,
            //     'does_pax_wants_to_pay' => null,
            //     'ttl' => null,
            //     'ptl' => null
            // ];
        }
        
        return $result;
    }

    /**
     * Save payment followup status
     */
    public function savePaymentFollowupStatus(array $data): array
    {
        if (empty($data['order_id'])) {
            throw new Exception('Order ID is required', 400);
        }

        $now = date('Y-m-d H:i:s');
        $exists = $this->dal->paymentFollowupExists($data['order_id']);

        if ($exists) {
            $updateData = [
                'order_id' => $data['order_id'],
                'updated_on' => $now
            ];

            if (isset($data['pax_preferred_pay_mode'])) {
                $updateData['pax_preferred_pay_mode'] = $data['pax_preferred_pay_mode'];
            }

            if (isset($data['does_pax_wants_to_pay'])) {
                $updateData['does_pax_wants_to_pay'] = $data['does_pax_wants_to_pay'];
            }

            if (isset($data['ttl'])) {
                $updateData['ttl'] = ($data['ttl'] === '' || $data['ttl'] === null) ? null : (string)$data['ttl'];
            }

            if (isset($data['ptl'])) {
                $updateData['ptl'] = ($data['ptl'] === '' || $data['ptl'] === null) ? null : (string)$data['ptl'];
            }

            $this->dal->updatePaymentFollowup($updateData);
        } else {
            $insertData = [
                'order_id' => $data['order_id'],
                'pax_preferred_pay_mode' => $data['pax_preferred_pay_mode'] ?? null,
                'does_pax_wants_to_pay' => $data['does_pax_wants_to_pay'] ?? null,
                'ttl' => ($data['ttl'] ?? null) === '' ? null : (string)($data['ttl'] ?? null),
                'ptl' => ($data['ptl'] ?? null) === '' ? null : (string)($data['ptl'] ?? null),
                'updated_on' => $now
            ];

            $this->dal->createPaymentFollowup($insertData);
        }

        return [
            'order_id' => $data['order_id'],
            'message' => 'Status saved successfully'
        ];
    }

    /**
     * Get remarks by order ID
     */
    public function getRemarksByOrderId(string $orderId): array
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        $remarks = $this->dal->getRemarksByOrderId($orderId);

        return [
            'order_id' => $orderId,
            'remarks' => $remarks,
            'total_count' => count($remarks)
        ];
    }

    /**
     * Add remark
     */
    public function addRemark(array $data): array
    {
        if (empty($data['order_id'])) {
            throw new Exception('Order ID is required', 400);
        }

        if (empty($data['comment'])) {
            throw new Exception('Comment is required', 400);
        }

        // Ensure table exists
        $this->dal->ensureRemarksTableExists();

        $remarkData = [
            'order_id' => $data['order_id'],
            'comment' => $data['comment'],
            'added_by' => $data['added_by'] ?? 'guest',
            'added_on' => date('Y-m-d H:i:s')
        ];

        $id = $this->dal->createRemark($remarkData);

        return [
            'id' => $id,
            'order_id' => $data['order_id'],
            'message' => 'Remark added successfully'
        ];
    }
}

