<?php
/**
 * Template Name: Get Customer Payment
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sri
 * @to get the customer's partial payment through popup (asiapay) in the middle of checkout and booking confirmation
 */
get_header();
date_default_timezone_set("Australia/Melbourne");
if(isset($_GET['order_id']) && isset($_GET['booked']) && $_GET['order_id'] != '' && $_GET['booked'] == 1)
{
    
    
    
    if (function_exists('wp_travel_gtag_purchase_adder_new'))
    {
        wp_travel_gtag_purchase_adder_new($_GET['order_id']);
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
    
    function reduce_max_pax_by_pax_count($pricingid, $current_order_pax) 
    {
        global $wpdb; // Access the global $wpdb object
    
        // Sanitize inputs to prevent SQL injection
        $pricing_id_new = sanitize_text_field($pricingid);
        $current_order_pax = (int)$current_order_pax;
    
        $table_name = $wpdb->prefix . 'backend_manage_seat_availability';
    
        // Get the current pax
        $current_max_pax = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = %d",
                $pricing_id_new
            )
        );

        if ($current_max_pax === null) {
            //wp_die("Error: Pricing ID $pricing_id_new not found.");
        }
    
        // Calculate the new max_pax
        $update_pax_count_new = (int)$current_max_pax + $current_order_pax;
        
        $updated_by = 'booking_page';
        $updated_current_time = date("Y-m-d H:i:s");
        
        // Update the max_pax value
        $result_status_new = $wpdb->update(
            $table_name,
            ['pax' => $update_pax_count_new, 'pax_updated_by' => $updated_by, 'pax_updated_on' => $updated_current_time ], 
            ['pricing_id' => $pricing_id_new],   
            ['%s', '%s', '%s'],
            ['%s']
        );
        
        $table_name_log = $wpdb->prefix . 'backend_manage_seat_availability_log';
        $order_id_from_url = $_GET['order_id'];
        $wpdb->insert(
            $table_name_log,
            array(
                'pricing_id'     => $pricing_id_new,
                'original_pax'   => $current_max_pax,
                'new_pax'        => $update_pax_count_new,
                'updated_on'     => $updated_current_time,
                'updated_by'     => $updated_by,
                'changed_pax_count'    => $current_order_pax,
                'order_id'       => $order_id_from_url
            ),
            array(
                '%s', // pricing_id (string)
                '%s', // original_pax (integer)
                '%s', // new_pax (integer)
                '%s', // updated_on (datetime)
                '%s', // updated_by (string)
                '%s', // changed_pax_count (integer)
                '%s'  // order_id (string)
            )
        );
        
        return $update_pax_count_new . ' to ' . $pricing_id_new;
    
        if ($result_status_new === false) {
            //wp_die("Error updating max_pax for Pricing ID $pricing_id_new.");
        }
    }
    
    if(!session_id() || session_id() == '' || !isset($_SESSION) || session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
    
    unset($_SESSION['payment_status']);
    unset($_SESSION['payment_ref']);
    unset($_SESSION['payment_confirmation']);
    unset($_SESSION['order_ref_for_payment']);
    
    // Check if the page is being reloaded
    if (!isset($_SESSION['page_reloaded'])) {
        // First load, clear the session only when the page reloads next time
        $_SESSION['page_reloaded'] = true; 
    } else {
        // Page is reloaded, clear the session
        unset($_SESSION['payment_status']);
        unset($_SESSION['page_reloaded']);
    }

    // Your existing code starts here
    $response = ['status' => 'no_payment_yet'];
    
    // Assuming you set the payment status in the session on the success or failure pages
    if (isset($_SESSION['payment_status'])) {
        if ($_SESSION['payment_status'] === 'success') {
            $payment_confirmation_id_asiapay = $_SESSION['payment_ref'];
            $response['status'] = 'success';
        } elseif ($_SESSION['payment_status'] === 'failed') {
            $response['status'] = 'failed';
        }
    }  


    function generatePaymentSecureHash($merchantId, $merchantReferenceNumber, $currencyCode, $amount, $paymentType, $secureHashSecret) 
    {
		$buffer = $merchantId . '|' . $merchantReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $paymentType . '|' . $secureHashSecret;
		return sha1($buffer);
	}

	function verifyPaymentDatafeed($src, $prc, $successCode, $merchantReferenceNumber, $paydollarReferenceNumber, $currencyCode, $amount, $payerAuthenticationStatus, $secureHashSecret, $secureHash) 
	{
		$buffer = $src . '|' . $prc . '|' . $successCode . '|' . $merchantReferenceNumber . '|' . $paydollarReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $payerAuthenticationStatus . '|' . $secureHashSecret;
		$verifyData = sha1($buffer);
		if ($secureHash == $verifyData) {
			return true;
		}
		return false;
	}
	
    // Asia Pay Required Parameters
    //$merchantId='16000806'; // test
    $merchantId='16001455'; // live
    
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
    $successUrl = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_status_success.php";
    $successUrlFullPayment = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_status_success_full_payment.php";
    $failUrl = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_status_failed.php";
    $cancelUrl="https://gauratravel.com.au/";

    //$secureHashSecret = 'rp6RIf6VpNbT4vMTskQ9qu0Gusyp2yJB'; // test
    $secureHashSecret = 'kYLMLkdK8IMMgMuYOqaTgij9PbBROMHP'; // live
    
    $_SESSION['order_ref_for_payment'] = $orderRef;
    
    $order_total_amount = get_post_meta( $org_order_id, 'order_totals' );
    
    $order_departure_time = get_post_meta( $org_order_id, 'wp_travel_departure_date' );
    $order_departure_time = $order_departure_time[0] ?? date("Y-m-d H:i:s");
    
    $post_meta_value = get_post_meta($org_order_id, 'order_items_data', true); // Ensure you're getting a single value
    
    //$order_total_pax = get_post_meta( $org_order_id, 'wp_travel_pax' );
    
    $first_name = get_post_meta( $org_order_id, 'wp_travel_fname_traveller', true );
    
    $order_total_pax_temp = get_post_meta( $org_order_id, 'wp_travel_pax' );
    
    if(isset($_SESSION['isWebBooking']) && $_SESSION['isWebBooking'] == '1')
    {
        add_post_meta($org_order_id, 'isWebBooking', 'yes', true);
        unset($_SESSION['isWebBooking']);
    }
    else
    {
        add_post_meta($org_order_id, 'isWebBooking', 'no', true);
    }
    
    $order_total_pax = 1;
    if ( is_array( $first_name ) ) 
    { 
        reset( $first_name );
        $first_key = key( $first_name );
        $order_total_pax = count($first_name[$first_key]);
    }
    
    if ( !is_numeric($order_total_pax) || $order_total_pax < 0 ) {
        if ( is_numeric($order_total_pax_temp) && $order_total_pax_temp >= 0 ) 
        {
            $order_total_pax = $order_total_pax_temp;
        } else {
            $order_total_pax = 1; 
        }
    }
    
    $is_maxpax_updated_for_booking = get_post_meta($org_order_id, 'is_max_pax_updated', true);
    
    //if(isset($_SESSION['userId']) && $_SESSION['userId'] == 'TGem09oac5g5W4VO4E3DQ6VXWYV2')
    if($is_maxpax_updated_for_booking != 'yes')
    {
        
        $pricing_ids = []; // To store the captured IDs

        if (is_array($post_meta_value)) {
            foreach ($post_meta_value as $key => $item) {
                // Split the key by `_` and capture the last part
                $parts = explode('_', $key);
                $pricing_id = end($parts); // Get the last part
                $pricing_ids[] = $pricing_id; // Store in the array
            }
        }
        
        foreach ($pricing_ids as $pricing_id) 
        {
            $return_pax_update_count = reduce_max_pax_by_pax_count($pricing_id, $order_total_pax);
            add_post_meta($org_order_id, 'is_max_pax_updated', 'yes', true);
            add_post_meta($org_order_id, 'pax count updated to availability check: '. $return_pax_update_count, 'yes', true);
        }            
    }
    
    // Check if the retrieved post_meta value is a string
    if (is_array($post_meta_value)) {
        foreach ($post_meta_value as $key => $value) {
            // Look for dates in the keys using regex
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $key, $matches)) {
                $order_departure_time = $matches[0];
                break;
            }
        }
    }
    
    $current_date_plus_14 = date("Y-m-d", strtotime("+14 days", strtotime(date("Y-m-d"))));
    
    
    
    $order_booking_date = get_the_time('Y-m-d H:i:s', $org_order_id);
    $order_date = new DateTime($order_booking_date, new DateTimeZone('Australia/Melbourne'));

    $current_date = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
    $current_date_minus_30_minutes = clone $current_date;
    $current_date_minus_30_minutes->modify('-75 minutes');
    
    
    $start_day = strtotime(date('Y-m-d', strtotime($order_departure_time)));
    $current_day = strtotime(date('Y-m-d'));
                        
    $travel_vs_current_date_difference = ( $start_day - $current_day ) / (60 * 60 * 24);
    
    if (!empty($order_total_amount) && is_array($order_total_amount) && $order_date > $current_date_minus_30_minutes ) 
    {
        $orderData = $order_total_amount[0];
        // Access the sub_total value
        $totalamount_counted = $orderData['sub_total'];
        $totalamount_counted_slicepay = $totalamount_counted + ($totalamount_counted * (6 / 100));
        
        $amount = $orderData['total_partial'];
        //$amount = 5 * $order_total_pax;
        // global $current_user; 
        // if(isset($current_user))
        // {
        // $currnt_userlogn = $current_user->user_login;
        // if(isset($currnt_userlogn) && $currnt_userlogn == 'sriharshans')
        //     {
        //         //echo '<pre>';
        //         //print_r($amount);
                
        //         //echo '</pre>';
        //         //echo '<pre>';
        //         //print_r($order_total_pax_temp);
        //         //echo '</pre>';
        //     }
        // } 
        
        // Step 1: Calculate 10% of $totalamount_counted and assign to $first_deposit
        $first_deposit = $totalamount_counted_slicepay * 0.05;
        $first_deposit = number_format((float)$first_deposit, 2, '.', '');
        
        // Step 2: Subtract $first_deposit from $totalamount_counted to get $balance_for_slicepay
        $balance_for_slicepay = $totalamount_counted_slicepay - $first_deposit;
        
        $current_timestamp = time(); // Current date as timestamp
        $order_timestamp = strtotime($order_departure_time); // Order departure date as timestamp
        $diff_in_days = ($order_timestamp - $current_timestamp) / (60 * 60 * 24); // Difference in days
        
        // Step 4: Convert days to weeks and round up
        $no_of_weeks_slicepay = ceil($diff_in_days / 7); // Calculate weeks and round up
        $no_of_weeks_slicepay = $no_of_weeks_slicepay - 3;
        //echo $no_of_weeks_slicepay.'</br>';
        $slicePayLink = '';
        // Step 4: Divide $balance_for_slicepay by $no_of_weeks_slicepay to get $weeklypayment_slicepay
        if ($no_of_weeks_slicepay > 0) { 
            $weeklypayment_slicepay = $balance_for_slicepay / $no_of_weeks_slicepay;
            $weeklypayment_slicepay = number_format((float)$weeklypayment_slicepay, 2, '.', '');
            //echo $totalamount_counted_slicepay.'</br>';
            //echo $totalamount_counted;
            $slicePayLink = generateSkyPayPaymentRequest($totalamount_counted, $order_departure_time, $org_order_id); // $totalamount_counted
        } else {
            $weeklypayment_slicepay = 0; 
        }
        
        $secureHash = generatePaymentSecureHash($merchantId, $orderRef, $currCode, $amount, $paymentType, $secureHashSecret); // $secureHash
        
        // Secure hash for $5 deposit

        $secureHash_5 = generatePaymentSecureHash(
            $merchantId,
            $orderRef,
            $currCode,
            $amount, // $5 deposit
            $paymentType,
            $secureHashSecret
        );
        
        
        
        // Secure hash for full payment
        $secureHash_full = generatePaymentSecureHash(
            $merchantId,
            $orderRef,
            $currCode,
            $totalamount_counted, // full amount
            $paymentType,
            $secureHashSecret
        );
        
        $is_slice_pay_eligible = false;
        if($slicePayLink != '' && $order_departure_time > $current_date_plus_14 && $travel_vs_current_date_difference > 31 )
        {
            $is_slice_pay_eligible = true;
        }
        ?>
        <script type="text/javascript">

        var asiapayConfig = {
    
            amount5: "<?php echo $amount; ?>",
    
            amountFull: "<?php echo $totalamount_counted; ?>",
    
            secureHash5: "<?php echo $secureHash_5; ?>",
    
            secureHashFull: "<?php echo $secureHash_full; ?>",
    
            merchantId: "<?php echo $merchantId; ?>",
    
            orderRef: "<?php echo $orderRef; ?>",
    
            currCode: "<?php echo $currCode; ?>",
    
            paymentType: "<?php echo $paymentType; ?>",
    
            successUrl: "<?php echo $successUrl; ?>",
            successUrlFullPayment: "<?php echo $successUrlFullPayment; ?>",
    
            failUrl: "<?php echo $failUrl; ?>",
    
            cancelUrl: "<?php echo $cancelUrl; ?>",
    
            mpsMode: "<?php echo $mpsMode; ?>",
    
            payMethod: "<?php echo $payMethod; ?>",
    
            lang: "<?php echo $lang; ?>"
    
        };
    
    </script>
        <div id="loadingOverlayDiv">
            <div class="loadingContent">
                <center>
                <p id="changing-text-for-booking">Processing your request.</p>
                <div class="scrollBar"></div>
                </br></br>
                <!-- Add dropdown to choose payment amount -->
                    <!-- Hidden field to store selected amount -->
                    <input type="hidden" id="selected_payment_amount" name="selected_payment_amount" value="<?php echo $amount; ?>">

                    <!-- AsiaPay Payment Button -->
                    
                    <button type="button" id="paynow_processer" class="payment_button_main" data-amount="<?php echo $amount; ?>" style="display:none;">Pay $<?php echo $amount; ?> Deposit & Book</button>
                    <div class='extra_break' style="display:none;">
                    OR
                    </div>
                    <?php
                    if($is_slice_pay_eligible)
                    {
                    ?>
                    <a target="_blank" href="<?php echo $slicePayLink; ?>"><button type="button" id="paynow_processer_slicepay" class="payment_button_main" style="display:none;">Pay $<?php echo $weeklypayment_slicepay; ?>/wk with SlicePay</button></a>
                    <div class='extra_break' style="display:none;">
                    <a style="color:#0e6ee9; margin-top:10px; text-decoration:none; " href='https://gauratravel.com.au/book-now-pay-later-2/'><u>Learn more about SlicePay</u></a>
                    </div>
                    <?php
                    }
                    ?>
                    <div class='extra_break' style="display:none;">
                    OR
                    </div>
                    <button type="button" id="paynow_processer_full" class="payment_button_main" data-amount="<?php echo $totalamount_counted; ?>" style="display:none;">Pay Full amount $<?php echo $totalamount_counted; ?></button>

                </center>
                <h6 id="payment-message"></h6>
                <div id="sessionData"></div>
            </div>
        </div>
        <style>
        #paynow_processer, #paynow_processer_slicepay, #paynow_processer_full
        {
            z-index:11000 !important;
        }
        
        #site-footer
        {
            padding-top:0px;
        }
        #loadingOverlayDiv {
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
            #paynow_processer, #paynow_processer_slicepay, #paynow_processer_full
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
            #paynow_processer, #paynow_processer_slicepay, #paynow_processer_full
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
            <p>Otherwise, the booking will not be generated with the information provided.</p>
            <p>Thank you.</p>
            <!--<button type="button" id="paynow_processer">Pay $<?php echo $amount; ?> Deposit & Book</button>-->
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        jQuery(document).ready(function($) {
            // Select all <a> tags inside the form with id "paymentForm"
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
                        // Optionally, update the UI to reflect the session change
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
            // Event listener for the "Proceed to Payment" button
            $('#paynow_processer').on('click', function(event) 
            {
                var amountSubmitted = $(this).data('amount');
                $('#freezeOverlay').css('display', 'flex');
                $('.payment_button_main').css('display', 'none');
                openAsiaPayPopup(amountSubmitted);
            });

            $('#paynow_processer_full').on('click', function(event) 
            {
                var amountSubmitted = $(this).data('amount');
                $('#freezeOverlay').css('display', 'flex');
                $('.payment_button_main').css('display', 'none');
                openAsiaPayPopup(amountSubmitted);
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
                        $('.extra_break').show();
                        $('#paynow_processer_full').show();
                        $('#paynow_processer').show(); // Show the button once all messages have been displayed
                        $('#paynow_processer_slicepay').show();
                    }
                }
            }, randomInterval());
        }
        
        function randomInterval() {
            return Math.random() * 1000; // Generates a random number between 0 and 1000 milliseconds
        }
        
        function openAsiaPayPopup(amount_submitted) {

    var selectedAmount = amount_submitted;



    // Determine secure hash based on selected amount

    var secureHash = (selectedAmount == asiapayConfig.amount5)

        ? asiapayConfig.secureHash5

        : asiapayConfig.secureHashFull;


    var finalSuccessUrl = (selectedAmount == asiapayConfig.amount5)

        ? asiapayConfig.successUrl

        : asiapayConfig.successUrlFullPayment;

    // Open AsiaPay popup

    window.open(

        'https://www.paydollar.com/b2c2/eng/payment/payForm.jsp'

        + '?merchantId=' + asiapayConfig.merchantId

        + '&amount=' + selectedAmount

        + '&orderRef=' + asiapayConfig.orderRef

        + '&currCode=' + asiapayConfig.currCode

        + '&successUrl=' + finalSuccessUrl

        + '&failUrl=' + asiapayConfig.failUrl

        + '&cancelUrl=' + asiapayConfig.cancelUrl

        + '&payType=' + asiapayConfig.paymentType

        + '&lang=' + asiapayConfig.lang

        + '&mpsMode=' + asiapayConfig.mpsMode

        + '&payMethod=' + asiapayConfig.payMethod

        + '&secureHash=' + secureHash

        + '&redirect=0',

        'AsiaPayPopup', 'width=900,height=800'

    );



    paymentStatusInterval = setInterval(checkPaymentStatus, 1000);

}
   
        let paymentStatusInterval;
        function fetchSessionData() {
            $.ajax({
                url: ajax_object.ajax_url, // This URL is passed from WordPress
                method: 'POST', // Use POST method for WordPress AJAX
                dataType: 'json', // Expect JSON data in return
                data: {
                    action: 'fetch_session_data', // Action to trigger PHP handler
                    nonce: ajax_object.nonce // Security nonce passed from PHP
                },
                success: function(response) {
                    if (response.data === "success") {
                        
                        // Stop polling as payment is successful
                        clearInterval(paymentStatusInterval);
                        
                        //$('#sessionData').html('<span style="color: green;">Payment Success: ' + response.data + '</span>'); // Display session data
                        
                        $('#paynow_processer').css('display', 'none');
                        $('#paynow_processer_slicepay').css('display', 'none');
                        $('#paynow_processer_full').css('display', 'none');
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
                        var queryString = currentUrl.substring(currentUrl.indexOf('?'));
                        setTimeout(function() {
                            window.location.href = "thank-you-flight/" + queryString;
                        }, 3000);
                        
                        //$('#wp-travel-book-now').click(); // Automatically click the Book Now button
        
                    } else if (response.status === "no_data") {
                        //$('#sessionData').html('<span style="color: orange;">No payment yet</span>'); // Display message if no data
        
                    } 
                    else if (response.data === "failed") {
                        
                        // Stop polling as payment is successful
                        clearInterval(paymentStatusInterval);
                        
                        //$('#sessionData').html('<span style="color: green;">Payment Success: ' + response.data + '</span>'); // Display session data
                        
                        $('#paynow_processer').css('display', 'none');
                        $('#paynow_processer_slicepay').css('display', 'none');
                        $('#paynow_processer_full').css('display', 'none');
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
                        var queryString = currentUrl.substring(currentUrl.indexOf('?'));
                        setTimeout(function() {
                            window.location.href = "thank-you-flight/" + queryString;
                        }, 3000);
                        
                        //$('#wp-travel-book-now').click(); // Automatically click the Book Now button
        
                    }
                    /*
                    else if (response.data === "failed") {
                        //$('#sessionData').html('<span style="color: red;">Payment failed</span>'); // Display message for failed payment
                        
                        $('#paynow_processer').css('display', 'none');
                        $('#paynow_processer_slicepay').css('display', 'none');
                        
                        // Stop polling as payment has failed
                        clearInterval(paymentStatusInterval);
                        //removePaymentSession();
                        
                        $('#freezeOverlay').css('display', 'none');
                        
                        var messages = [
                            "Validating payment.",
                            "Payment failed.",
                        ];
                
                        // Call the function with the messages
                        cycleMessages(messages, false);
                        
                        isSystemReload = true;
                        
                        var currentUrl = window.location.href;
                        setTimeout(function() {
                            window.location.href = currentUrl;
                        }, 5000);
                        
                    }*/
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
        
        document.getElementById('paynow_processer_slicepay').addEventListener('click', function () {
            isSystemReload = true;
        });
        
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
        
        <script>
            // Track if the customer has been on the page for 5 minutes
            let hasBeenOnPageForFiveMinutes = false;
            let ajaxCallInitiated = false;
            
            // Set a timer to check after 5 minutes
            setTimeout(function() {
                hasBeenOnPageForFiveMinutes = true;
                triggerPaymentPopup();
            }, 5 * 60 * 1000); // 5 minutes in milliseconds
            
            // Detect when the customer tries to close the tab or the browser window
            window.onbeforeunload = function () {
                if (!ajaxCallInitiated) {
                    triggerPaymentPopup();
                }
            };
            
            function triggerPaymentPopup() {
                
                var order_id = <?php echo json_encode($org_order_id); ?>;
                if (ajaxCallInitiated) return; // Prevent multiple AJAX calls
            
                ajaxCallInitiated = true; // Mark AJAX call as initiated
            
                // Make AJAX call to trigger the PHP file
                jQuery.ajax({
                    url: '/wp-content/themes/twentytwenty/templates/tpl_get_customer_partial_payment_through_popup_trigger_payment_no_action.php',
                    method: 'GET',
                    data: {
                        order_id: order_id // Pass order ID to the PHP file
                    },
                    success: function(response) {
                        // Handle the response if necessary
                        console.log('AJAX call successful:', response);
                        
                        var currentUrl = window.location.href;
                        var queryString = currentUrl.substring(currentUrl.indexOf('?'));
                        
                        window.location.href = "thank-you-flight/" + queryString;
                       
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                    // Log the error details
                    console.log('AJAX Error: ', textStatus, errorThrown);
                    console.log('Response Text: ', jqXHR.responseText);  // Show any error response from PHP
                }
                });
            }

        </script>
        <?php
    }
    else
    {
        echo '';
    }
}
get_footer(); ?>