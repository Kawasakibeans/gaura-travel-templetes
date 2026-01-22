<?php
/**
 * SMS Portal Data Access Layer
 * Handles database operations for SMS portal (batch tracking, chunk logging, templates, contact lists)
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class SmsPortalDAL extends BaseDAL
{
    /**
     * Get mobile numbers by list ID
     */
    public function getMobileNumbersByListId(int $listId): array
    {
        $sql = "
            SELECT mobile 
            FROM wpk4_backend_sms_contact_numbers 
            WHERE list_id = ?
        ";
        
        return $this->query($sql, [$listId]);
    }

    /**
     * Get phone numbers by order IDs
     */
    public function getPhoneNumbersByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT DISTINCT phone_pax 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id IN ($placeholders)
        ";
        
        return $this->query($sql, $orderIds);
    }

    /**
     * Get all SMS templates
     */
    public function getAllSmsTemplates(): array
    {
        $sql = "SELECT * FROM wpk4_backend_sms_templates";
        return $this->query($sql);
    }

    /**
     * Get all SMS contact lists
     */
    public function getAllSmsContactLists(): array
    {
        $sql = "SELECT list_id, list_name FROM wpk4_backend_sms_contact_list";
        return $this->query($sql);
    }

    /**
     * Get SMS templates by type
     */
    public function getSmsTemplatesByType(): array
    {
        $sql = "SELECT * FROM wpk4_backend_order_sms_templates";
        return $this->query($sql);
    }

    /**
     * Create batch tracking record
     */
    public function createBatchTracking(string $batchId, string $message, int $totalChunks, int $delayMinutes, int $chunkSize, int $totalNumbers, string $status, string $createdBy, string $createdAt): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_sms_batch_tracking 
            (batch_id, message, total_chunks, delay_minutes, chunk_size, total_numbers, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->execute($sql, [
            $batchId,
            $message,
            $totalChunks,
            $delayMinutes,
            $chunkSize,
            $totalNumbers,
            $status,
            $createdBy,
            $createdAt
        ]);
    }

    /**
     * Get batch tracking by batch ID
     */
    public function getBatchTrackingByBatchId(string $batchId): ?array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_sms_batch_tracking 
            WHERE batch_id = ?
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [$batchId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Update batch status
     */
    public function updateBatchStatus(string $batchId, string $status, ?int $successfulChunks = null, ?int $failedChunks = null, ?string $completedAt = null): bool
    {
        $setParts = ["status = ?"];
        $params = [$status];
        
        if ($successfulChunks !== null) {
            $setParts[] = "successful_chunks = ?";
            $params[] = $successfulChunks;
        }
        
        if ($failedChunks !== null) {
            $setParts[] = "failed_chunks = ?";
            $params[] = $failedChunks;
        }
        
        if ($completedAt !== null) {
            $setParts[] = "completed_at = ?";
            $params[] = $completedAt;
        } else {
            $setParts[] = "completed_at = NOW()";
        }
        
        $params[] = $batchId;
        
        $sql = "
            UPDATE wpk4_backend_sms_batch_tracking 
            SET " . implode(', ', $setParts) . "
            WHERE batch_id = ?
        ";
        
        return $this->execute($sql, $params);
    }

    /**
     * Insert chunk log
     */
    public function insertChunkLog(string $batchId, int $chunkNumber, string $mobileNumbers, int $numbersCount, ?string $scheduledTime, string $status, string $createdAt): int
    {
        $sql = "
            INSERT INTO wpk4_backend_sms_chunk_log 
            (batch_id, chunk_number, mobile_numbers, numbers_count, scheduled_time, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $this->execute($sql, [
            $batchId,
            $chunkNumber,
            $mobileNumbers,
            $numbersCount,
            $scheduledTime,
            $status,
            $createdAt
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Update chunk status
     */
    public function updateChunkStatus(string $batchId, int $chunkNumber, string $status, ?string $messageId = null, ?string $errorMessage = null, ?string $responseData = null, ?string $sentAt = null): bool
    {
        $setParts = ["status = ?"];
        $params = [$status];
        
        if ($messageId !== null) {
            $setParts[] = "message_id = ?";
            $params[] = $messageId;
        }
        
        if ($errorMessage !== null) {
            $setParts[] = "error_message = ?";
            $params[] = $errorMessage;
        }
        
        if ($responseData !== null) {
            $setParts[] = "response_data = ?";
            $params[] = $responseData;
        }
        
        if ($sentAt !== null) {
            $setParts[] = "sent_at = ?";
            $params[] = $sentAt;
        } else {
            $setParts[] = "sent_at = NOW()";
        }
        
        $params[] = $batchId;
        $params[] = $chunkNumber;
        
        $sql = "
            UPDATE wpk4_backend_sms_chunk_log 
            SET " . implode(', ', $setParts) . "
            WHERE batch_id = ? AND chunk_number = ?
        ";
        
        return $this->execute($sql, $params);
    }

    /**
     * Get chunk logs by batch ID
     */
    public function getChunkLogsByBatchId(string $batchId): array
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_sms_chunk_log 
            WHERE batch_id = ?
            ORDER BY chunk_number ASC
        ";
        
        return $this->query($sql, [$batchId]);
    }

    /**
     * Insert SMS history
     */
    public function insertSmsHistory(string $orderId, string $message, string $phone, string $source, string $messageId, string $addedOn, string $addedBy): int
    {
        $sql = "
            INSERT INTO wpk4_backend_order_sms_history 
            (order_id, message, phone, source, message_id, added_on, added_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $this->execute($sql, [
            $orderId,
            $message,
            $phone,
            $source,
            $messageId,
            $addedOn,
            $addedBy
        ]);
        
        return $this->lastInsertId();
    }
}

