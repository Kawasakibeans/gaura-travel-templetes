<?php
/**
 * Ticket Check Data Access Layer
 * Handles database operations for ticket check import
 */

namespace App\DAL;

use Exception;
use PDOException;

class TicketCheckDAL extends BaseDAL
{
    /**
     * Find matching passenger and booking by PNR and name
     */
    public function findMatchingPassenger($pnr, $firstName, $lastName)
    {
        try {
            $query = "
                SELECT pax.auto_id, pax.order_id, pax.pnr 
                FROM wpk4_backend_travel_booking_pax pax 
                JOIN wpk4_backend_travel_bookings booking 
                    ON pax.order_id = booking.order_id 
                    AND pax.product_id = booking.product_id 
                WHERE BINARY pax.pnr = :pnr 
                    AND pax.fname LIKE :first_name 
                    AND BINARY pax.lname = :last_name 
                    AND booking.payment_status = 'paid'
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'pnr' => $pnr,
                'first_name' => $firstName . '%',
                'last_name' => $lastName
            ]);
        } catch (PDOException $e) {
            error_log("TicketCheckDAL::findMatchingPassenger error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check if ticket number already exists
     */
    public function checkTicketExists($pnr, $ticketNumber)
    {
        try {
            $query = "
                SELECT auto_id 
                FROM wpk4_backend_travel_booking_ticket_number 
                WHERE BINARY pnr = :pnr 
                    AND BINARY document = :ticket_number
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'pnr' => $pnr,
                'ticket_number' => $ticketNumber
            ]);
            
            return $result !== null;
        } catch (PDOException $e) {
            error_log("TicketCheckDAL::checkTicketExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment status for booking
     */
    public function getPaymentStatus($orderId, $pnr)
    {
        try {
            $query = "
                SELECT bookings.payment_status 
                FROM wpk4_backend_travel_bookings bookings 
                JOIN wpk4_backend_travel_booking_pax pax ON  
                    bookings.order_id = pax.order_id AND 
                    bookings.co_order_id = pax.co_order_id AND 
                    bookings.product_id = pax.product_id
                WHERE BINARY pax.pnr = :pnr 
                    AND bookings.order_id = :order_id
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'pnr' => $pnr,
                'order_id' => $orderId
            ]);
            
            return $result ? $result['payment_status'] : null;
        } catch (PDOException $e) {
            error_log("TicketCheckDAL::getPaymentStatus error: " . $e->getMessage());
            // Return null if query fails
            return null;
        }
    }

    /**
     * Insert ticket number record
     */
    public function insertTicketNumber($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_ticket_number 
                (order_id, pax_id, pnr, document, document_type, reason, vendor, seq_no, confirmed, a_l, 
                 transaction_amount, tax, fee, comm, agent, fp, pax_fname, pax_lname, pax_ticket_name, a_s, 
                 updated_on, updated_by, gds_from) 
                VALUES 
                (:order_id, :pax_id, :pnr, :document, :document_type, 'New', :vendor, :seq_no, :confirmed, :a_l, 
                 :transaction_amount, :tax, :fee, :comm, :agent, :fp, :pax_fname, :pax_lname, :pax_ticket_name, :a_s, 
                 :updated_on, :updated_by, :gds_from)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("TicketCheckDAL::insertTicketNumber error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update passenger status to Ticketed
     */
    public function updatePassengerStatus($paxId, $ticketNumber, $modifiedBy, $modifiedOn)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_booking_pax
                SET ticket_number = :ticket_number, 
                    pax_status = 'Ticketed', 
                    modified_by = :modified_by, 
                    late_modified = :late_modified
                WHERE auto_id = :pax_id
            ";
            
            return $this->execute($query, [
                'ticket_number' => $ticketNumber,
                'modified_by' => $modifiedBy,
                'late_modified' => $modifiedOn,
                'pax_id' => $paxId
            ]);
        } catch (PDOException $e) {
            error_log("TicketCheckDAL::updatePassengerStatus error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

