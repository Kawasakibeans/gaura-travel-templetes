<?php
/**
 * Quote Service - Business Logic Layer
 * Handles flight quote management and operations
 */

namespace App\Services;

use App\DAL\QuoteDAL;
use Exception;

class QuoteService
{
    private $dal;

    public function __construct($dal = null)
    {
        $this->dal = $dal ?? new QuoteDAL();
    }

    /**
     * Get all quotes with filters
     */
    public function getAllQuotes($filters = [])
    {
        $quoteId = $filters['quote_id'] ?? null;
        $gdeals = $filters['gdeals'] ?? null;
        $quotedFrom = $filters['quoted_from'] ?? null;
        $quotedTo = $filters['quoted_to'] ?? null;
        $departDate = $filters['depart_date'] ?? null;
        $email = $filters['email'] ?? null;
        $phoneNum = $filters['phone_num'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $quotes = $this->dal->getAllQuotes(
            $quoteId, $gdeals, $quotedFrom, $quotedTo, $departDate, 
            $email, $phoneNum, $userId, $limit, $offset
        );

        $totalCount = $this->dal->getQuotesCount(
            $quoteId, $gdeals, $quotedFrom, $quotedTo, $departDate, 
            $email, $phoneNum, $userId
        );

        return [
            'quotes' => $quotes,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get quote by ID
     */
    public function getQuoteById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $quote = $this->dal->getQuoteById($id);

        if (!$quote) {
            throw new Exception('Quote not found', 404);
        }

        // Get multicity info if applicable
        $multicityInfo = $this->dal->getMulticityInfo($id);
        if ($multicityInfo) {
            $quote['multicity'] = $multicityInfo;
        }

        // Get sub-quotes count (G360)
        $subQuotesCount = $this->dal->getSubQuotesCount($id);
        $quote['sub_quotes_count'] = $subQuotesCount;

        return $quote;
    }

    /**
     * Get product availability
     */
    public function getProductAvailability($productId, $travelDate)
    {
        if (empty($productId) || empty($travelDate)) {
            throw new Exception('product_id and travel_date are required', 400);
        }

        $availability = $this->dal->getProductAvailability($productId, $travelDate);

        if (empty($availability)) {
            return [
                'product_id' => $productId,
                'travel_date' => $travelDate,
                'itinerary' => [],
                'message' => 'No availability found'
            ];
        }

        return [
            'product_id' => $productId,
            'travel_date' => $travelDate,
            'itinerary' => $availability
        ];
    }

    /**
     * Search users
     */
    public function searchUsers($query)
    {
        if (empty($query)) {
            throw new Exception('Search query is required', 400);
        }

        $users = $this->dal->searchUsers($query);

        return [
            'query' => $query,
            'users' => $users,
            'total_count' => count($users)
        ];
    }

    /**
     * Create quote
     */
    public function createQuote($data)
    {
        $requiredFields = ['email', 'depart_date'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $quoteId = $this->dal->createQuote($data);

        return [
            'quote_id' => $quoteId,
            'message' => 'Quote created successfully'
        ];
    }

    /**
     * Update quote status
     */
    public function updateQuoteStatus($id, $status)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $quote = $this->dal->getQuoteById($id);
        if (!$quote) {
            throw new Exception('Quote not found', 404);
        }

        $this->dal->updateQuoteStatus($id, $status);

        return [
            'quote_id' => $id,
            'status' => $status,
            'message' => 'Quote status updated successfully'
        ];
    }

    /**
     * Get quote details with user and call record information
     */
    public function getQuoteDetails($quoteId)
    {
        if (empty($quoteId) || !is_numeric($quoteId)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $quote = $this->dal->getQuoteDetailsWithJoins($quoteId);
        
        if (!$quote) {
            throw new Exception('Quote not found', 404);
        }

        return $quote;
    }

    /**
     * Get subquotes (G360 quotes) by original quote ID
     */
    public function getSubQuotes($originalQuoteId)
    {
        if (empty($originalQuoteId) || !is_numeric($originalQuoteId)) {
            throw new Exception('Valid original quote ID is required', 400);
        }

        $subquotes = $this->dal->getSubQuotesByOriginalQuoteId($originalQuoteId);
        $minPrice = $this->dal->getMinPriceFromSubQuotes($originalQuoteId);

        return [
            'original_quote_id' => $originalQuoteId,
            'subquotes' => $subquotes,
            'count' => count($subquotes),
            'min_price' => $minPrice
        ];
    }

    /**
     * Get G360 subquotes by quote ID (alias for getSubQuotes, returns subquotes array)
     */
    public function getG360Subquotes($quoteId)
    {
        if (empty($quoteId) || !is_numeric($quoteId)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $subquotes = $this->dal->getSubQuotesByOriginalQuoteId($quoteId);
        
        return $subquotes;
    }

    /**
     * Get complete quote details including subquotes
     */
    public function getCompleteQuoteDetails($quoteId)
    {
        if (empty($quoteId) || !is_numeric($quoteId)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $quote = $this->dal->getQuoteDetailsWithJoins($quoteId);
        
        if (!$quote) {
            throw new Exception('Quote not found', 404);
        }

        $subquotes = $this->dal->getSubQuotesByOriginalQuoteId($quoteId);
        $minPrice = $this->dal->getMinPriceFromSubQuotes($quoteId);

        return [
            'quote' => $quote,
            'subquotes' => $subquotes,
            'subquotes_count' => count($subquotes),
            'min_price' => $minPrice
        ];
    }

    /**
     * Get multicity quotes with filters
     */
    public function getMulticityQuotes($filters = [])
    {
        $multiQuoteId = $filters['multi_quote_id'] ?? null;
        $gdeals = isset($filters['gdeals']) ? (int)$filters['gdeals'] : null;
        $quotedFrom = $filters['from'] ?? null;
        $quotedTo = $filters['to'] ?? null;
        $email = $filters['email'] ?? null;
        $phoneNum = $filters['phone_num'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);

        $quotes = $this->dal->getMulticityQuotes(
            $multiQuoteId, $gdeals, $quotedFrom, $quotedTo, 
            $email, $phoneNum, $userId, $limit
        );

        $totalCount = $this->dal->getMulticityQuotesCount(
            $multiQuoteId, $gdeals, $quotedFrom, $quotedTo, 
            $email, $phoneNum, $userId
        );

        return [
            'quotes' => $quotes,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }
}

