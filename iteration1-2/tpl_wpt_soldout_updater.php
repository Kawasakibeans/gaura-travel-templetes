<?php
/**
 * Template Name: WPT Soldout Checker
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

// Load the WordPress environment to access constants and functions
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// API Configuration
// Using a constant defined in wp-config.php is best practice
if (!defined('API_BASE_URL')) {
    // Fallback if the constant is not defined
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';
} else {
    $apiBaseUrl = API_BASE_URL;
}

/**
 * Calls the API to update the soldout status of trips.
 *
 * @param array $excludedPostIds Array of post IDs to exclude from processing.
 * @return bool True on success, false on failure.
 */
function updateSoldoutStatusFromAPI(array $excludedPostIds = [60107, 60116]): array {
    global $apiBaseUrl;
    
    // Ensure endpoint doesn't have duplicate /v1
    $baseUrl = rtrim($apiBaseUrl, '/');
    if (substr($baseUrl, -3) === '/v1') {
        $endpoint = $baseUrl . '/trips/soldout/update';
    } else {
        $endpoint = $baseUrl . '/v1/trips/soldout/update';
    }

    $requestBody = json_encode(['excluded_post_ids' => $excludedPostIds]);

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($requestBody)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30-second timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // As per previous examples

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            error_log("Soldout Update API Error: " . $curlError);
            return ['success' => false, 'error' => 'cURL Error: ' . $curlError];
        }

        if ($httpCode !== 200 && $httpCode !== 204) { // 204 No Content is also a success
            $errorMsg = "HTTP Error: Status code " . $httpCode . ", Response: " . $response;
            error_log("Soldout Update API " . $errorMsg);
            return ['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode, 'response' => $response];
        }

        // Parse response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Soldout Update API JSON Error: " . json_last_error_msg());
            return ['success' => false, 'error' => 'JSON decode error: ' . json_last_error_msg()];
        }

        return ['success' => true, 'data' => $data];

    } catch (Exception $e) {
        error_log("Soldout Update API Exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

get_header(); ?>

<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings'
    style='width:95%;margin:auto;padding:100px 0px;'>
    <?php
    error_reporting(E_ALL);
    date_default_timezone_set("Australia/Melbourne");

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    
    // Note: The original IP check logic is preserved.
    // Consider using WordPress nonces for better security if possible in the future.
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
    
    // Use WordPress $wpdb for safer DB queries if needed, but since this is legacy code, we'll leave it.
    // However, direct DB access here is for the IP check only, not the main logic.
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_error) die('DB connection error for IP check: ' . $mysqli->connect_error);
    
    $result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
    $row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
    $is_ip_matched = mysqli_num_rows($result_ip_selection);
    
    if ($row_ip_selection['ip_address'] == $ip_address) {
        if (current_user_can('administrator') || current_user_can('ho_operations')) {
            
            // --- NEW API-BASED LOGIC ---
            $defaultExcludedIds = [60107, 60116];
            $result = updateSoldoutStatusFromAPI($defaultExcludedIds);

            if ($result['success']) {
                $data = $result['data'] ?? [];
                $message = $data['message'] ?? 'The soldout status has been updated based on current booking data.';
                $scenario1 = $data['scenario1'] ?? [];
                $scenario2 = $data['scenario2'] ?? [];
                
                echo "<center><h2>Success</h2>";
                echo "<p>" . htmlspecialchars($message) . "</p>";
                if (!empty($scenario1) || !empty($scenario2)) {
                    echo "<p><strong>Details:</strong></p>";
                    echo "<ul style='text-align:left; display:inline-block;'>";
                    if (!empty($scenario1)) {
                        echo "<li>Sold out trips processed: " . ($scenario1['processed'] ?? 0) . ", Added to exclude: " . ($scenario1['added_to_exclude'] ?? 0) . "</li>";
                    }
                    if (!empty($scenario2)) {
                        echo "<li>Available trips processed: " . ($scenario2['processed'] ?? 0) . ", Removed from exclude: " . ($scenario2['removed_from_exclude'] ?? 0) . "</li>";
                    }
                    echo "</ul>";
                }
                echo "</center>";
            } else {
                $error = $result['error'] ?? 'Unknown error';
                echo "<center><h2>Error</h2>";
                echo "<p>Failed to update the soldout status.</p>";
                echo "<p style='color:red;'><strong>Error:</strong> " . htmlspecialchars($error) . "</p>";
                if (isset($result['http_code'])) {
                    echo "<p><strong>HTTP Code:</strong> " . htmlspecialchars($result['http_code']) . "</p>";
                }
                if (isset($result['response'])) {
                    echo "<p><strong>Response:</strong> <pre style='text-align:left; display:inline-block;'>" . htmlspecialchars(substr($result['response'], 0, 500)) . "</pre></p>";
                }
                echo "<p>Please check the error logs or try again later.</p></center>";
            }

        } else {
            echo "<center>This page is not accessible for you.</center>";
        }
    } else {
        echo "<center>This page is not accessible for you.</center>";
    }
    ?>
</div>

<?php get_footer(); ?>