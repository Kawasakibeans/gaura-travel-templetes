<?php
/**
 * Sale Price Service Layer
 * 
 * Encapsulates business logic for sale price management
 */

namespace App\Services;

use App\DAL\SalePriceDAL;

class SalePriceService {
    private $dal;

    public function __construct(SalePriceDAL $dal) {
        $this->dal = $dal;
    }

    /**
     * Get sale prices with filters
     * 
     * @param array $filters Filter parameters
     * @return array Sale prices with calculated fields
     */
    public function getSalePrices($filters = []) {
        $salePrices = $this->dal->getSalePrices($filters);
        
        // Process each sale price to add calculated fields
        $processedPrices = [];
        $pricingIdPrinted = [];
        
        foreach ($salePrices as $price) {
            // Skip if pricing ID already processed
            if (in_array($price['id'], $pricingIdPrinted)) {
                continue;
            }
            
            $pricingIdPrinted[] = $price['id'];
            
            // Calculate additional fields
            $selectedDepDate = date('Y-m-d', strtotime($price['travel_date']));
            $selectedAirlineRoute = substr($price['trip_code'], -11);
            
            // Get booking count
            $paxCountBooked = $this->dal->getBookingCount($selectedAirlineRoute, $selectedDepDate);
            
            // Calculate final stock
            $finalStock = (int)$price['original_stock'] - (int)$price['stock_release'] - (int)$price['stock_unuse'];
            
            // Calculate unsold count
            $unsoldCount = $finalStock - $paxCountBooked;
            
            // Determine trip type (INT/DOM) based on PNR length
            $tripType = (strlen($price['pnr']) > 9) ? 'DOM' : 'INT';
            
            // Add calculated fields
            $price['final_stock'] = $finalStock;
            $price['pax_count_booked'] = $paxCountBooked;
            $price['unsold_count'] = $unsoldCount;
            $price['trip_type'] = $tripType;
            $price['formatted_travel_date'] = date('d/m/Y', strtotime($selectedDepDate));
            
            $processedPrices[] = $price;
        }
        
        return $processedPrices;
    }

    /**
     * Get distinct airlines
     * 
     * @param int $limit Maximum number of results
     * @return array Array of airlines
     */
    public function getAirlines($limit = 20) {
        return $this->dal->getAirlines($limit);
    }

    /**
     * Get distinct routes
     * 
     * @param int $limit Maximum number of results
     * @return array Array of routes
     */
    public function getRoutes($limit = 60) {
        return $this->dal->getRoutes($limit);
    }

    /**
     * Update sale price for a pricing ID
     * 
     * @param int $pricingId Pricing ID
     * @param string $columnName Column name
     * @param float $newValue New value
     * @param string $updatedUser Updated user
     * @return bool Success status
     */
    public function updateSalePrice($pricingId, $columnName, $newValue, $updatedUser = 'system') {
        // Get current sale price
        $currentPrice = $this->dal->getSalePriceById($pricingId);
        
        if (!$currentPrice) {
            throw new \Exception("Pricing ID not found: $pricingId", 404);
        }
        
        $oldValue = $currentPrice[$columnName] ?? null;
        
        // Only update if value has changed
        if ($oldValue != $newValue) {
            // Update the sale price
            $success = $this->dal->updateSalePrice($pricingId, $columnName, $newValue);
            
            if ($success) {
                // Insert history records
                $currentTime = date('Y-m-d H:i:s');
                
                // Insert original value
                $originalColName = 'original_' . $columnName;
                $this->dal->insertUpdateHistory(
                    $pricingId,
                    $originalColName,
                    $oldValue,
                    $currentTime,
                    $updatedUser
                );
                
                // Insert new value
                $newColName = 'new_' . $columnName;
                $this->dal->insertUpdateHistory(
                    $pricingId,
                    $newColName,
                    $newValue,
                    $currentTime,
                    $updatedUser
                );
            }
            
            return $success;
        }
        
        return true; // No change needed
    }
}

