<?php
require_once ('../../../../wp-config.php');
include('../../../../wp-config-custom.php');
include "tpl_contralized_functions.php";

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
	if(isset($_GET["airline"]) && $_GET["airline"] != '')
    {
	    $airline = "airline_code = '".$_GET['airline']."'";
    }
    else
    {
        $airline = "airline_code != ''";
    }
	$route=$_GET["route"];
	$array_dates = array();
	$results_lastorder = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where $airline && dep_date > '$endoftoday' order by route asc"); 
	$rowcount_orderinfo = count($results_lastorder);
	if($rowcount_orderinfo > 0)
		{
			foreach($results_lastorder as $row_last_order){   
			   $array_dates[] = $row_last_order->dep_date;
			}
		}	
    $dropdown = "";
    echo $dropdown;
	
}
if(isset($_GET["routeforprice"]) && $_GET["routeforprice"] != '' && $_GET["routeforprice"] != 'null')
{
	$route=$_GET["routeforprice"];
	$array_route_code = array();
	$results_lastorder = $wpdb->get_results( "SELECT DISTINCT(price.sale_price) as sale_price FROM wpk4_wt_price_category_relation price 
    JOIN wpk4_backend_stock_product_manager stock 
        ON trip_code LIKE '$route%' and date(stock.travel_date) >= CURRENT_DATE AND stock.pricing_id = price.pricing_id 
    WHERE price.pricing_category_id = '954' AND price.sale_price != '0' ORDER BY CAST(price.sale_price AS UNSIGNED) ASC;
    "); 
	$rowcount_orderinfo = 0;	
		foreach($results_lastorder as $row_last_order){ 
			$rowcount_orderinfo++;
			$array_route_code[] = $row_last_order->sale_price; // last order id
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
	
	$reldate1 = substr($datefrom, 0, 10).' 00:00:00';
	$reldate2 = substr($dateto, 25, 10).' 23:59:59';
	
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
	$results_trips = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc"); 
	$rowcount_total_rows = $wpdb->get_var("SELECT COUNT(*) FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc");
    
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
	   
	   $trip_product_id = '';
	   $tour_name__trip_code = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager'"); 
       foreach($tour_name__trip_code as $row_2){ 
            $trip_product_id = $row_2->product_id;
			$trip_itinerary = $row_2->itinerary;
			//echo $trip_id . ' - ' . $dep_date;
	  }
	   
	   $current_stock_total = 0;
	   //echo $trip_id . $dep_date_for_product_manager;
	   	 $results_trips_new_total = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_management_sheet where trip_id ='$trip_id' && dep_date LIKE '$dep_date_for_product_manager%' order by dep_date asc");
	   	 foreach($results_trips_new_total as $row_all_trips_new_total)
	        { 
	            //echo $row_all_trips_new_total->dep_date.'</br>';
	            //echo $row_all_trips_new_total->current_stock.'</br>';
	            $current_stock_total = $current_stock_total + (int)$row_all_trips_new_total->current_stock;
	        }

	   
		$results_orderinfo = $wpdb->get_results( "SELECT * FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date' && (payment_status = 'paid' || payment_status = 'partially_paid')"); 
		$order_count = 0;
		$pax_count = 0;
		$product_id_itinerary = $trip_product_id;
		foreach($results_orderinfo as $row_order_rows)
		{
		    //echo $dep_date . ' -> ' ; 
		    //echo $row_order_rows->total_pax . '</br>';
			$pax_count += (int)$row_order_rows->total_pax;
			$order_count++;
		}

		$traveldate_fxed = $dep_date;
		//echo $current_stock_total . ' - ' . $pax_count;
		$remainingseats = (int)$current_stock_total - (int)$pax_count;
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
			
		// dummy value starts
		$trip_pricing_id = '123456789987654321';
		$product_title_drived = '';
		$trip_adult_rate = '';
		$trip_child_rate = '';
		// dummy value ends
		
		$results_productinfomation = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_changed_with_0'"); 
		foreach($results_productinfomation as $row_productinfomation)
		{
			$trip_pricing_id = $row_productinfomation->pricing_id;
		}
		
		    $results_adult_rate = $wpdb->get_results( "SELECT * FROM wpk4_wt_price_category_relation where pricing_id='$trip_pricing_id' && pricing_category_id='954'"); 
    		foreach($results_adult_rate as $row_adult_rate)
    		{
    			$trip_adult_rate = $row_adult_rate->sale_price; // adult rate
    		}
		
		    $results_child_rate = $wpdb->get_results( "SELECT * FROM wpk4_wt_price_category_relation where pricing_id='$trip_pricing_id' && pricing_category_id='953'"); 
    		foreach($results_child_rate as $row_child_rate)
    		{
    			$trip_child_rate = $row_child_rate->sale_price; // child rate
    		}
    		
    	if($_GET["saleprice"] != '' && $_GET["saleprice"] != 'NULL' && $_GET["saleprice"] != 'null')
    	{
    	    //echo $trip_adult_rate .' != '. $saleprice.'</br>';
    	    if($trip_adult_rate != $saleprice)
    	    {
    	        continue;
    	    }
    	}	
		
		$results_productinfomation = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_changed_with_0'"); 
		foreach($results_productinfomation as $row_productinfomation)
		{
			$product_title_drived = $row_productinfomation->product_title;
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