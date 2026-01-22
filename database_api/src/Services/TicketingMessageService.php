<?php
/**
 * Ticketing Message Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TicketingMessageDAL;
use Exception;

class TicketingMessageService
{
    private $ticketingMessageDAL;

    public function __construct()
    {
        $this->ticketingMessageDAL = new TicketingMessageDAL();
    }

    /**
     * Sanitize textarea lines
     */
    private function sanitizeTextareaLines($text)
    {
        $lines = preg_split("/\r\n|\n|\r/", (string)$text);
        $clean = [];
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '') {
                $clean[] = $l;
            }
        }
        return $clean;
    }

    /**
     * Parse line into date and message
     */
    private function parseLineIntoDateMessage($line)
    {
        $line = trim($line);

        // Pattern 1: dd/mm/yyyy <space> rest
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})\s+(.*)$#', $line, $m)) {
            $d = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); // Y-m-d
            return [$d, trim($m[4])];
        }
        // Pattern 2: yyyy-mm-dd <space> rest
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})\s+(.*)$#', $line, $m)) {
            $d = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]); // Y-m-d
            return [$d, trim($m[4])];
        }
        return ['', $line];
    }

    /**
     * Normalize date (accept dd/mm/yyyy or yyyy-mm-dd)
     */
    private function normalizeDate($dateUi)
    {
        $dateUi = trim($dateUi);
        if ($dateUi === '') {
            return '';
        }
        
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $dateUi)) {
            [$dd, $mm, $yy] = explode('/', $dateUi);
            return sprintf('%04d-%02d-%02d', $yy, $mm, $dd);
        }
        
        // Assume yyyy-mm-dd
        return $dateUi;
    }

    /**
     * List messages
     */
    public function listMessages($limit = 500, $status = 'active')
    {
        $messages = $this->ticketingMessageDAL->listMessages($limit, $status);
        
        return [
            'success' => true,
            'messages' => $messages,
            'count' => count($messages)
        ];
    }

    /**
     * Get message by ID
     */
    public function getMessageById($autoId)
    {
        $message = $this->ticketingMessageDAL->getMessageById($autoId);
        
        if (!$message) {
            throw new Exception('Message not found', 404);
        }
        
        return [
            'success' => true,
            'message' => $message
        ];
    }

    /**
     * Create messages (supports bulk)
     */
    public function createMessages($data, $username)
    {
        $useIndividualDates = isset($data['use_individual_dates']) && $data['use_individual_dates'];
        $commonDateUi = trim($data['common_date'] ?? '');
        $updatedBy = trim($data['updated_by'] ?? $username);
        $type = trim($data['type'] ?? '');
        $now = date('Y-m-d H:i:s');

        // Normalize common date
        $commonDate = $this->normalizeDate($commonDateUi);

        // Get messages - can be array or text lines
        $rawMessages = $data['messages'] ?? '';
        if (is_array($rawMessages)) {
            $lines = $rawMessages;
        } else {
            $lines = $this->sanitizeTextareaLines($rawMessages);
        }

        if (empty($lines)) {
            throw new Exception('No messages to add', 400);
        }

        $added = 0;
        foreach ($lines as $line) {
            $dateVal = $commonDate;
            $msgVal = $line;

            if ($useIndividualDates) {
                // Parse date from line
                [$parsedDate, $parsedMsg] = $this->parseLineIntoDateMessage($line);
                if ($parsedDate !== '') {
                    $dateVal = $parsedDate;
                    $msgVal = $parsedMsg;
                } elseif ($commonDate !== '') {
                    $dateVal = $commonDate;
                } else {
                    // Skip if no date found
                    continue;
                }
            } else {
                // Using common date - must be provided
                if ($dateVal === '') {
                    continue;
                }
                // Strip any leading date from message
                [$pdate, $pmsg] = $this->parseLineIntoDateMessage($line);
                if ($pdate !== '' && $pmsg !== '') {
                    $msgVal = $pmsg;
                }
            }

            try {
                $this->ticketingMessageDAL->createMessage(
                    $dateVal,
                    $msgVal,
                    $now,
                    $updatedBy,
                    $type,
                    'active'
                );
                $added++;
            } catch (Exception $e) {
                error_log("Failed to create message: " . $e->getMessage());
                // Continue with next message
            }
        }

        if ($added === 0) {
            throw new Exception('Nothing added (check dates/messages)', 400);
        }

        return [
            'success' => true,
            'message' => "Added $added message(s).",
            'added_count' => $added
        ];
    }

    /**
     * Update message (with logging)
     */
    public function updateMessage($autoId, $data, $username)
    {
        $dateUi = trim($data['date'] ?? '');
        $message = trim($data['message'] ?? '');
        $typeIn = trim($data['type'] ?? '');
        $updatedBy = trim($data['updated_by'] ?? $username);
        $now = date('Y-m-d H:i:s');

        if (empty($message)) {
            throw new Exception('Message is required', 400);
        }

        // Normalize date
        $dateVal = $this->normalizeDate($dateUi);
        if ($dateVal === '') {
            throw new Exception('Invalid date', 400);
        }

        // Get original message
        $orig = $this->ticketingMessageDAL->getOriginalMessage($autoId);
        if (!$orig) {
            throw new Exception("Row not found for #$autoId", 404);
        }

        // Use provided type or keep original
        $typeVal = ($typeIn !== '') ? $typeIn : ($orig['type'] ?? '');

        // Begin transaction (using PDO transaction)
        $this->ticketingMessageDAL->beginTransaction();

        try {
            // Log original
            $this->ticketingMessageDAL->logMessage(
                $autoId,
                $orig['date'],
                $orig['message'],
                $orig['updated_on'],
                $orig['updated_by'],
                $orig['type'] ?? '',
                $orig['status'] ?? 'active',
                $username
            );

            // Update main row
            $this->ticketingMessageDAL->updateMessage(
                $autoId,
                $dateVal,
                $message,
                $typeVal,
                $updatedBy,
                $now
            );

            $this->ticketingMessageDAL->commit();

            return [
                'success' => true,
                'message' => "Row #$autoId updated (original saved to log)."
            ];
        } catch (Exception $e) {
            $this->ticketingMessageDAL->rollback();
            throw $e;
        }
    }

    /**
     * Delete message (soft delete with logging)
     */
    public function deleteMessage($autoId, $username)
    {
        // Get original message
        $row = $this->ticketingMessageDAL->getOriginalMessage($autoId);
        if (!$row) {
            throw new Exception("Row not found for #$autoId", 404);
        }

        // Begin transaction
        $this->ticketingMessageDAL->beginTransaction();

        try {
            // Log original with status 'deleted'
            $this->ticketingMessageDAL->logMessage(
                $autoId,
                $row['date'],
                $row['message'],
                $row['updated_on'],
                $row['updated_by'],
                $row['type'] ?? '',
                'deleted',
                $username
            );

            // Soft delete
            $this->ticketingMessageDAL->softDeleteMessage($autoId, 'deleted');

            $this->ticketingMessageDAL->commit();

            return [
                'success' => true,
                'message' => "Row #$autoId updated (soft-deleted) and logged."
            ];
        } catch (Exception $e) {
            $this->ticketingMessageDAL->rollback();
            throw $e;
        }
    }

    /**
     * Bulk update date
     */
    public function bulkUpdateDate($data, $username)
    {
        $bulkDateUi = trim($data['date'] ?? '');
        $updatedBy = trim($data['updated_by'] ?? $username);
        $now = date('Y-m-d H:i:s');

        // Normalize date
        $bulkDate = $this->normalizeDate($bulkDateUi);
        if ($bulkDate === '') {
            throw new Exception('Please provide a valid date for bulk update', 400);
        }

        $affectedRows = $this->ticketingMessageDAL->bulkUpdateDate($bulkDate, $now, $updatedBy);

        return [
            'success' => true,
            'message' => "Bulk date update complete. Updated $affectedRows row(s).",
            'affected_rows' => $affectedRows
        ];
    }
}

