<?php
/**
 * TRAMS Reconciliation Data Access Layer
 * Handles database operations for TRAMS-G360 reconciliation
 */

namespace App\DAL;

use Exception;
use PDOException;

class TramsReconciliationDAL extends BaseDAL
{
    /**
     * Get matched records (both TRAMS and G360)
     */
    public function getMatchedRecords($startDate, $endDate)
    {
        try {
            $query = "
                SELECT t.invoicelink_no AS invoicelink_no_trams,
                       t.order_amnt AS order_amnt_trams, 
                       t.net_due AS net_due_trams,
                       g.Invoicelink_no AS invoicelink_no_g360,
                       g.order_amnt AS order_amnt_g360, 
                       g.net_due AS net_due_g360
                FROM (
                  SELECT invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_trams_booking_invoice_reconciliation 
                  WHERE ISSUEDATE BETWEEN :start1 AND :end1
                ) t
                INNER JOIN (
                  SELECT Invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_ticket_reconciliation 
                  WHERE issue_date BETWEEN :start2 AND :end2
                ) g ON t.invoicelink_no = g.Invoicelink_no
            ";
            
            return $this->query($query, [
                'start1' => $startDate,
                'end1' => $endDate,
                'start2' => $startDate,
                'end2' => $endDate
            ]);
        } catch (PDOException $e) {
            error_log("TramsReconciliationDAL::getMatchedRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get TRAMS-only records
     */
    public function getTramsOnlyRecords($startDate, $endDate)
    {
        try {
            $query = "
                SELECT t.invoicelink_no AS invoicelink_no_trams,
                       t.order_amnt AS order_amnt_trams,
                       t.net_due AS net_due_trams,
                       NULL AS invoicelink_no_g360,
                       NULL AS order_amnt_g360,
                       NULL AS net_due_g360
                FROM (
                  SELECT invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_trams_booking_invoice_reconciliation 
                  WHERE ISSUEDATE BETWEEN :start1 AND :end1
                ) t
                LEFT JOIN (
                  SELECT Invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_ticket_reconciliation 
                  WHERE issue_date BETWEEN :start2 AND :end2
                ) g ON t.invoicelink_no = g.Invoicelink_no
                WHERE g.Invoicelink_no IS NULL
            ";
            
            return $this->query($query, [
                'start1' => $startDate,
                'end1' => $endDate,
                'start2' => $startDate,
                'end2' => $endDate
            ]);
        } catch (PDOException $e) {
            error_log("TramsReconciliationDAL::getTramsOnlyRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get G360-only records
     */
    public function getG360OnlyRecords($startDate, $endDate)
    {
        try {
            $query = "
                SELECT NULL AS invoicelink_no_trams,
                       NULL AS order_amnt_trams,
                       NULL AS net_due_trams,
                       g.Invoicelink_no AS invoicelink_no_g360,
                       g.order_amnt AS order_amnt_g360,
                       g.net_due AS net_due_g360
                FROM (
                  SELECT Invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_ticket_reconciliation 
                  WHERE issue_date BETWEEN :start1 AND :end1
                ) g
                LEFT JOIN (
                  SELECT invoicelink_no, order_amnt, net_due 
                  FROM wpk4_backend_trams_booking_invoice_reconciliation 
                  WHERE ISSUEDATE BETWEEN :start2 AND :end2
                ) t ON t.invoicelink_no = g.Invoicelink_no
                WHERE t.invoicelink_no IS NULL
            ";
            
            return $this->query($query, [
                'start1' => $startDate,
                'end1' => $endDate,
                'start2' => $startDate,
                'end2' => $endDate
            ]);
        } catch (PDOException $e) {
            error_log("TramsReconciliationDAL::getG360OnlyRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get TRAMS aggregates for an invoice
     */
    public function getTramsAggregates($invoiceNo, $startDate, $endDate)
    {
        try {
            $query = "
                SELECT COALESCE(SUM(order_amnt), 0) AS order_sum,
                       COALESCE(SUM(net_due), 0) AS net_sum
                FROM wpk4_backend_trams_booking_invoice_reconciliation
                WHERE invoicelink_no = :invoice
                  AND ISSUEDATE BETWEEN :start AND :end
            ";
            
            return $this->queryOne($query, [
                'invoice' => $invoiceNo,
                'start' => $startDate,
                'end' => $endDate
            ]);
        } catch (PDOException $e) {
            error_log("TramsReconciliationDAL::getTramsAggregates error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update G360 with TRAMS aggregates
     */
    public function updateG360WithTrams($invoiceNo, $orderSum, $netSum, $startDate, $endDate)
    {
        try {
            $query = "
                UPDATE wpk4_backend_ticket_reconciliation
                SET order_amnt = :order_sum,
                    net_due = :net_sum
                WHERE Invoicelink_no = :invoice
                  AND issue_date BETWEEN :start AND :end
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'order_sum' => $orderSum,
                'net_sum' => $netSum,
                'invoice' => $invoiceNo,
                'start' => $startDate,
                'end' => $endDate
            ]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("TramsReconciliationDAL::updateG360WithTrams error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Begin transaction (public wrapper)
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction (public wrapper)
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction (public wrapper)
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}

