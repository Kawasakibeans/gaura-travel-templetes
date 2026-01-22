<?php
date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Load WordPress to get API_BASE_URL constant from wp-config.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Get API base URL (should be defined in wp-config.php)
$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

// Helper function to call API
function callAPI($url, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("API call failed: $curlError");
        return ['error' => $curlError, 'http_code' => $httpCode];
    }
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("API call returned HTTP $httpCode: " . substr($response, 0, 500));
    return ['error' => 'HTTP ' . $httpCode, 'response' => $response];
}

$current_date_and_time = date("Y-m-d H:i:s");

// Call API to process payment reminders
$apiResult = callAPI($base_url . '/auto-cancellation/payment-reminder/process', 'POST');

// Return JSON output for n8n
header('Content-Type: application/json');

if (isset($apiResult['error'])) {
    // API call failed
    echo json_encode([
        "status" => "error",
        "message" => "API call failed: " . $apiResult['error'],
        "timestamp" => $current_date_and_time
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($apiResult && isset($apiResult['status']) && $apiResult['status'] === 'success') {
    // API returned success - use the data from API response
    echo json_encode([
        "status" => "success",
        "timestamp" => $apiResult['data']['timestamp'] ?? $current_date_and_time,
        "total_checked" => $apiResult['data']['total_checked'] ?? 0,
        "emails_logged" => $apiResult['data']['emails_logged'] ?? 0,
        "details" => $apiResult['data']['details'] ?? []
    ], JSON_PRETTY_PRINT);
} else {
    // API returned error or unexpected format
    echo json_encode([
        "status" => "error",
        "message" => $apiResult['message'] ?? 'Unknown error from API',
        "timestamp" => $current_date_and_time,
        "api_response" => $apiResult
    ], JSON_PRETTY_PRINT);
}
?>