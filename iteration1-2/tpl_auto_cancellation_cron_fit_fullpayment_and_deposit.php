<?php
date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Create the POST data string

$mysqli = new mysqli('localhost', 'gaurat_sriharan', 'r)?2lc^Q0cAE', 'gaurat_gauratravel');
//$mysqli = new mysqli("localhost","staginggauratr_usr_stag","yBvvnZ@Cvw8","staginggauratr_gau_stag");

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

//fit fullpayment
function availability_pax_update_ajax($order_id, $by_user)
{
	global $mysqli;
	$current_date_ymd = date("Y-m-d H:i:s");
	$query_select_booking = "SELECT order_id, trip_code, travel_date, total_pax FROM wpk4_backend_travel_bookings where order_type != 'gds' AND order_id='$order_id' ";
	$result_select_booking = mysqli_query($mysqli, $query_select_booking);
	while($row_select_booking = mysqli_fetch_assoc($result_select_booking))
	{
		$post_trip_code = $row_select_booking['trip_code'];
		$post_travel_date_int = $row_select_booking['travel_date'];
		$post_travel_date_int = date('Y-m-d', strtotime($post_travel_date_int));
		$post_pax = $row_select_booking['total_pax'];
				
		$query_get_current_availability_pax = "SELECT pax, pricing_id FROM wpk4_backend_manage_seat_availability where trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
		$result_get_current_availability_pax = mysqli_query($mysqli, $query_get_current_availability_pax);
		if(mysqli_num_rows($result_get_current_availability_pax) > 0)
		{
    		$row_get_current_availability_pax = mysqli_fetch_assoc($result_get_current_availability_pax); 
    		
    		$current_availability_pax = $row_get_current_availability_pax['pax'];
    		$current_availability_pricing_id = $row_get_current_availability_pax['pricing_id'];
    						
    		$new_availability_pax = $current_availability_pax - $post_pax; // reducing the pax count as the booking is being cancelled
    						
    		$updated_by_username = 'auto_cancellation_cron';
    		
    		$current_date_ymd_for_checkup = date('Y-m-d', strtotime($current_date_ymd));
    		$query_get_current_availability_log_check = "SELECT * FROM wpk4_backend_manage_seat_availability_log where pricing_id = '$current_availability_pricing_id' AND updated_by = '$by_user' AND order_id = '$order_id' AND date(updated_on) = '$current_date_ymd_for_checkup'";
    		$result_get_current_availability_log_check = mysqli_query($mysqli, $query_get_current_availability_log_check);
    		if(mysqli_num_rows($result_get_current_availability_log_check) == 0)
    		{
    		    
    		    $sql_update_current_availability_pax = "UPDATE wpk4_backend_manage_seat_availability 
    					SET pax = '$new_availability_pax', pax_updated_by = '$by_user', pax_updated_on = '$current_date_ymd'
    					WHERE trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
        		$result_update_current_availability_pax = mysqli_query($mysqli, $sql_update_current_availability_pax);
        		echo $sql_update_current_availability_pax.'</br>';
        		$post_pax2 = '-'.$post_pax;
        		
        		if (isset($result_update_current_availability_pax) && $result_update_current_availability_pax)
                {
        		    mysqli_query($mysqli, "insert into wpk4_backend_manage_seat_availability_log (pricing_id, original_pax, new_pax, updated_on, updated_by, order_id, changed_pax_count) 
                    values ('$current_availability_pricing_id','$current_availability_pax','$new_availability_pax','$current_date_ymd','$by_user', '$order_id', '$post_pax2')") or die(mysqli_error($mysqli));
                }
    		    
    		}
		
    		
		}
		else
		{
		    error_log( "Query params not found - tpl_auto_cancellation_cron_v2_midnight.php - ". $query_get_current_availability_pax );
		}
	}
}

//deposit
function availability_pax_update_ajax2($order_id, $by_user)
{
	global $mysqli;
	$current_date_ymd = date("Y-m-d H:i:s");
	$query_select_booking = "SELECT order_id, trip_code, travel_date, total_pax FROM wpk4_backend_travel_bookings where order_type != 'gds' AND order_id='$order_id' ";
	$result_select_booking = mysqli_query($mysqli, $query_select_booking);
	while($row_select_booking = mysqli_fetch_assoc($result_select_booking))
	{
		$post_trip_code = $row_select_booking['trip_code'];
		$post_travel_date_int = $row_select_booking['travel_date'];
		$post_travel_date_int = date('Y-m-d', strtotime($post_travel_date_int));
		$post_pax = $row_select_booking['total_pax'];
				
		$query_get_current_availability_pax = "SELECT pax, pricing_id FROM wpk4_backend_manage_seat_availability where trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
		$result_get_current_availability_pax = mysqli_query($mysqli, $query_get_current_availability_pax);
		if(mysqli_num_rows($result_get_current_availability_pax) > 0)
		{
    		$row_get_current_availability_pax = mysqli_fetch_assoc($result_get_current_availability_pax); 
    		
    		$current_availability_pax = $row_get_current_availability_pax['pax'];
    		$current_availability_pricing_id = $row_get_current_availability_pax['pricing_id'];
    						
    		$new_availability_pax = $current_availability_pax - $post_pax; // reducing the pax count as the booking is being cancelled
    						
    		$updated_by_username = 'auto_cancellation_cron';
    						
    		$current_date_ymd_for_checkup = date('Y-m-d', strtotime($current_date_ymd));
    		$query_get_current_availability_log_check = "SELECT * FROM wpk4_backend_manage_seat_availability_log where pricing_id = '$current_availability_pricing_id' AND updated_by = '$by_user' AND order_id = '$order_id' AND date(updated_on) = '$current_date_ymd_for_checkup'";
    		$result_get_current_availability_log_check = mysqli_query($mysqli, $query_get_current_availability_log_check);
    		if(mysqli_num_rows($result_get_current_availability_log_check) == 0)
    		{
    		    
    		    $sql_update_current_availability_pax = "UPDATE wpk4_backend_manage_seat_availability 
    					SET pax = '$new_availability_pax', pax_updated_by = '$by_user', pax_updated_on = '$current_date_ymd'
    					WHERE trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
        		$result_update_current_availability_pax = mysqli_query($mysqli, $sql_update_current_availability_pax);
        		echo $sql_update_current_availability_pax.'</br>';
        		$post_pax2 = '-'.$post_pax;
        		
        		if (isset($result_update_current_availability_pax) && $result_update_current_availability_pax)
                {
        		    mysqli_query($mysqli, "insert into wpk4_backend_manage_seat_availability_log (pricing_id, original_pax, new_pax, updated_on, updated_by, order_id, changed_pax_count) 
                    values ('$current_availability_pricing_id','$current_availability_pax','$new_availability_pax','$current_date_ymd','$by_user', '$order_id', '$post_pax2')") or die(mysqli_error($mysqli));
                }
    		    
    		}
		}
		else
		{
		    error_log( "Query params not found - tpl_auto_cancellation_cron_every_30_mins.php - ". $query_get_current_availability_pax );
		}
	}
}

$current_date_and_time = date("Y-m-d H:i:s");
        
        /* FIT cancellation if no payment received after 25 hours starts. */
        $query = "SELECT distinct
    b.order_id,
    b.order_date,
    b.total_amount,
    COALESCE(ph.total_received, 0) as payment,
    b.payment_status
FROM
    wpk4_backend_travel_bookings b
LEFT JOIN (
    SELECT
        order_id,
        SUM(trams_received_amount) AS total_received
    FROM
        wpk4_backend_travel_payment_history
    GROUP BY
        order_id
) ph ON b.order_id = ph.order_id
WHERE
    b.full_payment_deadline <= NOW()
    AND b.order_type IN ('gds')
    AND b.payment_status = 'partially_paid'
    AND (
        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
        OR b.sub_payment_status IS NULL
    )
    AND COALESCE(ph.total_received, 0) <> b.total_amount;
        ";
        
        echo $query;
        
        $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
        $row_counter = mysqli_num_rows($result);
        $processedOrders = array();	
        $orderIDs = array();
        echo '</br></br>Cancellation for FIT - Full amount based</br></br>';
        echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Payment Status</th><th>New Payment Status</th></tr>';
        while ($row = mysqli_fetch_assoc($result)) {
            $order_id = $row['order_id'];
            if (in_array($order_id, $processedOrders)) {
                continue; // Skip duplicate orders
            }
            $processedOrders[] = $order_id;
            
            $total_to_be_paid = '';
            $query_total_amount = "SELECT total_amount FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
            $result_total_amount = mysqli_query($mysqli, $query_total_amount) or die(mysqli_error($mysqli));
            if(mysqli_num_rows($result_total_amount) > 0)
            {
                $row_total_amount = mysqli_fetch_assoc($result_total_amount);
                $total_to_be_paid = number_format((float)$row_total_amount['total_amount'], 2, '.', '');
            }
            
            $is_already_stock_changed = 0;
            $query_status_duplicate = "SELECT auto_id as deposit_amount FROM wpk4_backend_travel_bookings where order_id = '$order_id' and payment_modified_by = 'cancel_duplicate_in_checkout'";
            $result_status_duplicate = mysqli_query($mysqli, $query_status_duplicate) or die(mysqli_error($mysqli));
            if(mysqli_num_rows($result_status_duplicate) > 0)
            {
                $is_already_stock_changed = 1;
            }
                    
            
            $get_paid_amount = 0.00;
            $query_status_payment_deposit = "SELECT sum(trams_received_amount) as deposit_amount FROM wpk4_backend_travel_payment_history where order_id = '$order_id' and CAST(trams_received_amount AS DECIMAL(10,2)) != '0.00' ";
            $result_status_payment_deposit = mysqli_query($mysqli, $query_status_payment_deposit) or die(mysqli_error($mysqli));
            if(mysqli_num_rows($result_status_payment_deposit) > 0)
            {
                $row_status_payment_deposit = mysqli_fetch_assoc($result_status_payment_deposit);
                $get_paid_amount = number_format((float)$row_status_payment_deposit['deposit_amount'], 2, '.', '');
            }
            
            if($total_to_be_paid > $get_paid_amount)
            {
                //if( $row['source'] != "import" )
                {
                    $current_email_date = date("Y-m-d H:i:s");
                    $by_user = 'fullpayment_deadline_cancellation';
                    
                    
                    $sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled', payment_modified = '$current_email_date', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
                    $result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
                	
                	echo $sql_update_status.'</br>';
                	mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, meta_key, meta_value, updated_time, updated_user) 
        			values ('$order_id', 'payment_status', 'canceled', '$current_email_date', '$by_user')") or die(mysqli_error($mysqli));
                    
                    if($is_already_stock_changed == 0)
                    {
                	    availability_pax_update_ajax($order_id, $by_user);
                    }
                    
                    
                    echo "<tr>
                        <td><input type='checkbox' class='order-checkbox' checked value='$order_id'></td>
                        <td><a href='/manage-wp-orders/?option=search&type=reference&id=".$row['order_id']."'>".$row['order_id']."</a>";
                            
                            echo "</td>
                        <td>".$row['order_date']."</td>
                        <td>".$total_to_be_paid . " - " .$get_paid_amount."</td>
                        <td>".$row['payment_status']."</td>
                        <td>cancel</td>
                    </tr>";
                }
            }
        }
        echo '</table>';
        

echo '</br>';


$current_date_and_time = date("Y-m-d H:i:s");

        $query = "SELECT
    b.order_id,
    b.order_date,
    b.deposit_deadline,
    COALESCE(ph.total_received, 0) as payment,
    b.payment_status
FROM
    wpk4_backend_travel_bookings b
LEFT JOIN (
    SELECT
        order_id,
        SUM(trams_received_amount) AS total_received
    FROM
        wpk4_backend_travel_payment_history
    GROUP BY
        order_id
) ph ON b.order_id = ph.order_id
WHERE
    b.deposit_deadline <= NOW()
    AND b.order_type IN ('gds', 'WPT')
    AND b.payment_status = 'partially_paid'
    AND (
        b.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
        OR b.sub_payment_status IS NULL
    )
    AND COALESCE(ph.total_received, 0) = 0;
";
    
        echo $query;
        $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
        $row_counter = mysqli_num_rows($result);
        $processedOrders = array();	
        $orderIDs = array();
        echo '</br></br>Cancellation for GDeals & FIT - Deposit date based';
        echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Payment Status</th><th>Status</th></tr>';
        while ($row = mysqli_fetch_assoc($result)) 
        {
            $order_id = $row['order_id'];
            if (in_array($order_id, $processedOrders)) 
            {
                continue; // Skip duplicate orders
            }
            $processedOrders[] = $order_id;
            
            $get_paid_amount = 0.00;
            $query_status_payment_deposit = "SELECT sum(trams_received_amount) as deposit_amount FROM wpk4_backend_travel_payment_history where order_id = '$order_id' and CAST(trams_received_amount AS DECIMAL(10,2)) != '0.00' ";
            $result_status_payment_deposit = mysqli_query($mysqli, $query_status_payment_deposit) or die(mysqli_error($mysqli));
            if(mysqli_num_rows($result_status_payment_deposit) > 0)
            {
                $row_status_payment_deposit = mysqli_fetch_assoc($result_status_payment_deposit);
                $get_paid_amount = number_format((float)$row_status_payment_deposit['deposit_amount'], 2, '.', '');
            }
            
            if($get_paid_amount == '0.00')
            {
                //if( $row['source'] != "import" )
                {
                    $by_user = 'deposit_deadline_cancellation';
                    
                    
                    $is_already_stock_changed = 0;
                    $query_status_duplicate = "SELECT auto_id as deposit_amount FROM wpk4_backend_travel_bookings where order_id = '$order_id' and payment_modified_by = 'cancel_duplicate_in_checkout'";
                    $result_status_duplicate = mysqli_query($mysqli, $query_status_duplicate) or die(mysqli_error($mysqli));
                    if(mysqli_num_rows($result_status_duplicate) > 0)
                    {
                        $is_already_stock_changed = 1;
                    }
            
            
                    $sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled', payment_modified = '$current_date_and_time', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
                	echo $sql_update_status.'</br>';
                	$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
                	
                	mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, meta_key, meta_value, updated_time, updated_user) 
        			values ('$order_id', 'payment_status', 'canceled', '$current_date_and_time', '$by_user')") or die(mysqli_error($mysqli));
                    
                    if($is_already_stock_changed == 0)
                    {
                        availability_pax_update_ajax2($order_id, $by_user);
                    }
                    
                    
                    echo '</br></br>';
                    echo "<tr>
                        <td><input type='checkbox' class='order-checkbox' checked value='$order_id'></td>
                        <td><a href='/manage-wp-orders/?option=search&type=reference&id=".$row['order_id']."'>".$row['order_id']."</a>";
                            echo "</td>
                        <td>".$row['order_date']."</td>
                        <td>".$row['payment'] ." (".$get_paid_amount.")</td>
                        <td>".$row['payment_status']."</td>
                        <td>cancel</td>
                    </tr>";
                }
            }
        }
        echo '</table>';
        /* All types cancellation if no payment received in 3 hrs. */
        echo '</br></br></br>';
        
?>