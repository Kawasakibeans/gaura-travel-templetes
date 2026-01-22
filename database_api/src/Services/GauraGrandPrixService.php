<?php
/**
 * Gaura Grand Prix Service
 * Handles business logic for Gaura Grand Prix championship data
 */

namespace App\Services;

use App\DAL\GauraGrandPrixDAL;

class GauraGrandPrixService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GauraGrandPrixDAL();
    }
    
    /**
     * Get GTIB data
     */
    public function getGTIBData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-04-24';
        $endDate = $params['end_date'] ?? '2025-04-30';
        
        return $this->dal->getGTIBData($startDate, $endDate);
    }
    
    /**
     * Get FCS data
     */
    public function getFCSData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-04-24';
        $endDate = $params['end_date'] ?? '2025-04-30';
        
        return $this->dal->getFCSData($startDate, $endDate);
    }
    
    /**
     * Get conversion data
     */
    public function getConversionData(array $params): array
    {
        $startDate = $params['start_date'] ?? '2025-04-24';
        $endDate = $params['end_date'] ?? '2025-04-30';
        
        return $this->dal->getConversionData($startDate, $endDate);
    }
}

