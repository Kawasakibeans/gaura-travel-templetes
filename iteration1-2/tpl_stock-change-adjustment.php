<?php
/**
 * Template Name: Stock Adjustment
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<html> 
<head>
</head>
<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
error_reporting(E_ALL);
date_default_timezone_set("Australia/Melbourne");
include("wp-config-custom.php");
$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
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
<?php get_footer(); ?>