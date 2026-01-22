<?php
/**
 * Quote Data Access Layer
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class QuoteDAL extends BaseDAL
{
    public function __construct($db = null)
    {
        // BaseDAL creates its own connection, so we ignore $db parameter
        // This allows compatibility with routes that pass $db
        parent::__construct();
    }

    /**
     * Get all quotes with filters
     */
    public function getAllQuotes($quoteId, $gdeals, $quotedFrom, $quotedTo, $departDate, $email, $phoneNum, $userId, $limit, $offset)
    {
        $whereParts = ["1=1"];
        $params = [];

        if ($quoteId) {
            $whereParts[] = "id = ?";
            $params[] = $quoteId;
        }

        if ($gdeals !== null) {
            $whereParts[] = "is_gdeals = ?";
            $params[] = $gdeals;
        }

        if ($quotedFrom) {
            $whereParts[] = "quoted_at >= ?";
            $params[] = $quotedFrom . ' 00:00:00';
        }

        if ($quotedTo) {
            $whereParts[] = "quoted_at <= ?";
            $params[] = $quotedTo . ' 23:59:59';
        }

        if ($departDate) {
            $whereParts[] = "depart_date = ?";
            $params[] = $departDate;
        }

        if ($email) {
            $whereParts[] = "email LIKE ?";
            $params[] = '%' . $email . '%';
        }

        if ($phoneNum) {
            $whereParts[] = "phone_num LIKE ?";
            $params[] = '%' . $phoneNum . '%';
        }

        if ($userId) {
            $whereParts[] = "user_id = ?";
            $params[] = $userId;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT * FROM wpk4_quote 
                  WHERE $whereSQL 
                  ORDER BY id DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get quotes count
     */
    public function getQuotesCount($quoteId, $gdeals, $quotedFrom, $quotedTo, $departDate, $email, $phoneNum, $userId)
    {
        $whereParts = ["1=1"];
        $params = [];

        if ($quoteId) {
            $whereParts[] = "id = ?";
            $params[] = $quoteId;
        }

        if ($gdeals !== null) {
            $whereParts[] = "is_gdeals = ?";
            $params[] = $gdeals;
        }

        if ($quotedFrom) {
            $whereParts[] = "quoted_at >= ?";
            $params[] = $quotedFrom . ' 00:00:00';
        }

        if ($quotedTo) {
            $whereParts[] = "quoted_at <= ?";
            $params[] = $quotedTo . ' 23:59:59';
        }

        if ($departDate) {
            $whereParts[] = "depart_date = ?";
            $params[] = $departDate;
        }

        if ($email) {
            $whereParts[] = "email LIKE ?";
            $params[] = '%' . $email . '%';
        }

        if ($phoneNum) {
            $whereParts[] = "phone_num LIKE ?";
            $params[] = '%' . $phoneNum . '%';
        }

        if ($userId) {
            $whereParts[] = "user_id = ?";
            $params[] = $userId;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT COUNT(*) as total FROM wpk4_quote WHERE $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get quote by ID
     */
    public function getQuoteById($id)
    {
        $query = "SELECT * FROM wpk4_quote WHERE id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get multicity info
     */
    public function getMulticityInfo($quoteId)
    {
        $query = "SELECT * FROM wpk4_quote_multicity WHERE id = ? LIMIT 1";
        return $this->queryOne($query, [$quoteId]);
    }

    /**
     * Get sub-quotes count (G360)
     */
    public function getSubQuotesCount($quoteId)
    {
        $query = "SELECT COUNT(*) as count FROM wpk4_quote_G360 WHERE original_quote_id = ?";
        $result = $this->queryOne($query, [$quoteId]);
        return (int)$result['count'];
    }

    /**
     * Get product availability
     */
    public function getProductAvailability($productId, $travelDate)
    {
        $categoryId = '953';
        
        $query = "SELECT wbspm.product_id, wbspm.trip_code, wbspm.product_title, 
                         wwpcr.regular_price as price, 
                         (wbmsa.stock - wbmsa.pax) as availability 
                  FROM wpk4_backend_stock_product_manager AS wbspm
                  JOIN wpk4_wt_price_category_relation AS wwpcr 
                    ON wbspm.pricing_id = wwpcr.pricing_id
                  JOIN wpk4_backend_manage_seat_availability AS wbmsa 
                    ON wbspm.pricing_id = wbmsa.pricing_id
                  WHERE wwpcr.pricing_category_id = ?
                    AND wbspm.product_id = ?
                    AND wbspm.travel_date LIKE ?";
        
        return $this->query($query, [$categoryId, $productId, '%' . $travelDate . '%']);
    }

    /**
     * Search users
     */
    public function searchUsers($query)
    {
        $searchQuery = "SELECT id, user_email, display_name 
                        FROM wpk4_users 
                        WHERE user_login LIKE ? OR user_email LIKE ? 
                        LIMIT 10";
        
        $searchPattern = '%' . $query . '%';
        return $this->query($searchQuery, [$searchPattern, $searchPattern]);
    }

    /**
     * Create quote
     */
    public function createQuote($data)
    {
        $query = "INSERT INTO wpk4_quote 
                  (email, phone_num, depart_date, return_date, depart_apt, dest_apt, 
                   current_price, name, user_id, status, quoted_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['email'],
            $data['phone_num'] ?? null,
            $data['depart_date'],
            $data['return_date'] ?? null,
            $data['depart_apt'] ?? null,
            $data['dest_apt'] ?? null,
            $data['current_price'] ?? null,
            $data['name'] ?? null,
            $data['user_id'] ?? null,
            $data['status'] ?? 'pending'
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update quote status
     */
    public function updateQuoteStatus($id, $status)
    {
        $query = "UPDATE wpk4_quote SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->execute($query, [$status, $id]);
    }

    /**
     * Get quote details with user and call record information
     */
    public function getQuoteDetailsWithJoins($quoteId)
    {
        $sql = "
            SELECT r.rec_duration as duration, r.rec_status as call_status, q.*, u.display_name 
            FROM wpk4_quote q 
            LEFT JOIN wpk4_users u ON q.user_id = u.ID
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON q.call_record_id = r.d_record_id
            WHERE q.id = ?
        ";
        
        return $this->queryOne($sql, [$quoteId]);
    }

    /**
     * Get subquotes (G360 quotes) by original quote ID
     */
    public function getSubQuotesByOriginalQuoteId($originalQuoteId)
    {
        $sql = "
            SELECT * 
            FROM wpk4_quote_G360 
            WHERE original_quote_id = ? 
            ORDER BY quoted_at DESC
        ";
        
        return $this->query($sql, [$originalQuoteId]);
    }

    /**
     * Get minimum price from subquotes
     */
    public function getMinPriceFromSubQuotes($originalQuoteId)
    {
        $sql = "
            SELECT MIN(current_price) as min_price 
            FROM wpk4_quote_G360 
            WHERE original_quote_id = ?
        ";
        
        $result = $this->queryOne($sql, [$originalQuoteId]);
        return $result ? (float)$result['min_price'] : null;
    }

    /**
     * Get multicity quotes with filters
     */
    public function getMulticityQuotes($multiQuoteId, $gdeals, $quotedFrom, $quotedTo, $email, $phoneNum, $userId, $limit)
    {
        $whereParts = ["1=1"];
        $params = [];

        if ($multiQuoteId) {
            $whereParts[] = "id = ?";
            $params[] = $multiQuoteId;
        }

        if ($gdeals !== null) {
            $whereParts[] = "is_gdeals = ?";
            $params[] = $gdeals;
        }

        if ($quotedFrom) {
            $whereParts[] = "quoted_at >= ?";
            $params[] = $quotedFrom . ' 00:00:00';
        }

        if ($quotedTo) {
            $whereParts[] = "quoted_at <= ?";
            $params[] = $quotedTo . ' 23:59:59';
        }

        if ($email) {
            $whereParts[] = "email LIKE ?";
            $params[] = '%' . $email . '%';
        }

        if ($phoneNum) {
            $whereParts[] = "phone_num LIKE ?";
            $params[] = '%' . $phoneNum . '%';
        }

        if ($userId) {
            $whereParts[] = "user_id = ?";
            $params[] = $userId;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $limit = $limit ?: 100;
        
        $query = "
            SELECT q.*, u.display_name 
            FROM wpk4_quote_multicity q
            LEFT JOIN wpk4_users u ON q.user_id = u.ID
            WHERE $whereSQL 
            ORDER BY q.quoted_at DESC 
            LIMIT ?
        ";
        
        $params[] = $limit;

        return $this->query($query, $params);
    }

    /**
     * Get multicity quotes count
     */
    public function getMulticityQuotesCount($multiQuoteId, $gdeals, $quotedFrom, $quotedTo, $email, $phoneNum, $userId)
    {
        $whereParts = ["1=1"];
        $params = [];

        if ($multiQuoteId) {
            $whereParts[] = "id = ?";
            $params[] = $multiQuoteId;
        }

        if ($gdeals !== null) {
            $whereParts[] = "is_gdeals = ?";
            $params[] = $gdeals;
        }

        if ($quotedFrom) {
            $whereParts[] = "quoted_at >= ?";
            $params[] = $quotedFrom . ' 00:00:00';
        }

        if ($quotedTo) {
            $whereParts[] = "quoted_at <= ?";
            $params[] = $quotedTo . ' 23:59:59';
        }

        if ($email) {
            $whereParts[] = "email LIKE ?";
            $params[] = '%' . $email . '%';
        }

        if ($phoneNum) {
            $whereParts[] = "phone_num LIKE ?";
            $params[] = '%' . $phoneNum . '%';
        }

        if ($userId) {
            $whereParts[] = "user_id = ?";
            $params[] = $userId;
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT COUNT(*) as total FROM wpk4_quote_multicity WHERE $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }
}

