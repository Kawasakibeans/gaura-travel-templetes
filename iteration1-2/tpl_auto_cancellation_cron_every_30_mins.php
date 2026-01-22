<?php
/**
 * Template Name: Auto Cancellation - Deposit Deadline
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Load WordPress to get API_BASE_URL constant from wp-config.php
// Check if WordPress is already loaded (when accessed as template)
if (!defined('ABSPATH')) {
    $wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        die('Error: WordPress wp-load.php not found at ' . $wp_load_path);
    }
    require_once($wp_load_path);
}

// Check if this is a CLI/cron request or web request
$is_cli = (php_sapi_name() === 'cli' || defined('STDIN'));

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
        error_log("API call failed: $curlError for URL: $url");
        return ['error' => $curlError, 'http_code' => $httpCode];
    }
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("API call returned HTTP $httpCode for URL: $url");
    return ['error' => 'HTTP ' . $httpCode, 'http_code' => $httpCode, 'response' => $response];
}

function availability_pax_update_ajax($order_id, $by_user)
{
    global $base_url;
    
    $apiResult = callAPI(
        $base_url . '/auto-cancellation/update-seat-availability',
        'POST',
        [
            'order_id' => $order_id,
            'by_user' => $by_user
        ]
    );
    
    if ($apiResult && $apiResult['status'] === 'success') {
        return $apiResult['data'];
    }
    return [];
}

// If web request and WordPress is loaded, use WordPress header
// Otherwise output standalone HTML
if (!$is_cli && function_exists('get_header')) {
    get_header();
    echo '<div class=\'wpb_column vc_column_container\' style=\'width:95%; margin: 0 auto; padding: 20px 0;\'>';
} else {
    // Standalone HTML for direct access
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Cancellation - Deposit Deadline</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .cancellation-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .cancellation-container h1 {
            color: #333;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background-color: white;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffebee;
            border: 1px solid #f44336;
            margin: 10px 0;
        }
        .info {
            color: #2196F3;
            padding: 10px;
            background-color: #e3f2fd;
            border: 1px solid #2196F3;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="cancellation-container">
<?php
}
?>
<div class='wpb_column vc_column_container' style='width:95%; margin: 0 auto; padding: 20px 0;'>
    <style>
        .cancellation-container {
            font-family: Arial, sans-serif;
            margin: 20px 0;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .cancellation-container h1 {
            color: #333;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffebee;
            border: 1px solid #f44336;
            margin: 10px 0;
        }
        .info {
            color: #2196F3;
            padding: 10px;
            background-color: #e3f2fd;
            border: 1px solid #2196F3;
            margin: 10px 0;
        }
    </style>
    <div class="cancellation-container">
        <h1>Auto Cancellation - Deposit Deadline Based</h1>
<?php

$current_date_and_time = date("Y-m-d H:i:s");

// Call API to get bookings for deposit deadline cancellation
$apiUrl = $base_url . '/auto-cancellation/deposit-deadline';
$apiResult = callAPI($apiUrl, 'GET');

// Debug: Show API response (remove in production if needed)
$show_debug = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($show_debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<strong>Debug Info:</strong><br>';
    echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl) . '<br>';
    echo '<strong>API Response:</strong><pre>' . htmlspecialchars(print_r($apiResult, true)) . '</pre>';
    echo '</div>';
}

// Check for API errors
if (isset($apiResult['error'])) {
    echo '<div class="error">';
    echo '<strong>API Error:</strong> ' . htmlspecialchars($apiResult['error']);
    if (isset($apiResult['http_code'])) {
        echo ' (HTTP ' . $apiResult['http_code'] . ')';
    }
    echo '<br><strong>API URL:</strong> ' . htmlspecialchars($apiUrl);
    if (isset($apiResult['response'])) {
        echo '<br><strong>Response:</strong> ' . htmlspecialchars(substr($apiResult['response'], 0, 500));
    }
    echo '</div>';
    if (!$is_cli && !function_exists('get_footer')) {
        echo '</body></html>';
    }
    exit;
}

$rows = [];
if ($apiResult && isset($apiResult['data']['bookings'])) {
    $rows = $apiResult['data']['bookings'];
} elseif ($apiResult && isset($apiResult['data']) && is_array($apiResult['data'])) {
    // Check if data is directly an array of bookings
    if (isset($apiResult['data'][0]) && isset($apiResult['data'][0]['order_id'])) {
        $rows = $apiResult['data'];
    } elseif (isset($apiResult['data']['bookings'])) {
        $rows = $apiResult['data']['bookings'];
    }
}

// If API returned success but no bookings found
if ($apiResult && isset($apiResult['status']) && $apiResult['status'] === 'success' && empty($rows)) {
    // Check if there's a total_count to show
    $total_count = isset($apiResult['data']['total_count']) ? $apiResult['data']['total_count'] : 0;
    if ($total_count > 0 && $show_debug) {
        echo '<div class="info">API returned total_count: ' . $total_count . ' but bookings array is empty. This may indicate a data structure mismatch.</div>';
    }
}

$row_counter = count($rows);
$processedOrders = array();	
$orderIDs = array();

// Get total count from API response if available
$total_count = 0;
if ($apiResult && isset($apiResult['data']['total_count'])) {
    $total_count = (int)$apiResult['data']['total_count'];
}

if ($row_counter > 0) {
    echo '<div class="info">';
    echo '<strong>✓ Found ' . $row_counter . ' booking(s) for deposit deadline cancellation.</strong><br>';
    echo 'These orders have passed their deposit deadline and have not received any payment.';
    echo '</div>';
    echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Payment Status</th><th>Status</th></tr>';
} else {
    echo '<div class="info">';
    echo '<strong>✓ No bookings found for deposit deadline cancellation.</strong><br>';
    echo 'This means there are currently no orders that:<br>';
    echo '• Have passed their deposit deadline<br>';
    echo '• Have payment status "partially_paid"<br>';
    echo '• Have not received any payment (paid_amount = 0.00)<br>';
    echo '• Are not from import source<br>';
    echo '• Are GDS or WPT order types<br>';
    if ($total_count > 0) {
        echo '<br><em>Note: API reported ' . $total_count . ' total bookings, but none match the display criteria.</em>';
    }
    echo '</div>';
}

foreach ($rows as $row) 
{
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) 
    {
        continue; // Skip duplicate orders
    }
    $processedOrders[] = $order_id;
    
    // Paid amount is already included in API response
    $get_paid_amount = isset($row['paid_amount']) ? number_format((float)$row['paid_amount'], 2, '.', '') : '0.00';
    
    if($get_paid_amount == '0.00')
    {
        if(isset($row['source']) && $row['source'] != "import")
        {
            $by_user = 'deposit_deadline_cancellation';
            
            /*
            $is_already_stock_changed = 0;
            $query_status_duplicate = "SELECT auto_id as deposit_amount FROM wpk4_backend_travel_bookings where order_id = '$order_id' and payment_modified_by = 'cancel_duplicate_in_checkout'";
            $result_status_duplicate = mysqli_query($mysqli, $query_status_duplicate) or die(mysqli_error($mysqli));
            if(mysqli_num_rows($result_status_duplicate) > 0)
            {
                $is_already_stock_changed = 1;
            }
    
    
            $sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled', payment_modified = '$current_date_and_time', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
        	echo $sql_update_status.'</br>';
        	$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
        	
        	mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, meta_key, meta_value, updated_time, updated_user) 
    			values ('$order_id', 'payment_status', 'canceled', '$current_date_and_time', '$by_user')") or die(mysqli_error($mysqli));
            
            if($is_already_stock_changed == 0)
            {
                availability_pax_update_ajax($order_id, $by_user);
            }
            */
            
            echo "<tr>
                <td><input type='checkbox' class='order-checkbox' checked value='".htmlspecialchars($row['order_id'])."'></td>
                <td><a href='/manage-wp-orders/?option=search&type=reference&id=".htmlspecialchars($row['order_id'])."'>".htmlspecialchars($row['order_id'])."</a>";
                    if (isset($row['source']) && $row['source'] == "import") {
                        echo " <span style='color: red;'>*</span>";
                    }
                    echo "</td>
                <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
                <td>".htmlspecialchars(isset($row['trams_received_amount']) ? $row['trams_received_amount'] : '0.00') ." (".htmlspecialchars($get_paid_amount).")</td>
                <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
                <td>cancel</td>
            </tr>";
        }
    }
}

if ($row_counter > 0) {
    echo '</table>';
}
/* All types cancellation if no payment received in 3 hrs. */

?>
    </div>
<?php
// If web request and WordPress is loaded, use WordPress footer
// Otherwise close standalone HTML
if (!$is_cli && function_exists('get_footer')) {
    echo '</div>'; // Close wpb_column div
    get_footer();
} else {
    ?>
    </div>
</body>
</html>
<?php
}
?>