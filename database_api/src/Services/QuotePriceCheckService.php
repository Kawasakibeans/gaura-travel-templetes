<?php
/**
 * Quote Price Check Service - Business Logic Layer
 * Handles automatic price checking for recent quotes
 */

namespace App\Services;

use App\DAL\QuotePriceCheckDAL;
use Exception;

class QuotePriceCheckService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new QuotePriceCheckDAL();
    }

    /**
     * Get recent quotes for price checking
     */
    public function getRecentQuotesForPriceCheck($days = 2)
    {
        $quotes = $this->dal->getRecentActiveQuotes($days);

        return [
            'quotes' => $quotes,
            'total_count' => count($quotes),
            'days_back' => $days,
            'message' => 'Recent quotes retrieved for price checking'
        ];
    }

    /**
     * Get quotes pending price check
     */
    public function getPendingPriceChecks()
    {
        $quotes = $this->dal->getPendingPriceCheckQuotes();

        return [
            'quotes' => $quotes,
            'total_count' => count($quotes)
        ];
    }

    /**
     * Mark quote as price checked
     */
    public function markQuoteAsChecked($quoteId, $newPrice = null)
    {
        if (empty($quoteId) || !is_numeric($quoteId)) {
            throw new Exception('Valid quote ID is required', 400);
        }

        $quote = $this->dal->getQuoteById($quoteId);
        if (!$quote) {
            throw new Exception('Quote not found', 404);
        }

        $updateData = ['status' => 1];
        if ($newPrice !== null) {
            $updateData['current_price'] = $newPrice;
            $updateData['price_checked_at'] = date('Y-m-d H:i:s');
        }

        $this->dal->updateQuote($quoteId, $updateData);

        return [
            'quote_id' => $quoteId,
            'old_price' => $quote['current_price'],
            'new_price' => $newPrice,
            'message' => 'Quote marked as price checked'
        ];
    }
}

