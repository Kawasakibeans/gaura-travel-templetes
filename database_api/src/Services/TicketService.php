<?php
/**
 * Ticket Service Layer
 * 
 * Encapsulates business logic for ticket management
 */

namespace App\Services;

use App\DAL\TicketDAL;

class TicketService {
    private $dal;

    public function __construct(TicketDAL $dal) {
        $this->dal = $dal;
    }

    /**
     * Get tickets with filters
     * 
     * @param array $filters Filter parameters
     * @return array Tickets
     */
    public function getTickets($filters = []) {
        return $this->dal->getTickets($filters);
    }

    /**
     * Delete ticket by auto_id
     * 
     * @param int $autoId Auto ID of the ticket
     * @return bool Success status
     */
    public function deleteTicket($autoId) {
        // Check if ticket exists
        $ticket = $this->dal->getTicketById($autoId);
        
        if (!$ticket) {
            throw new \Exception("Ticket not found with auto_id: $autoId", 404);
        }
        
        return $this->dal->deleteTicket($autoId);
    }
}

