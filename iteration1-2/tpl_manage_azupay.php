<?php
/**
 * Template Name: Manage Azupay
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
error_reporting(E_ALL);
include("wp-config-custom.php");
date_default_timezone_set("Australia/Melbourne");
$current_time = date('Y-m-d H:i:s');

global $current_user;
$currnt_userlogn = $current_user->user_login;
$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
if($row_ip_selection['ip_address'] == $ip_address)
{
    ?>
    <style>
        .genericButton {
            padding:10px;
            margin:0;
            left:10%;
            font-size:11px;
            border: none;
            color: white;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            background-color: #cd2653;
            color:white;
        }
        .genericDisabledButton {
            padding:10px;
            margin:0;
            left:10%;
            font-size:11px;
            border: none;
            color: white;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            background-color: #b0b0b0;
            color:black;
        }
        .genericTable {
            width:100%; 
            font-size:14px; 
            margin:auto; 
            border:1px solid black;
        }
    </style>
    <?php
    $client = new \GuzzleHttp\Client();

    //$authorization_code = 'SECRB12ADA_3137b12750b376c3b9809e254c35b512_V8hcgQ9RTlK265WL'; // uat
	$authorization_code = 'SECR7566D1_c4cc3709d612d1e0e677833ffbcef703_9Kz3JvUrYqPECSwl'; // live

	//$access_url = 'https://api-uat.azupay.com.au/v1'; // uat
	$access_url = 'https://api.azupay.com.au/v1'; // live
		
    //$client_id = "3137b12750b376c3b9809e254c35b512"; // uat
    $client_id = "c4cc3709d612d1e0e677833ffbcef703"; // live
        
    if(current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ))
    {
        if(!isset($_GET['pg']))
        {
            echo '<a href="?pg=create"><button class="genericButton">Create</button></a> ';
            echo '<a href="?pg=check-balance"><button class="genericButton">Check Balance</button></a> ';
            echo '</br></br>';
            
            ?>
            <script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
			<script>
            window.addEventListener("load", function (event) {
                var currentdate = new Date(); 
                var end_maxtime = currentdate.getFullYear() + "-" + (currentdate.getMonth()+1)  + "-" + currentdate.getDate();
                let drp = new DateRangePicker('paydate',
                {
                    maxDate: end_maxtime,
                    timePicker: false,
                    alwaysShowCalendars: true,
                    singleDatePicker: false,
                    autoApply: false,
                    maxSpan: { "days": 2 },
                    autoUpdateInput: false,
                    ranges: {
                        'Today': [moment().startOf('day'), moment().endOf('day')],
                        'Yesterday': [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
                    },
                    locale: {
                        format: "YYYY-MM-DD",
                    }
                },
                function (start, end) {
                    document.getElementById("paydate").value = start.format() + "-" + end.format();
                })
            });
            </script>
            <script type="text/javascript">
				function searchordejs() {
				    var transactionid = document.getElementById("transactionid").value;
					var paydate = document.getElementById("paydate").value;
					var payid = document.getElementById("payid").value;
					
					window.location='?transactionid=' + transactionid + '&date=' + paydate + '&payid=' + payid;
					
				}
				</script>
                <table class="genericTable">
    			    <tr>
    				    <td width='13%'>clientTransactionId
    				        <input type='text' name='transactionid' value='<?php if($_GET['transactionid'] != '') { echo $_GET['transactionid']; } ?>' id='transactionid'>
    				    </td>
        				<td width='13%'>Date
        				    <input type='text' name='paydate' value='<?php if(isset($_GET['date'])) { echo $_GET['date']; } ?>' id='paydate'>
        				</td>
        				<td width='13%'>payID
        				    <input type='text' name='payid' value='<?php if($_GET['payid'] != '') { echo $_GET['payid']; } ?>' id='payid'>
        				</td>
                    </tr>
                    <tr>
        				<td colspan='3' style='text-align:center;'>
        				    <button class="genericButton" id='search_orders' onclick="searchordejs()">Search</button>
        				</td>
    				</tr>
                </table>
            </br></br>
            
            <?php
            if(isset($_GET['date']) && $_GET['date'] != '') {
                
                $pay_start_date = substr($_GET['date'], 0, 10).'T00:00:00+10:00';
                $pay_new_start_date = new DateTime($pay_start_date);
                
                $formatted_start_date = $pay_new_start_date->format("Y-m-d\TH:i:s.u\Z");
                $formatted_start_date_2 = preg_replace('/\.?0+Z/', '.000Z', $formatted_start_date);
                

                $pay_end_date = substr($_GET['date'], 26, 10).'T23:59:59+10:00';
                $pay_new_end_date = new DateTime($pay_end_date);
                $formatted_end_date = $pay_new_end_date->format("Y-m-d\TH:i:s.u\Z");
                $formatted_end_date_2 = preg_replace('/\.?0+Z/', '.999Z', $formatted_end_date);
                

                $search_body_condition = '"fromDate":"'.$formatted_start_date_2.'","toDate":"'.$formatted_end_date_2.'"'; 
            }
            
            if(isset($_GET['transactionid']) && $_GET['transactionid'] != '') {
                $search_body_condition = '"clientTransactionId":"'.$_GET['transactionid'].'"'; 
            }
            
            if(isset($_GET['payid']) && $_GET['payid'] != '') {
                $search_body_condition = '"payID":"'.$_GET['payid'].'"'; 
            }
            
            
            // default search parameter
            if( (!isset($_GET['transactionid']) && !isset($_GET['date']) && !isset($_GET['payid'])) || ( isset($_GET['transactionid']) && $_GET['transactionid'] == '' && isset($_GET['date']) && $_GET['date'] == '' && isset($_GET['payid']) && $_GET['payid'] == '' ) ) 
            {
                $pay_start_date = date("Y-m-d").'T00:00:00+10:00';
                $pay_new_start_date = new DateTime($pay_start_date);
                
                $formatted_start_date = $pay_new_start_date->format("Y-m-d\TH:i:s.u\Z");
                $formatted_start_date_2 = preg_replace('/\.?0+Z/', '.000Z', $formatted_start_date);
                

                $pay_end_date = date("Y-m-d").'T23:59:59+10:00';
                $pay_new_end_date = new DateTime($pay_end_date);
                $formatted_end_date = $pay_new_end_date->format("Y-m-d\TH:i:s.u\Z");
                $formatted_end_date_2 = preg_replace('/\.?0+Z/', '.999Z', $formatted_end_date);
                
                $search_body_condition .= '"fromDate":"'.$formatted_start_date_2.'","toDate":"'.$formatted_end_date_2.'"'; 
            
            }
            
            $response = $client->request('POST', $access_url.'/paymentRequest/search', [
              'body' => '{"PaymentRequestSearch":{'.$search_body_condition.'}}',
              'headers' => [
                'Authorization' => $authorization_code,
                'accept' => 'application/json',
                'content-type' => 'application/json',
              ],
            ]);
            
            $data = json_decode($response->getBody(), true);

            // Check if the "records" key exists
            if (isset($data['records']) && is_array($data['records'])) {
                echo '<table border="1" class="genericTable">';
                echo '<tr>';
                echo '<th>Pay ID</th>';
                echo '<th>Client Transaction ID</th>';
                echo '<th>Payment Amount</th>';
                echo '<th>Payment Expiry Datetime</th>';
                echo '<th>Checkout URL</th>';
                echo '<th>Created Datetime</th>';
                echo '<th>Payment Request ID</th>';
                echo '<th>Status</th>';
                echo '<th>Status</th>';
                echo '</tr>';
            
                // Loop through the records and populate the table
                foreach ($data['records'] as $index => $record) {
                    
                    if($record['PaymentRequestStatus']['status'] === "COMPLETE") {
                            
                            $clientTransactionId_loop = $record['PaymentRequest']['clientTransactionId'];
                            mysqli_query($mysqli, "insert into wpk4_backend_travel_booking_update_history (order_id,meta_key,meta_value,updated_time,updated_user) 
                            values ('$clientTransactionId_loop','azupay_status','COMPLETE','$current_time','azu_callback')") or die(mysqli_error($mysqli));
                    }
                    
                    echo '<tr>';
                    echo '<td>' . $record['PaymentRequest']['payID'] . '</td>';
                    echo '<td>' . $record['PaymentRequest']['clientTransactionId'] . '</td>';
                    echo '<td>' . ($record['PaymentRequest']['paymentAmount'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($record['PaymentRequest']['paymentExpiryDatetime'] ?? 'N/A') . '</td>';
                    echo '<td><a href="' . $record['PaymentRequest']['checkoutUrl'] . '">Payment URL</a></td>';
                    echo '<td>' . $record['PaymentRequestStatus']['createdDateTime'] . '</td>';
                    echo '<td>' . $record['PaymentRequestStatus']['paymentRequestId'] . '</td>';
                    echo '<td>' . $record['PaymentRequestStatus']['status'] . '</td>';
                    echo '<td><a class="genericButton" href="?pg=check-status&id=' . $record['PaymentRequestStatus']['paymentRequestId'] . '">View</a>
                            <a class="genericDisabledButton" href="#">Delete</a>
                            <a class="genericDisabledButton" href="#">Refund</a>
                    </td>';
                    echo '</tr>';
                }
            
                echo '</table>';
            } else {
                echo 'No records found.';
            }
        }
        else
        {
            if($_GET['pg'] == 'create')
            {
                ?>
                <form action="#" name="contactsubmit" method="post" enctype="multipart/form-data">
        			<table class="table genericTable">	
        				<tr>
        				    <td width="25%">payID</td><td><input type='text' name='payID' required value="payments@gauratravel.com.au" style='width:100%; padding:10px;'></td>
        				</tr>
        			    <tr>
        				    <td>payIDSuffix</td><td><input type='text' readonly name='payIDSuffix' required value="gauratravel.com.au" style='width:100%; padding:10px;'></td>
        				</tr>
        				<tr>
        				    <td>clientId</td><td><input type='text' name='clientId' value="<?php echo $client_id; ?>" required style='width:100%; padding:10px;'></td>
        				</tr>
        				<tr>
        				    <td>clientTransactionId</td><td><input type='text' name='clientTransactionId' required value="ORDID5345345" style='width:100%; padding:10px;'></td>
        				</tr>
        				<tr>
        				    <td>paymentAmount</td><td><input type='text' name='paymentAmount' value="12" required style='width:100%; padding:10px;'></td>
        				</tr>
        				<tr>
        				    <td>paymentDescription</td><td><input type='text' name='paymentDescription' required value="test booking payment description" style='width:100%; padding:10px;'></td>
        				</tr>
        			</table></br>
    			    <center>
    			        <input type='submit' name='btn_createpayment' class='btn btn-success' value='Create Payment'>
    			    </center>
    			</form>
                <?php
                if(isset($_POST['btn_createpayment']))
    			{
        			$payID = $_POST['payID'];
        			$payIDSuffix = $_POST['payIDSuffix'];
        			$clientId = $_POST['clientId'];
        			$clientTransactionId = $_POST['clientTransactionId'];
        			$paymentAmount = $_POST['paymentAmount'];
        			$paymentDescription = $_POST['paymentDescription'];
                    
                    $response = $client->request('POST', $access_url.'/paymentRequest', [
                      'body' => '{"PaymentRequest":{"payID":"'.$payID.'","payIDSuffix":"'.$payIDSuffix.'","clientId":"'.$clientId.'","clientTransactionId":"'.$clientTransactionId.'","paymentAmount":'.$paymentAmount.',"paymentDescription":"'.$paymentDescription.'"}}',
                      'headers' => [
                        'Authorization' => $authorization_code,
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                      ],
                    ]);
                    
                    
                    $data = json_decode($response->getBody(), true);

                    /*
                    echo '<table class="genericTable">';
                    echo '<tr><th width="25%">Title</th><th>Value</th></tr>';
                    
                    function addTableRow($field, $value) {
                        echo '<tr>';
                        echo '<td>' . $field . '</td>';
                        echo '<td>' . $value . '</td>';
                        echo '</tr>';
                    }
                    
                    // Process the data
                    foreach ($data as $key1 => $value1) {
                        if (is_array($value1)) {
                            foreach ($value1 as $subKey1 => $subValue1) {
                                addTableRow($subKey1, $subValue1);
                            }
                        } else {
                            addTableRow($key1, $value1);
                        }
                    }
                    
                    echo '</table>';
                    */
                    
                    echo '<script>
                            alert("Payment has been initiated."); 
                            window.location.href="?";
                        </script>';
    			}
            }
            if($_GET['pg'] == 'check-status')
            {
                $paymentid = $_GET['id'];
                $response = $client->request('GET', $access_url.'/paymentRequest?id='.$paymentid, [
                  'headers' => [
                    'Authorization' => $authorization_code,
                    'accept' => 'application/json',
                  ],
                ]);
                
                $data = json_decode($response->getBody(), true);
                //print_r($data);

                
                function displayData($data) {
                    echo "<table>";
                    foreach ($data as $key => $value) {
                        echo "<tr>";
                        if($key != 0) {
                            echo "<td>$key</td>";
                        }
                        echo "<td>";
                        if (is_array($value)) {
                            displayData($value);
                        } else {
                            echo $value;
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                displayData($data);
                
                
                
            }
            if($_GET['pg'] == 'check-balance')
            {
                $response = $client->request('GET', $access_url.'/balance', [
                  'headers' => [
                    'Authorization' => $authorization_code,
                    'accept' => 'application/json',
                  ],
                ]);
                
                $jsonData = json_decode($response->getBody(), true);
                echo '<h6>';
                echo 'Balance: ' . $jsonData['balance'] . '<br>';
                echo 'Payout Balance: ' . $jsonData['payOut']['balance'] . '<br>';
                echo 'Payin Balance: ' . $jsonData['payIn']['balance'] . '<br>';
                echo '</h6>';

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