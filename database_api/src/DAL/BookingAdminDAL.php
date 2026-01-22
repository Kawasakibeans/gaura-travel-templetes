<?php
/**
 * Booking Admin Data Access Layer
 * Handles database operations for booking admin views
 */

namespace App\DAL;

use Exception;
use PDOException;

class BookingAdminDAL extends BaseDAL
{
    /**
     * Get bookings by booking date
     */
    public function getBookingsByDate($bookingDate)
    {
        try {
            $query = "
                SELECT p.id as ID, pm2.meta_value as travel_date
                FROM wpk4_posts p 
                LEFT JOIN wpk4_postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'wp_travel_arrival_date' 
                WHERE p.post_type = 'itinerary-booking' 
                  AND date(p.post_date) = :booking_date
            ";
            
            return $this->query($query, [
                'booking_date' => $bookingDate
            ]);
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getBookingsByDate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking postmeta by booking ID and meta key
     */
    public function getBookingMeta($bookingId, $metaKey)
    {
        try {
            $query = "
                SELECT meta_value 
                FROM wpk4_postmeta 
                WHERE post_id = :booking_id AND meta_key = :meta_key
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'booking_id' => $bookingId,
                'meta_key' => $metaKey
            ]);
            
            return $result ? $result['meta_value'] : null;
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getBookingMeta error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all postmeta for a booking
     */
    public function getAllBookingMeta($bookingId)
    {
        try {
            $query = "
                SELECT meta_key, meta_value 
                FROM wpk4_postmeta 
                WHERE post_id = :booking_id
            ";
            
            $results = $this->query($query, [
                'booking_id' => $bookingId
            ]);
            
            $meta = [];
            foreach ($results as $row) {
                $meta[$row['meta_key']] = $row['meta_value'];
            }
            
            return $meta;
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getAllBookingMeta error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get post by ID
     */
    public function getPostById($postId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_posts 
                WHERE ID = :post_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'post_id' => $postId
            ]);
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getPostById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get Ypsilon updated bookings
     */
    public function getYpsilonUpdatedBookings($limit = 100)
    {
        try {
            $limit = (int)$limit;
            $query = "
                SELECT * 
                FROM wpk4_backend_travel_bookings 
                WHERE is_updated = 'yes' 
                ORDER BY order_id DESC 
                LIMIT {$limit}
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getYpsilonUpdatedBookings error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get latest meta change for order
     */
    public function getLatestMetaChange($orderId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_history_of_meta_changes 
                WHERE type_id = :order_id 
                ORDER BY auto_id DESC 
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'order_id' => $orderId
            ]);
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getLatestMetaChange error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get payment ID for booking
     */
    public function getPaymentId($bookingId)
    {
        try {
            // Payment ID is typically stored in postmeta or can be found by searching for payment posts
            $query = "
                SELECT post_id 
                FROM wpk4_postmeta 
                WHERE meta_key = 'wp_travel_booking_id' 
                  AND meta_value = :booking_id
                LIMIT 1
            ";
            
            $result = $this->queryOne($query, [
                'booking_id' => $bookingId
            ]);
            
            return $result ? $result['post_id'] : null;
        } catch (PDOException $e) {
            error_log("BookingAdminDAL::getPaymentId error: " . $e->getMessage());
            return null;
        }
    }
}

