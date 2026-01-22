<?php

namespace App\DAL;

use PDO;

class BackendFunctionsDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get incentive dates for a month
     * Line: 15-25 (in template)
     */
    public function getIncentiveDatesForMonth($month)
    {
        $query = "SELECT start_date, end_date 
                 FROM wpk4_agent_data_incentive_conditions 
                 WHERE DATE_FORMAT(start_date, '%Y-%m') <= :month1 
                 AND DATE_FORMAT(end_date, '%Y-%m') >= :month2
                 ORDER BY start_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':month1', $month);
        $stmt->bindValue(':month2', $month);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get price per person for pricing_id
     * Line: 51-54 (in template)
     */
    public function getPricePerPerson($pricingId)
    {
        $query = "SELECT regular_price 
                 FROM wpk4_wt_price_category_relation 
                 WHERE pricing_id = :pricing_id 
                 AND pricing_category_id = 953
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pricing_id', $pricingId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['regular_price'] : 0;
    }

    /**
     * Get product info by tripcode and date
     * Line: 67-77 (in template)
     */
    public function getProductInfoByTripcodeAndDate($tripcode, $date)
    {
        $query = "SELECT * FROM wpk4_backend_stock_product_manager 
                 WHERE trip_code = :tripcode 
                 AND DATE(travel_date) = :date
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':tripcode', $tripcode);
        $stmt->bindValue(':date', $date);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return null;
        }
        
        // Get PNR from stock management sheet
        $pnrQuery = "SELECT pnr FROM wpk4_backend_stock_management_sheet 
                     WHERE trip_id = :tripcode 
                     AND DATE(dep_date) = :date
                     LIMIT 1";
        
        $pnrStmt = $this->db->prepare($pnrQuery);
        $pnrStmt->bindValue(':tripcode', $tripcode);
        $pnrStmt->bindValue(':date', $date);
        $pnrStmt->execute();
        
        $pnrResult = $pnrStmt->fetch(PDO::FETCH_ASSOC);
        $pnr = $pnrResult ? $pnrResult['pnr'] : '';
        
        return [
            'id' => $product['product_id'],
            'title' => $product['product_title'],
            'pricingid' => $product['pricing_id'],
            'pnr' => $pnr
        ];
    }

    /**
     * Get paid amount for adjustment (G360 version)
     * Line: 95-110 (in template)
     */
    public function getPaidAmountForAdjustmentG360($orderId)
    {
        // Get booking info
        $bookingQuery = "SELECT payment_status, total_amount 
                        FROM wpk4_backend_travel_bookings 
                        WHERE order_id = :order_id
                        LIMIT 1";
        
        $stmt = $this->db->prepare($bookingQuery);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return null;
        }
        
        $paymentStatus = $booking['payment_status'];
        $totalAmount = (float)$booking['total_amount'];
        
        // Get total paid
        $paymentQuery = "SELECT trams_received_amount 
                        FROM wpk4_backend_travel_payment_history 
                        WHERE order_id = :order_id";
        
        $paymentStmt = $this->db->prepare($paymentQuery);
        $paymentStmt->bindValue(':order_id', $orderId);
        $paymentStmt->execute();
        
        $totalAmountPaid = 0;
        while ($row = $paymentStmt->fetch(PDO::FETCH_ASSOC)) {
            $totalAmountPaid += (float)$row['trams_received_amount'];
        }
        
        return [
            'payment_status' => $paymentStatus,
            'total_amount' => $totalAmount,
            'total_paid' => $totalAmountPaid,
            'overpaid' => $paymentStatus === 'paid' && $totalAmount > 0 ? ($totalAmountPaid - $totalAmount) : null,
            'adjustment_amount' => $this->calculateAdjustmentAmount($paymentStatus, $totalAmount, $totalAmountPaid)
        ];
    }

    /**
     * Get paid amount for adjustment (simple version)
     * Line: 132-136 (in template)
     */
    public function getPaidAmountForAdjustment($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_payment_history 
                 WHERE order_id = :order_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $totalAmountPaid = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalAmountPaid += (float)$row['trams_received_amount'];
        }
        
        return $totalAmountPaid;
    }

    /**
     * Get paid amount for adjustment with deadline check
     * Line: 150-154 (in template)
     */
    public function getPaidAmountForAdjustmentWithDeadline($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_payment_history 
                 WHERE order_id = :order_id 
                 AND payment_change_deadline > NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();
        
        $totalAmountPaid = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalAmountPaid += (float)$row['trams_received_amount'];
        }
        
        return $totalAmountPaid;
    }

    /**
     * Calculate adjustment amount based on payment status
     */
    private function calculateAdjustmentAmount($paymentStatus, $totalAmount, $totalAmountPaid)
    {
        if ($paymentStatus === 'paid' && $totalAmount > 0) {
            return $totalAmountPaid - $totalAmount; // Overpaid amount
        } elseif (in_array($paymentStatus, ['partially_paid', 'canceled'])) {
            return $totalAmountPaid; // Total paid
        } else {
            return 0;
        }
    }
}

