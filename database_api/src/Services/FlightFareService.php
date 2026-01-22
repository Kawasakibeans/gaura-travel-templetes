<?php

namespace App\Services;

use Exception;

class FlightFareService
{
    private $ypsilonEndpoint;
    private $ypsilonVersion;
    private $ypsilonAuth;
    
    public function __construct()
    {
        // These should be loaded from config or environment variables
        // For now, using defaults from the original code
        $this->ypsilonEndpoint = 'http://xmlapiv3.ypsilon.net:10816';
        $this->ypsilonVersion = '3.92';
        $this->ypsilonAuth = 'c2hlbGx0ZWNoOjRlNDllOTAxMGZhYzA1NzEzN2VjOWQ0NWZjNTFmNDdh';
    }
    
    /**
     * Check Ypsilon fare for a flight
     * Returns total amount and baggage allowance
     */
    public function checkYpsilonFare($from, $to, $date, $airline)
    {
        if (empty($from) || empty($to) || empty($date) || empty($airline)) {
            throw new Exception("All parameters (from, to, date, airline) are required", 400);
        }
        
        $xml = '<?xml version=\'1.0\' encoding=\'UTF-8\'?><fareRequest xmlns:shared="http://ypsilon.net/shared" da="true"><vcrs><vcr>' . htmlspecialchars($airline, ENT_QUOTES) . '</vcr></vcrs><alliances/><shared:fareTypes/><tourOps/><flights><flight depDate="' . htmlspecialchars($date, ENT_QUOTES) . '" dstApt="' . htmlspecialchars($to, ENT_QUOTES) . '" depApt="' . htmlspecialchars($from, ENT_QUOTES) . '"/></flights><paxes><pax gender="M" surname="Klenz" firstname="Hans A ADT" dob="1970-12-12"/></paxes><paxTypes/><options><limit>1</limit><offset>0</offset><vcrSummary>false</vcrSummary><waitOnList><waitOn>ALL</waitOn></waitOnList></options><coses><cos>E</cos></coses><agentCodes><agentCode>gaura</agentCode></agentCodes><directFareConsos><directFareConso>gaura</directFareConso></directFareConsos></fareRequest>';
        
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
                'accessid: gaura gaura',
                'authmode: pwd',
                'authorization: Basic ' . $this->ypsilonAuth,
                'content-Length: ' . strlen($xml),
                'Connection: close',
                'Content-Type: text/plain'
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new Exception("cURL Error: " . $err, 500);
        }
        
        if (!$response) {
            throw new Exception("No response from Ypsilon API", 500);
        }
        
        $xmlObj = simplexml_load_string($response);
        
        if ($xmlObj && isset($xmlObj->tarifs->tarif)) {
            // Retrieve total amount
            $adtSell = (string)$xmlObj->tarifs->tarif['adtSell'];
            $adtTax = (string)$xmlObj->tarifs->tarif['adtTax'];
            $totalAmount = (float)$adtTax + (float)$adtSell;
            
            // Retrieve baggage allowance
            $baggageAllowance = null;
            if (isset($xmlObj->tarifs->tarif->fareXRefs->fareXRef->flights->flight->legXRefs->legXRef)) {
                $legXRefId = (string)$xmlObj->tarifs->tarif->fareXRefs->fareXRef->flights->flight->legXRefs->legXRef['legXRefId'];
                
                foreach ($xmlObj->serviceMappings->map as $map) {
                    if ((string)$map['elemID'] === $legXRefId) {
                        $serviceID = (string)$map['serviceID'];
                        $xmlObj->registerXPathNamespace('shared', 'http://ypsilon.net/shared');
                        
                        // Find the serviceGroup with identifier 'baggage'
                        $baggageGroups = $xmlObj->xpath('//shared:specialServices/serviceGroup[@identifier="baggage"]');
                        if ($baggageGroups && count($baggageGroups) > 0) {
                            foreach ($baggageGroups as $baggageGroup) {
                                foreach ($baggageGroup->service->selectionGroup->item as $item) {
                                    if ((string)$item['id'] === $serviceID) {
                                        $baggageAllowance = (string)$item['totalAllowance'] . ' ' . (string)$item['unit'];
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            return [
                'total_amount' => $totalAmount,
                'baggage' => $baggageAllowance ?: 'Not Available'
            ];
        } else {
            throw new Exception("No fare data found in response", 404);
        }
    }
}

