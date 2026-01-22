<?php
require_once ('../../../../wp-config.php');
include('../../../../wp-config-custom.php');
include "tpl_contralized_functions.php";

global $wpdb;
$endoftoday = date("Y-m-d"). ' 00:00:00';

// API Base URL
$api_base_url = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';

/**
 * Call API endpoint
 */
function callApi($url, $params = []) {
    $fullUrl = $url;
    if (!empty($params)) {
        $queryString = http_build_query($params);
        $fullUrl .= (strpos($url, '?') !== false ? '&' : '?') . $queryString;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("API call failed: $fullUrl - HTTP Code: $httpCode");
        return null;
    }
    
    $data = json_decode($response, true);
    if ($data && isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
        return $data['data'];
    }
    
    return null;
}
if(isset($_GET["airline"]) && !isset($_GET["route"]) && $_GET["airline"] != '' && $_GET["airline"] != 'null')
{
	$airline=$_GET["airline"];
	$array_route_code = array();
	
	// Call API to get routes by airline
	$apiUrl = $api_base_url . '/seat-availability/airlines/' . urlencode($airline) . '/routes';
	$apiData = callApi($apiUrl);
	
	if ($apiData && isset($apiData['routes'])) {
		$array_route_code = $apiData['routes'];
		$rowcount_orderinfo = count($array_route_code);
	} else {
		$rowcount_orderinfo = 0;
	}
		
	if($rowcount_orderinfo > 0)
	{
		$dropdown = "<select name='routeid' required id='routeid' onChange='updatedate(this.value)' style='width:100%; padding:10px;'>
		<option value='' selected>Select</option>
		";
		$array_route_code = array_unique($array_route_code);
		foreach($array_route_code as $value)
		{ 
			$dropdown .= "<option value='".$value."'>".$value."</option>";	
		}
		$dropdown .= "</select>";
	}
    echo $dropdown;
}
if(isset($_GET["route"]) && $_GET["route"] != '' && $_GET["route"] != 'null')
{
	$route=$_GET["route"];
	$array_dates = array();
	
	// Call API to get dates by route
	$apiParams = [];
	if(isset($_GET["airline"]) && $_GET["airline"] != '')
    {
	    $apiParams['airline_code'] = $_GET['airline'];
    }
	
	$apiUrl = $api_base_url . '/seat-availability/routes/' . urlencode($route) . '/dates';
	$apiData = callApi($apiUrl, $apiParams);
	
	if ($apiData && isset($apiData['dates'])) {
		$array_dates = $apiData['dates'];
		$rowcount_orderinfo = count($array_dates);
	} else {
		$rowcount_orderinfo = 0;
	}
	
	// This section returns empty dropdown (original behavior)
    $dropdown = "";
    echo $dropdown;
	
}
if(isset($_GET["routeforprice"]) && $_GET["routeforprice"] != '' && $_GET["routeforprice"] != 'null')
{
	$route=$_GET["routeforprice"];
	$array_route_code = array();
	
	// Call API to get prices by route
	$apiUrl = $api_base_url . '/seat-availability/routes/' . urlencode($route) . '/prices';
	$apiData = callApi($apiUrl);
	
	if ($apiData && isset($apiData['prices'])) {
		$array_route_code = $apiData['prices'];
		$rowcount_orderinfo = count($array_route_code);
	} else {
		$rowcount_orderinfo = 0;
	}
		
	if($rowcount_orderinfo > 0)
		{
			$dropdown = "<select name='saleprice' id='saleprice' style='width:100%; padding:10px;'>
			<option value='' selected>Select</option>
			";
			$array_route_code = array_unique($array_route_code);
			foreach($array_route_code as $value)
			{ 
				$dropdown .= "<option value='".$value."'>".$value."</option>";	
			}
			$dropdown .= "</select>";
		}

    //$dropdown = "";
    echo $dropdown;
	
}
if(isset($_GET["showresults"]))
{
    $popup_results = '';
	//$airlineid = $_GET["airline"];
	if(isset($_GET["airline"]) && $_GET["airline"] != '')
    {
	    $airlineid = $_GET["airline"];
    }
    else
    {
        $airlineid = '';
    }
    
	$routeid = $_GET["route"];
	
	
	$datefrom = $_GET["datefrom"];
	$dateto = $_GET["dateto"];
	
	// Parse concatenated date string (format: "YYYY-MM-DDYYYY-MM-DD" - 20 characters total)
	// First date starts at position 0, second date starts at position 10
	if (!empty($datefrom) && strlen($datefrom) >= 20) {
		// If it's a concatenated string (20 chars), extract both dates
		$api_date_from = substr($datefrom, 0, 10);
		$api_date_to = substr($datefrom, 10, 10);
		$reldate1 = $api_date_from.' 00:00:00';
		$reldate2 = $api_date_to.' 23:59:59';
	} else if (!empty($datefrom) && strlen($datefrom) >= 10) {
		// Single date format (10 chars) - use same date for start and end
		$api_date_from = substr($datefrom, 0, 10);
		$api_date_to = substr($datefrom, 0, 10);
		$reldate1 = $api_date_from.' 00:00:00';
		$reldate2 = $api_date_to.' 23:59:59';
	} else {
		$reldate1 = '';
		$reldate2 = '';
		$api_date_from = '';
		$api_date_to = '';
	}
	
	$saleprice = $_GET["saleprice"];
	
	if(isset($_GET["airline"]) && $_GET["airline"] != '' && $_GET["airline"] != 'NULL' && $_GET["airline"] != 'null')
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
	$popup_results .= '<center><table width="80%" style="font-size:14px;">
	<tr style="border:1px solid black;">
	<th width="25%">Trip Code</th>
	<th width="15%">Travel Date</th>
	<th width="20%">Product Title</th>
	<th width="7%">Adult Price</th>
	<th width="7%">Child Price</th>
	<th width="10%">Seats available</th>
	<th width="15%"></th></tr>';
	$processedTripInfo = [];
	
	// Call API to get seat availability
	$apiParams = [];
	if (!empty($airlineid)) {
		$apiParams['airline_code'] = $airlineid;
	}
	if (!empty($routeid)) {
		$apiParams['route'] = $routeid;
	}
	if (!empty($api_date_from) && !empty($api_date_to)) {
		$apiParams['date_from'] = $api_date_from;
		$apiParams['date_to'] = $api_date_to;
	}
	if (!empty($saleprice)) {
		$apiParams['sale_price'] = $saleprice;
	}
	
	$apiUrl = $api_base_url . '/seat-availability/internal/search';
	$apiData = callApi($apiUrl, $apiParams);
	
	// Convert API response to object array format (similar to $wpdb->get_results)
	$results_trips = [];
	if ($apiData && isset($apiData['availability'])) {
		foreach ($apiData['availability'] as $item) {
			$obj = new stdClass();
			$obj->auto_id = $item['auto_id'] ?? null;
			$obj->trip_id = $item['trip_code'] ?? null;
			$obj->dep_date = $item['travel_date'] ?? null;
			$obj->current_stock = $item['total_stock'] ?? 0;
			$obj->booked_pax = $item['booked_pax'] ?? 0;
			$obj->remaining_seats = $item['remaining_seats'] ?? 0;
			// Convert product_info array to object for compatibility
			if (isset($item['product_info']) && is_array($item['product_info'])) {
				$productInfoObj = new stdClass();
				foreach ($item['product_info'] as $key => $value) {
					$productInfoObj->$key = $value;
				}
				$obj->product_info = $productInfoObj;
			} else {
				$obj->product_info = null;
			}
			$obj->product_title = $item['product_title'] ?? '';
			$obj->adult_price = $item['adult_price'] ?? '';
			$obj->child_price = $item['child_price'] ?? '';
			$obj->pricing_id = $item['pricing_id'] ?? null;
			$results_trips[] = $obj;
		}
	}
	
	$rowcount_total_rows = count($results_trips);
    
    //echo "SELECT * FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc";

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
	   
	   $combination_of_trip = $trip_id.$dep_date;
	   if (in_array($combination_of_trip, $processedTripInfo)) 
	   {
            continue;
        } else {
            $processedTripInfo[] = $combination_of_trip;
        }

        
                                                
	   $dep_date_for_product_manager = date('Y-m-d', strtotime($dep_date));
	   $dep_date_changed = date('d-m-Y', strtotime($dep_date));
	   $dep_date_changed_with_0 = date('Y-m-d', strtotime($dep_date)).' 00:00:00';
	   
	   // Get product info from API response (already included in the data)
	   $trip_product_id = '';
	   $trip_itinerary = '';
	   if (isset($row_all_trips->product_info) && !empty($row_all_trips->product_info)) {
	       $trip_product_id = $row_all_trips->product_info->product_id ?? '';
	       $trip_itinerary = $row_all_trips->product_info->itinerary ?? '';
	   }
	   
	   // Get stock and booking info from API response (already calculated)
	   $current_stock_total = (int)($row_all_trips->current_stock ?? 0);
	   $pax_count = (int)($row_all_trips->booked_pax ?? 0);
	   $order_count = 0; // This would need to be added to API if needed, but not used in display
	   $product_id_itinerary = $trip_product_id;

		$traveldate_fxed = $dep_date;
		//echo $current_stock_total . ' - ' . $pax_count;
		$remainingseats = (int)($row_all_trips->remaining_seats ?? 0);
		if($remainingseats > 0)
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
		else
		{
		    $itinerary_vals = '';
		}
		//itinerary end
		
		$itinerary_details = getGDealFlightItinerary($trip_id, $dep_date);
		
		if($itinerary_details == '')
		{
		    $itinerary_details = $itinerary_vals;
		}
			
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
			
			$add_booking_button = '<a href="/add-booking-internal/?action=add&trip-id='.$trip_id.'&dep-date='.$dep_date.'&seat-available='.$remainingseats.'"><button class="button_customized showitinerary" style="width:160px; height:30px; font-size:10px;">Add Booking</button>';
			
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
			
		// Get pricing info from API response (already included in the data)
		$trip_pricing_id = $row_all_trips->pricing_id ?? '';
		$product_title_drived = $row_all_trips->product_title ?? '';
		$trip_adult_rate = $row_all_trips->adult_price ?? '';
		$trip_child_rate = $row_all_trips->child_price ?? '';
    		
    	// Sale price filtering is already done by API, but keep this check for consistency
    	if($_GET["saleprice"] != '' && $_GET["saleprice"] != 'NULL' && $_GET["saleprice"] != 'null')
    	{
    	    //echo $trip_adult_rate .' != '. $saleprice.'</br>';
    	    if($trip_adult_rate != $saleprice)
    	    {
    	        continue;
    	    }
    	}
			
			$popup_results .= '<tr style="border:1px solid black;"> 
			<td>'.$trip_id .'</td>
			<td>' . $dep_date_changed . '</td>
			<td>' . $product_title_drived . '</td>
			<td>'.$trip_adult_rate.'</td>
			<td>'.$trip_child_rate.'</td>
			<td>'.$remainingseats.'</td><td>'.$itineraryview_button.'</td>';
			
			if( current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ))
			{
			    $popup_results .= '<td>'.$add_booking_button.'</td>';
			}
			
			$popup_results .= '</tr>
			<tr style="border:none;"><td colspan="4" style="border:none;"><div class="itinerarybox" id="itinerary_'.$auto_count_id.'" style="display:none;">'.$itinerary_details.'</div></td></tr>';
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
}
?>