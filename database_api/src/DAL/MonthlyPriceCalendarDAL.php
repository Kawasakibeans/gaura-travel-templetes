<?php

namespace App\DAL;

use PDO;

class MonthlyPriceCalendarDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get routes from stock management sheet
     * Line: 148-149 (in template)
     */
    public function getRoutes($endOfToday)
    {
        $query = "SELECT DISTINCT route, airline_code 
                  FROM wpk4_backend_stock_management_sheet 
                  WHERE dep_date > :end_of_today 
                  ORDER BY route ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':end_of_today', $endOfToday);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unique airline codes
     * Line: 126-127 (in template)
     */
    public function getAirlineCodes($endOfToday)
    {
        $query = "SELECT DISTINCT airline_code 
                  FROM wpk4_backend_stock_management_sheet 
                  WHERE dep_date > :end_of_today 
                  ORDER BY airline_code ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':end_of_today', $endOfToday);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $codes = [];
        foreach ($results as $row) {
            $codes[] = $row['airline_code'];
        }
        return array_unique($codes);
    }

    /**
     * Get monthly price calendar data for a route and month
     * Based on backend call at line 415
     */
    public function getMonthlyPriceData($route, $dateFrom, $dateTo)
    {
        // Extract departure and destination from route (format: "FROM-TO")
        $routeParts = explode('-', $route);
        if (count($routeParts) !== 2) {
            return [];
        }
        
        $routeFrom = $routeParts[0];
        $routeTo = $routeParts[1];
        
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet 
                  WHERE route LIKE :route_pattern 
                  AND dep_date >= :date_from 
                  AND dep_date <= :date_to 
                  ORDER BY dep_date ASC";
        
        $routePattern = $routeFrom . '-' . $routeTo;
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':route_pattern', $routePattern);
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00');
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get departures (unique first part of route)
     */
    public function getDepartures($endOfToday)
    {
        $routes = $this->getRoutes($endOfToday);
        
        $departures = [];
        $destinations = [];
        
        foreach ($routes as $routeData) {
            $route = $routeData['route'];
            if (strpos($route, '-') !== false) {
                list($from, $to) = explode('-', $route, 2);
                $departures[$from] = $from;
                if (!isset($destinations[$from])) {
                    $destinations[$from] = [];
                }
                if (!in_array($to, $destinations[$from])) {
                    $destinations[$from][] = $to;
                }
            }
        }
        
        return [
            'departures' => array_values($departures),
            'destinations' => $destinations
        ];
    }
}

