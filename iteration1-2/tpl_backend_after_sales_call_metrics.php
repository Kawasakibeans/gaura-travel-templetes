<?php
/**
 * Template Name: After Sales Call Metrics (Date Grouped)
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

// Date filter and agent filter from GET params
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Fetch distinct agents for dropdown
$agentResult = $mysqli->query("
    SELECT DISTINCT agent_name
    FROM wpk4_backend_agent_codes
    WHERE location = 'BOM' AND status = 'active'
    ORDER BY agent_name ASC
");
$agents = [];
if ($agentResult) {
    while ($row = $agentResult->fetch_assoc()) {
        $agents[] = $row['agent_name'];
    }
}

/**
 * Fetch data grouped by date for a given date range and optional day range & agent filter
 * NOTE: total_acw is CHAR like '206 seconds' → parse with SUBSTRING_INDEX and CAST to seconds.
 */
function fetchDataByDate($mysqli, $startDate, $endDate, $startDay = null, $endDay = null, $agent = '') {
    $dateCondition = "`date` BETWEEN '$startDate' AND '$endDate'";
    if ($startDay && $endDay) {
        $dateCondition .= " AND DAY(`date`) BETWEEN $startDay AND $endDay";
    }
    if ($agent !== '') {
        $agentEscaped = $mysqli->real_escape_string($agent);
        $dateCondition .= " AND agent_name = '$agentEscaped'";
    }

    $query = "
        SELECT 
            `date`,
            SUM(inb_call_count)                                  AS inb_call_count,
            SUM(sales_aht)                                  AS sales_aht,
            SUM(gtmd_aht)                                  AS gtmd_aht,
            SUM(inb_call_count_duration)                         AS inb_call_count_duration,
            SUM(gtcs)                                            AS gtcs,
            SUM(gtpy)                                            AS gtpy,
            SUM(gtet)                                            AS gtet,
            SUM(gtdc)                                            AS gtdc,
            SUM(gtrf)                                            AS gtrf,
            SUM(gtib)                                            AS gtib,
            SUM(gtmd)                                            AS gtmd,
            /* Parse 'NNN seconds' to numeric seconds and sum */
            SUM(CAST(SUBSTRING_INDEX(total_acw, ' ', 1) AS UNSIGNED)) AS total_acw_seconds
        FROM wpk4_agent_after_sale_productivity_report
        WHERE $dateCondition
        GROUP BY `date`
        ORDER BY `date` ASC
    ";

    $result = $mysqli->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inb_calls = (int)$row['inb_call_count'];
            $gtib_calls = (int)$row['gtib'];
            $gtmd_calls = (int)$row['gtmd'];
            $sum_dur   = (int)$row['inb_call_count_duration']; // seconds
            $sum_dur_sales   = (int)$row['sales_aht']; // seconds
            $sum_dur_sales_gtmd   = (int)$row['gtmd_aht']; // seconds
            $sum_acw   = (int)$row['total_acw_seconds'];       // seconds

            $total_campaign = (int)$row['gtcs'] + (int)$row['gtpy'] + (int)$row['gtet'] + (int)$row['gtdc'] + (int)$row['gtrf']+ (int)$row['gtib']+ (int)$row['gtmd'];
            $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0; // seconds per call
            $gtib_aht = ($gtib_calls > 0) ? round($sum_dur_sales / $gtib_calls) : 0; // seconds per call
            $gtmd_aht = ($gtmd_calls > 0) ? round($sum_dur_sales_gtmd / $gtmd_calls) : 0; // seconds per call
            $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0; // seconds per call

            $data[] = [
                'date'                      => $row['date'],
                'inb_call_count'            => $inb_calls,
                'inb_call_count_duration'   => $sum_dur,  // raw total seconds for weighted avg
                'sales_aht'                 => $sum_dur_sales,  // raw total seconds for weighted avg
                'gtmd_duration'                 => $sum_dur_sales_gtmd,  // raw total seconds for weighted avg
                'aht'                       => $aht,      // per-day average (display)
                'gtib_aht'                       => $gtib_aht,      // per-day average (display)
                'gtmd_aht'                       => $gtmd_aht,      // per-day average (display)
                'gtcs'                      => (int)$row['gtcs'],
                'gtpy'                      => (int)$row['gtpy'],
                'gtet'                      => (int)$row['gtet'],
                'gtdc'                      => (int)$row['gtdc'],
                'gtrf'                      => (int)$row['gtrf'],
                'gtib'                      => (int)$row['gtib'],
                'gtmd'                      => (int)$row['gtmd'],
                'acw'                       => $acw,      // per-day average (display)
                'total_acw'                 => $sum_acw,  // raw total seconds for weighted avg
                'total_campaign'            => $total_campaign
            ];
        }
    }
    return $data;
}

// Fetch data for each day range including agent filter
$data_1_10   = fetchDataByDate($mysqli, $startDate, $endDate, 1, 10,  $selected_agent);
$data_11_20  = fetchDataByDate($mysqli, $startDate, $endDate, 11, 20, $selected_agent);
$data_21_end = fetchDataByDate($mysqli, $startDate, $endDate, 21, 31, $selected_agent);
$data_total  = fetchDataByDate($mysqli, $startDate, $endDate, null, null, $selected_agent);

/** AJAX: agent data for a specific date with agent filter
 *  NOTE: parse total_acw to seconds here too, and return averages per agent.
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'agent_data' && isset($_GET['date'])) {
    $date = $mysqli->real_escape_string($_GET['date']);
    $ajax_agent = $_GET['agent_name'] ?? '';

    $agentFilterSql = '';
    if (!empty($ajax_agent)) {
        $agentEscaped = $mysqli->real_escape_string($ajax_agent);
        $agentFilterSql = "AND agent_name = '$agentEscaped'";
    }

    $agentQuery = "
        SELECT 
            agent_name,
            SUM(inb_call_count)                                  AS inb_call_count,
            SUM(sales_aht)                                  AS sales_aht,
            SUM(gtmd_aht)                                  AS gtmd_aht,
            SUM(inb_call_count_duration)                         AS inb_call_count_duration,
            SUM(gtcs)                                            AS gtcs,
            SUM(gtpy)                                            AS gtpy,
            SUM(gtet)                                            AS gtet,
            SUM(gtdc)                                            AS gtdc,
            SUM(gtrf)                                            AS gtrf,
            SUM(gtib)                                            AS gtib,
            SUM(gtmd)                                            AS gtmd,
            SUM(CAST(SUBSTRING_INDEX(total_acw, ' ', 1) AS UNSIGNED)) AS total_acw_seconds
        FROM wpk4_agent_after_sale_productivity_report
        WHERE `date` = '$date' $agentFilterSql
        GROUP BY agent_name
        HAVING SUM(inb_call_count) > 0
        ORDER BY agent_name ASC
    ";
    $res = $mysqli->query($agentQuery);
    $agentsData = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $inb_calls = (int)$row['inb_call_count'];
            $gtib_calls = (int)$row['gtib'];
            $inb_calls = (int)$row['gtmd'];
            $gtmd_calls   = (int)$row['inb_call_count_duration'];
            $sum_dur_sales   = (int)$row['sales_aht'];
            $sum_dur_sales_gtmd   = (int)$row['gtmd_aht'];
            $sum_acw   = (int)$row['total_acw_seconds'];
            $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0;
             $gtib_aht = ($gtib_calls > 0) ? round($sum_dur_sales / $gtib_calls) : 0;
            $gtmd_aht = ($gtmd_calls > 0) ? round($sum_dur_sales_gtmd / $gtmd_calls) : 0; 
            $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0;

            $agentsData[] = [
                'agent_name'                 => $row['agent_name'],
                'inb_call_count'             => $inb_calls,
                'inb_call_count_duration'    => $sum_dur,
                'sales_aht'                 => $sum_dur_sales,
                'gtmd_duration'                 => $sum_dur_sales_gtmd,
                'aht'                        => $aht,
                'gtib_aht'                        => $gtib_aht,
                'gtmd_aht'                        => $gtmd_aht,
                'gtcs'                       => (int)$row['gtcs'],
                'gtpy'                       => (int)$row['gtpy'],
                'gtet'                       => (int)$row['gtet'],
                'gtdc'                       => (int)$row['gtdc'],
                'gtrf'                       => (int)$row['gtrf'],
                'gtib'                       => (int)$row['gtib'],
                'gtmd'                       => (int)$row['gtmd'],
                'acw'                        => $acw,
                'total_campaign'             => (int)$row['gtcs'] + (int)$row['gtpy'] + (int)$row['gtet'] + (int)$row['gtdc'] + (int)$row['gtrf']+ (int)$row['gtib']+ (int)$row['gtmd']
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($agentsData);
    exit;
}

function format_seconds_to_hhmmss($seconds) {
    $totalSeconds = (int)round($seconds);
    $hours = intdiv($totalSeconds, 3600);
    $minutes = intdiv($totalSeconds % 3600, 60);
    $secs = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function renderTableByDate($data, $title) {
    echo "<h2 class='section-title'>" . htmlspecialchars($title) . "</h2>";
    echo "<table>";
    echo "<thead>
        <tr>
            <th>Date</th>
            <th>GTCS</th>
            <th>GTPY</th>
            <th>GTET</th>
            <th>GTDC</th>
            <th>GTRF</th>
            <th>GTIB</th>
            <th>GTMD</th>
            <th>Total Calls</th>
            <th>AHT</th>
            <th>GTIB AHT</th>
            <th>GTMD AHT</th>
            <th>ACW</th>
        </tr>
    </thead><tbody>";

    if (count($data) === 0) {
        echo "<tr><td colspan='9' class='no-data'>No data available for this period</td></tr>";
    } else {
        // Grand total accumulators
        $sum_gtcs = 0;
        $sum_gtpy = 0;
        $sum_gtet = 0;
        $sum_gtdc = 0;
        $sum_gtrf = 0;
        $sum_gtib = 0;
        $sum_gtmd = 0;
        $sum_total_calls = 0;

        $sum_total_call_duration = 0; // seconds
        $sum_total_acw_time      = 0; // seconds
        $sum_total_calls_for_avg = 0; // calls
        $sum_total_calls_for_avg_gtib = 0; // calls
        $sum_total_calls_for_avg_gtmd = 0; // calls
        $sum_total_call_duration_gtib = 0; // seconds
        $sum_total_call_duration_gtmd = 0; // seconds

        foreach ($data as $row) {
            $aht_seconds = (int)$row['aht']; // per-day average
            $gtib_aht_seconds = (int)$row['gtib_aht']; // per-day average
            $gtmd_aht_seconds = (int)$row['gtmd_aht']; // per-day average
            $acw_seconds = (int)$row['acw']; // per-day average

            $inb_call_count          = (int)$row['inb_call_count'];
            $gtib_call_count          = (int)$row['gtib'];
            $gtmd_call_count          = (int)$row['gtmd'];
            $total_acw_value_seconds = (int)$row['total_acw'];                // raw total seconds
            $inb_call_dur_seconds    = (int)$row['inb_call_count_duration'];  // raw total seconds
            $inb_call_dur_seconds_sales    = (int)$row['sales_aht'];  // raw total seconds
            $inb_call_dur_seconds_sales_gtmd    = (int)$row['gtmd_aht'];  // raw total seconds

            // Accumulate counts & splits
            $sum_gtcs        += (int)$row['gtcs'];
            $sum_gtpy        += (int)$row['gtpy'];
            $sum_gtet        += (int)$row['gtet'];
            $sum_gtdc        += (int)$row['gtdc'];
            $sum_gtrf        += (int)$row['gtrf'];
            $sum_gtib        += (int)$row['gtib'];
            $sum_gtmd        += (int)$row['gtmd'];
            $sum_total_calls += (int)$row['total_campaign'];

            if ($inb_call_count > 0) {
                $sum_total_call_duration += $inb_call_dur_seconds;
                 $sum_total_call_duration_gtib += $inb_call_dur_seconds_sales;
                  $sum_total_call_duration_gtmd += $inb_call_dur_seconds_sales_gtmd;
                $sum_total_acw_time      += $total_acw_value_seconds;
                $sum_total_calls_for_avg += $inb_call_count;
                $sum_total_calls_for_avg_gtib += $gtib_call_count;
                $sum_total_calls_for_avg_gtmd += $gtmd_call_count;
            }

            // Date clickable to show agent details
            $dateFormatted = date('M j, Y', strtotime($row['date']));
            $dateLink = "<a href='#' class='date-link' data-date='" . htmlspecialchars($row['date'], ENT_QUOTES) . "'>$dateFormatted</a>";

            // Traffic light for AHT
            if ($aht_seconds < 300)      $aht_color = 'background-color: #ccffcc;';
            elseif ($aht_seconds < 360)  $aht_color = 'background-color: #fff6cc;';
            else                         $aht_color = 'background-color: #ffd6d6;';

            echo "<tr>
                <td><strong>$dateLink</strong></td>
                <td>{$row['gtcs']}</td>
                <td>{$row['gtpy']}</td>
                <td>{$row['gtet']}</td>
                <td>{$row['gtdc']}</td>
                <td>{$row['gtrf']}</td>
                <td>{$row['gtib']}</td>
                <td>{$row['gtmd']}</td>
                <td><strong>{$row['total_campaign']}</strong></td>
                <td style='$aht_color'>" . format_seconds_to_hhmmss($aht_seconds) . "</td>
                <td style='$aht_color'>" . format_seconds_to_hhmmss($gtib_aht_seconds) . "</td>
                <td style='$aht_color'>" . format_seconds_to_hhmmss($gtmd_aht_seconds) . "</td>
                <td>" . format_seconds_to_hhmmss($acw_seconds) . "</td>
            </tr>";
        }

        // Weighted averages for Grand Total (ACW should be average)
        $avg_aht = ($sum_total_calls_for_avg > 0) ? round($sum_total_call_duration / $sum_total_calls_for_avg) : 0;
        $avg_aht_gtib = ($sum_total_calls_for_avg_gtib > 0) ? round($sum_total_call_duration_gtib / $sum_total_calls_for_avg_gtib) : 0;
        $avg_aht_gtmd = ($sum_total_calls_for_avg_gtmd > 0) ? round($sum_total_call_duration_gtmd / $sum_total_calls_for_avg_gtmd) : 0;
        $avg_acw = ($sum_total_calls_for_avg > 0) ? round($sum_total_acw_time      / $sum_total_calls_for_avg) : 0;

        echo "<tr style='font-weight:bold; background-color:#ffe680;'>
            <td>Grand Total</td>
            <td>$sum_gtcs</td>
            <td>$sum_gtpy</td>
            <td>$sum_gtet</td>
            <td>$sum_gtdc</td>
            <td>$sum_gtrf</td>
            <td>$sum_gtib</td>
            <td>$sum_gtmd</td>
            <td>$sum_total_calls</td>
            <td>" . format_seconds_to_hhmmss($avg_aht) . "</td>
            <td>" . format_seconds_to_hhmmss($avg_aht_gtib) . "</td>
            <td>" . format_seconds_to_hhmmss($avg_aht_gtmd) . "</td>
            <td>" . format_seconds_to_hhmmss($avg_acw) . "</td>
        </tr>";
    }
    echo "</tbody></table>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>After Sales Call Metrics By Date</title>
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
        
        #agentModal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        #agentModal .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            width: 90%;
            max-width: 900px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            position: relative;
        }
        #agentModal .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
        }
        #agentModal h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        #agentModal table {
            margin-top: 10px;
        }
        #agentModal .no-data {
            font-style: italic;
            color: #666;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>After-Sales Monthly Call Performance Report</h1>
        <div class="subtitle">Performance metrics grouped by date. Click a date to view agent details.</div>
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

            <div class="filter-group">
                <label for="agent_name">Agent</label>
                <select id="agent_name" name="agent_name">
                    <option value="">-- All Agents --</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= htmlspecialchars($agent) ?>" <?= ($selected_agent === $agent) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Apply Filter</button>
        </form>
    </div>

    <div class="date-range">
        Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
        <?php if ($selected_agent !== ''): ?>
            for agent <strong><?= htmlspecialchars($selected_agent) ?></strong>
        <?php endif; ?>
    </div>

    <div class="data-section">
        <?php
        renderTableByDate($data_1_10,  "Day 1–10 Performance");
        renderTableByDate($data_11_20, "Day 11–20 Performance");
        renderTableByDate($data_21_end,"Day 21–End of Month Performance");
        renderTableByDate($data_total, "Total Performance (Full Date Range)");
        ?>
    </div>
</div>

<!-- Modal for Agent Details -->
<div id="agentModal" aria-hidden="true" role="dialog" aria-labelledby="agentModalTitle">
    <div class="modal-content" role="document">
        <span class="close-btn" title="Close Modal">&times;</span>
        <h3 id="agentModalTitle">Agent Details for <span id="modalDate"></span></h3>
        <table id="agentDetailsTable">
            <thead>
            <tr>
                <th>Agent Name</th>
                <th>GTCS</th>
                <th>GTPY</th>
                <th>GTET</th>
                <th>GTDC</th>
                <th>GTRF</th>
                <th>GTIB</th>
                <th>GTMD</th>
                <th>Total Calls</th>
                <th>AHT</th>
                <th>GTIB AHT</th>
                <th>GTMD AHT</th>
                <th>ACW</th>
            </tr>
            </thead>
            <tbody>
            <tr><td colspan="9" class="no-data">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('agentModal');
    const modalDateSpan = document.getElementById('modalDate');
    const modalCloseBtn = modal.querySelector('.close-btn');
    const agentTableBody = document.querySelector('#agentDetailsTable tbody');

    // Get current agent filter from URL param
    function getAgentFilter() {
        const params = new URLSearchParams(window.location.search);
        return params.get('agent_name') || '';
    }

    function formatSecondsToHHMMSS(seconds) {
        seconds = Math.round(seconds);
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function fetchAgentData(date) {
        const agent = getAgentFilter();
        agentTableBody.innerHTML = '<tr><td colspan="9" class="no-data">Loading data...</td></tr>';
        fetch(`?ajax=agent_data&date=${encodeURIComponent(date)}&agent_name=${encodeURIComponent(agent)}`)
            .then(response => response.json())
            .then(data => {
                if (!data || data.length === 0) {
                    agentTableBody.innerHTML = '<tr><td colspan="9" class="no-data">No agent data found for this date.</td></tr>';
                    return;
                }
                let html = '';
                data.forEach(row => {
                    let ahtColor = '';
                    if (row.aht < 300) ahtColor = 'background-color:#ccffcc;';
                    else if (row.aht < 360) ahtColor = 'background-color:#fff6cc;';
                    else ahtColor = 'background-color:#ffd6d6;';

                    html += `<tr>
                        <td>${row.agent_name}</td>
                        <td>${row.gtcs}</td>
                        <td>${row.gtpy}</td>
                        <td>${row.gtet}</td>
                        <td>${row.gtdc}</td>
                        <td>${row.gtrf}</td>
                        <td>${row.gtib}</td>
                        <td>${row.gtmd}</td>
                        <td><strong>${row.total_campaign}</strong></td>
                        <td style="${ahtColor}">${formatSecondsToHHMMSS(row.aht)}</td>
                        <td style="${ahtColor}">${formatSecondsToHHMMSS(row.aht_gtib)}</td>
                        <td style="${ahtColor}">${formatSecondsToHHMMSS(row.aht_gtmd)}</td>
                        <td>${formatSecondsToHHMMSS(row.acw)}</td>
                    </tr>`;
                });
                agentTableBody.innerHTML = html;
            })
            .catch(() => {
                agentTableBody.innerHTML = '<tr><td colspan="9" class="no-data">Error loading agent data.</td></tr>';
            });
    }

    // Open modal on date link click
    document.querySelectorAll('.date-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const date = this.dataset.date;
            modalDateSpan.textContent = new Date(date).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            fetchAgentData(date);
            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    // Close modal on close button click or outside click
    modalCloseBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    });
    window.addEventListener('click', e => {
        const modalEl = document.getElementById('agentModal');
        if (e.target === modalEl) {
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
        }
    });

    // Close modal with Escape key
    window.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    });
});
</script>

</body>
</html>
