<?php
/**
 * Template Name: G360 Note Report
 * Template Post Type: post, page
 */
// Ensure WordPress functions are available when this file is accessed directly.
if (!defined('ABSPATH')) {
    $possibleWpLoadPaths = array_filter([
        isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
            ? rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/wp-load.php'
            : null,
        dirname(__FILE__, 5) . '/wp-load.php',
        dirname(__FILE__, 4) . '/wp-load.php',
        dirname(__FILE__, 3) . '/wp-load.php',
    ]);

    foreach ($possibleWpLoadPaths as $wpLoadPath) {
        if (is_string($wpLoadPath) && file_exists($wpLoadPath)) {
            require_once $wpLoadPath;
            break;
        }
    }
}

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined');
}

function g360_note_report_build_url(string $endpoint): string
{
    $base = rtrim((string)API_BASE_URL, '/');
    $path = '/' . ltrim($endpoint, '/');

    // Some environments define API_BASE_URL with /v1, others without.
    // Normalize so the final URL has exactly one /v1 between base and endpoint.
    $baseHasV1Suffix = (bool)preg_match('~/v1$~', $base);
    $pathHasV1Prefix = (strpos($path, '/v1/') === 0);

    if ($baseHasV1Suffix && $pathHasV1Prefix) {
        $path = substr($path, 3); // remove leading "/v1"
    } elseif (!$baseHasV1Suffix && !$pathHasV1Prefix) {
        $path = '/v1' . $path;
    }

    return $base . $path;
}

function g360_note_report_api(string $endpoint, array $params = []): array
{
    $url = g360_note_report_build_url($endpoint);
    $query = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $response = wp_remote_get($url, [
        'timeout' => 60,
        'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        throw new RuntimeException('API request failed: ' . $response->get_error_message());
    }

    $payload = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($payload) || ($payload['status'] ?? '') !== 'success') {
        $message = $payload['message'] ?? 'Unknown API error';
        throw new RuntimeException($message);
    }

    return $payload['data'] ?? [];
}

$filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : date('Y-m-d');
$department = isset($_GET['department']) ? sanitize_text_field($_GET['department']) : '';
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';

$baseQuery = [
    'filter_date' => $filter_date,
    'department' => $department,
    'category' => $category,
];

try {
    if (isset($_GET['download']) && $_GET['download'] === 'csv') {
        $csvData = g360_note_report_api('/v1/g360-note-report', array_merge($baseQuery, ['fetch_all' => '1']));
        $records = $csvData['records'] ?? [];

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="note_report_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Note ID', 'Booking ID', 'Booking Date', 'Sales Agent', 'Note Added By',
            'Travel Date', 'Booking Type', 'Total Pax', 'Note Category',
            'Note Department', 'Note Description', 'Note Added On'
        ]);

        foreach ($records as $row) {
            fputcsv($output, [
                $row['Note_ID'] ?? '',
                $row['Booking_ID'] ?? '',
                !empty($row['Booking_Date']) ? date('d/m/y H:i', strtotime($row['Booking_Date'])) : '',
                $row['Sales_Agent'] ?? '',
                $row['cs_agent'] ?? '',
                !empty($row['Travel_Date']) ? date('d/m/y H:i', strtotime($row['Travel_Date'])) : '',
                $row['Booking_Type'] ?? '',
                $row['Total_Pax'] ?? '',
                $row['Note_Category'] ?? '',
                $row['Note_Department'] ?? '',
                $row['Note_Description'] ?? '',
                !empty($row['Note_Added_On']) ? date('d/m/y H:i', strtotime($row['Note_Added_On'])) : ''
            ]);
        }

        fclose($output);
        exit;
    }

    $reportData = g360_note_report_api('/v1/g360-note-report', array_merge($baseQuery, ['fetch_all' => '1']));
    $filtersData = g360_note_report_api('/v1/g360-note-report/filters');
    $deptCountsData = g360_note_report_api('/v1/g360-note-report/counts', [
        'filter_date' => $filter_date,
        'type' => 'department'
    ]);
    $catCountsData = g360_note_report_api('/v1/g360-note-report/counts', [
        'filter_date' => $filter_date,
        'type' => 'category'
    ]);
} catch (Throwable $e) {
    wp_die('Failed to load G360 note data: ' . esc_html($e->getMessage()));
}

$data = $reportData['records'] ?? [];
$paginationInfo = $reportData['pagination'] ?? [];
$total = $paginationInfo['total'] ?? count($data);
$perPage = $paginationInfo['per_page'] ?? 10;
$pages = $paginationInfo['total_pages'] ?? 1;

$departments = $filtersData['departments'] ?? [];
$categories = $filtersData['categories'] ?? [];
$dept_counts = $deptCountsData['items'] ?? [];
$cat_counts = $catCountsData['items'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G360 Note Report Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
     <style>
        :root {
            --gold: #FFD700;
            --dark-gold: #D4AF37;
            --orange: #FFA500;
            --dark-orange: #FF8C00;
            --black: #1A1A1A;
            --light-bg: #FFF9E6;
            --white: #FFFFFF;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--black);
            line-height: 1.6;
        }
        
        .container {
            max-width: 95%;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            padding: 20px 0;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            border: 2px solid var(--black);
        }
    
        
        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gold);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            border-bottom: 2px solid var(--gold);
            padding-bottom: 10px;
            margin-bottom: 15px;
            color: var(--dark-orange);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header i {
            color: var(--dark-gold);
            font-size: 1.5rem;
        }
        
        .stat {
            font-size: 2rem;
            font-weight: 700;
            color: var(--black);
            margin: 10px 0;
        }
        
        .data-table {
            width: 100%;
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid var(--gold);
        }
        
        .table-header {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: var(--dark-gold);
            color: var(--black);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gold);
        }
        
        tr:nth-child(even) {
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        tr:hover {
            background-color: rgba(255, 165, 0, 0.2);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            color: var(--black);
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid var(--gold);
            margin: 0 4px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .pagination a.active {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            font-weight: 600;
            border: 1px solid var(--black);
        }
        
        .pagination a:hover:not(.active) {
            background-color: var(--gold);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: rgba(0, 200, 83, 0.2);
            color: #00C853;
        }
        
        .badge-warning {
            background-color: rgba(255, 171, 0, 0.2);
            color: #FFAB00;
        }
        
        .badge-danger {
            background-color: rgba(255, 82, 82, 0.2);
            color: #FF5252;
        }
        
        .badge-info {
            background-color: rgba(0, 176, 255, 0.2);
            color: #00B0FF;
        }
        
        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-box, .filter-select {
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid var(--gold);
            background-color: var(--white);
            font-family: 'Poppins', sans-serif;
        }
        
        .search-box {
            flex-grow: 1;
            max-width: 300px;
        }
        
        .filter-select {
            min-width: 150px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            border: 1px solid var(--black);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .tab-container {
            margin-bottom: 30px;
        }
        
        .tab-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: var(--white);
            border: 1px solid var(--gold);
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            font-weight: 600;
            border: 1px solid var(--black);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        .notes_content{
            display: flex;
            gap: 10px
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .date-range-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .dashboard, .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .search-box, .filter-select {
                max-width: 100%;
            }
            
            .date-range-filter {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        .count-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .count-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .count-item:nth-child(even) {
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .count-value {
            font-weight: bold;
            color: var(--dark-orange);
        }
        .download-btn {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            color: var(--black);
            border: 1px solid var(--black);
            padding: 8px 15px;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .table-actions {
            background: linear-gradient(135deg, var(--gold), var(--orange));
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
        }
        /* Limit narrow columns */
        th.narrow, td.narrow {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Allow wide, wrapping description */
        th.wrap-text, td.wrap-text {
            max-width: 600px;
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
        }

    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>G360 Note Report Dashboard</h1>
        </header>
        
        <form method="GET" action="">
            <div class="search-filter">
                <div class="date-range-filter" style="display: flex; align-items: center; gap: 10px;">
                    <input type="date" class="filter-select" name="filter_date" value="<?php echo htmlspecialchars($filter_date ?? date('Y-m-d')); ?>" placeholder="Filter Date">
                    <button type="submit" class="btn">Apply Filters</button>
                </div>
            </div>
        </form>
        
        
        
        <div class="data-table">
            <div class="table-actions">
                <div>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['download' => 'csv'])); ?>" class="download-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Download CSV
                    </a>
                </div>
            </div>
            
            
            <div style="overflow-x: auto;">
               <table id="data-table" class="display note-table" style="width:100%">
                <thead>
                    <tr>
                        <th>Note ID</th>
                        <th>Booking ID</th>
                        <th>Booking Date</th>
                        <th>Sales Agent</th>
                        <th>Note Added By</th>
                        <th>Travel Date</th>
                        <th>Booking Type</th>
                        <th>Total Pax</th>
                        <th>Note Category</th>
                        <th>Note Department</th>
                        <th>Note Description</th>
                        <th>Note Added On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Note_ID']) ?></td>
                        <td><?= htmlspecialchars($row['Booking_ID']) ?></td>
                        <td>
                            <?= !empty($row['Booking_Date']) 
                                ? date('d/m/y', strtotime($row['Booking_Date'])) 
                                : '' ?>
                        </td>
                        <td><?= htmlspecialchars($row['Sales_Agent']) ?></td>
                        <td><?= htmlspecialchars($row['cs_agent']) ?></td>
                        <td>
                            <?= !empty($row['Travel_Date']) 
                                ? date('d/m/y', strtotime($row['Travel_Date'])) 
                                : '' ?>
                        </td>
                        <td><?= htmlspecialchars($row['Booking_Type']) ?></td>
                        <td><?= htmlspecialchars($row['Total_Pax']) ?></td>
                        <td><?= htmlspecialchars($row['Note_Category']) ?></td>
                        <td><?= htmlspecialchars($row['Note_Department']) ?></td>
                        <td><?= htmlspecialchars($row['Note_Description']) ?></td>
                        <td>
                            <?= !empty($row['Note_Added_On']) 
                                ? date('d/m/y H:i', strtotime($row['Note_Added_On'])) 
                                : '' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            </div>
            
            
        </div>
    </div>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        $('#data-table').DataTable({
            "processing": true,
            "serverSide": false, // set true if you want server-side pagination
            "pageLength": 10,
            "order": [[0, 'desc']],
            "searching": true,
            "dom": 'lfrtip',
            columnDefs: [
                { width: "100px", targets: [8, 9] },   // Note Category, Department
                { width: "50%", targets: 10 },          // Note Description
            ],
            autoWidth: false,
            scrollX: true  // Enable horizontal scroll if needed
        });
    });
    </script>

    <script>
        // Tab functionality
        function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            // Remove active class from all tab buttons
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            // Show the current tab and add active class to the button
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // AJAX pagination
        document.addEventListener('DOMContentLoaded', function() {
            function getQueryParams() {
                const params = {};
                document.querySelectorAll('form input, form select').forEach(function(el) {
                    if (el.name && el.value !== undefined) params[el.name] = el.value;
                });
                return params;
            }

            function loadPage(page) {
                const params = getQueryParams();
                params['ajax'] = 'pagination';
                params['page'] = page;

                fetch('?' + new URLSearchParams(params), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('data-table-body').innerHTML = data.tbody;
                    document.getElementById('pagination-container').innerHTML = data.pagination;
                    document.querySelector('.table-header span').textContent = data.showing;
                    attachPaginationEvents();
                });
            }

            function attachPaginationEvents() {
                document.querySelectorAll('#pagination-container a[data-page]').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = this.getAttribute('data-page');
                        if (page) loadPage(page);
                    });
                });
            }

            attachPaginationEvents();
        });

        // Initialize the first tab as active on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set the first tab as active if none is active
            if (!document.querySelector('.tab-content.active')) {
                document.querySelector('.tab-content').classList.add('active');
                document.querySelector('.tab-btn').classList.add('active');
            }
        });
    </script>
</body>
</html>
