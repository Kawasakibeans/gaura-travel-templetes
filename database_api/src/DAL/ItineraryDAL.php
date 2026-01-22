<?php
/**
 * Itinerary Data Access Layer
 * Handles database operations for itinerary import
 */

namespace App\DAL;

use Exception;
use PDOException;

class ItineraryDAL extends BaseDAL
{
    /**
     * Check if itinerary exists by trip_code (for preview - uses itinerary_table)
     */
    public function checkItineraryExistsPreview($tripCode)
    {
        try {
            $query = "
                SELECT * 
                FROM itinerary_table 
                WHERE BINARY trip_code = :trip_code
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['trip_code' => $tripCode]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::checkItineraryExistsPreview error: " . $e->getMessage());
            // Return null if table doesn't exist
            return null;
        }
    }

    /**
     * Check if itinerary exists by trip_code (for import - uses wpk4_backend_itinerary_table)
     */
    public function checkItineraryExists($tripCode)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_itinerary_table 
                WHERE BINARY trip_code = :trip_code
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['trip_code' => $tripCode]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::checkItineraryExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get itinerary by trip_code and travel_month
     */
    public function getItineraryByTripCodeAndMonth($tripCode, $travelMonth)
    {
        try {
            $query = "
                SELECT *
                FROM wpk4_backend_itinerary_table
                WHERE BINARY trip_code = :trip_code 
                  AND BINARY travel_month = :travel_month
            ";
            
            return $this->query($query, [
                'trip_code' => $tripCode,
                'travel_month' => $travelMonth
            ]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::getItineraryByTripCodeAndMonth error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert new itinerary record
     */
    public function insertItinerary($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_itinerary_table
                (trip_code, travel_month, _yoast_wpseo_metadesc, wp_travel_trip_itinerary_data, wp_travel_outline, 
                _yoast_wpseo_primary_itinerary_types, _yoast_wpseo_primary_travel_locations, _yoast_wpseo_primary_origin, 
                _yoast_wpseo_primary_airline, _yoast_wpseo_primary_ticket_type, _yoast_wpseo_primary_flight_type, 
                _yoast_wpseo_primary_status, _yoast_wpseo_primary_month, trip_extra) 
                VALUES 
                (:trip_code, :travel_month, :meta_desc, :itinerary_data, :outline, 
                :itinerary_types, :travel_locations, :origin, :airline, :ticket_type, 
                :flight_type, :status, :month, :trip_extra)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::insertItinerary error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update existing itinerary record
     */
    public function updateItinerary($data)
    {
        try {
            $query = "
                UPDATE wpk4_backend_itinerary_table SET 
                    travel_month = :travel_month, 
                    _yoast_wpseo_metadesc = :meta_desc, 
                    wp_travel_trip_itinerary_data = :itinerary_data, 
                    wp_travel_outline = :outline, 
                    _yoast_wpseo_primary_itinerary_types = :itinerary_types,
                    _yoast_wpseo_primary_travel_locations = :travel_locations,
                    _yoast_wpseo_primary_origin = :origin,
                    _yoast_wpseo_primary_airline = :airline,
                    _yoast_wpseo_primary_ticket_type = :ticket_type,
                    _yoast_wpseo_primary_flight_type = :flight_type,
                    _yoast_wpseo_primary_status = :status,
                    _yoast_wpseo_primary_month = :month,
                    trip_extra = :trip_extra
                WHERE BINARY trip_code = :trip_code
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::updateItinerary error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check if postmeta exists
     */
    public function checkPostMetaExists($productId, $metaKey)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_postmeta 
                WHERE post_id = :product_id 
                  AND meta_key = :meta_key
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'product_id' => $productId,
                'meta_key' => $metaKey
            ]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::checkPostMetaExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update postmeta
     */
    public function updatePostMeta($productId, $metaKey, $metaValue)
    {
        try {
            $query = "
                UPDATE wpk4_postmeta 
                SET meta_value = :meta_value 
                WHERE post_id = :product_id 
                  AND meta_key = :meta_key
            ";
            
            return $this->execute($query, [
                'product_id' => $productId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue
            ]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::updatePostMeta error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert postmeta
     */
    public function insertPostMeta($productId, $metaKey, $metaValue)
    {
        try {
            $query = "
                INSERT INTO wpk4_postmeta (post_id, meta_key, meta_value) 
                VALUES (:product_id, :meta_key, :meta_value)
            ";
            
            return $this->execute($query, [
                'product_id' => $productId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue
            ]);
        } catch (PDOException $e) {
            error_log("ItineraryDAL::insertPostMeta error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

