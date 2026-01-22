<?php
/**
 * Refund Service Layer
 * 
 * Handles business logic for refund operations
 */

namespace App\Services;

use App\DAL\RefundDAL;

class RefundService {
    private $dal;

    public function __construct(RefundDAL $dal) {
        $this->dal = $dal;
    }

    /**
     * Get refunds with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array containing refunds and metadata
     */
    public function getRefunds($filters = []) {
        $refunds = $this->dal->getRefunds($filters);
        
        // Get latest conversation for each refund
        $enrichedRefunds = [];
        foreach ($refunds as $refund) {
            $enrichedRefund = $refund;
            $latestChat = $this->dal->getLatestRefundConversation($refund['refund_id']);
            $enrichedRefund['latest_conversation'] = $latestChat;
            $enrichedRefunds[] = $enrichedRefund;
        }
        
        return [
            'refunds' => $enrichedRefunds,
            'count' => count($enrichedRefunds),
            'limit' => $filters['limit'] ?? 20
        ];
    }

    /**
     * Get refund by ID
     * 
     * @param string $refundId Refund ID
     * @return array|null Refund record with additional data or null if not found
     */
    public function getRefundById($refundId) {
        $refund = $this->dal->getRefundById($refundId);
        
        if (!$refund) {
            return null;
        }
        
        // Get conversations and attachments
        $conversations = $this->dal->getRefundConversations($refundId);
        $attachments = $this->dal->getRefundAttachments($refundId);
        
        $refund['conversations'] = $conversations;
        $refund['attachments'] = $attachments;
        
        return $refund;
    }

    /**
     * Get conversations for a refund
     * 
     * @param string $refundId Refund ID
     * @param int $limit Optional limit
     * @return array Array of conversations
     */
    public function getRefundConversations($refundId, $limit = null) {
        return $this->dal->getRefundConversations($refundId, $limit);
    }

    /**
     * Get attachments for a refund
     * 
     * @param string $refundId Refund ID
     * @return array Array of attachments
     */
    public function getRefundAttachments($refundId) {
        return $this->dal->getRefundAttachments($refundId);
    }

    /**
     * Get term keys for dropdown options
     * 
     * @param string $category Category
     * @param string $optionType Option type
     * @return array Array of term keys
     */
    public function getTermKeys($category, $optionType) {
        return $this->dal->getTermKeys($category, $optionType);
    }
}

