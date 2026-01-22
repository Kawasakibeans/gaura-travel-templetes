<?php
/**
 * Matched Ticket Service - Business Logic Layer
 * Handles business logic for ticket reconciliation
 */

namespace App\Services;

use App\DAL\MatchedTicketDAL;
use Exception;

class MatchedTicketService
{
    private $matchedTicketDAL;

    public function __construct()
    {
        $this->matchedTicketDAL = new MatchedTicketDAL();
    }

    /**
     * Get matched tickets with filters
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string|null $vendor Vendor filter (optional)
     * @return array Formatted data with matched/unmatched tickets
     */
    public function getMatchedTickets($fromDate, $toDate, $vendor = null)
    {
        // Validate dates
        if (!$this->validateDate($fromDate) || !$this->validateDate($toDate)) {
            throw new Exception("Invalid date format. Received: from_date='{$fromDate}', to_date='{$toDate}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)", 400);
        }

        if (strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        $results = $this->matchedTicketDAL->getMatchedTickets($fromDate, $toDate, $vendor);
        
        $matchedCount = 0;
        $unmatchedCount = 0;
        
        foreach ($results as $result) {
            if ($result['matched']) {
                $matchedCount++;
            } else {
                $unmatchedCount++;
            }
        }

        return [
            'success' => true,
            'date_range' => [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ],
            'vendor' => $vendor ?: 'all',
            'total_records' => count($results),
            'matched_count' => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'data' => $results,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]
        ];
    }

    /**
     * Insert matched tickets into reconciliation
     * 
     * @param array $documents Array of document numbers
     * @return array Insert result
     */
    public function insertMatchedTickets($documents)
    {
        if (empty($documents)) {
            throw new Exception('Documents array is required', 400);
        }

        if (!is_array($documents)) {
            throw new Exception('Documents must be an array', 400);
        }

        // Limit to 1000 documents per request
        if (count($documents) > 1000) {
            throw new Exception('Maximum 1000 documents allowed per request', 400);
        }

        $inserted = $this->matchedTicketDAL->insertMatchedTickets($documents);
        
        return [
            'success' => true,
            'total_documents' => count($documents),
            'inserted' => $inserted,
            'skipped' => count($documents) - $inserted
        ];
    }

    /**
     * Update order amounts
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param int|null $paxId Optional pax_id filter
     * @return array Update result
     */
    public function updateOrderAmounts($startDate, $endDate, $paxId = null)
    {
        // Validate dates
        if (!$this->validateDate($startDate) || !$this->validateDate($endDate)) {
            throw new Exception("Invalid date format. Received: start_date='{$startDate}', end_date='{$endDate}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)", 400);
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        $updated = $this->matchedTicketDAL->updateOrderAmounts($startDate, $endDate, $paxId);
        
        return [
            'success' => true,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'pax_id' => $paxId,
            'updated' => $updated
        ];
    }

    /**
     * Get ticket reconciliation records
     * 
     * @param string|null $fromDate Start date (Y-m-d format, optional)
     * @param string|null $toDate End date (Y-m-d format, optional)
     * @param string|null $vendor Vendor filter (optional)
     * @param int $page Page number (default 1)
     * @param int $perPage Records per page (default 100)
     * @return array Paginated reconciliation records
     */
    public function getTicketReconciliation($fromDate = null, $toDate = null, $vendor = null, $page = 1, $perPage = 100)
    {
        if ($fromDate !== null && !$this->validateDate($fromDate)) {
            throw new Exception("Invalid from_date format. Received: '{$fromDate}'. Expected format: YYYY-MM-DD", 400);
        }
        if ($toDate !== null && !$this->validateDate($toDate)) {
            throw new Exception("Invalid to_date format. Received: '{$toDate}'. Expected format: YYYY-MM-DD", 400);
        }

        if ($fromDate !== null && $toDate !== null && strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception('Start date must be before or equal to end date', 400);
        }

        $page = max(1, (int)$page);
        $perPage = max(1, min(1000, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $records = $this->matchedTicketDAL->getTicketReconciliation($fromDate, $toDate, $vendor, $perPage, $offset);
        
        return [
            'success' => true,
            'page' => $page,
            'per_page' => $perPage,
            'data' => $records,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'vendor' => $vendor ?: 'all'
            ]
        ];
    }

    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Expected format
     * @return bool True if valid, false otherwise
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

