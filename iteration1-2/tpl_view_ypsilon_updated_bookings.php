<?php
/**
 * Template Name: View Ypsilon Updated Bookings-tanvi
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header(); ?>

<html>
<head>
<style>
/* Simple styling for the table */
.search_bookings { font-family: sans-serif; }
.table { width:100%; border-collapse: collapse; margin: auto; font-size:14px; }
.table th, .table td { padding:8px; border: 1px solid #ddd; text-align: left; }
.table thead { background-color: #f2f2f2; }
.table tbody tr:nth-child(even) { background-color: #f9f9f9; }
.blink_me { animation: blinker 1.5s linear infinite; }
@keyframes blinker { 50% { opacity: 0; } }
.api-error {
  background-color: #ffebee;
  color: #d32f2f;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
}
.api-fallback {
  background-color: #e8f5e9;
  color: #2e7d32;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
}
.debug-info {
  background-color: #f5f5f5;
  border: 1px solid #ddd;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
  font-family: monospace;
  white-space: pre-wrap;
}
</style>
</head>

<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>

<?php
date_default_timezone_set("Australia/Melbourne");
error_reporting(E_ALL);

// Enable debug mode
 $debug_mode = false; // Set to false in production

// Function to log debug information
function debug_log($message, $data = null) {
    global $debug_mode;
    if ($debug_mode) {
        $output = $message;
        if ($data !== null) {
            $output .= ': ' . print_r($data, true);
        }
        echo '<div class="debug-info">' . htmlspecialchars($output) . '</div>';
    }
}

// Load WordPress environment to access API_BASE_URL constant
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// API Configuration
 $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';

debug_log("API Base URL", $apiBaseUrl);

/**
 * Fetches Ypsilon updated bookings from the new API.
 *
 * @param int $limit The maximum number of results to return.
 * @return array|null The decoded JSON data from the API, or null on failure.
 */
function getYpsilonUpdatedBookingsFromAPI(int $limit = 100): ?array
{
    global $apiBaseUrl;
    
    // Correct endpoint without duplicate /v1
    $endpoint = $apiBaseUrl . '/v1/bookings/ypsilon-updated';
    $params = ['limit' => $limit];
    $requestUrl = $endpoint . '?' . http_build_query($params);
    
    debug_log("Making API call to", $requestUrl);
    debug_log("With parameters", $params);

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        curl_close($ch);

        debug_log("HTTP Status Code", $httpCode);
        debug_log("Response Headers", $responseHeaders);
        debug_log("Response Body", $responseBody);

        if ($response === false || !empty($curlError)) {
            debug_log("API Error", $curlError);
            error_log("API Error for ypsilon-updated-bookings: " . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            debug_log("API HTTP Error", "Status code " . $httpCode . ", Response: " . $responseBody);
            error_log("API HTTP Error for ypsilon-updated-bookings: Status code " . $httpCode . ", Response: " . $responseBody);
            return null;
        }

        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debug_log("API JSON Error", json_last_error_msg() . ", Response: " . $responseBody);
            error_log("API JSON Error for ypsilon-updated-bookings: " . json_last_error_msg() . ", Response: " . $responseBody);
            return null;
        }

        // Extract data from API response structure
        // API returns: { status: 'success', message: '...', data: [...] }
        if (isset($data['data']) && is_array($data['data'])) {
            $bookings = $data['data'];
        } elseif (is_array($data)) {
            // Fallback: if data is already an array, use it directly
            $bookings = $data;
        } else {
            debug_log("API Data Error", "Unexpected response structure: " . print_r($data, true));
            error_log("API Data Error for ypsilon-updated-bookings: Unexpected response structure");
            return null;
        }

        debug_log("API Success", "Retrieved " . count($bookings) . " bookings");
        return $bookings;

    } catch (Exception $e) {
        debug_log("API Exception", $e->getMessage());
        error_log("API Exception for ypsilon-updated-bookings: " . $e->getMessage());
        return null;
    }
}

// Main logic
 $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
debug_log("Request limit", $limit);

 $bookings = getYpsilonUpdatedBookingsFromAPI($limit);
 $usedApi = true;

// FALLBACK: If API fails, use original DB logic
if ($bookings === null) {
    debug_log("API call failed. Falling back to database query.");
    error_log("API call failed. Falling back to database query.");
    $usedApi = false;
    
    // Include the custom config file that sets up the database connection
    include("wp-config-custom.php");
    
    // Check if the mysqli connection was established
    if (!isset($mysqli) || $mysqli->connect_error) {
        debug_log("Database connection failed", isset($mysqli) ? $mysqli->connect_error : "Connection not established");
        error_log("Database connection failed. Cannot retrieve bookings.");
        $bookings = [];
    } else {
        // Use the same query as the working code
        $query = "SELECT * FROM wpk4_backend_travel_bookings WHERE is_updated = 'yes' ORDER BY order_id DESC LIMIT " . (int)$limit;
        debug_log("Executing query", $query);
        
        $result = mysqli_query($mysqli, $query);
        if (!$result) {
            debug_log("Database query failed", mysqli_error($mysqli));
            error_log("Database query failed: " . mysqli_error($mysqli));
            $bookings = [];
        } else {
            $bookings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                // For each booking, get the latest update time from the history table
                $order_id = $row['order_id'];
                $query_selection_meta = "SELECT * FROM wpk4_backend_history_of_meta_changes WHERE type_id = '$order_id' ORDER BY auto_id DESC LIMIT 1";
                $result_selection_meta = mysqli_query($mysqli, $query_selection_meta);
                
                if ($result_selection_meta && mysqli_num_rows($result_selection_meta) > 0) {
                    $row_selection_meta = mysqli_fetch_assoc($result_selection_meta);
                    $row['gds_data_updated_on'] = $row_selection_meta['updated_on'];
                }
                
                $bookings[] = $row;
            }
            debug_log("Database query successful", "Retrieved " . count($bookings) . " bookings");
        }
    }
}
?>

<div class="search_bookings">
<h5>View Booking</h5>

<?php if (!$usedApi): ?>
<div class="api-fallback">Using fallback database connection</div>
<?php endif; ?>

<table class="table table-striped" style="width:100%; margin:auto;font-size:14px;">
<thead>
<tr>
<th>Order ID</th>
<th>Updated on</th>
<th>View</th>
</tr>
</thead>
<tbody>

<?php
 $row_count = count($bookings);

if ($row_count > 0) {
    foreach ($bookings as $row) {
        $order_id = $row['order_id'];
        $gds_data_updated_on = $row['gds_data_updated_on'] ?? null;
        $is_gds_updated_got_updated = '';

        if ($gds_data_updated_on) {
            $formatted_date = date('d/m/Y H:i:s', strtotime($gds_data_updated_on));
            $is_gds_updated_got_updated = '<p class="blink_me">Updated on: ' . $formatted_date . '</p>';
        }
?>
<tr>
<td><?php echo htmlspecialchars($order_id); ?></td>
<td><?php echo $is_gds_updated_got_updated; ?></td>
<td><a href="/manage-wp-orders/?option=search&type=reference&id=<?php echo htmlspecialchars($order_id); ?>" target="_blank">View</a></td>
</tr>
<?php
    }
} else {
    echo '<tr><td colspan="3">No results found.</td></tr>';
}
?>

</tbody>
</table>
</div>

</div>
</body>
</html>

<?php get_footer(); ?>