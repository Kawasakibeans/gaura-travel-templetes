<?php
/**
 * GDeal Checkout Agent Existing Data Access Layer
 * Handles database operations for GDeal checkout with existing customer data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDealCheckoutAgentExistingDAL extends BaseDAL
{
    /**
     * Get passenger data by customer ID
     */
    public function getPassengerByCustomerId(int $customerId): ?array
    {
        try {
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_passenger 
                WHERE customer_id = :customer_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':customer_id' => $customerId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            error_log("GDealCheckoutAgentExistingDAL::getPassengerByCustomerId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get passengers by customer IDs
     */
    public function getPassengersByCustomerIds(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }
        
        try {
            $placeholders = [];
            $params = [];
            foreach ($customerIds as $index => $customerId) {
                $key = ':customer_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = (int)$customerId;
            }
            
            $sql = "
                SELECT * 
                FROM wpk4_backend_travel_passenger 
                WHERE customer_id IN (" . implode(', ', $placeholders) . ")
            ";
            
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            error_log("GDealCheckoutAgentExistingDAL::getPassengersByCustomerIds error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get billing address data by customer ID
     */
    public function getBillingAddressByCustomerId(int $customerId): ?array
    {
        try {
            $sql = "
                SELECT p.*, a.* 
                FROM wpk4_backend_travel_passenger p 
                LEFT JOIN wpk4_backend_travel_passenger_address a ON p.address_id = a.address_id  
                WHERE p.customer_id = :customer_id 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':customer_id' => $customerId]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            error_log("GDealCheckoutAgentExistingDAL::getBillingAddressByCustomerId error: " . $e->getMessage());
            return null;
        }
    }
}

