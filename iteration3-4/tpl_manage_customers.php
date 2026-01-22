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

// Load WordPress to get API_BASE_URL constant from wp-config.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Get API base URL (should be defined in wp-config.php)
$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

// Helper function to call API
function callAPI($url, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("API call failed: $curlError");
        return ['error' => $curlError, 'http_code' => $httpCode];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    
    error_log("API call returned HTTP $httpCode: " . substr($response, 0, 500));
    return ['error' => 'HTTP ' . $httpCode, 'response' => $response];
}
?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:90%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
global $current_user; 
wp_get_current_user();

$current_date_and_time = date("Y-m-d H:i:s");
include('wp-config-custom.php');

// Check IP (keep original query or create API endpoint)
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
    			    <input type='text' name='customer_id_selector' value='<?php if(isset($_GET['customer_id'])) { echo htmlspecialchars($_GET['customer_id']); } ?>' id='customer_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Family ID</br>
    			    <input type='text' name='family_id_selector' value='<?php if(isset($_GET['family_id'])) { echo htmlspecialchars($_GET['family_id']); } ?>' id='family_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Trams Profile ID</br>
    			    <input type='text' name='profile_id_selector' value='<?php if(isset($_GET['profile_id'])) { echo htmlspecialchars($_GET['profile_id']); } ?>' id='profile_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Order ID</br>
    			    <input type='text' name='order_id_selector' value='<?php if(isset($_GET['order_id'])) { echo htmlspecialchars($_GET['order_id']); } ?>' id='order_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Email</br>
    			    <input type='text' name='email_id_selector' value='<?php if(isset($_GET['email_id'])) { echo htmlspecialchars($_GET['email_id']); } ?>' id='email_id_selector'>
    		    </td>
    		    <td width='8%'>
    			    Phone</br>
    			    <input type='text' name='phone_selector' value='<?php if(isset($_GET['phone'])) { echo htmlspecialchars($_GET['phone']); } ?>' id='phone_selector'>
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
		
		// Build API query parameters
		$apiParams = [];
		if (!empty($customer_id_filter)) {
		    $apiParams['customer_id'] = $customer_id_filter;
		}
		if (!empty($family_id_filter)) {
		    $apiParams['family_id'] = $family_id_filter;
		}
		if (!empty($profile_id_filter)) {
		    $apiParams['profile_id'] = $profile_id_filter;
		}
		if (!empty($order_id_filter)) {
		    $apiParams['order_id'] = $order_id_filter;
		}
		if (!empty($email_id_filter)) {
		    $apiParams['email'] = $email_id_filter;
		}
		if (!empty($phone_filter)) {
		    $apiParams['phone'] = $phone_filter;
		}
		$apiParams['limit'] = 10;
		
		// Check if any filters are provided
		if(
		    (isset($customer_id_filter) && $customer_id_filter != '') ||
		    (isset($family_id_filter) && $family_id_filter != '') ||
		    (isset($profile_id_filter) && $profile_id_filter != '') ||
		    (isset($order_id_filter) && $order_id_filter != '') ||
		    (isset($email_id_filter) && $email_id_filter != '') ||
		    (isset($phone_filter) && $phone_filter != '')
		  ) 
		{
		    // Call API to search customers
		    $apiUrl = $base_url . '/customers/search?' . http_build_query($apiParams);
		    $customersApiResult = callAPI($apiUrl, 'GET');
		    
		    $customers = [];
		    if ($customersApiResult && isset($customersApiResult['status']) && $customersApiResult['status'] === 'success') {
		        $customers = $customersApiResult['data']['customers'] ?? [];
		    }
		}
		else
		{
		    echo '</br><center><p style="color:red;">Kindly add the filters to check the records individually.</p></center>';
		    $customers = [];
		}
		
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
            		$row_counter_ticket = count($customers);
            		$auto_numbering = 1;
            		$total_paxs = 0;
            		
            		foreach($customers as $row)
            		{
            			$customer_id = $row['customer_id'] ?? '';
            		    $family_id = $row['family_id'] ?? '';
            			$fullname = ($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '');
            			$email_id = $row['email_address'] ?? '';
            			$order_id = $row['order_id'] ?? '';
            			$phone_number = $row['phone_number'] ?? '';
            			?>
            			<tr>
                			<td width='6%'>
                                <?php echo htmlspecialchars($customer_id); ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo htmlspecialchars($fullname); ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo htmlspecialchars($email_id); ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo htmlspecialchars($phone_number); ?>            	
                            </td>
                            <td width='15%'>
                                <?php echo htmlspecialchars($order_id); ?>            	
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