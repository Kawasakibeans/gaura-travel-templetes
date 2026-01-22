<?php
/**
 * Ticket Data Access Layer (DAL)
 * 
 * Handles all database operations for ticket management
 */

namespace App\DAL;

class TicketDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get tickets with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array of ticket records
     */
    public function getTickets($filters = []) {
        $where = [];
        $params = [];
        
        // Filter by document
        if (!empty($filters['document'])) {
            $where[] = "document = :document";
            $params[':document'] = $filters['document'];
        } else {
            $where[] = "order_id = 'DummyGT123'";
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? 20;
        
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_ticket_number 
            WHERE $whereClause 
            ORDER BY document DESC 
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete ticket by auto_id
     * 
     * @param int $autoId Auto ID of the ticket
     * @return bool Success status
     */
    public function deleteTicket($autoId) {
        $sql = "
            DELETE FROM wpk4_backend_travel_booking_ticket_number 
            WHERE auto_id = :auto_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':auto_id', $autoId, \PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get ticket by auto_id
     * 
     * @param int $autoId Auto ID of the ticket
     * @return array|null Ticket record or null
     */
    public function getTicketById($autoId) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_ticket_number 
            WHERE auto_id = :auto_id
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':auto_id', $autoId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }
}

