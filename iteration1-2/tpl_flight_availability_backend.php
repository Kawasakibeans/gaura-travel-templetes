<?php
// Backend for availability's operation

header('Content-Type: application/json');
// ✅ FIX: Removed direct wpdb usage - now using API endpoints only
// OLD CODE - COMMENTED OUT (now using API endpoints)
// global $wpdb;
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Handle non-POST requests (GET, OPTIONS, etc.)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'This endpoint only accepts POST requests. Use POST method to save flight availability data.',
        'allowed_methods' => ['POST'],
        'usage' => 'Send POST request with: depart_apt, dest_apt, depart_date, sessionId, tarifId, and other flight data'
    ]);
    exit;
}

// Define API base URL if not already defined
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api_test_pamitha/public');
}

// Build base URL - check if API_BASE_URL already includes /v1
$base_url = rtrim(API_BASE_URL, '/');
// Only add /v1 if it's not already there
if (substr($base_url, -3) !== '/v1' && strpos($base_url, '/v1') === false) {
    $base_url .= '/v1';
}

// Helper function to call flight availability API endpoint
function saveFlightAvailabilityViaAPI($data) {
    global $base_url;
    
    $apiUrl = rtrim($base_url, '/') . '/flight-availability/check';
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("Flight Availability API Error: " . $curlError);
            return [
                'success' => false,
                'message' => 'API connection error: ' . $curlError
            ];
        }
        
        if ($httpCode !== 200) {
            $errorDetails = '';
            if ($response) {
                $errorResponse = json_decode($response, true);
                if ($errorResponse && isset($errorResponse['message'])) {
                    $errorDetails = ': ' . $errorResponse['message'];
                } else {
                    $errorDetails = ': ' . substr($response, 0, 200);
                }
            }
            error_log("Flight Availability API HTTP Error: Status code " . $httpCode . " | Response: " . substr($response, 0, 500));
            return [
                'success' => false,
                'message' => 'API request failed with status code: ' . $httpCode . $errorDetails
            ];
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Flight Availability API JSON Error: " . json_last_error_msg());
            return [
                'success' => false,
                'message' => 'Invalid API response format'
            ];
        }
        
        // Handle different response formats
        if (isset($result['status']) && $result['status'] === 'success' && isset($result['data'])) {
            return $result['data'];
        } elseif (isset($result['success'])) {
            return $result;
        } elseif (is_array($result)) {
            return $result;
        }
        
        return [
            'success' => false,
            'message' => 'Unexpected API response format'
        ];
    } catch (Exception $e) {
        error_log("Flight Availability API Exception: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'API exception: ' . $e->getMessage()
        ];
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $depart_apt = sanitize_text_field($_POST['depart_apt']);
    $dest_apt = sanitize_text_field($_POST['dest_apt']);
    $outbound_seat = sanitize_text_field($_POST['outbound_seat']);
    $return_seat = sanitize_text_field($_POST['return_seat']);
    $depart_date = sanitize_text_field($_POST['depart_date']);
    $flightName = sanitize_text_field($_POST['flightName']);
    
    $sessionId = sanitize_text_field($_POST['sessionId']);
    $tarifId = sanitize_text_field($_POST['tarifId']);
    $outboundFlightId = sanitize_text_field($_POST['outboundFlightId']);
    $returnFlightId = sanitize_text_field($_POST['returnFlightId']);
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null; 
    
    // Validate and store original date format for API (service expects d-m-Y)
    if (empty($depart_date)) {
        echo json_encode([
            'success' => false,
            'message' => 'depart_date is required'
        ]);
        exit;
    }
    
    // Validate date format before processing
    $depart_date_obj = DateTime::createFromFormat('d-m-Y', $depart_date);
    if ($depart_date_obj === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid depart_date format. Expected format: d-m-Y (e.g., 16-07-2025)'
        ]);
        exit;
    }
    
    // Store original date format for API (service expects d-m-Y)
    $depart_date_original = $depart_date;

    $api_data_json = [];
    if (isset($_POST['apiData'])) {
        $api_data_raw = $_POST['apiData'];
        if (is_string($api_data_raw)) {
            $api_data_json = json_decode(stripslashes($api_data_raw), true);
            if (isset($api_data_json['legs']) && is_string($api_data_json['legs'])) {
                $api_data_json['legs'] = json_decode($api_data_json['legs'], true);
            }
        } elseif (is_array($api_data_raw)) {
            $api_data_json = $api_data_raw;
        }
    }

    // Handle return_date if provided
    $return_date = null;
    if (isset($_POST['return_date']) && !empty($_POST['return_date'])) {
        $return_date = sanitize_text_field($_POST['return_date']);
        $return_date_obj = DateTime::createFromFormat('d-m-Y', $return_date);
        if ($return_date_obj === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid return_date format. Expected format: d-m-Y (e.g., 20-07-2025)'
            ]);
            exit;
        }
        // Keep original format for API (service will convert internally)
    }
    
    // ✅ FIX: Prepare data for API call instead of direct database insert
    // Note: Service expects depart_date in d-m-Y format
    $apiRequestData = [
        'user_id' => $user_id,
        'depart_apt' => $depart_apt,
        'dest_apt' => $dest_apt,
        'outbound_seat' => $outbound_seat,
        'return_seat' => $return_seat,
        'depart_date' => $depart_date_original, // Keep original d-m-Y format for API
        'return_date' => isset($_POST['return_date']) ? $_POST['return_date'] : null, // Keep original d-m-Y format if provided
        'flightName' => $flightName,
        'tarifId' => $tarifId,
        'sessionId' => $sessionId,
        'outboundFlightId' => $outboundFlightId,
        'returnFlightId' => $returnFlightId,
        'apiData' => $api_data_json
    ];
    
    // Call API endpoint to save flight availability check
    $result = saveFlightAvailabilityViaAPI($apiRequestData);
    
    // Check if API call was successful
    if (!isset($result['success']) || !$result['success']) {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to save flight availability check'
        ]);
        exit;
    }
    
    // API returns the result with success, message, and id
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Availability and legs saved successfully',
        'id' => $result['id'] ?? null,
        'legs_inserted' => $result['legs_inserted'] ?? 0
    ]);
    exit;
}
?>