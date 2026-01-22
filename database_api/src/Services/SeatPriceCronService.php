<?php
/**
 * Seat and Price Cron Service Layer
 * 
 * Handles business logic for seat and price cron operations
 */

namespace App\Services;

use App\DAL\SeatPriceCronDAL;

class SeatPriceCronService {
    private $dal;

    public function __construct(SeatPriceCronDAL $dal = null) {
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
            $this->dal = new SeatPriceCronDAL($pdo);
        } else {
            $this->dal = $dal;
        }
    }

    /**
     * Delete upcoming months data
     */
    public function deleteUpcomingMonthsData($monthsAhead = 6) {
        $currentMonthIndex = (int)date('m') - 1;
        $endMonthIndex = ($currentMonthIndex + $monthsAhead) % 12;
        
        $deleted = $this->dal->deleteUpcomingMonthsData($currentMonthIndex, $endMonthIndex);
        
        return [
            'deleted_tables' => $deleted,
            'count' => count($deleted)
        ];
    }

    /**
     * Get trip dates for a date range
     */
    public function getTripDates($startDate, $endDate, $limit = 200) {
        $tripDates = $this->dal->getTripDates($startDate, $endDate, $limit);
        
        // Group by trip_id
        $grouped = [];
        foreach ($tripDates as $row) {
            $grouped[$row['trip_id']][] = $row['start_date'];
        }
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'trip_dates' => $grouped,
            'count' => count($grouped)
        ];
    }

    /**
     * Get trip metadata
     */
    public function getTripMetadata($tripId) {
        $metadata = $this->dal->getTripMetadata($tripId);
        
        // If no metadata found at all, return null
        if (!$metadata['itinerary_type'] && !$metadata['trip_code']) {
            return null;
        }
        
        $result = [
            'trip_id' => $tripId,
            'trip_code' => $metadata['trip_code'],
            'itinerary_type' => $metadata['itinerary_type'],
            'travel_type' => null,
            'airline' => null,
            'start_place' => null,
            'end_place' => null,
            'flight_1' => null,
            'flight_2' => null
        ];
        
        // Determine travel type if itinerary_type exists
        if ($metadata['itinerary_type']) {
            $result['travel_type'] = ($metadata['itinerary_type'] == 955) ? 'outbound' : 'inbound';
        }
        
        // Parse trip code if it exists
        if ($metadata['trip_code']) {
            $components = explode('-', $metadata['trip_code']);
            $result['start_place'] = $components[0] ?? '';
            $result['end_place'] = $components[1] ?? '';
            $result['airline'] = substr($components[2] ?? '', 0, 2);
            $result['flight_1'] = $components[2] ?? '';
            $result['flight_2'] = $components[3] ?? '';
        }
        
        return $result;
    }

    /**
     * Get pricing for a specific date and trip
     */
    public function getPricingForDate($tripId, $date) {
        $dateInfo = $this->dal->getPricingForDate($tripId, $date);
        
        if (!$dateInfo) {
            return null;
        }
        
        $pricingId = $dateInfo['pricing_ids'];
        $priceInfo = $this->dal->getPriceFromCategory($pricingId, 953);
        
        return [
            'trip_id' => $tripId,
            'date' => $date,
            'pricing_id' => $pricingId,
            'sale_price' => $priceInfo ? $priceInfo['sale_price'] : null,
            'price_info' => $priceInfo
        ];
    }

    /**
     * Calculate remaining seats for a trip and date
     */
    public function calculateRemainingSeats($tripId, $travelDate) {
        return $this->dal->calculateRemainingSeats($tripId, $travelDate);
    }

    /**
     * Process and insert seat stock data for a date range
     */
    public function processSeatStockData($startDate, $endDate, $limit = 200) {
        $processed = [];
        $errors = [];
        
        // Get trip dates
        $tripDatesData = $this->getTripDates($startDate, $endDate, $limit);
        $tripDates = $tripDatesData['trip_dates'];
        
        $currentTime = date('Y-m-d H:i:s');
        
        foreach ($tripDates as $tripId => $dates) {
            try {
                // Get trip metadata
                $metadata = $this->getTripMetadata($tripId);
                
                // Process each date in the range
                $startDateTime = new \DateTime($startDate);
                $endDateTime = new \DateTime($endDate);
                
                for ($dateIterator = clone $startDateTime; $dateIterator <= $endDateTime; $dateIterator->add(new \DateInterval('P1D'))) {
                    $currentDate = $dateIterator->format('Y-m-d');
                    
                    // Check if this date is in the trip dates
                    if (!in_array($currentDate, $dates)) {
                        continue;
                    }
                    
                    // Get pricing
                    $pricing = $this->getPricingForDate($tripId, $currentDate);
                    
                    if (!$pricing || !$pricing['sale_price']) {
                        continue;
                    }
                    
                    // Calculate remaining seats
                    $seatInfo = $this->calculateRemainingSeats($tripId, $currentDate);
                    
                    if (!$seatInfo || $seatInfo['remaining_seats'] <= 0) {
                        continue;
                    }
                    
                    // Insert/update seat stock
                    $this->dal->upsertSeatStock(
                        $tripId,
                        $currentDate,
                        $metadata['airline'],
                        $metadata['start_place'],
                        $metadata['end_place'],
                        $pricing['sale_price'],
                        $seatInfo['remaining_seats'],
                        $currentTime,
                        $metadata['travel_type'],
                        $metadata['flight_1'],
                        $metadata['flight_2']
                    );
                    
                    $processed[] = [
                        'trip_id' => $tripId,
                        'date' => $currentDate,
                        'price' => $pricing['sale_price'],
                        'seats' => $seatInfo['remaining_seats']
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'trip_id' => $tripId,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'processed' => $processed,
            'processed_count' => count($processed),
            'errors' => $errors,
            'error_count' => count($errors)
        ];
    }
}

