<?php
/**
 * Flight Pricing Data Access Layer
 * Handles database operations for flight pricing backend
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class FlightPricingDAL extends BaseDAL
{
    /**
     * Get airport info by code
     */
    public function getAirportInfo(string $airportCode): ?array
    {
        $sql = "
            SELECT city, airpotname 
            FROM airport_list_bk 
            WHERE airpotcode = :airport_code 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':airport_code' => $airportCode]);
        return $result ?: null;
    }
    
    /**
     * Get multiple airport info
     */
    public function getMultipleAirportInfo(array $airportCodes): array
    {
        if (empty($airportCodes)) {
            return [];
        }
        
        $placeholders = [];
        $params = [];
        
        foreach ($airportCodes as $index => $code) {
            $key = ':code' . $index;
            $placeholders[] = $key;
            $params[$key] = $code;
        }
        
        $sql = "
            SELECT airpotcode, city, airpotname 
            FROM airport_list_bk 
            WHERE airpotcode IN (" . implode(', ', $placeholders) . ")
        ";
        
        return $this->query($sql, $params);
    }
}

