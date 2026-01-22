<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load WordPress configuration to get API_BASE_URL
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$base_url = API_BASE_URL; // Fallback if not defined

// Get parameters
$case_id = isset($_GET['case_id']) ? $_GET['case_id'] : '';
$reservation_ref = isset($_GET['reservation_ref']) ? $_GET['reservation_ref'] : '';

// Prepare API URL
$apiUrl = $base_url . '/dc-remarks';
$queryParams = http_build_query([
    'case_id' => $case_id,
    'reservation_ref' => $reservation_ref
]);
$fullUrl = $apiUrl . '?' . $queryParams;

// Initialize curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// Optional: Add headers if needed, e.g., Authorization
// curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$remarks = [];
$errorMessage = '';

if ($response === false) {
    $errorMessage = 'API request failed: ' . $curlError;
} elseif ($httpCode >= 400) {
    $errorMessage = 'API returned error ' . $httpCode;
    $jsonResponse = json_decode($response, true);
    if (isset($jsonResponse['message'])) {
        $errorMessage .= ': ' . $jsonResponse['message'];
    }
} else {
    $jsonResponse = json_decode($response, true);
    if (isset($jsonResponse['data']['remarks'])) {
        $remarks = $jsonResponse['data']['remarks'];
    } else {
        // Fallback if 'data' wrapper is missing or different
        $remarks = $jsonResponse['remarks'] ?? []; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remarks Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .remarks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .remarks-table th, .remarks-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .remarks-table th {
            background-color: #ffbb00;
            position: sticky;
            top: 0;
        }
        .remarks-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .remarks-table tr:hover {
            background-color: #f1f1f1;
        }
        .no-remarks {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>

    <table class="remarks-table">
        <thead>
            <tr>
                <th>Case ID</th>
                <th>Reservation Ref</th>
                <th>Remark</th>
                <th>Request Type</th>
                <th>Remark Type</th>
                <th>Reason</th>
                <th>Created On</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($errorMessage)) {
                echo '<tr><td colspan="8" class="no-remarks" style="color: red;">' . htmlspecialchars($errorMessage) . '</td></tr>';
            } elseif (!empty($remarks)) {
                foreach ($remarks as $row) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['case_id'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['reservation_ref'] ?? '') . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['remark'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['request_type'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['remark_type'] ?? '')) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['failed_reason'] ?? '')) . '</td>';
                    echo '<td>' . htmlspecialchars($row['created_on'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['created_by'] ?? '') . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="8" class="no-remarks">No remarks found for this case.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</body>
</html>
