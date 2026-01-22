<?php
/**
 * Template Name: Escalation Status
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    echo "Database connection failed";
    exit;
}

/** --------- Read only date filters --------- */
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');

/** --------- AJAX: details for a day (no extra filters) --------- */
if (isset($_GET['action']) && $_GET['action'] === 'get_day_details' && !empty($_GET['date'])) {
    $date = $mysqli->real_escape_string($_GET['date']);

    $sql = "
        SELECT 
            DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') AS escalated_time,
            order_id,
            auto_id,             -- case id
            escalated_by,
            escalation_type,
            status,
            escalate_to
        FROM wpk4_backend_travel_escalations
        WHERE DATE(escalated_on) = '$date'
        ORDER BY escalated_on ASC
    ";
    $res = $mysqli->query($sql);

    if (!$res || $res->num_rows === 0) {
        echo '<p class="no-data">No escalations for this date.</p>';
        exit;
    }

    echo '<table class="compact-table">';
    echo '<thead><tr>
            <th>Escalated On</th>
            <th>Order ID</th>
            <th>Case ID</th>
            <th>Escalated By</th>
            <th>Escalation Type</th>
            <th>Status</th>
            <th>Escalated To</th>
          </tr></thead><tbody>';
    while ($row = $res->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['escalated_time']) . '</td>';
        echo '<td>' . htmlspecialchars($row['order_id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['auto_id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['escalated_by']) . '</td>';
        echo '<td>' . htmlspecialchars($row['escala tion_type'] ?? $row['escalation_type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['status']) . '</td>';
        echo '<td>' . htmlspecialchars($row['escalate_to']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}

/** --------- Fetch daily rollup (no status/escalated_to filters) --------- */
function fetchDailyRollup($mysqli, $startDate, $endDate) {
    $safeStart = $mysqli->real_escape_string($startDate);
    $safeEnd   = $mysqli->real_escape_string($endDate);

    $sql = "
        SELECT 
            DATE(escalated_on) AS d,

            -- Status buckets
            SUM(CASE WHEN UPPER(TRIM(status)) = 'OPEN'    THEN 1 ELSE 0 END) AS st_open,
            SUM(CASE WHEN UPPER(TRIM(status)) = 'CLOSED'  THEN 1 ELSE 0 END) AS st_closed,
            SUM(CASE WHEN UPPER(TRIM(status)) = 'PENDING' THEN 1 ELSE 0 END) AS st_pending,

            -- Escalated To buckets
            SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'HO'      THEN 1 ELSE 0 END) AS to_ho,
            SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'MANAGER' THEN 1 ELSE 0 END) AS to_manager,
            SUM(CASE WHEN (escalate_to IS NULL OR TRIM(escalate_to) = '') THEN 1 ELSE 0 END) AS to_blank

        FROM wpk4_backend_travel_escalations
        WHERE escalated_on BETWEEN '$safeStart' AND '$safeEnd'
        GROUP BY DATE(escalated_on)
        ORDER BY d ASC
    ";

    $data = [];
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) $data[] = $row;
    }
    return $data;
}

$daily = fetchDailyRollup($mysqli, $startDate, $endDate);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Escalation Status & Escalated-To Summary</title>
    <style>
        :root { --primary:#ffbb00; --primary-dark:#e6a800; --primary-light:#fff3cc; --primary-very-light:#fffdf5; --text:#333; --text-light:#666; --border:#e0e0e0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:0; padding:0; background:#f9f9f9; color:var(--text); line-height:1.6; }
        .container { max-width:100vw; width:100%; margin:0; padding:20px; box-sizing:border-box; }
        header { background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#000; padding:25px 0; margin-bottom:30px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center; }
        h1 { margin:0; font-size:28px; font-weight:600; }
        .subtitle { font-size:16px; opacity:.9; margin-top:8px; }

        .filter-section { background:#fff; padding:20px; border-radius:8px; margin-bottom:30px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
        .filter-form { display:flex; flex-wrap:wrap; align-items:flex-end; gap:15px; }
        .filter-group { display:flex; flex-direction:column; min-width:200px; }
        label { font-weight:500; margin-bottom:5px; color:var(--text-light); font-size:14px; }
        input[type="date"] { padding:10px 12px; border:1px solid var(--border); border-radius:6px; font-size:14px; transition:border-color .3s; background:#fff; }
        input[type="date"]:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 2px rgba(255,187,0,.2); }
        button { background:var(--primary); color:#000; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; transition:all .3s; }
        button:hover { background:var(--primary-dark); transform:translateY(-1px); }

        .table-container { background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
        .table-title { font-size:18px; margin:0 0 10px 0; padding-bottom:7px; border-bottom:2px solid var(--primary); color:var(--text); }
        table.compact-table { width:100%; border-collapse:collapse; background:#fff; font-size:13px; margin-top:10px; }
        table.compact-table th, table.compact-table td { padding:6px 8px; text-align:center; border:1px solid var(--border); }
        table.compact-table th { background:var(--primary); color:#000; font-weight:600; white-space:nowrap; }
        table.compact-table tr:nth-child(even) { background:var(--primary-very-light); }
        table.compact-table tr:hover { background:var(--primary-light); }
        .no-data { text-align:center; padding:20px; color:var(--text-light); font-style:italic; }
        .date-range { font-size:14px; color:#000; margin:16px 0; font-style:italic; }
        a.date-link { color:#000; font-weight:600; text-decoration:none; }
        a.date-link:hover { text-decoration:underline; }
        tfoot td { font-weight:700; background:var(--primary-light); }

        /* Modal */
        #dayModal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.45); }
        #dayModal .content { background:#fff; margin:5% auto; padding:24px; width:92%; max-width:950px; border-radius:10px; position:relative; }
        #dayModal .close { position:absolute; right:14px; top:8px; font-size:28px; cursor:pointer; }
        #dayModal h3 { margin:0 0 10px 0; border-bottom:2px solid var(--primary); padding-bottom:8px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Escalation Status & Escalated-To Summary</h1>
        <div class="subtitle">Daily counts grouped by Status and Escalated To</div>
    </header>

    <div class="filter-section">
        <form method="get" class="filter-form">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <button type="submit">Apply Date Range</button>
        </form>
    </div>

    <div class="date-range">
        Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
    </div>

    <div class="table-container">
        <h2 class="table-title">Daily Rollup</h2>
        <?php if (empty($daily)): ?>
            <div class="no-data">No data found for the selected period.</div>
        <?php else: 
            // Totals by column (no overall "Total" column anymore)
            $totals = ['open'=>0,'closed'=>0,'pending'=>0,'ho'=>0,'manager'=>0,'blank'=>0];
            foreach ($daily as $r) {
                $totals['open']    += (int)$r['st_open'];
                $totals['closed']  += (int)$r['st_closed'];
                $totals['pending'] += (int)$r['st_pending'];
                $totals['ho']      += (int)$r['to_ho'];
                $totals['manager'] += (int)$r['to_manager'];
                $totals['blank']   += (int)$r['to_blank'];
            }
        ?>
        <table class="compact-table">
            <thead>
                <tr>
                    <th rowspan="2">Date</th>
                    <th colspan="3">Status Data</th>
                    <th colspan="3">Escalated To</th>
                </tr>
                <tr>
                    <th>Open</th>
                    <th>Closed</th>
                    <th>Pending</th>
                    <th>HO</th>
                    <th>Manager</th>
                    <th>Blank</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily as $row): ?>
                    <tr>
                        <td>
                            <a class="date-link" href="#" data-date="<?= htmlspecialchars($row['d']) ?>">
                                <?= htmlspecialchars($row['d']) ?>
                            </a>
                        </td>
                        <td><?= (int)$row['st_open'] ?></td>
                        <td><?= (int)$row['st_closed'] ?></td>
                        <td><?= (int)$row['st_pending'] ?></td>
                        <td><?= (int)$row['to_ho'] ?></td>
                        <td><?= (int)$row['to_manager'] ?></td>
                        <td><?= (int)$row['to_blank'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Totals</td>
                    <td><?= $totals['open'] ?></td>
                    <td><?= $totals['closed'] ?></td>
                    <td><?= $totals['pending'] ?></td>
                    <td><?= $totals['ho'] ?></td>
                    <td><?= $totals['manager'] ?></td>
                    <td><?= $totals['blank'] ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for day details -->
<div id="dayModal">
    <div class="content">
        <span class="close">&times;</span>
        <h3>Details for <span id="modal-date"></span></h3>
        <div id="modal-body"><p class="no-data">Loading…</p></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.date-link').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            const d = a.getAttribute('data-date');
            openDayModal(d);
        });
    });

    const modal = document.getElementById('dayModal');
    const close = modal.querySelector('.close');
    close.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
});

function openDayModal(dateStr) {
    const modal = document.getElementById('dayModal');
    const tgt = document.getElementById('modal-body');
    document.getElementById('modal-date').textContent = dateStr;
    tgt.innerHTML = '<p class="no-data">Loading…</p>';
    modal.style.display = 'block';

    const params = new URLSearchParams({ action: 'get_day_details', date: dateStr });

    fetch('?' + params.toString())
        .then(r => r.text())
        .then(html => { tgt.innerHTML = html; })
        .catch(err => { tgt.innerHTML = '<p class="no-data">Error: ' + err.message + '</p>'; });
}
</script>
</body>
</html>
