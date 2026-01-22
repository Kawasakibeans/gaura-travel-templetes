<?php
/**
 * GDS Booking Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\GDSBookingDAL;
use Exception;

class GDSBookingService
{
    private $gdsBookingDAL;

    public function __construct()
    {
        $this->gdsBookingDAL = new GDSBookingDAL();
    }

    /**
     * Process CSV import and return preview data
     */
    public function previewImport($csvData)
    {
        // This would parse CSV and return preview
        // For now, return structure
        return [
            'success' => true,
            'preview' => $csvData,
            'message' => 'CSV parsed successfully'
        ];
    }

    /**
     * Import booking from data
     */
    public function importBooking($bookingData, $passengerData, $historyData = [])
    {
        // Check if booking exists
        $existing = $this->gdsBookingDAL->checkBookingExists($bookingData['order_id']);
        
        if (!$existing) {
            // Create booking
            $this->gdsBookingDAL->createBooking($bookingData);
            
            // Create history updates
            foreach ($historyData as $history) {
                $this->gdsBookingDAL->createHistoryUpdate(
                    $history['type_id'],
                    $history['meta_key'],
                    $history['meta_value'],
                    $history['updated_by'],
                    $history['updated_on']
                );
            }
        }
        
        // Create passenger
        $this->gdsBookingDAL->createPassenger($passengerData);
        
        return [
            'success' => true,
            'order_id' => $bookingData['order_id']
        ];
    }

    /**
     * Get next order ID
     */
    public function getNextOrderId()
    {
        $lastId = $this->gdsBookingDAL->getLastOrderId();
        return $lastId + 1;
    }

    /**
     * Retrieve Amadeus PNR data (calls external API)
     */
    public function retrieveAmadeusPnr($pnr, $officeId)
    {
        $url = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/amadeus_api/pnr_retrieve_itinerary_name_ticket.php"
             . "?pnr=" . urlencode($pnr)
             . "&officeId=" . urlencode($officeId);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Cache-Control: no-cache'],
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            error_log("Amadeus API cURL Error: " . $err . " | URL: " . $url);
            throw new Exception("cURL Error: " . $err, 500);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            error_log("Amadeus API HTTP Error: " . $httpCode . " | Response: " . substr($response, 0, 500) . " | URL: " . $url);
            throw new Exception("HTTP Error: " . (int)$httpCode . " - " . substr($response, 0, 200), $httpCode);
        }

        // Clean response: remove BOM and trim whitespace
        $response = trim($response);
        if (substr($response, 0, 3) === "\xEF\xBB\xBF") {
            $response = substr($response, 3); // Remove UTF-8 BOM
        }
        
        // Log raw response for debugging (first 500 chars)
        error_log("Amadeus API Raw Response (first 500 chars): " . substr($response, 0, 500));
        error_log("Amadeus API Response length: " . strlen($response));

        // Clean response: remove HTML tags, PHP warnings, and other non-JSON content
        $cleanedResponse = $this->cleanAmadeusResponse($response);
        
        // Log cleaned response
        error_log("Amadeus API Cleaned Response (first 500 chars): " . substr($cleanedResponse, 0, 500));

        // Check if cleaned response is empty
        if (empty(trim($cleanedResponse))) {
            error_log("Amadeus API Error: Empty response after cleaning");
            throw new Exception("Empty response from Amadeus API", 500);
        }
        
        // Try to decode JSON
        $data = json_decode($cleanedResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            error_log("Amadeus API JSON Error: " . $jsonError);
            error_log("Cleaned Response (first 1000 chars): " . substr($cleanedResponse, 0, 1000));
            
            // Check if response contains an error message
            if (preg_match('/"error"\s*:\s*"([^"]+)"/i', $cleanedResponse, $matches)) {
                $errorMessage = $matches[1];
                throw new Exception("Amadeus API Error: " . $errorMessage, 404);
            }
            
            throw new Exception("Invalid JSON response: " . $jsonError . " | Response preview: " . substr($cleanedResponse, 0, 200), 500);
        }

        // Check if response contains an error
        if (isset($data['error'])) {
            error_log("Amadeus API returned error: " . $data['error']);
            throw new Exception("Amadeus API Error: " . $data['error'], 404);
        }

        return $data;
    }

    /**
     * Clean Amadeus API response by removing HTML, PHP warnings, and extracting JSON
     */
    private function cleanAmadeusResponse($response)
    {
        // First, try to extract JSON object/array from the response
        // This handles cases where JSON is mixed with HTML/PHP warnings
        $jsonStart = strpos($response, '{');
        $arrayStart = strpos($response, '[');
        
        if ($jsonStart !== false || $arrayStart !== false) {
            $start = false;
            
            if ($jsonStart !== false && ($arrayStart === false || $jsonStart < $arrayStart)) {
                $start = $jsonStart;
            } elseif ($arrayStart !== false) {
                $start = $arrayStart;
            }
            
            if ($start !== false) {
                // Find the matching closing brace/bracket
                $depth = 0;
                $inString = false;
                $escapeNext = false;
                
                for ($i = $start; $i < strlen($response); $i++) {
                    $char = $response[$i];
                    
                    if ($escapeNext) {
                        $escapeNext = false;
                        continue;
                    }
                    
                    if ($char === '\\') {
                        $escapeNext = true;
                        continue;
                    }
                    
                    if ($char === '"' && !$escapeNext) {
                        $inString = !$inString;
                        continue;
                    }
                    
                    if (!$inString) {
                        if ($char === '{' || $char === '[') {
                            $depth++;
                        } elseif ($char === '}' || $char === ']') {
                            $depth--;
                            if ($depth === 0) {
                                // Extract the JSON portion
                                $response = substr($response, $start, $i - $start + 1);
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // If we still have HTML/PHP warnings mixed in, clean them up
        // Remove PHP warnings and errors (e.g., "Warning: Undefined variable...")
        $response = preg_replace('/<b>Warning<\/b>:\s*[^<]*<br\s*\/?>/i', '', $response);
        $response = preg_replace('/<b>Notice<\/b>:\s*[^<]*<br\s*\/?>/i', '', $response);
        $response = preg_replace('/<b>Fatal error<\/b>:\s*[^<]*<br\s*\/?>/i', '', $response);
        $response = preg_replace('/Warning:\s*[^\n]*\n?/i', '', $response);
        $response = preg_replace('/Notice:\s*[^\n]*\n?/i', '', $response);
        $response = preg_replace('/Fatal error:\s*[^\n]*\n?/i', '', $response);
        
        // Remove HTML tags (in case there are any left)
        $response = strip_tags($response);
        
        // Remove HTML entities
        $response = html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove <br /> and other HTML line breaks
        $response = preg_replace('/<br\s*\/?>/i', '', $response);
        $response = preg_replace('/\r\n|\r|\n/', ' ', $response);
        
        // Clean up extra whitespace
        $response = trim($response);
        
        return $response;
    }

    /**
     * Save Amadeus booking
     */
    public function saveAmadeusBooking($pnr, $officeId, $bookingData, $agentName)
    {
        try {
            // Re-fetch Amadeus data to ensure we have latest
            $amadeusData = $this->retrieveAmadeusPnr($pnr, $officeId);
        } catch (Exception $e) {
            error_log("Error retrieving Amadeus PNR in saveAmadeusBooking: " . $e->getMessage());
            throw new Exception("Failed to retrieve Amadeus data: " . $e->getMessage(), $e->getCode());
        }
        
        $passengers = $amadeusData['passengers'] ?? [];
        $itineraries = $amadeusData['itineraries'] ?? ($amadeusData['itinerary'] ?? []);

        $this->gdsBookingDAL->beginTransaction();
        try {
            // Get next order ID
            $orderId = $this->getNextOrderId();
            $now = date('Y-m-d H:i:s');
            
            // Calculate travel dates from itineraries
            $validSegs = array_values(array_filter($itineraries, function($s) {
                return !empty($s['departureCity']) && !empty($s['arrivalCity']);
            }));
            
            $travelDate = '';
            $returnDate = '';
            if (!empty($validSegs)) {
                $first = $validSegs[0];
                $last = $validSegs[count($validSegs) - 1];
                $travelDate = $this->formatDate($first['departureDate'] ?? '');
                $returnDate = $this->formatDate($last['arrivalDate'] ?? '');
            }

            // Create booking
            $bookingRecord = [
                'order_id' => $orderId,
                'order_date' => $bookingData['booked_on'],
                't_type' => $bookingData['trip_type'],
                'travel_date' => $travelDate,
                'return_date' => $returnDate,
                'total_pax' => count($passengers),
                'trip_code' => $bookingData['tripcode'],
                'agent_info' => $agentName,
                'total_amount' => $bookingData['total_amount'],
                'added_on' => $now,
                'added_by' => $agentName
            ];
            $this->gdsBookingDAL->createBooking($bookingRecord);

            // Create passengers
            foreach ($passengers as $p) {
                $title = $p['title'] ?? '';
                $fname = $this->stripTitleFromFirstName($p['firstName'] ?? '', $title);
                
                // Extract title from first name if not present
                if (empty($title)) {
                    if (preg_match('/\s(mr|mstr|sir|mrs|ms|miss|madam)\b/i', $fname, $matches)) {
                        $title = trim($matches[1]);
                        $fname = preg_replace('/\s(mr|mstr|sir|mrs|ms|miss|madam)\b/i', '', $fname);
                    }
                }
                
                $lname = $p['surname'] ?? '';
                $gender = $this->inferGenderFromTitle($title);
                $dob = trim($p['dob'] ?? '');

                $passengerRecord = [
                    'order_id' => $orderId,
                    'order_date' => $bookingData['booked_on'],
                    'salutation' => $title,
                    'fname' => $fname,
                    'lname' => $lname,
                    'gender' => $gender,
                    'dob' => $dob,
                    'email' => $bookingData['email_address'],
                    'pnr' => $pnr,
                    'added_on' => $now,
                    'added_by' => $agentName
                ];
                $this->gdsBookingDAL->createPassenger($passengerRecord);
            }

            // Create history updates
            $this->createHistoryFromItineraries($orderId, $validSegs, $bookingData['total_amount'], $agentName, $now);

            $this->gdsBookingDAL->commit();
            return ['success' => true, 'message' => 'Saved successfully', 'order_id' => $orderId];
        } catch (Exception $e) {
            $this->gdsBookingDAL->rollback();
            throw $e;
        }
    }

    /**
     * Preview CSV import (new format)
     */
    public function previewCsvImport($csvData, $isOldFormat = false)
    {
        $orderId = $this->getNextOrderId();
        $preview = [];
        $autonumber = 1;

        foreach ($csvData as $row) {
            $filekey = $isOldFormat ? ($row['filekey'] ?? '') : ($row['filekey'] ?? '');
            if (empty($filekey)) continue;

            if ($isOldFormat) {
                // Old format: pax_surname, pax_firstname are arrays separated by |
                $paxSurname = explode("|", $row['pax_surname'] ?? '');
                $paxFirstname = explode("|", $row['pax_firstname'] ?? '');
                $paxGender = explode("|", $row['pax_gender'] ?? '');
                $paxBirth = explode("|", $row['pax_birth'] ?? '');
                $paxType = explode("|", $row['pax_type'] ?? '');
                
                // Count INF occurrences and subtract from pax
                $occurrences = substr_count($row['pax_type'] ?? '', 'INF');
                $pax = (int)($row['pax'] ?? 0) - (int)$occurrences;
            } else {
                // New format: pax_salutation, pax_surname, pax_firstname are arrays separated by ;
                $paxSalutation = explode(";", $row['pax_salutation'] ?? '');
                $paxSurname = explode(";", $row['pax_surname'] ?? '');
                $paxFirstname = explode(";", $row['pax_firstname'] ?? '');
                $paxGender = explode(";", $row['pax_gender'] ?? '');
                $paxBirth = explode(";", $row['pax_birth'] ?? '');
                $paxType = explode(";", $row['pax_type'] ?? '');

                // Count INF occurrences and subtract from pax
                $occurrences = substr_count($row['pax_type'] ?? '', 'INF');
                $pax = (int)($row['pax'] ?? 0) - (int)$occurrences;
            }

            for ($i = 0; $i < count($paxSurname); $i++) {
                $gender = '';
                if (($paxGender[$i] ?? '') == "M") {
                    $gender = 'male';
                } elseif (($paxGender[$i] ?? '') == "F") {
                    $gender = 'female';
                }

                // Check if exists
                $exists = $this->gdsBookingDAL->checkPassengerExists(
                    $filekey,
                    $paxSurname[$i] ?? '',
                    $paxFirstname[$i] ?? ''
                );

                $status = $exists ? 'Existing' : 'New';
                $match = $exists ? 'Existing' : 'New';

                $previewItem = [
                    'autonumber' => $autonumber++,
                    'order_id' => $orderId,
                    'pnr' => $filekey,
                    'pax_surname' => $paxSurname[$i] ?? '',
                    'pax_firstname' => $paxFirstname[$i] ?? '',
                    'gender' => $gender,
                    'dob' => $paxBirth[$i] ?? '',
                    'pax_type' => $paxType[$i] ?? '',
                    'pax' => $pax,
                    'status' => $status,
                    'match' => $match,
                    'row_data' => $row
                ];

                if (!$isOldFormat) {
                    $previewItem['pax_salutation'] = $paxSalutation[$i] ?? '';
                }

                $preview[] = $previewItem;
            }
            $orderId++;
        }

        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import CSV bookings
     */
    public function importCsvBookings($records, $isOldFormat = false)
    {
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($records as $record) {
            if (empty($record['filekey']) || ($record['match'] ?? 'New') !== 'New') {
                continue;
            }

            $orderId = $record['order_id'] ?? $this->getNextOrderId();
            $pnr = $record['filekey'];

            // Check if booking exists
            $bookingExists = $this->gdsBookingDAL->checkBookingExists($orderId);
            
            if (!$bookingExists) {
                // Parse dates
                $bookedTime = $record['booked_time'] ?? $now;
                if (strpos($bookedTime, '/') !== false) {
                    $bookedTime = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $bookedTime)));
                }
                
                $departure = $record['departure'] ?? '';
                if (!empty($departure) && strpos($departure, '/') !== false) {
                    $departure = date('Y-m-d', strtotime(str_replace('/', '-', $departure)));
                }
                
                $return = $record['return'] ?? $departure;
                if (!empty($return) && strpos($return, '/') !== false) {
                    $return = date('Y-m-d', strtotime(str_replace('/', '-', $return)));
                }

                // Create booking
                $bookingData = [
                    'order_id' => $orderId,
                    'order_date' => $bookedTime,
                    't_type' => $record['journey_type'] ?? 'oneway',
                    'travel_date' => $departure,
                    'return_date' => $return,
                    'total_pax' => $record['pax'] ?? 1,
                    'trip_code' => $record['tripcode'] ?? '',
                    'agent_info' => $record['customer_number'] ?? '',
                    'total_amount' => $record['amount'] ?? 0,
                    'added_on' => $now,
                    'added_by' => $record['added_by'] ?? 'gds_import'
                ];

                if ($isOldFormat) {
                    $bookingData['source'] = $record['agent'] ?? 'import';
                    $bookingData['deposit_amount'] = $record['depositamountpaid'] ?? 0;
                    $bookingData['balance'] = $record['balance'] ?? ((float)$record['amount'] - (float)$record['depositamountpaid']);
                    $bookingData['late_modified'] = $now;
                    $bookingData['modified_by'] = $record['modified_by'] ?? 'gds_import_old';
                    $bookingData['added_by'] = $record['added_by'] ?? 'gds_import_old_style';
                    $this->gdsBookingDAL->createBookingOldFormat($bookingData);
                } else {
                    $this->gdsBookingDAL->createBooking($bookingData);
                }

                // Create history updates
                $this->createHistoryFromRecord($orderId, $record, $isOldFormat, $now);
            }

            // Parse birth date
            $paxBirth = $record['pax_birth'] ?? '';
            if (!empty($paxBirth) && strpos($paxBirth, '/') !== false) {
                $paxBirth = date('Y-m-d', strtotime(str_replace('/', '-', $paxBirth)));
            }

            // Create passenger
            $bookedTime = $record['booked_time'] ?? $now;
            if (strpos($bookedTime, '/') !== false) {
                $bookedTime = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $bookedTime)));
            }

            $passengerData = [
                'order_id' => $orderId,
                'order_date' => $bookedTime,
                'salutation' => $record['pax_salutation'] ?? '',
                'fname' => $record['pax_firstname'] ?? '',
                'lname' => $record['pax_surname'] ?? '',
                'gender' => $record['gender'] ?? '',
                'dob' => $paxBirth,
                'email' => $record['email'] ?? '',
                'pnr' => $pnr,
                'added_on' => $now,
                'added_by' => $record['added_by'] ?? 'gds_import'
            ];

            if ($isOldFormat) {
                $passengerData['late_modified'] = $now;
                $passengerData['modified_by'] = $record['modified_by'] ?? 'gds_import_old';
                $passengerData['added_by'] = $record['added_by'] ?? 'gds_import_old_style';
                $this->gdsBookingDAL->createPassengerOldFormat($passengerData);
            } else {
                $this->gdsBookingDAL->createPassenger($passengerData);
            }

            $importedCount++;
        }

        return ['success' => true, 'message' => 'Updated successfully', 'imported_count' => $importedCount];
    }

    /**
     * Preview total amount update
     */
    public function previewTotalAmountUpdate($csvData)
    {
        $preview = [];
        $autonumber = 1;

        foreach ($csvData as $row) {
            if (empty($row['orderid'])) continue;

            $orderId = $row['orderid'];
            $totalAmount = $this->gdsBookingDAL->getBookingTotalAmount($orderId);
            $transactionValue = $this->gdsBookingDAL->getTransactionValueFromMeta($orderId);

            $exists = $this->gdsBookingDAL->checkBookingExists($orderId);
            $status = $exists ? 'Existing & will be overwrite' : 'New';
            $checked = $exists && strlen($orderId) > 6;

            $preview[] = [
                'autonumber' => $autonumber++,
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'transaction_value' => $transactionValue,
                'status' => $status,
                'checked' => $checked
            ];
        }

        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Update total amount from meta
     */
    public function updateTotalAmountFromMeta($orderIds, $updatedBy)
    {
        $updatedCount = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($orderIds as $orderId) {
            if ($this->gdsBookingDAL->updateTotalAmountFromMeta($orderId, $updatedBy, $now)) {
                $updatedCount++;
            }
        }

        return ['success' => true, 'message' => 'Updated successfully', 'updated_count' => $updatedCount];
    }

    // Helper methods
    private function formatDate($date)
    {
        if (!$date) return '';
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : $date;
    }

    private function formatTime($time)
    {
        $time = trim((string)$time);
        if ($time === '' || strlen($time) < 3) return '';
        if (strlen($time) === 3) $time = '0' . $time;
        return substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':00';
    }

    private function stripTitleFromFirstName($first, $title)
    {
        $f = trim((string)$first);
        $t = strtoupper(trim((string)$title));
        if ($t && str_ends_with(strtoupper($f), ' ' . $t)) {
            $f = rtrim(substr($f, 0, -1 * (strlen($t) + 1)));
        }
        return $f;
    }

    private function inferGenderFromTitle($title)
    {
        $t = strtoupper(trim((string)$title));
        if (in_array($t, ['MR', 'MSTR', 'SIR'], true)) return 'M';
        if (in_array($t, ['MRS', 'MS', 'MISS', 'MADAM'], true)) return 'F';
        return '';
    }

    private function createHistoryFromItineraries($orderId, $itineraries, $totalAmount, $agentName, $now)
    {
        $depTimes = [];
        $depApts = [];
        $destApts = [];
        $destTimes = [];
        $marketing = [];
        $elapsed = [];
        $depTermn = [];
        $arrTermn = [];
        $equipm = [];
        $status = [];
        $flNum = [];
        $pnrArray = [];
        $classArray = [];

        foreach ($itineraries as $seg) {
            $depApt = trim($seg['departureCity'] ?? '');
            $arrApt = trim($seg['arrivalCity'] ?? '');
            $elapsedT = trim($seg['elapsed'] ?? '');
            $flightNumberT = trim($seg['flightNumber'] ?? '');
            $statusT = trim($seg['status'] ?? '');
            $classT = trim($seg['classOfService'] ?? '');
            $termDep = $seg['flightDetails']['departureTerminal'] ?? '';
            $termArr = $seg['flightDetails']['arrivalTerminal'] ?? '';
            $equip = $seg['flightDetails']['equipment'] ?? '';

            $pnrArray[] = $orderId;
            if ($termDep !== '') $depTermn[] = $termDep;
            if ($termArr !== '') $arrTermn[] = $termArr;
            if ($equip !== '') $equipm[] = $equip;
            if ($flightNumberT !== '') $flNum[] = $flightNumberT;
            if ($statusT !== '') $status[] = $statusT;
            if ($classT !== '') $classArray[] = $classT;
            if ($depApt !== '') $depApts[] = $depApt;
            if ($arrApt !== '') $destApts[] = $arrApt;
            if ($elapsedT !== '') $elapsed[] = $elapsedT;

            $depDT = $this->formatDate($seg['departureDate'] ?? '') . ' ' . $this->formatTime($seg['departureTime'] ?? '');
            $arrDT = $this->formatDate($seg['arrivalDate'] ?? '') . ' ' . $this->formatTime($seg['arrivalTime'] ?? '');
            if ($depDT !== '') $depTimes[] = $depDT;
            if ($arrDT !== '') $destTimes[] = $arrDT;

            $mkt = trim($seg['airline'] ?? '');
            if ($mkt !== '') $marketing[] = $mkt;
        }

        $rows = [
            ['Flight FileKey', (string)$orderId],
            ['Flightlegs DepTime', implode(' | ', $depTimes)],
            ['Flightlegs DepApt', implode(' | ', $depApts)],
            ['Flightlegs DestApt', implode(' | ', $destApts)],
            ['Flightlegs Class', implode(' | ', $classArray)],
            ['Flightlegs DestTime', implode(' | ', $destTimes)],
            ['Flightlegs MarketingCarrier', implode(' | ', $marketing)],
            ['Flightlegs OperatingCarrier', implode(' | ', $marketing)],
            ['Flightlegs Status', implode(' | ', $status)],
            ['Flightlegs Equipment', implode(' | ', $equipm)],
            ['Flightlegs FlNr', implode(' | ', $flNum)],
            ['Flightlegs DepTerminal', implode(' | ', $depTermn)],
            ['Flightlegs DestTerminal', implode(' | ', $arrTermn)],
            ['Flightlegs CrFileKey', implode(' | ', $pnrArray)],
            ['Flightlegs Elapsed', implode(' | ', $elapsed)],
            ['Transaction TotalTurnover', (string)$totalAmount],
        ];

        foreach ($rows as [$k, $v]) {
            $this->gdsBookingDAL->createHistoryUpdate($orderId, $k, $v, $agentName, $now);
        }
    }

    private function createHistoryFromRecord($orderId, $record, $isOldFormat, $now)
    {
        $updatedBy = $record['updated_by'] ?? 'gds_import';

        if ($isOldFormat) {
            // Old format has many more history fields
            $historyRows = [
                ['pax_name', $record['order_pax_name'] ?? ''],
                ['price', $record['price'] ?? ''],
                ['tax', $record['tax'] ?? ''],
                ['fees', $record['fees'] ?? ''],
                ['currency', $record['currency'] ?? ''],
                ['Flight CrsType', $record['crs'] ?? ''],
                ['Flight Segments', $record['segments'] ?? ''],
                ['Fares FareType', $record['faretype'] ?? ''],
                ['Flight Source', $record['source_type'] ?? ''],
                ['Seen Confirmation', $record['seen_confirmation'] ?? ''],
                ['TransactionService NetGainAgent', $record['gain'] ?? ''],
                ['TransactionService NetGainAffiliate', $record['affiliate'] ?? ''],
                ['Flight PNRStatus', $record['pnr_status'] ?? ''],
                ['Flight tkt_tl', $record['tkt_tl'] ?? ''],
                ['StaffName', $record['staffname'] ?? ''],
                ['Option', $record['option'] ?? ''],
                ['Double', $record['double'] ?? ''],
                ['Flightlegs DepApt', $record['leg_depart_apt'] ?? ''],
                ['Flightlegs DestApt', $record['leg_arrive_apt'] ?? ''],
                ['Flightlegs TurnAround', $record['leg_turn_around'] ?? ''],
                ['Flightlegs DepTime', $record['leg_depart_time'] ?? ''],
                ['Flightlegs DestTime', $record['leg_arrive_time'] ?? ''],
                ['Flightlegs MarketingCarrier', $record['leg_carrier'] ?? ''],
                ['Flightlegs FlNr', $record['leg_flightnumber'] ?? ''],
                ['Payments CCFee', $record['cc_fee'] ?? ''],
                ['Payments CCFee Currency', $record['cc_fee_currency'] ?? ''],
                ['Payments Low Surcharge', $record['low_surcharge'] ?? ''],
                ['Payments Low Surcharge Currency', $record['low_surcharge_currency'] ?? ''],
                ['Comment', $record['comment'] ?? ''],
                ['Insurance Type', $record['insurance_type'] ?? ''],
                ['Insurance Price', $record['insurance_price'] ?? ''],
                ['Insurance Currency', $record['insurance_currency'] ?? ''],
                ['Insurance Res Code', $record['insurance_res_code'] ?? ''],
                ['Watchlist', $record['watchlist'] ?? ''],
                ['Transaction TotalTurnover', $record['payment'] ?? ''],
                ['Payments Status', $record['payment_status'] ?? ''],
                ['Payments Provider', $record['payment_provider'] ?? '']
            ];
        } else {
            // New format has fewer history fields
            $legDepartApt = str_replace(';', ' | ', $record['leg_depart_apt'] ?? '');
            $legArriveApt = str_replace(';', ' | ', $record['leg_arrive_apt'] ?? '');
            $legDepartTime = str_replace(';', '|', $record['leg_depart_time'] ?? '');
            $legDepartTime = str_replace('|', ' | ', $legDepartTime);
            $legArriveTime = str_replace(';', '|', $record['leg_arrive_time'] ?? '');
            $legArriveTime = str_replace('|', ' | ', $legArriveTime);
            $legCarrier = str_replace(';', ' | ', $record['leg_carrier'] ?? '');

            $historyRows = [
                ['Flightlegs DepApt', $legDepartApt],
                ['Flightlegs DestApt', $legArriveApt],
                ['Flightlegs DepTime', $legDepartTime],
                ['Flightlegs DestTime', $legArriveTime],
                ['Transaction TotalTurnover', $record['amount'] ?? ''],
                ['Flightlegs MarketingCarrier', $legCarrier]
            ];
        }

        foreach ($historyRows as [$key, $value]) {
            if ($value !== '') {
                $this->gdsBookingDAL->createHistoryUpdate($orderId, $key, $value, $updatedBy, $now);
            }
        }
    }
}

