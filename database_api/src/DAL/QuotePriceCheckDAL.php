<?php
/**
 * Quote Price Check Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class QuotePriceCheckDAL extends BaseDAL
{
    /**
     * Get recent active quotes
     */
    public function getRecentActiveQuotes($days = 2)
    {
        $query = "SELECT * FROM wpk4_quote
                  WHERE quoted_at >= NOW() - INTERVAL ? DAY
                    AND depart_date >= CURDATE()
                    AND status = 0
                  ORDER BY quoted_at DESC";
        
        return $this->query($query, [$days]);
    }

    /**
     * Get pending price check quotes
     */
    public function getPendingPriceCheckQuotes()
    {
        $query = "SELECT * FROM wpk4_quote
                  WHERE status = 0
                    AND depart_date >= CURDATE()
                  ORDER BY quoted_at DESC
                  LIMIT 100";
        
        return $this->query($query);
    }

    /**
     * Get quote by ID
     */
    public function getQuoteById($quoteId)
    {
        $query = "SELECT * FROM wpk4_quote WHERE id = ? LIMIT 1";
        return $this->queryOne($query, [$quoteId]);
    }

    /**
     * Update quote
     */
    public function updateQuote($quoteId, $data)
    {
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = "$field = ?";
            $params[] = $value;
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_quote SET $setSQL WHERE id = ?";
        $params[] = $quoteId;

        return $this->execute($query, $params);
    }
}

