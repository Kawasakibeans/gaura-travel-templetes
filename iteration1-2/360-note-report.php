<?php
/**
 * Template Name: G360 Note Report
 * Template Post Type: post, page
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =============================================
// 1. HANDLE SPECIAL REQUESTS FIRST (BEFORE ANY OUTPUT)
// =============================================

// Database connections for special requests
function getDatabaseConnections() {
    $mysql_servername = "localhost";
    $mysql_username = "gaurat_sriharan";
    $mysql_password = "r)?2lc^Q0cAE";
    $mysql_dbname = "gaurat_gauratravel";

// PostgreSQL connection parameters
    $pgsql_dsn = 'pgsql:host=192.168.0.41;port=5432;dbname=task';
    $pgsql_username = 'oztele';
    $pgsql_password = 'pass1234';

    try {
        // MySQL PDO
        $mysql_conn = new PDO("mysql:host=$mysql_servername;dbname=$mysql_dbname", $mysql_username, $mysql_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // PostgreSQL PDO
        $pgsql_conn = new PDO($pgsql_dsn, $pgsql_username, $pgsql_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        return [$mysql_conn, $pgsql_conn];
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// AJAX handler for pagination
if (isset($_GET['ajax']) && $_GET['ajax'] == 'pagination') {
    list($mysql_conn, $pgsql_conn) = getDatabaseConnections();
    $data = fetchCallCenterData($mysql_conn, $pgsql_conn, false, $start, $perPage);
    $total = count($data);  // basic fallback count

    ob_start();
    if (!empty($data)) {
        foreach ($data as $row) {
            ?>
            <tr>
                <td><?= htmlspecialchars($row['Note_ID'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['CS_Agent'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Call_Date'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Call_Time'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Campaign'] ?? '') ?></td>
                <?php
                $callStatusClass = '';
                $callStatus = strtolower($row['Call_Status'] ?? '');
                if ($callStatus == 'answered') $callStatusClass = 'badge-success';
                elseif ($callStatus == 'missed') $callStatusClass = 'badge-danger';
                else $callStatusClass = 'badge-warning';
                ?>
                <td><span class='badge <?= $callStatusClass ?>'><?= htmlspecialchars($row['Call_Status'] ?? '') ?></span></td>
                <td>
                    <?php
                    $duration = $row['Duration'] ?? '';
                    if (is_numeric($duration)) {
                        $minutes = intval(floor($duration / 60));
                        $seconds = intval(round($duration % 60));
                        echo $minutes . 'm ' . $seconds . 's';
                    } else {
                        echo htmlspecialchars($duration ?? '');
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($row['Pax_Phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Noble_Record'] ?? '') ?></td>
                <td>
                    <?= !empty($row['Booking_Date']) 
                        ? date('d/m/y H:i', strtotime($row['Booking_Date'])) 
                        : '' ?>
                </td>
                <td><?= htmlspecialchars($row['Booking_ID'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Sales_Agent'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Travel_Date'] ?? '') ?></td>
                <?php
                $paymentStatusClass = '';
                $paymentStatus = strtolower($row['Payment_Status'] ?? '');
                if ($paymentStatus == 'paid') $paymentStatusClass = 'badge-success';
                elseif ($paymentStatus == 'pending') $paymentStatusClass = 'badge-warning';
                else $paymentStatusClass = 'badge-info';
                ?>
                <td><span class='badge <?= $paymentStatusClass ?>'><?= htmlspecialchars($row['Payment_Status'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($row['Total_Pax'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Booking_Type'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Note_Category'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Note_Department'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Note_Description'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['Note_Added_On'] ?? '') ?></td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="20">No data found</td></tr>';
    }
    $tbody = ob_get_clean();

    // Pagination HTML
    $pages = ceil($total / $perPage);
    $startPage = max(1, $page - 2);
    $endPage = min($pages, $page + 2);

    ob_start();
    if ($page > 1) {
        echo '<a href="#" data-page="' . ($page-1) . '">&laquo;</a>';
    }
    if ($startPage > 1) {
        echo '<a href="#" data-page="1">1</a>';
        if ($startPage > 2) echo '<span>...</span>';
    }
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<a href="#" data-page="' . $i . '" class="' . $active . '">' . $i . '</a>';
    }
    if ($endPage < $pages) {
        if ($endPage < $pages - 1) echo '<span>...</span>';
        echo '<a href="#" data-page="' . $pages . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        echo '<a href="#" data-page="' . ($page+1) . '">&raquo;</a>';
    }
    $pagination = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'tbody' => $tbody,
        'pagination' => $pagination,
        'showing' => 'Showing ' . ($start+1) . ' to ' . min($start + $perPage, $total) . ' of ' . $total . ' records'
    ]);
    exit();
}

// Handle CSV download request
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    list($mysql_conn, $pgsql_conn) = getDatabaseConnections();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="note_report_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // CSV Headers (same as visible table)
    fputcsv($output, [
        'Note ID', 'Booking ID', 'Booking Date', 'Sales Agent', 'Note Added By',
        'Travel Date', 'Booking Type', 'Total Pax', 'Note Category',
        'Note Department', 'Note Description', 'Note Added On'
    ]);

    // Fetch all filtered records
    $all_data = fetchCallCenterData($mysql_conn, $pgsql_conn, true);

    foreach ($all_data as $row) {
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
    exit();
}



// =============================================
// 2. MAIN PAGE FUNCTIONALITY
// =============================================

// Database connections for main page
list($mysql_conn, $pgsql_conn) = getDatabaseConnections();

// Get filter values
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$start_date = $filter_date;
$end_date = $filter_date;
$department = $_GET['department'] ?? '';
$category = $_GET['category'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Get data with pagination
$data = fetchCallCenterData($mysql_conn, $pgsql_conn, true);

// Get total count for pagination
$total = count($data); // You already fetched paginated data
$pages = ceil($total / $perPage);

// Get filter options
list($departments, $categories) = getFilterOptions($mysql_conn);

// Get data for department and category counts
$dept_counts = getCountData($mysql_conn, 'Department');
$cat_counts = getCountData($mysql_conn, 'Category');

function fetchCallCenterData($mysql_conn, $pgsql_conn, $all_records = false, $start = 0, $perPage = 10) {
    $filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$start_date = $filter_date;
$end_date = $filter_date;
    $department = $_GET['department'] ?? '';
    $category = $_GET['category'] ?? '';

    // Updated WHERE clause uses 'bns' alias (wpk4_backend_booking_note_summary)
    $where = [
        "(bns.updated_on BETWEEN :start_date AND :end_date and orderpax.order_id is not null and bns.additional_note is not null)"
    ];
    $params = [
        ':start_date' => "$start_date 00:00:00",
        ':end_date' => "$end_date 23:59:59"
    ];
    if ($department) {
        $where[] = "(bns.note_department = :department)";
        $params[':department'] = $department;
    }
    if ($category) {
        $where[] = "(bns.note_category = :category)";
        $params[':category'] = $category;
    }
    $where_clause = implode(' AND ', $where);  

    // Build the main query joining bookings with prepopulated summary table
    $mysql_sql = "
        SELECT
        orderpax.order_id AS Booking_ID,
        orderpax.order_date AS Booking_Date,
        orderpax.agent_info AS Sales_Agent,
        CASE 
            WHEN MIN(orderpax.travel_date) > CURRENT_DATE THEN MIN(orderpax.travel_date)
            ELSE MAX(orderpax.travel_date)
        END AS Travel_Date,
        orderpax.order_type AS Booking_Type,
        SUM(orderpax.total_pax) AS Total_Pax,
        bns.type_id,
        MAX(bns.auto_id) AS Note_ID,
        MAX(bns.updated_on) AS Note_Added_On,
        MAX(CASE WHEN bns.meta_key = 'Booking Note Category' THEN bns.meta_value END) AS Note_Category,
        MAX(CASE WHEN bns.meta_key = 'Booking Note Description' THEN bns.meta_value END) AS Note_Description,
        MAX(CASE WHEN bns.meta_key = 'Booking Note Department' THEN bns.meta_value END) AS Note_Department,
        MAX(bns.updated_by) AS cs_agent
    FROM 
        (
          SELECT *
          FROM wpk4_backend_history_of_updates
          WHERE updated_on >= NOW() - INTERVAL 7 DAY
            AND additional_note IS NOT NULL
        ) AS bns
    JOIN 
        wpk4_backend_travel_bookings orderpax
        ON bns.type_id = orderpax.order_id
    WHERE $where_clause    
    GROUP BY 
        orderpax.order_id, 
        orderpax.order_date, 
        orderpax.agent_info, 
        orderpax.order_type, 
        bns.type_id   
    ";

    if (!$all_records) {
        $mysql_sql .= " LIMIT :start, :perPage";
        $params[':start'] = $start;
        $params[':perPage'] = $perPage;
    }

    $stmt = $mysql_conn->prepare($mysql_sql);

    foreach ($params as $key => &$val) {
        if ($key === ':start' || $key === ':perPage') {
            $stmt->bindParam($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindParam($key, $val);
        }
    }

    $stmt->execute();
    $booking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch call data from PostgreSQL for the Note_IDs found
    $call_data_indexed = [];
    if (!empty($booking_data)) {
        $note_ids = [];
        foreach ($booking_data as $booking) {
            if (!empty($booking['Note_ID'])) {
                $note_ids[] = trim($booking['Note_ID']);
            }
        }
        $note_ids = array_unique($note_ids);
        if (!empty($note_ids)) {
            $params = [];
            $placeholders = [];
            foreach ($note_ids as $i => $id) {
                $param = ":note_id_$i";
                $params[$param] = $id;
                $placeholders[] = "TRIM(c.call_feedback) LIKE '%' || $param || '%'";
            }
            $where_clause = implode(' OR ', $placeholders);

            $pgsql_sql = "
                SELECT 
                    TRIM(c.call_feedback) AS note_id,
                    c.call_date AS call_date,
                    c.call_time AS call_time,
                    c.appl AS campaign,
                    c.rec_status AS call_status,
                    c.call_duration AS duration,
                    CONCAT(c.country_id,c.areacode,c.phone) AS pax_phone,
                    c.record_id AS noble_record
                FROM cust_ob_inb_hst c
                WHERE $where_clause
            ";

            $stmt = $pgsql_conn->prepare($pgsql_sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();

            $call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($call_data as $call) {
                if (!empty($call['note_id'])) {
                    $call_note_id = trim($call['note_id']);
                    foreach ($note_ids as $id) {
                        if (trim($id) === $call_note_id) {
                            $cleaned_call = [];
                            foreach ($call as $key => $value) {
                                $cleaned_call[$key] = $value !== null ? trim($value) : '';
                            }
                            $call_data_indexed[$id] = $cleaned_call;
                            break;
                        }
                    }
                }
            }
        }
    }

    // Merge call data into booking data
    foreach ($booking_data as &$booking) {
        $note_id = isset($booking['Note_ID']) ? trim($booking['Note_ID']) : null;
        if ($note_id !== null && $note_id !== '' && isset($call_data_indexed[$note_id])) {
            $call = $call_data_indexed[$note_id];
            $booking = array_merge($booking, [
                'Call_Date' => $call['call_date'] ?? '',
                'Call_Time' => $call['call_time'] ?? '',
                'Campaign' => $call['campaign'] ?? '',
                'Call_Status' => $call['call_status'] ?? '',
                'Duration' => $call['duration'] ?? '',
                'Pax_Phone' => $call['pax_phone'] ?? '',
                'Noble_Record' => $call['noble_record'] ?? ''
            ]);
        } else {
            $booking = array_merge($booking, [
                'Call_Date' => '',
                'Call_Time' => '',
                'Campaign' => '',
                'Call_Status' => '',
                'Duration' => '',
                'Pax_Phone' => '',
                'Noble_Record' => ''
            ]);
        }
    }

    return $booking_data;
}



function getFilterOptions($mysql_conn) {
    // Cache variables to avoid multiple queries per request
    static $departments = null;
    static $categories = null;

    if ($departments === null) {
        $stmt = $mysql_conn->query("
            SELECT DISTINCT note_department AS meta_value 
            FROM wpk4_backend_booking_note_summary 
            WHERE note_department IS NOT NULL AND note_department <> '' 
            ORDER BY note_department
        ");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($categories === null) {
        $stmt = $mysql_conn->query("
            SELECT DISTINCT note_category AS meta_value 
            FROM wpk4_backend_booking_note_summary 
            WHERE note_category IS NOT NULL AND note_category <> '' 
            ORDER BY note_category
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [$departments, $categories];
}

function getCountData($mysql_conn, $type) {
    $filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$start_date = $filter_date;
$end_date = $filter_date;

    // Map type to the relevant column in wpk4_backend_booking_note_summary
    $column_map = [
        'Category' => 'note_category',
        'Department' => 'note_department',
        'Description' => 'note_description',
    ];

    if (!isset($column_map[$type])) {
        throw new InvalidArgumentException("Invalid type: $type");
    }

    $column = $column_map[$type];

    $sql = "
        SELECT 
            $column AS group_name,
            COUNT(*) AS count
        FROM wpk4_backend_booking_note_summary bns
        WHERE bns.updated_on BETWEEN :start_date AND :end_date
          AND $column IS NOT NULL AND $column <> ''
          AND bns.note_id IS NOT NULL
        GROUP BY $column
        ORDER BY count DESC
    ";

    $stmt = $mysql_conn->prepare($sql);
    $stmt->execute([
        ':start_date' => "$start_date 00:00:00",
        ':end_date' => "$end_date 23:59:59"
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
<?php
// Close connections
$mysql_conn = null;
$pgsql_conn = null;
?>