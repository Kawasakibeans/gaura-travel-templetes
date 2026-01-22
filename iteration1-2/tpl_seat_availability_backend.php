<?php
require_once ('../../../../wp-config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Define API base URL
if (!defined('API_BASE_URL')) {
    $base_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';
} else {
    $base_url = API_BASE_URL;
}

// Helper function to make API calls
function make_api_request($method, $endpoint, $data = null) {
    global $base_url;
    
    // Validate inputs to prevent null errors
    if ($endpoint === null) {
        $endpoint = '';
    }
    if ($base_url === null) {
        $base_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';
    }
    
    // Ensure endpoint starts with / if not already
    if (is_string($endpoint) && strpos($endpoint, '/') !== 0 && strpos($endpoint, 'http') !== 0) {
        $endpoint = '/' . $endpoint;
    }
    
    $url = $base_url . $endpoint;
    
    $ch = curl_init($url);
    
    // For GET requests, use GET method; for POST, use POST
    if ($method === 'GET') {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
    } else {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $decoded = json_decode($response, true);
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => $decoded !== null ? $decoded : $response,
        'http_code' => $httpCode
    ];
}

global $wpdb;
$endoftoday = date("Y-m-d"). ' 00:00:00';
if(isset($_GET["airline"]) && !isset($_GET["route"]) && $_GET["airline"] != '' && $_GET["airline"] != 'null')
{
	$airline = $_GET["airline"];
	$airline_encoded = urlencode($airline);
	
	// Call API to get routes for the airline
	$routes_response = make_api_request('GET', '/seat-availability/airlines/' . $airline_encoded . '/routes');
	
	$dropdown = "<select name='routeid' required id='routeid' onChange='updatedate(this.value)' style='width:100%; padding:10px;'>
	<option value='' selected>Select</option>
	";
	
	if ($routes_response['success'] && isset($routes_response['data'])) {
		$routes_data = $routes_response['data']['data'];

		// Handle different possible response structures
		$routes = array();
		if (isset($routes_data['routes']) && is_array($routes_data['routes'])) {
			$routes = $routes_data['routes'];
		} elseif (is_array($routes_data)) {
			$routes = $routes_data;
		}
		
		// Extract route values
		$route_values = array();
		foreach($routes as $route) {
			if (is_array($route) || is_object($route)) {
				$route_obj = (object)$route;
				$route_value = isset($route_obj->route) ? $route_obj->route : (isset($route_obj->code) ? $route_obj->code : '');
			} else {
				$route_value = $route;
			}
			if (!empty($route_value)) {
				$route_values[] = $route_value;
			}
		}
		$route_values = array_unique($route_values);
		sort($route_values);
		
		foreach($route_values as $value) {
			$dropdown .= "<option value='".htmlspecialchars($value)."'>".htmlspecialchars($value)."</option>";
		}
	}
	
	$dropdown .= "</select>";
	echo $dropdown;
}
if(isset($_GET["route"]) && $_GET["route"] != '' && $_GET["route"] != 'null')
{
	$airline = isset($_GET["airline"]) ? $_GET["airline"] : '';
	$route = $_GET["route"];
	$array_dates = array();
	
	// ✅ FIX: This section doesn't output anything - kept for backward compatibility
	// Note: Date selection is handled by date picker in frontend
	// OLD SQL QUERY - COMMENTED OUT (not needed as dates come from search results)
	/*
	$results_lastorder = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where airline_code='$airline' && dep_date > '$endoftoday' order by route asc"); 
	$rowcount_orderinfo = is_array($results_lastorder) ? count($results_lastorder) : 0;
	if($rowcount_orderinfo > 0)
		{
			foreach($results_lastorder as $row_last_order){   
			   $array_dates[] = $row_last_order->dep_date;
			}
		}
	*/
	
    $dropdown = "";
    echo $dropdown;
	
}
if(isset($_GET["showresults"]))
{
    $popup_results = '';
	$airlineid = $_GET["airline"];
	$routeid = $_GET["route"];
	
	
	$datefrom = $_GET["datefrom"];
	$dateto = $_GET["dateto"];
	
	$reldate1 = substr($datefrom, 0, 10).' 00:00:00';
	$reldate2 = substr($dateto, 25, 10).' 23:59:59';
	
	if($_GET["airline"] != '' && $_GET["airline"] != 'NULL' && $_GET["airline"] != 'null')
	{
		$airline= "airline_code = '$airlineid' && ";
	}
	else
	{
		$airline= "airline_code != 'TEMPAIRF' && ";
	}
	
	if($_GET["route"] != '' && $_GET["route"] != 'NULL' && $_GET["route"] != 'null')
	{
		$route= "route = '$routeid' && ";
	}
	else
	{
		$route= "airline_code != 'TEMPAIRF' && ";
	}
	
	if($_GET["datefrom"] != '' && $_GET["datefrom"] != 'NULL' && $_GET["datefrom"] != 'null')
	{
		$datefrom = "dep_date >= '$reldate1' && dep_date <= '$reldate2' ";
	}
	else
	{
		$datefrom = "dep_date >= '$endoftoday'";
	}
	//echo $airline . ' '. $route .' ' . $reldate1 .' ' . $reldate2 . '</br>';
	
	$is_record_found = 0;
	$popup_results .= '<center><table width="80%" style="font-size:14px;"><tr style="border:1px solid black;"><th width="30%">Trip Code</th><th width="20%">Travel Date</th><th width="20%">Fare INR</th><th width="20%">Seats available</th><th width="20%"></th></tr>';
	
	$results_trips = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc"); 
	$rowcount_total_rows = $wpdb->get_var("SELECT COUNT(*) FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc");

    //echo 'user';
   	if($rowcount_total_rows > 0)
	{
	//$delete = $wpdb->query("TRUNCATE TABLE wp_duplicate_seat_availability");
	$auto_count_id = 1;
	$is_itinerary_available = 0;
    foreach($results_trips as $row_all_trips)
	{   
       $auto_id = $row_all_trips->auto_id;
	   $trip_id = $row_all_trips->trip_id; //tripcode
	   $dep_date = $row_all_trips->dep_date;
	   $sub_agent_fare_inr = $row_all_trips->sub_agent_fare_inr;
	   $dep_date_for_product_manager = date('Y-m-d', strtotime($dep_date));
	   $dep_date_changed = date('d-m-Y', strtotime($dep_date));
	   $current_stock = $row_all_trips->current_stock;
	   
	   $tour_name__trip_code = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager'"); 
       foreach($tour_name__trip_code as $row_2){ 
            $trip_product_id = $row_2->product_id;
			$trip_itinerary = $row_2->itinerary;
			//echo $trip_id . ' - ' . $dep_date;
	  }
	   
	   	    
	   
	   
		$results_orderinfo = $wpdb->get_results( "SELECT * FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date' && (payment_status = 'paid' || payment_status = 'partially_paid')"); 
		$order_count = 0;
		$pax_count = 0;
		$product_id_itinerary = $trip_product_id;
		foreach($results_orderinfo as $row_order_rows)
		{
			$pax_count += $row_order_rows->total_pax;
			$order_count++;
		}

		$traveldate_fxed = $dep_date;
		$remainingseats = (int)$current_stock - (int)$pax_count;
		if($remainingseats > 2)
		{
		$is_record_found = 1;
		if($product_id_itinerary !='')
		{
			
			$wptravel_itineraries = get_post_meta( $product_id_itinerary, 'wp_travel_trip_itinerary_data', true );
			$trip_wp_title = get_post_field( 'post_title', $product_id_itinerary );
			if ( isset( $wptravel_itineraries ) && ! empty( $wptravel_itineraries ) ) : 
				$wptravel_index = 1;
				$itinerary_location_array = array();
				$itinerary_time_array = array();
				$itinerary_flight_array = array();
				$itinerary_date_array = array();
				$itinerary_datedecider_array = array();
				$itinerary_array_counter = 0;
				$itinerary_counter=0;	
				foreach ( $wptravel_itineraries as $wptravel_itinerary ) : 
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

			$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
			$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
			$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
			$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));

			$length_aray = count($itinerary_location_array);
			$itinerary_vals = '';
			//$itinerary_vals .= '<h5>'.$trip_wp_title.'</h5></br>';
			$itinerary_vals .= '<center><table class="m_-8969220568537220410 tripitinerary wp-travel-table-content trip_'.$product_id_itinerary.'" cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px;">
			<thead>
			  <tr>
				 <th style="width:30%">Airport</th>
				 <th style="width:30%">Flight</th>
				 <th style="width:20%">Date</th>
				 <th style="width:20%">Time</th>
			  </tr>
			</thead>
			<tbody>';
			// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
			for ($i = 0; $i < $length_aray; $i++) {
				if($itinerary_location_array[$i] == 'WAIT')
				{
					$itinerary_vals .= "<tr>";
					$itinerary_vals .= '<td colspan="4" style="width:30%">'.$itinerary_location_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
					$itinerary_vals .= "</tr>"; 
				}
				else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
				{
					$itinerary_vals .= "<tr>";
					$itinerary_vals .= '<td colspan="4" style="width:30%">'.$itinerary_datedecider_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
					$itinerary_vals .= "</tr>"; 
				}
				else
				{
					$itinerary_vals .= "<tr>";
					$itinerary_vals .= '<td style="width:30%">'.$itinerary_location_array[$i].'</td>';
					$itinerary_vals .= '<td style="width:30%">'.$itinerary_flight_array[$i].'</td>';
					$itinerary_vals .= '<td style="width:20%">'.$itinerary_datedecider_array[$i].'</td>';
					$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i].'</td>';
					$itinerary_vals .= "</tr>";
				}
				
			}
    		$itinerary_vals .= "</tbody></table></center></br></br>";
    		
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
		}
		//itinerary end
		
		
			
			
			/*
			 $wpdb->insert('wp_duplicate_seat_availability', array(
                'tripcode' =>$trip_id,
                'traveldate' =>$dep_date_changed,
                'seat_available' => $remainingseats,
                'currentstock'=>$current_stock,
                'booked' =>$pax_count,
            ));
			*/
			if($remainingseats > 3)
			{
				$available_note = '<font style="color:green;">Available</font>';
			}
			else
			{
				$available_note = '<font style="color:orange;">Available</font>';
			}
			
			if($is_itinerary_available != 0) 
			{ 
				$itineraryview_button = '
				<button class="button_customized showitinerary" style="width:160px; height:30px; font-size:10px;" id="viewitinerary_'.$auto_count_id.'" onClick="showitinerary(this.id)">View Itinerary</button>
				<button class="button_customized hideitinerary" style="width:160px; height:30px; font-size:10px; display:none" id="hideitinerary_'.$auto_count_id.'" onClick="hideitinerary(this.id)">Hide Itinerary</button>
				';
			}
			else
			{
				$itineraryview_button = '';
			}
			
			
			$popup_results .= '<tr style="border:1px solid black;"> 
			<td>'.$trip_id .' '. $product_id_itinerary .'</td>
			<td>' . $dep_date_changed . '</td>';
			if($sub_agent_fare_inr != '')
			{
			    $popup_results .= '<td>&#x20b9; ' . $sub_agent_fare_inr . '</td>';
			}
			else
			{
			    $popup_results .= '<td></td>';
			}
			$popup_results .= '<td>'.$available_note.'</td><td>'.$itineraryview_button.'</td></tr>
			<tr style="border:none;"><td colspan="4" style="border:none;"><div class="itinerarybox" id="itinerary_'.$auto_count_id.'" style="display:none;">'.$itinerary_vals.'</div></td></tr>';
		}
		$auto_count_id++;
    }
	} 
    

	if($is_record_found == 0)
		{
		    $popup_results .= '<tr>
			<td colspan="4">No Record Found!!</td>
			</tr>';
		}
	$popup_results .= '</table><center>';
    
    echo $popup_results;
} else {
    // If no action parameter is set, show a message
    // This file is typically called via AJAX with specific parameters
    if (!isset($_GET['airline']) && !isset($_GET['route']) && !isset($_GET['showresults'])) {
        echo "<div style='padding:20px; font-family:Arial, sans-serif;'>";
        echo "<h2>Seat Availability Backend</h2>";
        echo "<p>This page requires specific parameters:</p>";
        echo "<ul>";
        echo "<li><strong>?airline=XXX</strong> - Get routes for an airline</li>";
        echo "<li><strong>?airline=XXX&route=YYY</strong> - Get dates for a route (no output)</li>";
        echo "<li><strong>?showresults=1&airline=XXX&route=YYY&datefrom=...</strong> - Show search results</li>";
        echo "</ul>";
        echo "<p><small>This endpoint is typically called via AJAX from the frontend.</small></p>";
        echo "</div>";
    }
}
?>