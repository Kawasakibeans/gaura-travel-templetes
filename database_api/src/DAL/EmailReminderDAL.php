<?php

namespace App\DAL;

use PDO;

class EmailReminderDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get bookings due in X days
     * Line: 34-38, 68-72, 103-107 (in template)
     */
    public function getBookingsDueInDays($days, $limit = null)
    {
        $query = "SELECT DATEDIFF(tb.travel_date, CURRENT_DATE) as total_days_left,
                         tb.order_id,
                         (SELECT tbp.email_pax 
                          FROM wpk4_backend_travel_booking_pax tbp 
                          WHERE tbp.order_id = tb.order_id 
                          AND tbp.email_pax <> '' 
                          LIMIT 1) as email
                  FROM wpk4_backend_travel_bookings tb
                  WHERE tb.travel_date > CURRENT_DATE 
                  AND DATEDIFF(tb.travel_date, CURRENT_DATE) = :days 
                  AND (tb.adult_order = '' OR tb.adult_order IS NULL) 
                  AND LOWER(tb.order_type) = 'wpt'";
        
        if ($limit !== null) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get bookings due in multiple day ranges
     * Line: 34-38, 68-72, 103-107 (in template)
     */
    public function getBookingsDueInDayRanges($dayRanges = [7, 4, 1])
    {
        $placeholders = [];
        foreach ($dayRanges as $index => $day) {
            $placeholders[] = ':day_' . $index;
        }
        $placeholdersStr = implode(',', $placeholders);
        
        $query = "SELECT DATEDIFF(tb.travel_date, CURRENT_DATE) as total_days_left,
                         tb.order_id,
                         tb.travel_date,
                         (SELECT tbp.email_pax 
                          FROM wpk4_backend_travel_booking_pax tbp 
                          WHERE tbp.order_id = tb.order_id 
                          AND tbp.email_pax <> '' 
                          LIMIT 1) as email
                  FROM wpk4_backend_travel_bookings tb
                  WHERE tb.travel_date > CURRENT_DATE 
                  AND DATEDIFF(tb.travel_date, CURRENT_DATE) IN ($placeholdersStr)
                  AND (tb.adult_order = '' OR tb.adult_order IS NULL) 
                  AND LOWER(tb.order_type) = 'wpt'
                  ORDER BY tb.travel_date ASC";
        
        $stmt = $this->db->prepare($query);
        foreach ($dayRanges as $index => $day) {
            $stmt->bindValue(':day_' . $index, (int)$day, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if reminder email already sent for order
     * Line: 50-57, 85-92, 120-127 (in template)
     */
    public function checkReminderEmailSent($orderId, $days)
    {
        $emailSubject = "Upcoming Trip in  {$days} Days - #{$orderId}";
        
        $query = "SELECT * FROM wpk4_backend_order_email_history 
                 WHERE order_id = :order_id 
                 AND email_type = 'Trip Remainder' 
                 AND email_subject = :email_subject
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':email_subject', $emailSubject);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false;
    }

    /**
     * Insert reminder email history
     * Line: 50-57, 85-92, 120-127 (in template)
     */
    public function insertReminderEmailHistory($orderId, $emailAddress, $days)
    {
        $todayDate = date("Y-m-d H:i:s");
        $emailSubject = "Upcoming Trip in  {$days} Days - #{$orderId}";
        
        $query = "INSERT INTO wpk4_backend_order_email_history 
                 (email_type, order_id, email_address, initiated_date, initiated_by, email_subject) 
                 VALUES 
                 ('Trip Remainder', :order_id, :email_address, :initiated_date, 'automation', :email_subject)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':email_address', $emailAddress);
        $stmt->bindValue(':initiated_date', $todayDate);
        $stmt->bindValue(':email_subject', $emailSubject);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }

    /**
     * Get booking details for reminder
     */
    public function getBookingDetails($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                 WHERE order_id = :order_id 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

