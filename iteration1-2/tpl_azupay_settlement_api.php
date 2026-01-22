<?php
if (ob_get_level() == 0) ob_start();

date_default_timezone_set("Australia/Melbourne");
include("../../../../wp-config-custom.php");
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$current_date_for_filename = date('YmdHis');

// Use ECMA262 simplified ISO 8601 format (UTC with Z suffix)
$timezone = new DateTimeZone('Australia/Melbourne');
$yesterday_start = new DateTime('yesterday', $timezone);
$yesterday_start->setTime(0, 0, 0);
$yesterday_end = new DateTime('yesterday', $timezone);
$yesterday_end->setTime(23, 59, 59);

// Convert to UTC and format as ECMA262 simplified ISO 8601 (YYYY-MM-DDTHH:mm:ssZ)
$yesterday_start->setTimezone(new DateTimeZone('UTC'));
$yesterday_end->setTimezone(new DateTimeZone('UTC'));
$from_date = $yesterday_start->format('Y-m-d\TH:i:s\Z');
$to_date = $yesterday_end->format('Y-m-d\TH:i:s\Z');


try {
    $url_report = 'https://api.azupay.com.au/v1/report?clientId=c4cc3709d612d1e0e677833ffbcef703&fromDate=' . $from_date . '&toDate=' . $to_date . '&timezone=Australia/Sydney';
    $ch = curl_init($url_report);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: SECR7566D1_c4cc3709d612d1e0e677833ffbcef703_9Kz3JvUrYqPECSwl',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    echo "<pre>🔍 Raw Response:\n";
    print_r($data);
    echo "</pre>";
    $reportId = $data['reports'][0]['reportId'] ?? null;
    if (!$reportId) {
        echo "❌ Report ID not found<br>";
    }



    $url_download = 'https://api.azupay.com.au/v1/report/download?clientId=c4cc3709d612d1e0e677833ffbcef703&reportId=' . $reportId;
    $ch = curl_init($url_download);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: SECR7566D1_c4cc3709d612d1e0e677833ffbcef703_9Kz3JvUrYqPECSwl',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    $reportData = json_decode($response, true);
    $reportUrl = $reportData['reportUrl'] ?? null;
    if (!$reportUrl) {
        echo "❌ Report URL not found<br>";
    }


    $fileContent = '';
    if ($reportUrl) {
        $ch = curl_init($reportUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $fileContent = curl_exec($ch);
        if ($fileContent === false) {
            throw new Exception('Download failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    $report_name = 'azupay_report-' . $current_date_for_filename . '.csv';
    $filePath = '/home/gaurat/public_html/csv_reports/' . $report_name;
    file_put_contents($filePath, $fileContent);


    $csvData = [];
    $headers = [];

    if (!file_exists($filePath)) {
        echo "⚠️ CSV file not found at path: $filePath<br>";
    } elseif (filesize($filePath) === 0) {
        echo "⚠️ CSV file exists but is empty (0 bytes).<br>";
    } else {
        $csvLines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!empty($csvLines)) {
            $csvData = array_map('str_getcsv', $csvLines);
            if (isset($csvData[0]) && isset($csvData[0][0]) && trim($csvData[0][0]) !== '') {
                $headers = explode('|', trim($csvData[0][0]));
            } else {
                echo "⚠️ CSV header empty or malformed.<br>";
                $headers = [];
            }
        } else {
            echo "⚠️ CSV file contains no valid lines.<br>";
        }
    }

    if (!isset($csvData) || !is_array($csvData)) $csvData = [];
    if (!isset($headers) || !is_array($headers)) $headers = [];

    echo "<p>📄 CSV Path: {$filePath}</p>";
    echo "<p>📦 File Size: " . (file_exists($filePath) ? filesize($filePath) : 0) . " bytes</p>";
    echo "<p>🧾 Headers Found: " . count($headers) . "</p>";

    if (!empty($headers) && count($csvData) > 1) {
        echo "<p>✅ CSV file parsed successfully.</p>";
    } else {
        echo "<p>⚠️ CSV file is empty or malformed (safe skip).</p>";
        $csvData = [['']];
        $headers = ['DummyHeader'];
    }


    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    

    $query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address='$ip_address'";
    $result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
    $is_ip_matched = mysqli_num_rows($result_ip_selection);

    echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
    $tablestirng = "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
    <tr>
        <td>#</td><td>Date</td><td>OrderID</td><td>Amount</td><td>Current Payment Status</td>
        <td>Reference</td><td>Settlement Date</td><td width='10%;'>Message</td><td>Existing/New</td>
    </tr>";

    $autonumber = 0;
    for ($i = 1; $i < count($csvData); $i++) {
        $data = isset($csvData[$i][0]) ? explode('|', $csvData[$i][0]) : [];
        if (count($headers) !== count($data)) continue;
        $transaction = @array_combine($headers, $data);
        if (!$transaction || !isset($transaction['DateTime'])) continue;

                    $transaction = array_combine($headers, $data);
                    //print_r($transaction['AccountName']);
                    echo '<pre>';
                    print_r($transaction);
                    echo '</pre>';
                    //echo '</br>';
                    //echo '</br>';
                    
                    $transaction_local_date = date('Y-m-d H:i:s', strtotime(substr($transaction['DateTime'], 0, 19)));
                    

                    $new_transaction_date = substr($transaction['LocalTime'], 0, 19);
                    $crdr = $transaction['CRDR'];
                    $amount = number_format((float)$transaction['Amount'], 2, '.', '');
                    $payment_type_block = $transaction['TransactionType'];
                    $payid = $transaction['PayId'];
                    $payment_description = $transaction['PaymentDescription'];
                    $payment_customer_reference = $transaction['PayerPaymentReference'];
                    $payment_request_id_child = $transaction['TransactionId'];
                    $payment_request_id = $transaction['ParentTransactionId'];
                    $clientTransactionId = $transaction['ClientTransactionId'];
                    $NPPTransactionId = $transaction['NPPTransactionId'];
                    
                    $new_settlement_date = date("Y-m-d", strtotime("+1 days", strtotime($new_transaction_date))) . ' ' . date("H:i:s");
                    
                    $payment_method_number = '7';
            		$autonumber++;
                    if($payment_type_block == 'PaymentRequest' && $payment_request_id != '')
            							{
            							    if($payment_request_id == '')
            							    {
            							        $payment_payid = $payid;
            							        
            							        $sql_payment_requests = "SELECT payment_request_id  FROM wpk4_backend_travel_booking_custom_payments where azupay_payid = '$payment_payid'";
                            					$result_payment_requests = $mysqli->query($sql_payment_requests);
                            					$row_payment_requests = $result_payment_requests->fetch_assoc();
                            					$payment_request_id = $row_payment_requests['payment_request_id'];
            							    }
            							    
            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                							$payment_request_id_new = $payment_request_id;										
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id."' ".$checked." \/></td></tr>";
                
            							}
            							
            							if($payment_type_block == 'PaymentRequest' && $payment_request_id_child != '' && $payment_request_id == '')
            							{
            							    if($payment_request_id_child == '')
            							    {
            							        $payment_payid = $payid;
            							        
            							        $sql_payment_requests = "SELECT payment_request_id  FROM wpk4_backend_travel_booking_custom_payments where azupay_payid = '$payment_payid'";
                            					$result_payment_requests = $mysqli->query($sql_payment_requests);
                            					$row_payment_requests = $result_payment_requests->fetch_assoc();
                            					$payment_request_id_child = $row_payment_requests['payment_request_id'];
            							    }
            							    
            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                								$payment_request_id_new = $payment_request_id_child;									
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id_child."' ".$checked." \/></td></tr>";
                
            							}
            							if($payment_type_block == 'Payment' && $payment_request_id_child != '')
            							{

            							    $order_id = '';
            							    $sql_request_id = "SELECT order_id  FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child'";
                        					$result_request_id = $mysqli->query($sql_request_id);
                        					$row_request_id = $result_request_id->fetch_assoc();
                        					if ($result_request_id->num_rows > 0) 
                    						{
                        					    $order_id = $row_request_id['order_id'];
                    						}
            							    $order_id_from_booking_table = '';
            							    $payment_status_from_booking_table = '';
                							$sql = "SELECT order_id, payment_status FROM wpk4_backend_travel_bookings where order_id = '$order_id'";
                    						$result = $mysqli->query($sql);
                    						$row = $result->fetch_assoc();
                    						if ($result->num_rows > 0) 
                    						{
                    						    $order_id_from_booking_table = $row['order_id'];
                    						    $payment_status_from_booking_table = $row['payment_status'];
                    						}
                							$tablestirng.= "<tr>
                								<td>".$autonumber."</td>
                								<td>".$new_transaction_date."</td>
                								<td>".$order_id."</td>
                								<td>".$amount."</td>
                								<td>".$payment_status_from_booking_table ."</td>
                								<td>".$payment_description . ' ' . $payment_customer_reference ."</td>
                								<td>".$new_settlement_date."</td>
                								";
                								
                								$match = [];
                								
                								if($order_id == $order_id_from_booking_table)
                								{
                								    $is_booking_exists = true;
                								}
                								else 
                								{
                								    $is_booking_exists = false;
                									$match[] = "<font style='color:red;'>Booking is not exist</font>";
                								}
                								$order_id_from_payment_table = '';
                								
                								$negative_amount = '-'.$amount;
                								if($crdr == 'DR')
                								{
                								    $amount = $negative_amount;
                								}
                								$sql_2 = "SELECT order_id FROM wpk4_backend_travel_payment_history where payment_request_id = '$payment_request_id_child' AND CAST(trams_received_amount AS DECIMAL(10,2)) = CAST($negative_amount AS DECIMAL(10,2)) AND payment_method = '7'";
                    							$result_2 = $mysqli->query($sql_2);
                    							$row_2 = $result_2->fetch_assoc();
                    							if ($result_2->num_rows > 0) 
                    							{
                    							    $order_id_from_payment_table = $row_2['order_id'];
            							        }
                							
                								if($order_id == $order_id_from_payment_table)
                								{
                									$match_hidden = 'Existing';
                									if($is_booking_exists)
                									{
                									    $checked="checked";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								else 
                								{
                									$match_hidden = 'New';
                									$match[] = "<font style='color:red;'>Payment is not exist</font>";
                									
                									if($is_booking_exists)
                									{
                									    $checked="";
                									}
                									else
                									{
                									    $checked="";
                									}
                								}
                								
                							$tablestirng.= "<td><input type='hidden' name='".$autonumber."_matchmaker' value='".$match_hidden."'>";
                							            if(isset($match[0]) && $match[0] != '')
                							            {
                							                $tablestirng.= $match[0];
                							            }
                							            
                							            if(isset($match[1]) && $match[1] != '')
                							            {
                							                $tablestirng.= '</br></br>'.$match[1];
                							            }
                							 $tablestirng.= "</td>";
                								$payment_request_id_new = $payment_request_id_child;							
                							$tablestirng.="<td><input type='checkbox' id='chk".$autonumber."' 
                							    name='".$autonumber."_checkoption' value='".$order_id."@#".$negative_amount."@#".$new_transaction_date."@#".$order_id."@#".$payment_method_number."@#".$match_hidden."@#".$new_settlement_date."@#".$payment_request_id_child."' ".$checked." \/></td></tr>";
                
            							}
            							
            							
            				if($payment_type_block == 'PaymentRequest' || $payment_type_block == 'Payment' )
            				{
            				    
            				
            							
            					$transaction_date_without_seconds = substr($new_transaction_date, 0, 10);
        						    
        						$payment_additional_stack = " AND order_id = '$order_id' AND payment_request_id = '".$payment_request_id_new."' AND date(process_date) = '" . $transaction_date_without_seconds . "' ";
        						
                                $sql_update_status = "UPDATE wpk4_backend_travel_payment_history SET 
                							is_reconciliated = 'yes',
                							cleared_date = '$new_settlement_date',
                							cleared_by = 'azupay_settlement_api'
            							WHERE ( CAST(trams_received_amount AS DECIMAL(10,2)) = CAST('$amount' AS DECIMAL(10,2)) ) AND cleared_date is null and cleared_by is null and payment_method = '$payment_method_number' $payment_additional_stack";
            							
            					    echo $sql_update_status.'</br></br>';
            		           
            		           $result_status= mysqli_query($mysqli,$sql_update_status) or die(mysqli_error($mysqli));
            				}
            						
                }
                
    

    $tablestirng .= '</table></form></center>';
    echo $tablestirng;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


$html_output = ob_get_clean();
$response_data = [
    "ok" => true,
    "status" => "success",
    "timestamp" => date('c'),
    "html_output" => base64_encode($html_output),
    "message" => "HTML captured successfully",
    "meta" => [
        "file" => basename(__FILE__),
        "php" => PHP_VERSION,
        "host" => $_SERVER['HTTP_HOST']
    ]
];

if (strpos($html_output, 'Report ID not found') !== false ||
    strpos($html_output, 'Report URL not found') !== false ||
    strpos($html_output, 'Error:') !== false ||
    strpos($html_output, '❌') !== false) {
    $response_data['ok'] = false;
    $response_data['status'] = 'failed';
    $response_data['message'] = 'Azupay fetch failed or incomplete';
    $response_data['error_details'] = strip_tags($html_output);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;