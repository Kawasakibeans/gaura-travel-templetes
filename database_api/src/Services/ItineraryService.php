<?php
/**
 * Itinerary Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\ItineraryDAL;
use Exception;

class ItineraryService
{
    private $itineraryDAL;

    public function __construct()
    {
        $this->itineraryDAL = new ItineraryDAL();
    }

    /**
     * Preview itinerary import
     */
    public function previewItinerary($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        $isHeader = true;
        
        foreach ($csvData as $row) {
            // Skip header row
            if ($isHeader) {
                $isHeader = false;
                continue;
            }
            
            // Skip rows with less than 14 columns
            if (count($row) < 14) {
                continue;
            }
            
            $tripCode = trim($row[0] ?? '');
            $travelMonth = trim($row[1] ?? '');
            $metaDesc = trim($row[2] ?? '');
            $itineraryData = trim($row[3] ?? '');
            $outline = trim($row[4] ?? '');
            $itineraryTypes = trim($row[5] ?? '');
            $travelLocations = trim($row[6] ?? '');
            $origin = trim($row[7] ?? '');
            $airline = trim($row[8] ?? '');
            $ticketType = trim($row[9] ?? '');
            $flightType = trim($row[10] ?? '');
            $status = trim($row[11] ?? '');
            $month = trim($row[12] ?? '');
            $tripExtra = trim($row[13] ?? '');
            
            if (empty($tripCode)) {
                continue; // Skip rows without trip_code
            }
            
            // Check if itinerary exists (using itinerary_table for preview)
            $existing = $this->itineraryDAL->checkItineraryExistsPreview($tripCode);
            $isExisting = ($existing !== null) ? 'Existing' : 'New';
            
            $preview[] = [
                'autonumber' => $autonumber,
                'trip_code' => $tripCode,
                'travel_month' => $travelMonth,
                'meta_desc' => $metaDesc,
                'itinerary_data' => $itineraryData,
                'outline' => $outline,
                'itinerary_types' => $itineraryTypes,
                'travel_locations' => $travelLocations,
                'origin' => $origin,
                'airline' => $airline,
                'ticket_type' => $ticketType,
                'flight_type' => $flightType,
                'status' => $status,
                'month' => $month,
                'trip_extra' => $tripExtra,
                'match_status' => $isExisting,
                'match' => $isExisting
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import itinerary records
     */
    public function importItinerary($records)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        
        foreach ($records as $record) {
            $tripCode = $record['trip_code'] ?? '';
            
            if (empty($tripCode)) {
                continue; // Skip invalid records
            }
            
            // Prepare itinerary data
            $itineraryData = [
                'trip_code' => $tripCode,
                'travel_month' => $record['travel_month'] ?? '',
                'meta_desc' => $record['meta_desc'] ?? '',
                'itinerary_data' => $record['itinerary_data'] ?? '',
                'outline' => $record['outline'] ?? '',
                'itinerary_types' => $record['itinerary_types'] ?? '',
                'travel_locations' => $record['travel_locations'] ?? '',
                'origin' => $record['origin'] ?? '',
                'airline' => $record['airline'] ?? '',
                'ticket_type' => $record['ticket_type'] ?? '',
                'flight_type' => $record['flight_type'] ?? '',
                'status' => $record['status'] ?? '',
                'month' => $record['month'] ?? '',
                'trip_extra' => $record['trip_extra'] ?? ''
            ];
            
            // Check if exists
            $existing = $this->itineraryDAL->checkItineraryExists($tripCode);
            
            if ($existing) {
                // Update existing
                $this->itineraryDAL->updateItinerary($itineraryData);
            } else {
                // Insert new
                $this->itineraryDAL->insertItinerary($itineraryData);
            }
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Itinerary imported successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Check itinerary by trip code and travel month
     */
    public function checkItinerary($tripCode, $travelMonth)
    {
        if (empty($tripCode) || empty($travelMonth)) {
            throw new Exception('Trip code and travel month are required', 400);
        }
        
        $results = $this->itineraryDAL->getItineraryByTripCodeAndMonth($tripCode, $travelMonth);
        
        return [
            'success' => true,
            'data' => $results
        ];
    }

    /**
     * Update product meta from itinerary data
     */
    public function updateProductMeta($productId, $itineraryData)
    {
        if (empty($productId)) {
            throw new Exception('Product ID is required', 400);
        }
        
        if (empty($itineraryData) || !is_array($itineraryData)) {
            throw new Exception('Itinerary data is required', 400);
        }
        
        // Define meta keys to update
        $metaKeys = [
            '_yoast_wpseo_metadesc' => $itineraryData['_yoast_wpseo_metadesc'] ?? '',
            'wp_travel_trip_itinerary_data' => $itineraryData['wp_travel_trip_itinerary_data'] ?? '',
            'wp_travel_outline' => $itineraryData['wp_travel_outline'] ?? '',
            '_yoast_wpseo_primary_itinerary_types' => $itineraryData['_yoast_wpseo_primary_itinerary_types'] ?? '',
            '_yoast_wpseo_primary_travel_locations' => $itineraryData['_yoast_wpseo_primary_travel_locations'] ?? '',
            '_yoast_wpseo_primary_origin' => $itineraryData['_yoast_wpseo_primary_origin'] ?? '',
            '_yoast_wpseo_primary_airline' => $itineraryData['_yoast_wpseo_primary_airline'] ?? '',
            '_yoast_wpseo_primary_ticket_type' => $itineraryData['_yoast_wpseo_primary_ticket_type'] ?? '',
            '_yoast_wpseo_primary_flight_type' => $itineraryData['_yoast_wpseo_primary_flight_type'] ?? '',
            '_yoast_wpseo_primary_status' => $itineraryData['_yoast_wpseo_primary_status'] ?? '',
            '_yoast_wpseo_primary_month' => $itineraryData['_yoast_wpseo_primary_month'] ?? '',
            'trip_extra' => $itineraryData['trip_extra'] ?? ''
        ];
        
        $updatedCount = 0;
        
        foreach ($metaKeys as $metaKey => $metaValue) {
            // Skip empty values
            if ($metaValue === '') {
                continue;
            }
            
            // Check if meta exists
            $existing = $this->itineraryDAL->checkPostMetaExists($productId, $metaKey);
            
            if ($existing) {
                // Update existing meta
                $this->itineraryDAL->updatePostMeta($productId, $metaKey, $metaValue);
            } else {
                // Insert new meta
                $this->itineraryDAL->insertPostMeta($productId, $metaKey, $metaValue);
            }
            
            $updatedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Product ID and metadata updated successfully',
            'updated_count' => $updatedCount
        ];
    }
}

