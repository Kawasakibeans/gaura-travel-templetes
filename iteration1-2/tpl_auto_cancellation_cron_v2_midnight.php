<?php
date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
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

// Note: availability_pax_update_ajax function is kept but not used since cancellation logic is commented out
// If you need to update seat availability, you'll need to provide the corresponding API endpoint
function availability_pax_update_ajax($order_id, $by_user)
{
    // This function is not called in the original code (cancellation is commented out)
    // Keep it for future use if needed
    return [];
}

$current_date_and_time = date("Y-m-d H:i:s");

// Always get diagnostics to show helpful feedback
$show_diagnostics = isset($_GET['diagnostics']) && $_GET['diagnostics'] == '1';

// Call API to get bookings for full payment cancellation
// Always include diagnostics parameter to get helpful feedback when no bookings found
$apiUrl = $base_url . '/auto-cancellation/midnight-fullpayment?diagnostics=1';
$apiResult = callAPI($apiUrl, 'GET');

// Debug information
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

if ($debug_mode) {
    echo '<div style="padding: 10px; background: #f0f0f0; margin: 10px; border: 1px solid #ccc;">';
    echo '<strong>Debug Information:</strong><br>';
    echo 'API URL: ' . htmlspecialchars($apiUrl) . '<br>';
    echo 'API Response: <pre>' . htmlspecialchars(print_r($apiResult, true)) . '</pre>';
    
    // Show raw data structure
    if (isset($apiResult['data']['bookings']) && is_array($apiResult['data']['bookings'])) {
        echo '<br><strong>Raw bookings array structure:</strong><br>';
        echo 'Count: ' . count($apiResult['data']['bookings']) . '<br>';
        if (count($apiResult['data']['bookings']) > 0) {
            echo 'First booking keys: ' . implode(', ', array_keys($apiResult['data']['bookings'][0])) . '<br>';
            echo 'First booking: <pre>' . htmlspecialchars(print_r($apiResult['data']['bookings'][0], true)) . '</pre>';
        }
    }
    
    echo '</div>';
}

$rows = [];
if ($apiResult && !isset($apiResult['error'])) {
    // Check different possible response structures
    if (isset($apiResult['data']['bookings'])) {
        $rows = $apiResult['data']['bookings'];
    } elseif (isset($apiResult['data']) && is_array($apiResult['data'])) {
        // If data is directly an array
        $rows = $apiResult['data'];
    } elseif (isset($apiResult['bookings'])) {
        $rows = $apiResult['bookings'];
    }
} else {
    // API error
    $error_msg = isset($apiResult['error']) ? $apiResult['error'] : 'Unknown error';
    echo '<div style="padding: 10px; background: #ffebee; margin: 10px; border: 1px solid #f44336;">';
    echo '<strong>API Error:</strong> ' . htmlspecialchars($error_msg) . '<br>';
    if (isset($apiResult['response'])) {
        echo 'Response: <pre>' . htmlspecialchars(substr($apiResult['response'], 0, 500)) . '</pre>';
    }
    echo '</div>';
}

$row_counter = count($rows);
$processedOrders = array();	
$orderIDs = array();

if ($debug_mode) {
    echo '<div style="padding: 10px; background: #e8f5e9; margin: 10px; border: 1px solid #4caf50;">';
    echo '<strong>Data Summary:</strong><br>';
    echo 'Total rows from API: ' . $row_counter . '<br>';
    if ($row_counter > 0) {
        echo 'First row sample: <pre>' . htmlspecialchars(print_r($rows[0], true)) . '</pre>';
    }
    echo '</div>';
}

echo '</br></br>Cancellation for GDeals & FIT - Full amount based</br></br>';
echo '<table><tr><th>#</th><th>Order ID / PNR</th><th>Order Date</th><th>payment</th><th>Payment Status</th><th>New Payment Status</th></tr>';

foreach ($rows as $row) {
    $order_id = $row['order_id'];
    if (in_array($order_id, $processedOrders)) {
        continue; // Skip duplicate orders
    }
    $processedOrders[] = $order_id;
    
    // Total and paid amounts are already included in API response
    $total_to_be_paid = isset($row['total_amount']) ? number_format((float)$row['total_amount'], 2, '.', '') : '0.00';
    $get_paid_amount = isset($row['paid_amount']) ? number_format((float)$row['paid_amount'], 2, '.', '') : '0.00';
    
    if($total_to_be_paid > $get_paid_amount)
    {
        if(isset($row['source']) && $row['source'] != "import")
        {
            $current_email_date = date("Y-m-d H:i:s");
            $by_user = 'fullpayment_deadline_cancellation';
            
            /*
            // Cancellation logic is commented out in original file
            // If you need to cancel, you'll need to provide the cancel API endpoint
            */
            
            echo "<tr>
                <td><input type='checkbox' class='order-checkbox' checked value='$order_id'></td>
                <td><a href='/manage-wp-orders/?option=search&type=reference&id=".$row['order_id']."'>".$row['order_id']."</a>";
                    if (isset($row['source']) && $row['source'] == "import") {
                        echo " <span style='color: red;'>*</span>";
                    }
                    echo "</td>
                <td>".$row['order_date']."</td>
                <td>".$total_to_be_paid . " - " .$get_paid_amount."</td>
                <td>".$row['payment_status']."</td>
                <td>cancel</td>
            </tr>";
        }
    }
}

if ($row_counter == 0) {
    // Main message
    echo '<tr><td colspan="6" style="text-align: center; padding: 30px 20px; background: #fff3cd; border: 2px solid #ffc107;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #856404; margin-bottom: 10px;">';
    echo '⚠️ No bookings found for full payment cancellation';
    echo '</div>';
    echo '<div style="color: #856404; font-size: 14px;">';
    echo 'All orders that passed the filters have been fully paid.';
    echo '</div>';
    echo '</td></tr>';
    
    // Show diagnostic information if available (always show, not just when requested)
    if (isset($apiResult['data']['diagnostics'])) {
        $diagnostics = $apiResult['data']['diagnostics'];
        
        // Summary Card - Most Important Information
        if (isset($diagnostics['summary'])) {
            $summary = $diagnostics['summary'];
            echo '<tr><td colspan="6" style="padding: 0;">';
            echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; margin: 15px 0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            echo '<h2 style="margin: 0 0 20px 0; font-size: 20px; color: white;">📊 Summary</h2>';
            
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">';
            
            // Total passing filters
            echo '<div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 6px; backdrop-filter: blur(10px);">';
            echo '<div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">' . $summary['total_passing_filters'] . '</div>';
            echo '<div style="font-size: 13px; opacity: 0.9;">Orders passed all filters</div>';
            echo '</div>';
            
            // Eligible for cancellation
            $eligibleColor = $summary['eligible_for_cancellation'] > 0 ? '#4caf50' : '#ff9800';
            echo '<div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 6px; backdrop-filter: blur(10px); border: 2px solid ' . $eligibleColor . ';">';
            echo '<div style="font-size: 32px; font-weight: bold; margin-bottom: 5px; color: ' . $eligibleColor . ';">' . $summary['eligible_for_cancellation'] . '</div>';
            echo '<div style="font-size: 13px; opacity: 0.9;">Eligible for cancellation</div>';
            echo '</div>';
            
            // Fully paid but status not updated
            echo '<div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 6px; backdrop-filter: blur(10px);">';
            echo '<div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;">' . $summary['fully_paid_but_status_not_updated'] . '</div>';
            echo '<div style="font-size: 13px; opacity: 0.9;">Fully paid (status needs update)</div>';
            echo '</div>';
            
            echo '</div>';
            
            // Main message
            echo '<div style="background: rgba(255,255,255,0.25); padding: 15px; border-radius: 6px; border-left: 4px solid white;">';
            echo '<div style="font-size: 15px; font-weight: 500; margin-bottom: 5px;">💡 What this means:</div>';
            echo '<div style="font-size: 14px; line-height: 1.6;">' . htmlspecialchars($summary['message']) . '</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</td></tr>';
        }
        
        // Sample Orders Analysis - Visual Table
        if (isset($diagnostics['sample_bookings']) && count($diagnostics['sample_bookings']) > 0) {
            echo '<tr><td colspan="6" style="padding: 0;">';
            echo '<div style="background: white; padding: 25px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #ff9800;">';
            echo '<h3 style="margin: 0 0 20px 0; font-size: 18px; color: #333; display: flex; align-items: center;">';
            echo '<span style="margin-right: 10px;">🔍</span> Sample Orders Analysis';
            echo '</h3>';
            
            echo '<div style="overflow-x: auto;">';
            echo '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
            echo '<thead>';
            echo '<tr style="background: #f8f9fa;">';
            echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;">Order ID</th>';
            echo '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;">Total Amount</th>';
            echo '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;">Paid Amount</th>';
            echo '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;">Difference</th>';
            echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;">Status</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($diagnostics['sample_bookings'] as $index => $sample) {
                $isEligible = $sample['eligible_for_cancellation'];
                $rowBg = $isEligible ? '#d4edda' : '#f8d7da';
                $diffColor = (float)$sample['difference'] > 0 ? '#28a745' : ((float)$sample['difference'] < 0 ? '#dc3545' : '#6c757d');
                $statusIcon = $isEligible ? '✅' : '❌';
                
                echo '<tr style="background: ' . $rowBg . '; transition: background 0.2s;">';
                echo '<td style="padding: 12px; border-bottom: 1px solid #dee2e6;">';
                echo '<a href="/manage-wp-orders/?option=search&type=reference&id=' . $sample['order_id'] . '" style="color: #007bff; text-decoration: none; font-weight: 500;">';
                echo '#' . $sample['order_id'];
                echo '</a>';
                echo '</td>';
                echo '<td style="padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6; font-weight: 500;">$' . number_format((float)$sample['total_amount'], 2) . '</td>';
                echo '<td style="padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6; font-weight: 500;">$' . number_format((float)$sample['paid_amount'], 2) . '</td>';
                echo '<td style="padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6; font-weight: 600; color: ' . $diffColor . ';">';
                echo '$' . number_format((float)$sample['difference'], 2);
                echo '</td>';
                echo '<td style="padding: 12px; border-bottom: 1px solid #dee2e6;">';
                echo '<span style="margin-right: 5px;">' . $statusIcon . '</span>';
                echo '<span style="font-size: 13px;">' . htmlspecialchars($sample['reason']) . '</span>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            
            echo '<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-radius: 6px; font-size: 13px; color: #856404;">';
            echo '<strong>Note:</strong> Green rows indicate orders eligible for cancellation. Red rows indicate fully paid orders that may need status update.';
            echo '</div>';
            
            echo '</div>';
            echo '</td></tr>';
        }
        
        // Filter Statistics - Progress Bar Style
        if (isset($diagnostics['after_order_type_filter'])) {
            echo '<tr><td colspan="6" style="padding: 0;">';
            echo '<div style="background: white; padding: 25px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #4caf50;">';
            echo '<h3 style="margin: 0 0 20px 0; font-size: 18px; color: #333; display: flex; align-items: center;">';
            echo '<span style="margin-right: 10px;">📈</span> Filter Statistics';
            echo '</h3>';
            
            $steps = [
                ['label' => 'Partially Paid Orders', 'count' => $diagnostics['partially_paid'] ?? 0, 'icon' => '💰'],
                ['label' => 'After Payment Status Filter', 'count' => $diagnostics['after_sub_payment_status_filter'] ?? 0, 'icon' => '🔍'],
                ['label' => 'After Deadline Filter', 'count' => $diagnostics['after_deadline_filter'] ?? 0, 'icon' => '⏰'],
                ['label' => 'After Source Filter', 'count' => $diagnostics['after_source_filter'] ?? 0, 'icon' => '📦'],
                ['label' => 'After Order Type Filter', 'count' => $diagnostics['after_order_type_filter'] ?? 0, 'icon' => '📋'],
                ['label' => 'Eligible for Cancellation', 'count' => $diagnostics['eligible_bookings_count'] ?? 0, 'icon' => '✅', 'highlight' => true],
            ];
            
            $maxCount = max(array_column($steps, 'count'));
            
            foreach ($steps as $step) {
                $percentage = $maxCount > 0 ? ($step['count'] / $maxCount * 100) : 0;
                $isHighlight = isset($step['highlight']) && $step['highlight'];
                $barColor = $isHighlight ? ($step['count'] > 0 ? '#28a745' : '#dc3545') : '#6c757d';
                
                echo '<div style="margin-bottom: 15px;">';
                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
                echo '<div style="font-weight: 500; color: #333;">';
                echo '<span style="margin-right: 8px;">' . $step['icon'] . '</span>';
                echo $step['label'] . ':';
                echo '</div>';
                echo '<div style="font-weight: 600; color: ' . ($isHighlight ? $barColor : '#333') . '; font-size: 16px;">' . $step['count'] . '</div>';
                echo '</div>';
                echo '<div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">';
                echo '<div style="background: ' . $barColor . '; height: 100%; width: ' . $percentage . '%; transition: width 0.3s ease;"></div>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</td></tr>';
        }
    } else {
        // No diagnostics available
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">';
        echo '<div style="margin-bottom: 10px;">No diagnostic information available.</div>';
        if (!$debug_mode) {
            echo '<div style="font-size: 13px;">';
            echo 'Add <code>?diagnostics=1</code> to the URL to see detailed diagnostic information, or <code>?debug=1</code> to see API response details.';
            echo '</div>';
        }
        echo '</td></tr>';
    }
}

echo '</table>';

        
?>