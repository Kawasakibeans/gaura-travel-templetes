<?php

namespace App\DAL;

use PDO;

class MonthlyPriceCalendar2DAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get routes from stock management sheet
     * Line: 132-133 (in template)
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
     * Get monthly price calendar data with return route support
     * Based on backend call at line 498
     */
    public function getMonthlyPriceDataWithReturn($outboundRoute, $returnRoute, $outboundMonth, $returnMonth)
    {
        // Get outbound data
        $outboundData = $this->getMonthlyPriceData($outboundRoute, $outboundMonth);
        
        // Get return data if return route and month provided
        $returnData = [];
        if (!empty($returnRoute) && !empty($returnMonth)) {
            $returnData = $this->getMonthlyPriceData($returnRoute, $returnMonth);
        }
        
        return [
            'outbound' => $outboundData,
            'return' => $returnData
        ];
    }

    /**
     * Get monthly price calendar data for a route and month
     */
    private function getMonthlyPriceData($route, $month)
    {
        // Extract departure and destination from route (format: "FROM-TO")
        $routeParts = explode('-', $route);
        if (count($routeParts) !== 2) {
            return [];
        }
        
        $routeFrom = $routeParts[0];
        $routeTo = $routeParts[1];
        
        // Get first and last day of month
        $firstDay = $month . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));
        
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet 
                  WHERE route = :route 
                  AND dep_date >= :date_from 
                  AND dep_date <= :date_to 
                  ORDER BY dep_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':route', $route);
        $stmt->bindValue(':date_from', $firstDay . ' 00:00:00');
        $stmt->bindValue(':date_to', $lastDay . ' 23:59:59');
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

