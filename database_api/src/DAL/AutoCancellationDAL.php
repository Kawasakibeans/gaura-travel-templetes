<?php
/**
 * Auto Cancellation Data Access Layer
 * Handles database operations for auto-cancellation bookings
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AutoCancellationDAL extends BaseDAL
{
    /**
     * Get bookings pending email reminder (20 minutes)
     */
    public function getPendingEmailReminders($limit = 100)
    {
        $query = "SELECT 
            bookings.auto_id, 
            bookings.order_id, 
            bookings.order_date, 
            bookings.travel_date, 
            bookings.payment_status,
            bookings.sub_payment_status,
            bookings.source,
            pays.trams_received_amount 
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON bookings.order_id = pax.order_id 
            AND bookings.co_order_id = pax.co_order_id 
            AND bookings.product_id = pax.product_id 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.order_date <= NOW() - INTERVAL 20 MINUTE 
            AND bookings.order_date >= NOW() - INTERVAL 600 MINUTE 
            AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = 0.00) 
            AND NOT EXISTS (
                SELECT 1 
                FROM wpk4_backend_order_email_history email 
                WHERE email.order_id = bookings.order_id 
                AND email.email_type = 'Payment reminder'
            )
        ORDER BY bookings.auto_id ASC 
        LIMIT ?";
        
        return $this->query($query, [$limit]);
    }

    /**
     * Get count of bookings pending email reminders
     */
    public function getPendingEmailRemindersCount()
    {
        $query = "SELECT COUNT(DISTINCT bookings.order_id) as total_count
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.order_date <= NOW() - INTERVAL 20 MINUTE 
            AND bookings.order_date >= NOW() - INTERVAL 600 MINUTE 
            AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = 0.00) 
            AND NOT EXISTS (
                SELECT 1 
                FROM wpk4_backend_order_email_history email 
                WHERE email.order_id = bookings.order_id 
                AND email.email_type = 'Payment reminder'
            )";
        
        $result = $this->queryOne($query);
        return (int)$result['total_count'];
    }

    /**
     * Get bookings for 3-hour cancellation (zero paid)
     */
    public function getPending3HourCancellation($limit = 100)
    {
        $query = "SELECT 
            bookings.auto_id, 
            bookings.order_id, 
            bookings.order_date, 
            bookings.travel_date, 
            bookings.payment_status,
            bookings.sub_payment_status,
            bookings.order_type,
            bookings.source,
            pays.trams_received_amount 
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON bookings.order_id = pax.order_id 
            AND bookings.co_order_id = pax.co_order_id 
            AND bookings.product_id = pax.product_id 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.order_date <= NOW() - INTERVAL 3 HOUR 
            AND bookings.order_type IN ('gds', 'WPT')
            AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = 0.00) 
        ORDER BY bookings.auto_id ASC 
        LIMIT ?";
        
        return $this->query($query, [$limit]);
    }

    /**
     * Get count of bookings pending 3-hour cancellation
     */
    public function getPending3HourCancellationCount()
    {
        $query = "SELECT COUNT(DISTINCT bookings.order_id) as total_count
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.order_date <= NOW() - INTERVAL 3 HOUR 
            AND bookings.order_type IN ('gds', 'WPT')
            AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = 0.00)";
        
        $result = $this->queryOne($query);
        return (int)$result['total_count'];
    }

    /**
     * Get bookings for 25-hour cancellation (FIT partially paid)
     */
    public function getPending25HourCancellation($limit = 100)
    {
        $query = "SELECT
            bookings.auto_id,
            bookings.order_id,
            bookings.order_date,
            bookings.travel_date,
            bookings.source,
            bookings.payment_status,
            bookings.sub_payment_status,
            bookings.order_type,
            COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
        FROM wpk4_backend_travel_bookings AS bookings
        LEFT JOIN wpk4_backend_travel_booking_pax AS pax
            ON bookings.order_id = pax.order_id
            AND bookings.co_order_id = pax.co_order_id
            AND bookings.product_id = pax.product_id
        LEFT JOIN wpk4_backend_travel_payment_history AS pays
            ON bookings.order_id = pays.order_id
        WHERE
            bookings.payment_status = 'partially_paid' 
            AND bookings.order_type = 'gds'
            AND (bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received') OR bookings.sub_payment_status IS NULL)
            AND bookings.order_date <= NOW() - INTERVAL 25 HOUR
            AND (pays.order_id IS NULL OR pays.trams_received_amount >= 0.00)
        ORDER BY bookings.auto_id ASC
        LIMIT ?";
        
        return $this->query($query, [$limit]);
    }

    /**
     * Get count of bookings pending 25-hour cancellation
     */
    public function getPending25HourCancellationCount()
    {
        $query = "SELECT COUNT(DISTINCT bookings.order_id) as total_count
        FROM wpk4_backend_travel_bookings AS bookings
        LEFT JOIN wpk4_backend_travel_payment_history AS pays
            ON bookings.order_id = pays.order_id
        WHERE
            bookings.payment_status = 'partially_paid' 
            AND bookings.order_type = 'gds'
            AND (bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received') OR bookings.sub_payment_status IS NULL)
            AND bookings.order_date <= NOW() - INTERVAL 25 HOUR
            AND (pays.order_id IS NULL OR pays.trams_received_amount >= 0.00)";
        
        $result = $this->queryOne($query);
        return (int)$result['total_count'];
    }

    /**
     * Get bookings for 96-hour cancellation (partially paid)
     */
    public function getPending96HourCancellation($limit = 100)
    {
        $query = "SELECT
            bookings.auto_id,
            bookings.order_id,
            bookings.order_date,
            bookings.travel_date,
            bookings.source,
            bookings.payment_status,
            bookings.sub_payment_status,
            bookings.order_type,
            COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
        FROM wpk4_backend_travel_bookings AS bookings
        LEFT JOIN wpk4_backend_travel_booking_pax AS pax
            ON bookings.order_id = pax.order_id
            AND bookings.co_order_id = pax.co_order_id
            AND bookings.product_id = pax.product_id
        LEFT JOIN wpk4_backend_travel_payment_history AS pays
            ON bookings.order_id = pays.order_id
        WHERE
            bookings.payment_status = 'partially_paid' 
            AND bookings.order_type IN ('gds', 'WPT')
            AND (bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received') OR bookings.sub_payment_status IS NULL)
            AND bookings.order_date <= NOW() - INTERVAL 96 HOUR
        ORDER BY bookings.auto_id ASC
        LIMIT ?";
        
        return $this->query($query, [$limit]);
    }

    /**
     * Get count of bookings pending 96-hour cancellation
     */
    public function getPending96HourCancellationCount()
    {
        $query = "SELECT COUNT(DISTINCT bookings.order_id) as total_count
        FROM wpk4_backend_travel_bookings AS bookings
        LEFT JOIN wpk4_backend_travel_payment_history AS pays
            ON bookings.order_id = pays.order_id
        WHERE
            bookings.payment_status = 'partially_paid' 
            AND bookings.order_type IN ('gds', 'WPT')
            AND (bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received') OR bookings.sub_payment_status IS NULL)
            AND bookings.order_date <= NOW() - INTERVAL 96 HOUR";
        
        $result = $this->queryOne($query);
        return (int)$result['total_count'];
    }

    /**
     * Get bookings past deposit deadline
     */
    public function getPendingDepositDeadline($limit = 100)
    {
        $query = "SELECT 
            MIN(bookings.auto_id) AS auto_id,
            bookings.order_id, 
            MIN(bookings.order_date) AS order_date, 
            MIN(bookings.payment_status) AS payment_status,
            MIN(bookings.sub_payment_status) AS sub_payment_status,
            MIN(bookings.source) AS source,
            MIN(bookings.deposit_deadline) AS deposit_deadline,
            COALESCE(SUM(pays.trams_received_amount), 0.00) AS trams_received_amount
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON bookings.order_id = pax.order_id 
            AND bookings.co_order_id = pax.co_order_id 
            AND bookings.product_id = pax.product_id 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.deposit_deadline <= NOW() 
            AND bookings.order_type IN ('gds', 'WPT')
            AND NOT EXISTS (
                SELECT 1 
                FROM wpk4_backend_travel_payment_history p
                WHERE p.order_id = bookings.order_id
                GROUP BY p.order_id
                HAVING SUM(p.trams_received_amount) > 0
            )
        GROUP BY bookings.order_id
        ORDER BY MIN(bookings.auto_id) ASC 
        LIMIT ?";
        
        return $this->query($query, [$limit]);
    }

    /**
     * Get count of bookings past deposit deadline
     */
    public function getPendingDepositDeadlineCount()
    {
        $query = "SELECT COUNT(DISTINCT bookings.order_id) as total_count
        FROM wpk4_backend_travel_bookings bookings 
        LEFT JOIN wpk4_backend_travel_payment_history pays 
            ON bookings.order_id = pays.order_id 
        WHERE 
            bookings.payment_status = 'partially_paid' 
            AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
            AND bookings.deposit_deadline <= NOW() 
            AND bookings.order_type IN ('gds', 'WPT')
            AND NOT EXISTS (
                SELECT 1 
                FROM wpk4_backend_travel_payment_history p
                WHERE p.order_id = bookings.order_id
                GROUP BY p.order_id
                HAVING SUM(p.trams_received_amount) > 0
            )";
        
        $result = $this->queryOne($query);
        return (int)$result['total_count'];
    }

    /**
     * Get paid amount for an order
     */
    public function getPaidAmount($orderId)
    {
        $query = "SELECT SUM(trams_received_amount) as deposit_amount 
                  FROM wpk4_backend_travel_payment_history 
                  WHERE order_id = ? 
                    AND CAST(trams_received_amount AS DECIMAL(10,2)) != 0.00";
        
        $result = $this->queryOne($query, [$orderId]);
        return (float)($result['deposit_amount'] ?? 0.00);
    }

    /**
     * Get total amount for an order
     */
    public function getTotalAmount($orderId)
    {
        $query = "SELECT total_amount 
                  FROM wpk4_backend_travel_bookings 
                  WHERE order_id = ? 
                  LIMIT 1";
        
        $result = $this->queryOne($query, [$orderId]);
        return (float)($result['total_amount'] ?? 0.00);
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking($orderId, $paymentStatus, $subPaymentStatus = null, $byUser = 'system')
    {
        $currentDateTime = date('Y-m-d H:i:s');
        
        // Update booking status
        if ($subPaymentStatus) {
            $query = "UPDATE wpk4_backend_travel_bookings 
                      SET payment_status = ?, 
                          sub_payment_status = ?,
                          payment_modified = ?, 
                          payment_modified_by = ? 
                      WHERE order_id = ?";
            $params = [$paymentStatus, $subPaymentStatus, $currentDateTime, $byUser, $orderId];
        } else {
            $query = "UPDATE wpk4_backend_travel_bookings 
                      SET payment_status = ?, 
                          payment_modified = ?, 
                          payment_modified_by = ? 
                      WHERE order_id = ?";
            $params = [$paymentStatus, $currentDateTime, $byUser, $orderId];
        }
        
        $this->execute($query, $params);

        // Log the update
        $logQuery = "INSERT INTO wpk4_backend_travel_booking_update_history 
                     (order_id, meta_key, meta_value, updated_time, updated_user) 
                     VALUES (?, 'payment_status', ?, ?, ?)";
        $this->execute($logQuery, [$orderId, $paymentStatus, $currentDateTime, $byUser]);

        return true;
    }

    /**
     * Update seat availability for cancelled booking
     */
    public function updateAvailabilityForCancellation($orderId, $byUser)
    {
        $currentDateTime = date('Y-m-d H:i:s');

        // Get booking details
        $query = "SELECT order_id, trip_code, travel_date, total_pax 
                  FROM wpk4_backend_travel_bookings 
                  WHERE order_type != 'gds' AND order_id = ?";
        
        $booking = $this->queryOne($query, [$orderId]);

        if (!$booking) {
            return false; // No availability to update for GDS bookings
        }

        $tripCode = $booking['trip_code'];
        $travelDate = $booking['travel_date'];
        $pax = (int)$booking['total_pax'];

        // Get current availability
        $availQuery = "SELECT pax, pricing_id 
                       FROM wpk4_backend_manage_seat_availability 
                       WHERE trip_code = ? AND DATE(travel_date) = ?";
        
        $availability = $this->queryOne($availQuery, [$tripCode, $travelDate]);

        if (!$availability) {
            return false; // No availability record found
        }

        $currentPax = (int)$availability['pax'];
        $pricingId = $availability['pricing_id'];
        $newPax = $currentPax - $pax; // Reduce pax count

        // Update availability
        $updateQuery = "UPDATE wpk4_backend_manage_seat_availability 
                        SET pax = ?, 
                            pax_updated_by = ?, 
                            pax_updated_on = ? 
                        WHERE trip_code = ? AND DATE(travel_date) = ?";
        
        $this->execute($updateQuery, [$newPax, $byUser, $currentDateTime, $tripCode, $travelDate]);

        // Log availability change
        $changedPax = -$pax;
        $logQuery = "INSERT INTO wpk4_backend_manage_seat_availability_log 
                     (pricing_id, original_pax, new_pax, updated_on, updated_by, order_id, changed_pax_count) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->execute($logQuery, [
            $pricingId,
            $currentPax,
            $newPax,
            $currentDateTime,
            $byUser,
            $orderId,
            $changedPax
        ]);

        return true;
    }
}

