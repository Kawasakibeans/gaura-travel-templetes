<?php
/**
 * GDeals 108 Data Access Layer
 * Handles database operations for GDeals 108 booking tracker
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GDeals108DAL extends BaseDAL
{
    /**
     * Get booking count for a specific date
     */
    public function getBookingCountForDate(string $date): int
    {
        try {
            $dateStart = $date . ' 00:00:00';
            $dateEnd = $date . ' 23:59:59';
            
            $sql = "
                SELECT COUNT(DISTINCT booking.order_id) as total_pax 
                FROM wpk4_backend_travel_bookings as booking 
                JOIN wpk4_backend_travel_booking_pax as pax ON booking.order_id = pax.order_id
                WHERE booking.order_date >= :date_start 
                AND booking.order_date <= :date_end 
                AND (booking.order_type = '' OR booking.order_type = 'WPT') 
                GROUP BY pax.fname, pax.lname, pax.dob
            ";
            
            $results = $this->query($sql, [
                ':date_start' => $dateStart,
                ':date_end' => $dateEnd
            ]);
            
            $totalPax = 0;
            foreach ($results as $row) {
                $totalPax += (int)($row['total_pax'] ?? 0);
            }
            
            return $totalPax;
        } catch (\Exception $e) {
            error_log("GDeals108DAL::getBookingCountForDate error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get booking counts for multiple dates
     */
    public function getBookingCountsForDates(array $dates): array
    {
        $results = [];
        
        foreach ($dates as $date) {
            $count = $this->getBookingCountForDate($date);
            $results[] = [
                'date' => $date,
                'total_pax' => $count,
                'reached_108' => $count >= 108
            ];
        }
        
        return $results;
    }
}

