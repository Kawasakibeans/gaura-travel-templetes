<?php
/**
 * Template Name: After Sales productivity report date wise
 * Template Post Type: post, page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

// Helper function to build query string (replacement for WordPress add_query_arg)
function buildQueryString($args) {
    $current = $_GET;
    foreach ($args as $key => $value) {
        $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

// Helper function to make API requests
function makeApiRequest($endpoint, $params = []) {
    // Check if API_BASE_URL is defined as constant or global variable
    $baseUrl = '';
    if (defined('API_BASE_URL')) {
        $baseUrl = constant('API_BASE_URL');
    } else {
        global $API_BASE_URL;
        $baseUrl = $API_BASE_URL ?? '';
    }
    
    if (empty($baseUrl)) {
        error_log("API_BASE_URL is not defined");
        return null;
    }
    
    // Build query string if params exist
    $queryString = !empty($params) ? '?' . http_build_query($params) : '';
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/') . $queryString;
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("API Request Error: " . $error);
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("API Request Failed with HTTP Code: " . $httpCode);
        return null;
    }
    
    $data = json_decode($response, true);
    return $data;
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

// Get all agents from API
$all_agents = [];
$agentParams = [
    'location' => 'BOM',
    'status' => 'active'
];
$agentResponse = makeApiRequest('/after-sale-productivity-agent-names', $agentParams);
if ($agentResponse && isset($agentResponse['data'])) {
    // API returns array of agent names
    $all_agents = is_array($agentResponse['data']) ? $agentResponse['data'] : [];
}

// Fetch aggregated data from API
function fetchAgentAggregatedData($start, $end, $agentName = '', $limit = null, $offset = 0) {
    $params = [
        'start_date' => $start,
        'end_date' => $end,
        'location' => 'BOM',
        'status' => 'active'
    ];
    
    if (!empty($agentName)) {
        $params['agent_name'] = $agentName;
    }
    
    if ($limit !== null) {
        $params['limit'] = (int)$limit;
        $params['offset'] = (int)$offset;
    }
    
    $response = makeApiRequest('/after-sale-productivity-report', $params);
    
    if ($response && isset($response['data']) && is_array($response['data'])) {
        return $response['data'];
    }
    
    return [];
}

// Fetch all data first to calculate total pages (needed for pagination)
// Note: This could be optimized if API returns total count
$allData = fetchAgentAggregatedData($startDate, $endDate, $selected_agent);
$total_records = count($allData);
$total_pages = max(1, ceil($total_records / $rows_per_page));

// Fetch data for current page only
$apiData = fetchAgentAggregatedData($startDate, $endDate, $selected_agent, $rows_per_page, $offset);

$data =ConvertDataToOldSqlFormat($apiData);

// Convert API Data To Old SQL Format
function ConvertDataToOldSqlFormat(array $data): array
{
    $allowedKeys = [
        'date',
        'ssr',
        'gdeals_ticket_issued',
        'fit_tickets_issued',
        'gdeals_audit',
        'fit_audit',
        'pre_departure_checklist',
        'inbound_calls',
        'outbound_calls',
        'escalation_raised',
        'inbound_calls_aht',
        'outbound_calls_aht',
        'dc_handle',
        'sc_handle',
    ];

    $result = [];

    foreach ($data as $index => $row) {
        foreach ($allowedKeys as $key) {
            $result[$index][$key] = $row[$key] ?? null;
        }
    }

    return $result;
}
    
// Render table function
function renderAgentTable($data) {
    echo "<table>
            <thead>
                <tr>
                    <th>date</th>
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

    if ($data && is_array($data) && count($data) > 0) {
        $totals = [];
        $aht_counts = ['inbound_calls_aht' => 0, 'outbound_calls_aht' => 0]; // count for averaging

        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                // Cast numeric columns to float to avoid warnings
                if (in_array($key, [
                    'ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
                    'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
                    'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht','dc_handle', 'sc_handle' 
                ])) {
                    $numValue = (float)$value;

                    // Convert AHT seconds to HH:MM:SS for display
                    if (in_array($key, ['inbound_calls_aht', 'outbound_calls_aht'])) {
                        echo "<td>" . gmdate("H:i:s", round($numValue)) . "</td>";
                        $totals[$key] = ($totals[$key] ?? 0) + $numValue;
                        $aht_counts[$key]++; // increment count for averaging
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

        // Grand total / average row
        echo "<tr style='font-weight:bold; background-color:#eaeaea;'><td>Grand Total</td>";
        foreach ($totals as $key => $val) {
            if (in_array($key, ['inbound_calls_aht', 'outbound_calls_aht'])) {
                // Calculate average for AHT
                $avg = $aht_counts[$key] > 0 ? $val / $aht_counts[$key] : 0;
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
        echo "<a href='" . htmlspecialchars(buildQueryString($query_args)) . "'>&laquo; Prev</a>";
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        $query_args['paged'] = $i;
        $url = htmlspecialchars(buildQueryString($query_args));
        $active = ($i === $page) ? 'class="active"' : '';
        echo "<a href='$url' $active>$i</a>";
    }
    if ($page < $total_pages) {
        $query_args['paged'] = $page + 1;
        echo "<a href='" . htmlspecialchars(buildQueryString($query_args)) . "'>Next &raquo;</a>";
    }
    ?>
</div>

</body>
</html>
