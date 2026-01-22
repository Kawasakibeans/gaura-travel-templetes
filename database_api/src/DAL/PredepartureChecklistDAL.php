<?php
/**
 * Predeparture Checklist Data Access Layer (DAL)
 * 
 * Handles all database operations for predeparture checklist management
 */

namespace App\DAL;

class PredepartureChecklistDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get bookings with filters for predeparture checklist
     */
    public function getBookings($filters = []) {
        $where = [];
        $params = [];

        // Base conditions
        $where[] = "booking.payment_status = 'paid'";

        // Route filter (trip_code LIKE)
        if (!empty($filters['route'])) {
            $where[] = "booking.trip_code LIKE :route";
            $params[':route'] = $filters['route'] . '%';
        } else {
            $where[] = "booking.trip_code != 'TEST_DMP_ID'";
        }

        // Airline filter (trip_code LIKE)
        if (!empty($filters['airline'])) {
            $where[] = "booking.trip_code LIKE :airline";
            $params[':airline'] = '%' . $filters['airline'] . '%';
        } else {
            $where[] = "booking.trip_code != 'TEST_DMP_ID'";
        }

        // Travel date filter
        if (!empty($filters['travel_date'])) {
            $where[] = "DATE(booking.travel_date) = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        } else {
            // Default: next 8 days
            $upcoming_8_days = date('Y-m-d', strtotime('+8 days'));
            $today_date = date('Y-m-d');
            $where[] = "DATE(booking.travel_date) <= :upcoming_date";
            $where[] = "DATE(booking.travel_date) >= :today_date";
            $params[':upcoming_date'] = $upcoming_8_days;
            $params[':today_date'] = $today_date;
        }

        // Status filter
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'completed') {
                $where[] = "pax.is_pre_departure_check_done = 'yes'";
            } elseif ($filters['status'] === 'not_completed') {
                $where[] = "pax.is_pre_departure_check_done IS NULL";
            }
        } else {
            $where[] = "booking.trip_code != 'TEST_DMP_ID'";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $limit = max(1, min(100, $limit));

        $sql = "
            SELECT DISTINCT
                booking.order_id,
                booking.travel_date,
                booking.trip_code,
                booking.total_pax,
                booking.t_type,
                booking.product_title,
                booking.product_id,
                booking.co_order_id
            FROM wpk4_backend_travel_bookings as booking
            JOIN wpk4_backend_travel_booking_pax as pax 
                ON booking.order_id = pax.order_id
                AND booking.co_order_id = pax.co_order_id
                AND booking.product_id = pax.product_id
            $whereClause
            ORDER BY booking.travel_date ASC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get booking details by order ID
     */
    public function getBookingByOrderId($orderId) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get PAX list for an order
     */
    public function getPaxByOrderId($orderId, $productId = null, $coOrderId = null) {
        $where = ["order_id = :order_id"];
        $params = [':order_id' => $orderId];

        if ($productId !== null) {
            $where[] = "product_id = :product_id";
            $params[':product_id'] = $productId;
        }

        if ($coOrderId !== null) {
            $where[] = "co_order_id = :co_order_id";
            $params[':co_order_id'] = $coOrderId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax
            $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get checklist categories/types
     */
    public function getChecklistCategories() {
        $sql = "
            SELECT * 
            FROM wpk4_backend_term_keys 
            WHERE category = 'PreDepartureCheckD7' 
                AND option_type = 'CategoryType'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get checklist item for a specific order, pax, and check title
     */
    public function getChecklistItem($orderId, $paxId, $checkTitle) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_predeparture_check 
            WHERE check_title = :check_title 
                AND order_id = :order_id 
                AND pax_id = :pax_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':check_title' => $checkTitle,
            ':order_id' => $orderId,
            ':pax_id' => $paxId
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all checklist items for an order and pax
     */
    public function getChecklistItems($orderId, $paxId) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_booking_predeparture_check 
            WHERE order_id = :order_id 
                AND pax_id = :pax_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_id' => $orderId,
            ':pax_id' => $paxId
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert or update checklist item
     */
    public function upsertChecklistItem($orderId, $paxId, $checkTitle, $checkValue, $checkOutcome, $updatedBy) {
        // Check if record exists
        $existing = $this->getChecklistItem($orderId, $paxId, $checkTitle);
        
        if ($existing) {
            // Update existing record
            $sql = "
                UPDATE wpk4_backend_travel_booking_predeparture_check 
                SET check_value = :check_value,
                    check_outcome = :check_outcome,
                    updated_by = :updated_by,
                    updated_on = NOW()
                WHERE check_title = :check_title 
                    AND order_id = :order_id 
                    AND pax_id = :pax_id
            ";
        } else {
            // Insert new record
            $sql = "
                INSERT INTO wpk4_backend_travel_booking_predeparture_check 
                (order_id, pax_id, check_title, check_value, check_outcome, updated_by, updated_on)
                VALUES
                (:order_id, :pax_id, :check_title, :check_value, :check_outcome, :updated_by, NOW())
            ";
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':order_id' => $orderId,
            ':pax_id' => $paxId,
            ':check_title' => $checkTitle,
            ':check_value' => $checkValue,
            ':check_outcome' => $checkOutcome,
            ':updated_by' => $updatedBy
        ]);
    }

    /**
     * Mark predeparture check as done for a PAX
     */
    public function markCheckDone($paxId) {
        $sql = "
            UPDATE wpk4_backend_travel_booking_pax 
            SET is_pre_departure_check_done = 'yes' 
            WHERE auto_id = :pax_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':pax_id' => $paxId]);
    }

    /**
     * Get count of bookings matching filters
     */
    public function countBookings($filters = []) {
        $where = [];
        $params = [];

        $where[] = "booking.payment_status = 'paid'";

        if (!empty($filters['route'])) {
            $where[] = "booking.trip_code LIKE :route";
            $params[':route'] = $filters['route'] . '%';
        } else {
            $where[] = "booking.trip_code != 'TEST_DMP_ID'";
        }

        if (!empty($filters['airline'])) {
            $where[] = "booking.trip_code LIKE :airline";
            $params[':airline'] = '%' . $filters['airline'] . '%';
        } else {
            $where[] = "booking.trip_code != 'TEST_DMP_ID'";
        }

        if (!empty($filters['travel_date'])) {
            $where[] = "DATE(booking.travel_date) = :travel_date";
            $params[':travel_date'] = $filters['travel_date'];
        } else {
            $upcoming_8_days = date('Y-m-d', strtotime('+8 days'));
            $today_date = date('Y-m-d');
            $where[] = "DATE(booking.travel_date) <= :upcoming_date";
            $where[] = "DATE(booking.travel_date) >= :today_date";
            $params[':upcoming_date'] = $upcoming_8_days;
            $params[':today_date'] = $today_date;
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'completed') {
                $where[] = "pax.is_pre_departure_check_done = 'yes'";
            } elseif ($filters['status'] === 'not_completed') {
                $where[] = "pax.is_pre_departure_check_done IS NULL";
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT COUNT(DISTINCT booking.order_id) as total
            FROM wpk4_backend_travel_bookings as booking
            JOIN wpk4_backend_travel_booking_pax as pax 
                ON booking.order_id = pax.order_id
                AND booking.co_order_id = pax.co_order_id
                AND booking.product_id = pax.product_id
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

