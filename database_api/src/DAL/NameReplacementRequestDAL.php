<?php

namespace App\DAL;

use PDO;

class NameReplacementRequestDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get datechange name replacement candidates
     * Line: 15-28 (in template)
     */
    public function getDatechangeNameReplacementCandidates($limit = 50)
    {
        $query = "SELECT DISTINCT 
                    bookings.order_id, 
                    bookings.product_id, 
                    bookings.trip_code, 
                    bookings.travel_date, 
                    dc_request.auto_id 
                  FROM wpk4_backend_travel_datechange_request dc_request 
                  JOIN wpk4_backend_travel_bookings bookings 
                      ON bookings.trip_code = dc_request.original_tripcode 
                      AND bookings.travel_date = dc_request.original_travel_date 
                      AND bookings.order_id > dc_request.order_id
                  JOIN wpk4_backend_travel_booking_pax pax
                      ON bookings.order_id = pax.order_id 
                      AND bookings.product_id = pax.product_id
                  WHERE bookings.payment_status = 'paid' 
                  AND pax.pax_status = 'New' 
                  AND dc_request.ticket_issued = 'yes' 
                  AND dc_request.status = 'updated'
                  ORDER BY dc_request.auto_id DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get refund name replacement candidates (base query)
     * Line: 54-57 (in template)
     */
    public function getRefundBaseBookings($limit = 50)
    {
        $query = "SELECT 
                    bookings.order_id, 
                    bookings.trip_code, 
                    bookings.travel_date, 
                    bookings.payment_status, 
                    bookings.order_type 
                  FROM wpk4_backend_travel_bookings bookings 
                  WHERE bookings.payment_status IN ('waiting_voucher', 'refund') 
                  AND bookings.order_type = 'WPT'
                  ORDER BY bookings.order_id DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get refund name replacement candidates (sub query)
     * Line: 67-73 (in template)
     */
    public function getRefundNameReplacementCandidates($tripCode, $travelDate, $orderId)
    {
        $query = "SELECT 
                    bookings.order_id, 
                    bookings.product_id, 
                    bookings.trip_code, 
                    bookings.travel_date, 
                    bookings.order_type, 
                    bookings.payment_status, 
                    pax.pax_status 
                  FROM wpk4_backend_travel_bookings bookings 
                  JOIN wpk4_backend_travel_booking_pax pax
                      ON bookings.order_id = pax.order_id 
                      AND bookings.product_id = pax.product_id
                  WHERE bookings.trip_code = :trip_code 
                  AND bookings.travel_date = :travel_date 
                  AND bookings.order_id > :order_id 
                  AND bookings.order_type = 'WPT' 
                  AND bookings.payment_status = 'paid' 
                  AND pax.pax_status = 'New'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':trip_code', $tripCode);
        $stmt->bindValue(':travel_date', $travelDate);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update pax status to name replacement request
     * Line: 40-46, 83-89 (in template)
     */
    public function updatePaxStatusToNameReplacement($orderId, $productId, $currentTime, $modifiedBy = 'name_replacement_cron')
    {
        $query = "UPDATE wpk4_backend_travel_booking_pax 
                  SET 
                      pax_status = 'Name replacement request',
                      late_modified = :late_modified,
                      modified_by = :modified_by
                  WHERE order_id = :order_id 
                  AND product_id = :product_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':late_modified', $currentTime);
        $stmt->bindValue(':modified_by', $modifiedBy);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        
        return $stmt->execute();
    }
}

