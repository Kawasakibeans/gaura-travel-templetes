<?php
require_once ('../../../../wp-config.php');
require_once ('../../../../wp-config-custom.php');
date_default_timezone_set("Australia/Melbourne"); 
global $wpdb;
global $current_user;
$currnt_userlogn = $current_user->user_login;
$currentdate = date("Y-m-d H:i:s");

if (isset($_POST["req_type"]) && $_POST['req_type'] == 'incentive_month') {
    $date = $_POST["month"]; // Assuming this is in 'YYYY-MM' format
    $gathered_dates = [];

    // Fetch rows where the date range intersects with the specified month
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT start_date, end_date 
            FROM wpk4_agent_data_incentive_conditions 
            WHERE DATE_FORMAT(start_date, '%%Y-%%m') <= %s 
              AND DATE_FORMAT(end_date, '%%Y-%%m') >= %s
            ORDER BY start_date ASC",
            $date,
            $date
        )
    );

    foreach ($results as $row) {
        $current = new DateTime($row->start_date);
        $end_date = new DateTime($row->end_date);
        // Loop from start_date to end_date
        while ($current <= $end_date) {
            $month_year = $current->format('Y-m');
            // Check if the current date's month and year matches the input month and year
            if ($month_year == $date) {
                $gathered_dates[$current->format('Y-m-d')] = true; // Use date as key
            }
            $current->modify('+1 day'); // Increment date by 1
        }
    }

    // Convert keys back to array
    $unique_dates = array_keys($gathered_dates);
    echo json_encode(array("dates" => $unique_dates)); // Send as "dates"
}

if (isset($_POST["req_type"]) && $_POST['req_type'] == 'movements_get_price_per_person') {

    $product_id = $_POST["pricing_id"];
    $amount = 0;
    
    $results_product_id = $wpdb->get_results( "SELECT regular_price FROM wpk4_wt_price_category_relation where pricing_id = '$product_id' AND pricing_category_id = 953"); 
		foreach($results_product_id as $row_product_id){ 
			$amount = $row_product_id->regular_price;
		}
		
        
    echo json_encode(["amount" => $amount]);
    exit;
}

if(isset($_POST["req_type"]) && $_POST['req_type'] == 'movements_getproductid' )
{
    $date = date('Y-m-d', strtotime($_POST["date"]));
    $tripcode = $_POST["tripcode"];
    $gathered_product_id = 0;
    $gathered_product_title = "";
    $results_product_id = $wpdb->get_results( "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$tripcode' AND date(travel_date) = '$date'"); 
		foreach($results_product_id as $row_product_id){ 
			$gathered_product_id = $row_product_id->product_id;
			$gathered_product_title = $row_product_id->product_title;
			$gathered_pricing_id = $row_product_id->pricing_id;
		}
	$pnr = '';
	$results_product_id2 = $wpdb->get_results( "SELECT pnr FROM wpk4_backend_stock_management_sheet where trip_id='$tripcode' AND date(dep_date) = '$date'"); 
		foreach($results_product_id2 as $row_product_i2d){ 
			$pnr = $row_product_i2d->pnr;
		}
		
	echo json_encode(array("id" => $gathered_product_id, "title" => $gathered_product_title, "pricingid" => $gathered_pricing_id, "pnr" => $pnr));
    //echo $gathered_product_id;
}

if (
    isset($_POST["get_paid_amount_for_adjustment_cs_g360"]) &&
    $_POST['get_paid_amount_for_adjustment_cs_g360'] == 'amount_adjustment'
) {
    if (!empty($_POST['old_order_id'])) {
        global $wpdb;

        $old_order_id = $_POST['old_order_id'];
        $totalamountpaid = 0;
        $payment_status = '';
        $total_amount = 0;

        $results_booking = $wpdb->get_results(
            $wpdb->prepare("SELECT payment_status, total_amount FROM wpk4_backend_travel_bookings WHERE order_id = %d", $old_order_id)
        );

        foreach ($results_booking as $row_booking) {
            $payment_status = $row_booking->payment_status;
            $total_amount = (float) $row_booking->total_amount;
        }

        $results_payments = $wpdb->get_results(
            $wpdb->prepare("SELECT trams_received_amount FROM wpk4_backend_travel_payment_history WHERE order_id = %d", $old_order_id)
        );

        foreach ($results_payments as $row_payments) {
            $totalamountpaid += (float) $row_payments->trams_received_amount;
        }

        if ($payment_status === 'paid' && $total_amount > 0) {
            $overpaid = $totalamountpaid - $total_amount;
            echo json_encode($overpaid);
        } elseif (in_array($payment_status, ['partially_paid', 'canceled'])) {
            echo json_encode($totalamountpaid);
        } else {
            echo json_encode("0");
        }
    } else {
        echo json_encode("Invalid order ID");
    }
    exit;
}

if(isset($_POST["get_paid_amount_for_adjustment"]) && $_POST['get_paid_amount_for_adjustment'] == 'amount_adjustment' )
{
    if (isset($_POST['old_order_id']) && $_POST['old_order_id'] != '') 
    {
        $old_order_id = $_POST['old_order_id'];
        $totalamountpaid = 0;
        $results_payments = $wpdb->get_results( "SELECT * FROM wpk4_backend_travel_payment_history where order_id='$old_order_id'"); 
    	foreach($results_payments as $row_payments) { 
    		$totalamountpaid += $row_payments->trams_received_amount;
    	}
    	echo $totalamountpaid;
    }
    else
    {
        echo '0';
    }
}

if(isset($_POST["get_paid_amount_for_adjustment"]) && $_POST['get_paid_amount_for_adjustment'] == 'deposit_amount_adjustment_from_customerportal' )
{
    if (isset($_POST['old_order_id']) && $_POST['old_order_id'] != '') 
    {
        $old_order_id = $_POST['old_order_id'];
        $totalamountpaid = 0;
        $results_payments = $wpdb->get_results( "SELECT * FROM wpk4_backend_travel_payment_history where order_id='$old_order_id' and payment_change_deadline > NOW()"); 
    	foreach($results_payments as $row_payments) { 
    		$totalamountpaid += $row_payments->trams_received_amount;
    	}
    	echo $totalamountpaid;
    }
    else
    {
        echo '0';
    }
}

if(isset($_POST["get_paid_amount_for_adjustment2"]) && $_POST['get_paid_amount_for_adjustment2'] == 'amount_adjustment2' )
{
    if (isset($_POST['old_order_id2']) && $_POST['old_order_id2'] != '') 
    {
        $old_order_id = $_POST['old_order_id2'];
        $totalamountpaid = 0;
        $results_payments = $wpdb->get_results( "SELECT * FROM wpk4_backend_travel_payment_history where order_id='$old_order_id'"); 
    	foreach($results_payments as $row_payments) { 
    		$totalamountpaid += $row_payments->trams_received_amount;
    	}
    	echo $totalamountpaid;
    }
    else
    {
        echo '0';
    }
}



if (isset($_POST['ticketing_g360_notes_submission']) && $_POST['ticketing_g360_notes_submission'] == '1') 
            		{
            		    header('Content-Type: application/json');
            		    
            		    //echo 'success';
            		    $product_id_api = $_POST['product_id_api'];
            		    $co_order_id_api = $_POST['co_order_id_api'];
            		    $order_id_api = $_POST['order_id_api'];
            		    
                        $categoryofnote = $_POST['categoryofnote'];
                        $notedescription = $_POST['notedescription'];
                        $department = $_POST['department'];
                        $current_date_time = date("Y-m-d H:i:s");
                    
                        if (isset($_GET['nobel']) && $_GET['nobel'] == '1') {
                            $note_column = 'Noble';
                        } else {
                            $note_column = '';
                        }

                		$insert_query = "INSERT INTO wpk4_backend_history_of_updates (`type_id`, `meta_key`, `meta_value`, `additional_note`, `updated_by`, `updated_on`) 
                		VALUES ('$order_id_api', 'Booking Note Category', '$categoryofnote', '$note_column', '$currnt_userlogn', '$current_date_time')";
                		$result = mysqli_query($mysqli, $insert_query);
                		
                		$insert_query = "INSERT INTO wpk4_backend_history_of_updates (`type_id`, `meta_key`, `meta_value`, `additional_note`, `updated_by`, `updated_on`) 
                		VALUES ('$order_id_api', 'Booking Note Description', '$notedescription', '$note_column', '$currnt_userlogn', '$current_date_time')";
                		$result = mysqli_query($mysqli, $insert_query);
                		
                		$insert_query = "INSERT INTO wpk4_backend_history_of_updates (`type_id`, `meta_key`, `meta_value`, `additional_note`, `updated_by`, `updated_on`) 
                		VALUES ('$order_id_api', 'Booking Note Department', '$department', '$note_column', '$currnt_userlogn', '$current_date_time')";
                		$result = mysqli_query($mysqli, $insert_query);
                		
                		$insert_query = "INSERT INTO wpk4_backend_travel_booking_update_history (`order_id`, `co_order_id`, `merging_id`, `meta_key`, `meta_value`, `meta_key_data`, `updated_time`, `updated_user`) 
                		VALUES ('$order_id_api', '$co_order_id_api', '$product_id_api', 'G360Events', 'Notes submitted', '$categoryofnote', '$current_date_time', '$currnt_userlogn')";
                		$result = mysqli_query($mysqli, $insert_query);
		
                		if (!$result) {
                          //echo mysqli_error($mysqli);
                        }
                        else
                        {
                            echo json_encode(['status' => 'success']);
                        }
                    
                        exit;
                    }
                    
                  
if (isset($_POST['ticketing_g360_escalation_submission']) && $_POST['ticketing_g360_escalation_submission'] == '1') {
    header('Content-Type: application/json');

    $response = ['status' => 'error', 'message' => ''];

    $escalation_type = $_POST['escalation_type'] ?? '';
    $input_note = $_POST['input_note'] ?? '';
    $escalation_to = $_POST['escalation_to'] ?? '';
    $followup_date = $_POST['followup_date'] ?? '';
    $airline = $_POST['airline'] ?? '';
    $fare_difference = $_POST['fare_difference'] ?? '';
    $new_option = $_POST['new_option'] ?? '';
    $other_note = $_POST['other_note'] ?? '';

    // You should securely set or pull these from session/context
    $order_id_api = $_POST['order_id_api'] ?? '';

    if ($escalation_type == '') {
        $response['message'] = 'Kindly add the escalation details.';
        echo json_encode($response);
        exit;
    }

    $uploadDirectory = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/customized_function_uploads/";
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $fileName = '';
    $fileName_2 = '';
    $allowed = ['jpeg', 'jpg', 'png', 'pdf'];

    // Handle existing_pnr_screenshot
    if (isset($_FILES['existing_pnr_screenshot']) && $_FILES['existing_pnr_screenshot']['error'] === UPLOAD_ERR_OK) {
        $temp = explode(".", $_FILES['existing_pnr_screenshot']['name']);
        $ext = strtolower(end($temp));
        if (in_array($ext, $allowed) && $_FILES['existing_pnr_screenshot']['size'] <= 4000000) {
            $filename_time = date('ymdHis') . '_' . uniqid();
            $existingfilename = 'g360_escalation_' . $filename_time . '.' . $ext;
            $uploadPath = $uploadDirectory . $existingfilename;
            if (move_uploaded_file($_FILES['existing_pnr_screenshot']['tmp_name'], $uploadPath)) {
                $fileName = $existingfilename;
            }
        }
    }

    // Handle new_option_screenshot
    if (isset($_FILES['new_option_screenshot']) && $_FILES['new_option_screenshot']['error'] === UPLOAD_ERR_OK) {
        $temp = explode(".", $_FILES['new_option_screenshot']['name']);
        $ext = strtolower(end($temp));
        if (in_array($ext, $allowed) && $_FILES['new_option_screenshot']['size'] <= 4000000) {
            $filename_time = date('ymdHis') . '_' . uniqid();
            $existingfilename = 'g360_escalation_' . $filename_time . '.' . $ext;
            $uploadPath = $uploadDirectory . $existingfilename;
            if (move_uploaded_file($_FILES['new_option_screenshot']['tmp_name'], $uploadPath)) {
                $fileName_2 = $existingfilename;
            }
        }
    }

    // Insert into database
    $stmt = $mysqli->prepare("INSERT INTO wpk4_backend_travel_escalations (order_id, escalation_type, note, status, escalate_to, escalated_by, followup_date, airline, fare_difference, new_option, existing_pnr_screenshot, new_option_screenshot, other_note) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssssssssss', $order_id_api, $escalation_type, $input_note, $escalation_to, $currnt_userlogn, $followup_date, $airline, $fare_difference, $new_option, $fileName, $fileName_2, $other_note);

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Escalation saved successfully.';

        if ($currnt_userlogn === 'sriharshans') {
            include(get_template_directory() . '/templates/email/tpl_email_escalation_submission.php');
        }
    } else {
        $response['message'] = 'Database error: ' . $mysqli->error;
    }

    echo json_encode($response);
    exit;
}


if (isset($_POST['ticketing_g360_ticketing_submission']) && $_POST['ticketing_g360_ticketing_submission'] == '1') {
    //header('Content-Type: application/json');

    $response = ['status' => 'error', 'message' => '', 'reload' => false];

    $admin_email = "sriharshans@gauratravel.com.au";
    $mailbody = "<html><body><table border='1' cellspacing='0' cellpadding='5'>";
    $has_updates = false;
    $mail_entries = [];

    if (isset($_POST['name_replacement']) && is_array($_POST['name_replacement'])) {
        foreach ($_POST['name_replacement'] as $auto_id => $new_name) {
            if ($new_name != '') {
                $ticketing_request = $_POST['ticketing_request'][$auto_id] ?? '';

                $trip_query = "SELECT b.trip_code, p.pnr, p.fname AS old_fname, p.lname AS old_lname, b.travel_date, p.salutation AS old_salutation
                               FROM wpk4_backend_travel_bookings AS b
                               JOIN wpk4_backend_travel_booking_pax AS p ON b.order_id = p.order_id
                               WHERE p.auto_id = '$auto_id'";
                $trip_result = mysqli_query($mysqli, $trip_query);
                if (!$trip_result || mysqli_num_rows($trip_result) === 0) {
                    continue; // skip if no data
                }

                $trip_row = mysqli_fetch_assoc($trip_result);
                $trip_code = $trip_row['trip_code'];
                $order_id_api = $trip_row['order_id'];
                $pnr = $trip_row['pnr'];
                $old_name = $trip_row['old_lname'] . ' / ' . $trip_row['old_fname'] . ' / ' . $trip_row['old_salutation'];
                $travel_date = date('d/m/Y', strtotime($trip_row['travel_date']));
                $trip_parts = explode('-', $trip_code);
                $airline = substr($trip_parts[count($trip_parts) - 1], 0, 2);

                $updated_status = $ticketing_request;
                if (stripos($ticketing_request, 'completed') !== false) {
                    $new_name = '';
                }

                $update_query = "UPDATE wpk4_backend_travel_booking_pax 
                                 SET name_updated = '$updated_status', ticketing_remarks = '$new_name'
                                 WHERE auto_id = '$auto_id'";
                if (mysqli_query($mysqli, $update_query)) {
                    $has_updates = true;

                    $history_query = "INSERT INTO wpk4_edit_history (order_id, airline, name, objective, status, trip_code, pnr) 
                                      VALUES ('$order_id_api', '$airline', '$new_name', '$ticketing_request', '$updated_status', '$trip_code', '$pnr')";
                    mysqli_query($mysqli, $history_query);

                    $mailbody .= "<tr><td>$new_name</td><td>$pnr</td><td>$old_name</td><td>$trip_code</td><td>$travel_date</td><td>$ticketing_request</td></tr>";

                    $mail_entries[] = [
                        'pnr' => $pnr,
                        'action_required' => $ticketing_request,
                        'airline_code' => $airline
                    ];
                }
            }
        }
    } else {
        $response['message'] = 'No data of name_replacement sent!';
        echo json_encode($response);
        exit;
    }

    $mailbody .= "</table></body></html>";
    ob_start();
    $response['status'] = 'success';
                $response['message'] = 'Ticketing request updated and email sent successfully.';
                $response['reload'] = true;

    
//$response = ['status' => 'test', 'message' => 'Simple test OK'];
ob_end_clean();
echo json_encode($response);
exit;

}





if (isset($_POST['action']) && $_POST['action'] === 'update_pax_field') {

    $auto_id = intval($_POST['auto_id']);
    $column = sanitize_key($_POST['column']); // keep this to avoid SQL injection
    $value = sanitize_text_field($_POST['value']);
    

    $table = $wpdb->prefix . 'backend_travel_booking_pax';

    // Dynamically build the update query with the received column
    $updated = $wpdb->update(
        $table,
        [$column => $value],
        ['auto_id' => $auto_id],
        ['%s'],
        ['%d']
    );
    
    $table_name_history = $wpdb->prefix . 'backend_travel_booking_pax_ticketing_update';

    $insert_result = $wpdb->insert($table_name_history, [
        'pax_id'      => $auto_id,  
        'column_name' => $column,
        'field_value' => $value,
        'updated_by'  => $currnt_userlogn,
    ]);
    
    
    
    
							
	$query_order_id = "SELECT order_id, product_id, co_order_id, pax_status FROM wpk4_backend_travel_booking_pax where auto_id='$auto_id' ";
    $result_order_id = mysqli_query($mysqli, $query_order_id);	
    $row_order_id = mysqli_fetch_assoc($result_order_id);
    $order_id = $row_order_id['order_id'];
    $product_id = $row_order_id['product_id'];
    $co_order_id = $row_order_id['co_order_id'];
    $pax_status_txt = $row_order_id['pax_status'];
    $pax_status_check = $row_order_id['pax_status'];
		
		if($column == 'pnr' && $value == '')
	{
		$pax_status_txt = 'Name Updated'; 
	}								
										if($column == 'name_audit' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        name_audit_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Name Audit','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										if($column == 'ticketed_by' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        ticketed_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Ticketed by','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										if($column == 'ticketing_remarks_by' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        ticketing_remarks_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Ticketing Remark','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										if($column == 'ticketing_audit' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        ticketing_audit_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Ticket Audit','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										if($column == 'name_update_check' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        name_update_check_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Name Update Check','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										if($column == 'ticketing_in_progress_by' && $value != '')
										{
										    $sql_update_status_audit = "UPDATE wpk4_backend_travel_booking_pax SET 
										        ticketing_in_progress_on ='$currentdate' 
    										    WHERE auto_id='$auto_id'";
										    $result_status_audit = mysqli_query($mysqli,$sql_update_status_audit);
										    
										    mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										    values ('$order_id','$product_id','$auto_id','Ticketing In Progress','Yes','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
										}
										
										$pax_status_txt_new = $pax_status_txt;
										if(($column == 'name_updated' && $value == 'name removal requested') || ($column == 'name_updated' && $value == 'name removal completed'))
										{
											$pax_status_txt_new = 'Name removal'; 
										}
										if(($column == 'name_updated' && $value == 'Name updated'))
										{
											$pax_status_txt_new = 'name updated'; 
										}
										if(($column == 'name_updated' && $value == 'escalated to HO') || ($column == 'name_updated' && $value == 'name removal sent') || ($column == 'name_updated' && $value == 'escalation case resolved') || ($column == 'name_updated' && $value == 'Do not issue ticket'))
										{
											$pax_status_txt_new = $pax_status_txt; 
										}
										
									if($column == 'ticket_number' && $value != '')
										{
										    $pax_status_txt_new = 'Ticketed'; 
										}
										
									if($pax_status_check != $pax_status_txt_new)
									{	
										
										$sql_update_status_2 = "UPDATE wpk4_backend_travel_booking_pax SET pax_status='$pax_status_txt_new' WHERE auto_id='$auto_id'";
										$result_status_2 = mysqli_query($mysqli,$sql_update_status_2);
										
										mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,co_order_id, merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
										values ('$order_id','$co_order_id','$product_id','$auto_id','pnr_status','$pax_status_txt_new','$currentdate','$currnt_userlogn')") or die(mysqli_error($mysqli));
									}
    
    

    if ($updated !== false) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed or no change']);
    }
    exit;
}


// FIT Itinerary for DC in Customer Portal
if (isset($_POST['action']) && $_POST['action'] === 'get_flight_numbers' || $_POST['action'] === 'get_flight_details' || $_POST['action'] === 'get_flight_details_by_date'
|| $_POST['action'] === 'get_origins_by_airline' || $_POST['action'] === 'get_destinations_by_airline_origin' || $_POST['action'] === 'get_flights_by_route' )
{
    $action = $_POST['action'];
    if ($action === 'get_flight_numbers') {
        $airline_code = sanitize_text_field($_POST['airline_code'] ?? '');
    
        $flights = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT Flight FROM wpk4_backend_FIT_flight_schedule WHERE Flight LIKE %s ORDER BY Flight",
            $wpdb->esc_like($airline_code) . '%'
        ));
    
        echo json_encode($flights);
        exit;
    }
    
    if ($action === 'get_flight_details') {
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');
    
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wpk4_backend_FIT_flight_schedule WHERE Flight = %s LIMIT 1",
            $flight_number
        ), ARRAY_A);
    
        $result = [
            'Origin'         => $row['Origin_Code'] ?? '',
            'Destination'    => $row['Destination_Code'] ?? '',
            'DepartureDate'  => $row['Dep_date'] ?? '',
            'DepartureTime'  => $row['Dep_Time'] ?? '',
            'DepartureTerminal'  => $row['Dep_Terminal'] ?? '',
            'ArrivalDate'    => $row['Arr_date'] ?? '',
            'ArrivalTime'    => $row['Arr_Time'] ?? '',
            'ArrivalTerminal'  => $row['Arr_Terminal'] ?? '',
            'EquipmentCode'  => $row['Equipment_Code'] ?? '',
            'EquipmentName'  => $row['Equipment_Name'] ?? '',
        ];
    
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'get_flight_details_by_date') {
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');
        $departure_date = sanitize_text_field($_POST['departure_date'] ?? '');
    
        // Make sure From and To are wrapped with backticks since they are MySQL reserved words
        $query = "
            SELECT * FROM wpk4_backend_FIT_flight_schedule 
            WHERE Flight = %s AND %s BETWEEN `From` AND `To` 
            LIMIT 1
        ";
    
        $row = $wpdb->get_row($wpdb->prepare($query, $flight_number, $departure_date), ARRAY_A);
    
        $result = [
            'Origin'         => $row['Origin_Code'] ?? '',
            'Destination'    => $row['Destination_Code'] ?? '',
            'DepartureDate'  => $row['Dep_date'] ?? '',
            'DepartureTime'  => $row['Dep_Time'] ?? '',
            'DepartureTerminal'  => $row['Dep_Terminal'] ?? '',
            'ArrivalDate'    => $row['Arr_date'] ?? '',
            'ArrivalTime'    => $row['Arr_Time'] ?? '',
            'ArrivalTerminal'  => $row['Arr_Terminal'] ?? '',
            'EquipmentCode'  => $row['Equipment_Code'] ?? '',
            'EquipmentName'  => $row['Equipment_Name'] ?? '',
        ];
    
        echo json_encode($result);
        exit;
    }
    
    // Get origin list based on airline
    if ($_POST['action'] === 'get_origins_by_airline') {
        $airline_code = sanitize_text_field($_POST['airline_code']);
        $origins = $wpdb->get_col("
            SELECT DISTINCT Origin_Code FROM wpk4_backend_FIT_flight_schedule 
            WHERE LEFT(Flight, 2) = '$airline_code' ORDER BY Origin_Code
        ");
        echo json_encode($origins);
        exit;
    }
    
    // Get destinations by airline + origin
    if ($_POST['action'] === 'get_destinations_by_airline_origin') {
        $airline = sanitize_text_field($_POST['airline_code']);
        $origin = sanitize_text_field($_POST['origin']);
        $destinations = $wpdb->get_col("
            SELECT DISTINCT Destination_Code FROM wpk4_backend_FIT_flight_schedule 
            WHERE LEFT(Flight, 2) = '$airline' AND Origin_Code = '$origin' ORDER BY Destination_Code
        ");
        echo json_encode($destinations);
        exit;
    }
    
    // Get flights by airline + origin + destination
    if ($_POST['action'] === 'get_flights_by_route') {
        $airline = sanitize_text_field($_POST['airline_code']);
        $origin = sanitize_text_field($_POST['origin']);
        $destination = sanitize_text_field($_POST['destination']);
        $flights = $wpdb->get_results("
            SELECT * FROM wpk4_backend_FIT_flight_schedule 
            WHERE LEFT(Flight, 2) = '$airline' AND Origin_Code = '$origin' AND Destination_Code = '$destination'
        ", ARRAY_A);
        echo json_encode($flights);
        exit;
    }


}