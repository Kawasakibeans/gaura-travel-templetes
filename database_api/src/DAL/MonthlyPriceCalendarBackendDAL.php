<?php

namespace App\DAL;

use PDO;

class MonthlyPriceCalendarBackendDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get routes by airline code
     * Line: 11 (in template)
     */
    public function getRoutesByAirline($airlineCode, $endOfToday)
    {
        $query = "SELECT DISTINCT route 
                  FROM wpk4_backend_stock_management_sheet 
                  WHERE airline_code = :airline_code 
                  AND dep_date > :end_of_today 
                  ORDER BY route ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':airline_code', $airlineCode);
        $stmt->bindValue(':end_of_today', $endOfToday);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $routes = [];
        foreach ($results as $row) {
            $routes[] = $row['route'];
        }
        return array_unique($routes);
    }

    /**
     * Get sale prices by route
     * Line: 60-64 (in template)
     */
    public function getSalePricesByRoute($route)
    {
        $query = "SELECT DISTINCT price.sale_price 
                  FROM wpk4_wt_price_category_relation price 
                  JOIN wpk4_backend_stock_product_manager stock 
                      ON stock.trip_code LIKE :route_pattern 
                      AND DATE(stock.travel_date) >= CURRENT_DATE 
                      AND stock.pricing_id = price.pricing_id 
                  WHERE price.pricing_category_id = '954' 
                  AND price.sale_price != '0' 
                  ORDER BY CAST(price.sale_price AS UNSIGNED) ASC";
        
        $routePattern = $route . '%';
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':route_pattern', $routePattern);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prices = [];
        foreach ($results as $row) {
            $prices[] = $row['sale_price'];
        }
        return array_unique($prices);
    }

    /**
     * Get trips for calendar with stock and booking data
     * Line: 176-346 (in template)
     */
    public function getTripsForCalendar($route, $dateFrom, $dateTo, $airlineCode = '')
    {
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet 
                  WHERE route = :route 
                  AND dep_date >= :date_from 
                  AND dep_date <= :date_to";
        
        if (!empty($airlineCode)) {
            $query .= " AND airline_code = :airline_code";
        } else {
            $query .= " AND airline_code != 'TEMPAIRF'";
        }
        
        $query .= " ORDER BY dep_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':route', $route);
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00');
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
        
        if (!empty($airlineCode)) {
            $stmt->bindValue(':airline_code', $airlineCode);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product information by trip code and date
     * Line: 215, 276, 294 (in template)
     */
    public function getProductInfo($tripCode, $travelDate)
    {
        $query = "SELECT * FROM wpk4_backend_stock_product_manager 
                  WHERE trip_code = :trip_code 
                  AND travel_date = :travel_date 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':trip_code', $tripCode);
        $stmt->bindValue(':travel_date', $travelDate);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get total current stock for a trip and date
     * Line: 224-230 (in template)
     */
    public function getTotalCurrentStock($tripCode, $travelDate)
    {
        $query = "SELECT SUM(current_stock) as total_stock 
                  FROM wpk4_backend_stock_management_sheet 
                  WHERE trip_id = :trip_id 
                  AND dep_date LIKE :date_pattern";
        
        $datePattern = $travelDate . '%';
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':trip_id', $tripCode);
        $stmt->bindValue(':date_pattern', $datePattern);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total_stock'] : 0;
    }

    /**
     * Get booked passengers count for a trip and date
     * Line: 233-241 (in template)
     */
    public function getBookedPassengersCount($tripCode, $travelDate)
    {
        $query = "SELECT SUM(total_pax) as total_pax 
                  FROM wpk4_backend_travel_bookings 
                  WHERE trip_code = :trip_code 
                  AND travel_date = :travel_date 
                  AND (payment_status = 'paid' OR payment_status = 'partially_paid')";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':trip_code', $tripCode);
        $stmt->bindValue(':travel_date', $travelDate);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total_pax'] : 0;
    }

    /**
     * Get adult rate by pricing ID
     * Line: 282-286 (in template)
     */
    public function getAdultRate($pricingId)
    {
        $query = "SELECT sale_price 
                  FROM wpk4_wt_price_category_relation 
                  WHERE pricing_id = :pricing_id 
                  AND pricing_category_id = '954' 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pricing_id', $pricingId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['sale_price'] : 0;
    }

    /**
     * Get child rate by pricing ID
     * Line: 288-292 (in template)
     */
    public function getChildRate($pricingId)
    {
        $query = "SELECT sale_price 
                  FROM wpk4_wt_price_category_relation 
                  WHERE pricing_id = :pricing_id 
                  AND pricing_category_id = '953' 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':pricing_id', $pricingId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['sale_price'] : 0;
    }

    /**
     * Get seat availability data
     * Line: 302-307 (in template)
     */
    public function getSeatAvailability($tripCode, $travelDate)
    {
        $query = "SELECT * FROM wpk4_backend_manage_seat_availability 
                  WHERE trip_code = :trip_code 
                  AND DATE(travel_date) = :travel_date 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':trip_code', $tripCode);
        $stmt->bindValue(':travel_date', $travelDate);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

