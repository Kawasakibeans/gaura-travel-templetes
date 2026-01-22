<?php
/**
 * Template Name: After Sales Call Metrics agent wise
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

// Fetch distinct agents for dropdown (excluding 'ABDN')
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
 * Fetch data grouped by AGENT for the date range, optional day range & agent filter.
 * ACW is stored as text like '206 seconds' → parse the numeric seconds via SUBSTRING_INDEX/CAST.
 */
function fetchDataByAgent($mysqli, $startDate, $endDate, $startDay = null, $endDay = null, $agent = '') {
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
            agent_name,
            SUM(inb_call_count)                                  AS inb_call_count,
            SUM(inb_call_count_duration)                         AS inb_call_count_duration,
            SUM(gtcs)                                            AS gtcs,
            SUM(gtpy)                                            AS gtpy,
            SUM(gtet)                                            AS gtet,
            SUM(gtdc)                                            AS gtdc,
            SUM(gtrf)                                            AS gtrf,
            /* Parse 'NNN seconds' to numeric seconds and sum */
            SUM(CAST(SUBSTRING_INDEX(total_acw, ' ', 1) AS UNSIGNED)) AS total_acw_seconds
        FROM wpk4_agent_after_sale_productivity_report
        WHERE $dateCondition
        GROUP BY agent_name
        HAVING SUM(inb_call_count) > 0
        ORDER BY agent_name ASC
    ";

    $result = $mysqli->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inb_calls = (int)$row['inb_call_count'];
            $sum_dur   = (int)$row['inb_call_count_duration']; // seconds
            $sum_acw   = (int)$row['total_acw_seconds'];       // seconds

            $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0; // seconds per call
            $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0; // seconds per call
            $total_campaign = (int)$row['gtcs'] + (int)$row['gtpy'] + (int)$row['gtet'] + (int)$row['gtdc'] + (int)$row['gtrf'];

            $data[] = [
                'agent_name'                 => $row['agent_name'],
                'inb_call_count'             => $inb_calls,
                'inb_call_count_duration'    => $sum_dur,      // raw total seconds (for weighted avg)
                'aht'                        => $aht,          // per-agent avg (display)
                'gtcs'                       => (int)$row['gtcs'],
                'gtpy'                       => (int)$row['gtpy'],
                'gtet'                       => (int)$row['gtet'],
                'gtdc'                       => (int)$row['gtdc'],
                'gtrf'                       => (int)$row['gtrf'],
                'acw'                        => $acw,          // per-agent avg (display)
                'total_acw_seconds'          => $sum_acw,      // raw total seconds (for weighted avg)
                'total_campaign'             => $total_campaign
            ];
        }
    }
    return $data;
}

function format_seconds_to_hhmmss($seconds) {
    $totalSeconds = (int)round($seconds);
    $hours = intdiv($totalSeconds, 3600);
    $minutes = intdiv($totalSeconds % 3600, 60);
    $secs = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function renderTableByAgent($data, $title) {
    echo "<h2 class='section-title'>" . htmlspecialchars($title) . "</h2>";
    echo "<table>";
    echo "<thead>
        <tr>
            <th>Agent Name</th>
            <th>GTCS</th>
            <th>GTPY</th>
            <th>GTET</th>
            <th>GTDC</th>
            <th>GTRF</th>
            <th>Total Calls</th>
            <th>AHT</th>
            <th>ACW</th>
        </tr>
    </thead><tbody>";

    if (count($data) === 0) {
        echo "<tr><td colspan='9' class='no-data'>No data available for this period</td></tr>";
    } else {
        // Grand total accumulators (for weighted averages)
        $sum_gtcs = 0;
        $sum_gtpy = 0;
        $sum_gtet = 0;
        $sum_gtdc = 0;
        $sum_gtrf = 0;
        $sum_total_calls = 0;

        $sum_total_call_duration = 0; // seconds
        $sum_total_acw_time      = 0; // seconds
        $sum_total_calls_for_avg = 0; // calls

        foreach ($data as $row) {
            $aht_seconds = (int)$row['aht']; // per-agent avg
            $acw_seconds = (int)$row['acw']; // per-agent avg

            $inb_call_count          = (int)$row['inb_call_count'];
            $total_acw_value_seconds = (int)$row['total_acw_seconds'];       // raw total ACW seconds
            $inb_call_dur_seconds    = (int)$row['inb_call_count_duration'];  // raw total AHT seconds

            // Accumulate sums
            $sum_gtcs        += (int)$row['gtcs'];
            $sum_gtpy        += (int)$row['gtpy'];
            $sum_gtet        += (int)$row['gtet'];
            $sum_gtdc        += (int)$row['gtdc'];
            $sum_gtrf        += (int)$row['gtrf'];
            $sum_total_calls += (int)$row['total_campaign'];

            if ($inb_call_count > 0) {
                $sum_total_call_duration += $inb_call_dur_seconds;
                $sum_total_acw_time      += $total_acw_value_seconds;
                $sum_total_calls_for_avg += $inb_call_count;
            }

            // Traffic light for AHT
            if      ($aht_seconds < 300) $aht_color = 'background-color: #ccffcc;';
            elseif  ($aht_seconds < 360) $aht_color = 'background-color: #fff6cc;';
            else                         $aht_color = 'background-color: #ffd6d6;';

            echo "<tr>
                <td><strong>" . htmlspecialchars($row['agent_name']) . "</strong></td>
                <td>{$row['gtcs']}</td>
                <td>{$row['gtpy']}</td>
                <td>{$row['gtet']}</td>
                <td>{$row['gtdc']}</td>
                <td>{$row['gtrf']}</td>
                <td><strong>{$row['total_campaign']}</strong></td>
                <td style='$aht_color'>" . format_seconds_to_hhmmss($aht_seconds) . "</td>
                <td>" . format_seconds_to_hhmmss($acw_seconds) . "</td>
            </tr>";
        }

        // Weighted averages for Grand Total
        $avg_aht = ($sum_total_calls_for_avg > 0) ? round($sum_total_call_duration / $sum_total_calls_for_avg) : 0;
        $avg_acw = ($sum_total_calls_for_avg > 0) ? round($sum_total_acw_time      / $sum_total_calls_for_avg) : 0;

        echo "<tr style='font-weight:bold; background-color:#ffe680;'>
            <td>Grand Total</td>
            <td>$sum_gtcs</td>
            <td>$sum_gtpy</td>
            <td>$sum_gtet</td>
            <td>$sum_gtdc</td>
            <td>$sum_gtrf</td>
            <td>$sum_total_calls</td>
            <td>" . format_seconds_to_hhmmss($avg_aht) . "</td>
            <td>" . format_seconds_to_hhmmss($avg_acw) . "</td>
        </tr>";
    }
    echo "</tbody></table>";
}

// Fetch data for each day range including agent filter (now grouped by agent)
$data_1_10   = fetchDataByAgent($mysqli, $startDate, $endDate, 1, 10,  $selected_agent);
$data_11_20  = fetchDataByAgent($mysqli, $startDate, $endDate, 11, 20, $selected_agent);
$data_21_end = fetchDataByAgent($mysqli, $startDate, $endDate, 21, 31, $selected_agent);
$data_total  = fetchDataByAgent($mysqli, $startDate, $endDate, null, null, $selected_agent);
?>
<!DOCTYPE html>
<html>
<head>
    <title>After Sales Call Metrics By Agent</title>
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; color: var(--text); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 25px 0; margin-bottom: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; }
        h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .subtitle { font-size: 16px; opacity: 0.9; margin-top: 8px; }
        select#agent_name { padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; transition: border-color 0.3s; height: 40px; background: #fff; }
        select#agent_name:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2); }
        .filter-section { background-color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; }
        .filter-group { display: flex; flex-direction: column; }
        label { font-weight: 500; margin-bottom: 5px; color: var(--text-light); font-size: 14px; }
        input[type="date"] { padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        input[type="date"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2); }
        button { background-color: var(--primary); color: #000; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; align-self: flex-end; }
        button:hover { background-color: var(--primary-dark); transform: translateY(-1px); }
        .data-section { margin-bottom: 40px; }
        .section-title { font-size: 20px; font-weight: 600; color: var(--text); margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid var(--primary); display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        th, td { padding: 12px 15px; text-align: center; border: 1px solid var(--border); }
        th { background-color: var(--primary); color: #000; font-weight: 600; text-transform: uppercase; font-size: 13px; }
        tr:nth-child(even) { background-color: var(--primary-very-light); }
        tr:hover { background-color: var(--primary-light); }
        .no-data { text-align: center; padding: 20px; color: var(--text-light); font-style: italic; }
        .date-range { font-size: 14px; color: var(--text-light); margin-bottom: 20px; font-style: italic; }
        @media (max-width: 768px) {
            .filter-form { flex-direction: column; align-items: stretch; }
            button { align-self: stretch; }
            table { display: block; overflow-x: auto; }
        }
        #agentModal { display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto; }
        #agentModal .modal-content { background: white; margin: 10% auto; padding: 20px; width: 90%; max-width: 900px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); position: relative; }
        #agentModal .close-btn { position: absolute; right: 15px; top: 10px; font-size: 28px; font-weight: bold; color: #333; cursor: pointer; }
        #agentModal h3 { margin-top: 0; margin-bottom: 15px; }
        #agentModal table { margin-top: 10px; }
        #agentModal .no-data { font-style: italic; color: #666; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>After-Sales Monthly Call Performance Report</h1>
        <div class="subtitle">Performance metrics grouped by agent.</div>
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
        renderTableByAgent($data_1_10,  "Day 1–10 Performance");
        renderTableByAgent($data_11_20, "Day 11–20 Performance");
        renderTableByAgent($data_21_end,"Day 21–End of Month Performance");
        renderTableByAgent($data_total, "Total Performance (Full Date Range)");
        ?>
    </div>
</div>
</body>
</html>
