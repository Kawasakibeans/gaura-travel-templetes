<?php

namespace App\DAL;

use PDO;

class PNRCheckupDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get passenger by first name, last name, and PNR
     * Line: 453-456 (in template)
     */
    public function getPaxByNameAndPNR($firstName, $lastName, $pnr)
    {
        $query = "SELECT * 
                  FROM wpk4_backend_travel_booking_pax 
                  WHERE fname = :fname AND lname = :lname AND pnr = :pnr 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':fname', $firstName);
        $stmt->bindValue(':lname', $lastName);
        $stmt->bindValue(':pnr', $pnr);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get currency conversion rate for a booking
     * Line: 497-502 (in template)
     */
    public function getCurrencyRate($orderId)
    {
        $query = "SELECT rate 
                  FROM wpk4_ypsilon_bookings_table_currency_rates 
                  WHERE booking_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['rate'] : null;
    }

    /**
     * Get passenger by order_id, first name, and last name
     * Line: 599-605 (in template)
     */
    public function getPaxByOrderAndName($orderId, $firstName, $lastName)
    {
        $query = "SELECT gds_pax_id, auto_id
                  FROM wpk4_backend_travel_booking_pax 
                  WHERE order_id = :order_id AND fname = :fname AND lname = :lname
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':fname', $firstName);
        $stmt->bindValue(':lname', $lastName);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get fare information for a passenger
     * Line: 614-619 (in template)
     */
    public function getFareByPaxId($paxId)
    {
        $query = "SELECT PriceBuy 
                  FROM wpk4_ypsilon_bookings_table_fare 
                  WHERE PaxId = :pax_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pax_id', $paxId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['PriceBuy'] : 0;
    }

    /**
     * Get tax information for a passenger
     * Line: 625-630 (in template)
     */
    public function getTaxByPaxId($paxId)
    {
        $query = "SELECT Amount 
                  FROM wpk4_ypsilon_bookings_table_tax 
                  WHERE PaxId = :pax_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pax_id', $paxId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['Amount'] : 0;
    }

    /**
     * Get metadata from history of updates
     * Line: 697-700 (in template)
     */
    public function getHistoryMetadata($metaKey, $orderId)
    {
        $query = "SELECT meta_value 
                  FROM wpk4_backend_history_of_updates 
                  WHERE meta_key = :meta_key AND type_id = :order_id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':meta_key', $metaKey);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['meta_value'] : null;
    }

    /**
     * Insert passenger mismatch record
     * Line: 762-771 (in template)
     */
    public function insertPaxMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy)
    {
        $query = "INSERT INTO wpk4_backend_travel_booking_pax_mismatch 
                  (order_id, pax_id, unique_id, meta_key, api_response, db_results, checked_date, checked_by)
                  VALUES (:order_id, :pax_id, :unique_id, :meta_key, :api_response, :db_results, :checked_date, :checked_by)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':pax_id', $paxId, PDO::PARAM_INT);
        $stmt->bindValue(':unique_id', $uniqueId);
        $stmt->bindValue(':meta_key', $metaKey);
        $stmt->bindValue(':api_response', $apiResponse);
        $stmt->bindValue(':db_results', $dbResults);
        $stmt->bindValue(':checked_date', $checkedDate);
        $stmt->bindValue(':checked_by', $checkedBy);
        
        return $stmt->execute();
    }

    /**
     * Insert itinerary mismatch record
     * Line: 781-790 (in template)
     */
    public function insertItineraryMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy)
    {
        // Same table as passenger mismatch
        return $this->insertPaxMismatch($orderId, $paxId, $uniqueId, $metaKey, $apiResponse, $dbResults, $checkedDate, $checkedBy);
    }

    /**
     * Get booking by order_id
     * Line: 73-75 (in template)
     */
    public function getBookingByOrderId($orderId)
    {
        $query = "SELECT order_date, travel_date, return_date, total_pax, source, agent_info, total_amount, billing_email, billing_phone 
                  FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get passenger by PNR and GDS PAX ID
     * Line: 77-80 (in template)
     */
    public function getPaxByPnrAndGdsPaxId($pnr, $gdsPaxId)
    {
        $query = "SELECT salutation, fname, lname, gender, ppn, dob, phone_pax, email_pax, gds_pax_id 
                  FROM wpk4_backend_travel_booking_pax 
                  WHERE pnr = :pnr AND gds_pax_id = :gds_pax_id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pnr', $pnr);
        $stmt->bindValue(':gds_pax_id', $gdsPaxId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all history metadata for an order_id
     * Line: 169-171 (in template)
     */
    public function getAllHistoryMetadata($orderId)
    {
        $query = "SELECT meta_key, meta_value 
                  FROM wpk4_backend_history_of_updates 
                  WHERE type_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

