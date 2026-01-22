<?php
/**
 * Soldout Updater Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\SoldoutUpdaterDAL;
use Exception;

class SoldoutUpdaterService
{
    private $soldoutDAL;
    
    public function __construct()
    {
        $this->soldoutDAL = new SoldoutUpdaterDAL();
    }
    
    /**
     * Update soldout status
     * Scenario 1: Add sold out trips to exclude table
     * Scenario 2: Remove available trips from exclude table
     */
    public function updateSoldoutStatus($excludedPostIds = [60107, 60116])
    {
        try {
            // Validate excluded post IDs
            if (!is_array($excludedPostIds)) {
                $excludedPostIds = [60107, 60116];
            }
            
            // Ensure all IDs are integers
            $excludedPostIds = array_map('intval', $excludedPostIds);
            
            $scenario1Processed = 0;
            $scenario1Added = 0;
            $scenario2Processed = 0;
            $scenario2Removed = 0;
            
            // Begin transaction
            $this->soldoutDAL->beginTransaction();
            
            try {
                // Scenario 1: Find sold out trips and add to exclude if not already excluded
                $soldOutTrips = $this->soldoutDAL->getSoldOutTrips($excludedPostIds);
                
                foreach ($soldOutTrips as $trip) {
                    $scenario1Processed++;
                    $tripId = $trip['trip_id'];
                    $pricingId = $trip['id'];
                    $startDate = $trip['start_date'];
                    
                    // Check if already excluded
                    if (!$this->soldoutDAL->isExcluded($tripId, $startDate)) {
                        // Get product title
                        $dateInfo = $this->soldoutDAL->getProductTitle($tripId, $pricingId);
                        $productTitle = $dateInfo['title'] ?? '';
                        
                        // Add to exclude table
                        $this->soldoutDAL->addToExclude($tripId, $productTitle, $startDate);
                        $scenario1Added++;
                    }
                }
                
                // Scenario 2: Find trips with availability and remove from exclude if exists
                $availableTrips = $this->soldoutDAL->getAvailableTrips($excludedPostIds);
                
                foreach ($availableTrips as $trip) {
                    $scenario2Processed++;
                    $tripId = $trip['trip_id'];
                    $startDate = $trip['start_date'];
                    
                    // Check if excluded
                    if ($this->soldoutDAL->isExcluded($tripId, $startDate)) {
                        // Remove from exclude table
                        $this->soldoutDAL->removeFromExclude($tripId, $startDate);
                        $scenario2Removed++;
                    }
                }
                
                // Commit transaction
                $this->soldoutDAL->commit();
                
                return [
                    'success' => true,
                    'message' => 'Soldout status updated successfully',
                    'scenario1' => [
                        'processed' => $scenario1Processed,
                        'added_to_exclude' => $scenario1Added
                    ],
                    'scenario2' => [
                        'processed' => $scenario2Processed,
                        'removed_from_exclude' => $scenario2Removed
                    ]
                ];
            } catch (Exception $e) {
                // Rollback on error
                $this->soldoutDAL->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("SoldoutUpdaterService::updateSoldoutStatus error: " . $e->getMessage());
            throw new Exception("Failed to update soldout status: " . $e->getMessage(), 500);
        }
    }
}

