<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set the timezone
date_default_timezone_set('Australia/Melbourne');

/**
 * Wipes realtime booking data for a specific date by calling the API.
 *
 * @param string $targetDate The date (YYYY-MM-DD) for which to wipe the bookings.
 * @return bool True on success, false on failure.
 */
function wipeRealtimeBookingsFromAPI(string $targetDate): bool {
    // Load WordPress environment to access API_BASE_URL constant
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

    // Fallback if the constant is not defined
    if (!defined('API_BASE_URL')) {
        error_log('API_BASE_URL constant is not defined. Cannot proceed.');
        return false;
    }

    $apiBaseUrl = API_BASE_URL;
    $endpoint = rtrim($apiBaseUrl, '/') . '/realtime-bookings/wipe/yesterday';

    // Prepare the request body
    $requestBody = json_encode(['date' => $targetDate]);

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); // Using DELETE as specified in documentation
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($requestBody)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30-second timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // As per previous examples

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            error_log("API cURL Error for realtime booking wipe: " . $curlError);
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("API HTTP Error for realtime booking wipe: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }

        // Optional: Check for a specific success flag in JSON response
        $result = json_decode($response, true);
        if (isset($result['success']) && $result['success'] === false) {
             error_log("API returned success=false for realtime booking wipe. Response: " . $response);
             return false;
        }

        return true;

    } catch (Exception $e) {
        error_log("API Exception for realtime booking wipe: " . $e->getMessage());
        return false;
    }
}

// --- Main Script Execution ---

// Determine the target date.
// Priority: Command line argument > GET parameter > yesterday's date.
 $targetDate = $argv[1] ?? $_GET['date'] ?? date('Y-m-d', strtotime('yesterday'));

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $targetDate)) {
    echo "Error: Invalid date format provided. Please use YYYY-MM-DD.\n";
    exit(1);
}

echo "Attempting to wipe realtime bookings for date: " . htmlspecialchars($targetDate) . "\n";

// Debug: Print the endpoint and request body
 $apiBaseUrl = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';
 $endpoint = rtrim($apiBaseUrl, '/') . '/realtime-bookings/wipe/yesterday';
 $requestBody = json_encode(['date' => $targetDate]);

echo "API Endpoint: " . htmlspecialchars($endpoint) . "\n";
echo "Request Method: DELETE\n";
echo "Request Body: " . htmlspecialchars($requestBody) . "\n";

// Call the API function
 $success = wipeRealtimeBookingsFromAPI($targetDate);

// Provide feedback
if ($success) {
    echo "Success: Realtime bookings for " . htmlspecialchars($targetDate) . " have been wiped.\n";
    exit(0); // Exit with success code
} else {
    echo "Error: Failed to wipe realtime bookings. Check error logs for details.\n";
    exit(1); // Exit with error code
}