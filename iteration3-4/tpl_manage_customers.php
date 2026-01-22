<?php
/**
 * Template Name: Manage Customers
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();
?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:90%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
global $current_user; 
wp_get_current_user();

$current_date_and_time = date("Y-m-d H:i:s");
include('wp-config-custom.php');

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
if(mysqli_num_rows($result_ip_selection) > 0)
{
    $currnt_userlogin = $current_user->user_login;
    if(!isset($_GET['option']))
    {
        echo '<script>window.location.href="?option=view";</script>';
    }
    if( isset($_GET['option']) && $_GET['option']=='view' )
    {
    ?>
		<script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
		<script>
        function searchordejs() 
		{
			var customer_id_selector = document.getElementById("customer_id_selector").value;
			var family_id_selector = document.getElementById("family_id_selector").value;	
			var profile_id_selector = document.getElementById("profile_id_selector").value;	
			var order_id_selector = document.getElementById("order_id_selector").value;	
			var email_id_selector = document.getElementById("email_id_selector").value;	
			var phone_selector = document.getElementById("phone_selector").value;	
			
			window.location='?option=view&customer_id=' + customer_id_selector + '&family_id=' + family_id_selector + '&profile_id=' + profile_id_selector + '&order_id=' + order_id_selector + '&email_id=' + email_id_selector + '&phone=' + phone_selector ;
		}
		</script>
		<h5>Manage Customers</h5>
    	<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
    	    <tr>
    	        <td width='8%'>
    			    Customer ID</br>
    			    <input type='text' name='customer_id_selector' value='<?php if(isset($_GET['customer_id'])) { echo $_GET['customer_id']; } ?>' id='customer_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Family ID</br>
    			    <input type='text' name='family_id_selector' value='<?php if(isset($_GET['family_id'])) { echo $_GET['family_id']; } ?>' id='family_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Trams Profile ID</br>
    			    <input type='text' name='profile_id_selector' value='<?php if(isset($_GET['profile_id'])) { echo $_GET['profile_id']; } ?>' id='profile_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Order ID</br>
    			    <input type='text' name='order_id_selector' value='<?php if(isset($_GET['order_id'])) { echo $_GET['order_id']; } ?>' id='order_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Email</br>
    			    <input type='text' name='email_id_selector' value='<?php if(isset($_GET['email_id'])) { echo $_GET['email_id']; } ?>' id='email_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Phone</br>
    			    <input type='text' name='phone_selector' value='<?php if(isset($_GET['phone'])) { echo $_GET['phone']; } ?>' id='phone_selector'>
    		    </td>
    		</tr>
    		<tr>
    			<td colspan="6" style='text-align:center;'>
    				<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
    			</td>
			</tr>
		</table>
		<?php
		$common_start_filter = date('Y-m-d').' 00:00:00';
		$common_end_filter = date('Y-m-d').' 23:59:59';
		
		$customer_id_filter = ($_GET['customer_id'] ?? false) ? $_GET['customer_id'] : '' ;
		$family_id_filter = ($_GET['family_id'] ?? false) ? $_GET['family_id'] : '' ;
		$profile_id_filter = ($_GET['profile_id'] ?? false) ? $_GET['profile_id'] : '' ;
		$order_id_filter = ($_GET['order_id'] ?? false) ? $_GET['order_id'] : '' ;
		$email_id_filter = ($_GET['email_id'] ?? false) ? $_GET['email_id'] : '' ;
		$phone_filter = ($_GET['phone'] ?? false) ? $_GET['phone'] : '' ;
		
		if(isset($customer_id_filter) && $customer_id_filter != '')
		{
			$customer_id_sql = "passenger.customer_id = '".$customer_id_filter."' AND ";
		}
		else
		{
			$customer_id_sql = "passenger.customer_id IS NOT NULL AND ";
		}
		
		if(isset($family_id_filter) && $family_id_filter != '')
		{
			$family_id_sql = "passenger.family_id = '".$family_id_filter."' AND ";
		}
		else
		{
			$family_id_sql = "passenger.customer_id IS NOT NULL AND ";
		}
		
		if(isset($profile_id_filter) && $profile_id_filter != '')
		{
			$profile_id_sql = "passenger.trams_profile_id = '".$profile_id_filter."' AND ";
		}
		else
		{
			$profile_id_sql = "passenger.customer_id IS NOT NULL AND ";
		}
		
		if(isset($order_id_filter) && $order_id_filter != '')
		{
			$order_id_sql = "bookings.order_id = '".$order_id_filter."' AND ";
		}
		else
		{
			$order_id_sql = "passenger.customer_id IS NOT NULL AND ";
		}
		
		if(isset($email_id_filter) && $email_id_filter != '')
		{
			$email_id_sql = "passenger.email_address = '".$email_id_filter."' AND ";
		}
		else
		{
			$email_id_sql = "passenger.customer_id IS NOT NULL AND ";
		}
		
		if(isset($phone_filter) && $phone_filter != '')
		{
			$phone_sql = "passenger.phone_number = '".$phone_filter."'";
		}
		else
		{
			$phone_sql = "passenger.customer_id IS NOT NULL";
		}
		
		if(
		    (isset($customer_id_filter) && $customer_id_filter != '') ||
		    (isset($family_id_filter) && $family_id_filter != '') ||
		    (isset($profile_id_filter) && $profile_id_filter != '') ||
		    (isset($order_id_filter) && $order_id_filter != '') ||
		    (isset($email_id_filter) && $email_id_filter != '') ||
		    (isset($phone_filter) && $phone_filter != '')
		  ) 
		{
			$query = "SELECT passenger.customer_id, passenger.family_id, passenger.fname, passenger.lname, passenger.email_address, passenger.phone_number, bookings.order_id 
			    FROM wpk4_backend_travel_passenger passenger
			    LEFT JOIN wpk4_backend_travel_passenger_address ads ON 
				    ads.address_id = passenger.address_id 
				
				LEFT JOIN wpk4_backend_travel_booking_pax pax ON 
                    pax.fname = passenger.fname AND 
                    pax.email_pax = passenger.email_address
                    
			    LEFT JOIN wpk4_backend_travel_bookings bookings ON 
                    bookings.order_id = pax.order_id

				where 
					$customer_id_sql
                    $family_id_sql
                    $profile_id_sql
                    $order_id_sql
                    $email_id_sql
                    $phone_sql
				order by passenger.customer_id desc LIMIT 10";
		}
		else
		{
		    $query = "SELECT passenger.customer_id, passenger.family_id, passenger.fname, passenger.lname, passenger.email_address, passenger.phone_number, bookings.order_id 
		    FROM wpk4_backend_travel_passenger passenger
			    LEFT JOIN wpk4_backend_travel_passenger_address ads ON 
				    ads.address_id = passenger.address_id 
			    LEFT JOIN wpk4_backend_travel_bookings bookings ON 
                    passenger.family_id = bookings.family_id   
                LEFT JOIN wpk4_backend_travel_booking_pax pax ON 
					passenger.customer_id = pax.customer_id
				
				order by passenger.customer_id desc LIMIT 10";
			echo '</br><center><p style="color:red;">Kindly add the filters to check the records individually.</p></center>';
		}
		//echo $query;		
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
        			<th>Customer ID</th>
        			<th>Name</th>
        			<th>Email ID</th>
        			<th>Phone Number</th>
        			<th>Order ID</th>
    			</tr>
			</thead>
			<tbody>
        	    <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
            		<?php
            		while($row = mysqli_fetch_assoc($result))
            		{
            			$customer_id = $row['customer_id'];
            		    $family_id = $row['family_id'];
            			$fullname = $row['fname'] . ' ' .$row['lname'];
            			$email_id = $row['email_address'];
            			$order_id = $row['order_id'];
            			$phone_number = $row['phone_number'];
            			?>
            			<tr>
                			<td width='6%'>
                                <?php echo $customer_id; ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo $fullname; ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo $email_id; ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo $phone_number; ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo $order_id; ?>            	
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
}
?>
</div>
<?php get_footer(); ?>