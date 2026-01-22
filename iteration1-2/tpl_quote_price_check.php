<?php
/**
 * Template Name: Quote Price Check
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

// Load WordPress if not already loaded
if (!function_exists('get_header')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

get_header();
?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='quote_price_check' style='width:95%;margin:auto;padding:100px 0px;'>
<?php

// API Configuration
if (defined('API_BASE_URL')) {
    $base_url = API_BASE_URL;
} else {
    $base_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';
}

echo "<h2>Quote Price Check</h2>";

// Get days parameter from URL or use default
$days = isset($_GET['days']) ? (int)$_GET['days'] : 28;

// Call the endpoint to get recent quotes
$apiUrl = rtrim($base_url, '/') . '/quote-price-check/recent?days=' . $days;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);

$apiResponse = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle errors
if ($curlError) {
    echo "<div style='padding: 15px; background-color: #f8d7da; border: 1px solid #dc3545; color: #721c24; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>Error:</strong> cURL Error - " . htmlspecialchars($curlError);
    echo "</div>";
} elseif ($httpCode !== 200) {
    echo "<div style='padding: 15px; background-color: #f8d7da; border: 1px solid #dc3545; color: #721c24; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>Error:</strong> HTTP Status Code {$httpCode}";
    echo "<pre style='margin-top: 10px; background: #fff; padding: 10px; border-radius: 3px;'>" . htmlspecialchars(substr($apiResponse, 0, 500)) . "</pre>";
    echo "</div>";
} else {
    $responseData = json_decode($apiResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<div style='padding: 15px; background-color: #f8d7da; border: 1px solid #dc3545; color: #721c24; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>Error:</strong> JSON Decode Error - " . json_last_error_msg();
        echo "</div>";
    } elseif (isset($responseData['status']) && $responseData['status'] === 'success') {
        $data = $responseData['data'] ?? [];
        $quotes = $data['quotes'] ?? [];
        $totalCount = $data['total_count'] ?? count($quotes);
        $daysBack = $data['days_back'] ?? $days;
        
        // Debug: Show API response structure (can be removed later)
        if (isset($_GET['debug'])) {
            echo "<div style='padding: 15px; background-color: #e7f3ff; border: 1px solid #0066cc; color: #004085; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4>Debug Info:</h4>";
            echo "<p><strong>API URL:</strong> " . htmlspecialchars($apiUrl) . "</p>";
            echo "<p><strong>HTTP Code:</strong> {$httpCode}</p>";
            echo "<details style='margin-top: 10px;'>";
            echo "<summary style='cursor: pointer;'><strong>Raw API Response:</strong></summary>";
            echo "<pre style='background: #fff; padding: 10px; border-radius: 3px; max-height: 400px; overflow: auto;'>" . htmlspecialchars($apiResponse) . "</pre>";
            echo "</details>";
            echo "<details style='margin-top: 10px;'>";
            echo "<summary style='cursor: pointer;'><strong>Parsed Response Data:</strong></summary>";
            echo "<pre style='background: #fff; padding: 10px; border-radius: 3px; max-height: 400px; overflow: auto;'>" . htmlspecialchars(print_r($responseData, true)) . "</pre>";
            echo "</details>";
            echo "<p><strong>Quotes Array Count:</strong> " . count($quotes) . "</p>";
            echo "<p><strong>Data Keys:</strong> " . implode(', ', array_keys($data)) . "</p>";
            echo "</div>";
        }
        
        // Display summary
        echo "<div style='padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='margin-top: 0;'>Summary</h3>";
        echo "<p><strong>Total Quotes Found:</strong> {$totalCount}</p>";
        echo "<p><strong>Days Back:</strong> {$daysBack}</p>";
        echo "<p><small style='color: #666;'>Query Criteria: quoted_at >= NOW() - INTERVAL {$daysBack} DAY AND depart_date >= CURDATE() AND status = 0</small></p>";
        echo "<p><small><a href='?days={$days}&debug=1' style='color: #0066cc;'>Show Debug Info</a></small></p>";
        echo "</div>";
        
        // Display quotes table
        if (!empty($quotes)) {
            echo "<h3>Quotes</h3>";
            echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
            echo "<thead>";
            echo "<tr style='background-color: #f8f9fa;'>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>ID</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Route</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Depart Date</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Return Date</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Current Price</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Passengers</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Quoted At</th>";
            echo "<th style='border: 1px solid #dee2e6; padding: 12px; text-align: left;'>Status</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            
            foreach ($quotes as $quote) {
                $quoteId = $quote['id'] ?? $quote['quote_id'] ?? 'N/A';
                $departApt = htmlspecialchars($quote['depart_apt'] ?? 'N/A');
                $destApt = htmlspecialchars($quote['dest_apt'] ?? 'N/A');
                $departDate = htmlspecialchars($quote['depart_date'] ?? 'N/A');
                $returnDateValue = $quote['return_date'] ?? '';
                $returnDate = ($returnDateValue === '0000-00-00' || empty($returnDateValue)) ? 'N/A' : htmlspecialchars($returnDateValue);
                $currentPrice = isset($quote['current_price']) ? '₹' . number_format((float)$quote['current_price'], 2) : 'N/A';
                $adultCount = $quote['adult_count'] ?? 0;
                $childCount = $quote['child_count'] ?? 0;
                $infantCount = $quote['infant_count'] ?? 0;
                $totalPax = $quote['total_pax'] ?? ($adultCount + $childCount + $infantCount);
                $quotedAt = htmlspecialchars($quote['quoted_at'] ?? 'N/A');
                $status = $quote['status'] ?? 0;
                $statusText = $status == 0 ? '<span style="color: orange;">Pending</span>' : '<span style="color: green;">Checked</span>';
                
                echo "<tr>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>{$quoteId}</td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'><strong>{$departApt}</strong> → <strong>{$destApt}</strong></td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>{$departDate}</td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>{$returnDate}</td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'><strong>{$currentPrice}</strong></td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>";
                echo "Adults: {$adultCount}";
                if ($childCount > 0) echo ", Children: {$childCount}";
                if ($infantCount > 0) echo ", Infants: {$infantCount}";
                echo " <br><small>(Total: {$totalPax})</small>";
                echo "</td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>{$quotedAt}</td>";
                echo "<td style='border: 1px solid #dee2e6; padding: 12px;'>{$statusText}</td>";
                echo "</tr>";
            }
            
            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<div style='padding: 15px; background-color: #fff3cd; border: 1px solid #ffc107; color: #856404; border-radius: 5px; margin: 20px 0;'>";
            echo "<strong>No quotes found</strong> for the last {$daysBack} days.";
            echo "</div>";
        }
        
        // Display message if available
        if (isset($responseData['message'])) {
            echo "<p style='margin-top: 20px; color: #28a745;'><strong>{$responseData['message']}</strong></p>";
        }
                } else {
        echo "<div style='padding: 15px; background-color: #f8d7da; border: 1px solid #dc3545; color: #721c24; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>Error:</strong> " . ($responseData['message'] ?? 'Unknown error');
        if (isset($responseData['errors'])) {
            echo "<pre style='margin-top: 10px; background: #fff; padding: 10px; border-radius: 3px;'>" . htmlspecialchars(print_r($responseData['errors'], true)) . "</pre>";
        }
        echo "</div>";
    }
}

echo "<hr>";
echo "<p><strong>Data retrieved from API endpoint.</strong></p>";
?>
</div>
<?php get_footer(); ?>
