<?php
/**
 * Template Name: Import Stock Flight 2
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sri Harshan
 * @usage - to import data into stock table by auto_id. currently used to update the existing AUD fare column.
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
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
        // general form to get csv file
        if(!isset($_GET['pg']))
	    {
	    ?>
		<center>
		</br></br></br>
		<form class="form-horizontal" action="?pg=check" method="post" name="uploadCSV" enctype="multipart/form-data">
			<div class="input-row">
				<label class="col-md-4 control-label">Choose CSV File</label>
				<a href="https://gauratravel.com.au/wp-content/uploads/2023/12/template-import-stock-flight.csv" style="font-size:12px; ">Download Template</a></br></br>
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
    				// check is there any record in the csv
    				if ($_FILES["file"]["size"] > 0) 
    				{
    					$file = fopen($fileName, "r");
    					echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
    					$tablestirng="<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
    					<tr>
    						<td>Auto ID</td>
    						<td>Flight 2</td>
    						
    					</tr>
    					";
    					$autonumber = 1;
    					// loop through the file
    					while (($column = fgetcsv($file, 10000, ",")) !== FALSE) 
    					{
    					    $non_matching_reasons = '';
    					    $is_matched_any_condition = 0;
    						if($column[0] == 'auto_id' && $column[1] == 'flight_2')
    						{
    							// Do Nothing
    						}
    						else
    						{
    							$auto_id = $column[0];
    							$flight_2 = $column[1];
                                // check whether the data existing in the table
    							$sql = "SELECT * FROM wpk4_backend_stock_management_sheet where auto_id = '$auto_id'";
    							$result = $mysqli->query($sql);
    							$row = $result->fetch_assoc();
    							
    							$auto_id_from_table = $row['auto_id'];

    							
    								$tablestirng.= "<tr>
    								<td>".$auto_id."</td>
    								<td>".$flight_2."</td>
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
    									// assign values into field to pass								
    									$tablestirng.="<td><input type='checkbox' id='chk".$auto_id."' name='".$auto_id."_checkoption' value='".$auto_id."@#".$flight_2."@#".$match_hidden."' ".$checked." \/></td>
    									</tr>";
    
    							$autonumber++;
    						}
    					}
    					
    					$tablestirng.= "</table>";
    					echo $tablestirng;
    					?>
    					<br><br><input type="submit" name="submit_flight" value="Update"/></form></center>
    					<?php
    				}
    			}
    			if (isset($_POST["submit_flight"])) 
    			{
    			    // loop through the submitted values
    				foreach ($_POST as $post_fieldname => $post_fieldvalue) 
    				{
    					$post_name_dividants = explode('_', $post_fieldname); // divide the post name to identify the id and column name
    					$postname_auto_id = $post_name_dividants[0];
						$postname_fieldname = $post_name_dividants[1];
    					$check_whether_its_ticked = $postname_auto_id.'_checkoption';
    					
    					if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
    					{
    						$post_value_get = $_POST[$post_fieldname];
    						$post_values = explode('@#', $post_value_get);
    							$auto_id_from_table_post = $post_values[0];	
								$flight_2 = $post_values[1];
								
    						    // update the existing AUD fare
    						    $sql_update_status = "UPDATE wpk4_backend_stock_management_sheet SET 
    												flight2 = '$flight_2'
    												WHERE auto_id='$auto_id_from_table_post'";
    							$result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
    						
    					
    						$values = array(
    						    array($auto_id_from_table_post, "stock flight2", $flight_2, $currnt_userlogn, $current_time),
    						);
                            // add history
    						foreach ($values as $row) {
    							$type_id = $row[0];
    							$meta_key = $row[1];
    							$meta_value = $row[2];
    							$updated_by = $row[3];
    							$updated_on = $row[4];
    
    							mysqli_query($mysqli,"insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on) values ('$type_id', '$meta_key', '$meta_value', '$updated_by', '$updated_on')") or die(mysqli_error($mysqli));	
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