<?php
/**
 * Soldout Updater Data Access Layer
 * Handles database operations for soldout status updates
 */

namespace App\DAL;

use Exception;
use PDOException;

class SoldoutUpdaterDAL extends BaseDAL
{
    /**
     * Get sold out trips (max_pax - booked < 1)
     */
    public function getSoldOutTrips($excludedPostIds = [60107, 60116])
    {
        try {
            // Build excluded post IDs string
            $excludedIds = implode(',', array_map('intval', $excludedPostIds));
            
            $query = "
                SELECT p.trip_id, wp.meta_value as booked, p.id, p.max_pax, d.start_date, 
                       p.max_pax - wp.meta_value as closed 
                FROM wpk4_postmeta wp 
                LEFT JOIN wpk4_wt_pricings p ON SUBSTRING_INDEX(SUBSTRING_INDEX(wp.meta_key,'pax-', -1),'-',1) = p.id
                LEFT JOIN wpk4_wt_dates d ON p.id = d.pricing_ids
                LEFT JOIN wpk4_posts wp2 ON p.trip_id = wp2.ID
                WHERE wp.meta_key LIKE 'wt_booked_pax%' 
                  AND wp.post_id NOT IN ($excludedIds)
                  AND wp2.post_status = 'publish' 
                  AND wp2.post_type = 'itineraries' 
                  AND (p.max_pax - wp.meta_value) < 1
                  AND d.start_date IS NOT NULL
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::getSoldOutTrips error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get trips with availability (max_pax - booked > 0)
     */
    public function getAvailableTrips($excludedPostIds = [60107, 60116])
    {
        try {
            // Build excluded post IDs string
            $excludedIds = implode(',', array_map('intval', $excludedPostIds));
            
            $query = "
                SELECT p.trip_id, wp.meta_value as booked, p.id, p.max_pax, d.start_date, 
                       p.max_pax - wp.meta_value as closed 
                FROM wpk4_postmeta wp 
                LEFT JOIN wpk4_wt_pricings p ON SUBSTRING_INDEX(SUBSTRING_INDEX(wp.meta_key,'pax-', -1),'-',1) = p.id
                LEFT JOIN wpk4_wt_dates d ON p.id = d.pricing_ids
                LEFT JOIN wpk4_posts wp2 ON p.trip_id = wp2.ID
                WHERE wp.meta_key LIKE 'wt_booked_pax%' 
                  AND wp.post_id NOT IN ($excludedIds)
                  AND wp2.post_status = 'publish' 
                  AND wp2.post_type = 'itineraries' 
                  AND (p.max_pax - wp.meta_value) > 0
                  AND d.start_date IS NOT NULL
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::getAvailableTrips error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get product title by trip_id and pricing_id
     */
    public function getProductTitle($tripId, $pricingId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_wt_dates 
                WHERE trip_id = :trip_id AND pricing_ids = :pricing_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'trip_id' => $tripId,
                'pricing_id' => $pricingId
            ]);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::getProductTitle error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check if trip is excluded
     */
    public function isExcluded($tripId, $startDate)
    {
        try {
            $query = "
                SELECT * FROM wpk4_wt_excluded_dates_times 
                WHERE trip_id = :trip_id AND start_date = :start_date
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'trip_id' => $tripId,
                'start_date' => $startDate
            ]);
            
            return !empty($result);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::isExcluded error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Add trip to exclude table
     */
    public function addToExclude($tripId, $title, $startDate)
    {
        try {
            $query = "
                INSERT INTO wpk4_wt_excluded_dates_times (trip_id, title, years, months, start_date) 
                VALUES (:trip_id, :title, 'every_year', 'every_month', :start_date)
            ";
            
            return $this->execute($query, [
                'trip_id' => $tripId,
                'title' => $title,
                'start_date' => $startDate
            ]);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::addToExclude error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Remove trip from exclude table
     */
    public function removeFromExclude($tripId, $startDate)
    {
        try {
            $query = "
                DELETE FROM wpk4_wt_excluded_dates_times 
                WHERE trip_id = :trip_id AND start_date = :start_date
            ";
            
            return $this->execute($query, [
                'trip_id' => $tripId,
                'start_date' => $startDate
            ]);
        } catch (PDOException $e) {
            error_log("SoldoutUpdaterDAL::removeFromExclude error: " . $e->getMessage());
            throw new Exception("Database delete failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Begin transaction (public wrapper)
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction (public wrapper)
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction (public wrapper)
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}

