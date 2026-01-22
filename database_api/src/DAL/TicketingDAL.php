<?php
/**
 * Ticketing Data Access Layer (DAL)
 * 
 * Handles all database operations for ticketing management
 */

namespace App\DAL;

class TicketingDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get bookings with filters for ticketing
     * 
     * @param array $filters Array of filter conditions
     * @return array Array of booking records
     */
    public function getBookings($filters = []) {
        $where = [];
        $params = [];
        
        // Base conditions
        $where[] = "wpk4_backend_travel_bookings.order_id = wpk4_backend_travel_booking_pax.order_id";
        $where[] = "wpk4_backend_travel_bookings.co_order_id = wpk4_backend_travel_booking_pax.co_order_id";
        $where[] = "wpk4_backend_travel_bookings.product_id = wpk4_backend_travel_booking_pax.product_id";
        $where[] = "wpk4_backend_travel_bookings.order_type = 'gds'";
        $where[] = "wpk4_backend_travel_bookings.payment_status = 'paid'";
        $where[] = "wpk4_backend_travel_booking_pax.ticketed_by IS NULL";
        $where[] = "wpk4_backend_travel_booking_pax.pax_status <> 'Ticketed'";
        $where[] = "(wpk4_backend_travel_booking_pax.name_updated IS NULL OR wpk4_backend_travel_booking_pax.name_updated IN ('New', 'Name updated', 'escalation case resolved', 'name correction completed', 'name removal completed', 'name replacement completed'))";
        
        // Filter by trip code
        if (!empty($filters['tripcode'])) {
            $where[] = "wpk4_backend_travel_bookings.trip_code LIKE :tripcode";
            $params[':tripcode'] = '%' . $filters['tripcode'] . '%';
        }
        
        // Filter by travel date
        if (!empty($filters['travel_date'])) {
            $where[] = "DATE(wpk4_backend_travel_bookings.travel_date) = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        }
        
        // Filter by order date range
        if (!empty($filters['order_date_from'])) {
            $where[] = "DATE(wpk4_backend_travel_bookings.order_date) >= :order_date_from";
            $params[':order_date_from'] = $filters['order_date_from'];
        }
        
        if (!empty($filters['order_date_to'])) {
            $where[] = "DATE(wpk4_backend_travel_bookings.order_date) <= :order_date_to";
            $params[':order_date_to'] = $filters['order_date_to'];
        }
        
        // Filter by order ID
        if (!empty($filters['order_id'])) {
            $where[] = "wpk4_backend_travel_bookings.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        
        // Filter by PNR
        if (!empty($filters['pnr'])) {
            $where[] = "wpk4_backend_travel_booking_pax.pnr LIKE :pnr";
            $params[':pnr'] = '%' . $filters['pnr'] . '%';
        }
        
        // Filter by payment status
        if (!empty($filters['payment_status'])) {
            $where[] = "wpk4_backend_travel_bookings.payment_status = :payment_status";
            $params[':payment_status'] = $filters['payment_status'];
        }
        
        // Filter by pax status
        if (!empty($filters['pax_status'])) {
            $where[] = "wpk4_backend_travel_booking_pax.pax_status = :pax_status";
            $params[':pax_status'] = $filters['pax_status'];
        }
        
        // Filter by name updated status
        if (!empty($filters['name_updated'])) {
            $where[] = "wpk4_backend_travel_booking_pax.name_updated = :name_updated";
            $params[':name_updated'] = $filters['name_updated'];
        }
        
        // Domestic filter
        if (!empty($filters['domestic_filter']) && $filters['domestic_filter'] == 'yes') {
            // Get domestic trip codes
            $domesticQuery = "SELECT DISTINCT CONCAT(trip_id, '-', DATE(dep_date)) as trip_date_combo 
                             FROM wpk4_backend_stock_management_sheet 
                             WHERE CHAR_LENGTH(pnr) > 6";
            $domesticStmt = $this->pdo->query($domesticQuery);
            $domesticCombos = $domesticStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (!empty($domesticCombos)) {
                $placeholders = [];
                foreach ($domesticCombos as $idx => $combo) {
                    $key = ':domestic_' . $idx;
                    $placeholders[] = $key;
                    $params[$key] = $combo;
                }
                $where[] = "CONCAT(wpk4_backend_travel_bookings.trip_code, '-', wpk4_backend_travel_bookings.travel_date) IN (" . implode(',', $placeholders) . ")";
            }
        } elseif (!empty($filters['domestic_filter']) && $filters['domestic_filter'] == 'no') {
            // Get domestic trip codes to exclude
            $domesticQuery = "SELECT DISTINCT CONCAT(trip_id, '-', DATE(dep_date)) as trip_date_combo 
                             FROM wpk4_backend_stock_management_sheet 
                             WHERE CHAR_LENGTH(pnr) > 6";
            $domesticStmt = $this->pdo->query($domesticQuery);
            $domesticCombos = $domesticStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (!empty($domesticCombos)) {
                $placeholders = [];
                foreach ($domesticCombos as $idx => $combo) {
                    $key = ':domestic_' . $idx;
                    $placeholders[] = $key;
                    $params[$key] = $combo;
                }
                $where[] = "CONCAT(wpk4_backend_travel_bookings.trip_code, '-', wpk4_backend_travel_bookings.travel_date) NOT IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? 100;
        
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            JOIN wpk4_backend_travel_booking_pax ON 
                wpk4_backend_travel_bookings.order_id = wpk4_backend_travel_booking_pax.order_id AND 
                wpk4_backend_travel_bookings.co_order_id = wpk4_backend_travel_booking_pax.co_order_id AND 
                wpk4_backend_travel_bookings.product_id = wpk4_backend_travel_booking_pax.product_id 
            WHERE $whereClause
            ORDER BY wpk4_backend_travel_bookings.order_id DESC 
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
     * Update passenger ticketing information
     * 
     * @param int $paxAutoId Passenger auto ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function updatePaxTicketing($paxAutoId, $data) {
        $set = [];
        $params = [':auto_id' => $paxAutoId];
        
        foreach ($data as $column => $value) {
            if ($column !== 'auto_id' && $column !== 'order_id') {
                $set[] = "$column = :$column";
                $params[":$column"] = $value;
            }
        }
        
        if (empty($set)) {
            return false;
        }
        
        $setClause = implode(', ', $set);
        
        $sql = "
            UPDATE wpk4_backend_travel_booking_pax 
            SET $setClause, late_modified = NOW()
            WHERE auto_id = :auto_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Insert update history
     * 
     * @param array $historyData History data
     * @return bool Success status
     */
    public function insertUpdateHistory($historyData) {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_update_history 
            (order_id, co_order_id, merging_id, pax_auto_id, meta_key, meta_value, updated_time, updated_user)
            VALUES (:order_id, :co_order_id, :merging_id, :pax_auto_id, :meta_key, :meta_value, :updated_time, :updated_user)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $historyData['order_id']);
        $stmt->bindValue(':co_order_id', $historyData['co_order_id'] ?? '');
        $stmt->bindValue(':merging_id', $historyData['merging_id'] ?? '');
        $stmt->bindValue(':pax_auto_id', $historyData['pax_auto_id'] ?? '');
        $stmt->bindValue(':meta_key', $historyData['meta_key']);
        $stmt->bindValue(':meta_value', $historyData['meta_value']);
        $stmt->bindValue(':updated_time', $historyData['updated_time'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':updated_user', $historyData['updated_user'] ?? 'system');
        
        return $stmt->execute();
    }

    /**
     * Get distinct airlines
     * 
     * @return array Array of airlines
     */
    public function getAirlines() {
        $sql = "
            SELECT DISTINCT airline_code 
            FROM wpk4_backend_stock_management_sheet 
            ORDER BY airline_code ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get distinct trip codes
     * 
     * @return array Array of trip codes
     */
    public function getTripCodes() {
        $sql = "
            SELECT DISTINCT trip_code 
            FROM wpk4_backend_travel_bookings 
            ORDER BY trip_code ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get passenger by auto_id
     * 
     * @param int $paxAutoId Passenger auto ID
     * @return array|null Passenger record or null
     */
    public function getPaxById($paxAutoId) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE auto_id = :auto_id
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':auto_id', $paxAutoId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }
}

