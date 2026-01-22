<?php
date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Create the POST data string

$mysqli = new mysqli('localhost', 'gaurat_sriharan', 'r)?2lc^Q0cAE', 'gaurat_gauratravel');

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$current_email_date = date("Y-m-d H:i:s");

function availability_pax_update($order_id, $by_user)
{
	global $mysqli;
	$current_date_ymd = date("Y-m-d H:i:s");
	$query_select_booking = "SELECT order_id, trip_code, travel_date, total_pax FROM wpk4_backend_travel_bookings where order_type = 'WPT' AND order_id='$order_id' ";
	$result_select_booking = mysqli_query($mysqli, $query_select_booking);
	while($row_select_booking = mysqli_fetch_assoc($result_select_booking))
	{
		$post_trip_code = $row_select_booking['trip_code'];
		$post_travel_date_int = $row_select_booking['travel_date'];
		$post_pax = $row_select_booking['total_pax'];
				
		$query_get_current_availability_pax = "SELECT pax FROM wpk4_backend_manage_seat_availability where trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
		$result_get_current_availability_pax = mysqli_query($mysqli, $query_get_current_availability_pax);
		$row_get_current_availability_pax = mysqli_fetch_assoc($result_get_current_availability_pax); 
		$current_availability_pax = $row_get_current_availability_pax['pax'];
						
		$new_availability_pax = $current_availability_pax - $post_pax; // reducing the pax count as the booking is being cancelled
						
		$updated_by_username = 'auto_cancellation_cron';
						
		$sql_update_current_availability_pax = "UPDATE wpk4_backend_manage_seat_availability 
					SET pax = '$new_availability_pax', pax_updated_by = '$by_user', pax_updated_on = '$current_date_ymd'
					WHERE trip_code = '$post_trip_code' AND date(travel_date) = '$post_travel_date_int'";
		$result_update_current_availability_pax = mysqli_query($mysqli, $sql_update_current_availability_pax);
	}
}

/* All types reminder email which sents in 20 mins after booking starts. */
$query = "SELECT 
    bookings.auto_id, 
    bookings.order_id, 
    bookings.order_date, 
    bookings.travel_date, 
    bookings.payment_status, 
    pays.trams_received_amount 
FROM wpk4_backend_travel_bookings bookings 
LEFT JOIN wpk4_backend_travel_booking_pax pax 
    ON bookings.order_id = pax.order_id 
    AND bookings.co_order_id = pax.co_order_id 
    AND bookings.product_id = pax.product_id 
LEFT JOIN wpk4_backend_travel_payment_history pays 
    ON bookings.order_id = pays.order_id 
WHERE 
    bookings.payment_status = 'partially_paid' and bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
    AND bookings.order_date <= NOW() - INTERVAL 20 MINUTE AND bookings.order_date >= NOW() - INTERVAL 600 MINUTE 
    AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = '0.00' ) 
    AND NOT EXISTS (
        SELECT 1 
        FROM wpk4_backend_order_email_history email 
        WHERE email.order_id = bookings.order_id 
        AND email.email_type = 'Payment reminder'
    )
ORDER BY 
    bookings.auto_id ASC 
LIMIT 100;
";
echo $query;
$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
$row_counter = mysqli_num_rows($result);
$processedOrders = array();	
echo '</br></br>Email remider for GDeals & FIT</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>Status</th></tr>';
while($row = mysqli_fetch_assoc($result))
{
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) {
		continue; // Skip to the next iteration if the order ID is already processed
	}
	$processedOrders[] = $order_id;

    //include("email/tpl_email_trigger_deposit_reminder.php");
    
    //mysqli_query($mysqli, "insert into wpk4_backend_order_email_history (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
    //values ('$order_id','Payment reminder','','$current_email_date','deposit_check_cancellation_cron', '', 'Payment Reminder')") or die(mysqli_error($mysqli));
	
    $new_status = 'email sent';
    
    echo "<tr>
        <td>".$row['order_id']."</td>
        <td>".$row['order_date']."</td>
        <td>".$row['trams_received_amount']."</td>
        <td>".$row['travel_date']."</td>
        <td>".$row['payment_status']."</td>
        <td>".$new_status."</td>
    </tr>";
}
echo '</table>';
/* All types reminder email which sents in 20 mins after booking ends. */

echo '</br></br></br>';

/* All types cancellation if no payment received in 3 hrs. */
$query = "SELECT 
    bookings.auto_id, 
    bookings.order_id, 
    bookings.order_date, 
    bookings.travel_date, 
    bookings.payment_status, 
    pays.trams_received_amount 
FROM wpk4_backend_travel_bookings bookings 
LEFT JOIN wpk4_backend_travel_booking_pax pax 
    ON bookings.order_id = pax.order_id 
    AND bookings.co_order_id = pax.co_order_id 
    AND bookings.product_id = pax.product_id 
LEFT JOIN wpk4_backend_travel_payment_history pays 
    ON bookings.order_id = pays.order_id 
WHERE 
    bookings.payment_status = 'partially_paid' and bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
    AND bookings.order_date <= NOW() - INTERVAL 3 HOUR 
    AND (pays.order_id IS NULL OR CAST(pays.trams_received_amount AS DECIMAL(10,2)) = '0.00' ) 
    AND EXISTS (
        SELECT 1 
        FROM wpk4_backend_order_email_history email 
        WHERE email.order_id = bookings.order_id 
        AND email.email_type = 'Payment reminder'
    )
ORDER BY 
    bookings.auto_id ASC 
LIMIT 100;
";
echo $query;
$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
$row_counter = mysqli_num_rows($result);
$processedOrders = array();	
echo '</br></br>Cancellation for GDeals & FIT - zero paid in 3 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>Status</th></tr>';
while($row = mysqli_fetch_assoc($result))
{
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) {
		continue; // Skip to the next iteration if the order ID is already processed
	}
	$processedOrders[] = $order_id;
	
    $by_user = 'zeropaid_cancellation_20min';
    
    //$sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled_zero_payment', payment_modified = '$current_email_date', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
	//$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
    
    //mysqli_query($mysqli, "insert into wpk4_backend_order_email_history (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
    //values ('$order_id','Cancellation','','$current_email_date','$by_user', '', 'Cancellation')") or die(mysqli_error($mysqli));
    
	
	//availability_pax_update($order_id, $by_user);
	
    $new_status = 'cancel';
    
    echo "<tr>
        <td>".$row['order_id']."</td>
        <td>".$row['order_date']."</td>
        <td>".$row['trams_received_amount']."</td>
        <td>".$row['travel_date']."</td>
        <td>".$row['payment_status']."</td>
        <td>".$new_status."</td>
    </tr>";
}
echo '</table>';
/* All types cancellation if no payment received in 3 hrs. */

echo '</br></br></br>';

/* FIT cancellation if no payment received after 25 hours starts. */
$query = "SELECT 
    bookings.auto_id, 
    bookings.order_id, 
    bookings.order_date, 
    bookings.travel_date, 
    bookings.payment_status, 
    COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
FROM wpk4_backend_travel_bookings AS bookings 
LEFT JOIN wpk4_backend_travel_booking_pax AS pax 
    ON bookings.order_id = pax.order_id 
    AND bookings.co_order_id = pax.co_order_id 
    AND bookings.product_id = pax.product_id 
LEFT JOIN wpk4_backend_travel_payment_history AS pays 
    ON bookings.order_id = pays.order_id 
WHERE 
    bookings.payment_status = 'partially_paid' and bookings.order_type = 'gds' 
    AND bookings.sub_payment_status NOT IN ('BPAY Paid', 'BPAY Received')
    AND bookings.order_date <= NOW() - INTERVAL 25 HOUR
    AND (pays.order_id IS NULL OR pays.trams_received_amount >= 0.00) 
ORDER BY 
    bookings.auto_id ASC 
LIMIT 100;
";
echo $query;
$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
$row_counter = mysqli_num_rows($result);
$processedOrders = array();	
echo '</br></br>Cancellation for FIT - partially paid after 25 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>New Payment Status</th></tr>';
while($row = mysqli_fetch_assoc($result))
{
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) {
		continue; // Skip to the next iteration if the order ID is already processed
	}
	$processedOrders[] = $order_id;
	$by_user = 'zeropaid_cancellation_25hr';
	
	//$sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled', payment_modified = '$current_email_date', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
	//$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
    
    //mysqli_query($mysqli, "insert into wpk4_backend_order_email_history (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
    //values ('$order_id','Cancellation','','$current_email_date','$by_user', '', 'Cancellation')") or die(mysqli_error($mysqli));

	//availability_pax_update($order_id, $by_user);
	
    $new_status = 'cancel';
    
    echo "<tr>
        <td>".$row['order_id']."</td>
        <td>".$row['order_date']."</td>
        <td>".$row['trams_received_amount']."</td>
        <td>".$row['travel_date']."</td>
        <td>".$row['payment_status']."</td>
        <td>".$new_status."</td>
    </tr>";
}
echo '</table>';
/* FIT cancellation if no payment received after 25 hours ends. */

/* All booking cancellation if no payment received after 96 hours starts. */
$query = "SELECT 
    bookings.auto_id, 
    bookings.order_id, 
    bookings.order_date, 
    bookings.travel_date, 
    bookings.payment_status, 
    COALESCE(pays.trams_received_amount, '0.00') AS trams_received_amount
FROM wpk4_backend_travel_bookings AS bookings 
LEFT JOIN wpk4_backend_travel_booking_pax AS pax 
    ON bookings.order_id = pax.order_id 
    AND bookings.co_order_id = pax.co_order_id 
    AND bookings.product_id = pax.product_id 
LEFT JOIN wpk4_backend_travel_payment_history AS pays 
    ON bookings.order_id = pays.order_id 
WHERE 
    bookings.payment_status = 'partially_paid' 
    AND bookings.sub_payment_status IN ('BPAY Paid', 'BPAY Received')
    AND bookings.order_date <= NOW() - INTERVAL 96 HOUR 
ORDER BY 
    bookings.auto_id ASC 
LIMIT 100;
";
echo $query;
$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
$row_counter = mysqli_num_rows($result);
$processedOrders = array();	
echo '</br></br>Cancellation for GDeals & FIT - BPAY Paid after 96 hrs</br></br>';
echo '<table><tr><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Travel date</th><th>Payment Status</th><th>New Payment Status</th></tr>';
while($row = mysqli_fetch_assoc($result))
{
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) {
		continue; // Skip to the next iteration if the order ID is already processed
	}
	$processedOrders[] = $order_id;
	$by_user = 'zeropaid_cancellation_96hr';
	
	//$sql_update_status = "UPDATE wpk4_backend_travel_bookings SET payment_status = 'canceled', payment_modified = '$current_email_date', payment_modified_by = '$by_user' WHERE order_id = '$order_id'";
	//$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
    
    //mysqli_query($mysqli, "insert into wpk4_backend_order_email_history (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
    //values ('$order_id','Cancellation','','$current_email_date','$by_user', '', 'Cancellation')") or die(mysqli_error($mysqli));
	
	//availability_pax_update($order_id, $by_user);
	
    $new_status = 'cancel';
    
    echo "<tr>
        <td>".$row['order_id']."</td>
        <td>".$row['order_date']."</td>
        <td>".$row['trams_received_amount']."</td>
        <td>".$row['travel_date']."</td>
        <td>".$row['payment_status']."</td>
        <td>".$new_status."</td>
    </tr>";
}
echo '</table>';
/* All booking cancellation if no payment received after 96 hours ends. */

?>