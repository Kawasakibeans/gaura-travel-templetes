<?php
// At the very top
date_default_timezone_set("Australia/Sydney");
// ✅ Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ MySQL connection
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

function safeQuery($conn, $query, $label) {
    $result = $conn->query($query);
    if (!$result) die("❌ Query failed in [$label]: " . $conn->error);
    return $result->fetch_assoc();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 day'));
$end_date = $_GET['end_date'] ?? '2025-06-30';
$team_filter = $_GET['team_filter'] ?? '';
$manager_filter = $_GET['manager_filter'] ?? '';

date('Y-m-d', strtotime('-1 day'));

// Criteria values
$keyMetricsQuery = "
SELECT
    SUM(combined.pax) AS total_pax,
    SUM(combined.gdeals) AS gdeals,
    SUM(combined.fit) AS fit,
    SUM(combined.gtib_count) AS total_gtib,
    ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.pax) / SUM(combined.gtib_count), 0), 4) AS conversion,
    ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 0), 4) AS fcs,
    SEC_TO_TIME(ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.rec_duration) / SUM(combined.gtib_count), 0))) AS AHT
FROM (
    SELECT a.agent_name, 0 pax, 0 fit, 0 pif, 0 gdeals, a.team_name, a.gtib_count, a.new_sale_made_count, a.non_sale_made_count, a.rec_duration
    FROM wpk4_backend_agent_inbound_call a
    LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
    WHERE a.call_date = '$start_date'
    UNION ALL
    SELECT a.agent_name, a.pax, a.fit, a.pif, a.gdeals, a.team_name, 0, 0, 0, 0
    FROM wpk4_backend_agent_booking a
    LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
    WHERE date(a.order_date) = '$start_date'
) AS combined
";
$criteria = safeQuery($conn, $keyMetricsQuery, 'Key Metrics');

// Convert AHT to seconds for comparison
$aht_parts = explode(':', $criteria['AHT'] ?? '00:00:00');
$aht_seconds = ($aht_parts[0] ?? 0) * 3600 + ($aht_parts[1] ?? 0) * 60 + ($aht_parts[2] ?? 0);

// Build SQL query with filters
$sql = "SELECT * FROM wpk4_agent_productivity_report_June_2025 WHERE Date = '$start_date'";
if ($team_filter) $sql .= " AND Team_name = '$team_filter'";
if ($manager_filter) $sql .= " AND SM_name = '$manager_filter'";
$result = $conn->query($sql);

// Get unique teams and managers for filters
$teams = [];
$teams_result = $conn->query("SELECT DISTINCT Team_name FROM wpk4_agent_productivity_report_June_2025");
while ($row = $teams_result->fetch_assoc()) $teams[] = $row['Team_name'];

$managers = [];
$managers_result = $conn->query("SELECT DISTINCT SM_name FROM wpk4_agent_productivity_report_June_2025");
while ($row = $managers_result->fetch_assoc()) $managers[] = $row['SM_name'];

// Process agent data
$agents = [];
$team_stats = [];
$manager_stats = [];
$company_stats = [
    'total_pax' => 0, 'total_pax_pif' => 0, 'total_quotes' => 0,
    'total_fcs' => 0, 'total_aht' => 0, 'count' => 0
];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['Total_pax'] = (int)($row['Total_pax'] ?? 0);
        $row['Total_pax_PIF'] = (int)($row['Total_pax_PIF'] ?? 0);
        $row['gtib'] = (int)($row['GTIB_call_count'] ?? 0);
        $row['Total_quotes'] = (int)($row['Total_quotes'] ?? 0);

        $row['pax_conv_percent'] = ($row['gtib'] > 0) ? round(($row['Total_pax'] / $row['gtib']) * 100, 2) : 0;
        $row['FCS'] = isset($row['FCS']) ? (float)str_replace('%', '', $row['FCS']) : 0;

        if (!empty($row['AHT'])) {
            // Remove microseconds and safely convert to seconds
            $clean_aht = explode('.', $row['AHT'])[0]; // "00:26:15.000000" → "00:26:15"
            $agent_aht_parts = explode(':', $clean_aht);
        
            $hours = isset($agent_aht_parts[0]) ? (int)$agent_aht_parts[0] : 0;
            $minutes = isset($agent_aht_parts[1]) ? (int)$agent_aht_parts[1] : 0;
            $seconds = isset($agent_aht_parts[2]) ? (int)$agent_aht_parts[2] : 0;
        
            $row['AHT_seconds'] = ($hours * 3600) + ($minutes * 60) + $seconds;
        } else {
            $row['AHT_seconds'] = 0;
        }


        // Categorize agent
        $row['category'] = 'neutral';
        if ($row['pax_conv_percent'] > ($criteria['conversion'] * 100)) $row['category'] = 'above';
        elseif ($row['pax_conv_percent'] < ($criteria['conversion'] * 100)) $row['category'] = 'below';

        $agents[] = $row;

        // Aggregate team stats
        $team = $row['Team_name'];
        if (!isset($team_stats[$team])) $team_stats[$team] = ['total_pax'=>0,'total_pax_pif'=>0,'total_fcs'=>0,'total_aht'=>0,'count'=>0];
        $team_stats[$team]['total_pax'] += $row['Total_pax'];
        $team_stats[$team]['total_pax_pif'] += $row['Total_pax_PIF'];
        $team_stats[$team]['total_fcs'] += $row['FCS'];
        $team_stats[$team]['total_aht'] += $row['AHT_seconds'];
        $team_stats[$team]['count']++;

        // Aggregate manager stats
        $manager = $row['SM_name'];
        if (!isset($manager_stats[$manager])) $manager_stats[$manager] = ['total_pax'=>0,'total_pax_pif'=>0,'total_fcs'=>0,'total_aht'=>0,'count'=>0,'team'=>$team];
        $manager_stats[$manager]['total_pax'] += $row['Total_pax'];
        $manager_stats[$manager]['total_pax_pif'] += $row['Total_pax_PIF'];
        $manager_stats[$manager]['total_fcs'] += $row['FCS'];
        $manager_stats[$manager]['total_aht'] += $row['AHT_seconds'];
        $manager_stats[$manager]['count']++;

        // Aggregate company stats
        $company_stats['total_pax'] += $row['Total_pax'];
        $company_stats['total_pax_pif'] += $row['Total_pax_PIF'];
        $company_stats['total_quotes'] += $row['Total_quotes'];
        $company_stats['total_fcs'] += $row['FCS'];
        $company_stats['total_aht'] += $row['AHT_seconds'];
        $company_stats['count']++;
    }
}

// Calculate averages for teams
foreach ($team_stats as $team => $stats) {
    $team_stats[$team]['avg_pax_conv'] = $stats['total_pax'] > 0 ? round(($stats['total_pax_pif'] / $stats['total_pax']) * 100, 2) : 0;
    $team_stats[$team]['avg_fcs'] = $stats['count'] > 0 ? round($stats['total_fcs'] / $stats['count'], 2) : 0;
    $team_stats[$team]['avg_aht'] = $stats['count'] > 0 ? round($stats['total_aht'] / $stats['count']) : 0;
}

// Calculate averages for managers
foreach ($manager_stats as $manager => $stats) {
    $manager_stats[$manager]['avg_pax_conv'] = $stats['total_pax'] > 0 ? round(($stats['total_pax_pif'] / $stats['total_pax']) * 100, 2) : 0;
    $manager_stats[$manager]['avg_fcs'] = $stats['count'] > 0 ? round($stats['total_fcs'] / $stats['count'], 2) : 0;
    $manager_stats[$manager]['avg_aht'] = $stats['count'] > 0 ? round($stats['total_aht'] / $stats['count']) : 0;
}

// Calculate company averages
$company_stats['avg_pax_conv'] = $company_stats['total_pax'] > 0 ? round(($company_stats['total_pax_pif'] / $company_stats['total_pax']) * 100, 2) : 0;
$company_stats['avg_fcs'] = $company_stats['count'] > 0 ? round($company_stats['total_fcs'] / $company_stats['count'], 2) : 0;
$company_stats['avg_aht'] = $company_stats['count'] > 0 ? round($company_stats['total_aht'] / $company_stats['count']) : 0;

$conn->close();

// Function to format time
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

// Pagination for main agent table
$per_page = 60;
$total_agents = count($agents);
$total_pages = ceil($total_agents / $per_page);
$current_page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;
$offset = ($current_page - 1) * $per_page;
$paginated_agents = array_slice($agents, $offset, $per_page);

// Prepare filtered arrays for each category
$above_agents_all = array_values(array_filter($agents, fn($a) => $a['category'] === 'above'));
$neutral_agents_all = array_values(array_filter($agents, fn($a) => $a['category'] === 'neutral'));
$below_agents_all = array_values(array_filter($agents, fn($a) => $a['category'] === 'below'));

// Pagination for Top Performers (Above Criteria)
$above_per_page = 60;
$above_total = count($above_agents_all);
$above_total_pages = max(1, ceil($above_total / $above_per_page));
$above_page = isset($_GET['above_page']) ? max(1, min($above_total_pages, intval($_GET['above_page']))) : 1;
$above_offset = ($above_page - 1) * $above_per_page;
$paginated_above_agents = array_slice($above_agents_all, $above_offset, $above_per_page);

// Pagination for Neutral Performers
$neutral_per_page = 60;
$neutral_total = count($neutral_agents_all);
$neutral_total_pages = max(1, ceil($neutral_total / $neutral_per_page));
$neutral_page = isset($_GET['neutral_page']) ? max(1, min($neutral_total_pages, intval($_GET['neutral_page']))) : 1;
$neutral_offset = ($neutral_page - 1) * $neutral_per_page;
$paginated_neutral_agents = array_slice($neutral_agents_all, $neutral_offset, $neutral_per_page);

// Pagination for Below Average Performers
$below_per_page = 60;
$below_total = count($below_agents_all);
$below_total_pages = max(1, ceil($below_total / $below_per_page));
$below_page = isset($_GET['below_page']) ? max(1, min($below_total_pages, intval($_GET['below_page']))) : 1;
$below_offset = ($below_page - 1) * $below_per_page;
$paginated_below_agents = array_slice($below_agents_all, $below_offset, $below_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Productivity Dashboard - June 2025</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        :root {
            --primary: #f8961e;
            --secondary: #ff6900;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
            --light: #f8f9fa;
            --dark: #212529;
            --neutral: #6c757d;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: var(--dark);
        }
        
        .container {
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 1s ease-in-out;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: var(--neutral);
            font-size: 1.1rem;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .filter-group select, 
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .filter-group button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .filter-group button:hover {
            background-color: var(--secondary);
        }
        
        .criteria-banner {
            background: linear-gradient(135deg, #ff6900 0%, #fcb900 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            animation: slideUp 0.8s ease-out;
        }
        
        .criteria-item {
            text-align: center;
            padding: 10px 20px;
            margin: 5px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            min-width: 150px;
        }
        
        .criteria-item .value {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .criteria-item .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .dashboard-section {
            margin-bottom: 40px;
            animation: fadeIn 1s ease-in-out;
        }
        
        .section-title {
            color: #212529;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
        }
        
        .cards-container {
            justify-content: center;
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            width: 300px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .card-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .card-description {
            font-size: 0.9rem;
            color: var(--neutral);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: 400px;
        }
        
        .small-chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: 300px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd; /* Add border to the table */
        }
        
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd; /* Add borders to the table cells */
        }
        
        th {
            background-color: var(--primary);
            color: white;
            text-align: left;
        }
        
        td {
            background-color: white;
        }
        
        tr:last-child td {
            border-bottom: none; /* Remove the bottom border for the last row */
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }


        
        /* Style for above average category */
        .above-criteria {
            background-color: rgba(76, 201, 240, 0.1); /* Light blue background */
            color: #007bff; /* Blue text color */
        }
        
        /* Style for neutral category */
        .neutral-criteria {
            background-color: rgba(108, 117, 125, 0.1); /* Light grey background */
            color: #6c757d; /* Grey text color */
        }
        
        /* Style for below average category */
        .below-criteria {
            background-color: rgba(249, 65, 68, 0.1); /* Light red background */
            color: #f72585; /* Red text color */
        }

        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-above {
            background-color: var(--success);
        }
        
        .status-neutral {
            background-color: var(--neutral);
        }
        
        .status-below {
            background-color: var(--danger);
        }
        
        .agent-tooltip {
            position: absolute;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            z-index: 100;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #f8961e, #ff6900);
            border-radius: 5px;
            transition: width 1s ease-in-out;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a, 
        .pagination span {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: var(--primary);
        }
        
        .pagination a:hover {
            background-color: #f1f1f1;
        }
        
        .pagination .active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .disabled {
            color: #ddd;
            pointer-events: none;
        }
        
        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
            
            .criteria-banner {
                flex-direction: column;
                align-items: center;
            }
            
            .criteria-item {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .grid-2-col {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Agent Productivity Dashboard</h1>
            <p>Performance Metrics on <?php echo date('M j, Y', strtotime($start_date)); ?></p>
        </div>
        <form method="GET" class="filters">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label for="team_filter">Team</label>
                <select id="team_filter" name="team_filter">
                    <option value="">All Teams</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo htmlspecialchars($team); ?>" <?php echo $team_filter === $team ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($team); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="manager_filter">Sales Manager</label>
                <select id="manager_filter" name="manager_filter">
                    <option value="">All Managers</option>
                    <?php foreach ($managers as $manager): ?>
                        <option value="<?php echo htmlspecialchars($manager); ?>" <?php echo $manager_filter === $manager ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($manager); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit">Apply Filters</button>
            </div>
        </form>

        <div class="criteria-banner">
            <?php
            $criteria_items = [
                ['value' => isset($criteria['conversion']) ? round($criteria['conversion'] * 100, 2) . '%': '-', 'label' => 'Total Pax Conv'],
                ['value' => isset($criteria['total_pax']) ? intval($criteria['total_pax']) : '-', 'label' => 'Total Pax'],
                ['value' => isset($criteria['gdeals']) ? intval($criteria['gdeals']) : '-', 'label' => 'GDEALS'],
                ['value' => isset($criteria['fit']) ? intval($criteria['fit']) : '-', 'label' => 'FIT'],
                ['value' => isset($criteria['fcs']) ? round($criteria['fcs'] * 100, 2) . '%' : '-', 'label' => 'FCS'],
                ['value' => isset($criteria['AHT']) ? htmlspecialchars($criteria['AHT']) : '-', 'label' => 'AHT'],
            ];
            foreach ($criteria_items as $item): ?>
                <div class="criteria-item">
                    <div class="value"><?php echo $item['value']; ?></div>
                    <div class="label"><?php echo $item['label']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
        <div class="filters" style="margin-top: -10px; margin-bottom: 20px;">
            <div class="filter-group">
                 <button onclick="toggleSection('allAgentsSection')">All Agents</button>
            </div>
            <div class="filter-group">
                <button onclick="toggleSection('aboveAvgSection')">Above Avg Conversion</button>
            </div>
            <div class="filter-group">
                <button onclick="toggleSection('belowAvgSection')">Below Avg Conversion</button>
            </div>
        </div>


        <?php
        // Helper to render agent table rows
        function render_agent_row($agent, $category_class) {
            ?>
            <tr class="<?php echo $category_class; ?>">
                <td>
                    <span class="agent-name-tooltip"
                        data-tooltip="
                            <strong><?php echo htmlspecialchars($agent['Name'] ?? ''); ?></strong><br>
                            Employee ID: <?php echo htmlspecialchars($agent['Employee_ID'] ?? ''); ?><br>
                            Team: <?php echo htmlspecialchars($agent['Team_name'] ?? ''); ?><br>
                            SM: <?php echo htmlspecialchars($agent['SM_name'] ?? ''); ?><br>
                            TL: <?php echo htmlspecialchars($agent['TL_name'] ?? ''); ?>
                        ">
                        <?php echo htmlspecialchars($agent['Name'] ?? ''); ?>
                    </span>
                </td>
                        <td><?php echo htmlspecialchars(date('dM', strtotime($agent['Date'] ?? ''))); ?></td>
                <td><?php echo htmlspecialchars($agent['GTIB_call_count'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['GTMD_call_count'] ?? ''); ?></td>
                <td><?php echo isset($agent['FCS']) ? (is_numeric($agent['FCS']) ? round($agent['FCS'], 2) : $agent['FCS']) : ''; ?>%</td>
                <td><?php echo htmlspecialchars($agent['Total_pax'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_pax_PIF'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_quotes'] ?? ''); ?></td>
                <td>
                    <div style="min-width:60px;">
                        <span><?php echo intval($agent['pax_conv_percent']); ?>%</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, max(0, intval($agent['pax_conv_percent']))); ?>%"></div>
                        </div>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($agent['Noble_start_time'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Noble_end_time'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_connected_time'] ?? $agent['Total_Idle'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_Idle'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_GTBK'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_GTTN'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Total_ACW'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['Attendance'] ?? ''); ?></td>
                <td>
                    <span class="status-indicator status-<?php
                        if ($category_class === 'above-criteria') echo 'above';
                        elseif ($category_class === 'below-criteria') echo 'below';
                        else echo 'neutral';
                    ?>"></span>
                    <?php
                        if ($category_class === 'above-criteria') echo 'Above';
                        elseif ($category_class === 'below-criteria') echo 'Below';
                        else echo 'Neutral';
                    ?>
                </td>
            </tr>
            <?php
        }
        // Helper for pagination
        function render_pagination($total_pages, $current_page, $param_name) {
            if ($total_pages <= 1) return;
            echo '<div class="pagination">';
            if ($current_page > 1) {
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => 1])) . '">&laquo;</a>';
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => $current_page - 1])) . '">&lsaquo;</a>';
            } else {
                echo '<span class="disabled">&laquo;</span><span class="disabled">&lsaquo;</span>';
            }
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            if ($start_page > 1) {
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => 1])) . '">1</a>';
                if ($start_page > 2) echo '<span class="disabled">...</span>';
            }
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $current_page) echo '<span class="active">' . $i . '</span>';
                else echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => $i])) . '">' . $i . '</a>';
            }
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<span class="disabled">...</span>';
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => $total_pages])) . '">' . $total_pages . '</a>';
            }
            if ($current_page < $total_pages) {
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => $current_page + 1])) . '">&rsaquo;</a>';
                echo '<a href="?' . http_build_query(array_merge($_GET, [$param_name => $total_pages])) . '">&raquo;</a>';
            } else {
                echo '<span class="disabled">&rsaquo;</span><span class="disabled">&raquo;</span>';
            }
            echo '</div>';
        }
        ?>

       <!-- All Agents Section -->
        <div id="allAgentsSection" class="dashboard-section" style="display: block;">
            <h3>All Agent Productivity Data</h3>
            <table id="allAgentsTable" class="agent-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date</th>
                            <th>GTIB</th>
                            <th>GTMD</th>
                            <th>FCS</th>
                            <th>PAX</th>
                            <th>PIF</th>
                            <th>QUOTES</th>
                            <th>CONV</th>
                            <th>Logon Time</th>
                            <th>Logoff Time</th>
                            <th>Connected Time</th>
                            <th>Idle Time</th>
                            <th>GTBK</th>
                            <th>GTTN</th>
                            <th>ACW</th>
                            <th>Attendance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated_agents as $agent):
                            $cat = $agent['category'] === 'above' ? 'above-criteria' : ($agent['category'] === 'below' ? 'below-criteria' : 'neutral-criteria');
                            render_agent_row($agent, $cat);
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($total_pages, $current_page, 'page'); ?>
            
            
            <!-- Above Average Conversion Section -->
            <div id="aboveAvgSection" class="dashboard-section" style="display: none;">
                <h3>Above Average Conversion Performers</h3>
                <table id="aboveAvgTable" class="agent-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date</th>
                            <th>GTIB</th>
                            <th>GTMD</th>
                            <th>FCS</th>
                            <th>PAX</th>
                            <th>PIF</th>
                            <th>QUOTES</th>
                            <th>CONV</th>
                            <th>Logon Time</th>
                            <th>Logoff Time</th>
                            <th>Connected Time</th>
                            <th>Idle Time</th>
                            <th>GTBK</th>
                            <th>GTTN</th>
                            <th>ACW</th>
                            <th>Attendance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $above_agents = $paginated_above_agents;
                        usort($above_agents, fn($a, $b) => $b['pax_conv_percent'] <=> $a['pax_conv_percent']);
                        foreach ($above_agents as $agent) render_agent_row($agent, 'above-criteria');
                        ?>
                    </tbody>
                </table>
                <?php render_pagination($above_total_pages, $above_page, 'above_page'); ?>
            </div>
        </div>
        
        
        <div id="neutralSection" style="display: none;">
          <div class="dashboard-section">
            <h3>😐 Neutral Performers</h3>
            <table class="agent-table">table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date</th>
                            <th>GTIB</th>
                            <th>GTMD</th>
                            <th>FCS</th>
                            <th>PAX</th>
                            <th>PIF</th>
                            <th>QUOTES</th>
                            <th>CONV</th>
                            <th>Logon Time</th>
                            <th>Logoff Time</th>
                            <th>Idle Time</th>
                            <th>GTBK</th>
                            <th>GTTN</th>
                            <th>ACW</th>
                            <th>Attendance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $neutral_agents = $paginated_neutral_agents;
                        usort($neutral_agents, fn($a, $b) => $b['pax_conv_percent'] <=> $a['pax_conv_percent']);
                        foreach ($neutral_agents as $agent) render_agent_row($agent, 'neutral-criteria');
                        ?>
                    </tbody>
                </table>
                <?php render_pagination($neutral_total_pages, $neutral_page, 'neutral_page'); ?>
            </div>
        </div>
        
        
        <!-- Below Average Conversion Section -->
        <div id="belowAvgSection" class="dashboard-section" style="display: none;">
            <h3>Below Average Conversion Performers</h3>
            <table id="belowAvgTable" class="agent-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date</th>
                            <th>GTIB</th>
                            <th>GTMD</th>
                            <th>FCS</th>
                            <th>PAX</th>
                            <th>PIF</th>
                            <th>QUOTES</th>
                            <th>CONV</th>
                            <th>Logon Time</th>
                            <th>Logoff Time</th>
                            <th>Idle Time</th>
                            <th>Connected Time</th>
                            <th>GTBK</th>
                            <th>GTTN</th>
                            <th>ACW</th>
                            <th>Attendance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $below_agents = $paginated_below_agents;
                        usort($below_agents, fn($a, $b) => $a['pax_conv_percent'] <=> $b['pax_conv_percent']);
                        foreach ($below_agents as $agent) render_agent_row($agent, 'below-criteria');
                        ?>
                    </tbody>
                </table>
                <?php render_pagination($below_total_pages, $below_page, 'below_page'); ?>
            </div>
        </div>
    </div>
    <div class="agent-tooltip" id="agentTooltip"></div>
<script>
// Function to apply sorting to a table
function applySorting(tableId) {
    const table = document.getElementById(tableId);
    const headers = table.querySelectorAll('th');
    
    headers.forEach((header, columnIndex) => {
        header.style.cursor = 'pointer';
        header.addEventListener("click", function() {
            const tbody = table.querySelector("tbody");
            const rows = Array.from(tbody.querySelectorAll("tr"));
            const ascending = header.classList.contains("asc"); // Check if it's currently ascending

            // Toggle 'asc' class for the header
            headers.forEach(h => h.classList.remove("asc", "desc"));
            if (!ascending) {
                header.classList.add("asc"); // Set this header to ascending
            } else {
                header.classList.add("desc"); // Set this header to descending
            }

            // Sort the rows based on the column index and direction
            rows.sort((a, b) => {
                const cellA = a.children[columnIndex].innerText.trim().replace('%','');
                const cellB = b.children[columnIndex].innerText.trim().replace('%','');
                
                // Attempt to convert to numbers for comparison
                const aNum = parseFloat(cellA.replace(/[^0-9.-]+/g, ''));
                const bNum = parseFloat(cellB.replace(/[^0-9.-]+/g, ''));
                const isNumeric = !isNaN(aNum) && !isNaN(bNum);
                
                // Compare numerically if both are numbers, otherwise lexicographically
                if (isNumeric) {
                    return (aNum - bNum) * (ascending ? -1 : 1); // Reverse the multiplication if ascending
                } else {
                    return cellA.localeCompare(cellB) * (ascending ? -1 : 1); // Reverse lexicographic comparison
                }
            });

            // Append the sorted rows back to the table body
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}


// Function to toggle between sections
function toggleSection(sectionId) {
    const sections = ['allAgentsSection', 'aboveAvgSection', 'belowAvgSection'];

    // Hide all sections
    sections.forEach(function(section) {
        const sectionElement = document.getElementById(section);
        sectionElement.style.display = 'none';  // Hide section
    });

    // Show the selected section
    const selectedSection = document.getElementById(sectionId);
    selectedSection.style.display = 'block';  // Show selected section

    // Apply sorting to the table of the newly visible section
    if (sectionId === 'allAgentsSection') {
        applySorting('allAgentsTable');
    } else if (sectionId === 'aboveAvgSection') {
        applySorting('aboveAvgTable');
    } else if (sectionId === 'belowAvgSection') {
        applySorting('belowAvgTable');
    }
}

// Initialize Flatpickr
flatpickr("#start_date", {
    dateFormat: "Y-m-d",
    defaultDate: "<?php echo $start_date; ?>"
});

document.addEventListener('DOMContentLoaded', function() {
    const tooltip = document.getElementById('agentTooltip');
    
    // Handle tooltips
    document.querySelectorAll('.agent-name-tooltip').forEach(nameEl => {
        nameEl.addEventListener('mouseenter', function(e) {
            tooltip.innerHTML = this.getAttribute('data-tooltip');
            tooltip.style.left = `${e.pageX + 15}px`;
            tooltip.style.top = `${e.pageY + 15}px`;
            tooltip.style.opacity = '1';
        });
        nameEl.addEventListener('mouseleave', function() {
            tooltip.style.opacity = '0';
        });
        nameEl.addEventListener('mousemove', function(e) {
            tooltip.style.left = `${e.pageX + 15}px`;
            tooltip.style.top = `${e.pageY + 15}px`;
        });
    });

    // Handle progress bar animation
    document.querySelectorAll('.progress-fill').forEach(bar => {
        const targetWidth = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => { bar.style.width = targetWidth; }, 100);
    });

    // Initially, apply sorting to the All Agents section table
    applySorting('allAgentsTable');
});


// // Button to toggle visibility of sections
// function toggleSection(sectionId) {
//     const sections = ['allAgentsSection', 'aboveAvgSection', 'belowAvgSection'];

//     sections.forEach(function(section) {
//         const sectionElement = document.getElementById(section);
//         sectionElement.style.display = 'none';  // Hide section
//     });

//     const selectedSection = document.getElementById(sectionId);
//     selectedSection.style.display = 'block';  // Show selected section

//     // Apply sorting to the table of the newly visible section
//     if (sectionId === 'allAgentsSection') {
//         applySorting('allAgentsTable');
//     } else if (sectionId === 'aboveAvgSection') {
//         applySorting('aboveAvgTable');
//     } else if (sectionId === 'belowAvgSection') {
//         applySorting('belowAvgTable');
//     }
// }

</script>


</body>
</html>
