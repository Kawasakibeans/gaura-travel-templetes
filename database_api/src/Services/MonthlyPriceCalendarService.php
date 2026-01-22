<?php

namespace App\Services;

use App\DAL\MonthlyPriceCalendarDAL;

class MonthlyPriceCalendarService
{
    private $dal;

    public function __construct(MonthlyPriceCalendarDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get routes with departures and destinations
     * Line: 148-171 (in template)
     */
    public function getRoutes()
    {
        $endOfToday = date('Y-m-d') . ' 00:00:00';
        return $this->dal->getDepartures($endOfToday);
    }

    /**
     * Get monthly price calendar data
     * Line: 388-417 (in template)
     */
    public function getMonthlyPriceData($routeFrom, $routeTo, $month)
    {
        if (empty($routeFrom) || empty($routeTo)) {
            throw new \Exception('route_from and route_to are required', 400);
        }
        
        if (empty($month)) {
            throw new \Exception('month is required (format: YYYY-MM)', 400);
        }
        
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \Exception('Invalid month format. Use YYYY-MM', 400);
        }
        
        // Build route
        $route = $routeFrom . '-' . $routeTo;
        
        // Get first and last day of month
        $firstDay = $month . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay)); // Last day of month
        
        $data = $this->dal->getMonthlyPriceData($route, $firstDay, $lastDay);
        
        // Group by date for calendar view
        $groupedData = [];
        foreach ($data as $row) {
            $date = date('Y-m-d', strtotime($row['dep_date']));
            if (!isset($groupedData[$date])) {
                $groupedData[$date] = [];
            }
            $groupedData[$date][] = $row;
        }
        
        return [
            'route' => $route,
            'route_from' => $routeFrom,
            'route_to' => $routeTo,
            'month' => $month,
            'data' => $groupedData,
            'total_records' => count($data)
        ];
    }

    /**
     * Get airline codes
     * Line: 126-132 (in template)
     */
    public function getAirlineCodes()
    {
        $endOfToday = date('Y-m-d') . ' 00:00:00';
        return $this->dal->getAirlineCodes($endOfToday);
    }
}

