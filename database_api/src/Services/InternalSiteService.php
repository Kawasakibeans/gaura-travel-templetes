<?php
/**
 * Internal Site Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\InternalSiteDAL;
use Exception;

class InternalSiteService
{
    private $internalDAL;
    
    public function __construct()
    {
        $this->internalDAL = new InternalSiteDAL();
    }
    
    /**
     * Get trip itinerary
     */
    public function getTripItinerary($tripId)
    {
        if (empty($tripId)) {
            throw new Exception('Trip ID is required', 400);
        }
        
        // Get post data
        $post = $this->internalDAL->getPostById($tripId);
        
        if (!$post) {
            throw new Exception('Trip not found', 404);
        }
        
        // Get itinerary data from post meta
        $itineraryData = $this->internalDAL->getPostMeta($tripId, 'wp_travel_trip_itinerary_data');
        
        if (empty($itineraryData) || !is_array($itineraryData)) {
            return [
                'trip_id' => $tripId,
                'trip_title' => $post['post_title'],
                'trip_name' => $post['post_name'],
                'itinerary' => []
            ];
        }
        
        // Format itinerary data
        $itinerary = [];
        $travelDateFixed = '0000-00-00 00:00:00';
        
        foreach ($itineraryData as $index => $item) {
            $label = stripslashes($item['label'] ?? '');
            $title = stripslashes($item['title'] ?? '');
            $desc = stripslashes($item['desc'] ?? '');
            $date = $item['date'] ?? '';
            $time = $item['time'] ?? '';
            
            // Format time
            $timeFormatted = '';
            if (!empty($time)) {
                $timeFormatted = date('g:i A', strtotime($time));
            }
            
            // Determine type
            $type = 'flight';
            if ($label === 'WAIT') {
                $type = 'wait';
            } elseif ($label === 'SELF-TRANSFER') {
                $type = 'self-transfer';
            }
            
            // Format date description
            $dateDesc = strip_tags($desc);
            if (strpos($dateDesc, 'Departure Date') !== false) {
                $dateDesc = 'Departure Date: ' . $dateDesc;
            } elseif (strpos($dateDesc, 'Arrival Date') !== false) {
                $dateDesc = 'Arrival Date: ' . $dateDesc;
            }
            
            $itinerary[] = [
                'index' => $index,
                'airport' => $label,
                'flight' => $title,
                'date' => $dateDesc,
                'time' => $timeFormatted,
                'type' => $type
            ];
        }
        
        return [
            'trip_id' => $tripId,
            'trip_title' => $post['post_title'],
            'trip_name' => $post['post_name'],
            'itinerary' => $itinerary
        ];
    }
}

