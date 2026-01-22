<?php
/**
 * Template Name: Get Customer FIT Payment
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sri
 * @to get customer's partial payment through popup (asiapay) in the middle of checkout and booking confirmation
 */
get_header();
global $wpdb;

// Define API base URL for maintainability
define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
date_default_timezone_set("Australia/Melbourne");

if(isset($_GET['order_id']) && isset($_GET['booked']) && $_GET['order_id'] != '' && ($_GET['booked'] == 1 || $_GET['booked'] === '1'))
{
    $order_id = $_GET['order_id'];
    
    if(!session_id() || session_id() == '' || !isset($_SESSION) || session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
    
    unset($_SESSION['payment_status']);
    unset($_SESSION['payment_ref']);
    unset($_SESSION['payment_confirmation']);
    unset($_SESSION['order_ref_for_payment']);
    
    // Check if page is being reloaded
    if (!isset($_SESSION['page_reloaded'])) {
        // First load, clear session only when page reloads next time
        $_SESSION['page_reloaded'] = true; 
    } else {
        // Page is reloaded, clear session
        unset($_SESSION['payment_status']);
        unset($_SESSION['page_reloaded']);
    }

    // Your existing code starts here
    $response = ['status' => 'no_payment_yet'];
    
    // Assuming you set payment status in session on success or failure pages
    if (isset($_SESSION['payment_status'])) {
        if ($_SESSION['payment_status'] === 'success') {
            $payment_confirmation_id_asiapay = $_SESSION['payment_ref'];
            $response['status'] = 'success';
        } elseif ($_SESSION['payment_status'] === 'failed') {
            $response['status'] = 'failed';
        }
    }  
    
    function generateSkyPayPaymentRequest($newSalePrice, $departureDate, $order_id_for_payment_request)
    {
        $order_id_from_url = $_GET['order_id'];
        $booked_from_url = $_GET['booked'];
        
        $callbackURL_sky = 'https://gauratravel.com.au/skypay-post-callback/';
        $returnURL_sky = 'https://gauratravel.com.au/slicepay-agreement-signed/?order_id='.$order_id_from_url.'&booked='.$booked_from_url;
        
        $slice_pay_agent_id = 'agent-gaura-41fbn0';

        $href_paylater = '';
        $paylater_link = '';
        $curl_paylater = curl_init();
        curl_setopt_array($curl_paylater, array(
            CURLOPT_URL => 'https://api.slicepay.travel/api/create-link',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'salePrice='.$newSalePrice.'&currency=AUD&departureDate='.$departureDate.'&bookingReference='.$order_id_for_payment_request.'&agentId='.$slice_pay_agent_id.'&bookingNotificationUrl='.$callbackURL_sky.'&redirectUrlSucceeded='.$returnURL_sky,
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        $response_paylater = curl_exec($curl_paylater);

        curl_close($curl_paylater);
                                                                        
        if (!empty($response_paylater)) 
        {
            $doc_paylater = new DOMDocument();
            @$doc_paylater->loadHTML($response_paylater);
            $anchors_paylater = $doc_paylater->getElementsByTagName('a');
                                                                            
            if ($anchors_paylater->length > 0) {
                $anchor_paylater = $anchors_paylater->item(0);
                $href_paylater = $anchor_paylater->getAttribute('href');
            }
            if($href_paylater != '')
            {
                $paylater_link = $href_paylater;
            }
        }
        return $paylater_link;
    }

    // API integration for secure hash generation
    function generatePaymentSecureHash($merchantId, $merchantReferenceNumber, $currencyCode, $amount, $paymentType, $secureHashSecret) 
    {
        // Try to use the API first
        $api_success = false;
        
        try {
            $api_url = API_BASE_URL . '/v1/payments/generate-secure-hash';
            $post_data = json_encode([
                'merchant_id' => $merchantId,
                'merchant_reference' => $merchantReferenceNumber,
                'currency_code' => $currencyCode,
                'amount' => $amount,
                'payment_type' => $paymentType,
                'secure_hash_secret' => $secureHashSecret
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_data)
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Process the response
            if (!$curl_error && $http_code == 200) {
                $response_data = json_decode($response, true);
                
                // Check if API call was successful
                if (isset($response_data['status']) && $response_data['status'] === 'success' && isset($response_data['data']['secure_hash'])) {
                    $api_success = true;
                    error_log("API Success: Secure hash generated successfully for reference: " . $merchantReferenceNumber);
                    return $response_data['data']['secure_hash'];
                } else {
                    error_log("API Error: Failed to generate secure hash. Response: " . $response);
                }
            } else {
                error_log("cURL Error: Failed to connect to API. HTTP Code: " . $http_code . ". URL: " . $api_url);
            }
        } catch (Exception $e) {
            error_log("Exception: " . $e->getMessage());
        }
        
        // Fallback to local generation if API fails
        if (!$api_success) {
            error_log("API call failed, falling back to local secure hash generation for reference: " . $merchantReferenceNumber);
            $buffer = $merchantId . '|' . $merchantReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $paymentType . '|' . $secureHashSecret;
            return sha1($buffer);
        }
    }

    // API integration for payment datafeed verification
    function verifyPaymentDatafeed($src, $prc, $successCode, $merchantReferenceNumber, $paydollarReferenceNumber, $currencyCode, $amount, $payerAuthenticationStatus, $secureHashSecret, $secureHash) 
    {
        // Try to use the API first
        $api_success = false;
        
        try {
            $api_url = API_BASE_URL . '/v1/payments/verify-datafeed';
            $post_data = json_encode([
                'src' => $src,
                'prc' => $prc,
                'success_code' => $successCode,
                'merchant_reference' => $merchantReferenceNumber,
                'paydollar_reference' => $paydollarReferenceNumber,
                'currency_code' => $currencyCode,
                'amount' => $amount,
                'payer_authentication_status' => $payerAuthenticationStatus,
                'secure_hash_secret' => $secureHashSecret,
                'secure_hash' => $secureHash
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_data)
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Process the response
            if (!$curl_error && $http_code == 200) {
                $response_data = json_decode($response, true);
                
                // Check if API call was successful
                if (isset($response_data['status']) && $response_data['status'] === 'success' && isset($response_data['data']['valid'])) {
                    $api_success = true;
                    error_log("API Success: Payment datafeed verified successfully for reference: " . $merchantReferenceNumber);
                    return $response_data['data']['valid'];
                } else {
                    error_log("API Error: Failed to verify payment datafeed. Response: " . $response);
                }
            } else {
                error_log("cURL Error: Failed to connect to API. HTTP Code: " . $http_code . ". URL: " . $api_url);
            }
        } catch (Exception $e) {
            error_log("Exception: " . $e->getMessage());
        }
        
        // Fallback to local verification if API fails
        if (!$api_success) {
            error_log("API call failed, falling back to local payment datafeed verification for reference: " . $merchantReferenceNumber);
            $buffer = $src . '|' . $prc . '|' . $successCode . '|' . $merchantReferenceNumber . '|' . $paydollarReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $payerAuthenticationStatus . '|' . $secureHashSecret;
            $verifyData = sha1($buffer);
            if ($secureHash == $verifyData) {
                return true;
            }
            return false;
        }
    }
    
    // Asia Pay Required Parameters
    //$merchantId='16000806'; // test
    $merchantId='16001455'; // live
    // $merchantId='16001341'; // asia pay + slice pay ( added by tanvi)
    
    //$orderRef= uniqid().date('His');
    //$orderRef = uniqid().bin2hex(random_bytes(2)).date('His');
    
    $microtime = sprintf('%.0f', microtime(true) * 1000);
    $orderRef = uniqid().bin2hex(random_bytes(4)).$microtime;
    
    $org_order_id = $_GET['order_id'];
    
    $customer_ip_address = '';
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $customer_ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $customer_ip_address = $_SERVER['REMOTE_ADDR'];
    else
        $customer_ip_address = 'UNKNOWN';

    $result_ip_address = explode(',', $customer_ip_address);
    $customer_ip_address = array_shift($result_ip_address);
    
    add_post_meta($org_order_id, 'customer_ip_address', $customer_ip_address, true);
    
    $orderRef = $_GET['order_id'].$microtime;
    
    $currCode = '036';
    $paymentType = 'N';
    $mpsMode = "NIL";
    $payMethod = "ALL";
    $lang = "E";
    
    $successUrl = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_status_fit_success.php?orderId=$order_id";
    $failUrl = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_status_fit_failed.php";
    $cancelUrl="https://gauratravel.com.au/";
    // $secureHashSecret = 'rp6RIf6VpNbT4vMTskQ9qu0Gusyp2yJB'; // test
    $secureHashSecret = 'kYLMLkdK8IMMgMuYOqaTgij9PbBROMHP'; // live
    // $secureHashSecret =  'HwrIa7S2SnUtMmGr7jeNKElQP45wLHFt'; // asia pay + slice pay
    $_SESSION['order_ref_for_payment'] = $orderRef;
    
    // Get order details from database
    // Try both possible table names
    $possible_tables = [
        'wpk4_backend_travel_bookings_g360_booking',
        'wpk4_backend_travel_bookings'
    ];
    
    $table_name = null;
    $order_details = null;
    
    // Try each table until we find one that exists and has the order
    foreach ($possible_tables as $table) {
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table
        ));
        
        if ($table_exists) {
            $order_details = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT total_amount, total_pax, travel_date FROM {$table} WHERE order_id = %d",
                    $order_id
                )
            );
            
            if ($order_details) {
                $table_name = $table;
                break; // Found the order, stop searching
            }
        }
    }
    
    // Initialize variables
    $total_amount = 0;
    $total_pax = 0;
    $travel_date = '';
    $amount5percent = 0;
    $balance_amount = 0;
    $weeklypayment_slicepay = 0;
    $slicePayLink = '';
    $is_slice_pay_eligible = false;
    
    if ($order_details) {
        $total_amount = $order_details->total_amount;
        $total_pax = $order_details->total_pax;
        $travel_date = $order_details->travel_date;
        
        // Calculate 5% deposit amount
        $amount5percent = $total_amount * 0.05;
        $amount5percent = number_format((float)$amount5percent, 2, '.', '');
        
        // Calculate balance amount
        $balance_amount = $total_amount - $amount5percent;
        
        $current_timestamp = time(); // Current date as timestamp
        $order_timestamp = strtotime($travel_date); // Order departure date as timestamp
        $diff_in_days = ($order_timestamp - $current_timestamp) / (60 * 60 * 24); // Difference in days
        
        // Calculate weeks for SlicePay
        $no_of_weeks_slicepay = ceil($diff_in_days / 7); // Calculate weeks and round up
        $no_of_weeks_slicepay = $no_of_weeks_slicepay - 3;
        
        if ($no_of_weeks_slicepay > 0) { 
            $weeklypayment_slicepay = $balance_amount / $no_of_weeks_slicepay;
            $weeklypayment_slicepay = number_format((float)$weeklypayment_slicepay, 2, '.', '');
            
            // Generate SlicePay link using API
            $api_success = false;
            try {
                $api_url = API_BASE_URL . '/v1/payments/generate-slicepay-link';
                $post_data = json_encode([
                    'sale_price' => (string)(int)($total_amount * 100), // Convert to cents
                    'departure_date' => date('Y-m-d', strtotime($travel_date)),
                    'order_id' => (string)$order_id,
                    'callback_url' => 'https://gauratravel.com.au/skypay-post-callback/',
                    'return_url' => 'https://gauratravel.com.au/slicepay-agreement-signed/?order_id='.$order_id.'&booked=1'
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($post_data)
                ]);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                // Process the response
                if (!$curl_error && $http_code == 200) {
                    $response_data = json_decode($response, true);
                    
                    // Check if API call was successful
                    if (isset($response_data['status']) && $response_data['status'] === 'success' && isset($response_data['data']['link'])) {
                        $api_success = true;
                        error_log("API Success: SlicePay link generated successfully for order: " . $order_id);
                        $slicePayLink = $response_data['data']['link'];
                    } else {
                        error_log("API Error: Failed to generate SlicePay link. Response: " . $response);
                    }
                } else {
                    error_log("cURL Error: Failed to connect to API. HTTP Code: " . $http_code . ". URL: " . $api_url);
                }
            } catch (Exception $e) {
                error_log("Exception: " . $e->getMessage());
            }
            
            // Fallback to local generation if API fails
            if (!$api_success) {
                error_log("API call failed, falling back to local SlicePay link generation for order: " . $order_id);
                $slicePayLink = generateSkyPayPaymentRequest($total_amount, date('Y-m-d', strtotime($travel_date)), $order_id);
            }
        }
        
        // Determine if SlicePay is eligible
        $current_date_plus_14 = date("Y-m-d", strtotime("+14 days", strtotime(date("Y-m-d"))));
        $start_day = strtotime(date('Y-m-d', strtotime($travel_date)));
        $current_day = strtotime(date('Y-m-d'));
        $travel_vs_current_date_difference = ($start_day - $current_day) / (60 * 60 * 24);
        
        $is_slice_pay_eligible = ($slicePayLink != '' && $travel_date > $current_date_plus_14 && $travel_vs_current_date_difference > 31);
    } else {
        // Order not found - show error message with debugging info
        $possible_tables = [
            'wpk4_backend_travel_bookings_g360_booking',
            'wpk4_backend_travel_bookings'
        ];
        
        $table_info = [];
        $all_sample_orders = [];
        
        foreach ($possible_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            ));
            
            $table_info[$table] = [
                'exists' => $table_exists,
                'sample_orders' => []
            ];
            
            if ($table_exists) {
                $sample_orders = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT order_id FROM {$table} ORDER BY order_id DESC LIMIT 5"
                    ),
                    ARRAY_A
                );
                $table_info[$table]['sample_orders'] = $sample_orders;
                $all_sample_orders = array_merge($all_sample_orders, $sample_orders);
            }
        }
        
        echo '<div style="padding: 50px; text-align: center; max-width: 900px; margin: 0 auto;">';
        echo '<h2 style="color: #d32f2f;">Error: Order Not Found</h2>';
        echo '<p>The order ID <strong>' . esc_html($order_id) . '</strong> could not be found in the database.</p>';
        
        echo '<div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left;">';
        echo '<h3 style="margin-top: 0;">Database Information:</h3>';
        echo '<p><strong>Database Name:</strong> <code>' . esc_html(DB_NAME) . '</code></p>';
        
        foreach ($possible_tables as $table) {
            $info = $table_info[$table];
            echo '<div style="background: white; padding: 15px; margin: 10px 0; border-radius: 3px; border-left: 4px solid ' . ($info['exists'] ? '#4CAF50' : '#f44336') . ';">';
            echo '<p><strong>Table:</strong> <code>' . esc_html($table) . '</code></p>';
            echo '<p><strong>Exists:</strong> ' . ($info['exists'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>') . '</p>';
            
            if ($info['exists']) {
                echo '<p><strong>SQL Query:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 0.9em;">';
                echo esc_html("SELECT total_amount, total_pax, travel_date FROM {$table} WHERE order_id = " . intval($order_id));
                echo '</pre>';
                
                if (!empty($info['sample_orders'])) {
                    echo '<p><strong>Sample Order IDs in this table:</strong></p>';
                    echo '<ul style="list-style: none; padding: 0;">';
                    foreach ($info['sample_orders'] as $sample) {
                        echo '<li>• Order ID: <code>' . esc_html($sample['order_id']) . '</code></li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p style="color: orange;">⚠ Table exists but contains no orders.</p>';
                }
            }
            echo '</div>';
        }
        
        if (!empty($all_sample_orders)) {
            echo '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 20px;">';
            echo '<p><strong>💡 Try using one of these order IDs to test the page:</strong></p>';
            echo '<ul style="list-style: none; padding: 0;">';
            $unique_orders = [];
            foreach ($all_sample_orders as $sample) {
                if (!in_array($sample['order_id'], $unique_orders)) {
                    $unique_orders[] = $sample['order_id'];
                    echo '<li>• <a href="?order_id=' . esc_attr($sample['order_id']) . '&booked=1" style="color: #1976d2; text-decoration: underline;">Order ID: ' . esc_html($sample['order_id']) . '</a></li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<p style="margin-top: 20px;">Please check the order ID and try again.</p>';
        echo '<p style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 20px;">';
        echo '<strong>Note:</strong> See <code>check_order_database_queries.sql</code> for SQL queries to check the database.';
        echo '</p>';
        echo '</div>';
        get_footer();
        exit;
    }
    
    // Generate secure hashes using API or fallback
    $secureHash = generatePaymentSecureHash($merchantId, $orderRef, $currCode, $amount5percent, $paymentType, $secureHashSecret);
    $secureHash5percent = $secureHash; // Use the same hash for 5% deposit
    $secureHash_full = generatePaymentSecureHash($merchantId, $orderRef, $currCode, $total_amount, $paymentType, $secureHashSecret);
    ?>
    <div id="loadingOverlay">
        <div class="loadingContent">
            <center>
                <p id="changing-text-for-booking">Processing your request.</p>
                <div class="scrollBar"></div>
                </br></br>
                <button type="button" id="paynow_processer_deposit" class="payment_button_main" style="display:none;">Pay $<?php echo $amount5percent; ?> Deposit & Book</button>
                
                <div class='extra_break' style="display:none;">
                
                </div>
                <?php
                if($is_slice_pay_eligible)
                {
                ?>
                <a target="_blank" href="<?php echo $slicePayLink; ?>"><button type="button" id="paynow_processer_slicepay" class="payment_button_main" style="display:none;">Pay $<?php echo $weeklypayment_slicepay; ?>/wk with SlicePay</button></a>
                </br>
                <a style="color:white; margin-top:10px; text-decoration:none; " href='https://gauratravel.com.au/book-now-pay-later-2/'>Learn more about SlicePay</a>
                <?php
                }
                ?>
                <div class='extra_break' style="display:none;">
                
                </div>
                <button type="button" id="paynow_processer" class="payment_button_main" style="display:none;">Pay Full amount $<?php echo $total_amount; ?></button>
                
                </center>
                <h6 id="payment-message"></h6>
                <div id="sessionData"></div>
            </div>
        </div>
        <style>
        #paynow_processer_deposit, #paynow_processer, #paynow_processer_slicepay
        {
            z-index:11000 !important;
        }
        
        #site-footer
        {
            padding-top:0px;
        }
        #loadingOverlay {
            height:800px;
            width:100%;
            color:black;
            padding:150px 0px 150px 0px;
            /*background-color: rgba(0, 0, 0, 0.4);*/
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10999;
        }
        
        .loadingContent p {
            color: black;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .scrollBar {
            width: 100%;
            height: 10px;
            background-color: #ccc;
            position: relative;
            overflow: hidden;
        }
        
        .scrollBar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -50%;
            width: 50%;
            height: 100%;
            background-color: #4CAF50;
            animation: scrollLoading 2s linear infinite;
        }
        
        @keyframes scrollLoading {
            0% { left: -50%; }
            100% { left: 100%; }
        }
        
        #freezeOverlay 
        {
            display: none;  
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 24px;
            z-index: 10000;
            text-align: center;
            padding: 20px;
        }
        #freezeOverlay p {
            margin: 10px 0;
        }

        @media only screen and (min-width: 701px) 
        {
            .loadingContent
            {
                width:600px;
            }
            #paynow_processer_deposit, #paynow_processer, #paynow_processer_slicepay
            {
                width: 550px;
                margin-top: 20px;
                padding: 10px 20px;
                font-size: 25px;
                cursor: pointer;
                background-color:#ffbb00!important;
                color:black;
                border-radius:7px;
                height:50px;
            }
        }
        
        @media only screen and (max-width: 700px) 
        {
            .loadingContent
            {
                width:95%;
            }
            #paynow_processer_deposit, #paynow_processer, #paynow_processer_slicepay
            {
                width: 90%;
                margin-top: 20px;
                padding: 10px 20px;
                cursor: pointer;
                background-color:#ffbb00!important;
                color:black;
                border-radius:7px;
                height:50px;
            }
        }
        </style>
        
        <div id="freezeOverlay">
            <p>Please wait until the payment process is completed.</p>
            <p>Otherwise, booking will not be generated with the information provided.</p>
            <p>Thank you.</p>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        jQuery(document).ready(function($) {
            // Select all <a> tags inside form with id "paymentForm"
            $('#paymentForm a').attr('target', '_blank');
        });
        
        function removePaymentSession() {
            jQuery.ajax({
                url: ajaxurl, // 'ajaxurl' is provided by WordPress
                type: 'POST',
                data: {
                    action: 'remove_payment_session'
                },
                success: function(response) {
                    if (response.success) {
                        //console.log('Session variables removed successfully');
                        // Optionally, update UI to reflect session change
                    } else {
                        //console.log('Failed to remove session variables');
                    }
                },
                error: function(error) {
                    //console.error('AJAX request failed:', error);
                }
            });
        }
        
        var isSystemReload = false;
        
        $(document).ready(function() {
            // Event listener for "Proceed to Payment" button
            $('#paynow_processer').on('click', function(event) 
            {
                $('#freezeOverlay').css('display', 'flex');
                $('.payment_button_main').css('display', 'none');
                openAsiaPayPopup();
            });
            
            $('#paynow_processer_deposit').on('click', function(event) 
            {
                $('#freezeOverlay').css('display', 'flex');
                $('.payment_button_main').css('display', 'none');
                openAsiaPayPopup5percent();
            });
            
            $('#paynow_processer_slicepay').on('click', function(event) 
            {
                //$('#freezeOverlay').css('display', 'flex');
                //$('.payment_button_main').css('display', 'none');
                //openAsiaPayPopup();
            });
            
            var messages = [
                "Rechecking availability.",
                "Validating the request.",
                "Processing booking.",
                "Awaiting for payment."
            ];
    
            // Call the function with the messages
            cycleMessages(messages, true);
        
        });
        
        function cycleMessages(messages, is_payment_active) {
            var currentIndex = 0;
            var intervalId = setInterval(function() {
                if (currentIndex < messages.length) {
                    $('#changing-text-for-booking').text(messages[currentIndex]);
                    currentIndex++;
                }
                if (currentIndex === messages.length) {
                    clearInterval(intervalId);
                    if(is_payment_active)
                    {
                        $('#paynow_processer').show(); // Show the button once all messages have been displayed
                        $('#paynow_processer_slicepay').show();
                        $('#paynow_processer_deposit').show();
                        $('.extra_break').show();
                    }
                }
            }, randomInterval());
        }
        
        function randomInterval() {
            return Math.random() * 1000; // Generates a random number between 0 and 1000 milliseconds
        }
        
        function openAsiaPayPopup() {
            // Open AsiaPay in a popup window
            window.open(
                'https://www.paydollar.com/b2c2/eng/payment/payForm.jsp'
                + '?merchantId=' + '<?php echo $merchantId; ?>'
                + '&amount=' + '<?php echo $total_amount; ?>'
                + '&orderRef=' + '<?php echo $orderRef; ?>'
                + '&currCode=' + '<?php echo $currCode; ?>'
                + '&successUrl=' + '<?php echo $successUrl; ?>'
                + '&failUrl=' + '<?php echo $failUrl; ?>'
                + '&cancelUrl=' + '<?php echo $cancelUrl; ?>'
                + '&payType=' + '<?php echo $paymentType; ?>'
                + '&lang=' + '<?php echo $lang; ?>'
                + '&mpsMode=' + '<?php echo $mpsMode; ?>'
                + '&payMethod=' + '<?php echo $payMethod; ?>'
                + '&secureHash=' + '<?php echo $secureHash; ?>'
                + '&redirect=2',
                'AsiaPayPopup', 'width=900,height=800'
            );

            // Payment status is already being polled by fetchSessionData
        }
        
        function openAsiaPayPopup5percent() {
            // Open AsiaPay in a popup window
            window.open(
                'https://www.paydollar.com/b2c2/eng/payment/payForm.jsp'
                + '?merchantId=' + '<?php echo $merchantId; ?>'
                + '&amount=' + '<?php echo $amount5percent; ?>'
                + '&orderRef=' + '<?php echo $orderRef; ?>'
                + '&currCode=' + '<?php echo $currCode; ?>'
                + '&successUrl=' + '<?php echo $successUrl; ?>'
                + '&failUrl=' + '<?php echo $failUrl; ?>'
                + '&cancelUrl=' + '<?php echo $cancelUrl; ?>'
                + '&payType=' + '<?php echo $paymentType; ?>'
                + '&lang=' + '<?php echo $lang; ?>'
                + '&mpsMode=' + '<?php echo $mpsMode; ?>'
                + '&payMethod=' + '<?php echo $payMethod; ?>'
                + '&secureHash=' + '<?php echo $secureHash5percent; ?>'
                + '&redirect=2',
                'AsiaPayPopup', 'width=900,height=800'
            );

            // Payment status is already being polled by fetchSessionData
        }
        
        let paymentStatusInterval;
        function fetchSessionData() {
            $.ajax({
                url: ajax_object.ajax_url, // This URL is passed from WordPress
                method: 'POST', // Use POST method for WordPress AJAX
                dataType: 'json', // Expect JSON data in return
                data: {
                    action: 'fetch_session_data_fit', // Action to trigger PHP handler
                    nonce: ajax_object.nonce // Security nonce passed from PHP
                },
                success: function(response) {
                    if (response.status === "success") {
                        
                        // Stop polling as payment is successful
                        clearInterval(paymentStatusInterval);
                        
                        //$('#sessionData').html('<span style="color: green;">Payment Success: ' + response.data + '</span>'); // Display session data
                        
                        $('#paynow_processer').css('display', 'none');
                        $('#paynow_processer_slicepay').css('display', 'none');
                        $('#paynow_processer_deposit').css('display', 'none');
                        $('.extra_break').css('display', 'none');
                        
                        removePaymentSession();
                        
                        $('#freezeOverlay').css('display', 'none');
                        
                        var messages = [
                            "Validating payment.",
                            "Payment confirmed."
                        ];
                
                        // Call the function with the messages
                        cycleMessages(messages, false);
                        
                        isSystemReload = true;
                        var currentUrl = window.location.href;
                        var queryString = new URLSearchParams(window.location.search).toString();
                        setTimeout(function() {
                            window.location.href = "https://gauratravel.com.au/fit-checkout-thank-you?" + queryString;
                        }, 3000);
                        
                        //$('#wp-travel-book-now').click(); // Automatically click the Book Now button
        
                    } else if (response.status === "no_data") {
                        //$('#sessionData').html('<span style="color: orange;">No payment yet</span>'); // Display message if no data
        
                    } 
                    else if (response.status === "failed") {
                        
                        // Stop polling as payment is successful
                        clearInterval(paymentStatusInterval);
                        
                        //$('#sessionData').html('<span style="color: green;">Payment Success: ' + response.data + '</span>'); // Display session data
                        
                        $('#paynow_processer').css('display', 'none');
                        $('#paynow_processer_slicepay').css('display', 'none');
                        $('#paynow_processer_deposit').css('display', 'none');
                        $('.extra_break').css('display', 'none');
                        
                        removePaymentSession();
                        
                        $('#freezeOverlay').css('display', 'none');
                        
                        var messages = [
                            "Validating payment.",
                            "Payment failed."
                        ];
                
                        // Call the function with the messages
                        cycleMessages(messages, false);
                        
                        isSystemReload = true;
                        var currentUrl = window.location.href;
                        var queryString = new URLSearchParams(window.location.search).toString();
                        setTimeout(function() {
                            window.location.href = "https://gauratravel.com.au/fit-checkout-thank-you?" + queryString;
                        }, 3000);
                    }
                },
                error: function(xhr, status, error) {
                    //$('#sessionData').html('Error fetching payment status: ' + xhr.responseText);
                }
            });
        }
        
        // Start polling every 2 seconds
        $(document).ready(function() {
            paymentStatusInterval = setInterval(fetchSessionData, 2000); // Store the interval ID and start polling
        });
        
        var slicePayButton = document.getElementById('paynow_processer_slicepay');
        if (slicePayButton) {
            slicePayButton.addEventListener('click', function () {
                isSystemReload = true;
            });
        }
        
        window.addEventListener('beforeunload', function (e) {
            if (!isSystemReload) 
            {
                // Only prompt the user if the reload is not system-initiated
                e.preventDefault(); // This instructs the browser to prompt the user
                //$('#exitOverlay').css('display', 'flex');
                return 'Are you sure you want to leave? Changes you made may not be saved.';
            }
        });
        
        </script>
        <?php
    } else {
        // Missing required parameters - show error message
        $missing_params = [];
        if (!isset($_GET['order_id']) || $_GET['order_id'] == '') {
            $missing_params[] = 'order_id';
        }
        if (!isset($_GET['booked']) || ($_GET['booked'] != 1 && $_GET['booked'] !== '1')) {
            $missing_params[] = 'booked (must be 1)';
        }
        ?>
        <div style="padding: 50px; text-align: center; max-width: 800px; margin: 0 auto;">
            <h2 style="color: #d32f2f;">Error: Missing Required Parameters</h2>
            <p>This page requires the following URL parameters:</p>
            <ul style="list-style: none; padding: 0; text-align: left; display: inline-block;">
                <li><strong>order_id</strong> - The order ID (<?php echo isset($_GET['order_id']) && $_GET['order_id'] != '' ? '✓ Provided: ' . esc_html($_GET['order_id']) : '✗ Missing'; ?>)</li>
                <li><strong>booked</strong> - Must be set to 1 (<?php echo isset($_GET['booked']) && ($_GET['booked'] == 1 || $_GET['booked'] === '1') ? '✓ Provided: ' . esc_html($_GET['booked']) : '✗ Missing or invalid (received: ' . (isset($_GET['booked']) ? esc_html($_GET['booked']) : 'none') . ')'; ?>)</li>
            </ul>
            <p style="margin-top: 20px;">Please ensure you are accessing this page with the correct parameters.</p>
            <p style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 20px;">
                <strong>Example URL:</strong><br>
                <code style="background: white; padding: 5px 10px; border-radius: 3px;">?order_id=123&booked=1</code>
            </p>
            <?php if (!empty($missing_params)): ?>
            <p style="color: #d32f2f; margin-top: 20px;">
                <strong>Missing parameters:</strong> <?php echo implode(', ', $missing_params); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

get_footer(); ?>