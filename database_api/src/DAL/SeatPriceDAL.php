<?php
/**
 * Seat and Price Data Access Layer (DAL)
 * 
 * Handles all database operations for seat and price management
 */

namespace App\DAL;

class SeatPriceDAL {
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
     * Get distinct trip IDs with filters
     */
    public function getTripIds($year, $month, $filters = []) {
        $tableName = $this->getMonthTableName($month);
        
        $where = [];
        $params = [];
        
        // Year and month filters
        $where[] = "YEAR(date) = :year";
        $where[] = "MONTH(date) = :month";
        $params[':year'] = $year;
        $params[':month'] = $month;
        
        // Travel type filter
        if (!empty($filters['travel_type'])) {
            $where[] = "travel_type = :travel_type";
            $params[':travel_type'] = $filters['travel_type'];
        }
        
        // Airline filter
        if (!empty($filters['airline'])) {
            $where[] = "airline = :airline";
            $params[':airline'] = $filters['airline'];
        } else if (!empty($filters['search_type']) && $filters['search_type'] === 'all') {
            // For 'all' search type, exclude QF
            $where[] = "airline != 'QF'";
        } else if (!empty($filters['search_type']) && $filters['search_type'] === 'qf') {
            // For 'qf' search type, only include QF
            $where[] = "airline = 'QF'";
        }
        
        // From location filter
        if (!empty($filters['from_location'])) {
            $where[] = "start_place = :from_location";
            $params[':from_location'] = $filters['from_location'];
        }
        
        // To location filter
        if (!empty($filters['to_location'])) {
            $where[] = "end_place = :to_location";
            $params[':to_location'] = $filters['to_location'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "
            SELECT DISTINCT 
                trip_id, 
                airline, 
                start_place, 
                end_place, 
                travel_type, 
                added_on, 
                flight_1, 
                flight_2 
            FROM $tableName 
            $whereClause
            ORDER BY airline, start_place, end_place, travel_type
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get price and seat data for a specific date and trip
     */
    public function getPriceAndSeat($year, $month, $day, $tripId) {
        $tableName = $this->getMonthTableName($month);
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        
        $sql = "
            SELECT price, seat 
            FROM $tableName 
            WHERE date = :date AND trip_id = :trip_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date' => $date,
            ':trip_id' => $tripId
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct airlines from table
     */
    public function getDistinctAirlines($month) {
        $tableName = $this->getMonthTableName($month);
        
        $sql = "
            SELECT DISTINCT airline 
            FROM $tableName 
            WHERE airline IS NOT NULL AND airline != ''
            ORDER BY airline
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'airline');
    }

    /**
     * Get distinct start places from table
     */
    public function getDistinctStartPlaces($month) {
        $tableName = $this->getMonthTableName($month);
        
        $sql = "
            SELECT DISTINCT start_place 
            FROM $tableName 
            WHERE start_place IS NOT NULL AND start_place != ''
            ORDER BY start_place
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'start_place');
    }

    /**
     * Get distinct end places from table
     */
    public function getDistinctEndPlaces($month) {
        $tableName = $this->getMonthTableName($month);
        
        $sql = "
            SELECT DISTINCT end_place 
            FROM $tableName 
            WHERE end_place IS NOT NULL AND end_place != ''
            ORDER BY end_place
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'end_place');
    }

    /**
     * Get distinct trip IDs for Qantas (QF) airline only
     */
    public function getQantasTripIds($year, $month, $filters = []) {
        $tableName = $this->getMonthTableName($month);
        
        $where = [];
        $params = [];
        
        // Year and month filters
        $where[] = "YEAR(date) = :year";
        $where[] = "MONTH(date) = :month";
        $params[':year'] = $year;
        $params[':month'] = $month;
        
        // Always filter for QF airline
        $where[] = "airline = 'QF'";
        
        // Travel type filter
        if (!empty($filters['travel_type'])) {
            $where[] = "travel_type = :travel_type";
            $params[':travel_type'] = $filters['travel_type'];
        }
        
        // From location filter
        if (!empty($filters['from_location'])) {
            $where[] = "start_place = :from_location";
            $params[':from_location'] = $filters['from_location'];
        }
        
        // To location filter
        if (!empty($filters['to_location'])) {
            $where[] = "end_place = :to_location";
            $params[':to_location'] = $filters['to_location'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "
            SELECT DISTINCT 
                trip_id, 
                airline, 
                start_place, 
                end_place, 
                travel_type, 
                added_on
            FROM $tableName 
            $whereClause
            ORDER BY start_place, end_place, travel_type
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct start places for Qantas (QF) airline only
     */
    public function getQantasDistinctStartPlaces($month) {
        $tableName = $this->getMonthTableName($month);
        
        $sql = "
            SELECT DISTINCT start_place 
            FROM $tableName 
            WHERE airline = 'QF' 
            AND start_place IS NOT NULL 
            AND start_place != ''
            ORDER BY start_place
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'start_place');
    }

    /**
     * Get distinct end places for Qantas (QF) airline only
     */
    public function getQantasDistinctEndPlaces($month) {
        $tableName = $this->getMonthTableName($month);
        
        $sql = "
            SELECT DISTINCT end_place 
            FROM $tableName 
            WHERE airline = 'QF' 
            AND end_place IS NOT NULL 
            AND end_place != ''
            ORDER BY end_place
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'end_place');
    }
}

