<?php
/**
 * Seat and Price Cron Data Access Layer (DAL)
 * 
 * Handles all database operations for seat and price cron job
 */

namespace App\DAL;

class SeatPriceCronDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get month table name from month number
     */
    private function getMonthTableName($month) {
        $months = ["january", "february", "march", "april", "may", "june", 
                   "july", "august", "september", "october", "november", "december"];
        $monthIndex = (int)$month - 1;
        if ($monthIndex < 0 || $monthIndex > 11) {
            throw new \Exception("Invalid month: $month");
        }
        return "wpk4_backend_travel_seats_stock_" . $months[$monthIndex];
    }

    /**
     * Delete all data from month tables for upcoming months
     */
    public function deleteUpcomingMonthsData($startMonthIndex, $endMonthIndex) {
        $months = ["january", "february", "march", "april", "may", "june", 
                   "july", "august", "september", "october", "november", "december"];
        
        $deleted = [];
        for ($i = $startMonthIndex; $i != $endMonthIndex; $i = ($i + 1) % 12) {
            $tableName = "wpk4_backend_travel_seats_stock_" . $months[$i];
            $sql = "DELETE FROM $tableName";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $deleted[] = $tableName;
        }
        return $deleted;
    }

    /**
     * Get trip dates within a date range
     */
    public function getTripDates($startDate, $endDate, $limit = 200) {
        $sql = "
            SELECT * 
            FROM wpk4_wt_dates 
            WHERE start_date BETWEEN :start_date AND :end_date 
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get trip itinerary type from postmeta
     */
    public function getTripItineraryType($tripId) {
        $sql = "
            SELECT * 
            FROM wpk4_postmeta 
            WHERE post_id = :trip_id 
            AND meta_key = '_yoast_wpseo_primary_itinerary_types'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':trip_id' => $tripId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['meta_value'] : null;
    }

    /**
     * Get trip code from postmeta
     */
    public function getTripCode($tripId) {
        $sql = "
            SELECT * 
            FROM wpk4_postmeta 
            WHERE post_id = :trip_id 
            AND meta_key = 'wp_travel_trip_code'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':trip_id' => $tripId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['meta_value'] : null;
    }

    /**
     * Get pricing information for a date and trip
     */
    public function getPricingForDate($tripId, $date) {
        $sql = "
            SELECT * 
            FROM wpk4_wt_dates 
            WHERE start_date = :date 
            AND trip_id = :trip_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date' => $date,
            ':trip_id' => $tripId
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get price from price category relation
     */
    public function getPriceFromCategory($pricingId, $pricingCategoryId = 953) {
        $sql = "
            SELECT * 
            FROM wpk4_wt_price_category_relation 
            WHERE pricing_id = :pricing_id 
            AND pricing_category_id = :pricing_category_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pricing_id' => $pricingId,
            ':pricing_category_id' => $pricingCategoryId
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get stock product manager data
     */
    public function getStockProductManager($tripId, $travelDate) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_product_manager 
            WHERE product_id = :trip_id 
            AND DATE(travel_date) = :travel_date
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':trip_id' => $tripId,
            ':travel_date' => $travelDate
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get stock count from stock management sheet
     */
    public function getStockCount($tripCode, $depDate) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_management_sheet 
            WHERE trip_id = :trip_code 
            AND DATE(dep_date) = :dep_date 
            ORDER BY dep_date ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':trip_code' => $tripCode,
            ':dep_date' => $depDate
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get booked pax count for a trip and date
     */
    public function getBookedPaxCount($tripId, $travelDate) {
        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE ((product_id = :trip_id AND new_product_id IS NULL) 
                OR (new_product_id = :trip_id)) 
            AND travel_date LIKE :travel_date_pattern 
            AND (payment_status = 'paid' OR payment_status = 'partially_paid')
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':trip_id' => $tripId,
            ':travel_date_pattern' => $travelDate . '%'
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert or update seat stock data
     */
    public function upsertSeatStock($tripId, $date, $airline, $startPlace, $endPlace, 
                                   $price, $seat, $addedOn, $travelType, $flight1, $flight2) {
        $month = (int)date('m', strtotime($date));
        $tableName = $this->getMonthTableName($month);
        
        // Check if record exists
        $checkSql = "
            SELECT * 
            FROM $tableName 
            WHERE trip_id = :trip_id 
            AND date = :date
        ";
        
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([
            ':trip_id' => $tripId,
            ':date' => $date
        ]);
        $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $sql = "
                UPDATE $tableName 
                SET airline = :airline,
                    start_place = :start_place,
                    end_place = :end_place,
                    price = :price,
                    seat = :seat,
                    added_on = :added_on,
                    travel_type = :travel_type,
                    flight_1 = :flight_1,
                    flight_2 = :flight_2
                WHERE trip_id = :trip_id 
                AND date = :date
            ";
        } else {
            // Insert new record
            $sql = "
                INSERT INTO $tableName 
                (trip_id, date, airline, start_place, end_place, price, seat, added_on, travel_type, flight_1, flight_2) 
                VALUES 
                (:trip_id, :date, :airline, :start_place, :end_place, :price, :seat, :added_on, :travel_type, :flight_1, :flight_2)
            ";
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':trip_id' => $tripId,
            ':date' => $date,
            ':airline' => $airline,
            ':start_place' => $startPlace,
            ':end_place' => $endPlace,
            ':price' => $price,
            ':seat' => $seat,
            ':added_on' => $addedOn,
            ':travel_type' => $travelType,
            ':flight_1' => $flight1,
            ':flight_2' => $flight2
        ]);
    }

    /**
     * Get trip metadata (itinerary type and trip code)
     */
    public function getTripMetadata($tripId) {
        $itineraryType = $this->getTripItineraryType($tripId);
        $tripCode = $this->getTripCode($tripId);
        
        return [
            'itinerary_type' => $itineraryType,
            'trip_code' => $tripCode
        ];
    }

    /**
     * Calculate remaining seats for a trip and date
     */
    public function calculateRemainingSeats($tripId, $travelDate) {
        // Get stock product manager
        $stockProduct = $this->getStockProductManager($tripId, $travelDate);
        
        if (!$stockProduct) {
            return null;
        }
        
        $tripCode = $stockProduct['trip_code'];
        
        // Get stock count
        $stockRows = $this->getStockCount($tripCode, $travelDate);
        $currentStockTotal = 0;
        foreach ($stockRows as $row) {
            $currentStockTotal += (int)$row['current_stock'];
        }
        
        // Get booked pax count
        $bookings = $this->getBookedPaxCount($tripId, $travelDate);
        $paxCount = 0;
        foreach ($bookings as $booking) {
            $paxCount += (int)$booking['total_pax'];
        }
        
        $remainingSeats = (int)$currentStockTotal - (int)$paxCount;
        
        return [
            'trip_code' => $tripCode,
            'current_stock_total' => $currentStockTotal,
            'booked_pax_count' => $paxCount,
            'remaining_seats' => $remainingSeats
        ];
    }
}

