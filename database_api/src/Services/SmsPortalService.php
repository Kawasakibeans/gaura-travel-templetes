<?php
/**
 * SMS Portal Service
 * Business logic for SMS portal operations
 */

namespace App\Services;

use App\DAL\SmsPortalDAL;

class SmsPortalService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SmsPortalDAL();
    }

    /**
     * Get mobile numbers by list ID
     */
    public function getMobileNumbersByListId(int $listId): array
    {
        $numbers = $this->dal->getMobileNumbersByListId($listId);
        
        return [
            'list_id' => $listId,
            'numbers' => array_column($numbers, 'mobile'),
            'count' => count($numbers)
        ];
    }

    /**
     * Get phone numbers by order IDs
     */
    public function getPhoneNumbersByOrderIds(array $orderIds): array
    {
        // Filter and sanitize order IDs
        $orderIds = array_filter(array_map('intval', $orderIds));
        
        if (empty($orderIds)) {
            return [
                'order_ids' => [],
                'numbers' => [],
                'count' => 0
            ];
        }
        
        $numbers = $this->dal->getPhoneNumbersByOrderIds($orderIds);
        
        return [
            'order_ids' => $orderIds,
            'numbers' => array_column($numbers, 'phone_pax'),
            'count' => count($numbers)
        ];
    }

    /**
     * Get all SMS templates
     */
    public function getAllSmsTemplates(): array
    {
        $templates = $this->dal->getAllSmsTemplates();
        
        return [
            'templates' => $templates,
            'count' => count($templates)
        ];
    }

    /**
     * Get all SMS contact lists
     */
    public function getAllSmsContactLists(): array
    {
        $lists = $this->dal->getAllSmsContactLists();
        
        return [
            'lists' => $lists,
            'count' => count($lists)
        ];
    }

    /**
     * Get SMS templates by type
     */
    public function getSmsTemplatesByType(): array
    {
        $templates = $this->dal->getSmsTemplatesByType();
        
        return [
            'templates' => $templates,
            'count' => count($templates)
        ];
    }

    /**
     * Create batch tracking
     */
    public function createBatchTracking(string $message, int $totalChunks, int $delayMinutes, int $chunkSize, int $totalNumbers, string $createdBy): array
    {
        $batchId = uniqid('sms_batch_', true);
        $currentDate = date('Y-m-d H:i:s');
        
        $success = $this->dal->createBatchTracking(
            $batchId,
            $message,
            $totalChunks,
            $delayMinutes,
            $chunkSize,
            $totalNumbers,
            'processing',
            $createdBy,
            $currentDate
        );
        
        if (!$success) {
            throw new \Exception('Failed to create batch tracking');
        }
        
        return [
            'batch_id' => $batchId,
            'message' => $message,
            'total_chunks' => $totalChunks,
            'delay_minutes' => $delayMinutes,
            'chunk_size' => $chunkSize,
            'total_numbers' => $totalNumbers,
            'status' => 'processing',
            'created_at' => $currentDate
        ];
    }

    /**
     * Get batch tracking by batch ID
     */
    public function getBatchTrackingByBatchId(string $batchId): array
    {
        $batch = $this->dal->getBatchTrackingByBatchId($batchId);
        
        if (!$batch) {
            throw new \Exception('Batch tracking not found', 404);
        }
        
        return $batch;
    }

    /**
     * Update batch status
     */
    public function updateBatchStatus(string $batchId, string $status, ?int $successfulChunks = null, ?int $failedChunks = null): array
    {
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        
        $success = $this->dal->updateBatchStatus($batchId, $status, $successfulChunks, $failedChunks, $completedAt);
        
        if (!$success) {
            throw new \Exception('Failed to update batch status');
        }
        
        return [
            'batch_id' => $batchId,
            'status' => $status,
            'successful_chunks' => $successfulChunks,
            'failed_chunks' => $failedChunks,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Insert chunk log
     */
    public function insertChunkLog(string $batchId, int $chunkNumber, array $mobileNumbers, ?string $scheduledTime = null): array
    {
        $numbersCount = count($mobileNumbers);
        
        // Store only sample of numbers to prevent DB column overflow
        $sampleSize = min(10, $numbersCount);
        $sampleNumbers = array_slice($mobileNumbers, 0, $sampleSize);
        $numbersString = implode(',', $sampleNumbers);
        
        if ($numbersCount > $sampleSize) {
            $numbersString .= ' ... (and ' . ($numbersCount - $sampleSize) . ' more)';
        }
        
        $currentDate = date('Y-m-d H:i:s');
        
        $id = $this->dal->insertChunkLog(
            $batchId,
            $chunkNumber,
            $numbersString,
            $numbersCount,
            $scheduledTime,
            'scheduled',
            $currentDate
        );
        
        return [
            'id' => $id,
            'batch_id' => $batchId,
            'chunk_number' => $chunkNumber,
            'numbers_count' => $numbersCount,
            'scheduled_time' => $scheduledTime,
            'status' => 'scheduled',
            'created_at' => $currentDate
        ];
    }

    /**
     * Update chunk status
     */
    public function updateChunkStatus(string $batchId, int $chunkNumber, string $status, ?string $messageId = null, ?string $errorMessage = null, ?string $responseData = null): array
    {
        $sentAt = ($status === 'sent' || $status === 'error') ? date('Y-m-d H:i:s') : null;
        
        $success = $this->dal->updateChunkStatus($batchId, $chunkNumber, $status, $messageId, $errorMessage, $responseData, $sentAt);
        
        if (!$success) {
            throw new \Exception('Failed to update chunk status');
        }
        
        return [
            'batch_id' => $batchId,
            'chunk_number' => $chunkNumber,
            'status' => $status,
            'message_id' => $messageId,
            'error_message' => $errorMessage,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get chunk logs by batch ID
     */
    public function getChunkLogsByBatchId(string $batchId): array
    {
        $chunks = $this->dal->getChunkLogsByBatchId($batchId);
        
        return [
            'batch_id' => $batchId,
            'chunks' => $chunks,
            'count' => count($chunks)
        ];
    }

    /**
     * Log SMS history
     */
    public function logSmsHistory(string $orderId, string $message, string $phone, string $source, string $messageId, string $addedBy): array
    {
        $currentDate = date('Y-m-d H:i:s');
        
        $id = $this->dal->insertSmsHistory(
            $orderId,
            $message,
            $phone,
            $source,
            $messageId,
            $currentDate,
            $addedBy
        );
        
        return [
            'id' => $id,
            'order_id' => $orderId,
            'phone' => $phone,
            'message_id' => $messageId,
            'added_on' => $currentDate
        ];
    }
}

