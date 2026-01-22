<?php

namespace App\Services;

use App\DAL\MonthlyPriceCalendar2DAL;

class MonthlyPriceCalendar2Service
{
    private $dal;

    public function __construct(MonthlyPriceCalendar2DAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get routes with departures and destinations
     * Line: 132-155 (in template)
     */
    public function getRoutes()
    {
        $endOfToday = date('Y-m-d') . ' 00:00:00';
        return $this->dal->getDepartures($endOfToday);
    }

    /**
     * Get monthly price calendar data with return route support
     * Line: 458-500 (in template)
     */
    public function getMonthlyPriceDataWithReturn($routeFrom, $routeTo, $outboundMonth, $returnMonth = '')
    {
        if (empty($routeFrom) || empty($routeTo)) {
            throw new \Exception('route_from and route_to are required', 400);
        }
        
        if (empty($outboundMonth)) {
            throw new \Exception('outbound_month is required (format: YYYY-MM)', 400);
        }
        
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $outboundMonth)) {
            throw new \Exception('Invalid outbound_month format. Use YYYY-MM', 400);
        }
        
        // Build routes
        $outboundRoute = $routeFrom . '-' . $routeTo;
        $returnRoute = $routeTo . '-' . $routeFrom; // Reverse route for return
        
        // Get data
        $data = $this->dal->getMonthlyPriceDataWithReturn(
            $outboundRoute,
            $returnRoute,
            $outboundMonth,
            $returnMonth
        );
        
        // Group by date for calendar view
        $groupedOutbound = [];
        foreach ($data['outbound'] as $row) {
            $date = date('Y-m-d', strtotime($row['dep_date']));
            if (!isset($groupedOutbound[$date])) {
                $groupedOutbound[$date] = [];
            }
            $groupedOutbound[$date][] = $row;
        }
        
        $groupedReturn = [];
        foreach ($data['return'] as $row) {
            $date = date('Y-m-d', strtotime($row['dep_date']));
            if (!isset($groupedReturn[$date])) {
                $groupedReturn[$date] = [];
            }
            $groupedReturn[$date][] = $row;
        }
        
        return [
            'outbound' => [
                'route' => $outboundRoute,
                'route_from' => $routeFrom,
                'route_to' => $routeTo,
                'month' => $outboundMonth,
                'data' => $groupedOutbound,
                'total_records' => count($data['outbound'])
            ],
            'return' => [
                'route' => $returnRoute,
                'route_from' => $routeTo,
                'route_to' => $routeFrom,
                'month' => $returnMonth,
                'data' => $groupedReturn,
                'total_records' => count($data['return'])
            ]
        ];
    }
}

