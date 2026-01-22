<?php
/**
 * Template Name: After Sales Call Metrics agent wise
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

// Clean output buffers to prevent conflicts
while (ob_get_level()) {
    ob_end_clean();
}

// Date filter and agent filter from GET params
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Helper function to fetch agent names from API
function fetchAgentNamesFromAPI($location = 'BOM', $status = 'active') {
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public';
    $apiEndpoint = '/v1/agent-codes-agent-names';
    
    // Build query parameters
    $params = [
        'location' => $location,
        'status' => $status
    ];
    
    $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '?' . http_build_query($params);
    
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

// Fetch distinct agents for dropdown from API
$agents = fetchAgentNamesFromAPI('BOM', 'active');

// Helper function to fetch productivity agent metrics from API
function fetchProductivityAgentMetricsFromAPI($start_date, $end_date, $start_day = null, $end_day = null, $agent = '') {
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public';
    $apiEndpoint = '/v1/after-sale-productivity-agent-metrics';
    
    // Build query parameters
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if ($start_day !== null && $end_day !== null) {
        $params['start_day'] = (int)$start_day;
        $params['end_day'] = (int)$end_day;
    }
    
    if ($agent !== '') {
        $params['agent_name'] = $agent;
    }
    
    $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("Productivity Agent Metrics API Error: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("Productivity Agent Metrics API HTTP Error: Status code " . $httpCode);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Productivity Agent Metrics API JSON Error: " . json_last_error_msg());
            return [];
        }
        
        // Handle different response formats
        $results = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $results = $data['data'];
        } elseif (isset($data['results']) && is_array($data['results'])) {
            $results = $data['results'];
        } elseif (isset($data['metrics']) && is_array($data['metrics'])) {
            $results = $data['metrics'];
        } elseif (is_array($data)) {
            $results = $data;
        }
        
        // Process and format the data to match expected structure
        $formattedData = [];
        foreach ($results as $row) {
            $inb_calls = isset($row['inb_call_count']) ? (int)$row['inb_call_count'] : 0;
            $sum_dur   = isset($row['inb_call_count_duration']) ? (int)$row['inb_call_count_duration'] : 0; // seconds
            $sum_acw   = isset($row['total_acw_seconds']) ? (int)$row['total_acw_seconds'] : 0; // seconds

            $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0; // seconds per call
            $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0; // seconds per call
            $gtcs = isset($row['gtcs']) ? (int)$row['gtcs'] : 0;
            $gtpy = isset($row['gtpy']) ? (int)$row['gtpy'] : 0;
            $gtet = isset($row['gtet']) ? (int)$row['gtet'] : 0;
            $gtdc = isset($row['gtdc']) ? (int)$row['gtdc'] : 0;
            $gtrf = isset($row['gtrf']) ? (int)$row['gtrf'] : 0;
            $total_campaign = $gtcs + $gtpy + $gtet + $gtdc + $gtrf;

            $formattedData[] = [
                'agent_name'                 => $row['agent_name'] ?? '',
                'inb_call_count'             => $inb_calls,
                'inb_call_count_duration'    => $sum_dur,      // raw total seconds (for weighted avg)
                'aht'                        => $aht,          // per-agent avg (display)
                'gtcs'                       => $gtcs,
                'gtpy'                       => $gtpy,
                'gtet'                       => $gtet,
                'gtdc'                       => $gtdc,
                'gtrf'                       => $gtrf,
                'acw'                        => $acw,          // per-agent avg (display)
                'total_acw_seconds'          => $sum_acw,      // raw total seconds (for weighted avg)
                'total_campaign'             => $total_campaign
            ];
        }
        
        return $formattedData;
    } catch (Exception $e) {
        error_log("Productivity Agent Metrics API Exception: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch data grouped by AGENT for the date range, optional day range & agent filter.
 * Now uses API instead of SQL query.
 */
function fetchDataByAgent($startDate, $endDate, $startDay = null, $endDay = null, $agent = '') {
    // Fetch data from API instead of SQL
    return fetchProductivityAgentMetricsFromAPI($startDate, $endDate, $startDay, $endDay, $agent);
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
$data_1_10   = fetchDataByAgent($startDate, $endDate, 1, 10,  $selected_agent);
$data_11_20  = fetchDataByAgent($startDate, $endDate, 11, 20, $selected_agent);
$data_21_end = fetchDataByAgent($startDate, $endDate, 21, 31, $selected_agent);
$data_total  = fetchDataByAgent($startDate, $endDate, null, null, $selected_agent);
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
