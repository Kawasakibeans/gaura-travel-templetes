<?php
/**
 * Template Name: After Sales productivity report
 * Template Post Type: post, page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// require_once(dirname(__FILE__, 5) . '/wp-config.php');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    echo "Database connection failed";
    exit;
}

// Filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Pagination
$rows_per_page = 100;
$page = isset($_GET['paged']) && is_numeric($_GET['paged']) && $_GET['paged'] > 0 ? (int)$_GET['paged'] : 1;
$offset = ($page - 1) * $rows_per_page;

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-t');

// Helper function to fetch agent names from API
function fetchAgentNamesFromAPI($location = 'BOM', $status = 'Active') {
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';
    $apiEndpoint = '/v1/after-sale-productivity-agent-names';
    
    // Build query parameters
    $params = [
        'location' => $location,
        'status' => $status
    ];
    
    $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("Agent Names API Error: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("Agent Names API HTTP Error: Status code " . $httpCode . " Response: " . substr($response, 0, 500));
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Agent Names API JSON Error: " . json_last_error_msg() . " Response: " . substr($response, 0, 500));
            return [];
        }
        
        // Extract agent names from response
        $agentNames = [];
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        } elseif (isset($data['agents']) && is_array($data['agents'])) {
            foreach ($data['agents'] as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        } elseif (is_array($data)) {
            // Direct array of agent names or objects
            foreach ($data as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        }
        
        // Filter out 'ABDN' and sort
        $agentNames = array_filter($agentNames, function($name) {
            return $name !== 'ABDN' && !empty($name);
        });
        sort($agentNames);
        
        return array_values($agentNames);
    } catch (Exception $e) {
        error_log("Agent Names API Exception: " . $e->getMessage());
        return [];
    }
}

// Get all agents from API
$all_agents = fetchAgentNamesFromAPI('BOM', 'Active');

// // Debug: Log agent names for troubleshooting
// error_log("All agents count: " . count($all_agents));
// if (!empty($all_agents)) {
//     error_log("First few agents: " . implode(', ', array_slice($all_agents, 0, 5)));
// } else {
//     error_log("Warning: No agents fetched from API");
// }

// Helper function to fetch productivity report data from API (agent-wise)
function fetchProductivityReportFromAPI($start_date, $end_date, $agent_name = '', $location = 'BOM', $status = 'active', $limit = null, $offset = 0) {
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';
    $apiEndpoint = '/v1/after-sale-productivity-report-by-agent';
    
    // Build query parameters
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'location' => $location,
        'status' => $status
    ];
    
    if ($agent_name !== '') {
        $params['agent_name'] = $agent_name;
    }

    if ($limit !== null) {
        $params['limit'] = (int)$limit;
        $params['offset'] = (int)$offset;
    }
    
    $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("Productivity Report API Error: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("Productivity Report API HTTP Error: Status code " . $httpCode);
            error_log("API URL: " . $apiUrl);
            error_log("Response: " . substr($response, 0, 500));
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Productivity Report API JSON Error: " . json_last_error_msg() . " Response: " . substr($response, 0, 1000));
            return [];
        }
        
        // Debug: Log API response for troubleshooting
        error_log("Productivity Report API URL: " . $apiUrl);
        error_log("API Response structure: " . json_encode([
            'has_data_key' => isset($data['data']),
            'has_results_key' => isset($data['results']),
            'is_array' => is_array($data),
            'top_level_keys' => is_array($data) ? array_keys($data) : [],
            'response_sample' => substr(json_encode($data), 0, 500)
        ]));
        
        if (isset($data['data']) && is_array($data['data'])) {
            error_log("Productivity Report API - Data count: " . count($data['data']));
            if (!empty($data['data'])) {
                error_log("Productivity Report API - First row keys: " . implode(', ', array_keys($data['data'][0])));
                error_log("Productivity Report API - First row: " . json_encode($data['data'][0]));
            }
            return $data['data'];
        } elseif (isset($data['results']) && is_array($data['results'])) {
            error_log("Productivity Report API - Results count: " . count($data['results']));
            return $data['results'];
        } elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
            // Direct array of rows
            error_log("Productivity Report API - Direct array count: " . count($data));
            error_log("Productivity Report API - First row keys: " . implode(', ', array_keys($data[0])));
            return $data;
        }
        
        error_log("Productivity Report API - No valid data structure found. Response keys: " . json_encode(array_keys($data ?? [])));
        return [];
    } catch (Exception $e) {
        error_log("Productivity Report API Exception: " . $e->getMessage());
        return [];
    }
}

// Fetch ticket issued data from database (API may not return this) - grouped by agent
function fetchTicketIssuedDataFromDB($mysqli, $start, $end, $agentName = '') {

    
    $sql = "
        SELECT 
            agent_name,
            SUM(gdeal_ticketed) AS gdeals_ticket_issued,
            SUM(fit_ticketed) AS fit_tickets_issued
        FROM wpk4_agent_after_sale_productivity_report
        WHERE `date` BETWEEN ? AND ?
          AND agent_name <> 'ABDN'
    ";
    
    $params = [$start, $end];
    $types = "ss";
    
    if ($agentName !== '') {
        $sql .= " AND agent_name = ?";
        $params[] = $agentName;
        $types .= "s";
    }
    
    $sql .= " GROUP BY agent_name ORDER BY agent_name ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Ticket Issued Data SQL Error: " . $mysqli->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[$row['agent_name']] = $row;
    }
    
    return $data;
}

// Helper function to normalize row data (convert API column names to display column names)
function normalizeRowData($row) {
    $normalized = ['agent_name' => $row['agent_name'] ?? ''];
    
    // Map API columns to display columns based on actual database structure
    // SSR
    $normalized['ssr'] = (float)($row['ssr'] ?? 0);
    
    // GDeals Ticket Issued - column 30 (gdeal_ticketed)
    $normalized['gdeals_ticket_issued'] = (float)($row['gdeals_ticket_issued'] ?? $row['gdeal_ticketed'] ?? 0);
    
    // FIT Tickets Issued - column 29 (fit_ticketed)
    $normalized['fit_tickets_issued'] = (float)($row['fit_tickets_issued'] ?? $row['fit_ticketed'] ?? 0);
    
    // GDeals Audit - column 34 (gdeal_audit)
    $normalized['gdeals_audit'] = (float)($row['gdeals_audit'] ?? $row['gdeal_audit'] ?? $row['gtcs'] ?? 0);
    
    // FIT Audit - column 33 (fit_audit)
    $normalized['fit_audit'] = (float)($row['fit_audit'] ?? $row['gtpy'] ?? 0);
    
    // Pre Departure Checklist - column 47 (pre_departure)
    $normalized['pre_departure_checklist'] = (float)($row['pre_departure_checklist'] ?? $row['pre_departure'] ?? $row['gtet'] ?? 0);
    
    // Inbound Calls - column 19 (gtdc) - GTDC type inbound calls
    $normalized['inbound_calls'] = (float)($row['inbound_calls'] ?? $row['gtdc'] ?? 0);
    
    // Outbound Calls - column 22 (gtrf) - GTRF type outbound calls
    $normalized['outbound_calls'] = (float)($row['outbound_calls'] ?? $row['gtrf'] ?? 0);
    
    // Escalation Raised - column 48 (escalate) or column 49 (gtib)
    $normalized['escalation_raised'] = (float)($row['escalation_raised'] ?? $row['escalate'] ?? $row['gtib'] ?? 0);
    
    // DC case handle - column 53 (dc_case_handle) or column 23 (dc_request)
    $normalized['dc_handle'] = (float)($row['dc_handle'] ?? $row['dc_case_handle'] ?? $row['dc_request'] ?? $row['gtmd'] ?? 0);
    
    // SC case handle - column 54 (sc_case_handle)
    $normalized['sc_handle'] = (float)($row['sc_handle'] ?? $row['sc_case_handle'] ?? 0);
    
    // // Calculate AHT from duration and call count
    // $inbDurationStr = $row['inb_call_count_duration'] ?? 0;
    // $inbDuration = 0;
    // if (is_string($inbDurationStr) && strpos($inbDurationStr, ':') !== false) {
    //     // Convert "HH:MM:SS" to seconds
    //     $parts = explode(':', $inbDurationStr);
    //     if (count($parts) === 3) {
    //         $inbDuration = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
    //     }
    // } else {
    //     $inbDuration = (float)$inbDurationStr;
    // }
    
    // $inbCalls = (float)($row['inb_call_count'] ?? 0);
    // $normalized['inbound_calls_aht'] = $inbCalls > 0 ? ($inbDuration / $inbCalls) : 0;
    
    // $otbDurationStr = $row['otb_call_count_duration'] ?? 0;
    // $otbDuration = 0;
    // if (is_string($otbDurationStr) && strpos($otbDurationStr, ':') !== false) {
    //     // Convert "HH:MM:SS" to seconds
    //     $parts = explode(':', $otbDurationStr);
    //     if (count($parts) === 3) {
    //         $otbDuration = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
    //     }
    // } else {
    //     $otbDuration = (float)$otbDurationStr;
    // }
    
    // $otbCalls = (float)($row['otb_call_count'] ?? 0);
    // $normalized['outbound_calls_aht'] = $otbCalls > 0 ? ($otbDuration / $otbCalls) : 0;
    
    // return $normalized;
    
    // -------------------------------------------------------
    // AHT: prefer API-calculated values if present.
    // Only calculate from raw duration/count as a fallback.
    // -------------------------------------------------------

    // Inbound AHT
    if (isset($row['inbound_calls_aht']) && $row['inbound_calls_aht'] !== '' && $row['inbound_calls_aht'] !== null) {
        // API usually returns seconds (or numeric). Keep as seconds.
        $normalized['inbound_calls_aht'] = (float)$row['inbound_calls_aht'];
    } else {
        $inbDurationStr = $row['inb_call_count_duration'] ?? 0;
        $inbDuration = 0;

        if (is_string($inbDurationStr) && strpos($inbDurationStr, ':') !== false) {
            $parts = explode(':', $inbDurationStr);
            if (count($parts) === 3) {
                $inbDuration = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
            }
        } else {
            $inbDuration = (float)$inbDurationStr;
        }

        // Fallback call count (try both possible keys)
        $inbCalls = (float)($row['inb_call_count'] ?? $row['inbound_calls'] ?? 0);
        $normalized['inbound_calls_aht'] = $inbCalls > 0 ? ($inbDuration / $inbCalls) : 0;
    }

    // Outbound AHT
    if (isset($row['outbound_calls_aht']) && $row['outbound_calls_aht'] !== '' && $row['outbound_calls_aht'] !== null) {
        $normalized['outbound_calls_aht'] = (float)$row['outbound_calls_aht'];
    } else {
        $otbDurationStr = $row['otb_call_count_duration'] ?? 0;
        $otbDuration = 0;

        if (is_string($otbDurationStr) && strpos($otbDurationStr, ':') !== false) {
            $parts = explode(':', $otbDurationStr);
            if (count($parts) === 3) {
                $otbDuration = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
            }
        } else {
            $otbDuration = (float)$otbDurationStr;
        }

        $otbCalls = (float)($row['otb_call_count'] ?? $row['outbound_calls'] ?? 0);
        $normalized['outbound_calls_aht'] = $otbCalls > 0 ? ($otbDuration / $otbCalls) : 0;
    }
    
    return $normalized;
}

// Fallback function to fetch data directly from database if API fails
function fetchAgentDataDirectFromDB($mysqli, $start, $end, $agentName = '', $limit = null, $offset = 0) {
    $sql = "
        SELECT 
            agent_name,
            COALESCE(SUM(ssr), 0) AS ssr,
            COALESCE(SUM(gdeal_ticketed), 0) AS gdeals_ticket_issued,
            COALESCE(SUM(fit_ticketed), 0) AS fit_tickets_issued,
            COALESCE(SUM(gdeal_audit), 0) AS gdeals_audit,
            COALESCE(SUM(fit_audit), 0) AS fit_audit,
            COALESCE(SUM(pre_departure), 0) AS pre_departure_checklist,
            COALESCE(SUM(inb_call_count), 0) AS inbound_calls,
            COALESCE(SUM(otb_call_count), 0) AS outbound_calls,
            COALESCE(SUM(escalate), 0) AS escalation_raised,
            COALESCE(ROUND(SUM(inb_call_count_duration) / NULLIF(SUM(inb_call_count), 0), 2), 0) AS inbound_calls_aht,
            COALESCE(ROUND(SUM(otb_call_count_duration) / NULLIF(SUM(otb_call_count), 0), 2), 0) AS outbound_calls_aht,
            COALESCE(SUM(dc_request), 0) AS dc_handle,
            COALESCE(SUM(sc_case_handle), 0) AS sc_handle
        FROM wpk4_agent_after_sale_productivity_report
        WHERE date BETWEEN ? AND ?
          AND agent_name <> 'ABDN'
    ";
    
    $params = [$start, $end];
    $types = "ss";
    
    if ($agentName !== '') {
        $sql .= " AND agent_name = ?";
        $params[] = $agentName;
        $types .= "s";
    }
    
    $sql .= " GROUP BY agent_name ORDER BY agent_name ASC";
    
    if ($limit !== null) {
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        $sql .= " LIMIT {$offset}, {$limit}";
    }
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Direct DB Query Error: " . $mysqli->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Number of rows in result ". $result->num_rows);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = normalizeRowData($row);
    }
    
    error_log("Direct DB query returned " . count($data) . " rows");
    
    return $data;
}

// Fetch aggregated data by agent (now using API)
function fetchAgentAggregatedData($mysqli, $start, $end, $agentName = '', $limit = null, $offset = 0) {
    // Fetch data from API instead of SQL
    $apiData = fetchProductivityReportFromAPI($start, $end, $agentName, 'BOM', 'active', $limit, $offset);
    
    // If API returns empty, try direct database query as fallback
    if (empty($apiData)) {
        error_log("API returned empty, trying direct database query");
        return fetchAgentDataDirectFromDB($mysqli, $start, $end, $agentName, $limit, $offset);
    }
    
    // Fetch ticket issued data from database (API may not return this)
    $ticketData = fetchTicketIssuedDataFromDB($mysqli, $start, $end, $agentName);
    
    // Normalize the data and merge ticket issued data
    $normalizedData = [];
    
    error_log("API Data received count: " . count($apiData));
    if (!empty($apiData)) {
        error_log("First API row sample: " . json_encode($apiData[0]));
    }
    
    foreach ($apiData as $row) {
        $agentNameFromRow = $row['agent_name'] ?? '';
        
        // Add ticket issued data if available
        if (!empty($agentNameFromRow) && isset($ticketData[$agentNameFromRow])) {
            $row['gdeals_ticket_issued'] = $ticketData[$agentNameFromRow]['gdeals_ticket_issued'] ?? 0;
            $row['fit_tickets_issued'] = $ticketData[$agentNameFromRow]['fit_tickets_issued'] ?? 0;
        } else {
            $row['gdeals_ticket_issued'] = $row['gdeals_ticket_issued'] ?? $row['gdeal_ticketed'] ?? 0;
            $row['fit_tickets_issued'] = $row['fit_tickets_issued'] ?? $row['fit_ticketed'] ?? 0;
        }
        
        $normalized = normalizeRowData($row);
        // Always add the row if agent_name exists
        if (!empty($normalized['agent_name'])) {
            $normalizedData[] = $normalized;
        }
    }
    
    error_log("Normalized data count: " . count($normalizedData));
    
    return $normalizedData;
}

// Count total agents (for pagination)
$count_sql = "SELECT COUNT(DISTINCT agent_name) AS total_agents 
              FROM wpk4_agent_after_sale_productivity_report 
              WHERE date BETWEEN ? AND ?";
$count_params = [$startDate, $endDate];
$count_types = "ss";
if ($selected_agent !== '') {
    $count_sql .= " AND agent_name = ?";
    $count_params[] = $selected_agent;
    $count_types .= "s";
}
$count_stmt = $mysqli->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_agents = $count_res['total_agents'];
$total_pages = ceil($total_agents / $rows_per_page);

// Fetch data for current page
$data = fetchAgentAggregatedData($mysqli, $startDate, $endDate, $selected_agent, $rows_per_page, $offset);

// Debug: Log what we received
error_log("Final data received count: " . (is_array($data) ? count($data) : 0));
if (is_array($data) && !empty($data)) {
    error_log("First row keys: " . implode(', ', array_keys($data[0])));
    error_log("First row: " . json_encode($data[0]));
} else {
    error_log("WARNING: No data received! API may have failed or database has no data for date range.");
}

// TEMPORARY DEBUG OUTPUT - Add ?debug=1 to URL to see debug info
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<div style='background:#fff3cd; padding:20px; margin:20px; border:2px solid #ffc107; font-family:monospace; font-size:12px; max-height:500px; overflow:auto;'>";
    echo "<h2>DEBUG INFORMATION</h2>";
    
    echo "<h3>API URL:</h3>";
    $apiUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1/after-sale-productivity-report-by-agent?' . http_build_query([
        'start_date' => $startDate,
        'end_date' => $endDate,
        'location' => 'BOM',
        'status' => 'active',
        'limit' => $rows_per_page,
        'offset' => $offset
    ]);
    echo "<p><a href='" . htmlspecialchars($apiUrl) . "' target='_blank'>" . htmlspecialchars($apiUrl) . "</a></p>";
    
    echo "<h3>Raw API Response:</h3>";
    $testData = fetchProductivityReportFromAPI($startDate, $endDate, $selected_agent, 'BOM', 'active', $rows_per_page, $offset);
    echo "<pre>" . htmlspecialchars(print_r($testData, true)) . "</pre>";
    
    echo "<h3>Final Data Count:</h3>";
    echo "<p>" . count($data) . " rows</p>";
    
    echo "<h3>Final Data Sample (first 3 rows):</h3>";
    if (!empty($data)) {
        echo "<pre>" . htmlspecialchars(print_r(array_slice($data, 0, 3), true)) . "</pre>";
    } else {
        echo "<p>No data - trying direct DB query...</p>";
        $dbData = fetchAgentDataDirectFromDB($mysqli, $startDate, $endDate, $selected_agent, $rows_per_page, $offset);
        echo "<p>Direct DB query returned: " . count($dbData) . " rows</p>";
        if (!empty($dbData)) {
            echo "<pre>" . htmlspecialchars(print_r(array_slice($dbData, 0, 3), true)) . "</pre>";
        }
    }
    
    echo "</div>";
    exit; // Stop execution to show only debug info
} else {
    error_log("No data received from fetchAgentAggregatedData");
}

// // Debug: Log data count for troubleshooting
// error_log("Fetched data count: " . (is_array($data) ? count($data) : 0));
// if (is_array($data) && !empty($data)) {
//     error_log("First row keys: " . implode(', ', array_keys($data[0])));
// }

    
// Render table function
function renderAgentTable($data) {
    echo "<table>
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th>SSR</th>
                    <th>GDeals Ticket Issued</th>
                    <th>FIT Tickets Issued</th>
                    <th>GDeals Audit</th>
                    <th>FIT Audit</th>
                    <th>Pre Departure Checklist</th>
                    <th>Inbound Calls</th>
                    <th>Outbound Calls</th>
                    <th>Escalation Raised</th>
                    <th>Inbound Calls AHT</th>
                    <th>Outbound Calls AHT</th>
                    <th>DC case handle</th>
                    <th>SC case handle</th>
                </tr>
            </thead>
            <tbody>";

    // Handle both array (from API) and mysqli result object
    $isArray = is_array($data);
    $hasData = $isArray ? !empty($data) : ($data && $data->num_rows > 0);
    
    if ($hasData) {
        $totals = [];
        $aht_counts = ['inbound_calls_aht' => 0, 'outbound_calls_aht' => 0]; // count for averaging
        
        // Define column order - agent_name first instead of date
        $columnOrder = [
            'agent_name', 'ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
            'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
            'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht', 'dc_handle', 'sc_handle'
        ];

        // Iterate through data
        if ($isArray) {
            foreach ($data as $row) {
                echo "<tr>";
                // Display columns in the correct order
                foreach ($columnOrder as $key) {
                    $value = $row[$key] ?? '';
                    
                    // Handle agent_name column
                    if ($key === 'agent_name') {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    // Cast numeric columns to float to avoid warnings
                    elseif (in_array($key, [
                        'ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
                        'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
                        'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht', 'dc_handle', 'sc_handle'
                    ])) {
                        $numValue = (float)$value;

                        // Convert AHT seconds to HH:MM:SS for display
                        if (in_array($key, ['inbound_calls_aht', 'outbound_calls_aht'])) {
                            echo "<td>" . gmdate("H:i:s", round($numValue)) . "</td>";
                            $totals[$key] = ($totals[$key] ?? 0) + $numValue;
                            $aht_counts[$key] = ($aht_counts[$key] ?? 0) + 1; // increment count for averaging
                        } else {
                            echo "<td>$numValue</td>";
                            $totals[$key] = ($totals[$key] ?? 0) + $numValue;
                        }
                    } else {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                }
                echo "</tr>";
            }
        } else {
            // Original mysqli result handling
            while ($row = $data->fetch_assoc()) {
                echo "<tr>";
                // Display columns in the correct order
                foreach ($columnOrder as $key) {
                    $value = $row[$key] ?? '';
                    
                    // Handle agent_name column
                    if ($key === 'agent_name') {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    // Cast numeric columns to float to avoid warnings
                    elseif (in_array($key, [
                        'ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
                        'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
                        'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht', 'dc_handle', 'sc_handle'
                    ])) {
                        $numValue = (float)$value;

                        // Convert AHT seconds to HH:MM:SS for display
                        if (in_array($key, ['inbound_calls_aht', 'outbound_calls_aht'])) {
                            echo "<td>" . gmdate("H:i:s", round($numValue)) . "</td>";
                            $totals[$key] = ($totals[$key] ?? 0) + $numValue;
                            $aht_counts[$key] = ($aht_counts[$key] ?? 0) + 1; // increment count for averaging
                        } else {
                            echo "<td>$numValue</td>";
                            $totals[$key] = ($totals[$key] ?? 0) + $numValue;
                        }
                    } else {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                }
                echo "</tr>";
            }
        }

        // Grand total / average row
        echo "<tr style='font-weight:bold; background-color:#eaeaea;'><td>Grand Total</td>";
        // Display totals in the correct column order (skip 'agent_name')
        foreach (['ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
                 'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
                 'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht', 'dc_handle', 'sc_handle'] as $key) {
            $val = $totals[$key] ?? 0;
            if (in_array($key, ['inbound_calls_aht', 'outbound_calls_aht'])) {
                // Calculate average for AHT
                $avg = isset($aht_counts[$key]) && $aht_counts[$key] > 0 ? $val / $aht_counts[$key] : 0;
                $val = gmdate("H:i:s", round($avg));
            }
            echo "<td>$val</td>";
        }
        echo "</tr>";
    } else {
        echo "<tr><td colspan='14' style='text-align:center; padding:15px;'>No data available</td></tr>";
    }

    echo "</tbody></table>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>After Sales Report Agent Wise</title>
    <style>
        body { font-family:sans-serif; margin:20px; background:#f9f9f9; color:#333; }
        table { width:100%; border-collapse: collapse; margin-bottom:30px; background:#fff; }
        th, td { border:1px solid #ddd; padding:8px; text-align:center; font-size:13px; }
        th { background:#ffbb00; font-weight:600; }
        tr:nth-child(even){background:#fef9e6;}
        tr:hover{background:#fff3cc;}
        h1{margin-bottom:20px;}
        .filter-form { margin-bottom:20px; display:flex; gap:15px; flex-wrap:wrap; align-items:flex-end; }
        .filter-form label { display:block; font-size:13px; margin-bottom:4px; }
        .filter-form input, .filter-form select { padding:6px 8px; font-size:13px; border-radius:4px; border:1px solid #ccc; }
        .filter-form button { padding:8px 14px; background:#ffbb00; border:none; cursor:pointer; font-weight:600; }
        .filter-form button:hover { background:#e6a800; }
        .pagination { margin-top:10px; }
        .pagination a { margin:0 5px; text-decoration:none; color:#004080; font-weight:600; }
        .pagination a.active { font-weight:bold; color:#e6a800; }
    </style>
</head>
<body>

<h1>After Sales Report Agent Wise</h1>

<form method="get" class="filter-form">
    <div>
        <label for="start_date">Start Date</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
    </div>
    <div>
        <label for="end_date">End Date</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
    </div>
    <div>
        <label for="agent_name">Agent</label>
        <select name="agent_name">
            <option value="">All Agents</option>
            <?php foreach($all_agents as $agent): 
                $sel = ($agent === $selected_agent) ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($agent) ?>" <?= $sel ?>><?= htmlspecialchars($agent) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><button type="submit">Apply Filter</button></div>
</form>

<p>Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?></p>

<?php renderAgentTable($data); ?>

<div class="pagination">
    <?php
    $query_args = $_GET;
    if ($page > 1) {
        $query_args['paged'] = $page - 1;
        echo "<a href='" . htmlspecialchars(add_query_arg($query_args)) . "'>&laquo; Prev</a>";
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        $query_args['paged'] = $i;
        $url = htmlspecialchars(add_query_arg($query_args));
        $active = ($i === $page) ? 'class=\"active\"' : '';
        echo "<a href='$url' $active>$i</a>";
    }
    if ($page < $total_pages) {
        $query_args['paged'] = $page + 1;
        echo "<a href='" . htmlspecialchars(add_query_arg($query_args)) . "'>Next &raquo;</a>";
    }
    ?>
</div>

</body>
</html>
