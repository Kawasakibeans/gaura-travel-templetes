<?php
require_once ('../../../../wp-config.php');
global $wpdb;
$endoftoday = date("Y-m-d"). ' 00:00:00';
if(isset($_GET["airline"]) && !isset($_GET["route"]) && $_GET["airline"] != '' && $_GET["airline"] != 'null')
{
	$airline=$_GET["airline"];
	$array_route_code = array();
	$results_lastorder = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where airline_code='$airline' && dep_date > '$endoftoday' order by route asc"); 
	$rowcount_orderinfo = 0;	
		foreach($results_lastorder as $row_last_order){ 
			$rowcount_orderinfo++;
			$array_route_code[] = $row_last_order->route; // last order id
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
	$airline=$_GET["airline"];
	$route=$_GET["route"];
	$array_dates = array();
	$results_lastorder = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where airline_code='$airline' && dep_date > '$endoftoday' order by route asc"); 
	$rowcount_orderinfo = $results_lastorder->num_rows;
	if($rowcount_orderinfo > 0)
		{
			foreach($results_lastorder as $row_last_order){   
			   $array_dates[] = $row_last_order->dep_date;
			}
		}	
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
}
?>