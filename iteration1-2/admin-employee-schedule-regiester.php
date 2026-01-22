<?php
/**
 * Template Name: Admin Lock/Unlock View
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

// Load WordPress configuration to get API_BASE_URL
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );

// Define API base URL if not already defined (fallback)
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1');
}

$base_url = API_BASE_URL; // Use global constant

// ✅ FIX: Removed PDO database connection - now using API endpoints for all operations
// OLD DATABASE CONNECTION - COMMENTED OUT (now using API endpoints)
/*
// Database configuration
$host = 'localhost';
$db = 'gt1ybwhome_gt1';
$user = 'gt1ybwhome';
$pass = 'gDf3ghfxb6c';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die("DB connection error: " . $e->getMessage());
}

// Table name
$availability_table = 'wpk4_backend_employee_schedule';
*/

// Fetch lock status from API endpoint
// API Endpoint: GET /v1/employee-schedule/lock-status
// Source: EmployeeScheduleDAL::getLockStatus
// Query parameters: none
// Response payload: { "is_locked": 0/1 } or { "status": "success", "data": { "is_locked": 0/1 } }
function getEmployeeScheduleLockStatus() {
    global $base_url;
    $apiUrl = $base_url . '/employee-schedule/lock-status';
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("Employee Schedule Lock Status API Error: " . $curlError);
            return 0; // Default to unlocked on error
        }
        
        if ($httpCode !== 200) {
            error_log("Employee Schedule Lock Status API HTTP Error: Status code " . $httpCode);
            return 0; // Default to unlocked on error
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Employee Schedule Lock Status API JSON Error: " . json_last_error_msg());
            return 0; // Default to unlocked on error
        }
        
        // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
        /*
        // Query: Get Lock Status
        // SELECT is_locked FROM `$availability_table` LIMIT 1
        // Source: EmployeeScheduleDAL::getLockStatus
        // Method: GET
        // Endpoint: /v1/employee-schedule/lock-status
        // Query parameters: none
        */
        
        // Handle different response formats
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']['is_locked'])) {
            return (int)$data['data']['is_locked'];
        } elseif (isset($data['is_locked'])) {
            return (int)$data['is_locked'];
        } elseif (isset($data['lock_status'])) {
            return (int)$data['lock_status'];
        } elseif (is_numeric($data)) {
            return (int)$data;
        }
        
        return 0; // Default to unlocked if format is unexpected
    } catch (Exception $e) {
        error_log("Employee Schedule Lock Status API Exception: " . $e->getMessage());
        return 0; // Default to unlocked on exception
    }
}

// ✅ FIX: Helper function to call API endpoints
if (!function_exists('callApiEndpoint')) {
    function callApiEndpoint($endpoint, $method = 'GET', $data = null) {
        global $base_url;
        
        // Ensure endpoint starts with /
        if (strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        
        $url = $base_url . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        
        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ]);
            }
        }
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API call error for $endpoint: " . $curlError);
            return ['success' => false, 'error' => $curlError];
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API call failed for $endpoint: HTTP $httpCode | Response: " . substr($response, 0, 500));
            return ['success' => false, 'error' => "HTTP $httpCode", 'response' => $response];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for $endpoint: " . json_last_error_msg());
            return ['success' => false, 'error' => 'JSON decode error'];
        }
        
        return $data;
    }
}

// Initialize message variable
$msg = '';

// Admin Check
if (current_user_can('administrator')) {
    // Handle lock/unlock action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_status']) && isset($_POST['lock_nonce']) && wp_verify_nonce($_POST['lock_nonce'], 'lock_unlock_action')) {
        $lock_status = $_POST['lock_status']; // 0 for unlock, 1 for lock
        
        try {
            // ✅ FIX: Update lock status using API endpoint
            // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
            /*
            // Query: Update Lock Status
            // UPDATE `$availability_table` SET is_locked = ? WHERE 1
            // Method: POST
            // Endpoint: /v1/employee-schedule/lock
            // Body parameters: { "is_locked": 0/1 }
            */
            
            $apiPayload = [
                'is_locked' => (int)$lock_status
            ];
            
            $result = callApiEndpoint('/employee-schedule/lock', 'POST', $apiPayload);
            
            // Check result structure
            if (isset($result['status']) && $result['status'] === 'success') {
                $msg = $lock_status ? ['type' => 'success', 'text' => 'Availability Register Locked!'] : 
                                     ['type' => 'success', 'text' => 'Availability Register Unlocked!'];
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                error_log("API update lock status failed: " . $errorMsg);
                $msg = ['type' => 'danger', 'text' => 'Error updating lock status: ' . $errorMsg];
            }
        } catch (Exception $e) {
            error_log("Exception updating lock status: " . $e->getMessage());
            $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
} else {
    // If not an admin, redirect to home or show an error
    wp_redirect(home_url());
    exit;
}

get_header(); // load WordPress header
?>

<!-- Admin Lock/Unlock Page Content -->
<div class="container mt-5 pt-4">
    <?php if (!empty($msg)): ?>
        <div class="floating-notification">
            <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show shadow" role="alert">
                <?php if ($msg['type'] === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($msg['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="text-center mb-4">Admin Lock/Unlock Availability Register</h2>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php
            // Get the current lock status from API
            $lock_status = getEmployeeScheduleLockStatus();
            $lock_text = $lock_status ? 'Unlock' : 'Lock';
            ?>
            
            <!-- Lock/Unlock Form for Admin -->
            <form method="post" class="text-center">
                <?php wp_nonce_field('lock_unlock_action', 'lock_nonce'); ?>
                <input type="hidden" name="lock_status" value="<?= $lock_status ? '0' : '1' ?>">
                <button type="submit" class="btn btn-warning btn-lg"><?= $lock_text ?> Availability</button>
            </form>

            <p class="text-center mt-3">
                <a href="<?= home_url(); ?>" class="btn btn-info">Back to Dashboard</a>
            </p>
        </div>
    </div>
</div>

<?php get_footer(); // load WordPress footer ?>
