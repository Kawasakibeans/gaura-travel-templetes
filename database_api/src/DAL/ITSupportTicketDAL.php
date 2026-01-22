<?php
/**
 * IT Support Ticket Data Access Layer
 * Handles database operations for IT support tickets
 */

namespace App\DAL;

use Exception;
use PDOException;

class ITSupportTicketDAL extends BaseDAL
{
    /**
     * Create ticket
     */
    public function createTicket($data, $escalateBy, $subStatus = null)
    {
        try {
            $status = $subStatus ? "status, sub_status" : "status";
            $statusValue = $subStatus ? "'pending', 'Escalated to Web'" : "'pending'";
            
            $query = "
                INSERT INTO wpk4_backend_it_support_ticket_portal 
                (fname, lname, branch_location, department, email, request_type, category, sub_category, 
                 specification, existing_pnr_screenshot, new_option_screenshot, escalate_to, escalate_by, 
                 delegate_name, $status) 
                VALUES 
                (:fname, :lname, :branch_location, :department, :email, :type, :category, :sub_category, 
                 :specification, :existing_pnr_screenshot, :new_option_screenshot, :escalate_to, :escalate_by, 
                 :delegate_name, $statusValue)
            ";
            
            $params = [
                'fname' => $data['first_name'],
                'lname' => $data['last_name'],
                'branch_location' => $data['branch_location'],
                'department' => $data['department'],
                'email' => $data['email'],
                'type' => $data['type'],
                'category' => $data['category'],
                'sub_category' => $data['subcategory'] ?? null,
                'specification' => $data['specification'],
                'existing_pnr_screenshot' => $data['existing_pnr_screenshot'] ?? null,
                'new_option_screenshot' => $data['new_option_screenshot'] ?? null,
                'escalate_to' => $data['escalate_to'],
                'escalate_by' => $escalateBy,
                'delegate_name' => $data['delegate_name'] ?? null
            ];
            
            $this->execute($query, $params);
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::createTicket error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get tickets with filters
     */
    public function getTickets($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['request_date'])) {
                $where[] = "created_at LIKE :request_date";
                $params['request_date'] = $filters['request_date'] . '%';
            }
            
            if (!empty($filters['problem_category'])) {
                $where[] = "request_type = :problem_category";
                $params['problem_category'] = $filters['problem_category'];
            }
            
            if (!empty($filters['department'])) {
                $where[] = "department = :department";
                $params['department'] = $filters['department'];
            }
            
            if (!empty($filters['case_id'])) {
                $where[] = "auto_id = :case_id";
                $params['case_id'] = $filters['case_id'];
            }
            
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] == 'Pending') {
                    $where[] = "status NOT IN ('Completed', 'Rejected')";
                } else {
                    $where[] = "status = :status";
                    $params['status'] = $filters['status'];
                }
            }
            
            // Exclude web escalations unless specifically requested
            if (empty($filters['include_web_escalations'])) {
                $where[] = "(sub_status IS NULL OR sub_status != 'Escalated to Web')";
            } else if (!empty($filters['web_escalations_only'])) {
                $where[] = "sub_status = 'Escalated to Web'";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "
                SELECT * FROM wpk4_backend_it_support_ticket_portal
                WHERE $whereClause
                ORDER BY auto_id DESC
                LIMIT 100
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::getTickets error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get ticket by ID
     */
    public function getTicketById($ticketId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_it_support_ticket_portal
                WHERE auto_id = :ticket_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['ticket_id' => $ticketId]);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::getTicketById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update ticket remark
     */
    public function updateRemark($ticketId, $remark, $updatedBy)
    {
        try {
            $query = "
                UPDATE wpk4_backend_it_support_ticket_portal 
                SET remark = :remark, updated_by = :updated_by 
                WHERE auto_id = :ticket_id
            ";
            
            return $this->execute($query, [
                'remark' => $remark,
                'updated_by' => $updatedBy,
                'ticket_id' => $ticketId
            ]);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::updateRemark error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus($ticketId, $status, $priority, $delegateName, $updatedBy)
    {
        try {
            $query = "
                UPDATE wpk4_backend_it_support_ticket_portal 
                SET status = :status, 
                    priority = :priority, 
                    delegate_name = :delegate_name,
                    updated_by = :updated_by
                WHERE auto_id = :ticket_id
            ";
            
            return $this->execute($query, [
                'status' => $status,
                'priority' => $priority,
                'delegate_name' => $delegateName,
                'updated_by' => $updatedBy,
                'ticket_id' => $ticketId
            ]);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::updateStatus error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Move ticket to IT support portal (remove web escalation)
     */
    public function moveToIT($ticketId, $updatedAt, $updatedBy)
    {
        try {
            $query = "
                UPDATE wpk4_backend_it_support_ticket_portal 
                SET sub_status = NULL, 
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE auto_id = :ticket_id
            ";
            
            return $this->execute($query, [
                'updated_at' => $updatedAt,
                'updated_by' => $updatedBy,
                'ticket_id' => $ticketId
            ]);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::moveToIT error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Escalate ticket to web
     */
    public function escalateToWeb($ticketId, $updatedAt, $updatedBy)
    {
        try {
            $query = "
                UPDATE wpk4_backend_it_support_ticket_portal 
                SET sub_status = 'Escalated to Web', 
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE auto_id = :ticket_id
            ";
            
            return $this->execute($query, [
                'updated_at' => $updatedAt,
                'updated_by' => $updatedBy,
                'ticket_id' => $ticketId
            ]);
        } catch (PDOException $e) {
            error_log("ITSupportTicketDAL::escalateToWeb error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
}

