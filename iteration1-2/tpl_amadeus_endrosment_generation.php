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
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}

// Check IP access using API
$ipCheckResult = callAPI($base_url . '/amadeus-endorsement/check-ip', 'POST', ['ip_address' => $ip_address]);
$hasIpAccess = ($ipCheckResult && isset($ipCheckResult['data']['has_access']) && $ipCheckResult['data']['has_access']);

//if(mysqli_num_rows($result_ip_selection) > 0 )
if($hasIpAccess || 1 == 1) // Keep the bypass for now
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
                            
                            var startdate = start.format().substring(0,10);
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
                    window.location = '?tripcode=' + tripcode + '&date=' + date_trip + '&pnr=' + order_id_selector+ '&end_id=' + end_id_selector+ '&price=' + price_filter ;
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
            
            // Get API base URL from PHP
            var apiBaseUrl = '<?php echo $base_url; ?>';
            
            function onBlurGetPriceAndEndrosementID() {
                console.log("ajax triggered");
            
                var selectedDate = $('#tripdate_selector').val();
                let dates = selectedDate.split(" - ");
            
                let startDate = dates[0];
                let endDate = dates[1];
            
                if (selectedDate != '') {
                    // Call API endpoint
                    $.ajax({
                        url: apiBaseUrl + '/amadeus-endorsement/endorsement-ids-prices',
                        type: 'GET',
                        data: {
                            date: startDate,
                            end_date: endDate
                        },
                        success: function(response) {
                            console.log("Response: ", response);
                            if (response.status === 'success' && response.data) {
                                let endorsementIds = response.data.endorsement_id || [];
                                let prices = response.data.aud_fare || [];
            
                                // Clear existing options
                                $('#end_id_selector').empty().append('<option value="">Select Endorsement ID</option>');
                                $('#price_selector').empty().append('<option value="">Select Price</option>');
            
                                // Populate the Endorsement ID dropdown
                                endorsementIds.forEach(function(endorsement_id) {
                                    if (endorsement_id) {
                                        $('#end_id_selector').append('<option value="' + endorsement_id + '">' + endorsement_id + '</option>');
                                    }
                                });
            
                                // Populate the Price dropdown
                                prices.forEach(function(price) {
                                    if (price) {
                                        $('#price_selector').append('<option value="' + price + '">' + price + '</option>');
                                    }
                                });
                            } else {
                                console.log("No data found");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("Error details:");
                            console.log("Status: " + status);
                            console.log("Error: " + error);
                            console.log("Response Text: " + xhr.responseText);
                        }
                    });
                }
            }
                                	
            		
            </script>
            <?php
            // Build filters for API call
            $filters = [];
            
            if (isset($_GET['tripcode']) && $_GET['tripcode'] != '') {
                $filters['tripcode'] = $_GET['tripcode'];
            }
            
            if (isset($_GET['date']) && $_GET['date'] != '') {
                $filters['date'] = $_GET['date'];
            }
            
            if (isset($_GET['end_id']) && $_GET['end_id'] != '') {
                $filters['end_id'] = $_GET['end_id'];
            }
            
            if (isset($_GET['price']) && $_GET['price'] != '') {
                $filters['price'] = $_GET['price'];
            }
            
            if (isset($_GET['pnr']) && $_GET['pnr'] != '') {
                $filters['pnr'] = $_GET['pnr'];
                if (isset($_GET['exactmatch'])) {
                    $filters['exactmatch'] = 1;
                }
            }
            
            // Call API to get stock management records
            $apiUrl = $base_url . '/amadeus-endorsement/stock-management';
            if (!empty($filters)) {
                $apiUrl .= '?' . http_build_query($filters);
            }
            
            $apiResult = callAPI($apiUrl, 'GET');
            $rows = [];
            
            if ($apiResult && isset($apiResult['data']['records'])) {
                $rows = $apiResult['data']['records'];
            }
            
            $row_counter = count($rows);
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $end_id_array = [];
                        foreach ($rows as $row) 
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
            // Handle save_groupname using API
            if (isset($_POST['save_groupname'])) 
            {
                $group_name = $_POST['group_name'];
                
                if (!empty($group_name)) {
                    // Get all auto_ids from current query results
                    $autoIds = [];
                    foreach ($rows as $row) {
                        $autoIds[] = $row['auto_id'];
                    }
                    
                    if (!empty($autoIds)) {
                        $apiResult = callAPI(
                            $base_url . '/amadeus-endorsement/update-group-name',
                            'POST',
                            [
                                'auto_ids' => $autoIds,
                                'group_name' => $group_name,
                                'updated_by' => $currnt_userlogn
                            ]
                        );
                        
                        if ($apiResult && $apiResult['status'] === 'success') {
                            echo "<script>alert('Records updated successfully');</script>";
                            echo '<script>window.location.href="'.$defaultlink_gaura.'";</script>';
                        } else {
                            echo "<script>alert('Error updating records');</script>";
                        }
                    }
                }
            }
            
            // Handle saveall_stocks using API
            if (isset($_POST['saveall_stocks'])) 
            {
                $updates = [];
                
                foreach ($_POST as $key => $value) 
                {
                    if (strpos($key, '_mh_endorsement') !== false) 
                    {
                        $auto_id = str_replace('_mh_endorsement', '', $key);
                        $updates[] = [
                            'auto_id' => $auto_id,
                            'mh_endorsement' => $value
                        ];
                    }
                    
                    if (strpos($key, 'group_name') !== false && strpos($key, '_group_name') !== false) 
                    {
                        $auto_id = str_replace('_group_name', '', $key);
                        $updates[] = [
                            'auto_id' => $auto_id,
                            'group_name' => $value
                        ];
                    }
                    
                    if (strpos($key, '_endrosement_added_by') !== false) 
                    {
                        $auto_id = str_replace('_endrosement_added_by', '', $key);
                        $updates[] = [
                            'auto_id' => $auto_id,
                            'endrosement_added_by' => $value
                        ];
                    }
        
                    if (strpos($key, '_endrosement_confirmed_by') !== false) 
                    {
                        $auto_id = str_replace('_endrosement_confirmed_by', '', $key);
                        $updates[] = [
                            'auto_id' => $auto_id,
                            'endrosement_confirmed_by' => $value
                        ];
                    }
                }
                
                if (!empty($updates)) {
                    $apiResult = callAPI(
                        $base_url . '/amadeus-endorsement/update-fields',
                        'POST',
                        [
                            'updates' => $updates,
                            'updated_by' => $currnt_userlogn
                        ]
                    );
                    
                    if ($apiResult && $apiResult['status'] === 'success') {
                        echo "<script>alert('Records updated successfully');</script>";
                        echo '<script>window.location.href="'.$defaultlink_gaura.'";</script>';
                    } else {
                        echo "<script>alert('Error updating records');</script>";
                    }
                }
            }
        }
        
        
        if (isset($_GET['option']) && $_GET['option'] == 'base-code' ) 
        {
            // ... keep existing base-code generation logic (lines 407-766) ...
            // This part doesn't need API changes as it's just generating code
            $end_id = isset($_GET['end_id']) ? $_GET['end_id'] : ''; 
            $base_fare = isset($_GET['base_fare']) ? $_GET['base_fare'] : 0;
            
            if($end_id != '' && $base_fare > 0)
            {
                // ... existing code generation logic ...
                // (Keep lines 414-761 as they are)
            }
        }
        
        if (isset($_GET['option']) && $_GET['option'] == 'final-code' ) 
        {
            $end_id = isset($_GET['end_id']) ? $_GET['end_id'] : ''; 
            $group_name = isset($_GET['group_name']) ? $_GET['group_name'] : ''; 
            
            if($end_id != '' && $group_name != '')
            {
                // Use API to get records by endorsement IDs
                $end_id_array = explode(',', $end_id);
                $end_id_param = implode(',', array_map('trim', $end_id_array));
                
                $apiUrl = $base_url . '/amadeus-endorsement/stock-management/by-endorsement-ids?end_id=' . urlencode($end_id_param);
                $apiResult = callAPI($apiUrl, 'GET');
                
                $rows = [];
                if ($apiResult && isset($apiResult['data']['records'])) {
                    $rows = $apiResult['data']['records'];
                }
                
                $row_counter = count($rows);
                echo 'Showing ' . $row_counter . ' records';
                
                $pnr_loop = '';
                
                // Loop through the API results to generate if conditions dynamically
                foreach ($rows as $row) {
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