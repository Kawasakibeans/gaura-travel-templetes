<?php

namespace App\DAL;

use PDO;

class TicketNumberUpdatorDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get passenger by auto_id to get order_id and pnr
     * Line: 158-162 (in template)
     */
    public function getPaxByAutoId($autoId)
    {
        $query = "SELECT order_id, pnr 
                  FROM wpk4_backend_travel_booking_pax 
                  WHERE auto_id = :auto_id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':auto_id', $autoId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insert ticket number record
     * Line: 76-196 (in template)
     */
    public function insertTicketNumber($paxId, $document, $documentType, $transactionAmount, $vendor, $issueDate, $updatedOn, $updatedBy, $a_l, $paxFname, $paxLname, $addedOn, $addedBy, $orderId, $pnr, $oid)
    {
        $query = "INSERT INTO wpk4_backend_travel_booking_ticket_number
                  (pax_id, document, document_type, transaction_amount, vendor, issue_date,
                   updated_on, updated_by, a_l, pax_fname, pax_lname, added_on, added_by, order_id, pnr, oid)
                  VALUES (:pax_id, :document, :document_type, :transaction_amount, :vendor, :issue_date,
                          :updated_on, :updated_by, :a_l, :pax_fname, :pax_lname, :added_on, :added_by, :order_id, :pnr, :oid)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pax_id', $paxId, PDO::PARAM_INT);
        $stmt->bindValue(':document', $document);
        $stmt->bindValue(':document_type', $documentType);
        $stmt->bindValue(':transaction_amount', $transactionAmount);
        $stmt->bindValue(':vendor', $vendor);
        $stmt->bindValue(':issue_date', $issueDate);
        $stmt->bindValue(':updated_on', $updatedOn);
        $stmt->bindValue(':updated_by', $updatedBy);
        $stmt->bindValue(':a_l', $a_l);
        $stmt->bindValue(':pax_fname', $paxFname);
        $stmt->bindValue(':pax_lname', $paxLname);
        $stmt->bindValue(':added_on', $addedOn);
        $stmt->bindValue(':added_by', $addedBy);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':pnr', $pnr);
        $stmt->bindValue(':oid', $oid);
        
        return $stmt->execute();
    }

    /**
     * Update passenger ticket number and status
     * Line: 223-234 (in template)
     */
    public function updatePaxTicketNumber($autoId, $ticketNumber, $paxStatus)
    {
        $query = "UPDATE wpk4_backend_travel_booking_pax 
                  SET ticket_number = :ticket_number, pax_status = :pax_status 
                  WHERE auto_id = :auto_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':ticket_number', $ticketNumber);
        $stmt->bindValue(':pax_status', $paxStatus);
        $stmt->bindValue(':auto_id', $autoId, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        return [
            'success' => $result,
            'affected_rows' => $stmt->rowCount()
        ];
    }

    /**
     * Insert ticket checkup record
     * Line: 327 (in template)
     * Note: This is optional - will silently fail if table doesn't exist
     */
    public function insertTicketCheckup($data)
    {
        if (empty($data)) {
            return false;
        }

        try {
            $cols = array_keys($data);
            $placeholders = array_fill(0, count($cols), '?');
            
            $query = "INSERT INTO wpk4_amadeus_ticket_number_checkup (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                return false;
            }
            
            $values = array_values($data);
            for ($i = 0; $i < count($values); $i++) {
                $param = $i + 1;
                if (is_int($values[$i])) {
                    $stmt->bindValue($param, $values[$i], PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, (string)$values[$i], PDO::PARAM_STR);
                }
            }
            
            return $stmt->execute();
        } catch (\PDOException $e) {
            // Table doesn't exist or other database error - silently fail
            // This is optional functionality
            return false;
        }
    }
}

