<?php
/**
 * Seat and Price Service Layer
 * 
 * Handles business logic for seat and price operations
 */

namespace App\Services;

use App\DAL\SeatPriceDAL;

class SeatPriceService {
    private $dal;

    public function __construct(SeatPriceDAL $dal = null) {
        // If DAL is not provided, create it with default database connection
        if ($dal === null) {
            global $pdo;
            if (!isset($pdo)) {
                // Database connection
                $servername = "localhost";
                $username   = "gaurat_sriharan";
                $password   = "r)?2lc^Q0cAE";
                $dbname     = "gaurat_gauratravel";
                
                $pdo = new \PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]);
            }
            $this->dal = new SeatPriceDAL($pdo);
        } else {
            $this->dal = $dal;
        }
    }

    /**
     * Get seat and price data with filters
     */
    public function getSeatPriceData($year, $month, $filters = []) {
        // Validate year and month
        $year = (int)$year;
        $month = (int)$month;
        
        if ($year < 2000 || $year > 2100) {
            throw new \Exception("Invalid year: $year", 400);
        }
        
        if ($month < 1 || $month > 12) {
            throw new \Exception("Invalid month: $month", 400);
        }
        
        // Get days in month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        // Get trip IDs
        $trips = $this->dal->getTripIds($year, $month, $filters);
        
        if (empty($trips)) {
            return [
                'year' => $year,
                'month' => $month,
                'days_in_month' => $daysInMonth,
                'trips' => [],
                'last_updated' => null,
                'summary' => []
            ];
        }
        
        // Get last updated date from first trip
        $lastUpdated = $trips[0]['added_on'] ?? null;
        
        // Build trip data with daily price and seat information
        $tripData = [];
        $verticalTotals = array_fill(1, $daysInMonth, 0);
        
        foreach ($trips as $trip) {
            $tripId = $trip['trip_id'];
            $horizontalTotal = 0;
            $dailyData = [];
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $priceSeat = $this->dal->getPriceAndSeat($year, $month, $day, $tripId);
                
                if ($priceSeat) {
                    $dailyData[$day] = [
                        'price' => $priceSeat['price'],
                        'seat' => (int)$priceSeat['seat']
                    ];
                    $horizontalTotal += (int)$priceSeat['seat'];
                    $verticalTotals[$day] += (int)$priceSeat['seat'];
                } else {
                    $dailyData[$day] = null;
                }
            }
            
            $tripData[] = [
                'trip_id' => $tripId,
                'airline' => $trip['airline'],
                'from' => $trip['start_place'],
                'to' => $trip['end_place'],
                'travel_type' => $trip['travel_type'],
                'flight_1' => $trip['flight_1'],
                'flight_2' => $trip['flight_2'],
                'daily_data' => $dailyData,
                'total_seats' => $horizontalTotal
            ];
        }
        
        // Calculate grand total
        $grandTotal = array_sum($verticalTotals);
        
        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'last_updated' => $lastUpdated,
            'trips' => $tripData,
            'summary' => [
                'vertical_totals' => $verticalTotals,
                'grand_total' => $grandTotal
            ],
            'filters' => [
                'travel_type' => $filters['travel_type'] ?? null,
                'airline' => $filters['airline'] ?? null,
                'from_location' => $filters['from_location'] ?? null,
                'to_location' => $filters['to_location'] ?? null,
                'search_type' => $filters['search_type'] ?? null
            ]
        ];
    }

    /**
     * Get filter options (airlines, locations)
     */
    public function getFilterOptions($month) {
        $month = (int)$month;
        
        if ($month < 1 || $month > 12) {
            throw new \Exception("Invalid month: $month", 400);
        }
        
        return [
            'airlines' => $this->dal->getDistinctAirlines($month),
            'start_places' => $this->dal->getDistinctStartPlaces($month),
            'end_places' => $this->dal->getDistinctEndPlaces($month)
        ];
    }

    /**
     * Get Qantas (QF) seat and price data with filters
     */
    public function getQantasSeatPriceData($year, $month, $filters = []) {
        // Validate year and month
        $year = (int)$year;
        $month = (int)$month;
        
        if ($year < 2000 || $year > 2100) {
            throw new \Exception("Invalid year: $year", 400);
        }
        
        if ($month < 1 || $month > 12) {
            throw new \Exception("Invalid month: $month", 400);
        }
        
        // Get days in month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        // Get trip IDs for Qantas only
        $trips = $this->dal->getQantasTripIds($year, $month, $filters);
        
        if (empty($trips)) {
            return [
                'year' => $year,
                'month' => $month,
                'days_in_month' => $daysInMonth,
                'trips' => [],
                'last_updated' => null,
                'summary' => []
            ];
        }
        
        // Get last updated date from first trip
        $lastUpdated = $trips[0]['added_on'] ?? null;
        
        // Build trip data with daily price and seat information
        $tripData = [];
        $verticalTotals = array_fill(1, $daysInMonth, 0);
        
        foreach ($trips as $trip) {
            $tripId = $trip['trip_id'];
            $horizontalTotal = 0;
            $dailyData = [];
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $priceSeat = $this->dal->getPriceAndSeat($year, $month, $day, $tripId);
                
                if ($priceSeat) {
                    $dailyData[$day] = [
                        'price' => $priceSeat['price'],
                        'seat' => (int)$priceSeat['seat']
                    ];
                    $horizontalTotal += (int)$priceSeat['seat'];
                    $verticalTotals[$day] += (int)$priceSeat['seat'];
                } else {
                    $dailyData[$day] = null;
                }
            }
            
            $tripData[] = [
                'trip_id' => $tripId,
                'airline' => $trip['airline'],
                'from' => $trip['start_place'],
                'to' => $trip['end_place'],
                'travel_type' => $trip['travel_type'],
                'daily_data' => $dailyData,
                'total_seats' => $horizontalTotal
            ];
        }
        
        // Calculate grand total
        $grandTotal = array_sum($verticalTotals);
        
        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'last_updated' => $lastUpdated,
            'trips' => $tripData,
            'summary' => [
                'vertical_totals' => $verticalTotals,
                'grand_total' => $grandTotal
            ],
            'filters' => [
                'travel_type' => $filters['travel_type'] ?? null,
                'from_location' => $filters['from_location'] ?? null,
                'to_location' => $filters['to_location'] ?? null
            ]
        ];
    }

    /**
     * Get Qantas filter options (locations only, airline is always QF)
     */
    public function getQantasFilterOptions($month) {
        $month = (int)$month;
        
        if ($month < 1 || $month > 12) {
            throw new \Exception("Invalid month: $month", 400);
        }
        
        return [
            'airline' => 'QF',
            'start_places' => $this->dal->getQantasDistinctStartPlaces($month),
            'end_places' => $this->dal->getQantasDistinctEndPlaces($month)
        ];
    }
}

