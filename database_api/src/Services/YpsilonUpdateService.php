<?php

namespace App\Services;

use App\DAL\YpsilonUpdateDAL;
use Exception;

class YpsilonUpdateService
{
    private $ypsilonUpdateDAL;
    private $ypsilonEndpoint;
    private $ypsilonVersion;
    private $ypsilonAuth;
    
    public function __construct()
    {
        $this->ypsilonUpdateDAL = new YpsilonUpdateDAL();
        $this->ypsilonEndpoint = 'https://enxi.norrisdata.net:11025';
        $this->ypsilonVersion = '3.92';
        $this->ypsilonAuth = 'c2hlbGx0ZWNoOjRlNDllOTAxMGZhYzA1NzEzN2VjOWQ0NWZjNTFmNDdh';
    }
    
    /**
     * Fetch API data for a specific date
     */
    public function fetchApiData($accessId, $date)
    {
        if (empty($accessId) || empty($date)) {
            throw new Exception("Access ID and date are required", 400);
        }
        
        $finalApiUrl = 'https://norristest.ypsilon.net:11024';
        $xmlRequest = '<?xml version="1.0" encoding="UTF-8"?><PnrInfoListingRequest includeFetched="true"><Booked>' . htmlspecialchars($date, ENT_QUOTES) . '</Booked></PnrInfoListingRequest>';
        $xmlRequestCount = strlen($xmlRequest);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $finalApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xmlRequest,
            CURLOPT_HTTPHEADER => [
                'accept: application/xml',
                'accept-encoding: gzip',
                'transfer-encoding: identity',
                'api-version: 3.90',
                'accessmode: agency',
                'accessid: ' . $accessId,
                'authmode: pwd',
                'authorization: Basic ' . $this->ypsilonAuth,
                'content-Length: ' . $xmlRequestCount,
                'Connection: close'
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new Exception("cURL Error: " . $err, 500);
        }
        
        if ($response === false) {
            throw new Exception("No response from Ypsilon API", 500);
        }
        
        $transactionIdArray = [];
        $xml = simplexml_load_string($response);
        
        if ($xml && isset($xml->Transactions->Transaction)) {
            foreach ($xml->Transactions->Transaction as $transaction) {
                $agent = (string)$transaction['agent'];
                if (in_array($agent, ['gaura', 'gauraaws', 'gaurain'])) {
                    $bookingCode = (string)$transaction['bookingCode'];
                    $booked = (string)$transaction['booked'];
                    $booked = substr($booked, 0, 10);
                    $consolidator = (string)$transaction['consolidator'];
                    
                    $transactionIdArray[] = [
                        'pnr' => $bookingCode,
                        'date' => $booked,
                        'agent' => $agent,
                        'consolidator' => $consolidator
                    ];
                }
            }
        }
        
        return $transactionIdArray;
    }
    
    /**
     * Fetch individual API data and update booking
     */
    public function fetchIndividualApiData($apiUrl, $date, $pnr, $consol)
    {
        if (empty($apiUrl) || empty($date) || empty($pnr) || empty($consol)) {
            throw new Exception("API URL, date, PNR, and consolidator are required", 400);
        }
        
        $consoName = $consol . ' ' . $consol;
        $innerXmlRequest = '<?xml version="1.0" encoding="UTF-8"?><PnrInfoRequest updateFromGds="true"><FileKey>' . htmlspecialchars($pnr, ENT_QUOTES) . '</FileKey><BookingDate>' . htmlspecialchars($date, ENT_QUOTES) . '</BookingDate></PnrInfoRequest>';
        $innerXmlRequestCount = strlen($innerXmlRequest);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $innerXmlRequest,
            CURLOPT_HTTPHEADER => [
                'accept: application/xml',
                'accept-encoding: gzip',
                'transfer-encoding: identity',
                'api-version: 3.90',
                'accessmode: agency',
                'accessid: ' . $consoName,
                'authmode: pwd',
                'authorization: Basic ' . $this->ypsilonAuth,
                'content-Length: ' . $innerXmlRequestCount,
                'Connection: close'
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new Exception("cURL Error: " . $err, 500);
        }
        
        if ($response === false) {
            throw new Exception("No response from Ypsilon API", 500);
        }
        
        $doc = simplexml_load_string($response);
        if ($doc === false) {
            throw new Exception("Failed to parse XML response", 500);
        }
        
        $agent = (string)$doc->Common->Agent;
        if (empty($agent)) {
            throw new Exception("No agent found in response", 404);
        }
        
        // Get previous order ID from PNR
        $paxRecord = $this->ypsilonUpdateDAL->getBookingPaxByPnr($pnr);
        if (!$paxRecord) {
            throw new Exception("No booking found for PNR: $pnr", 404);
        }
        
        $orderId = $paxRecord['order_id'];
        $newlyCreatedPaymentId = (string)$doc->FlightBookings->FlightBooking->Payments->Payment->RemoteTransaction;
        
        // Update passenger information
        $this->updatePassengerInfo($doc, $orderId);
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'pnr' => $pnr,
            'payment_id' => $newlyCreatedPaymentId,
            'agent' => $agent
        ];
    }
    
    /**
     * Update passenger information from Ypsilon response
     */
    private function updatePassengerInfo($doc, $orderId)
    {
        foreach ($doc->FlightBookings->FlightBooking as $flightBooking) {
            $passengers = $flightBooking->Passengers;
            
            foreach ($passengers->Passenger as $passenger) {
                $passengerId = (string)$passenger['id'];
                
                $meals = "";
                $baggageAllowance = "";
                $ticketNumberUpdate = "";
                
                foreach ($flightBooking->Legs->PassengerLegs->PassengerLeg as $passengerLeg) {
                    if ((string)$passengerLeg->PaxId === $passengerId) {
                        $meals = (string)$passengerLeg->Meals;
                        $baggageAllowance = (string)$passengerLeg->BaggageAllowance;
                        $ticketNumberUpdate = (string)$passengerLeg->TicketNumber;
                        break;
                    }
                }
                
                // Check if passenger exists by GDS pax ID
                $updateData = [
                    'baggage' => $baggageAllowance,
                    'meal' => $meals
                ];
                
                if (!empty($ticketNumberUpdate)) {
                    $updateData['ticket_number'] = $ticketNumberUpdate;
                    $updateData['pax_status'] = 'Ticketed';
                }
                
                // Try to update by GDS pax ID first
                $updated = $this->ypsilonUpdateDAL->updateBookingPaxByGdsPaxId(
                    $orderId,
                    $passengerId,
                    $updateData
                );
                
                // If not found, try to update by name
                if ($updated === 0) {
                    $fname = (string)$passenger->FirstName;
                    $lname = (string)$passenger->Surname;
                    $this->ypsilonUpdateDAL->updateBookingPaxByName(
                        $orderId,
                        $fname,
                        $lname,
                        $updateData
                    );
                }
            }
        }
    }
    
    /**
     * Get bookings for update
     */
    public function getBookingsForUpdate($travelDateAfter = null, $orderDateAfter = null, $limit = 1)
    {
        return $this->ypsilonUpdateDAL->getBookingsForUpdate($travelDateAfter, $orderDateAfter, $limit);
    }
    
    /**
     * Update booking from Ypsilon API (cronjob)
     */
    public function updateBookingFromYpsilon($pnr, $date, $source = null)
    {
        if (empty($pnr) || empty($date)) {
            throw new Exception("PNR and date are required", 400);
        }
        
        // Determine API URL based on PNR
        $apiUsed = "https://enxi.norrisdata.net:11025";
        $apiUsedStaging = "https://enxi.norrisdata.net:11025";
        
        if (substr($pnr, 0, 3) === "SQ_" || substr($pnr, 0, 3) === "JQ_") {
            $finalApiUrl = $apiUsedStaging;
        } else {
            $finalApiUrl = $apiUsed;
        }
        
        // Get consolidator access ID
        $conso = '';
        if ($source) {
            $conso = $this->ypsilonUpdateDAL->getGdsConsolidatorAccessId($source);
        }
        
        if (empty($conso)) {
            // Try to get from history
            $paxRecord = $this->ypsilonUpdateDAL->getBookingPaxByPnr($pnr);
            if ($paxRecord) {
                $orderId = $paxRecord['order_id'];
                $historyRecord = $this->ypsilonUpdateDAL->getHistoryOfUpdates($orderId, 'agent');
                if ($historyRecord) {
                    $conso = $historyRecord['meta_value'] . ' ' . $source;
                }
            }
        }
        
        if (empty($conso)) {
            throw new Exception("Could not determine consolidator for PNR: $pnr", 400);
        }
        
        // Determine agency selector
        $agencySelector = 'agency';
        $agentList = [
            'gaura shelltechb2b',
            'shelltechb2baws shelltechb2baws',
            'gaurandc shelltechb2bndcxau',
            'shelltechb2bina shelltechb2bina',
            'gauraina shelltechb2bina',
            'gaurainn shelltechb2bndcxin',
            'gaurain shelltechb2bin',
            'gauraaws shelltechb2baws'
        ];
        
        if (in_array($conso, $agentList)) {
            $agencySelector = 'intranet';
            if ($conso === 'shelltechb2b gaura') {
                $conso = 'gaura shelltechb2b';
            }
            if ($conso === 'shelltechb2bina shelltechb2bina') {
                $conso = 'gauraina shelltechb2bina';
            }
        }
        
        // Call API
        return $this->fetchIndividualApiData($finalApiUrl, $date, $pnr, $conso);
    }
    
    /**
     * Update history of updates
     */
    public function updateHistoryOfUpdates($orderId, $metaKey, $metaValue, $updatedBy)
    {
        if (empty($orderId) || empty($metaKey) || empty($updatedBy)) {
            throw new Exception("Order ID, meta key, and updated by are required", 400);
        }
        
        return $this->ypsilonUpdateDAL->insertHistoryOfUpdates($orderId, $metaKey, $metaValue, $updatedBy);
    }
    
    /**
     * Migrate and delete history
     */
    public function migrateAndDeleteHistory($orderId, $updatedBy)
    {
        if (empty($orderId)) {
            throw new Exception("Order ID is required", 400);
        }
        
        // Get current divider ID
        $currentTime = date('Y-m-d H:i:s');
        $dividerId = $this->ypsilonUpdateDAL->getLatestMetaChangesDividerId($orderId);
        
        // Check if we need to increment divider ID
        $latestHistory = $this->ypsilonUpdateDAL->getHistoryOfMetaChanges($orderId);
        if (!empty($latestHistory) && $latestHistory[0]['updated_on'] !== $currentTime) {
            $dividerId++;
        } else {
            $dividerId = max(1, $dividerId);
        }
        
        // Migrate history of updates to history of meta changes
        // This is a simplified version - the actual implementation would need to query and migrate all records
        
        // Delete history of updates with specific patterns
        $this->ypsilonUpdateDAL->deleteHistoryOfUpdates($orderId, [
            'Flight %',
            'Flightlegs %'
        ]);
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'divider_id' => $dividerId
        ];
    }
}

