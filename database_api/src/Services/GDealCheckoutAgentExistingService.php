<?php
/**
 * GDeal Checkout Agent Existing Service
 * Handles business logic for GDeal checkout with existing customer data
 */

namespace App\Services;

use App\DAL\GDealCheckoutAgentExistingDAL;

class GDealCheckoutAgentExistingService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GDealCheckoutAgentExistingDAL();
    }
    
    /**
     * Get passenger data
     */
    public function getPassengerData(array $params): array
    {
        $customerId = $params['customer_id'] ?? null;
        
        if (!$customerId) {
            throw new \Exception('customer_id is required');
        }
        
        if (!is_numeric($customerId)) {
            throw new \Exception('customer_id must be numeric');
        }
        
        $passenger = $this->dal->getPassengerByCustomerId((int)$customerId);
        
        return [
            'passenger' => $passenger
        ];
    }
    
    /**
     * Get passengers by customer IDs
     */
    public function getPassengersByCustomerIds(array $params): array
    {
        $customerIds = $params['customer_ids'] ?? [];
        
        if (empty($customerIds)) {
            return ['passengers' => []];
        }
        
        if (is_string($customerIds)) {
            $customerIds = json_decode($customerIds, true);
        }
        
        if (!is_array($customerIds)) {
            throw new \Exception('customer_ids must be an array');
        }
        
        $passengers = $this->dal->getPassengersByCustomerIds($customerIds);
        
        return [
            'passengers' => $passengers
        ];
    }
    
    /**
     * Get billing address data
     */
    public function getBillingAddress(array $params): array
    {
        $customerId = $params['customer_id'] ?? null;
        
        if (!$customerId) {
            throw new \Exception('customer_id is required');
        }
        
        if (!is_numeric($customerId)) {
            throw new \Exception('customer_id must be numeric');
        }
        
        $billingData = $this->dal->getBillingAddressByCustomerId((int)$customerId);
        
        return [
            'billing_address' => $billingData
        ];
    }
}

