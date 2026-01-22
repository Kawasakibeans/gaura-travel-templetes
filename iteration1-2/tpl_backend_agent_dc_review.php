<?php
/**
 * Template Name: After Sales Date Change Metrics
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
$endDate   = $_GET['end_date']   ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Basic YYYY-MM-DD validation
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
if (!$valid($startDate) || !$valid($endDate)) {
    http_response_code(400);
    exit('Invalid date format');
}

// Ensure start <= end
if (strtotime($startDate) > strtotime($endDate)) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

// Get all active agents for filter dropdown
$all_agents = [];
$agent_query = "
    SELECT DISTINCT agent_name
    FROM wpk4_backend_agent_codes
    WHERE location = 'BOM' AND status = 'active'
    ORDER BY agent_name ASC
";
$res = $mysqli->query($agent_query);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $all_agents[] = $row['agent_name'];
    }
}

// Function to get date grouped metrics
function getMetricsByDate($mysqli, $start, $end, $agentName = '') {
    $sql = "
        SELECT 
            DATE(`date`) AS day,
            SUM(dc_request)       AS dc_request,
            SUM(dc_case_success)  AS dc_case_success,
            SUM(dc_case_fail)     AS dc_case_fail,
            SUM(dc_case_pending)  AS dc_case_pending,
            SUM(total_revenue)    AS total_revenue
        FROM wpk4_agent_after_sale_productivity_report
        WHERE `date` >= ? 
          AND `date` < DATE_ADD(?, INTERVAL 1 DAY)
          AND agent_name <> 'ABDN'
    ";

    $types = "ss";
    $params = [$start, $end];

    if ($agentName !== '') {
        $sql .= " AND agent_name = ?";
        $types .= "s";
        $params[] = $agentName;
    }

    $sql .= " GROUP BY day ORDER BY day ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get agent-wise details for a specific date
function getAgentDetailsByDate($mysqli, $date, $agentName = '') {
    $sql = "
        SELECT 
            agent_name,
            dc_request,
            dc_case_success,
            dc_case_fail,
            dc_case_pending,
            total_revenue
        FROM wpk4_agent_after_sale_productivity_report
        WHERE DATE(`date`) = ? and dc_request > 0
          AND agent_name <> 'ABDN'
    ";

    $types = "s";
    $params = [$date];

    if ($agentName !== '') {
        $sql .= " AND agent_name = ?";
        $types .= "s";
        $params[] = $agentName;
    }

    $sql .= " ORDER BY agent_name ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Build date segments
$startY = date('Y', strtotime($startDate));
$startM = date('m', strtotime($startDate));
$endY   = date('Y', strtotime($endDate));
$endM   = date('m', strtotime($endDate));
$sameMonth = ($startY.$startM) === ($endY.$endM);

$segments = [
    ['label' => 'Total', 'start' => $startDate, 'end' => $endDate],
];

if ($sameMonth) {
    $month   = date('Y-m', strtotime($startDate));
    $lastDay = date('t', strtotime($startDate));

    // Clamp slices to selection
    $s1s = max($startDate, "$month-01"); $s1e = min($endDate, "$month-10");
    $s2s = max($startDate, "$month-11"); $s2e = min($endDate, "$month-20");
    $s3s = max($startDate, "$month-21"); $s3e = min($endDate, "$month-$lastDay");

    $segments = [
        ['label' => '1 - 10',            'start' => $s1s, 'end' => $s1e],
        ['label' => '11 - 20',           'start' => $s2s, 'end' => $s2e],
        ['label' => "21 - $lastDay",     'start' => $s3s, 'end' => $s3e],
        ['label' => 'Total',             'start' => $startDate, 'end' => $endDate],
    ];
}

// Handle AJAX request for agent details popup
if (isset($_GET['action']) && $_GET['action'] === 'get_agent_details' && isset($_GET['date'])) {
    header('Content-Type: application/json');
    $date = $_GET['date'];
    $agent_filter = $_GET['agent_name'] ?? '';
    $result = getAgentDetailsByDate($mysqli, $date, $agent_filter);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
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
            height: 40px; /* or remove for auto-height based on padding */
            background: #fff;
        }
        select#agent_name:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2);
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
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
    color: #003366;  /* Darker blue shade */
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
        <?php foreach ($segments as $segment):
            $result = getMetricsByDate($mysqli, $segment['start'], $segment['end'], $selected_agent);
        ?>
            <h2 class="section-title">Period: <?= htmlspecialchars($segment['label']) ?></h2>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
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
                        <tr class="clickable-row" data-date="<?= htmlspecialchars($row['day']) ?>">
                            <td><?= htmlspecialchars($row['day']) ?></td>
                            <td><?= (int)$row['dc_request'] ?></td>
                            <td><?= (int)$row['dc_case_success'] ?></td>
                            <td><?= (int)$row['dc_case_fail'] ?></td>
                            <td><?= (int)$row['dc_case_pending'] ?></td>
                            <td><?= number_format($success_rate, 2) ?>%</td>
                            <td class="currency">$<?= number_format($row['total_revenue'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
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
        <?php endforeach; ?>
    </div>
</div>

<!-- Agent details modal -->
<div id="agentDetailModal">
    <div class="modal-content">
        <span class="close-btn" id="modalCloseBtn">&times;</span>
        <h2>Agent Details for <span id="modalDate"></span></h2>
        <div id="modalAgentNameFilterContainer" style="margin-bottom: 10px;">
            <label for="modal_agent_name">Filter Agent:</label>
            <select id="modal_agent_name">
                <option value="">All Agents</option>
                <?php foreach ($all_agents as $agent): ?>
                <option value="<?= htmlspecialchars($agent) ?>"><?= htmlspecialchars($agent) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <table id="agentDetailsTable">
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th>DC Requests</th>
                    <th>DC Success Cases</th>
                    <th>DC Fail Cases</th>
                    <th>DC Pending Cases</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="modalNoData" style="text-align:center; font-style: italic; display:none;">No data available</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('agentDetailModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalDateSpan = document.getElementById('modalDate');
    const modalAgentNameSelect = document.getElementById('modal_agent_name');
    const modalTableBody = document.querySelector('#agentDetailsTable tbody');
    const modalNoData = document.getElementById('modalNoData');
    let currentDate = null;

    document.querySelectorAll('tr.clickable-row').forEach(row => {
        row.addEventListener('click', () => {
            currentDate = row.getAttribute('data-date');
            modalDateSpan.textContent = currentDate;
            const mainAgentFilter = document.getElementById('agent_name').value;
            modalAgentNameSelect.value = mainAgentFilter;
            fetchAgentDetails(currentDate, modalAgentNameSelect.value);
            modal.style.display = 'block';
        });
    });

    modalCloseBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
    modalAgentNameSelect.addEventListener('change', () => {
        if (!currentDate) return;
        fetchAgentDetails(currentDate, modalAgentNameSelect.value);
    });

    function fetchAgentDetails(date, agentName) {
        modalTableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
        modalNoData.style.display = 'none';
        const params = new URLSearchParams({
            action: 'get_agent_details',
            date: date,
            agent_name: agentName
        });
        fetch(`?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    modalTableBody.innerHTML = '';
                    data.data.forEach(row => {
                        modalTableBody.innerHTML += `
                            <tr>
                                <td>${escapeHtml(row.agent_name)}</td>
                                <td>${parseInt(row.dc_request)}</td>
                                <td>${parseInt(row.dc_case_success)}</td>
                                <td>${parseInt(row.dc_case_fail)}</td>
                                <td>${parseInt(row.dc_case_pending)}</td>
                                <td>$${parseFloat(row.total_revenue).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                } else {
                    modalTableBody.innerHTML = '';
                    modalNoData.style.display = 'block';
                }
            }).catch(() => {
                modalTableBody.innerHTML = '';
                modalNoData.style.display = 'block';
            });
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>
</body>
</html>