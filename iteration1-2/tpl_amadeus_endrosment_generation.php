<?php
/**
 * Template Name: Manage Amadeus Endorsement Generation
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
$defaultlink_gaura = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

global $current_user; 
wp_get_current_user();
$current_date_and_time = date("Y-m-d H:i:s");
include('wp-config-custom.php');

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
//if(mysqli_num_rows($result_ip_selection) > 0 )
if(1 == 1)
{
    $currnt_userlogn = $current_user->user_login;
    if($currnt_userlogn && current_user_can( 'administrator' )) 
    {             
        if(!isset($_GET['option']))
        {
            ?>
            <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
							<script>
								window.addEventListener("load", function (event) {
								var currentdate = new Date(); 					
									let drp = new DateRangePicker('tripdate_selector',
										{
											timePicker: false,
											alwaysShowCalendars: true,
											singleDatePicker: false,
											autoApply: false,
											autoUpdateInput: false,
											locale: {
												format: "YYYY-MM-DD",
											}
										},
										function (start, end) {
										    
										    var startdate = start.format().substring(0,10); // 2023-04-11T00:00:00+10:00 -> 2023-04-11
        									var enddate = end.format().substring(0,10);
        											
											document.getElementById("tripdate_selector").value = startdate + " - " + enddate;	
																	
										})
									});
									</script>
            <script type="text/javascript">
                function searchordejs() 
                {
                    var tripcode = document.getElementById("tripcode_selector").value;
                    var date_trip = document.getElementById("tripdate_selector").value;
                    var order_id_selector = document.getElementById("order_id_selector").value;
                    var end_id_selector = document.getElementById("end_id_selector").value;
                    var price_filter = document.getElementById("price_selector").value;
                    window.location = '?tripcode=' + tripcode + '&date=' + date_trip + '&pnr=' + order_id_selector+ '&end_id=' + end_id_selector+ '&price=' + price_filter ; // parse data for filtering
                }
            </script>
            </br></br>
            <table class="table" style="width:100%;margin:auto;border:1px solid #adadad;margin-top: 1%;">
                <tr>
                    <td>Trip Code
                        <input type='text' name='tripcode_selector' value='<?php if (isset($_GET['tripcode'])) { echo $_GET['tripcode']; } ?>' id='tripcode_selector'>
                    </td>
                    <td>Departure Date
                        <input type='text' name='tripdate_selector' value='<?php if (isset($_GET['date'])) { echo $_GET['date']; } ?>' id='tripdate_selector'>
                        <button style='padding:10px; margin:0;font-size:11px; ' onClick="onBlurGetPriceAndEndrosementID()"  id='search_by_date' >Get ID and Price</button>
                    </td>
                    <td>PNR</br>
                        <input type='text' name='order_id_selector' value='<?php if (isset($_GET['pnr'])) { echo $_GET['pnr']; } ?>' id='order_id_selector'>
                    </td>
                    <td>Endorsement ID</br>
                        <select name='end_id_selector' id='end_id_selector' style='width:100%; padding:10px;'>
                            <option value="">Select Endorsement ID</option>
                        </select>
                    </td>
                    <td>Price</br>
                        <select name='price_selector' id='price_selector' style='width:100%; padding:10px;'>
                            <option value="">Select Price</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan='5' style='text-align:center;'>
                        <button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' onclick="searchordejs()">Search</button>
                    </td>
                </tr>
            </table>
            
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
            
            function onBlurGetPriceAndEndrosementID() {
                console.log("ajax triggered");
            
                var selectedDate = $('#tripdate_selector').val(); // Get the selected date
                let dates = selectedDate.split(" - "); // Split the date range into start and end dates
            
                // Get the start and end dates
                let startDate = dates[0];
                let endDate = dates[1];
            
                if (selectedDate != '') {
                    // Make the GET request to fetch data from the database
                    $.ajax({
                        url: '/wp-content/themes/twentytwenty/templates/tpl_amadeus_endrosement_generation_backend.php', // PHP file to query the database
                        type: 'GET',
                        data: {
                            date: startDate, // Send the start date
                            end_date: endDate // Send the end date
                        },
                        success: function(response) {
                            console.log("Response: ", response);
                            // Handle the response here, populate dropdowns or perform any actions
                            if (response.length > 0) {
                                let endorsementIds = Object.values(response[0].endorsement_id).filter(id => id !== null); // Remove null values
                                let prices = Object.values(response[0].aud_fare); // Get prices array
            
                                // Populate the Endorsement ID dropdown
                                endorsementIds.forEach(function(endorsement_id) {
                                    $('#end_id_selector').append('<option value="' + endorsement_id + '">' + endorsement_id + '</option>');
                                });
            
                                // Populate the Price dropdown
                                prices.forEach(function(price) {
                                    $('#price_selector').append('<option value="' + price + '">' + price + '</option>');
                                });
                            } else {
                                console.log("No data found");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("Error details:");
                            console.log("Status: " + status); // Show the status of the request
                            console.log("Error: " + error); // Show the error message
                            console.log("Response Text: " + xhr.responseText); // Show the response text (for debugging)
                        }
                    });
                }
            }
            									
            		
            </script>
            <?php
            if (isset($_GET['tripcode']) &&$_GET['tripcode'] != '') {
                            $tripcode = $_GET['tripcode'];
                            $tripcode_sq = "trip_id LIKE '%$tripcode%' AND ";
                        } else {
                            $tripcode_sq = "trip_id!='TEST_DMP_ID' AND ";
                        }
            
                        if (isset($_GET['date']) &&$_GET['date'] != '') {
                            $depdate_main = $_GET['date'];
                            $depdate = substr($depdate_main, 0, 10);
                            $enddate = substr($depdate_main, 13, 10);
                        
                            $date_sq = "dep_date>='$depdate' && dep_date<='$enddate' AND ";
                        } else {
                            $date_sq = "trip_id!='TEST_DMP_ID' AND ";
                        }
                        
                        if (isset($_GET['end_id']) &&$_GET['end_id'] != '') {
                            $end_id = $_GET['end_id'];
                            $end_id_sq = "mh_endorsement = '$end_id' AND ";
                        } else {
                            $end_id_sq = "trip_id!='TEST_DMP_ID' AND ";
                        }
                        
                        if (isset($_GET['price']) &&$_GET['price'] != '') 
                        {
                            $price = $_GET['price'];
                            $price_sq = "aud_fare = '$price' AND ";
                        } else {
                            $price_sq = "trip_id!='TEST_DMP_ID' AND ";
                        }
                        
            
                        if (isset($_GET['pnr']) &&$_GET['pnr'] != '') {
                            $trippnr = $_GET['pnr'];
                            if(isset($_GET['exactmatch']) && isset($_GET['exactmatch']))
                            {
                                $pnr_sq = "pnr LIKE '$trippnr'  ";
                            }
                            else
                            {
                                $pnr_sq = "pnr LIKE '%$trippnr%'  ";
                            }
                            
                        } else {
                            $pnr_sq = "trip_id!='TEST_DMP_ID'  ";
                        }
            
                        if ( (isset($_GET['tripcode']) && $_GET['tripcode'] != '') || 
                            (isset($_GET['date']) && $_GET['date'] != '') || 
                            (isset($_GET['end_id']) && $_GET['end_id'] != '') || 
                            (isset($_GET['pnr']) && $_GET['pnr'] != '') 
                        ) 
                        {
                            $query = "SELECT * FROM wpk4_backend_stock_management_sheet where $tripcode_sq $date_sq $end_id_sq $price_sq $pnr_sq order by dep_date ASC";
                        } 
                        else 
                        {
                            $thismonthstart_dep = date("Y-m-d");
                            $thismonthend_dep = date("Y-m-d", strtotime("7 day", strtotime($thismonthstart_dep)));
                            $query = "SELECT * FROM wpk4_backend_stock_management_sheet where dep_date>='$thismonthstart_dep' && dep_date<='$thismonthend_dep' order by dep_date ASC limit 0";
                        }
                        //echo $query;
                        $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                        $row_counter = mysqli_num_rows($result);
                        echo 'Showing ' . $row_counter . ' records';
                        
                        $endrosement_id = '';
                        $base_fare = '';
                        $group_name = '';
            ?>
            
            <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                </br></br>
                Assign Group Name
                <input type='text' style='width:100%;' name='group_name' placeholder="Group Name" ><input type='submit' style='padding:10px; width:10%; margin:0;font-size:14px;' name='save_groupname' value='Save'>
            </form>
            <form action="#" name="statusupdate" method="post" enctype="multipart/form-data">
                <table class="table" style="width:100%; font-size:14px;">
                    <thead>
                        <tr>
                            <th width="8%">PNR</th>
                            <th width="6%">Departure Date</th>
                            <th width="15%">Trip Code</th>
                            <th width="13%">Group Name</th>
                            <th width='8%'>Enderosement ID</th>
                            <th width="7%">Base fare</th>
                            <th width="20%">Endorsement added by</th>
                            <th width="20%">Endorsement confirmed by</th>
                            <?php
                            /*if (current_user_can( 'administrator' )) {
                            ?>
                                <th width="6%">Generate base code</th>
                            <?php
                            }*/
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $end_id_array = [];
                        while ($row = mysqli_fetch_assoc($result)) 
                        {
                            $auto_id = $row['auto_id'];
                            $aud_fare_note = $row['aud_fare'];
                            $dep_date_pnr = date('Y-m-d', strtotime($row['dep_date']));
                            
                            if($row['mh_endorsement'] != '')
                            {
                                $endrosement_id = $row['mh_endorsement'];
                            }
                            if($aud_fare_note != '')
                            {
                                $base_fare = $aud_fare_note;
                            }
                            if($row['group_name'] != '')
                            {
                                $group_name = $row['group_name'];
                            }
                            $end_id_array[] = $row['mh_endorsement'];
                            ?>
                            <tr>
                                <td><?php echo $row['pnr']; ?></td>
                                <td><?php echo $dep_date_pnr; ?></td>
                                <td><?php echo $row['trip_id']; ?></td>
                                <td><?php echo $row['group_name']; ?></td>
                                <td><?php echo $row['mh_endorsement']; ?></td>
                                <td><?php echo $row['aud_fare']; ?></td>
                                <td><input type="checkbox" name="<?php echo $row['auto_id']; ?>_endrosement_added_by" <?php if ($row['endrosement_added_by'] != '') echo 'checked'; ?> value="<?php echo $currnt_userlogn; ?>"></br>
                                <?php if ($row['endrosement_added_by'] != '') echo $row['endrosement_added_by'] . ' on ' . $row['endrosement_added_on']; ?></td>
                                <td><input type="checkbox" name="<?php echo $row['auto_id']; ?>_endrosement_confirmed_by" <?php if ($row['endrosement_confirmed_by'] != '') echo 'checked'; ?> value="<?php echo $currnt_userlogn; ?>"></br>
                                <?php if ($row['endrosement_confirmed_by'] != '') echo $row['endrosement_confirmed_by'] . ' on ' . $row['endrosement_confirmed_on']; ?></td>
                                <?php
                                /*if (current_user_can( 'administrator' )) 
                                {
                                    ?>
                                    <td><a href="?option=base-code&end_id=<?php echo $row['mh_endorsement']; ?>&base_fare=<?php echo $row['aud_fare']; ?>">Generate</a></td>
                                    <?php
                                }*/
                                ?>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
                <a style="background-color: #04AA6D; border: none; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; font-size: 13px;" 
                    href="?option=base-code&end_id=<?php echo $endrosement_id; ?>&base_fare=<?php echo $base_fare; ?>">Generate base code</a> 
                <?php
                $end_id_array = array_unique($end_id_array);

                $comma_separated_ids = implode(',', $end_id_array);
                ?>
                <a style="background-color: #04AA6D; border: none; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; font-size: 13px;" 
                    href="?option=final-code&end_id=<?php echo $comma_separated_ids; ?>&group_name=<?php echo $group_name; ?>">Generate final code</a> 
                    
                <input type='submit' style='float:right;padding:10px; margin:0;font-size:14px;' name='saveall_stocks' value='Save All'>
            </form>
            <?php
            if (isset($_POST['save_groupname'])) 
            {
                $endrosement_date = date('Y-m-d H:i:s'); // Current date and time
                $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                while ($row = mysqli_fetch_assoc($result)) 
                {
                    $auto_id = $row['auto_id'];
                    $group_name = $_POST['group_name'];

                    // Update endorsement confirmed by
                    $update_query = "UPDATE wpk4_backend_stock_management_sheet 
                                        SET group_name = '$group_name' 
                                    WHERE auto_id = '$auto_id'";
                    mysqli_query($mysqli, $update_query) or die(mysqli_error($mysqli));
                    
                    
                    mysqli_query($mysqli,"insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on ) 
				    values ('$auto_id','group_name','$group_name' ,'$currnt_userlogn' ,'$endrosement_date')") or die(mysqli_error($mysqli));
			
    			}				        
                $current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                echo "<script>alert('Records updated successfully');</script>";
                echo '<script>window.location.href="'.$current_url.'";</script>';
            }
            
            if (isset($_POST['saveall_stocks'])) 
            {
                $endrosement_date = date('Y-m-d H:i:s'); // Current date and time
                
                foreach ($_POST as $key => $value) 
                {
                    if (strpos($key, '_mh_endorsement') !== false) 
                    {
                        $auto_id = str_replace('_mh_endorsement', '', $key);
                        $mh_endorsement = mysqli_real_escape_string($mysqli, $value);

                        $update_query = "UPDATE wpk4_backend_stock_management_sheet 
                                         SET mh_endorsement = '$mh_endorsement' 
                                         WHERE auto_id = '$auto_id'";
                        mysqli_query($mysqli, $update_query) or die(mysqli_error($mysqli));
                    }
                    
                    if (strpos($key, 'group_name') !== false) 
                    {
                        $auto_id = str_replace('_group_name', '', $key);
                        $group_name = mysqli_real_escape_string($mysqli, $value);

                        $update_query = "UPDATE wpk4_backend_stock_management_sheet 
                                         SET group_name = '$group_name' 
                                         WHERE auto_id = '$auto_id'";
                        mysqli_query($mysqli, $update_query) or die(mysqli_error($mysqli));
                    }
                    
                    if (strpos($key, '_endrosement_added_by') !== false) 
                    {
                        $auto_id = str_replace('_endrosement_added_by', '', $key);
                        $endrosement_added_by = mysqli_real_escape_string($mysqli, $value);

                        // Update endorsement added by
                        $update_query = "UPDATE wpk4_backend_stock_management_sheet 
                                         SET endrosement_added_by = '$endrosement_added_by', 
                                             endrosement_added_on = '$endrosement_date' 
                                         WHERE auto_id = '$auto_id' and endrosement_added_by is null";
                        mysqli_query($mysqli, $update_query) or die(mysqli_error($mysqli));
                    }
            
                    if (strpos($key, '_endrosement_confirmed_by') !== false) 
                    {
                        $auto_id = str_replace('_endrosement_confirmed_by', '', $key);
                        $endrosement_confirmed_by = mysqli_real_escape_string($mysqli, $value);

                        // Update endorsement confirmed by
                        $update_query = "UPDATE wpk4_backend_stock_management_sheet 
                                         SET endrosement_confirmed_by = '$endrosement_confirmed_by', 
                                             endrosement_confirmed_on = '$endrosement_date' 
                                         WHERE auto_id = '$auto_id' and endrosement_confirmed_by is null";
                        mysqli_query($mysqli, $update_query) or die(mysqli_error($mysqli));
                    }
                    
                    $updated_user = 'endorsement_update_'.$currnt_userlogn;
                    
                    mysqli_query($mysqli,"insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on ) 
				    values ('$auto_id','$key','$value' ,'$updated_user' ,'$endrosement_date')") or die(mysqli_error($mysqli));
			
    			}				        
                $current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                echo "<script>alert('Records updated successfully');</script>";
                echo '<script>window.location.href="'.$current_url.'";</script>';
            }
        }
        
        
        if (isset($_GET['option']) && $_GET['option'] == 'base-code' ) 
        {
            $end_id = isset($_GET['end_id']) ? $_GET['end_id'] : ''; 
            $base_fare = isset($_GET['base_fare']) ? $_GET['base_fare'] : 0;
            
            if($end_id != '' && $base_fare > 0)
            {
                // Convert base fare to required formats
                $base_plain = (int) $base_fare; // Remove decimals
                $base_decimal = number_format($base_fare, 2, '.', ''); // Two decimal places
                $base_10_plain = (int) ($base_fare * 0.1); // 10% without decimal
                $base_10_decimal = number_format($base_fare * 0.1, 2, '.', ''); // 10% with decimal
                
                {
                $generated_code = 'send "RTN,J"
                    mandatory ask "Enter passenger number(S) for contact details" assign to passengerNumber
                    choose "What type of contact details do you want to add?" {
                      when ("Phone number") {
                        mandatory ask number  "Type the phone number including the country code" assign to phoneNumber
                        send "SRCTCM-" + phoneNumber + "/P" + passengerNumber
                        }
                        
                      
                      when ("Passenger refused to provide contact details") {
                        send "SRCTCR-REFUSED/P" + passengerNumber
                      }
                    }
                    choose "Add more contact details for same or another traveller" {
                      when ("Yes") {
                        call "Gaura contacts"
                      }
                      when ("No") {
                        
                      }
                    }
                    send "FM0"
                    send "RMZ/CONF*NOFEE"
                    send "FENONEND***ID'.$end_id.'***"
                    send "RTA"
                    ask "Please enter Date of Travel" assign to nva
                        capture line : 3, column : 23, length : 3 assign to depcity
                        capture line : 4, column : 26, length : 3 assign to arrcity
                        send "DAC" + depcity
                        capture line : 4, column : 35, length : 2 assign to country
                    if (country != "IN"){
                    send "RTN"
                    choose "Select Passenger Type" until "FINISHED" {
                    when ("ADULT ONLY") {
                        send "RTN"
                        group {
                        ask "Select <b>ADULT</b> passengers you want to create TST for?
                        <i>For multiple passengers enter the format 1,3,6,7</i>" 
                        assign to adultpax
                      }
                        send "FXP/L-QBXOWAU/P" + adultpax
                        send "FXT1/P" + adultpax
                        send "TQT/P" + adultpax
                        capture line : 1, column : 6, length : 3 assign to tstnumber  
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_plain.'/" + roe
                        capture line : 2, column : 1, length : 6 assign to nuc
                        send "TTK/T" + tstnumber + "/FAUD'.$base_decimal.'/A30K/BQGVOWAU/VXX" +nva
                        send "TTK/T" + tstnumber + "/C" + depcity + "MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultpax
                        choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"
                           send "IR"
                           call "Gaura ITR"
                           }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                       send "RTN"
                    } 
                        when ("CHILD") {
                        send "RTN"
                        group {
                        ask "Select <b>CHILD</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to childpax
                        }
                       send "FXP/L-QBXOWAU/P" + childpax
                       send "FXT1/P" + childpax
                       send "TQT/P" + childpax
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_plain.'/" + roe
                        capture line : 2, column : 1, length : 6 assign to nuc
                        send "TTK/T" + tstnumber + "/FAUD'.$base_decimal.'/A30K/BQGVOWAUCH/VXX" +nva
                        send "TTK/T" + tstnumber + "/C" + depcity + "MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + childpax
                        choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    }       
                        when ("ADULT AND INFANT") {
                        send "RTN"
                        group {
                        ask "Select <b>ADULT and INFANT</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to adultinfantpax
                    }
                        send "FXP/L-QBXOWAU/INF/P" + adultinfantpax 
                        send "FXT1/P" + adultinfantpax
                        send "TQT/P" + adultinfantpax + "/INF"
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_10_plain.'/" + roe
                        capture line : 2, column : 1, length : 5 assign to nuc
                        send "TTK/T" + tstnumber + "/FAUD'.$base_10_decimal.'/A10K/BQGVOWAUIN/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + "MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultinfantpax     
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }    
                       send "FXP/L-QBXOWAU/P" + adultinfantpax + "/PAX"
                       send "FXT1/P" + adultinfantpax 
                       send "TQT/P" + adultinfantpax + "/PAX"
                       capture line : 1, column : 6, length : 3 assign to tstnumber
                       ask "Enter the ROE rate" assign to roe
                       send "DF'.$base_plain.'/" + roe
                       capture line : 2, column : 1, length : 6 assign to nuc
                       send "TTK/T" + tstnumber + "/FAUD'.$base_decimal.'/A30K/BQGVOWAU/VXX" +nva
                       send "TTK/T" + tstnumber + "/C" + depcity + "MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                       send "FPCASH/P" + adultinfantpax
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"   
                          }
                        }
                        }
                      when ("INFANT") {
                        send "RTN"
                        group {
                        ask "Select <b>INFANT</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to infantpax
                    }
                        send "FXP/L-QBXOWAU/INF/P" + infantpax
                        send "FXT1/P" + infantpax
                        send "TQT/P" + infantpax + "/INF"
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_10_plain.'/" + roe
                        capture line : 2, column : 1, length : 5 assign to nuc
                        send "TTK/T" + tstnumber + "/FAUD'.$base_10_decimal.'/A10K/BQGVOWAUIN/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + "MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + infantpax 
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    }}
                    send "TQT"
                        
                      }
                    else {
                    if (country == "IN") {
                          send "RTN"
                    choose "Select Passenger Type" until "FINISHED" {
                    when ("ADULT ONLY") {
                        send "RTN"
                        group {
                        ask "Select <b>ADULT</b> passengers you want to create TST for?
                        <i>For multiple passengers enter the format 1,3,6,7</i>" 
                        assign to adultpax
                      }
                       send "FXP/L-QBXOWIZ/P" + adultpax
                       send "FXT1/P" + adultpax
                        send "TQT/P" + adultpax
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the BSR rate" assign to bsr
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_plain.'/" + bsr
                        capture line : 2, column : 1, length : 6 assign to inr
                        send "DF" + inr + "/" + roe
                        capture line : 2, column : 1, length : 6 assign to nuc
                        send "TTK/T" + tstnumber + "/F" + inr + "/E'.$base_decimal.'/A30K/BQGVOWAU/X4-X18.60K3CB/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + " MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultpax
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"       
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    } 
                        when ("CHILD") {
                        send "RTN"
                        group {
                        ask "Select <b>CHILD</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to childpax
                        }
                       send "FXP/L-QBXOWIZ/P" + childpax
                       send "FXT1/P" + childpax
                        send "TQT/P" + childpax
                        capture line : 1, column : 6, length : 3 assign to tstnumber 
                        ask "Enter the BSR rate" assign to bsr
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_plain.'/" + bsr
                        capture line : 2, column : 1, length : 6 assign to inr
                        send "DF" + inr + "/" + roe
                        capture line : 2, column : 1, length : 6 assign to nuc
                        send "TTK/T" + tstnumber + "/F" + inr + "/E'.$base_decimal.'/A30K/BQGVOWAUCH/X4-X18.60K3CB/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + " MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + childpax
                        choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    }       
                        when ("ADULT AND INFANT") {
                        send "RTN"
                        group {
                        ask "Select <b>ADULT and INFANT</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to adultinfantpax
                    }
                       send "FXP/L-QBXOWIZ/P" + adultinfantpax + "/INF"
                       send "FXT1/P" + adultinfantpax 
                        send "TQT/P" + adultinfantpax + "/INF"
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the BSR rate" assign to bsr
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_10_plain.'/" + bsr     
                        capture line : 2, column : 1, length : 5 assign to inr
                        send "DF" + inr + "/" + roe
                        capture line : 2, column : 1, length : 5 assign to nuc
                        send "TTK/T" + tstnumber + "/F" + inr + "/E'.$base_10_decimal.'/A10K/BQGVOWAUIN/X1-X1.85K3CB/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + " MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultinfantpax
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"    
                       send "FXP/L-QBXOWIZ/P" + adultinfantpax + "/PAX"
                       send "FXT1/P" + adultinfantpax 
                        send "TQT/P" + adultinfantpax + "/PAX"
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the BSR rate" assign to bsr
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_plain.'/" + bsr
                        capture line : 2, column : 1, length : 6 assign to inr
                        send "DF" + inr + "/" + roe
                        capture line : 2, column : 1, length : 6 assign to nuc
                        send "TTK/T" + tstnumber + "/F" + inr + "/E'.$base_decimal.'/A30K/BQGVOWAU/X4-X18.60K3CB/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + " MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultinfantpax
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT"  
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    }
                    when ("INFANT") {
                        send "RTN"
                        group {
                        ask "Select <b>INFANT</b> passengers you want to create TST for?
                        <i><b> For Multiple passengers enter the format P1,3,6,7</b></i>" 
                        assign to adultinfantpax
                    }
                        send "FXP/L-QBXOWIZ/P" + adultinfantpax + "/INF"
                        send "FXT1/P" + adultinfantpax
                        send "TQT/P" + adultinfantpax + "/INF"
                        capture line : 1, column : 6, length : 3 assign to tstnumber
                        ask "Enter the BSR rate" assign to bsr
                        ask "Enter the ROE rate" assign to roe
                        send "DF'.$base_10_plain.'/" + bsr     
                        capture line : 2, column : 1, length : 5 assign to inr
                        send "DF" + inr + "/" + roe
                        capture line : 2, column : 1, length : 5 assign to nuc
                        send "TTK/T" + tstnumber + "/F" + inr + "/E'.$base_10_decimal.'/A10K/BQGVOWAUIN/X1-X1.85K3CB/VXX" +nva 
                        send "TTK/T" + tstnumber + "/C" + depcity + " MH X/KUL MH " + arrcity + nuc + "NUC" + nuc + "END ROE" + roe
                        send "FPCASH/P" + adultinfantpax
                          choose "Do you want to issue tickets?" {
                          when ("YES") {
                           send "RFGAURA;ER"
                           send "TTP/T" + tstnumber +"/RT" 
                           send "IR"
                           call "Gaura ITR"
                          }
                          when ("NO") {
                          send "RTN"  
                          }
                        }
                        send "RTN"
                    }}
                    send "TQT"
                    
                    }}';
                }    
                
                // Output the updated code
                echo '<textarea style="height:600px;">'.$generated_code.'</textarea>';
            }
        }
        
        if (isset($_GET['option']) && $_GET['option'] == 'final-code' ) 
        {
            $end_id = isset($_GET['end_id']) ? $_GET['end_id'] : ''; 
            $group_name = isset($_GET['group_name']) ? $_GET['group_name'] : ''; 
            
            $end_id_array = explode(',', $end_id); // Split the string into an array
            $quoted_ids = array_map(function($id) {
                return "'" . trim($id) . "'";
            }, $end_id_array);
            $end_id_sql = implode(',', $quoted_ids); // Join them back into a string
                        
            if($end_id != '' && $group_name != '')
            {
                
                $query = "SELECT * FROM wpk4_backend_stock_management_sheet WHERE mh_endorsement IN ($end_id_sql) order by group_name asc";
                //echo $query;
                
                $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                $row_counter = mysqli_num_rows($result);
                
                echo 'Showing ' . $row_counter . ' records';
                
                $pnr_loop = '';
                
                // Loop through the database results to generate if conditions dynamically
                while ($row = mysqli_fetch_assoc($result)) {
                    $pnr_loop .= 'if (reloc=="' . $row['pnr'] . '") {' . "\n";
                    $pnr_loop .= '  call "' . $row['group_name'] . '"' . "\n";
                    $pnr_loop .= '}' . "\n";
                }
                
                // Output the final structured code block
                $final_code = 'choose "If this booking MH or SQ" {
                    when ("SQ") {
                        call "DEC Group 15"
                    }
                    when ("MH") {
                        send "RT"
                        capture line : 2, column : 58, length : 6 assign to reloc
                        //ask "Enter PNR Reloc" assign to reloc 
                            '.$pnr_loop.'
                        }
                    }
                    ';
                
                echo '<pre>'.$final_code.'</pre>';

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