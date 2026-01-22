<?php
/**
 * Template Name: After Sales Ticketed Review agent wise
 * Template Post Type: post, page
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API Configuration
$apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';

// DB connection
require_once(dirname(__FILE__, 5) . '/wp-config.php');
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Filters from GET
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$selected_agent = $_GET['agent_name'] ?? '';

// Validate dates (basic)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-t');

// Get all unique agents for the dropdown
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

// API Helper Functions
function fetchAgentTicketSummaryFromAPI(string $startDate, string $endDate, ?string $agentName = null): array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/after-sale-productivity-agent-ticket-summary';
    
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    
    if ($agentName !== null && $agentName !== '') {
        $params['agent_name'] = $agentName;
    }
    
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for after-sale-productivity-agent-ticket-summary: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for after-sale-productivity-agent-ticket-summary: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for after-sale-productivity-agent-ticket-summary: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        } elseif (isset($data['records']) && is_array($data['records'])) {
            return $data['records'];
        } elseif (is_array($data)) {
            return $data;
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for after-sale-productivity-agent-ticket-summary: " . $e->getMessage());
        return [];
    }
}

function fetchAgentTicketSummaryActiveFromAPI(string $startDate, string $endDate, ?string $agentName = null): array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/after-sale-productivity-agent-ticket-summary-active';
    
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    
    if ($agentName !== null && $agentName !== '') {
        $params['agent_name'] = $agentName;
    }
    
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for after-sale-productivity-agent-ticket-summary-active: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for after-sale-productivity-agent-ticket-summary-active: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for after-sale-productivity-agent-ticket-summary-active: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        } elseif (isset($data['records']) && is_array($data['records'])) {
            return $data['records'];
        } elseif (is_array($data)) {
            return $data;
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for after-sale-productivity-agent-ticket-summary-active: " . $e->getMessage());
        return [];
    }
}

// Helper class to mimic mysqli_result for compatibility
class ArrayResult {
    private $data;
    private $position = 0;
    public $num_rows;
    
    public function __construct(array $data) {
        $this->data = $data;
        $this->num_rows = count($data);
    }
    
    public function fetch_assoc() {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return null;
    }
    
    // Reset position for re-iteration
    public function data_seek($position = 0) {
        $this->position = $position;
    }
}

// Helper: fetch aggregated data by date for given range & agent filter
function fetchDateAggregatedData($mysqli, $start, $end, $agentName = '') {
    $data = fetchAgentTicketSummaryFromAPI($start, $end, $agentName);
    return new ArrayResult($data);
}

// Helper: fetch aggregated data by agent for given range & agent filter
function fetchAgentAggregatedData($mysqli, $start, $end, $agentName = '') {
    $data = fetchAgentTicketSummaryActiveFromAPI($start, $end, $agentName);
    return new ArrayResult($data);
}

// Date ranges for tables within filter range
$startTimestamp = strtotime($startDate);
$endTimestamp = strtotime($endDate);
$startMonth = date('Y-m', $startTimestamp);
$endMonth = date('Y-m', $endTimestamp);

// Define ranges, but clipped to $startDate and $endDate to avoid showing outside filter
function clipDateRange($start, $end, $filterStart, $filterEnd) {
    if ($start < $filterStart) $start = $filterStart;
    if ($end > $filterEnd) $end = $filterEnd;
    if ($start > $end) return null;
    return [$start, $end];
}

// Only show period breakdown if date range is within a single month
$showPeriods = ($startMonth === $endMonth);

$data1 = null;
$data2 = null;
$data3 = null;

if ($showPeriods) {
    // Get the last day of month for start month
    $lastDayOfMonth = date('t', $startTimestamp);
    $monthStartStr = date('Y-m', $startTimestamp);
    
    // Define the 3 ranges for the month (1–10, 11–20, 21–end)
    $range1 = clipDateRange("$monthStartStr-01", "$monthStartStr-10", $startDate, $endDate);
    $range2 = clipDateRange("$monthStartStr-11", "$monthStartStr-20", $startDate, $endDate);
    $range3 = clipDateRange("$monthStartStr-21", "$monthStartStr-$lastDayOfMonth", $startDate, $endDate);
    
    // Fetch data for each range (skip if null)
    $data1 = $range1 ? fetchAgentAggregatedData($mysqli, $range1[0], $range1[1], $selected_agent) : null;
    $data2 = $range2 ? fetchAgentAggregatedData($mysqli, $range2[0], $range2[1], $selected_agent) : null;
    $data3 = $range3 ? fetchAgentAggregatedData($mysqli, $range3[0], $range3[1], $selected_agent) : null;
}

$dataAll = fetchAgentAggregatedData($mysqli, $startDate, $endDate, $selected_agent);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ticketed Metrics Dashboard</title>
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
        
        .highlight {
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

        /* Clickable date style - blue */
        .clickable-date {
            cursor: pointer;
            color: #004080;;
            text-decoration: underline;
        }
        .clickable-date:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Ticketed Metrics Dashboard</h1>
            <div class="subtitle">Ticket issuance performance by date range</div>
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
                        <option value="">All Agents</option>
                        <?php
                        foreach ($all_agents as $agent) {
                            $selected = ($selected_agent === $agent) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($agent) . "\" $selected>" . htmlspecialchars($agent) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit">Apply Filter</button>
            </form>
        </div>
        
        <div class="date-range">
            Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
        </div>
        
        <div class="data-section">
            <?php
            // Render tables grouped by agent
            function renderAgentTable($title, $data) {
                echo "<h2 class='section-title'>$title</h2>";
                echo "<table>
                        <thead>
                            <tr>
                                <th>Agent Name</th>
                                <th>FIT Ticketed</th>
                                <th>GDeal Ticketed</th>
                                <th>GKT IATA</th>
                                <th>IFN IATA</th>
                                <th>CTG</th>
                                <th>Gilpin</th>
                                <th>CCUVS32NQ</th>
                                <th>MELA821CV</th>
                                <th>I5FC</th>
                                <th>MELA828FN</th>
                                <th>CCUVS32MV</th>
                                <th>Ticket Issued</th>
                            </tr>
                        </thead>
                        <tbody>";
                $grand_fit = $grand_gdeal = $grand_gkt_iata = $grand_ifn_iata = $grand_ctg = $grand_gilpin = $grand_CCUVS32NQ = $grand_MELA821CV = $grand_I5FC = $grand_MELA828FN = $grand_CCUVS32MV = $grand_ticket = 0;
                if ($data && $data->num_rows > 0) {
                    // Reset position if it's an ArrayResult
                    if (is_object($data) && method_exists($data, 'data_seek')) {
                        $data->data_seek(0);
                    }
                    while ($row = $data->fetch_assoc()) {
                        if ($row === null) break;
                        echo "<tr>
                                <td>{$row['agent_name']}</td>
                                <td>{$row['fit_ticketed']}</td>
                                <td>{$row['gdeal_ticketed']}</td>
                                <td>{$row['gkt_iata']}</td>
                                <td>{$row['ifn_iata']}</td>
                                <td>{$row['ctg']}</td>
                                <td>{$row['gilpin']}</td>
                                <td>{$row['CCUVS32NQ']}</td>
                                <td>{$row['MELA821CV']}</td>
                                <td>{$row['I5FC']}</td>
                                <td>{$row['MELA828FN']}</td>
                                <td>{$row['CCUVS32MV']}</td>
                                <td>{$row['ticket_issued']}</td>
                              </tr>";
                        $grand_fit += $row['fit_ticketed'];
                        $grand_gdeal += $row['gdeal_ticketed'];
                        $grand_gkt_iata += $row['gkt_iata'];
                        $grand_ifn_iata += $row['ifn_iata'];
                        $grand_ctg += $row['ctg'];
                        $grand_gilpin += $row['gilpin'];
                        $grand_CCUVS32NQ += $row['CCUVS32NQ'];
                        $grand_MELA821CV += $row['MELA821CV'];
                        $grand_I5FC += $row['I5FC'];
                        $grand_MELA828FN += $row['MELA828FN'];
                        $grand_CCUVS32MV += $row['CCUVS32MV'];
                         $grand_ticket += $row['ticket_issued'];
                    }
                    echo "<tr style='font-weight:bold; background-color:#eaeaea;' >
                            <td>Grand Total</td>
                            <td>$grand_fit</td>
                            <td>$grand_gdeal</td>
                            <td>$grand_gkt_iata</td>
                            <td>$grand_ifn_iata</td>
                            <td>$grand_ctg</td>
                            <td>$grand_gilpin</td>
                            <td>$grand_CCUVS32NQ</td>
                            <td>$grand_MELA821CV</td>
                            <td>$grand_I5FC</td>
                            <td>$grand_MELA828FN</td>
                            <td>$grand_CCUVS32MV</td>
                            <td>$grand_ticket</td>
                          </tr>";
                } else {
                    echo "<tr><td colspan='4' class='no-data'>No data available for this period</td></tr>";
                }
                echo "</tbody></table>";
            }

            if ($showPeriods) {
                if ($data1) renderAgentTable("Day 1–10 Performance", $data1);
                if ($data2) renderAgentTable("Day 11–20 Performance", $data2);
                if ($data3) renderAgentTable("Day 21–End of Month Performance", $data3);
            }
            renderAgentTable("Total for Selected Date Range", $dataAll);
            ?>
        </div>
    </div>
</body>
</html>
