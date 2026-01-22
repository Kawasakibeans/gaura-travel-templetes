<?php
/**
 * Template Name: After Sales Call Metrics (Date Grouped)
 * Template Post Type: post, page
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

$apiBase = defined('API_BASE_URL') ? rtrim(API_BASE_URL, '/') : 'https://gauratravel.com.au/api';

// Date filter and agent filter from GET params
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

function api_get_json($endpoint, $query = [])
{
    $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new Exception("API call failed: $err");
    }
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON from API ($code): " . json_last_error_msg());
    }
    if ($code < 200 || $code >= 300) {
        $msg = $decoded['message'] ?? "HTTP $code";
        throw new Exception("API error: $msg");
    }
    return $decoded;
}

// Fetch distinct agents for dropdown via API
$agents = [];
try {
    $respAgents = api_get_json(
        $apiBase . '/agent-codes-agent-names',
        ['location' => 'BOM', 'status' => 'active', 'limit' => 500]
    );
    $agentData = $respAgents['data'] ?? $respAgents['agents'] ?? [];
    foreach ($agentData as $row) {
        if (isset($row['agent_name'])) {
            $agents[] = $row['agent_name'];
        } elseif (is_string($row)) {
            $agents[] = $row;
        }
    }
    sort($agents);
} catch (Exception $e) {
    // Fallback: empty list; UI will show All Agents only
    $agents = [];
}

/**
 * Fetch data grouped by date for a given date range and optional day range & agent filter
 */
function fetchDataByDate($apiBase, $startDate, $endDate, $startDay = null, $endDay = null, $agent = '') {
    // Pull data from API and locally aggregate by date to mimic the original SQL
    $queryParams = [
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'location'   => 'BOM',
        'status'     => 'active',
        'limit'      => 5000,
        'offset'     => 0,
    ];
    if (!empty($agent)) {
        $queryParams['agent_name'] = $agent;
    }

    // Call main API for date-grouped data - API now returns all required fields
    $resp = api_get_json($apiBase . '/after-sale-productivity-report', $queryParams);
    $rows = $resp['data'] ?? $resp['records'] ?? $resp['report'] ?? [];

    $byDate = [];
    foreach ($rows as $row) {
        $date = $row['date'] ?? $row['call_date'] ?? null;
        if (!$date) {
            continue;
        }
        // Day filter if provided
        $day = (int)date('j', strtotime($date));
        if ($startDay !== null && $endDay !== null) {
            if ($day < $startDay || $day > $endDay) {
                continue;
            }
        }

        $key = $date;
        if (!isset($byDate[$key])) {
            $byDate[$key] = [
                'date' => $date,
                'inb_call_count' => 0,
                'inb_call_count_duration' => 0,
                'sales_aht' => 0,
                'gtmd_aht_raw' => 0,
                'gtcs' => 0,
                'gtpy' => 0,
                'gtet' => 0,
                'gtdc' => 0,
                'gtrf' => 0,
                'gtib' => 0,
                'gtmd' => 0,
                'total_acw_raw' => 0,
            ];
        }

        // Normalize fields from API (fallbacks for naming)
        // API returns: inbound_calls, inbound_calls_aht, outbound_calls, outbound_calls_aht, etc.
        $inb_calls = (int)($row['inb_call_count'] ?? $row['inbound_calls'] ?? 0);
        $inb_aht = (float)($row['inbound_calls_aht'] ?? $row['inb_call_count_aht'] ?? 0);
        
        $byDate[$key]['inb_call_count'] += $inb_calls;
        
        // Calculate duration from AHT: duration = AHT * count
        // If API provides duration directly, use it; otherwise calculate from AHT
        if (isset($row['inb_call_count_duration']) || isset($row['inbound_duration'])) {
            $byDate[$key]['inb_call_count_duration'] += (int)($row['inb_call_count_duration'] ?? $row['inbound_duration'] ?? 0);
        } else {
            // Calculate from AHT: AHT is average, so total duration = AHT * count
            $byDate[$key]['inb_call_count_duration'] += (int)round($inb_aht * $inb_calls);
        }
        
        // GT call types, Sales AHT, GTMD AHT, and Total ACW - Get from API response
        $byDate[$key]['gtcs'] += (int)($row['gtcs'] ?? 0);
        $byDate[$key]['gtpy'] += (int)($row['gtpy'] ?? 0);
        $byDate[$key]['gtet'] += (int)($row['gtet'] ?? 0);
        $byDate[$key]['gtdc'] += (int)($row['gtdc'] ?? 0);
        $byDate[$key]['gtrf'] += (int)($row['gtrf'] ?? 0);
        $byDate[$key]['gtib'] += (int)($row['gtib'] ?? 0);
        $byDate[$key]['gtmd'] += (int)($row['gtmd'] ?? 0);
        
        // Sales AHT (GTIB duration) and GTMD AHT from API
        $byDate[$key]['sales_aht'] += (int)($row['sales_aht'] ?? 0);
        $byDate[$key]['gtmd_aht_raw'] += (int)($row['gtmd_aht'] ?? 0);
        
        // Total ACW from API (already in seconds)
        $byDate[$key]['total_acw_raw'] += (int)($row['total_acw_seconds'] ?? 0);
    }

    $data = [];
    foreach ($byDate as $dayRow) {
        $inb_calls = (int)$dayRow['inb_call_count'];
        $gtib_calls = (int)$dayRow['gtib'];
        $gtmd_calls = (int)$dayRow['gtmd'];
        $sum_dur   = (int)$dayRow['inb_call_count_duration']; // seconds
        $sum_dur_sales   = (int)$dayRow['sales_aht']; // seconds
        $sum_dur_sales_gtmd   = (int)$dayRow['gtmd_aht_raw']; // seconds
        $sum_acw   = (int)$dayRow['total_acw_raw'];       // seconds

        $total_campaign = (int)$dayRow['gtcs'] + (int)$dayRow['gtpy'] + (int)$dayRow['gtet'] + (int)$dayRow['gtdc'] + (int)$dayRow['gtrf']+ (int)$dayRow['gtib']+ (int)$dayRow['gtmd'];
        $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0; // seconds per call
        $gtib_aht = ($gtib_calls > 0) ? round($sum_dur_sales / $gtib_calls) : 0; // seconds per call
        $gtmd_aht = ($gtmd_calls > 0) ? round($sum_dur_sales_gtmd / $gtmd_calls) : 0; // seconds per call
        $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0; // seconds per call

        $data[] = [
            'date'                      => $dayRow['date'],
            'inb_call_count'            => $inb_calls,
            'inb_call_count_duration'   => $sum_dur,  // raw total seconds for weighted avg
            'sales_aht'                 => $sum_dur_sales,  // raw total seconds for weighted avg
            'gtmd_aht'                  => $sum_dur_sales_gtmd,  // raw total seconds for weighted avg
            'aht'                       => $aht,      // per-day average (display)
            'gtib_aht'                  => $gtib_aht,      // per-day average (display)
            'gtmd_aht_percall'          => $gtmd_aht,      // per-day average (display)
            'gtcs'                      => (int)$dayRow['gtcs'],
            'gtpy'                      => (int)$dayRow['gtpy'],
            'gtet'                      => (int)$dayRow['gtet'],
            'gtdc'                      => (int)$dayRow['gtdc'],
            'gtrf'                      => (int)$dayRow['gtrf'],
            'gtib'                      => (int)$dayRow['gtib'],
            'gtmd'                      => (int)$dayRow['gtmd'],
            'acw'                       => $acw,      // per-day average (display)
            'total_acw'                 => $sum_acw,  // raw total seconds for weighted avg
            'total_campaign'            => $total_campaign
        ];
    }

    // Sort by date ascending
    usort($data, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    return $data;
}

// Fetch data for each day range including agent filter
$data_1_10   = fetchDataByDate($apiBase, $startDate, $endDate, 1, 10,  $selected_agent);
$data_11_20  = fetchDataByDate($apiBase, $startDate, $endDate, 11, 20, $selected_agent);
$data_21_end = fetchDataByDate($apiBase, $startDate, $endDate, 21, 31, $selected_agent);
$data_total  = fetchDataByDate($apiBase, $startDate, $endDate, null, null, $selected_agent);

/** AJAX: agent data for a specific date with agent filter
 *  NOTE: parse total_acw to seconds here too, and return averages per agent.
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'agent_data' && isset($_GET['date'])) {
    $date = $_GET['date'];
    $ajax_agent = $_GET['agent_name'] ?? '';

    try {
        $queryParams = [
            'start_date' => $date,
            'end_date'   => $date,
            'location'   => 'BOM',
            'status'     => 'active',
            'limit'      => 1000,
            'offset'     => 0,
        ];
        if (!empty($ajax_agent)) {
            $queryParams['agent_name'] = $ajax_agent;
        }

        // Use the by-agent endpoint for AJAX requests to get agent-level data - API now returns all required fields
        $resp = api_get_json($apiBase . '/after-sale-productivity-report-by-agent', $queryParams);
        $rows = $resp['data'] ?? $resp['records'] ?? $resp['report'] ?? [];

        $byAgent = [];
        foreach ($rows as $row) {
            // API returns data already grouped by agent_name
            $agentName = $row['agent_name'] ?? $row['agent'] ?? 'Unknown';
            if (!isset($byAgent[$agentName])) {
                $byAgent[$agentName] = [
                    'agent_name' => $agentName,
                    'inb_call_count' => 0,
                    'inb_call_count_duration' => 0,
                    'sales_aht' => 0,
                    'gtmd_aht_raw' => 0,
                    'gtcs' => 0,
                    'gtpy' => 0,
                    'gtet' => 0,
                    'gtdc' => 0,
                    'gtrf' => 0,
                    'gtib' => 0,
                    'gtmd' => 0,
                    'total_acw_raw' => 0,
                ];
            }

        // API returns: inbound_calls, inbound_calls_aht, etc.
        $agent_inb_calls = (int)($row['inb_call_count'] ?? $row['inbound_calls'] ?? 0);
        $agent_inb_aht = (float)($row['inbound_calls_aht'] ?? $row['inb_call_count_aht'] ?? 0);
        
        $byAgent[$agentName]['inb_call_count'] += $agent_inb_calls;
        
        // Calculate duration from AHT if not provided directly
        if (isset($row['inb_call_count_duration']) || isset($row['inbound_duration'])) {
            $byAgent[$agentName]['inb_call_count_duration'] += (int)($row['inb_call_count_duration'] ?? $row['inbound_duration'] ?? 0);
        } else {
            // Calculate from AHT: duration = AHT * count
            $byAgent[$agentName]['inb_call_count_duration'] += (int)round($agent_inb_aht * $agent_inb_calls);
        }
        
        // GT call types, Sales AHT, GTMD AHT, and Total ACW - Get from API response
        $byAgent[$agentName]['gtcs'] += (int)($row['gtcs'] ?? 0);
        $byAgent[$agentName]['gtpy'] += (int)($row['gtpy'] ?? 0);
        $byAgent[$agentName]['gtet'] += (int)($row['gtet'] ?? 0);
        $byAgent[$agentName]['gtdc'] += (int)($row['gtdc'] ?? 0);
        $byAgent[$agentName]['gtrf'] += (int)($row['gtrf'] ?? 0);
        $byAgent[$agentName]['gtib'] += (int)($row['gtib'] ?? 0);
        $byAgent[$agentName]['gtmd'] += (int)($row['gtmd'] ?? 0);
        
        // Sales AHT (GTIB duration) and GTMD AHT from API
        $byAgent[$agentName]['sales_aht'] += (int)($row['sales_aht'] ?? 0);
        $byAgent[$agentName]['gtmd_aht_raw'] += (int)($row['gtmd_aht'] ?? 0);
        
        // Total ACW from API (already in seconds)
        $byAgent[$agentName]['total_acw_raw'] += (int)($row['total_acw_seconds'] ?? 0);
        }

        $agentsData = [];
        foreach ($byAgent as $agentRow) {
            $inb_calls = (int)$agentRow['inb_call_count'];
            $gtib_calls = (int)$agentRow['gtib'];
            $gtmd_calls = (int)$agentRow['gtmd'];
            $sum_dur   = (int)$agentRow['inb_call_count_duration'];
            $sum_dur_sales   = (int)$agentRow['sales_aht'];
            $sum_dur_sales_gtmd   = (int)$agentRow['gtmd_aht_raw'];
            $sum_acw   = (int)$agentRow['total_acw_raw'];

            $aht = ($inb_calls > 0) ? round($sum_dur / $inb_calls) : 0;
            $gtib_aht = ($gtib_calls > 0) ? round($sum_dur_sales / $gtib_calls) : 0;
            $gtmd_aht = ($gtmd_calls > 0) ? round($sum_dur_sales_gtmd / $gtmd_calls) : 0;
            $acw = ($inb_calls > 0) ? round($sum_acw / $inb_calls) : 0;

            $agentsData[] = [
                'agent_name'              => $agentRow['agent_name'],
                'inb_call_count'          => $inb_calls,
                'inb_call_count_duration' => $sum_dur,
                'sales_aht'               => $sum_dur_sales,
                'gtmd_duration'           => $sum_dur_sales_gtmd,
                'aht'                     => $aht,
                'aht_gtib'                => $gtib_aht,
                'aht_gtmd'                => $gtmd_aht,
                'gtcs'                    => (int)$agentRow['gtcs'],
                'gtpy'                    => (int)$agentRow['gtpy'],
                'gtet'                    => (int)$agentRow['gtet'],
                'gtdc'                    => (int)$agentRow['gtdc'],
                'gtrf'                    => (int)$agentRow['gtrf'],
                'gtib'                    => (int)$agentRow['gtib'],
                'gtmd'                    => (int)$agentRow['gtmd'],
                'acw'                     => $acw,
                'total_campaign'          => (int)$agentRow['gtcs'] + (int)$agentRow['gtpy'] + (int)$agentRow['gtet'] + (int)$agentRow['gtdc'] + (int)$agentRow['gtrf'] + (int)$agentRow['gtib'] + (int)$agentRow['gtmd'],
            ];
        }

    } catch (Exception $e) {
        $agentsData = [];
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
            $gtmd_aht_seconds = (int)$row['gtmd_aht_percall']; // per-day average
            $acw_seconds = (int)$row['acw']; // per-day average

            $inb_call_count          = (int)$row['inb_call_count'];
            $gtib_call_count          = (int)$row['gtib'];
            $gtmd_call_count          = (int)$row['gtmd'];
            $total_acw_value_seconds = (int)$row['total_acw'];                // raw total seconds
            $inb_call_dur_seconds    = (int)$row['inb_call_count_duration'];  // raw total seconds
            $inb_call_dur_seconds_sales    = (int)$row['sales_aht'];  // raw total seconds
            $inb_call_dur_seconds_sales_gtmd    = (int)$row['gtmd_aht'];  // raw total seconds (stored as gtmd_aht in data array)

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
