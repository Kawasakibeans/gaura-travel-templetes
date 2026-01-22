<?php
/**
 * Template Name: After Sales productivity report
 * Template Post Type: post, page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

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

// Get all agents
$all_agents = [];
$res = $mysqli->query("SELECT DISTINCT a.agent_name FROM wpk4_agent_after_sale_productivity_report a join wpk4_backend_agent_codes c on a.agent_name = c.agent_name
WHERE a.agent_name <> 'ABDN' and c.status = 'active' and c.location = 'BOM'
  ORDER BY a.agent_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $all_agents[] = $row['agent_name'];
}

// Fetch aggregated data by agent
function fetchAgentAggregatedData($mysqli, $start, $end, $agentName = '', $limit = null, $offset = 0) {
    $sql = "SELECT 
    a.agent_name,
    SUM(a.ssr) AS ssr,
    SUM(a.gdeal_ticketed) AS gdeals_ticket_issued,
    SUM(a.fit_ticketed) AS fit_tickets_issued,
    SUM(a.gdeal_audit) AS gdeals_audit,
    SUM(a.fit_audit) AS fit_audit,
    SUM(a.pre_departure) AS pre_departure_checklist,
    SUM(a.inb_call_count) AS inbound_calls,
    SUM(a.otb_call_count) AS outbound_calls,
    SUM(a.escalate) AS escalation_raised,
    ROUND(SUM(a.inb_call_count_duration) / NULLIF(SUM(a.inb_call_count), 0),2) AS inbound_calls_aht,
    ROUND(SUM(a.otb_call_count_duration) / NULLIF(SUM(a.otb_call_count), 0),2) AS outbound_calls_aht,
    SUM(a.dc_request) AS dc_handle,
    SUM(a.sc_case_handle) AS sc_handle
FROM wpk4_agent_after_sale_productivity_report a
left join wpk4_backend_agent_codes c on a.agent_name = c.agent_name
WHERE a.`date` BETWEEN ? AND ?
  AND a.agent_name <> 'ABDN' and c.status = 'active' and c.location = 'BOM'";
    $params = [$start, $end];
    $types = "ss";

    if ($agentName !== '') {
        $sql .= " AND a.agent_name = ?";
        $params[] = $agentName;
        $types .= "s";
    }

    $sql .= " GROUP BY a.agent_name ORDER BY a.agent_name ASC";

    if ($limit !== null) {
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
    }

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
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

    if ($data && $data->num_rows > 0) {
        $totals = [];
        $aht_counts = ['inbound_calls_aht' => 0, 'outbound_calls_aht' => 0]; // count for averaging

        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                // Cast numeric columns to float to avoid warnings
                if (in_array($key, [
                    'ssr', 'gdeals_ticket_issued', 'fit_tickets_issued', 'gdeals_audit',
                    'fit_audit', 'pre_departure_checklist', 'inbound_calls', 'outbound_calls',
                    'escalation_raised', 'inbound_calls_aht', 'outbound_calls_aht' ,'dc_handle', 'sc_handle' 
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
