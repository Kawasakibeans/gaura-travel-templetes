<?php
/**
 * Product Update Data Access Layer
 * Handles database operations for updating products to stock product manager table
 */

namespace App\DAL;

use Exception;
use PDOException;

class ProductUpdateDAL extends BaseDAL
{
    /**
     * Find products not in stock product manager
     */
    public function findMissingProducts()
    {
        try {
            $query = "
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
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("ProductUpdateDAL::findMissingProducts error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product itinerary data
     */
    public function getProductItinerary($tripId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_postmeta 
                WHERE post_id = :trip_id AND meta_key = 'wp_travel_trip_itinerary_data'
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['trip_id' => $tripId]);
        } catch (PDOException $e) {
            error_log("ProductUpdateDAL::getProductItinerary error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product details
     */
    public function getProductDetails($tripId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_posts 
                WHERE ID = :trip_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['trip_id' => $tripId]);
        } catch (PDOException $e) {
            error_log("ProductUpdateDAL::getProductDetails error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert product to stock product manager
     */
    public function insertProductToStockManager($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_stock_product_manager
                (product_id, product_title, product_url, pricing_id, trip_code, travel_date, travel_time, itinerary, added_date)
                VALUES (:product_id, :product_title, :product_url, :pricing_id, :trip_code, :travel_date, '', :itinerary, :added_date)
            ";
            
            $this->execute($query, $data);
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("ProductUpdateDAL::insertProductToStockManager error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

