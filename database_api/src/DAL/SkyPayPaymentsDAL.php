<?php

namespace App\DAL;

use PDO;

class SkyPayPaymentsDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get SkyPay callbacks with optional filters
     * Line: 190-194 (in template)
     */
    public function getSkyPayCallbacks($orderId = null, $date = null, $limit = 100)
    {
        $conditions = [];
        $params = [];
        
        if ($orderId !== null && $orderId !== '') {
            $conditions[] = "order_id = :order_id";
            $params[':order_id'] = $orderId;
        } else {
            $conditions[] = "id != 'DummyGT123'";
        }
        
        if ($date !== null && $date !== '') {
            $conditions[] = "date_time = :date_time";
            $params[':date_time'] = $date;
        } else {
            $conditions[] = "id != 'DummyGT123'";
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        if ($orderId !== null && $date !== null) {
            $query = "SELECT * FROM wpk4_backend_travel_skypay_callbacks 
                     WHERE $whereClause 
                     ORDER BY id ASC 
                     LIMIT :limit";
        } else {
            $query = "SELECT * FROM wpk4_backend_travel_skypay_callbacks 
                     WHERE $whereClause 
                     ORDER BY id DESC 
                     LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking payment details for an order
     * Line: 206-214 (in template)
     */
    public function getBookingPaymentDetails($orderId)
    {
        $query = "SELECT total_amount, deposit_amount, payment_status 
                 FROM wpk4_backend_travel_bookings 
                 WHERE order_id = :order_id 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $totalAmount = (float)$result['total_amount'];
            $depositAmount = (float)$result['deposit_amount'];
            $balance = $totalAmount - $depositAmount;
            
            return [
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'balance' => $balance,
                'payment_status' => $result['payment_status']
            ];
        }
        
        return null;
    }
}

