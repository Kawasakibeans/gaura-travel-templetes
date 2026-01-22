<?php
/**
 * GDS API Missing Order Search Service
 * Handles business logic for checking if GDS bookings exist by PNR
 */

namespace App\Services;

use App\DAL\GDSAPIMissingOrderSearchDAL;

class GDSAPIMissingOrderSearchService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GDSAPIMissingOrderSearchDAL();
    }
    
    /**
     * Check if PNR exists
     */
    public function checkPnrExists(array $params): array
    {
        $pnr = $params['pnr'] ?? null;
        $date = $params['date'] ?? date('Y-m-d');
        
        if (!$pnr) {
            throw new \Exception('pnr is required');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \Exception('date must be in YYYY-MM-DD format');
        }
        
        $exists = $this->dal->checkPnrExists($pnr, $date);
        $orderInfo = null;
        
        if ($exists) {
            $orderInfo = $this->dal->getOrderInfoByPnr($pnr, $date);
        }
        
        return [
            'pnr' => $pnr,
            'date' => $date,
            'exists' => $exists,
            'order_info' => $orderInfo
        ];
    }
}

