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

$current_date_and_time = date("Y-m-d H:i:s");

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
        	
        	require '/home/gaurat/public_html/wp-includes/class-phpmailer.php';
        	require '/home/gaurat/public_html/wp-includes/PHPMailer/SMTP.php';

            include("email/tpl_email_trigger_deposit_reminder.php");
            
            mysqli_query($mysqli, "insert into wpk4_backend_order_email_history (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
            values ('$order_id','Payment reminder','','$current_email_date','deposit_check_email_cron', '', 'Payment Reminder')") or die(mysqli_error($mysqli));
        	
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
        
?>