<?php
/**
 * Template Name: Employee Roster Register
 * Template Post Type: post, page
 */

get_header();
global $wpdb;

$roster_table = $wpdb->prefix . 'backend_employee_roster';
$agent_codes_table = $wpdb->prefix . 'backend_agent_codes';
$roster_requests_table = $wpdb->prefix . 'manage_roster_requests';

$selected_month = isset($_GET['month']) 
    ? sanitize_text_field($_GET['month']) 
    : date('F');
$current_user = wp_get_current_user();
$current_user_login = $current_user->user_login;

$agent_data_row = $wpdb->get_row($wpdb->prepare(
    "SELECT agent_name FROM $agent_codes_table WHERE wordpress_user_name = %s",
    $current_user_login
));

if (!$agent_data_row) {
    echo "<div style='padding:20px;color:red;'>No matching agent found for user: <strong>" . esc_html($current_user_login) . "</strong></div>";
    get_footer();
    exit;
}

$username = $agent_data_row->agent_name;

$agent_data = null;
$employee_name = '';
$sale_manager = '';
$current_shift = 'NA';
$current_rdo = 'NA';

$day_mapping = [
    'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
    'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
];



// 1. Default to current month if not set
$selected_month = isset($_GET['month']) 
    ? sanitize_text_field($_GET['month']) 
    : date('F', strtotime('first day of this monthh'));

// 2. Get current and next month names
$current_month_name = date('F');
$currentMonthNumber = date('m');
$next_month_name = date('F', strtotime('first day of next month'));

// 3. Determine the target month
if ($selected_month === 'current') {
    $target_month = $current_month_name;
} elseif ($selected_month === 'next') {
    $target_month = $next_month_name;
} else {
    $target_month = $selected_month;
}

// 4. Build an array of month names (next 12 months)
$months = [];
for ($i = 0; $i < 12; $i++) {
    $months[] = date('F', strtotime("+$i month"));
}


$agent_record = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $agent_codes_table WHERE agent_name LIKE %s",
    $username
));

if ($agent_record) {
    $roster_code = $agent_record->roster_code;
    $agent_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $roster_table WHERE employee_code = %s AND month = %s",
        $roster_code,
        $selected_month
    ));
}


if ($agent_data) {
        $sale_manager = $agent_data->sm ?? '';
        $current_shift = $agent_data->shift_time ?? 'NA';
        if (preg_match('/^\d{4}$/', $current_shift)) {
            $current_shift = substr($current_shift, 0, 2) . ':' . substr($current_shift, 2);
        }
        $rdo_day = strtolower($agent_data->rdo ?? '');
        $current_rdo = isset($day_mapping[strtolower($agent_data->rdo)]) 
                      ? $day_mapping[strtolower($agent_data->rdo)] 
                      : 'NA';
                      
        $is_confirmed = false;
        if (!empty($roster_code)) {
            $confirmation_status = $wpdb->get_var($wpdb->prepare(
                "SELECT confirm FROM $roster_table 
                 WHERE employee_code = %s and month = '%s'
                 LIMIT 1",
                $roster_code,$selected_month
            ));
            $is_confirmed = ((int) $confirmation_status == 1);
        }
        
        // Get the current month and year
        $current_month = $agent_data->month ?? date('m');
        $current_year = $agent_data->year ?? date('Y');
    }

$approval_history = [];
if ($username !== 'lee') {
if (!empty($username)) {
    $approval_history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $roster_requests_table WHERE agent_name = %s ORDER BY auto_id DESC",
        $username
    ));
}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitize_text_field($_POST['reason']);

    if (isset($_POST['shift_change'])) {
        $new_shift = sanitize_text_field($_POST['newShift']);
        $formatted_current_shift = date('g:i A', strtotime($current_shift));
        $wpdb->insert($roster_requests_table, [
            'type' => 'Shift Change Request',
            'agent_name' => $employee_name,
            'sale_manager' => $sale_manager,
            'roster_code' => $roster_code,
            'status' => 'Pending',
            'current_shift' => $formatted_current_shift,
            'requested_shift' => $new_shift,
            'reason' => $reason
        ]);

    } elseif (isset($_POST['rdo_change'])) {
        $full_day = sanitize_text_field($_POST['day']);
        $requested_day = trim(strtok($full_day, '('));
        $wpdb->insert($roster_requests_table, [
            'type' => 'RDO Change Request',
            'agent_name' => $employee_name,
            'sale_manager' => $sale_manager,
            'roster_code' => $roster_code,
            'status' => 'Pending',
            'current_rdo' => $current_rdo,
            'requested_rdo' => $requested_day,
            'reason' => $reason
        ]);

    } elseif (isset($_POST['leave_request'])) {
        $full_day = sanitize_text_field($_POST['day']);
        $requested_day = trim(strtok($full_day, '('));
        $wpdb->insert($roster_requests_table, [
            'type' => 'Leave Request',
            'agent_name' => $employee_name,
            'sale_manager' => $sale_manager,
            'roster_code' => $roster_code,
            'status' => 'Pending',
            'current_rdo' => $requested_day,
            'reason' => $reason
        ]);

    wp_redirect($_SERVER['REQUEST_URI']);
    exit;
}
elseif (isset($_POST['confirm_roster'])) {
        // Update the confirm column in the database
        $wpdb->update(
            $roster_table,
            ['confirm' => 1],
            [
                'employee_code' => $roster_code,
                'month' => $selected_month
            ],
            ['%d'],
            ['%s','%s']
        );
        // Set confirmation status to true
        $is_confirmed = true;
        
        // Show confirmation message
        add_action('wp_footer', function() {
            echo '<script>
                alert("No more roster requests will be available after confirming the roster.");
                window.location.href = window.location.href;
            </script>';
        });
}
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Roster View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-yellow: #ffd207;
            --light-blue: #add8e6;
            --dark-blue: #0d6efd;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --warning-yellow: #ffc107;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary-yellow);
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .table-header {
            background-color: var(--primary-yellow);
            color: #000;
        }
        
        .roster-table th, .roster-table td {
            text-align: center;
            vertical-align: middle;
            padding: 12px 8px;
        }
        
        .roster-table th {
            font-weight: 600;
        }
        
        .request-form {
            display: none;
            margin-top: 15px;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .roster-table {
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .roster-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .btn-action {
            margin: 5px;
            transition: all 0.2s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .date-range-btn {
            margin-right: 5px;
            margin-bottom: 10px;
            border-radius: 20px;
            padding: 8px 15px;
            font-weight: 500;
        }
        
        .active-range {
            background-color: var(--dark-blue);
            color: white;
        }
        
        .date-range-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .date-range-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .shift-input {
            width: 150px;
            margin: 0 auto;
            text-align: center;
        }
        
        .shift-input::placeholder {
            color: #999;
            font-size: 0.9rem;
        }
        
        .approval-history {
            display: none;
            margin-top: 30px;
        }
        
        .status-pending {
            color: var(--warning-yellow);
            font-weight: bold;
        }
        
        .status-approved {
            color: var(--success-green);
            font-weight: bold;
        }
        
        .status-rejected {
            color: var(--danger-red);
            font-weight: bold;
        }
        
        .btn-shift-request {
            background-color: var(--light-blue);
            color: #000;
            font-weight: 500;
            border-radius: 20px;
            padding: 8px 20px;
            transition: all 0.2s ease;
        }
        
        .btn-shift-request:hover {
            background-color: #9bc9e0;
            transform: translateY(-2px);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.25rem rgba(173, 216, 230, 0.25);
        }
        
        .word-count-warning {
            color: var(--danger-red);
        }
        
        .welcome-header {
            color: #333;
            margin-bottom: 20px;
        }
        
        .welcome-message {
            color: #555;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tooltip-inner {
            max-width: 300px;
            padding: 8px 12px;
        }
        
        .rdo-highlight {
            background-color: rgba(40, 167, 69, 0.6);
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-radius: 3px;
            padding: 2px 5px;
            font-weight: bold;
            display: inline-block;
            margin: 0 auto;
            width: auto;
            min-width: 100px;
        }
        
        .convert-highlight {
            background-color: rgba(255, 193, 7, 0.6);
            border: 1px solid rgba(255, 193, 7, 0.5);
            border-radius: 3px;
            padding: 2px 5px;
            font-weight: bold;
            display: inline-block;
            margin: 0 auto;
            width: auto;
            min-width: 100px;
        }
        
        .leave-highlight {
            background-color: rgba(253, 126, 20, 0.6);
            border: 1px solid rgba(253, 126, 20, 0.5);
            border-radius: 3px;
            padding: 2px 5px;
            font-weight: bold;
            display: inline-block;
            margin: 0 auto;
            width: auto;
            min-width: 100px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        /* Approval history table styles */
        #approval-history-body tr {
            border-bottom: 1px solid #eee;
        }
        
        #approval-history-body td {
            vertical-align: top;
            padding: 10px;
        }
        
        #approval-history-body tr:last-child {
            border-bottom: none;
        }
        
        #approval-history-body strong {
            color: #555;
            font-weight: 600;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .date-range-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .roster-table th, .roster-table td {
                padding: 8px 5px;
                font-size: 0.9rem;
            }
            
            .btn-action {
                padding: 5px 8px;
                font-size: 0.8rem;
            }
            
            #approval-history-body td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            
            #approval-history-body tr {
                margin-bottom: 15px;
                display: block;
            }
        }
        
        /* Font size adjustments */
        body {
            font-size: 18px;
        }
        
        .roster-table td {
            font-size: 20px;
        }
        
        .roster-table th {
            font-size: 16px;
        }
        
        .roster-table td:nth-child(1),
        .roster-table td:nth-child(2),
        .roster-table td:nth-child(3) {
            font-size: 15px;
            font-weight: 500;
        }
        
        .btn-action {
            font-size: 16px;
        }
        
        .form-label {
            font-size: 15px;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            font-size: 14px;
        }
        
        .toast-body {
            font-size: 19px;
        }
        
        .empty-state h5 {
            font-size: 18px;
        }
        
        .empty-state p {
            font-size: 15px;
        }
        
        .rdo-highlight, 
        .convert-highlight, 
        .leave-highlight {
            font-size: 14px;
        }
        
        .welcome-header {
            font-size: 20px;
        }
        
        .welcome-message {
            font-size: 16px;
        }
        
        .username-search {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .username-search form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .username-search .form-control {
            flex: 1;
            min-width: 200px;
        }
        
        .username-search .btn {
            white-space: nowrap;
        }
        
        .agent-not-found {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
        }
        
        #approval-history-body td {
            vertical-align: top;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        #approval-history-body tr:last-child td {
            border-bottom: none;
        }
        
        #approval-history-body strong {
            color: #555;
            font-weight: 600;
        }
        
        /* Month selector styles */
        .month-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .month-selector label {
            font-weight: 500;
            margin-bottom: 0;
        }
        
        .month-selector select {
            width: 200px;
        }
        
        /* Table Column Styles */
        .roster-table {
            table-layout: fixed; /* Ensures consistent column widths */
            width: 100%;
        }
        
        .roster-table th, 
        .roster-table td {
            height: 60px; /* Fixed row height */
            vertical-align: middle;
            text-align: center;
            padding: 10px;
            word-wrap: break-word; /* Ensures text wraps within cells */
        }
        
        /* Column Widths */
        .roster-table .date-col {
            width: 25%;
        }
        
        .roster-table .day-col {
            width: 20%;
        }
        
        .roster-table .shift-col {
            width: 20%;
        }
        
        .roster-table .action-col {
            width: 35%;
        }
        
        /* Ensure action buttons stay consistent */
        .action-buttons .btn {
            white-space: nowrap;
            margin: 2px;
            padding: 8px 12px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .roster-table th, 
            .roster-table td {
                height: auto;
                padding: 8px 5px;
            }
            
            .roster-table .date-col,
            .roster-table .day-col,
            .roster-table .shift-col,
            .roster-table .action-col {
                width: auto;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }

        /* Calendar Grid Styles */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 15px;
        }

        .calendar-header {
            font-weight: bold;
            text-align: center;
            padding: 5px;
            background-color: #f0f0f0;
        }

        .calendar-day {
            border: 1px solid #ddd;
            padding: 5px;
            min-height: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background-color: #f5f5f5;
        }

        .calendar-day.selected {
            background-color: #007bff;
            color: white;
        }

        .calendar-day.disabled {
            color: #ccc;
            background-color: #f9f9f9;
            cursor: not-allowed;
        }

        .calendar-day.rdo-day {
            background-color: rgba(40, 167, 69, 0.2);
            font-weight: bold;
        }

        .calendar-day-number {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .calendar-day-name {
            font-size: 10px;
            color: #666;
        }

        .calendar-day.selected .calendar-day-name {
            color: #eee;
        }
        

        .calendar-day.convert-day {
            background-color: rgba(255, 193, 7, 0.2);
            font-weight: bold;
        }
    
        .calendar-day.leave-day {
            background-color: rgba(253, 126, 20, 0.2);
            font-weight: bold;
        }
    
        .calendar-day-label {
            font-size: 10px;
            margin-top: 2px;
            font-weight: bold;
        }
    
        .calendar-day-label.rdo {
            color: #28a745;
        }
    
        .calendar-day-label.convert {
            color: #ffc107;
        }
    
        .calendar-day-label.leave {
            color: #fd7e14;
        }
        
        .approval-table-head {
            #ffd207 !Important;
        }
    
            /* Month selector styles */
        .month-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .month-selector label {
            font-weight: 500;
            margin-bottom: 0;
        }
        
        .month-selector select {
            width: 200px;
        }
        
        /* Updated search container styles */
        .search-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        
        .search-form {
            flex: 1;
            min-width: 300px;
        }
        
        .month-selector {
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .search-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-form, .month-selector {
                width: 100%;
            }
        }
        .btn-confirm {
            background-color: #28a745;
            color: white;
        }
        .btn-confirm:hover {
            background-color: #218838;
            color: white;
        }
        .roster-confirmed-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <!-- Combined Search and Month Selection -->
    <br>
    <br>
    <br>
    <br>

    <!--<div class="card">-->
    <!--    <div class="card-body">-->
    <!--        <form method="GET" action="">-->
    <!--            <div class="search-container">-->
                <!-- Month Selector Dropdown -->
    <!--            <div class="month-selector">-->
    <!--                <label for="month-select" class="form-label"><strong>Select Month:</strong></label>-->
    <!--                <select class="form-select" id="month-select" name="month" onchange="this.form.submit()">-->
    <!--                    <?php foreach ($months as $month): ?>-->
    <!--                        <option value="<?php echo esc_attr($month); ?>" <?php echo $selected_month === $month ? 'selected' : ''; ?>>-->
    <!--                            <?php echo esc_html($month); ?>-->
    <!--                        </option>-->
    <!--                    <?php endforeach; ?>-->
    <!--                </select>-->
    <!--            </div>-->
    <!--            </div>-->
    <!--        </form>-->
    <!--    </div>-->
    <!--</div>-->
    <?php if (!empty($username) && $agent_data): ?>
    <div class="row">
        <!-- Roster Overview for the agent -->
        <div class="col-12">
            <br><br>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-calendar-alt me-2"></i>Agent Roster Overview</strong>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleApprovalHistory()">
                        <i class="fas fa-history me-1"></i>View History
                    </button>
                </div>
                 <div class="card-body">
                    <?php if ($is_confirmed): ?>
                        <div class="roster-confirmed-message">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Roster confirmed for <?php echo esc_html($selected_month); ?></strong> - No more changes can be requested for this month.
                        </div>
                    <?php endif; ?>
                    <h5 class="welcome-header">
                        <i class="fas fa-user-circle me-2"></i>
                        Welcome, 
                        <span id="agent-name">
                            <?php echo esc_html($username ?? $employee_name ?? ''); ?>
                        </span>!
                    </h5>

                    <p class="welcome-message">Your current roster is shown below. <?php echo $is_confirmed ? 'This roster has been confirmed and cannot be modified.' : 'You can request shift changes or RDO changes as needed.'; ?></p>
                    
                    <div class="date-range-container">
                        <div class="date-range-buttons" id="date-range-buttons">
                            <button class="btn btn-outline-primary btn-lg fs-5 date-range-btn" onclick="showDateRange(1, 10)">
                                <i class="fas fa-calendar-week me-1"></i>Date 1-10
                            </button>
                            <button class="btn btn-outline-primary btn-lg fs-5 date-range-btn" onclick="showDateRange(11, 20)">
                                <i class="fas fa-calendar-week me-1"></i>Date 11-20
                            </button>
                            <button class="btn btn-outline-primary btn-lg fs-5 date-range-btn" onclick="showDateRange(21, lastDayOfMonth)">
                                <i class="fas fa-calendar-week me-1"></i>Date 21-<span id="last-day-display"></span>
                            </button>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                        <?php if (!$is_confirmed): ?>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-shift-request btn-lg fs-5" onclick="toggleShiftRequestForm()">
                                    <i class="fas fa-exchange-alt me-1"></i>Request Shift Change
                                </button>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="confirm_roster" class="btn btn-confirm btn-lg fs-5" onclick="return confirm('Are you sure you want to confirm this roster? No more changes will be allowed for this month.')">
                                        <i class="fas fa-check-circle me-1"></i>Confirm Roster
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover roster-table">
                            <thead class="table-header">
                                <tr>
                                    <th class="date-col"><i class="fas fa-calendar-day me-1"></i>Date</th>
                                    <th class="day-col"><i class="fas fa-clock me-1"></i>Day</th>
                                    <th class="shift-col"><i class="fas fa-business-time me-1"></i>Current Shift</th>
                                    <th class="action-col"><i class="fas fa-edit me-1"></i>Action</th>
                                </tr>
                            </thead>
                            <tbody id="roster-body">
                                <!-- Roster data will be populated here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="empty-roster" class="empty-state" style="display: none;">
                        <i class="fas fa-calendar-times"></i>
                        <h5>No shifts found for this date range</h5>
                        <p>Try selecting a different date range</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RDO Change Request Form -->
    <div id="rdo-request-form" class="request-form card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-calendar-minus me-2"></i>Request RDO Change</strong>
            <button type="button" class="btn-close" onclick="closeRDORequestForm()" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <form id="rdo-change-form" method="POST">
                <input type="hidden" name="rdo_change" value="1">
                <div class="mb-3">
                    <label for="current-rdo-day" class="form-label">Current Day</label>
                    <input type="text" class="form-control" id="current-rdo-day" name="current_day" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select New RDO Day</label>
                    <div id="month-calendar" class="mb-3"></div>
                    <input type="hidden" id="requested-rdo" name="day" required>
                </div>
                <div class="mb-3">
                    <label for="rdo-reason" class="form-label">Reason for RDO Change</label>
                    <textarea class="form-control" id="rdo-reason" name="reason" rows="3" 
                              placeholder="Please provide a reason for the RDO change"
                              required></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeRDORequestForm()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-1"></i>Submit RDO Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Shift Change Request Form -->
    <div id="shift-request-form" class="request-form card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-exchange-alt me-2"></i>Request Shift Change</strong>
            <button type="button" class="btn-close" onclick="closeShiftRequestForm()" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <form id="shift-change-form" method="POST">
                <input type="hidden" name="shift_change" value="1">
                <div class="mb-3">
                    <p><strong>Note: Shift Change will be applied to the whole month.</strong></p>
                    <label class="form-label">Current Shift</label>
                    <input type="text" class="form-control" id="current-shift" name="currentShift" value="<?php echo esc_attr($current_shift); ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="new-shift" class="form-label">Select New Shift Time</label>
                    <select class="form-select" id="new-shift" name="newShift" required>
                        <option value="">-- Select new shift --</option>
                        <option value="7:00 AM">7:00 AM</option>
                        <option value="8:00 AM">8:00 AM</option>
                        <option value="9:00 AM">9:00 AM</option>
                        <option value="10:00 AM">10:00 AM</option>
                        <option value="11:00 AM">11:00 AM</option>
                        <option value="12:00 PM">12:00 PM</option>
                        <option value="1:00 PM">1:00 PM</option>
                        <option value="2:00 PM">2:00 PM</option>
                        <option value="4:00 PM">4:00 PM</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="shift-reason" class="form-label">Reason for Shift Change</label>
                    <textarea class="form-control" id="shift-reason" name="reason" rows="3" 
                              placeholder="Please provide a reason for the shift change (e.g., personal preference, transportation issues)"
                              maxlength="500" required></textarea>
                    <small class="text-muted"><span id="word-count">0</span>/100 words <span id="word-warning" class="word-count-warning" style="display: none;">(Over limit!)</span></small>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeShiftRequestForm()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Submit Shift Request
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Leave Request Form -->
    <div id="leave-request-form" class="request-form card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-umbrella-beach me-2"></i>Request Leave</strong>
            <button type="button" class="btn-close" onclick="closeLeaveRequestForm()" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <form id="leave-change-form" method="POST">
                <input type="hidden" name="leave_request" value="1">
                <div class="mb-3">
                    <label for="leave-day" class="form-label">Day</label>
                    <input type="text" class="form-control" id="leave-day" name="day" readonly>
                </div>
                <div class="mb-3">
                    <label for="leave-reason" class="form-label">Reason for Leave</label>
                    <textarea class="form-control" id="leave-reason" name="reason" rows="3" 
                              placeholder="Please provide a reason for your leave request"
                              required></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeLeaveRequestForm()">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane me-1"></i>Submit Leave Request
                    </button>
                </div>
            </form>
        </div>
    </div>
    

<!-- Approval History Table -->
<div id="approval-history" class="approval-history card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-history me-2"></i>Approval History</strong>
        <button type="button" class="btn-close" onclick="toggleApprovalHistory()" aria-label="Close"></button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="approval-table-head">
                    <tr>
                        <th>Request Date</th>
                        <th>Request Type</th>
                        <th>Status</th>
                        <th>Current</th>
                        <th>Requested</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="approval-history-body">
                    <!-- Approval history will be populated here -->
                </tbody>
            </table>
        </div>
        
        <div id="empty-history" class="empty-state">
            <i class="fas fa-inbox"></i>
            <h5>No approval history yet</h5>
            <p>Your shift change requests will appear here once submitted</p>
        </div>
    </div>
</div>
    <?php endif; ?>
</div>
<!-- Success Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="successToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto"><i class="fas fa-check-circle me-2"></i>Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Your request has been submitted successfully!
        </div>
    </div>
</div>


<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Global variables
    let lastDayOfMonth;
    let currentRange = '1-10';
    let approvalHistory = <?php echo json_encode($approval_history); ?>;
    let rdoDay = '<?php echo $rdo_day; ?>'; // From PHP
    let currentRdo = '<?php echo isset($current_rdo) ? esc_js($current_rdo) : ''; ?>';
    let agentShifts = <?php 
        if ($agent_data) {
            $shifts = [];
            for ($i = 1; $i <= 31; $i++) {
                $day_key = 'day_' . $i;
                $shifts[$i] = $agent_data->$day_key ?? '';
            }
            echo json_encode($shifts);
        } else {
            echo '{}';
        }
    ?>;
    const selectedMonth = '<?php echo $selected_month; ?>'; // The full month name
    
    // Format shift time for display
    function formatShiftTime(shiftValue) {
        if (!shiftValue) return '';
        
        // Handle special cases
        if (shiftValue === 'RDO' || shiftValue === 'Convert' || shiftValue === 'Leave' || shiftValue === 'convert' || shiftValue === 'leave') {
            return shiftValue;
        }
        
        // Handle time values (like '0730', '0900')
        if (/^\d{4}$/.test(shiftValue)) {
            const hours = shiftValue.substring(0, 2);
            const minutes = shiftValue.substring(2);
            return `${hours}:${minutes}`;
        }
        
        // Return as-is if not a recognizable format
        return shiftValue;
    }
    
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize toast
        const toastEl = document.getElementById('successToast');
        const toast = new bootstrap.Toast(toastEl);
        
        // Calculate month details based on selection
        const now = new Date();
        let targetMonth;
        
        
        
        // Find the selected month in the months array to get its index
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
        const monthIndex = months.indexOf(selectedMonth);
        
        // Create a date object for the selected month (using current year)
        targetMonth = new Date(now.getFullYear(), monthIndex, 1);
        
        lastDayOfMonth = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
        
        // Update the last day display in the button
        document.getElementById('last-day-display').textContent = lastDayOfMonth;
        
        // Generate roster data for the selected month
        generateRosterData(targetMonth);
        
        // Show the first date range by default
        showDateRange(1, 10);
        
        // Set up word count for shift reason
        document.getElementById('shift-reason').addEventListener('input', updateWordCount);
        
        // Update approval history table on load if there are records
        if (approvalHistory.length > 0) {
            document.getElementById('empty-history').style.display = 'none';
            updateApprovalHistoryTable();
        }
    });
    
  let isConfirmed = <?php echo $is_confirmed ? 'true' : 'false'; ?>;
    
    // Modify the generateRosterData function to check confirmation status
    function generateRosterData(targetMonth) {
        // Clear existing data
        const rosterBody = document.getElementById('roster-body');
        rosterBody.innerHTML = '';
        
        // Get month name (e.g. "June", "July")
        const monthName = targetMonth.toLocaleDateString('en-US', { month: 'long' });
        
        // Generate data for each day of the month
        for (let day = 1; day <= lastDayOfMonth; day++) {
            const date = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), day);
            const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
            
            // Get the shift for this day from the database
            const shiftValue = agentShifts[day] || '';
            const formattedShift = formatShiftTime(shiftValue);
            
            // Determine if this is an RDO day (only if explicitly marked as 'RDO')
            const isRDO = shiftValue === 'RDO';
            
            // Create shift cell content based on the shift value
            let shiftCellContent;
            if (shiftValue === 'Convert' || shiftValue === 'convert') {
                shiftCellContent = '<span class="convert-highlight">Convert</span>';
            } else if (shiftValue === 'Leave' || shiftValue === 'leave') {
                shiftCellContent = '<span class="leave-highlight">Leave</span>';
            } else if (isRDO) {
                shiftCellContent = '<span class="rdo-highlight">RDO</span>';
            } else if (formattedShift) {
                shiftCellContent = formattedShift;
            } else {
                shiftCellContent = '<?php echo $current_shift; ?>';
            }
            
            // Create a row for each day
            const row = document.createElement('tr');
            row.setAttribute('data-date', day);
            row.style.display = 'none'; // Hide all rows initially
            
            // Create action buttons based on the day type and confirmation status
            let actionButtons = '';
            
            if (!isConfirmed) {
                if (isRDO) {
                    // Only show RDO change button for days explicitly marked as RDO
                    actionButtons = `
                        <button class="btn btn-danger btn-action" 
                                onclick="toggleRDORequestForm('${dayName} (${day}/${targetMonth.getMonth() + 1})')">
                            <i class="fas fa-calendar-minus me-1"></i>Request RDO Change
                        </button>
                    `;
                } else if (shiftValue === 'Convert' || shiftValue === 'convert') {
                    // For "convert" days, show Leave request button
                    actionButtons = `
                        <button class="btn btn-warning btn-action" 
                                onclick="toggleLeaveRequestForm('${dayName} (${day}/${targetMonth.getMonth() + 1})')">
                            <i class="fas fa-umbrella-beach me-1"></i>Request Leave
                        </button>
                    `;
                }
            }
            
            row.innerHTML = `
                <td class="date-col">${day}/${targetMonth.getMonth() + 1}/${targetMonth.getFullYear()}</td>
                <td class="day-col">${dayName}</td>
                <td class="shift-col" style="text-align: center;">${shiftCellContent}</td>
                <td class="action-col">
                    <div class="action-buttons">
                        ${actionButtons}
                    </div>
                </td>
            `;
                        
            rosterBody.appendChild(row);
        }
    }

    // Generate calendar for the month
    // Function to generate month calendar and handle RDO selection
function generateMonthCalendar(targetMonth, selectedDay) {
    const calendarContainer = document.getElementById('month-calendar');
    calendarContainer.innerHTML = '';
    
    // Create header with day names
    const header = document.createElement('div');
    header.className = 'calendar-grid';
    
    const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    daysOfWeek.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-header';
        dayHeader.textContent = day;
        header.appendChild(dayHeader);
    });
    
    calendarContainer.appendChild(header);
    
    // Create calendar grid
    const grid = document.createElement('div');
    grid.className = 'calendar-grid';
    
    // Ensure the current month is correctly passed
    console.log("Target month: ", targetMonth);
    
    // Get first day of the month and calculate last day of the month
    const firstDay = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), 1).getDay();
    const lastDayOfMonth = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate(); // Correct last day
    
    // Add empty cells for days before the first of the month
    for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'calendar-day disabled';
        grid.appendChild(emptyCell);
    }
    
    // Add cells for each day of the month
    for (let day = 1; day <= lastDayOfMonth; day++) {
        const date = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), day);
        const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
        
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        
        // Get the shift type for this day
        const shiftType = agentShifts[day] || '';
        let dayLabel = '';
        
        // Add appropriate class and label based on shift type
        if (shiftType === 'RDO') {
            dayCell.classList.add('rdo-day');
            dayLabel = '<div class="calendar-day-label rdo">RDO</div>';
        } else if (shiftType === 'Convert' || shiftType === 'convert') {
            dayCell.classList.add('convert-day');
            dayLabel = '<div class="calendar-day-label convert">Convert</div>';
        } else if (shiftType === 'Leave' || shiftType === 'leave') {
            dayCell.classList.add('leave-day');
            dayLabel = '<div class="calendar-day-label leave">Leave</div>';
        }
        
        // Check if this is the selected day
        if (selectedDay && day === selectedDay) {
            dayCell.classList.add('selected');
        }
        
        dayCell.innerHTML = `
            <div class="calendar-day-number">${day}</div>
            <div class="calendar-day-name">${dayName}</div>
            ${dayLabel}
        `;
        
        // Add click handler - only allow selection of non-RDO days
        dayCell.addEventListener('click', function() {
            if (shiftType !== 'RDO') {
                // Remove previous selection
                document.querySelectorAll('.calendar-day.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                
                // Add selection to clicked day
                this.classList.add('selected');
                
                // Update the hidden input with the selected date
                // Fixing the selected date format: day/month/year
                const selectedDate = `${dayName} (${day}/${targetMonth.getMonth() + 1})`; // Use +1 for correct month selection
                document.getElementById('requested-rdo').value = selectedDate; // Correct value being passed
            }
        });
        
        grid.appendChild(dayCell);
    }
    
    calendarContainer.appendChild(grid);
}

    // Update word count for shift reason
    function updateWordCount() {
        const textarea = document.getElementById('shift-reason');
        const wordCount = document.getElementById('word-count');
        const wordWarning = document.getElementById('word-warning');
        const words = textarea.value.trim() ? textarea.value.trim().split(/\s+/) : [];
        wordCount.textContent = words.length;
        
        if (words.length > 100) {
            wordCount.classList.add('text-danger');
            wordWarning.style.display = 'inline';
        } else {
            wordCount.classList.remove('text-danger');
            wordWarning.style.display = 'none';
        }
    }
    
    // Show only dates in the specified range
    function showDateRange(start, end) {
        // Update active button styling
        const buttons = document.querySelectorAll('.date-range-btn');
        buttons.forEach(btn => {
            btn.classList.remove('active-range');
        });
        
        // Determine which button was clicked
        let buttonText;
        if (start === 1 && end === 10) {
            buttonText = 'Date 1-10';
            currentRange = '1-10';
        } else if (start === 11 && end === 20) {
            buttonText = 'Date 11-20';
            currentRange = '11-20';
        } else {
            buttonText = `Date 21-${lastDayOfMonth}`;
            currentRange = `21-${lastDayOfMonth}`;
        }
        
        // Find and highlight the active button
        buttons.forEach(btn => {
            if (btn.textContent.includes(buttonText)) {
                btn.classList.add('active-range');
            }
        });
        
        // Show/hide rows based on date range
        const rows = document.querySelectorAll('#roster-body tr');
        let visibleRows = 0;
        
        rows.forEach(row => {
            const date = parseInt(row.getAttribute('data-date'));
            if (date >= start && date <= end) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show empty state if no rows visible
        const emptyState = document.getElementById('empty-roster');
        if (visibleRows === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }
    
    // Toggle shift request form
    function toggleShiftRequestForm() {
        document.getElementById('shift-request-form').style.display = 'block';
        document.getElementById('new-shift').focus();
    }
    
    function closeShiftRequestForm() {
        document.getElementById('shift-request-form').style.display = 'none';
        document.getElementById('shift-change-form').reset();
        document.getElementById('word-count').textContent = '0';
        document.getElementById('word-warning').style.display = 'none';
    }

    // Toggle RDO Request Form with calendar for the entire month
    function toggleRDORequestForm(dayName) {
      // Show form & reset fields
      document.getElementById('rdo-request-form').style.display = 'block';
      document.getElementById('current-rdo-day').value = dayName;
      document.getElementById('rdo-reason').value = '';
      document.getElementById('requested-rdo').value = '';
    
      // Use the selected month (PHP sets this to next month by default)
      const now = new Date();
      const months = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
      ];
    
      const monthIndex = months.indexOf(selectedMonth); // e.g. "September"
      // Fallback to *next month from now* if selectedMonth isn't a full month name
      let targetMonthIndex, targetYear = now.getFullYear();
    
      if (monthIndex === -1) {
        // Fallback: next month relative to "now"
        targetMonthIndex = (now.getMonth() + 1) % 12;
        if (now.getMonth() === 11) targetYear += 1; // Dec -> Jan
      } else {
        // Show the selected month; adjust year if it’s earlier in the year (Dec->Jan case)
        targetMonthIndex = monthIndex;
        if (targetMonthIndex < now.getMonth()) targetYear += 1;
      }
    
      // Build the calendar for that month
      generateMonthCalendar(new Date(targetYear, targetMonthIndex, 1), null);
    
      document.getElementById('rdo-reason').focus();
    
      // (Optional) if you need the numeric day from "Weekday (DD/MM)" in dayName:
      // const dayMatch = dayName.match(/\((\d+)\//);
      // const currentDay = dayMatch ? parseInt(dayMatch[1], 10) : 1;
    }

    
    function closeRDORequestForm() {
        document.getElementById('rdo-request-form').style.display = 'none';
        document.getElementById('rdo-change-form').reset();
    }
    
    function toggleLeaveRequestForm(day) {
        document.getElementById('leave-request-form').style.display = 'block';
        document.getElementById('leave-day').value = day;
        document.getElementById('leave-reason').focus();
    }
    
    function closeLeaveRequestForm() {
        document.getElementById('leave-request-form').style.display = 'none';
        document.getElementById('leave-change-form').reset();
    }
    
    // Toggle approval history
    function toggleApprovalHistory() {
        const historySection = document.getElementById('approval-history');
        if (historySection.style.display === 'block') {
            historySection.style.display = 'none';
        } else {
            historySection.style.display = 'block';
            updateApprovalHistoryTable();
        }
    }
    
    // Update the approval history table
function updateApprovalHistoryTable() {
    const tableBody = document.getElementById('approval-history-body');
    tableBody.innerHTML = '';
    
    if (approvalHistory.length === 0) {
        document.getElementById('empty-history').style.display = 'block';
        return;
    } else {
        document.getElementById('empty-history').style.display = 'none';
    }
    
    approvalHistory.forEach(request => {
        const row = document.createElement('tr');
        
        // Determine status class
        let statusClass = 'status-pending';
        if (request.status.toLowerCase().includes('approved')) {
            statusClass = 'status-approved';
        }
        if (request.status === 'Rejected') statusClass = 'status-rejected';
        
        // Determine which fields to show based on request type
        let currentValue = '';
        let requestedValue = '';
        
        if (request.type === 'Shift Change Request') {
            currentValue = request.current_shift || '';
            requestedValue = request.requested_shift || '';
        } else if (request.type === 'RDO Change Request') {
            currentValue = request.current_rdo || '';
            requestedValue = request.requested_rdo || '';
        } else if (request.type === 'Leave Request') {
            currentValue = ''; // Leave requests don't have a "current" value
            requestedValue = request.current_rdo || ''; // Using current_rdo to store the leave day
        }
        
        // Create table row with request date as first column
        row.innerHTML = `
            <td>${request.day || 'N/A'}</td>
            <td>${request.type || 'N/A'}</td>
            <td><span class="${statusClass}">${request.status || 'Pending'}</span></td>
            <td>${currentValue}</td>
            <td>${requestedValue}</td>
            <td>${request.reason || 'N/A'}</td>
        `;
        
        tableBody.appendChild(row);
    });
}
    
 // Form submission handlers - modified to work with PHP form submission
    document.getElementById('shift-change-form').addEventListener('submit', function(e) {
        // Let the form submit normally to PHP after validation
        updateWordCount();
        const words = document.getElementById('shift-reason').value.trim() ? 
                     document.getElementById('shift-reason').value.trim().split(/\s+/) : [];
        if (words.length > 100) {
            e.preventDefault();
            alert('Reason must be 100 words or less');
            return;
        }
        
        // Additional validation if needed
        if (!document.getElementById('new-shift').value) {
            e.preventDefault();
            alert('Please select a new shift time');
            return;
        }
    });

    document.getElementById('rdo-change-form').addEventListener('submit', function(e) {
        // Validate that a day was selected
        if (!document.getElementById('requested-rdo').value) {
            e.preventDefault();
            alert('Please select a day from the calendar');
            return;
        }
    });

    document.getElementById('leave-change-form').addEventListener('submit', function(e) {
        // Let the form submit normally to PHP
        // HTML5 required attribute handles basic validation
    });
    
    document.getElementById('leave-change-form').addEventListener('submit', function(e) {
        // Let the form submit normally to PHP
        // HTML5 required attribute handles basic validation
    });
</script>




</body>
</html>

<?php
get_footer();
?>