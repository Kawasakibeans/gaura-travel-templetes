<?php
/**
 * Sale Price Data Access Layer (DAL)
 * 
 * Handles all database operations for sale price management
 */

namespace App\DAL;

class SalePriceDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get sale prices with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array of sale price records
     */
    public function getSalePrices($filters = []) {
        $where = [];
        $params = [];
        
        // Build WHERE clause based on filters
        if (!empty($filters['airline'])) {
            $where[] = "stock.airline_code LIKE :airline";
            $params[':airline'] = '%' . $filters['airline'] . '%';
        } else {
            $where[] = "pricing.id != 'DUMMYEMPTY'";
        }
        
        if (!empty($filters['route'])) {
            $where[] = "stock.route LIKE :route";
            $params[':route'] = $filters['route'];
        } else {
            if (empty($filters['airline'])) {
                $where = ['pricing.id != \'DUMMYEMPTY\''];
            } else {
                $where[] = "pricing.id != 'DUMMYEMPTY'";
            }
        }
        
        if (!empty($filters['travel_date_from']) && !empty($filters['travel_date_to'])) {
            $where[] = "DATE(product.travel_date) >= :travel_date_from";
            $where[] = "DATE(product.travel_date) <= :travel_date_to";
            $params[':travel_date_from'] = $filters['travel_date_from'];
            $params[':travel_date_to'] = $filters['travel_date_to'];
        } else {
            if (count($where) == 0 || (count($where) == 1 && $where[0] == "pricing.id != 'DUMMYEMPTY'")) {
                $where = ['pricing.id != \'DUMMYEMPTY\''];
            } else {
                $where[] = "pricing.id != 'DUMMYEMPTY'";
            }
        }
        
        if (!empty($filters['route_type'])) {
            $where[] = "stock.route_type = :route_type";
            $params[':route_type'] = $filters['route_type'];
        } else {
            if (count($where) == 0 || (count($where) == 1 && $where[0] == "pricing.id != 'DUMMYEMPTY'")) {
                $where = ['pricing.id != \'DUMMYEMPTY\''];
            } else {
                $where[] = "pricing.id != 'DUMMYEMPTY'";
            }
        }
        
        if (!empty($filters['flight_type'])) {
            if ($filters['flight_type'] == 'int') {
                $where[] = "CHAR_LENGTH(stock.pnr) < 7";
            } else if ($filters['flight_type'] == 'dom') {
                $where[] = "CHAR_LENGTH(stock.pnr) > 6";
            } else {
                $where[] = "CHAR_LENGTH(stock.pnr) > 0";
            }
        } else {
            if (count($where) == 0 || (count($where) == 1 && $where[0] == "pricing.id != 'DUMMYEMPTY'")) {
                $where = ['pricing.id != \'DUMMYEMPTY\''];
            } else {
                $where[] = "pricing.id != 'DUMMYEMPTY'";
            }
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? 50;
        
        $sql = "
            SELECT 
                pricing.id,
                pricing.trip_id,
                pricing.min_pax,
                pricing.max_pax,
                
                pricecategory.regular_price,
                pricecategory.sale_price,
                pricecategory.pricing_category_id,
                
                stock.pnr,
                stock.original_stock,
                stock.current_stock,
                stock.stock_release,
                stock.stock_unuse,
                stock.aud_fare,
                stock.airline_code,
                stock.route,
                stock.route_type,
                
                product.product_id,
                product.pricing_id,
                product.travel_date,
                product.trip_code
            
            FROM wpk4_wt_pricings as pricing 
                JOIN wpk4_backend_stock_product_manager as product ON  
                    pricing.id = product.pricing_id 
                    
                JOIN wpk4_wt_price_category_relation as pricecategory ON  
                    pricing.id = pricecategory.pricing_id 
                    
                JOIN wpk4_backend_stock_management_sheet as stock ON
                    product.trip_code = stock.trip_id AND product.travel_date = stock.dep_date
                    
            WHERE $whereClause
            
            ORDER BY stock.airline_code ASC, product.travel_date ASC
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct airlines from stock management sheet
     * 
     * @param int $limit Maximum number of results
     * @return array Array of distinct airlines
     */
    public function getAirlines($limit = 20) {
        $sql = "
            SELECT DISTINCT SUBSTRING(trip_id, 9, 2) AS airlines 
            FROM wpk4_backend_stock_management_sheet 
            ORDER BY airlines ASC 
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct routes from stock management sheet
     * 
     * @param int $limit Maximum number of results
     * @return array Array of distinct routes
     */
    public function getRoutes($limit = 60) {
        $sql = "
            SELECT DISTINCT SUBSTRING(trip_id, 1, 7) AS route 
            FROM wpk4_backend_stock_management_sheet 
            ORDER BY route ASC 
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get booking count for a specific trip and travel date
     * 
     * @param string $tripCode Trip code pattern
     * @param string $travelDate Travel date
     * @return int Total booked pax count
     */
    public function getBookingCount($tripCode, $travelDate) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE trip_code LIKE :trip_code 
            AND travel_date LIKE :travel_date 
            AND (payment_status = 'paid' OR payment_status = 'partially_paid')
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':trip_code', '%' . $tripCode . '%');
        $stmt->bindValue(':travel_date', $travelDate . '%');
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $totalPax = 0;
        
        foreach ($bookings as $booking) {
            $totalPax += (int)($booking['total_pax'] ?? 0);
        }
        
        return $totalPax;
    }

    /**
     * Update sale price for a pricing ID
     * 
     * @param int $pricingId Pricing ID
     * @param string $columnName Column name (e.g., 'sale_price')
     * @param float $newValue New sale price value
     * @return bool Success status
     */
    public function updateSalePrice($pricingId, $columnName, $newValue) {
        $sql = "
            UPDATE wpk4_wt_price_category_relation 
            SET $columnName = :new_value 
            WHERE pricing_id = :pricing_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':new_value', $newValue);
        $stmt->bindValue(':pricing_id', $pricingId, \PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get current sale price for a pricing ID
     * 
     * @param int $pricingId Pricing ID
     * @return array|null Pricing record or null
     */
    public function getSalePriceById($pricingId) {
        $sql = "
            SELECT 
                pricing.id,
                pricing.trip_id,
                pricing.min_pax,
                pricing.max_pax,
                
                pricecategory.regular_price,
                pricecategory.sale_price,
                pricecategory.pricing_category_id,
                
                stock.pnr,
                stock.original_stock,
                stock.current_stock,
                stock.stock_release,
                stock.stock_unuse,
                stock.aud_fare,
                stock.airline_code,
                stock.route,
                stock.route_type,
                
                product.product_id,
                product.pricing_id,
                product.travel_date,
                product.trip_code
            
            FROM wpk4_wt_pricings as pricing 
                JOIN wpk4_backend_stock_product_manager as product ON  
                    pricing.id = product.pricing_id 
                    
                JOIN wpk4_wt_price_category_relation as pricecategory ON  
                    pricing.id = pricecategory.pricing_id 
                    
                JOIN wpk4_backend_stock_management_sheet as stock ON
                    product.trip_code = stock.trip_id AND product.travel_date = stock.dep_date
                    
            WHERE pricing.id = :pricing_id
            
            ORDER BY stock.airline_code ASC, product.travel_date ASC
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pricing_id', $pricingId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }

    /**
     * Insert update history record
     * 
     * @param int $pricingId Pricing ID
     * @param string $metaKey Meta key
     * @param string $metaValue Meta value
     * @param string $updatedTime Updated time
     * @param string $updatedUser Updated user
     * @return bool Success status
     */
    public function insertUpdateHistory($pricingId, $metaKey, $metaValue, $updatedTime, $updatedUser) {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_update_history 
            (order_id, merging_id, pax_auto_id, meta_key, meta_value, updated_time, updated_user)
            VALUES (:order_id, '', '', :meta_key, :meta_value, :updated_time, :updated_user)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $pricingId, \PDO::PARAM_INT);
        $stmt->bindValue(':meta_key', $metaKey);
        $stmt->bindValue(':meta_value', $metaValue);
        $stmt->bindValue(':updated_time', $updatedTime);
        $stmt->bindValue(':updated_user', $updatedUser);
        
        return $stmt->execute();
    }
}

