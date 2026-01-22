<?php

namespace App\Services;

use App\DAL\FlightAvailabilityDAL;
use Exception;

class FlightAvailabilityCheckService
{
    private $ypsilonEndpoint;
    private $ypsilonVersion;
    private $ypsilonAuth;
    private $flightAvailabilityDAL;
    
    public function __construct()
    {
        // These should be loaded from config or environment variables
        $this->ypsilonEndpoint = 'http://xmlapiv3.ypsilon.net:10816';
        $this->ypsilonVersion = '3.92';
        $this->ypsilonAuth = 'c2hlbGx0ZWNoOjRlNDllOTAxMGZhYzA1NzEzN2VjOWQ0NWZjNTFmNDdh';
        $this->flightAvailabilityDAL = new FlightAvailabilityDAL();
    }
    
    /**
     * Check flight availability via Ypsilon API
     */
    public function checkAvailability($sessionId, $tarifId, $outboundFlightId = null, $returnFlightId = null)
    {
        if (empty($sessionId) || empty($tarifId)) {
            throw new Exception("sessionId and tarifId are required", 400);
        }
        
        // Build XML request
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<availRequest tarifId="' . htmlspecialchars($tarifId, ENT_QUOTES) . '"/>';
        
        $contentLength = strlen($xml);
        
        // Send to Ypsilon via cURL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->ypsilonEndpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'accept: application/xml',
                'accept-encoding: gzip',
                'api-version: ' . $this->ypsilonVersion,
                'accessmode: agency',
                'Session: ' . $sessionId,
                'accessid: gaura gaura',
                'authmode: pwd',
                'authorization: Basic ' . $this->ypsilonAuth,
                'Connection: close',
                'Content-Type: application/xml',
                'Content-Length: ' . $contentLength,
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($err) {
            throw new Exception("cURL Error: " . $err, 500);
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: " . $httpCode, $httpCode);
        }
        
        // Convert XML to JSON
        $jsonData = $this->xmlToJson($response);
        
        // Error handling
        if (isset($jsonData['@attributes']['no'])) {
            throw new Exception($jsonData['0'] ?? 'Unknown error', $jsonData['@attributes']['no']);
        }
        
        if ($jsonData === null) {
            throw new Exception("Failed to parse XML response", 500);
        }
        
        // Clean up the JSON structure
        $cleanedData = $this->cleanXmlArray($jsonData);
        $cleanedData['success'] = isset($cleanedData) && !empty($cleanedData);
        
        // Add minimum available seats from specified flights
        $flightSeats = $this->getMinimumSeatsFromFlights($cleanedData, $outboundFlightId, $returnFlightId);
        if (!empty($flightSeats)) {
            $cleanedData['minimumAvailableSeats'] = $flightSeats;
        }
        
        // Save check record to database
        $availId = $this->flightAvailabilityDAL->insertAvailabilityCheckFromYpsilon(
            $sessionId,
            $tarifId,
            $outboundFlightId,
            $returnFlightId
        );
        
        // Extract valid leg IDs from fareXRefs
        $validLegIds = [];
        if (isset($cleanedData['tarifs']) && is_array($cleanedData['tarifs'])) {
            foreach ($cleanedData['tarifs'] as $tarif) {
                if (isset($tarif['fareXRefs']) && is_array($tarif['fareXRefs'])) {
                    $fareXRefs = $tarif['fareXRefs'];
                    
                    // Handle single fareXRef or array
                    if (isset($fareXRefs['fareXRef'])) {
                        $fareXRefs = $fareXRefs['fareXRef'];
                    }
                    if (isset($fareXRefs['fareId'])) {
                        $fareXRefs = [$fareXRefs];
                    }
                    
                    foreach ($fareXRefs as $fareXRef) {
                        if (!isset($fareXRef['flights']) || !is_array($fareXRef['flights'])) {
                            continue;
                        }
                        
                        foreach ($fareXRef['flights'] as $flight) {
                            if (
                                ($outboundFlightId && isset($flight['flightId']) && $flight['flightId'] == $outboundFlightId) ||
                                ($returnFlightId && isset($flight['flightId']) && $flight['flightId'] == $returnFlightId)
                            ) {
                                if (isset($flight['legXRefs']) && is_array($flight['legXRefs'])) {
                                    foreach ($flight['legXRefs'] as $legXref) {
                                        $legId = is_array($legXref) ? ($legXref['legId'] ?? $legXref['legXRefId'] ?? null) : $legXref;
                                        if ($legId) {
                                            $validLegIds[] = $legId;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Insert leg records if available
        $legsInserted = 0;
        if (isset($cleanedData['legs']) && is_array($cleanedData['legs'])) {
            // Handle different leg structures
            $legs = [];
            if (isset($cleanedData['legs']['leg'])) {
                $legs = $cleanedData['legs']['leg'];
                if (isset($legs['legId'])) {
                    $legs = [$legs];
                }
            } else {
                $legs = $cleanedData['legs'];
            }
            
            foreach ($legs as $leg) {
                if (!isset($leg['legId']) || (!empty($validLegIds) && !in_array($leg['legId'], $validLegIds))) {
                    continue;
                }
                
                $this->flightAvailabilityDAL->insertAvailabilityLeg($availId, $leg);
                $legsInserted++;
            }
        }
        
        // Add check ID and legs count to response
        $cleanedData['check_id'] = $availId;
        $cleanedData['legs_inserted'] = $legsInserted;
        
        return $cleanedData;
    }
    
    /**
     * Convert XML string to JSON array
     */
    private function xmlToJson($xmlString)
    {
        // Remove XML declaration and processing instructions
        $xmlString = preg_replace('/<\?xml.*?\?>/', '', $xmlString);
        $xmlString = preg_replace('/<\?ypsilon.*?\?>/', '', $xmlString);
        
        // Load XML
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            return null;
        }
        
        // Convert to JSON and back to array
        $json = json_encode($xml);
        return json_decode($json, true);
    }
    
    /**
     * Recursively clean up the array and preserve attributes
     */
    private function cleanXmlArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        
        $result = [];
        
        foreach ($data as $key => $value) {
            // Handle attributes (they start with @)
            if (strpos($key, '@attributes') === 0) {
                // Merge attributes into the parent level without @ prefix
                if (is_array($value)) {
                    foreach ($value as $attrKey => $attrValue) {
                        $result[$attrKey] = $attrValue;
                    }
                }
            } else {
                $cleanedValue = is_array($value) ? $this->cleanXmlArray($value) : $value;
                
                // Special handling for flights to create flat arrays
                if ($key === 'flights') {
                    $result[$key] = $this->flattenFlights($cleanedValue);
                } else {
                    $result[$key] = $cleanedValue;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Flatten flights structure into a single array with all details
     */
    private function flattenFlights($flightsData)
    {
        $flattenedFlights = [];
        
        if (!is_array($flightsData)) {
            return $flattenedFlights;
        }
        
        // Handle single flight or array of flights
        if (isset($flightsData['flight'])) {
            $flights = $flightsData['flight'];
            
            // If it's a single flight, wrap it in an array
            if (isset($flights['flightId']) || isset($flights['@attributes'])) {
                $flights = [$flights];
            }
            
            foreach ($flights as $flight) {
                $flightData = [];
                
                // Add flight attributes
                if (isset($flight['@attributes'])) {
                    foreach ($flight['@attributes'] as $attr => $value) {
                        $flightData[$attr] = $value;
                    }
                }
                
                // Add direct flight properties
                foreach ($flight as $key => $value) {
                    if ($key !== '@attributes' && $key !== 'legXRefs') {
                        $flightData[$key] = $value;
                    }
                }
                
                // Add leg references as an array
                if (isset($flight['legXRefs']['legXRef'])) {
                    $legRefs = $flight['legXRefs']['legXRef'];
                    
                    // If it's a single legXRef, wrap it in an array
                    if (isset($legRefs['legXRefId']) || isset($legRefs['@attributes'])) {
                        $legRefs = [$legRefs];
                    }
                    
                    $flightData['legXRefs'] = [];
                    foreach ($legRefs as $legRef) {
                        $legData = [];
                        
                        // Add legXRef attributes
                        if (isset($legRef['@attributes'])) {
                            foreach ($legRef['@attributes'] as $attr => $value) {
                                $legData[$attr] = $value;
                            }
                        }
                        
                        // Add direct legXRef properties
                        foreach ($legRef as $key => $value) {
                            if ($key !== '@attributes') {
                                $legData[$key] = $value;
                            }
                        }
                        
                        $flightData['legXRefs'][] = $legData;
                    }
                }
                
                $flattenedFlights[] = $flightData;
            }
        }
        
        return $flattenedFlights;
    }
    
    /**
     * Get minimum available seats from specific flights organized by flight ID
     */
    private function getMinimumSeatsFromFlights($data, $outboundFlightId = null, $returnFlightId = null)
    {
        $flightSeats = [];
        $targetFlightIds = [];
        
        // Prepare list of flight IDs to search for
        if ($outboundFlightId) {
            $targetFlightIds[] = $outboundFlightId;
            $flightSeats[$outboundFlightId] = null;
        }
        if ($returnFlightId) {
            $targetFlightIds[] = $returnFlightId;
            $flightSeats[$returnFlightId] = null;
        }
        
        // If no specific flight IDs provided, fall back to first flight behavior
        $useFirstFlight = empty($targetFlightIds);
        
        // Look for flights in tarifs structure
        if (isset($data['tarifs']) && is_array($data['tarifs'])) {
            foreach ($data['tarifs'] as $tarif) {
                if (isset($tarif['fareXRefs']) && is_array($tarif['fareXRefs'])) {
                    // Ensure fareXRef is always an array
                    if (isset($tarif['fareXRefs']['fareXRef'])) {
                        $fareXRefs = $tarif['fareXRefs']['fareXRef'];
                        
                        // If it's a single fareXRef, wrap it in an array
                        if (isset($fareXRefs['fareId']) || (is_array($fareXRefs) && !isset($fareXRefs[0]))) {
                            $fareXRefs = [$fareXRefs];
                        }
                        
                        foreach ($fareXRefs as $fareXRef) {
                            if (isset($fareXRef['flights']) && is_array($fareXRef['flights']) && !empty($fareXRef['flights'])) {
                                foreach ($fareXRef['flights'] as $flight) {
                                    $shouldProcessFlight = false;
                                    $currentFlightId = $flight['flightId'] ?? null;
                                    
                                    if ($useFirstFlight) {
                                        // If no specific flight IDs, process first flight and return
                                        $shouldProcessFlight = true;
                                    } else {
                                        // Check if this flight ID matches our target IDs
                                        if ($currentFlightId && in_array($currentFlightId, $targetFlightIds)) {
                                            $shouldProcessFlight = true;
                                        }
                                    }
                                    
                                    if ($shouldProcessFlight && $currentFlightId) {
                                        $minSeatsForThisFlight = null;
                                        
                                        if (isset($flight['legXRefs']) && is_array($flight['legXRefs'])) {
                                            foreach ($flight['legXRefs'] as $legXRef) {
                                                if (isset($legXRef['seats'])) {
                                                    $seats = intval($legXRef['seats']);
                                                    if ($minSeatsForThisFlight === null || $seats < $minSeatsForThisFlight) {
                                                        $minSeatsForThisFlight = $seats;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // Store the minimum seats for this flight ID
                                        $flightSeats[$currentFlightId] = $minSeatsForThisFlight;
                                        
                                        // If using first flight mode, return after processing first flight
                                        if ($useFirstFlight) {
                                            return $flightSeats;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $flightSeats;
    }
}

