<?php
date_default_timezone_set("Australia/Melbourne"); 

// Load WordPress to get API_BASE_URL constant from wp-config.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Get API base URL (should be defined in wp-config.php)
$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

header('Content-Type: application/json');

// Check if the 'date' and 'end_date' parameters are passed in the GET request
if (isset($_GET['date']) && isset($_GET['end_date'])) {
    $selectedDate = $_GET['date'];
    $selectedDate_end = $_GET['end_date'];

    // Build API URL with query parameters
    $apiUrl = $base_url . '/amadeus-endorsement/endorsement-ids-prices';
    $apiUrl .= '?date=' . urlencode($selectedDate) . '&end_date=' . urlencode($selectedDate_end);
    
    // Initialize cURL with optimized settings for external calls
    $ch = curl_init($apiUrl);
    
    // Basic cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    // Increased timeout settings for external API calls
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Total timeout: 120 seconds
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Connection timeout: 30 seconds
    
    // Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // SSL options (for HTTPS)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // User agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP cURL)');
    
    // Enable compression if supported
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    // Execute API request
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($curlError || $curlErrno) {
        echo json_encode([
            'success' => false, 
            'message' => 'API request failed: ' . ($curlError ?: 'cURL Error #' . $curlErrno)
        ]);
        exit;
    }
    
    // Check HTTP status code
    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false, 
            'message' => 'API request failed with HTTP code: ' . $httpCode
        ]);
        exit;
    }
    
    // Decode API response
    $responseData = json_decode($apiResponse, true);
    
    // Check if JSON decode was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid JSON response from API: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    // Check API response status
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        // Extract data from API response
        $data = $responseData['data'] ?? [];
        
        // Format response to match original structure (array with single object)
        $response = [[
            'success' => $data['success'] ?? false,
            'endorsement_id' => $data['endorsement_id'] ?? [],
            'aud_fare' => $data['aud_fare'] ?? []
        ]];
        
        echo json_encode($response);
    } else {
        // API returned an error
        $errorMessage = $responseData['message'] ?? 'Unknown error from API';
        echo json_encode([
            'success' => false, 
            'message' => $errorMessage
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing date parameters'
    ]);
}
?>