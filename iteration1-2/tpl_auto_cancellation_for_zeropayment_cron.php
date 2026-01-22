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

$current_email_date = date("Y-m-d H:i:s");

// Call API to get all bookings for zero payment cancellation
$apiUrl = $base_url . '/auto-cancellation/zero-payment';
$apiResult = callAPI($apiUrl, 'GET');

// Initialize data arrays
$reminderBookings = [];
$zeroPayment3HoursBookings = [];
$fit25HoursBookings = [];
$bpay96HoursBookings = [];

if ($apiResult && isset($apiResult['status']) && $apiResult['status'] === 'success') {
    if (isset($apiResult['data'])) {
        $data = $apiResult['data'];
        
        // Extract reminder bookings (query 1)
        if (isset($data['reminder']['bookings'])) {
            $reminderBookings = $data['reminder']['bookings'];
        }
        
        // Extract zero payment 3 hours bookings (query 2)
        if (isset($data['zero_payment_3hours']['bookings'])) {
            $zeroPayment3HoursBookings = $data['zero_payment_3hours']['bookings'];
        }
        
        // Extract FIT 25 hours bookings (query 3)
        if (isset($data['fit_25hours']['bookings'])) {
            $fit25HoursBookings = $data['fit_25hours']['bookings'];
        }
        
        // Extract BPAY 96 hours bookings (query 4)
        if (isset($data['bpay_96hours']['bookings'])) {
            $bpay96HoursBookings = $data['bpay_96hours']['bookings'];
        }
    }
}

// Section 1: Email reminder (20 minutes to 600 minutes after booking)
echo '</br></br>Email reminder for GDeals & FIT</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>Status</th></tr>';

$processedOrders = [];
foreach ($reminderBookings as $row) {
    $order_id = $row['order_id'] ?? '';
    if (in_array($order_id, $processedOrders)) {
        continue;
    }
    $processedOrders[] = $order_id;
    
    // Original file had email sending logic commented out
    // API handles the business logic, we just display
    
    $new_status = $row['new_status'] ?? 'email sent';
    
    echo "<tr>
        <td>".htmlspecialchars($order_id)."</td>
        <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['trams_received_amount'] ?? '0.00')."</td>
        <td>".htmlspecialchars($row['travel_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
        <td>".htmlspecialchars($new_status)."</td>
    </tr>";
}
echo '</table>';
/* All types reminder email which sents in 20 mins after booking ends. */

echo '</br></br></br>';

// Section 2: Zero payment cancellation (3 hours)
echo '</br></br>Cancellation for GDeals & FIT - zero paid in 3 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>Status</th></tr>';

$processedOrders = [];
foreach ($zeroPayment3HoursBookings as $row) {
    $order_id = $row['order_id'] ?? '';
    if (in_array($order_id, $processedOrders)) {
        continue;
    }
    $processedOrders[] = $order_id;
    
    // Original file had cancellation logic commented out
    // API handles the business logic, we just display
    
    $new_status = $row['new_status'] ?? 'cancel';
    
    echo "<tr>
        <td>".htmlspecialchars($order_id)."</td>
        <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['trams_received_amount'] ?? '0.00')."</td>
        <td>".htmlspecialchars($row['travel_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
        <td>".htmlspecialchars($new_status)."</td>
    </tr>";
}
echo '</table>';
/* All types cancellation if no payment received in 3 hrs. */

echo '</br></br></br>';

// Section 3: FIT cancellation (25 hours)
echo '</br></br>Cancellation for FIT - partially paid after 25 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>New Payment Status</th></tr>';

$processedOrders = [];
foreach ($fit25HoursBookings as $row) {
    $order_id = $row['order_id'] ?? '';
    if (in_array($order_id, $processedOrders)) {
        continue;
    }
    $processedOrders[] = $order_id;
    
    // Original file had cancellation logic commented out
    // API handles the business logic, we just display
    
    $new_status = $row['new_status'] ?? 'cancel';
    
    echo "<tr>
        <td>".htmlspecialchars($order_id)."</td>
        <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['trams_received_amount'] ?? '0.00')."</td>
        <td>".htmlspecialchars($row['travel_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
        <td>".htmlspecialchars($new_status)."</td>
    </tr>";
}
echo '</table>';
/* FIT cancellation if no payment received after 25 hours ends. */

// Section 4: BPAY cancellation (96 hours)
echo '</br></br></br>';
echo '</br></br>Cancellation for GDeals & FIT - BPAY Paid after 96 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>New Payment Status</th></tr>';

$processedOrders = [];
foreach ($bpay96HoursBookings as $row) {
    $order_id = $row['order_id'] ?? '';
    if (in_array($order_id, $processedOrders)) {
        continue;
    }
    $processedOrders[] = $order_id;
    
    // Original file had cancellation logic commented out
    // API handles the business logic, we just display
    
    $new_status = $row['new_status'] ?? 'cancel';
    
    echo "<tr>
        <td>".htmlspecialchars($order_id)."</td>
        <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['trams_received_amount'] ?? '0.00')."</td>
        <td>".htmlspecialchars($row['travel_date'] ?? '')."</td>
        <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
        <td>".htmlspecialchars($new_status)."</td>
    </tr>";
}
echo '</table>';
/* All booking cancellation if no payment received after 96 hours ends. */

?>