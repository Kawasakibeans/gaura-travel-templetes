<?php
/**
 * Pricing Data Access Layer
 * Handles database operations for pricing, category relation, and dates import
 */

namespace App\DAL;

use Exception;
use PDOException;

class PricingDAL extends BaseDAL
{
    /**
     * Check if pricing exists by id
     */
    public function checkPricingExists($pricingId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_wt_pricings 
                WHERE id = :pricing_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['pricing_id' => $pricingId]);
        } catch (PDOException $e) {
            error_log("PricingDAL::checkPricingExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert new pricing record
     */
    public function insertPricing($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_wt_pricings (id, title, min_pax, max_pax, trip_extras) 
                VALUES (:id, :title, :min_pax, :max_pax, :trip_extras)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::insertPricing error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update existing pricing record
     */
    public function updatePricing($data)
    {
        try {
            $query = "
                UPDATE wpk4_wt_pricings SET 
                    title = :title,
                    min_pax = :min_pax,
                    max_pax = :max_pax,
                    trip_extras = :trip_extras
                WHERE id = :id
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::updatePricing error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check if category relation exists
     */
    public function checkCategoryRelationExists($pricingId, $pricingCategoryId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_wt_price_category_relation 
                WHERE pricing_id = :pricing_id 
                  AND pricing_category_id = :pricing_category_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'pricing_id' => $pricingId,
                'pricing_category_id' => $pricingCategoryId
            ]);
        } catch (PDOException $e) {
            error_log("PricingDAL::checkCategoryRelationExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert new category relation record
     */
    public function insertCategoryRelation($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_wt_price_category_relation 
                (pricing_id, pricing_category_id, regular_price, sale_price, is_sale) 
                VALUES (:pricing_id, :pricing_category_id, :regular_price, :sale_price, :is_sale)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::insertCategoryRelation error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update existing category relation record
     */
    public function updateCategoryRelation($data)
    {
        try {
            $query = "
                UPDATE wpk4_wt_price_category_relation SET 
                    pricing_id = :pricing_id,
                    pricing_category_id = :pricing_category_id,
                    regular_price = :regular_price,
                    is_sale = :is_sale,
                    sale_price = :sale_price
                WHERE id = :id
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::updateCategoryRelation error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get trip code and travel date from product by pricing_id
     */
    public function getProductInfoByPricingId($pricingId)
    {
        try {
            $query = "
                SELECT trip_code, DATE(travel_date) as travel_dated 
                FROM wpk4_backend_stock_product_manager 
                WHERE pricing_id = :pricing_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['pricing_id' => $pricingId]);
        } catch (PDOException $e) {
            error_log("PricingDAL::getProductInfoByPricingId error: " . $e->getMessage());
            // Return null if not found, don't throw exception
            return null;
        }
    }

    /**
     * Get sales price from stock management sheet
     */
    public function getStockPrice($tripCode, $travelDate)
    {
        try {
            $query = "
                SELECT aud_fare 
                FROM wpk4_backend_stock_management_sheet 
                WHERE trip_id = :trip_code 
                  AND DATE(dep_date) = :travel_date
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'trip_code' => $tripCode,
                'travel_date' => $travelDate
            ]);
            
            return $result ? ($result['aud_fare'] ?? 0) : 0;
        } catch (PDOException $e) {
            error_log("PricingDAL::getStockPrice error: " . $e->getMessage());
            // Return 0 if not found, don't throw exception
            return 0;
        }
    }

    /**
     * Check if date exists by pricing_ids
     */
    public function checkDateExists($pricingIds)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_wt_dates 
                WHERE pricing_ids = :pricing_ids
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['pricing_ids' => $pricingIds]);
        } catch (PDOException $e) {
            error_log("PricingDAL::checkDateExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert new date record
     */
    public function insertDate($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_wt_dates 
                (title, recurring, years, months, date_days, start_date, end_date, pricing_ids) 
                VALUES 
                (:title, :recurring, :years, :months, :date_days, :start_date, :end_date, :pricing_ids)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::insertDate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update existing date record
     */
    public function updateDate($data)
    {
        try {
            $query = "
                UPDATE wpk4_wt_dates SET 
                    title = :title,
                    recurring = :recurring,
                    years = :years,
                    months = :months,
                    date_days = :date_days,
                    start_date = :start_date,
                    end_date = :end_date
                WHERE pricing_ids = :pricing_ids
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("PricingDAL::updateDate error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert history update record
     */
    public function insertHistoryUpdate($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_history_of_updates 
                (type_id, meta_key, meta_value, updated_by, updated_on) 
                VALUES 
                (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
            ";
            
            return $this->execute($query, [
                'type_id' => $typeId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'updated_by' => $updatedBy,
                'updated_on' => $updatedOn
            ]);
        } catch (PDOException $e) {
            error_log("PricingDAL::insertHistoryUpdate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

