<?php

namespace App\DAL;

use PDO;

class EmailManagerDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get bookings with email status
     * Line: 75-220 (in template)
     */
    public function getBookingsWithEmailStatus($orderId = null, $limit = 100)
    {
        if ($orderId !== null && $orderId !== '') {
            $query = "SELECT * FROM wpk4_backend_travel_bookings 
                     WHERE order_id = :order_id AND order_type != 'AGENT' 
                     ORDER BY order_id DESC 
                     LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':order_id', $orderId);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        } else {
            $query = "SELECT * FROM wpk4_backend_travel_bookings 
                     WHERE order_type != 'AGENT' 
                     ORDER BY order_id DESC 
                     LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get customer email for an order
     * Line: 122-125 (in template)
     */
    public function getCustomerEmail($orderId)
    {
        $query = "SELECT email_pax FROM wpk4_backend_travel_booking_pax 
                 WHERE order_id = :order_id 
                 ORDER BY auto_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['email_pax'] : null;
    }

    /**
     * Get booking payment status
     * Line: 240-243 (in template)
     */
    public function getBookingPaymentStatus($orderId)
    {
        $query = "SELECT payment_status FROM wpk4_backend_travel_bookings 
                 WHERE order_id = :order_id 
                 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['payment_status'] : null;
    }

    /**
     * Check if email type exists for order
     * Line: 128-134, 137-143, 146-152, 155-161 (in template)
     */
    public function checkEmailTypeExists($orderId, $emailType)
    {
        $query = "SELECT * FROM wpk4_backend_order_email_history 
                 WHERE order_id = :order_id AND email_type = :email_type 
                 ORDER BY auto_id DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':email_type', $emailType);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'exists' => $result !== false,
            'data' => $result
        ];
    }

    /**
     * Get email history for an order
     * Line: 203-205 (in template)
     */
    public function getEmailHistory($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_order_email_history 
                 WHERE order_id = :order_id 
                 ORDER BY auto_id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert email history record
     * Line: 386-393, 403-410, 465-472, 482-489 (in template)
     */
    public function insertEmailHistory($orderId, $emailType, $emailAddress, $emailSubject, $initiatedBy = 'api')
    {
        $todayDate = date("Y-m-d H:i:s");
        
        $query = "INSERT INTO wpk4_backend_order_email_history 
                 (email_type, order_id, email_address, initiated_date, initiated_by, email_subject) 
                 VALUES (:email_type, :order_id, :email_address, :initiated_date, :initiated_by, :email_subject)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':email_type', $emailType);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':email_address', $emailAddress);
        $stmt->bindValue(':initiated_date', $todayDate);
        $stmt->bindValue(':initiated_by', $initiatedBy);
        $stmt->bindValue(':email_subject', $emailSubject);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }

    /**
     * Get email status summary for an order
     * Line: 245-302 (in template)
     */
    public function getEmailStatusSummary($orderId)
    {
        $emailTypes = ['Booking Email', 'Itinerary Email', 'Tax Invoice', 'Payment Update'];
        $summary = [];
        
        foreach ($emailTypes as $emailType) {
            $check = $this->checkEmailTypeExists($orderId, $emailType);
            $summary[$emailType] = [
                'sent' => $check['exists'],
                'sent_date' => $check['exists'] ? $check['data']['initiated_date'] : null,
                'sent_by' => $check['exists'] ? $check['data']['initiated_by'] : null
            ];
        }
        
        return $summary;
    }
}

