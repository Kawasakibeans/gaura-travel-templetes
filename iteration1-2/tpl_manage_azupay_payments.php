<?php
/**
 * Template Name: Manage Azupay Requests
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();
require 'vendor/autoload.php';

$apiUrl = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass_main.php';
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_USERAGENT      => 'GTX-SettingsFetcher/1.0',
    CURLOPT_SSL_VERIFYPEER => true,   // keep true in prod
    CURLOPT_SSL_VERIFYHOST => 2,      // keep strict
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false) {
    die("Failed to load settings: $err");
}
if ($http !== 200) {
    // Show a snippet of body for debugging
    die("Settings endpoint HTTP $http.\n".substr($body, 0, 500));
}

$resp = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON: ".json_last_error_msg()."\n".substr($body, 0, 500));
}
if (!is_array($resp) || empty($resp['success'])) {
    die("Invalid settings response shape.\n".substr($body, 0, 500));
}

$settings = $resp['data'] ?? [];
foreach ($settings as $k => $v) {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
        $GLOBALS[$k] = $v;
    }
}

?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
include("wp-config-custom.php");
$current_time = date('Y-m-d H:i:s');

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
if($row_ip_selection['ip_address'] == $ip_address)
{

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    
    if(current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ) )
    {
        if(!isset($_GET['pg']))
	    {
	        ?>
	        <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
        	<script>		
        	window.addEventListener("load", function (event) 
        	{
        	    var currentdate = new Date(); 
            	var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
            	let drp = new DateRangePicker('orderdate_selector',
                {
                    maxDate: end_maxtime,
                    timePicker: false,
                    alwaysShowCalendars: true,
                    singleDatePicker: true,
                    autoApply: false,
            		autoUpdateInput: false,
                    locale: {
                        format: "YYYY-MM-DD",
                    }
                },
                function (start) {
                    var start_fixed = start.format().slice(0,10);
            		document.getElementById("orderdate_selector").value = start_fixed;
                })
        	});
			window.addEventListener("load", function (event) 
        	{
            	let drp = new DateRangePicker('tripdate_selector',
                {
                    timePicker: false,
                    alwaysShowCalendars: true,
                    singleDatePicker: true,
                    autoApply: false,
            		autoUpdateInput: false,
                    locale: {
                        format: "YYYY-MM-DD",
                    }
                },
                function (start) {
                    var start_fixed = start.format().slice(0,10);
            		document.getElementById("tripdate_selector").value = start_fixed;
                })
        	});
			window.addEventListener("load", function (event) 
        	{
        	    var currentdate = new Date(); 
            	var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
            	let drp = new DateRangePicker('paymentrequestdate_selector',
                {
                    maxDate: end_maxtime,
                    timePicker: false,
                    alwaysShowCalendars: true,
                    singleDatePicker: true,
                    autoApply: false,
            		autoUpdateInput: false,
                    locale: {
                        format: "YYYY-MM-DD",
                    }
                },
                function (start) {
                    var start_fixed = start.format().slice(0,10);
            		document.getElementById("paymentrequestdate_selector").value = start_fixed;
                })
        	});
			
			function searchordejs() 
			{
				var orderdate_selector = document.getElementById("orderdate_selector").value;
				var tripdate_selector = document.getElementById("tripdate_selector").value;
				var order_id_selector = document.getElementById("order_id_selector").value;
				var paymentrequestdate_selector = document.getElementById("paymentrequestdate_selector").value;
				var status_selector = document.getElementById("status_selector").value;	

				window.location='?order_date=' + orderdate_selector + '&travel_date=' + tripdate_selector + '&order_id=' + order_id_selector + '&paymentrequest_date=' + paymentrequestdate_selector + '&status=' + status_selector ;
				
			}
			</script>
	        <table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
    		    <tr>
    				<td width='13%'>
    				    Order Date
    				    <input type='text' name='orderdate_selector' value='<?php if(isset($_GET['order_date'])) { echo $_GET['order_date']; } ?>' id='orderdate_selector'>
    				</td>
    				<td width='13%'>
    				    Travel Date
    				    <input type='text' name='tripdate_selector' value='<?php if(isset($_GET['travel_date'])) { echo $_GET['travel_date']; } ?>' id='tripdate_selector'>
    				</td>
    				<td width='13%'>
    				    Payment request date
    				    <input type='text' name='paymentrequestdate_selector' value='<?php if(isset($_GET['paymentrequest_date'])) { echo $_GET['paymentrequest_date']; } ?>' id='paymentrequestdate_selector'>
    				</td>
    				<td width='13%'>
    				    Order ID
    				    <input type='text' name='order_id_selector' value='<?php if(isset($_GET['order_id'])) { echo $_GET['order_id']; } ?>' id='order_id_selector'>
    				</td>
    				<td width='13%'>
    				    Status
    					<select name='status_selector' id='status_selector' style="width:100%; padding:10px;">
    					    <option value='' <?php if(isset($_GET['status']) && $_GET['status']=='') { echo 'selected'; } ?>>All</option>
    					    <option value='waiting' <?php if(isset($_GET['status']) && $_GET['status'] == 'waiting' ) { echo 'selected'; } ?>>Waiting</option>
    						<option value='complete' <?php if(isset($_GET['status']) && $_GET['status']=='complete') { echo 'selected'; } ?>>Complete</option>
    						<option value='expired' <?php if(isset($_GET['status']) && $_GET['status'] == 'expired' ) { echo 'selected'; } ?>>Expired</option>
    					</select>
    				</td>
    			</tr>
    			<tr>
    				<td colspan='9' style='text-align:center;'>
    				    <button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
    				</td>
				</tr>
				</table>
	        <?php
	        $previous_5_days = date('Y-m-d', strtotime(date("Y-m-d") . ' -5 day'));
	        
	        $order_date_sql = "bookings.auto_id != 'DUMMYID' AND ";
	        if(isset($_GET['order_date']) && $_GET['order_date'] != '')
	        {
	            $order_date_sql = "date( bookings.order_date ) = '".$_GET['order_date']."' AND ";
	        }
	        
	        $travel_date_sql = "bookings.auto_id != 'DUMMYID' AND ";
	        if(isset($_GET['travel_date']) && $_GET['travel_date'] != '')
	        {
	            $travel_date_sql = "date( bookings.travel_date ) = '".$_GET['travel_date']."' AND ";
	        }
	        
	        $orderid_sql = "bookings.auto_id != 'DUMMYID' AND ";
	        if(isset($_GET['order_id']) && $_GET['order_id'] != '')
	        {
	            $orderid_sql = "payments.order_id = '".$_GET['order_id']."' AND ";
	        }
	        
	        $request_date_sql = "bookings.auto_id != 'DUMMYID' ";
	        if(isset($_GET['paymentrequest_date']) && $_GET['paymentrequest_date'] != '')
	        {
	            $request_date_sql = "date( payments.requested_on ) = '".$_GET['paymentrequest_date']."' ";
	        }
	        
	        $is_hide_failed_payments = '';
	        if(isset($_GET['failed_status']) && $_GET['failed_status'] != '')
	        {
	            if($_GET['failed_status'] == 'yes')
	            {
	                $is_hide_failed_payments = 'yes';
	            }
	            else
	            {
	                $is_hide_failed_payments = 'no';
	            }
	        }
	        
    	    $query = "
                SELECT bookings.order_id, bookings.trip_code, bookings.order_date, payments.azupay_payid, bookings.travel_date, bookings.order_type, payments.type_of_payment, payments.requested_on, payments.status, payments.amount, payments.paid_on, payments.amount_paid, payments.payment_request_id, payments.payment_client_id FROM wpk4_backend_travel_booking_custom_payments payments 
                    JOIN wpk4_backend_travel_bookings bookings ON  
                        payments.order_id = bookings.order_id 
    			    where $order_date_sql $travel_date_sql $orderid_sql $request_date_sql order by payments.auto_id desc LIMIT 60;
            ";
            //echo $query;
            
            $result = $mysqli->query($query);
            echo '<table style="font-size:13px;">
                    <tr>
                        <th width="10%">Order ID</th>
                        <th width="10%">Type of payment</th>
                        <th width="10%">Order Date</th>
                        <th width="10%">Travel Date</th>
                        <th width="10%">Requested on</th>
                        <th width="10%">Payment date</th>
                        <th width="10%">Amount</th>
                        <th width="10%">Status</th>
                    </tr>';
            if ($result) {
                // Assuming you want to fetch all rows and print them
                while ($row = $result->fetch_assoc()) 
                {
                    $azupay_payid = '';
                    $data = '';
                    $azupay_payid = $row['azupay_payid'];
                    //echo $row['status'].'</br>';
                    $status_from_azupay = '';
                    
                    {
                        try 
                        {
                            $authorization_code = 'SECR7566D1_c4cc3709d612d1e0e677833ffbcef703_9Kz3JvUrYqPECSwl';
                            $access_url = 'https://api.azupay.com.au/v1'; // live
                            
                            $paymentRequestId = $row['payment_request_id'];
                            $client = new \GuzzleHttp\Client();
                            $response = $client->request('POST', $access_url.'/paymentRequest/search', [
                                'body' => '{"PaymentRequestSearch":{"payID":"'.$azupay_payid.'"}}',
                                'headers' => [
                                    'Authorization' => $authorization_code,
                                    'accept' => 'application/json',
                                    'content-type' => 'application/json',
                                ],
                            ]);
                            
                            $data = json_decode($response->getBody(), true);
                            
                            //print_r($data);

                            $paymentRequestId = $data['records']['PaymentRequestStatus']['paymentRequestId'] ?? '';
                            $status_from_azupay = $data['records'][0]['PaymentRequestStatus']['status'] ?? '';
                            $checkupStatus = strtolower($data['records'][0]['PaymentRequestStatus']['status'] ?? '');
                            $clientId = $data['records'][0]['PaymentRequest']['clientId'] ?? '';
                            $paymentNotificationEndpointUrl = $data['records'][0]['PaymentRequest']['paymentNotification']['paymentNotificationEndpointUrl'] ?? '';
                            $paymentNotificationAuthorizationHeaderValue = $data['records'][0]['PaymentRequest']['paymentNotification']['paymentNotificationAuthorizationHeaderValue'] ?? '';
                            $variant = $data['records'][0]['PaymentRequest']['variant'] ?? '';
                            $multiPayment = $data['records'][0]['PaymentRequest']['multiPayment'] ?? 0;
                            $clientTransactionId = $data['records'][0]['PaymentRequest']['clientTransactionId'] ?? '';
                            $payID = $data['records'][0]['PaymentRequest']['payID'] ?? '';
                            $paymentExpiryDatetime = $data['records'][0]['PaymentRequest']['paymentExpiryDatetime'] ?? '';
                            $failedPaymentAttempts = $data['records'][0]['PaymentRequestStatus']['failedPaymentAttempts'] ?? [];
                            $payIDSuffix = $data['records'][0]['PaymentRequest']['payIDSuffix'] ?? '';
                            $paymentDescription = $data['records'][0]['PaymentRequest']['paymentDescription'] ?? '';
                            $checkoutUrl = $data['records'][0]['PaymentRequest']['checkoutUrl'] ?? '';
                            $paymentAmount = $data['records'][0]['PaymentRequest']['paymentAmount'] ?? '';
                            if(isset($data['records'][0]['PaymentRequestStatus']['createdDateTime']) && $data['records'][0]['PaymentRequestStatus']['createdDateTime'] != '')
                            {
                                $createdDateTime = new DateTime($data['records'][0]['PaymentRequestStatus']['createdDateTime']);
                                $createdDateTime->modify($AZUPAY_TIME_ZONE_DIFFERENCE_1_PLUS);
                                $createdDateTimeFormatted = $createdDateTime->format('Y-m-d H:i:s');
                            }
                            else
                            {
                                $createdDateTime = '';
                                $createdDateTimeFormatted = '';
                            }
                        } 
                        catch (\GuzzleHttp\Exception\ClientException $e) {
                            //echo 'Request failed: ' . $e->getMessage();
                        } catch (\GuzzleHttp\Exception\ServerException $e) {
                            // Server errors (5xx)
                            //echo 'Server error: ' . $e->getMessage();
                        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                            // Other errors (networking error, etc.)
                            //echo 'Error sending request: ' . $e->getMessage();
                        } catch (Exception $e) {
                            // Non-Guzzle related errors
                            //echo 'General error: ' . $e->getMessage();
                        }
                    }
                    

                    $is_failed_found = 0;
                    if (!empty($failedPaymentAttempts)) 
                    {
                        $is_failed_found = 1;
                    }
                    
                    if($status_from_azupay == '')
                    {
                        if($row['status'] == 'paid')
                        {
                            //$status_from_azupay = 'COMPLETE';
                        }
                        else
                        {
                            //$status_from_azupay = 'WAITING';
                        }
                        
                    }

                    if($status_from_azupay == 'COMPLETE')
                    {
                        $row_color = ' style = "background-color: #30ff83; color: black;"'; // green
                    }
                    else if($status_from_azupay == 'EXPIRED' && $is_failed_found == 0)
                    {
                        $row_color = ' style = "background-color: #ff5e6c; color: black;"'; // red
                    }
                    else if($status_from_azupay == 'EXPIRED' && $is_failed_found == 1)
                    {
                        $row_color = ' style = "background-color: #f5a911; color: black;"'; // red
                    }
                    else if($status_from_azupay == 'WAITING' && $is_failed_found == 0)
                    {
                        $row_color = ' style = "background-color: #FFF; color: black;"'; // white
                    }
                    else if($status_from_azupay == 'WAITING' && $is_failed_found == 1)
                    {
                        $row_color = ' style = "background-color: #f5a911; color: black;"'; // orange
                    }
                    else
                    {
                        $row_color = '';
                    }
                    
                    // - ".$row['azupay_payid']."
                    $paid_on = '';
                    if( $row['paid_on'] != '' )
                    {
                        $paid_on = date('d/m/Y H:i:s', strtotime($row['paid_on']));
                    }
                    
                    if(isset($_GET['status']) && $_GET['status'] != '')
                    {
                        if(strtolower($status_from_azupay) != $_GET['status'])
                        {
                            continue;
                        }
                    }
                    
                    if($status_from_azupay == '')
                    {
                        $status_from_azupay = '<font style="color:red;">Rejected / Cancelled</font>';
                    }
                    
                    echo "<tr ".$row_color.">
                            <td><a target='_blank' style = 'color:black;' href='/manage-wp-orders/?option=search&type=reference&id=".$row['order_id']."'>".$row['order_id']."</a></td>
                            <td>".$row['type_of_payment']."</td>
                            <td>".date('d/m/Y H:i:s', strtotime($row['order_date']))."</td>
                            <td>".date('d/m/Y H:i:s', strtotime($row['travel_date']))."</td>
                            <td>".date('d/m/Y H:i:s', strtotime($row['requested_on']))."</td>
                            <td>".$paid_on."</td>
                            <td>".$row['amount']."</td>
                            <td>".$status_from_azupay;
                            //echo '<pre>';
                            //print_r($data);
                            //echo '</pre>';
                            if (!empty($failedPaymentAttempts))
                            {
                                echo "<span class='toggle-arrow' data-target='failed_payment_".$row['order_id']."' style='cursor: pointer; float:right; font-size:16px;'>&#9660;</span>";
                            }
                            echo "</td>";
                    echo "</tr>";
                    if (!empty($failedPaymentAttempts)) {
                        echo "<tr class='failedpaymentblock failed_payment_".$row['order_id']."' style='display: none;'><th>Date</th><th>Amount</th><th colspan='6'>Reason</th></tr>";
                        foreach ($failedPaymentAttempts as $attempt) {
                            echo "<tr class='failedpaymentblock failed_payment_".$row['order_id']."' style='display: none;'>
                                    <td>" . date('d/m/Y H:i:s', strtotime($attempt['attemptDateTime'])) . "</td>
                                    <td>" . number_format($attempt['attemptAmount'], 2) . "</td>
                                    <td colspan='6'>" . htmlspecialchars($attempt['attemptFailureReason']) . "</td>
                                  </tr>
                                  ";
                        }
                        echo "<tr class='failedpaymentblock failed_payment_".$row['order_id']."' style='display: none;'><td colspan='8'>&nbsp;</br>&nbsp;</br></td></tr>";
                    }
                }
            }
            echo '</table>';
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.toggle-arrow').forEach(function(arrow) {
                    arrow.addEventListener('click', function() {
                        const targetClass = this.getAttribute('data-target');
                        document.querySelectorAll(`.${targetClass}`).forEach(function(row) {
                            row.style.display = row.style.display === 'none' ? '' : 'none';
                        });
                        // Toggle arrow direction
                        this.innerHTML = this.innerHTML === '&#9660;' ? '&#9650;' : '&#9660;';
                    });
                });
            });
            </script>
            <?php
	    }
	    
    }
}
else
{
echo "<center>This page is not accessible for you.</center>";
}
?>
</div>
<?php get_footer(); ?>