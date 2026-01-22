<?php
/**
 * Invoice Data Access Layer
 * Handles database operations for invoice generation
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class InvoiceDAL extends BaseDAL
{
    /**
     * Get booking basic info
     */
    public function getBookingBasicInfo($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = ? LIMIT 1";
        return $this->queryOne($query, [$orderId]);
    }

    /**
     * Get PAX information
     */
    public function getPaxInfo($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_booking_pax 
                  WHERE order_id = ? 
                  ORDER BY auto_id ASC 
                  LIMIT 1";
        
        return $this->queryOne($query, [$orderId]);
    }

    /**
     * Get booking details (all products)
     */
    public function getBookingDetails($orderId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_bookings 
                  WHERE order_id = ? 
                  ORDER BY travel_date ASC";
        
        return $this->query($query, [$orderId]);
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails($orderId)
    {
        $query = "SELECT trams_received_amount, process_date, pay_type
                  FROM wpk4_backend_travel_payment_history 
                  WHERE order_id = ? 
                    AND trams_received_amount > 0 
                    AND (pay_type = 'deposit' OR pay_type LIKE 'balance%')
                  ORDER BY process_date ASC";
        
        return $this->query($query, [$orderId]);
    }

    /**
     * Get trip price for pax
     */
    public function getTripPrice($orderId, $productId)
    {
        $query = "SELECT trip_price_individual 
                  FROM wpk4_backend_travel_booking_pax 
                  WHERE order_id = ? AND product_id = ? 
                  ORDER BY auto_id DESC 
                  LIMIT 1";
        
        return $this->queryOne($query, [$orderId, $productId]);
    }

    /**
     * Get pricing info
     */
    public function getPricingInfo($tripCode, $travelDate, $productId)
    {
        // Get pricing ID
        $query1 = "SELECT pricing_id 
                   FROM wpk4_backend_stock_product_manager 
                   WHERE DATE(travel_date) = ? AND product_id = ? 
                   ORDER BY auto_id DESC 
                   LIMIT 1";
        
        $result = $this->queryOne($query1, [$travelDate, $productId]);
        
        if ($result) {
            $pricingId = $result['pricing_id'];
            
            // Get sale price
            $query2 = "SELECT sale_price 
                       FROM wpk4_wt_price_category_relation 
                       WHERE pricing_id = ? 
                       ORDER BY id DESC 
                       LIMIT 1";
            
            return $this->queryOne($query2, [$pricingId]);
        }

        return null;
    }
}

