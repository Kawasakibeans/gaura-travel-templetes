<?php
/**
 * Template Name: Add New Agent Booking
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Haodong
 */
get_header();
include("wp-config-custom.php");

// Load WordPress configuration to get API_BASE_URL
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Use global API_BASE_URL constant (defined in WordPress environment)
// If not defined, fallback to default
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1');
}

$base_url = API_BASE_URL; // Use global constant

global $current_user; 
wp_get_current_user();
$currnt_userlogn = $current_user->user_login;

if(isset($_GET['action']) && $_GET['action'] == 'create') {
    ?>
    <h3 style="text-align: center;">Create Order</h3>
    <div class='table-container'>
        <h5></h5>
        <form method="post">
            <div id="form-container">
                <label for="email">Email:</label>
                <input type="email" name="email" required><br><br>
                
                <label for="amount">Amount:</label>
                <input type="number" name="amount" step="0.01" required><br><br>
                
                <label for="payment_reference">Payment Reference:</label>
                <input type="text" name="payment_reference"><br><br>
                
                <label for="amount">Payment Method:</label>
                <?php
                // Initialize variables for status display
                $api_status = 'unknown';
                $api_status_message = '';
                ?>
                <select name='payment_method' required id='payment_method' style="width:100%; padding:10px;">
                			        <option value="">-- Select Payment Method --</option>
                			        <?php
                			        // Fetch payment methods from API endpoint
                			        // API Endpoint: GET /v1/agent-bookings/payment-methods
                			        // Source: AgentBookingDAL::getPaymentMethods
                			        // Query parameters: none
                			        $payment_methods = [];
                			        $httpCode = null;
                			        $curlError = '';
                			        $response = '';
                			        $apiUrl = $base_url . '/agent-bookings/payment-methods';
                			        
                			        try {
                			            $ch = curl_init();
                			            curl_setopt($ch, CURLOPT_URL, $apiUrl);
                			            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                			            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                			            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                			            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                			            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
                			            
                			            $response = curl_exec($ch);
                			            $curlError = curl_error($ch);
                			            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                			            curl_close($ch);
                			            
                			            if (!empty($curlError)) {
                			                error_log("Payment Methods CURL Error: " . $curlError);
                			            }
                			            
                			            if ($httpCode === 200 && $response) {
                			                $jsonResponse = json_decode($response, true);
                			                $jsonError = json_last_error();
                			                
                			                // Debug: Add ?debug=1 to URL to see API response details
                			            if (isset($_GET['debug'])) {
                			                echo "<!-- API URL: " . htmlspecialchars($apiUrl) . " -->\n";
                			                echo "<!-- HTTP Code: " . $httpCode . " -->\n";
                			                echo "<!-- JSON Decode Error: " . $jsonError . " (" . json_last_error_msg() . ") -->\n";
                			                echo "<!-- Raw Response: " . htmlspecialchars($response) . " -->\n";
                			                echo "<!-- Decoded Response: " . htmlspecialchars(print_r($jsonResponse, true)) . " -->\n";
                			                echo "<!-- CURL Error: " . htmlspecialchars($curlError) . " -->\n";
                			            }
                			                
                			                if ($jsonError !== JSON_ERROR_NONE) {
                			                    error_log("Payment Methods JSON Decode Error: " . json_last_error_msg() . " - Response: " . substr($response, 0, 200));
                			                } else {
                			                    // Handle different response formats
                			                    if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                			                        // Format: {"status": "success", "data": {"payment_methods": [...]}}
                			                        if (isset($jsonResponse['data']['payment_methods']) && is_array($jsonResponse['data']['payment_methods'])) {
                			                            $payment_methods = $jsonResponse['data']['payment_methods'];
                			                        } elseif (is_array($jsonResponse['data'])) {
                			                            // Format: {"status": "success", "data": [...]}
                			                            $payment_methods = $jsonResponse['data'];
                			                        }
                			                    } elseif (isset($jsonResponse['data']) && is_array($jsonResponse['data'])) {
                			                        // Format: {"data": [...]}
                			                        if (isset($jsonResponse['data']['payment_methods']) && is_array($jsonResponse['data']['payment_methods'])) {
                			                            $payment_methods = $jsonResponse['data']['payment_methods'];
                			                        } else {
                			                            $payment_methods = $jsonResponse['data'];
                			                        }
                			                    } elseif (is_array($jsonResponse) && isset($jsonResponse[0]) && is_array($jsonResponse[0])) {
                			                        // Format: [{...}, {...}]
                			                        $payment_methods = $jsonResponse;
                			                    } elseif (is_array($jsonResponse) && !empty($jsonResponse)) {
                			                        // Try direct array format
                			                        $payment_methods = $jsonResponse;
                			                    } else {
                			                        error_log("Payment Methods API: Unexpected response format. JSON: " . print_r($jsonResponse, true));
                			                    }
                			                }
                			            } else {
                			                error_log("Payment Methods API Error: HTTP Code " . $httpCode . ", Response: " . substr($response ?? '', 0, 200));
                			            }
                			        } catch (Exception $e) {
                			            error_log("Payment Methods API Error: " . $e->getMessage());
                			        }
                			        
                			        // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
                			        /*
                			        // SQL Query:
                			        // SELECT account_name, bank_id FROM wpk4_backend_accounts_bank_account
                			        // WHERE bank_id IN (7, 8, 9, 5) ORDER BY account_name ASC;
                			        // Source: AgentBookingDAL::getPaymentMethods
                			        // Method: GET
                			        // Endpoint: /v1/agent-bookings/payment-methods
                			        $query_payment_method = "SELECT account_name, bank_id FROM wpk4_backend_accounts_bank_account where bank_id IN (7,8,9,5) order by account_name asc";
                			        $result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                			        */
                			        
                			        // Debug: Show response info if debug mode is on
                			        if (isset($_GET['debug'])) {
                			            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
                			            echo '<strong>Debug Info:</strong><br>';
                			            echo 'API URL: ' . htmlspecialchars($apiUrl ?? 'N/A') . '<br>';
                			            echo 'HTTP Code: ' . ($httpCode ?? 'N/A') . '<br>';
                			            echo 'Payment Methods Count: ' . count($payment_methods) . '<br>';
                			            if (!empty($response)) {
                			                echo 'Raw API Response: <pre style="max-height: 200px; overflow: auto;">' . htmlspecialchars($response) . '</pre>';
                			            }
                			            echo 'Payment Methods Array: <pre style="max-height: 300px; overflow: auto;">' . print_r($payment_methods, true) . '</pre>';
                			            echo '</div>';
                			        }
                			        
                			        if (empty($payment_methods)) {
                			            // Fallback: Show error message or use old SQL query if API fails
                			            echo '<option value="">No payment methods available (API Error - Check debug mode)</option>';
                			        } else {
                			            foreach($payment_methods as $row_payment_method) {
                			                // Handle both array and object formats
                			                // Try multiple possible field names
                			                if (is_array($row_payment_method)) {
                			                    $bank_id = $row_payment_method['bank_id'] ?? $row_payment_method['id'] ?? $row_payment_method['payment_method_id'] ?? '';
                			                    $account_name = $row_payment_method['account_name'] ?? $row_payment_method['name'] ?? $row_payment_method['method_name'] ?? '';
                			                } else {
                			                    $bank_id = $row_payment_method->bank_id ?? $row_payment_method->id ?? $row_payment_method->payment_method_id ?? '';
                			                    $account_name = $row_payment_method->account_name ?? $row_payment_method->name ?? $row_payment_method->method_name ?? '';
                			                }
                			                
                			                // Skip if bank_id or account_name is empty
                			                if (empty($bank_id) || empty($account_name)) {
                			                    // Debug: Log skipped items
                			                    if (isset($_GET['debug'])) {
                			                        error_log("Skipped payment method - bank_id: " . ($bank_id ?? 'empty') . ", account_name: " . ($account_name ?? 'empty'));
                			                    }
                			                    continue;
                			                }
                			                
                			                if(isset($_GET['payment_method']) && $_GET['payment_method'] != '' && $_GET['payment_method'] == $bank_id)
                			                {
                			                    ?>
                			                    <option value="<?php echo esc_attr($bank_id); ?>" selected><?php echo esc_html($account_name); ?></option>
                			                    <?php
                			                }
                			                else
                			                {
                			                    ?>
                			                    <option value="<?php echo esc_attr($bank_id); ?>"><?php echo esc_html($account_name); ?></option>
                			                    <?php
                			                }
                			            }
                			        }
                			        
                			        // Set status for display
                			        if (isset($httpCode) && $httpCode === 200 && !empty($payment_methods)) {
                			            $api_status = 'success';
                			            $api_status_message = 'Loaded ' . count($payment_methods) . ' payment method(s)';
                			        } elseif (isset($httpCode) && $httpCode === 200 && empty($payment_methods)) {
                			            $api_status = 'warning';
                			            $api_status_message = 'API returned 200 but no payment methods found. Check debug mode.';
                			        } elseif (isset($httpCode) && $httpCode !== 200) {
                			            $api_status = 'error';
                			            $api_status_message = 'API Error: HTTP ' . $httpCode;
                			        } elseif (isset($curlError) && !empty($curlError)) {
                			            $api_status = 'error';
                			            $api_status_message = 'Connection Error: ' . htmlspecialchars($curlError);
                			        } else {
                			            $api_status = 'unknown';
                			            $api_status_message = 'Unable to load payment methods';
                			        }
                			        ?>
                			    </select>
                			    <?php if (isset($api_status) && $api_status !== 'success' && $api_status !== 'unknown'): ?>
                			    <div style="margin-top: 5px; padding: 5px; font-size: 12px; 
                			        background-color: <?php echo $api_status === 'warning' ? '#fff3cd' : '#f8d7da'; ?>; 
                			        color: <?php echo $api_status === 'warning' ? '#856404' : '#721c24'; ?>; 
                			        border: 1px solid <?php echo $api_status === 'warning' ? '#ffeaa7' : '#f5c6cb'; ?>;">
                			        <strong>Status:</strong> <?php echo htmlspecialchars($api_status_message); ?>
                			        <br><small>Add <code>?debug=1</code> to URL for detailed information</small>
                			    </div>
                			    <?php endif; ?>
                			    <br><br>
                
                <label for="remark">Remark:</label>
                <textarea name="remark"></textarea><br><br>
                
                <button type="submit" class="submit-button">Place Order</button>
            </div>
        </form>
    </div>
    <?php
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'];
        $trams_received_amount = $_POST['amount'];
        $payment_reference = $_POST['payment_reference'] ?? '';
        $payment_method = $_POST['payment_method'];
        $trams_remarks = $_POST['remark'] ?? '';
        
        // Create booking via API endpoint
        // API Endpoint: POST /v1/agent-bookings
        // Source: AgentBookingService::createBooking
        // Body parameters: email (required), amount (required), payment_method (required), added_by (required), 
        //                   optional payment_reference, remark
        try {
            $apiUrl = $base_url . '/agent-bookings';
            $apiPayload = [
                'email' => $email,
                'amount' => $trams_received_amount,
                'payment_method' => $payment_method,
                'added_by' => $currnt_userlogn
            ];
            
            if (!empty($payment_reference)) {
                $apiPayload['payment_reference'] = $payment_reference;
            }
            if (!empty($trams_remarks)) {
                $apiPayload['remark'] = $trams_remarks;
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiPayload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300 && $response) {
                $jsonResponse = json_decode($response, true);
                if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                    $booking_data = $jsonResponse['data']['booking'];
                    $new_auto_id = $booking_data['auto_id'] ?? ($booking_data['id'] ?? '');
                    
                        
                    if (!empty($new_auto_id)) {
                        $target_url = add_query_arg(array(
                            'action' => 'view',
                            'auto_id' => $new_auto_id
                        ), '');
                        
                        
                        wp_redirect($target_url);
                        exit;
                    } else {
                        echo '<p style="text-align: center; color: red;">Booking created but auto_id not found in response.</p>';
                    }
                } else {
                    $errorMsg = $jsonResponse['message'] ?? 'Unknown error';
                    echo '<p style="text-align: center; color: red;">API error: ' . htmlspecialchars($errorMsg) . '</p>';
                }
            } else {
                $errorMsg = 'API request failed with HTTP code ' . $httpCode;
                $jsonResponse = json_decode($response, true);
                if (isset($jsonResponse['message'])) {
                    $errorMsg .= ': ' . $jsonResponse['message'];
                }
                echo '<p style="text-align: center; color: red;">' . htmlspecialchars($errorMsg) . '</p>';
            }
        } catch (Exception $e) {
            echo '<p style="text-align: center; color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        
        // OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
        /*
        // SQL Query 1: Create booking shell
        // INSERT INTO wpk4_backend_travel_bookings
        //     (order_type, product_id, order_id, order_date, travel_date, total_pax, payment_status, added_on, added_by)
        // SELECT 'failed', '1234554321', COALESCE(MAX(order_id), 0) + 1, NOW(), NOW(), 1, 'partially_paid', NOW(), ?
        // FROM wpk4_backend_travel_bookings
        // WHERE order_type = 'failed';
        //
        // SQL Query 2: Insert into booking_pax
        // INSERT INTO wpk4_backend_travel_booking_pax
        //     (order_type, product_id, order_id, order_date, email_pax, pax_status, added_on, added_by)
        // VALUES ('failed', '1234554321', ?, ?, ?, 'New', ?, ?);
        //
        // SQL Query 3: Insert into payment_history
        // INSERT INTO wpk4_backend_travel_payment_history
        //     (order_id, source, trams_remarks, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, payment_change_deadline)
        // VALUES (?, 'gds', ?, ?, ?, ?, ?, 'deposit', ?, ?, ?);
        //
        // Source: AgentBookingService::createBooking
        // Method: POST
        // Endpoint: /v1/agent-bookings
        // Body parameters: email, amount, payment_method, added_by; optional payment_reference, remark
        
        $sql_bookings = "INSERT INTO wpk4_backend_travel_bookings (order_type, product_id, order_id, order_date, travel_date, total_pax, payment_status, added_on, added_by)
                        SELECT 'failed', '1234554321', COALESCE(MAX(order_id), 0) + 1, NOW(), NOW(), 1, 'partially_paid', NOW(), '$currnt_userlogn'
                        FROM wpk4_backend_travel_bookings
                        WHERE order_type = 'failed';";
        mysqli_query($mysqli, $sql_bookings) or die(mysqli_error($mysqli));
        $new_auto_id = mysqli_insert_id($mysqli);
        
        if(isset($new_auto_id) && $new_auto_id != '') {
            $sql_new_booking = "SELECT * FROM wpk4_backend_travel_bookings WHERE auto_id='$new_auto_id';";
            $result2 = mysqli_query($mysqli, $sql_new_booking);
            if ($new_booking = mysqli_fetch_assoc($result2)) {
                $order_id = $new_booking['order_id'];
                $order_date = $new_booking['order_date'];
                
                // insert into wpk4_backend_travel_booking_pax
                $sql = "INSERT INTO wpk4_backend_travel_booking_pax (order_type, product_id, order_id, order_date, email_pax, pax_status, added_on, added_by)
                        VALUES ( 'failed', '1234554321', '$order_id', '$order_date', '$email', 'New', '$order_date', '$currnt_userlogn');";
                mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
                
                $previous_order_date = date("Y-m-d H:i:s");
                $payment_refund_deadline = date('Y-m-d H:i:s', strtotime($previous_order_date . ' +96 hours'));

                // insert into wpk4_backend_travel_payment_history
                $sql = "INSERT INTO wpk4_backend_travel_payment_history 
                            (order_id, source, trams_remarks, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, payment_change_deadline) 
                        VALUES ('$order_id', 'gds', '$trams_remarks', '$trams_received_amount', '$payment_reference', '$payment_method', '$order_date', 'deposit', '$order_date', '$currnt_userlogn', '$payment_refund_deadline');";
                mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
                
                $target_url = add_query_arg(array(
                    'action' => 'view',
                    'auto_id' => $new_auto_id
                ), 'add-booking-internal');
                wp_redirect($target_url);
                exit;
            }
            else {
                echo '<p style="text-align: center; color: red;">fail to save</p>';
            }
        }
        else {
            echo '<p style="text-align: center; color: red;">fail to save</p>';
        }
        */
    }
}

else if(isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['auto_id']) && $_GET['auto_id'] != '') {
    $auto_id = $_GET['auto_id'];
    
    // Fetch booking by auto_id from API endpoint
    // API Endpoint: GET /v1/agent-bookings/{autoId}
    // Source: AgentBookingDAL::getBookingByAutoId
    // Query parameters: none (autoId in URL path)
    $row2 = null;
    try {
        $apiUrl = $base_url . '/agent-bookings/' . urlencode($auto_id);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $jsonResponse = json_decode($response, true);
            
            error_log(print_r($jsonResponse, true));
            
            if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                $row2 = $jsonResponse['data']['booking'];
            } elseif (is_array($jsonResponse) && isset($jsonResponse[0])) {
                $row2 = $jsonResponse[0];
            }
        }
    } catch (Exception $e) {
        error_log("Get Booking API Error: " . $e->getMessage());
    }
    
    // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    /*
    // SQL Query:
    // SELECT * FROM wpk4_backend_travel_bookings WHERE auto_id = ?;
    // Source: AgentBookingDAL::getBookingByAutoId
    // Method: GET
    // Endpoint: /v1/agent-bookings/{autoId}
    $sql2 = "SELECT * FROM `wpk4_backend_travel_bookings` WHERE auto_id='$auto_id';";
    $result2 = mysqli_query($mysqli, $sql2);
    */
    
    if($row2) {
        ?>
        <h3 style="text-align: center;">Order Summary</h3>
        <div class='table-container'>
            <h5>Order Information</h5>
        	<table class="table">
        		<tr>
        			<td>Order ID</td>
        			<td>
        			    <?php 
                        $order_id = is_array($row2) ? ($row2['order_id'] ?? '') : ($row2->order_id ?? '');
                        echo sprintf('%06d', $order_id); 
                        $co_order_id = is_array($row2) ? ($row2['co_order_id'] ?? '') : ($row2->co_order_id ?? '');
                        if(!empty($co_order_id)) { 
                            echo ' ' . esc_html($co_order_id); 
                        } 
                        ?>
        			</td>
        		</tr>
        		<tr>
        			<td>Order Date</td>
        			<td><?php echo esc_html(is_array($row2) ? ($row2['order_date'] ?? '') : ($row2->order_date ?? '')); ?></td>
        		</tr>
        		<tr>
        			<td>Travel Type</td>
        			<td><?php echo esc_html(is_array($row2) ? ($row2['t_type'] ?? '') : ($row2->t_type ?? '')); ?></td>
        		</tr>
        	</table>
        	<p style="text-align: center; color: green;">New order is successfully saved!</p>
		</div>
        <?php
        if(isset($_GET['trip-id']) && $_GET['trip-id'] != '' && isset($_GET['dep-date']) && $_GET['dep-date'] != '') {
            $trip_id = $_GET['trip-id'];
            $dep_date_for_product_manager = $_GET['dep-date'];
            
            // Fetch stock product details from API endpoint
            // API Endpoint: GET /v1/agent-bookings/stock-product
            // Source: AgentBookingDAL::getStockProduct
            // Query parameters: trip_code (required), travel_date (required)
            $row = null;
            try {
                $apiUrl = $base_url . '/agent-bookings/stock-product';
                $queryParams = http_build_query([
                    'trip_code' => $trip_id,
                    'travel_date' => $dep_date_for_product_manager
                ]);
                $fullUrl = $apiUrl . '?' . $queryParams;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fullUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $jsonResponse = json_decode($response, true);
                    if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                        $row = $jsonResponse['data'];
                    } elseif (is_array($jsonResponse) && isset($jsonResponse[0])) {
                        $row = $jsonResponse[0];
                    }
                }
            } catch (Exception $e) {
                error_log("Stock Product API Error: " . $e->getMessage());
            }
            
            // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
            /*
            // SQL Query:
            // SELECT * FROM wpk4_backend_stock_product_manager
            // WHERE trip_code = ? AND travel_date = ?;
            // Source: AgentBookingDAL::getStockProduct
            // Method: GET
            // Endpoint: /v1/agent-bookings/stock-product
            // Query parameters: trip_code, travel_date
            $sql = "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager';";
            $result = mysqli_query($mysqli, $sql);
            */
            
            if($row) {
                ?>
                <div class='table-container'>
                    <h5>Trip Information</h5>
                    <table class="table">
                        <tr>
                            <td>Product Title</td>
                            <td><input type='text' name='product_title' value='<?php echo esc_attr(is_array($row) ? ($row['product_title'] ?? '') : ($row->product_title ?? '')); ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Trip Code</td>
                            <td><input type='text' name='trip_code' value='<?php echo esc_attr(is_array($row) ? ($row['trip_code'] ?? '') : ($row->trip_code ?? '')); ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Product ID</td>
                            <td><input type='text' name='product_id' value='<?php echo esc_attr(is_array($row) ? ($row['product_id'] ?? '') : ($row->product_id ?? '')); ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Travel Date</td>
                            <td><input type='text' name='travel_date' value='<?php echo esc_attr(is_array($row) ? ($row['travel_date'] ?? '') : ($row->travel_date ?? '')); ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Pax Count</td>
                            <td><input type='text' name='total_pax' value='<?php echo esc_attr(is_array($row2) ? ($row2['total_pax'] ?? '') : ($row2->total_pax ?? '')); ?>' readonly></td>
                        </tr>
                    </table>
                </div>
                <?php
            }
        }
    }
    else {
        echo '<p style="text-align: center; color: red;">fail to save</p>';
    }
}

else if(isset($_GET['action']) && $_GET['action'] == 'add' 
    && isset($_GET['trip-id']) && $_GET['trip-id'] != '' 
    && isset($_GET['dep-date']) && $_GET['dep-date'] != '' ) {
        
    $trip_id = $_GET['trip-id'];
    $dep_date_for_product_manager = $_GET['dep-date'];
    $seat_available = 999;
    if(isset($_GET['seat-available']) && $_GET['seat-available'] != '') {
        $seat_available = $_GET['seat-available'];
    }
    
    // Fetch stock product details from API endpoint
    // API Endpoint: GET /v1/agent-bookings/stock-product
    // Source: AgentBookingDAL::getStockProduct
    // Query parameters: trip_code (required), travel_date (required)
    $row = null;
    try {
        $apiUrl = $base_url . '/agent-bookings/stock-product';
        $queryParams = http_build_query([
            'trip_code' => $trip_id,
            'travel_date' => $dep_date_for_product_manager
        ]);
        $fullUrl = $apiUrl . '?' . $queryParams;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $jsonResponse = json_decode($response, true);
            if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                $row = $jsonResponse['data'];
            } elseif (is_array($jsonResponse) && isset($jsonResponse[0])) {
                $row = $jsonResponse[0];
            }
        }
    } catch (Exception $e) {
        error_log("Stock Product API Error: " . $e->getMessage());
    }
    
    // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    /*
    // SQL Query:
    // SELECT * FROM wpk4_backend_stock_product_manager
    // WHERE trip_code = ? AND travel_date = ?;
    // Source: AgentBookingDAL::getStockProduct
    // Method: GET
    // Endpoint: /v1/agent-bookings/stock-product
    // Query parameters: trip_code, travel_date
    $sql = "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager';";
    $result = mysqli_query($mysqli, $sql);
    */
    
    if($row) {
        ?>
        <h3 style="text-align: center;">New Order</h3>
        <form id="mainForm" method="POST">
            <!-- Trip Information -->
            <div class='table-container'>
                <h5>Trip Information</h5>
                <table class="table">
                    <tr>
                        <td>Product Title</td>
                        <td><input type='text' name='product_title' value='<?php echo esc_attr(is_array($row) ? ($row['product_title'] ?? '') : ($row->product_title ?? '')); ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Trip Code</td>
                        <td><input type='text' name='trip_code' value='<?php echo esc_attr(is_array($row) ? ($row['trip_code'] ?? '') : ($row->trip_code ?? '')); ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Product ID</td>
                        <td><input type='text' name='product_id' value='<?php echo esc_attr(is_array($row) ? ($row['product_id'] ?? '') : ($row->product_id ?? '')); ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Travel Date</td>
                        <td><input type='text' name='travel_date' value='<?php echo esc_attr(is_array($row) ? ($row['travel_date'] ?? '') : ($row->travel_date ?? '')); ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Pax Count</td>
                        <td><input type='text' name='total_pax' id="form_count" value='0' readonly></td>
                    </tr>
                </table>
            </div>
            
            <!-- Add Pax Info -->
            <div id="form-container">
                <!-- Dynamically added forms will appear here -->
            </div>
            <input type="hidden" id="form_count" name="form_count" value="0">
            <button type="button" class="add-button" onclick="addNewTableForm()">+</button>
            <center><input type="submit" class="submit-button" name="save_booking" value="Place Order"></center>
            <script>
                let formCount = 0; // Initial form count
                const seatLimit = <?php echo $seat_available; ?>; // Seat limit from PHP
                function addNewTableForm() {
                    if (formCount < seatLimit) {
                        formCount++;
                        document.getElementById('form_count').value = formCount;
                        const formContainer = document.getElementById('form-container');
                        
                        const newTable = document.createElement('div');
                        newTable.classList.add('table-container');
                        newTable.id = `form_${formCount}`; // Unique ID for the form
                        newTable.innerHTML = `
                            <h6>Pax ${formCount}</h6>
                            <table class="table">
                                <tr>
                                    <td>Salutation</td><td><input type='text' name='${formCount}_salutation' value=''></td>
                                </tr>
                                <tr>
                                    <td>First Name</td><td><input type='text' name='${formCount}_fname' value=''></td>
                                </tr>
                                <tr>
                                    <td>Last Name</td><td><input type='text' name='${formCount}_lname' value=''></td>
                                </tr>
                                <tr>
                                    <td>Gender</td><td><input type='text' name='${formCount}_gender' value=''></td>
                                </tr>
                                <tr>
                                    <td>Passport Number</td><td><input type='text' name='${formCount}_ppn' value=''></td>
                                </tr>
                                <tr>
                                    <td>Passport Expiry</td><td><input type='text' class='date-picker' name='${formCount}_ppe' value=''></td>
                                </tr>
                                <tr>
                                    <td>DOB</td><td><input type='text' class='date-picker' name='${formCount}_dob' value=''></td>
                                </tr>
                                <tr>
                                    <td>Country</td><td><input type='text' name='${formCount}_country' value=''></td>
                                </tr>
                                <tr>
                                    <td>Meal</td><td><input type='text' name='${formCount}_meal' value=''></td>
                                </tr>
                                <tr>
                                    <td>Wheelchair</td><td><input type='text' name='${formCount}_wheelchair' value=''></td>
                                </tr>
                                <tr>
                                    <td>Phone</td><td><input type='text' name='${formCount}_phone_pax' value=''></td>
                                </tr>
                                <tr>
                                    <td>Email</td><td><input type='text' name='${formCount}_email_pax' value=''></td>
                                </tr>
                            </table>
                            <button type="button" class="delete-button" style="background-color: red;" onclick="deleteForm(${formCount})">-</button>
                        `;
                
                        formContainer.appendChild(newTable);
                
                        // Initialize the date picker for the newly added fields
                        $(`#form_${formCount} .date-picker`).datepicker({
                            dateFormat: 'yy-mm-dd',
                            yearRange: "1900:2100"
                        });
                    } else {
                        alert('No more seats available');
                    }
                }
                function deleteForm(count) {
                    const formToDelete = document.getElementById(`form_${count}`);
                    if (formToDelete) {
                        formToDelete.remove();
                        formCount--;
                        document.getElementById('form_count').value = formCount;
                        updatePaxNumbers();
                    }
                }
                function updatePaxNumbers() {
                    const allForms = document.querySelectorAll('.table-container');
                    allForms.forEach((form, index) => {
                        form.querySelector('h6').textContent = `Pax ${index + 1}`;
                        form.id = `form_${index + 1}`;
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach(input => {
                            input.name = `${index + 1}_${input.name.split('_').slice(1).join('_')}`;
                        });
                    });
                }
            </script>
        </form>
        <?php
    }
    
    // Handle form submission
    if (isset($_POST['save_booking'])) 
    {
        $product_title = $_POST['product_title'];
        $trip_code = $_POST['trip_code'];
        $product_id = $_POST['product_id'];
        $travel_date = $_POST['travel_date'];
        $total_pax = $_POST['total_pax'];
        
        // Fetch latest large order ID from API endpoint
        // API Endpoint: GET /v1/agent-bookings/last-order
        // Source: AgentBookingDAL::getLastLargeOrder
        // Query parameters: none
        $new_orderID = 90000001; // Default fallback
        try {
            $apiUrl = $base_url . '/agent-bookings/last-order';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $jsonResponse = json_decode($response, true);
                if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                    $last_order = $jsonResponse['data'];
                    $last_order_id = is_array($last_order) ? ($last_order['order_id'] ?? 0) : ($last_order->order_id ?? 0);
                    $new_orderID = $last_order_id + 1;
                } elseif (is_array($jsonResponse) && isset($jsonResponse[0])) {
                    $last_order = $jsonResponse[0];
                    $last_order_id = is_array($last_order) ? ($last_order['order_id'] ?? 0) : ($last_order->order_id ?? 0);
                    $new_orderID = $last_order_id + 1;
                }
            }
        } catch (Exception $e) {
            error_log("Last Order API Error: " . $e->getMessage());
        }
        
        // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
        /*
        // SQL Query:
        // SELECT * FROM wpk4_backend_travel_bookings
        // WHERE order_id > 90000000 ORDER BY order_id DESC LIMIT 1;
        // Source: AgentBookingDAL::getLastLargeOrder
        // Method: GET
        // Endpoint: /v1/agent-bookings/last-order
        $query_get_last_id = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id > 90000000 order by order_id desc limit 1";
        $result_get_last_id = mysqli_query($mysqli, $query_get_last_id) or die(mysqli_error($mysqli));
        $row_get_last_id = mysqli_fetch_assoc($result_get_last_id);
        $new_orderID = $row_get_last_id['order_id'] + 1;
        */
                    
        $date_current = date('Y-m-d H:i:s');
        
        // Fetch PNR for trip and travel date from API endpoint
        // API Endpoint: GET /v1/agent-bookings/pnr
        // Source: AgentBookingDAL::getPnrForTrip
        // Query parameters: trip_code (required), travel_date (required)
        $new_pnr = '';
        $travel_date_ymd = date('Y-m-d', strtotime($travel_date));
        try {
            $apiUrl = $base_url . '/agent-bookings/pnr';
            $queryParams = http_build_query([
                'trip_code' => $trip_code,
                'travel_date' => $travel_date_ymd
            ]);
            $fullUrl = $apiUrl . '?' . $queryParams;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $jsonResponse = json_decode($response, true);
                if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                    $pnr_data = $jsonResponse['data'];
                    $new_pnr = is_array($pnr_data) ? ($pnr_data['pnr'] ?? '') : ($pnr_data->pnr ?? '');
                } elseif (is_array($jsonResponse) && isset($jsonResponse['pnr'])) {
                    $new_pnr = $jsonResponse['pnr'];
                } elseif (is_string($jsonResponse)) {
                    $new_pnr = $jsonResponse;
                }
            }
        } catch (Exception $e) {
            error_log("PNR API Error: " . $e->getMessage());
        }
        
        // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
        /*
        // SQL Query:
        // SELECT pnr FROM wpk4_backend_stock_management_sheet
        // WHERE trip_id = ? AND DATE(dep_date) = ? LIMIT 1;
        // Source: AgentBookingDAL::getPnrForTrip
        // Method: GET
        // Endpoint: /v1/agent-bookings/pnr
        // Query parameters: trip_code, travel_date
        $query_select_pnr = "SELECT pnr FROM wpk4_backend_stock_management_sheet WHERE trip_id ='$trip_code' AND date(dep_date) ='$travel_date_ymd' ";
        $result_select_pnr = mysqli_query($mysqli, $query_select_pnr) or die(mysqli_error($mysqli));
        if(mysqli_num_rows($result_select_pnr) > 0)
        {
            $row_select_pnr = mysqli_fetch_assoc($result_select_pnr);
            $new_pnr = $row_select_pnr['pnr'];
        }
        */
                    
                    
        $insert_sql_book = "
                        INSERT INTO `wpk4_backend_travel_bookings` (
                            `order_type`, `order_id`, `order_date`, `t_type`, `product_title`, `trip_code`, 
                            `product_id`, `travel_date`, `total_pax`, `payment_status`, `source`, `added_on`,
                            `added_by` )
                        VALUES (
                            'Agent', '$new_orderID', '$date_current', 'oneway', '$product_title', '$trip_code', 
                            '$product_id', '$travel_date', '$total_pax', 'partially_paid', 'import', '$date_current', 
                            '$currnt_userlogn'
                        )
                    ";
        mysqli_query($mysqli, $insert_sql_book);

        $new_auto_id = mysqli_insert_id($mysqli);
        
        // update wpk4_backend_travel_booking_pax
        if(isset($new_auto_id) && $new_auto_id != '') 
        {
            // Fetch booking by auto_id from API endpoint
            // API Endpoint: GET /v1/agent-bookings/{autoId}
            // Source: AgentBookingDAL::getBookingByAutoId
            // Query parameters: none (autoId in URL path)
            $row = null;
            try {
                $apiUrl = $base_url . '/agent-bookings/' . urlencode($new_auto_id);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $jsonResponse = json_decode($response, true);
                    if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
                        $row = $jsonResponse['data'];
                    } elseif (is_array($jsonResponse) && isset($jsonResponse[0])) {
                        $row = $jsonResponse[0];
                    }
                }
            } catch (Exception $e) {
                error_log("Get Booking API Error: " . $e->getMessage());
            }
            
            // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
            /*
            // SQL Query:
            // SELECT * FROM wpk4_backend_travel_bookings WHERE auto_id = ?;
            // Source: AgentBookingDAL::getBookingByAutoId
            // Method: GET
            // Endpoint: /v1/agent-bookings/{autoId}
            $sql = "SELECT * FROM `wpk4_backend_travel_bookings` WHERE auto_id='$new_auto_id';";
            $result2 = mysqli_query($mysqli, $sql);
            */
            
            if ($row) 
            {
                for ($i = 1; $i <= $total_pax; $i++) 
                {
                    $salutation = mysqli_real_escape_string($mysqli, $_POST[$i . '_salutation']);
                    $fname = mysqli_real_escape_string($mysqli, $_POST[$i . '_fname']);
                    $lname = mysqli_real_escape_string($mysqli, $_POST[$i . '_lname']);
                    $gender = mysqli_real_escape_string($mysqli, $_POST[$i . '_gender']);
                    $ppn = mysqli_real_escape_string($mysqli, $_POST[$i . '_ppn']);
                    $ppe = mysqli_real_escape_string($mysqli, $_POST[$i . '_ppe']);
                    $dob = mysqli_real_escape_string($mysqli, $_POST[$i . '_dob']);
                    $country = mysqli_real_escape_string($mysqli, $_POST[$i . '_country']);
                    $meal = mysqli_real_escape_string($mysqli, $_POST[$i . '_meal']);
                    $wheelchair = mysqli_real_escape_string($mysqli, $_POST[$i . '_wheelchair']);
                    $phone_pax = mysqli_real_escape_string($mysqli, $_POST[$i . '_phone_pax']);
                    $email_pax = mysqli_real_escape_string($mysqli, $_POST[$i . '_email_pax']);
            
                    $order_type = is_array($row) ? ($row['order_type'] ?? '') : ($row->order_type ?? '');
                    $order_id = is_array($row) ? ($row['order_id'] ?? '') : ($row->order_id ?? '');
                    $order_date = is_array($row) ? ($row['order_date'] ?? '') : ($row->order_date ?? '');
                    $product_id = is_array($row) ? ($row['product_id'] ?? '') : ($row->product_id ?? '');
                    $payment_status = is_array($row) ? ($row['payment_status'] ?? '') : ($row->payment_status ?? '');
                    
                    // Escape for SQL (still needed for the INSERT query below)
                    $order_type = mysqli_real_escape_string($mysqli, $order_type);
                    $order_id = mysqli_real_escape_string($mysqli, $order_id);
                    $order_date = mysqli_real_escape_string($mysqli, $order_date);
                    $product_id = mysqli_real_escape_string($mysqli, $product_id);
                    $payment_status = mysqli_real_escape_string($mysqli, $payment_status);
            
                    $insert_sql = "
                        INSERT INTO `wpk4_backend_travel_booking_pax` (
                            `salutation`, `fname`, `lname`, `gender`, `ppn`, `ppe`, 
                            `dob`, `country`, `meal`, `wheelchair`, `phone_pax`, `email_pax`,
                            `order_type`, `order_id`, `order_date`, `product_id`, `payment_status`, `pax_status`, `added_on`, `added_by`, `pnr`
                        ) VALUES (
                            '$salutation', '$fname', '$lname', '$gender', '$ppn', '$ppe', 
                            '$dob', '$country', '$meal', '$wheelchair', '$phone_pax', '$email_pax', 
                            '$order_type', '$order_id', '$order_date', '$product_id', '$payment_status', 'New', '$order_date', '$currnt_userlogn', '$new_pnr'
                        )
                    ";
                    echo $insert_sql;
            
                    mysqli_query($mysqli, $insert_sql);
                }
                /*
                $target_url = add_query_arg(array(
                    'action' => 'view',
                    'auto_id' => $new_auto_id,
                    'trip-id' => $trip_code,
                    'dep-date' => $travel_date
                ), 'add-booking-internal');
                wp_redirect($target_url);
                exit;
                */
                
                echo '<script>window.location.href="?action=view&auto_id='.$new_auto_id.'&trip-id='.$trip_code.'&dep-date='.$travel_date.'";</script>';

            }
            else {
                echo '<p style="text-align: center; color: red;">fail to save</p>';
            }
        }
        else {
            echo '<p style="text-align: center; color: red;">fail to save</p>';
        }
    }
}

// redirect to create
else {
    echo "<script>window.location.href = '?action=create';</script>";
}

?>

<style>
    .table-container {
        margin: 20px auto;
        width: 80%;
        border: 1px solid black;
        padding: 1%;
        padding-top: 0;
    }
    .table {
        width: 100%;
        margin-bottom: 20px;
    }
    .add-button, .delete-button {
        display: block;
        margin: 20px auto;
        padding: 10px 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 16px;
    }
    .submit-button {
        display: block;
        margin: 20px auto;
    }
</style>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<?php get_footer(); ?>
