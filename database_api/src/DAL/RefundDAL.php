<?php
/**
 * Refund Data Access Layer (DAL)
 * 
 * Handles all database operations for refund management
 */

namespace App\DAL;

class RefundDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get refunds with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array of refund records
     */
    public function getRefunds($filters = []) {
        $where = ['1=1'];
        $params = [];

        // Filter by order_id
        if (!empty($filters['order_id'])) {
            $where[] = "order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }

        // Filter by refund_id
        if (!empty($filters['refund_id'])) {
            $where[] = "refund_id = :refund_id";
            $params[':refund_id'] = $filters['refund_id'];
        }

        // Filter by source_pcc
        if (!empty($filters['pcc'])) {
            $where[] = "source_pcc = :pcc";
            $params[':pcc'] = $filters['pcc'];
        }

        // Filter by pnr
        if (!empty($filters['pnr'])) {
            $where[] = "pnr = :pnr";
            $params[':pnr'] = $filters['pnr'];
        }

        // Filter by airline
        if (!empty($filters['airline'])) {
            $where[] = "airline = :airline";
            $params[':airline'] = $filters['airline'];
        }

        // Filter by consolidator
        if (!empty($filters['consolidator'])) {
            $where[] = "consolidator = :consolidator";
            $params[':consolidator'] = $filters['consolidator'];
        }

        // Filter by case_status
        if (!empty($filters['status'])) {
            $where[] = "case_status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filter by ticket_issued_country
        if (!empty($filters['ticket_country'])) {
            $where[] = "ticket_issued_country = :ticket_country";
            $params[':ticket_country'] = $filters['ticket_country'];
        }

        // Filter by travel_date (ticketed_date_filter)
        if (!empty($filters['ticketed_date'])) {
            $where[] = "travel_date LIKE :ticketed_date";
            $params[':ticketed_date'] = $filters['ticketed_date'] . '%';
        }

        // Filter by refund_applied_date (refund_received_date_filter)
        if (!empty($filters['refund_received_date'])) {
            $where[] = "refund_applied_date LIKE :refund_received_date";
            $params[':refund_received_date'] = $filters['refund_received_date'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $limit = max(1, min(1000, $limit)); // Clamp between 1 and 1000

        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_refunds 
            WHERE $whereClause 
            ORDER BY created_on DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get refund by ID
     * 
     * @param string $refundId Refund ID
     * @return array|null Refund record or null if not found
     */
    public function getRefundById($refundId) {
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM wpk4_backend_travel_payment_refunds 
            WHERE refund_id = :refund_id
            LIMIT 1
        ");
        $stmt->execute([':refund_id' => $refundId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }

    /**
     * Get conversations/notes for a refund
     * 
     * @param string $refundId Refund ID
     * @param int $limit Limit number of results
     * @return array Array of conversation records
     */
    public function getRefundConversations($refundId, $limit = null) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_conversations 
            WHERE order_id = :refund_id 
            AND msg_type LIKE 'refundchats%' 
            ORDER BY auto_id DESC
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':refund_id', $refundId);
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get attachments for a refund
     * 
     * @param string $refundId Refund ID
     * @return array Array of attachment records
     */
    public function getRefundAttachments($refundId) {
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM wpk4_backend_travel_payment_conversations 
            WHERE order_id = :refund_id 
            AND msg_type LIKE 'refundchatattachments%' 
            ORDER BY auto_id DESC
        ");
        $stmt->execute([':refund_id' => $refundId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get term keys for dropdown options
     * 
     * @param string $category Category (e.g., 'refund')
     * @param string $optionType Option type (e.g., 'type of refund', 'oneway_return')
     * @return array Array of term key records
     */
    public function getTermKeys($category, $optionType) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_term_keys 
            WHERE category = :category 
            AND option_type = :option_type
        ";
        
        $params = [
            ':category' => $category,
            ':option_type' => $optionType
        ];
        
        // Add exclusion for 'not selected' if it's type of refund
        if ($optionType === 'type of refund') {
            $sql .= " AND option_value != 'not selected'";
        }
        
        // Add exclusion for Charter and 'not selected' if it's oneway_return
        if ($optionType === 'oneway_return') {
            $sql .= " AND option_value NOT IN ('Charter', 'not selected')";
        }
        
        $sql .= " ORDER BY option_value ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get latest conversation for a refund
     * 
     * @param string $refundId Refund ID
     * @return array|null Latest conversation record or null if not found
     */
    public function getLatestRefundConversation($refundId) {
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM wpk4_backend_travel_payment_conversations 
            WHERE order_id = :refund_id 
            AND msg_type LIKE 'refundchats%' 
            ORDER BY auto_id DESC 
            LIMIT 1
        ");
        $stmt->execute([':refund_id' => $refundId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }
}

