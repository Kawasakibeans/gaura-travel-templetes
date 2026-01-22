<?php
/**
 * Payment Manager Service
 * Business logic for payment manager operations
 */

namespace App\Services;

use App\DAL\PaymentManagerDAL;
use Exception;

class PaymentManagerService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PaymentManagerDAL();
    }

    /**
     * Get bookings with payment filters
     */
    public function getBookingsWithFilters(array $filters = []): array
    {
        // Set default date range (last 7 days)
        if (empty($filters['order_date_start']) && empty($filters['order_date_end'])) {
            $filters['order_date_start'] = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
            $filters['order_date_end'] = date('Y-m-d') . ' 23:59:59';
        }

        $filters['limit'] = $filters['limit'] ?? 100;

        $bookings = $this->dal->getBookingsWithFilters($filters);

        // Enrich with additional data
        foreach ($bookings as &$booking) {
            $orderId = $booking['order_id'];

            // Get payment history
            $paymentHistory = $this->dal->getPaymentHistoryByOrderId($orderId);
            $booking['payment_history'] = $paymentHistory;
            $booking['payment_history_count'] = count($paymentHistory);

            // Get passenger contact
            $passengerContact = $this->dal->getPassengerContactByOrderId($orderId);
            $booking['passenger_contact'] = $passengerContact;

            // Get conversations
            $conversationTypes = ['call_out_remarks', 'request', 'reasons', 'emailsent', 'remarks', 'extranotes'];
            $booking['conversations'] = [];
            foreach ($conversationTypes as $type) {
                $conversation = $this->dal->getPaymentConversation($orderId, $type);
                if ($conversation) {
                    $booking['conversations'][$type] = $conversation['message'];
                } else {
                    $booking['conversations'][$type] = '';
                }
            }
        }

        return [
            'bookings' => $bookings,
            'total_count' => count($bookings),
            'filters' => $filters
        ];
    }

    /**
     * Get payment history by order ID
     */
    public function getPaymentHistoryByOrderId(string $orderId): array
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        $history = $this->dal->getPaymentHistoryByOrderId($orderId);

        return [
            'order_id' => $orderId,
            'payment_history' => $history,
            'total_count' => count($history)
        ];
    }

    /**
     * Update payment conversation
     */
    public function updatePaymentConversation(array $data): array
    {
        if (empty($data['order_id'])) {
            throw new Exception('Order ID is required', 400);
        }

        if (empty($data['msg_type'])) {
            throw new Exception('Message type is required', 400);
        }

        $conversationData = [
            'order_id' => $data['order_id'],
            'msg_type' => $data['msg_type'],
            'message' => $data['message'] ?? '',
            'updated_on' => date('Y-m-d H:i:s'),
            'updated_by' => $data['updated_by'] ?? 'system'
        ];

        $id = $this->dal->upsertPaymentConversation($conversationData);

        return [
            'id' => $id,
            'order_id' => $data['order_id'],
            'msg_type' => $data['msg_type'],
            'message' => 'Conversation updated successfully'
        ];
    }

    /**
     * Get booking notes by order ID
     */
    public function getBookingNotesByOrderId(string $orderId): array
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        $notes = $this->dal->getBookingNotesByOrderId($orderId);

        // Group by updated_on
        $groupedNotes = [];
        foreach ($notes as $note) {
            $updatedOn = $note['updated_on'];
            if (!isset($groupedNotes[$updatedOn])) {
                $groupedNotes[$updatedOn] = [
                    'updated_on' => $updatedOn,
                    'updated_by' => $note['updated_by'],
                    'notes' => []
                ];
            }
            $groupedNotes[$updatedOn]['notes'][] = $note;
        }

        return [
            'order_id' => $orderId,
            'notes' => array_values($groupedNotes),
            'total_count' => count($notes)
        ];
    }

    /**
     * Get matched payments
     */
    public function getMatchedPayments(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-4 days')) . ' 00:00:00';
        $limit = $filters['limit'] ?? 100;

        $bookings = $this->dal->getMatchedPayments($dateFrom, $limit);

        // Calculate balances for each booking
        $enrichedBookings = [];
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            $payments = $this->dal->getPaymentsForBalance($orderId);

            $totalAmount = 0;
            $tramsReceivedAmount = 0;
            $profileId = '';

            foreach ($payments as $payment) {
                if ($payment['profile_no']) {
                    $profileId = $payment['profile_no'];
                }
                if ($payment['total_amount']) {
                    $totalAmount = (float)$payment['total_amount'];
                }
                $tramsReceivedAmount += (float)$payment['trams_received_amount'];
            }

            $balance = $totalAmount - $tramsReceivedAmount;

            $booking['profile_id'] = $profileId;
            $booking['total_amount'] = $totalAmount;
            $booking['trams_received_amount'] = $tramsReceivedAmount;
            $booking['balance'] = $balance;
            $booking['match_status'] = $this->calculateMatchStatus($balance, $booking['payment_status']);

            $enrichedBookings[] = $booking;
        }

        return [
            'bookings' => $enrichedBookings,
            'total_count' => count($enrichedBookings)
        ];
    }

    /**
     * Get non-matched payments
     */
    public function getNonMatchedPayments(array $filters = []): array
    {
        // Set default date range
        if (empty($filters['date_from'])) {
            $filters['date_from'] = date('Y-m-d', strtotime('-20 days')) . ' 00:00:00';
        }

        $filters['limit'] = $filters['limit'] ?? 150;

        $bookings = $this->dal->getNonMatchedPayments($filters);

        // Enrich with payment data
        $enrichedBookings = [];
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            $payments = $this->dal->getPaymentsForBalance($orderId);

            $totalAmount = 0;
            $tramsReceivedAmount = 0;
            $profileId = '';

            foreach ($payments as $payment) {
                if ($payment['profile_no']) {
                    $profileId = $payment['profile_no'];
                }
                if ($payment['total_amount']) {
                    $totalAmount = (float)$payment['total_amount'];
                }
                $tramsReceivedAmount += (float)$payment['trams_received_amount'];
            }

            $balance = $totalAmount - $tramsReceivedAmount;

            // Get conversations
            $callOutRemarks = $this->dal->getPaymentConversation($orderId, 'call_out_remarks');
            $request = $this->dal->getPaymentConversation($orderId, 'request');
            $reasons = $this->dal->getPaymentConversation($orderId, 'reasons');

            $booking['profile_id'] = $profileId;
            $booking['total_amount'] = $totalAmount;
            $booking['trams_received_amount'] = $tramsReceivedAmount;
            $booking['balance'] = $balance;
            $booking['match_status'] = $this->calculateMatchStatus($balance, $booking['payment_status']);
            $booking['call_out_remarks'] = $callOutRemarks ? $callOutRemarks['message'] : '';
            $booking['request'] = $request ? $request['message'] : '';
            $booking['reasons'] = $reasons ? $reasons['message'] : '';

            $enrichedBookings[] = $booking;
        }

        return [
            'bookings' => $enrichedBookings,
            'total_count' => count($enrichedBookings)
        ];
    }

    /**
     * Get orders for 72-hour cancellation
     */
    public function getOrdersFor72HourCancellation(): array
    {
        $orders = $this->dal->getOrdersFor72HourCancellation();

        $enrichedOrders = [];
        foreach ($orders as $order) {
            $orderId = $order['order_id'];

            // Skip if receipt uploaded
            if ($this->dal->hasReceiptUploaded($orderId)) {
                continue;
            }

            $payments = $this->dal->getPaymentsForBalance($orderId);
            $tramsReceivedAmount = 0;

            foreach ($payments as $payment) {
                $tramsReceivedAmount += (float)$payment['trams_received_amount'];
            }

            $totalAmount = (float)($order['total_amount'] ?? 0);

            // Skip if received amount > 5%
            if ($tramsReceivedAmount > ($totalAmount * 0.05 + 0.1)) {
                continue;
            }

            $order['trams_received_amount'] = $tramsReceivedAmount;
            $order['balance'] = $totalAmount - $tramsReceivedAmount;

            $enrichedOrders[] = $order;
        }

        return [
            'orders' => $enrichedOrders,
            'total_count' => count($enrichedOrders)
        ];
    }

    /**
     * Calculate match status
     */
    private function calculateMatchStatus(float $balance, string $paymentStatus): string
    {
        if ($paymentStatus === 'paid') {
            return 'Already paid';
        }

        if ($balance == 0) {
            return 'Fully paid';
        } elseif ($balance < 1 && $balance > -1) {
            return '+-$1 Different. Fully paid';
        } else {
            return '$' . number_format($balance, 2) . ' different';
        }
    }
}

