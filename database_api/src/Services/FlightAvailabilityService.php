<?php

namespace App\Services;

use App\DAL\FlightAvailabilityDAL;
use Exception;

class FlightAvailabilityService
{
    private $flightAvailabilityDAL;
    
    public function __construct()
    {
        $this->flightAvailabilityDAL = new FlightAvailabilityDAL();
    }
    
    /**
     * Save flight availability check
     */
    public function saveAvailabilityCheck($data)
    {
        // Validate required fields
        $required = ['depart_apt', 'dest_apt', 'depart_date', 'sessionId', 'tarifId'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }
        
        // Format dates
        if (isset($data['depart_date'])) {
            $departDate = \DateTime::createFromFormat('d-m-Y', $data['depart_date']);
            if ($departDate === false) {
                throw new Exception("Invalid depart_date format. Expected d-m-Y", 400);
            }
            $data['depart_date'] = $departDate->format('Y-m-d');
        }
        
        if (isset($data['return_date']) && !empty($data['return_date'])) {
            $returnDate = \DateTime::createFromFormat('d-m-Y', $data['return_date']);
            if ($returnDate === false) {
                throw new Exception("Invalid return_date format. Expected d-m-Y", 400);
            }
            $data['return_date'] = $returnDate->format('Y-m-d');
        } else {
            $data['return_date'] = null;
        }
        
        // Prepare data for insertion
        $insertData = [
            'user_id' => $data['user_id'] ?? null,
            'depart_apt' => $data['depart_apt'],
            'dest_apt' => $data['dest_apt'],
            'outbound_seat' => $data['outbound_seat'] ?? null,
            'return_seat' => $data['return_seat'] ?? null,
            'depart_date' => $data['depart_date'],
            'return_date' => $data['return_date'],
            'airline' => $data['flightName'] ?? null,
            'tariffId' => $data['tarifId'],
            'session_id' => $data['sessionId']
        ];
        
        // Insert main record
        $availId = $this->flightAvailabilityDAL->insertAvailabilityCheck($insertData);
        
        if (!$availId) {
            throw new Exception("Failed to save availability check", 500);
        }
        
        // Process and insert legs if apiData is provided
        $legsInserted = 0;
        if (isset($data['apiData'])) {
            $apiData = $data['apiData'];
            
            // Parse JSON if string
            if (is_string($apiData)) {
                $apiData = json_decode(stripslashes($apiData), true);
                if (isset($apiData['legs']) && is_string($apiData['legs'])) {
                    $apiData['legs'] = json_decode($apiData['legs'], true);
                }
            }
            
            // Extract valid leg IDs from fareXRefs
            $validLegIds = [];
            $outboundFlightId = $data['outboundFlightId'] ?? null;
            $returnFlightId = $data['returnFlightId'] ?? null;
            
            if (isset($apiData['tarifs']['tarif']['fareXRefs']['fareXRef'])) {
                $fareXRefs = $apiData['tarifs']['tarif']['fareXRefs']['fareXRef'];
                
                // Make sure it's an array
                if (isset($fareXRefs['fareId'])) {
                    $fareXRefs = [$fareXRefs];
                }
                
                foreach ($fareXRefs as $fareXRef) {
                    if (!isset($fareXRef['flights']) || !is_array($fareXRef['flights'])) {
                        continue;
                    }
                    
                    foreach ($fareXRef['flights'] as $flight) {
                        if (
                            ($outboundFlightId && $flight['flightId'] == $outboundFlightId) ||
                            ($returnFlightId && $flight['flightId'] == $returnFlightId)
                        ) {
                            if (isset($flight['legXRefs']) && is_array($flight['legXRefs'])) {
                                foreach ($flight['legXRefs'] as $legXref) {
                                    $validLegIds[] = $legXref['legId'];
                                }
                            }
                        }
                    }
                }
            }
            
            // Insert leg records
            if (isset($apiData['legs']['leg']) && is_array($apiData['legs']['leg'])) {
                foreach ($apiData['legs']['leg'] as $leg) {
                    if (!isset($leg['legId']) || !in_array($leg['legId'], $validLegIds)) {
                        continue;
                    }
                    
                    $this->flightAvailabilityDAL->insertAvailabilityLeg($availId, $leg);
                    $legsInserted++;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Availability and legs saved successfully',
            'id' => $availId,
            'legs_inserted' => $legsInserted
        ];
    }
    
    /**
     * Get availability check with legs
     */
    public function getAvailabilityCheck($id)
    {
        $check = $this->flightAvailabilityDAL->getAvailabilityCheckById($id);
        
        if (!$check) {
            throw new Exception("Availability check not found", 404);
        }
        
        $legs = $this->flightAvailabilityDAL->getAvailabilityLegs($id);
        
        return [
            'availability_check' => $check,
            'legs' => $legs
        ];
    }
}

