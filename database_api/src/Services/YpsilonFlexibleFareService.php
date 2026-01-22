<?php

namespace App\Services;

use Exception;

class YpsilonFlexibleFareService
{
    private $ypsilonEndpoint;
    private $ypsilonVersion;
    private $ypsilonAuth;
    
    public function __construct()
    {
        $this->ypsilonEndpoint = 'http://xmlapiv3.ypsilon.net:10816';
        $this->ypsilonVersion = '3.92';
        $this->ypsilonAuth = 'c2hlbGx0ZWNoOjRlNDllOTAxMGZhYzA1NzEzN2VjOWQ0NWZjNTFmNDdh';
    }
    
    /**
     * Get fares for flexible dates
     */
    public function getFaresForFlexibleDates($responseData)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($responseData);
        
        if ($xml === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = $error->message;
            }
            libxml_clear_errors();
            throw new Exception("Failed to parse XML: " . implode(", ", $errors), 500);
        }
        
        // Register namespaces
        $xml->registerXPathNamespace('shared', 'http://ypsilon.net/shared');
        
        $fares = [];
        $fareId = [];
        
        foreach ($xml->fares->fare as $fare) {
            $fareId[] = (string)$fare['fareId'];
            $namespaces = $fare->getNamespaces(true);
            $fares[] = [
                'fareId' => (string)$fare['fareId'],
                'shared:vcr' => (string)$fare->attributes($namespaces['shared'])->vcr,
            ];
        }
        
        return [$fares, $fareId];
    }
    
    /**
     * Fetch flexible flight fares for return trips
     */
    public function fetchFlexibleFlightFaresReturn($date, $return, $start, $end, $class)
    {
        if (empty($date) || empty($return) || empty($start) || empty($end) || empty($class)) {
            throw new Exception("All parameters (date, return, start, end, class) are required", 400);
        }
        
        $postFields = "<?xml version='1.0' encoding='UTF-8'?><fareRequest xmlns:shared=\"http://ypsilon.net/shared\" da=\"true\"><vcrs/><alliances/><shared:fareTypes/><tourOps/><flights><flight depDate=\"" . htmlspecialchars($date, ENT_QUOTES) . "\" dstApt=\"" . htmlspecialchars($end, ENT_QUOTES) . "\" depApt=\"" . htmlspecialchars($start, ENT_QUOTES) . "\"/><flight depDate=\"" . htmlspecialchars($return, ENT_QUOTES) . "\" dstApt=\"" . htmlspecialchars($start, ENT_QUOTES) . "\" depApt=\"" . htmlspecialchars($end, ENT_QUOTES) . "\"/></flights><paxes><pax gender=\"M\" surname=\"Klenz\" firstname=\"Hans A ADT\" dob=\"1945-12-12\"/></paxes><paxTypes/><options><limit>1</limit><offset>0</offset><vcrSummary>false</vcrSummary><waitOnList><waitOn>ALL</waitOn></waitOnList></options><coses><cos>" . htmlspecialchars($class, ENT_QUOTES) . "</cos></coses></fareRequest>";
        
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
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'accept: application/xml',
                'accept-encoding: gzip',
                'api-version: ' . $this->ypsilonVersion,
                'accessmode: agency',
                'accessid: gaura gaura',
                'authmode: pwd',
                'authorization: Basic ' . $this->ypsilonAuth,
                'content-Length: ' . strlen($postFields),
                'Connection: close',
                'Content-Type: text/plain'
            ],
        ]);
        
        $responseData = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new Exception("cURL Error: " . $err, 500);
        }
        
        if ($responseData === false) {
            throw new Exception("No response from Ypsilon API", 500);
        }
        
        list($fares, $fareId) = $this->getFaresForFlexibleDates($responseData);
        $flightsData = [];
        $xml = simplexml_load_string($responseData);
        
        foreach ($fares as $id) {
            $fareXRef = $this->getTarifByFareIdFlexible($responseData, $xml, $id['fareId']);
            
            if ($fareXRef && isset($fareXRef['@attributes'])) {
                $adtSell = ($fareXRef['@attributes']['adtSell'] ?? 0) + ($fareXRef['@attributes']['adtTax'] ?? 0);
                
                $flightsData[] = [
                    'vcrcode' => $id['shared:vcr'],
                    'adtSell' => number_format((float)$adtSell, 2, '.', ''),
                ];
            }
        }
        
        return $flightsData;
    }
    
    /**
     * Get tarif by fare ID for flexible dates
     */
    private function getTarifByFareIdFlexible($responseData, $xml2, $fareIdToMatch)
    {
        if (!$fareIdToMatch) {
            return null;
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($responseData);
        
        if ($xml === false) {
            error_log("XML Parsing Error: " . print_r(libxml_get_errors(), true));
            libxml_clear_errors();
            return null;
        }
        
        // Find the <tarif> element matching the given fareId
        $matchedTarif = $xml->xpath("//tarif[contains(@tarifId, '$fareIdToMatch')]");
        
        if (!is_array($matchedTarif) || empty($matchedTarif)) {
            return null;
        }
        
        // Convert to array
        return json_decode(json_encode($matchedTarif[0]), true);
    }
    
    /**
     * Get flexible fares for date range
     */
    public function getFlexibleFaresForDateRange($depDate, $returnDate, $depApt, $dstApt, $travelClass, $dateRange = 1)
    {
        if (empty($depDate) || empty($returnDate) || empty($depApt) || empty($dstApt) || empty($travelClass)) {
            throw new Exception("All parameters are required", 400);
        }
        
        $currentDate = date('Y-m-d');
        
        // Define date ranges (-dateRange to +dateRange days)
        $outboundDates = [];
        $returnDates = [];
        
        for ($i = -$dateRange; $i <= $dateRange; $i++) {
            $outboundDates[] = date('Y-m-d', strtotime($depDate . " $i days"));
            $returnDates[] = date('Y-m-d', strtotime($returnDate . " $i days"));
        }
        
        $results = [];
        
        foreach ($returnDates as $return) {
            foreach ($outboundDates as $outbound) {
                if ($outbound >= $currentDate && $return > $outbound) {
                    try {
                        $flexibleOutbound = $this->fetchFlexibleFlightFaresReturn($outbound, $return, $depApt, $dstApt, $travelClass);
                        
                        if (!empty($flexibleOutbound)) {
                            $results[] = [
                                'outbound_date' => $outbound,
                                'return_date' => $return,
                                'fares' => $flexibleOutbound
                            ];
                        }
                    } catch (Exception $e) {
                        // Log error but continue with other dates
                        error_log("Error fetching fares for $outbound - $return: " . $e->getMessage());
                    }
                }
            }
        }
        
        return $results;
    }
}

