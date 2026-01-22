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

// Get API base URL
 $api_base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';

// Function to make API calls
function make_api_call($url, $method = 'GET', $data = null) {
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
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
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
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("API call returned HTTP $httpCode: " . substr($response, 0, 500));
    return ['error' => 'HTTP ' . $httpCode, 'response' => $response];
}

// Fallback function to get updated clients from database
function fetchUpdatedDataFallback($mysqli) {
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

// Function to fetch updated data from the API
function fetchUpdatedData($api_base_url, $mysqli) {
    $api_url = $api_base_url . '/client-base-manager/clients';
    $api_result = make_api_call($api_url, 'GET');
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        // Return API data
        return [
            'data' => $api_result['data'],
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        $results = fetchUpdatedDataFallback($mysqli);
        return [
            'data' => $results,
            'source' => 'Database (Fallback)'
        ];
    }
}

// Fallback function to get distinct remark types from database
function getDistinctRemarkTypesFallback($mysqli) {
    $query = "SELECT DISTINCT type_of_remark FROM wpk4_backend_travel_client_balance WHERE type_of_remark IS NOT NULL AND type_of_remark != ''";
    $distinct = mysqli_query($mysqli, $query);
    return $distinct;
}

// Function to get distinct remark types from API
function getDistinctRemarkTypes($api_base_url, $mysqli) {
    $api_url = $api_base_url . '/client-base-manager/remark-types';
    $api_result = make_api_call($api_url, 'GET');
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        // Return API data
        return [
            'data' => $api_result['data'],
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        $distinct = getDistinctRemarkTypesFallback($mysqli);
        return [
            'data' => $distinct,
            'source' => 'Database (Fallback)'
        ];
    }
}

// Fallback function to get remark type counts from database
function getRemarkTypeCountsFallback($mysqli) {
    $query_date = "SELECT updated_on FROM wpk4_backend_travel_client_balance WHERE date(updated_on) <= CURDATE() ORDER BY updated_on DESC LIMIT 1";
    $results_date = mysqli_query($mysqli, $query_date);
    $row_date = mysqli_fetch_assoc($results_date);
    $fixed_date = date('Y-m-d', strtotime($row_date['updated_on']));
    
    $counts = [];
    $query = "SELECT type_of_remark, COUNT(*) AS count FROM wpk4_backend_travel_client_balance WHERE type_of_remark IS NOT NULL AND type_of_remark != '' AND status = 'updated' AND date(updated_on) >= '$fixed_date' GROUP BY type_of_remark";
    $result = mysqli_query($mysqli, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[$row['type_of_remark']] = $row['count'];
    }
    
    return $counts;
}

// Function to get remark type counts from API
function getRemarkTypeCounts($api_base_url, $mysqli) {
    $api_url = $api_base_url . '/client-base-manager/remark-type-counts';
    $api_result = make_api_call($api_url, 'GET');
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        // Return API data
        return [
            'data' => $api_result['data'],
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        $counts = getRemarkTypeCountsFallback($mysqli);
        return [
            'data' => $counts,
            'source' => 'Database (Fallback)'
        ];
    }
}

// Fallback function to update client in database
function updateClientFallback($mysqli, $client_id, $recent_order_id, $type_of_remark, $remark) {
    $updateQuery = "UPDATE wpk4_backend_travel_client_balance SET recent_order_id = '$recent_order_id', type_of_remark = '$type_of_remark', remark = '$remark' WHERE client_id = '$client_id'";
    return mysqli_query($mysqli, $updateQuery);
}

// Function to update client via API
function updateClient($api_base_url, $mysqli, $client_id, $recent_order_id, $type_of_remark, $remark) {
    $api_url = $api_base_url . '/client-base-manager/clients/' . urlencode($client_id);
    $data = [
        'recent_order_id' => $recent_order_id,
        'type_of_remark' => $type_of_remark,
        'remark' => $remark
    ];
    
    $api_result = make_api_call($api_url, 'PUT', $data);
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        return [
            'success' => true,
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        $result = updateClientFallback($mysqli, $client_id, $recent_order_id, $type_of_remark, $remark);
        return [
            'success' => $result,
            'source' => 'Database (Fallback)'
        ];
    }
}

// Fallback function to import clients to database
function importClientsFallback($mysqli, $clients, $current_time, $currnt_userlogn) {
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($clients as $client) {
        $client_id = $client['client_id'];
        $client_name = $client['client_name'];
        $phone = $client['phone'];
        $invoice_total = $client['invoice_total'];
        
        // Check if client exists
        $sql = "SELECT * FROM wpk4_backend_travel_client_balance WHERE client_id = '$client_id'";
        $result = $mysqli->query($sql);
        
        if ($result->num_rows > 0) {
            // Update existing client
            $sql_update = "UPDATE wpk4_backend_travel_client_balance SET 
                invoice_total='$invoice_total',
                updated_on='$current_time',
                updated_by='$currnt_userlogn',
                status='updated'
                WHERE client_id='$client_id'";
            
            if (mysqli_query($mysqli, $sql_update)) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Failed to update client $client_id: " . mysqli_error($mysqli);
            }
        } else {
            // Insert new client
            $sql_insert = "INSERT INTO wpk4_backend_travel_client_balance 
                (client_name, client_id, phone, invoice_total, status, updated_on, updated_by) 
                VALUES ('$client_name', '$client_id', '$phone', '$invoice_total', 'updated', '$current_time', '$currnt_userlogn')";
            
            if (mysqli_query($mysqli, $sql_insert)) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Failed to insert client $client_id: " . mysqli_error($mysqli);
            }
        }
    }
    
    return [
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors,
        'source' => 'Database (Fallback)'
    ];
}

// Function to import clients via API
function importClients($api_base_url, $mysqli, $clients, $current_time, $currnt_userlogn) {
    $api_url = $api_base_url . '/client-base-manager/clients/import';
    $data = [
        'clients' => $clients,
        'updated_by' => $currnt_userlogn,
        'updated_on' => $current_time
    ];
    
    $api_result = make_api_call($api_url, 'POST', $data);
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        return [
            'success_count' => $api_result['data']['success_count'],
            'error_count' => $api_result['data']['error_count'],
            'errors' => $api_result['data']['errors'] ?? [],
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        return importClientsFallback($mysqli, $clients, $current_time, $currnt_userlogn);
    }
}

// Fallback function to get filtered data from database
function getFilteredDataFallback($mysqli, $search, $options) {
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

// Function to get filtered data from API
function getFilteredData($api_base_url, $mysqli, $search, $options) {
    $api_url = $api_base_url . '/client-base-manager/clients';
    $params = [];
    
    if (!empty($search)) {
        $params['client_id'] = $search;
    }
    
    if (!empty($options)) {
        $params['type_of_remark'] = $options;
    }
    
    if (!empty($params)) {
        $api_url .= '?' . http_build_query($params);
    }
    
    $api_result = make_api_call($api_url, 'GET');
    
    if ($api_result && isset($api_result['status']) && $api_result['status'] === 'success') {
        // Return API data
        return [
            'data' => $api_result['data'],
            'source' => 'API'
        ];
    } else {
        // Fallback to database
        $results = getFilteredDataFallback($mysqli, $search, $options);
        return [
            'data' => $results,
            'source' => 'Database (Fallback)'
        ];
    }
}

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
            // Function to fetch updated data from the database or API
 $start_number =0;
 $end_number = 5;

// Fetch data using API or fallback
 $data_result = fetchUpdatedData($api_base_url, $mysqli);
 $results = $data_result['data'];
 $data_source = $data_result['source'];

// search bar & filter code
if(isset($_GET['search']) && $_GET['search'] != '' || isset($_GET['options']) && $_GET['options'] != ''){
    $search = mysqli_real_escape_string($mysqli, $_GET['search']);
    $options = mysqli_real_escape_string($mysqli, $_GET['options']);
    
    // Get filtered data using API or fallback
    $filtered_result = getFilteredData($api_base_url, $mysqli, $search, $options);
    $results = $filtered_result['data'];
    $data_source = $filtered_result['source'];
}

if(!$mysqli){
    die("Connection error");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['export'])) {
    // Handle form submission for updating client data
    $recent_order_ids = $_POST['recent_order_id'];
    $type_of_remarks = $_POST['type_of_remark'];
    $remarks = $_POST['remark'];
    
    $update_results = [];
    $update_sources = [];
    
    // Loop through each row and update the database
    for ($i = 0; $i < count($recent_order_ids); $i++) {
        $recent_order_id = mysqli_real_escape_string($mysqli, $recent_order_ids[$i]);
        $type_of_remark = mysqli_real_escape_string($mysqli, $type_of_remarks[$i]);
        $remark = mysqli_real_escape_string($mysqli, $remarks[$i]);
        
        // Get client_id from the results
        if (is_resource($results)) {
            // Database result
            mysqli_data_seek($results, $i);
            $row = mysqli_fetch_assoc($results);
        } else {
            // API result
            $row = $results[$i];
        }
        
        $client_id = mysqli_real_escape_string($mysqli, $row['client_id']);
        
        // Update using API or fallback
        $update_result = updateClient($api_base_url, $mysqli, $client_id, $recent_order_id, $type_of_remark, $remark);
        $update_results[] = $update_result['success'];
        $update_sources[] = $update_result['source'];
    }
    
    // Fetch updated data after submission
    $data_result = fetchUpdatedData($api_base_url, $mysqli);
    $results = $data_result['data'];
    $data_source = $data_result['source'];
}

// Function to output CSV
function outputCSV($data) {
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
}

// Export to CSV
if (isset($_POST['export'])) {
    // Fetch filtered data
    $search = mysqli_real_escape_string($mysqli, $_GET['search'] ?? '');
    $options = mysqli_real_escape_string($mysqli, $_GET['options'] ?? '');
    
    $filtered_result = getFilteredData($api_base_url, $mysqli, $search, $options);
    $filteredResults = $filtered_result['data'];

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
    if (is_resource($filteredResults)) {
        // Database result
        while ($row = mysqli_fetch_assoc($filteredResults)) {
            outputCSV(array($row));
        }
    } else {
        // API result
        foreach ($filteredResults as $row) {
            outputCSV(array($row));
        }
    }

    // Close database connection
    mysqli_close($mysqli);
    exit();
}

?>
<h1 class="text-center" style=" font-size: xxx-large;">CLIENTS <span style="color:#ffba10;">BASE</span> DETAILS</h1>
<div style="display: flex; justify-content: center; gap:25px; padding-bottom:25px; font-size: inherit; font-weight: 600;">
<?php 
// Get distinct types of remarks using API or fallback
 $distinct_result = getDistinctRemarkTypes($api_base_url, $mysqli);
 $distinct = $distinct_result['data'];
 $remark_source = $distinct_result['source'];

// Get remark type counts using API or fallback
 $counts_result = getRemarkTypeCounts($api_base_url, $mysqli);
 $counts = $counts_result['data'];
 $counts_source = $counts_result['source'];

// Display data source indicator
echo "<div style='position: absolute; top: 10px; right: 10px; background-color: #f8f9fa; padding: 5px 10px; border-radius: 4px; font-size: 12px;'>";
echo "Data Source: " . $data_source;
echo "</div>";

 $iteration = 0; // Initialize iteration counter
if (is_resource($distinct)) {
    // Database result
    while($row = mysqli_fetch_assoc($distinct)): 
        $type_of_remark = $row['type_of_remark'];
        $count = $counts[$type_of_remark] ?? 0;
        $color = ($iteration % 2 == 0) ? "black" : "#ffba10";
        ?>
        <p style="color: <?php echo $color; ?>"><?php echo $type_of_remark . " : " .$count; ?></p>
        <?php 
        $iteration++; // Increment iteration counter
    endwhile; 
} else {
    // API result
    foreach ($distinct as $type_of_remark): 
        $count = $counts[$type_of_remark] ?? 0;
        $color = ($iteration % 2 == 0) ? "black" : "#ffba10";
        ?>
        <p style="color: <?php echo $color; ?>"><?php echo $type_of_remark . " : " .$count; ?></p>
        <?php 
        $iteration++; // Increment iteration counter
    endforeach; 
}
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
    </tr>
    <tr>
    <?php 
    // Check if results is a resource (database) or array (API)
    $is_db_result = is_resource($results);
    $num_rows = $is_db_result ? mysqli_num_rows($results) : count($results);
    
    if ($num_rows == 0): ?>
        <tr id="hidden-col">
            <td colspan='9'>Data is not found</td>
        </tr>
    <?php else: ?>
        <?php 
        $val = 1; 
        if ($is_db_result) {
            // Database result
            foreach($results as $row): ?>
                <tr>
                    <td><?php echo $val ?></td>
                    <td><?php echo $row['client_name']?></td>
                    <td><?php echo $row['client_id']?></td>
                    <td><?php echo $row['phone']?></td>
                    <td><?php echo $row['invoice_total']?></td>
                    <td><input  type="text" name="recent_order_id[]" value="<?php echo $row['recent_order_id'] ?>"></td>
                    <td><input  type="text" name="type_of_remark[]" value="<?php echo $row['type_of_remark'] ?>"></td>
                    <td><input  type="text" name="remark[]" value="<?php echo $row['remark'] ?>"></td>
                </tr>
                <?php $val++; ?>
            <?php endforeach; 
        } else {
            // API result
            foreach($results as $row): ?>
                <tr>
                    <td><?php echo $val ?></td>
                    <td><?php echo $row['client_name']?></td>
                    <td><?php echo $row['client_id']?></td>
                    <td><?php echo $row['phone']?></td>
                    <td><?php echo $row['invoice_total']?></td>
                    <td><input  type="text" name="recent_order_id[]" value="<?php echo $row['recent_order_id'] ?>"></td>
                    <td><input  type="text" name="type_of_remark[]" value="<?php echo $row['type_of_remark'] ?>"></td>
                    <td><input  type="text" name="remark[]" value="<?php echo $row['remark'] ?>"></td>
                </tr>
                <?php $val++; ?>
            <?php endforeach; 
        }
        ?>
    <?php endif; ?>
</tr>
</table>
<div style="display:flex; justify-content:space-between;">
    <button style="margin-top:25px" type="submit" id="btn">Save Details</button>
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
                        $clients_to_import = [];
                        
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
                                
                                // Check if client exists using API or fallback
                                $client_check_url = $api_base_url . '/client-base-manager/clients?client_id=' . urlencode($clientid);
                                $client_check_result = make_api_call($client_check_url, 'GET');
                                
                                $client_exists = false;
                                if ($client_check_result && isset($client_check_result['status']) && $client_check_result['status'] === 'success') {
                                    // API result
                                    $client_exists = !empty($client_check_result['data']);
                                } else {
                                    // Fallback to database
                                    $sql = "SELECT * FROM wpk4_backend_travel_client_balance WHERE client_id = '$clientid'";
                                    $result = $mysqli->query($sql);
                                    $client_exists = ($result->num_rows > 0);
                                }
                                
                                $tablestirng.= "<tr>
                                <td>".$autonumber."</td>
                                <td>".$clientid."</td>
                                <td>".$clientname."</td>
                                <td>".$phone."</td>
                                <td>".$invoicetotal."</td>
                                ";
                                    
                                if($client_exists) {
                                    $match_hidden = 'Existing';
                                    $match= "Existing";
                                    $checked="checked";
                                } else {
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
                    $clients_to_import = [];
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
                            
                                $clients_to_import[] = [
                                    'client_id' => $clientid_post,
                                    'client_name' => $clientname_post,
                                    'phone' => $phone_post,
                                    'invoice_total' => $invoicetotal_post,
                                    'status' => $match_hidden_post
                                ];
                        }
                    }
                    
                    // Import clients using API or fallback
                    $import_result = importClients($api_base_url, $mysqli, $clients_to_import, $current_time, $currnt_userlogn);
                    
                    // Display result message
                    $message = "Import completed. Success: {$import_result['success_count']}, Errors: {$import_result['error_count']}. Source: {$import_result['source']}";
                    echo '<script>alert("' . $message . '");</script>';
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