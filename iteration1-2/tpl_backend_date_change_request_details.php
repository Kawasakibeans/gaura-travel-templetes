<?php
/**
 * Template Name: Date Change Dashboard
 * Template Post Type: post, page
 */
// --------------------------
// CONFIGURATION
// --------------------------
require_once(dirname(__FILE__, 5) . '/wp-config.php');

// Connect DB
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) die("MySQL Connection Failed: " . $mysqli->connect_error);

// Input Sanitize
function clean($s) { return htmlspecialchars(trim($s ?? ''), ENT_QUOTES, 'UTF-8'); }

// Get input
$from_date = clean($_GET['from_date'] ?? '');
$to_date   = clean($_GET['to_date'] ?? '');
$current_filter = clean($_GET['filter'] ?? '');

$processed_requests = [];
$monthly_summary = [];
$date_summary = [];
$agent_summary = [];
$show_kpis = false;

// MAIN DATA QUERY
if ($from_date || $to_date) {
    $where = "r.case_type = 'datechange'";
    if ($from_date && $to_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN '$from_date' AND '$to_date'";
    else if ($from_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= '$from_date'";
    else if ($to_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= '$to_date'";

    $query = "
        SELECT 
            r.case_id, r.reservation_ref, r.status, r.case_date, r.last_response_on, r.updated_by,
            b.order_id, b.order_type, b.travel_date, b.product_title, b.total_pax, b.trip_code,
            orig_b.total_amount, cp.amount as cost_taken_amount
        FROM wpk4_backend_user_portal_requests r
        LEFT JOIN wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
        LEFT JOIN wpk4_backend_travel_bookings orig_b ON r.reservation_ref = orig_b.previous_order_id
        LEFT JOIN wpk4_backend_travel_booking_custom_payments cp ON r.reservation_ref = cp.order_id AND cp.type_of_payment = 'Date Change' AND cp.status = 'paid'
        WHERE $where
        ORDER BY STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s') ASC
    ";

    $res = $mysqli->query($query);
    $all_requests = [];
    while ($row = $res->fetch_assoc()) $all_requests[] = $row;

    // Fetch transaction sums per reservation_ref
    $reservation_refs = array_column($all_requests, 'reservation_ref');
    $transaction_map = [];
    if ($reservation_refs) {
        $in = implode("','", array_map([$mysqli, 'real_escape_string'], $reservation_refs));
        $tq = "
            SELECT order_id, SUM(transaction_amount) as transaction_sum
            FROM wpk4_backend_travel_booking_ticket_number
            WHERE order_id IN ('$in') AND reason = 'Datechange'
            GROUP BY order_id
        ";
        $tres = $mysqli->query($tq);
        while ($r = $tres->fetch_assoc()) $transaction_map[$r['order_id']] = floatval($r['transaction_sum']);
    }

    foreach ($all_requests as $request) {
        $ref = $request['reservation_ref'];
        $transaction_sum = $transaction_map[$ref] ?? 0;
        $airline = '';
        if (!empty($request['trip_code']) && strlen($request['trip_code']) >= 10) {
            $airline = substr($request['trip_code'], 8, 2);
        }
        $case_date = '';
        if (!empty($request['case_date'])) {
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $request['case_date']);
            if ($d) $case_date = $d->format('d/m/Y');
        }
        $travel_date = !empty($request['travel_date']) ? date('d/m/Y', strtotime($request['travel_date'])) : '';
        $last_response_on = !empty($request['last_response_on']) ? date('d/m/Y', strtotime($request['last_response_on'])) : '';
        $cost_taken = ($request['status'] === 'success') ? ($request['cost_taken_amount'] ?? 0) : 0;
        $total_revenue = ($request['status'] === 'success') ? ($cost_taken - $transaction_sum) : 0;

        $processed_requests[] = [
            'query_date' => $case_date,
            'agent' => $request['updated_by'] ?? '',
            'case_id' => $request['case_id'] ?? '',
            'pnr' => $request['reservation_ref'] ?? '',
            'request_type' => 'datechange',
            'pax_count' => $request['total_pax'] ?? '',
            'airline' => $airline,
            'booking_type' => (isset($request['order_type']) && strtolower($request['order_type']) === 'gds') ? 'FIT' : 'GDeals',
            'old_travel_date' => $travel_date,
            'cost_given' => $request['total_amount'] ?? 0,
            'cost_taken' => $cost_taken,
            'total_revenue' => $total_revenue,
            'status' => $request['status'] ?? '',
            'status_date' => $last_response_on
        ];
    }
    $show_kpis = !!$processed_requests;
}

// SUMMARY QUERIES (monthly, daily, agent)...
// Monthly
if ($from_date || $to_date) {
    $where = "r.case_type = 'datechange'";
    if ($from_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= '$from_date'";
    if ($to_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= '$to_date'";
    $q = "
        SELECT 
            YEAR(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as year,
            MONTH(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as month,
            SUM(CASE WHEN LOWER(b.order_type) = 'gds' THEN 1 ELSE 0 END) as fit_count,
            SUM(CASE WHEN LOWER(b.order_type) != 'gds' OR b.order_type IS NULL THEN 1 ELSE 0 END) as gdeals_count,
            COUNT(*) as total_count
        FROM wpk4_backend_user_portal_requests r
        LEFT JOIN wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
        WHERE $where
        GROUP BY year, month
        ORDER BY year DESC, month DESC
    ";
    $res = $mysqli->query($q);
    while ($r = $res->fetch_assoc()) $monthly_summary[] = $r;
}
// Daily
if ($from_date || $to_date) {
    $where = "r.case_type = 'datechange'";
    if ($from_date && $to_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN '$from_date' AND '$to_date'";
    else if ($from_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= '$from_date'";
    else if ($to_date) $where .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= '$to_date'";
    $q = "
        SELECT 
            DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as case_date,
            SUM(CASE WHEN LOWER(b.order_type) = 'gds' AND r.status = 'open' THEN 1 ELSE 0 END) as fit_pending_count,
            SUM(CASE WHEN LOWER(b.order_type) = 'gds' AND (r.status = 'success' OR r.status = 'fail') THEN 1 ELSE 0 END) as fit_close_count,
            SUM(CASE WHEN (LOWER(b.order_type) != 'gds' OR b.order_type IS NULL) AND r.status = 'open' THEN 1 ELSE 0 END) as gdeals_pending_count,
            SUM(CASE WHEN (LOWER(b.order_type) != 'gds' OR b.order_type IS NULL) AND (r.status = 'success' OR r.status = 'fail') THEN 1 ELSE 0 END) as gdeals_close_count,
            COUNT(*) as total_count
        FROM wpk4_backend_user_portal_requests r
        LEFT JOIN wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
        WHERE $where
        GROUP BY case_date
        ORDER BY case_date DESC
    ";
    $res = $mysqli->query($q);
    while ($r = $res->fetch_assoc()) $date_summary[] = $r;
}
// Agent
foreach ($processed_requests as $req) {
    $agent = $req['agent'] ?: 'Unknown';
    if (!isset($agent_summary[$agent])) $agent_summary[$agent] = ['agent' => $agent, 'total_cases' => 0, 'success_cases' => 0, 'total_revenue' => 0];
    $agent_summary[$agent]['total_cases'] += 1;
    if (strtolower($req['status']) === 'success') {
        $agent_summary[$agent]['success_cases'] += 1;
        $agent_summary[$agent]['total_revenue'] += floatval($req['total_revenue']);
    }
}
foreach ($agent_summary as &$a) $a['success_percent'] = $a['total_cases'] > 0 ? round($a['success_cases'] / $a['total_cases'] * 100, 1) : 0;
unset($a);

$month_names = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

// ADD THIS LINE before HTML output:
$filtered_data = $processed_requests;
?>
<!DOCTYPE html>
<html>
<head>
<title>Date Change Dashboard</title>
<meta charset="utf-8">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Base Styles */
.date-change-container { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
.date-change-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
.date-change-header h1 { color: #ffcc33; margin: 0; font-size: 28px; }
.header-actions { display: flex; gap: 15px; align-items: center; }
.search-filter-container { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
.search-input, .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px; }
.primary-button { background-color: #3498db; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 500; transition: background-color 0.2s; }
.primary-button:hover { background-color: #2980b9; }
.secondary-button { background-color: #f8f9fa; color: #2c3e50; border: 1px solid #ddd; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.secondary-button:hover { background-color: #e9ecef; }

/* Date Filter Container */
.date-filter-container {max-width: 1200px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);}
.date-filter-form {display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;}
.date-filter-form .form-group {margin-bottom: 0;}
.date-filter-form label {display: block; margin-bottom: 5px; font-weight: 500; color: #495057;}

/* No Data Message */
.no-data-message {padding: 20px; background: #f8f9fa; border-radius: 4px; text-align: center; color: #6c757d; margin: 20px 0; border: 1px dashed #dee2e6;}
.no-data-message i {font-size: 24px; margin-bottom: 10px; color: #6c757d;}

/* Table Styles */
.requests-table-container { overflow-x: auto; margin-bottom: 20px; border-radius: 4px; border: 1px solid #e0e0e0; }
.requests-table { width: 100%; border-collapse: collapse; font-size: 16px; }
.requests-table th { background-color: #ffcc33; color: #495057; text-align: left; padding: 12px 15px; font-weight: 600; border-bottom: 2px solid #e0e0e0; }
.requests-table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; }
.requests-table tr:hover { background-color: #f8fafc; }

/* Status Badges */
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.status-pending { background-color: #fff3cd; color: #856404; }
.status-approved { background-color: #d4edda; color: #155724; }
.status-rejected { background-color: #f8d7da; color: #721c24; }

/* Pagination */
.pagination-container { display: flex; justify-content: flex-end; }
.pagination { display: flex; gap: 5px; }
.page-btn { width: 36px; height: 36px; border-radius: 4px; border: 1px solid #ddd; background-color: white; color: #222; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.page-btn:hover:not(.disabled):not(.active) { background-color: #f0f0f0; }
.page-btn.active { background-color: #3498db; color: white; border-color: #3498db; }
.page-btn.disabled { opacity: 0.5; cursor: not-allowed; }

/* Modal Styles */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
.modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 700px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
.modal-header h2 { margin: 0; color: #2c3e50; font-size: 20px; }
.close-modal { font-size: 24px; cursor: pointer; color: #7f8c8d; }
.close-modal:hover { color: #2c3e50; }
.detail-row { display: flex; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
.detail-row.full-width { flex-direction: column; }
.detail-label { font-weight: 500; color: #495057; min-width: 150px; }
.detail-value { color: #2c3e50; }

/* KPI Section */
.kpi-container {padding-left: 100px;}
.kpi-section { margin: 0 50px 20px; background: #fff; padding: 20px; max-width: 1550px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
.kpi-section-title { color: #2c3e50; margin: 0 0 20px 0; font-size: 22px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.kpi-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); transition: all 0.3s ease; border-left: 4px solid transparent; position: relative; overflow: hidden; aspect-ratio: 1/1; display: flex; flex-direction: column; justify-content: space-between; }
.date-change-card { background-color: #e3f2fd; }
.success-card { background-color: #e8f5e9; }
.failure-card { background-color: #ffebee; }
.progress-card { background-color: #fff8e1; }
.cost-card { background-color: #f3e5f5; }
.revenue-card { background-color: #e0f7fa; }
.kpi-card.active { border-left-color: #ffb900; box-shadow: 0 4px 12px rgba(255, 185, 0, 0.2); transform: translateY(-3px); }
.kpi-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
.kpi-link { text-decoration: none; color: inherit; display: flex; flex-direction: column; height: 100%; }
.kpi-icon { margin-bottom: 15px; text-align: center; }
.kpi-emoji { font-size: 40px; display: inline-block; }
.kpi-content { flex-grow: 1; display: flex; flex-direction: column; }
.kpi-value { font-size: 32px; font-weight: 700; margin: 0; line-height: 1; color: #2c3e50; text-align: center; }
.kpi-title { font-size: 16px; color: #555; margin: 10px 0; font-weight: 600; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-percentage { margin-top: auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; font-size: 12px; font-weight: 500; }
.percentage-success { color: #2e7d32; background: rgba(46, 125, 50, 0.1); padding: 2px 6px; border-radius: 10px; }
.percentage-failure { color: #c62828; background: rgba(198, 40, 40, 0.1); padding: 2px 6px; border-radius: 10px; }
.percentage-progress { color: #f9a825; background: rgba(249, 168, 37, 0.1); padding: 2px 6px; border-radius: 10px; }
.percentage-positive { color: #1b5e20; background: rgba(27, 94, 32, 0.1); padding: 2px 6px; border-radius: 10px; }
.percentage-neutral { color: #37474f; background: rgba(55, 71, 79, 0.1); padding: 2px 6px; border-radius: 10px; }

/* Loading Indicator */
#loading-indicator { background: rgba(255, 255, 255, 0.9); position: fixed; top: 0; left: 0; right: 0; bottom: 0; display: flex; justify-content: center; align-items: center; z-index: 9999; font-size: 24px; color: #ffb900; }
.loading-spinner { display: flex; flex-direction: column; align-items: center; gap: 15px; }
.loading-spinner i { font-size: 48px; }

/* Date Picker */
.date-picker { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%237f8c8d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 30px; cursor: pointer; width: 100%; }
.date-picker::placeholder { color: #7f8c8d; opacity: 1; }
.date-picker.has-value::placeholder { color: transparent; }

/* Responsive */
@media (max-width: 768px) {
    .date-change-header, .header-actions { flex-direction: column; align-items: flex-start; width: 100%; }
    .search-filter-container, .search-input, .filter-select { width: 100%; }
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .kpi-value { font-size: 28px; }
    .kpi-emoji { font-size: 36px; }
    .modal-content { width: 90%; margin: 10% auto; }
    .date-filter-form { flex-direction: column; align-items: flex-start; }
    .date-filter-form .form-group { width: 100%; }
}
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .kpi-card { padding: 15px; }
    .kpi-section { margin: 0 20px 20px; }
}

.requests-table tbody tr:nth-child(even) {
    background-color: #f9f9f9; /* Light grey background for even rows */
}

.requests-table tbody tr:hover {
    background-color: #f8fafc; /* Keep your hover color */
}

#export-requests-csv, #export-monthly-csv { margin-right: 8px; display: inline-flex; align-items: center; gap: 6px; background-color: #88d8b0; }
#export-requests-csv i { font-size: 14px; }

/* Monthly Summary Table Styles */
.monthly-summary-table {width: 100%;border-collapse: collapse;}
.monthly-summary-table th { background-color: #ffcc33; color: #2c3e50; padding: 12px 15px; text-align: left; }
.monthly-summary-table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; }
.monthly-summary-table tr:nth-child(even) { background-color: #f9f9f9; }
.monthly-summary-table tr:hover {
    background-color: #f0f7f4;
}

/* Monthly Summary Table Styles */
.daily-summary-table {width: 100%;border-collapse: collapse;}
.daily-summary-table th { background-color: #ffbb00; color: #2c3e50; padding: 12px 15px; text-align: left; }
.daily-summary-table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; }
.daily-summary-table tr:nth-child(even) { background-color: #f9f9f9; }
.daily-summary-table tr:hover {
    background-color: #f0f7f4;
}

.pagination {display: flex;gap: 5px;justify-content: center;margin-top: 20px;}
.page-btn {width: 36px;height: 36px; border-radius: 4px; border: 1px solid #ddd; background-color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px;}
.page-btn:hover:not(.disabled):not(.active) {background-color: #f0f0f0;}
.page-btn.active {background-color: #3498db; color: white;border-color: #3498db;}
.page-btn.disabled {opacity: 0.5; cursor: not-allowed;}
.pagination-ellipsis {display: flex; align-items: center; padding: 0 10px; color: #6c757d;}
body { background: #f8f9fa; }
<?php /* Paste your previous CSS here for brevity */ ?>
</style>
</head>
<body>
<!-- Single, deduped Date Range Filter Form at the Top -->
<div class="date-filter-container">
    <form method="get" action="" class="date-filter-form">
        <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
        <div class="form-group">
            <label for="from-date">From Date:</label>
            <input type="text" id="from-date" name="from_date" class="date-picker" 
                   placeholder="Select start date" value="<?php echo esc_attr($from_date); ?>">
        </div>
        <div class="form-group">
            <label for="to-date">To Date:</label>
            <input type="text" id="to-date" name="to_date" class="date-picker" 
                   placeholder="Select end date" value="<?php echo esc_attr($to_date); ?>">
        </div>
        <button type="submit" class="primary-button">Load Data</button>
        <?php if (!empty($from_date) || !empty($to_date)): ?>
            <a href="?page_id=<?php echo get_the_ID(); ?>" class="secondary-button">Clear Filter</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($from_date) && empty($to_date)): ?>
    <div class="data-summary">
        <div class="no-data-message">
            <i class="fas fa-info-circle"></i>
            Please select a date range to view date change requests.
        </div>
    </div>
<?php elseif (empty($processed_requests)): ?>
    <div class="data-summary">
        <div class="no-data-message">
            <i class="fas fa-info-circle"></i>
            No date change requests found for the selected date range.
        </div>
    </div>
<?php else: ?>
    <!-- Only show KPIs and tables when we have data -->
    <div class="kpi-container">
        <!-- KPI Section -->
        <div class="kpi-section">
            <h2 class="kpi-section-title">📊 Date Change Request Overview</h2>
            <div class="kpi-grid">
                <!-- Total Date Changes -->
                <div class="kpi-card date-change-card <?php echo $current_filter === 'total_date_changes' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=total_date_changes" class="kpi-link" data-filter="total_date_changes">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">🔄</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count(array_filter($processed_requests, function($r) { return $r['request_type'] === 'datechange'; })); ?></div>
                            <div class="kpi-title">Date Changes</div>
                        </div>
                    </a>
                </div>

                <!-- Success -->
                <div class="kpi-card success-card <?php echo $current_filter === 'status_success' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=status_success" class="kpi-link" data-filter="status_success">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">✅</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count(array_filter($processed_requests, function($r) { return $r['status'] === 'success'; })); ?></div>
                            <div class="kpi-title">Success</div>
                            <div class="kpi-percentage">
                                <span class="percentage-success">
                                    <?php 
                                    $total = count($processed_requests);
                                    $success_count = count(array_filter($processed_requests, function($r) {
                                        return $r['status'] === 'success';
                                    }));

                                    $percentage = $total > 0 ? round(($success_count / $total) * 100, 1) : 0;
                                    echo $percentage . '% of total';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                
                <!-- Failure -->
                <div class="kpi-card failure-card <?php echo $current_filter === 'status_failure' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=status_failure" class="kpi-link" data-filter="status_failure">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">❌</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count(array_filter($processed_requests, function($r) { return $r['status'] === 'fail'; })); ?></div>
                            <div class="kpi-title">Failure</div>
                            <div class="kpi-percentage">
                                <span class="percentage-failure">
                                    <?php 
                                    $total = count($processed_requests);
                                    $failure_count = count(array_filter($processed_requests, function($r) {
                                        return $r['status'] === 'fail';
                                    }));
                                
                                    $percentage = $total > 0 ? round(($failure_count / $total) * 100, 1) : 0;
                                    echo $percentage . '% of total';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                
                <!-- In Progress -->
                <div class="kpi-card progress-card <?php echo $current_filter === 'status_in_progress' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=status_in_progress" class="kpi-link" data-filter="status_in_progress">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">⏳</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count(array_filter($processed_requests, function($r) { return $r['status'] === 'open'; })); ?></div>
                            <div class="kpi-title">In Progress</div>
                            <div class="kpi-percentage">
                                <span class="percentage-progress">
                                    <?php 
                                    $total = count($processed_requests);
                                    $progress_count = count(array_filter($processed_requests, function($r) {
                                        return $r['status'] === 'open';
                                    }));
                                
                                    $percentage = $total > 0 ? round(($progress_count / $total) * 100, 1) : 0;
                                    echo $percentage . '% of total';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Total Cost Given -->
                <div class="kpi-card cost-card <?php echo $current_filter === 'total_cost_given' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=total_cost_given" class="kpi-link" data-filter="total_cost_given">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">💸</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value">$<?php echo number_format(array_sum(array_column($processed_requests, 'cost_given')), 2); ?></div>
                            <div class="kpi-title">Total Cost</div>
                            <div class="kpi-percentage">
                                <span class="percentage-neutral">
                                    Avg: $<?php echo count($processed_requests) > 0 ? number_format(array_sum(array_column($processed_requests, 'cost_given')) / count($processed_requests), 2) : '0.00'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Total Revenue -->
                <div class="kpi-card revenue-card <?php echo $current_filter === 'total_revenue' ? 'active' : ''; ?>">
                    <a href="?page_id=<?php echo get_the_ID(); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&filter=total_revenue" class="kpi-link" data-filter="total_revenue">
                        <div class="kpi-icon">
                            <span class="kpi-emoji">💰</span>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value">$<?php echo number_format(array_sum(array_column($processed_requests, 'total_revenue')), 2); ?></div>
                            <div class="kpi-title">Total Revenue</div>
                            <div class="kpi-percentage">
                                <span class="percentage-positive">
                                    Avg: $<?php echo count($processed_requests) > 0 ? number_format(array_sum(array_column($processed_requests, 'total_revenue')) / count($processed_requests), 2) : '0.00'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" style="display: none; text-align: center; padding: 20px;">
        <div class="loading-spinner" style="font-size: 24px; color: #ffb900;">
            <i class="fas fa-spinner fa-spin"></i> Loading
        </div>
    </div>
    
    <!-- Monthly Summary Table -->
    <?php if (!empty($monthly_summary)): ?>
    <div class="date-change-container">
        <div class="date-change-header">
            <h1>Monthly Date Change Summary</h1>
            <div class="header-actions">
                <div class="year-filter">
                    <label for="year-filter">Filter by Year:</label>
                    <select id="year-filter" class="year-dropdown">
                        <?php
                        // Get current year
                        $current_year = date('Y');
                        
                        // Get distinct years from your data
                        $years = [];
                        foreach ($monthly_summary as $summary) {
                            $years[$summary['year']] = true;
                        }
                        $years = array_keys($years);
                        rsort($years); // Show most recent years first
                
                        // Add "All Years" option
                        echo '<option value="all">All Years</option>';
                
                        // Add year options
                        foreach ($years as $year) {
                            $selected = ($year == $current_year) ? 'selected' : '';
                            echo '<option value="' . esc_attr($year) . '" ' . $selected . '>' . esc_html($year) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <button type="button" id="export-monthly-csv" class="primary-button">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="date-change-content">
            <div class="requests-table-container">
                <table class="monthly-summary-table">   
                <thead>
                        <tr>
                            <th>Year</th>
                            <th>Month</th>
                            <th>GDeals PAX (PIF)</th>
                            <th>FIT PAX (PIF)</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="monthly-summary-body">
                        <?php foreach ($monthly_summary as $summary): ?>
                            <tr data-year="<?php echo esc_attr($summary['year']); ?>">
                                <td><?php echo esc_html($summary['year']); ?></td>
                                <td><?php echo esc_html($month_names[$summary['month']] ?? $summary['month']); ?></td>
                                <td><?php echo esc_html($summary['gdeals_count']); ?></td>
                                <td><?php echo esc_html($summary['fit_count']); ?></td>
                                <td><?php echo esc_html($summary['total_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($date_summary)): ?>
        <div class="date-change-container">
            <div class="date-change-header">
                <h1>Date-wise Date Change Summary</h1>
                <div class="header-actions">
                    <button type="button" id="export-daily-csv" class="primary-button">
                        <i class="fas fa-file-export"></i> Export CSV
                    </button>
                </div>
            </div>
        
            <div class="date-change-content">
                <div class="requests-table-container">
                    <table class="daily-summary-table">   
                        <thead style="background-color: #ffbb00;">
                            <tr>
                                <th>Date</th>
                                <th>GDeals (Pending)</th>
                                <th>GDeals (Close)</th>
                                <th>FIT PAX (Pending)</th>
                                <th>FIT PAX (Close)</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="daily-summary-body">
                            <?php foreach ($date_summary as $summary): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($summary['case_date'])); ?></td>
                                    <td><?php echo esc_html($summary['gdeals_pending_count']); ?></td>
                                    <td><?php echo esc_html($summary['gdeals_close_count']); ?></td>
                                    <td><?php echo esc_html($summary['fit_pending_count']); ?></td>
                                    <td><?php echo esc_html($summary['fit_close_count']); ?></td>
                                    <td><?php echo esc_html($summary['total_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    
    <?php if (!empty($agent_summary)): ?>
        <div class="date-change-container">
            <div class="date-change-header">
                <h1>Agent-wise Date Change Performance</h1>
                <div class="header-actions">
                    <button type="button" id="export-agent-csv" class="primary-button">
                        <i class="fas fa-file-export"></i> Export CSV
                    </button>
                </div>
            </div>
    
            <div class="date-change-content">
                <div class="requests-table-container">
                    <table class="agent-summary-table">
                        <thead style="background-color: #ffbb00;">
                            <tr>
                                <th>Agent</th>
                                <th>Total Cases</th>
                                <th>Success Cases</th>
                                <th>Success %</th>
                                <th>Total Revenue ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agent_summary as $agent): ?>
                                <tr>
                                    <td><?php echo esc_html($agent['agent']); ?></td>
                                    <td><?php echo esc_html($agent['total_cases']); ?></td>
                                    <td><?php echo esc_html($agent['success_cases']); ?></td>
                                    <td><?php echo esc_html($agent['success_percent']); ?>%</td>
                                    <td>$<?php echo number_format($agent['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <!-- Table Container -->
    <div id="table-container" class="date-change-container" style="<?php echo ($show_kpis && !empty($filtered_data)) ? '' : 'display: none;' ?>">        
        <div class="date-change-header">
            <h1>Date Change Requests</h1>
            <div class="header-actions">
                <div class="search-filter-container">
                    <!-- Search -->
                    <div class="form-group">
                        <label for="search-requests">Search</label>
                        <input type="text" id="search-requests" placeholder="Search requests..." class="search-input">
                    </div>

                    <!-- Status Filter -->
                    <div class="form-group">
                        <label for="status-filter">Status</label>
                        <select id="status-filter" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="success" <?php echo isset($_GET['status-filter']) && $_GET['status-filter'] === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="fail" <?php echo isset($_GET['status-filter']) && $_GET['status-filter'] === 'fail' ? 'selected' : ''; ?>>Fail</option>
                            <option value="open" <?php echo isset($_GET['status-filter']) && $_GET['status-filter'] === 'open' ? 'selected' : ''; ?>>In Progress</option>
                        </select>
                    </div>

                    <!-- Airline Filter -->
                    <div class="form-group">
                        <label for="airline-filter">Airline</label>
                        <select id="airline-filter" class="filter-select">
                            <option value="">All Airlines</option>
                            <?php 
                            $airlines = array_unique(array_column($processed_requests, 'airline'));
                            sort($airlines);
                            foreach ($airlines as $airline): ?>
                                <option value="<?php echo esc_attr($airline); ?>"><?php echo esc_html($airline); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Booking Type Filter -->
                    <div class="form-group">
                        <label for="booking-type-filter">Booking Type</label>
                        <select id="booking-type-filter" class="filter-select">
                            <option value="">All Types</option>
                            <?php 
                            $booking_types = array_unique(array_column($processed_requests, 'booking_type'));
                            sort($booking_types);
                            foreach ($booking_types as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Travel Date Filter -->
                    <!--<div class="form-group">-->
                    <!--    <label>Travel Date</label>-->
                    <!--    <div style="display: flex; gap: 6px;">-->
                    <!--        <div>-->
                    <!--            <label for="travel-date-from" style="font-size: 12px; display: block;"></label>-->
                    <!--            <input type="text" id="travel-date-from" class="filter-select date-picker"-->
                    <!--                placeholder="From">-->
                    <!--        </div>-->
                    <!--        <div>-->
                    <!--            <label for="travel-date-to" style="font-size: 12px; display: block;"></label>-->
                    <!--            <input type="text" id="travel-date-to" class="filter-select date-picker" placeholder="To">-->
                    <!--        </div>-->
                    <!--    </div>-->
                    <!--</div>-->

                    <!-- Request Date Filter -->
                    <!--<div class="form-group">-->
                    <!--    <label>Request Date</label>-->
                    <!--    <div style="display: flex; gap: 6px;">-->
                    <!--        <div>-->
                    <!--            <label for="request-date-from" style="font-size: 12px; display: block;"></label>-->
                    <!--            <input type="text" id="request-date-from" class="filter-select date-picker"-->
                    <!--                placeholder="From">-->
                    <!--        </div>-->
                    <!--        <div>-->
                    <!--            <label for="request-date-to" style="font-size: 12px; display: block;"></label>-->
                    <!--            <input type="text" id="request-date-to" class="filter-select date-picker" placeholder="To">-->
                    <!--        </div>-->
                    <!--    </div>-->
                    <!--</div>-->

                    <div class="form-group" style="align-self: flex-end;">
                        <button type="button" id="apply-filters" class="primary-button">Apply Filters</button>
                        <button type="button" id="reset-filters" class="secondary-button">Reset</button>
                        <button type="button" id="export-requests-csv" class="primary-button">
                            <i class="fas fa-file-export"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="date-change-content">
            <?php if (!empty($filtered_data)): ?>
                <div class="requests-table-container">
                    <table id="paginated-table" class="requests-table">
                        <thead>
                            <tr>
                                <th>Query Date</th>
                                <th>Status</th>
                                <th>Agent</th>
                                <th>Case ID</th>
                                <th>Reservation Ref</th>
                                <th>Request Type</th>
                                <th>Pax Count</th>
                                <th>Airline</th>
                                <!--<th>Last Quoted By</th>-->
                                <th>Booking Type</th>
                                <th>Old Travel Date</th>
                                <!--<th>Airline Change Fee</th>-->
                                <!--<th>Fare Difference</th>-->
                                <!--<th>Gaura Travel Service Fee</th>-->
                                <!--<th>Buffer</th>-->
                                <th>Cost Given</th>
                                <!--<th>Expected Cost</th>-->
                                <th>Cost Taken</th>
                                <th>Total Revenue</th>
                                <th>Status Date</th>
                            </tr>
                        </thead>
                        <tbody id="requests-table-body">
                            <?php foreach ($filtered_data as $request):
                                $status_class = strtolower($request['status']); 
                                ?>
                                <tr data-status="<?php echo esc_attr($status_class); ?>">
                                    <td><?php echo esc_html($request['query_date']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($request['agent']); ?></td>
                                    <td><?php echo esc_html($request['case_id']); ?></td>
                                    <td><?php echo esc_html($request['pnr']); ?></td>
                                    <td><?php echo esc_html($request['request_type']); ?></td>
                                    <td><?php echo esc_html($request['pax_count']); ?></td>
                                    <td><?php echo esc_html($request['airline']); ?></td>
                                    <!--<td><?php echo esc_html($request['last_quoted_by']); ?></td>-->
                                    <td><?php echo esc_html($request['booking_type']); ?></td>
                                    <td><?php echo esc_html($request['old_travel_date']); ?></td>
                                    <!--<td><?php echo esc_html($request['airline_change_fee']); ?></td>-->
                                    <!--<td><?php echo esc_html($request['fare_difference']); ?></td>-->
                                    <!--<td><?php echo esc_html($request['gaura_travel_service_fee']); ?></td>-->
                                    <!--<td><?php echo esc_html($request['buffer']); ?></td>-->
                                    <td>$<?php echo number_format(floatval($request['cost_given']), 2); ?></td>
                                    <!--<td>$<?php echo number_format(floatval($request['expected_cost']), 2); ?></td>-->
                                    <td>$<?php echo number_format(floatval($request['cost_taken']), 2); ?></td>
                                    <td>$<?php echo number_format(floatval($request['total_revenue']), 2); ?></td>
                                    <td><?php echo esc_html($request['status_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div class="pagination">
                        <button class="page-btn disabled"><i class="fas fa-chevron-left"></i></button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data-message">
                    <i class="fas fa-info-circle"></i>
                    No data matches the current filters.
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif;
?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>

    document.addEventListener('DOMContentLoaded', function () {

        // Always initialize flatpickr for all .date-picker inputs immediately
        const dateOptions = {
            dateFormat: "Y-m-d",
            allowInput: true,
            static: true,
            monthSelectorType: 'static',
            prevArrow: '<i class="fas fa-chevron-left"></i>',
            nextArrow: '<i class="fas fa-chevron-right"></i>',
            locale: {
                firstDayOfWeek: 1
            },
            clickOpens: true,
            onReady: function(selectedDates, dateStr, instance) {
                instance._input.setAttribute('placeholder', 
                    instance.element.id.includes('from') ? 'From' : 'To');
            },
            onOpen: function(selectedDates, dateStr, instance) {
                instance._input.removeAttribute('placeholder');
            },
            onClose: function(selectedDates, dateStr, instance) {
                if (!selectedDates.length) {
                    instance._input.setAttribute('placeholder', 
                        instance.element.id.includes('from') ? 'From' : 'To');
                }
            }
        };
        document.querySelectorAll('.date-picker').forEach(input => {
            flatpickr(input, dateOptions);
        });

        // ...existing code...
        // Apply Filters button
        document.getElementById('apply-filters').addEventListener('click', function(e) {
            e.preventDefault();
            applyFilters();
        });
        // ...existing code...
        // Reset Filters button
        document.getElementById('reset-filters').addEventListener('click', function(e) {
            e.preventDefault();
            resetFilters();
        });
        // ...existing code...
        // Search input - apply on Enter key
        document.getElementById('search-requests').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        // ...existing code...
        // Form submission handler
        const dateFilterForm = document.querySelector('.date-filter-form');
        if (dateFilterForm) {
            dateFilterForm.addEventListener('submit', function(e) {
                const fromDate = document.getElementById('from-date').value;
                const toDate = document.getElementById('to-date').value;
                // Prevent submission if both dates are empty
                if (!fromDate && !toDate) {
                    e.preventDefault();
                    alert('Please select at least one date');
                    return false;
                }
                // Show loading indicator
                document.getElementById('loading-indicator').style.display = 'flex';
                return true;
            });
        }
        
        // Initialize all date pickers
        // document.querySelectorAll('.date-picker').forEach(input => {
        //     flatpickr(input, dateOptions);
        // });

        // // Initialize all date pickers
        // document.querySelectorAll('.date-picker').forEach(input => {
        //     flatpickr(input, dateOptions);
        //     input.setAttribute('placeholder',
        //         input.id.includes('from') ? 'From' : 'To');
        // });

        // // Initialize request date picker with different options
        // const requestDatePicker = flatpickr("#request-date", {
        //     dateFormat: "Y-m-d",
        //     allowInput: true,
        //     static: true,
        //     monthSelectorType: 'static',
        //     prevArrow: '<i class="fas fa-chevron-left"></i>',
        //     nextArrow: '<i class="fas fa-chevron-right"></i>',
        //     locale: {
        //         firstDayOfWeek: 1
        //     },
        //     defaultDate: null
        // });

        // Modal functionality
        const viewDetailsModal = document.getElementById('view-details-modal');
        const closeModalButtons = document.querySelectorAll('.close-modal');

        // Open view details modal with request data
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const requestData = JSON.parse(this.getAttribute('data-request'));

                // Populate modal with request data
                document.getElementById('detail-query-date').textContent = requestData.query_date || '-';
                document.getElementById('detail-first-quote-day').textContent = requestData.first_quote_day || '-';
                document.getElementById('detail-agent').textContent = requestData.agent || '-';
                document.getElementById('detail-case-id').textContent = requestData.case_id || '-';
                document.getElementById('detail-pnr').textContent = requestData.pnr || '-';
                document.getElementById('detail-request-type').textContent = requestData.request_type || '-';
                document.getElementById('detail-request-sub-type').textContent = requestData.request_sub_type || '-';
                document.getElementById('detail-actual-case').textContent = requestData.actual_case || '-';
                document.getElementById('detail-pax-count').textContent = requestData.pax_count || '-';
                document.getElementById('detail-airline').textContent = requestData.airline || '-';
                document.getElementById('detail-last-quoted-by').textContent = requestData.last_quoted_by || '-';
                document.getElementById('detail-booking-type').textContent = requestData.booking_type || '-';
                document.getElementById('detail-old-travel-date').textContent = requestData.old_travel_date || '-';
                document.getElementById('detail-airline-change-fee').textContent = requestData.airline_change_fee ? '$' + parseFloat(requestData.airline_change_fee).toFixed(2) : '-';
                document.getElementById('detail-fare-difference').textContent = requestData.fare_difference ? '$' + parseFloat(requestData.fare_difference).toFixed(2) : '-';
                document.getElementById('detail-service-fee').textContent = requestData.gaura_travel_service_fee ? '$' + parseFloat(requestData.gaura_travel_service_fee).toFixed(2) : '-';
                document.getElementById('detail-buffer').textContent = requestData.buffer ? '$' + parseFloat(requestData.buffer).toFixed(2) : '-';
                document.getElementById('detail-cost-given').textContent = requestData.cost_given ? '$' + parseFloat(requestData.cost_given).toFixed(2) : '-';
                document.getElementById('detail-expected-cost').textContent = requestData.expected_cost ? '$' + parseFloat(requestData.expected_cost).toFixed(2) : '-';
                document.getElementById('detail-cost-taken').textContent = requestData.cost_taken ? '$' + parseFloat(requestData.cost_taken).toFixed(2) : '-';
                document.getElementById('detail-total-revenue').textContent = requestData.total_revenue ? '$' + parseFloat(requestData.total_revenue).toFixed(2) : '-';

                // Status with badge
                const statusBadge = document.createElement('span');
                const statusClass = requestData.status ? requestData.status.toLowerCase().replace(' ', '-') : '';
                statusBadge.className = 'status-badge status-' + statusClass;
                statusBadge.textContent = requestData.status || '-';
                document.getElementById('detail-status').innerHTML = '';
                document.getElementById('detail-status').appendChild(statusBadge);

                document.getElementById('detail-status-date').textContent = requestData.status_date || '-';
                document.getElementById('detail-failure-reason').textContent = requestData.failure_reason || '-';
                document.getElementById('detail-remarks').textContent = requestData.remarks || '-';

                viewDetailsModal.style.display = 'block';
            });
        });
        
        // Function to render the table with pagination
        let currentPage = 1;
        const rowsPerPage = 10;
        let filteredRequests = <?php echo json_encode($filtered_data); ?>;
        
        // Render table rows for current page
        function renderTable() {
            const tbody = document.getElementById('requests-table-body');
            tbody.innerHTML = '';
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageRows = filteredRequests.slice(start, end);
        
            pageRows.forEach(request => {
                const status_class = request.status ? request.status.toLowerCase() : '';
                const row = document.createElement('tr');
                row.setAttribute('data-status', status_class);
                row.innerHTML = `
                    <td>${request.query_date}</td>
                    <td><span class="status-badge status-${status_class}">${request.status}</span></td>
                    <td>${request.agent}</td>
                    <td>${request.case_id}</td>
                    <td>${request.pnr}</td>
                    <td>${request.request_type}</td>
                    <td>${request.pax_count}</td>
                    <td>${request.airline}</td>
                    <td>${request.booking_type}</td>
                    <td>${request.old_travel_date}</td>
                    <td>$${parseFloat(request.cost_given).toFixed(2)}</td>
                    <td>$${parseFloat(request.cost_taken).toFixed(2)}</td>
                    <td>$${parseFloat(request.total_revenue).toFixed(2)}</td>
                    <td>${request.status_date}</td>
                `;
                tbody.appendChild(row);
            });
        
            updatePaginationControls(filteredRequests.length);
        }

        // Function to update pagination controls
        function updatePaginationControls(totalRows) {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const paginationContainer = document.querySelector('.pagination');
            if (!paginationContainer) return;
            paginationContainer.innerHTML = '';
        
            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.className = 'page-btn';
            if (currentPage === 1) prevBtn.classList.add('disabled');
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderTable();
                }
            };
            paginationContainer.appendChild(prevBtn);
        
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'page-btn' + (i === currentPage ? ' active' : '');
                pageBtn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                paginationContainer.appendChild(pageBtn);
            }
        
            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.className = 'page-btn';
            if (currentPage === totalPages) nextBtn.classList.add('disabled');
            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTable();
                }
            };
            paginationContainer.appendChild(nextBtn);
        }


        const yearFilter = document.getElementById('year-filter');
        const tableBody = document.getElementById('monthly-summary-body');
        const rows = tableBody.querySelectorAll('tr');

        yearFilter.addEventListener('change', function () {
            const selectedYear = this.value;

            rows.forEach(row => {
                if (selectedYear === 'all' || row.getAttribute('data-year') === selectedYear) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
            // Filter by current year by default
        function filterByCurrentYear() {
            const currentYear = new Date().getFullYear().toString();
            const yearFilter = document.getElementById('year-filter');
            const tableBody = document.getElementById('monthly-summary-body');
            const rows = tableBody.querySelectorAll('tr');
            
            // If current year exists in the dropdown, select it
            let hasCurrentYear = false;
            yearFilter.querySelectorAll('option').forEach(option => {
                if (option.value === currentYear) {
                    hasCurrentYear = true;
                    yearFilter.value = currentYear;
                }
            });
            
            // Filter rows
            rows.forEach(row => {
                const rowYear = row.getAttribute('data-year');
                if (hasCurrentYear) {
                    row.style.display = (rowYear === currentYear) ? '' : 'none';
                } else {
                    row.style.display = ''; // Show all if current year not available
                }
            });
        }
        
        // Call this function when the page loads
        filterByCurrentYear();
        
        // Keep your existing year filter change handler
        document.getElementById('year-filter').addEventListener('change', function() {
            const selectedYear = this.value;
            const rows = document.querySelectorAll('#monthly-summary-body tr');
            
            rows.forEach(row => {
                if (selectedYear === 'all' || row.getAttribute('data-year') === selectedYear) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });


        // Export to CSV function for requests table only
        document.getElementById('export-requests-csv').addEventListener('click', function () {
            // Get the requests table specifically
            const requestsTable = document.querySelector('.requests-table');
            
            // Get visible rows only (respecting current filters)
            const visibleRows = Array.from(requestsTable.querySelectorAll('tbody tr'))
                .filter(row => row.style.display !== 'none');
        
            if (visibleRows.length === 0) {
                alert('No data to export!');
                return;
            }
        
            // Get headers from the requests table
            const headers = Array.from(requestsTable.querySelectorAll('thead th'))
                .map(th => th.textContent.trim());
        
            // Prepare CSV content
            let csvContent = headers.join(',') + '\n';
        
            visibleRows.forEach(row => {
                const rowData = Array.from(row.cells)
                    .map(cell => {
                        // Handle special cases (like status badges)
                        if (cell.querySelector('.status-badge')) {
                            return cell.querySelector('.status-badge').textContent.trim();
                        }
                        // Handle currency values
                        const text = cell.textContent.trim();
                        if (text.startsWith('$')) {
                            return text.replace('$', '').trim();
                        }
                        // Default case
                        return `"${text.replace(/"/g, '""')}"`; // Escape quotes
                    });
                csvContent += rowData.join(',') + '\n';
            });
        
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `date-change-requests_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Export Monthly Summary to CSV (filtered by year)
        document.getElementById('export-monthly-csv').addEventListener('click', function () {
            // Get the monthly summary table specifically
            const monthlyTable = document.querySelector('.monthly-summary-table');
            
            if (!monthlyTable) {
                alert('Could not find the monthly summary table!');
                return;
            }
        
            // Get only visible rows (filtered by year selection)
            const visibleRows = Array.from(monthlyTable.querySelectorAll('tbody tr'))
                .filter(row => row.style.display !== 'none');
        
            if (visibleRows.length === 0) {
                alert('No data to export!');
                return;
            }
        
            // Get headers
            const headers = Array.from(monthlyTable.querySelectorAll('thead th'))
                .map(th => th.textContent.trim());
        
            // Prepare CSV content
            let csvContent = headers.join(',') + '\n';
        
            visibleRows.forEach(row => {
                const rowData = Array.from(row.cells)
                    .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`);
                csvContent += rowData.join(',') + '\n';
            });
        
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `monthly-date-change-summary_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // Export Daily Summary to CSV
        document.getElementById('export-daily-csv').addEventListener('click', function () {
            // Get the daily summary table specifically
            const dailyTable = document.querySelector('.daily-summary-table');
            
            if (!dailyTable) {
                alert('Could not find the daily summary table!');
                return;
            }
        
            // Get all rows
            const rows = Array.from(dailyTable.querySelectorAll('tbody tr'));
        
            if (rows.length === 0) {
                alert('No data to export!');
                return;
            }
        
            // Get headers
            const headers = Array.from(dailyTable.querySelectorAll('thead th'))
                .map(th => th.textContent.trim());
        
            // Prepare CSV content
            let csvContent = headers.join(',') + '\n';
        
            rows.forEach(row => {
                const rowData = Array.from(row.cells)
                    .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`);
                csvContent += rowData.join(',') + '\n';
            });
        
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `daily-date-change-summary_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        document.getElementById('export-agent-csv').addEventListener('click', function () {
            const table = document.querySelector('.agent-summary-table');
            let csvContent = "";
            const rows = table.querySelectorAll('tr');
        
            rows.forEach(row => {
                const cols = row.querySelectorAll('th, td');
                const rowData = Array.from(cols).map(col => `"${col.innerText.trim()}"`);
                csvContent += rowData.join(",") + "\n";
            });
        
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "agent_performance_summary.csv";
            link.click();
        });



        // Close modals
        closeModalButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                viewDetailsModal.style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target === viewDetailsModal) {
                viewDetailsModal.style.display = 'none';
            }
        });

        // KPI click handler to show loading and then table
        document.querySelectorAll('.kpi-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const filter = this.getAttribute('data-filter');

                // Show loading indicator
                document.getElementById('loading-indicator').style.display = 'flex';

                // Hide table while loading
                document.getElementById('table-container').style.display = 'none';

                // Simulate loading for 1 second
                setTimeout(function () {
                    // Hide loading indicator
                    document.getElementById('loading-indicator').style.display = 'none';

                    // Show table
                    document.getElementById('table-container').style.display = 'block';

                    // Navigate to the filtered page
                    window.location.href = `?request_date=<?php echo urlencode($selected_date); ?>&filter=${filter}`;
                }, 1000);
            });
        });

        // Enhanced filter functionality
        function applyFilters() {
            const statusFilter = document.getElementById('status-filter').value;
            const airlineFilter = document.getElementById('airline-filter').value.toLowerCase();
            const bookingTypeFilter = document.getElementById('booking-type-filter').value.toLowerCase();
            const searchValue = document.getElementById('search-requests').value.toLowerCase();
        
            // Start from the full PHP array
            let allRequests = <?php echo json_encode($processed_requests); ?>;
            filteredRequests = allRequests.filter(request => {
                let showRow = true;
                if (statusFilter && request.status.toLowerCase() !== statusFilter) showRow = false;
                if (airlineFilter && request.airline.toLowerCase() !== airlineFilter) showRow = false;
                if (bookingTypeFilter && request.booking_type.toLowerCase() !== bookingTypeFilter) showRow = false;
                if (searchValue) {
                    const rowText = Object.values(request).join(' ').toLowerCase();
                    if (!rowText.includes(searchValue)) showRow = false;
                }
                return showRow;
            });
        
            currentPage = 1;
            renderTable();
        
            // Show/hide "no data" message
            const noDataMessage = document.querySelector('.no-data-message');
            if (noDataMessage) {
                noDataMessage.style.display = filteredRequests.length ? 'none' : 'block';
            }
        }

        // Add this function to update pagination after filtering
        function updatePagination() {
            const visibleRows = Array.from(document.querySelectorAll('.requests-table tbody tr'))
                .filter(row => row.style.display !== 'none');
            const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
            
            // Reset to first page when filters change
            currentPage = 1;
            renderPagination(totalPages);
        }
        
        // Modify the renderPagination function to accept totalPages parameter
        function renderPagination(totalPages) {
            const paginationContainer = document.querySelector(".pagination");
            if (!paginationContainer) return;
        
            paginationContainer.innerHTML = "";
        
            const prevBtn = document.createElement("button");
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.className = "page-btn";
            if (currentPage === 1) prevBtn.classList.add("disabled");
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderTable();
                }
            };
            paginationContainer.appendChild(prevBtn);
        
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement("button");
                btn.textContent = i;
                btn.className = "page-btn" + (i === currentPage ? " active" : "");
                btn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                paginationContainer.appendChild(btn);
            }
        
            const nextBtn = document.createElement("button");
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.className = "page-btn";
            if (currentPage === totalPages) nextBtn.classList.add("disabled");
            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTable();
                }
            };
            paginationContainer.appendChild(nextBtn);
        }
        // Reset all filters
        function resetFilters() {
            // Reset all filter dropdowns and inputs
            document.getElementById('status-filter').value = '';
            document.getElementById('airline-filter').value = '';
            document.getElementById('booking-type-filter').value = '';
            document.getElementById('search-requests').value = '';
            
            // Reset date pickers (both travel and request dates)
            const datePickers = [
                'travel-date-from', 'travel-date-to',
                'request-date-from', 'request-date-to'
            ];
            
            datePickers.forEach(pickerId => {
                const picker = document.getElementById(pickerId);
                if (picker) {
                    picker.value = '';
                    // Clear Flatpickr instance if it exists
                    if (typeof flatpickr !== 'undefined' && picker._flatpickr) {
                        picker._flatpickr.clear();
                    }
                }
            });
        
            // Show all rows immediately (no need to call applyFilters)
            const rows = document.querySelectorAll('#paginated-table tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        
            // Reset pagination to first page
            currentPage = 1;
            renderTable();
        
            // Hide any "no data" message that might be showing
            const noDataMessage = document.querySelector('.no-data-message');
            if (noDataMessage) {
                noDataMessage.style.display = 'none';
            }

    // Optional: Reset URL parameters if you're using them
    // const cleanUrl = window.location.pathname + '?page_id=' + <?php echo get_the_ID(); ?>;
    // window.history.pushState({}, '', cleanUrl);
        }

        // Attach event listeners
        // document.getElementById('apply-filters').addEventListener('click', applyFilters);
        // document.getElementById('reset-filters').addEventListener('click', resetFilters);

        // // Also apply filters when Enter is pressed in search
        // document.getElementById('search-requests').addEventListener('keyup', function (e) {
        //     if (e.key === 'Enter') {
        //         applyFilters();
        //     }
        // });

        // Initialize by applying filters (in case there are any default values)
        applyFilters();
        renderTable();
    });
</script>
</body>
</html>
