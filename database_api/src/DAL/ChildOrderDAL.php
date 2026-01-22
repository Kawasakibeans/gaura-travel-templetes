<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class ChildOrderDAL extends BaseDAL
{
    /**
     * Get child orders
     */
    public function getChildOrders(?string $tripCodePattern = null, ?string $startDate = null, ?string $endDate = null, int $limit = 1000, int $offset = 0): array
    {
        $todayYmd = date('Y-m-d');
        $untilYmd = $endDate ?? date('Y-m-d', strtotime('+30 days'));
        $startDateYmd = $startDate ?? $todayYmd;
        
        $tripCodePattern = $tripCodePattern ?? '%SQ%';
        
        $sql = "
            SELECT 
                b.order_id,
                b.order_date,
                b.order_type,
                b.trip_code,
                p.pnr,
                b.travel_date,
                b.payment_status,
                p.fname,
                p.lname,
                p.dob,
                p.email_pax,
                p.ticket_number,
                TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
            FROM wpk4_backend_travel_bookings b
            INNER JOIN wpk4_backend_travel_booking_pax p ON b.order_id = p.order_id 
                AND b.product_id = p.product_id 
                AND b.co_order_id = p.co_order_id
            WHERE 
                b.order_type = 'WPT'
                AND b.trip_code LIKE :trip_code_pattern
                AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 2 AND 12
                AND (p.ticket_number IS NULL OR p.ticket_number = '')
                AND b.travel_date BETWEEN :start_date AND :end_date
                AND b.payment_status = 'paid'
            ORDER BY b.travel_date ASC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";
        
        $params = [
            ':trip_code_pattern' => $tripCodePattern,
            ':start_date' => $startDateYmd,
            ':end_date' => $untilYmd
        ];
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get child orders count
     */
    public function getChildOrdersCount(?string $tripCodePattern = null, ?string $startDate = null, ?string $endDate = null): int
    {
        $todayYmd = date('Y-m-d');
        $untilYmd = $endDate ?? date('Y-m-d', strtotime('+30 days'));
        $startDateYmd = $startDate ?? $todayYmd;
        
        $tripCodePattern = $tripCodePattern ?? '%SQ%';
        
        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_backend_travel_bookings b
            INNER JOIN wpk4_backend_travel_booking_pax p ON b.order_id = p.order_id 
                AND b.product_id = p.product_id 
                AND b.co_order_id = p.co_order_id
            WHERE 
                b.order_type = 'WPT'
                AND b.trip_code LIKE :trip_code_pattern
                AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 2 AND 12
                AND (p.ticket_number IS NULL OR p.ticket_number = '')
                AND b.travel_date BETWEEN :start_date AND :end_date
                AND b.payment_status = 'paid'
        ";
        
        $params = [
            ':trip_code_pattern' => $tripCodePattern,
            ':start_date' => $startDateYmd,
            ':end_date' => $untilYmd
        ];
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }
}

