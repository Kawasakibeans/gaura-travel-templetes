<?php
/**
 * Amadeus Name Update Check Service
 * Handles business logic for checking passengers that need name updates
 */

namespace App\Services;

use App\DAL\AmadeusNameUpdateCheckDAL;
use Exception;

class AmadeusNameUpdateCheckService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AmadeusNameUpdateCheckDAL();
    }

    /**
     * Check passengers for name update
     */
    public function checkPassengersForNameUpdate($orderId, $agent = 'Auto Try')
    {
        if (empty($orderId)) {
            throw new Exception("Order ID is required", 400);
        }

        // Get passengers that need name update
        $passengers = $this->dal->getPassengersForNameUpdate($orderId);
        
        if (empty($passengers)) {
            return [
                'order_id' => $orderId,
                'agent' => $agent,
                'passengers' => [],
                'message' => 'No passengers found that need name update check'
            ];
        }

        $results = [];
        
        foreach ($passengers as $row) {
            $paxId = $row['paxauto_id'];
            $tripCode = $row['trip_code'];
            $travelDate = $row['travel_date'];
            
            // Get PNR info
            $pnrInfo = $this->dal->getPnrInfo($tripCode, $travelDate);
            
            if (!$pnrInfo) {
                $results[] = [
                    'pax_id' => $paxId,
                    'trip_code' => $tripCode,
                    'travel_date' => $travelDate,
                    'error' => 'PNR information not found in stock management sheet'
                ];
                continue;
            }

            $tripPnr = $pnrInfo['pnr'];
            $tripOfficeId = $pnrInfo['OID'];
            $tripAirline = $pnrInfo['airline_code'];

            // Only process SQ and MH airlines
            if ($tripAirline != 'SQ' && $tripAirline != 'MH') {
                continue;
            }

            $salutation = $row['salutation'];
            $fname = $row['fname'];
            $lname = $row['lname'];
            $dob = $row['dob'];

            // Handle airline-specific name formatting
            if ($tripAirline == 'MH' && $fname == $lname) {
                $fname = 'FNU';
            }
            
            if ($tripAirline == 'SQ' && strtolower($fname) === 'fnu') {
                $fname = $lname;
            }

            // Check if passenger already exists in log
            $passengerExists = $this->dal->checkPassengerExists($tripPnr, $orderId, $fname, $lname, $dob);
            $infantBookingExists = $this->dal->checkInfantBooking($orderId);

            // Build passenger data
            $passengerData = [
                'pnr' => $tripPnr,
                'trip_code' => $tripCode,
                'pax_id' => $paxId,
                'airline' => $tripAirline,
                'travel_date' => $travelDate,
                'officeID' => $tripOfficeId,
                'pax' => [
                    'salutation' => $salutation,
                    'fname' => $fname,
                    'lname' => $lname,
                    'dob' => $dob
                ],
                'already_exists' => $passengerExists,
                'has_infant_booking' => $infantBookingExists
            ];

            // Call Amadeus API to check PNR
            try {
                $pnrCheckResult = $this->checkPnrWithAmadeus($tripPnr, $tripOfficeId, $tripCode, $travelDate, $fname, $lname, $salutation, $dob);
                $passengerData['pnr_check'] = $pnrCheckResult;
            } catch (Exception $e) {
                $passengerData['pnr_check'] = [
                    'error' => $e->getMessage()
                ];
            }

            $results[] = $passengerData;
        }

        return [
            'order_id' => $orderId,
            'agent' => $agent,
            'passengers' => $results,
            'total_passengers' => count($results)
        ];
    }

    /**
     * Check PNR with Amadeus API
     */
    private function checkPnrWithAmadeus($pnr, $officeId, $tripCode, $travelDate, $fname, $lname, $salutation, $dob)
    {
        $apiUrl = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/amadeus_api/pnr_retrieve_for_gdeal_name_update.php?pnr=" . urlencode($pnr) . "&officeId=" . urlencode($officeId);
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception("Curl error: " . curl_error($ch));
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Amadeus API returned HTTP code: " . $httpCode);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['success']) || !$data['success']) {
            return [
                'success' => false,
                'error' => 'PNR retrieve failed',
                'response' => $response
            ];
        }

        $sessionId = $data['session']['sessionId'] ?? null;
        $securityToken = $data['session']['securityToken'] ?? null;
        $passengers = $data['passengers'] ?? [];
        $itineraries = $data['itineraries'] ?? [];

        // Calculate passenger type
        $age = 99;
        if ($dob && $travelDate) {
            $birthDate = new \DateTime($dob);
            $travelDateObj = new \DateTime($travelDate);
            $age = $birthDate->diff($travelDateObj)->y;
        }

        $passengerType = 'ADT';
        if ($age < 2) {
            $passengerType = 'INF';
        } elseif ($age < 12) {
            $passengerType = 'CHD';
        }

        // Count existing passengers (excluding infants)
        $totalExistingPax = is_array($passengers)
            ? count(array_filter($passengers, function ($pax) {
                return strtoupper(trim($pax['type'] ?? '')) !== 'INF';
            }))
            : 0;
        
        if ($totalExistingPax > 0) {
            $totalExistingPax = $totalExistingPax - 1;
        }

        $passengersCountAvailable = $passengers[0]['quantity'] ?? 0;
        $totalPaxPossible = $passengersCountAvailable;

        // Get max pax and stock
        $maxPaxInfo = $this->dal->getMaxPaxAndStock($tripCode, $travelDate);
        $maxPax = isset($maxPaxInfo['pax']) ? (int)$maxPaxInfo['pax'] : 0;
        $stock = isset($maxPaxInfo['stock']) ? (int)$maxPaxInfo['stock'] : 0;

        // Check if can proceed
        $isAmadeusLiveProceed = ($totalPaxPossible > $totalExistingPax) ? 1 : 0;

        // Check if passenger already exists
        $exists = false;
        $fullInputFirstName = strtolower(trim($fname . ' ' . $salutation));
        $inputSurname = strtolower(trim($lname));

        foreach ($passengers as $existingPax) {
            $existingFirstName = strtolower(trim($existingPax['firstName'] ?? ''));
            $existingSurname = strtolower(trim($existingPax['surname'] ?? ''));
            $existingDOB = $existingPax['dob'] ?? '';

            if (
                $existingFirstName === $fullInputFirstName &&
                $existingSurname === $inputSurname &&
                $existingDOB === $dob
            ) {
                $exists = true;
                break;
            }
        }

        // Check itinerary match
        $itineraryMatched = false;
        $itineraryStatus = 3; // Not found

        if (!empty($itineraries)) {
            $firstSegment = $itineraries[0];
            $lastSegment = end($itineraries);

            $departureCity = $firstSegment['departureCity'] ?? '';
            $departureDate = $firstSegment['departureDate'] ?? '';
            $arrivalCity = $lastSegment['arrivalCity'] ?? '';

            $tripOrigin = strtoupper(substr($tripCode, 0, 3));
            $tripDestination = strtoupper(substr($tripCode, 4, 3));
            $travelDateFormatted = date('Y-m-d', strtotime($travelDate));

            if (
                $departureCity === $tripOrigin &&
                $arrivalCity === $tripDestination &&
                $departureDate === $travelDateFormatted
            ) {
                $itineraryMatched = true;
                $itineraryStatus = 1; // Matched
            } else {
                $itineraryStatus = 2; // Not matched
            }
        }

        // Determine final status
        $status = 'Unknown';
        $canProceed = false;

        if ($exists) {
            $status = "Passenger $fname $lname already exists in PNR $pnr";
            $isAmadeusLiveProceed = 0;
        } elseif ($isAmadeusLiveProceed == 0) {
            $status = "Passenger count exceeded in Amadeus. Available Slot: $totalPaxPossible | Already added: $totalExistingPax";
        } elseif ($itineraryStatus == 2) {
            $status = "Itinerary not matched. G360: $tripOrigin-$tripDestination-$travelDateFormatted | Amadeus: $departureCity-$arrivalCity-$departureDate";
            $isAmadeusLiveProceed = 2;
        } elseif ($itineraryStatus == 3) {
            $status = "Itinerary not found. G360: $tripOrigin$tripDestination$travelDateFormatted";
            $isAmadeusLiveProceed = 3;
        } else {
            $status = "Success. Can be inserted into Amadeus";
            $canProceed = true;
        }

        return [
            'success' => true,
            'session_id' => $sessionId,
            'security_token' => $securityToken,
            'passenger_type' => $passengerType,
            'total_existing_pax' => $totalExistingPax,
            'total_pax_possible' => $totalPaxPossible,
            'max_pax' => $maxPax,
            'stock' => $stock,
            'is_amadeus_live_proceed' => $isAmadeusLiveProceed,
            'passenger_exists' => $exists,
            'itinerary_matched' => $itineraryMatched,
            'itinerary_status' => $itineraryStatus,
            'status' => $status,
            'can_proceed' => $canProceed
        ];
    }
}

