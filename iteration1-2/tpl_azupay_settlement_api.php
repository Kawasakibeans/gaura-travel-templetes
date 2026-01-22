<?php
if (ob_get_level() == 0) ob_start();

date_default_timezone_set("Australia/Melbourne");
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$current_date_for_filename = date('YmdHis');

// Use ECMA262 simplified ISO 8601 format (UTC with Z suffix)
$timezone = new DateTimeZone('Australia/Sydney');
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
    // Fetch Azupay report (keep original logic)
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

    // Download report
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

    // Download CSV file
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
    $filePath = '/home/gt1ybwhome/public_html/csv_reports/' . $report_name;
    file_put_contents($filePath, $fileContent);

    // Parse CSV
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

    // Check IP access using API
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipCheckResult = callAPI($base_url . '/azupay-settlement/check-ip', 'POST', ['ip_address' => $ip_address]);
    $is_ip_matched = 0;
    if ($ipCheckResult && isset($ipCheckResult['status']) && $ipCheckResult['status'] === 'success') {
        if (isset($ipCheckResult['data']['has_access']) && $ipCheckResult['data']['has_access']) {
            $is_ip_matched = 1;
        }
    }

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
        echo '<pre>';
        print_r($transaction);
        echo '</pre>';
        
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

        // Process transaction using API
        $processResult = callAPI($base_url . '/azupay-settlement/process-transaction', 'POST', [
            'transaction' => $transaction
        ]);

        if ($processResult && isset($processResult['status']) && $processResult['status'] === 'success') {
            $processData = $processResult['data'];
            $order_id = $processData['order_id'] ?? '';
            $order_id_from_booking_table = $processData['order_id_from_booking'] ?? '';
            $payment_status_from_booking_table = $processData['payment_status'] ?? '';
            $match_hidden = $processData['match_status'] ?? 'New';
            $is_booking_exists = $processData['is_booking_exists'] ?? false;
            $match_messages = $processData['match_messages'] ?? [];
            $payment_request_id_new = $processData['payment_request_id'] ?? $payment_request_id ?? $payment_request_id_child;

            // Handle negative amount for DR transactions
            $display_amount = $amount;
            if ($payment_type_block == 'Payment' && $crdr == 'DR') {
                $display_amount = '-' . $amount;
            }

            $tablestirng .= "<tr>
                <td>" . $autonumber . "</td>
                <td>" . $new_transaction_date . "</td>
                <td>" . $order_id . "</td>
                <td>" . $display_amount . "</td>
                <td>" . $payment_status_from_booking_table . "</td>
                <td>" . $payment_description . ' ' . $payment_customer_reference . "</td>
                <td>" . $new_settlement_date . "</td>
            ";

            $match = [];
            if (!$is_booking_exists) {
                $match[] = "<font style='color:red;'>Booking is not exist</font>";
            }
            if ($match_hidden == 'New') {
                $match[] = "<font style='color:red;'>Payment is not exist</font>";
            }
            if (!empty($match_messages)) {
                $match = array_merge($match, $match_messages);
            }

            $checked = ($is_booking_exists && $match_hidden == 'Existing') ? 'checked' : '';

            $tablestirng .= "<td><input type='hidden' name='" . $autonumber . "_matchmaker' value='" . $match_hidden . "'>";
            if (isset($match[0]) && $match[0] != '') {
                $tablestirng .= $match[0];
            }
            if (isset($match[1]) && $match[1] != '') {
                $tablestirng .= '</br></br>' . $match[1];
            }
            $tablestirng .= "</td>";

            // Update reconciliation using API
            if ($payment_type_block == 'PaymentRequest' || $payment_type_block == 'Payment') {
                $transaction_date_without_seconds = substr($new_transaction_date, 0, 10);
                
                $updateResult = callAPI($base_url . '/azupay-settlement/update-reconciliation', 'POST', [
                    'order_id' => $order_id,
                    'payment_request_id' => $payment_request_id_new,
                    'transaction_date' => $new_transaction_date,
                    'amount' => $display_amount,
                    'settlement_date' => $new_settlement_date,
                    'payment_method' => $payment_method_number
                ]);

                if ($updateResult && isset($updateResult['status']) && $updateResult['status'] === 'success') {
                    echo "✅ Reconciliation updated for order: $order_id<br>";
                } else {
                    $errorMsg = $updateResult['message'] ?? 'Unknown error';
                    echo "⚠️ Failed to update reconciliation for order: $order_id - $errorMsg<br>";
                }
            }

            $tablestirng .= "<td><input type='checkbox' id='chk" . $autonumber . "' 
                name='" . $autonumber . "_checkoption' value='" . $order_id . "@#" . $display_amount . "@#" . $new_transaction_date . "@#" . $order_id . "@#" . $payment_method_number . "@#" . $match_hidden . "@#" . $new_settlement_date . "@#" . $payment_request_id_new . "' " . $checked . " /></td></tr>";
        } else {
            $errorMsg = $processResult['message'] ?? 'Unknown error';
            echo "⚠️ Failed to process transaction: $errorMsg<br>";
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