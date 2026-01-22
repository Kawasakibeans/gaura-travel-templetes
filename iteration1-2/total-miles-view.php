<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection - no longer needed (now using API endpoints)
// All database queries have been replaced with API endpoint calls
// Old database connection code is commented out below
/*
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
global $wpdb;
*/
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
// Define API base URL if not already defined
$apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';
if (defined('API_BASE_URL')) {
    $apiBaseUrl = API_BASE_URL;
    // Remove trailing /v1 if present
    if (substr($apiBaseUrl, -3) === '/v1') {
        $apiBaseUrl = rtrim($apiBaseUrl, '/v1');
    }
}

// Fetch total miles per TSR + agent name from API endpoint
// API Endpoint: GET /v1/total-miles/totals
// Source: TotalMilesDAL::getTotals
// Query parameters: none
$totals = [];
$endpoint = '';
$http_code = 0;
$curl_error = '';
$api_response = '';

try {
    $api_url = rtrim($apiBaseUrl, '/');
    // Check if /v1 is already in the URL
    if (substr($api_url, -3) !== '/v1') {
        $endpoint = $api_url . '/v1/total-miles/totals';
    } else {
        $endpoint = $api_url . '/total-miles/totals';
    }
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        // error_log("Total Miles API CURL Error: " . $curl_error . " | URL: " . $endpoint);
    }
    
    if ($http_code !== 200) {
        // error_log("Total Miles API HTTP Error: Status " . $http_code . " | Response: " . substr($api_response, 0, 500) . " | URL: " . $endpoint);
    }
    
    if ($http_code === 200 && $api_response) {
        $apiData = json_decode($api_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // error_log("Total Miles API JSON Error: " . json_last_error_msg() . " | Response: " . substr($api_response, 0, 500));
        } else {
            // Debug: log the response structure
            error_log("Total Miles API Response: " . print_r($apiData, true));
            
            if (isset($apiData['status']) && $apiData['status'] === 'success' && isset($apiData['data'])) {
                // Check if data contains 'totals' array (nested structure)
                if (isset($apiData['data']['totals']) && is_array($apiData['data']['totals'])) {
                    $totals = $apiData['data']['totals'];
                } elseif (is_array($apiData['data']) && isset($apiData['data'][0])) {
                    // Data is direct array
                    $totals = $apiData['data'];
                } else {
                    $totals = [];
                }
            } elseif (is_array($apiData) && isset($apiData[0])) {
                // Response might be direct array
                $totals = $apiData;
            } else {
                // error_log("Total Miles API: Unexpected response format. Full response: " . print_r($apiData, true));
            }
        }
    } else {
        // error_log("Total Miles API: HTTP " . $http_code . " | Response: " . substr($api_response, 0, 500));
    }
    
    // Convert array to object format for compatibility with existing code
    if (!empty($totals) && is_array($totals)) {
        $totals = array_map(function($row) {
            // Ensure we have the right structure
            if (is_array($row)) {
                return (object) $row;
            }
            return $row;
        }, $totals);
    }
    
    // Debug: log final totals (commented out)
    /*
    if (empty($totals)) {
        error_log("Total Miles: Final totals array is empty. API URL: " . $endpoint);
    } else {
        error_log("Total Miles: Got " . count($totals) . " records");
    }
    */
    
} catch (Exception $e) {
    $totals = [];
    // error_log("API error fetching total miles: " . $e->getMessage());
}

// Fetch miles transactions from API endpoint (optional - can be used for detail views or AJAX)
// API Endpoint: GET /v1/total-miles/transactions
// Source: TotalMilesDAL::getTransactions
// Query parameters: optional tsr, optional agent_name
$all_data = [];
$data_by_tsr = [];

// Optional: Fetch transactions if needed (e.g., for detail view or AJAX requests)
// Uncomment and use filters as needed:
/*
try {
    $api_url = API_BASE_URL;
    $endpoint = $api_url . '/total-miles/transactions';
    
    $query_params = [];
    // Optional filters
    if (isset($_GET['tsr']) && !empty($_GET['tsr'])) {
        $query_params[] = 'tsr=' . urlencode($_GET['tsr']);
    }
    if (isset($_GET['agent_name']) && !empty($_GET['agent_name'])) {
        $query_params[] = 'agent_name=' . urlencode($_GET['agent_name']);
    }
    
    $apiUrl = $endpoint;
    if (!empty($query_params)) {
        $apiUrl .= '?' . implode('&', $query_params);
    }
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $api_response) {
        $apiData = json_decode($api_response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($apiData['status']) && $apiData['status'] === 'success' && isset($apiData['data'])) {
                $all_data = $apiData['data'];
            } elseif (is_array($apiData) && isset($apiData[0])) {
                // Response might be direct array
                $all_data = $apiData;
            }
        }
    }
    
    // Convert to object format and group by TSR
    if (!empty($all_data) && is_array($all_data)) {
        $all_data = array_map(function($row) {
            return (object) $row;
        }, $all_data);
        
        foreach ($all_data as $row) {
            $tsr = $row->tsr ?? '';
            if ($tsr) {
                $data_by_tsr[$tsr][] = $row;
            }
        }
    }
    
} catch (Exception $e) {
    error_log("API error fetching transactions: " . $e->getMessage());
}
*/

// OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
/*
// Fetch total miles per TSR + agent name
// SQL Query:
// SELECT m.tsr, ac.agent_name, SUM(m.points) AS total_miles
// FROM wpk4_backend_gaura_points m
// LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
// GROUP BY m.tsr, ac.agent_name
// ORDER BY total_miles DESC;
// Source: TotalMilesDAL::getTotals
// Method: GET
// Endpoint: /v1/total-miles/totals
// Query parameters: none
$totals = $wpdb->get_results("
    SELECT m.tsr, ac.agent_name, SUM(m.points) as total_miles
    FROM wpk4_backend_gaura_points m
    LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
    GROUP BY m.tsr
    ORDER BY total_miles DESC
");

// Fetch all transaction records grouped by TSR
// SQL Query:
// SELECT m.*, ac.agent_name
// FROM wpk4_backend_gaura_points m
// LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
// [WHERE m.tsr = ?]
// [  AND ac.agent_name LIKE ?]
// ORDER BY m.tsr, m.transaction_date DESC;
// Source: TotalMilesDAL::getTransactions
// Method: GET
// Endpoint: /v1/total-miles/transactions
// Query parameters: optional tsr, optional agent_name
$all_data = $wpdb->get_results("
    SELECT m.*, ac.agent_name
    FROM wpk4_backend_gaura_points m
    LEFT JOIN wpk4_backend_agent_codes ac ON m.tsr = ac.tsr
    ORDER BY m.tsr, m.transaction_date DESC
");
$data_by_tsr = [];
foreach ($all_data as $row) {
    $data_by_tsr[$row->tsr][] = $row;
}
*/
?>

<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 2rem;
        background-color: #f9fafb;
        color: #333;
    }

    h1 {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: #0073aa;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .summary-table thead {
        background-color: #f1f5f9;
    }

    .summary-table th, .summary-table td {
        padding: 1rem;
        text-align: left;
    }

    .summary-table th {
        font-weight: 600;
        color: #333;
    }

    .summary-table tbody tr:nth-child(even) {
        background-color: #f9fafc;
    }

    .summary-table tbody tr:hover {
        background-color: #e6f4ff;
    }

    .summary-table td {
        border-bottom: 1px solid #e5e7eb;
    }

    @media (max-width: 600px) {
        .summary-table thead {
            display: none;
        }

        .summary-table, .summary-table tbody, .summary-table tr, .summary-table td {
            display: block;
            width: 100%;
        }

        .summary-table tr {
            margin-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .summary-table td {
            padding: 0.75rem 1rem;
            text-align: right;
            position: relative;
        }

        .summary-table td::before {
            content: attr(data-label);
            position: absolute;
            left: 1rem;
            top: 0.75rem;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
        }
    }
</style>



<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".accordion-header").forEach(header => {
        header.addEventListener("click", () => {
            header.parentElement.classList.toggle("active");
        });
    });
});
</script>

<div class="wrap">
    <h1>Total Miles per Agent</h1>
    <?php 
    // Debug output (commented out)
    /*
    echo "<div style='background: #f0f0f0; padding: 15px; margin: 20px 0; border: 1px solid #ccc; font-family: monospace; font-size: 12px;'>";
    echo "<strong>Debug Info:</strong><br>";
    echo "API Endpoint: " . htmlspecialchars($endpoint ?? 'N/A') . "<br>";
    echo "HTTP Code: " . ($http_code ?? 'N/A') . "<br>";
    echo "CURL Error: " . htmlspecialchars($curl_error ?? 'None') . "<br>";
    echo "Response Length: " . (isset($api_response) ? strlen($api_response) : 0) . "<br>";
    echo "Totals Count: " . count($totals) . "<br>";
    if (!empty($totals)) {
        echo "First Record: <pre>" . print_r($totals[0], true) . "</pre><br>";
    }
    if (isset($api_response)) {
        echo "API Response (first 1000 chars): <pre>" . htmlspecialchars(substr($api_response, 0, 1000)) . "</pre><br>";
        $decoded = json_decode($api_response, true);
        if ($decoded) {
            echo "Decoded Response: <pre>" . print_r($decoded, true) . "</pre><br>";
        } else {
            echo "JSON Decode Error: " . json_last_error_msg() . "<br>";
        }
    }
    echo "</div>";
    */
    ?>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Agent Name</th>
                <th>Total Miles</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($totals)): ?>
                <tr>
                    <td colspan="2" style="text-align: center; padding: 2rem; color: #666;">No data available</td>
                </tr>
            <?php else: ?>
                <?php foreach ($totals as $row): ?>
                    <tr>
                        <td data-label="Agent Name"><?php echo esc_html($row->agent_name ?: 'N/A'); ?></td>
                        <td data-label="Total Miles"><?php echo number_format($row->total_miles ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>



