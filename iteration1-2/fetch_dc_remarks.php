<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Get parameters
$case_id = isset($_POST['case_id']) ? $mysqli->real_escape_string($_POST['case_id']) : '';
$reservation_ref = isset($_POST['reservation_ref']) ? $mysqli->real_escape_string($_POST['reservation_ref']) : '';

// Fetch remarks
$query = "SELECT * FROM wpk4_backend_dc_remark 
          WHERE case_id = '$case_id' AND reservation_ref = '$reservation_ref'
          ORDER BY created_on DESC";
$result = $mysqli->query($query);

// HTML output with table
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
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['case_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['reservation_ref']) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['remark'])) . '</td>';
                    
                    echo '<td>' . nl2br(htmlspecialchars($row['request_type'])) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['remark_type'])) . '</td>';
                    echo '<td>' . nl2br(htmlspecialchars($row['failed_reason'])) . '</td>';
                    
                    echo '<td>' . htmlspecialchars($row['created_on']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['created_by']) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="8" class="no-remarks">No remarks found for this case.</td></tr>';
            }
            ?>
        </tbody>
    </table>

    <?php
    $mysqli->close();
    ?>
</body>
</html>