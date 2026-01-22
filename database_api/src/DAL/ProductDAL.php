<?php
/**
 * Product Data Access Layer (DAL)
 * 
 * Handles all database operations for product management
 */

namespace App\DAL;

class ProductDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get products available for insertion (not yet in stock_product_manager)
     */
    public function getAvailableProducts() {
        $sql = "
            SELECT DISTINCT 
                wp2.id,
                d.pricing_ids,
                d.end_date,
                wp.meta_value as trip_code
            FROM wpk4_wt_dates d
            LEFT JOIN wpk4_wt_pricings p 
                ON d.pricing_ids = p.id
            LEFT JOIN wpk4_postmeta wp 
                ON d.trip_id = wp.post_id AND wp.meta_key = 'wp_travel_trip_code'
            LEFT JOIN wpk4_posts wp2  
                ON d.trip_id = wp2.id
            LEFT JOIN wpk4_backend_stock_product_manager pm 
                ON d.pricing_ids = pm.pricing_id
            WHERE wp2.post_type = 'itineraries' 
                AND wp2.post_status = 'publish' 
                AND d.pricing_ids NOT IN (
                    SELECT wdpm.pricing_id 
                    FROM wpk4_backend_stock_product_manager wdpm
                )
                AND d.end_date != '0000-00-00'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get product details by trip ID
     */
    public function getProductDetails($tripId) {
        $sql = "
            SELECT * 
            FROM wpk4_posts 
            WHERE ID = :trip_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':trip_id' => $tripId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get product itinerary
     */
    public function getProductItinerary($tripId) {
        $sql = "
            SELECT * 
            FROM wpk4_postmeta 
            WHERE post_id = :trip_id 
                AND meta_key = 'wp_travel_trip_itinerary_data'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':trip_id' => $tripId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['meta_value'] : '';
    }

    /**
     * Insert product into stock_product_manager
     */
    public function insertProduct($data) {
        $sql = "
            INSERT INTO wpk4_backend_stock_product_manager
            (product_id, product_title, product_url, pricing_id, trip_code, travel_date, travel_time, itinerary, added_date)
            VALUES
            (:product_id, :product_title, :product_url, :pricing_id, :trip_code, :travel_date, :travel_time, :itinerary, NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':product_id' => $data['product_id'],
            ':product_title' => $data['product_title'],
            ':product_url' => $data['product_url'],
            ':pricing_id' => $data['pricing_id'],
            ':trip_code' => $data['trip_code'],
            ':travel_date' => $data['travel_date'],
            ':travel_time' => $data['travel_time'] ?? '',
            ':itinerary' => $data['itinerary'] ?? ''
        ]);
    }

    /**
     * Get products with filters
     */
    public function getProducts($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[] = "product_id = :product_id";
            $params[':product_id'] = $filters['product_id'];
        }

        if (!empty($filters['pricing_id'])) {
            $where[] = "pricing_id = :pricing_id";
            $params[':pricing_id'] = $filters['pricing_id'];
        }

        if (!empty($filters['trip_code'])) {
            $where[] = "trip_code = :trip_code";
            $params[':trip_code'] = $filters['trip_code'];
        }

        if (!empty($filters['travel_date'])) {
            $where[] = "DATE(travel_date) = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE auto_id != \'TEST_DMP_ID\'';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $limit = max(1, min(100, $limit)); // Clamp between 1 and 100

        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_product_manager
            $whereClause
            ORDER BY added_date DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get count of products matching filters
     */
    public function countProducts($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[] = "product_id = :product_id";
            $params[':product_id'] = $filters['product_id'];
        }

        if (!empty($filters['pricing_id'])) {
            $where[] = "pricing_id = :pricing_id";
            $params[':pricing_id'] = $filters['pricing_id'];
        }

        if (!empty($filters['trip_code'])) {
            $where[] = "trip_code = :trip_code";
            $params[':trip_code'] = $filters['trip_code'];
        }

        if (!empty($filters['travel_date'])) {
            $where[] = "DATE(travel_date) = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE auto_id != \'TEST_DMP_ID\'';

        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_backend_stock_product_manager
            $whereClause
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get product by ID (auto_id or product_id)
     */
    public function getProductById($id) {
        // Convert to integer if it's numeric
        $id = is_numeric($id) ? (int)$id : $id;
        $paramType = is_numeric($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
        
        // Try auto_id first (primary key)
        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_product_manager 
            WHERE auto_id = :id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, $paramType);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // If not found by auto_id, try product_id
        if (!$result) {
            $sql = "
                SELECT * 
                FROM wpk4_backend_stock_product_manager 
                WHERE product_id = :id
                LIMIT 1
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, $paramType);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        return $result;
    }

    /**
     * Update product
     */
    public function updateProduct($autoId, $data) {
        $sql = "
            UPDATE wpk4_backend_stock_product_manager 
            SET product_id = :product_id,
                product_title = :product_title,
                product_url = :product_url,
                pricing_id = :pricing_id,
                trip_code = :trip_code,
                travel_date = :travel_date,
                travel_time = :travel_time,
                itinerary = :itinerary
            WHERE auto_id = :auto_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':auto_id' => $autoId,
            ':product_id' => $data['product_id'],
            ':product_title' => $data['product_title'],
            ':product_url' => $data['product_url'],
            ':pricing_id' => $data['pricing_id'],
            ':trip_code' => $data['trip_code'],
            ':travel_date' => $data['travel_date'],
            ':travel_time' => $data['travel_time'] ?? '',
            ':itinerary' => $data['itinerary'] ?? ''
        ]);
    }

    /**
     * Delete product
     */
    public function deleteProduct($autoId) {
        $sql = "
            DELETE FROM wpk4_backend_stock_product_manager 
            WHERE auto_id = :auto_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':auto_id' => $autoId]);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
}

