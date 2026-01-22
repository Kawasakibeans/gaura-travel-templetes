<?php
/** 
 * Template Name: After Sales Ticket Audit Metrics agent wise
 * Template Post Type: post, page
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
$api_url = API_BASE_URL;

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Helper function to fetch agent names from API
function fetchAgentNamesFromAPI($location = 'BOM', $status = 'active') {
    global $api_url;
    $apiEndpoint = '/agent-codes-agent-names';
    
    // Build query parameters
    $params = [
        'location' => $location,
        'status' => $status
    ];
    
    $apiUrl = rtrim($api_url, '/') . $apiEndpoint . '?' . http_build_query($params);
    
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
            error_log("Agent Names API HTTP Error: Status code " . $httpCode);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Agent Names API JSON Error: " . json_last_error_msg());
            return [];
        }
        
        // Extract agent names from response
        $agentNames = [];
        if (isset($data['agents']) && is_array($data['agents'])) {
            foreach ($data['agents'] as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        } elseif (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        } elseif (is_array($data)) {
            foreach ($data as $agent) {
                if (is_string($agent)) {
                    $agentNames[] = $agent;
                } elseif (is_array($agent) && isset($agent['agent_name'])) {
                    $agentNames[] = $agent['agent_name'];
                }
            }
        }
        
        // Sort agent names
        sort($agentNames);
        
        return array_values($agentNames);
    } catch (Exception $e) {
        error_log("Agent Names API Exception: " . $e->getMessage());
        return [];
    }
}

// Fetch agent names from API
$all_agents = fetchAgentNamesFromAPI('BOM', 'active');

// Helper: fetch aggregated data by agent for given range & agent filter
function fetchAgentAggregatedData($mysqli, $from, $to, $agentName = '') {
    $sql = "
        SELECT a.agent_name, 
            SUM(a.fit_audit) AS fit_audit,
            SUM(a.gdeal_audit) AS gdeal_audit,
            SUM(a.ticket_audited) AS ticket_audited
        FROM wpk4_agent_after_sale_productivity_report a
        join wpk4_backend_agent_codes b on a.agent_name = b.agent_name
        WHERE DATE(a.date) BETWEEN ? AND ? AND a.agent_name <> 'ABDN' and b.status = 'active'
    ";
    $params = [$from, $to];
    $types = "ss";

    if ($agentName !== '') {
        $sql .= " AND a.agent_name = ?";
        $params[] = $agentName;
        $types .= "s";
    }
    $sql .= " GROUP BY a.agent_name having SUM(a.ticket_audited) > 0 ORDER BY a.agent_name ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Date segments for tables (with boundaries within filter)
$endDate = new DateTime($to);
$lastDay = $endDate->format('d');
$segments = [
    ['label' => '1 - 10', 'start' => date('Y-m-01', strtotime($from)), 'end' => date('Y-m-10', strtotime($from))],
    ['label' => '11 - 20', 'start' => date('Y-m-11', strtotime($from)), 'end' => date('Y-m-20', strtotime($from))],
    ['label' => '21 - ' . $lastDay, 'start' => date('Y-m-21', strtotime($from)), 'end' => date('Y-m-' . $lastDay, strtotime($from))],
    ['label' => 'Total', 'start' => $from, 'end' => $to],
];

// Adjust segments to not exceed user-selected filter range
foreach ($segments as &$segment) {
    if ($segment['start'] < $from) $segment['start'] = $from;
    if ($segment['end'] > $to) $segment['end'] = $to;
    if ($segment['start'] > $segment['end']) {
        $segment['empty'] = true;
    } else {
        $segment['empty'] = false;
    }
}
unset($segment);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>After Sales Ticket Audit Metrics</title>
<style>
    :root {
        --primary: #ffbb00;
        --primary-dark: #e6a800;
        --primary-light: #fff3cc;
        --primary-very-light: #fffdf5;
        --text: #333333;
        --text-light: #666666;
        --border: #e0e0e0;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0; padding: 0;
        background-color: #f9f9f9;
        color: var(--text);
        line-height: 1.6;
    }
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #000;
        padding: 25px 0;
        margin-bottom: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-align: center;
    }
    h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
    }
    .subtitle {
        font-size: 16px;
        opacity: 0.9;
        margin-top: 8px;
        color: #222;
    }
    .filter-section {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 15px;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 140px;
    }
    label {
        font-weight: 500;
        margin-bottom: 5px;
        color: var(--text-light);
        font-size: 14px;
    }
    input[type="date"],
    select#agent_name {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 14px;
        background: #fff;
        transition: border-color 0.3s;
        height: 40px;
    }
    input[type="date"]:focus,
    select#agent_name:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2);
    }
    button {
        background-color: var(--primary);
        color: #000;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        align-self: flex-end;
    }
    button:hover {
        background-color: var(--primary-dark);
        transform: translateY(-1px);
    }
    .date-range {
        font-size: 14px;
        color: var(--text-light);
        margin-bottom: 20px;
        font-style: italic;
    }
    .table-container {
        margin-bottom: 40px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        padding: 15px 20px 30px 20px;
    }
    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 15px;
        border-bottom: 2px solid var(--primary);
        padding-bottom: 10px;
        display: inline-block;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    th, td {
        padding: 12px 15px;
        text-align: center;
        border: 1px solid var(--border);
    }
    th {
        background-color: var(--primary);
        color: #000;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 13px;
    }
    tr:nth-child(even) {
        background-color: var(--primary-very-light);
    }
    tr:hover {
        background-color: var(--primary-light);
    }
    .no-data {
        text-align: center;
        padding: 20px;
        color: var(--text-light);
        font-style: italic;
    }
</style>
</head>
<body>
<div class="container">
    <header>
        <h1>After Sales Ticket Audit Metrics</h1>
        <div class="subtitle">Audit performance metrics grouped by agent</div>
    </header>

    <div class="filter-section">
        <form method="GET" class="filter-form" id="filter-form">
            <div class="filter-group">
                <label for="from">From Date</label>
                <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>" required>
            </div>
            <div class="filter-group">
                <label for="to">To Date</label>
                <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>" required>
            </div>
            <div class="filter-group">
                <label for="agent_name">Agent</label>
                <select id="agent_name" name="agent_name">
                    <option value="">All Agents</option>
                    <?php
                    foreach ($all_agents as $agent) {
                        $selected = ($selected_agent === $agent) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($agent) . "\" $selected>" . htmlspecialchars($agent) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Apply Filter</button>
        </form>
    </div>

    <div class="date-range">
        Showing data from <?= date('F j, Y', strtotime($from)) ?> to <?= date('F j, Y', strtotime($to)) ?>
    </div>

    <div class="data-section">
        <?php
        foreach ($segments as $segment) {
            echo "<div class='table-container'>";
            echo "<h2 class='section-title'>Period: " . htmlspecialchars($segment['label']) . "</h2>";
            if ($segment['empty']) {
                echo "<div class='no-data'>No data for this period</div>";
            } else {
                $result = fetchAgentAggregatedData($mysqli, $segment['start'], $segment['end'], $selected_agent);
                echo "<table class='summary-table'>";
                echo "<thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>FIT Audit</th>
                            <th>GDeal Audit</th>
                            <th>Total Ticket Audited</th>
                        </tr>
                      </thead><tbody>";
                $total_fit = 0;
                $total_gdeal = 0;
                $total_audit = 0;
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['agent_name']) . "</td>
                                <td>{$row['fit_audit']}</td>
                                <td>{$row['gdeal_audit']}</td>
                                <td>{$row['ticket_audited']}</td>
                              </tr>";
                        $total_fit += $row['fit_audit'];
                        $total_gdeal += $row['gdeal_audit'];
                        $total_audit += $row['ticket_audited'];
                    }
                    echo "<tr style='font-weight:bold; background-color:#eaeaea;'>
                            <td>Grand Total</td>
                            <td>{$total_fit}</td>
                            <td>{$total_gdeal}</td>
                            <td>{$total_audit}</td>
                          </tr>";
                } else {
                    echo "<tr><td colspan='4' class='no-data'>No data available for this period</td></tr>";
                }
                echo "</tbody></table>";
            }
            echo "</div>";
        }
        $mysqli->close();
        ?>
    </div>
</div>
</body>
</html>
