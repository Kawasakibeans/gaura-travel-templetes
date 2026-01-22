<?php
/**
 * Cart Data Access Layer
 * Handles all database operations for cart-related data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CartDAL extends BaseDAL
{
    /**
     * Validate stock availability for a single trip
     */
    public function validateStock($pricingId, $pax = 1)
    {
        $query = "
            SELECT stock, pax 
            FROM wpk4_backend_manage_seat_availability 
            WHERE pricing_id = :pricing_id
        ";
        
        $result = $this->queryOne($query, ['pricing_id' => $pricingId]);
        
        if (!$result || !is_array($result)) {
            return null;
        }
        
        $stockCount = (int)$result['stock'];
        $paxCount = (int)$result['pax'];
        $available = $stockCount - $paxCount;
        
        return [
            'stock' => $stockCount,
            'pax' => $paxCount,
            'available' => $available,
            'stock_available' => $available >= $pax,
            'count' => $available . ' ' . $pax
        ];
    }

    /**
     * Validate stock availability for round trip
     */
    public function validateStockRoundTrip($pricingId, $pricingIdReturn, $pax)
    {
        $queryOutbound = "
            SELECT stock, pax 
            FROM wpk4_backend_manage_seat_availability 
            WHERE pricing_id = :pricing_id
        ";
        
        $queryReturn = "
            SELECT stock, pax 
            FROM wpk4_backend_manage_seat_availability 
            WHERE pricing_id = :pricing_id_return
        ";
        
        $resultOutbound = $this->queryOne($queryOutbound, ['pricing_id' => $pricingId]);
        $resultReturn = $this->queryOne($queryReturn, ['pricing_id_return' => $pricingIdReturn]);
        
        if (!$resultOutbound || !is_array($resultOutbound) || !$resultReturn || !is_array($resultReturn)) {
            return null;
        }
        
        // Calculate outbound availability
        $stockCount = (int)$resultOutbound['stock'];
        $paxCount = (int)$resultOutbound['pax'];
        $available = $stockCount - $paxCount;
        $countAvailable = $available . ' ' . $pax;
        
        // Calculate return availability
        $stockCount2 = (int)$resultReturn['stock'];
        $paxCount2 = (int)$resultReturn['pax'];
        $available2 = $stockCount2 - $paxCount2;
        $countAvailable2 = $available2 . ' ' . $pax;
        
        return [
            'outbound' => [
                'stock' => $stockCount,
                'pax' => $paxCount,
                'available' => $available,
                'count' => $countAvailable
            ],
            'return' => [
                'stock' => $stockCount2,
                'pax' => $paxCount2,
                'available' => $available2,
                'count' => $countAvailable2
            ],
            'stock_available' => ($available >= $pax && $available2 >= $pax),
            'count' => $countAvailable,
            'count2' => $countAvailable2
        ];
    }

    /**
     * Check for recent booking within last 30 minutes
     */
    public function checkRecentBooking($emailId)
    {
        $query = "
            SELECT auto_id, meta_value 
            FROM wpk4_customer_event_log 
            WHERE email_id = :email_id 
              AND meta_value LIKE 'payment/%' 
              AND added_on >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY added_on DESC
            LIMIT 1
        ";
        
        return $this->queryOne($query, ['email_id' => $emailId]);
    }

    /**
     * Get payment status for an order
     */
    public function getPaymentStatus($orderId)
    {
        $query = "
            SELECT payment_status 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
            LIMIT 1
        ";
        
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['payment_status'] : null;
    }

    /**
     * Check if order exists
     */
    public function orderExists($orderId)
    {
        $query = "
            SELECT COUNT(*) as count 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result && (int)$result['count'] > 0;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return parent::beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return parent::commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return parent::rollback();
    }

    /**
     * Insert cancel meta for order
     */
    public function insertCancelMeta($orderId)
    {
        $query = "
            INSERT INTO wpk4_postmeta (post_id, meta_key, meta_value) 
            VALUES (:post_id, 'cancelit', '1')
        ";
        
        return $this->execute($query, ['post_id' => $orderId]);
    }

    /**
     * Update payment status to canceled
     */
    public function updatePaymentStatus($orderId, $currentDateTime, $modifiedBy = 'cancel_duplicate_in_checkout')
    {
        $query = "
            UPDATE wpk4_backend_travel_bookings 
            SET payment_status = 'canceled', 
                payment_modified = :payment_modified, 
                payment_modified_by = :payment_modified_by 
            WHERE order_id = :order_id
        ";
        
        return $this->execute($query, [
            'order_id' => $orderId,
            'payment_modified' => $currentDateTime,
            'payment_modified_by' => $modifiedBy
        ]);
    }

    /**
     * Insert booking update history
     */
    public function insertBookingHistory($orderId, $currentDateTime, $updatedUser = 'cancel_duplicate_in_checkout')
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_update_history 
            (order_id, meta_key, meta_value, updated_time, updated_user) 
            VALUES (:order_id, 'payment_status', 'canceled', :updated_time, :updated_user)
        ";
        
        return $this->execute($query, [
            'order_id' => $orderId,
            'updated_time' => $currentDateTime,
            'updated_user' => $updatedUser
        ]);
    }

    /**
     * Get bookings for cancellation
     */
    public function getBookingsForCancellation($orderId)
    {
        $query = "
            SELECT order_id, trip_code, travel_date, total_pax 
            FROM wpk4_backend_travel_bookings 
            WHERE order_type = 'WPT' AND order_id = :order_id
        ";
        
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get current availability pax
     */
    public function getCurrentAvailabilityPax($tripCode, $travelDate)
    {
        $query = "
            SELECT pax 
            FROM wpk4_backend_manage_seat_availability 
            WHERE trip_code = :trip_code AND DATE(travel_date) = :travel_date
        ";
        
        $result = $this->queryOne($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
        
        return $result ? (int)$result['pax'] : null;
    }

    /**
     * Update seat availability
     */
    public function updateSeatAvailability($tripCode, $travelDate, $newPax, $currentDateTime, $updatedBy = 'previous_cancellation_checkout')
    {
        $query = "
            UPDATE wpk4_backend_manage_seat_availability 
            SET pax = :pax, 
                pax_updated_by = :pax_updated_by, 
                pax_updated_on = :pax_updated_on 
            WHERE trip_code = :trip_code AND DATE(travel_date) = :travel_date
        ";
        
        return $this->execute($query, [
            'pax' => $newPax,
            'pax_updated_by' => $updatedBy,
            'pax_updated_on' => $currentDateTime,
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
    }

    /**
     * Get pricing ID for trip
     */
    public function getPricingId($tripCode, $travelDate)
    {
        $query = "
            SELECT pricing_id 
            FROM wpk4_backend_manage_seat_availability 
            WHERE trip_code = :trip_code AND DATE(travel_date) = :travel_date
        ";
        
        $result = $this->queryOne($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
        
        return $result ? (int)$result['pricing_id'] : null;
    }

    /**
     * Insert seat availability log
     */
    public function insertSeatAvailabilityLog($pricingId, $originalPax, $newPax, $currentDateTime, $changedPaxCount, $orderId, $updatedBy = 'previous_cancellation_checkout')
    {
        $query = "
            INSERT INTO wpk4_backend_manage_seat_availability_log 
            (pricing_id, original_pax, new_pax, updated_on, updated_by, changed_pax_count, order_id) 
            VALUES (:pricing_id, :original_pax, :new_pax, :updated_on, :updated_by, :changed_pax_count, :order_id)
        ";
        
        return $this->execute($query, [
            'pricing_id' => $pricingId,
            'original_pax' => $originalPax,
            'new_pax' => $newPax,
            'updated_on' => $currentDateTime,
            'updated_by' => $updatedBy,
            'changed_pax_count' => $changedPaxCount,
            'order_id' => $orderId
        ]);
    }
}

