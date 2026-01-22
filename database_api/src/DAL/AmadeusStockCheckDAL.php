<?php
/**
 * Amadeus Stock Check Data Access Layer
 * Handles database operations for Amadeus stock check cronjob
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AmadeusStockCheckDAL extends BaseDAL
{
    /**
     * Get stock data for Amadeus check (Step 1)
     */
    public function getStockDataForCheck($startDate, $endDate)
    {
        $query = "
            SELECT 
                s.pnr AS pnr,
                s.trip_id AS trip_code,
                s.dep_date AS travel_date1,
                s.dep_date - INTERVAL 5 DAY AS ticketing_timelimit,
                s.oid AS oid,
                s.current_stock AS current_stock,
                COALESCE(b.paid_pax, 0) AS paid_pax,
                COALESCE(c.partially_paid_pax, 0) AS partially_paid_pax,
                COALESCE(b.paid_pax, 0) + COALESCE(c.partially_paid_pax, 0) AS total_pax_count
            FROM wpk4_backend_stock_management_sheet s
            LEFT JOIN (
                SELECT 
                    trip_code COLLATE utf8mb4_general_ci AS trip_code,
                    travel_date,
                    SUM(total_pax) AS paid_pax
                FROM wpk4_backend_travel_bookings
                WHERE payment_status = 'paid'
                GROUP BY trip_code, travel_date
            ) b
              ON s.trip_id COLLATE utf8mb4_general_ci = b.trip_code
             AND s.dep_date = b.travel_date
            LEFT JOIN (
                SELECT 
                    trip_code COLLATE utf8mb4_general_ci AS trip_code,
                    travel_date,
                    SUM(total_pax) AS partially_paid_pax
                FROM wpk4_backend_travel_bookings
                WHERE payment_status = 'partially_paid'
                GROUP BY trip_code, travel_date
            ) c
              ON s.trip_id COLLATE utf8mb4_general_ci = c.trip_code
             AND s.dep_date = c.travel_date
            WHERE s.dep_date BETWEEN :start_date AND :end_date
              AND s.airline_code IN ('MH', 'SQ')
              AND s.current_stock > 0
        ";
        
        return $this->query($query, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Upsert stock check record
     */
    public function upsertStockCheck($data)
    {
        $query = "
            INSERT INTO wpk4_amadeus_stock_check (
                pnr, trip_code, travel_date1, ticketing_timelimit, current_stock, oid,
                paid_pax, partially_paid_pax, total_pax_count
            ) VALUES (
                :pnr, :trip_code, :travel_date1, :ticketing_timelimit, :current_stock, :oid,
                :paid_pax, :partially_paid_pax, :total_pax_count
            )
            ON DUPLICATE KEY UPDATE
                ticketing_timelimit = VALUES(ticketing_timelimit),
                current_stock = VALUES(current_stock),
                oid = VALUES(oid),
                paid_pax = VALUES(paid_pax),
                partially_paid_pax = VALUES(partially_paid_pax),
                total_pax_count = VALUES(total_pax_count),
                added_date = CURRENT_TIMESTAMP
        ";
        
        return $this->execute($query, $data);
    }

    /**
     * Get segment 1 flight data
     */
    public function getSegment1Data($startDate, $endDate)
    {
        $query = "
            SELECT
                s.pnr AS pnr,
                s.trip_id AS trip_code,
                s.dep_date AS travel_date1,
                f.departure_time AS depart_time1,
                f.arrival_time AS arrival_time1,
                CONCAT(f.origin_city,'-',f.destination_city) AS segment1
            FROM wpk4_backend_stock_management_sheet AS s
            JOIN wpk4_backend_flight_segment_data AS f
              ON TRIM(UPPER(s.flight1)) = TRIM(UPPER(f.flight_no))
             AND UPPER(TRIM(LEFT(s.route, 3))) = UPPER(TRIM(f.origin_city))
             AND DATE(s.dep_date) BETWEEN DATE(f.date_from) AND DATE(COALESCE(f.date_to,'9999-12-31'))
            WHERE s.dep_date BETWEEN :start_date AND :end_date
              AND s.airline_code IN ('MH','SQ')
              AND s.current_stock > 0
        ";
        
        return $this->query($query, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Update segment 1 times
     */
    public function updateSegment1Times($tripCode, $travelDate, $pnr, $departTime, $arrivalTime, $segment)
    {
        $query = "
            UPDATE wpk4_amadeus_stock_check
            SET depart_time1 = :depart_time1,
                arrival_time1 = :arrival_time1,
                segment1 = :segment1,
                added_date = CURRENT_TIMESTAMP
            WHERE trip_code = :trip_code 
              AND travel_date1 = :travel_date1 
              AND pnr = :pnr
        ";
        
        return $this->execute($query, [
            'trip_code' => $tripCode,
            'travel_date1' => $travelDate,
            'pnr' => $pnr,
            'depart_time1' => $departTime,
            'arrival_time1' => $arrivalTime,
            'segment1' => $segment
        ]);
    }

    /**
     * Get segment 2 flight data
     */
    public function getSegment2Data($startDate, $endDate)
    {
        $query = "
            SELECT
                s.pnr,
                s.trip_id,
                s.dep_date AS travel_date1,
                f.departure_time AS depart_time2,
                f.arrival_time AS arrival_time2,
                CONCAT(f.origin_city,'-',f.destination_city) AS segment2
            FROM wpk4_backend_stock_management_sheet AS s
            JOIN wpk4_backend_flight_segment_data AS f
              ON TRIM(UPPER(s.flight2)) = TRIM(UPPER(f.flight_no))
             AND UPPER(TRIM(RIGHT(s.route,3))) = UPPER(TRIM(f.destination_city))
             AND DATE(s.dep_date) BETWEEN DATE(f.date_from) AND DATE(COALESCE(f.date_to,'9999-12-31'))
            WHERE s.dep_date BETWEEN :start_date AND :end_date
              AND s.airline_code IN ('MH','SQ')
              AND s.current_stock > 0
        ";
        
        return $this->query($query, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Update segment 2 times
     */
    public function updateSegment2Times($tripCode, $travelDate, $pnr, $departTime, $arrivalTime, $segment)
    {
        $query = "
            UPDATE wpk4_amadeus_stock_check
            SET depart_time2 = :depart_time2,
                arrival_time2 = :arrival_time2,
                segment2 = :segment2,
                added_date = CURRENT_TIMESTAMP
            WHERE trip_code = :trip_code 
              AND travel_date1 = :travel_date1 
              AND pnr = :pnr
        ";
        
        return $this->execute($query, [
            'trip_code' => $tripCode,
            'travel_date1' => $travelDate,
            'pnr' => $pnr,
            'depart_time2' => $departTime,
            'arrival_time2' => $arrivalTime,
            'segment2' => $segment
        ]);
    }
}

