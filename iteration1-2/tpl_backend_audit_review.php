<?php
/**
 * Template Name: After Sales Ticket Audit Metrics
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

// --- Filters ---
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Validate and normalize dates
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
if (!$valid($from) || !$valid($to)) {
    http_response_code(400);
    exit('Invalid date format. Use YYYY-MM-DD.');
}
if (strtotime($from) > strtotime($to)) {
    [$from, $to] = [$to, $from];
}

// Agents for dropdown
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

// --- AJAX: agent-wise details for a specific calendar date ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'agent_detail' && isset($_GET['date'])) {
    header('Content-Type: application/json');

    $ajax_date  = $_GET['date'];
    $ajax_agent = $_GET['agent_name'] ?? '';

    if (!$valid($ajax_date)) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT agent_name,
               SUM(fit_audit)      AS fit_audit,
               SUM(gdeal_audit)    AS gdeal_audit,
               SUM(ticket_audited) AS ticket_audited
        FROM wpk4_agent_after_sale_productivity_report
        WHERE DATE(`date`) = ?
          AND agent_name <> 'ABDN' and ticket_audited > 0
    ";
    $types = "s";
    $params = [$ajax_date];

    if ($ajax_agent !== '') {
        $sql .= " AND agent_name = ?";
        $types .= "s";
        $params[] = $ajax_agent;
    }

    $sql .= " GROUP BY agent_name ORDER BY agent_name ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
    exit;
}

/**
 * Fetch date summary for a slice of the month (by day-of-month) within a given overall range.
 * - Half-open range [from, to+1day) includes the full end day.
 * - Optional agent filter.
 * NOTE: Using DAY(`date`) will limit index use on `date`, but keeps your current slice semantics.
 */
function fetchDateSummary($mysqli, $startDay, $endDay, $fromDate, $toDate, $agentName = '')
{
    $sql = "
        SELECT DATE(`date`) AS report_date,
               SUM(fit_audit)      AS fit_audit,
               SUM(gdeal_audit)    AS gdeal_audit,
               SUM(ticket_audited) AS ticket_audited
        FROM wpk4_agent_after_sale_productivity_report
        WHERE `date` >= ? 
          AND `date` < DATE_ADD(?, INTERVAL 1 DAY)
          AND DAY(`date`) BETWEEN ? AND ?
          AND agent_name <> 'ABDN'
    ";

    $types  = "ssii";
    $params = [$fromDate, $toDate, (int)$startDay, (int)$endDay];

    if ($agentName !== '') {
        $sql   .= " AND agent_name = ?";
        $types .= "s";
        $params[] = $agentName;
    }

    $sql .= " GROUP BY report_date ORDER BY report_date ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$endDate = new DateTime($to);
$lastDay = (int)$endDate->format('d');

// If range spans multiple months, these slices will pick those day numbers across all months in range.
// If you want slices only when the range is single-month, you can add a same-month guard like in prior files.
$ranges = [
    "1 - 10"        => [1, 10],
    "11 - 20"       => [11, 20],
    "21 - $lastDay" => [21, $lastDay],
    "Total"         => [1,  $lastDay],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>After Sales Ticket Audit Metrics</title>
<style>
    :root { --primary:#ffbb00; --primary-dark:#e6a800; --primary-light:#fff3cc; --primary-very-light:#fffdf5; --text:#333; --text-light:#666; --border:#e0e0e0; }
    body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:0; padding:0; background:#f9f9f9; color:var(--text); line-height:1.6; }
    .container { max-width:1200px; margin:0 auto; padding:20px; }
    header { background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#000; padding:25px 0; margin-bottom:30px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center; }
    h1 { margin:0; font-size:28px; font-weight:600; }
    .subtitle { font-size:16px; opacity:0.9; margin-top:8px; color:#222; }
    .filter-section { background:#fff; padding:20px; border-radius:8px; margin-bottom:30px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
    .filter-form { display:flex; flex-wrap:wrap; align-items:center; gap:15px; }
    .filter-group { display:flex; flex-direction:column; min-width:140px; }
    label { font-weight:500; margin-bottom:5px; color:var(--text-light); font-size:14px; }
    input[type="date"], select#agent_name { padding:10px 12px; border:1px solid var(--border); border-radius:6px; font-size:14px; background:#fff; transition:border-color 0.3s; height:40px; }
    input[type="date"]:focus, select#agent_name:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 2px rgba(255,187,0,0.2); }
    button { background:var(--primary); color:#000; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; transition:all 0.3s; align-self:flex-end; }
    button:hover { background:var(--primary-dark); transform:translateY(-1px); }
    .date-range { font-size:14px; color:var(--text-light); margin-bottom:20px; font-style:italic; }
    .table-container { margin-bottom:40px; background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); padding:15px 20px 30px; }
    .section-title { font-size:20px; font-weight:600; color:var(--text); margin-bottom:15px; border-bottom:2px solid var(--primary); padding-bottom:10px; display:inline-block; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th, td { padding:12px 15px; text-align:center; border:1px solid var(--border); }
    th { background:var(--primary); color:#000; font-weight:600; text-transform:uppercase; font-size:13px; }
    tr:nth-child(even) { background:var(--primary-very-light); }
    tr:hover { background:var(--primary-light); }
    .no-data { text-align:center; padding:20px; color:var(--text-light); font-style:italic; }
    tr.date-row { cursor:pointer; }
    tr.date-row td:first-child { color:#004080; text-decoration:underline; cursor:pointer; }
    /* Modal */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; z-index:9999; }
    .modal { background:#fff; border-radius:10px; max-width:700px; width:90%; max-height:80vh; overflow-y:auto; padding:20px 30px 30px; box-shadow:0 10px 30px rgba(0,0,0,0.25); position:relative; }
    .modal h3 { margin-top:0; color:var(--primary-dark); font-weight:700; }
    .modal-close { position:absolute; top:15px; right:20px; font-size:24px; font-weight:bold; color:#333; cursor:pointer; border:none; background:none; }
    .modal-close:hover { color:var(--primary-dark); }
    .modal table { margin-bottom:0; }
    @media (max-width:768px){ .filter-form{flex-direction:column; align-items:stretch;} button{align-self:stretch;} table{display:block; overflow-x:auto;} }
</style>
</head>
<body>
<div class="container">
    <header>
        <h1>After Sales Ticket Audit Metrics</h1>
        <div class="subtitle">Audit performance metrics grouped by date range</div>
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
                    <?php foreach ($all_agents as $agent):
                        $sel = ($selected_agent === $agent) ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($agent) ?>" <?= $sel ?>><?= htmlspecialchars($agent) ?></option>
                    <?php endforeach; ?>
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
        foreach ($ranges as $title => [$startDay, $endDay]) {
            $result = fetchDateSummary($mysqli, $startDay, $endDay, $from, $to, $selected_agent);

            echo "<div class='table-container'>";
            echo "<h2 class='section-title'>Date Range: $title</h2>";
            echo "<table class='summary-table' data-range='$title'>";
            echo "<thead>
                    <tr>
                        <th>Date</th>
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
                    $dateFormatted = date('Y-m-d', strtotime($row['report_date']));
                    $fit   = (int)$row['fit_audit'];
                    $gdeal = (int)$row['gdeal_audit'];
                    $aud   = (int)$row['ticket_audited'];

                    echo "<tr class='date-row' data-date='{$dateFormatted}'>
                            <td><strong>{$dateFormatted}</strong></td>
                            <td>{$fit}</td>
                            <td>{$gdeal}</td>
                            <td>{$aud}</td>
                          </tr>";

                    $total_fit   += $fit;
                    $total_gdeal += $gdeal;
                    $total_audit += $aud;
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
            echo "</div>";
        }
        $mysqli->close();
        ?>
    </div>
</div>

<!-- Modal for agent details -->
<div class="modal-overlay" id="modalOverlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" id="modalClose" aria-label="Close modal">&times;</button>
        <div id="modalContent"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const agentFilter  = document.getElementById('agent_name');
    const modalOverlay = document.getElementById('modalOverlay');
    const modalClose   = document.getElementById('modalClose');
    const modalContent = document.getElementById('modalContent');

    function openModal(){ modalOverlay.style.display='flex'; modalOverlay.setAttribute('aria-hidden','false'); }
    function closeModal(){ modalOverlay.style.display='none'; modalOverlay.setAttribute('aria-hidden','true'); modalContent.innerHTML=''; }

    async function fetchAgentDetails(date) {
        const agentName = agentFilter.value;
        const url = `?ajax=agent_detail&date=${encodeURIComponent(date)}&agent_name=${encodeURIComponent(agentName)}`;

        try {
            const resp = await fetch(url);
            const data = await resp.json();

            if (!Array.isArray(data) || data.length === 0) {
                modalContent.innerHTML = `<p style="font-style: italic; color: #666;">No agent data available for ${date}.</p>`;
                openModal();
                return;
            }

            let html = `<h3 id="modalTitle">Agent-wise details for ${date}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>FIT Audit</th>
                            <th>GDeal Audit</th>
                            <th>Total Ticket Audited</th>
                        </tr>
                    </thead>
                    <tbody>`;

            let totalFit=0,totalG=0,totalA=0;
            data.forEach(row => {
                const fit = parseInt(row.fit_audit) || 0;
                const gd  = parseInt(row.gdeal_audit) || 0;
                const aud = parseInt(row.ticket_audited) || 0;
                totalFit += fit; totalG += gd; totalA += aud;

                html += `<tr>
                    <td>${row.agent_name}</td>
                    <td>${fit}</td>
                    <td>${gd}</td>
                    <td>${aud}</td>
                </tr>`;
            });

            html += `
                <tr style="font-weight:bold; background-color:#eaeaea;">
                    <td>Grand Total</td>
                    <td>${totalFit}</td>
                    <td>${totalG}</td>
                    <td>${totalA}</td>
                </tr>
            </tbody></table>`;

            modalContent.innerHTML = html;
            openModal();
        } catch (e) {
            console.error(e);
            modalContent.innerHTML = `<p style="color:red;">Error loading data.</p>`;
            openModal();
        }
    }

    modalClose.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modalOverlay.style.display === 'flex') closeModal(); });

    document.querySelectorAll('tr.date-row').forEach(row => {
        row.addEventListener('click', () => {
            const date = row.getAttribute('data-date');
            fetchAgentDetails(date);
        });
    });

    // If agent filter changes while modal is open, refresh data for current date
    agentFilter.addEventListener('change', () => {
        if (modalOverlay.style.display === 'flex') {
            const h3 = modalContent.querySelector('h3');
            const match = h3 ? h3.textContent.match(/\d{4}-\d{2}-\d{2}/) : null;
            if (match) fetchAgentDetails(match[0]);
        }
    });
});
</script>
</body>
</html>
