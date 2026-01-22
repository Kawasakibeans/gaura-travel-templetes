<?php
/**
 * GDeals 108 Service
 * Handles business logic for GDeals 108 booking tracker
 */

namespace App\Services;

use App\DAL\GDeals108DAL;

class GDeals108Service
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new GDeals108DAL();
    }
    
    /**
     * Get booking tracker data
     */
    public function getBookingTracker(array $params): array
    {
        $days = isset($params['days']) ? (int)$params['days'] : 7;
        $days = max(0, min($days, 30)); // Limit to 0-30 days
        
        $dates = [];
        for ($i = 0; $i <= $days; $i++) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }
        
        $results = $this->dal->getBookingCountsForDates($dates);
        
        // Format results
        $formatted = [];
        foreach ($results as $result) {
            $date = $result['date'];
            $datePrinting = ($date === date('Y-m-d')) ? 'Today' : date('d/m/Y', strtotime($date));
            
            $formatted[] = [
                'date' => $date,
                'date_display' => $datePrinting,
                'total_pax' => $result['total_pax'],
                'reached_108' => $result['reached_108']
            ];
        }
        
        return [
            'tracker_data' => $formatted
        ];
    }
}

