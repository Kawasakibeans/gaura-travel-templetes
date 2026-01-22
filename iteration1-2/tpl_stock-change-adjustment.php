<?php
/**
 * Template Name: Stock Adjustment
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

error_reporting(E_ALL);
date_default_timezone_set("Australia/Melbourne");
$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

global $wpdb, $current_user;

wp_get_current_user();
$currnt_userlogn = $current_user->user_login ?? 'system';

// Check if this is an API request
$is_api_request = false;
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = array_filter(explode('/', trim($path, '/')));
$path_parts = array_values($path_parts);

// Check if path contains API indicators
for ($i = 0; $i < count($path_parts); $i++) {
    if ($path_parts[$i] === 'stock' && isset($path_parts[$i + 1]) && $path_parts[$i + 1] === 'adjustment') {
        $is_api_request = true;
        break;
    }
}

// If API request, handle it and exit
if ($is_api_request) {
    header('Content-Type: application/json');
    
    // Helper functions
    function sendResponse($status, $data = null, $message = null, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'status' => $status,
            'data' => $data,
            'message' => $message
        ]);
        exit;
    }
    
    function sendError($message, $code = 500) {
        sendResponse('error', null, $message, $code);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Extract resource from path
    $resource = null;
    for ($i = 0; $i < count($path_parts); $i++) {
        if ($path_parts[$i] === 'adjustment' && isset($path_parts[$i + 1])) {
            $resource = $path_parts[$i + 1];
            break;
        }
    }
    
    try {
        // POST /v1/stock/adjustment/preview - Preview stock adjustment
        if ($method === 'POST' && $resource === 'preview') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                $input = $_POST;
            }
            
            $pnr = isset($input['pnr']) ? trim($input['pnr']) : '';
            $limit = isset($input['limit']) ? intval($input['limit']) : 50;
            
            $current_date = date("Y-m-d H:i:s");
            $current_date_starting = date("Y-m-d") . ' 00:00:00';
            
            $records = [];
            
            if (!empty($pnr)) {
                // Individual (by PNR)
                // Original Query:
                // SELECT * FROM wpk4_backend_stock_management_sheet 
                // WHERE pnr = :pnr 
                //   AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                // ORDER BY dep_date ASC
                
                $stock_records = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}backend_stock_management_sheet 
                         WHERE pnr = %s 
                         AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                         ORDER BY dep_date ASC",
                        $pnr
                    ),
                    ARRAY_A
                );
            } else {
                // Bulk (recent changes)
                // Original Query:
                // SELECT * FROM wpk4_backend_stock_management_sheet 
                // WHERE modified_date <= :current_date 
                //   AND modified_date >= :current_date_starting 
                //   AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                // ORDER BY dep_date ASC 
                // LIMIT :limit
                
                $stock_records = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}backend_stock_management_sheet 
                         WHERE modified_date <= %s 
                         AND modified_date >= %s 
                         AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                         ORDER BY dep_date ASC 
                         LIMIT %d",
                        $current_date,
                        $current_date_starting,
                        $limit
                    ),
                    ARRAY_A
                );
            }
            
            foreach ($stock_records as $row) {
                $pnr_item = $row['pnr'];
                $dep_date = $row['dep_date'];
                $trip_id = $row['trip_id'];
                $current_stock = intval($row['current_stock']);
                $current_stock_dummy = $row['current_stock_dummy'];
                $stock_unuse = $row['stock_unuse'];
                
                // Get product and pricing info
                // Original Query:
                // SELECT * FROM wpk4_backend_stock_product_manager 
                // WHERE trip_code = :trip_id AND travel_date = :dep_date
                
                $product_info = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}backend_stock_product_manager 
                         WHERE trip_code = %s AND travel_date = %s",
                        $trip_id,
                        $dep_date
                    ),
                    ARRAY_A
                );
                
                $product_id = '';
                $pricing_id = '';
                if ($product_info) {
                    $product_id = $product_info['product_id'] ?? '';
                    $pricing_id = $product_info['pricing_id'] ?? '';
                }
                
                // Get max_pax from pricing
                $max_pax_original = 0;
                if (!empty($pricing_id)) {
                    // Original Query:
                    // SELECT * FROM wpk4_wt_pricings WHERE id = :pricing_id
                    
                    $pricing_info = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}wt_pricings WHERE id = %s",
                            $pricing_id
                        ),
                        ARRAY_A
                    );
                    
                    if ($pricing_info) {
                        $max_pax_original = intval($pricing_info['max_pax'] ?? 0);
                    }
                }
                
                // Get total paid pax
                // Original Query:
                // SELECT total_pax FROM wpk4_backend_travel_bookings 
                // WHERE trip_code = :trip_id AND travel_date = :dep_date 
                //   AND (payment_status = 'paid' OR payment_status = 'partially_paid')
                
                $total_pax = 0;
                $paid_bookings = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT total_pax FROM {$wpdb->prefix}backend_travel_bookings 
                         WHERE trip_code = %s AND travel_date = %s 
                         AND (payment_status = 'paid' OR payment_status = 'partially_paid')",
                        $trip_id,
                        $dep_date
                    ),
                    ARRAY_A
                );
                
                foreach ($paid_bookings as $booking) {
                    $total_pax += intval($booking['total_pax'] ?? 0);
                }
                
                // Get total booked pax
                $total_booked = 0;
                $all_bookings = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT total_pax FROM {$wpdb->prefix}backend_travel_bookings 
                         WHERE trip_code = %s AND travel_date = %s",
                        $trip_id,
                        $dep_date
                    ),
                    ARRAY_A
                );
                
                foreach ($all_bookings as $booking) {
                    $total_booked += intval($booking['total_pax'] ?? 0);
                }
                
                // Calculate new_max_pax
                $new_max_pax = $max_pax_original + ($current_stock - intval($current_stock_dummy));
                
                // Determine match status
                $match = 'New';
                if (!empty($pnr_item)) {
                    if (empty($pricing_id)) {
                        $match = 'PricingID not found';
                    } else {
                        $match = 'Existing';
                    }
                }
                
                $records[] = [
                    'pnr' => $pnr_item,
                    'dep_date' => $dep_date,
                    'trip_id' => $trip_id,
                    'current_stock' => $current_stock,
                    'current_stock_dummy' => $current_stock_dummy,
                    'stock_unuse' => $stock_unuse,
                    'product_id' => $product_id,
                    'pricing_id' => $pricing_id,
                    'max_pax_original' => $max_pax_original,
                    'new_max_pax' => $new_max_pax,
                    'total_pax' => $total_pax,
                    'total_booked' => $total_booked,
                    'match' => $match
                ];
            }
            
            sendResponse('success', [
                'records' => $records,
                'count' => count($records)
            ], 'Stock adjustment preview retrieved successfully');
        }
        
        // POST /v1/stock/adjustment/apply - Apply stock adjustment
        if ($method === 'POST' && $resource === 'apply') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                $input = $_POST;
            }
            
            if (empty($input['records']) || !is_array($input['records'])) {
                sendError('records array is required', 400);
            }
            
            $records = $input['records'];
            $current_time_modified = date('Y-m-d H:i:s');
            $processed_pnrs = [];
            $applied_count = 0;
            
            foreach ($records as $record) {
                // Validate required fields
                $required = ['pnr', 'dep_date', 'trip_id', 'pricing_id', 'product_id', 'max_pax_original', 'new_max_pax', 'total_pax'];
                foreach ($required as $field) {
                    if (!isset($record[$field])) {
                        sendError("Field '{$field}' is required in record", 400);
                    }
                }
                
                $pnr = trim($record['pnr']);
                $dep_date = trim($record['dep_date']);
                $trip_id = trim($record['trip_id']);
                $pricing_id = trim($record['pricing_id']);
                $product_id = trim($record['product_id']);
                $max_pax_original = intval($record['max_pax_original']);
                $new_max_pax = intval($record['new_max_pax']);
                $total_pax = intval($record['total_pax']);
                
                // Apply adjustment logic
                if ($new_max_pax < 1 && $total_pax == 0) {
                    // Delete pricing and date records
                    // Original Query:
                    // DELETE FROM wpk4_wt_pricings WHERE id = :pricing_id AND trip_id = :product_id
                    // DELETE FROM wpk4_wt_dates WHERE pricing_ids = :pricing_id AND trip_id = :product_id AND end_date = :dep_date
                    
                    $wpdb->delete(
                        $wpdb->prefix . 'wt_pricings',
                        [
                            'id' => $pricing_id,
                            'trip_id' => $product_id
                        ],
                        ['%s', '%s']
                    );
                    
                    $wpdb->delete(
                        $wpdb->prefix . 'wt_dates',
                        [
                            'pricing_ids' => $pricing_id,
                            'trip_id' => $product_id,
                            'end_date' => $dep_date
                        ],
                        ['%s', '%s', '%s']
                    );
                } else {
                    // Update pricing max_pax
                    // Original Query:
                    // UPDATE wpk4_wt_pricings SET max_pax = :new_max_pax 
                    // WHERE id = :pricing_id AND trip_id = :product_id
                    
                    $wpdb->update(
                        $wpdb->prefix . 'wt_pricings',
                        ['max_pax' => $new_max_pax],
                        [
                            'id' => $pricing_id,
                            'trip_id' => $product_id
                        ],
                        ['%d'],
                        ['%s', '%s']
                    );
                }
                
                // Clear current_stock_dummy
                // Original Query:
                // UPDATE wpk4_backend_stock_management_sheet 
                // SET current_stock_dummy = '' 
                // WHERE pnr = :pnr
                
                $wpdb->update(
                    $wpdb->prefix . 'backend_stock_management_sheet',
                    ['current_stock_dummy' => ''],
                    ['pnr' => $pnr],
                    ['%s'],
                    ['%s']
                );
                
                if (!in_array($pnr, $processed_pnrs)) {
                    $processed_pnrs[] = $pnr;
                }
                $applied_count++;
            }
            
            // Clear remaining dummy values for processed PNRs
            foreach ($processed_pnrs as $pnr_clear) {
                $wpdb->update(
                    $wpdb->prefix . 'backend_stock_management_sheet',
                    ['current_stock_dummy' => ''],
                    [
                        'pnr' => $pnr_clear,
                        'current_stock_dummy' => ['!=', '']
                    ],
                    ['%s'],
                    ['%s', '%s']
                );
            }
            
            sendResponse('success', [
                'applied_count' => $applied_count,
                'processed_pnrs' => $processed_pnrs
            ], 'Stock adjustment applied successfully');
        }
        
        // Route not found
        sendError('Endpoint not found', 404);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    }
}

// Continue with original template code for HTML output
get_header();
?>
<html> 
<head>
</head>
<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
include("wp-config-custom.php");

// Original Query:
// SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address = :ip_address
$query_ip_selection = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}backend_ip_address_checkup WHERE ip_address = %s",
    $ip_address
);
$result_ip_selection = $wpdb->get_results($query_ip_selection, ARRAY_A);
$row_ip_selection = !empty($result_ip_selection) ? $result_ip_selection[0] : null;
$is_ip_matched = count($result_ip_selection);
if($row_ip_selection['ip_address'] == $ip_address)
{
global $current_user;
$currnt_userlogn = $current_user->user_login;
$user_roles = $current_user->roles;
$user_role = array_shift($user_roles);
if(current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ))
{
    
    
    
/* ================================================================================
Individual product stock adjustment START
================================================================================ */

    if (isset($_GET['pnr']) && $_GET['pnr'] !== '') {
    
    
    $pnrfromurl = $_GET['pnr'];
    	echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
				echo  "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
				<tr>
				    <td>#</td>
					<td>PNR</td>
					<td>Dep Date</td>
					<td>Trip Code</td>
					<td>Current Stock</td>
					<td>Stock Unuse</td>
					<td>Extras</td>
					<td></td>
				</tr>
				";
	$autonumber = 1;	
	$current_date = date("Y-m-d H:i:s");
	$current_date_starting = date("Y-m-d").' 00:00:00'; 
	//$current_date_plus_three = date("Y-m-d", strtotime("3 day", strtotime($current_date))).' 23:59:59'; 
	
	$sql = "SELECT * FROM wpk4_backend_stock_management_sheet where pnr='$pnrfromurl' && (current_stock_dummy != '' && current_stock_dummy IS NOT NULL) order by dep_date ASC";
	$selectedquery=$sql;
	$result = $mysqli->query($sql);
	while($row = $result->fetch_assoc())
		{
			$pnr = $row['pnr'];
			$dep_date = $row['dep_date'];
			$trip_id = $row['trip_id']; // tripcode
			$current_stock = (int)$row['current_stock'];
			$current_stock_dummy = $row['current_stock_dummy'];
			
			$stock_unuse = $row['stock_unuse'];
				
			$sql_product = "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date'";
			$result_product = $mysqli->query($sql_product);
			$row_product = $result_product->fetch_assoc();
			$product_id = '';
			$pricing_id = '';
			if($result_product->num_rows > 0)
			{
				$product_id = $row_product['product_id'];
				$pricing_id = $row_product['pricing_id'];
		    }
			
			$sql_maxpax = "SELECT * FROM wpk4_wt_pricings where id='$pricing_id'";
			$result_maxpax = $mysqli->query($sql_maxpax);
			$max_pax_original = 0;
			$row_maxpax = $result_maxpax->fetch_assoc();
			if($result_maxpax->num_rows > 0)
			{
				$max_pax_original = $row_maxpax['max_pax'];	
			}
			$total_pax = 0;
			$sql_pax = "SELECT * FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date' && (payment_status = 'paid' || payment_status = 'partially_paid')";
			$result_pax = $mysqli->query($sql_pax);
			while($row_pax = $result_pax->fetch_assoc())
			{
				$total_pax += $row_pax['total_pax'];
			}
			
			$total_booked = 0;
			$sql_pax = "SELECT * FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date'";
			$result_pax = $mysqli->query($sql_pax);
			while($row_pax = $result_pax->fetch_assoc())
			{
				$total_booked += $row_pax['total_pax'];
			}
			
			//$new_max_pax = $max_pax_original + (($current_stock - $total_pax) - ($max_pax_original - (int)$current_stock_dummy));
			
			$new_max_pax = $max_pax_original + ($current_stock - (int)$current_stock_dummy);
			
			//$remainingseats = $stock_unuse + ((int)$current_stock - (int)$total_pax);
			//$stock_unuse_new = $remainingseats;	// final stock unused value
			//$current_stock_new = (int)$current_stock - ((int)$current_stock - (int)$total_pax);	// final current stock
			$dep_date_cropped = date("Y-m-d", strtotime($dep_date)); 
			
			
			
					if($pnr)
						{ 
						    if ($pricing_id =='')
        					{
        					        $match= "PricingID not found";
                					$checked="";	
        					}
        					else
        					{
                            		$match= "Existing";
                					$checked="checked";	
        					}		
						}
					
					else 
						{
							$match = "New";
							$checked="";
						}	

				//if($current_stock != $max_pax_original)
					{	
						echo "<tr>
						<td>".$autonumber."</td>
								<td>".$pnr."</td>
								<td>".$dep_date_cropped."</td>
								<td>".$trip_id."</td>
								<td>".$current_stock."</td>
								<td>".$stock_unuse."</td>
								<td>
								Pricing ID: ".$pricing_id."</br>
								Product ID: ".$product_id."</br></br>
								Booking pax: ".$total_pax."</br>
								Max_pax Original: ".$max_pax_original."</br>
								Max Pax New: ".$new_max_pax."
								</td>
								<td>								
								<input type='hidden' name='".$pnr."___matchmaker' value='".$match."'>
								".$match."</td>";
								
								$mainbookingvalues = $pnr .'*@#@*'. $dep_date .'*@#@*'. $trip_id .'*@#@*'. $current_stock .'*@#@*'. $pricing_id .'*@#@*'. $product_id .'*@#@*'. $max_pax_original .'*@#@*'. $new_max_pax .'*@#@*'. $total_pax .'*@#@*';
								echo "<td>
								<input type='hidden' name='".$pnr."___stockvalues' value='".$mainbookingvalues."'>
								<input type='checkbox' id='chk".$pnr."' name='".$pnr."___checkoption' value='".$mainbookingvalues."' ".$checked." \/></td>
								</tr>";
						$autonumber++;			
					}
						
			}
		?>
			<tr><td colspan='10'><center><input type="submit" name="submit" value="Update records"/></td></center></tr></table></form></center>
		<?php
	if (isset($_POST["submit"])) 
	{
	   
	    
	global $current_user;
	wp_get_current_user();
	$current_usernme = $current_user->user_login;
	$current_time_modified = date('Y-m-d H:i:s');
	
		foreach ($_POST as $post_fieldname => $post_fieldvalue) 
		{
			$post_name_dividants = explode('___', $post_fieldname); // Eg: PNR___stockvalues, PNR___checkoption - 3 underscores as few PNR contains single underscore
			
			$postname_pnr = $post_name_dividants[0];
			$postname_fieldname = '';
			if(isset($post_name_dividants[1]))
			{
		    	$postname_fieldname = $post_name_dividants[1];
			}
			$check_whether_its_ticked = $postname_pnr.'___checkoption';
			
			if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
			{
			   
				$post_value_get = $_POST[$post_fieldname];
				$post_values = explode('*@#@*', $post_value_get);
				
				$pnr = $post_values[0];
				$dep_date = $post_values[1];
				$trip_id = $post_values[2];
				$current_stock = $post_values[3];
				$pricing_id = $post_values[4];
				$product_id = $post_values[5];
				$max_pax_original = $post_values[6];
				$new_max_pax = $post_values[7];
				$total_pax = $post_values[8];
			    if($new_max_pax < 1 && $total_pax == 0)
				{
				    $sql_update_pricing = "DELETE FROM wpk4_wt_pricings WHERE id='$pricing_id' AND trip_id='$product_id'";
				    $result_pricing = mysqli_query($mysqli,$sql_update_pricing) or die(mysqli_error($mysqli));
				    
                    $sql_update_date = "DELETE FROM wpk4_wt_dates WHERE pricing_ids='$pricing_id' AND trip_id='$product_id' AND end_date = '$dep_date'";
				    $result_date = mysqli_query($mysqli,$sql_update_date) or die(mysqli_error($mysqli));
				    
				}
				else
				{	
    				$sql_update_pricing = "UPDATE wpk4_wt_pricings SET max_pax='$new_max_pax'	WHERE id='$pricing_id' && trip_id='$product_id'";
    				$result_pricing= mysqli_query($mysqli,$sql_update_pricing) or die(mysqli_error($mysqli));
				}	
    				$sql_update_dummy = "UPDATE wpk4_backend_stock_management_sheet SET current_stock_dummy='' WHERE pnr='$pnr'";
    				$result_dummy= mysqli_query($mysqli,$sql_update_dummy) or die(mysqli_error($mysqli));
			
			}
			
			
		}
   // echo $selectedquery;
    	$result = $mysqli->query($selectedquery);
    	
	while($row = $result->fetch_assoc()){
	    $pnr=$row['pnr'];
	    $sql_remove_dummy="UPDATE wpk4_backend_stock_management_sheet SET current_stock_dummy='' where pnr='$pnr'and current_stock_dummy != ''";
	    $result_remove= mysqli_query($mysqli,$sql_remove_dummy) or die(mysqli_error($mysqli));
	   // echo '<br>'.$pnr;
	    
	}
	echo '<script>alert("Update successful.");</script>';
		//echo '<script>alert("Update on hold for approval.");</script>';
	echo '<script>window.location.href="?";</script>';
    }
     exit;

        
    }
    
/* ================================================================================
Individual product stock adjustment END
================================================================================== */
  
    
    
	echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
				echo  "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
				<tr>
				    <td>#</td>
					<td>PNR</td>
					<td>Dep Date</td>
					<td>Trip Code</td>
					<td>Current Stock</td>
					<td>Stock Unuse</td>
					<td>Extras</td>
					<td></td>
				</tr>
				";
	$autonumber = 1;	
	$current_date = date("Y-m-d H:i:s");
	$current_date_starting = date("Y-m-d").' 00:00:00'; 
	//$current_date_plus_three = date("Y-m-d", strtotime("3 day", strtotime($current_date))).' 23:59:59'; 
	
	$sql = "SELECT * FROM wpk4_backend_stock_management_sheet where modified_date <= '$current_date' AND modified_date >= '$current_date_starting' AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) order by dep_date ASC limit 50";
	$result = $mysqli->query($sql);
	echo '<h6>Showing '. $result->num_rows .' records</h6>';
	while($row = $result->fetch_assoc())
		{
			$pnr = $row['pnr'];
			$dep_date = $row['dep_date'];
			$trip_id = $row['trip_id']; // tripcode
			$current_stock = (int)$row['current_stock'];
			$current_stock_dummy = $row['current_stock_dummy'];
			
			$stock_unuse = $row['stock_unuse'];
				
			$sql_product = "SELECT product_id, pricing_id FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date'";
			$result_product = $mysqli->query($sql_product);
			$row_product = $result_product->fetch_assoc();
			$product_id = '';
			$pricing_id = '';
			if($result_product->num_rows> 0)
			{
				$product_id = $row_product['product_id'];
				$pricing_id = $row_product['pricing_id'];
			}
			
			$sql_maxpax = "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id'";
			$result_maxpax = $mysqli->query($sql_maxpax);
			$row_maxpax = $result_maxpax->fetch_assoc();
			$max_pax_original = 0;
			if($result_maxpax->num_rows > 0)
			{
			    $max_pax_original = $row_maxpax['max_pax'];	
			}
			
			$total_pax = 0;
			$sql_pax = "SELECT total_pax FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date' && (payment_status = 'paid' || payment_status = 'partially_paid')";
			$result_pax = $mysqli->query($sql_pax);
			while($row_pax = $result_pax->fetch_assoc())
			{
				$total_pax += $row_pax['total_pax'];
			}
			
			$total_booked = 0;
			$sql_pax = "SELECT total_pax FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date'";
			$result_pax = $mysqli->query($sql_pax);
			while($row_pax = $result_pax->fetch_assoc())
			{
				$total_booked += $row_pax['total_pax'];
			}
			
			//$new_max_pax = $max_pax_original + (($current_stock - $total_pax) - ($max_pax_original - (int)$current_stock_dummy));
			
			$new_max_pax = $max_pax_original + ($current_stock - (int)$current_stock_dummy);
			
			//$remainingseats = $stock_unuse + ((int)$current_stock - (int)$total_pax);
			//$stock_unuse_new = $remainingseats;	// final stock unused value
			//$current_stock_new = (int)$current_stock - ((int)$current_stock - (int)$total_pax);	// final current stock
			$dep_date_cropped = date("Y-m-d", strtotime($dep_date)); 
			
			
			
					if($pnr)
						{ 
						    if ($pricing_id =='')
        					{
        					        $match= "PricingID not found";
                					$checked="";	
        					}
        					else
        					{
                            		$match= "Existing";
                					$checked="checked";	
        					}		
						}
					
					else 
						{
							$match = "New";
							$checked="";
						}	

				//if($current_stock != $max_pax_original)
					{	
						echo "<tr>
						<td>".$autonumber."</td>
								<td>".$pnr."</td>
								<td>".$dep_date_cropped."</td>
								<td>".$trip_id."</td>
								<td>".$current_stock."</td>
								<td>".$stock_unuse."</td>
								<td>
								Pricing ID: ".$pricing_id."</br>
								Product ID: ".$product_id."</br></br>
								Booking pax: ".$total_pax."</br>
								Max_pax Original: ".$max_pax_original."</br>
								Max Pax New: ".$new_max_pax."
								</td>
								<td>								
								<input type='hidden' name='".$pnr."___matchmaker' value='".$match."'>
								".$match."</td>";
								
								$mainbookingvalues = $pnr .'*@#@*'. $dep_date .'*@#@*'. $trip_id .'*@#@*'. $current_stock .'*@#@*'. $pricing_id .'*@#@*'. $product_id .'*@#@*'. $max_pax_original .'*@#@*'. $new_max_pax .'*@#@*'. $total_pax .'*@#@*';
								echo "<td>
								<input type='hidden' name='".$pnr."___stockvalues' value='".$mainbookingvalues."'>
								<input type='checkbox' id='chk".$pnr."' name='".$pnr."___checkoption' value='".$mainbookingvalues."' ".$checked." \/></td>
								</tr>";
						$autonumber++;			
					}
						
			}
		?>
			<tr><td colspan='10'><center><input type="submit" name="submit" value="Update records"/></td></center></tr></table></form></center>
		<?php
	if (isset($_POST["submit"])) 
	{
	global $current_user;
	wp_get_current_user();
	$current_usernme = $current_user->user_login;
	$current_time_modified = date('Y-m-d H:i:s');
	
		foreach ($_POST as $post_fieldname => $post_fieldvalue) 
		{
			$post_name_dividants = explode('___', $post_fieldname); // Eg: PNR___stockvalues, PNR___checkoption - 3 underscores as few PNR contains single underscore
			
			$postname_pnr = $post_name_dividants[0];
			$postname_fieldname = '';
			if(isset($post_name_dividants[1]))
			{
			    $postname_fieldname = $post_name_dividants[1];
			}
			$check_whether_its_ticked = $postname_pnr.'___checkoption';
			
			if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
			{
			   
				$post_value_get = $_POST[$post_fieldname];
				$post_values = explode('*@#@*', $post_value_get);
				
				$pnr = $post_values[0];
				$dep_date = $post_values[1];
				$trip_id = $post_values[2];
				$current_stock = $post_values[3];
				$pricing_id = $post_values[4];
				$product_id = $post_values[5];
				$max_pax_original = $post_values[6];
				$new_max_pax = $post_values[7];
				$total_pax = $post_values[8];
				
				if($new_max_pax == 0 && $total_pax == 0)
				{
				    $sql_update_pricing = "DELETE FROM wpk4_wt_pricings WHERE id='$pricing_id' AND trip_id='$product_id'";
				    $result_pricing = mysqli_query($mysqli,$sql_update_pricing) or die(mysqli_error($mysqli));
				    
                    $sql_update_date = "DELETE FROM wpk4_wt_dates WHERE pricing_ids='$pricing_id' AND trip_id='$product_id' AND end_date = '$dep_date'";
				    $result_date = mysqli_query($mysqli,$sql_update_date) or die(mysqli_error($mysqli));
				    
				}
				else
				{
				    $sql_update_pricing = "UPDATE wpk4_wt_pricings SET max_pax='$new_max_pax'	WHERE id='$pricing_id' && trip_id='$product_id'";
				    $result_pricing= mysqli_query($mysqli,$sql_update_pricing) or die(mysqli_error($mysqli));
				}
				$sql_update_dummy = "UPDATE wpk4_backend_stock_management_sheet SET current_stock_dummy='' WHERE pnr='$pnr'";
				$result_dummy= mysqli_query($mysqli,$sql_update_dummy) or die(mysqli_error($mysqli));
			
			}
		}
		echo '<script>alert("Update successful.");</script>';
		//echo '<script>alert("Update on hold for approval.");</script>';
		echo '<script>window.location.href="?";</script>';
    }
}

?>
</br></br></br></br>
</br></br>
</div>
</body>	  
<?php
}
else
{
echo "<center>This page is not accessible for you.</center>";
}
?>
<?php 
/**
 * ============================================
 * POSTMAN TEST EXAMPLES
 * ============================================
 * 
 * 1. Preview Stock Adjustment (Individual by PNR)
 *    Method: POST
 *    URL: {{base_url}}/v1/stock/adjustment/preview
 *    Body (JSON):
 *      {
 *        "pnr": "ABC123"
 *      }
 * 
 * 2. Preview Stock Adjustment (Bulk - Recent Changes)
 *    Method: POST
 *    URL: {{base_url}}/v1/stock/adjustment/preview
 *    Body (JSON):
 *      {
 *        "limit": 50
 *      }
 *    Or empty body for default (limit 50)
 * 
 *    Response:
 *    {
 *      "status": "success",
 *      "data": {
 *        "records": [
 *          {
 *            "pnr": "ABC123",
 *            "dep_date": "2025-01-15",
 *            "trip_id": "TRIP001",
 *            "current_stock": 10,
 *            "current_stock_dummy": "5",
 *            "stock_unuse": 2,
 *            "product_id": "123",
 *            "pricing_id": "456",
 *            "max_pax_original": 20,
 *            "new_max_pax": 25,
 *            "total_pax": 5,
 *            "total_booked": 8,
 *            "match": "Existing"
 *          }
 *        ],
 *        "count": 1
 *      },
 *      "message": "Stock adjustment preview retrieved successfully"
 *    }
 * 
 * 3. Apply Stock Adjustment
 *    Method: POST
 *    URL: {{base_url}}/v1/stock/adjustment/apply
 *    Body (JSON):
 *      {
 *        "records": [
 *          {
 *            "pnr": "ABC123",
 *            "dep_date": "2025-01-15",
 *            "trip_id": "TRIP001",
 *            "pricing_id": "456",
 *            "product_id": "123",
 *            "max_pax_original": 20,
 *            "new_max_pax": 25,
 *            "total_pax": 5
 *          }
 *        ]
 *      }
 * 
 *    Response:
 *    {
 *      "status": "success",
 *      "data": {
 *        "applied_count": 1,
 *        "processed_pnrs": ["ABC123"]
 *      },
 *      "message": "Stock adjustment applied successfully"
 *    }
 */
get_footer(); ?>