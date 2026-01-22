<?php
/**
 * IT Support Ticket Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\ITSupportTicketDAL;
use Exception;

class ITSupportTicketService
{
    private $ticketDAL;
    
    public function __construct()
    {
        $this->ticketDAL = new ITSupportTicketDAL();
    }
    
    /**
     * Create ticket
     */
    public function createTicket($data, $escalateBy, $isWebEscalation = false)
    {
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'branch_location', 'department', 
                          'email', 'type', 'category', 'specification', 'escalate_to'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }
        
        // Handle file uploads
        $existingPnrScreenshot = null;
        $newOptionScreenshot = null;
        
        // Note: File uploads should be handled in the route handler
        // This service expects file paths to be passed in $data
        
        $data['existing_pnr_screenshot'] = $existingPnrScreenshot;
        $data['new_option_screenshot'] = $newOptionScreenshot;
        
        $subStatus = $isWebEscalation ? 'Escalated to Web' : null;
        $ticketId = $this->ticketDAL->createTicket($data, $escalateBy, $subStatus);
        
        return [
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => [
                'ticket_id' => $ticketId
            ]
        ];
    }
    
    /**
     * Get tickets with filters
     */
    public function getTickets($filters = [])
    {
        $tickets = $this->ticketDAL->getTickets($filters);
        
        return [
            'success' => true,
            'data' => $tickets,
            'count' => count($tickets)
        ];
    }
    
    /**
     * Get ticket by ID
     */
    public function getTicketById($ticketId)
    {
        if (empty($ticketId)) {
            throw new Exception('Ticket ID is required', 400);
        }
        
        $ticket = $this->ticketDAL->getTicketById($ticketId);
        
        if (!$ticket) {
            throw new Exception('Ticket not found', 404);
        }
        
        return [
            'success' => true,
            'data' => $ticket
        ];
    }
    
    /**
     * Update ticket remark
     */
    public function updateRemark($ticketId, $remark, $updatedBy)
    {
        if (empty($ticketId)) {
            throw new Exception('Ticket ID is required', 400);
        }
        
        if (empty($remark)) {
            throw new Exception('Remark is required', 400);
        }
        
        if (empty($updatedBy)) {
            throw new Exception('Updated by is required', 400);
        }
        
        $this->ticketDAL->updateRemark($ticketId, $remark, $updatedBy);
        
        return [
            'success' => true,
            'message' => 'Remark updated successfully'
        ];
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus($ticketId, $status, $priority, $delegateName, $updatedBy)
    {
        if (empty($ticketId)) {
            throw new Exception('Ticket ID is required', 400);
        }
        
        if (empty($updatedBy)) {
            throw new Exception('Updated by is required', 400);
        }
        
        // Default values
        $status = $status ?? 'Pending';
        $priority = $priority ?? 'Low';
        $delegateName = $delegateName ?? null;
        
        $this->ticketDAL->updateStatus($ticketId, $status, $priority, $delegateName, $updatedBy);
        
        // Get updated ticket for email notification if needed
        $ticket = $this->ticketDAL->getTicketById($ticketId);
        
        // Note: Email notification logic should be handled separately
        // if ($status == 'Awaiting HO' || $status == 'Escalated to HO') {
        //     // Send email notification
        // }
        
        return [
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $ticket
        ];
    }
    
    /**
     * Move ticket to IT support portal
     */
    public function moveToIT($ticketId, $updatedBy)
    {
        if (empty($ticketId)) {
            throw new Exception('Ticket ID is required', 400);
        }
        
        if (empty($updatedBy)) {
            throw new Exception('Updated by is required', 400);
        }
        
        $updatedAt = date('Y-m-d H:i:s');
        $this->ticketDAL->moveToIT($ticketId, $updatedAt, $updatedBy);
        
        return [
            'success' => true,
            'message' => 'Ticket moved to IT support portal successfully'
        ];
    }
    
    /**
     * Escalate ticket to web
     */
    public function escalateToWeb($ticketId, $updatedBy)
    {
        if (empty($ticketId)) {
            throw new Exception('Ticket ID is required', 400);
        }
        
        if (empty($updatedBy)) {
            throw new Exception('Updated by is required', 400);
        }
        
        $updatedAt = date('Y-m-d H:i:s');
        $this->ticketDAL->escalateToWeb($ticketId, $updatedAt, $updatedBy);
        
        return [
            'success' => true,
            'message' => 'Ticket escalated to web successfully'
        ];
    }
}

