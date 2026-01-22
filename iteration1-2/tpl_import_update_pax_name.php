<?php
/**
 * Template Name: Import Update Pax Names
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
// ✅ FIX: Removed direct SQL queries - now using API endpoints for all operations
// OLD DATABASE CONNECTION - COMMENTED OUT (now using API endpoints)
/*
include("wp-config-custom.php");
*/

date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
$current_time = date('Y-m-d H:i:s');

// API Configuration
$apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public';

// API Helper Functions
function checkIpAddressViaAPI($ipAddress) {
    global $apiBaseUrl;
    // Use unified IP check endpoint for consistency with other files
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/outbound-payment/check-ip';
    
    try {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            error_log("API Error for IP check: Failed to initialize cURL");
            return false;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['ip_address' => $ipAddress]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for IP check: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for IP check: Status code " . $httpCode . ", Response: " . substr($response, 0, 200));
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for IP check: " . json_last_error_msg());
            return false;
        }
        
        // Check if IP has access (unified endpoint returns has_access)
        if (isset($data['status']) && $data['status'] === 'success' && 
            isset($data['data']['has_access']) && $data['data']['has_access'] === true) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("API Exception for IP check: " . $e->getMessage());
        return false;
    }
}

function getPaxByAutoIdViaAPI($autoId) {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/ticket-number-updator/pax/' . urlencode($autoId);
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for get pax by auto_id: " . $curlError);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for get pax by auto_id: Status code " . $httpCode . ", Response: " . $response);
            return null;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for get pax by auto_id: " . json_last_error_msg());
            return null;
        }
        
        // Handle response format
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        } elseif (is_array($data) && isset($data['auto_id'])) {
            return $data;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("API Exception for get pax by auto_id: " . $e->getMessage());
        return null;
    }
}

function updatePaxNameViaAPI($autoId, $fname, $lname, $updatedUser) {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/ticketing/update-pax';
    
    try {
        $requestData = [
            'pax_auto_id' => (int)$autoId,
            'fname' => $fname,
            'lname' => $lname,
            'updated_user' => $updatedUser
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for update pax name: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for update pax name: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for update pax name: " . json_last_error_msg());
            return false;
        }
        
        return isset($data['success']) ? $data['success'] : ($httpCode === 200 || $httpCode === 201);
    } catch (Exception $e) {
        error_log("API Exception for update pax name: " . $e->getMessage());
        return false;
    }
}

function logNameUpdateViaAPI($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn) {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/name-updates/log';
    
    try {
        $requestData = [
            'type_id' => $typeId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => $updatedOn
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for log name update: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for log name update: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("API Exception for log name update: " . $e->getMessage());
        return false;
    }
}

// Check IP address via API
// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
/*
$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
*/
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$is_ip_allowed = checkIpAddressViaAPI($ip_address);

if($is_ip_allowed)
{

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    
    if(current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ))
    {
        if(!isset($_GET['pg']))
	    {
	    ?>
		<center>
		</br></br></br>
		<form class="form-horizontal" action="?pg=check" method="post" name="uploadCSV" enctype="multipart/form-data">
			<div class="input-row">
				<label class="col-md-4 control-label">Choose CSV File</label>
				<a href="https://gauratravel.com.au/wp-content/uploads/2024/11/import_pax_name_update.csv" style="font-size:12px; ">Download Template</a></br></br>
				<input type="file" required name="file" id="file" accept=".csv" style="display:block;">
				<input type="submit" id="submit" style='height:30px; width:70px; font-size:12px; padding:7px; margin:0px;' name="import_pricing"></input>
				<br />
			</div>
			<div id="labelError"></div>
		</form>
		</center>
        <?php
	    }
	    
        if(isset($_GET['pg']) && $_GET['pg'] == 'check')
	    {
    		// IMPORT PRICING START
    		if (isset($_POST["import_pricing"])) 
    			{
    				$fileName = $_FILES["file"]["tmp_name"];
    				if ($_FILES["file"]["size"] > 0) 
    				{
    					$file = fopen($fileName, "r");
    					echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
    					$tablestirng="<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
    					<tr>
    						<td>#</td>
    						<td>Auto ID</td>
    						<td>FNAME</td>
    						<td>LNAME</td>
    						<td>Existing/New</td>
    						<td></td>
    					</tr>
    					";
    					$autonumber = 1;
    					
    					while (($column = fgetcsv($file, 10000, ",")) !== FALSE) 
    					{
    					    $non_matching_reasons = '';
    					    $is_matched_any_condition = 0;
    						if($column[0] == 'id' && $column[1] == 'fname')
    						{
    							// Do Nothing
    						}
    						else
    						{
    							$auto_id = $column[0];
    							$pnr = $column[1];
    							$ticketno = $column[2];

    							// ✅ FIX: Get passenger by auto_id via API endpoint instead of SQL query
    							// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    							/*
    							$sql = "SELECT * FROM wpk4_backend_travel_booking_pax where auto_id = '$auto_id'";
    							$result = $mysqli->query($sql);
    							$row = $result->fetch_assoc();
    							*/
    							$row = getPaxByAutoIdViaAPI($auto_id);
    							
    							$order_id_from_table = $row['order_id'] ?? null;
    							$auto_id_from_table = $row['auto_id'] ?? null;
    							
    							
    								$tablestirng.= "<tr>
    								<td>".$autonumber."</td>
    								<td>".$auto_id."</td>
    								<td>".$pnr."</td>
    								";
    									
    									if($auto_id == $auto_id_from_table)
    										{
    											$match_hidden = 'Existing';
    											$match= "Existing";
    											$checked="checked";
    										}
    									else 
    										{
    											$match_hidden = 'New';
    											$match = "<font style='color:red;'>New Record</font>";
    											$checked="disabled";
    										}
    									
    								$tablestirng.= "		
    									<td>								
    									<input type='hidden' name='".$auto_id."_matchmaker' value='".$match_hidden."'>
    									".$match."</td>";
    																	
    									$tablestirng.="<td><input type='checkbox' id='chk".$auto_id."' name='".$auto_id."_checkoption' value='".$auto_id."@#".$pnr."@#".$ticketno."@#".$match_hidden."' ".$checked." \/></td>
    									</tr>";
    
    							$autonumber++;
    						}
    					}
    					
    					$tablestirng.= "</table>";
    					echo $tablestirng;
    					?>
    					<br><br><input type="submit" name="submit_pricing" value="Update"/></form></center>
    					<?php
    				}
    			}
    			if (isset($_POST["submit_pricing"])) 
    			{
    				foreach ($_POST as $post_fieldname => $post_fieldvalue) 
    				{
    					$post_name_dividants = explode('_', $post_fieldname);
    					$postname_auto_id = $post_name_dividants[0];
						$postname_fieldname = $post_name_dividants[1];
    					$check_whether_its_ticked = $postname_auto_id.'_checkoption';
    					
    					if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
    					{
    						$post_value_get = $_POST[$post_fieldname];
    						$post_values = explode('@#', $post_value_get);
    							$auto_id_from_table_post = $post_values[0];	
								$pnr_post = $post_values[1];
								$ticketno_post = $post_values[2];
								$match_hidden_post = $post_values[3];
    						    
    						    // ✅ FIX: Update passenger name via API endpoint instead of SQL query
    						    // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    						    /*
    						    $sql_update_status = "UPDATE wpk4_backend_travel_booking_pax SET 
    												fname='$pnr_post',
    												lname='$ticketno_post'
    												WHERE auto_id='$auto_id_from_table_post'";
    							$result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
    							*/
    							$result_status = updatePaxNameViaAPI($auto_id_from_table_post, $pnr_post, $ticketno_post, $currnt_userlogn);
    							
    							if (!$result_status) {
    								error_log("Failed to update passenger name for auto_id: " . $auto_id_from_table_post);
    								continue; // Skip to next record if update fails
    							}
    						
    					
    						$values = array(
    						array($auto_id_from_table_post, "fname", $pnr_post, $currnt_userlogn, $current_time),
    						array($auto_id_from_table_post, "lname", $ticketno_post, $currnt_userlogn, $current_time)
    						);
    
    						// ✅ FIX: Insert history records via API endpoint instead of SQL query
    						// Loop through the array and insert each row via API
    						foreach ($values as $row) {
    							$type_id = $row[0];
    							$meta_key = $row[1];
    							$meta_value = $row[2];
    							$updated_by = $row[3];
    							$updated_on = $row[4];
    
    							// OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
    							/*
    							mysqli_query($mysqli,"insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on) values ('$type_id', '$meta_key', '$meta_value', '$updated_by', '$updated_on')") or die(mysqli_error($mysqli));
    							*/
    							logNameUpdateViaAPI($type_id, $meta_key, $meta_value, $updated_by, $updated_on);
    						}
    					}
    				}
    				echo '<script>alert("Updated successfully.");</script>';
    				echo '<script>window.location.href="?";</script>';
    			}
    		}
    }
}
else
{
echo "<center>This page is not accessible for you.</center>";
}
?>
</div>
<?php get_footer(); ?>