<?php

namespace App\DAL;

use PDO;

class OrderManagementDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get booking by order_id and co_order_id
     * Line: 1271 (in template)
     */
    public function getBookingByOrderId($orderId, $coOrderId = '')
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id";
        
        $params = [':order_id' => $orderId];
        
        if ($coOrderId !== '') {
            $query .= " AND co_order_id = :co_order_id";
            $params[':co_order_id'] = $coOrderId;
        } else {
            $query .= " AND (co_order_id = '' OR co_order_id IS NULL)";
        }
        
        $query .= " LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent bookings
     * Line: 784 (in template)
     */
    public function getRecentBookings($limit = 60, $minOrderId = 100000, $maxOrderId = 800000)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  WHERE order_id > :min_order_id 
                  AND order_id < :max_order_id 
                  ORDER BY auto_id DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':min_order_id', $minOrderId, PDO::PARAM_INT);
        $stmt->bindValue(':max_order_id', $maxOrderId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get passengers by order_id, co_order_id, and product_id
     * Line: 876 (in template)
     */
    public function getPassengersByOrder($orderId, $coOrderId, $productId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_booking_pax 
                  WHERE order_id = :order_id 
                  AND (co_order_id = :co_order_id OR co_order_id IS NULL) 
                  AND product_id = :product_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->bindValue(':product_id', $productId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get last co_order_id for an order
     * Line: 912, 938 (in template)
     */
    public function getLastCoOrderId($orderId)
    {
        $query = "SELECT co_order_id FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id 
                  ORDER BY co_order_id DESC 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['co_order_id'] : '';
    }

    /**
     * Update booking co_order_id
     * Line: 920, 931 (in template)
     */
    public function updateBookingCoOrderId($orderId, $productId, $coOrderId, $dividedDate, $oldCoOrderId = '')
    {
        $query = "UPDATE wpk4_backend_travel_bookings 
                  SET co_order_id = :co_order_id, divided_date = :divided_date 
                  WHERE order_id = :order_id AND product_id = :product_id";
        
        if ($oldCoOrderId !== '') {
            $query .= " AND co_order_id = :old_co_order_id";
        } else {
            $query .= " AND (co_order_id = '' OR co_order_id IS NULL)";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->bindValue(':divided_date', $dividedDate);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        
        if ($oldCoOrderId !== '') {
            $stmt->bindValue(':old_co_order_id', $oldCoOrderId);
        }
        
        return $stmt->execute();
    }

    /**
     * Update passenger co_order_id
     * Line: 923, 934, 962 (in template)
     */
    public function updatePassengerCoOrderId($orderId, $productId, $coOrderId, $oldCoOrderId = '', $autoIds = [])
    {
        $query = "UPDATE wpk4_backend_travel_booking_pax 
                  SET co_order_id = :co_order_id 
                  WHERE order_id = :order_id AND product_id = :product_id";
        
        $params = [
            ':co_order_id' => $coOrderId,
            ':order_id' => $orderId,
            ':product_id' => $productId
        ];
        
        if (!empty($autoIds)) {
            // Use named parameters instead of positional
            $placeholders = [];
            foreach ($autoIds as $index => $autoId) {
                $placeholder = ':auto_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $autoId;
            }
            $query .= " AND auto_id IN (" . implode(', ', $placeholders) . ")";
        } elseif ($oldCoOrderId !== '') {
            $query .= " AND co_order_id = :old_co_order_id";
            $params[':old_co_order_id'] = $oldCoOrderId;
        } else {
            $query .= " AND (co_order_id = '' OR co_order_id IS NULL)";
        }
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        return $stmt->execute();
    }

    /**
     * Update booking total_pax
     * Line: 956 (in template)
     */
    public function updateBookingTotalPax($orderId, $coOrderId, $productId, $totalPax)
    {
        $query = "UPDATE wpk4_backend_travel_bookings 
                  SET total_pax = :total_pax 
                  WHERE order_id = :order_id 
                  AND co_order_id = :co_order_id 
                  AND product_id = :product_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':total_pax', $totalPax, PDO::PARAM_INT);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->bindValue(':product_id', $productId);
        
        return $stmt->execute();
    }

    /**
     * Insert new divided booking
     * Line: 965 (in template)
     */
    public function insertDividedBooking($bookingData)
    {
        $fields = array_keys($bookingData);
        $placeholders = ':' . implode(', :', $fields);
        $fieldsStr = implode(', ', $fields);
        
        $query = "INSERT INTO wpk4_backend_travel_bookings ($fieldsStr) VALUES ($placeholders)";
        
        $stmt = $this->db->prepare($query);
        foreach ($bookingData as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Get booking for movement update
     * Line: 974 (in template)
     */
    public function getBookingForMovement($orderId, $productId, $coOrderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id 
                  AND product_id = :product_id 
                  AND co_order_id = :co_order_id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insert booking movement
     * Line: 1083 (in template)
     */
    public function insertBookingMovement($movementData)
    {
        $fields = array_keys($movementData);
        $placeholders = ':' . implode(', :', $fields);
        $fieldsStr = implode(', ', $fields);
        
        $query = "INSERT INTO wpk4_backend_travel_booking_movements ($fieldsStr) VALUES ($placeholders)";
        
        $stmt = $this->db->prepare($query);
        foreach ($movementData as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Update booking movement
     * Line: 1084 (in template)
     */
    public function updateBookingMovement($orderId, $productId, $coOrderId, $updateData)
    {
        $setParts = [];
        foreach (array_keys($updateData) as $key) {
            $setParts[] = "$key = :$key";
        }
        $setClause = implode(', ', $setParts);
        
        $query = "UPDATE wpk4_backend_travel_bookings 
                  SET $setClause 
                  WHERE order_id = :order_id 
                  AND product_id = :product_id 
                  AND co_order_id = :co_order_id";
        
        $stmt = $this->db->prepare($query);
        foreach ($updateData as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        
        return $stmt->execute();
    }

    /**
     * Update passenger PNR and ticket info after movement
     * Line: 1086-1089 (in template)
     */
    public function updatePassengerAfterMovement($orderId, $productId, $coOrderId, $pnr)
    {
        $query = "UPDATE wpk4_backend_travel_booking_pax 
                  SET ticket_number = NULL, 
                      ticketed_on = NULL, 
                      ticketed_by = NULL, 
                      pax_status = 'New', 
                      eticket_emailed = NULL, 
                      PNR = :pnr 
                  WHERE order_id = :order_id 
                  AND product_id = :product_id 
                  AND co_order_id = :co_order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pnr', $pnr);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        
        return $stmt->execute();
    }

    /**
     * Get booking with passenger details
     * Line: 1103-1109 (in template)
     */
    public function getBookingWithPassenger($orderId, $paxId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  JOIN wpk4_backend_travel_booking_pax ON 
                      wpk4_backend_travel_bookings.order_id = wpk4_backend_travel_booking_pax.order_id AND 
                      wpk4_backend_travel_bookings.co_order_id = wpk4_backend_travel_booking_pax.co_order_id AND 
                      wpk4_backend_travel_bookings.product_id = wpk4_backend_travel_booking_pax.product_id 
                  WHERE wpk4_backend_travel_bookings.order_id = :order_id 
                  AND wpk4_backend_travel_booking_pax.auto_id = :pax_id
                  ORDER BY wpk4_backend_travel_bookings.order_id DESC 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':pax_id', $paxId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get airline code by IATA code
     * Line: 1121-1122 (in template)
     */
    public function getAirlineCode($iataCode)
    {
        $query = "SELECT airline_code FROM wpk4_backend_travel_booking_airline_code 
                  WHERE iata_code = :iata_code 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':iata_code', $iataCode);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['airline_code'] : '';
    }

    /**
     * Check return bookings count
     * Line: 792 (in template)
     */
    public function getReturnBookingsCount($orderId, $coOrderId)
    {
        $query = "SELECT COUNT(*) as count FROM wpk4_backend_travel_bookings 
                  WHERE order_id = :order_id 
                  AND co_order_id = :co_order_id 
                  AND t_type = 'return'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Get movements count
     * Line: 795 (in template)
     */
    public function getMovementsCount($orderId, $productId, $coOrderId)
    {
        $query = "SELECT COUNT(*) as count FROM wpk4_backend_travel_booking_movements 
                  WHERE order_id = :order_id 
                  AND product_id = :product_id 
                  AND co_order_id = :co_order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':product_id', $productId);
        $stmt->bindValue(':co_order_id', $coOrderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['count'] : 0;
    }
}

