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

function formatPhoneNumber($phoneNumber) {
    // Remove all non-digit characters
    $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

    // Check for Indian country code (91)
    if (substr($phoneNumber, 0, 2) === '91') {
        return $phoneNumber;
    }

    // Check for Australian country code (61)
    if (substr($phoneNumber, 0, 2) === '61') {
        return $phoneNumber;
    }

    // Handle numbers starting with '0' (Australian local number)
    if (substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '61' . substr($phoneNumber, 1);
    } else {
        // Default to Australian country code (61) if no valid country code is found
        $phoneNumber = '61' . $phoneNumber;
    }

    return $phoneNumber;
}

$current_date_ymd = date("Y-m-d H:i:s");

// Call all 4 API endpoints
$apiEndpoints = [
    '/auto-cancellation/non-payment/reminder',
    '/auto-cancellation/non-payment/zero-payment-3hours',
    '/auto-cancellation/non-payment/fit-25hours',
    '/auto-cancellation/non-payment/bpay-96hours'
];

$allBookings = [];
foreach ($apiEndpoints as $endpoint) {
    $apiResult = callAPI($base_url . $endpoint, 'GET');
    if ($apiResult && isset($apiResult['status']) && $apiResult['status'] === 'success') {
        if (isset($apiResult['data']['bookings'])) {
            $allBookings = array_merge($allBookings, $apiResult['data']['bookings']);
        }
    }
}

// Remove duplicates based on order_id
$processedOrders = [];
$uniqueBookings = [];
foreach ($allBookings as $booking) {
    $order_id = $booking['order_id'] ?? null;
    if ($order_id && !in_array($order_id, $processedOrders)) {
        $processedOrders[] = $order_id;
        $uniqueBookings[] = $booking;
    }
}

$row_counter = count($uniqueBookings);

echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment expiry Date</th><th>Travel date</th><th>Payment Status</th><th>New Payment Status</th></tr>';

foreach ($uniqueBookings as $row) {
    $order_id = $row['order_id'] ?? '';
    $travel_date = $row['travel_date'] ?? '';
    $order_date = $row['order_date'] ?? '';
    $payment_status = $row['payment_status'] ?? '';
    $azupay_expiry_date = $row['azupay_expiry_date'] ?? ''; // May not be in API response
    
    // Note: Phone number query removed since it's not used in the display logic
    // If needed, you'll need to provide an API endpoint for it
    
    $travel_date_obj = new DateTime($travel_date);
    $order_date_obj = new DateTime($order_date);
    $current_date_obj = new DateTime();
    
    $days_difference = $order_date_obj->diff($travel_date_obj)->days; // Difference between order and travel date
    $order_elapsed_time = $order_date_obj->diff($current_date_obj)->days; // Days since order date
    
    $txTime = date("Y-m-d H:i:s");
    $new_status = 'partially_paid';
    
    // Condition 1: If order date and travel date difference is ≤ 31 days AND order_date + 24 hours passed
    $order_date_plus_1day = clone $order_date_obj;
    $order_date_plus_1day->modify('+1 day');
    if ($days_difference < 31 && $order_date_plus_1day <= $current_date_obj) {
        $new_status = 'cancel';
    }
    // Condition 2: If order date and travel date difference is > 31 days AND order_date + 4 days passed
    elseif ($days_difference >= 31) {
        $order_date_plus_4days = clone $order_date_obj;
        $order_date_plus_4days->modify('+4 days');
        if ($order_date_plus_4days <= $current_date_obj) {
            $new_status = 'cancel';
        }
    }
    
    // Check azupay_expiry_date if available
    if (!empty($azupay_expiry_date) && $azupay_expiry_date < $txTime) {
        // Original cancellation logic was commented out
        // If you need to cancel, you'll need to provide the cancel API endpoint
    }
    
    if ($new_status == 'cancel') {
        echo "<tr>
            <td>".htmlspecialchars($order_id)."</td>
            <td>".htmlspecialchars($order_date)."</td>
            <td>".htmlspecialchars($azupay_expiry_date)."</td>
            <td>".htmlspecialchars($travel_date)."</td>
            <td>".htmlspecialchars($payment_status)."</td>
            <td>".htmlspecialchars($new_status)."</td>
        </tr>";
    }
}
echo '</table>';
?>