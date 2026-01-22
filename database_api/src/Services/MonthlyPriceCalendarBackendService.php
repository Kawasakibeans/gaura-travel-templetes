<?php

namespace App\Services;

use App\DAL\MonthlyPriceCalendarBackendDAL;

class MonthlyPriceCalendarBackendService
{
    private $dal;

    public function __construct(MonthlyPriceCalendarBackendDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get routes by airline code
     * Line: 7-31 (in template)
     */
    public function getRoutesByAirline($airlineCode)
    {
        if (empty($airlineCode)) {
            throw new \Exception('airline_code is required', 400);
        }
        
        $endOfToday = date('Y-m-d') . ' 00:00:00';
        $routes = $this->dal->getRoutesByAirline($airlineCode, $endOfToday);
        
        return [
            'airline_code' => $airlineCode,
            'routes' => $routes,
            'count' => count($routes)
        ];
    }

    /**
     * Get sale prices by route
     * Line: 56-87 (in template)
     */
    public function getSalePricesByRoute($route)
    {
        if (empty($route)) {
            throw new \Exception('route is required', 400);
        }
        
        $prices = $this->dal->getSalePricesByRoute($route);
        
        return [
            'route' => $route,
            'prices' => $prices,
            'count' => count($prices),
            'min_price' => !empty($prices) ? min($prices) : null,
            'max_price' => !empty($prices) ? max($prices) : null
        ];
    }

    /**
     * Get detailed monthly calendar data with fares and seat availability
     * Line: 88-346, 471-673 (in template)
     */
    public function getDetailedCalendarData($route, $month, $airlineCode = '')
    {
        if (empty($route)) {
            throw new \Exception('route is required', 400);
        }
        
        if (empty($month)) {
            throw new \Exception('month is required (format: YYYY-MM)', 400);
        }
        
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \Exception('Invalid month format. Use YYYY-MM', 400);
        }
        
        // Get first and last day of month
        $firstDay = $month . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));
        
        // Get trips
        $trips = $this->dal->getTripsForCalendar($route, $firstDay, $lastDay, $airlineCode);
        
        $processedTripInfo = [];
        $calendarData = [];
        
        foreach ($trips as $trip) {
            $tripCode = $trip['trip_id'];
            $depDate = $trip['dep_date'];
            
            // Skip if already processed
            $combination = $tripCode . $depDate;
            if (in_array($combination, $processedTripInfo)) {
                continue;
            }
            $processedTripInfo[] = $combination;
            
            $depDateFormatted = date('Y-m-d', strtotime($depDate));
            $depDateWithTime = $depDateFormatted . ' 00:00:00';
            
            // Get product info
            $productInfo = $this->dal->getProductInfo($tripCode, $depDateFormatted);
            if (!$productInfo) {
                continue;
            }
            
            $pricingId = $productInfo['pricing_id'];
            
            // Get total current stock
            $totalStock = $this->dal->getTotalCurrentStock($tripCode, $depDateFormatted);
            
            // Get booked passengers
            $bookedPax = $this->dal->getBookedPassengersCount($tripCode, $depDate);
            
            // Calculate remaining seats
            $remainingSeats = $totalStock - $bookedPax;
            
            if ($remainingSeats <= 0) {
                continue;
            }
            
            // Get rates
            $adultRate = $this->dal->getAdultRate($pricingId);
            $childRate = $this->dal->getChildRate($pricingId);
            
            // Get seat availability
            $seatAvailability = $this->dal->getSeatAvailability($tripCode, $depDateFormatted);
            
            // Extract route info from trip code
            $depApt = substr($tripCode, 0, 3);
            $dstApt = substr($tripCode, 4, 3);
            $airline = substr($tripCode, 8, 2);
            
            $day = (int)date('j', strtotime($depDate));
            
            // Group by day
            if (!isset($calendarData[$day])) {
                $calendarData[$day] = [];
            }
            
            $calendarData[$day][] = [
                'trip_code' => $tripCode,
                'dep_date' => $depDate,
                'dep_date_formatted' => date('d-m-Y', strtotime($depDate)),
                'adult_rate' => $adultRate,
                'child_rate' => $childRate,
                'remaining_seats' => $remainingSeats,
                'total_stock' => $totalStock,
                'booked_pax' => $bookedPax,
                'airline' => $airline,
                'dep_apt' => $depApt,
                'dst_apt' => $dstApt,
                'product_id' => $productInfo['product_id'],
                'product_title' => $productInfo['product_title'] ?? '',
                'pricing_id' => $pricingId,
                'seat_availability' => $seatAvailability ? [
                    'stock' => (int)$seatAvailability['stock'],
                    'pax' => (int)$seatAvailability['pax']
                ] : null
            ];
        }
        
        // Process each day to get lowest fare
        $processedCalendarData = [];
        foreach ($calendarData as $day => $dayTrips) {
            $lowestFare = null;
            $lowestFareTrip = null;
            $allFares = [];
            
            foreach ($dayTrips as $trip) {
                if ($trip['adult_rate'] > 0) {
                    $allFares[] = [
                        'fare' => $trip['adult_rate'],
                        'seats' => $trip['remaining_seats']
                    ];
                    
                    if ($lowestFare === null || $trip['adult_rate'] < $lowestFare) {
                        $lowestFare = $trip['adult_rate'];
                        $lowestFareTrip = $trip;
                    }
                }
            }
            
            if ($lowestFareTrip) {
                $processedCalendarData[$day] = [
                    'lowest_fare' => $lowestFare,
                    'matching_seats' => $lowestFareTrip['remaining_seats'],
                    'airline' => $lowestFareTrip['airline'],
                    'all_fares' => $allFares,
                    'trip_details' => $lowestFareTrip
                ];
            }
        }
        
        return [
            'route' => $route,
            'month' => $month,
            'airline_code' => $airlineCode,
            'calendar_data' => $processedCalendarData,
            'total_days_with_data' => count($processedCalendarData)
        ];
    }
}

