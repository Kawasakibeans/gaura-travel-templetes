<?php
date_default_timezone_set("Australia/Melbourne");
// Include WordPress core (needed to access WP functions and classes)
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// API base URL
 $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

// Function to make API calls
function callValidateStockAPI($endpoint, $data) {
    global $apiBaseUrl;
    
    $url = $apiBaseUrl . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curlError)) {
        error_log("API Error for $endpoint: " . $curlError);
        return ['stock_available' => false, 'count' => 0];
    }
    
    if ($httpCode !== 200) {
        error_log("API HTTP Error for $endpoint: Status code " . $httpCode . ", Response: " . $response);
        return ['stock_available' => false, 'count' => 0];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("API JSON Error for $endpoint: " . json_last_error_msg() . ", Response: " . $response);
        return ['stock_available' => false, 'count' => 0];
    }
    
    // Extract data from API response if it's wrapped in status/message/data structure
    if (isset($data['data']) && is_array($data['data'])) {
        $result = $data['data'];
        
        // Process count2: change second number to 0 (e.g., "18 2" -> "18 0")
        if (isset($result['count2']) && is_string($result['count2'])) {
            $count2Parts = explode(' ', $result['count2']);
            if (count($count2Parts) >= 2) {
                $result['count2'] = $count2Parts[0] . ' 0';
            }
        }
        
        return $result;
    }
    
    // If response is already in the expected format, return as is
    return $data;
}

// Handle single trip validation
if (isset($_POST['pricing_id']) && !isset($_POST['pricing_id_return'])) {
    $pricing_id = intval($_POST['pricing_id']);
    $pax = isset($_POST['pax']) ? intval($_POST['pax']) : 1;
    
    // Prepare data for API call
    $data = [
        'pricing_id' => $pricing_id,
        'pax' => $pax
    ];
    
    // Call the API
    $result = callValidateStockAPI('/cart/validate-stock', $data);
    
    // Return the response (compact single-line format)
    header('Content-Type: application/json');
    $output = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo trim($output);
    exit;
}

// Handle round trip validation
if (isset($_POST['pricing_id']) && isset($_POST['pricing_id_return'])) {
    $pricing_id = intval($_POST['pricing_id']);
    $pricing_id_return = intval($_POST['pricing_id_return']);
    $pax = isset($_POST['pax']) ? intval($_POST['pax']) : 1;
    
    // Prepare data for API call
    $data = [
        'pricing_id' => $pricing_id,
        'pricing_id_return' => $pricing_id_return,
        'pax' => $pax
    ];
    
    // Call the API
    $result = callValidateStockAPI('/cart/validate-stock-round-trip', $data);
    
    // Return the response (compact single-line format)
    header('Content-Type: application/json');
    $output = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo trim($output);
    exit;
}

// Default response for invalid requests
header('Content-Type: application/json');
$output = json_encode(['stock_available' => false, 'count' => 0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo trim($output);
exit;
?>