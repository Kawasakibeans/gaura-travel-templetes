<?php
/**
 * Template Name: Escalation Status
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

// Define API base URL if not already defined
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1');
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
// if ($mysqli->connect_error) {
//     echo "Database connection failed";
//     exit;
// }
// ============================================================================

/** --------- Read only date filters --------- */
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');

/** --------- AJAX: details for a day (no extra filters) --------- */
if (isset($_GET['action']) && $_GET['action'] === 'get_day_details' && !empty($_GET['date'])) {
    $date = filter_var($_GET['date'], FILTER_SANITIZE_STRING);

    // ============================================================================
    // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
    // ============================================================================
    // $date = $mysqli->real_escape_string($_GET['date']);
    // 
    // $sql = "
    //     SELECT 
    //         DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') AS escalated_time,
    //         order_id,
    //         auto_id,             -- case id
    //         escalated_by,
    //         escalation_type,
    //         status,
    //         escalate_to
    //     FROM wpk4_backend_travel_escalations
    //     WHERE DATE(escalated_on) = '$date'
    //     ORDER BY escalated_on ASC
    // ";
    // $res = $mysqli->query($sql);
    // 
    // if (!$res || $res->num_rows === 0) {
    //     echo '<p class="no-data">No escalations for this date.</p>';
    //     exit;
    // }
    // 
    // echo '<table class="compact-table">';
    // echo '<thead><tr>
    //         <th>Escalated On</th>
    //         <th>Order ID</th>
    //         <th>Case ID</th>
    //         <th>Escalated By</th>
    //         <th>Escalation Type</th>
    //         <th>Status</th>
    //         <th>Escalated To</th>
    //       </tr></thead><tbody>';
    // while ($row = $res->fetch_assoc()) {
    //     echo '<tr>';
    //     echo '<td>' . htmlspecialchars($row['escalated_time']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['order_id']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['auto_id']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['escalated_by']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['escala tion_type'] ?? $row['escalation_type']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['status']) . '</td>';
    //     echo '<td>' . htmlspecialchars($row['escalate_to']) . '</td>';
    //     echo '</tr>';
    // }
    // echo '</tbody></table>';
    // exit;
    // ============================================================================
    
    // Fetch day details from API
    $url = API_BASE_URL . '/escalations-statuswise/date/' . urlencode($date);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    $details = [];
    
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        if (isset($responseData['data']['details'])) {
            $details = $responseData['data']['details'];
        } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
            $details = $responseData['data'];
        }
    }

    if (count($details) === 0) {
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
    foreach ($details as $row) {
        // Handle both database format and API format
        $escalated_time = $row['escalated_time'] ?? $row['escalated_on'] ?? '';
        $order_id = $row['order_id'] ?? '';
        $auto_id = $row['auto_id'] ?? '';
        $escalated_by = $row['escalated_by'] ?? '';
        $escalation_type = $row['escalation_type'] ?? '';
        $status = $row['status'] ?? '';
        $escalate_to = $row['escalate_to'] ?? '';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($escalated_time) . '</td>';
        echo '<td>' . htmlspecialchars($order_id) . '</td>';
        echo '<td>' . htmlspecialchars($auto_id) . '</td>';
        echo '<td>' . htmlspecialchars($escalated_by) . '</td>';
        echo '<td>' . htmlspecialchars($escalation_type) . '</td>';
        echo '<td>' . htmlspecialchars($status) . '</td>';
        echo '<td>' . htmlspecialchars($escalate_to) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}

/** --------- Fetch daily rollup (no status/escalated_to filters) --------- */
function fetchDailyRollup($startDate, $endDate) {
    // ============================================================================
    // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
    // ============================================================================
    // $safeStart = $mysqli->real_escape_string($startDate);
    // $safeEnd   = $mysqli->real_escape_string($endDate);
    // 
    // $sql = "
    //     SELECT 
    //         DATE(escalated_on) AS d,
    // 
    //         -- Status buckets
    //         SUM(CASE WHEN UPPER(TRIM(status)) = 'OPEN'    THEN 1 ELSE 0 END) AS st_open,
    //         SUM(CASE WHEN UPPER(TRIM(status)) = 'CLOSED'  THEN 1 ELSE 0 END) AS st_closed,
    //         SUM(CASE WHEN UPPER(TRIM(status)) = 'PENDING' THEN 1 ELSE 0 END) AS st_pending,
    // 
    //         -- Escalated To buckets
    //         SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'HO'      THEN 1 ELSE 0 END) AS to_ho,
    //         SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'MANAGER' THEN 1 ELSE 0 END) AS to_manager,
    //         SUM(CASE WHEN (escalate_to IS NULL OR TRIM(escalate_to) = '') THEN 1 ELSE 0 END) AS to_blank
    // 
    //     FROM wpk4_backend_travel_escalations
    //     WHERE escalated_on BETWEEN '$safeStart' AND '$safeEnd'
    //     GROUP BY DATE(escalated_on)
    //     ORDER BY d ASC
    // ";
    // 
    // $data = [];
    // if ($res = $mysqli->query($sql)) {
    //     while ($row = $res->fetch_assoc()) $data[] = $row;
    // }
    // return $data;
    // ============================================================================
    
    // Fetch daily rollup from API
    $url = API_BASE_URL . '/escalations-statuswise';
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    $url .= '?' . http_build_query($params);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Debug mode
    $debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    // Check for HTTP errors
    if ($httpCode >= 400) {
        error_log("Escalations Statuswise API error: HTTP $httpCode - $response");
        return [];
    }
    
    $responseData = json_decode($response, true);
    $data = [];
    
    if ($debug_mode) {
        error_log("Escalations Statuswise API URL: $url");
        error_log("Escalations Statuswise API Response: " . substr($response, 0, 2000));
        error_log("Escalations Statuswise Parsed Data: " . print_r($responseData, true));
    }
    
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        // API returns data in nested structure: { date, status: {open, closed, pending}, escalated_to: {ho, manager, blank} }
        // Need to transform to flat structure: { d, st_open, st_closed, st_pending, to_ho, to_manager, to_blank }
        $apiData = [];
        if (isset($responseData['data']['data']) && is_array($responseData['data']['data'])) {
            $apiData = $responseData['data']['data'];
        } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
            $apiData = $responseData['data'];
        }
        
        // Transform nested structure to flat structure
        foreach ($apiData as $row) {
            if (!is_array($row)) {
                continue;
            }
            
            $data[] = [
                'd' => $row['date'] ?? '',
                'st_open' => isset($row['status']['open']) ? (int)$row['status']['open'] : 0,
                'st_closed' => isset($row['status']['closed']) ? (int)$row['status']['closed'] : 0,
                'st_pending' => isset($row['status']['pending']) ? (int)$row['status']['pending'] : 0,
                'to_ho' => isset($row['escalated_to']['ho']) ? (int)$row['escalated_to']['ho'] : 0,
                'to_manager' => isset($row['escalated_to']['manager']) ? (int)$row['escalated_to']['manager'] : 0,
                'to_blank' => isset($row['escalated_to']['blank']) ? (int)$row['escalated_to']['blank'] : 0
            ];
        }
    } else {
        $debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
        if ($debug_mode) {
            error_log("Escalations Statuswise API: Status is not success. Response: " . print_r($responseData, true));
        }
    }
    
    return $data;
}

$daily = fetchDailyRollup($startDate, $endDate);

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

    <?php
    // Debug mode
    $debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($debug_mode) {
        echo '<div style="background: #fff3cd; padding: 15px; margin: 20px 0; border: 1px solid #ffc107; border-radius: 5px;">';
        echo '<strong>Debug Mode Enabled</strong><br>';
        echo 'API Base URL: ' . htmlspecialchars(API_BASE_URL) . '<br>';
        echo 'Start Date: ' . htmlspecialchars($startDate) . '<br>';
        echo 'End Date: ' . htmlspecialchars($endDate) . '<br>';
        echo 'Daily Data Count: ' . count($daily) . '<br>';
        if (!empty($daily)) {
            echo 'Daily Data Sample (first 2 items): <pre>' . htmlspecialchars(print_r(array_slice($daily, 0, 2), true)) . '</pre>';
        }
        echo '</div>';
    }
    ?>

    <div class="table-container">
        <h2 class="table-title">Daily Rollup</h2>
        <?php if (empty($daily)): ?>
            <div class="no-data">No data found for the selected period.</div>
        <?php else: 
            // Totals by column (no overall "Total" column anymore)
            $totals = ['open'=>0,'closed'=>0,'pending'=>0,'ho'=>0,'manager'=>0,'blank'=>0];
            foreach ($daily as $r) {
                if (!is_array($r)) {
                    continue; // Skip non-array items
                }
                $totals['open']    += isset($r['st_open']) ? (int)$r['st_open'] : 0;
                $totals['closed']  += isset($r['st_closed']) ? (int)$r['st_closed'] : 0;
                $totals['pending'] += isset($r['st_pending']) ? (int)$r['st_pending'] : 0;
                $totals['ho']      += isset($r['to_ho']) ? (int)$r['to_ho'] : 0;
                $totals['manager'] += isset($r['to_manager']) ? (int)$r['to_manager'] : 0;
                $totals['blank']   += isset($r['to_blank']) ? (int)$r['to_blank'] : 0;
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
                    <?php if (!is_array($row)) continue; ?>
                    <tr>
                        <td>
                            <a class="date-link" href="#" data-date="<?= htmlspecialchars($row['d'] ?? '') ?>">
                                <?= htmlspecialchars($row['d'] ?? '') ?>
                            </a>
                        </td>
                        <td><?= isset($row['st_open']) ? (int)$row['st_open'] : 0 ?></td>
                        <td><?= isset($row['st_closed']) ? (int)$row['st_closed'] : 0 ?></td>
                        <td><?= isset($row['st_pending']) ? (int)$row['st_pending'] : 0 ?></td>
                        <td><?= isset($row['to_ho']) ? (int)$row['to_ho'] : 0 ?></td>
                        <td><?= isset($row['to_manager']) ? (int)$row['to_manager'] : 0 ?></td>
                        <td><?= isset($row['to_blank']) ? (int)$row['to_blank'] : 0 ?></td>
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
