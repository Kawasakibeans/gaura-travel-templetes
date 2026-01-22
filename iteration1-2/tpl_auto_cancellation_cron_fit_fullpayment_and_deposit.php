<?php
/**
 * Template Name: Auto Cancellation - FIT Full Payment & Deposit
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

// Check if JSON output is requested (for n8n automation)
$json_output = isset($_GET['json']) && $_GET['json'] == '1';

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

function availability_pax_update_ajax2($order_id, $by_user)
{
    // Same as availability_pax_update_ajax - use same API
    return availability_pax_update_ajax($order_id, $by_user);
}

// If JSON output is requested, output JSON only and exit
if ($json_output && !$is_cli) {
    header('Content-Type: application/json');
    // We'll build the JSON response below
}

// If web request and WordPress is loaded, use WordPress header
// Otherwise output standalone HTML
if (!$is_cli && !$json_output && function_exists('get_header')) {
    get_header();
    echo '<div class=\'wpb_column vc_column_container\' style=\'width:95%; margin: 0 auto; padding: 20px 0;\'>';
} elseif (!$is_cli && !$json_output) {
    // Standalone HTML for direct access
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Cancellation - FIT Full Payment & Deposit</title>
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
        .success {
            color: #4CAF50;
            padding: 10px;
            background-color: #e8f5e9;
            border: 1px solid #4CAF50;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="cancellation-container">
<?php
}
?>
    <style>
        .cancellation-container {
            font-family: Arial, sans-serif;
            margin: 80px 0 20px; /* push content below nav bar */
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
        .success {
            color: #4CAF50;
            padding: 10px;
            background-color: #e8f5e9;
            border: 1px solid #4CAF50;
            margin: 10px 0;
        }
    </style>
    <div class="cancellation-container">
        <h1>Auto Cancellation - FIT Full Payment & Deposit</h1>
<?php

$current_date_and_time = date("Y-m-d H:i:s");
$response = []; 

/* FIT cancellation if no payment received after 25 hours starts. */
// Call API to get FIT bookings for full payment cancellation
$apiUrl1 = $base_url . '/auto-cancellation/fit-fullpayment';
$apiResult = callAPI($apiUrl1, 'GET');

// Debug: Show API response (remove in production if needed)
$show_debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$test_db = isset($_GET['test_db']) && $_GET['test_db'] == '1';

if ($show_debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<strong>Debug Info (Full Payment API):</strong><br>';
    echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl1) . '<br>';
    echo '<strong>API Response:</strong><pre>' . htmlspecialchars(print_r($apiResult, true)) . '</pre>';
    echo '</div>';
}

// Test direct database query if requested
if ($test_db && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
    try {
        $db_host = DB_HOST;
        $db_user = DB_USER;
        $db_password = DB_PASSWORD;
        $db_name = DB_NAME;
        
        $host_parts = explode(':', $db_host);
        $db_hostname = $host_parts[0];
        $db_port = isset($host_parts[1]) ? $host_parts[1] : 3306;
        
        $test_conn = new mysqli($db_hostname, $db_user, $db_password, $db_name, $db_port);
        
        if ($test_conn->connect_error) {
            echo '<div class="error">Database connection failed: ' . $test_conn->connect_error . '</div>';
        } else {
            // Test specific order IDs from user's query results
            $specific_order_ids = ['900120387', '910000765', '910000771', '910000763', '910000768', 
                                  '910000774', '910000776', '910000761', '910000770', '910000760', '910000764'];
            $order_ids_str = "'" . implode("','", $specific_order_ids) . "'";
            
            $test_sql_specific = "
                SELECT DISTINCT
                    b.order_id,
                    b.order_date,
                    b.total_amount,
                    COALESCE(ph.total_received, 0) as payment,
                    b.payment_status,
                    b.full_payment_deadline,
                    b.sub_payment_status,
                    b.order_type,
                    (b.total_amount - COALESCE(ph.total_received, 0)) as remaining_amount,
                    NOW() as `current_db_time`,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL THEN 'NULL deadline'
                        WHEN b.full_payment_deadline > NOW() THEN 'Future deadline'
                        ELSE 'Past deadline'
                    END as deadline_status,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL THEN 'Missing deadline'
                        WHEN b.full_payment_deadline > NOW() THEN 'Not expired'
                        WHEN b.payment_status != 'partially_paid' THEN CONCAT('Wrong payment_status: ', b.payment_status)
                        WHEN b.sub_payment_status IN ('BPAY Paid', 'BPAY Received') THEN 'BPAY status'
                        WHEN COALESCE(ph.total_received, 0) >= b.total_amount THEN 'Fully paid'
                        WHEN COALESCE(ph.total_received, 0) = b.total_amount THEN 'Fully paid (equal)'
                        ELSE 'Should be eligible'
                    END as eligibility_reason
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN (
                    SELECT order_id, SUM(trams_received_amount) AS total_received
                    FROM wpk4_backend_travel_payment_history
                    GROUP BY order_id
                ) ph ON b.order_id = ph.order_id
                WHERE b.order_id IN ($order_ids_str)
                ORDER BY b.order_date DESC
            ";
            
            $test_result_specific = $test_conn->query($test_sql_specific);
            
            if ($test_result_specific) {
                $test_rows_specific = $test_result_specific->fetch_all(MYSQLI_ASSOC);
                echo '<div style="background: #e3f2fd; padding: 10px; margin: 10px 0; border: 1px solid #2196F3;">';
                echo '<strong>Specific Order IDs Check (from your query results):</strong><br>';
                echo 'Checking order IDs: ' . implode(', ', $specific_order_ids) . '<br>';
                echo 'Found ' . count($test_rows_specific) . ' booking(s) in database.<br>';
                if (count($test_rows_specific) > 0) {
                    echo '<table style="margin-top: 10px; font-size: 11px;">';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Total</th><th>Payment</th><th>Deadline</th><th>Deadline Status</th><th>Payment Status</th><th>Sub Status</th><th>Eligibility</th></tr>';
                    foreach ($test_rows_specific as $test_row) {
                        $row_class = '';
                        if ($test_row['eligibility_reason'] == 'Should be eligible') {
                            $row_class = 'style="background-color: #e8f5e9;"'; // Green
                        } elseif (strpos($test_row['eligibility_reason'], 'Fully paid') !== false) {
                            $row_class = 'style="background-color: #ffebee;"'; // Red
                        } else {
                            $row_class = 'style="background-color: #fff3cd;"'; // Yellow
                        }
                        echo '<tr ' . $row_class . '>';
                        echo '<td>' . htmlspecialchars($test_row['order_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['total_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['full_payment_deadline'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['deadline_status']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment_status']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['sub_payment_status'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['eligibility_reason']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="error">None of the specified order IDs were found in the database!</div>';
                    echo '<p>This means these order IDs either:</p>';
                    echo '<ul>';
                    echo '<li>Do not exist in wpk4_backend_travel_bookings table</li>';
                    echo '<li>Have been deleted</li>';
                    echo '<li>Are in a different database</li>';
                    echo '</ul>';
                }
                echo '</div>';
            } else {
                echo '<div class="error">Specific order IDs query failed: ' . $test_conn->error . '</div>';
                echo '<div class="error">SQL: ' . htmlspecialchars($test_sql_specific) . '</div>';
            }
            
            // Test query matching DAL exactly (with all filters)
            $test_sql = "
                SELECT DISTINCT
                    b.order_id,
                    b.order_date,
                    b.total_amount,
                    COALESCE(ph.total_received, 0) as payment,
                    b.payment_status,
                    b.full_payment_deadline,
                    b.sub_payment_status,
                    b.order_type,
                    (b.total_amount - COALESCE(ph.total_received, 0)) as remaining_amount,
                    NOW() as `current_db_time`,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL THEN 'NULL deadline'
                        WHEN b.full_payment_deadline > NOW() THEN 'Future deadline'
                        ELSE 'Past deadline'
                    END as deadline_status,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) > NOW() THEN 'Order date + 25h not expired'
                        WHEN b.full_payment_deadline IS NULL AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) <= NOW() THEN 'Order date + 25h expired'
                        ELSE 'Using full_payment_deadline'
                    END as deadline_logic
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN (
                    SELECT order_id, SUM(trams_received_amount) AS total_received
                    FROM wpk4_backend_travel_payment_history
                    GROUP BY order_id
                ) ph ON b.order_id = ph.order_id
                WHERE (
                        b.full_payment_deadline IS NOT NULL AND b.full_payment_deadline <= NOW()
                        OR (b.full_payment_deadline IS NULL AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) <= NOW())
                    )
                    AND b.order_type IN ('gds')
                    AND b.payment_status = 'partially_paid'
                    AND (
                        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                        OR b.sub_payment_status IS NULL
                    )
                    AND COALESCE(ph.total_received, 0) <> b.total_amount
                ORDER BY b.order_date DESC
                LIMIT 50
            ";
            
            $test_result_all = $test_conn->query($test_sql);
            
            if ($test_result_all) {
                $test_rows_all = $test_result_all->fetch_all(MYSQLI_ASSOC);
                echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffc107;">';
                echo '<strong>All Partially Paid GDS Orders (matching DAL query exactly):</strong><br>';
                echo 'Found ' . count($test_rows_all) . ' booking(s).<br>';
                if (count($test_rows_all) > 0) {
                    echo '<table style="margin-top: 10px; font-size: 11px;">';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Total</th><th>Payment</th><th>Remaining</th><th>Deadline</th><th>Deadline Logic</th><th>Sub Status</th></tr>';
                    foreach ($test_rows_all as $test_row) {
                        $deadline_check = '';
                        $current_time_col = 'current_db_time';
                        if (isset($test_row[$current_time_col]) && $test_row['full_payment_deadline']) {
                            $deadline_check = ($test_row['full_payment_deadline'] <= $test_row[$current_time_col]) ? '✓ Eligible' : '✗ Future';
                        } else {
                            $deadline_check = 'NULL';
                        }
                        echo '<tr>';
                        $remaining = (float)$test_row['remaining_amount'];
                        $row_color = ($remaining > 0) ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';
                        echo '<tr style="' . $row_color . '">';
                        echo '<td>' . htmlspecialchars($test_row['order_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['total_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['remaining_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['full_payment_deadline'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['deadline_logic'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['sub_payment_status'] ?? 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
            }
            
            // Now test with deadline filter (matching DAL query exactly)
            $test_sql = "
                SELECT DISTINCT
                    b.order_id,
                    b.order_date,
                    b.total_amount,
                    COALESCE(ph.total_received, 0) as payment,
                    b.payment_status,
                    b.full_payment_deadline,
                    b.sub_payment_status,
                    b.order_type,
                    (b.total_amount - COALESCE(ph.total_received, 0)) as remaining_amount,
                    CASE 
                        WHEN COALESCE(ph.total_received, 0) >= b.total_amount THEN 'Fully Paid'
                        WHEN COALESCE(ph.total_received, 0) < b.total_amount THEN 'Partially Paid'
                        ELSE 'No Payment'
                    END as payment_status_check
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN (
                    SELECT order_id, SUM(trams_received_amount) AS total_received
                    FROM wpk4_backend_travel_payment_history
                    GROUP BY order_id
                ) ph ON b.order_id = ph.order_id
                WHERE b.full_payment_deadline <= NOW()
                    AND b.order_type IN ('gds')
                    AND b.payment_status = 'partially_paid'
                    AND (
                        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                        OR b.sub_payment_status IS NULL
                    )
                AND COALESCE(ph.total_received, 0) <> b.total_amount
                ORDER BY b.order_date DESC
                LIMIT 20
            ";
            
            $test_result = $test_conn->query($test_sql);
            
            // Additional test: Find orders with NULL deadline but order_date + 25h expired
            $test_sql_null_deadline = "
                SELECT DISTINCT
                    b.order_id,
                    b.order_date,
                    b.total_amount,
                    COALESCE(ph.total_received, 0) as payment,
                    (b.total_amount - COALESCE(ph.total_received, 0)) as remaining_amount,
                    b.payment_status,
                    b.full_payment_deadline,
                    b.sub_payment_status,
                    DATE_ADD(b.order_date, INTERVAL 25 HOUR) as order_date_plus_25h,
                    NOW() as `current_db_time`,
                    CASE 
                        WHEN DATE_ADD(b.order_date, INTERVAL 25 HOUR) <= NOW() THEN 'Expired (25h passed)'
                        ELSE 'Not expired yet'
                    END as expiry_status
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN (
                    SELECT order_id, SUM(trams_received_amount) AS total_received
                    FROM wpk4_backend_travel_payment_history
                    GROUP BY order_id
                ) ph ON b.order_id = ph.order_id
                WHERE b.full_payment_deadline IS NULL
                    AND b.order_type IN ('gds')
                    AND b.payment_status = 'partially_paid'
                    AND (
                        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                        OR b.sub_payment_status IS NULL
                    )
                    AND COALESCE(ph.total_received, 0) <> b.total_amount
                    AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) <= NOW()
                ORDER BY b.order_date DESC
                LIMIT 20
            ";
            
            $test_result_null = $test_conn->query($test_sql_null_deadline);
            
            if ($test_result_null) {
                $test_rows_null = $test_result_null->fetch_all(MYSQLI_ASSOC);
                echo '<div style="background: #e1f5fe; padding: 10px; margin: 10px 0; border: 1px solid #03a9f4;">';
                echo '<strong>Orders with NULL deadline but order_date + 25h expired:</strong><br>';
                echo 'Found ' . count($test_rows_null) . ' booking(s).<br>';
                if (count($test_rows_null) > 0) {
                    echo '<table style="margin-top: 10px; font-size: 11px;">';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Order Date + 25h</th><th>Total</th><th>Payment</th><th>Remaining</th><th>Sub Status</th><th>Expiry Status</th></tr>';
                    foreach ($test_rows_null as $test_row) {
                        $remaining = (float)$test_row['remaining_amount'];
                        $row_color = ($remaining > 0) ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';
                        echo '<tr style="' . $row_color . '">';
                        echo '<td>' . htmlspecialchars($test_row['order_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date_plus_25h']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['total_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['remaining_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['sub_payment_status'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['expiry_status']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<p style="margin-top: 10px;"><strong>Note:</strong> Green = Eligible for cancellation (Total > Payment), Red = Fully paid</p>';
                } else {
                    echo '<p>No orders found with NULL deadline and expired 25-hour window.</p>';
                }
                echo '</div>';
            } else {
                echo '<div style="background: #e1f5fe; padding: 10px; margin: 10px 0; border: 1px solid #03a9f4;">';
                echo '<strong>Orders with NULL deadline but order_date + 25h expired:</strong><br>';
                echo '<div class="error">Query failed: ' . $test_conn->error . '</div>';
                echo '</div>';
            }
            
            // Test: Find all partially_paid orders with remaining amount > 0 (regardless of deadline)
            $test_sql_all_partial = "
                SELECT DISTINCT
                    b.order_id,
                    b.order_date,
                    b.total_amount,
                    COALESCE(ph.total_received, 0) as payment,
                    (b.total_amount - COALESCE(ph.total_received, 0)) as remaining_amount,
                    b.payment_status,
                    b.full_payment_deadline,
                    b.sub_payment_status,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL THEN DATE_ADD(b.order_date, INTERVAL 25 HOUR)
                        ELSE b.full_payment_deadline
                    END as effective_deadline,
                    CASE 
                        WHEN b.full_payment_deadline IS NULL AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) <= NOW() THEN 'Expired (25h)'
                        WHEN b.full_payment_deadline IS NULL AND DATE_ADD(b.order_date, INTERVAL 25 HOUR) > NOW() THEN 'Not expired (25h)'
                        WHEN b.full_payment_deadline IS NOT NULL AND b.full_payment_deadline <= NOW() THEN 'Expired (deadline)'
                        WHEN b.full_payment_deadline IS NOT NULL AND b.full_payment_deadline > NOW() THEN 'Not expired (deadline)'
                        ELSE 'Unknown'
                    END as expiry_status
                FROM wpk4_backend_travel_bookings b
                LEFT JOIN (
                    SELECT order_id, SUM(trams_received_amount) AS total_received
                    FROM wpk4_backend_travel_payment_history
                    GROUP BY order_id
                ) ph ON b.order_id = ph.order_id
                WHERE b.order_type IN ('gds')
                    AND b.payment_status = 'partially_paid'
                    AND (
                        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
                        OR b.sub_payment_status IS NULL
                    )
                    AND COALESCE(ph.total_received, 0) < b.total_amount
                ORDER BY b.order_date DESC
                LIMIT 30
            ";
            
            $test_result_all_partial = $test_conn->query($test_sql_all_partial);
            
            if ($test_result_all_partial) {
                $test_rows_all_partial = $test_result_all_partial->fetch_all(MYSQLI_ASSOC);
                echo '<div style="background: #fff9c4; padding: 10px; margin: 10px 0; border: 1px solid #fbc02d;">';
                echo '<strong>All Partially Paid GDS Orders with Remaining Amount > 0 (regardless of deadline):</strong><br>';
                echo 'Found ' . count($test_rows_all_partial) . ' booking(s).<br>';
                if (count($test_rows_all_partial) > 0) {
                    echo '<table style="margin-top: 10px; font-size: 11px;">';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Total</th><th>Payment</th><th>Remaining</th><th>Deadline</th><th>Effective Deadline</th><th>Expiry Status</th><th>Sub Status</th></tr>';
                    foreach ($test_rows_all_partial as $test_row) {
                        $remaining = (float)$test_row['remaining_amount'];
                        $row_color = ($remaining > 0) ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';
                        $expired = (strpos($test_row['expiry_status'], 'Expired') !== false);
                        if ($expired && $remaining > 0) {
                            $row_color = 'background-color: #ffebee; border: 2px solid #f44336;'; // Red border for expired and eligible
                        }
                        echo '<tr style="' . $row_color . '">';
                        echo '<td>' . htmlspecialchars($test_row['order_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['total_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['remaining_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['full_payment_deadline'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['effective_deadline']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['expiry_status']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['sub_payment_status'] ?? 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<p style="margin-top: 10px;"><strong>Note:</strong> Green = Eligible for cancellation (Total > Payment), Red border = Expired and eligible, Red = Fully paid</p>';
                } else {
                    echo '<p>No orders found with partially_paid status and remaining amount > 0.</p>';
                }
                echo '</div>';
            } else {
                echo '<div style="background: #fff9c4; padding: 10px; margin: 10px 0; border: 1px solid #fbc02d;">';
                echo '<strong>All Partially Paid GDS Orders with Remaining Amount > 0:</strong><br>';
                echo '<div class="error">Query failed: ' . $test_conn->error . '</div>';
                echo '</div>';
            }
            
            if ($test_result) {
                $test_rows = $test_result->fetch_all(MYSQLI_ASSOC);
                echo '<div style="background: #e8f5e9; padding: 10px; margin: 10px 0; border: 1px solid #4CAF50;">';
                echo '<strong>Direct Database Query Test:</strong><br>';
                echo 'Found ' . count($test_rows) . ' booking(s) from direct database query.<br>';
                if (count($test_rows) > 0) {
                    echo '<table style="margin-top: 10px; font-size: 12px;">';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Total</th><th>Payment</th><th>Remaining</th><th>Deadline</th><th>Sub Status</th><th>Payment Check</th></tr>';
                    foreach ($test_rows as $test_row) {
                        $row_class = '';
                        if ((float)$test_row['payment'] >= (float)$test_row['total_amount']) {
                            $row_class = 'style="background-color: #ffebee;"'; // Red for fully paid
                        } elseif ((float)$test_row['total_amount'] > (float)$test_row['payment']) {
                            $row_class = 'style="background-color: #e8f5e9;"'; // Green for eligible
                        }
                        echo '<tr ' . $row_class . '>';
                        echo '<td>' . htmlspecialchars($test_row['order_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['total_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['remaining_amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['full_payment_deadline'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['sub_payment_status'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($test_row['payment_status_check'] ?? 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<p style="font-size: 11px; color: #666;">';
                    echo '<span style="background-color: #e8f5e9; padding: 2px 5px;">Green</span> = Eligible for cancellation (Total > Payment)<br>';
                    echo '<span style="background-color: #ffebee; padding: 2px 5px;">Red</span> = Fully paid (should not be cancelled)';
                    echo '</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="error">Database query failed: ' . $test_conn->error . '</div>';
            }
            
            $test_conn->close();
        }
    } catch (Exception $e) {
        echo '<div class="error">Database test error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Check for API errors
if (isset($apiResult['error'])) {
    echo '<div class="error">';
    echo '<strong>API Error (Full Payment):</strong> ' . htmlspecialchars($apiResult['error']);
    if (isset($apiResult['http_code'])) {
        echo ' (HTTP ' . $apiResult['http_code'] . ')';
    }
    echo '<br><strong>API URL:</strong> ' . htmlspecialchars($apiUrl1);
    if (isset($apiResult['response'])) {
        echo '<br><strong>Response:</strong> ' . htmlspecialchars(substr($apiResult['response'], 0, 500));
    }
    echo '</div>';
} else {
    $rows = [];
    // Check multiple possible response structures
    if ($apiResult && isset($apiResult['data']['bookings'])) {
        $rows = $apiResult['data']['bookings'];
    } elseif ($apiResult && isset($apiResult['data']['full_payment_expired'])) {
        $rows = $apiResult['data']['full_payment_expired'];
    } elseif ($apiResult && isset($apiResult['full_payment_expired'])) {
        $rows = $apiResult['full_payment_expired'];
    } elseif ($apiResult && isset($apiResult['data']) && is_array($apiResult['data'])) {
        // If data is directly an array of bookings
        if (isset($apiResult['data'][0]) && isset($apiResult['data'][0]['order_id'])) {
            $rows = $apiResult['data'];
        }
    }

    $row_counter = count($rows);
    $processedOrders = array();	
    $orderIDs = array();
    
    // Debug: Show why bookings might be filtered
    if ($show_debug && $apiResult && isset($apiResult['data']['total_count'])) {
        $total_from_api = (int)$apiResult['data']['total_count'];
        if ($total_from_api > 0 && $row_counter == 0) {
            echo '<div class="error">';
            echo '<strong>Warning:</strong> API reported ' . $total_from_api . ' total bookings, but bookings array is empty. ';
            echo 'This suggests the Service layer filtered out all bookings. ';
            echo 'Possible reasons: getTotalAmount() returned null, or getPaidAmount() calculation differs from main query.';
            echo '</div>';
        }
    }
    
    if ($row_counter > 0) {
        echo '<div class="info"><strong>Section 1: Cancellation for FIT - Full amount based</strong></div>';
        echo '<div class="info">Found ' . $row_counter . ' booking(s) for full payment cancellation.</div>';
        echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>Total / Paid</th><th>Payment Status</th><th>New Payment Status</th></tr>';
    } else {
        echo '<div class="info"><strong>Section 1: Cancellation for FIT - Full amount based</strong></div>';
        if ($apiResult && isset($apiResult['data']['total_count']) && $apiResult['data']['total_count'] > 0) {
            echo '<div class="error">';
            echo 'API reported ' . $apiResult['data']['total_count'] . ' booking(s) in total_count, but bookings array is empty. ';
            echo 'This indicates a data structure mismatch or filtering issue in the Service layer.';
            echo '</div>';
        } else {
            echo '<div class="info">No bookings found for full payment cancellation.</div>';
        }
    }

    foreach ($rows as $row) {
        $order_id = $row['order_id'];
        if (in_array($order_id, $processedOrders)) {
            continue;
        }
        $processedOrders[] = $order_id;
        
        // Total and paid amounts - check different field names
        $total_to_be_paid = isset($row['total_amount']) ? (float)$row['total_amount'] : 0.00;
        $get_paid_amount = isset($row['paid_amount']) ? (float)$row['paid_amount'] : 
                          (isset($row['payment']) ? (float)$row['payment'] : 0.00);
        
        $total_formatted = number_format($total_to_be_paid, 2, '.', '');
        $paid_formatted = number_format($get_paid_amount, 2, '.', '');

        if($total_to_be_paid > $get_paid_amount) {
            $current_email_date = date("Y-m-d H:i:s");
            $by_user = 'fullpayment_deadline_cancellation';
            
            // Only cancel if not in JSON-only mode (to avoid actual cancellation during viewing)
            if (!$json_output) {
                // Call API to cancel booking
                $cancelResult = callAPI(
                    $base_url . '/auto-cancellation/fit-fullpayment/cancel',
                    'POST',
                    ['order_id' => $order_id]
                );
                
                if ($cancelResult && $cancelResult['status'] === 'success') {
                    echo '<div class="success">Order ' . htmlspecialchars($order_id) . ' cancelled successfully</div>';
                    
                    // Seat availability is updated automatically by the API
                    availability_pax_update_ajax($order_id, $by_user);
                } else {
                    echo '<div class="error">Failed to cancel order ' . htmlspecialchars($order_id) . '</div>';
                }
            }

            echo "<tr>
                <td><input type='checkbox' class='order-checkbox' checked value='".htmlspecialchars($order_id)."'></td>
                <td><a href='/manage-wp-orders/?option=search&type=reference&id=".htmlspecialchars($order_id)."'>".htmlspecialchars($order_id)."</a></td>
                <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
                <td>".htmlspecialchars($total_formatted) . " / " . htmlspecialchars($paid_formatted)."</td>
                <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
                <td>".($json_output ? 'pending' : 'cancel')."</td>
            </tr>";

            $response['fullpayment'][] = [
                'order_id' => $order_id,
                'order_date' => $row['order_date'] ?? '',
                'total_amount' => $total_formatted,
                'payment' => $paid_formatted,
                'payment_status' => $row['payment_status'] ?? '',
                'status' => $json_output ? 'pending' : 'canceled'
            ];
        }
    }
    
    if ($row_counter > 0) {
        echo '</table><br/>';
    }
}

// Call API to get bookings for deposit deadline cancellation
$apiUrl2 = $base_url . '/auto-cancellation/fit-deposit-deadline';
$apiResult2 = callAPI($apiUrl2, 'GET');

// Debug: Show API response
if ($show_debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<strong>Debug Info (Deposit Deadline API):</strong><br>';
    echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl2) . '<br>';
    echo '<strong>API Response:</strong><pre>' . htmlspecialchars(print_r($apiResult2, true)) . '</pre>';
    echo '</div>';
}

// Check for API errors
if (isset($apiResult2['error'])) {
    echo '<div class="error">';
    echo '<strong>API Error (Deposit Deadline):</strong> ' . htmlspecialchars($apiResult2['error']);
    if (isset($apiResult2['http_code'])) {
        echo ' (HTTP ' . $apiResult2['http_code'] . ')';
    }
    echo '<br><strong>API URL:</strong> ' . htmlspecialchars($apiUrl2);
    if (isset($apiResult2['response'])) {
        echo '<br><strong>Response:</strong> ' . htmlspecialchars(substr($apiResult2['response'], 0, 500));
    }
    echo '</div>';
} else {
    $rows2 = [];
    // Check multiple possible response structures
    if ($apiResult2 && isset($apiResult2['data']['bookings'])) {
        $rows2 = $apiResult2['data']['bookings'];
    } elseif ($apiResult2 && isset($apiResult2['data']['deposit_expired'])) {
        $rows2 = $apiResult2['data']['deposit_expired'];
    } elseif ($apiResult2 && isset($apiResult2['deposit_expired'])) {
        $rows2 = $apiResult2['deposit_expired'];
    } elseif ($apiResult2 && isset($apiResult2['data']) && is_array($apiResult2['data'])) {
        // If data is directly an array of bookings
        if (isset($apiResult2['data'][0]) && isset($apiResult2['data'][0]['order_id'])) {
            $rows2 = $apiResult2['data'];
        }
    }

    $row_counter2 = count($rows2);
    
    // Debug: Show why bookings might be filtered
    if ($show_debug && $apiResult2 && isset($apiResult2['data']['total_count'])) {
        $total_from_api2 = (int)$apiResult2['data']['total_count'];
        if ($total_from_api2 > 0 && $row_counter2 == 0) {
            echo '<div class="error">';
            echo '<strong>Warning:</strong> API reported ' . $total_from_api2 . ' total bookings, but bookings array is empty. ';
            echo 'This suggests the Service layer filtered out all bookings.';
            echo '</div>';
        }
    }
    
    if ($row_counter2 > 0) {
        echo '<div class="info"><strong>Section 2: Cancellation for GDeals & FIT - Deposit date based</strong></div>';
        echo '<div class="info">Found ' . $row_counter2 . ' booking(s) for deposit deadline cancellation.</div>';
        echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>Deposit Deadline</th><th>Payment</th><th>Payment Status</th><th>Status</th></tr>';
    } else {
        echo '<div class="info"><strong>Section 2: Cancellation for GDeals & FIT - Deposit date based</strong></div>';
        if ($apiResult2 && isset($apiResult2['data']['total_count']) && $apiResult2['data']['total_count'] > 0) {
            echo '<div class="error">';
            echo 'API reported ' . $apiResult2['data']['total_count'] . ' booking(s) in total_count, but bookings array is empty. ';
            echo 'This indicates a data structure mismatch or filtering issue in the Service layer.';
            echo '</div>';
        } else {
            echo '<div class="info">No bookings found for deposit deadline cancellation.</div>';
        }
    }

    foreach ($rows2 as $row) {
        $order_id = $row['order_id'];
        $by_user = 'deposit_deadline_cancellation';
        
        // Only cancel if not in JSON-only mode
        if (!$json_output) {
            // Call API to cancel booking
            $cancelResult = callAPI(
                $base_url . '/auto-cancellation/fit-deposit-deadline/cancel',
                'POST',
                ['order_id' => $order_id]
            );
            
            if ($cancelResult && $cancelResult['status'] === 'success') {
                echo '<div class="success">Order ' . htmlspecialchars($order_id) . ' cancelled successfully</div>';
                
                // Seat availability is updated automatically by the API
                availability_pax_update_ajax2($order_id, $by_user);
            } else {
                echo '<div class="error">Failed to cancel order ' . htmlspecialchars($order_id) . '</div>';
            }
        }
        
        $payment_amount = isset($row['payment']) ? number_format((float)$row['payment'], 2, '.', '') : '0.00';
        
        echo "<tr>
            <td><input type='checkbox' class='order-checkbox' checked value='".htmlspecialchars($order_id)."'></td>
            <td><a href='/manage-wp-orders/?option=search&type=reference&id=".htmlspecialchars($order_id)."'>".htmlspecialchars($order_id)."</a></td>
            <td>".htmlspecialchars($row['order_date'] ?? '')."</td>
            <td>".htmlspecialchars($row['deposit_deadline'] ?? 'N/A')."</td>
            <td>".htmlspecialchars($payment_amount)."</td>
            <td>".htmlspecialchars($row['payment_status'] ?? '')."</td>
            <td>".($json_output ? 'pending' : 'cancel')."</td>
        </tr>";

        $response['deposit'][] = [
            'order_id' => $order_id,
            'order_date' => $row['order_date'] ?? '',
            'deposit_deadline' => $row['deposit_deadline'] ?? '',
            'payment' => $payment_amount,
            'payment_status' => $row['payment_status'] ?? '',
            'status' => $json_output ? 'pending' : 'canceled'
        ];
    }
    
    if ($row_counter2 > 0) {
        echo '</table>';
    }
}

// Output JSON if requested
if ($json_output) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'success',
        'timestamp' => date("Y-m-d H:i:s"),
        'full_payment_expired' => $response['fullpayment'] ?? [],
        'deposit_expired' => $response['deposit'] ?? []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
    </div>
</div>
<?php
// If web request and WordPress is loaded, use WordPress footer
// Otherwise close standalone HTML
if (!$is_cli && function_exists('get_footer')) {
    echo '</div>'; // Close wpb_column div
    get_footer();
} elseif (!$is_cli) {
    ?>
    </div>
</body>
</html>
<?php
}
?>