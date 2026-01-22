<?php
/**
 * Seat Availability Service
 * Business logic for seat availability operations
 */

namespace App\Services;

use App\DAL\SeatAvailabilityDAL;
use Exception;

class SeatAvailabilityService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SeatAvailabilityDAL();
    }

    /**
     * Check IP address access
     */
    public function checkIpAccess($ipAddress)
    {
        if (empty($ipAddress)) {
            throw new Exception('ip_address is required', 400);
        }

        return [
            'has_access' => $this->dal->checkIpAddress($ipAddress)
        ];
    }

    /**
     * Get airlines list
     */
    public function getAirlines($excludeAirlines = ['FC', 'MH'])
    {
        $airlines = $this->dal->getAirlines($excludeAirlines);
        
        $airlineCodes = array_unique(array_column($airlines, 'airline_code'));
        
        return [
            'airlines' => $airlineCodes,
            'total_count' => count($airlineCodes)
        ];
    }

    /**
     * Get routes by airline
     */
    public function getRoutesByAirline($airlineCode)
    {
        if (empty($airlineCode)) {
            throw new Exception('airline_code is required', 400);
        }

        $routes = $this->dal->getRoutesByAirline($airlineCode);
        
        $routeCodes = array_unique(array_column($routes, 'route'));
        
        return [
            'routes' => $routeCodes,
            'total_count' => count($routeCodes)
        ];
    }

    /**
     * Get all routes
     */
    public function getAllRoutes()
    {
        $routes = $this->dal->getAllRoutes();
        
        $routeCodes = array_unique(array_column($routes, 'route'));
        
        return [
            'routes' => $routeCodes,
            'total_count' => count($routeCodes)
        ];
    }

    /**
     * Get seat availability with calculations
     */
    public function getSeatAvailability($filters = [])
    {
        $stockData = $this->dal->getSeatAvailability($filters);
        
        $results = [];
        foreach ($stockData as $stock) {
            $tripCode = $stock['trip_id'];
            $travelDate = $stock['dep_date'];
            $currentStock = (int)($stock['current_stock'] ?? 0);
            
            // Get booked passengers
            $booked = $this->dal->getBookedPassengersCount($tripCode, $travelDate);
            $paxCount = $booked['pax_count'];
            
            // Calculate remaining seats
            $remainingSeats = $currentStock - $paxCount;
            
            // Get product info
            $productInfo = $this->dal->getProductInfo($tripCode, $travelDate);
            
            $results[] = [
                'auto_id' => $stock['auto_id'],
                'trip_code' => $tripCode,
                'travel_date' => $travelDate,
                'current_stock' => $currentStock,
                'booked_pax' => $paxCount,
                'order_count' => $booked['order_count'],
                'remaining_seats' => $remainingSeats,
                'availability_status' => $remainingSeats > 3 ? 'available' : ($remainingSeats > 0 ? 'limited' : 'unavailable'),
                'product_info' => $productInfo,
                'sub_agent_fare_inr' => $stock['sub_agent_fare_inr'] ?? null,
                'airline_code' => $stock['airline_code'] ?? null,
                'route' => $stock['route'] ?? null
            ];
        }
        
        return [
            'availability' => $results,
            'total_count' => count($results)
        ];
    }

    /**
     * Get seat availability for internal view (with pricing)
     */
    public function getSeatAvailabilityInternal($filters = [])
    {
        $stockData = $this->dal->getSeatAvailability($filters);
        $salePrice = $filters['sale_price'] ?? null;
        
        $results = [];
        $processedTrips = [];
        
        foreach ($stockData as $stock) {
            $tripCode = $stock['trip_id'];
            $travelDate = $stock['dep_date'];
            $travelDateFormatted = date('Y-m-d', strtotime($travelDate));
            
            // Skip duplicates
            $tripKey = $tripCode . $travelDate;
            if (in_array($tripKey, $processedTrips)) {
                continue;
            }
            $processedTrips[] = $tripKey;
            
            // Get total current stock for this trip/date
            $totalStock = $this->dal->getTotalCurrentStock($tripCode, $travelDate);
            
            // Get booked passengers
            $booked = $this->dal->getBookedPassengersCount($tripCode, $travelDate);
            $paxCount = $booked['pax_count'];
            
            // Calculate remaining seats
            $remainingSeats = $totalStock - $paxCount;
            
            if ($remainingSeats <= 0) {
                continue; // Skip unavailable trips
            }
            
            // Get product info
            $productInfo = $this->dal->getProductInfoByDateWithTime($tripCode, $travelDate);
            $productInfoData = !empty($productInfo) ? $productInfo[0] : null;
            
            $pricingId = $productInfoData['pricing_id'] ?? null;
            $adultPricing = null;
            $childPricing = null;
            
            if ($pricingId) {
                $adultPricing = $this->dal->getAdultPricing($pricingId);
                $childPricing = $this->dal->getChildPricing($pricingId);
            }
            
            $adultRate = $adultPricing['sale_price'] ?? '';
            $childRate = $childPricing['sale_price'] ?? '';
            
            // Filter by sale price if provided
            if ($salePrice !== null && $salePrice !== '' && $salePrice !== 'NULL' && $salePrice !== 'null') {
                if ($adultRate != $salePrice) {
                    continue;
                }
            }
            
            $results[] = [
                'auto_id' => $stock['auto_id'],
                'trip_code' => $tripCode,
                'travel_date' => $travelDate,
                'travel_date_formatted' => date('d-m-Y', strtotime($travelDate)),
                'total_stock' => $totalStock,
                'booked_pax' => $paxCount,
                'order_count' => $booked['order_count'],
                'remaining_seats' => $remainingSeats,
                'availability_status' => $remainingSeats > 3 ? 'available' : ($remainingSeats > 0 ? 'limited' : 'unavailable'),
                'product_info' => $productInfoData,
                'product_title' => $productInfoData['product_title'] ?? '',
                'adult_price' => $adultRate,
                'child_price' => $childRate,
                'pricing_id' => $pricingId,
                'airline_code' => $stock['airline_code'] ?? null,
                'route' => $stock['route'] ?? null
            ];
        }
        
        return [
            'availability' => $results,
            'total_count' => count($results)
        ];
    }

    /**
     * Get sale prices by route
     */
    public function getSalePricesByRoute($route)
    {
        if (empty($route)) {
            throw new Exception('route is required', 400);
        }

        $prices = $this->dal->getSalePricesByRoute($route);
        
        $salePrices = array_unique(array_column($prices, 'sale_price'));
        
        return [
            'prices' => $salePrices,
            'total_count' => count($salePrices)
        ];
    }

    /**
     * Get pricing information for a trip
     */
    public function getTripPricing($tripCode, $travelDate)
    {
        if (empty($tripCode) || empty($travelDate)) {
            throw new Exception('trip_code and travel_date are required', 400);
        }

        $productInfo = $this->dal->getProductInfoByDateWithTime($tripCode, $travelDate);
        
        if (empty($productInfo)) {
            return [
                'adult_price' => null,
                'child_price' => null,
                'pricing_id' => null
            ];
        }
        
        $productData = $productInfo[0];
        $pricingId = $productData['pricing_id'] ?? null;
        
        $adultPricing = $pricingId ? $this->dal->getAdultPricing($pricingId) : null;
        $childPricing = $pricingId ? $this->dal->getChildPricing($pricingId) : null;
        
        return [
            'pricing_id' => $pricingId,
            'adult_price' => $adultPricing['sale_price'] ?? null,
            'child_price' => $childPricing['sale_price'] ?? null,
            'product_info' => $productData
        ];
    }
}

