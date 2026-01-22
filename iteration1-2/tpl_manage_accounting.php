<?php
/**
 * Template Name: Manage Accounting
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sri Harshan
 */
get_header();
date_default_timezone_set("Australia/Melbourne"); 
global $current_user; 
wp_get_current_user();
$currnt_userlogn = $current_user->user_login;
$current_date_and_time = date("Y-m-d H:i:s");
include('wp-config-custom.php');

$current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);

if(!function_exists('gdeal_name_update_ajax'))
{
	function gdeal_name_update_ajax($order_id)
	{
			$currnt_userlogn = 'GDeals Payment'; 
			$url = "https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_amadeus_name_update_backend.php?order_id=" . urlencode($order_id) ."&agent=" . urlencode($currnt_userlogn);

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET'
			));

			$response = curl_exec($curl);
			$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			if (curl_errno($curl)) {
				//echo "cURL Error: " . curl_error($curl);
			} else {
				//echo "Response (HTTP $http_code): " . $response;
			}

			curl_close($curl);

	}
}
?>
<style>
.accounts_home_button
{
    width: 170px;
    padding:8px 12px;
    font-size: 11px;
    margin:20px 30px;
}
.accounts_back_to_home_button
{
    width: 130px;
    padding:8px 12px;
    font-size: 11px;
    margin:0px 5px;
}
.accounts_general_button
{
    width: 130px;
    padding:8px 12px;
    font-size: 11px;
    float:right;
    margin:0px 5px;
}
.accounts_submit_button
{
    padding:6px 10px;
    margin:0;
    font-size:11px;
}
.accounts_general_table
{
    width:100%;
    margin:auto;
    font-size:14px;
}
</style>

    <div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:90%;margin:auto;padding:100px 0px;'>
        <?php
        if( $is_ip_matched > 0 )
        {
            if(!isset($_GET['pg']) )
            {
            ?>
                <a href="?pg=add-payment"><button class="accounts_home_button">Add Payment</button></a>
                <a href="/import-payments/" target="_blank"><button class="accounts_home_button">Import Payments</button></a>
                
                <a href="?pg=view-payments"><button class="accounts_home_button">View Payments</button></a>
            <?php
            }
            else
            {
                // view all bank accounts section
                if( $_GET['pg'] == 'bank-accounts' && current_user_can( 'administrator' ) )
                {
                    $query_bank_accounts = "SELECT * FROM wpk4_backend_accounts_bank_account";
					$result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                    ?>
                    <a href="?"><button class="accounts_back_to_home_button">Home</button></a>
                    <a href="?pg=add-bank-account"><button class="accounts_general_button">Add New Bank</button></a>
    				</br></br>
    				<table class="table table-striped accounts_general_table">
    					<thead>
        					<tr>
            					<th>ID</th>
            					<th>Account Name</th>
            					<th>Branch</th>
            					<th>Account</th>
        				    </tr>
        				</thead>
    				    <tbody>
            				<?php
            				while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
            				{
        		                ?>
        		                <tr>
                					<td><?php echo $row_bank_accounts['bank_id']; ?></td>
                					<td><?php echo $row_bank_accounts['account_name']; ?></td>
                					<td><?php echo $row_bank_accounts['branch']; ?></td>
                					<td><?php echo $row_bank_accounts['account']; ?></td>
            				    </tr>
        		                <?php
            				}
            				?>
        				</tbody>
    				</table>
				    <?php
                }
                if($_GET['pg'] == 'add-bank-account' && current_user_can( 'administrator' ) )
                {
                    ?>
                    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        <table class="table table-striped accounts_general_table">
        				    <tbody>
                				<tr>
                    				<td width="20%">ID</td>
                    				<td><input type='text' name='bank_id' required></td>
                    			</tr>
                    			<tr>
                    				<td>Account Name</td>
                    				<td><input type='text' name='bank_account_name' required></td>
                    			</tr>
                    			<tr>
                    				<td>Branch</td>
                    				<td><input type='text' name='bank_branch' required></td>
                    			</tr>
                    			<tr>	
                    				<td>Account</td>
                    				<td><input type='text' name='bank_account_number' required></td>
                				</tr>
                				<tr>	
                    				<td colspan="2"><center><input type='submit' name='save_bank_account' style="padding:15px; margin:0; font-size:11px;" value='Save Bank'></center></td>
                				</tr>
            				</tbody>
        				</table>
    				</form>
                    <?php
                    if(isset($_POST['save_bank_account']))
				    {
				        $bank_id = $_POST['bank_id'];
                        $bank_account_name = $_POST['bank_account_name'];
                        $bank_branch = $_POST['bank_branch'];
                        $bank_account_number = $_POST['bank_account_number'];
                                        
				        mysqli_query($mysqli,"insert into wpk4_backend_accounts_bank_account ( bank_id, account_name, account, branch ) 
						values ('$bank_id','$bank_account_name','$bank_account_number' ,'$bank_branch' )") or die(mysqli_error($mysqli));
						
                        echo '<script>window.location.href="?pg=bank-accounts";</script>';
				    }
                }
                if($_GET['pg'] == 'view-payments' && (current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ) || current_user_can( 'ticketing_user' ) || current_user_can( 'ticketing_admin' ) || current_user_can( 'datechange_manager' ) ) )
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
		    	    <script>
                        window.addEventListener("load", function (event) {
                    	    var currentdate = new Date(); 
                    		var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                    		let drp = new DateRangePicker('payment_date',
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
                    			document.getElementById("payment_date").value = start.format() + " - " + start.format();
                            })
                        });
                    </script>
            		<script>
                    function searchordejs() 
            		{
            			var payment_date = document.getElementById("payment_date").value;
            			var payment_method = document.getElementById("payment_method").value;	
            			var booking_source = document.getElementById("booking_source").value;	
            			var order_id = document.getElementById("order_id").value;	
            			var profile_id = document.getElementById("profile_id").value;	
            			var payment_type = document.getElementById("payment_type").value;	
            			
            			window.location='?pg=view-payments&payment_date=' + payment_date + '&payment_method=' + payment_method + '&booking_source=' + booking_source + '&order_id=' + order_id + '&profile_id=' + profile_id + '&payment_type=' + payment_type ;
            		}
            		</script>
            		<h5>Manage Customers</h5>
                	<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
                	    <tr>
                	        <td width='8%'>
                			    Payment Date</br>
                			    <input type='text' name='payment_date' value='<?php if(isset($_GET['payment_date'])) { echo substr($_GET['payment_date'], 0, 10); } ?>' id='payment_date'>
                		    </td>
                		    <td width='8%'>
                			    Payment Method</br>
                			    <select name='payment_method' id='payment_method' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        <?php
                			        $query_payment_method = "SELECT account_name, bank_id FROM wpk4_backend_accounts_bank_account where bank_id IN (7,8,9,5,13,14) order by account_name asc";
                            		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                            		while($row_payment_method = mysqli_fetch_assoc($result_payment_method))
                        		    {
                        		        if(isset($_GET['payment_method']) && $_GET['payment_method'] != '' && $_GET['payment_method'] == $row_payment_method['bank_id'])
                        		        {
                        			        ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>" selected><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		        else
                        		        {
                        		            ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>"><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		    }
                			        ?>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Booking Source</br>
                			    <select name='booking_source' id='booking_source' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        <option value="WPT" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'WPT') { echo 'selected'; } ?>>GDeals</option>
                			        <option value="gds" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'gds') { echo 'selected'; } ?>>GDS</option>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Order ID</br>
                			    <input type='text' name='order_id' value='<?php if(isset($_GET['order_id'])) { echo $_GET['order_id']; } ?>' id='order_id'>
                		    </td>
                		    <td width='8%'>
                			    Profile ID</br>
                			    <input type='text' name='profile_id' value='<?php if(isset($_GET['profile_id'])) { echo $_GET['profile_id']; } ?>' id='profile_id'>
                		    </td>
                		    <td width='8%'>
                			    Payment Type</br>
                			    <select name='payment_type' id='payment_type' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        
                			        <option value="deposit" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'deposit') { echo 'selected'; } ?>>Deposit</option>
                			        <option value="balance" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'balance') { echo 'selected'; } ?>>Balance</option>
                			        <option value="dc_charge" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'dc_charge') { echo 'selected'; } ?>>Datechange Payment</option>
                			        <option value="additional_payment" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'additional_payment') { echo 'selected'; } ?>>Additional Payment</option>
                			        <option value="deposit_adjustment" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'deposit_adjustment') { echo 'selected'; } ?>>Deposit Adjustment</option>
                			        <option value="refund" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'refund') { echo 'selected'; } ?>>Refund</option>
                			        <option value="other" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'other') { echo 'selected'; } ?>>Other</option>
                			        
                			        
                			        <?php
                			        /*
                			        $query_payment_type = "SELECT distinct(pay_type) FROM wpk4_backend_travel_payment_history";
                            		$result_payment_type = mysqli_query($mysqli, $query_payment_type) or die(mysqli_error($mysqli));
                            		while($row_payment_type = mysqli_fetch_assoc($result_payment_type))
                        		    {
                        		        if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == $row_payment_type['pay_type'])
                        		        {
                        			        ?>
                        			        <option value="<?php echo $row_payment_type['pay_type']; ?>" selected><?php echo $row_payment_type['pay_type']; ?></option>
                        			        <?php
                        		        }
                        		        else
                        		        {
                        		            ?>
                        			        <option value="<?php echo $row_payment_type['pay_type']; ?>"><?php echo $row_payment_type['pay_type']; ?></option>
                        			        <?php
                        		        }
                        		    }*/
                			        ?>
                			    </select>
                		    </td>
                		</tr>
                		<tr>
                			<td colspan="6" style='text-align:center;'>
                				<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
                			</td>
            			</tr>
            		</table>
            		<?php
            		$common_start_filter = date('Y-m-d');

            		$payment_date = ($_GET['payment_date'] ?? false) ? substr($_GET['payment_date'], 0, 10) : '' ;
            		$payment_method = ($_GET['payment_method'] ?? false) ? $_GET['payment_method'] : '' ;
            		$booking_source = ($_GET['booking_source'] ?? false) ? $_GET['booking_source'] : '' ;
            		$order_id = ($_GET['order_id'] ?? false) ? $_GET['order_id'] : '' ;
            		$profile_id = ($_GET['profile_id'] ?? false) ? $_GET['profile_id'] : '' ;
            		$payment_type = ($_GET['payment_type'] ?? false) ? $_GET['payment_type'] : '' ;
            		
            		if(isset($payment_date) && $payment_date != '')
            		{
            			$payment_date_sql = "date(payments.process_date) = '".$payment_date."' AND ";
            		}
            		else
            		{
            			$payment_date_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($payment_method) && $payment_method != '')
            		{
            			$payment_method_sql = "payments.payment_method = '".$payment_method."' AND ";
            		}
            		else
            		{
            			$payment_method_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($booking_source) && $booking_source != '')
            		{
            		    if($booking_source == 'WPT')
            		    {
            		        $booking_source_sql = "(bookings.order_type = '".$booking_source."' OR bookings.order_type = '') AND ";
            		    }
            		    else
            		    {
            			    $booking_source_sql = "bookings.order_type = '".$booking_source."' AND ";
            		    }
            		}
            		else
            		{
            			$booking_source_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($order_id) && $order_id != '')
            		{
            		    if (ctype_digit($order_id)) 
                        {
                            $order_id_sql = "payments.order_id = '".$order_id."' AND ";
                        }
                        else
                        {
                            $order_id_sql = "pax.pnr = '".$order_id."' AND ";
                        }
            		}
            		else
            		{
            			$order_id_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($profile_id) && $profile_id != '')
            		{
            			$profile_id_sql = "payments.profile_no = '".$profile_id."' AND ";
            		}
            		else
            		{
            			$profile_id_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($payment_type) && $payment_type != '')
            		{
            		    if($payment_type == 'deposit')
            		    {
            		        $payment_type_value = '"deposit"';
            		    }
            		    else if($payment_type == 'balance')
            		    {
            		        $payment_type_value = '"balance", "balance "';
            		    }
            		    else if($payment_type == 'dc_charge')
            		    {
            		        $payment_type_value = '"dc_charge", "Datechange"';
            		    }
            		    else if($payment_type == 'additional_payment')
            		    {
            		        $payment_type_value = '"additional_payment"';
            		    }
            		    else if($payment_type == 'deposit_adjustment')
            		    {
            		        $payment_type_value = '"deposit_adjustment"';
            		    }
            		    else if($payment_type == 'refund')
            		    {
            		        $payment_type_value = '"Refund"';
            		    }
            		    else if($payment_type == 'other')
            		    {
            		        $payment_type_value = '"other"';
            		    }
            		    else
            		    {
            		        $payment_type_value = '';
            		    }
            			$payment_type_sql = "payments.pay_type IN ($payment_type_value)";
            		}
            		else
            		{
            			$payment_type_sql = "payments.order_id IS NOT NULL";
            		}
            		
            		if(
            		    (isset($payment_date_sql) && $payment_date_sql != '') ||
            		    (isset($payment_method_sql) && $payment_method_sql != '') ||
            		    (isset($booking_source_sql) && $booking_source_sql != '') ||
            		    (isset($order_id_sql) && $order_id_sql != '') ||
            		    (isset($profile_id_sql) && $profile_id_sql != '') ||
            		    (isset($payment_type_sql) && $payment_type_sql != '')
            		  ) 
            		{
            			$query = "SELECT 
            			            payments.auto_id, payments.order_id, payments.process_date, payments.source, payments.profile_no, 
            			            payments.trams_remarks, payments.trams_received_amount, payments.reference_no, payments.payment_method, payments.pay_type, pax.ticket_number,
            			            bookings.payment_status, pax.pnr
            			    FROM wpk4_backend_travel_payment_history payments
            			    JOIN wpk4_backend_travel_bookings bookings ON 
                                payments.order_id = bookings.order_id
                            JOIN wpk4_backend_travel_booking_pax pax ON 
                                payments.order_id = pax.order_id
            				where 
            					$payment_date_sql
                                $payment_method_sql
                                $booking_source_sql
                                $order_id_sql
                                $profile_id_sql
                                $payment_type_sql
            				order by payments.auto_id desc LIMIT 100";
            		}
            		else
            		{
            		    $query = "SELECT 
            		                payments.auto_id, payments.order_id, payments.process_date, payments.source, payments.profile_no, 
            			            payments.trams_remarks, payments.trams_received_amount, payments.reference_no, payments.payment_method, payments.pay_type, pax.ticket_number,
            			            bookings.payment_status, pax.pnr
            			    FROM wpk4_backend_travel_payment_history payments
            			    JOIN wpk4_backend_travel_bookings bookings ON 
                                passenger.family_id = bookings.family_id
                            JOIN wpk4_backend_travel_booking_pax pax ON 
                                payments.order_id = pax.order_id
            				where date(payments.process_date) = '$common_start_filter'
            				order by payments.auto_id desc LIMIT 100";
            			echo '</br><center><p style="color:red;">Kindly add the filters to check the records.</p></center>';
            		}
            		if( $currnt_userlogn == 'sriharshans')
            		{
            		   //echo $query;	
            		}
            		$selection_query = $query;
            		$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            		$row_counter_ticket = mysqli_num_rows($result);
            		$auto_numbering = 1;
            		$total_paxs = 0;
            		
            		?>
            		</br>
            		<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
            			<thead>
                			<tr>
                    			<th>Created Date Time</th>
                    			<th>Status</th>
                    			<th>Received Amount</th>
                    			<th>Payment Method</th>
                    			<th>PNR</th>
                    			<th>Order ID</th>
                    			<th>Ticket Number</th>
                    			<th>Profile No</th>
                    			<th>Remarks</th>
                			</tr>
            			</thead>
            			<tbody>
                    	    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        		<?php
                        		$delimiter = ","; 
                                $filename = "payment_records_" . date('Y-m-dH-i-s') . ".csv"; 			 
                                $f = fopen('csv_reports/'.$filename, 'w');
                                $fields = array('Created Date Time', 'Status','Amount','PNR','Order','Profile no.','Remarks');
                                fputcsv($f, $fields, $delimiter);  
                                $processedOrders = [];
                        		while($row = mysqli_fetch_assoc($result))
                        		{
                        			$auto_id = $row['auto_id'];
                        			
                        			if (in_array($auto_id, $processedOrders)) {
                            			continue; // Skip to the next iteration if the order ID is already processed
                            		}
                            		$processedOrders[] = $auto_id;
                            							
                        			$order_id = $row['order_id'];
                        			$ticket_number = $row['ticket_number'];
                        			$process_date = date('Y-m-d', strtotime($row['process_date']));
                        		    $source = $row['source'];
                        			$profile_no = $row['profile_no'];
                        			$trams_remarks = $row['trams_remarks'];
                        			$trams_received_amount = $row['trams_received_amount'];
                        			$reference_no = $row['reference_no'];
                        			$payment_method = $row['payment_method'];
                        			$pay_type = $row['pay_type'];
                        			$payment_status = $row['payment_status'];
                        			$pnr = $row['pnr'];
                        			?>
                        			<tr>
                            			<td width='6%'>
                                            <?php echo $process_date; ?>            	
                                        </td>
                                        <td width='10%'>
                                            <?php 
                                            if($payment_status == 'pending')
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                            				else if($payment_status == 'partially_paid')
                            				{
                            					$txt_payment_status = 'Partially Paid';
                            				}
                            				else if($payment_status == 'paid')
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
                            				else if($payment_status == 'voucher_submited')
                            				{
                            					$txt_payment_status = 'Rebooked';
                            				}
                            				else if($payment_status == 'receipt_received')
                            				{
                            					$txt_payment_status = 'Receipt Received';
                            				}
                            				else
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                                            echo $txt_payment_status; 
                                            
                                            ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php echo $trams_received_amount; ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php 
                                            if( $payment_method == 'UNKNOWN' || $payment_method == 'IATA' || $payment_method == 'DIRECT' || $payment_method == 'ASIAPAY' )
                                            {
                                                $payment_method = '8';
                                            }
                                           
                                            if (ctype_digit($payment_method)) 
                                            {
                                                
                                                
                                                $query_payment_method = "SELECT account_name FROM wpk4_backend_accounts_bank_account where bank_id = $payment_method";
                                        		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                                        		$row_payment_method = mysqli_fetch_assoc($result_payment_method);
                                    		    if(mysqli_num_rows($result_payment_method) > 0)
                                    		    {
                                    		        echo $row_payment_method['account_name'];  
                                    		    }
                                    		    else
                                    		    {
                                    		        echo 'Unknown';
                                    		    }
                                            }
                                            else
                                    		{
                                    		    echo 'Unknown';
                                    		}
                                            ?>            	
                                        </td>
                                        <td width='5%'>
                                            <?php echo $pnr; ?>            	
                                        </td>
                                        <td width='5%'>
                                            <?php echo $order_id; ?>            	
                                        </td>
                                         <td width='5%'>
                                            <?php echo $ticket_number; ?>            	
                                        </td>
                                        <td width='6%'>
                                            <input type="text" value="<?php echo $profile_no; ?>" name="<?php echo $auto_id; ?>_profile_no">
                                        </td>
                                        <td width='15%'>
                                            <input type="text" value="<?php echo $trams_remarks; ?>" name="<?php echo $auto_id; ?>_trams_remarks">          	
                                        </td>
                        		    </tr>
                        			<?php
                        			$lineData = array($row['process_date'], $payment_status,  $trams_received_amount, $pnr, $order_id, $profile_no, $trams_remarks ); 
                                    fputcsv($f, $lineData, $delimiter);
                            
                        			$auto_numbering++;
                        		}

                                fseek($f, 0);
                                fpassthru($f);
                                
                        		?>
                        		<tr>
                				    <td colspan = '8'>
                				        <input type="submit" class="gtc_submission_btn" name="download_payment_report" value="Download File" style="width:180px; height:35px; font-size:11px;"/>
                        				<?php
                        			    if( current_user_can( 'administrator' ) )
                        				{
                        				?>
                        				    <input type='submit' style='float:right;padding:10px; margin:0;font-size:14px; float:right; ' name='save_all_payment_records' value='Save All'>
                        				<?php
                        				}
                        				?>
                				    </td>
                				</tr>
                    		</form>
                    	</tbody>
                    </table>
            		</br></br>
                    <?php
                    if (isset($_POST["download_payment_report"])) // to export the data
                    {
                    	echo '<script>window.location.href="https://gauratravel.com.au/csv_reports/'.$filename.'";</script>';
                    }
                    if(isset($_POST['save_all_payment_records']))
				    {
    					$query_checker = $selection_query;
    					$result_checker = mysqli_query($mysqli, $query_checker);
    					while($row_checker = mysqli_fetch_assoc($result_checker))
    					{
    						$row_auto_id = $row_checker['auto_id'];
    						$row_order_id = $row_checker['order_id'];

    						foreach($row_checker as $columnname_db => $db_value)
    						{
    							$dbcolumn_and_postname_checker = $row_auto_id.'_'.$columnname_db;
    							foreach ($_POST as $post_fieldname => $post_fieldvalue)
    							{
    								if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_checker[$columnname_db])
    								{
    									$sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
    										        $columnname_db='$post_fieldvalue', 
        										    modified_date = '$current_date_and_time', 
                                                    modified_by = '$currnt_userlogn' WHERE auto_id='$row_auto_id'";
    									$result_status= mysqli_query($mysqli,$sql_update_status);
    									
    									mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, pax_auto_id, meta_key, meta_value, meta_key_data, updated_time, updated_user) 
										values ('$row_order_id', '$row_auto_id', '$columnname_db', '$post_fieldvalue', 'Payment Update into wpk4_backend_travel_payment_history', '$current_date_and_time', '$currnt_userlogn')") or die(mysqli_error($mysqli));
    								}
    							}
    						}
    					}
                        echo '<script>window.location.href="'.$current_url.'";</script>';
					}
                }
                
                if($_GET['pg'] == 'view-payments-2' && (current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ) || current_user_can( 'ticketing_user' ) || current_user_can( 'ticketing_admin' ) || current_user_can( 'datechange_manager' ) ) )
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
		    	    <script>
                        window.addEventListener("load", function (event) {
                    	    var currentdate = new Date(); 
                    		var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                    		let drp = new DateRangePicker('payment_date',
                            {
                                maxDate: end_maxtime,
                                timePicker: false,
                                alwaysShowCalendars: true,
                    			singleDatePicker: false,
                                autoApply: false,
                                maxSpan: { "days": 3 },
                    			autoUpdateInput: false,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                    		function (start, end) {
                    		        var start_fixed = start.format().slice(0,10);
                    		        var end_fixed = end.format().slice(0,10);
                    		        
    						        document.getElementById("payment_date").value = start_fixed + ' - ' + end_fixed;
							})
                        });
                        
                        window.addEventListener("load", function (event) {
                    	    var currentdate = new Date(); 
                    		var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                    		let drp = new DateRangePicker('order_date',
                            {
                                maxDate: end_maxtime,
                                timePicker: false,
                                alwaysShowCalendars: true,
                    			singleDatePicker: false,
                                autoApply: false,
                                maxSpan: { "days": 3 },
                    			autoUpdateInput: false,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                    		function (start, end) {
                    		        var start_fixed = start.format().slice(0,10);
                    		        var end_fixed = end.format().slice(0,10);
                    		        
    						        document.getElementById("order_date").value = start_fixed + ' - ' + end_fixed;
							})
                        });
                        
                        window.addEventListener("load", function (event) {
                    	    var currentdate = new Date(); 
                    		var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                    		let drp = new DateRangePicker('clear_date',
                            {
                                maxDate: end_maxtime,
                                timePicker: false,
                                alwaysShowCalendars: true,
                    			singleDatePicker: false,
                                autoApply: false,
                                maxSpan: { "days": 3 },
                    			autoUpdateInput: false,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                    		function (start, end) {
                    		        var start_fixed = start.format().slice(0,10);
                    		        var end_fixed = end.format().slice(0,10);
                    		        
    						        document.getElementById("clear_date").value = start_fixed + ' - ' + end_fixed;
							})
                        });
                    </script>
            		<script>
                    function searchordejs() 
            		{
            			var payment_date = document.getElementById("payment_date").value;
            			var order_date = document.getElementById("order_date").value;
            			var reference_no = document.getElementById("reference_no").value;
            			var amount = document.getElementById("amount").value;	
            			var payment_method = document.getElementById("payment_method").value;	
            			var booking_source = document.getElementById("booking_source").value;	
            			var order_id = document.getElementById("order_id").value;	
            			var profile_id = document.getElementById("profile_id").value;
            			var email_phone = document.getElementById("email_phone").value;	
            			var pnr_ticket = document.getElementById("pnr_ticket").value;	
            			var payment_type = document.getElementById("payment_type").value;
            			var clear_date = document.getElementById("clear_date").value;
            			
            			window.location='?pg=view-payments-2&payment_date=' + payment_date + '&clear_date=' + clear_date + '&order_date=' + order_date + '&reference_no=' + reference_no + '&amount=' + amount + '&pnr_ticket=' + pnr_ticket + '&email_phone=' + email_phone + '&payment_method=' + payment_method + '&booking_source=' + booking_source + '&order_id=' + order_id + '&profile_id=' + profile_id + '&payment_type=' + payment_type ;
            		}
            		</script>
            		<h5>Manage Customers</h5>
                	<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
                	    <tr>
                	        <td width='8%'>
                			    Order</br>Date</br>
                			    <input type='text' name='order_date' value='<?php if(isset($_GET['order_date'])) { echo $_GET['order_date']; } ?>' id='order_date'>
                		    </td>
                	        <td width='8%'>
                			    Payment</br>Date</br>
                			    <input type='text' name='payment_date' value='<?php if(isset($_GET['payment_date'])) { echo $_GET['payment_date']; } ?>' id='payment_date'>
                		    </td>
                		    <td width='8%'>
                			    Payment</br>Method</br>
                			    <select name='payment_method' id='payment_method' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        <?php
                			        $query_payment_method = "SELECT account_name, bank_id FROM wpk4_backend_accounts_bank_account where bank_id IN (7,8,9,5,13,14) order by account_name asc";
                            		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                            		while($row_payment_method = mysqli_fetch_assoc($result_payment_method))
                        		    {
                        		        if(isset($_GET['payment_method']) && $_GET['payment_method'] != '' && $_GET['payment_method'] == $row_payment_method['bank_id'])
                        		        {
                        			        ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>" selected><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		        else
                        		        {
                        		            ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>"><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		    }
                			        ?>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Booking</br>Source</br>
                			    <select name='booking_source' id='booking_source' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        <option value="WPT" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'WPT') { echo 'selected'; } ?>>GDeals</option>
                			        <option value="gds" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'gds') { echo 'selected'; } ?>>GDS</option>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Order ID</br></br>
                			    <input type='text' name='order_id' value='<?php if(isset($_GET['order_id'])) { echo $_GET['order_id']; } ?>' id='order_id'>
                		    </td>
                		    <td width='8%'>
                			    Profile ID</br></br>
                			    <input type='text' name='profile_id' value='<?php if(isset($_GET['profile_id'])) { echo $_GET['profile_id']; } ?>' id='profile_id'>
                		    </td>
                		    <td width='8%'>
                			    Reference No</br></br>
                			    <input type='text' name='reference_no' value='<?php if(isset($_GET['reference_no'])) { echo $_GET['reference_no']; } ?>' id='reference_no'>
                		    </td>
                		    <td width='8%'>
                			    Email/ Phone</br></br>
                			    <input type='text' name='email_phone' value='<?php if(isset($_GET['email_phone'])) { echo $_GET['email_phone']; } ?>' id='email_phone'>
                		    </td>
                		    <td width='8%'>
                			    PNR/</br>Ticket No</br>
                			    <input type='text' name='pnr_ticket' value='<?php if(isset($_GET['pnr_ticket'])) { echo $_GET['pnr_ticket']; } ?>' id='pnr_ticket'>
                		    </td>
                		    <td width='8%'>
                			    Amount</br></br>
                			    <input type='text' name='amount' value='<?php if(isset($_GET['amount'])) { echo $_GET['amount']; } ?>' id='amount' placeholder='100 | 99-101'>
                		    </td>
                		    <td width='8%'>
                			    Payment</br>Type</br>
                			    <select name='payment_type' id='payment_type' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        
                			        <option value="deposit" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'deposit') { echo 'selected'; } ?>>Deposit</option>
                			        <option value="balance" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'balance') { echo 'selected'; } ?>>Balance</option>
                			        <option value="dc_charge" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'dc_charge') { echo 'selected'; } ?>>Datechange Payment</option>
                			        <option value="additional_payment" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'additional_payment') { echo 'selected'; } ?>>Additional Payment</option>
                			        <option value="deposit_adjustment" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'deposit_adjustment') { echo 'selected'; } ?>>Deposit Adjustment</option>
                			        <option value="refund" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'refund') { echo 'selected'; } ?>>Refund</option>
                			        <option value="other" <?php if(isset($_GET['payment_type']) && $_GET['payment_type'] != '' && $_GET['payment_type'] == 'other') { echo 'selected'; } ?>>Other</option>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Clear</br>Date</br>
                			    <input type='text' name='clear_date' value='<?php if(isset($_GET['clear_date'])) { echo $_GET['clear_date']; } ?>' id='clear_date'>
                		    </td>
                		</tr>
                		<tr>
                			<td colspan="12" style='text-align:center;'>
                				<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
                			</td>
            			</tr>
            		</table>
            		<?php
            		$common_start_filter = date('Y-m-d');

            		$payment_date = ($_GET['payment_date'] ?? false) ? substr($_GET['payment_date'], 0, 10) : '' ;
            		$payment_date_end = ($_GET['payment_date'] ?? false) ? substr($_GET['payment_date'], 13, 10) : '' ;
            		
            		$order_date = ($_GET['order_date'] ?? false) ? substr($_GET['order_date'], 0, 10) : '' ;
            		$order_date_end = ($_GET['order_date'] ?? false) ? substr($_GET['order_date'], 13, 10) : '' ;
            		
            		$clear_date = ($_GET['clear_date'] ?? false) ? substr($_GET['clear_date'], 0, 10) : '' ;
            		$clear_date_end = ($_GET['clear_date'] ?? false) ? substr($_GET['clear_date'], 13, 10) : '' ;
            		
            		$payment_method = ($_GET['payment_method'] ?? false) ? $_GET['payment_method'] : '' ;
            		$booking_source = ($_GET['booking_source'] ?? false) ? $_GET['booking_source'] : '' ;
            		$order_id = ($_GET['order_id'] ?? false) ? $_GET['order_id'] : '' ;
            		$profile_id = ($_GET['profile_id'] ?? false) ? $_GET['profile_id'] : '' ;
            		$payment_type = ($_GET['payment_type'] ?? false) ? $_GET['payment_type'] : '' ;
            		
            		$reference_no_filter = ($_GET['reference_no'] ?? false) ? $_GET['reference_no'] : '' ;
            		$email_phone_filter = ($_GET['email_phone'] ?? false) ? $_GET['email_phone'] : '' ;
            		$pnr_ticket_filter = ($_GET['pnr_ticket'] ?? false) ? $_GET['pnr_ticket'] : '' ;
            		
            		$amount_filter = ($_GET['amount'] ?? false) ? $_GET['amount'] : '' ;
            		
            		if(isset($payment_date) && $payment_date != '' && isset($payment_date_end) && $payment_date_end != '')
            		{
            			$payment_date_sql = "date(payments.process_date) >= '".$payment_date."' AND date(payments.process_date) <= '".$payment_date_end."' AND ";
            		}
            		else
            		{
            			$payment_date_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($clear_date) && $clear_date != '' && isset($clear_date_end) && $clear_date_end != '')
            		{
            			$clear_date_sql = "date(payments.cleared_date) >= '".$clear_date."' AND date(payments.cleared_date) <= '".$clear_date_end."' AND ";
            		}
            		else
            		{
            			$clear_date_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($order_date) && $order_date != '' && isset($order_date_end) && $order_date_end != '')
            		{
            			$order_date_sql = "date(bookings.order_date) >= '".$order_date."' AND date(bookings.order_date) <= '".$order_date_end."' AND ";
            		}
            		else
            		{
            			$order_date_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($payment_method) && $payment_method != '')
            		{
            			$payment_method_sql = "payments.payment_method = '".$payment_method."' AND ";
            		}
            		else
            		{
            			$payment_method_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($booking_source) && $booking_source != '')
            		{
            		    if($booking_source == 'WPT')
            		    {
            		        $booking_source_sql = "(bookings.order_type = '".$booking_source."' OR bookings.order_type = '') AND ";
            		    }
            		    else
            		    {
            			    $booking_source_sql = "bookings.order_type = '".$booking_source."' AND ";
            		    }
            		}
            		else
            		{
            			$booking_source_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($order_id) && $order_id != '')
            		{
            		    if (ctype_digit($order_id)) 
                        {
                            $order_id_sql = "payments.order_id = '".$order_id."' AND ";
                        }
                        else
                        {
                            $order_id_sql = "pax.pnr = '".$order_id."' AND ";
                        }
            		}
            		else
            		{
            			$order_id_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($profile_id) && $profile_id != '')
            		{
            			$profile_id_sql = "payments.profile_no = '".$profile_id."' AND ";
            		}
            		else
            		{
            			$profile_id_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($payment_type) && $payment_type != '')
            		{
            		    if($payment_type == 'deposit')
            		    {
            		        $payment_type_value = '"deposit"';
            		    }
            		    else if($payment_type == 'balance')
            		    {
            		        $payment_type_value = '"balance", "balance "';
            		    }
            		    else if($payment_type == 'dc_charge')
            		    {
            		        $payment_type_value = '"dc_charge", "Datechange"';
            		    }
            		    else if($payment_type == 'additional_payment')
            		    {
            		        $payment_type_value = '"additional_payment"';
            		    }
            		    else if($payment_type == 'deposit_adjustment')
            		    {
            		        $payment_type_value = '"deposit_adjustment"';
            		    }
            		    else if($payment_type == 'refund')
            		    {
            		        $payment_type_value = '"Refund"';
            		    }
            		    else if($payment_type == 'other')
            		    {
            		        $payment_type_value = '"other"';
            		    }
            		    else
            		    {
            		        $payment_type_value = '';
            		    }
            			$payment_type_sql = "payments.pay_type IN ($payment_type_value)";
            		}
            		else
            		{
            			$payment_type_sql = "payments.order_id IS NOT NULL";
            		}
            		
            		if(isset($reference_no_filter) && $reference_no_filter != '')
            		{
            			$reference_no_sql = "payments.reference_no = '".$reference_no_filter."' AND ";
            		}
            		else
            		{
            			$reference_no_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($amount_filter) && $amount_filter != '')
            		{
            		    if (strpos($amount_filter, '-') !== false) 
            		    {
                            // Split the string into two parts
                            list($from_amount, $to_amount) = explode('-', $amount_filter, 2);
                            
                            // Trim the values to ensure there are no spaces
                            $from_amount = trim($from_amount);
                            $to_amount = trim($to_amount);
                            
                            $from_amount = number_format((float)$from_amount, 2, '.', '');
                            $to_amount = number_format((float)$to_amount, 2, '.', '');
                            
            			    $amount_sql = "CAST(payments.trams_received_amount AS DECIMAL(10,2)) >= '".$from_amount."' AND CAST(payments.trams_received_amount AS DECIMAL(10,2)) <= '".$to_amount."' AND ";
            		    }
            		    else
            		    {
            		        $amount_filter = number_format((float)$amount_filter, 2, '.', '');
            			    $amount_sql = "CAST(payments.trams_received_amount AS DECIMAL(10,2)) = '".$amount_filter."' AND ";
            		    }
            		    
            		}
            		else
            		{
            			$amount_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($pnr_ticket_filter) && $pnr_ticket_filter != '')
            		{
            			$pnr_ticket_sql = " ( pax.pnr = '".$pnr_ticket_filter."' OR pax.ticket_number = '".$pnr_ticket_filter."' ) AND ";
            		}
            		else
            		{
            			$pnr_ticket_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(isset($email_phone_filter) && $email_phone_filter != '')
            		{
            			$email_phone_sql = " ( pax.email_pax = '".$email_phone_filter."' OR pax.phone_pax = '".$email_phone_filter."' ) AND ";
            		}
            		else
            		{
            			$email_phone_sql = "payments.order_id IS NOT NULL AND ";
            		}
            		
            		if(
            		    (isset($payment_date_sql) && $payment_date_sql != '') ||
            		    (isset($order_date_sql) && $order_date_sql != '') ||
            		    (isset($payment_method_sql) && $payment_method_sql != '') ||
            		    (isset($booking_source_sql) && $booking_source_sql != '') ||
            		    (isset($order_id_sql) && $order_id_sql != '') ||
            		    (isset($profile_id_sql) && $profile_id_sql != '') ||
            		    (isset($reference_no_sql) && $reference_no_sql != '') ||
            		    (isset($amount_sql) && $amount_sql != '') ||
            		    (isset($email_phone_sql) && $email_phone_sql != '') ||
            		    (isset($pnr_ticket_sql) && $pnr_ticket_sql != '') ||
            		    (isset($payment_type_sql) && $payment_type_sql != '')
            		  ) 
            		{
            			$query = "SELECT 
            			            payments.auto_id, payments.order_id, payments.process_date, payments.source, payments.profile_no, 
            			            payments.trams_remarks, payments.trams_received_amount, payments.reference_no, payments.payment_method, payments.pay_type, pax.ticket_number,
            			            bookings.payment_status, pax.pnr
            			    FROM wpk4_backend_travel_payment_history payments
            			    JOIN wpk4_backend_travel_bookings bookings ON 
                                payments.order_id = bookings.order_id
                            JOIN wpk4_backend_travel_booking_pax pax ON 
                                payments.order_id = pax.order_id
            				where 
            					$payment_date_sql
            					$order_date_sql
            					$clear_date_sql
                                $payment_method_sql
                                $booking_source_sql
                                $order_id_sql
                                $profile_id_sql
                                $reference_no_sql
                                $amount_sql
                                $email_phone_sql
                                $pnr_ticket_sql
                                $payment_type_sql
            				order by payments.auto_id desc LIMIT 100";
            		}
            		else
            		{
            		    $query = "SELECT 
            		                payments.auto_id, payments.order_id, payments.process_date, payments.source, payments.profile_no, 
            			            payments.trams_remarks, payments.trams_received_amount, payments.reference_no, payments.payment_method, payments.pay_type, pax.ticket_number,
            			            bookings.payment_status, pax.pnr
            			    FROM wpk4_backend_travel_payment_history payments
            			    JOIN wpk4_backend_travel_bookings bookings ON 
                                passenger.family_id = bookings.family_id
                            JOIN wpk4_backend_travel_booking_pax pax ON 
                                payments.order_id = pax.order_id
            				where date(payments.process_date) = '$common_start_filter'
            				order by payments.auto_id desc LIMIT 100";
            			echo '</br><center><p style="color:red;">Kindly add the filters to check the records.</p></center>';
            		}
            		if( $currnt_userlogn == 'sriharshans')
            		{
            		   echo $query;	
            		}
            		$selection_query = $query;
            		$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            		$row_counter_ticket = mysqli_num_rows($result);
            		$auto_numbering = 1;
            		$total_paxs = 0;
            		
            		?>
            		</br>
            		<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
            			<thead>
                			<tr>
                    			<th>Created Date Time</th>
                    			<th>Status</th>
                    			<th>Received Amount</th>
                    			<th>Payment Method</th>
                    			<th>PNR</th>
                    			<th>Order ID</th>
                    			<th>Ticket Number</th>
                    			<th>Profile No</th>
                    			<th>Remarks</th>
                			</tr>
            			</thead>
            			<tbody>
                    	    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        		<?php
                        		$delimiter = ","; 
                                $filename = "payment_records_" . date('Y-m-dH-i-s') . ".csv"; 			 
                                $f = fopen('csv_reports/'.$filename, 'w');
                                $fields = array('Created Date Time', 'Status','Amount','PNR','Order','Profile no.','Remarks');
                                fputcsv($f, $fields, $delimiter);  
                                $processedOrders = [];
                        		while($row = mysqli_fetch_assoc($result))
                        		{
                        			$auto_id = $row['auto_id'];
                        			
                        			if (in_array($auto_id, $processedOrders)) {
                            			continue; // Skip to the next iteration if the order ID is already processed
                            		}
                            		$processedOrders[] = $auto_id;
                            							
                        			$order_id = $row['order_id'];
                        			$ticket_number = $row['ticket_number'];
                        			$process_date = date('Y-m-d', strtotime($row['process_date']));
                        		    $source = $row['source'];
                        			$profile_no = $row['profile_no'];
                        			$trams_remarks = $row['trams_remarks'];
                        			$trams_received_amount = $row['trams_received_amount'];
                        			$reference_no = $row['reference_no'];
                        			$payment_method = $row['payment_method'];
                        			$pay_type = $row['pay_type'];
                        			$payment_status = $row['payment_status'];
                        			$pnr = $row['pnr'];
                        			?>
                        			<tr>
                            			<td width='6%'>
                                            <?php echo $process_date; ?>            	
                                        </td>
                                        <td width='10%'>
                                            <?php 
                                            if($payment_status == 'pending')
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                            				else if($payment_status == 'partially_paid')
                            				{
                            					$txt_payment_status = 'Partially Paid';
                            				}
                            				else if($payment_status == 'paid')
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
                            				else if($payment_status == 'voucher_submited')
                            				{
                            					$txt_payment_status = 'Rebooked';
                            				}
                            				else if($payment_status == 'receipt_received')
                            				{
                            					$txt_payment_status = 'Receipt Received';
                            				}
                            				else
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                                            echo $txt_payment_status; 
                                            
                                            ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php echo $trams_received_amount; ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php 
                                            if( $payment_method == 'UNKNOWN' || $payment_method == 'IATA' || $payment_method == 'DIRECT' || $payment_method == 'ASIAPAY' )
                                            {
                                                $payment_method = '8';
                                            }
                                           
                                            if (ctype_digit($payment_method)) 
                                            {
                                                
                                                
                                                $query_payment_method = "SELECT account_name FROM wpk4_backend_accounts_bank_account where bank_id = $payment_method";
                                        		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                                        		$row_payment_method = mysqli_fetch_assoc($result_payment_method);
                                    		    if(mysqli_num_rows($result_payment_method) > 0)
                                    		    {
                                    		        echo $row_payment_method['account_name'];  
                                    		    }
                                    		    else
                                    		    {
                                    		        echo 'Unknown';
                                    		    }
                                            }
                                            else
                                    		{
                                    		    echo 'Unknown';
                                    		}
                                            ?>            	
                                        </td>
                                        <td width='5%'>
                                            <?php echo $pnr; ?>            	
                                        </td>
                                        <td width='5%'>
                                            <?php echo $order_id; ?>            	
                                        </td>
                                         <td width='5%'>
                                            <?php echo $ticket_number; ?>            	
                                        </td>
                                        <td width='6%'>
                                            <input type="text" value="<?php echo $profile_no; ?>" name="<?php echo $auto_id; ?>_profile_no">
                                        </td>
                                        <td width='15%'>
                                            <input type="text" value="<?php echo $trams_remarks; ?>" name="<?php echo $auto_id; ?>_trams_remarks">          	
                                        </td>
                        		    </tr>
                        			<?php
                        			$lineData = array($row['process_date'], $payment_status,  $trams_received_amount, $pnr, $order_id, $profile_no, $trams_remarks ); 
                                    fputcsv($f, $lineData, $delimiter);
                            
                        			$auto_numbering++;
                        		}

                                fseek($f, 0);
                                fpassthru($f);
                                
                        		?>
                        		<tr>
                				    <td colspan = '8'>
                				        <input type="submit" class="gtc_submission_btn" name="download_payment_report" value="Download File" style="width:180px; height:35px; font-size:11px;"/>
                        				<?php
                        			    if( current_user_can( 'administrator' ) )
                        				{
                        				?>
                        				    <input type='submit' style='float:right;padding:10px; margin:0;font-size:14px; float:right; ' name='save_all_payment_records' value='Save All'>
                        				<?php
                        				}
                        				?>
                				    </td>
                				</tr>
                    		</form>
                    	</tbody>
                    </table>
            		</br></br>
                    <?php
                    if (isset($_POST["download_payment_report"])) // to export the data
                    {
                    	echo '<script>window.location.href="https://gauratravel.com.au/csv_reports/'.$filename.'";</script>';
                    }
                    if(isset($_POST['save_all_payment_records']))
				    {
    					$query_checker = $selection_query;
    					$result_checker = mysqli_query($mysqli, $query_checker);
    					while($row_checker = mysqli_fetch_assoc($result_checker))
    					{
    						$row_auto_id = $row_checker['auto_id'];
    						$row_order_id = $row_checker['order_id'];

    						foreach($row_checker as $columnname_db => $db_value)
    						{
    							$dbcolumn_and_postname_checker = $row_auto_id.'_'.$columnname_db;
    							foreach ($_POST as $post_fieldname => $post_fieldvalue)
    							{
    								if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_checker[$columnname_db])
    								{
    									$sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
    										        $columnname_db='$post_fieldvalue', 
        										    modified_date = '$current_date_and_time', 
                                                    modified_by = '$currnt_userlogn' WHERE auto_id='$row_auto_id'";
    									$result_status= mysqli_query($mysqli,$sql_update_status);
    									
    									mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, pax_auto_id, meta_key, meta_value, meta_key_data, updated_time, updated_user) 
										values ('$row_order_id', '$row_auto_id', '$columnname_db', '$post_fieldvalue', 'Payment Update into wpk4_backend_travel_payment_history', '$current_date_and_time', '$currnt_userlogn')") or die(mysqli_error($mysqli));
    								}
    							}
    						}
    					}
                        echo '<script>window.location.href="'.$current_url.'";</script>';
					}
                }
                
                if($_GET['pg'] == 'view-less-payments' && (current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ) || current_user_can( 'ticketing_user' ) || current_user_can( 'ticketing_admin' ) || current_user_can( 'datechange_manager' ) ) )
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
		    	    <script>
                        window.addEventListener("load", function (event) {
                    	    var currentdate = new Date(); 
                    		var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                    		let drp = new DateRangePicker('payment_date',
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
                    			document.getElementById("payment_date").value = start.format() + " - " + start.format();
                            })
                        });
                    </script>
            		<script>
                    function searchordejs() 
            		{
            			var payment_date = document.getElementById("payment_date").value;
            			var booking_source = document.getElementById("booking_source").value;	
            			var order_id = document.getElementById("order_id").value;	

            			window.location='?pg=view-less-payments&payment_date=' + payment_date + '&booking_source=' + booking_source + '&order_id=' + order_id ;
            		}
            		</script>
            		<h5>View Less Payments</h5>
                	<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
                	    <tr>
                	        <td width='8%'>
                			    Booking Date</br>
                			    <input type='text' name='payment_date' value='<?php if(isset($_GET['payment_date'])) { echo substr($_GET['payment_date'], 0, 10); } ?>' id='payment_date'>
                		    </td>
                		    <td width='8%'>
                			    Booking Source</br>
                			    <select name='booking_source' id='booking_source' style="width:100%; padding:10px;">
                			        <option value="" selected>All</option>
                			        <option value="WPT" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'WPT') { echo 'selected'; } ?>>GDeals</option>
                			        <option value="gds" <?php if(isset($_GET['booking_source']) && $_GET['booking_source'] != '' && $_GET['booking_source'] == 'gds') { echo 'selected'; } ?>>GDS</option>
                			    </select>
                		    </td>
                		    <td width='8%'>
                			    Order ID</br>
                			    <input type='text' name='order_id' value='<?php if(isset($_GET['order_id'])) { echo $_GET['order_id']; } ?>' id='order_id'>
                		    </td>
                		</tr>
                		<tr>
                			<td colspan="6" style='text-align:center;'>
                				<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
                			</td>
            			</tr>
            		</table>
            		<?php
            		$common_start_filter = date('Y-m-d');

            		$payment_date = ($_GET['payment_date'] ?? false) ? substr($_GET['payment_date'], 0, 10) : '' ;
            		$booking_source = ($_GET['booking_source'] ?? false) ? $_GET['booking_source'] : '' ;
            		$order_id = ($_GET['order_id'] ?? false) ? $_GET['order_id'] : '' ;

            		if(isset($payment_date) && $payment_date != '')
            		{
            			$payment_date_sql = "date(bookings.order_date) = '".$payment_date."' AND ";
            		}
            		else
            		{
            			$payment_date_sql = "bookings.auto_id IS NOT NULL AND ";
            		}
            		
            		if(isset($booking_source) && $booking_source != '')
            		{
            			$booking_source_sql = "bookings.order_type = '".$booking_source."' AND ";
            		}
            		else
            		{
            			$booking_source_sql = "bookings.auto_id IS NOT NULL AND ";
            		}
            		
            		if(isset($order_id) && $order_id != '')
            		{
            		    $order_id_sql = "bookings.order_id = '".$order_id."' ";
            		}
            		else
            		{
            			$order_id_sql = "bookings.auto_id IS NOT NULL ";
            		}
            		
            		if(
            		    (isset($payment_date_sql) && $payment_date_sql != '') ||
            		    (isset($booking_source_sql) && $booking_source_sql != '') ||
            		    (isset($order_id_sql) && $order_id_sql != '')
            		  ) 
            		{
            			$query = "SELECT 
            			            bookings.auto_id, bookings.order_id, bookings.payment_status, bookings.order_date, bookings.order_type,  bookings.total_amount,  bookings.deposit_amount,  bookings.balance
                			    FROM wpk4_backend_travel_bookings bookings
                				where bookings.payment_status != 'paid' AND date(bookings.travel_date) > $common_start_filter AND
                					$payment_date_sql
                                    $booking_source_sql
                                    $order_id_sql
                				order by bookings.auto_id asc LIMIT 100";
            		}
            		else
            		{
            		    $query = "SELECT bookings.order_id, bookings.payment_status, bookings.order_date, bookings.order_type,  bookings.total_amount,  bookings.deposit_amount,  bookings.balance
            			        FROM wpk4_backend_travel_bookings bookings 
            			        join wpk4_backend_travel_payment_history payment 
            			        on bookings.order_id = payment.order_id
                				where bookings.payment_status != 'paid' AND date(bookings.travel_date) > $common_start_filter
                				order by bookings.auto_id asc LIMIT 100";
            			echo '</br><center><p style="color:red;">Kindly add the filters to check the records.</p></center>';
            		}
            		if( $currnt_userlogn == 'sriharshans')
            		{
            		   //echo $query;	
            		}
            		$selection_query = $query;
            		$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            		$row_counter_ticket = mysqli_num_rows($result);
            		$auto_numbering = 1;
            		$total_paxs = 0;
            		
            		?>
            		</br>
            		<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
            			<thead>
                			<tr>
                			    <th>Order ID</th>
                    			<th>Created Date Time</th>
                    			<th>Status</th>
                    			<th>Total Amount</th>
                    			<th>Received Amount</th>
                    			<th>Balance</th>
                			</tr>
            			</thead>
            			<tbody>
                    	    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        		<?php
                        		$delimiter = ","; 
                                $filename = "less_payment_records_" . date('Y-m-dH-i-s') . ".csv"; 			 
                                $f = fopen('csv_reports/'.$filename, 'w');
                                $fields = array('Order ID', 'Order Date','Status','Total Amount','Received Amount','Balance');
                                fputcsv($f, $fields, $delimiter);  
                                $processedOrders = [];
                        		while($row = mysqli_fetch_assoc($result))
                        		{
                        			$auto_id = $row['auto_id'];
                        			
                        			if (in_array($auto_id, $processedOrders)) {
                            			continue; // Skip to the next iteration if the order ID is already processed
                            		}
                            		$processedOrders[] = $auto_id;
                            							
                        			$order_id = $row['order_id'];
                        			$order_date = date('Y-m-d', strtotime($row['order_date']));
                        		    $order_type = $row['order_type'];
                        			$payment_status = $row['payment_status'];
                        			$total_amount = $row['total_amount'];
                        			$deposit_amount = $row['deposit_amount'];
                        			$balance = $row['balance'];
                        			//$pnr = $row['pnr'];
                        			
                        			if($order_type != 'gds')
                                    {
                                        $total_amount = $row['total_amount'];

                                        $wpt_coupon_code_array = get_post_meta($order_id, 'wp_travel_applied_coupon_data', true);
                                        if(isset($wpt_coupon_code_array) && $wpt_coupon_code_array != '' && isset($wpt_coupon_code_array['coupon_code']) && $wpt_coupon_code_array['coupon_code'] != '')
                                        {
                                            $ordertotal__query = $wpdb->get_results( "SELECT * FROM wpk4_postmeta where post_id='$order_id' && meta_key='order_totals'"); 
                                            foreach($ordertotal__query as $row_2){ 
                                                $order_total_amount = $row_2->meta_value;
                                            }
                                            $orderData = unserialize($order_total_amount);
                                            $total_amount = $orderData['sub_total'];
                                        }
                                            
                                    }
                                    
                                    if($order_type == 'gds')
                                    {
                                        $total_amount = get_meta_from_history_of_updates($order_id, 'Transaction TotalTurnover');
                                    }
                                    
                                    
                                    $query_pnr_for_total_paid = "SELECT * FROM wpk4_backend_travel_booking_pax where order_id='$order_id'";
                                    $result_pnr_for_total_paid = mysqli_query($mysqli, $query_pnr_for_total_paid);
                                    $row_pnr_for_total_paid = mysqli_fetch_assoc($result_pnr_for_total_paid);
                                	$received_pnr_for_total_paid = $row_pnr_for_total_paid['pnr'];
                                	
                                	$processedPaymentsPaid = array();	
                            		$query_payment_paid_amount = "SELECT * FROM wpk4_backend_travel_payment_history where (pay_type = 'deposit' OR pay_type LIKE 'balance' 
                            		OR pay_type LIKE 'Balance' OR pay_type = 'deposit_adjustment' OR pay_type = 'Refund' OR pay_type = 'additional_payment') 
                            		AND (order_id='$received_pnr_for_total_paid' OR order_id='$order_id') AND order_id != '' order by process_date desc";
                                    $result_payment_paid_amount = mysqli_query($mysqli, $query_payment_paid_amount);
                                    $total_paid_for_booking = 0;
                                    if($result_payment_paid_amount && mysqli_num_rows($result_payment_paid_amount) > 0)
                                    {
                                        while($row_payment_paid_amount = mysqli_fetch_assoc($result_payment_paid_amount))
                                        {
                                            $payment_identifier = $row_payment_paid_amount['process_date'].'^'.$row_payment_paid_amount['trams_received_amount'].'^'.$row_payment_paid_amount['reference_no']; // defined value to restric duplicate amount + ref no combination on G360
                                			if (in_array($payment_identifier, $processedPaymentsPaid)) 
                                			{
                                			    continue; // Skip to the next value
                                			}
                                				
                                			$processedPaymentsPaid[] = $payment_identifier;
                                					
                                            $total_paid_for_booking += (float)$row_payment_paid_amount['trams_received_amount'];
                                        }
                                        
                                        $balance = (float)$total_amount - (float)$total_paid_for_booking; 
                                    }
                                    if($balance <= 0 || $balance <= 0.0)
                                    {
                                        continue;
                                    }
                        			?>
                        			<tr>
                        			    <td width='6%'>
                                            <a target="_blank" href="<?php echo site_url(); ?>/manage-wp-orders/?option=search&type=reference&id=<?php echo $order_id; ?>"><?php echo $order_id; ?></a>
                                        </td>
                            			<td width='6%'>
                                            <?php echo $order_date; ?>            	
                                        </td>
                                        <td width='10%'>
                                            <?php 
                                            if($payment_status == 'pending')
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                            				else if($payment_status == 'partially_paid')
                            				{
                            					$txt_payment_status = 'Partially Paid';
                            				}
                            				else if($payment_status == 'paid')
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
                            				else if($payment_status == 'voucher_submited')
                            				{
                            					$txt_payment_status = 'Rebooked';
                            				}
                            				else if($payment_status == 'receipt_received')
                            				{
                            					$txt_payment_status = 'Receipt Received';
                            				}
                            				else
                            				{
                            					$txt_payment_status = 'Pending';
                            				}
                                            echo $txt_payment_status; 
                                            
                                            ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php 
                                            $total_amount = number_format((float)$total_amount, 2, '.', '');
                                            echo $total_amount; 
                                            ?>	
                                        </td>
                                        <td width='7%'>
                                            <?php 
                                            $total_paid_for_booking = number_format((float)$total_paid_for_booking, 2, '.', '');
                                            echo $total_paid_for_booking; 
                                            ?>
                                        </td>
                                        <td width='5%'>
                                            <?php 
                                            $balance = number_format((float)$balance, 2, '.', '');
                                            echo $balance; 
                                            ?>            	
                                        </td>
                        		    </tr>
                        			<?php
                        			$lineData = array($order_id, $order_date,  $txt_payment_status, $total_amount, $total_paid_for_booking, $balance ); 
                                    fputcsv($f, $lineData, $delimiter);
                            
                        			$auto_numbering++;
                        		}

                                fseek($f, 0);
                                fpassthru($f);
                                
                        		?>
                        		<tr>
                				    <td colspan = '8'>
                				        <input type="submit" class="gtc_submission_btn" name="download_less_payment_report" value="Download File" style="width:180px; height:35px; font-size:11px;"/>
                				    </td>
                				</tr>
                    		</form>
                    	</tbody>
                    </table>
            		</br></br>
                    <?php
                    if (isset($_POST["download_less_payment_report"])) // to export the data
                    {
                    	echo '<script>window.location.href="https://gauratravel.com.au/csv_reports/'.$filename.'";</script>';
                    }
                }
                if($_GET['pg'] == 'add-payment' && current_user_can( 'administrator' ))
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
                    <script>
                        window.addEventListener("load", function (event) {
                        var currentdate = new Date(); 
        			    var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
            			let drp = new DateRangePicker('payment_date',
                                {
                                    maxDate: end_maxtime,
                                    timePicker: false,
                                    alwaysShowCalendars: true,
                                    singleDatePicker: true,
                                    autoApply: false,
            						autoUpdateInput: true,
                                    locale: {
                                        format: "YYYY-MM-DD",
                                    }
                                },
                                function (start) {
            						document.getElementById("payment_date").value = start.format();
            						
                                })
            			});
                    </script>
                    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        <table>
                            <tbody>
                                <tr>
                                    <td width="20%">
                                        Order ID / PNR
                                        <input type="text" name="order_id" required>
                                    </td>
                                    <td width="20%">
                                        Amount
                                        <input type="text" name="payment_amount" required>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Payment Type
                                        <select name="payment_type" style="width:100%; padding:10px;">
                                            <option value="received">Received</option>
                                        </select>
                                    </td>
                                    <td>
                                        Payment Date
                                        <input type='text' name='payment_date' id='payment_date' required>
                                    </td>
                                    <td rowspan="2" style="vertical-align:top;">
                                        Remark
                                        <textarea name="payment_remark"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        Bank Acct
                                        <select name="bank_account" style="width:100%; padding:10px;">
                                            <?php
                                            $query_bank_accounts = "SELECT * FROM wpk4_backend_accounts_bank_account";
                        					$result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                            while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                    		{
                                    		    ?>
                                                <option value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                                <?php
                                    		}
                                    		?>
                                        </select>
                                    </td>
                                    <td>
                                        Payment Type
                                        <select name="pay_type" style="width:100%; padding:10px;">
                                            <option value="deposit">Deposit</option>
                                            <option value="Balance">Balance</option>
                                            <option value="Datechange">Date Change</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        Invoice Reference
                                        <input type="text" name="invoice_reference" required>
                                    </td>
                                    <td>
                                        Profile No
                                        <input type="text" name="profile_no" required></td>
                                </tr>
                                
                                <tr>
                                    <td colspan="3">
                                        <center><input type='submit' name='save_payment_information' style="padding:15px; margin:0; font-size:11px;" value='Save Payment'></center>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>                    
                    <?php
                    if(isset($_POST['save_payment_information']))
				    {
				        $order_id = $_POST['order_id'];
				        if(ctype_digit($order_id))
				        {
				            $source = 'WPT';
				        }
				        else
				        {
				            $source = 'gds';
				        }
				        
				        $previous_order_date = date("Y-m-d H:i:s");
                        $query_select_booking_order_date = "SELECT order_date FROM wpk4_backend_travel_bookings where order_id='$order_id'";
                        $result_select_booking_order_date = mysqli_query($mysqli, $query_select_booking_order_date);
                        if(mysqli_num_rows($result_select_booking_order_date) > 0)
                        {
                        	$row_select_booking_order_date = mysqli_fetch_assoc($result_select_booking_order_date);
                        	$previous_order_date = $row_select_booking_order_date['order_date'];
                        }
                        
                        $payment_refund_deadline = date('Y-m-d H:i:s', strtotime($previous_order_date . ' +96 hours'));
	


                        $payment_amount = $_POST['payment_amount'];
                        $payment_type = $_POST['payment_type'];
                        $payment_date = $_POST['payment_date'] . ' 00:00:00';
                        $payment_remark = $_POST['payment_remark'];
                        $bank_account = $_POST['bank_account'];
                        $pay_type = $_POST['pay_type'];
                        $invoice_reference = $_POST['invoice_reference'];
                        $profile_no = $_POST['profile_no'];

				        mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_history ( order_id, source, profile_no, trams_remarks, trams_received_amount, reference_no, payment_method, process_date, added_on, added_by, pay_type, payment_change_deadline ) 
						values ('$order_id', '$source', '$profile_no', '$payment_remark', '$payment_amount', '$invoice_reference', '$bank_account', '$payment_date', '$current_date_and_time', '$currnt_userlogn', '$pay_type', '$payment_refund_deadline' )") or die(mysqli_error($mysqli));
						
                        echo '<script>window.location.href="?";</script>';
				    }
                }
                if($_GET['pg'] == 'transfer-payment' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
                    <script>
                        window.addEventListener("load", function (event) {
                        var currentdate = new Date(); 
        			    var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
            			let drp = new DateRangePicker('payment_date',
                                {
                                    maxDate: end_maxtime,
                                    timePicker: false,
                                    alwaysShowCalendars: true,
                                    singleDatePicker: true,
                                    autoApply: false,
            						autoUpdateInput: true,
                                    locale: {
                                        format: "YYYY-MM-DD",
                                    }
                                },
                                function (start) {
            						document.getElementById("payment_date").value = start.format();
            						
                                })
            			});
                    </script>
                    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        <table>
                            <tbody>
                                <tr>
                                    <td>Transfer from
                                        <select name="bank_account_from" id="bank_account_from" style="width:100%; padding:10px;" required>
                                            <?php
                                            $query_bank_accounts = "SELECT bank_id, account_name FROM wpk4_backend_accounts_bank_account";
                                            $result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                            while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                            {
                                                ?>
                                                <option value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        Payment Date
                                        <input type='text' name='payment_date' id='payment_date' required>
                                    </td>
                                    <td>Transfer to
                                        <select name="bank_account_to" id="bank_account_to" style="width:100%; padding:10px;" required>
                                            <?php
                                            $query_bank_accounts = "SELECT bank_id, account_name FROM wpk4_backend_accounts_bank_account where bank_id IN (3, 12, 9)";
                                            $result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                            while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                            {
                                                ?>
                                                <option value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        Amount
                                        <input type='text' name='payment_amount' id='amount' required>
                                    </td>
                                    <td>
                                        Remark
                                        <input type='text' name='payment_remark' id='payment_date' required>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3">
                                        <center><input type='submit' name='save_payment_transfer' style="padding:15px; margin:0; font-size:11px;" value='Save Payment Transfer'></center>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>                    
                    <?php
                    if(isset($_POST['save_payment_transfer']))
				    {
				        $process_date = $_POST['payment_date'];
                        $bank_account_from = $_POST['bank_account_from'];
                        $bank_account_to = $_POST['bank_account_to'];
                        $amount = $_POST['payment_amount'];
                        $total_cleared_amount_negative = '-'.$_POST['payment_amount'];
                        $remark = $_POST['payment_remark'];
                        
                        if($bank_account_from == '12' && $bank_account_to == '12')
                        {
                            // do nothing
                        }
                        else
                        {

                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$process_date', '$bank_account_from', '$total_cleared_amount_negative', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                        }
                        
                        mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                        values ('$process_date', '$bank_account_to', '$amount', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                            
                        echo '<script>window.location.href="?pg=transfer-payment";</script>';
				    }
                }
                if($_GET['pg'] == 'bank-reconciliation' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
                    <script>
                        window.addEventListener("load", function (event) {
                            var currentdate = new Date(); 
            			    var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_from',
                                {
                                    maxDate: end_maxtime,
                                    timePicker: false,
                                    alwaysShowCalendars: true,
                                    singleDatePicker: true,
                                    autoApply: false,
            						autoUpdateInput: true,
                                    locale: {
                                        format: "YYYY-MM-DD",
                                    }
                                },
                                function (start) {
            						document.getElementById("payment_date_from").value = start.format();
            						
                                })
            			});
            			
            			window.addEventListener("load", function (event) {
            			    //var start_date = new Date(($('#payment_date_from').val()).valueOf());

                            var currentdate = new Date(); 
            			    var end_maxtime_2 = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_to',
                            {
                                maxDate: end_maxtime_2,
                                timePicker: false,
                                alwaysShowCalendars: true,
                                singleDatePicker: true,
                                autoApply: false,
            					autoUpdateInput: true,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                            function (start) {
            					document.getElementById("payment_date_to").value = start.format();
                            })
            			});
            			
            			function searchjs() {
                            var back_account = document.getElementById("back_account").value;
                            var payment_date_from = document.getElementById("payment_date_from").value;
                            var payment_date_to = document.getElementById("payment_date_to").value;
                            
                            window.location = '?pg=bank-reconciliation&bank=' + back_account + '&from=' + payment_date_from + '&to=' + payment_date_to;
                        }
                        
                        // show already cleared block start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateClearedBalance() {
                                var total = 0;
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                //$('#already_cleared_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            //$("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateClearedBalance(); // Update the total amount whenever a checkbox is clicked
                            //});
                            
                        });
                        // show already cleared block ends
                        
                        // show statement ending with full balance start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingTotalBalance() {
                                
                                var total_cleared = 0;
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total_cleared += amount;
                                    }
                                });
                                
                                if(total_cleared != 0)
                                {
                                    var total = total_cleared;
                                }
                                else
                                {
                                    var total = 0;
                                }
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_full_balance').text(total.toFixed(2));
                            }
                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance();
                            
                            // ----------------------------
                            
                            function updateEndingTotalBalance2() {
    var totalCleared = 0;

    // Iterate over each checked checkbox excluding those with the class 'bank'
    $("input[type=checkbox]:checked").not('.bank').each(function () {
        var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
        var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
        if (!isNaN(amount)) { // Check if the amount is a valid number
            totalCleared += amount;
        }
    });

    var total = totalCleared > 0 ? totalCleared : 0;

    // For each checked checkbox excluding those with the class 'bank', find the corresponding amount by ID and add it to the total
    $("input[type=checkbox]:checked").not('.bank').each(function () {
        var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
        var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
        if (!isNaN(amount)) { // Check if the amount is a valid number
            total += amount;
        }
    });

    // Optional: Alert the total cleared amount
    //alert(total.toFixed(2));

    // Update the ending balance span with the new total, formatted to two decimal places
    $('#ending_full_balance2').text(total.toFixed(2));
}

                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance2(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance2();
                            
                        });
                        // show statement ending with full balance ends
                        
                        // show statement ending with current selection starts
                        /*
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingBalance() {
                                var total = 0;
                                
                                
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                                
                                
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                        });
                        */
                        function updateBalance() {
                            const alreadyClearedBalance = parseFloat(document.getElementById('already_cleared_balance').innerText);
                            const endingFullBalance = parseFloat(document.getElementById('ending_full_balance').innerText);
                
                            const endingBalanceElement = document.getElementById('ending_balance');
                
                            setInterval(() => {
                                const newBalance = alreadyClearedBalance + endingFullBalance;
                                endingBalanceElement.innerText = newBalance.toFixed(2);
                            }, 1000);
                        }
                
                        setInterval(() => {
                                updateBalance();
                            }, 1000);
                            
                        window.onload = updateBalance;
                        
                        


                        function updateBalance2() {
                            
                
                            const endingBalanceElement = document.getElementById('ending_balance2');
                
                            setInterval(() => {
                                const alreadyClearedBalance = parseFloat(document.getElementById('ending_full_balance2').innerText);
                                const endingFullBalance = parseFloat(document.getElementById('already_cleared_balance2').innerText);
                            
                                const newBalance = alreadyClearedBalance + endingFullBalance;
                                endingBalanceElement.innerText = newBalance.toFixed(2);
                            }, 1000);
                        }
                
                        window.onload = updateBalance2;
                        // show statement ending with current selection ends
                    </script>
                    <table class="table table-striped accounts_general_table">
                        <tr>
                            <td>
                                Bank Acct
                                <select name="bank_account" id="back_account" style="width:100%; padding:10px;" required>
                                    <?php
                                    $query_bank_accounts = "SELECT bank_id, account_name FROM wpk4_backend_accounts_bank_account";
                                    $result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                    while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                    {
                                        ?>
                                        <option <?php if(isset($_GET['bank']) && $_GET['bank'] != '' && $_GET['bank'] == $row_bank_accounts['bank_id'] ) { echo 'selected'; } ?> value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                        <?php
                                    }
                                    ?>

                                </select>
                            </td>
                            <td>
                                Clearning Date from
                                <input type='text' name='payment_date_from' id='payment_date_from' required value="<?php if(isset($_GET['from']) && $_GET['from'] != '') { echo $_GET['from']; } ?>">
                            </td>
                            <td>
                                Clearning Date To
                                <input type='text' name='payment_date_to' id='payment_date_to' required value="<?php if(isset($_GET['to']) && $_GET['to'] != '') { echo $_GET['to']; } ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style='text-align:center;'>
                                <button style='padding:10px; margin:0;font-size:11px;' id='search_keys' onclick="searchjs()">Search</button>
                            </td>
                        </tr>
                    </table>
                    <?php
                    $query_parameters = '';
                    $initial_cleared_amount = 0;
                    $initial_outstanding_amount = 0;
            		if(isset($_GET['bank']) && $_GET['bank'] != '' && isset($_GET['from']) && $_GET['from'] != '' && isset($_GET['to']) && $_GET['to'] != '') 
            		{
            		    $bank_filter = $_GET['bank'];
            		    $from_filter_date = $_GET['from'];
            			$from_filter = $_GET['from'] .' 00:00:00';
            			$to_filter = $_GET['to'] .' 23:59:59';
            				    
            			$query_parameters = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter' order by process_date asc";
            			
            			$query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
            			if($bank_filter == '12')
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND date(process_date) < '$from_filter_date'";
            			}
            			else
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter'";
            			}
            			
            			$query_initial_outstanding_amount = "SELECT outstanding_amount FROM wpk4_backend_accounts_bank_account WHERE bank_id = '$bank_filter'";
                        $result_initial_outstanding_amount = mysqli_query($mysqli, $query_initial_outstanding_amount);
                        $row_initial_outstanding_amount = mysqli_fetch_assoc($result_initial_outstanding_amount);
                        $initial_outstanding_amount = $row_initial_outstanding_amount['outstanding_amount'];
            		}
            		else 
            		{
            			$query_parameters = 'LIMIT 0';
            			
            			$query_parameters_for_cleared_amount = ' LIMIT 0';
            			
            			echo '<center><p style="color:red;">Kindly add filters..</p></center>';
            		}
            		
            		
            		//$query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
            		//if($bank_filter == '12')
            		{
            	        $query_payments_received = "SELECT sum(amount) as total_cleared_amount FROM wpk4_backend_travel_payment_reconciliation $query_parameters_for_cleared_amount";
            		}
            		
            		if( $currnt_userlogn == 'sriharshans')
            		{
            			echo $query_payments_received;
            		}
                    $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            		$initial_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
            		
            		if($bank_filter == '12')
            		{    
                        ?>
                        <center>
                            <h6>Opening balance: <span id="already_cleared_balance"><?php echo $initial_cleared_amount; ?></span></h6>
                            <h6>Cleared Full Balance: <span id="ending_full_balance"><?php echo $initial_outstanding_amount; ?></span></h6>
                            <h6>Stmt. Ending Balance: <span id="ending_balance"><?php echo $initial_outstanding_amount; ?></span></h6>
                        </center>
                        <?php
            		}
            		else
            		{
            		    
            		    	$query_parameters_for_cleared_amount_2 = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
                            $query_payments_received_2 = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount_2";
                            $result_payments_received_2 = mysqli_query($mysqli, $query_payments_received_2);
                            $row_payments_received_2 = mysqli_fetch_assoc($result_payments_received_2);
                    		$initial_cleared_amount2 = number_format((float)$row_payments_received_2['total_cleared_amount'], 2, '.', '');
                		?>
                        <center>
                            <h6>Cleared Amount: <span id="ending_full_balance2"><?php echo $initial_cleared_amount2; ?></span></h6>
                            <h6>Transfered amount: <span id="already_cleared_balance2"><?php echo $initial_cleared_amount; ?></span></h6>
                            <h6>Stmt. Ending Balance: <span id="ending_balance2"><?php echo $initial_outstanding_amount; ?></span></h6>
                        </center>
                        <?php
            		}
            		?>
    				</br>
    				<form action="#" name="contactsubmit" method="post" enctype="multipart/form-data">
    				<table class="table table-striped accounts_general_table">
    					<thead>
        					<tr>
            					<th>Date</th>
            					<th>Amount</th>
            					<th>Payee</th>
            					<th>Remarks</th>
            					<th>Date cleared</th>
            					<th>PaymentNo</th>
            					<th></th>
        				    </tr>
        				</thead>
    				    <tbody>
    				        <?php
    				    if($bank_filter == '12')
            			{
    				        ?>
    				        <tr>
                    					<td><?php echo date('d/m/Y', strtotime($from_filter_date)); ?></td>
                    					<td><?php echo $initial_cleared_amount; ?></td>
                    					<td</td>
                    					<td>Opening balance</td>
                    					<td colspan="4"></td>
                				    </tr>
            				<?php
            			}
            				//if(isset($_GET['bank']) && $_GET['bank'] != '12')
                            {
                				$query_payments_received = "SELECT auto_id, order_id, cleared_date, process_date, trams_received_amount, trams_remarks, is_reconciliated FROM wpk4_backend_travel_payment_history $query_parameters";
                				if( $currnt_userlogn == 'sriharshans')
                        		{
                        			echo $query_payments_received;
                        		}
                                $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                                while($row_payments_received = mysqli_fetch_assoc($result_payments_received))
                				{
                				    $auto_id = $row_payments_received['auto_id'];
                				    $order_id = $row_payments_received['order_id'];
                				    
                				    $query_pax_name_of_booking = "SELECT fname, lname FROM wpk4_backend_travel_booking_pax WHERE order_id = '$order_id' OR pnr = '$order_id' ORDER BY auto_id LIMIT 1";
                                    $result_pax_name_of_booking = mysqli_query($mysqli, $query_pax_name_of_booking);
                                    $row_pax_name_of_booking = mysqli_fetch_assoc($result_pax_name_of_booking);
                                    $fname_pax = '';
                                    $lname_pax = '';
                                    if(mysqli_num_rows($result_pax_name_of_booking) > 0)
                                    {
                                        $fname_pax = $row_pax_name_of_booking['fname'];
                                        $lname_pax = $row_pax_name_of_booking['lname'];
                                    }
                				    ?>
            		                <tr>
                    					<td><?php echo date('d/m/Y', strtotime($row_payments_received['process_date'])); ?></td>
                    					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received['trams_received_amount']; ?></span></td>
                    					<td><?php echo ucfirst(strtolower($fname_pax)); ?> <?php echo ucfirst(strtolower($lname_pax)); ?></td>
                    					<td><?php echo $row_payments_received['trams_remarks']; ?></td>
                    					<td><?php echo $row_payments_received['cleared_date']; ?></td>
                    					<td><?php echo $order_id; ?></td>
                    					<td>
                    					   <?php
                    					   if($row_payments_received['is_reconciliated'] == 'yes') 
                    					   {
                    					       if(isset($_GET['bank']) && $_GET['bank'] == '12')
                    					       {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared2" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared2"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php    
                    					       }
                    					       else
                    					       {
                    					           ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php   
                    					       }
                    					       
                    					   }
                    					   else
                    					   {
                    					       ?>
                        					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_is_reconciliated" value="yes">
                        					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="<?php echo $row_payments_received['trams_received_amount']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="<?php echo $row_payments_received['trams_remarks']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="<?php echo $row_payments_received['process_date']; ?>">
                        					   <?php
                    					   }
                    					   ?>
                    					</td>
                				    </tr>
            		            <?php
                                }
                                echo '<tr><td colspan="7">&nbsp;</td></tr>';
                                $reconcile_from = $_GET['from'];
                                $reconcile_to = $_GET['to'];
                                $query_payments_received_from_reconcile = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '$bank_filter' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile = mysqli_query($mysqli, $query_payments_received_from_reconcile);
                                if(mysqli_num_rows($result_payments_received_from_reconcile) > 0)
                                {
                                    while($row_payments_received_from_reconcile = mysqli_fetch_assoc($result_payments_received_from_reconcile))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile['amount']; ?></span></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" class="bank" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" class="bank" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
                            if(isset($_GET['bank']) && $_GET['bank'] == '12' && 1 == 2)
                            {
                                $reconcile_from = $_GET['from'];
                                $reconcile_to = $_GET['to'];
                                $query_payments_received_from_reconcile_cba = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '12' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile_cba = mysqli_query($mysqli, $query_payments_received_from_reconcile_cba);
                                if(mysqli_num_rows($result_payments_received_from_reconcile_cba) > 0)
                                {
                                    while($row_payments_received_from_reconcile_cba = mysqli_fetch_assoc($result_payments_received_from_reconcile_cba))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile_cba['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile_cba['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile_cba['amount']; ?></span></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile_cba['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile_cba['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
        		            ?>
        		            <tr>
        		                <td colspan="7">
        		                    <?php 
        		                    //if(isset($_GET['bank']) && $_GET['bank'] == '12') 
        		                    { 
            		                    ?>
            		                    &nbsp;<input type='submit' style='float:right;padding:10px; margin:0;font-size:14px; margin-left:10px;' name='save_final_selected_payments' value='Reconsile CBA / Negative Selected'>&nbsp;
            		                    <?php
        		                    }
        		                    //if(isset($_GET['bank']) && $_GET['bank'] != '12') 
        		                    {
            		                    ?>
            		                    &nbsp;<input type='submit' name='save_selected_payments' style='float:right;padding:10px; margin:0;font-size:14px; ' value='Reconsile customer payments selected'>&nbsp;
            		                    <?php
        		                    }
        		                    ?>
        		                </td>
        		            </tr>
        				</tbody>
    				</table>
    				</form>
				    <?php
				    if(isset($_POST['save_selected_payments']))
				    {
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];
                            $row_order_id = $row_get_payments['order_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET $columnname_db='$post_fieldvalue', cleared_date = '$current_date_and_time', cleared_by = '$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";

                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                        mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
                                        values ('$row_order_id','$row_auto_id_pay','','$columnname_db','$post_fieldvalue','$current_date_and_time','$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        
                                        if($_GET['bank'] == '12')
                                        {
                                            $transfering_amount_field = $_POST[$row_auto_id_pay.'_transfer_amount'];
                                            $transfering_remark_field = $_POST[$row_auto_id_pay.'_transfer_remark'];
                                            $transfering_date_field = $_POST[$row_auto_id_pay.'_transfer_date'];
                                            $current_date = date("Y-m-d");
                                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                                            values ('$transfering_date_field', '12', '$transfering_amount_field', '$transfering_remark_field', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        }            
                                    }
                                }
                            }
                        }
                        
                        
                        $query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        $row_payments_received = mysqli_fetch_assoc($result_payments_received);
                    	$total_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
                    	$total_cleared_amount_negative = '-'.$total_cleared_amount;
                    	
                    	$bank_id = $_GET['bank'];
                    	$date_from = $_GET['from'];
                    	$date_to = $_GET['to'];
                    	$remark = 'Cleared amount for '.$_GET['bank'] . ' from ' . $_GET['from'] . ' to ' . $_GET['to'] . ' on '. date("Y-m-d");
                    	/*
                        $query_payments_received = "SELECT auto_id FROM wpk4_backend_travel_payment_reconciliation $query_parameters";
                        echo $query_payments_received.'</br>';
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        if(mysqli_num_rows($result_payments_received) != 0)
            			{
            			    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            			    $auto_id = $row_payments_received['auto_id'];
            			    
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount_negative', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '$bank_id'";
                            
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '12'";
                                
                            $result_status= mysqli_query($mysqli,$sql_update_status);
                            echo 'UPDATE wpk4_backend_travel_payment_reconciliation SET amount='.$total_cleared_amount_negative.', remark='.$remark.', added_by = '.$currnt_userlogn.' WHERE auto_id = '.$auto_id.'</br>';
                                
            			}
            			else
            			{
            			    mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '$bank_id', '$total_cleared_amount_negative', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                            
                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '12', '$total_cleared_amount', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                
                            echo 'insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) values ('.$date_to.', '.$bank_id.', '.$total_cleared_amount_negative.', '.$remark.', '.$currnt_userlogn.')</br>';
                        }
                        */
                        
                        echo '<script>window.location.href="'.$current_url.'";</script>';
                    }
                    
				    if(isset($_POST['save_final_selected_payments']))
				    {
				        $query_payments_received = "SELECT * FROM wpk4_backend_travel_payment_reconciliation $query_parameters";
				        echo $query_payments_received.'</br></br>';
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    echo $post_fieldname .'=='. $dbcolumn_and_postname_checker .'&&'. $post_fieldvalue .'!='. $row_get_payments[$columnname_db].'</br></br>';
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        //echo $dbcolumn_and_postname_checker.'</br></br>';
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET $columnname_db='$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";
                                                    
                                                   // echo $sql_update_status;
                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                                    
                                    }
                                }
                            }
                        }
                    	
                        echo '<script>window.location.href="'.$current_url.'";</script>';
            		    
                    }
                }
                
                if($_GET['pg'] == 'bank-reconciliation-view' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
                    <script>
                        window.addEventListener("load", function (event) {
                            var currentdate = new Date(); 
            			    var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_from',
                                {
                                    maxDate: end_maxtime,
                                    timePicker: false,
                                    alwaysShowCalendars: true,
                                    singleDatePicker: true,
                                    autoApply: false,
            						autoUpdateInput: true,
                                    locale: {
                                        format: "YYYY-MM-DD",
                                    }
                                },
                                function (start) {
            						document.getElementById("payment_date_from").value = start.format();
            						
                                })
            			});
            			
            			window.addEventListener("load", function (event) {
            			    //var start_date = new Date(($('#payment_date_from').val()).valueOf());

                            var currentdate = new Date(); 
            			    var end_maxtime_2 = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_to',
                            {
                                maxDate: end_maxtime_2,
                                timePicker: false,
                                alwaysShowCalendars: true,
                                singleDatePicker: true,
                                autoApply: false,
            					autoUpdateInput: true,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                            function (start) {
            					document.getElementById("payment_date_to").value = start.format();
                            })
            			});
            			
            			function searchjs() {
                            var back_account = document.getElementById("back_account").value;
                            var payment_date_from = document.getElementById("payment_date_from").value;
                            var payment_date_to = document.getElementById("payment_date_to").value;
                            
                            window.location = '?pg=bank-reconciliation-view&bank=' + back_account + '&from=' + payment_date_from + '&to=' + payment_date_to;
                        }
                        
                        // show already cleared block start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateClearedBalance() {
                                var total = 0;
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                //$('#already_cleared_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            //$("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateClearedBalance(); // Update the total amount whenever a checkbox is clicked
                            //});
                            
                        });
                        // show already cleared block ends
                        
                        // show statement ending with full balance start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingTotalBalance() {
                                
                                var total_cleared = 0;
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total_cleared += amount;
                                    }
                                });
                                
                                if(total_cleared > 0)
                                {
                                    var total = total_cleared;
                                }
                                else
                                {
                                    var total = 0;
                                }
                                
                                var total = total_cleared;
                                
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_full_balance').text(total.toFixed(2));
                            }
                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance();
                            
                            // ----------------------------
                            
                            function updateEndingTotalBalance2() {
                                var totalCleared = 0;
                            
                                // Iterate over each checked checkbox excluding those with the class 'bank'
                                $("input[type=checkbox]:checked").not('.bank').each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        totalCleared += amount;
                                    }
                                });
                            
                                var total = totalCleared > 0 ? totalCleared : 0;
                            
                                // For each checked checkbox excluding those with the class 'bank', find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").not('.bank').each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                            
                                // Update the ending balance span with the new total, formatted to two decimal places
                                $('#ending_full_balance2').text(total.toFixed(2));
                            }

                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance2(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance2();
                            
                        });
                        // show statement ending with full balance ends
                        
                        // show statement ending with current selection starts
                        /*
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingBalance() {
                                var total = 0;
                                
                                
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                                
                                
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                        });
                        */
                        
                        function updateBalance() {
                            //const endingBalanceElement2 = document.getElementById('ending_balance1');

                            setInterval(() => {
                                const alreadyClearedBalance = parseFloat(document.getElementById('already_cleared_balance').innerText);
                                const endingFullBalance = parseFloat(document.getElementById('ending_full_balance').innerText);
                                const newBalance1 = alreadyClearedBalance + endingFullBalance;
                                const newBalance2 = newBalance1.toFixed(2);
                                
                                const endingBalanceElement2 = document.getElementById('ending_balance1'); // Ensure this references the correct element
                                
                                endingBalanceElement2.innerText = newBalance2;
                                
                            }, 2000);

                        }
                        updateBalance();
                        
                        
                        
                        
                        


                        function updateBalance2() {
                            
                
                            const endingBalanceElement = document.getElementById('ending_balance2');
                
                            setInterval(() => {
                                const PreviousReconciliatedBalance = parseFloat(document.getElementById('previous_ending_full_balance2').innerText);
                                const alreadyClearedBalance = parseFloat(document.getElementById('ending_full_balance2').innerText);
                                const endingFullBalance = parseFloat(document.getElementById('already_cleared_balance2').innerText);
                            
                                const newBalance = PreviousReconciliatedBalance + alreadyClearedBalance + endingFullBalance;
                                endingBalanceElement.innerText = newBalance.toFixed(2);
                            }, 1000);
                        }
                
                        window.onload = updateBalance2;
                        // show statement ending with current selection ends
                    </script>
                    <table class="table table-striped accounts_general_table">
                        <tr>
                            <td>
                                Bank Acct
                                <select name="bank_account" id="back_account" style="width:100%; padding:10px;" required>
                                    <?php
                                    $query_bank_accounts = "SELECT bank_id, account_name FROM wpk4_backend_accounts_bank_account";
                                    $result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                    while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                    {
                                        ?>
                                        <option <?php if(isset($_GET['bank']) && $_GET['bank'] != '' && $_GET['bank'] == $row_bank_accounts['bank_id'] ) { echo 'selected'; } ?> value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                        <?php
                                    }
                                    ?>

                                </select>
                            </td>
                            <td>
                                Clearning Date from
                                <input type='text' name='payment_date_from' id='payment_date_from' required value="<?php if(isset($_GET['from']) && $_GET['from'] != '') { echo $_GET['from']; } ?>">
                            </td>
                            <td>
                                Clearning Date To
                                <input type='text' name='payment_date_to' id='payment_date_to' required value="<?php if(isset($_GET['to']) && $_GET['to'] != '') { echo $_GET['to']; } ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style='text-align:center;'>
                                <button style='padding:10px; margin:0;font-size:11px;' id='search_keys' onclick="searchjs()">Search</button>
                            </td>
                        </tr>
                    </table>
                    <?php
                    $bank_filter = '';
                    $from_filter = date("Y-m-d").' 00:00:00';
            		$to_filter = date("Y-m-d").' 23:59:59';
            		$from_filter_date = date("Y-m-d");
            		
                    $query_parameters = '';
                    $initial_cleared_amount = 0;
                    $initial_outstanding_amount = 0;
            		if(isset($_GET['bank']) && $_GET['bank'] != '' && isset($_GET['from']) && $_GET['from'] != '' && isset($_GET['to']) && $_GET['to'] != '') 
            		{
            		    $bank_filter = $_GET['bank'];
            		    $from_filter_date = $_GET['from'];
            			$from_filter = $_GET['from'] .' 00:00:00';
            			$to_filter = $_GET['to'] .' 23:59:59';
            				    
            			$query_parameters = "WHERE payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter' order by process_date asc";
            			//$query_parameters = "WHERE (payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter') OR (trams_received_amount != '0' AND payment_method = '$bank_filter' AND process_date BETWEEN '2024-07-01 00:00:00' AND '$to_filter' AND cleared_date IS NULL) order by process_date asc LIMIT 300";
            			
            			
            			$query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
            			if($bank_filter == '12')
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND date(process_date) < '$from_filter_date'";
            			}
            			else
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter'";
            			}
            			
            			$query_initial_outstanding_amount = "SELECT outstanding_amount FROM wpk4_backend_accounts_bank_account WHERE bank_id = '$bank_filter'";
                        $result_initial_outstanding_amount = mysqli_query($mysqli, $query_initial_outstanding_amount);
                        $row_initial_outstanding_amount = mysqli_fetch_assoc($result_initial_outstanding_amount);
                        $initial_outstanding_amount = $row_initial_outstanding_amount['outstanding_amount'];
            		}
            		else 
            		{
            			$query_parameters = 'LIMIT 0';
            			
            			$query_parameters_for_cleared_amount = ' LIMIT 0';
            			
            			echo '<center><p style="color:red;">Kindly add filters..</p></center>';
            		}
            		
            		
            		//$query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
            		//if($bank_filter == '12')
            		{
            	        $query_payments_received = "SELECT sum(amount) as total_cleared_amount FROM wpk4_backend_travel_payment_reconciliation $query_parameters_for_cleared_amount";
            		}
            		
            		if( $currnt_userlogn == 'sriharshans')
            		{
            			//echo $query_payments_received;
            		}
                    $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            		$initial_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
            		
            		if($bank_filter == '12')
            		{    
                        ?>
                        <center>
                            <h6>Opening balance: <span id="already_cleared_balance"><?php echo $initial_cleared_amount; ?></span></h6>
                            <h6>Cleared Full Balance: <span id="ending_full_balance"></span></h6>
                            <h6>Stmt. Ending Balance: <span id="ending_balance1"></span></h6>
                        </center>
                        <?php
            		}
            		else
            		{
            		    
            		    	$query_parameters_for_cleared_amount_2 = "WHERE payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
                            $query_payments_received_2 = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount_2";
                            //echo $query_payments_received_2;
                            $result_payments_received_2 = mysqli_query($mysqli, $query_payments_received_2);
                            $row_payments_received_2 = mysqli_fetch_assoc($result_payments_received_2);
                    		$initial_cleared_amount2 = number_format((float)$row_payments_received_2['total_cleared_amount'], 2, '.', '');
                    		
                    		
                    		$query_parameters_for_previous_day = "WHERE payment_method = '$bank_filter' AND date(process_date) < '$from_filter_date'";
                            $query_payments_for_previous_day = "SELECT sum(amount) as total_cleared_amount FROM wpk4_backend_travel_payment_reconciliation $query_parameters_for_previous_day";
                            $result_payments_for_previous_day = mysqli_query($mysqli, $query_payments_for_previous_day);
                            $row_payments_for_previous_day = mysqli_fetch_assoc($result_payments_for_previous_day);
                    		$initial_cleared_amount_for_previous_day = number_format((float)$row_payments_for_previous_day['total_cleared_amount'], 2, '.', '');
                    		
                    		$query_parameters_history_for_previous_day = "WHERE payment_method = '$bank_filter' AND date(cleared_date) < '$from_filter_date'";
                            $query_payments_history_for_previous_day = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_history_for_previous_day AND cleared_date IS NOT NULL ";
                            $result_payments_history_for_previous_day = mysqli_query($mysqli, $query_payments_history_for_previous_day);
                            $row_payments_history_for_previous_day = mysqli_fetch_assoc($result_payments_history_for_previous_day);
                    		$initial_history_amount_for_previous_day = number_format((float)$row_payments_history_for_previous_day['total_cleared_amount'], 2, '.', '');
                		?>
                        <center>
                            <?php //echo $query_payments_for_previous_day. ' -> ' . $initial_cleared_amount_for_previous_day; ?>
                            </br>
                            <?php //echo $query_payments_history_for_previous_day . ' -> ' . $initial_history_amount_for_previous_day; ?>
                            
                            <h6>Previous day closing balance: <span id="previous_ending_full_balance2"><?php echo $initial_cleared_amount_for_previous_day + $initial_history_amount_for_previous_day; ?></span></h6>
                            
                            <?php //echo $query_payments_received_2; ?>
                            <h6>Cleared Amount: <span id="ending_full_balance2"><?php echo $initial_cleared_amount2; ?></span></h6>
                            
                            <?php //echo $query_payments_received; ?>
                            <h6>Transfered amount: <span id="already_cleared_balance2"><?php echo $initial_cleared_amount; ?></span></h6>
                            
                            <h6>Stmt. Ending Balance: <span id="ending_balance2"><?php echo $initial_outstanding_amount; ?></span></h6>
                        </center>
                        <?php
            		}
            		?>
    				</br>
    				<form action="#" name="contactsubmit" method="post" enctype="multipart/form-data">
    				<table class="table table-striped accounts_general_table">
    					<thead>
        					<tr>
            					<th>Date</th>
            					<th>Amount</th>
            					<th>Payee</th>
            					<th>Reference</th>
            					<th>Remarks</th>
            					<th>Date cleared</th>
            					<th>PaymentNo</th>
            					<th></th>
        				    </tr>
        				</thead>
    				    <tbody>
    				        <?php
    				    if($bank_filter == '12')
            			{
    				        ?>
    				        <tr>
                    					<td><?php echo date('d/m/Y', strtotime($from_filter_date)); ?></td>
                    					<td><?php echo $initial_cleared_amount; ?></td>
                    					<td</td>
                    					<td>Opening balance</td>
                    					<td colspan="4"></td>
                				    </tr>
            				<?php
            			}
            				if(isset($_GET['bank']) && $_GET['bank'] == '12')
            				{
            				    $query_parameters = str_replace("cleared_date", "process_date", $query_parameters);
            				}
            				
                            {
                				$query_payments_received = "SELECT auto_id, order_id, cleared_date, process_date, trams_received_amount, trams_remarks, is_reconciliated, reference_no FROM wpk4_backend_travel_payment_history $query_parameters";
                                //echo $query_payments_received;
                                if( $currnt_userlogn == 'sriharshans')
                        		{
                        			//echo $query_payments_received;
                        		}
                                $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                                while($row_payments_received = mysqli_fetch_assoc($result_payments_received))
                				{
                				    
                				    $auto_id = $row_payments_received['auto_id'];
                				    $order_id = $row_payments_received['order_id'];
                				    
                				    $first_name = 'Not found';
                                    $last_name = 'Not found';
                				    $query_pax_name_of_booking = "SELECT fname, lname FROM wpk4_backend_travel_booking_pax WHERE order_id = '$order_id' OR pnr = '$order_id' ORDER BY auto_id LIMIT 1";
                                    $result_pax_name_of_booking = mysqli_query($mysqli, $query_pax_name_of_booking);
                                    $row_pax_name_of_booking = mysqli_fetch_assoc($result_pax_name_of_booking);
                                    if(mysqli_fetch_assoc($result_pax_name_of_booking) > 0)
                                    {
                                        $first_name = $row_pax_name_of_booking['fname'];
                                        $last_name = $row_pax_name_of_booking['lname'];
                                    }
                				    ?>
            		                <tr>
                    					<td><?php echo date('d/m/Y', strtotime($row_payments_received['process_date'])); ?></td>
                    					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received['trams_received_amount']; ?></span></td>
                    					<td><?php echo ucfirst(strtolower($first_name)); ?> <?php echo ucfirst(strtolower($last_name)); ?></td>
                    					<td><?php echo $row_payments_received['reference_no']; ?></td>
                    					<td><?php echo $row_payments_received['trams_remarks']; ?></td>
                    					<td><?php echo $row_payments_received['cleared_date']; ?></td>
                    					<td><?php echo $order_id; ?></td>
                    					<td>
                    					   <?php
                    					   if($row_payments_received['is_reconciliated'] == 'yes') 
                    					   {
                    					       if(isset($_GET['bank']) && $_GET['bank'] == '12')
                    					       {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared2" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared2"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php    
                    					       }
                    					       else
                    					       {
                    					           ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php   
                    					       }
                    					       
                    					   }
                    					   else
                    					   {
                    					       ?>
                        					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_is_reconciliated" value="yes">
                        					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="<?php echo $row_payments_received['trams_received_amount']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="<?php echo $row_payments_received['trams_remarks']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="<?php echo $row_payments_received['process_date']; ?>">
                        					   <?php
                    					   }
                    					   ?>
                    					</td>
                				    </tr>
            		            <?php
                                }
                                echo '<tr><td colspan="7">&nbsp;</td></tr>';
                                if(isset($_GET['from']) && isset($_GET['to']))
                                {
                                    $reconcile_from = $_GET['from'];
                                    $reconcile_to = $_GET['to'];
                                }
                                else
                                {
                                    $reconcile_from = date("Y-m-d");
                                    $reconcile_to = date("Y-m-d");
                                }
                                $query_payments_received_from_reconcile = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '$bank_filter' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile = mysqli_query($mysqli, $query_payments_received_from_reconcile);
                                if(mysqli_num_rows($result_payments_received_from_reconcile) > 0)
                                {
                                    while($row_payments_received_from_reconcile = mysqli_fetch_assoc($result_payments_received_from_reconcile))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile['amount']; ?></span></td>
                        					<td></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" class="bank" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" class="bank" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
                            if(isset($_GET['bank']) && $_GET['bank'] == '12' && 1 == 2)
                            {
                                $reconcile_from = $_GET['from'];
                                $reconcile_to = $_GET['to'];
                                $query_payments_received_from_reconcile_cba = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '12' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile_cba = mysqli_query($mysqli, $query_payments_received_from_reconcile_cba);
                                if(mysqli_num_rows($result_payments_received_from_reconcile_cba) > 0)
                                {
                                    while($row_payments_received_from_reconcile_cba = mysqli_fetch_assoc($result_payments_received_from_reconcile_cba))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile_cba['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile_cba['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile_cba['amount']; ?></span></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile_cba['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile_cba['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
        		            ?>
        		            <tr>
        		                <td colspan="7">
        		                    <?php 
        		                    //if(isset($_GET['bank']) && $_GET['bank'] == '12') 
        		                    { 
            		                    ?>
            		                    &nbsp;<input type='submit' style='float:right;padding:10px; margin:0;font-size:14px; margin-left:10px;' name='save_final_selected_payments' value='Reconsile CBA / Negative Selected'>&nbsp;
            		                    <?php
        		                    }
        		                    //if(isset($_GET['bank']) && $_GET['bank'] != '12') 
        		                    {
            		                    ?>
            		                    &nbsp;<input type='submit' name='save_selected_payments' style='float:right;padding:10px; margin:0;font-size:14px; ' value='Reconsile customer payments selected'>&nbsp;
            		                    <?php
        		                    }
        		                    ?>
        		                </td>
        		            </tr>
        				</tbody>
    				</table>
    				</form>
				    <?php
				    if(isset($_POST['save_selected_payments']))
				    {
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];
                            $row_order_id = $row_get_payments['order_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET $columnname_db='$post_fieldvalue', cleared_date = '$current_date_and_time', cleared_by = '$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";

                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                        mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
                                        values ('$row_order_id','$row_auto_id_pay','','$columnname_db','$post_fieldvalue','$current_date_and_time','$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        
                                        if($_GET['bank'] == '12')
                                        {
                                            $transfering_amount_field = $_POST[$row_auto_id_pay.'_transfer_amount'];
                                            $transfering_remark_field = $_POST[$row_auto_id_pay.'_transfer_remark'];
                                            $transfering_date_field = $_POST[$row_auto_id_pay.'_transfer_date'];
                                            $current_date = date("Y-m-d");
                                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                                            values ('$transfering_date_field', '12', '$transfering_amount_field', '$transfering_remark_field', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        }            
                                    }
                                }
                            }
                        }
                        
                        
                        $query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        $row_payments_received = mysqli_fetch_assoc($result_payments_received);
                    	$total_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
                    	$total_cleared_amount_negative = '-'.$total_cleared_amount;
                    	
                    	$bank_id = $_GET['bank'];
                    	$date_from = $_GET['from'];
                    	$date_to = $_GET['to'];
                    	$remark = 'Cleared amount for '.$_GET['bank'] . ' from ' . $_GET['from'] . ' to ' . $_GET['to'] . ' on '. date("Y-m-d");
                    	/*
                        $query_payments_received = "SELECT auto_id FROM wpk4_backend_travel_payment_reconciliation $query_parameters";
                        echo $query_payments_received.'</br>';
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        if(mysqli_num_rows($result_payments_received) != 0)
            			{
            			    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            			    $auto_id = $row_payments_received['auto_id'];
            			    
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount_negative', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '$bank_id'";
                            
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '12'";
                                
                            $result_status= mysqli_query($mysqli,$sql_update_status);
                            echo 'UPDATE wpk4_backend_travel_payment_reconciliation SET amount='.$total_cleared_amount_negative.', remark='.$remark.', added_by = '.$currnt_userlogn.' WHERE auto_id = '.$auto_id.'</br>';
                                
            			}
            			else
            			{
            			    mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '$bank_id', '$total_cleared_amount_negative', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                            
                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '12', '$total_cleared_amount', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                
                            echo 'insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) values ('.$date_to.', '.$bank_id.', '.$total_cleared_amount_negative.', '.$remark.', '.$currnt_userlogn.')</br>';
                        }
                        */
                        
                        echo '<script>window.location.href="'.$current_url.'";</script>';
                    }
                    
				    if(isset($_POST['save_final_selected_payments']))
				    {
				        $query_parameters2 = str_replace("cleared_date", "process_date", $query_parameters);
				        $query_payments_received = "SELECT * FROM wpk4_backend_travel_payment_reconciliation $query_parameters2";
				        echo $query_payments_received.'</br></br>';
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    echo $post_fieldname .'=='. $dbcolumn_and_postname_checker .'&&'. $post_fieldvalue .'!='. $row_get_payments[$columnname_db].'</br></br>';
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        //echo $dbcolumn_and_postname_checker.'</br></br>';
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET $columnname_db='$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";
                                                    
                                                   // echo $sql_update_status;
                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                                    
                                    }
                                }
                            }
                        }
                    	
                        echo '<script>window.location.href="'.$current_url.'";</script>';
            		    
                    }
                }
                

                if($_GET['pg'] == 'bank-reconciliation-view2-removed' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    ?>
                    <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
                    <script>
                        window.addEventListener("load", function (event) {
                            var currentdate = new Date(); 
            			    var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_from',
                                {
                                    maxDate: end_maxtime,
                                    timePicker: false,
                                    alwaysShowCalendars: true,
                                    singleDatePicker: true,
                                    autoApply: false,
            						autoUpdateInput: true,
                                    locale: {
                                        format: "YYYY-MM-DD",
                                    }
                                },
                                function (start) {
            						document.getElementById("payment_date_from").value = start.format();
            						
                                })
            			});
            			
            			window.addEventListener("load", function (event) {
            			    //var start_date = new Date(($('#payment_date_from').val()).valueOf());

                            var currentdate = new Date(); 
            			    var end_maxtime_2 = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                			let drp = new DateRangePicker('payment_date_to',
                            {
                                maxDate: end_maxtime_2,
                                timePicker: false,
                                alwaysShowCalendars: true,
                                singleDatePicker: true,
                                autoApply: false,
            					autoUpdateInput: true,
                                locale: {
                                    format: "YYYY-MM-DD",
                                }
                            },
                            function (start) {
            					document.getElementById("payment_date_to").value = start.format();
                            })
            			});
            			
            			function searchjs() {
                            var back_account = document.getElementById("back_account").value;
                            var payment_date_from = document.getElementById("payment_date_from").value;
                            var payment_date_to = document.getElementById("payment_date_to").value;
                            
                            window.location = '?pg=bank-reconciliation-view2&bank=' + back_account + '&from=' + payment_date_from + '&to=' + payment_date_to;
                        }
                        
                        // show already cleared block start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateClearedBalance() {
                                var total = 0;
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                //$('#already_cleared_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            //$("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateClearedBalance(); // Update the total amount whenever a checkbox is clicked
                            //});
                            
                        });
                        // show already cleared block ends
                        
                        // show statement ending with full balance start
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingTotalBalance() {
                                
                                var total_cleared = 0;
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total_cleared += amount;
                                    }
                                });
                                
                                if(total_cleared > 0)
                                {
                                    var total = total_cleared;
                                }
                                else
                                {
                                    var total = 0;
                                }
                                var total = total_cleared;
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_full_balance').text(total.toFixed(2));
                            }
                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance();
                            
                            // ----------------------------
                            
                            function updateEndingTotalBalance2() {
                                var totalCleared = 0;
                            
                                // Iterate over each checked checkbox excluding those with the class 'bank'
                                $("input[type=checkbox]:checked").not('.bank').each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile_cleared', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        totalCleared += amount;
                                    }
                                });
                            
                                var total = totalCleared > 0 ? totalCleared : 0;
                            
                                // For each checked checkbox excluding those with the class 'bank', find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").not('.bank').each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                            
                                // Update the ending balance span with the new total, formatted to two decimal places
                                $('#ending_full_balance2').text(total.toFixed(2));
                            }

                        
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingTotalBalance2(); // Update the total amount whenever a checkbox is clicked
                            });
                            updateEndingTotalBalance2();
                            
                        });
                        // show statement ending with full balance ends
                        
                        // show statement ending with current selection starts
                        /*
                        $(document).ready(function () {
                            // Function to calculate and update the total amount
                            function updateEndingBalance() {
                                var total = 0;
                                
                                
                                // For each checked checkbox, find the corresponding amount by ID and add it to the total
                                $("input[type=checkbox]:checked").each(function () {
                                    var amountId = $(this).attr('id').replace('_reconcile', '_trams_received_amount'); // Construct the amount ID
                                    var amount = parseFloat($('#' + amountId).text().replace(',', '')); // Get the amount from the span with the constructed ID, remove comma if formatted as number with comma
                                    if (!isNaN(amount)) { // Check if the amount is a valid number
                                        total += amount;
                                    }
                                });
                                
                                
                        
                                // Update the ending balance span with the new total, format it to two decimal places
                                $('#ending_balance').text(total.toFixed(2));
                            }
                        
                            // Attach the event listener to all checkboxes with the name ending in '_is_reconciliated'
                            $("input[type=checkbox][name$='_is_reconciliated']").on('click', function () {
                                updateEndingBalance(); // Update the total amount whenever a checkbox is clicked
                            });
                        });
                        */
                        
                        function updateBalance() {
                            //const endingBalanceElement2 = document.getElementById('ending_balance1');

                            setInterval(() => {
                                const alreadyClearedBalance = parseFloat(document.getElementById('already_cleared_balance').innerText);
                                const endingFullBalance = parseFloat(document.getElementById('ending_full_balance').innerText);
                                const newBalance1 = alreadyClearedBalance + endingFullBalance;
                                const newBalance2 = newBalance1.toFixed(2);
                                
                                const endingBalanceElement2 = document.getElementById('ending_balance1'); // Ensure this references the correct element
                                
                                endingBalanceElement2.innerText = newBalance2;
                                
                            }, 2000);

                        }
                        updateBalance();
                        
                        
                        
                        
                        


                        function updateBalance2() {
                            
                
                            const endingBalanceElement = document.getElementById('ending_balance2');
                
                            setInterval(() => {
                                const PreviousReconciliatedBalance = parseFloat(document.getElementById('previous_ending_full_balance2').innerText);
                                const alreadyClearedBalance = parseFloat(document.getElementById('ending_full_balance2').innerText);
                                const endingFullBalance = parseFloat(document.getElementById('already_cleared_balance2').innerText);
                            
                                const newBalance = PreviousReconciliatedBalance + alreadyClearedBalance + endingFullBalance;
                                endingBalanceElement.innerText = newBalance.toFixed(2);
                            }, 1000);
                        }
                
                        window.onload = updateBalance2;
                        // show statement ending with current selection ends
                    </script>
                    <table class="table table-striped accounts_general_table">
                        <tr>
                            <td>
                                Bank Acct
                                <select name="bank_account" id="back_account" style="width:100%; padding:10px;" required>
                                    <?php
                                    $query_bank_accounts = "SELECT bank_id, account_name FROM wpk4_backend_accounts_bank_account";
                                    $result_bank_accounts = mysqli_query($mysqli, $query_bank_accounts);
                                    while($row_bank_accounts = mysqli_fetch_assoc($result_bank_accounts))
                                    {
                                        ?>
                                        <option <?php if(isset($_GET['bank']) && $_GET['bank'] != '' && $_GET['bank'] == $row_bank_accounts['bank_id'] ) { echo 'selected'; } ?> value="<?php echo $row_bank_accounts['bank_id']; ?>"><?php echo $row_bank_accounts['bank_id']; ?> <?php echo $row_bank_accounts['account_name']; ?></option>
                                        <?php
                                    }
                                    ?>

                                </select>
                            </td>
                            <td>
                                Clearning Date from
                                <input type='text' name='payment_date_from' id='payment_date_from' required value="<?php if(isset($_GET['from']) && $_GET['from'] != '') { echo $_GET['from']; } ?>">
                            </td>
                            <td>
                                Clearning Date To
                                <input type='text' name='payment_date_to' id='payment_date_to' required value="<?php if(isset($_GET['to']) && $_GET['to'] != '') { echo $_GET['to']; } ?>">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style='text-align:center;'>
                                <button style='padding:10px; margin:0;font-size:11px;' id='search_keys' onclick="searchjs()">Search</button>
                            </td>
                        </tr>
                    </table>
                    <?php
                    $query_parameters = '';
                    $initial_cleared_amount = 0;
                    $initial_outstanding_amount = 0;
            		if(isset($_GET['bank']) && $_GET['bank'] != '' && isset($_GET['from']) && $_GET['from'] != '' && isset($_GET['to']) && $_GET['to'] != '') 
            		{
            		    $bank_filter = $_GET['bank'];
            		    $from_filter_date = $_GET['from'];
            			$from_filter = $_GET['from'] .' 00:00:00';
            			$to_filter = $_GET['to'] .' 23:59:59';
            				    
            			$query_parameters = "WHERE payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter' order by process_date asc";
            			//$query_parameters = "WHERE (payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter') OR (trams_received_amount != '0' AND payment_method = '$bank_filter' AND process_date BETWEEN '2024-07-01 00:00:00' AND '$to_filter' AND cleared_date IS NULL) order by process_date asc LIMIT 300";
            			
            			
            			$query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
            			if($bank_filter == '12')
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND date(process_date) < '$from_filter_date'";
            			}
            			else
            			{
            			    $query_parameters_for_cleared_amount = "WHERE payment_method = '$bank_filter' AND process_date BETWEEN '$from_filter' AND '$to_filter'";
            			}
            			
            			$query_initial_outstanding_amount = "SELECT outstanding_amount FROM wpk4_backend_accounts_bank_account WHERE bank_id = '$bank_filter'";
                        $result_initial_outstanding_amount = mysqli_query($mysqli, $query_initial_outstanding_amount);
                        $row_initial_outstanding_amount = mysqli_fetch_assoc($result_initial_outstanding_amount);
                        $initial_outstanding_amount = $row_initial_outstanding_amount['outstanding_amount'];
            		}
            		else 
            		{
            			$query_parameters = 'LIMIT 0';
            			
            			$query_parameters_for_cleared_amount = ' LIMIT 0';
            			
            			echo '<center><p style="color:red;">Kindly add filters..</p></center>';
            		}
            		
            		
            		//$query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
            		//if($bank_filter == '12')
            		{
            	        $query_payments_received = "SELECT sum(amount) as total_cleared_amount FROM wpk4_backend_travel_payment_reconciliation $query_parameters_for_cleared_amount";
            		}
            		
            		if( $currnt_userlogn == 'sriharshans')
            		{
            			//echo $query_payments_received;
            		}
                    $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            		$initial_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
            		
            		if($bank_filter == '12')
            		{    
                        ?>
                        <center>
                            <h6>Opening balance: <span id="already_cleared_balance"><?php echo $initial_cleared_amount; ?></span></h6>
                            <h6>Cleared Full Balance: <span id="ending_full_balance"></span></h6>
                            <h6>Stmt. Ending Balance: <span id="ending_balance1"></span></h6>
                        </center>
                        <?php
            		}
            		else
            		{
            		    
            		    	$query_parameters_for_cleared_amount_2 = "WHERE payment_method = '$bank_filter' AND cleared_date BETWEEN '$from_filter' AND '$to_filter' AND cleared_date IS NOT NULL order by process_date asc";
                            $query_payments_received_2 = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount_2";
                            $result_payments_received_2 = mysqli_query($mysqli, $query_payments_received_2);
                            $row_payments_received_2 = mysqli_fetch_assoc($result_payments_received_2);
                    		$initial_cleared_amount2 = number_format((float)$row_payments_received_2['total_cleared_amount'], 2, '.', '');
                    		
                    		
                    		$query_parameters_for_previous_day = "WHERE payment_method = '$bank_filter' AND date(process_date) < '$from_filter_date'";
                            $query_payments_for_previous_day = "SELECT sum(amount) as total_cleared_amount FROM wpk4_backend_travel_payment_reconciliation $query_parameters_for_previous_day";
                            $result_payments_for_previous_day = mysqli_query($mysqli, $query_payments_for_previous_day);
                            $row_payments_for_previous_day = mysqli_fetch_assoc($result_payments_for_previous_day);
                    		$initial_cleared_amount_for_previous_day = number_format((float)$row_payments_for_previous_day['total_cleared_amount'], 2, '.', '');
                    		
                    		$query_parameters_history_for_previous_day = "WHERE payment_method = '$bank_filter' AND date(cleared_date) < '$from_filter_date'";
                            $query_payments_history_for_previous_day = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_history_for_previous_day AND cleared_date IS NOT NULL ";
                            $result_payments_history_for_previous_day = mysqli_query($mysqli, $query_payments_history_for_previous_day);
                            $row_payments_history_for_previous_day = mysqli_fetch_assoc($result_payments_history_for_previous_day);
                    		$initial_history_amount_for_previous_day = number_format((float)$row_payments_history_for_previous_day['total_cleared_amount'], 2, '.', '');
                		?>
                        <center>
                            <?php //echo $query_payments_for_previous_day. ' -> ' . $initial_cleared_amount_for_previous_day; ?>
                            </br>
                            <?php //echo $query_payments_history_for_previous_day . ' -> ' . $initial_history_amount_for_previous_day; ?>
                            
                            <h6>Previous day closing balance: <span id="previous_ending_full_balance2"><?php echo $initial_cleared_amount_for_previous_day + $initial_history_amount_for_previous_day; ?></span></h6>
                            
                            <?php //echo $query_payments_received_2; ?>
                            <h6>Cleared Amount: <span id="ending_full_balance2"><?php echo $initial_cleared_amount2; ?></span></h6>
                            
                            <?php //echo $query_payments_received; ?>
                            <h6>Transfered amount: <span id="already_cleared_balance2"><?php echo $initial_cleared_amount; ?></span></h6>
                            
                            <h6>Stmt. Ending Balance: <span id="ending_balance2"><?php echo $initial_outstanding_amount; ?></span></h6>
                        </center>
                        <?php
            		}
            		?>
    				</br>
    				<form action="#" name="contactsubmit" method="post" enctype="multipart/form-data">
    				<table class="table table-striped accounts_general_table">
    					<thead>
        					<tr>
            					<th>Date</th>
            					<th>Amount</th>
            					<th>Payee</th>
            					<th>Reference</th>
            					<th>Remarks</th>
            					<th>Date cleared</th>
            					<th>PaymentNo</th>
            					<th></th>
        				    </tr>
        				</thead>
    				    <tbody>
    				        <?php
    				    if($bank_filter == '12')
            			{
    				        ?>
    				        <tr>
                    					<td><?php echo date('d/m/Y', strtotime($from_filter_date)); ?></td>
                    					<td><?php echo $initial_cleared_amount; ?></td>
                    					<td</td>
                    					<td>Opening balance</td>
                    					<td colspan="4"></td>
                				    </tr>
            				<?php
            			}
            				if(isset($_GET['bank']) && $_GET['bank'] == '12')
            				{
            				    $query_parameters = str_replace("cleared_date", "process_date", $query_parameters);
            				}
            				
                            {
                				$query_payments_received = "SELECT auto_id, order_id, cleared_date, process_date, trams_received_amount, trams_remarks, is_reconciliated, reference_no FROM wpk4_backend_travel_payment_history $query_parameters";
                                //echo $query_payments_received;
                                if( $currnt_userlogn == 'sriharshans')
                        		{
                        			//echo $query_payments_received;
                        		}
                                $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                                while($row_payments_received = mysqli_fetch_assoc($result_payments_received))
                				{
                				    
                				    $auto_id = $row_payments_received['auto_id'];
                				    $order_id = $row_payments_received['order_id'];
                				    
                				    $query_pax_name_of_booking = "SELECT fname, lname FROM wpk4_backend_travel_booking_pax WHERE order_id = '$order_id' OR pnr = '$order_id' ORDER BY auto_id LIMIT 1";
                                    $result_pax_name_of_booking = mysqli_query($mysqli, $query_pax_name_of_booking);
                                    $row_pax_name_of_booking = mysqli_fetch_assoc($result_pax_name_of_booking);
                				    ?>
            		                <tr>
                    					<td><?php echo date('d/m/Y', strtotime($row_payments_received['process_date'])); ?></td>
                    					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received['trams_received_amount']; ?></span></td>
                    					<td><?php echo ucfirst(strtolower($row_pax_name_of_booking['fname'])); ?> <?php echo ucfirst(strtolower($row_pax_name_of_booking['lname'])); ?></td>
                    					<td><?php echo $row_payments_received['reference_no']; ?></td>
                    					<td><?php echo $row_payments_received['trams_remarks']; ?></td>
                    					<td><?php echo $row_payments_received['cleared_date']; ?></td>
                    					<td><?php echo $order_id; ?></td>
                    					<td>
                    					   <?php
                    					   if($row_payments_received['is_reconciliated'] == 'yes') 
                    					   {
                    					       if(isset($_GET['bank']) && $_GET['bank'] == '12')
                    					       {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared2" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared2"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php    
                    					       }
                    					       else
                    					       {
                    					           ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_is_reconciliated_cleared" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="">
                            					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="">
                            					   <?php   
                    					       }
                    					       
                    					   }
                    					   else
                    					   {
                    					       ?>
                        					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_is_reconciliated" value="yes">
                        					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_amount" value="<?php echo $row_payments_received['trams_received_amount']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_remark" value="<?php echo $row_payments_received['trams_remarks']; ?>">
                        					   <input type="hidden" name="<?php echo $auto_id; ?>_transfer_date" value="<?php echo $row_payments_received['process_date']; ?>">
                        					   <?php
                    					   }
                    					   ?>
                    					</td>
                				    </tr>
            		            <?php
                                }
                                echo '<tr><td colspan="7">&nbsp;</td></tr>';
                                $reconcile_from = $_GET['from'];
                                $reconcile_to = $_GET['to'];
                                $query_payments_received_from_reconcile = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '$bank_filter' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile = mysqli_query($mysqli, $query_payments_received_from_reconcile);
                                if(mysqli_num_rows($result_payments_received_from_reconcile) > 0)
                                {
                                    while($row_payments_received_from_reconcile = mysqli_fetch_assoc($result_payments_received_from_reconcile))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile['amount']; ?></span></td>
                        					<td></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" class="bank" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" class="bank" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
                            if(isset($_GET['bank']) && $_GET['bank'] == '12' && 1 == 2)
                            {
                                $reconcile_from = $_GET['from'];
                                $reconcile_to = $_GET['to'];
                                $query_payments_received_from_reconcile_cba = "SELECT auto_id, amount, remark, process_date, cleared_by FROM wpk4_backend_travel_payment_reconciliation WHERE payment_method = '12' AND ( date(process_date) BETWEEN '$reconcile_from' AND  '$reconcile_to' ) order by process_date asc";
                                $result_payments_received_from_reconcile_cba = mysqli_query($mysqli, $query_payments_received_from_reconcile_cba);
                                if(mysqli_num_rows($result_payments_received_from_reconcile_cba) > 0)
                                {
                                    while($row_payments_received_from_reconcile_cba = mysqli_fetch_assoc($result_payments_received_from_reconcile_cba))
                    				{
                    				    $auto_id = $row_payments_received_from_reconcile_cba['auto_id'];
                    				    ?>
                		                <tr>
                        					<td><?php echo date('d/m/Y', strtotime($row_payments_received_from_reconcile_cba['process_date'])); ?></td>
                        					<td><span id="<?php echo $auto_id; ?>_trams_received_amount"><?php echo $row_payments_received_from_reconcile_cba['amount']; ?></span></td>
                        					<td></td>
                        					<td><?php echo $row_payments_received_from_reconcile_cba['remark']; ?></td>
                        					<td></td>
                        					<td></td>
                        					<td>
                        					   <?php
                        					   if($row_payments_received_from_reconcile_cba['cleared_by'] != '') 
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile_cleared" name="<?php echo $auto_id; ?>_cleared_by" checked disabled value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile_cleared"> Cleared</label>
                        					       <?php
                        					   }
                        					   else
                        					   {
                        					       ?>
                            					   <input type="checkbox" id="<?php echo $auto_id; ?>_reconcile" name="<?php echo $auto_id; ?>_cleared_by" value="yes">
                            					   <label for="<?php echo $auto_id; ?>_reconcile"> Clear</label>
                        					       <?php
                        					   }
                        					   ?>
                        					</td>
                    				    </tr>
                		                <?php
                                    }
                                }
                            }
        		            ?>
        		            <tr>
        		                <td colspan="7">
        		                    <?php 
        		                    //if(isset($_GET['bank']) && $_GET['bank'] == '12') 
        		                    { 
            		                    ?>
            		                    &nbsp;<input type='submit' style='float:right;padding:10px; margin:0;font-size:14px; margin-left:10px;' name='save_final_selected_payments' value='Reconsile CBA / Negative Selected'>&nbsp;
            		                    <?php
        		                    }
        		                    //if(isset($_GET['bank']) && $_GET['bank'] != '12') 
        		                    {
            		                    ?>
            		                    &nbsp;<input type='submit' name='save_selected_payments' style='float:right;padding:10px; margin:0;font-size:14px; ' value='Reconsile customer payments selected'>&nbsp;
            		                    <?php
        		                    }
        		                    ?>
        		                </td>
        		            </tr>
        				</tbody>
    				</table>
    				</form>
				    <?php
				    if(isset($_POST['save_selected_payments']))
				    {
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];
                            $row_order_id = $row_get_payments['order_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET $columnname_db='$post_fieldvalue', cleared_date = '$current_date_and_time', cleared_by = '$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";

                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                        mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id,merging_id,pax_auto_id,meta_key,meta_value,updated_time,updated_user) 
                                        values ('$row_order_id','$row_auto_id_pay','','$columnname_db','$post_fieldvalue','$current_date_and_time','$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        
                                        if($_GET['bank'] == '12')
                                        {
                                            $transfering_amount_field = $_POST[$row_auto_id_pay.'_transfer_amount'];
                                            $transfering_remark_field = $_POST[$row_auto_id_pay.'_transfer_remark'];
                                            $transfering_date_field = $_POST[$row_auto_id_pay.'_transfer_date'];
                                            $current_date = date("Y-m-d");
                                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                                            values ('$transfering_date_field', '12', '$transfering_amount_field', '$transfering_remark_field', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                        }            
                                    }
                                }
                            }
                        }
                        
                        
                        $query_payments_received = "SELECT sum(trams_received_amount) as total_cleared_amount FROM wpk4_backend_travel_payment_history $query_parameters_for_cleared_amount";
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        $row_payments_received = mysqli_fetch_assoc($result_payments_received);
                    	$total_cleared_amount = number_format((float)$row_payments_received['total_cleared_amount'], 2, '.', '');
                    	$total_cleared_amount_negative = '-'.$total_cleared_amount;
                    	
                    	$bank_id = $_GET['bank'];
                    	$date_from = $_GET['from'];
                    	$date_to = $_GET['to'];
                    	$remark = 'Cleared amount for '.$_GET['bank'] . ' from ' . $_GET['from'] . ' to ' . $_GET['to'] . ' on '. date("Y-m-d");
                    	/*
                        $query_payments_received = "SELECT auto_id FROM wpk4_backend_travel_payment_reconciliation $query_parameters";
                        echo $query_payments_received.'</br>';
                        $result_payments_received = mysqli_query($mysqli, $query_payments_received);
                        if(mysqli_num_rows($result_payments_received) != 0)
            			{
            			    $row_payments_received = mysqli_fetch_assoc($result_payments_received);
            			    $auto_id = $row_payments_received['auto_id'];
            			    
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount_negative', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '$bank_id'";
                            
                            $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET amount='$total_cleared_amount', remark='$remark', added_by = '$currnt_userlogn'
                                WHERE auto_id = '$auto_id' AND payment_method = '12'";
                                
                            $result_status= mysqli_query($mysqli,$sql_update_status);
                            echo 'UPDATE wpk4_backend_travel_payment_reconciliation SET amount='.$total_cleared_amount_negative.', remark='.$remark.', added_by = '.$currnt_userlogn.' WHERE auto_id = '.$auto_id.'</br>';
                                
            			}
            			else
            			{
            			    mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '$bank_id', '$total_cleared_amount_negative', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                            
                            mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) 
                            values ('$date_to', '12', '$total_cleared_amount', '$remark', '$currnt_userlogn')") or die(mysqli_error($mysqli));
                                
                            echo 'insert into wpk4_backend_travel_payment_reconciliation ( process_date, payment_method, amount, remark, added_by ) values ('.$date_to.', '.$bank_id.', '.$total_cleared_amount_negative.', '.$remark.', '.$currnt_userlogn.')</br>';
                        }
                        */
                        
                        echo '<script>window.location.href="'.$current_url.'";</script>';
                    }
                    
				    if(isset($_POST['save_final_selected_payments']))
				    {
				        $query_parameters2 = str_replace("cleared_date", "process_date", $query_parameters);
				        $query_payments_received = "SELECT * FROM wpk4_backend_travel_payment_reconciliation $query_parameters2";
				        echo $query_payments_received.'</br></br>';
                        $result_get_payments = mysqli_query($mysqli, $query_payments_received); // using the same query used to run initial selection
                        while($row_get_payments = mysqli_fetch_assoc($result_get_payments))
                        {
                            $row_auto_id_pay = $row_get_payments['auto_id'];

                            foreach($row_get_payments as $columnname_db => $db_value)
                            {
                                $dbcolumn_and_postname_checker = $row_auto_id_pay.'_'.$columnname_db;
                                foreach ($_POST as $post_fieldname => $post_fieldvalue) 
                                {
                                    echo $post_fieldname .'=='. $dbcolumn_and_postname_checker .'&&'. $post_fieldvalue .'!='. $row_get_payments[$columnname_db].'</br></br>';
                                    if($post_fieldname == $dbcolumn_and_postname_checker && $post_fieldvalue != $row_get_payments[$columnname_db])
                                    {
                                        //echo $dbcolumn_and_postname_checker.'</br></br>';
                                        $sql_update_status = "UPDATE wpk4_backend_travel_payment_reconciliation SET $columnname_db='$currnt_userlogn'
                                                    WHERE auto_id='$row_auto_id_pay'";
                                                    
                                                   // echo $sql_update_status;
                                        $result_status= mysqli_query($mysqli,$sql_update_status);
                                                    
                                                    
                                    }
                                }
                            }
                        }
                    	
                        echo '<script>window.location.href="'.$current_url.'";</script>';
            		    
                    }
                }
                
                if($_GET['pg'] == 'import-reconcile-payments' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    if(!isset($_POST["import_reconcile_to_view"])) 
        			{
                    ?>
                    <style>
                    .radio-group {
                        display: flex;
                        align-items: center;
                        margin:auto;
                        width:600px;
                    }
                                
                    .radio-group label {
                        margin-right: 10px;
                        margin-top:13px;
                    }
                    </style>
            		<center>
            		</br></br></br>
            		<form class="form-horizontal" action="?pg=import-reconcile-payments" method="post" name="uploadCSV" enctype="multipart/form-data">
            			<div class="input-row">
            				<label class="col-md-4 control-label">Choose Bank type and attach file</label>
            				</br>
            				<a href="https://gauratravel.com.au/wp-content/uploads/2024/12/template-slicepay-reconcile-import.csv">Template for SlicePay</a>
            				</br></br>
            				<!--
            				<div class="radio-group" >
                				<input type="radio" id="Asiapay" required name="banktypes" value="Asiapay"> <label for="Asiapay"> Asiapay</label>
                				<input type="radio" id="Azupay" required name="banktypes" value="Azupay"> <label for="Azupay"> Azupay</label>
                				<input type="radio" id="BPAY" required name="banktypes" value="BPAY"> <label for="BPAY"> BPAY</label>
                				<input type="radio" id="BPOINT" required name="banktypes" value="BPOINT"> <label for="BPOINT"> BPOINT</label>
                				<?php
                				if(current_user_can( 'administrator' ))
                				{
                				?>
                				<input type="radio" id="Mint" required name="banktypes" value="Mint"> <label for="Mint"> Mint</label>
                				<?php
                				}
                				?>
            				</div>
            				-->
            				<div class="radio-group" >
                				<input type="radio" id="Asiapay" required name="banktypes" value="Asiapay"> <label for="Asiapay"> Mint FIT</label>
                				<input type="radio" id="PayDollar_GDeals" required name="banktypes" value="PayDollar_GDeals"> <label for="PayDollar_GDeals"> Mint GDeals</label>
                				<input type="radio" id="Azupay" required name="banktypes" value="Azupay"> <label for="Azupay"> Azupay</label>
                				<input type="radio" id="BPAY" required name="banktypes" value="BPAY"> <label for="BPAY"> BPAY</label>
                				<input type="radio" id="BPOINT" required name="banktypes" value="BPOINT"> <label for="BPOINT"> BPOINT</label>
                				<input type="radio" id="SlicePay" required name="banktypes" value="SlicePay"> <label for="SlicePay"> SlicePay</label>
                				<?php
                				if(current_user_can( 'administrator2' ))
                				{
                				?>
                				<input type="radio" id="Mint" required name="banktypes" value="Mint"> <label for="Mint"> Mint</label>
                				<?php
                				}
                				?>
            				</div>
            				</br>
            				<input type="file" required name="file" id="file" accept=".csv" style="display:block;"></br>
            				<input type="submit" id="submit" style='height:30px; width:70px; font-size:12px; padding:7px; margin:0px;' name="import_reconcile_to_view"></input>
            				<br />
            			</div>
            			<div id="labelError"></div>
            		</form>
            		</center>
                    <?php
                    }
                    //UPDATE wpk4_backend_travel_payment_history SET is_reconciliated = 'yes', cleared_date = '2024-07-19 13:50:50', cleared_by = 'sriharshans' WHERE order_id = '529765564' AND trams_received_amount = '218.11' AND payment_method = '8'

                    if(isset($_POST["import_reconcile_to_view"])) 
        			{
        			    //ini_set('error_reporting',E_ALL);
                        //ini_set('display_errors','On');
                        //ini_set('display_errors', '1');
                        //ini_set('display_startup_errors', '1');

        			    $bank_type = $_POST["banktypes"];
        				$fileName = $_FILES["file"]["tmp_name"];
        				echo 'Importing '.$bank_type;
        				if ($_FILES["file"]["size"] > 0) 
        				{
        					$file = fopen($fileName, "r");
        					echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
        					$tablestirng = "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
        					<tr>
        						<td>#</td>
        						<td>Date</td>
        						<td>OrderID</td>
        						<td>Amount</td>
        						<td>Current Payment Status</td>";
        						if($bank_type == 'BPAY')
        						{
        						    $tablestirng .= "<td>New Payment Status</td>
        						    <td>Note</td>";
        						}
        						$tablestirng .= "<td>Reference</td>
        						<td>Settlement Date</td>
        						<td width='10%;'>Message</td>
        						<td>Existing/New</td>
        					</tr>";
        					$autonumber = 1;
        					
        					while (($column = fgetcsv($file, 10000, ",")) !== FALSE) 
        					{
        					    $non_matching_reasons = '';
        					    $is_matched_any_condition = 0;
        						if(($column[0] == 'Transaction Date' && $column[1] == 'Merchant Ref.') || ($column[0] == 'order_id' && $column[1] == 'amount') || ($column[0] == 'DateTime' && $column[1] == 'LocalTime') || ($column[0] == 'Data Type' && $column[1] == 'Payment Instruction Type') || ($column[0] == 'Transaction type' && $column[1] == 'Biller code'))
        						{
        							// Do Nothing
        							//($column[0] == 'Transaction Date' && $column[1] == 'Merchant Ref.') - Asiapay
        							//$column[0] == 'DateTime' && $column[1] == 'LocalTime' - Azupay (required to skip when the TransactionType = Sweep)
        							//($column[0] == 'Data Type' && $column[1] == 'Payment Instruction Type') - BPAY
        							//($column[0] == 'Transaction type' && $column[1] == 'Biller code') - BPOINT
        						}
        						else
        						{
            						if($bank_type == 'Asiapay' && isset($column[2]) && $column[2] != '' && isset($column[9]) && isset($column[2])) // Mint FIT
            						{
            						    $payment_method_number = '8';
                                        $transaction_date = $column[0]; // Transaction Date
                                        $transaction_type = $column[3];
                                        $transaction_date = strtotime(str_replace('/', '-', $transaction_date));
                                        $new_transaction_date = date('Y-m-d H:i:s', $transaction_date);
                                        
                                        if(isset($column[18]) && $column[18] != '')
                                        {
                                            $settlement_date = $column[18]; // Settlement Date
                                            $settlement_date = strtotime(str_replace('/', '-', $settlement_date));
                                            $new_settlement_date = date('Y-m-d H:i:s', $settlement_date);
                                        }
                                        else
                                        {
                                            $new_settlement_date = date('Y-m-d H:i:s');
                                        }
                                        
            							$order_id = ltrim($column[2], '0'); // System Ref.
            							$amount = number_format((float)$column[9], 2, '.', ''); // Amount
            							
            							if($new_transaction_date != '1970-01-01 10:00:00')
            							{
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if($result->num_rows > 0)
            							    {
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
            							    }
            							    else
            							    {
            							        $order_id_from_booking_table = '';
                    						    $payment_status_from_booking_table = '';
            							    }
    
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table."</td>
                								<td>".$order_id."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								
                								$is_amount_mismatch = '';
                								if($transaction_type == 'PAYID')
        						                {
                								    $sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND reference_no = '$order_id' and payment_method = '8'";
                								    $is_amount_mismatch = 'Amount will be overwrite';
        						                }
        						                else
        						                {
        						                    $sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND reference_no = '$order_id' and 
                								        ( ABS(CAST(trams_received_amount AS DECIMAL(10,2)) - CAST($amount AS DECIMAL(10,2))) < 0.5 OR CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) ) AND payment_method = '8'";
        						                }
                    							$result_2 = $mysqli->query($sql_2);
                    							if( $currnt_userlogn == 'sriharshans')
                            		            {
                            		                //echo $sql_2.'</br></br>';
                            		            }
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
            							        {
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
            							        else
            							        {
            							            $order_id_from_payment_table = '';
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>".$is_amount_mismatch;
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$transaction_type."' ".$checked." \/></td></tr>";
                
                							
            							}
            						}
            						
            						if($bank_type == 'Azupay')
            						{
            						    $payment_method_number = '7';
            						    
                                        $new_transaction_date = substr($column[1], 0, 19); // Transaction Date
                                        
                                        $payment_type_block = $column[5];
                                        
                                        $amount = number_format((float)$column[3], 2, '.', ''); // Amount
                                        
            							$payment_description = $column[11];
            							
            							$payment_customer_reference = $column[12];
            							
            							$payment_request_id = $column[14];
            							$payment_request_id_child = $column[13]; // for refund
            							
            							if(isset($column[17]) && $column[17] != '')
                                        {
                                            $settlement_date = $column[17]; // Settlement Date
                                            $settlement_date = strtotime(str_replace('/', '-', $settlement_date));
                                            $new_settlement_date = date('Y-m-d H:i:s', $settlement_date);
                                        }
                                        else
                                        {
                                            $new_settlement_date = date('Y-m-d H:i:s');
                                        }
            							
            							if($payment_type_block == 'PaymentRequest' && $payment_request_id != '')
            							{
            							    if($payment_request_id == '')
            							    {
            							        $payment_payid = $column[9];
            							        
            							        $sql_payment_requests = "SELECT payment_request_id  FROM wpk4_backend_travel_booking_custom_payments where azupay_payid = '$payment_payid'";
                            					$result_payment_requests = $mysqli->query($sql_payment_requests);
                            					$row_payment_requests = $result_payment_requests->fetch_assoc();
                            					$payment_request_id = $row_payment_requests['payment_request_id'];
            							    }
            							    
            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id."' ".$checked." \/></td></tr>";
                
            							}
            							
            							if($payment_type_block == 'PaymentRequest' && $payment_request_id_child != '' && $payment_request_id == '')
            							{
            							    if($payment_request_id_child == '')
            							    {
            							        $payment_payid = $column[9];
            							        
            							        $sql_payment_requests = "SELECT payment_request_id  FROM wpk4_backend_travel_booking_custom_payments where azupay_payid = '$payment_payid'";
                            					$result_payment_requests = $mysqli->query($sql_payment_requests);
                            					$row_payment_requests = $result_payment_requests->fetch_assoc();
                            					$payment_request_id_child = $row_payment_requests['payment_request_id'];
            							    }
            							    
            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id_child."' ".$checked." \/></td></tr>";
                
            							}
            							if($payment_type_block == 'Payment' && $payment_request_id_child != '')
            							{

            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								$negative_amount = '-'.$amount;
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($negative_amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$negative_amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id_child."' ".$checked." \/></td></tr>";
                
            							}
            						}
            						
            						if($bank_type == 'BPAY')
            						{
            						    $payment_method_number = '9';
            						    
                                        $new_transaction_date = $column[17]; // Transaction Date
                                        $new_transaction_time = $column[18]; // time

                                        // Pad the time string to ensure it's 6 characters long
                                        $time = str_pad($new_transaction_time, 6, '0', STR_PAD_LEFT);
                                        
                                        // Extract parts of the date
                                        $year = substr($new_transaction_date, 0, 4);
                                        $month = substr($new_transaction_date, 4, 2);
                                        $day = substr($new_transaction_date, 6, 2);
                                        
                                        // Extract parts of the time
                                        $hour = substr($time, 0, 2);
                                        $minute = substr($time, 2, 2);
                                        $second = substr($time, 4, 2);
                                        
                                        // Format the date and time as YYYY-mm-dd HH:ii:ss
                                        $new_transaction_date = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second;
                                        
                                        
                                        $new_settlement_date = $column[16]; // Settlement Date Ymd
                                        
                                        // Extract parts of the date
                                        $settlement_year = substr($new_settlement_date, 0, 4);
                                        $settlement_month = substr($new_settlement_date, 4, 2);
                                        $settlement_day = substr($new_settlement_date, 6, 2);
                                        
                                        $new_settlement_date = $settlement_year . '-' . $settlement_month . '-' . $settlement_day . ' 00:00:00';

                                        $amount = number_format((float)$column[13], 2, '.', ''); // Amount
                                        
            							$payment_description = $column[14];
            							
            							$order_id = '';
            							$match = '';
            							$current_payment_status_from_booking = '';
            							$total_amount_from_booking = '';
            							
            							$reference_number = $column[10];
            							
            							$order_id = substr($column[10], 0, -1);
            							$order_id = ltrim($order_id, '0');
            							
            							
            							
            							
            							$sql_booking = "SELECT order_id, total_amount, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id' AND order_id IS NOT NULL";
            							$result_booking = $mysqli->query($sql_booking);
            							$row_booking = $result_booking->fetch_assoc();
            							if($result_booking->num_rows > 0)
            							{
            							    $total_amount_from_booking = number_format((float)$row_booking['total_amount'], 2, '.', '');
            							    $current_payment_status_from_booking = $row_booking['payment_status'];

                							$trams_received_amount = 0;
                                            $sql_payments = "SELECT trams_received_amount FROM wpk4_backend_travel_payment_history where order_id = '$order_id'";
                        					$result_payments = $mysqli->query($sql_payments);
                        					while($row_payments = $result_payments->fetch_assoc())
                        					{
                        						$trams_received_amount += (float)$row_payments['trams_received_amount'];
                        					}
                							$trams_received_amount = number_format((float)$trams_received_amount, 2, '.', '');
                							
                                            $balance = number_format((float)($total_amount_from_booking - $trams_received_amount) - $amount, 2, '.', '');
            
                                            $is_paid_fully = '';
                    						if($current_payment_status_from_booking != 'paid' && $current_payment_status_from_booking != '' && $total_amount_from_booking > 0)
                    						{
                    							if($balance == 0.00)
                    							{
                    								$match= "Fully paid";
                    								$is_paid_fully = 'paid';
                    								$payment_type = 'Balance';
                    							}
                    							else if($balance < 1.00)
                    							{
                    								$match= "+-$1 Different. Fully paid / Overpaid";
                    								$is_paid_fully = 'paid';
                    								$payment_type = 'Balance';
                    							}
                    							else
                    							{
                    								$match= "<font style='color:red'>$". $balance . " different</font>";
                    								if($current_payment_status_from_booking != 'voucher_submited')
                    								{
                    								    $is_paid_fully = 'partially_paid';
                    								}
                    								else
                    								{
                    								    $is_paid_fully = 'voucher_submited';
                    								}
                    								$payment_type = '';
                    							}
                    						}
                    						else
                    						{
                    						    $is_paid_fully = '';
                    							$match= "<font style='color:red'>Already paid</font>";
                    							$payment_type = '';
                    						}
                    						
                    						$is_booking_check_enabled = 'no';
                							if( (strtolower($payment_type) == 'balance') )
                							{
                								$is_booking_check_enabled = 'yes';
                							}
                									
                							$checked="";
                							$match_view = 'New Record';
                							$match_hidden = 'New';
                							$localpayment_status = '';
                							
                							if($is_booking_check_enabled == 'yes' && $is_paid_fully == 'paid')
                							{
                								$localpayment_status = 'paid';
                    							$match_hidden = 'New';
                    							$match_view = "New Record";
                    							$checked="checked";
                							}
                							if( $is_booking_check_enabled == 'no' && $is_paid_fully == 'paid' )
                    						{
                    							$localpayment_status = 'notpaid';
                    							$match_hidden = 'New';
                        						$match_view = "New Record";
                        						$checked="checked";
                    						}
                    										
                    						if( $is_booking_check_enabled == 'yes' && $is_paid_fully == '' )
                    						{
                    							$localpayment_status = 'notpaid';
                    							$match_hidden = 'New';
                        						$match_view = "New Record";
                        						$checked="checked";
                    						}
                    										
                    						if( $is_booking_check_enabled == 'no' && $is_paid_fully == '' )
                    						{
                    							$localpayment_status = 'notpaid';
                    							$match_hidden = 'New';
                        						$match_view = "New Record";
                        						$checked="checked";
                    						}
                						}
                										
                						$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    					$result = $mysqli->query($sql);
                    					$row = $result->fetch_assoc();
                    					if($result->num_rows > 0)
                    					{
                    					    $order_id_from_booking_table = $row['order_id'];
                    					    $payment_status_from_booking_table = $row['payment_status'];
                    					}
                    					else
                    					{
                    					    $order_id_from_booking_table = '';
                    					    $payment_status_from_booking_table = '';
                    					}
    
                						$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$is_paid_fully."</td>
                								<td>".$match."</td>
                								<td>".$payment_description ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                						$match2 = [];
                								
                						if($order_id == $order_id_from_booking_table)
                						{
                								$is_booking_exists = true;
                						}
                						else 
                						{
                							$is_booking_exists = false;
                							$match2[] = "<font style='color:red;'>Booking is not exist</font>";
                						}
                						
                						$amount = $mysqli->real_escape_string($amount);
                								
                						$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST('$amount' AS DECIMAL(10,2)) AND payment_method = '9'";
                    					$result_2 = $mysqli->query($sql_2);
                    					$row_2 = $result_2->fetch_assoc();
                    					if($result_2->num_rows > 0)
                    					{
                    					    $order_id_from_payment_table = $row_2['order_id'];
                    					}	
                    					else
                    					{
                    					    $order_id_from_payment_table = '';
                    					}
                						if($order_id == $order_id_from_payment_table)
                						{
                							$match_hidden = 'Existing';
                							if($is_booking_exists)
                							{
                								$checked="checked";
                							}
                							else
                							{
                							    $checked="";
                							}
                						}
                						else 
                						{
                							$match_hidden = 'New';
                							$match2[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                							if($is_booking_exists)
                							{
                								$checked="checked";
                							}
                						    else
                							{
                								$checked="";
                							}
                						}
                								
                    					$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                    					if(isset($match2[0]) && $match2[0] != '')
                    					{
                    						$tablestirng.= $match2[0];
                    					}
                    							            
                    					if(isset($match2[1]) && $match2[1] != '')
                    					{
                    						$tablestirng.= '</br></br>'.$match2[1];
                    					}
                    					$tablestirng.= "</td>";
                    																	
                    					$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$reference_number."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$is_paid_fully."@#".$payment_description."' ".$checked." \/></td></tr>";
                    

            						}
            						
            						if($bank_type == 'BPOINT')
            						{
            						    $payment_method_number = '5';
            						    
            						    $new_transaction_date = $column[12]; // Transaction Date
                                        $new_transaction_time = $column[13]; // time

                                        // Pad the time string to ensure it's 6 characters long
                                        $time = str_pad($new_transaction_time, 6, '0', STR_PAD_LEFT);
                                        
                                        // Extract parts of the date
                                        $year = substr($new_transaction_date, 0, 4);
                                        $month = substr($new_transaction_date, 4, 2);
                                        $day = substr($new_transaction_date, 6, 2);
                                        
                                        // Extract parts of the time
                                        $hour = substr($time, 0, 2);
                                        $minute = substr($time, 2, 2);
                                        $second = substr($time, 4, 2);
                                        
                                        // Format the date and time as YYYY-mm-dd HH:ii:ss
                                        $new_transaction_date = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second;
                                        
                                        
                                        $new_settlement_date = ($column[15] ?? false)? $column[15] : ''; // Settlement Date
                                        
                                        if($new_settlement_date != '')
                                        {
                                            // Extract parts of the date
                                            $settlement_year = substr($new_settlement_date, 0, 4);
                                            $settlement_month = substr($new_settlement_date, 4, 2);
                                            $settlement_day = substr($new_settlement_date, 6, 2);
                                            
                                            // Format the date and time as YYYY-mm-dd HH:ii:ss
                                            $new_settlement_date = $settlement_year . '-' . $settlement_month . '-' . $settlement_day . ' 00:00:00';
                                        }

                                        $amount = number_format((float)$column[5], 2, '.', ''); // Amount
                                        
            							$payment_request_id = $column[2];
            							
            							    $sql_request_id = "SELECT order_id, bpoint_ref, payment_status  FROM wpk4_backend_travel_bookings where bpoint_ref LIKE '$payment_request_id'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if($result_request_id->num_rows > 0)
                            				{
                            					$order_id = $row_request_id['order_id'];
                            					$bpoint_ref = $row_request_id['bpoint_ref'];
                        						$payment_status_from_booking_table = $row_request_id['payment_status'];
                            				}
                            				else
                            				{
                            				    $order_id = '';
                            					$bpoint_ref = '';
                        						$payment_status_from_booking_table = '';
                            				}
                
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_request_id . "</td>
                								<td>".$new_settlement_date . "</td>
                								";
                								
                								$match = [];
                								
                								if(strcmp($payment_request_id, $bpoint_ref) == 0)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '5'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
                            					{
                            					    $order_id_from_payment_table = $row_2['order_id'];
                            					}	
                            					else
                            					{
                            					    $order_id_from_payment_table = '';
                            					}
                    							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."' ".$checked." \/></td></tr>";
                
            						}
            						
            						if($bank_type == 'Mint') // Not in use
            						{
            						    $payment_method_number = '8';
                                        $transaction_date = $column[0]; // Transaction Date
                                        $transaction_date = strtotime(str_replace('/', '-', $transaction_date));
                                        $new_transaction_date = date('Y-m-d H:i:s', $transaction_date);
                                        
                                        if(isset($column[18]) && $column[18] != '')
                                        {
                                            $settlement_date = $column[18]; // Settlement Date
                                            $settlement_date = strtotime(str_replace('/', '-', $settlement_date));
                                            $new_settlement_date = date('Y-m-d H:i:s', $settlement_date);
                                        }
                                        else
                                        {
                                            $new_settlement_date = date('Y-m-d H:i:s');
                                        }
                                        
            							$payreference = ltrim($column[1], '0'); // System Ref.
            							$amount = number_format((float)$column[9], 2, '.', ''); // Amount
            							
            							if($new_transaction_date != '1970-01-01 10:00:00')
            							{
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where bpoint_ref = '$payreference'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if($result->num_rows > 0)
            							    {
                    						    $order_id = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
            							    }
            							    else
            							    {
            							        $order_id = '';
                    						    $payment_status_from_booking_table = '';
            							    }
    
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table."</td>
                								<td>".$payreference."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id != '')
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '8'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
            							        {
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
            							        else
            							        {
            							            $order_id_from_payment_table = '';
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$payreference."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."' ".$checked." \/></td></tr>";
                
            							}
            						}
            						
            						if($bank_type == 'PayDollar_GDeals' && isset($column[1]) && $column[1] != '' && isset($column[9]) && $column[9] != '') // Mint GDeals
            						{
            						    $payment_method_number = '8';
                                        $transaction_date = $column[0]; // Transaction Date
                                        $transaction_date = strtotime(str_replace('/', '-', $transaction_date));
                                        $new_transaction_date = date('Y-m-d H:i:s', $transaction_date);
                                        
                                        if(isset($column[18]) && $column[18] != '')
                                        {
                                            $settlement_date = $column[18]; // Settlement Date
                                            $settlement_date = strtotime(str_replace('/', '-', $settlement_date));
                                            $new_settlement_date = date('Y-m-d H:i:s', $settlement_date);
                                        }
                                        else
                                        {
                                            $new_settlement_date = date('Y-m-d H:i:s');
                                        }
                                        
            							$system_refe = ltrim($column[1], '0'); // System Ref.
            							$amount = number_format((float)$column[9], 2, '.', ''); // Amount
            							
            							if($new_transaction_date != '1970-01-01 10:00:00')
            							{
            							    $sql3 = "SELECT order_id FROM wpk4_backend_travel_payment_history where reference_no = '$system_refe'";
                    						$result3 = $mysqli->query($sql3);
                    						$row3 = $result3->fetch_assoc();
                    						if($result3->num_rows > 0)
            							    {
                    						    $order_id = $row3['order_id'];
            							    }
            							    else
            							    {
            							        $order_id = 'TEMP12345';
            							    }
            							    
            							    
                							$sql = "SELECT payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if($result->num_rows > 0)
            							    {
                    						    $payment_status_from_booking_table = $row['payment_status'];
            							    }
            							    else
            							    {
                    						    $payment_status_from_booking_table = '';
            							    }
    
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table."</td>
                								<td>".$order_id."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id != 'TEMP12345')
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '8'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
            							        {
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
            							        else
            							        {
            							            $order_id_from_payment_table = '';
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$system_refe."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."' ".$checked." \/></td></tr>";
                
                							
            							}
            						}
            						
            						if($bank_type == 'SlicePay')
            						{
            						    $payment_method_number = '16';
            						    
            						    $payment_settlement_date = $column[3]; // Transaction Date
                                        $booking_date = $column[2]; // booking_date

                                        $amount = number_format((float)$column[1], 2, '.', ''); // Amount
                                        
            							$order_id = $column[0];
            							
            							$match = [];
            							
            							    $sql_request_id = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if($result_request_id->num_rows > 0)
                            				{
                            				    $is_booking_exists = true;
                            					$order_id = $row_request_id['order_id'];
                        						$payment_status_from_booking_table = $row_request_id['payment_status'];
                            				}
                            				else
                            				{
                            				    $order_id = '';
                        						$payment_status_from_booking_table = '';
                        						$is_booking_exists = false;
                								$match[] = "<font style='color:red;'>Booking is not exist</font>";
                            				}
                
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$booking_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$order_id."</td>
                								<td>".$payment_settlement_date . "</td>
                								";
                								
                								
                								
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id' AND payment_method = '16'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
                            					{
                            					    $order_id_from_payment_table = $row_2['order_id'];
                            					}	
                            					else
                            					{
                            					    $order_id_from_payment_table = '';
                            					}
                    							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									$match[] = "<font style='color:red;'>Payment amount will be overwritten</font>";
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment amount will be overwritten</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$booking_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$payment_settlement_date."' ".$checked." \/></td></tr>";
                
            						}
            						
            						
            						$autonumber++;
        							
        						}
        					}
        					
        					$tablestirng.= "</table>";
        					echo $tablestirng;
        					?>
        					<br><br><input type="submit" name="submit_reconcile_import" value="Update"/></form></center>
        					<?php
        				}
        			}
        			if (isset($_POST["submit_reconcile_import"])) 
        			{
        				foreach ($_POST as $post_field_name => $post_fieldvalue) 
        				{
        					$post_name_dividants = explode('_', $post_field_name);
        					$postname_auto_id = $post_name_dividants[0];
    						$postname_fieldname = $post_name_dividants[1];
        					$check_whether_its_ticked = $postname_auto_id.'_checkoption';

        					if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
        					{
        						$post_value_get = $_POST[$post_field_name];
        						$post_values = explode('@#', $post_value_get);
        						
        						$order_id_post = $post_values[0];	
    							$amount_post = $post_values[1];
    							$transaction_date_post = $post_values[2];
    							$reference_no_post = $post_values[3];
    							$payment_method_post = $post_values[4];
    							$match_hidden_post = $post_values[5];
    							$settlement_date_post = $post_values[6];

        						$date_cleared = date("Y-m-d H:i:s");
        						
        						if($payment_method_post == '16') // SlicePay
        						{
                                    $amount_post_esc = $mysqli->real_escape_string($amount_post);
                                    
                                    $sql_payment_exist = "SELECT auto_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id_post' AND payment_method = '16'";
            						$result_payment_exist = $mysqli->query($sql_payment_exist);
            						if($result_payment_exist->num_rows > 0)
            						{
            						    $row_payment_exist_slicepay = $result_payment_exist->fetch_assoc();
                        				$auto_id_slicepay = $row_payment_exist_slicepay['auto_id'];
                        				
                        				$sql_update_status3 = "UPDATE wpk4_backend_travel_payment_history SET 
                						        trams_received_amount = '$amount_post'
            						            WHERE auto_id = '$auto_id_slicepay' ";
            						    $result_status3 = mysqli_query($mysqli,$sql_update_status3) or die(mysqli_error($mysqli));
            						    
        						        //mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_history (order_id, source, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, cleared_date, cleared_by ) 
				                        //values ('$order_id_post', '', '$amount_post_esc', '$reference_no_post', '$payment_method_post', '$transaction_date_post', 'Balance', '$date_cleared', '$currnt_userlogn', '$settlement_date_post', '$currnt_userlogn' )") or die(mysqli_error($mysqli));
				                        
            						}
        						}
        						
        						if($payment_method_post == '9') // BPAY
        						{
        						    $bpay_payment_status_post = $post_values[7] ?? 'partially_paid';
        						    $bpay_payment_reference = $post_values[8] ?? '';
        						    
        						    if(ctype_digit($order_id_post) && strlen($order_id_post) < 7) 
                                    {
                                        $source = 'WPT';
                                    }
                                    else
                                    {
                                        $source = 'gds';
                                    }
                                    
                                    $bpay_full_ref = $reference_no_post . ' - ' . $bpay_payment_reference;
                                    
                                    $transaction_date_post_ymd = date('Y-m-d', strtotime($transaction_date_post)).' 00:00:00';
                                    
                                    $amount_post_esc = $mysqli->real_escape_string($amount_post);
                                    //AND process_date = '$transaction_date_post_ymd' AND reference_no = '$bpay_full_ref'
                                    $sql_payment_exist = "SELECT auto_id, order_id FROM wpk4_backend_travel_payment_history where order_id = '$order_id_post' AND payment_method = '9' AND ( CAST(trams_received_amount AS DECIMAL(10,2)) = CAST('$amount_post_esc' AS DECIMAL(10,2)) ) ";
            						//echo $sql_payment_exist.'</br>';
            						$result_payment_exist = $mysqli->query($sql_payment_exist);
            						if($result_payment_exist->num_rows > 0)
            						{
            						    
            						    $row_payment_exist_bpay = $result_payment_exist->fetch_assoc();
                        				$auto_id_bpay = $row_payment_exist_bpay['auto_id'];
                        				
                        				$sql_update_status3 = "UPDATE wpk4_backend_travel_payment_history SET 
                						        process_date = '$transaction_date_post'
            						            WHERE auto_id = '$auto_id_bpay' ";
            						    $result_status3 = mysqli_query($mysqli,$sql_update_status3) or die(mysqli_error($mysqli));
                            					
        						        //mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_history (order_id, source, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, cleared_date, cleared_by ) 
				                        //values ('$order_id_post', '$source', '$amount_post_esc', '$bpay_full_ref', '$payment_method_post', '$transaction_date_post_ymd', 'Balance', '$date_cleared', '$currnt_userlogn', '$settlement_date_post', '$currnt_userlogn' )") or die(mysqli_error($mysqli));
				                        
            						}
            						else
                                    {
                                        
                                        $previous_order_date = date("Y-m-d H:i:s");
                                        $query_select_booking_order_date = "SELECT order_date FROM wpk4_backend_travel_bookings where order_id='$order_id_post'";
                                        $result_select_booking_order_date = mysqli_query($mysqli, $query_select_booking_order_date);
                                        if(mysqli_num_rows($result_select_booking_order_date) > 0)
                                        {
                                        	$row_select_booking_order_date = mysqli_fetch_assoc($result_select_booking_order_date);
                                        	$previous_order_date = $row_select_booking_order_date['order_date'];
                                        }
                                        
                                        $payment_refund_deadline = date('Y-m-d H:i:s', strtotime($previous_order_date . ' +96 hours'));
                                        

                                        mysqli_query($mysqli,"insert into wpk4_backend_travel_payment_history (order_id, source, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, cleared_date, cleared_by, payment_change_deadline )
                                        values ('$order_id_post', '$source', '$amount_post_esc', '$bpay_full_ref', '$payment_method_post', '$transaction_date_post_ymd', 'Balance', '$date_cleared', '$currnt_userlogn', '$settlement_date_post', '$currnt_userlogn', '$payment_refund_deadline' )") or die(mysqli_error($mysqli));
                                    }
            						
				                    if($bpay_payment_status_post != '')
				                    {
				                        $sql_update_status2 = "UPDATE wpk4_backend_travel_bookings SET 
                							payment_status = '$bpay_payment_status_post',
                							payment_modified = '$date_cleared',
                							payment_modified_by = '$currnt_userlogn'
            							WHERE order_id = '$order_id_post' AND payment_status = 'partially_paid'";
            							$result_status = mysqli_query($mysqli,$sql_update_status2) or die(mysqli_error($mysqli));
            							
            							if($bpay_payment_status_post == 'paid')
            							{
            							    $source2 = '';
            							    if (ctype_digit($order_id_post) && strlen($order_id_post) <= 7) {
            									$source2 = 'WPT';
                                            }
                							
                                            if($source2 == 'WPT')
                                            {
                                                gdeal_name_update_ajax($order_id_post);	
        							
                                        		mysqli_query($mysqli,"insert into wpk4_amadeus_name_update_payment_status_log (order_id, order_type, updated_by, updated_on, is_processed_amadeus, amadeus_processed_on, page_title ) 
                                        		values ('$order_id_post', 'WPT', '$currnt_userlogn', '$date_cleared', 'yes', '$date_cleared', 'BPAY Import')") or die(mysqli_error($mysqli));
                                            }
            							}
				                    }
        						}
        						
        						if($payment_method_post == '8') // Asiapay
        						{
        						    $transaction_type_post = ($post_values[7] ?? false) ? $post_values[7] : '' ;
        						    if($transaction_type_post == 'PAYID')
        						    {
        						        $sql_update_status2 = "UPDATE wpk4_backend_travel_payment_history SET 
                						trams_received_amount = '$amount_post', modified_by = 'import_reconcile_asiapay', modified_date = '$date_cleared'
            						    WHERE order_id = '$order_id_post' and reference_no = '$reference_no_post' and payment_method = '8' ";
            						    $result_status = mysqli_query($mysqli,$sql_update_status2) or die(mysqli_error($mysqli));
        						    }
        						    else
        						    {
				                        $sql_update_status2 = "UPDATE wpk4_backend_travel_payment_history SET 
                						trams_received_amount = '$amount_post', modified_by = 'import_reconcile_asiapay', modified_date = '$date_cleared'
            						    WHERE order_id = '$order_id_post' and reference_no = '$reference_no_post' and payment_method = '8' and ABS(CAST(trams_received_amount AS DECIMAL(10,2)) - CAST('$amount_post' AS DECIMAL(10,2))) < 0.5";
            						    $result_status = mysqli_query($mysqli,$sql_update_status2) or die(mysqli_error($mysqli));
        						    }
        						}
        						
        						$amount_post_esc = $mysqli->real_escape_string($amount_post);
        						
        						// reconcile additional method
        						if($payment_method_post == '9') 
        						{
        						    //AND reference_no = '".$bpay_full_ref."' 
        						    $payment_additional_stack = " AND order_id = '$order_id_post' ";
        						}
        						else if($payment_method_post == '8') 
        						{
        						    $payment_additional_stack = " AND order_id = '$order_id_post' AND reference_no = '".$reference_no_post."' ";
        						}
        						else if($payment_method_post == '16') 
        						{
        						    //AND reference_no = '".$reference_no_post."'
        						    $payment_additional_stack = " AND order_id = '$order_id_post'  ";
        						}
        						else if($payment_method_post == '7') 
        						{
        						    $post_payment_request_id = $post_values[7];
        						    
        						    //$transaction_date_without_seconds = substr($transaction_date_post, 0, 16);
        						    $transaction_date_without_seconds = substr($transaction_date_post, 0, 10);
        						    
        						    //$payment_additional_stack = " AND payment_request_id = '".$post_payment_request_id."' AND DATE_FORMAT(process_date, '%Y-%m-%d %H:%i') = '" . $transaction_date_without_seconds . "' ";
        						    $payment_additional_stack = " AND order_id = '$order_id_post' AND payment_request_id = '".$post_payment_request_id."' AND DATE_FORMAT(process_date, '%Y-%m-%d') = '" . $transaction_date_without_seconds . "' ";

        						}
        						else
        						{
        						    $payment_additional_stack = " AND order_id = '$order_id_post'";
        						}
        						// reconcile additional method ends
        						
        						$sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
                							is_reconciliated = 'yes',
                							cleared_date = '$settlement_date_post',
                							cleared_by = '$currnt_userlogn'
            							WHERE ( CAST(trams_received_amount AS DECIMAL(10,2)) = CAST('$amount_post_esc' AS DECIMAL(10,2)) ) AND payment_method = '$payment_method_post' $payment_additional_stack";
            					if( $currnt_userlogn == 'sriharshans' || $currnt_userlogn == 'lee' )
            		            {		
            					    echo $sql_update_status.'</br></br>';
            		            }
            		            $result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
            	            	
        					}
        					//echo '<hr>';
        				}
        			    echo '<script>alert("Updated successfully.");</script>';
        				//echo '<script>window.location.href="?pg=import-reconcile-payments";</script>';
        			}
                }
                
                if($_GET['pg'] == 'import-reconcile-and-update-reference' && (current_user_can( 'administrator' ) || current_user_can( 'ho_payment' ) ))
                {
                    if(!isset($_POST["import_reconcile_to_view2"])) 
        			{
                    ?>
                    <style>
                    .radio-group {
                        display: flex;
                        align-items: center;
                        margin:auto;
                        width:500px;
                    }
                                
                    .radio-group label {
                        margin-right: 10px;
                        margin-top:13px;
                    }
                    </style>
            		<center>
            		</br></br></br>
            		<form class="form-horizontal" action="?pg=import-reconcile-and-update-reference" method="post" name="uploadCSV" enctype="multipart/form-data">
            			<div class="input-row">
            				<label class="col-md-4 control-label">Choose Bank type and attach file</label>
            				</br>
            				
            				<div class="radio-group" >
                				<input type="radio" id="Asiapay" required name="banktypes" value="Asiapay"> <label for="Asiapay"> Mint FIT</label>
                				
            				</div>
            				</br>
            				<input type="file" required name="file" id="file" accept=".csv" style="display:block;"></br>
            				<input type="submit" id="submit" style='height:30px; width:70px; font-size:12px; padding:7px; margin:0px;' name="import_reconcile_to_view2"></input>
            				<br />
            			</div>
            			<div id="labelError"></div>
            		</form>
            		</center>
                    <?php
                    }
                    //UPDATE wpk4_backend_travel_payment_history SET is_reconciliated = 'yes', cleared_date = '2024-07-19 13:50:50', cleared_by = 'sriharshans' WHERE order_id = '529765564' AND trams_received_amount = '218.11' AND payment_method = '8'

                    if(isset($_POST["import_reconcile_to_view2"])) 
        			{
        			    //ini_set('error_reporting',E_ALL);
                        //ini_set('display_errors','On');
                        //ini_set('display_errors', '1');
                        //ini_set('display_startup_errors', '1');

        			    $bank_type = $_POST["banktypes"];
        				$fileName = $_FILES["file"]["tmp_name"];
        				echo 'Importing '.$bank_type;
        				if ($_FILES["file"]["size"] > 0) 
        				{
        					$file = fopen($fileName, "r");
        					echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
        					$tablestirng = "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
        					<tr>
        						<td>#</td>
        						<td>Date</td>
        						<td>OrderID</td>
        						<td>Amount</td>
        						<td>Current Payment Status</td>";
        						if($bank_type == 'BPAY')
        						{
        						    $tablestirng .= "<td>New Payment Status</td>
        						    <td>Note</td>";
        						}
        						$tablestirng .= "<td>Reference</td>
        						<td>Settlement Date</td>
        						<td width='10%;'>Message</td>
        						<td>Existing/New</td>
        					</tr>";
        					$autonumber = 1;
        					
        					while (($column = fgetcsv($file, 10000, ",")) !== FALSE) 
        					{
        					    $non_matching_reasons = '';
        					    $is_matched_any_condition = 0;
        						if(($column[0] == 'Transaction Date' && $column[1] == 'Merchant Ref.') || ($column[0] == 'DateTime' && $column[1] == 'LocalTime') || ($column[0] == 'Data Type' && $column[1] == 'Payment Instruction Type') || ($column[0] == 'Transaction type' && $column[1] == 'Biller code'))
        						{
        							// Do Nothing
        							//($column[0] == 'Transaction Date' && $column[1] == 'Merchant Ref.') - Asiapay
        							//$column[0] == 'DateTime' && $column[1] == 'LocalTime' - Azupay (required to skip when the TransactionType = Sweep)
        							//($column[0] == 'Data Type' && $column[1] == 'Payment Instruction Type') - BPAY
        							//($column[0] == 'Transaction type' && $column[1] == 'Biller code') - BPOINT
        						}
        						else
        						{
            						if($bank_type == 'Asiapay' && isset($column[2]) && $column[2] != '' && isset($column[9]) && isset($column[2])) // Mint FIT
            						{
            						    $payment_method_number = '8';
                                        $transaction_date = $column[0]; // Transaction Date
                                        $transaction_type = $column[3];
                                        $customers_ip = $column[15];
                                        $transaction_date = strtotime(str_replace('/', '-', $transaction_date));
                                        $new_transaction_date = date('Y-m-d H:i:s', $transaction_date);
                                        $new_transaction_date_ymd = date('Y-m-d', $transaction_date);
                                        
                                        
                            
            				
                        				 //$sql_by_ip = "SELECT order_id FROM wpk4_backend_travel_bookings where ip_address = '$customers_ip' and date(order_date) = '$new_transaction_date_ymd' ";
                                        
            							    
                                        if(isset($column[18]) && $column[18] != '')
                                        {
                                            $settlement_date = $column[18]; // Settlement Date
                                            $settlement_date = strtotime(str_replace('/', '-', $settlement_date));
                                            $new_settlement_date = date('Y-m-d H:i:s', $settlement_date);
                                        }
                                        else
                                        {
                                            $new_settlement_date = date('Y-m-d H:i:s');
                                        }
                                        
            							$order_id = ltrim($column[2], '0'); // System Ref.
            							$amount = number_format((float)$column[9], 2, '.', ''); // Amount
            							
            							if($new_transaction_date != '1970-01-01 10:00:00')
            							{
            							    $sql_by_ip = "SELECT 
                    			                bookings.order_id, bookings.payment_status, payments.reference_no
                                			    FROM wpk4_backend_travel_payment_history payments
                                			    JOIN wpk4_backend_travel_bookings bookings ON 
                                                    payments.order_id = bookings.order_id
                                				where 
                            					bookings.ip_address = '$customers_ip' and date(bookings.order_date) = '$new_transaction_date_ymd' and 
                            					CAST(payments.trams_received_amount AS DECIMAL(10,2)) = 0.00 AND payments.payment_method = '8'";
                            				$result_by_ip = $mysqli->query($sql_by_ip);
                            				//echo $sql_by_ip.'</br>';
                            				$row_by_ip = $result_by_ip->fetch_assoc();
                            				if($result_by_ip->num_rows > 0)
                    						{
                            					$order_id_from_booking_table = $row_by_ip['order_id'];
                            					$payment_status_from_booking_table = $row_by_ip['payment_status'];
                            					$payment_reference_from_payment = $row_by_ip['reference_no'];
                    						}
            							    else
            							    {
            							        $order_id_from_booking_table = '';
                    						    $payment_status_from_booking_table = '';
                    						    $payment_reference_from_payment = $row_by_ip['reference_no'];
            							    }
            							    $match = [];
            							    $new_order_id = '';
            							    if($order_id == $order_id_from_booking_table)
                								{
                								    $new_order_id = $order_id;
                								    $is_booking_exists = true;
                								}
                								else if ($order_id != $order_id_from_booking_table && $order_id_from_booking_table != '')
                								{
                								    $new_order_id = $order_id_from_booking_table;
                								    $is_booking_exists = true;
                								    $match[] = "<font style='color:red;'>Different booking found</font></br>";
                								}
                								else 
                								{
                								    $new_order_id = '';
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font></br>";
                								}
    
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$new_order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table."</td>
                								<td>".$order_id."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								
                								
                								
                								
                								$is_amount_mismatch = '';
                								//if($transaction_type == 'PAYID')
        						                {
                								    $sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$new_order_id' and 
                								    CAST(trams_received_amount AS DECIMAL(10,2)) = 0.00 and payment_method = '8'";
                								    //echo $sql_2.'</br>';
                								    $is_amount_mismatch = 'Amount & reference will be overwrite</br>';
        						                }
        						                
                    							$result_2 = $mysqli->query($sql_2);
                    							if( $currnt_userlogn == 'sriharshans')
                            		            {
                            		                //echo $sql_2.'</br></br>';
                            		            }
                    							$row_2 = $result_2->fetch_assoc();
                    							if($result_2->num_rows > 0)
            							        {
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
            							        else
            							        {
            							            $order_id_from_payment_table = '';
            							        }
                							
                								if($new_order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font></br>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>".$is_amount_mismatch;
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							            
                							            if(isset($match[2]) && $match[2] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[2];
                							            }
                							 $tablestirng.= "</td>";
                																	
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$new_order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$transaction_type."' ".$checked." \/></td></tr>";
                
                							
            							}
            						}
            						
            						$autonumber++;
        							
        						}
        					}
        					
        					$tablestirng.= "</table>";
        					echo $tablestirng;
        					?>
        					<br><br><input type="submit" name="submit_reconcile_import2" value="Update"/></form></center>
        					<?php
        				}
        			}
        			if (isset($_POST["submit_reconcile_import2"])) 
        			{
        				foreach ($_POST as $post_field_name => $post_fieldvalue) 
        				{
        					$post_name_dividants = explode('_', $post_field_name);
        					$postname_auto_id = $post_name_dividants[0];
    						$postname_fieldname = $post_name_dividants[1];
        					$check_whether_its_ticked = $postname_auto_id.'_checkoption';

        					if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
        					{
        						$post_value_get = $_POST[$post_field_name];
        						$post_values = explode('@#', $post_value_get);
        						
        						$order_id_post = $post_values[0];	
    							$amount_post = $post_values[1];
    							$transaction_date_post = $post_values[2];
    							$reference_no_post = $post_values[3];
    							$payment_method_post = $post_values[4];
    							$match_hidden_post = $post_values[5];
    							$settlement_date_post = $post_values[6];

        						$date_cleared = date("Y-m-d H:i:s");
        						
        						
        						if($payment_method_post == '8') // asiapay
        						{
        						    $transaction_type_post = $post_values[7];
        						    if($transaction_type_post == 'PAYID')
        						    {
        						        $sql_update_status2 = "UPDATE wpk4_backend_travel_payment_history SET 
                						trams_received_amount = '$amount_post', reference_no = '$reference_no_post', modified_by = 'import_reconcile_asiapay_update', modified_date = '$date_cleared'
            						    WHERE order_id = '$order_id_post' and payment_method = '8' ";
            						    $result_status = mysqli_query($mysqli,$sql_update_status2) or die(mysqli_error($mysqli));
        						    }
        						    else
        						    {
				                        $sql_update_status2 = "UPDATE wpk4_backend_travel_payment_history SET 
                						trams_received_amount = '$amount_post', reference_no = '$reference_no_post', modified_by = 'import_reconcile_asiapay_update', modified_date = '$date_cleared'
            						    WHERE order_id = '$order_id_post' and payment_method = '8' and CAST(trams_received_amount AS DECIMAL(10,2)) = 0.00";
            						    $result_status = mysqli_query($mysqli,$sql_update_status2) or die(mysqli_error($mysqli));
        						    }
        						    echo $sql_update_status2;
        						    
        						    $wpdb->insert('wpk4_backend_history_of_updates', array(
                                                        'type_id' =>$order_id_post,
                                                        'meta_key' =>'payment_reference_update',
                                                        'meta_value' => $reference_no_post,
                                                        'updated_by' =>$currnt_userlogn,
                                                        'updated_on' => $date_cleared,
                                                ));
                                                
        						}
        						
        						$amount_post_esc = $mysqli->real_escape_string($amount_post);
        						
        						if($payment_method_post == '8') 
        						{
        						    $payment_additional_stack = " AND order_id = '$order_id_post' AND reference_no = '".$reference_no_post."' ";
        						}
        						else
        						{
        						    $payment_additional_stack = "";
        						}
        						// reconcile additional method ends
        						
        						$sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
                							is_reconciliated = 'yes',
                							cleared_date = '$settlement_date_post',
                							cleared_by = '$currnt_userlogn'
            							WHERE ( CAST(trams_received_amount AS DECIMAL(10,2)) = CAST('$amount_post_esc' AS DECIMAL(10,2)) ) AND payment_method = '$payment_method_post' $payment_additional_stack";
            					if( $currnt_userlogn == 'sriharshans')
            		            {		
            					    echo $sql_update_status.'</br></br>';
            		            }
            		            $result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
            	            	
        					}
        					//echo '<hr>';
        				}
        			    echo '<script>alert("Updated successfully.");</script>';
        				echo '<script>window.location.href="?pg=import-reconcile-and-update-reference";</script>';
        			}
                }
                
                if($_GET['pg'] == 'view-orphan-payments' && (current_user_can( 'administrator' ) ) )
                {
                    ?>
            		<h5>Manage orphan payments</h5>
            		<?php
            		$query = "SELECT ph.* FROM wpk4_backend_travel_payment_history ph 
            		LEFT JOIN wpk4_backend_travel_bookings b ON ph.order_id = b.order_id 
            		WHERE b.order_id IS NULL and ph.trams_received_amount != '0' and date(ph.process_date) > '2025-01-01' 
            		ORDER BY `auto_id` DESC";
            		
            		if( $currnt_userlogn == 'sriharshans')
            		{
            		   //echo $query;	
            		}
            		$selection_query = $query;
            		$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            		$row_counter_ticket = mysqli_num_rows($result);
            		$auto_numbering = 1;
            		$total_paxs = 0;
            		
            		?>
            		</br>
            		<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
            			<thead>
                			<tr>
                    			<th>Payment Date</th>
                    			<th>Reference No</th>
                    			<th>Received Amount</th>
                    			<th>Payment Method</th>
                    			
                    			<th>Possible Booking ID</th>
                    			<th></th>
                			</tr>
            			</thead>
            			<tbody>
                    	    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        		<?php
                                $processedOrders = [];
                        		while($row = mysqli_fetch_assoc($result))
                        		{
                        			$auto_id = $row['auto_id'];
                        			
                        			if (in_array($auto_id, $processedOrders)) {
                            			continue; // Skip to the next iteration if the order ID is already processed
                            		}
                            		$processedOrders[] = $auto_id;
                        			$order_id = $row['order_id'];
                        			$process_date = $row['process_date'];
                        			$trams_remarks = $row['trams_remarks'];
                        			$trams_received_amount = $row['trams_received_amount'];
                        			$reference_no = $row['reference_no'];
                        			$payment_method = $row['payment_method'];
                        			$pay_type = $row['pay_type'];

                        			if ( ctype_digit($order_id) && strlen($order_id) <= 7 ) {
                                			$source = 'WPT';
                                    	} elseif (ctype_alpha($order_id)) {
                                    		$source = 'gds';
                                    	} else {
                                    		$source = 'gds';
                                    	}
                                    	
                        			?>
                        			<tr>
                            			<td width='6%'>
                                            <?php echo $process_date; ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php echo $reference_no; ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php echo $trams_received_amount; ?>            	
                                        </td>
                                        <td width='7%'>
                                            <?php 
                                            $query_payment_method = "SELECT account_name FROM wpk4_backend_accounts_bank_account where bank_id = $payment_method";
                                        		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                                        		$row_payment_method = mysqli_fetch_assoc($result_payment_method);
                                    		    if(mysqli_num_rows($result_payment_method) > 0)
                                    		    {
                                    		        echo $row_payment_method['account_name'];  
                                    		    }
                                    		    else
                                    		    {
                                    		        echo 'Unknown';
                                    		    }
                                            ?>            	
                                        </td>
                                        
                                         <td width='5%'>
                                            <?php 
                                            $possible_order_id = '';
                                                $query_main_booking_finding = "SELECT b.order_id
                                                    FROM wpk4_backend_travel_bookings b
                                                    WHERE ABS(TIMESTAMPDIFF(HOUR, b.order_date, '$process_date')) <= 1
                                                      AND b.order_type = '$source' AND ABS((b.total_amount * 0.05) - $trams_received_amount) <= 1;";
                                        		$result_main_booking_finding = mysqli_query($mysqli, $query_main_booking_finding) or die(mysqli_error($mysqli));
                                    		    if(mysqli_num_rows($result_main_booking_finding) > 0)
                                    		    {
                                    		        while($row_main_booking_finding = mysqli_fetch_assoc($result_main_booking_finding))
                                        		    {
                                        		        $finding_order_id = $row_main_booking_finding['order_id'];
                                        		        
                                        		        $query_payment_finder = "SELECT order_id FROM wpk4_backend_travel_payment_history where order_id = '$finding_order_id' and trams_received_amount = '0'";
                                                		$result_payment_finder = mysqli_query($mysqli, $query_payment_finder) or die(mysqli_error($mysqli));
                                            		    if(mysqli_num_rows($result_payment_finder) > 0)
                                            		    {
                                            		        $possible_order_id .= $finding_order_id.', '; 
                                    		                echo $row_main_booking_finding['order_id'].'</br>'; 
                                            		    }
                                        		    }
                                    		    }
                                    		    else
                                    		    {
                                    		        echo '-';
                                    		    }
                                            ?>         	
                                        </td>
                                        <td width='5%'>
                                            <?php
                                            if($possible_order_id != '')
                                            {
                                            ?>
                                            <a href="?pg=update-orphan-payments&auto_id=<?php echo $auto_id; ?>&possible_orders=<?php echo $possible_order_id; ?>">Update</a>
                                            <?php
                                            }
                                            ?>
                                        </td>
                        		    </tr>
                        			<?php
                        			$auto_numbering++;
                        		}
                        		?>
                    		</form>
                    	</tbody>
                    </table>
            		</br></br>
                    <?php
                }
                
                if($_GET['pg'] == 'update-orphan-payments' && (current_user_can( 'administrator' ) ) )
                {
                    $auto_id = $_GET['auto_id'];
                    $possible_order_id = $_GET['possible_orders'];
                    echo 'Possible Order ID(s): ' . $possible_order_id .'</br></br></br>';
                    ?>
                    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                        <table class="table table-striped accounts_general_table">
        				    <tbody>
                				<tr>
                    				<td width="20%">New OrderID : </td>
                    				<td><input type='text' name='new_order_id' required></td>
                    			</tr>
                				<tr>	
                    				<td colspan="2"><center><input type='submit' name='save_order_id' style="padding:15px; margin:0; font-size:11px;" value='Update Order ID'></center></td>
                				</tr>
            				</tbody>
        				</table>
    				</form>
                    <?php
                    if(isset($_POST['save_order_id']))
				    {
				        $new_order_id = $_POST['new_order_id'];
                        $auto_id = $_GET['auto_id'];
                        
				        $sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
    										        order_id = '$new_order_id' WHERE auto_id = '$auto_id'";
    					//echo $sql_update_status;
    					$result_status= mysqli_query($mysqli,$sql_update_status);
    									
    					mysqli_query($mysqli,"insert into wpk4_backend_travel_booking_update_history (order_id, pax_auto_id, meta_key, meta_value, meta_key_data, updated_time, updated_user) 
						values ('$new_order_id', '', 'new_order_id', '$new_order_id', 'New order id updated through Orphan order', '$current_date_and_time', '$currnt_userlogn')") or die(mysqli_error($mysqli));

                        echo '<script>window.location.href="?pg=view-orphan-payments";</script>';
				    }
                }
            } // end of inside pages
        }
        else
        {
            echo "<center>This page is not accessible for you.</center>";
        }
        ?>
    </div>
<?php get_footer(); ?>