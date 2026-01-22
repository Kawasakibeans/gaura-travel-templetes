<?php
/**
 * Seat Availability Data Access Layer
 * Handles database operations for seat availability queries
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class SeatAvailabilityDAL extends BaseDAL
{
    /**
     * Check IP address access
     */
    public function checkIpAddress($ipAddress)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_ip_address_checkup 
            WHERE ip_address = :ip_address
        ";
        
        $result = $this->query($query, ['ip_address' => $ipAddress]);
        return !empty($result);
    }

    /**
     * Get airlines from stock management sheet
     */
    public function getAirlines($excludeAirlines = ['FC', 'MH'], $endOfToday = null)
    {
        if ($endOfToday === null) {
            $endOfToday = date("Y-m-d") . ' 00:00:00';
        }
        
        $excludeCondition = '';
        if (!empty($excludeAirlines)) {
            $excludeList = "'" . implode("','", $excludeAirlines) . "'";
            $excludeCondition = "AND airline_code NOT IN ({$excludeList})";
        }
        
        $query = "
            SELECT DISTINCT airline_code 
            FROM wpk4_backend_stock_management_sheet 
            WHERE dep_date > :end_of_today 
            {$excludeCondition}
            ORDER BY airline_code ASC
        ";
        
        return $this->query($query, ['end_of_today' => $endOfToday]);
    }

    /**
     * Get routes by airline
     */
    public function getRoutesByAirline($airlineCode, $endOfToday = null)
    {
        if ($endOfToday === null) {
            $endOfToday = date("Y-m-d") . ' 00:00:00';
        }
        
        $query = "
            SELECT DISTINCT route 
            FROM wpk4_backend_stock_management_sheet 
            WHERE airline_code = :airline_code 
            AND dep_date > :end_of_today 
            ORDER BY route ASC
        ";
        
        return $this->query($query, [
            'airline_code' => $airlineCode,
            'end_of_today' => $endOfToday
        ]);
    }

    /**
     * Get all routes (for internal view)
     */
    public function getAllRoutes($endOfToday = null)
    {
        if ($endOfToday === null) {
            $endOfToday = date("Y-m-d") . ' 00:00:00';
        }
        
        $query = "
            SELECT DISTINCT route 
            FROM wpk4_backend_stock_management_sheet 
            WHERE dep_date > :end_of_today 
            ORDER BY route ASC
        ";
        
        return $this->query($query, ['end_of_today' => $endOfToday]);
    }

    /**
     * Get seat availability data with filters
     */
    public function getSeatAvailability($filters = [])
    {
        $airlineCode = $filters['airline_code'] ?? null;
        $route = $filters['route'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $endOfToday = $filters['end_of_today'] ?? date("Y-m-d") . ' 00:00:00';
        
        // Build WHERE conditions
        $conditions = [];
        $params = [];
        
        if ($airlineCode !== null && $airlineCode !== '' && $airlineCode !== 'NULL' && $airlineCode !== 'null') {
            $conditions[] = "airline_code = :airline_code";
            $params['airline_code'] = $airlineCode;
        } else {
            $conditions[] = "airline_code != 'TEMPAIRF'";
        }
        
        if ($route !== null && $route !== '' && $route !== 'NULL' && $route !== 'null') {
            $conditions[] = "route = :route";
            $params['route'] = $route;
        } else {
            $conditions[] = "airline_code != 'TEMPAIRF'";
        }
        
        if ($dateFrom !== null && $dateTo !== null && $dateFrom !== '' && $dateTo !== '') {
            $reldate1 = substr($dateFrom, 0, 10) . ' 00:00:00';
            $reldate2 = substr($dateTo, 25, 10) . ' 23:59:59';
            $conditions[] = "dep_date >= :date_from AND dep_date <= :date_to";
            $params['date_from'] = $reldate1;
            $params['date_to'] = $reldate2;
        } else {
            $conditions[] = "dep_date >= :end_of_today";
            $params['end_of_today'] = $endOfToday;
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $query = "
            SELECT * 
            FROM wpk4_backend_stock_management_sheet 
            WHERE {$whereClause}
            ORDER BY dep_date ASC
        ";
        
        return $this->query($query, $params);
    }

    /**
     * Get booked passengers count for a trip
     */
    public function getBookedPassengersCount($tripCode, $travelDate)
    {
        $query = "
            SELECT 
                COUNT(*) as order_count,
                SUM(total_pax) as pax_count
            FROM wpk4_backend_travel_bookings 
            WHERE trip_code = :trip_code 
            AND travel_date = :travel_date 
            AND (payment_status = 'paid' OR payment_status = 'partially_paid')
        ";
        
        $result = $this->query($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
        
        return !empty($result) ? [
            'order_count' => (int)($result[0]['order_count'] ?? 0),
            'pax_count' => (int)($result[0]['pax_count'] ?? 0)
        ] : ['order_count' => 0, 'pax_count' => 0];
    }

    /**
     * Get product information from stock product manager
     */
    public function getProductInfo($tripCode, $travelDate)
    {
        $travelDateFormatted = date('Y-m-d', strtotime($travelDate));
        
        $query = "
            SELECT * 
            FROM wpk4_backend_stock_product_manager 
            WHERE trip_code = :trip_code 
            AND travel_date = :travel_date
        ";
        
        $result = $this->query($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDateFormatted
        ]);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get product info by travel date with time
     */
    public function getProductInfoByDateWithTime($tripCode, $travelDate)
    {
        $travelDateWithTime = date('Y-m-d', strtotime($travelDate)) . ' 00:00:00';
        
        $query = "
            SELECT * 
            FROM wpk4_backend_stock_product_manager 
            WHERE trip_code = :trip_code 
            AND travel_date = :travel_date
        ";
        
        $result = $this->query($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDateWithTime
        ]);
        
        return !empty($result) ? $result : [];
    }

    /**
     * Get total current stock for a trip and date
     */
    public function getTotalCurrentStock($tripCode, $travelDate)
    {
        $travelDateFormatted = date('Y-m-d', strtotime($travelDate));
        
        $query = "
            SELECT SUM(current_stock) as total_stock 
            FROM wpk4_backend_stock_management_sheet 
            WHERE trip_id = :trip_code 
            AND dep_date LIKE :travel_date_pattern
        ";
        
        $result = $this->query($query, [
            'trip_code' => $tripCode,
            'travel_date_pattern' => $travelDateFormatted . '%'
        ]);
        
        return !empty($result) ? (int)($result[0]['total_stock'] ?? 0) : 0;
    }

    /**
     * Get sale prices by route
     */
    public function getSalePricesByRoute($route)
    {
        $query = "
            SELECT DISTINCT price.sale_price 
            FROM wpk4_wt_price_category_relation price 
            JOIN wpk4_backend_stock_product_manager stock 
                ON trip_code LIKE :route_pattern 
                AND DATE(stock.travel_date) >= CURRENT_DATE 
                AND stock.pricing_id = price.pricing_id 
            WHERE price.pricing_category_id = '954' 
            AND price.sale_price != '0' 
            ORDER BY CAST(price.sale_price AS UNSIGNED) ASC
        ";
        
        return $this->query($query, ['route_pattern' => $route . '%']);
    }

    /**
     * Get pricing information
     */
    public function getPricingInfo($pricingId, $categoryId)
    {
        $query = "
            SELECT * 
            FROM wpk4_wt_price_category_relation 
            WHERE pricing_id = :pricing_id 
            AND pricing_category_id = :category_id
        ";
        
        $result = $this->query($query, [
            'pricing_id' => $pricingId,
            'category_id' => $categoryId
        ]);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get adult pricing (category 954)
     */
    public function getAdultPricing($pricingId)
    {
        return $this->getPricingInfo($pricingId, '954');
    }

    /**
     * Get child pricing (category 953)
     */
    public function getChildPricing($pricingId)
    {
        return $this->getPricingInfo($pricingId, '953');
    }
}

