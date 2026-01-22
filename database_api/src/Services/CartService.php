<?php
/**
 * Cart Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\CartDAL;
use Exception;

class CartService
{
    private $cartDAL;

    public function __construct()
    {
        $this->cartDAL = new CartDAL();
    }

    /**
     * Validate stock availability for a single trip
     */
    public function validateStock($pricingId, $pax = 1)
    {
        if (empty($pricingId) || $pricingId <= 0) {
            throw new Exception('pricing_id is required and must be greater than 0', 400);
        }

        if ($pax < 1) {
            throw new Exception('pax must be at least 1', 400);
        }

        $result = $this->cartDAL->validateStock($pricingId, $pax);
        
        if ($result === null) {
            return [
                'stock_available' => false,
                'count' => '0 ' . $pax,
                'count2' => 0
            ];
        }

        return [
            'stock_available' => $result['stock_available'],
            'count' => $result['count']
        ];
    }

    /**
     * Validate stock availability for round trip
     */
    public function validateStockRoundTrip($pricingId, $pricingIdReturn, $pax)
    {
        if (empty($pricingId) || $pricingId <= 0) {
            throw new Exception('pricing_id is required and must be greater than 0', 400);
        }

        if (empty($pricingIdReturn) || $pricingIdReturn <= 0) {
            throw new Exception('pricing_id_return is required and must be greater than 0', 400);
        }

        if ($pax < 1) {
            throw new Exception('pax must be at least 1', 400);
        }

        $result = $this->cartDAL->validateStockRoundTrip($pricingId, $pricingIdReturn, $pax);
        
        if ($result === null) {
            return [
                'stock_available' => false,
                'count' => '0 ' . $pax,
                'count2' => '0 ' . $pax
            ];
        }

        return [
            'stock_available' => $result['stock_available'],
            'count' => $result['count'],
            'count2' => $result['count2']
        ];
    }

    /**
     * Check for recent booking
     */
    public function checkRecentBooking($emailId)
    {
        if (empty($emailId)) {
            throw new Exception('email_id is required', 400);
        }

        $result = $this->cartDAL->checkRecentBooking($emailId);
        
        if (!$result) {
            return [
                'recent_booking' => false
            ];
        }

        // Parse order_id from meta_value (format: "payment/?order_id=123")
        $metaValue = $result['meta_value'];
        $parsedUrl = parse_url($metaValue);
        $orderId = null;
        
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);
            $orderId = isset($params['order_id']) ? (int)$params['order_id'] : null;
        }

        if (!$orderId) {
            return [
                'recent_booking' => false
            ];
        }

        // Check payment status
        $paymentStatus = $this->cartDAL->getPaymentStatus($orderId);
        
        if ($paymentStatus === 'partially_paid') {
            return [
                'recent_booking' => true,
                'previous_order_id' => $orderId
            ];
        }

        return [
            'recent_booking' => false
        ];
    }

    /**
     * Cancel previous booking
     */
    public function cancelPreviousBooking($orderId)
    {
        if (empty($orderId) || $orderId <= 0) {
            throw new Exception('order_id is required and must be greater than 0', 400);
        }

        // Validate order exists
        if (!$this->cartDAL->orderExists($orderId)) {
            throw new Exception("Order ID {$orderId} not found", 404);
        }

        // Set timezone
        date_default_timezone_set("Australia/Melbourne");
        $currentDateTime = date("Y-m-d H:i:s");

        try {
            // Begin transaction
            $this->cartDAL->beginTransaction();

            // Insert cancel meta
            $this->cartDAL->insertCancelMeta($orderId);

            // Update payment status
            $this->cartDAL->updatePaymentStatus($orderId, $currentDateTime);

            // Insert booking history
            $this->cartDAL->insertBookingHistory($orderId, $currentDateTime);

            // Get bookings for cancellation
            $bookings = $this->cartDAL->getBookingsForCancellation($orderId);

            // Update seat availability for each booking
            foreach ($bookings as $booking) {
                $tripCode = $booking['trip_code'];
                $travelDate = $booking['travel_date'];
                $totalPax = (int)$booking['total_pax'];

                // Get current availability
                $currentPax = $this->cartDAL->getCurrentAvailabilityPax($tripCode, $travelDate);

                if ($currentPax !== null) {
                    // Calculate new availability (subtract booked pax)
                    $newPax = $currentPax - $totalPax;

                    // Update availability
                    $this->cartDAL->updateSeatAvailability($tripCode, $travelDate, $newPax, $currentDateTime);

                    // Get pricing ID
                    $pricingId = $this->cartDAL->getPricingId($tripCode, $travelDate);

                    if ($pricingId) {
                        // Insert log
                        $changedPaxCount = '-' . $totalPax;
                        $this->cartDAL->insertSeatAvailabilityLog(
                            $pricingId,
                            $currentPax,
                            $newPax,
                            $currentDateTime,
                            $changedPaxCount,
                            $orderId
                        );
                    }
                }
            }

            // Commit transaction
            $this->cartDAL->commit();

            return [
                'message' => 'Previous booking canceled successfully'
            ];

        } catch (Exception $e) {
            // Rollback on error
            $this->cartDAL->rollback();
            throw $e;
        }
    }
}

