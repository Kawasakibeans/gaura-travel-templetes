<?php
date_default_timezone_set("Australia/Melbourne"); 
header("Content-Type: application/json");

$mysqli = new mysqli("localhost","gaurat_sriharan","r)?2lc^Q0cAE","gaurat_gauratravel");

if ($mysqli->connect_errno) {
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $mysqli->connect_error]);
    exit;
}

$table_main = "wpk4_booking_quotes_main";
$table_options = "wpk4_booking_quotes_options";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['flights']) || empty($data['flights'])) {
    echo json_encode(["success" => false, "message" => "Missing required data (flights)."]);
    exit;
}

$phone_number = isset($data['phone']) ? $mysqli->real_escape_string($data['phone']) : null;
$user_id = "test1"; 
$created_at = date("Y-m-d H:i:s");

$query_main = "INSERT INTO $table_main (phone_number, name) VALUES ('$phone_number', '$user_id')";

if ($mysqli->query($query_main)) {
    $booking_id = $mysqli->insert_id; 
} else {
    echo json_encode(["success" => false, "message" => "Error inserting quote: " . $mysqli->error]);
    exit;
}

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/quotes/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
}

$unique_id = 1;
foreach ($data['flights'] as $flightData) {
    
    $xmlContent = json_encode($flightData['xml'], JSON_PRETTY_PRINT);
    $xmlFilePath = $upload_dir . $booking_id.'_'.$unique_id . ".txt"; // Unique filename using booking ID
    $unique_id++;
    file_put_contents($xmlFilePath, $xmlContent);
    
    $responseJson = $mysqli->real_escape_string(json_encode($flightData['xml']));
    $outboundJson = $mysqli->real_escape_string(json_encode($flightData['outboundFlight']));
    $returnJson = $mysqli->real_escape_string(json_encode($flightData['returnFlight']));
    $packageJson = $mysqli->real_escape_string(json_encode($flightData['package']));

    $query_option = "INSERT INTO $table_options (quote_id, outbound_trip, return_trip, package) 
                     VALUES ('$booking_id', '$outboundJson', '$returnJson', '$packageJson')";

    if (!$mysqli->query($query_option)) {
        echo json_encode(["success" => false, "message" => "Error inserting option: " . $mysqli->error]);
        exit;
    }
}

echo json_encode(["success" => true, "message" => "Quote has been successfully saved!", "quote_id" => $booking_id]);
$mysqli->close();
exit;
?>
