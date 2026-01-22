<?php

namespace App\Services;

use App\DAL\OrderManagementDAL;

class OrderManagementService
{
    private $dal;

    public function __construct(OrderManagementDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get booking details by order_id
     * Line: 1262-1274 (in template)
     */
    public function getBookingDetails($orderId, $coOrderId = '')
    {
        $booking = $this->dal->getBookingByOrderId($orderId, $coOrderId);
        
        if (!$booking) {
            throw new \Exception('Booking not found', 404);
        }
        
        // Format payment status
        $paymentStatusMap = [
            'pending' => 'Pending',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'canceled' => 'Xxln With Deposit',
            'N/A' => 'Failed',
            'refund' => 'Refund Done',
            'waiting_voucher' => 'Refund Under Process',
            'receipt_received' => 'Receipt Received',
            'voucher_submited' => 'Rebooked'
        ];
        
        $booking['payment_status_text'] = $paymentStatusMap[$booking['payment_status']] ?? 'Pending';
        
        return $booking;
    }

    /**
     * Get recent bookings
     * Line: 784-848 (in template)
     */
    public function getRecentBookings($limit = 60)
    {
        $bookings = $this->dal->getRecentBookings($limit);
        
        $result = [];
        foreach ($bookings as $booking) {
            $orderId = $booking['order_id'];
            $coOrderId = $booking['co_order_id'];
            $productId = $booking['product_id'];
            
            // Get return bookings count
            $returnCount = $this->dal->getReturnBookingsCount($orderId, $coOrderId);
            
            // Get movements count
            $movementsCount = $this->dal->getMovementsCount($orderId, $productId, $coOrderId);
            
            // Format payment status
            $paymentStatusMap = [
                'pending' => 'Pending',
                'partially_paid' => 'Partially Paid',
                'paid' => 'Paid',
                'canceled' => 'Xxln With Deposit',
                'N/A' => 'Failed',
                'refund' => 'Refund Done',
                'waiting_voucher' => 'Refund Under Process',
                'receipt_received' => 'Receipt Received',
                'voucher_submited' => 'Rebooked'
            ];
            
            $booking['payment_status_text'] = $paymentStatusMap[$booking['payment_status']] ?? 'Pending';
            $booking['return_count'] = $returnCount;
            $booking['movements_count'] = $movementsCount;
            $booking['is_return'] = $returnCount > 1;
            $booking['has_movements'] = $movementsCount > 0;
            
            $result[] = $booking;
        }
        
        return $result;
    }

    /**
     * Divide order - split passengers into separate bookings
     * Line: 856-970 (in template)
     */
    public function divideOrder($orderId, $productId, $coOrderId, $paxAutoIds, $currentUser)
    {
        if (empty($paxAutoIds)) {
            throw new \Exception('No passengers selected for division', 400);
        }
        
        $dividedDate = date('Y-m-d H:i:s');
        
        // Get current booking info
        $booking = $this->dal->getBookingByOrderId($orderId, $coOrderId);
        if (!$booking) {
            throw new \Exception('Booking not found', 404);
        }
        
        // Get last co_order_id
        $lastCoOrderId = $this->dal->getLastCoOrderId($orderId);
        
        // Determine new co_order_id
        if ($coOrderId === '') {
            if ($lastCoOrderId === '') {
                // First division - set original to D1
                $newCoOrderId = 'D1';
                $this->dal->updateBookingCoOrderId($orderId, $productId, $newCoOrderId, $dividedDate);
                $this->dal->updatePassengerCoOrderId($orderId, $productId, $newCoOrderId);
                $coOrderId = $newCoOrderId;
            } else {
                // Already divided - increment
                $lastCoNumber = (int)ltrim($lastCoOrderId, 'D');
                $newCoOrderId = 'D' . ($lastCoNumber + 1);
                $this->dal->updateBookingCoOrderId($orderId, $productId, $newCoOrderId, $dividedDate);
                $this->dal->updatePassengerCoOrderId($orderId, $productId, $newCoOrderId);
                $coOrderId = $newCoOrderId;
            }
        }
        
        // Get the last co_order_id again to determine the new one for divided passengers
        $lastCoOrderId = $this->dal->getLastCoOrderId($orderId);
        $lastCoNumber = (int)ltrim($lastCoOrderId, 'D');
        $finalCoOrderId = 'D' . ($lastCoNumber + 1);
        
        // Count passengers to divide
        $countPax = count($paxAutoIds);
        
        // Get original booking info
        $originalBooking = $this->dal->getBookingByOrderId($orderId, $coOrderId);
        if (!$originalBooking) {
            throw new \Exception('Original booking not found', 404);
        }
        
        // Update original booking total_pax
        $totalPaxNew = $originalBooking['total_pax'] - $countPax;
        $this->dal->updateBookingTotalPax($orderId, $coOrderId, $productId, $totalPaxNew);
        
        // Update passengers to new co_order_id
        $this->dal->updatePassengerCoOrderId($orderId, $productId, $finalCoOrderId, '', $paxAutoIds);
        
        // Create new booking for divided passengers
        $newBookingData = [
            'order_type' => $originalBooking['order_type'] ?? 'WPT',
            'order_id' => $orderId,
            'co_order_id' => $finalCoOrderId,
            'divided_date' => $dividedDate,
            't_type' => $originalBooking['t_type'] ?? '',
            'product_id' => $productId,
            'new_product_id' => $originalBooking['new_product_id'] ?? '',
            'product_title' => $originalBooking['product_title'] ?? '',
            'total_pax' => $countPax,
            'source' => $originalBooking['source'] ?? '',
            'trip_code' => $originalBooking['trip_code'] ?? '',
            'dom_date' => $originalBooking['dom_date'] ?? '',
            'remarks' => $originalBooking['remarks'] ?? '',
            'agent_info' => $originalBooking['agent_info'] ?? '',
            'total_amount' => $originalBooking['total_amount'] ?? 0,
            'payment_ref' => $originalBooking['payment_ref'] ?? '',
            'payment_status' => $originalBooking['payment_status'] ?? 'pending',
            'modified_by' => $currentUser,
            'deposit_amount' => $originalBooking['deposit_amount'] ?? 0,
            'travel_date' => $originalBooking['travel_date'] ?? '',
            'order_date' => $originalBooking['order_date'] ?? date('Y-m-d H:i:s')
        ];
        
        $this->dal->insertDividedBooking($newBookingData);
        
        return [
            'order_id' => $orderId,
            'original_co_order_id' => $coOrderId,
            'new_co_order_id' => $finalCoOrderId,
            'divided_passengers_count' => $countPax,
            'remaining_passengers_count' => $totalPaxNew
        ];
    }

    /**
     * Update booking movement
     * Line: 971-1096 (in template)
     */
    public function updateBookingMovement($orderId, $productId, $coOrderId, $movementData, $currentUser)
    {
        // Get current booking
        $booking = $this->dal->getBookingForMovement($orderId, $productId, $coOrderId);
        if (!$booking) {
            throw new \Exception('Booking not found', 404);
        }
        
        $currentTime = date('Y-m-d H:i:s');
        
        // Prepare old movement data for history
        $oldProductId = $booking['new_product_id'] ?: $booking['product_id'];
        $oldMovementData = [
            'order_id' => $orderId,
            'product_id' => $booking['product_id'],
            'co_order_id' => $coOrderId,
            'new_product_id' => $oldProductId,
            'product_title' => $booking['product_title'],
            'trip_code' => $booking['trip_code'],
            'travel_date_int' => $booking['travel_date'],
            'pax' => $booking['total_pax'],
            'remarks' => $booking['remarks'],
            'updated_on' => $currentTime,
            'updated_by' => $currentUser
        ];
        
        // Insert movement history
        $this->dal->insertBookingMovement($oldMovementData);
        
        // Prepare update data
        $updateData = [
            'new_product_id' => $movementData['new_product_id'] ?? $oldProductId,
            'product_title' => $movementData['product_title'] ?? $booking['product_title'],
            'trip_code' => $movementData['trip_code'] ?? $booking['trip_code'],
            'travel_date' => isset($movementData['travel_date']) ? date('Y-m-d', strtotime($movementData['travel_date'])) . ' 00:00:00' : $booking['travel_date'],
            'total_pax' => $movementData['pax'] ?? $booking['total_pax'],
            'remarks' => $movementData['remarks'] ?? $booking['remarks'],
            'late_modified' => $currentTime,
            'modified_by' => $currentUser
        ];
        
        // Update booking
        $this->dal->updateBookingMovement($orderId, $productId, $coOrderId, $updateData);
        
        // Update passenger PNR if provided
        if (isset($movementData['pnr'])) {
            $this->dal->updatePassengerAfterMovement($orderId, $productId, $coOrderId, $movementData['pnr']);
        }
        
        return [
            'order_id' => $orderId,
            'co_order_id' => $coOrderId,
            'product_id' => $productId,
            'updated_fields' => array_keys($updateData),
            'movement_recorded' => true
        ];
    }

    /**
     * Get booking with passenger details
     * Line: 1097-1124 (in template)
     */
    public function getBookingWithPassenger($orderId, $paxId)
    {
        $booking = $this->dal->getBookingWithPassenger($orderId, $paxId);
        
        if (!$booking) {
            throw new \Exception('Booking or passenger not found', 404);
        }
        
        // Get airline code if trip code exists
        if (!empty($booking['trip_code'])) {
            $tripcodeDivided = substr($booking['trip_code'], 8, 2);
            $airlineCode = $this->dal->getAirlineCode($tripcodeDivided);
            $booking['airline_code'] = $airlineCode;
        }
        
        return $booking;
    }
}

