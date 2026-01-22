<?php
/**
 * Ticketing Service Layer
 * 
 * Encapsulates business logic for ticketing management
 */

namespace App\Services;

use App\DAL\TicketingDAL;

class TicketingService {
    private $dal;

    public function __construct(TicketingDAL $dal) {
        $this->dal = $dal;
    }

    /**
     * Get bookings with filters
     * 
     * @param array $filters Filter parameters
     * @return array Bookings
     */
    public function getBookings($filters = []) {
        return $this->dal->getBookings($filters);
    }

    /**
     * Update passenger ticketing information
     * 
     * @param int $paxAutoId Passenger auto ID
     * @param array $data Data to update
     * @param string $updatedUser Updated user
     * @return bool Success status
     */
    public function updatePaxTicketing($paxAutoId, $data, $updatedUser = 'system') {
        // Get current passenger data
        $currentPax = $this->dal->getPaxById($paxAutoId);
        
        if (!$currentPax) {
            throw new \Exception("Passenger not found with auto_id: $paxAutoId", 404);
        }
        
        $orderId = $currentPax['order_id'];
        $coOrderId = $currentPax['co_order_id'] ?? '';
        $productId = $currentPax['product_id'] ?? '';
        
        // Update the passenger record
        $success = $this->dal->updatePaxTicketing($paxAutoId, $data);
        
        if ($success) {
            // Insert history for each changed field
            $updatedTime = date('Y-m-d H:i:s');
            
            foreach ($data as $column => $value) {
                if (isset($currentPax[$column]) && $currentPax[$column] != $value) {
                    $this->dal->insertUpdateHistory([
                        'order_id' => $orderId,
                        'co_order_id' => $coOrderId,
                        'merging_id' => $productId,
                        'pax_auto_id' => $paxAutoId,
                        'meta_key' => $column,
                        'meta_value' => $value,
                        'updated_time' => $updatedTime,
                        'updated_user' => $updatedUser
                    ]);
                }
            }
        }
        
        return $success;
    }

    /**
     * Get distinct airlines
     * 
     * @return array Array of airlines
     */
    public function getAirlines() {
        return $this->dal->getAirlines();
    }

    /**
     * Get distinct trip codes
     * 
     * @return array Array of trip codes
     */
    public function getTripCodes() {
        return $this->dal->getTripCodes();
    }
}

