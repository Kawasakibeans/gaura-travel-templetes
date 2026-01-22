<?php

namespace App\Services;

use App\DAL\FlightPricingDAL;

class FlightPricingService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new FlightPricingDAL();
    }

    /**
     * Get airport info
     */
    public function getAirportInfo(array $params): array
    {
        $airportCode = $params['airport_code'] ?? null;
        $airportCodes = $params['airport_codes'] ?? null;
        
        // Handle single airport code
        if ($airportCode && empty($airportCodes)) {
            $airportInfo = $this->dal->getAirportInfo($airportCode);
            
            if (!$airportInfo) {
                return [
                    'airport_code' => $airportCode,
                    'city' => '',
                    'airport_name' => ''
                ];
            }
            
            return [
                'airport_code' => $airportCode,
                'city' => $airportInfo['city'] ?? '',
                'airport_name' => $airportInfo['airpotname'] ?? ''
            ];
        }
        
        // Handle multiple airport codes
        if ($airportCodes) {
            if (is_string($airportCodes)) {
                $airportCodes = explode(',', $airportCodes);
            }
            
            $airportCodes = array_map('trim', $airportCodes);
            $airportCodes = array_filter($airportCodes);
            
            $airports = $this->dal->getMultipleAirportInfo($airportCodes);
            
            return [
                'airports' => $airports,
                'total' => count($airports)
            ];
        }
        
        throw new \Exception('airport_code or airport_codes is required');
    }
}

