<?php
/**
 * Template Name: Client Base Manager
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:50px 0px; 100px 0px'>

<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
include("wp-config-custom.php");
$current_time = date('Y-m-d H:i:s');

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
if($row_ip_selection['ip_address'] == $ip_address)
{

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    
    if(current_user_can( 'administrator' ))
    {
        if(!isset($_GET['pg']))
        {
            // Function to fetch updated data from the database
$start_number =0;
$end_number = 5;
function fetchUpdatedData($mysqli) {
    
    $results = '';
    $query_date = "SELECT updated_on FROM wpk4_backend_travel_client_balance WHERE date(updated_on) <= CURDATE() ORDER BY updated_on DESC LIMIT 1";
    $results_date = mysqli_query($mysqli, $query_date);
    if(mysqli_num_rows($results_date) > 0)
    {
        $row_date = mysqli_fetch_assoc($results_date);
        $fixed_date = date('Y-m-d', strtotime($row_date['updated_on']));
    
        $query = "SELECT * FROM wpk4_backend_travel_client_balance WHERE invoice_total != 0 AND status = 'updated' AND date(updated_on) >= '$fixed_date'";
    
        $results = mysqli_query($mysqli, $query);
    }
    return $results;
}
$results = fetchUpdatedData($mysqli);

// search bar & filter code

if(isset($_GET['search']) && $_GET['search'] != '' || isset($_GET['options']) && $_GET['options'] != ''){
    $search = mysqli_real_escape_string($mysqli, $_GET['search']);
    $options = mysqli_real_escape_string($mysqli, $_GET['options']);
    
    $query = "SELECT * FROM wpk4_backend_travel_client_balance WHERE type_of_remark LIKE '%$options%' AND client_id LIKE '%$search%'";
    $results = mysqli_query($mysqli, $query);
}else{
    $results = fetchUpdatedData($mysqli);
}



if(!$mysqli){
	die("Connection error");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle form submission
    $recent_order_ids = $_POST['recent_order_id'];
    $type_of_remarks = $_POST['type_of_remark'];
    $remarks = $_POST['remark'];

    // Loop through each row and update the database
    for ($i = 0; $i < count($recent_order_ids); $i++) {
        $recent_order_id = mysqli_real_escape_string($mysqli, $recent_order_ids[$i]);
        $type_of_remark = mysqli_real_escape_string($mysqli, $type_of_remarks[$i]);
        $remark = mysqli_real_escape_string($mysqli, $remarks[$i]);

        // Fetch booking_id for the current row
        $row = mysqli_fetch_assoc($results);
        $client_id = mysqli_real_escape_string($mysqli, $row['client_id']);

        // Update database
        $updateQuery = "UPDATE wpk4_backend_travel_client_balance SET recent_order_id = '$recent_order_id', type_of_remark = '$type_of_remark', remark = '$remark' WHERE client_id = '$client_id'";
        mysqli_query($mysqli, $updateQuery);
    }
    //  echo '<script>window.location.reload(true);</script>';
    // Fetch updated data after submission
    $results = fetchUpdatedData($mysqli);
}

// Function to output CSV
function outputCSV($data) {
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
}

function fetchFilteredData($mysqli) {
    $search = mysqli_real_escape_string($mysqli, $_GET['search'] ?? '');
    $options = mysqli_real_escape_string($mysqli, $_GET['options'] ?? '');

    $query = "SELECT * FROM wpk4_backend_travel_client_balance WHERE 1=1";

    if (!empty($search)) {
        $query .= " AND client_id LIKE '%$search%'";
    }

    if (!empty($options)) {
        $query .= " AND type_of_remark = '$options'";
    }

    $results = mysqli_query($mysqli, $query);
    return $results;
}

// Export to CSV
if (isset($_POST['export'])) {
    // Fetch filtered data
    $filteredResults = fetchFilteredData($mysqli);

    if (!$mysqli || !$filteredResults) {
        die("Connection error or no data found");
    }

    // Clear output buffer
    ob_end_clean();

    // Output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clients_details.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Write CSV header
    outputCSV(array(array('client_name', 'client_id', 'phone', 'invoice_total', 'recent_order_id', 'type_of_remark', 'remark', 'status')));

    // Fetch data from filtered results and write to CSV
    while ($row = mysqli_fetch_assoc($filteredResults)) {
        outputCSV(array($row));
    }

    // Close database connection
    mysqli_close($mysqli);
    exit();
}


?>
<h1 class="text-center" style=" font-size: xxx-large;">CLIENTS <span style="color:#ffba10;">BASE</span> DETAILS</h1>
<div style="display: flex; justify-content: center; gap:25px; padding-bottom:25px; font-size: inherit; font-weight: 600;">
<?php 
// Query distinct types of remarks
$query = "SELECT DISTINCT type_of_remark FROM wpk4_backend_travel_client_balance";
$distinct = mysqli_query($mysqli, $query);
?>

<?php 
$iteration = 0; // Initialize iteration counter
while($row = mysqli_fetch_assoc($distinct)): 
    // Toggle color based on iteration
     // Get the count of each type of remark
    $query_date = "SELECT updated_on FROM wpk4_backend_travel_client_balance WHERE date(updated_on) <= CURDATE() ORDER BY updated_on DESC LIMIT 1";
    $results_date = mysqli_query($mysqli, $query_date);
    $row_date = mysqli_fetch_assoc($results_date);
    $fixed_date = date('Y-m-d', strtotime($row_date['updated_on']));
    
        $type_of_remark = $row['type_of_remark'];
        $count_query = "SELECT COUNT(*) AS count FROM wpk4_backend_travel_client_balance WHERE type_of_remark = '$type_of_remark' AND status = 'updated' AND date(updated_on) >= '$fixed_date'";
        $count_result = mysqli_query($mysqli, $count_query);
        $count_row = mysqli_fetch_assoc($count_result);
        $count = $count_row['count'];
    $color = ($iteration % 2 == 0) ? "black" : "#ffba10";
    ?>
    <p style="color: <?php echo $color; ?>"><?php echo $row['type_of_remark'] . " : " .$count; ?></p>
    <?php 
    $iteration++; // Increment iteration counter
endwhile; 
?>
</div>
<form style="margin-bottom:50px;" action="" method="get">
    <table class="table" style="width:50%; margin:auto; border:1px solid #adadad;">
		<tr>
			<td style="width: 75%;">
			    <label for="search" style="color: black; font-weight: 600;">Client id :</label>
			    <input id="search" type="text" value="<?=isset($_GET['search'])==true ? $_GET['search'] : ''?>" name="search" placeholder="Enter client id" value="">
			</td>
			<td style="width: 25%;">
				<label for="select" style="color: black; font-weight: 600;">Type of remark :</label>
                <select id="select" name="options" style="width:246px; height: 48px;">
                    <option value="none" selected disabled>Select Remark</option>
                    <option value="Important">Important</option>
                    <option value="Pending">Pending</option>
                    <option value="Refund">Refund</option>
                    <option value="Normal">Normal</option>
                    <!--<?php
                    // Reset the pointer of the $distinct result set to the beginning
                    mysqli_data_seek($distinct, 0);
                
                    // Iterate over the result set to populate the dropdown
                    while($row = mysqli_fetch_assoc($distinct)): ?>
                        <option value="<?php echo $row['type_of_remark']; ?>"
                            <?= isset($_GET['options']) && $_GET['options'] == $row['type_of_remark'] ? 'selected' : ''; ?>>
                            <?php echo $row['type_of_remark']; ?>
                        </option>
                    <?php endwhile; ?>-->
                </select>
			</td>
		</tr>
		<tr>
			<td colspan='9' style='text-align:center;'>
    			<button style='padding: 8px 20px; font-size: 15px;' type="submit" >Search</button>
    		</td>
		</tr>
	</table>
</form>
<form action="" method="post">
    <div style="display: flex;justify-content: end">
        <button style="margin-bottom:25px" type="submit" name="export" onclick="outputCSV()" id="btn">Download CSV</button>
    </div>
<table class="table text-center" style="width:100%; margin:auto; border:1px solid #adadad;">
	<tr>
	    <th>Sr/no</th>
		<th>Client name</th>
		<th>Client id</th>
		<th>Phone</th>
		<th>Invoice total</th>
		<th>Recent order id</th>
		<th>Type of remark</th>
		<th>Remark</th>
		<!--<th>Status</th>-->
		<!--<th>Updated on</th>-->
		<!--<th>Updated by</th>-->
	</tr>
	<tr>
    <?php if (mysqli_num_rows($results) == 0): ?>
        <tr id="hidden-col">
            <td colspan='9'>Data is not found</td>
        </tr>
    <?php else: ?>
        <?php $val = 1; foreach($results as $row): ?>
            <tr>
                <td><?php echo $val ?></td>
                <td><?php echo $row['client_name']?></td>
                <td><?php echo $row['client_id']?></td>
                <td><?php echo $row['phone']?></td>
                <td><?php echo $row['invoice_total']?></td>
                <td><input  type="text" name="recent_order_id[]" value="<?php echo $row['recent_order_id'] ?>"></td>
                <td><input  type="text" name="type_of_remark[]" value="<?php echo $row['type_of_remark'] ?>"></td>
                <td><input  type="text" name="remark[]" value="<?php echo $row['remark'] ?>"></td>
                <!--<td><?php echo $row['status']?></td>-->
                <!--<td><?php echo $row['updated_on']?></td>-->
                <!--<td><?php echo $row['updated_by']?></td>-->
            </tr>
            <?php $val++; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</tr>
</table>
<div style="display:flex; justify-content:space-between;">
    <button style="margin-top:25px" type="submit" id="btn">Save Details</button>
    <!--<div style="display:flex; gap:25px; align-items:center;">
        <button style="height: 30px; width:30px; text-align:center; padding:0;">1</button>
        <button style="height: 30px; width:30px; text-align:center; padding:0;">2</button>
        <button style="height: 30px; width:30px; text-align:center; padding:0;">3</button>
    </div>-->
</div>
</form>
<?php
}
        if(isset($_GET['pg']) && $_GET['pg'] == 'import-client-base')
	    {
	    ?>
		<center>
		</br></br></br>
		<form class="form-horizontal" action="?pg=check" method="post" name="uploadCSV" enctype="multipart/form-data">
			<div class="input-row">
				<label class="col-md-4 control-label">Choose CSV File</label>
				<a href="https://beta.yourbestwayhome.com.au/wp-content/uploads/2024/04/client-base-template.csv" style="font-size:12px; ">Download Template</a></br></br>
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
    						<td>Client ID</td>
    						<td>Client Name</td>
    						<td>Phone</td>
    						<td>Invoice Total</td>
    						<td>Existing/New</td>
    						<td></td>
    					</tr>
    					";
    					$autonumber = 1;
    					
    					while (($column = fgetcsv($file, 10000, ",")) !== FALSE) 
    					{
    					    $non_matching_reasons = '';
    					    $is_matched_any_condition = 0;
    						if($column[0] == 'Client Name' && $column[1] == 'Client Id')
    						{
    							// Do Nothing
    						}
    						else
    						{
    							$clientname = $column[0];
    							$clientid = $column[1];
    							$phone = $column[2];
    							$invoicetotal = $column[3];
    							
    							$client_id_from_table = 'EMPTY';
    							$sql = "SELECT * FROM wpk4_backend_travel_client_balance where client_id = '$clientid'";
    							$result = $mysqli->query($sql);
    							$row = $result->fetch_assoc();
    							if ($result->num_rows > 0) 
                                {
    							    $client_id_from_table = $row['client_id'];
                                }
    								$tablestirng.= "<tr>
    								<td>".$autonumber."</td>
    								<td>".$clientid."</td>
    								<td>".$clientname."</td>
    								<td>".$phone."</td>
    								<td>".$invoicetotal."</td>
    								";
    									
    									if($clientid == $client_id_from_table)
    										{
    											$match_hidden = 'Existing';
    											$match= "Existing";
    											$checked="checked";
    										}
    									else 
    										{
    											$match_hidden = 'New';
    											$match = "<font style='color:green;'>New Record</font>";
    											$checked="checked";
    										}
    									
    								$tablestirng.= "		
    									<td>								
    									<input type='hidden' name='".$clientid."_matchmaker' value='".$match_hidden."'>
    									".$match."</td>";
    																	
    									$tablestirng.="<td><input type='checkbox' id='chk".$clientid."' name='".$clientid."_checkoption' value='".$clientid."@#".$clientname."@#".$phone."@#".$invoicetotal."@#".$match_hidden."' ".$checked." \/></td>
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
    			    $client_id_array = '';
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
    							$clientid_post = $post_values[0];	
								$clientname_post = $post_values[1];
								$phone_post = $post_values[2];
								$invoicetotal_post = $post_values[3];
								$match_hidden_post = $post_values[4];
    						    $client_id_array .= "'".$clientid_post."', ";
    						    
    						    
    						    $sql = "SELECT * FROM wpk4_backend_travel_client_balance where client_id = '$clientid_post'";
    							$result = $mysqli->query($sql);
    							$row = $result->fetch_assoc();
    							// client_name	client_id	phone	invoice_total	recent_order_id	type_of_remark	remark	status	

    							$client_id_from_table = $row['client_id'];
    							if($clientid_post == $client_id_from_table)
    							{
        						    $sql_update_status = "UPDATE wpk4_backend_travel_client_balance SET 
        												invoice_total='$invoicetotal_post',
        												updated_on='$current_time',
        												updated_by='$currnt_userlogn',
        												status='updated'
        												WHERE client_id='$clientid_post'";
        							$result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
    							}
    							else
    							{
    							    mysqli_query($mysqli,"insert into wpk4_backend_travel_client_balance (client_name, client_id, phone, invoice_total, status, updated_on, updated_by ) 
    							    values ('$clientname_post', '$clientid_post', '$phone_post', '$invoicetotal_post', 'updated', '$current_time', '$currnt_userlogn' )") or die(mysqli_error($mysqli));	
    							}
    					    /*
    						$values = array(
    						array($auto_id_from_table_post, "pnr", $pnr_post, $currnt_userlogn, $current_time),
    						array($auto_id_from_table_post, "ticket_number", $ticketno_post, $currnt_userlogn, $current_time),
    						array($auto_id_from_table_post, "pax_status", $paxstatus_post, $currnt_userlogn, $current_time)
    						);
    
    						// Loop through the array and insert each row into the database
    						foreach ($values as $row) {
    							$type_id = $row[0];
    							$meta_key = $row[1];
    							$meta_value = $row[2];
    							$updated_by = $row[3];
    							$updated_on = $row[4];
    
    							mysqli_query($mysqli,"insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on) values ('$type_id', '$meta_key', '$meta_value', '$updated_by', '$updated_on')") or die(mysqli_error($mysqli));	
    						}
    					    */
    					}
    				}
    				/*
    				$client_id_array = substr($client_id_array, 0, -2);
    				$sql_update_status_2 = "UPDATE wpk4_backend_travel_client_balance SET 
        												updated_on='$current_time',
        												updated_by='$currnt_userlogn',
        												status='completed'
        												WHERE client_id NOT IN ($client_id_array)";
        			$result_status2 = mysqli_query($mysqli,$sql_update_status_2) or die(mysqli_error($mysqli));
        			*/
        			//echo $sql_update_status_2;				
    				echo '<script>alert("Updated successfully.");</script>';
    				echo '<script>window.location.href="?pg=import-client-base";</script>';
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