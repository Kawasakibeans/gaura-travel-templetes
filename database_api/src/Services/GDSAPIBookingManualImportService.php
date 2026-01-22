<?php
/**
 * GDS API Booking Manual Import Service
 * Handles business logic for manual GDS booking import
 */

namespace App\Services;

use App\DAL\GDSAPIBookingManualImportDAL;

class GDSAPIBookingManualImportService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GDSAPIBookingManualImportDAL();
    }
    
    /**
     * Get last order ID
     */
    public function getLastOrderId(): array
    {
        $lastOrderId = $this->dal->getLastOrderId();
        $nextOrderId = $lastOrderId ? $lastOrderId + 1 : 90009896;
        
        return [
            'last_order_id' => $lastOrderId,
            'next_order_id' => $nextOrderId
        ];
    }
    
    /**
     * Check if passenger exists
     */
    public function checkPassengerExists(array $params): array
    {
        $pnr = $params['pnr'] ?? null;
        $lastName = $params['last_name'] ?? null;
        $firstName = $params['first_name'] ?? null;
        
        if (!$pnr || !$lastName || !$firstName) {
            throw new \Exception('pnr, last_name, and first_name are required');
        }
        
        $passenger = $this->dal->checkPassengerExists($pnr, $lastName, $firstName);
        
        return [
            'pnr' => $pnr,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'exists' => $passenger !== null,
            'passenger' => $passenger
        ];
    }
}

