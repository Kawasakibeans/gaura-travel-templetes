<?php
/**
 * Template Name: After Sales Date Change Metrics agent wise
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

// Handle filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Validate date inputs (basic)
if (!strtotime($startDate)) $startDate = date('Y-m-01');
if (!strtotime($endDate)) $endDate = date('Y-m-t');

// Get last day of month for segment calculation
$lastDay = date('t', strtotime($startDate));

// Get all active agents for filter dropdown
$all_agents = [];
$agent_query = "SELECT DISTINCT agent_name
    FROM wpk4_backend_agent_codes
    WHERE location = 'BOM' and status = 'active'
    ORDER BY agent_name ASC";
$res = $mysqli->query($agent_query);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $all_agents[] = $row['agent_name'];
    }
}

// Function to get agent-wise metrics filtered by date range & agent
function getMetricsByAgent($mysqli, $start, $end, $agentName = '') {
    $start_safe = $mysqli->real_escape_string($start);
    $end_safe = $mysqli->real_escape_string($end);
    $where = "date BETWEEN '$start_safe' AND '$end_safe' AND agent_name <> 'ABDN'";
    if ($agentName !== '') {
        $safeAgent = $mysqli->real_escape_string($agentName);
        $where .= " AND agent_name = '$safeAgent'";
    }
    $query = "
        SELECT 
            agent_name,
            SUM(dc_request) AS dc_request,
            SUM(dc_case_success) AS dc_case_success,
            SUM(dc_case_fail) AS dc_case_fail,
            SUM(dc_case_pending) AS dc_case_pending,
            SUM(total_revenue) AS total_revenue
        FROM wpk4_agent_after_sale_productivity_report
        WHERE $where
        GROUP BY agent_name
        having SUM(dc_request) > 0
        ORDER BY agent_name ASC
    ";
    return $mysqli->query($query);
}

// Date segments for tables (with boundaries within filter)
$segments = [
    ['label' => '1 - 10', 'start' => date('Y-m-01', strtotime($startDate)), 'end' => date('Y-m-10', strtotime($startDate))],
    ['label' => '11 - 20', 'start' => date('Y-m-11', strtotime($startDate)), 'end' => date('Y-m-20', strtotime($startDate))],
    ['label' => '21 - ' . $lastDay, 'start' => date('Y-m-21', strtotime($startDate)), 'end' => date('Y-m-' . $lastDay, strtotime($startDate))],
    ['label' => 'Total', 'start' => $startDate, 'end' => $endDate],
];

// Adjust segments to not exceed user-selected filter range
foreach ($segments as &$segment) {
    // Clip segment start to >= $startDate
    if ($segment['start'] < $startDate) $segment['start'] = $startDate;
    // Clip segment end to <= $endDate
    if ($segment['end'] > $endDate) $segment['end'] = $endDate;
    // If after adjustment start > end, mark segment as empty (will show no data)
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
<title>After Sales Date Change Metrics</title>
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
    select#agent_name {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
        height: 40px;
        background: #fff;
    }
    select#agent_name:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2);
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
        color: white;
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
    }
    .filter-section {
        background-color: white;
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
    }
    label {
        font-weight: 500;
        margin-bottom: 5px;
        color: var(--text-light);
        font-size: 14px;
    }
    input[type="date"] {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    input[type="date"]:focus {
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
    .data-section {
        margin-bottom: 40px;
    }
    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: var(--text);
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary);
        display: inline-block;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
    .date-range {
        font-size: 14px;
        color: var(--text-light);
        margin-bottom: 20px;
        font-style: italic;
    }
    .currency {
        font-family: 'Courier New', monospace;
        font-weight: 600;
    }
    @media (max-width: 768px) {
        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }
        button {
            align-self: stretch;
        }
        table {
            display: block;
            overflow-x: auto;
        }
    }
    tr.clickable-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    tr.clickable-row:hover {
        background-color: #ffd966;
    }
    /* Modal styles */
    #agentDetailModal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0; top: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        overflow: auto;
    }
    #agentDetailModal .modal-content {
        background: white;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        max-width: 800px;
        position: relative;
    }
    #agentDetailModal .close-btn {
        position: absolute;
        top: 10px; right: 15px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    #agentDetailModal table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    #agentDetailModal th, #agentDetailModal td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: center;
    }
    #agentDetailModal th {
        background-color: #ffbb00;
        color: #000;
    }
    /* Highlight date cell in clickable rows with darker blue text */
    tr.clickable-row td:first-child {
        color: #003366;
        font-weight: 600;
        text-decoration: underline;
    }
</style>
</head>
<body>
<div class="container">
    <header>
        <h1>Date Change Metric Dashboard</h1>
        <div class="subtitle">Date change requests and revenue metrics</div>
    </header>

    <div class="filter-section">
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="filter-group">
                <label for="agent_name">Agent</label>
                <select id="agent_name" name="agent_name">
                    <option value="">All Agents</option>
                    <?php foreach ($all_agents as $agent): 
                        $selected = ($selected_agent === $agent) ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($agent) ?>" <?= $selected ?>><?= htmlspecialchars($agent) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Apply Filter</button>
        </form>
    </div>

    <div class="date-range">
        Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
    </div>

    <div class="data-section">
        <?php foreach ($segments as $segment): ?>
            <h2 class="section-title">Period: <?= htmlspecialchars($segment['label']) ?></h2>
            <?php if ($segment['empty']): ?>
                <div class="no-data">No data for this period</div>
            <?php else: 
                $result = getMetricsByAgent($mysqli, $segment['start'], $segment['end'], $selected_agent);
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>DC Requests</th>
                        <th>DC Success Cases</th>
                        <th>DC Fail Cases</th>
                        <th>DC Pending Cases</th>
                        <th>Success Rate</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        $total_dc_request = 0;
                        $total_dc_case_success = 0;
                        $total_dc_case_fail = 0;
                        $total_dc_case_pending = 0;
                        $total_revenue = 0;
                        while ($row = $result->fetch_assoc()):
                            $total_dc_request += (int)$row['dc_request'];
                            $total_dc_case_success += (int)$row['dc_case_success'];
                            $total_dc_case_fail += (int)$row['dc_case_fail'];
                            $total_dc_case_pending += (int)$row['dc_case_pending'];
                            $total_revenue += (float)$row['total_revenue'];
                            $success_rate = ($row['dc_request'] > 0) ? ($row['dc_case_success'] / $row['dc_request']) * 100 : 0;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['agent_name']) ?></td>
                            <td><?= (int)$row['dc_request'] ?></td>
                            <td><?= (int)$row['dc_case_success'] ?></td>
                            <td><?= (int)$row['dc_case_fail'] ?></td>
                            <td><?= (int)$row['dc_case_pending'] ?></td>
                            <td><?= number_format($success_rate, 2) ?>%</td>
                            <td class="currency">$<?= number_format($row['total_revenue'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <!-- Grand total -->
                    <?php 
                        $total_success_rate = ($total_dc_request > 0) ? ($total_dc_case_success / $total_dc_request) * 100 : 0;
                    ?>
                    <tr style="font-weight: bold; background-color: #eaeaea;">
                        <td>Grand Total</td>
                        <td><?= $total_dc_request ?></td>
                        <td><?= $total_dc_case_success ?></td>
                        <td><?= $total_dc_case_fail ?></td>
                        <td><?= $total_dc_case_pending ?></td>
                        <td><?= number_format($total_success_rate, 2) ?>%</td>
                        <td class="currency">$<?= number_format($total_revenue, 2) ?></td>
                    </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No data available for this period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
