<?php
date_default_timezone_set("Australia/Melbourne"); 
require_once ('../../../../wp-config-custom.php');

header('Content-Type: application/json');

// Check if the 'date' and 'end_date' parameters are passed in the GET request
if (isset($_GET['date']) && isset($_GET['end_date'])) {
    $selectedDate = $_GET['date'];
    $selectedDate_end = $_GET['end_date'];

    // Prepare SQL query to fetch data where dep_date is between the selected date range
    $query = "SELECT DISTINCT mh_endorsement, aud_fare
              FROM wpk4_backend_stock_management_sheet 
              WHERE date(dep_date) >= ? AND date(dep_date) <= ?";
    
    $endrosement_id = array();
    $prices = array();
    
    // Prepare the statement
    if ($stmt = $mysqli->prepare($query)) {
        // Bind the parameters to the statement
        $stmt->bind_param("ss", $selectedDate, $selectedDate_end);

        // Execute the statement
        $stmt->execute();

        // Get the result
        $result = $stmt->get_result();

        // Check if there are any rows
        if ($result->num_rows > 0) {

            while ($row = $result->fetch_assoc()) {
                // Collect the data into an array
                $endrosement_id[] = $row['mh_endorsement'];
                $prices[] = $row['aud_fare'];
            }
            // Return the data as a JSON response
            
            
            $endrosement_id = array_unique($endrosement_id);
            $prices = array_unique($prices);
            
            sort($endrosement_id);
            sort($prices);
            
            $response[] = [
                    'success' => true,
                    'endorsement_id' => $endrosement_id,
                    'aud_fare' => $prices
                ];
                
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false]); // No results found
        }

        // Close the statement
        $stmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing date parameters']); // No date parameter
}

// Close the database connection
$mysqli->close();
?>