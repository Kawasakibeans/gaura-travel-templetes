<?php
/**
 * Template Name: Manager Roster Approval
 * Template Post Type: post, page
 */


get_header();

// --- Next-month window (handles Dec -> Jan) ---
$start_next = (new DateTime('first day of next month 00:00:00'))->format('Y-m-d H:i:s');
$end_next   = (new DateTime('last day of next month 23:59:59'))->format('Y-m-d H:i:s');

// For dd/mm/YYYY HH:ii text dates stored in leave tables
$start_next_str = (new DateTime('first day of next month 00:00'))->format('Y-m-d H:i:s');
$end_next_str   = (new DateTime('last day of next month 23:59'))->format('Y-m-d H:i:s');


// Handle leave request approval/rejection (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'leave_request_action')) {
        $leave_action = sanitize_text_field($_POST['action']);
        $leave_id = intval($_POST['request_id']);
        $leave_requests_table = 'wpk4_backend_employee_roster_leaves_approval';
        if (in_array($leave_action, ['approve', 'reject'])) {
            $new_status = $leave_action === 'approve' ? 'Approved' : 'Rejected';
            $wpdb->update(
                $leave_requests_table,
                ['current_status' => $new_status],
                ['id' => $leave_id],
                ['%s'],
                ['%d']
            );
            // Redirect to avoid resubmission and show toast
            $redirect_url = add_query_arg('action', $leave_action, remove_query_arg(['action', 'request_id']));
            wp_redirect($redirect_url);
            exit;
        }
    }
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Weekday date helpers (ISO weeks: Monday=0 ... Sunday=6) ---
function _weekday_index($w) {
    $w = strtolower(trim($w));
    // accept full/short names: monday/mon, tuesday/tue, wed, etc.
    $map = [
        'mon' => 0, 'monday' => 0,
        'tue' => 1, 'tuesday' => 1,
        'wed' => 2, 'wednesday' => 2,
        'thu' => 3, 'thursday' => 3,
        'fri' => 4, 'friday' => 4,
        'sat' => 5, 'saturday' => 5,
        'sun' => 6, 'sunday' => 6,
    ];
    // also handle 3-letter substrings (e.g., "sat", "wed")
    if (isset($map[$w])) return $map[$w];
    $w3 = substr($w, 0, 3);
    return $map[$w3] ?? null;
}

/**
 * Given a base date string (created_date) and a weekday label (e.g. "Saturday" or "Wed"),
 * return the calendar date (d/m/Y) of that weekday in the SAME ISO week as the base date.
 * ISO week starts Monday.
 */
function date_for_weekday_in_same_week($base_date_str, $weekday_label) {
    if (empty($base_date_str) || empty($weekday_label)) return '';
    $base_ts = strtotime($base_date_str);
    if ($base_ts === false) return '';

    // PHP: N (1..7) -> Mon..Sun
    $base_day_idx = (int)date('N', $base_ts) - 1; // 0..6 (Mon=0)
    $target_idx = _weekday_index($weekday_label);
    if ($target_idx === null) return '';

    // Find Monday of the same week as base date
    $monday_ts = strtotime(date('Y-m-d', $base_ts) . ' monday this week');
    // BUT if base date itself is Sunday (N=7), "monday this week" returns next day Monday.
    // Fix: if base is Sunday (base_day_idx=6), go back 6 days.
    if ($base_day_idx === 6) {
        $monday_ts = strtotime('-6 days', $base_ts);
    }

    $target_ts = strtotime("+{$target_idx} days", $monday_ts);
    return $target_ts ? date('d/m/Y', $target_ts) : '';
}


global $wpdb;

// Define table names with WordPress table prefix
$roster_requests_table = $wpdb->prefix . 'manage_roster_requests';
$agent_codes_table = $wpdb->prefix . 'backend_agent_codes';
$availability_table = $wpdb->prefix . 'backend_availability_sheet';
$roster_table = $wpdb->prefix . 'backend_employee_roster';

// Handle approval/rejection actions
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action = sanitize_text_field($_GET['action']);
    
    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action === 'approve' ? 'Provision Approve' : 'Rejected';
        
        // Get the request details first
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $roster_requests_table WHERE auto_id = %d",
            $request_id
        ));
        
        // Update the request status
        $wpdb->update(
            $roster_requests_table,
            ['status' => $new_status],
            ['auto_id' => $request_id],
            ['%s'],
            ['%d']
        );
        
        // If approved, update the relevant system records
        if ($action === 'approve') {
            // Get agent details from agent codes table
            $agent_details = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $agent_codes_table WHERE roster_code = %s",
                $request->roster_code
            ));
            
            if ($agent_details) {
                // Handle different request types
                switch ($request->type) {
                    case 'RDO Change Request':
                        // Get first 3 letters of requested RDO (e.g., "tue" for "tuesday")
                        $new_rdo = strtolower(substr($request->requested_rdo, 0, 3));
                        
                        // Update availability sheet
                        $wpdb->update(
                            $availability_table,
                            ['rdo' => $new_rdo],
                            ['roster_code' => $request->roster_code],
                            ['%s'],
                            ['%s']
                        );
                        break;
                        
                    case 'Shift Change Request':
                        // Format the new shift time (remove AM/PM and convert to 24-hour format)
                        $new_shift = preg_replace('/[^0-9:]/', '', $request->requested_shift);
                        $new_shift = date('Hi', strtotime($new_shift));
                        
                        // Update roster table
                        $wpdb->update(
                            $roster_table,
                            ['shift_time' => $new_shift],
                            ['roster_code' => $request->roster_code],
                            ['%s'],
                            ['%s']
                        );
                        break;
                        
                    case 'Leave Request':
                        // Handle leave approval if needed
                        break;
                }
            }
        }
        
        // Redirect to avoid form resubmission
        wp_redirect(remove_query_arg(['action', 'request_id']));
        exit;
    }
}

// Get selected sales manager from URL
$selected_manager = isset($_GET['sale_manager']) ? sanitize_text_field($_GET['sale_manager']) : '';

// Build WHERE clauses with sales manager filter
$pending_where = "status = 'Pending'";
$processed_where = "status != 'Pending'";

if (!empty($selected_manager)) {
    $pending_where .= $wpdb->prepare(" AND sale_manager = %s", $selected_manager);
    $processed_where .= $wpdb->prepare(" AND sale_manager = %s", $selected_manager);
}

// Get all pending requests with agent names joined from agent_codes_table
// Get all pending requests (NEXT MONTH only) with agent names joined
$pending_requests = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT r.*, a.agent_name
         FROM {$roster_requests_table} r
         LEFT JOIN {$agent_codes_table} a ON r.roster_code = a.roster_code
         WHERE r.status = 'Pending'
           AND r.created_date BETWEEN %s AND %s
           " . (!empty($selected_manager) ? " AND r.sale_manager = %s " : "") . "
         ORDER BY r.auto_id DESC",
        ...(!empty($selected_manager)
            ? [$start_next, $end_next, $selected_manager]
            : [$start_next, $end_next]
        )
    )
);

// Get all processed requests (NEXT MONTH only) with agent names joined
$processed_requests = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT r.*, a.agent_name
         FROM {$roster_requests_table} r
         LEFT JOIN {$agent_codes_table} a ON r.roster_code = a.roster_code
         WHERE r.status != 'Pending'
           AND r.created_date BETWEEN %s AND %s
           " . (!empty($selected_manager) ? " AND r.sale_manager = %s " : "") . "
         ORDER BY r.auto_id DESC",
        ...(!empty($selected_manager)
            ? [$start_next, $end_next, $selected_manager]
            : [$start_next, $end_next]
        )
    )
);


// Get all unique sales managers for dropdown
$sales_managers = $wpdb->get_col(
    "SELECT DISTINCT sale_manager FROM $roster_requests_table ORDER BY sale_manager"
);

$leave_requests_table = 'wpk4_backend_employee_roster_leaves_approval';

if (!empty($selected_manager)) {
    $leave_requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $leave_requests_table WHERE sm = %s and MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1 ORDER BY doc_no DESC",
            $selected_manager
        )
    );
} else {
    $leave_requests = $wpdb->get_results("SELECT * FROM $leave_requests_table WHERE MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1 ORDER BY doc_no DESC");
}


function daterange($start_date, $end_date) {
    $dates = [];
    $start = DateTime::createFromFormat('d/m/Y H:i', $start_date);
    $end = DateTime::createFromFormat('d/m/Y H:i', $end_date);

    if (!$start || !$end) return $dates;

    while ($start <= $end) {
        $dates[] = $start->format('d/m/Y');
        $start->modify('+1 day');
    }
    return $dates;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Roster Approval</title>
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
            font-size: 18px;
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
        
        .request-table th, .request-table td {
            vertical-align: middle;
            padding: 12px 8px;
        }
        
        .request-table th {
            font-weight: 600;
            font-size: 16px;
        }
        
        .request-table tbody {
            overflow: visible !important;
        }
        
        .request-table td {
            font-size: 15px;
        }
        
        .btn-action {
            margin: 5px;
            transition: all 0.2s ease;
            font-size: 16px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
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
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.25rem rgba(173, 216, 230, 0.25);
        }
        
        .welcome-header {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
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
        
        .nav-tabs .nav-link {
            font-weight: 500;
            border: none;
            color: #495057;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--dark-blue);
            background-color: transparent;
            border-bottom: 3px solid var(--dark-blue);
        }
        
        .badge-count {
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .request-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .request-details p {
            margin-bottom: 8px;
        }
        
        .request-details strong {
            display: inline-block;
            width: 150px;
        }
        
        @media (max-width: 768px) {
            .request-table th, .request-table td {
                padding: 8px 5px;
                font-size: 0.9rem;
            }
            
            .btn-action {
                padding: 5px 8px;
                font-size: 0.8rem;
            }
            
            .request-details strong {
                display: block;
                width: auto;
            }
        }
                
        .details-cell {
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reason-box {
            background-color: #cfe2ff;
            color: #084298;
            padding: 6px 12px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 1.25rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: fit-content;
            margin: 0 auto;
        }
        
        .reason-tooltip {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #eaf4ff;
            border: 1px solid #6c757d;
            padding: 15px;
            z-index: 100;
            min-width: 100px;
            max-width: 350px;
            font-size: 1.25rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: #000;
            text-align: center;
        }
        
        /* Show tooltip when hovering the reason box */
        .reason-box:hover + .reason-tooltip,
        .reason-tooltip:hover {
            display: block;
        }      

        .action-buttons {
            display: inline-flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Filter dropdown styles */
        .input-group-text {
            font-weight: 500;
            background-color: var(--primary-yellow);
        }
        
        .form-select {
            cursor: pointer;
        }
        
        .clear-filter-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        

        .leave-requests .employee-name {
            font-weight: 600;
            color: #5b4d00;
        }
        .leave-requests td {
            vertical-align: middle !important;
        }
        /* Custom zebra striping for leave requests table (per leave request group) */
        .leave-requests tbody tr.leave-group-odd {
            background-color: #eaf4ff !important; /* light blue */
        }
        .leave-requests tbody tr.leave-group-even {
            background-color: #f8f9fa !important; /* light grey */
        }
        .leave-requests tbody tr.leave-group-odd:hover,
        .leave-requests tbody tr.leave-group-even:hover {
            background-color: #d0e7ff !important;
        }
        /* Prevent folding/wrapping in Date and Status columns */
        .leave-requests td.status-col, .leave-requests th.status-col {
            white-space: nowrap;
            min-width: 110px;
        }
        .leave-requests td.date-col, .leave-requests th.date-col {
            white-space: nowrap;
            min-width: 110px;
        }
        .leave-requests td {
            word-break: keep-all;
        }
        
        .remarks-preview {
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 4px !important;
            transition: all 0.2s ease;
        }
        
        .remarks-preview:hover {
            background-color: #e0e0e0;
            border-radius: 4px !important;

        }
        
        .d-flex.justify-content-center.gap-2 {
            gap: 10px;
        }
        
        /* Make buttons same width */
        .d-flex.justify-content-center.gap-2 .btn {
            min-width: 80px;
        }
        .weekday-date {
          display:block;
          font-size:.85rem;
          color:#6c757d;
          margin-top:4px;
        }


    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <br /><br /><br><br>
    <div class="card">
        <div class="card-header">
            <strong><i class="fas fa-clipboard-check me-2"></i>Roster Request Approvals</strong>
        </div>
        <div class="card-body">
            <h5 class="welcome-header"><i class="fas fa-user-tie me-2"></i>Manager Dashboard</h5>
            
            <!-- Sales Manager Filter Dropdown -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <form method="get" action="">
                        <div class="input-group">
                            <label class="input-group-text" for="sales_manager_filter">
                                <i class="fas fa-filter me-2"></i>Filter by Manager:
                            </label>
                            <select class="form-select" id="sales_manager_filter" name="sale_manager" onchange="this.form.submit()">
                                <option value="">All Managers</option>
                                <?php foreach ($sales_managers as $manager): ?>
                                    <option value="<?php echo esc_attr($manager); ?>" <?php selected($manager, $selected_manager); ?>>
                                        <?php echo esc_html($manager); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selected_manager)): ?>
                                <a href="<?php echo remove_query_arg('sale_manager'); ?>" class="btn btn-outline-secondary clear-filter-btn" type="button">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <ul class="nav nav-tabs mb-4" id="approvalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        Pending Requests
                        <?php if (!empty($pending_requests)): ?>
                            <span class="badge bg-danger badge-count"><?php echo count($pending_requests); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="processed-tab" data-bs-toggle="tab" data-bs-target="#processed" type="button" role="tab">
                        Approval History
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave-requests" type="button" role="tab">
                        Leave Requests
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="leave-processed-tab" data-bs-toggle="tab" data-bs-target="#leave-requests-processed" type="button" role="tab">
                        Leave Requests Approval History
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="approvalTabsContent">
            <!-- Pending Requests Tab -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <?php if (!empty($pending_requests)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover request-table text-center">
                            <thead class="table-header">
                                <tr>
                                    <th>Requested Date</th>
                                    <th>Agent</th>
                                    <th>Type</th>
                                    <th>Current</th>
                                    <th>Requested</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo !empty($request->created_date) 
                                                ? esc_html(date('d/m/Y', strtotime($request->created_date))) 
                                                : '';
                                            ?>
                                        </td>

                                        <td><?php echo esc_html($request->agent_name); ?></td>
                                        <td><strong><?php echo esc_html($request->type); ?></strong></td>
                                        <td>
                                          <?php 
                                          if ($request->type === 'RDO Change Request') {
                                              $d = date_for_weekday_in_same_week($request->created_date ?? '', $request->current_rdo ?? '');
                                              echo esc_html($request->current_rdo ?: '-');
                                              if ($d) echo '<span class="weekday-date">'.esc_html($d).'</span>';
                                          } elseif ($request->type === 'Shift Change Request') {
                                              echo esc_html($request->current_shift ?: '-');
                                          } else {
                                              echo '-';
                                          }
                                          ?>
                                        </td>
                                        <td>
                                          <?php 
                                          if ($request->type === 'RDO Change Request') {
                                              $d = date_for_weekday_in_same_week($request->created_date ?? '', $request->requested_rdo ?? '');
                                              echo esc_html($request->requested_rdo ?: '-');
                                              if ($d) echo '<span class="weekday-date">'.esc_html($d).'</span>';
                                          } elseif ($request->type === 'Shift Change Request') {
                                              echo esc_html($request->requested_shift ?: '-');
                                          } elseif ($request->type === 'Leave Request') {
                                              echo esc_html($request->leave_request ?: '');
                                          }
                                          ?>
                                        </td>

                                        <td>
                                            <div class="details-cell">
                                                <div class="d-flex justify-content-center position-relative">
                                                    <p class="reason-box m-0">Reason</p>
                                                    <div class="reason-tooltip">
                                                        <?php echo esc_html($request->reason); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?action=approve&request_id=<?php echo $request->auto_id; ?>&sale_manager=<?php echo urlencode($selected_manager); ?>" class="btn btn-success btn-action">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </a>
                                                <a href="?action=reject&request_id=<?php echo $request->auto_id; ?>&sale_manager=<?php echo urlencode($selected_manager); ?>" class="btn btn-danger btn-action">
                                                    <i class="fas fa-times me-1"></i>Decline
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No pending requests</h5>
                        <p>All roster change requests have been processed</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Processed Requests Tab -->
            <div class="tab-pane fade" id="processed" role="tabpanel">
                <?php if (!empty($processed_requests)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover request-table text-center">
                            <thead class="table-header">
                                <tr>
                                    <th>Requested Date</th>
                                    <th>Agent</th>
                                    <th>Type</th>
                                    <th>Current</th>
                                    <th>Requested</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo !empty($request->created_date) 
                                                ? esc_html(date('d/m/Y', strtotime($request->created_date))) 
                                                : '';
                                            ?>
                                        </td>

                                        <td><?php echo esc_html($request->agent_name); ?></td>
                                        <td><strong><?php echo esc_html($request->type); ?></strong></td>
                                        <td>
                                          <?php 
                                          if ($request->type === 'RDO Change Request') {
                                              $d = date_for_weekday_in_same_week($request->created_date ?? '', $request->current_rdo ?? '');
                                              echo esc_html($request->current_rdo ?: '-');
                                              if ($d) echo '<span class="weekday-date">'.esc_html($d).'</span>';
                                          } elseif ($request->type === 'Shift Change Request') {
                                              echo esc_html($request->current_shift ?: '-');
                                          } else {
                                              echo '-';
                                          }
                                          ?>
                                        </td>
                                        <td>
                                          <?php 
                                          if ($request->type === 'RDO Change Request') {
                                              $d = date_for_weekday_in_same_week($request->created_date ?? '', $request->requested_rdo ?? '');
                                              echo esc_html($request->requested_rdo ?: '-');
                                              if ($d) echo '<span class="weekday-date">'.esc_html($d).'</span>';
                                          } elseif ($request->type === 'Shift Change Request') {
                                              echo esc_html($request->requested_shift ?: '-');
                                          } elseif ($request->type === 'Leave Request') {
                                              echo esc_html($request->leave_request ?: '');
                                          }
                                          ?>
                                        </td>

                                        <td>
                                            <div class="details-cell">
                                                <div class="d-flex justify-content-center position-relative">
                                                    <p class="reason-box m-0">Reason</p>
                                                    <div class="reason-tooltip">
                                                        <?php echo esc_html($request->reason); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo strtolower($request->status); ?>">
                                                <?php echo esc_html($request->status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h5>No approval history yet</h5>
                        <p>Approved or rejected requests will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
            
        <div class="tab-pane fade" id="leave-requests-processed" role="tabpanel">
            <?php 
            // Get processed leave requests (not 'Initiated')
            if (!empty($selected_manager)) {
                $processed_leave_requests = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $leave_requests_table WHERE sm = %s AND current_status != 'Initiated' and MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1 ORDER BY doc_no DESC",
                        $selected_manager
                    )
                );
            } else {
                $processed_leave_requests = $wpdb->get_results(
                    "SELECT * FROM $leave_requests_table WHERE current_status != 'Initiated' and MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1 ORDER BY doc_no DESC"
                );
            }
            ?>
            
            <?php if (!empty($processed_leave_requests)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped leave-requests text-center align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>DocNo</th>
                                <th>Employee Code</th>
                                <th>Employee Name</th>
                                <th>Leave Type</th>
                                <th class="date-col">Date</th>
                                <th>Remarks</th>
                                <th class="status-col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $group_idx = 0;
                            foreach ($processed_leave_requests as $req):
                                $dates = daterange($req->from_date, $req->till_date);
                                $rowspan = max(1, count($dates));
                                $group_class = ($group_idx % 2 === 0) ? 'leave-group-even' : 'leave-group-odd';
                                foreach ($dates as $index => $date):
                            ?>
                                    <tr class="<?php echo $group_class; ?>">
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->doc_no); ?></td>
                                            <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->employee_code); ?></td>
                                            <td rowspan="<?php echo $rowspan; ?>" class="employee-name"><?php echo esc_html($req->employee_name); ?></td>
                                            <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->leave_type); ?></td>
                                        <?php endif; ?>
                                        <td class="date-col"><?php echo esc_html($date); ?></td>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>">
                                                <div class="position-relative d-inline-block">
                                                    <span class="remarks-preview" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo esc_attr($req->remarks); ?>">
                                                        <?php echo strlen($req->remarks) > 20 ? substr(esc_html($req->remarks), 0, 20).'...' : esc_html($req->remarks); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td rowspan="<?php echo $rowspan; ?>" class="status-col">
                                                <span class="status-<?php echo strtolower($req->current_status); ?>">
                                                    <?php echo esc_html($req->current_status); ?>
                                                </span>
                                            </td>
                                        
                                        <?php endif; ?>
                                    </tr>
                            <?php endforeach;
                                $group_idx++;
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h5>No processed leave requests found</h5>
                    <p>Approved or rejected leave requests will appear here</p>
                </div>
            <?php endif; ?>
        </div>
            
            <div class="tab-pane fade" id="leave-requests" role="tabpanel">
    <?php if (!empty($leave_requests)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped leave-requests text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>DocNo</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Leave Type</th>
                        <th class="date-col">Date</th>
                        <th>Remarks</th>
                        <th class="status-col">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $group_idx = 0;
                    foreach ($leave_requests as $req):
                        $dates = daterange($req->from_date, $req->till_date);
                        $rowspan = max(1, count($dates));
                        $group_class = ($group_idx % 2 === 0) ? 'leave-group-even' : 'leave-group-odd';
                        foreach ($dates as $index => $date):
                    ?>
                            <tr class="<?php echo $group_class; ?>">
                                <?php if ($index === 0): ?>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->doc_no); ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->employee_code); ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>" class="employee-name"><?php echo esc_html($req->employee_name); ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo esc_html($req->leave_type); ?></td>
                                <?php endif; ?>
                                <td class="date-col"><?php echo esc_html($date); ?></td>
                                <?php if ($index === 0): ?>
                                    <td rowspan="<?php echo $rowspan; ?>">
                                        <div class="position-relative d-inline-block">
                                            <span class="remarks-preview" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo esc_attr($req->remarks); ?>">
                                                <?php echo strlen($req->remarks) > 20 ? substr(esc_html($req->remarks), 0, 20).'...' : esc_html($req->remarks); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td rowspan="<?php echo $rowspan; ?>" class="status-col"><?php echo esc_html($req->current_status); ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>">
                                        <?php if (strtolower($req->current_status) === 'initiated'): ?>
                                            <div class="d-flex justify-content-center gap-2">
                                                <form method="post" class="mb-0">
                                                    <?php wp_nonce_field('leave_request_action'); ?>
                                                    <input type="hidden" name="request_id" value="<?php echo intval($req->id); ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                </form>
                                                <form method="post" class="mb-0">
                                                    <?php wp_nonce_field('leave_request_action'); ?>
                                                    <input type="hidden" name="request_id" value="<?php echo intval($req->id); ?>">
                                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <em class="text-muted">Action taken</em>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                    <?php endforeach;
                        $group_idx++;
                    endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h5>No leave requests found</h5>
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
            Request has been processed successfully!
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>

// Corrected JavaScript - single DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Show toast if there's a success message in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('action')) {
        const toastEl = document.getElementById('successToast');
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
    
    // Handle approve button clicks for all request types
    document.querySelectorAll('a[href*="action=approve"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const row = this.closest('tr');
            if (row) {
                const requestType = row.querySelector('td:nth-child(2) strong').textContent;
                let confirmationMessage = '';
                
                if (requestType === 'Shift Change Request') {
                    confirmationMessage = 'Are you sure you want to approve this shift change request? This will update the agent\'s shift time in the system.';
                } else if (requestType === 'RDO Change Request') {
                    confirmationMessage = 'Are you sure you want to approve this RDO change request? This will update the agent\'s RDO in the availability sheet.';
                } else if (requestType === 'Leave Request') {
                    confirmationMessage = 'Are you sure you want to approve this Leave request? ';
                
                if (confirmationMessage && !confirm(confirmationMessage)) {
                    e.preventDefault();
                }
            }
        });
    });
});


</script>
</body>
</html>

<?php
get_footer();
?>