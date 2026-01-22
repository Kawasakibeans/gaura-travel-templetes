<?php
/**
 * GDS Booking Data Access Layer
 * Handles all database operations for GDS booking imports
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDSBookingDAL extends BaseDAL
{
    /**
     * Get last order ID
     */
    public function getLastOrderId()
    {
        $query = "
            SELECT order_id 
            FROM wpk4_backend_travel_bookings 
            ORDER BY order_id DESC 
            LIMIT 1
        ";
        $result = $this->queryOne($query);
        return $result ? (int)$result['order_id'] : 0;
    }

    /**
     * Check if passenger exists
     */
    public function checkPassengerExists($pnr, $lname, $fname)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE pnr = :pnr 
                AND lname LIKE :lname 
                AND fname LIKE :fname
            LIMIT 1
        ";
        return $this->queryOne($query, [
            'pnr' => $pnr,
            'lname' => '%' . $lname . '%',
            'fname' => '%' . $fname . '%'
        ]);
    }

    /**
     * Check if booking exists
     */
    public function checkBookingExists($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
            LIMIT 1
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }

    /**
     * Create new booking
     */
    public function createBooking($bookingData)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_bookings 
                (order_type, order_id, order_date, t_type, product_id, travel_date, return_date, 
                 payment_status, total_pax, source, trip_code, agent_info, total_amount, 
                 deposit_amount, balance, added_on, added_by)
            VALUES 
                ('gds', :order_id, :order_date, :t_type, '', :travel_date, :return_date, 
                 'partially_paid', :total_pax, 'import', :trip_code, :agent_info, :total_amount, 
                 '', '', :added_on, :added_by)
        ";
        
        return $this->execute($query, $bookingData);
    }

    /**
     * Create booking history update
     */
    public function createHistoryUpdate($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn)
    {
        $query = "
            INSERT INTO wpk4_backend_history_of_updates 
                (type_id, meta_key, meta_value, updated_by, updated_on)
            VALUES 
                (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
        ";
        
        return $this->execute($query, [
            'type_id' => $typeId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => $updatedOn
        ]);
    }

    /**
     * Create passenger record
     */
    public function createPassenger($passengerData)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_pax 
                (order_type, order_id, order_date, product_id, salutation, fname, lname, 
                 gender, dob, email_pax, pnr, added_on, added_by)
            VALUES 
                ('gds', :order_id, :order_date, '', :salutation, :fname, :lname, 
                 :gender, :dob, :email, :pnr, :added_on, :added_by)
        ";
        
        return $this->execute($query, $passengerData);
    }

    /**
     * Create booking with old format (includes late_modified, modified_by)
     */
    public function createBookingOldFormat($bookingData)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_bookings 
                (order_type, order_id, order_date, t_type, product_id, travel_date, return_date, 
                 payment_status, total_pax, source, trip_code, agent_info, total_amount, 
                 deposit_amount, balance, late_modified, modified_by, added_on, added_by)
            VALUES 
                ('gds', :order_id, :order_date, :t_type, '', :travel_date, :return_date, 
                 'partially_paid', :total_pax, :source, :trip_code, :agent_info, :total_amount, 
                 :deposit_amount, :balance, :late_modified, :modified_by, :added_on, :added_by)
        ";
        
        return $this->execute($query, $bookingData);
    }

    /**
     * Create passenger with old format (includes late_modified, modified_by)
     */
    public function createPassengerOldFormat($passengerData)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_pax 
                (order_type, order_id, order_date, product_id, salutation, fname, lname, 
                 gender, dob, email_pax, pnr, late_modified, modified_by, added_on, added_by)
            VALUES 
                ('gds', :order_id, :order_date, '', :salutation, :fname, :lname, 
                 :gender, :dob, :email, :pnr, :late_modified, :modified_by, :added_on, :added_by)
        ";
        
        return $this->execute($query, $passengerData);
    }

    /**
     * Get booking total amount
     */
    public function getBookingTotalAmount($orderId)
    {
        $query = "
            SELECT total_amount 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? (float)$result['total_amount'] : null;
    }

    /**
     * Get transaction value from meta
     */
    public function getTransactionValueFromMeta($orderId)
    {
        $query = "
            SELECT CAST(ROUND(meta_value,2) as DECIMAL(11,2)) as transaction_value
            FROM wpk4_backend_history_of_updates 
            WHERE meta_key LIKE 'Transaction TotalTurnover' 
              AND type_id = :order_id
            ORDER BY updated_on DESC 
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? (float)$result['transaction_value'] : null;
    }

    /**
     * Update total amount from meta
     */
    public function updateTotalAmountFromMeta($orderId, $updatedBy, $updatedOn)
    {
        // First get the current values
        $totalAmount = $this->getBookingTotalAmount($orderId);
        $transactionValue = $this->getTransactionValueFromMeta($orderId);

        if ($totalAmount === null || $transactionValue === null) {
            return false;
        }

        // Update if different
        if (abs($totalAmount - $transactionValue) > 0.01) {
            $query = "
                UPDATE wpk4_backend_travel_bookings 
                SET total_amount = :transaction_value
                WHERE order_id = :order_id
            ";
            $this->execute($query, [
                'transaction_value' => $transactionValue,
                'order_id' => $orderId
            ]);

            // Insert history record
            $metaValue = $totalAmount . " - " . $transactionValue;
            $this->createHistoryUpdate(
                $orderId,
                'Total Transation Turnover',
                $metaValue,
                $updatedBy,
                $updatedOn
            );

            return true;
        }

        return false;
    }

    /**
     * Check if PNR exists
     */
    public function checkPnrExists($pnr)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE pnr = :pnr
            LIMIT 1
        ";
        return $this->queryOne($query, ['pnr' => $pnr]);
    }
}

