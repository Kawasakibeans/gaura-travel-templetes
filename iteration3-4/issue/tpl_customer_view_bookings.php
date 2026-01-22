<?php
/**
 * Template Name: Manage My Bookings
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<html> 
<head>
</head>
<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);

// Load WordPress configuration to get API_BASE_URL
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );

// Define API base URL if not already defined (fallback)
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1');
}

$base_url = API_BASE_URL; // Use global constant

// OLD DATABASE CONNECTION - COMMENTED OUT (now using API endpoints)
/*
include("wp-config-custom.php");
*/

        ?>
        <div class="search_bookings">
				<script type="text/javascript">
				function searchordejs() {
				  
				  var pnr_or_id = document.getElementById("searchkeyvalue").value;
					var email = document.getElementById("searchemailid").value;
					
					
					window.location='?option=search&email=' + email + '&id=' + pnr_or_id ;
					
				}
				</script>
				<h5>View Booking</h5>
        
				<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
				<tr>
				<td width='10%'>
                	Search by
                </td>
				<td width='30%'>
                	<input type='text' name='searchkeyvalue' value='<?php if(isset($_GET['id'])) { echo $_GET['id']; } ?>' placeholder="Booking Reference" id='searchkeyvalue'>
                </td>
                <td width='20%'>
				    <input type='text' name='searchemailid' value='<?php if(isset($_GET['email'])) { echo $_GET['email']; } ?>' placeholder="Email Address" id='searchemailid'>
				</td>
				<td>
				<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
				</td>
			
				</tr>
				</table>
        
				<?php
				if (ctype_digit($_GET['id'])) 
				{
					$ref_type = 'orderid';
    			}
    			else
    			{
    				$ref_type = 'pnr';
    			}
    			
				$filter_email = $_GET['email'];
				$filetr_id = $_GET['id'];
				
				if(isset($_GET['email']) && isset($_GET['id']) && $_GET['email'] != '' && $_GET['id'] != '') // is any values searched
				{
    				if($ref_type == 'pnr')
    				{
        				$filter_sql = "wpk4_backend_travel_booking_pax.pnr like '%".$_GET['id']."%' AND wpk4_backend_travel_booking_pax.email_pax like '%".$_GET['email']."%'";
    				}
    				else if($ref_type == 'orderid')
    				{
    				    $filter_sql = "wpk4_backend_travel_booking_pax.order_id='".$_GET['id']."' AND wpk4_backend_travel_booking_pax.email_pax like '%".$_GET['email']."%'";
    				}
    				else
        			{
        				$filter_sql = "wpk4_backend_travel_bookings.auto_id='GTDummyTD'";
        			}
				
    				if($_GET['email'] != '' && $_GET['id'] != '')
    				{
    					// Fetch bookings from API endpoint
    					// API Endpoint: GET /v1/customer-view-bookings/search
    					// Source: CustomerViewBookingsDAL::searchBookings
    					// Query parameters: id (required, PNR or order_id), email (required), limit (default: 20)
    					// Response payload: { "bookings": [...] } or { "status": "success", "data": { "bookings": [...] } }
    					$search_results = [];
    					$api_error_message = '';
    					
    					try {
    						$apiUrl = $base_url . '/customer-view-bookings/search';
    						$queryParams = [
    							'id' => $_GET['id'],
    							'email' => $_GET['email'],
    							'limit' => 20
    						];
    						$fullUrl = $apiUrl . '?' . http_build_query($queryParams);
    						
    						$ch = curl_init();
    						curl_setopt($ch, CURLOPT_URL, $fullUrl);
    						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    						curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    						curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    						curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    						
    						$response = curl_exec($ch);
    						$curl_error = curl_error($ch);
    						$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    						curl_close($ch);
    						
    						if ($curl_error) {
    							$api_error_message = "Connection error: " . $curl_error;
    							error_log("Customer View Bookings Search API cURL Error: " . $curl_error);
    						} elseif ($httpCode === 200 && $response) {
    							$jsonResponse = json_decode($response, true);
    							
    							if (json_last_error() !== JSON_ERROR_NONE) {
    								$api_error_message = "Invalid response format from API";
    								error_log("Customer View Bookings Search API JSON Error: " . json_last_error_msg());
    							} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'error') {
    								$api_error_message = isset($jsonResponse['message']) ? $jsonResponse['message'] : "API returned an error";
    								error_log("Customer View Bookings Search API Error Response: " . $api_error_message);
    							} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['bookings'])) {
    								$search_results = is_array($jsonResponse['data']['bookings']) ? $jsonResponse['data']['bookings'] : [];
    							} elseif (isset($jsonResponse['bookings']) && is_array($jsonResponse['bookings'])) {
    								$search_results = $jsonResponse['bookings'];
    							}
    						} else {
    							$error_detail = '';
    							if ($response) {
    								$errorResponse = json_decode($response, true);
    								if (isset($errorResponse['message'])) {
    									$error_detail = $errorResponse['message'];
    								} else {
    									$error_detail = substr($response, 0, 200);
    								}
    							}
    							$api_error_message = "API request failed (HTTP $httpCode)" . ($error_detail ? ": " . $error_detail : "");
    							error_log("Customer View Bookings Search API Error: HTTP $httpCode - " . substr($response, 0, 200));
    						}
    					} catch (Exception $e) {
    						$api_error_message = "Exception: " . $e->getMessage();
    						error_log("Customer View Bookings Search API Exception: " . $e->getMessage());
    					}
    					
    					// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    					/*
    					// Query: Search Bookings
    					// SELECT DISTINCT b.*, p.pnr, p.email_pax, p.phone_pax
    					// FROM wpk4_backend_travel_bookings b
    					// JOIN wpk4_backend_travel_booking_pax p ON b.order_id = p.order_id 
    					//   AND b.co_order_id = p.co_order_id 
    					//   AND b.product_id = p.product_id
    					// WHERE p.pnr LIKE :search_id AND p.email_pax LIKE :email
    					// ORDER BY p.order_id DESC
    					// LIMIT :limit
    					// Source: CustomerViewBookingsDAL::searchBookings
    					// Method: GET
    					// Endpoint: /v1/customer-view-bookings/search
    					// Query parameters: id (required), email (required), limit (default: 20)
    					
    					$query = "SELECT * FROM wpk4_backend_travel_bookings JOIN wpk4_backend_travel_booking_pax ON  wpk4_backend_travel_bookings.order_id = wpk4_backend_travel_booking_pax.order_id && 
    					wpk4_backend_travel_bookings.co_order_id = wpk4_backend_travel_booking_pax.co_order_id && wpk4_backend_travel_bookings.product_id = wpk4_backend_travel_booking_pax.product_id 
    					where $filter_sql order by wpk4_backend_travel_booking_pax.order_id desc LIMIT 20";
    					$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
    					*/
    				}
    				else
    				{
    					$search_results = [];
    				}
				
    				$row_count = count($search_results);
    				
    				// Display API error if any
    				if (!empty($api_error_message)) {
    					echo '<div style="margin: 20px auto; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 14px;">';
    					echo '<strong>Error:</strong> ' . htmlspecialchars($api_error_message);
    					echo '</div>';
    				}
    				
                    if($row_count > 0)
            		{
            		    $processedOrders_search = [];
            		    foreach($search_results as $row)
            		    {
                		    $order_id = $row['order_id'];
                		    $order_id_api = $row['order_id'];
                		    $co_order_id_api = $row['co_order_id'];
                		    $product_id_api = $row['product_id'];
                		    
                            if (in_array($order_id, $processedOrders_search)) {
								continue; // Skip to the next iteration if the order ID is already processed
							}
							$processedOrders_search[] = $order_id;
							
                    		$pax_remarks=$row['remarks'];
                    		$booking_date_dmy = date('d/m/Y H:i:s', strtotime($row['order_date'])); 
                            $travel_date_domestic = date('Y-m-d', strtotime($row['travel_date'])); 
                    		$trip_code_domestic = $row['trip_code'];
                    		
                    		// Get contact info from API endpoint (included in booking details)
                    		// API Endpoint: GET /v1/customer-view-bookings/{order_id}
                    		// Source: CustomerViewBookingsDAL::getBookingDetails (includes contact info)
                    		// The contact info is already included in the search results, but we can extract it here
                    		$phone_pax = $row['phone_pax'] ?? '';
                    		$email_pax = $row['email_pax'] ?? '';
                    		
                    		// OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
                    		/*
                    		// Query: Get Contact Info
                    		// SELECT phone_pax, email_pax 
                    		// FROM wpk4_backend_travel_booking_pax 
                    		// WHERE order_id = :order_id 
                    		//   AND (phone_pax != '' OR email_pax != '')
                    		// LIMIT 1
                    		// Source: CustomerViewBookingsDAL::getBookingDetails
                    		// Method: GET
                    		// Endpoint: /v1/customer-view-bookings/{order_id} (included in response)
                    		
                    		$query_pax_contact_selection_phone = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id' AND phone_pax != ''";
        					$result_pax_contact_selection_phone = mysqli_query($mysqli, $query_pax_contact_selection_phone);
        					$row_pax_contact_selection_phone = mysqli_fetch_assoc($result_pax_contact_selection_phone);
        					
                    		$phone_pax = $row_pax_contact_selection_phone['phone_pax'];
                    		if($phone_pax == '')
                    		{
                                $query_pax_contact_from_meta = "SELECT * FROM wpk4_backend_history_of_updates where type_id='$order_id' AND meta_key = 'Billing PrivatePhone'";
            					$result_pax_contact_from_meta = mysqli_query($mysqli, $query_pax_contact_from_meta);
            					$row_pax_contact_from_meta = mysqli_fetch_assoc($result_pax_contact_from_meta);
            					
            					$phone_pax = $row_pax_contact_from_meta['meta_value'];
                    		}
                    		
                    		$query_pax_contact_selection_email = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id' AND email_pax != ''";
        					$result_pax_contact_selection_email = mysqli_query($mysqli, $query_pax_contact_selection_email);
        					$row_pax_contact_selection_email = mysqli_fetch_assoc($result_pax_contact_selection_email);
        					
                    		$email_pax = $row_pax_contact_selection_email['email_pax'];
                    		if($email_pax == '')
                    		{
                                $query_pax_contact_from_meta = "SELECT * FROM wpk4_backend_history_of_updates where type_id='$order_id' AND meta_key = 'Billing Email'";
            					$result_pax_contact_from_meta = mysqli_query($mysqli, $query_pax_contact_from_meta);
            					$row_pax_contact_from_meta = mysqli_fetch_assoc($result_pax_contact_from_meta);
            					
            					$email_pax = $row_pax_contact_from_meta['meta_value'];
                    		}
                    		*/
                    		
                    		$total_pax = $row['total_pax'];
                    		$order_type = $row['order_type'];
                    		$order_type_itinerary = $row['order_type'];
                    		
                    		// Payment status is already included in the search results from API
            				$payment_status = $row['payment_status'] ?? '';
                    		
                    		// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
                    		/*
                    		// Query: Get Booking (for payment status)
                    		// SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1
                    		// Source: CustomerViewBookingsDAL::getBookingDetails
                    		// Method: GET
                    		// Endpoint: /v1/customer-view-bookings/{order_id} (included in search response)
                    		
                    		$query_payment_status = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id'";
        					$result_payment_status = mysqli_query($mysqli, $query_payment_status);
        					$row_payment_status = mysqli_fetch_assoc($result_payment_status);
        					
            				$payment_status = $row_payment_status['payment_status'];
            				*/
    				        if($payment_status == 'pending')
    						{
    							$txt_payment_status = 'Pending';
    						}
    						else if($payment_status == 'partially_paid')
    						{
    							$txt_payment_status = 'Partially Paid';
    						}
    						else if($payment_status == 'paid' || $payment_status == 'Paid')
    						{
    							$txt_payment_status = 'Paid';
    						}
    						else if($payment_status == 'canceled')
    						{
    							$txt_payment_status = 'Xxln With Deposit';
    						}
    						else if($payment_status == 'N/A')
    						{
    							$txt_payment_status = 'Failed';
    						}
    						else if($payment_status == 'refund')
    						{
    							$txt_payment_status = 'Refund Done';
    						}
    						else if($payment_status == 'waiting_voucher')
    						{
    							$txt_payment_status = 'Refund Under Process';
    						}
    						else if($payment_status == 'receipt_received')
    						{
    							$txt_payment_status = 'Receipt Received';
    						}
    						else if($payment_status == 'voucher_submited')
    						{
    							$txt_payment_status = 'Rebooked';
    						}
    						else
    						{
    							$txt_payment_status = 'Pending';
    						}
            				$order_date = $row['order_date'];
            				
            			
            				?>
            				</br>
            				<div style="margin: auto; font-size:16px;">
                				<div style="float: left; width:49%;">
                    			    Booking Ref: <?php echo $row['order_id']; ?> <?php if($co_order_id != '') { echo ' '.$co_order_id; } ?></br>
                    				Agent: <?php echo $row['agent_info']; ?></br>
                    				Booked on: <?php echo $booking_date_dmy; ?></br>
                    				Travel Type: <?php echo $row['t_type']; ?></br>
                				</div>
                				<div style="float: right; width:49%;">
                    				Email: <?php echo $email_pax; ?></br>
                    				Phone Number: <?php echo $phone_pax; ?></br>
                    				Pax: <?php echo $total_pax; ?></br>
                    				Payment Status: <?php echo $txt_payment_status; ?></br>
                    				
                    				
                    				</br>
                				</div>
            				</div>
            				</br></br></br></br></br>
            				<style>
            				.blink_me {
                                animation: blinker 2s linear infinite;
                                margin:0;
                                padding:0;
                            }
                                
                            @keyframes blinker {
                                50% {
                                    opacity: 0;
                                }
                            }
            				.search_bookings button, input[type=submit]
            				{
            				    background-color:#ffbb00;
            				    color:black;
            				}
            				.tabnavigation
            				{
            				    float:left;
            				    left:0;
            				}
                			.chatcategory
                			{
                    			padding:7px 10px; 
                    			margin:0;
                    			font-size:13px;
                			}
                			.tab .active
                			{
                			    color:#FFF!important;
                			    background-color:#000!important
                			}
                			.ssr_request_radio_button {
                			    margin-top: 10px; display: block;
                                flex-wrap: wrap;
                            }
                            .ssr_request_radio_button label.radio {
                             display:inline;
                             position:relative;
                             margin-left: 0.2em;
                             _top:0.2em;
                             }
                             
                             .radio-group {
                                    display: flex;
                                    align-items: center;
                                }
                                
                                .radio-group label {
                                    margin-right: 10px;
                                    margin-top:13px;
                                }
                                .badge.badge-primary {
                                    background-color: #149efa;
                                }
                                
                                .badge {
                                    display: inline-block;
                                    min-width: 10px;
                                    padding: 3px 7px;
                                    font-size: 12px;
                                    font-weight: 700;
                                    line-height: 1;
                                    color: #fff;
                                    text-align: center;
                                    white-space: nowrap;
                                    vertical-align: middle;
                                    background-color: #777;
                                    border-radius: 10px;
                                }


                			</style>
                			<div class="tabnavigation">
                				<div class="tab">

                    				<button class="tablinks chatcategory" onclick="openCity(event, 'summary_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Summary</button>
                    				
                    				<button class="tablinks chatcategory" onclick="openCity(event, 'itinerary_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Itinerary</button>
                    				<button class="tablinks chatcategory" onclick="openCity(event, 'names_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Pax Details</button>
                    				<button class="tablinks chatcategory " onclick="openCity(event, 'payments_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Payments</button>
                    				

                    				<button class="tablinks chatcategory" onclick="openCity(event, 'portal_request_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Customer Portal Requests</button>
                    				
                    				


                    			</div>
                			</div>
            				</br></br>
								<div id="tabcontent_main" style="font-size:14px;">
									
									<div id="summary_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent">
										
										</br></br>
										<table class="table table-striped" style="width:100%; margin:auto;font-size:14px; margin-top:35px;">
											<thead>
												<tr>	
													<th>Order ID</th>
													<th style="text-align:center;">PNR</th>
													<th style="text-align:center;">Source</th>
													<th style="text-align:center;">GDS ID</th>
													<th style="text-align:center;">Flight Details</th>
													<th style="text-align:center;">Travel Date</th>
												</tr>
											</thead>
											<tbody>
											<?php
											$order_id = $order_id_api;
											
											// Fetch booking details (WPT bookings) from API endpoint
											// API Endpoint: GET /v1/customer-view-bookings/{order_id}
											// Source: CustomerViewBookingsDAL::getBookingDetails
											// Query parameters: order_id (required)
											// Response payload: { "booking": {...}, "wpt_bookings": [...], "gds_bookings": [...], "contact_info": {...} }
											$wpt_bookings = [];
											$wpt_api_error = '';
											
											try {
												$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api);
												
												$ch = curl_init();
												curl_setopt($ch, CURLOPT_URL, $apiUrl);
												curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
												curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
												curl_setopt($ch, CURLOPT_TIMEOUT, 30);
												curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
												curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
												
												$response = curl_exec($ch);
												$curl_error = curl_error($ch);
												$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
												curl_close($ch);
												
												if ($curl_error) {
													$wpt_api_error = "Connection error: " . $curl_error;
													error_log("Customer View Bookings Details API cURL Error: " . $curl_error);
												} elseif ($httpCode === 200 && $response) {
													$jsonResponse = json_decode($response, true);
													if (json_last_error() !== JSON_ERROR_NONE) {
														$wpt_api_error = "Invalid response format from API";
														error_log("Customer View Bookings Details API JSON Error: " . json_last_error_msg());
													} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'error') {
														$wpt_api_error = isset($jsonResponse['message']) ? $jsonResponse['message'] : "API returned an error";
														error_log("Customer View Bookings Details API Error Response: " . $wpt_api_error);
													} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
														$bookingData = $jsonResponse['data'];
														$wpt_bookings = isset($bookingData['wpt_bookings']) && is_array($bookingData['wpt_bookings']) ? $bookingData['wpt_bookings'] : [];
													} elseif (isset($jsonResponse['wpt_bookings']) && is_array($jsonResponse['wpt_bookings'])) {
														$wpt_bookings = $jsonResponse['wpt_bookings'];
													}
												} else {
													$error_detail = '';
													if ($response) {
														$errorResponse = json_decode($response, true);
														if (isset($errorResponse['message'])) {
															$error_detail = $errorResponse['message'];
														} else {
															$error_detail = substr($response, 0, 200);
														}
													}
													$wpt_api_error = "API request failed (HTTP $httpCode)" . ($error_detail ? ": " . $error_detail : "");
													error_log("Customer View Bookings Details API Error: HTTP $httpCode - " . substr($response, 0, 200));
												}
											} catch (Exception $e) {
												$wpt_api_error = "Exception: " . $e->getMessage();
												error_log("Customer View Bookings Details API Exception: " . $e->getMessage());
											}
											
											// Display error if API call failed
											if (!empty($wpt_api_error)) {
												echo '<div style="margin: 10px 0; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 13px;">';
												echo '<strong>Error loading booking details:</strong> ' . htmlspecialchars($wpt_api_error);
												echo '</div>';
											}
											
											// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
											/*
											// Query: Get WPT Bookings
											// SELECT * FROM wpk4_backend_travel_bookings 
											// WHERE order_id = :order_id 
											//   AND (order_type = 'WPT' OR order_type = '')
											// ORDER BY travel_date ASC
											// Source: CustomerViewBookingsDAL::getBookingDetails
											// Method: GET
											// Endpoint: /v1/customer-view-bookings/{order_id}
											// Query parameters: order_id (required)
											
											$query_summary_loop = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api' && (order_type = 'WPT' || order_type = '') order by travel_date asc";
											$result_summary_loop = mysqli_query($mysqli, $query_summary_loop) or die(mysqli_error($mysqli));
											*/
											
											foreach($wpt_bookings as $row_summary_loop)
											{
												$order_id_summary = $row_summary_loop['order_id'];
												$product_id_summary = $row_summary_loop['product_id'];
												$co_order_id_summary = $row_summary_loop['co_order_id'];
												$pax_remarks=$row_summary_loop['remarks'];
							
												$travel_date_domestic = date('Y-m-d', strtotime($row_summary_loop['travel_date'])); 
												$trip_code_domestic = $row_summary_loop['trip_code'];
												$tripcode_plus_trav_date_for_domestic_filter = $trip_code_domestic.$travel_date_domestic; //Eg: MEL-AMD-TR019-TR5742023-03-22
												
												// Get pax details from API (already fetched in booking details, but we need PNR here)
												// The booking details API response should include pax info, but we'll fetch it separately if needed
												$pnr_received = '';
												$row_count_pax = [];
												
												try {
													$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_summary) . '/pax';
													$queryParams = [];
													if (!empty($co_order_id_summary)) {
														$queryParams['co_order_id'] = urlencode($co_order_id_summary);
													}
													if (!empty($product_id_summary)) {
														$queryParams['product_id'] = urlencode($product_id_summary);
													}
													if (!empty($queryParams)) {
														$apiUrl .= '?' . http_build_query($queryParams);
													}
													
													$ch = curl_init();
													curl_setopt($ch, CURLOPT_URL, $apiUrl);
													curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
													curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
													curl_setopt($ch, CURLOPT_TIMEOUT, 30);
													curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
													curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
													
													$response = curl_exec($ch);
													$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
													curl_close($ch);
													
													if ($httpCode === 200 && $response) {
														$jsonResponse = json_decode($response, true);
														if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['pax']) && is_array($jsonResponse['data']['pax']) && !empty($jsonResponse['data']['pax'])) {
															$row_count_pax = $jsonResponse['data']['pax'][0]; // Get first pax record
															$pnr_received = $row_count_pax['pnr'] ?? '';
														} elseif (isset($jsonResponse['pax']) && is_array($jsonResponse['pax']) && !empty($jsonResponse['pax'])) {
															$row_count_pax = $jsonResponse['pax'][0];
															$pnr_received = $row_count_pax['pnr'] ?? '';
														}
													}
												} catch (Exception $e) {
													error_log("Customer View Bookings Pax Count API Exception: " . $e->getMessage());
												}
												
												// OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
												/*
												$query_count = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_summary' && co_order_id='$co_order_id_summary' && product_id = '$product_id_summary'";
												$result_count = mysqli_query($mysqli, $query_count);
												$row_count = mysqli_num_rows($result_count);
											   
												$query_movement = "SELECT * FROM wpk4_backend_travel_booking_movements where order_id='$order_id_summary' && product_id='$product_id_summary' && co_order_id='$co_order_id_summary' ";
												$result_movement = mysqli_query($mysqli, $query_movement);
												$row_movement_count = mysqli_num_rows($result_movement);
											   
												$query_count_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_summary' && co_order_id='$co_order_id_summary' && product_id='$product_id_summary'";
												$result_count_pax = mysqli_query($mysqli, $query_count_pax);
												$row_count_pax = mysqli_fetch_assoc($result_count_pax);
												$pnr_received = $row_count_pax['pnr'];
												*/
												$total_paxs++;
												$x_id = 1;
												?>
												<tr>
													<td width='8%'><b>
														<?php echo $row_summary_loop['order_id']; ?> <?php if($co_order_id_summary != '') { echo ' '.$co_order_id_summary; } ?></b>
														</br>
													</td>
													<?php 
													 $order_type = $row_summary_loop['order_type'];
													 $source_f = $row_summary_loop['source'];
													 $sourcebkng = $row_summary_loop['source'];
													 $gds_id = '';
						
													$source= '';
													
													if ($order_type == 'WPT' && substr($trip_code_domestic, 8, 2) === 'SQ') {
														$source = 'Gdeals';
														$gds_id = 'CCUVS32NQ';
													}
													if ($order_type == 'WPT' && substr($trip_code_domestic, 8, 2) === 'QF') {
														$source = 'Gdeals';
														$gds_id = 'MELA821CV ';
													}
													if($order_type == 'WPT' && $sourcebkng == 'wpwebsite' ){
														$source = 'Gdeals';
														$gds_id = 'CCUVS32NQ';
													}
												
													 if($order_type == 'gds' && $sourcebkng == 'gaurain' ){
														$source = 'Sabre';
														$gds_id = '1BIK';
													}
													 if($order_type == 'gds' && $sourcebkng == 'gauraaws' ){
														$source = 'Amadeus';
														$gds_id = 'MELA821CV ';
													}
													if($order_type == 'gds' && $sourcebkng == 'gaura' ){
														$source = 'Sabre';
														$gds_id = 'I5FC';
													}
													$pnr_received = $row_count_pax['pnr'];
													if ($order_type == 'gds' && substr($pnr_received, 0, 3) === 'SQ_') {
														$source = 'SQ NDC';
														$gds_id = 'I5FC';
													} 
													?>
													<td width='8%' style="text-align:center;"><?php echo $row_count_pax['pnr']; ?></td>
													<td width='8%' style="text-align:center;">	<?php echo $source ?></td>
													<td width='9%' style="text-align:center;"><?php echo $gds_id ?></td>
												
													<td width='10%' style="text-align:center;">
														<?php echo $row_summary_loop['trip_code']; ?>
													</td>
													<td width='5%' style="text-align:center;"> <?php echo date('d/m/Y', strtotime($row_summary_loop['travel_date']));?> </td>
													</tr>
													<?php
													$x_id++;
													$auto_numbering++;
											}
											// Fetch GDS bookings from API endpoint
											// API Endpoint: GET /v1/customer-view-bookings/{order_id}
											// Source: CustomerViewBookingsDAL::getBookingDetails
											// Query parameters: order_id (required)
											// Response payload: { "gds_bookings": [...] } (included in same response as WPT bookings)
											$gds_bookings = [];
											$gds_api_error = '';
											
											try {
												$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api);
												
												$ch = curl_init();
												curl_setopt($ch, CURLOPT_URL, $apiUrl);
												curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
												curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
												curl_setopt($ch, CURLOPT_TIMEOUT, 30);
												curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
												curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
												
												$response = curl_exec($ch);
												$curl_error = curl_error($ch);
												$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
												curl_close($ch);
												
												if ($curl_error) {
													$gds_api_error = "Connection error: " . $curl_error;
													error_log("Customer View Bookings GDS API cURL Error: " . $curl_error);
												} elseif ($httpCode === 200 && $response) {
													$jsonResponse = json_decode($response, true);
													if (json_last_error() !== JSON_ERROR_NONE) {
														$gds_api_error = "Invalid response format from API";
														error_log("Customer View Bookings GDS API JSON Error: " . json_last_error_msg());
													} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'error') {
														$gds_api_error = isset($jsonResponse['message']) ? $jsonResponse['message'] : "API returned an error";
														error_log("Customer View Bookings GDS API Error Response: " . $gds_api_error);
													} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data'])) {
														$bookingData = $jsonResponse['data'];
														$gds_bookings = isset($bookingData['gds_bookings']) && is_array($bookingData['gds_bookings']) ? $bookingData['gds_bookings'] : [];
													} elseif (isset($jsonResponse['gds_bookings']) && is_array($jsonResponse['gds_bookings'])) {
														$gds_bookings = $jsonResponse['gds_bookings'];
													}
												} else {
													$error_detail = '';
													if ($response) {
														$errorResponse = json_decode($response, true);
														if (isset($errorResponse['message'])) {
															$error_detail = $errorResponse['message'];
														} else {
															$error_detail = substr($response, 0, 200);
														}
													}
													$gds_api_error = "API request failed (HTTP $httpCode)" . ($error_detail ? ": " . $error_detail : "");
													error_log("Customer View Bookings GDS API Error: HTTP $httpCode - " . substr($response, 0, 200));
												}
											} catch (Exception $e) {
												$gds_api_error = "Exception: " . $e->getMessage();
												error_log("Customer View Bookings GDS API Exception: " . $e->getMessage());
											}
											
											// Display error if API call failed
											if (!empty($gds_api_error)) {
												echo '<div style="margin: 10px 0; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 13px;">';
												echo '<strong>Error loading GDS bookings:</strong> ' . htmlspecialchars($gds_api_error);
												echo '</div>';
											}
											
											// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
											/*
											// Query: Get GDS Bookings
											// SELECT * FROM wpk4_backend_travel_bookings 
											// WHERE order_id = :order_id 
											//   AND order_type = 'gds'
											// ORDER BY travel_date ASC
											// Source: CustomerViewBookingsDAL::getBookingDetails
											// Method: GET
											// Endpoint: /v1/customer-view-bookings/{order_id}
											// Query parameters: order_id (required)
											
											$query_summary_loop = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api' && order_type = 'gds'";
											$result_summary_loop = mysqli_query($mysqli, $query_summary_loop) or die(mysqli_error($mysqli));
											*/
											
											foreach($gds_bookings as $row_summary_loop)
											{
												$order_id_summary = $row_summary_loop['order_id'];
												$product_id_summary = $row_summary_loop['product_id'];
												$co_order_id_summary = $row_summary_loop['co_order_id'];
												$pax_remarks=$row_summary_loop['remarks'];
												$return_date=$row_summary_loop['return_date'];
												$travel_date=$row_summary_loop['travel_date'];
												
												$travel_date_domestic = date('Y-m-d', strtotime($row_summary_loop['travel_date'])); 
													$trip_code_domestic = $row_summary_loop['trip_code'];
													$tripcode_plus_trav_date_for_domestic_filter = $trip_code_domestic.$travel_date_domestic; //Eg: MEL-AMD-TR019-TR5742023-03-22
													
													// Get pax details from API (for GDS bookings)
													$pnr_received = '';
													$row_count_pax = [];
													
													try {
														$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_summary) . '/pax';
														$queryParams = [];
														if (!empty($co_order_id_summary)) {
															$queryParams['co_order_id'] = urlencode($co_order_id_summary);
														}
														if (!empty($product_id_summary)) {
															$queryParams['product_id'] = urlencode($product_id_summary);
														}
														if (!empty($queryParams)) {
															$apiUrl .= '?' . http_build_query($queryParams);
														}
														
														$ch = curl_init();
														curl_setopt($ch, CURLOPT_URL, $apiUrl);
														curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
														curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
														curl_setopt($ch, CURLOPT_TIMEOUT, 30);
														curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
														curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
														
														$response = curl_exec($ch);
														$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
														curl_close($ch);
														
														if ($httpCode === 200 && $response) {
															$jsonResponse = json_decode($response, true);
															if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['pax']) && is_array($jsonResponse['data']['pax']) && !empty($jsonResponse['data']['pax'])) {
																$row_count_pax = $jsonResponse['data']['pax'][0];
																$pnr_received = $row_count_pax['pnr'] ?? '';
															} elseif (isset($jsonResponse['pax']) && is_array($jsonResponse['pax']) && !empty($jsonResponse['pax'])) {
																$row_count_pax = $jsonResponse['pax'][0];
																$pnr_received = $row_count_pax['pnr'] ?? '';
															}
														}
													} catch (Exception $e) {
														error_log("Customer View Bookings GDS Pax API Exception: " . $e->getMessage());
													}
													
													// OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
													/*
													$query_count = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_summary' && co_order_id='$co_order_id_summary'";
													$result_count = mysqli_query($mysqli, $query_count);
													$row_count = mysqli_num_rows($result_count);
												   
													$query_movement = "SELECT * FROM wpk4_backend_travel_booking_movements where order_id='$order_id_summary' && product_id='$product_id_summary' && co_order_id='$co_order_id_summary' ";
													$result_movement = mysqli_query($mysqli, $query_movement);
													$row_movement_count = mysqli_num_rows($result_movement);
												   
													$query_count_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_summary' && co_order_id='$co_order_id_summary' && product_id='$product_id_summary'";
													$result_count_pax = mysqli_query($mysqli, $query_count_pax);
													$row_count_pax = mysqli_fetch_assoc($result_count_pax);
													*/
													$total_paxs++;
													$x_id = 1;
														 $order_type = $row_summary_loop['order_type'];
														 //$source = $row_summary_loop['source'];
														 $sourcebkng = $row_summary_loop['source'];
														 $gds_id = '';
														$source= '';
														$pnr_received = $row_count_pax['pnr'];
														
														if ($order_type == 'WPT' && substr($trip_code_domestic, 8, 2) === 'SQ') {
															$source = 'Gdeals';
															$gds_id = 'CCUVS32NQ';
														}
														if ($order_type == 'WPT' && substr($trip_code_domestic, 8, 2) === 'QF') {
															$source = 'Gdeals';
															$gds_id = 'MELA821CV ';
														}
														if($order_type == 'WPT' && $sourcebkng == 'wpwebsite' ){
															$source = 'Gdeals';
															$gds_id = 'CCUVS32NQ';
														}
													
														 if($order_type == 'gds' && $sourcebkng == 'gaurain' ){
															$source = 'Sabre';
															$gds_id = '1BIK';
														}
														 if($order_type == 'gds' && $sourcebkng == 'gauraaws' ){
															$source = 'Amadeus';
															$gds_id = 'MELA821CV ';
														}
														 if($order_type == 'gds' && $sourcebkng == 'gaura' ){
															$source = 'Sabre';
															$gds_id = 'I5FC';
														}
														
														if ($order_type == 'gds' && substr($pnr_received, 0, 3) === 'SQ_') {
															$source = 'SQ NDC';
															$gds_id = 'I5FC';
														} 
												if($return_date == $travel_date)
												{
													?>
													<tr>
														<td width='8%'><b>
															<?php echo $row_summary_loop['order_id']; ?> <?php if($co_order_id_summary != '') { echo ' '.$co_order_id_summary; } ?></b>
															</br>
														</td>
														<td width='8%' style="text-align:center;"><?php echo $row_count_pax['pnr']; ?></td>
														<td width='8%' style="text-align:center;">	<?php echo $source ?></td>
														<td width='9%' style="text-align:center;"><?php echo $gds_id ?></td>
													
														<td width='10%' style="text-align:center;">
															<?php echo $row_summary_loop['trip_code']; ?>
														</td>
														<td width='5%' style="text-align:center;"> <?php echo date('d-m-Y', strtotime($row_summary_loop['travel_date']));?> </td>
													</tr>
													<?php
												}
												else
												{
													?>
													<tr>
														<td width='8%'><b>
															<?php echo $row_summary_loop['order_id']; ?> <?php if($co_order_id_summary != '') { echo ' '.$co_order_id_summary; } ?></b>
															</br>
														</td>
														<td width='8%' style="text-align:center;"><?php echo $row_count_pax['pnr']; ?></td>
														<td width='8%' style="text-align:center;">	<?php echo $source ?></td>
														<td width='9%' style="text-align:center;"><?php echo $gds_id ?></td>
													
														<td width='10%' style="text-align:center;">
															<?php echo $row_summary_loop['trip_code']; ?>
														</td>
														<td width='5%' style="text-align:center;"> <?php echo date('d-m-Y', strtotime($row_summary_loop['travel_date']));?> </td>
													</tr>
													<tr>
														<td width='8%'><b>
															<?php echo $row_summary_loop['order_id']; ?> <?php if($co_order_id_summary != '') { echo ' '.$co_order_id_summary; } ?></b>
															</br>
														</td>
														<td width='8%' style="text-align:center;"><?php echo $row_count_pax['pnr']; ?></td>
														<td width='8%' style="text-align:center;">	<?php echo $source ?></td>
														<td width='9%' style="text-align:center;"><?php echo $gds_id ?></td>
													
														<td width='10%' style="text-align:center;">
														<?php
														$parts_trip = explode("-", $row_summary_loop['trip_code']);

														if (count($parts_trip) >= 2) {
															$origin_place = $parts_trip[0];
															$destination_place = $parts_trip[1];
														
															echo $destination_place . ' - ' .$origin_place;
														}
														?>
														</td>
														<td width='5%' style="text-align:center;"> <?php echo date('d-m-Y', strtotime($row_summary_loop['return_date']));?> </td>
													</tr>
													<?php
												}
												
												$x_id++;
												$auto_numbering++;
											}
											?>
											</tbody>
										</table>
									</div>
									
									<div id="names_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent" style="display:none;">
										<button class="tablinks chatcategory" onclick="openCity(event, 'fullnames_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">View detailed info</button></br></br>
										
												<?php
												$order_id = $order_id_api;
												if($order_type_itinerary == 'WPT' || $order_type_itinerary == '')
												{
													$total_paxs = 0;
													$query_summary_loop = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api'";
													$result_loop = mysqli_query($mysqli, $query_summary_loop) or die(mysqli_error($mysqli));
													while($row_loop = mysqli_fetch_assoc($result_loop))
													{
														$order_id_pax_view = $row_loop['order_id'];
														$co_order_id_pax_view = $row_loop['co_order_id'];
														$product_id_pax_view = $row_loop['product_id'];
														echo $row_loop['product_title']. ' | ' .$row_loop['trip_code'];
														?>
														<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
														<thead>
															<tr>
																<th>PNR</th>
																<th>Ticket</th>
																<th>Name</th>
																<th>DOB</th>
																<th>PPN</th>
																<th>PPE</th>
																<th>Meal</th>
																<th>Wheelchair</th>
																<th>Baggage</th>
																<th>e-ticket</br>emailed</th>
																<th>Invoice</br>Raised</th>
																<th>Remarks</th>
															</tr>
														</thead>
														<tbody>
														<?php
														// Fetch pax details from API endpoint
														// API Endpoint: GET /v1/customer-view-bookings/{order_id}/pax
														// Source: CustomerViewBookingsDAL::getPaxDetails
														// Query parameters: order_id (required), co_order_id (optional), product_id (optional)
														// Response payload: { "pax": [...] } or { "status": "success", "data": { "pax": [...] } }
														$pax_details = [];
														
														try {
															$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_pax_view) . '/pax';
															$queryParams = [];
															if (!empty($co_order_id_pax_view)) {
																$queryParams['co_order_id'] = urlencode($co_order_id_pax_view);
															}
															if (!empty($product_id_pax_view)) {
																$queryParams['product_id'] = urlencode($product_id_pax_view);
															}
															if (!empty($queryParams)) {
																$apiUrl .= '?' . http_build_query($queryParams);
															}
															
															$ch = curl_init();
															curl_setopt($ch, CURLOPT_URL, $apiUrl);
															curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
															curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
															curl_setopt($ch, CURLOPT_TIMEOUT, 30);
															curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
															curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
															
															$response = curl_exec($ch);
															$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
															curl_close($ch);
															
															if ($httpCode === 200 && $response) {
																$jsonResponse = json_decode($response, true);
																if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['pax'])) {
																	$pax_details = is_array($jsonResponse['data']['pax']) ? $jsonResponse['data']['pax'] : [];
																} elseif (isset($jsonResponse['pax']) && is_array($jsonResponse['pax'])) {
																	$pax_details = $jsonResponse['pax'];
																} elseif (isset($jsonResponse['data']) && is_array($jsonResponse['data'])) {
																	$pax_details = $jsonResponse['data'];
																}
															} else {
																error_log("Customer View Bookings Pax Details API Error: HTTP $httpCode - " . substr($response, 0, 200));
															}
														} catch (Exception $e) {
															error_log("Customer View Bookings Pax Details API Exception: " . $e->getMessage());
														}
														
														// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
														/*
														// Query: Get Pax Details
														// SELECT * FROM wpk4_backend_travel_booking_pax 
														// WHERE order_id = :order_id
														//   AND co_order_id = :co_order_id
														//   AND product_id = :product_id
														// Source: CustomerViewBookingsDAL::getPaxDetails
														// Method: GET
														// Endpoint: /v1/customer-view-bookings/{order_id}/pax
														// Query parameters: order_id (required), co_order_id (optional), product_id (optional)
														
														$query_summary_loop_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && co_order_id = '$co_order_id_pax_view' && product_id = '$product_id_pax_view'";
														$result_loop_pax = mysqli_query($mysqli, $query_summary_loop_pax) or die(mysqli_error($mysqli));
														*/
														
														foreach($pax_details as $row_loop_pax)
														{
															$total_paxs++;
															?>
															<tr>
															<td width='8%'><?php echo $row_loop_pax['pnr']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['ticket_number']; ?></td>
															<td width='11%'><?php echo $row_loop_pax['salutation']; ?> <?php echo $row_loop_pax['fname']; ?> <?php echo $row_loop_pax['lname']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['dob']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['ppn']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['ppe']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['meal']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['wheelchair']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['baggage']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['eticket_emailed']; ?></td>
															<td width='8%'><?php echo $row_loop_pax['invoice_raised']; ?></td>
															<td width='5%'><?php echo $row_loop_pax['remarks']; ?></td>
															</tr>
															<?php
														}
														?>
														</tbody>
														</table></br></br>
														<?php
													}
												}
												else if($order_type_itinerary == 'gds')
												{
													$total_paxs = 0;
													$query_summary_loop = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api'";
													$result_loop = mysqli_query($mysqli, $query_summary_loop) or die(mysqli_error($mysqli));
													while($row_loop = mysqli_fetch_assoc($result_loop))
													{
														$order_id_pax_view = $row_loop['order_id'];
														$travel_date = $row_loop['travel_date'];
														$return_date = $row_loop['return_date'];
														$co_order_id_pax_view = $row_loop['co_order_id'];
														$product_id_pax_view = $row_loop['product_id'];
														if($return_date != $travel_date) // return trip block
														{
															$parts_trip = explode("-", $row_loop['trip_code']);
															if (count($parts_trip) >= 2) {
																$origin_place = $parts_trip[0];
																$destination_place = $parts_trip[1];
																	echo '<tr><th colspan="6">'. $origin_place . ' - ' . $destination_place . ' - ' . $travel_date .'</th></tr>';
															}
															?>
															<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
															<thead>
																<tr>
																	<th>PNR</th>
																	<th>Ticket</th>
																	<th>Name</th>
																	<th>DOB</th>
																	<th>PPN</th>
																	<th>PPE</th>
																	<th>Meal</th>
																	<th>Wheelchair</th>
																	<th>Baggage</th>
																	<th>e-ticket</br>emailed</th>
																	<th>Invoice</br>Raised</th>
																	<th>Remarks</th>
																</tr>
															</thead>
															<tbody>
															<?php
															$query_summary_loop_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && co_order_id = '$co_order_id_pax_view' && product_id = '$product_id_pax_view'";
															$result_loop_pax = mysqli_query($mysqli, $query_summary_loop_pax) or die(mysqli_error($mysqli));
															while($row_loop_pax = mysqli_fetch_assoc($result_loop_pax))
															{
																$total_paxs++;
																?>
																<tr>
																<td width='8%'><?php echo $row_loop_pax['pnr']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ticket_number']; ?></td>
																<td width='11%'><?php echo $row_loop_pax['salutation']; ?> <?php echo $row_loop_pax['fname']; ?> <?php echo $row_loop_pax['lname']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['dob']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppn']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppe']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['meal']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['wheelchair']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['baggage']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['eticket_emailed']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['invoice_raised']; ?></td>
																<td width='5%'><?php echo $row_loop_pax['remarks']; ?></td>
																</tr>
																<?php
															}
															?>
															</tbody>
															</table></br></br>
															<?php
															$parts_trip = explode("-", $row_loop['trip_code']);
															if (count($parts_trip) >= 2) {
																$origin_place = $parts_trip[0];
																$destination_place = $parts_trip[1];
																	echo '<tr><th colspan="6">'. $destination_place . ' - ' . $origin_place . ' - ' . $return_date .'</th></tr>';
															}
															?>
															<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
															<thead>
																<tr>
																	<th>PNR</th>
																	<th>Ticket</th>
																	<th>Name</th>
																	<th>DOB</th>
																	<th>PPN</th>
																	<th>PPE</th>
																	<th>Meal</th>
																	<th>Wheelchair</th>
																	<th>Baggage</th>
																	<th>e-ticket</br>emailed</th>
																	<th>Invoice</br>Raised</th>
																	<th>Remarks</th>
																</tr>
															</thead>
															<tbody>
															<?php
															$query_summary_loop_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && co_order_id = '$co_order_id_pax_view' && product_id = '$product_id_pax_view'";
															$result_loop_pax = mysqli_query($mysqli, $query_summary_loop_pax) or die(mysqli_error($mysqli));
															while($row_loop_pax = mysqli_fetch_assoc($result_loop_pax))
															{
																$total_paxs++;
																?>
																<tr>
																<td width='8%'><?php echo $row_loop_pax['pnr']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ticket_number']; ?></td>
																<td width='11%'><?php echo $row_loop_pax['salutation']; ?> <?php echo $row_loop_pax['fname']; ?> <?php echo $row_loop_pax['lname']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['dob']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppn']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppe']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['meal']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['wheelchair']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['baggage']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['eticket_emailed']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['invoice_raised']; ?></td>
																<td width='5%'><?php echo $row_loop_pax['remarks']; ?></td>
																</tr>
																<?php
															}
															?>
															</tbody>
															</table></br></br>
															<?php
														}
														else
														{
															?>
															<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
															<thead>
																<tr>
																	<th>PNR</th>
																	<th>Ticket</th>
																	<th>Name</th>
																	<th>DOB</th>
																	<th>PPN</th>
																	<th>PPE</th>
																	<th>Meal</th>
																	<th>Wheelchair</th>
																	<th>Baggage</th>
																	<th>e-ticket</br>emailed</th>
																	<th>Invoice</br>Raised</th>
																	<th>Remarks</th>
																</tr>
															</thead>
															<tbody>
															<?php
															$query_summary_loop_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && co_order_id = '$co_order_id_pax_view' && product_id = '$product_id_pax_view'";
															$result_loop_pax = mysqli_query($mysqli, $query_summary_loop_pax) or die(mysqli_error($mysqli));
															while($row_loop_pax = mysqli_fetch_assoc($result_loop_pax))
															{
																$total_paxs++;
																?>
																<tr>
																<td width='8%'><?php echo $row_loop_pax['pnr']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ticket_number']; ?></td>
																<td width='11%'><?php echo $row_loop_pax['salutation']; ?> <?php echo $row_loop_pax['fname']; ?> <?php echo $row_loop_pax['lname']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['dob']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppn']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['ppe']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['meal']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['wheelchair']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['baggage']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['eticket_emailed']; ?></td>
																<td width='8%'><?php echo $row_loop_pax['invoice_raised']; ?></td>
																<td width='5%'><?php echo $row_loop_pax['remarks']; ?></td>
																</tr>
																<?php
															}
															?>
															</tbody>
															</table></br></br>
															<?php
														}
													}
												}
												?>
											
									</div>
									
									<div id="fullnames_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent" style="display:none;">
										<button class="tablinks chatcategory" onclick="openCity(event, 'names_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>')">Switch to table view</button></br></br>
										Pax Details</br>
										<?php
										$order_id = $order_id_api;
										if($order_type_itinerary == 'WPT' || $order_type_itinerary == '')
										{
											$query_pax = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api'";
											$result_pax = mysqli_query($mysqli, $query_pax);
											while($row_pax = mysqli_fetch_assoc($result_pax))
											{
												echo $row_pax['product_title']. ' | ' .$row_pax['trip_code'];  
												?>
												</br>
												<?php
												$pax_counter = 1;
												$selected_orderid = $row_pax['order_id'];
												$selected_product_id = $row_pax['product_id'];
												$original_co_order_id = $row_pax['co_order_id'];
												$query_2 = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$selected_orderid' && product_id='$selected_product_id' && co_order_id = '$original_co_order_id'";
												$result_2 = mysqli_query($mysqli, $query_2);
												while($row_2 = mysqli_fetch_assoc($result_2))
												{
												
												echo 'Pax '.$pax_counter;
												?>
												<div style="display: flex;">
													<div style="width: 50%; padding-right: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; width: 40%; background-color: #f2f2f2;">Salutation</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['salutation']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">First Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['fname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Last Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['lname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Gender</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['gender']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ppn']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Expiry</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo$row_2['ppe']?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">DOB</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo date('d-m-Y', strtotime($row_2['dob'])); ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Country</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['country']; ?></td>
															</tr>
														</table>
													</div>
													
													<div style="width: 50%; padding-left: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															 <tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">PNR</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['pnr']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Phone</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['phone_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Email</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['email_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Ticket Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ticket_number']; ?></td>
															</tr>
															
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Meal</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['meal']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">e-ticket emailed</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['eticket_emailed']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Wheelchair</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['wheelchair']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Adult Order</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['adult_order']; ?></td>
															</tr>
														</table>
													</div>
												</div>
												</br>
												<?php
												$pax_counter++;
												}
												echo '</br>'; 
											}
										}
										else if($order_type_itinerary == 'gds')
												{
													$total_paxs = 0;
													$query_summary_loop = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api'";
													$result_loop = mysqli_query($mysqli, $query_summary_loop) or die(mysqli_error($mysqli));
													while($row_loop = mysqli_fetch_assoc($result_loop))
													{
														$order_id_pax_view = $row_loop['order_id'];
														$travel_date = $row_loop['travel_date'];
														$return_date = $row_loop['return_date'];
														$co_order_id_pax_view = $row_loop['co_order_id'];
														$product_id_pax_view = $row_loop['product_id'];
														if($return_date != $travel_date) // return trip block
														{
															$parts_trip = explode("-", $row_loop['trip_code']);
															if (count($parts_trip) >= 2) {
																$origin_place = $parts_trip[0];
																$destination_place = $parts_trip[1];
																	echo '<tr><th colspan="6">'. $origin_place . ' - ' . $destination_place . ' - ' . $travel_date .'</th></tr>';
															}
															?>
															<?php
												$pax_counter = 1;
												
												$query_2 = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && product_id='$product_id_pax_view' && co_order_id = '$co_order_id_pax_view'";
												$result_2 = mysqli_query($mysqli, $query_2);
												while($row_2 = mysqli_fetch_assoc($result_2))
												{
												echo '</br>';
												echo 'Pax '.$pax_counter;
												?>
												<div style="display: flex;">
													<div style="width: 50%; padding-right: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; width: 40%; background-color: #f2f2f2;">Salutation</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['salutation']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">First Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['fname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Last Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['lname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Gender</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['gender']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ppn']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Expiry</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo$row_2['ppe']?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">DOB</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo date('d-m-Y', strtotime($row_2['dob'])); ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Country</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['country']; ?></td>
															</tr>
														</table>
													</div>
													
													<div style="width: 50%; padding-left: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															 <tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">PNR</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['pnr']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Phone</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['phone_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Email</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['email_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Ticket Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ticket_number']; ?></td>
															</tr>
															
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Meal</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['meal']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">e-ticket emailed</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['eticket_emailed']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Wheelchair</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['wheelchair']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Adult Order</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['adult_order']; ?></td>
															</tr>
														</table>
													</div>
												</div>
												</br>
												<?php
												$pax_counter++;
												}
												echo '</br>'; 
															$parts_trip = explode("-", $row_loop['trip_code']);
															if (count($parts_trip) >= 2) {
																$origin_place = $parts_trip[0];
																$destination_place = $parts_trip[1];
																	echo '<tr><th colspan="6">'. $destination_place . ' - ' . $origin_place . ' - ' . $return_date .'</th></tr>';
															}
															?>
															<?php
												$pax_counter = 1;
												
												$query_2 = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && product_id='$product_id_pax_view' && co_order_id = '$co_order_id_pax_view'";
												$result_2 = mysqli_query($mysqli, $query_2);
												while($row_2 = mysqli_fetch_assoc($result_2))
												{
												echo '</br>';
												echo 'Pax '.$pax_counter;
												?>
												<div style="display: flex;">
													<div style="width: 50%; padding-right: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; width: 40%; background-color: #f2f2f2;">Salutation</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['salutation']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">First Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['fname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Last Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['lname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Gender</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['gender']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ppn']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Expiry</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo$row_2['ppe']?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">DOB</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo date('d-m-Y', strtotime($row_2['dob'])); ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Country</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['country']; ?></td>
															</tr>
														</table>
													</div>
													
													<div style="width: 50%; padding-left: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															 <tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">PNR</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['pnr']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Phone</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['phone_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Email</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['email_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Ticket Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ticket_number']; ?></td>
															</tr>
															
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Meal</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['meal']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">e-ticket emailed</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['eticket_emailed']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Wheelchair</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['wheelchair']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Adult Order</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['adult_order']; ?></td>
															</tr>
														</table>
													</div>
												</div>
												</br>
												<?php
												$pax_counter++;
												}
												echo '</br>'; 
														}
														else
														{
															?>
															<?php
												$pax_counter = 1;
												
												$query_2 = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id_pax_view' && product_id='$product_id_pax_view' && co_order_id = '$co_order_id_pax_view'";
												$result_2 = mysqli_query($mysqli, $query_2);
												while($row_2 = mysqli_fetch_assoc($result_2))
												{
												echo '</br>';
												echo 'Pax '.$pax_counter;
												?>
												<div style="display: flex;">
													<div style="width: 50%; padding-right: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; width: 40%; background-color: #f2f2f2;">Salutation</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['salutation']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">First Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['fname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Last Name</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['lname']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Gender</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['gender']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ppn']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Passport Expiry</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo$row_2['ppe']?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">DOB</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo date('d-m-Y', strtotime($row_2['dob'])); ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Country</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['country']; ?></td>
															</tr>
														</table>
													</div>
													
													<div style="width: 50%; padding-left: 10px;">
														<table style="width: 100%; border: 1px solid #c2c0bc; border-collapse: collapse; font-size:14px;">
															 <tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">PNR</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['pnr']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Phone</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['phone_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Email</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['email_pax']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Ticket Number</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['ticket_number']; ?></td>
															</tr>
															
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Meal</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['meal']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">e-ticket emailed</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['eticket_emailed']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Wheelchair</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['wheelchair']; ?></td>
															</tr>
															<tr>
																<td style="border: 1px solid #c2c0bc; padding: 8px; background-color: #f2f2f2;">Adult Order</td>
																<td style="border: 1px solid #c2c0bc; padding: 8px;"><?php echo $row_2['adult_order']; ?></td>
															</tr>
														</table>
													</div>
												</div>
												</br>
												<?php
												$pax_counter++;
												}
												echo '</br>'; 
														}
													}
												}
										?>
									</div>
									
									<div id="payments_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent" style="display:none;">
										
										<?php
										$order_id = $order_id_api;
										// Get booking details from API endpoint (for payment section)
										// API Endpoint: GET /v1/customer-view-bookings/{order_id}
										// Source: CustomerViewBookingsDAL::getBookingDetails
										// Query parameters: order_id (required)
										// Response payload: { "booking": {...} } or { "status": "success", "data": { "booking": {...} } }
										$row_initial_order = [];
										
										try {
											$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api);
											
											$ch = curl_init();
											curl_setopt($ch, CURLOPT_URL, $apiUrl);
											curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
											curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
											curl_setopt($ch, CURLOPT_TIMEOUT, 30);
											curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
											curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
											
											$response = curl_exec($ch);
											$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
											curl_close($ch);
											
											if ($httpCode === 200 && $response) {
												$jsonResponse = json_decode($response, true);
												if (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['booking'])) {
													$row_initial_order = $jsonResponse['data']['booking'];
												} elseif (isset($jsonResponse['booking'])) {
													$row_initial_order = $jsonResponse['booking'];
												}
											} else {
												error_log("Customer View Bookings Initial Order API Error: HTTP $httpCode - " . substr($response, 0, 200));
											}
										} catch (Exception $e) {
											error_log("Customer View Bookings Initial Order API Exception: " . $e->getMessage());
										}
										
										// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
										/*
										// Query: Get Booking
										// SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1
										// Source: CustomerViewBookingsDAL::getBookingDetails
										// Method: GET
										// Endpoint: /v1/customer-view-bookings/{order_id}
										// Query parameters: order_id (required)
										
										$query_initial_order = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_api'";
										$result_initial_order = mysqli_query($mysqli, $query_initial_order);
										$row_initial_order = mysqli_fetch_assoc($result_initial_order);
										*/
										$order_date = $row_initial_order['order_date'];
										$order_type_paymentblock = $row_initial_order['order_type'];
										$payment_status = $row_initial_order['payment_status'];
										$late_modified = $row_initial_order['late_modified'];
										$modified_by = $row_initial_order['modified_by'];
										$payment_modified = $row_initial_order['payment_modified'];
										$payment_modified_by = $row_initial_order['payment_modified_by'];
										
										if($order_type_paymentblock != 'gds')
										{
											$total_amount = $row_initial_order['total_amount'];
											$deposit_amount = $row_initial_order['deposit_amount'];
											$balance = $row_initial_order['balance'];
										}
										else
										{
											$total_amount = get_meta_from_history_of_updates($order_id_api, 'Transaction TotalTurnover');
											$deposit_amount_temp = 0;
											$query_initial_order_meta = "SELECT * FROM wpk4_backend_history_of_updates where type_id='$order_id_api' && meta_key = 'Payments Amount'";
											$result_initial_order_meta = mysqli_query($mysqli, $query_initial_order_meta);
											while($row_initial_order_meta = mysqli_fetch_assoc($result_initial_order_meta))
											{
												$deposit_amount_temp = $deposit_amount_temp + (float)$row_initial_order_meta['meta_value'];
											}
											$deposit_amount = $deposit_amount_temp;
											$balance = (float)$total_amount - (float)$deposit_amount;
										}
										if($balance == '')
										{
											$balance = (float)$total_amount - (float)$deposit_amount;
										}
										?>
										Total Amount: <b><?php echo number_format((float)$total_amount, 2, '.', ''); ?></b></br>
										Amount paid: <b><?php echo number_format((float)$deposit_amount, 2, '.', ''); ?></b></br>
										Balance: <b><?php echo number_format((float)$balance, 2, '.', ''); ?></b></br>
										<table style="font-size:13px;">
											<tr><th>Payment Date</th><th>Paid Amount</th><th>Reference No</th></tr>
											<?php
// Fetch payment history from API endpoint
// API Endpoint: GET /v1/customer-view-bookings/{order_id}/payments
// Source: CustomerViewBookingsDAL::getPaymentHistory
// Query parameters: order_id (required, in URL path)
// Response payload: { "payments": [...] } or { "status": "success", "data": { "payments": [...] } }
$paymentHistoryRows = [];
$payment_api_error = '';

try {
	$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api) . '/payments';
	
	$chPayments = curl_init();
	curl_setopt($chPayments, CURLOPT_URL, $apiUrl);
	curl_setopt($chPayments, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($chPayments, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($chPayments, CURLOPT_TIMEOUT, 30);
	curl_setopt($chPayments, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($chPayments, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
	
	$paymentsResponse = curl_exec($chPayments);
	$paymentsError = curl_error($chPayments);
	$paymentsHttpCode = curl_getinfo($chPayments, CURLINFO_HTTP_CODE);
	curl_close($chPayments);
	
	if ($paymentsError) {
		$payment_api_error = "Connection error: " . $paymentsError;
		error_log('Customer View Bookings Payment History API cURL Error: ' . $paymentsError);
	} elseif ($paymentsHttpCode === 200 && $paymentsResponse) {
		$decodedPayments = json_decode($paymentsResponse, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$payment_api_error = "Invalid response format from API";
			error_log('Customer View Bookings Payment History API JSON Error: ' . json_last_error_msg());
		} elseif (isset($decodedPayments['status']) && $decodedPayments['status'] === 'error') {
			$payment_api_error = isset($decodedPayments['message']) ? $decodedPayments['message'] : "API returned an error";
			error_log('Customer View Bookings Payment History API Error Response: ' . $payment_api_error);
		} elseif (json_last_error() === JSON_ERROR_NONE) {
			if (isset($decodedPayments['status']) && $decodedPayments['status'] === 'success' && isset($decodedPayments['data']['payments'])) {
				$paymentHistoryRows = is_array($decodedPayments['data']['payments']) ? $decodedPayments['data']['payments'] : [];
			} elseif (isset($decodedPayments['payments']) && is_array($decodedPayments['payments'])) {
				$paymentHistoryRows = $decodedPayments['payments'];
			} elseif (isset($decodedPayments['data']) && is_array($decodedPayments['data'])) {
				$paymentHistoryRows = $decodedPayments['data'];
			}
		}
	} else {
		$error_detail = '';
		if ($paymentsResponse) {
			$errorResponse = json_decode($paymentsResponse, true);
			if (isset($errorResponse['message'])) {
				$error_detail = $errorResponse['message'];
			} else {
				$error_detail = substr($paymentsResponse, 0, 200);
			}
		}
		$payment_api_error = "API request failed (HTTP $paymentsHttpCode)" . ($error_detail ? ": " . $error_detail : "");
		error_log('Customer View Bookings Payment History API Error: HTTP ' . $paymentsHttpCode . ' - ' . substr($paymentsResponse, 0, 200));
	}
} catch (Exception $e) {
	$payment_api_error = "Exception: " . $e->getMessage();
	error_log('Customer View Bookings Payment History API Exception: ' . $e->getMessage());
}

// Display error if API call failed
if (!empty($payment_api_error)) {
	echo '<div style="margin: 10px 0; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 13px;">';
	echo '<strong>Error loading payment history:</strong> ' . htmlspecialchars($payment_api_error);
	echo '</div>';
}

if (!empty($paymentHistoryRows)):
	foreach ($paymentHistoryRows as $row_payment_history):
											?>
											<tr><td><?php echo date('d/m/Y H:i:s', strtotime($row_payment_history['process_date'])); ?></td><td><?php echo $row_payment_history['trams_received_amount']; ?></td><td><?php echo $row_payment_history['reference_no']; ?></td></tr>
											
											<?php
	endforeach;
endif;

/* Original SQL query retained for reference
$query_payment_history = "SELECT * FROM wpk4_backend_travel_payment_history where order_id='$order_id_api'";
$result_payment_history = mysqli_query($mysqli, $query_payment_history);
while($row_payment_history = mysqli_fetch_assoc($result_payment_history))
{
	// render rows
}
*/
											
											if($order_type_paymentblock == 'gds')
											{
												?>
												<tr><td colspan='3'>Payment records not available</td></tr>
												<?php
											}
											
											?>
										</table>
										
										<h5 style="font-size:14px;">Remark</h5>
										<?php
										$query_chat_call_out_remarks = "SELECT * FROM wpk4_backend_travel_payment_conversations 		
											where order_id='$order_id_api' && msg_type = 'call_out_remarks' order by updated_on desc limit 1";
											$result_chat_call_out_remarks = mysqli_query($mysqli, $query_chat_call_out_remarks) or die(mysqli_error($mysqli));
											$row_chat_call_out_remarks = mysqli_fetch_assoc($result_chat_call_out_remarks);
											$call_out_remarks = $row_chat_call_out_remarks['message'];
										echo $call_out_remarks;
										?>
										
										<form action="#" name="statusupdate" method="post" enctype="multipart/form-data" >
										<h6>Attachments</h6>
										<?php
										// Fetch payment attachments from API endpoint
										// API Endpoint: GET /v1/customer-view-bookings/{order_id}/attachments
										// Source: CustomerViewBookingsDAL::getPaymentAttachments
										// Query parameters: order_id (required), co_order_id (optional), product_id (optional)
										// Response payload: { "attachments": [...] } or { "status": "success", "data": { "attachments": [...] } }
										$payment_attachments = [];
										$attachments_api_error = '';
										
										try {
											$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api) . '/attachments';
											$queryParams = [];
											if (!empty($co_order_id_api)) {
												$queryParams['co_order_id'] = urlencode($co_order_id_api);
											}
											if (!empty($product_id_api)) {
												$queryParams['product_id'] = urlencode($product_id_api);
											}
											if (!empty($queryParams)) {
												$apiUrl .= '?' . http_build_query($queryParams);
											}
											
											$ch = curl_init();
											curl_setopt($ch, CURLOPT_URL, $apiUrl);
											curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
											curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
											curl_setopt($ch, CURLOPT_TIMEOUT, 30);
											curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
											curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
											
											$response = curl_exec($ch);
											$curl_error = curl_error($ch);
											$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
											curl_close($ch);
											
											if ($curl_error) {
												$attachments_api_error = "Connection error: " . $curl_error;
												error_log("Customer View Bookings Payment Attachments API cURL Error: " . $curl_error);
											} elseif ($httpCode === 200 && $response) {
												$jsonResponse = json_decode($response, true);
												if (json_last_error() !== JSON_ERROR_NONE) {
													$attachments_api_error = "Invalid response format from API";
													error_log("Customer View Bookings Payment Attachments API JSON Error: " . json_last_error_msg());
												} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'error') {
													$attachments_api_error = isset($jsonResponse['message']) ? $jsonResponse['message'] : "API returned an error";
													error_log("Customer View Bookings Payment Attachments API Error Response: " . $attachments_api_error);
												} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['attachments'])) {
													$payment_attachments = is_array($jsonResponse['data']['attachments']) ? $jsonResponse['data']['attachments'] : [];
												} elseif (isset($jsonResponse['attachments']) && is_array($jsonResponse['attachments'])) {
													$payment_attachments = $jsonResponse['attachments'];
												}
											} else {
												$error_detail = '';
												if ($response) {
													$errorResponse = json_decode($response, true);
													if (isset($errorResponse['message'])) {
														$error_detail = $errorResponse['message'];
													} else {
														$error_detail = substr($response, 0, 200);
													}
												}
												$attachments_api_error = "API request failed (HTTP $httpCode)" . ($error_detail ? ": " . $error_detail : "");
												error_log("Customer View Bookings Payment Attachments API Error: HTTP $httpCode - " . substr($response, 0, 200));
											}
										} catch (Exception $e) {
											$attachments_api_error = "Exception: " . $e->getMessage();
											error_log("Customer View Bookings Payment Attachments API Exception: " . $e->getMessage());
										}
										
										// Display error if API call failed
										if (!empty($attachments_api_error)) {
											echo '<div style="margin: 10px 0; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 13px;">';
											echo '<strong>Error loading payment attachments:</strong> ' . htmlspecialchars($attachments_api_error);
											echo '</div>';
										}
										
										// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
										/*
										// Query: Get Payment Attachments
										// SELECT * FROM wpk4_backend_travel_booking_update_history 
										// WHERE order_id = :order_id
										//   AND co_order_id = :co_order_id
										//   AND merging_id = :product_id
										//   AND meta_key LIKE 'G360Events'
										//   AND meta_value LIKE 'g360paymentattachments'
										// ORDER BY auto_id DESC
										// Source: CustomerViewBookingsDAL::getPaymentAttachments
										// Method: GET
										// Endpoint: /v1/customer-view-bookings/{order_id}/attachments
										// Query parameters: order_id (required), co_order_id (optional), product_id (optional)
										
										$query_all_refunds_latest_chat = "SELECT * FROM wpk4_backend_travel_booking_update_history where order_id = '$order_id_api' && co_order_id = '$co_order_id_api' && merging_id = '$product_id_api' && meta_key like 'G360Events' && meta_value like 'g360paymentattachments' order by auto_id desc";
										$result_all_refunds_latest_chat = mysqli_query($mysqli, $query_all_refunds_latest_chat);
										$row_all_refunds_latest_chat_count = mysqli_num_rows($result_all_refunds_latest_chat);
										*/
										
										if(count($payment_attachments) > 0)
										{
											?>
											<table style="font-size:13px;">
											<tr><td width="70%">Attachment</td><td>Date uploaded</td><td>Updated by</td></tr>
											<?php
											foreach($payment_attachments as $row_all_refunds_latest_chat)
											{
												$payment_file_extension = pathinfo($row_all_refunds_latest_chat['meta_key_data'], PATHINFO_EXTENSION);
												if($payment_file_extension == 'pdf' || $payment_file_extension == 'txt')
												{
													echo '<tr><td><a target="_blank" href="https://gauratravel.com.au/wp-content/uploads/customized_function_uploads/'.$row_all_refunds_latest_chat['meta_key_data'].'">'.$row_all_refunds_latest_chat['meta_key_data'].'</a></td><td>'.$row_all_refunds_latest_chat['updated_time'].'</td><td>'.$row_all_refunds_latest_chat['updated_user'].'</td></tr>';
												}
												else
												{
													echo '<tr><td><a target="_blank" href="https://gauratravel.com.au/wp-content/uploads/customized_function_uploads/'.$row_all_refunds_latest_chat['meta_key_data'].'"><img src="https://gauratravel.com.au/wp-content/uploads/customized_function_uploads/'.$row_all_refunds_latest_chat['meta_key_data'].'" style="width:200px;"></a></td><td>'.$row_all_refunds_latest_chat['updated_time'].'</td><td>'.$row_all_refunds_latest_chat['updated_user'].'</td></tr>';
												}
											}
											?>
											</table>
											<?php
										}
										else
										{
											echo 'No attachment found';
										}
										?>
										
									</form>	  
									
									</div>
									
									<div id="portal_request_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent" style="display:none;">
											<?php
$order_id = $order_id_api;

// Fetch pax details from API endpoint to get PNRs
// API Endpoint: GET /v1/customer-view-bookings/{order_id}/pax
// Source: CustomerViewBookingsDAL::getPaxDetails
// Query parameters: order_id (required), co_order_id (optional), product_id (optional)
// Response payload: { "pax": [...] } or { "status": "success", "data": { "pax": [...] } }
$array_pax_pnr = [];

try {
	$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id) . '/pax';
	$queryParams = [];
	if (!empty($co_order_id_api)) {
		$queryParams['co_order_id'] = urlencode($co_order_id_api);
	}
	if (!empty($product_id_api)) {
		$queryParams['product_id'] = urlencode($product_id_api);
	}
	if (!empty($queryParams)) {
		$apiUrl .= '?' . http_build_query($queryParams);
	}
	
	$chPax = curl_init();
	curl_setopt($chPax, CURLOPT_URL, $apiUrl);
	curl_setopt($chPax, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($chPax, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($chPax, CURLOPT_TIMEOUT, 30);
	curl_setopt($chPax, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($chPax, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
	
	$paxResponse = curl_exec($chPax);
	$paxError = curl_error($chPax);
	$paxHttpCode = curl_getinfo($chPax, CURLINFO_HTTP_CODE);
	curl_close($chPax);
	
	if ($paxHttpCode === 200 && $paxResponse) {
		$decodedPax = json_decode($paxResponse, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$paxData = [];
			if (isset($decodedPax['status']) && $decodedPax['status'] === 'success' && isset($decodedPax['data']['pax'])) {
				$paxData = is_array($decodedPax['data']['pax']) ? $decodedPax['data']['pax'] : [];
			} elseif (isset($decodedPax['pax']) && is_array($decodedPax['pax'])) {
				$paxData = $decodedPax['pax'];
			} elseif (isset($decodedPax['data']) && is_array($decodedPax['data'])) {
				$paxData = $decodedPax['data'];
			}
			
			foreach ($paxData as $paxRow) {
				if (!empty($paxRow['pnr'])) {
					$array_pax_pnr[] = $paxRow['pnr'];
				}
			}
		}
	} else {
		error_log('Customer View Bookings Pax API Error: HTTP ' . $paxHttpCode . ' - ' . substr($paxResponse, 0, 200));
	}
} catch (Exception $e) {
	error_log('Customer View Bookings Pax API Exception: ' . $e->getMessage());
}

// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
/*
// Query: Get Pax Details
// SELECT * FROM wpk4_backend_travel_booking_pax 
// WHERE order_id = :order_id
// Source: CustomerViewBookingsDAL::getPaxDetails
// Method: GET
// Endpoint: /v1/customer-view-bookings/{order_id}/pax
// Query parameters: order_id (required), co_order_id (optional), product_id (optional)

$query_summary_loop_pax = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id'";
$result_loop_pax = mysqli_query($mysqli, $query_summary_loop_pax) or die(mysqli_error($mysqli));
while($row_loop_pax = mysqli_fetch_assoc($result_loop_pax))
{
	$array_pax_pnr[] = $row_loop_pax['pnr'];
}
*/

$array_pax_pnr = array_unique($array_pax_pnr);
$array_pax_pnr_separated = implode("', '", $array_pax_pnr);
if (!empty($array_pax_pnr_separated)) {
	$array_pax_pnr_separated = "'" . $array_pax_pnr_separated . "'";
}

// Fetch portal requests from API endpoint
// API Endpoint: GET /v1/customer-view-bookings/{order_id}/portal-requests
// Source: CustomerViewBookingsDAL::getPortalRequests
// Query parameters: order_id (required), pnrs (optional, comma-separated)
// Response payload: { "requests": [...] } or { "status": "success", "data": { "requests": [...] } }
$portal_requests = [];
$count_task_history_userportal = 0;
$portal_api_error = '';

try {
	$apiUrl = $base_url . '/customer-view-bookings/' . urlencode($order_id_api) . '/portal-requests';
	$queryParams = [];
	if (!empty($array_pax_pnr_separated)) {
		$queryParams['pnrs'] = urlencode($array_pax_pnr_separated);
	}
	if (!empty($queryParams)) {
		$apiUrl .= '?' . http_build_query($queryParams);
	}
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $apiUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
	
	$response = curl_exec($ch);
	$curl_error = curl_error($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	
	if ($curl_error) {
		$portal_api_error = "Connection error: " . $curl_error;
		error_log("Customer View Bookings Portal Requests API cURL Error: " . $curl_error);
	} elseif ($httpCode === 200 && $response) {
		$jsonResponse = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$portal_api_error = "Invalid response format from API";
			error_log("Customer View Bookings Portal Requests API JSON Error: " . json_last_error_msg());
		} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'error') {
			$portal_api_error = isset($jsonResponse['message']) ? $jsonResponse['message'] : "API returned an error";
			error_log("Customer View Bookings Portal Requests API Error Response: " . $portal_api_error);
		} elseif (isset($jsonResponse['status']) && $jsonResponse['status'] === 'success' && isset($jsonResponse['data']['requests'])) {
			$portal_requests = is_array($jsonResponse['data']['requests']) ? $jsonResponse['data']['requests'] : [];
			$count_task_history_userportal = count($portal_requests);
		} elseif (isset($jsonResponse['requests']) && is_array($jsonResponse['requests'])) {
			$portal_requests = $jsonResponse['requests'];
			$count_task_history_userportal = count($portal_requests);
		}
	} else {
		$error_detail = '';
		if ($response) {
			$errorResponse = json_decode($response, true);
			if (isset($errorResponse['message'])) {
				$error_detail = $errorResponse['message'];
			} else {
				$error_detail = substr($response, 0, 200);
			}
		}
		$portal_api_error = "API request failed (HTTP $httpCode)" . ($error_detail ? ": " . $error_detail : "");
		error_log("Customer View Bookings Portal Requests API Error: HTTP $httpCode - " . substr($response, 0, 200));
	}
} catch (Exception $e) {
	$portal_api_error = "Exception: " . $e->getMessage();
	error_log("Customer View Bookings Portal Requests API Exception: " . $e->getMessage());
}

// Display error if API call failed
if (!empty($portal_api_error)) {
	echo '<div style="margin: 10px 0; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 13px;">';
	echo '<strong>Error loading portal requests:</strong> ' . htmlspecialchars($portal_api_error);
	echo '</div>';
}

// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
/*
// Query: Get Portal Requests
// SELECT * FROM wpk4_backend_user_portal_requests 
// WHERE reservation_ref = :order_id
//   OR reservation_ref IN (:pnr1, :pnr2, ...)
// Source: CustomerViewBookingsDAL::getPortalRequests
// Method: GET
// Endpoint: /v1/customer-view-bookings/{order_id}/portal-requests
// Query parameters: order_id (required), pnrs (optional, comma-separated)

$query_task_history = "SELECT * FROM wpk4_backend_user_portal_requests where reservation_ref='$order_id_api' OR reservation_ref IN ('$array_pax_pnr_separated')";
$result_task_history = mysqli_query($mysqli, $query_task_history);
$count_task_history_userportal = mysqli_num_rows($result_task_history);
*/
											
											if($count_task_history_userportal > 0)
											{
												?>
												<h5>Portal Requests <span class="badge badge-primary"><?php echo $count_task_history_userportal; ?></span></h5>
												<table style="font-size:13px;">
													<tr><th>CaseID</th><th>Task Type</th><th>Initiated on</th><th>Status</th><th>&nbsp;</th></tr>
													<?php
													foreach($portal_requests as $row_task_history_user)
													{
														$task_order_id = $row_task_history_user['order_id'];
														$task_case_type = $row_task_history_user['case_type'];
														$case_date = $row_task_history_user['case_date'];
															$case_id = $row_task_history_user['case_id'];
														$gstatus = $row_task_history_user['status'];
													$enc_pws = md5($case_id); 
													?>
													<tr><td><?php echo $case_id; ?></td><td><?php echo $task_case_type; ?></td><td><?php echo $case_date; ?></td><td><?php echo $gstatus; ?></td><td><a target="_blank" href="/customer-portal/?p=admin-request-view&id=<?php echo $case_id; ?>&co=<?php echo $enc_pws; ?>&t=&casetype=<?php echo $task_case_type; ?>">View</a></td></tr>
													
													<?php
													}
													?>
												</table>
												<?php
											}
											else
											{
												
												echo '<h5>Customer Portal Requests</h5>
													No task created';
											}
											
											
										?>
										
									</div>

									<div id="itinerary_<?php echo $order_id_api; ?><?php echo $co_order_id_api; ?><?php echo $product_id_api; ?>" class="tabcontent" style="display:none;">
										<?php
										$order_id = $order_id_api;
										if($order_type_itinerary == 'WPT' || $order_type_itinerary == '')
										{
											$order_id_for_itinerary = $order_id_api;
											$query_order_booking_info_itinerary = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id_for_itinerary' order by travel_date asc";
											$result_order_booking_info_itinerary = mysqli_query($mysqli, $query_order_booking_info_itinerary) or die(mysqli_error($mysqli));
											while($row_order_booking_info_itinerary = mysqli_fetch_assoc($result_order_booking_info_itinerary))
											{
												$traveldate_fxed = $row_order_booking_info_itinerary['travel_date'];
												$product_title = $row_order_booking_info_itinerary['product_title'];
												$trip_code = $row_order_booking_info_itinerary['trip_code'];
												$product_id_itinerary = $row_order_booking_info_itinerary['product_id'];
												$new_product_id_itinerary = $row_order_booking_info_itinerary['new_product_id'];
												
												if($new_product_id_itinerary == '' || $new_product_id_itinerary == NULL )
												{
													$wptravel_itineraries_r = get_post_meta( $product_id_itinerary, 'wp_travel_trip_itinerary_data', true );
													$trip_wp_title = get_post_field( 'post_title', $product_id_itinerary );
													$wptravel_travel_outline = get_post_meta( $product_id_itinerary, 'wp_travel_outline', true );
													$productid_for_wptravel_product = $product_id_itinerary;
												}
												else
												{
													$wptravel_itineraries_r = get_post_meta( $new_product_id_itinerary, 'wp_travel_trip_itinerary_data', true );
													$trip_wp_title = get_post_field( 'post_title', $new_product_id_itinerary );
													$wptravel_travel_outline = get_post_meta( $new_product_id_itinerary, 'wp_travel_outline', true );
													$productid_for_wptravel_product = $new_product_id_itinerary;
												}
				
												


												if ( isset( $wptravel_itineraries_r ) && ! empty( $wptravel_itineraries_r ) ) : 
													$wptravel_index = 1;
													$itinerary_location_array = array();
													$itinerary_time_array = array();
													$itinerary_flight_array = array();
													$itinerary_date_array = array();
													$itinerary_datedecider_array = array();
													$itinerary_array_counter = 0;
													$itinerary_counter=0;	
													foreach ( $wptravel_itineraries_r as $wptravel_itinerary ) : 
														$wptravel_time_format = get_option( 'time_format' );
														$wptravel_itinerary_label = '';
														$wptravel_itinerary_title = '';
														$wptravel_itinerary_desc  = '';
														$wptravel_itinerary_date  = '';
														$wptravel_itinerary_time  = '';
														$itinerary_counter = 1;
														$is_itinerary_available = 1;
															$wptravel_itinerary_label = stripslashes( $wptravel_itinerary['label'] );
														
															$wptravel_itinerary_title = stripslashes( $wptravel_itinerary['title'] );
														
															$wptravel_itinerary_desc = stripslashes( $wptravel_itinerary['desc'] );
														
															$wptravel_itinerary_date = wptravel_format_date( $wptravel_itinerary['date'] );
														
															$wptravel_itinerary_time = stripslashes( $wptravel_itinerary['time'] );
															$wptravel_itinerary_time = date( $wptravel_time_format, strtotime( $wptravel_itinerary_time ) ); 
														$itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
														$itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
														$itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
														$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
														$itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
																
														$wptravel_index++;
														$itinerary_array_counter++;
														
													endforeach;
												endif; 
												// DEFINE EXTRA DAYS
												$itinerary_vals = '';
												$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
												$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
												$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
												$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));
												if ( is_array( $itinerary_location_array ) ) {
												$length_aray = count($itinerary_location_array);
												$itinerary_vals .= '<center><table class="m_-8969220568537220410 tripitinerary wp-travel-table-content trip_'.$product_id_itinerary.'" cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">
												<thead>
												  <tr style="background: #f0f0f0; border:none;">
													 <th style="width:20%">From</th>
													 <th style="width:20%">To</th>
													 <th style="width:20%">Flight</th>
													 <th style="width:20%">Departure</th>
													 <th style="width:20%">Arrival</th>
												  </tr>
												</thead>
												<tbody>';
														}
														else {
												// Handle the case when $itinerary_location_array is null
												$itinerary_vals .= '<p>No itinerary locations found.</p>';
												}
												// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
												$is_printed_destination = '';
												for ($i = 0; $i < $length_aray; $i++) {
													if($itinerary_location_array[$i] == 'WAIT')
													{
														//$itinerary_vals .= "<tr>";
														//$itinerary_vals .= '<td colspan="5" style="width:30%">'.$itinerary_location_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
														//$itinerary_vals .= "</tr>"; 
														
														$is_printed_destination = '';
													}
													else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
													{
														//$itinerary_vals .= "<tr>";
														//$itinerary_vals .= '<td colspan="5" style="width:30%">'.$itinerary_datedecider_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
														//$itinerary_vals .= "</tr>"; 
														
														$is_printed_destination = '';
													}
													else
													{
														if ($is_printed_destination == '') 
														{
															 
															$date1 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i]))." ".date("H:i:s", strtotime($itinerary_time_array[$i]));
															$date2 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i+1]))." ".date("H:i:s", strtotime($itinerary_time_array[$i+1]));
															$dateDiff = intval((strtotime($date2) - strtotime($date1)) / 60);
															$hours = intval($dateDiff / 60); 
															$minutes = $dateDiff % 60;
															
															$time_duration = $hours.":".$minutes;
															$airline_from_itinerary = substr($itinerary_flight_array[$i],0,2);
												
															$itinerary_vals .= "<tr>";
															$itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i].'</td>';
															$itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i+1].'</td>';
															$itinerary_vals .= '<td style="width:20%">'.$itinerary_flight_array[$i].'</td>';
															$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i].'</br>'.$itinerary_datedecider_array[$i].'</td>';
															$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i+1].'</br>'.$itinerary_datedecider_array[$i+1].'</td>';
															$itinerary_vals .= "</tr>";
															$empty = '';
															$itinerary_vals .= "<tr style='border:none;'>";
															$itinerary_vals .= '<td colspan="2" style="border:none;">   Class: Economy
																	   </td>';
															$itinerary_vals .= '<td colspan="2" style="border:none;">Operated by: '.$airline_from_itinerary.'</td>';
															$itinerary_vals .= '<td style="border:none;">Duration: </td>';
															$itinerary_vals .= "</tr>";
															
															$is_printed_destination = 'yes';
														}
														else
														{
															$is_printed_destination = '';
														}
													}
												}
												$itinerary_vals .= "</tbody></table></center></br></br>";
												
												$wptravel_travel_outline = get_post_meta( $productid_for_wptravel_product, 'wp_travel_outline', true );

												$itinerary_vals .= $wptravel_travel_outline;
												
												// REPLACE ALL THE FIXED WORDS AS DEPARTURE AND ARRIVAL ON BACKEND
												$itinerary_vals = str_replace("Departure Date", '' ,$itinerary_vals);
												$itinerary_vals = str_replace("Arrival Date", '' ,$itinerary_vals);
												
												$itinerary_vals = str_replace("Selected Date +4", $departure_date_plus_four ,$itinerary_vals);
												$itinerary_vals = str_replace("Selected Date+4", $departure_date_plus_four ,$itinerary_vals);
												
												$itinerary_vals = str_replace("Selected Date +3", $departure_date_plus_three ,$itinerary_vals);
												$itinerary_vals = str_replace("Selected Date+3", $departure_date_plus_three ,$itinerary_vals);
												
												$itinerary_vals = str_replace("Selected Date +2", $departure_date_plus_two ,$itinerary_vals);
												$itinerary_vals = str_replace("Selected Date+2", $departure_date_plus_two ,$itinerary_vals);
												
												$itinerary_vals = str_replace("Selected Date+1", $departure_date_plus_one ,$itinerary_vals);
												$itinerary_vals = str_replace("Selected Date +1", $departure_date_plus_one ,$itinerary_vals);
												
												$traveldate_fxed_1 = date("d/m/Y", strtotime($traveldate_fxed)); 
												
												
												$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
												
												
												$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
												echo $itinerary_vals;
												
												echo '<hr>';
											}
											
											
											
											echo '</br></br>';
											
											
											
										}
										else if($order_type_itinerary == 'gds')
										{
											$carrier = get_meta_from_history_of_updates($order_id_api, "Flightlegs MarketingCarrier");
											$flight_number = get_meta_from_history_of_updates($order_id_api, "Flightlegs FlNr");
											$departure_airport = get_meta_from_history_of_updates($order_id_api, "Flightlegs DepApt");
											$destination_airport = get_meta_from_history_of_updates($order_id_api, "Flightlegs DestApt");
											$departure_time = get_meta_from_history_of_updates($order_id_api, "Flightlegs DepTime");
											$destination_time = get_meta_from_history_of_updates($order_id_api, "Flightlegs DestTime");
											$flight_class = get_meta_from_history_of_updates($order_id_api, "Flightlegs CosDescription");
											$flight_class_code = get_meta_from_history_of_updates($order_id_api, "Flightlegs Class");
											$flight_elapsed_time = get_meta_from_history_of_updates($order_id_api, "Flightlegs Elapsed");
											$flight_meals = get_meta_from_history_of_updates($order_id_api, "Flightlegs Meal");
											$flight_departure_terminal = get_meta_from_history_of_updates($order_id_api, "Flightlegs DepTerminal");
											$flight_destination_terminal = get_meta_from_history_of_updates($order_id_api, "Flightlegs DestTerminal");
											
											
											$carrier_array = explode(' | ', $carrier);
											$flight_number_array = explode(' | ', $flight_number);
											$departure_airport_array = explode(' | ', $departure_airport);
											$destination_airport_array = explode(' | ', $destination_airport);
											$departure_time_array = explode(' | ', $departure_time);
											$destination_time_array = explode(' | ', $destination_time);
											$flight_class_array = explode(' | ', $flight_class);
											$flight_class_code_array = explode(' | ', $flight_class_code);
											$flight_elapsed_time_array = explode(' | ', $flight_elapsed_time);
											$flight_meals_array = explode(' | ', $flight_meals);
											$flight_departure_terminal_array = explode(' | ', $flight_departure_terminal);
											$flight_destination_terminal_array = explode(' | ', $flight_destination_terminal);
											
											$combined_array = array();
											for ($i = 0; $i < count($carrier_array); $i++) {
												$combined_array[] = array(
													'carrier' => $carrier_array[$i],
													'flightnumber' => $flight_number_array[$i],
													'departureairport' => $departure_airport_array[$i],
													'destinationairport' => $destination_airport_array[$i],
													'departuretime' => $departure_time_array[$i],
													'destinationtime' => $destination_time_array[$i],
													'flightclass' => $flight_class_array[$i],
													'flightclasscode' => $flight_class_code_array[$i],
													'flightelapedtime' => $flight_elapsed_time_array[$i],
													'flightdepartureterminal' => $flight_departure_terminal_array[$i],
													'flightdestinationterminal' => $flight_destination_terminal_array[$i],
													'flightmeals' => $flight_meals_array[$i]
												);
											}
											
											echo '<table cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">';
											echo '<tr><th width="10%">Dep Airport</th><th width="15%">Dep Date</th><th width="10%">Flight</th><th width="10%">Dest Airport</th><th width="15%">Destination Time</th><th width="10%">Class</th><th width="10%">Meal</th><th width="10%">Duration</th></tr>';
											$waitTime = '';
											$previousDepTime = null;

											foreach ($combined_array as $index => $item) {
												$totalMinutes = (int)$item['flightelapedtime'] * 60;
												$hours = floor($totalMinutes / 60);
												$minutes = $totalMinutes % 60;
												$timeFormatted = sprintf("%dh %dmin", $hours, $minutes);
												if ($index > 0) {
													$currentDepTime = strtotime($item['departuretime']);
													$previousDestTime = strtotime($combined_array[$index - 1]['destinationtime']);
													$waitTimeMinutes = round(($currentDepTime - $previousDestTime) / 60);
													$waitHours = floor($waitTimeMinutes / 60);
													$waitMinutes = $waitTimeMinutes % 60;
													$waitTime = sprintf("%dh %dmin", $waitHours, $waitMinutes);
												} else {
													$waitTime = '';
												}
												if ($waitTime !== '' && $waitHours < 24) {
													echo '<tr>';
													echo '<td colspan="7">WAIT: ' . $waitTime . '</td>';
													echo '</tr>';
												}
												if ($waitHours >= 24 && $waitTime !== '') {
													echo '</table></br><table cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">
													<tr><th width="10%">Dep Airport</th><th width="15%">Dep Date</th><th width="10%">Flight</th><th width="10%">Dest Airport</th><th width="15%">Destination Time</th><th width="10%">Class</th><th width="10%">Meal</th><th width="10%">Duration</th></tr>';
												}
												echo '<tr>';
												echo '<td>' . $item['departureairport'] . '</br>' . $item['flightdepartureterminal'] . '</td>';
												echo '<td>' . $item['departuretime'] . '</td>';
												echo '<td>' . $item['carrier'] . $item['flightnumber'] . '</td>';
												echo '<td>' . $item['destinationairport'] . '</br>' . $item['flightdestinationterminal'] . '</td>';
												echo '<td>' . $item['destinationtime'] . '</td>';
												echo '<td>' . $item['flightclasscode'] . ' - ' . $item['flightclass'] . '</td>';
												echo '<td>' . $item['flightmeals'] . '</td>';
												echo '<td>' . $timeFormatted . '</td>'; // flight time
												echo '</tr>';
												$previousDepTime = strtotime($item['departuretime']);
											}
											echo '</table>';
											
										}
										else
										{
											echo '<p>No itinerary locations found.</p>';
										}
										?>
									</div>
									




								</div>
            			    
            			    <?php
            			    echo '<hr>';
            			}
				    }
            		else
                	{
                			echo 'No results found!!.';
                	}
            	}
            	else
            	{
            		echo 'Kindly search with PNR/Order ID';
            	}
            	?>
				</div><!-- END OF TAB CONTENT -->
				<script>
				    function openCity(evt, cityName) {
            		  var i, tabcontent, tablinks;
            		  tabcontent = document.getElementsByClassName("tabcontent");
            		  for (i = 0; i < tabcontent.length; i++) {
            			tabcontent[i].style.display = "none";
            		  }
            		  tablinks = document.getElementsByClassName("tablinks");
            		  for (i = 0; i < tablinks.length; i++) {
            			tablinks[i].className = tablinks[i].className.replace(" active", "");
            		  }
            		  document.getElementById(cityName).style.display = "block";
            		  evt.currentTarget.className += " active";
            		  
            		  if(cityName == 'fullnames' || cityName == 'names')
            		  {
            		      document.getElementById("").currentTarget.className += " active";
            		  }
            		}
				</script>
				</br></br>
			
			
	</div>

</body>	
<?php get_footer(); ?>      
    