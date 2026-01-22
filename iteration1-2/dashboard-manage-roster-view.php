<!-- This file also needs the function get_weekly_roster_data() declared in functions.php to work, please include that when making any changes. -->

<?php
/**
 * Template Name: Manager Roster View
 * Template Post Type: post, page
 */
get_header();
$current_user = wp_get_current_user();
$loggedInLogin = $current_user->user_login;
$isAdmin = in_array('administrator', (array) $current_user->roles);

// Get mapped sales manager name
global $wpdb;


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Team Roster</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    <style>
        /* Calendar Styles */
        .calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr); /* 7 equal columns */
    gap: 1px;
    background: #ccc;
    border: 1px solid #333;
    border-radius: 5px;
    width: 100vw; /* Full screen width */
    margin: 0;
    padding: 0;
}

.calendar-header {
    background: #ffd207;
    text-align: center;
    font-weight: bold;
    font-size: 1.8rem;
    padding: 10px 5px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
}

.day-cell {
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px;
    min-height: 120px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.day-cell::after {
    content: "View Details";
    position: absolute;
    bottom: 5px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 1rem;
    color: #6c757d;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.day-cell:hover::after {
    opacity: 1;
}

.day-number {
    text-align: center;
    font-weight: bold;
    margin-bottom: 5px;
}

.day-summary {
    font-size: 0.9rem;
    text-align: center;
    margin-bottom: 5px;
    padding: 2px;
    background-color: #f8f9fa;
    border-radius: 3px;
}

.agent-name {
    font-size: 1rem;
    margin: 3px 0;
    padding: 3px 6px;
    border-radius: 3px;
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.agent-shift {
    background-color: rgba(100, 181, 246, 0.5);
}

.agent-rdo {
    background-color: rgba(102, 212, 156, 0.9);
}

.agent-leave {
    background-color: rgba(255, 167, 71, 0.9);
}

.agent-convert {
    background-color: rgba(255, 255, 0, 0.9);
}

.attendance-missing {
    background-color: rgba(255, 99, 71, 0.2); /* light red background */
    color: #dc3545; /* red text */
    padding: 3px 6px;
    border-radius: 4px;
    font-weight: 500;
}

.summary-avail {
    color: #1e88e5;
    font-weight: bold;
}

.summary-rdo {
    color: #2e7d32;
    font-weight: bold;
}

.summary-convert {
    color: #b59f00;
    font-weight: bold;
}

.summary-leave {
    color: #f57c00;
    font-weight: bold;
}

.bg-orange {
    background-color: rgba(255, 167, 71, 0.15) !important;
    color: #f57c00 !important;
}

.bg-yellow {
    background-color: rgba(255, 255, 0, 0.15) !important;
    color: #b59f00 !important;
}

.text-orange {
    color: #f57c00 !important;
}

.text-yellow {
    color: #b59f00 !important;
}

.text-green {
    color: #4caf50 !important;
}
html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.container, .site-content {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}




    </style>
</head>

<body>

    <div class="container mt-4">
        <?php
        date_default_timezone_set('Asia/Kolkata'); // IST timezone
        $selectedTeam = isset($_GET['team']) ? sanitize_text_field($_GET['team']) : '';
        $selectedRosterType = isset($_GET['roster_type']) ? sanitize_text_field($_GET['roster_type']) : '';
        $selectedSalesManager = isset($_GET['sale_manager']) ? sanitize_text_field($_GET['sale_manager']) : '';
        $selectedShiftTime = isset($_GET['shift_time']) ? sanitize_text_field($_GET['shift_time']) : '';
        $monthToShow = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $yearToShow = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Convert numeric month to month name for database comparison
        $monthNameForDB = date('F', mktime(0, 0, 0, $monthToShow, 1, $yearToShow));

        $firstDayOfMonth = strtotime("$yearToShow-$monthToShow-01");
        $displayMonthName = date('F', $firstDayOfMonth);
        $daysInMonth = date('t', $firstDayOfMonth);
        $startWeekday = date('w', $firstDayOfMonth);
        $nextMonth = ($monthToShow == 12) ? 1 : $monthToShow + 1;
        $nextYear = ($monthToShow == 12) ? $yearToShow + 1 : $yearToShow;
        $prevMonth = ($monthToShow == 1) ? 12 : $monthToShow - 1;
        $prevYear = ($monthToShow == 1) ? $yearToShow - 1 : $yearToShow;

        function buildUrl($baseUrl, $m, $y, $team, $rosterType = '', $salesManager = '', $shiftTime = '')
        {
            $url = "$baseUrl?month=$m&year=$y";
            if ($team)
                $url .= "&team=" . urlencode($team);
            if ($rosterType)
                $url .= "&roster_type=" . urlencode($rosterType);
            if ($salesManager)
                $url .= "&sale_manager=" . urlencode($salesManager);
            if ($shiftTime)
                $url .= "&shift_time=" . urlencode($shiftTime);
            return $url;
        }

        // Build the base query - using month name for comparison and joining with agent_codes table

        if ($isAdmin) {
            // Admin sees all records
            $query = "SELECT r.*
                      FROM wpk4_backend_employee_roster r
                      WHERE r.month = %s AND r.year = %d";
            $query_params = array($monthNameForDB, $yearToShow);
        } else {
            // Manager sees only their team
            $managerName = $wpdb->get_var( $wpdb->prepare(
                "SELECT DISTINCT sale_manager 
                 FROM wpk4_backend_agent_codes 
                 WHERE wordpress_user_name = %s" 
            ));
        
            $query = "SELECT r.*
                      FROM wpk4_backend_employee_roster r
                      WHERE r.month = %s AND r.year = %d";
            $query_params = array($monthNameForDB, $yearToShow);
        }



        // Add filters to the query
        if (!empty($selectedTeam)) {
            $query .= " AND r.team = %s";
            $query_params[] = $selectedTeam;
        }
        if (!empty($selectedRosterType)) {
            $query .= " AND r.department = %s";
            $query_params[] = $selectedRosterType;
        }
        if (!empty($selectedSalesManager)) {
            $query .= " AND r.sm = %s";
            $query_params[] = $selectedSalesManager;
        }
        if (!empty($selectedShiftTime)) {
            $query .= " AND r.shift_time = %s";
            $query_params[] = $selectedShiftTime;
        }

        $rosters = $wpdb->get_results($wpdb->prepare($query, $query_params));

        // Get distinct values for filters from agent_codes table
        $teams = $wpdb->get_col("SELECT DISTINCT team FROM wpk4_backend_employee_roster ORDER BY team");
        $salesManagers = $wpdb->get_col("SELECT DISTINCT sm FROM wpk4_backend_employee_roster ORDER BY sm");
        
        // Get roster types and shift times from employee_roster table joined with agent_codes table
        $rosterTypes = $wpdb->get_col("
            SELECT DISTINCT r.department 
            FROM wpk4_backend_employee_roster r
            WHERE r.department != ' ' 
            ORDER BY r.department
        ");
        
        $shiftTimes = $wpdb->get_col("
            SELECT DISTINCT r.shift_time 
            FROM wpk4_backend_employee_roster r
            JOIN wpk4_backend_agent_codes a ON r.employee_code = a.roster_code
            WHERE r.shift_time != '' 
            ORDER BY r.shift_time
        ");

        // Prepare day data
        $dayData = array();
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dayData[$day] = array(
        'shifts' => array(),
        'rdo' => array(),
        'leave' => array(),
        'convert' => array(),
        'ulwp' => array()
    );
}

// Get "now" in Sydney timezone
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$todayDay = (int)date('j');
$todayMonth = (int)date('n');
$todayYear = (int)date('Y');

foreach ($rosters as $roster) {
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $column = 'day_' . $day;
        if (!isset($roster->$column)) continue;

        $status = strtoupper(trim($roster->$column));
        $employeeInfo = array(
            'name' => $roster->employee_name,
            'code' => $roster->employee_code,
            'team' => $roster->team, 
            'tl' => $roster->tl,
            'sm' => $roster->sm,
            'shift_time' => $roster->shift_time,
            'status' => $status
        );

        $empCode = $roster->employee_code;
        $dateStr = sprintf('%02d/%02d/%04d', $day, $monthToShow, $yearToShow);

        $login_time = '';
        $login_date = '';
        $logout_time = '';
        $logout_date = '';
        $all_events = [];

        // --- Attendance events ---
        if (!empty($empCode)) {
            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT Etime, EntryExitType, Edate
                     FROM wpk4_Mx_VEW_UserAttendanceEvents
                     WHERE UserID = %s AND Edate = %s
                     ORDER BY Etime ASC",
                    $empCode, $dateStr
                ),
                ARRAY_A
            );
            $all_events = $events ?: [];
            foreach ($events as $ev) {
                if ($ev['EntryExitType'] == 0) {
                    $login_time = $ev['Etime'];
                    $login_date = $ev['Edate'];
                    break;
                }
            }
            for ($i = count($events) - 1; $i >= 0; $i--) {
                $ev = $events[$i];
                if ($ev['EntryExitType'] == 1) {
                    $logout_time = $ev['Etime'];
                    $logout_date = $ev['Edate'];
                    break;
                }
            }
        }

        $employeeInfo['attendance'] = [
            'Punch1_Date' => $login_date ?: '-',
            'COL35'       => $login_time ?: '-',
            'COL126'      => $logout_time ?: '-',
            'COL125'      => $logout_date ?: '-',
        ];
        $employeeInfo['all_events'] = $all_events;
        $employeeInfo['attendance_missing'] = ($login_time === '' && $logout_time === '');

        // Always add to main group
        if ($status === 'RDO') {
            $dayData[$day]['rdo'][] = $employeeInfo;
        } elseif ($status === 'LEAVE') {
            $dayData[$day]['leave'][] = $employeeInfo;
        } elseif ($status === 'CONVERT') {
            $dayData[$day]['convert'][] = $employeeInfo;
        } elseif ($status === 'ULWP') {
            $dayData[$day]['ulwp'][] = $employeeInfo;
        } elseif (!empty($status)) {
            // Add to shift group
            if (!isset($dayData[$day]['shifts'][$status])) {
                $dayData[$day]['shifts'][$status] = array();
            }
            $dayData[$day]['shifts'][$status][] = $employeeInfo;
        }

        // Now, *separately*, decide whether to add to ULWP group:
        $isToday = ($day == $todayDay && $monthToShow == $todayMonth && $yearToShow == $todayYear);
$hasAttendance = !$employeeInfo['attendance_missing'];

if ($status !== 'RDO' && $status !== 'LEAVE' && $status !== 'CONVERT') {
    if ($isToday && !$hasAttendance) {
        $shiftTimeRaw = $employeeInfo['shift_time'];
        if (preg_match('/^(\d{2})(\d{2})$/', $shiftTimeRaw, $m)) {
            $shiftHour = intval($m[1]);
            $shiftMin = intval($m[2]);
            $shiftStart = new DateTime($now->format('Y-m-d') . sprintf(' %02d:%02d:00', $shiftHour, $shiftMin), new DateTimeZone('Australia/Sydney'));
            $shiftStart->modify('+30 minutes');
            // DEBUG: uncomment this to check values
            // error_log("Agent: {$employeeInfo['name']} Now: " . $now->format('Y-m-d H:i:s') . " Shift+30: " . $shiftStart->format('Y-m-d H:i:s'));
            if ($now >= $shiftStart) {
                $ulwpCopy = $employeeInfo;
                $ulwpCopy['status'] = 'ULWP';
                $dayData[$day]['ulwp'][] = $ulwpCopy;
            }
        } else {
            // If no/invalid shift time, use 8am fallback
            $shiftStart = new DateTime($now->format('Y-m-d') . ' 08:00:00', new DateTimeZone('Australia/Sydney'));
            if ($now >= $shiftStart) {
                $ulwpCopy = $employeeInfo;
                $ulwpCopy['status'] = 'ULWP';
                $dayData[$day]['ulwp'][] = $ulwpCopy;
            }
        }
    } elseif (!$isToday && !$hasAttendance) {
        // Past days, always add to ULWP if missing
        $ulwpCopy = $employeeInfo;
        $ulwpCopy['status'] = 'ULWP';
        $dayData[$day]['ulwp'][] = $ulwpCopy;
    }
}

    }
}

    
        ?>

        <!-- Header -->
        <br><br><br>
        <h1 class="text-center mb-3 display-3">Team Roster – <?php echo "$displayMonthName $yearToShow"; ?></h1>

        <!-- View Toggle Buttons -->
        <div class="text-center mb-4 view-toggle">
            <button class="btn btn-lg btn-outline-primary active px-4 py-2" onclick="showView('monthly')"><span
                    class="fw-medium">Monthly View</span></button>
            <button class="btn btn-lg btn-outline-primary px-4 py-2" onclick="showView('weekly')"><span
                    class="fw-medium">Weekly View</span></button>
         <!--    <button class="btn btn-lg btn-outline-primary px-4 py-2" onclick="showView('agent')"><span
                    class="fw-medium">Agent View</span></button> -->
        </div>

        <!-- Filters -->
        <div class="filter-container mb-4">
            <form method="get" action="">
                <input type="hidden" name="month" value="<?php echo $monthToShow; ?>">
                <input type="hidden" name="year" value="<?php echo $yearToShow; ?>">

                <div class="row">
                    <div class="col-md-3">
                        <label for="team" class="form-label" style="font-size: 1.5rem">Team</label>
                        <select class="form-select" id="team" name="team">
                            <option value="">All Teams</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo esc_attr($team); ?>" <?php echo $selectedTeam === $team ? 'selected' : ''; ?>>
                                    <?php echo esc_html($team); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="roster_type" class="form-label" style="font-size: 1.5rem">Department</label>
                        <select class="form-select" id="roster_type" name="roster_type">
                            <option value="">All Types</option>
                            <?php foreach ($rosterTypes as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php echo $selectedRosterType === $type ? 'selected' : ''; ?>>
                                    <?php echo esc_html($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="sale_manager" class="form-label" style="font-size: 1.5rem">Sales Manager</label>
                        <select class="form-select" id="sale_manager" name="sale_manager">
                            <option value="">All Managers</option>
                            <?php foreach ($salesManagers as $manager): ?>
                                <option value="<?php echo esc_attr($manager); ?>" <?php echo $selectedSalesManager === $manager ? 'selected' : ''; ?>>
                                    <?php echo esc_html($manager); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="shift_time" class="form-label" style="font-size: 1.5rem">Shift Time</label>
                        <select class="form-select" id="shift_time" name="shift_time">
                            <option value="">All Shifts</option>
                            <?php foreach ($shiftTimes as $shift): ?>
                                <option value="<?php echo esc_attr($shift); ?>" <?php echo $selectedShiftTime === $shift ? 'selected' : ''; ?>>
                                    <?php echo esc_html($shift); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12 text-center">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="?month=<?php echo $monthToShow; ?>&year=<?php echo $yearToShow; ?>"
                            class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Month Navigation -->
        <div class="d-flex justify-content-between mb-4">
            <a href="<?php echo buildUrl(get_permalink(), $prevMonth, $prevYear, $selectedTeam, $selectedRosterType, $selectedSalesManager, $selectedShiftTime); ?>"
                class="btn btn-outline-primary">
                &laquo; Previous Month
            </a>
            <h3 class="text-center" style="font-size: 2.2rem;"><?php echo "$displayMonthName $yearToShow"; ?></h3>
            <a href="<?php echo buildUrl(get_permalink(), $nextMonth, $nextYear, $selectedTeam, $selectedRosterType, $selectedSalesManager, $selectedShiftTime); ?>"
                class="btn btn-outline-primary">
                Next Month &raquo;
            </a>
        </div>

        <div style="white-space: nowrap; text-align: center; margin: 15px 0; font-size: 1.3rem;">
            <strong>Disclaimer:</strong>The colours representation is as follows:
            <span style="color: #1e88e5;">Light Blue: On Shift</span> &nbsp;&nbsp;
            <span style="color: #2e7d32;">Green: RDO</span> &nbsp;&nbsp;
            <span style="color: #f57c00;">Orange: Leave</span> &nbsp;&nbsp;
            <span style="color: #b59f00;">Yellow: Convert</span>
        </div>

        <!-- Calendar -->
        <div class="d-flex justify-content-center">
            <div class="calendar">
            <!-- Calendar Headers -->
            <div class="calendar-header">Sun</div>
            <div class="calendar-header">Mon</div>
            <div class="calendar-header">Tue</div>
            <div class="calendar-header">Wed</div>
            <div class="calendar-header">Thu</div>
            <div class="calendar-header">Fri</div>
            <div class="calendar-header">Sat</div>
            
            

            <?php
            // Empty cells for days before the first of the month
            for ($i = 0; $i < $startWeekday; $i++) {
                echo '<div class="day-cell" style="background-color: #f8f9fa;"></div>';
            }

            // Day cells
            $currentDay = 1;
            while ($currentDay <= $daysInMonth) {
                $dayClass = '';
                if (date('Y-m-d') == sprintf("%04d-%02d-%02d", $yearToShow, $monthToShow, $currentDay)) {
                    $dayClass = 'bg-info bg-opacity-10';
                }

                $shifts = $dayData[$currentDay]['shifts'];
                $rdo = $dayData[$currentDay]['rdo'];
                $leave = $dayData[$currentDay]['leave'];
                $convert = $dayData[$currentDay]['convert'];
                $ulwp = $dayData[$currentDay]['ulwp'];
                

                // Calculate total agents available (working shifts)
                // Calculate total agents available (working shifts)
$totalAvailable = 0;
$ulwpCount = 0;

$todayTimestamp = strtotime(date('Y-m-d'));
$currentDateTimestamp = strtotime("$yearToShow-$monthToShow-$currentDay");

$totalAvailable = 0;
$ulwpCount = 0;

$todayTimestamp = strtotime(date('Y-m-d'));
$currentDateTimestamp = strtotime("$yearToShow-$monthToShow-$currentDay");

foreach ($shifts as $shiftTime => $employees) {
    foreach ($employees as $employee) {
        $totalAvailable++;
        // Only count ULWP if the agent is actually late and has no attendance!
        $isULWP = false;

        if ($currentDateTimestamp < $todayTimestamp) {
            // In the past, no attendance = ULWP
            if (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-') {
                $isULWP = true;
            }
        } elseif ($currentDateTimestamp == $todayTimestamp) {
            // For today, only count as ULWP if their shift+30min is <= now and no login!
            $shiftRaw = $employee['shift_time'];
            if (preg_match('/^(\d{2})(\d{2})$/', $shiftRaw, $m)) {
                $shiftHour = intval($m[1]);
                $shiftMin = intval($m[2]);
                $shiftPlus30 = strtotime(sprintf("%04d-%02d-%02d %02d:%02d:00", $yearToShow, $monthToShow, $currentDay, $shiftHour, $shiftMin)) + 30 * 60;
                $now = strtotime(date('Y-m-d H:i:s'));
                if ($now >= $shiftPlus30 && (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-')) {
                    $isULWP = true;
                }
            } else {
                // If shift time is missing, fallback to 8:30am logic
                $shiftPlus30 = strtotime(sprintf("%04d-%02d-%02d 08:30:00", $yearToShow, $monthToShow, $currentDay));
                $now = strtotime(date('Y-m-d H:i:s'));
                if ($now >= $shiftPlus30 && (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-')) {
                    $isULWP = true;
                }
            }
        }
        if ($isULWP) $ulwpCount++;
    }
}


                echo '<div class="day-cell ' . $dayClass . '" data-day="' . $currentDay . '" data-month="' . $monthToShow . '" data-year="' . $yearToShow . '">';
                echo '<div class="day-number">' . $currentDay . '</div>';

                // Display summary
                echo '<div class="day-summary">';
echo '<span class="summary-avail">Avail: ' . ($totalAvailable - $ulwpCount) . '</span> | ';
echo '<span class="summary-rdo">RDO: ' . count($rdo) . '</span> | ';
echo '<span class="summary-convert">Conv: ' . count($convert) . '</span> | ';
echo '<span class="summary-leave">Leave: ' . count($leave) . '</span> | ';

if ($currentDateTimestamp <= $todayTimestamp) {
    echo '<span class="text-danger fw-bold">ULWP: ' . $ulwpCount . '</span>';
} else {
    echo '<span class="text-muted">ULWP: 0</span>'; // Future dates show 0 ULWP
}
echo '</div>';
                
                if (!empty($shifts)) {
                    echo '<div style="
                        font-weight: bold;
                        font-size: 0.95rem;
                        margin-bottom: 6px;
                        background-color: #ffd207;
                        color: #000;
                        padding: 4px 6px;
                        text-align: center;
                        border-radius: 4px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    ">';
                    echo 'Agent Name | Shift Time | Login | Logout';
                    echo '</div>';
                }

                // Display shift employees
                foreach ($shifts as $shiftTime => $employees) {
    foreach ($employees as $employee) {
        $status = $employee['status'];
        $punch1_time = (!empty($employee['attendance']['COL35']) && $employee['attendance']['COL35'] !== '-') 
            ? date('H:i', strtotime($employee['attendance']['COL35'])) 
            : '-';
        $punch_out = (!empty($employee['attendance']['COL126']) && $employee['attendance']['COL126'] !== '-') 
            ? date('H:i', strtotime($employee['attendance']['COL126'])) 
            : '-';

        $todayDate = strtotime(date('Y-m-d')); // Current date
        $thisCellDate = strtotime("$yearToShow-$monthToShow-$currentDay");
        $bgClass = '';

        $isULWP = false;
        if ($thisCellDate < $todayDate) {
            if (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-') {
                $isULWP = true;
            }
        } elseif ($thisCellDate == $todayDate) {
            $shiftRaw = $employee['shift_time'];
            if (preg_match('/^(\d{2})(\d{2})$/', $shiftRaw, $m)) {
                $shiftHour = intval($m[1]);
                $shiftMin = intval($m[2]);
                $shiftPlus30 = strtotime(sprintf("%04d-%02d-%02d %02d:%02d:00", $yearToShow, $monthToShow, $currentDay, $shiftHour, $shiftMin)) + 30 * 60;
                $now = strtotime(date('Y-m-d H:i:s'));
                if ($now >= $shiftPlus30 && (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-')) {
                    $isULWP = true;
                }
            } else {
                $shiftPlus30 = strtotime(sprintf("%04d-%02d-%02d 08:30:00", $yearToShow, $monthToShow, $currentDay));
                $now = strtotime(date('Y-m-d H:i:s'));
                if ($now >= $shiftPlus30 && (empty($employee['attendance']['COL35']) || $employee['attendance']['COL35'] === '-')) {
                    $isULWP = true;
                }
            }
        }

        if ($isULWP) {
            $bgClass = 'attendance-missing';
        }

        echo '<div class="agent-name agent-shift ' . $bgClass . '" title="' . $employee['name'] . ' - ' . $shiftTime . '" data-agent-code="' . esc_attr($employee['code']) . '" data-day="' . $currentDay . '">';
        if ($isULWP) {
            echo htmlspecialchars($employee['name'] . ' | ' . $shiftTime . ' | ');
            echo '<span style="color:red; font-weight:bold;">ULWP</span>';
        } else {
            echo htmlspecialchars($employee['name'] . ' | ' . $shiftTime . ' | ' . $punch1_time . ' | ' . $punch_out);
        }
        echo '</div>';
    }
}





                // Display RDO employees
                foreach ($rdo as $employee) {
                    echo '<div class="agent-name agent-rdo" title="' . $employee['name'] . ' - RDO" data-agent-code="' . esc_attr($employee['code']) . '" data-day="' . $currentDay . '">';
                    echo htmlspecialchars($employee['name']);
                    echo '</div>';
                }

                // Display Leave employees
                foreach ($leave as $employee) {
                    echo '<div class="agent-name agent-leave" title="' . $employee['name'] . ' - Leave">';
                    echo htmlspecialchars($employee['name']);
                    echo '</div>';
                }

                // Display Convert employees
                foreach ($convert as $employee) {
                    echo '<div class="agent-name agent-convert" title="' . $employee['name'] . ' - Convert">';
                    echo htmlspecialchars($employee['name']);
                    echo '</div>';
                }

                echo '</div>';
                $currentDay++;

                // Break the row after 7 days
                if (($startWeekday + $currentDay - 1) % 7 === 0 && $currentDay <= $daysInMonth) {
                    // This would naturally flow in the grid layout
                }
            }

            // Empty cells after the last day of the month to complete the grid
            $remainingCells = (7 - (($startWeekday + $daysInMonth) % 7)) % 7;
            for ($i = 0; $i < $remainingCells; $i++) {
                echo '<div class="day-cell" style="background-color: #f8f9fa;"></div>';
            }
            ?>
        </div>
        </div>

        <!-- Day Details Modal -->
        <div class="modal fade" id="dayDetailsModal" tabindex="-1" aria-labelledby="dayDetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title" id="dayDetailsModalLabel" style="text-align: center;">Day Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
    <div class="modal-day-header">
        <h4 id="modal-day-title" style="font-size: 1.5rem;"></h4>
        <!-- badges/counters if needed -->
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-striped table-bordered employee-table">
            <thead class="yellow-header">
                <tr>
                    <th>Agent Name</th>
                    <th>Team Name</th>
                    <th>Sales Manager</th>
                    <th>Shift Time</th>
                    <th>Login Date</th>
                    <th>Login Time</th>
                    <th>Logout Time</th>
                    <th>Logout Date</th>
                </tr>
            </thead>
            <tbody id="employee-details">
                <!-- JS fills summary here -->
            </tbody>
        </table>
    </div>
    <h4 class="mt-4 mb-2">Attendance Events (All Swipes)</h4>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Punch Type</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody id="event-list-details">
                <!-- JS fills all IN/OUT punches here -->
            </tbody>
        </table>
    </div>
</div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly View (hidden by default) -->
        <div id="weekly-view" style="display: none;">
            <div class="d-flex justify-content-center mb-4">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary date-range-btn active" data-start="1"
                        data-end="10">1-10</button>
                    <button type="button" class="btn btn-outline-primary date-range-btn" data-start="11"
                        data-end="20">11-20</button>
                    <button type="button" class="btn btn-outline-primary date-range-btn" data-start="21" 
                        data-end="last">21-<?php echo $daysInMonth; ?></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="weekly-table">
                    <thead class="table-dark">
                        <tr>
                            <th>Agent Name</th>
                            <?php
                            // Generate day headers for the first range (1-10) by default
                            for ($day = 1; $day <= 10; $day++) {
                                $weekday = date('D', strtotime("$yearToShow-$monthToShow-$day"));
                                echo "<th>$day<br>$weekday</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Will be filled by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.agent-name').forEach(function(agentDiv) {
        agentDiv.addEventListener('click', function(e) {
            e.stopPropagation();

            const agentCode = this.getAttribute('data-agent-code');
            const day = this.getAttribute('data-day');

            // Find the agent in dayData
            const data = dayData[day];
            let agent = null, statusDisplay = '';
            for (const shift in data.shifts) {
                agent = data.shifts[shift].find(emp => emp.code === agentCode);
                if (agent) { statusDisplay = shift; break; }
            }
            if (!agent) { agent = data.rdo.find(emp => emp.code === agentCode); statusDisplay = 'RDO'; }
            if (!agent) { agent = data.leave.find(emp => emp.code === agentCode); statusDisplay = 'Leave'; }
            if (!agent) { agent = data.convert.find(emp => emp.code === agentCode); statusDisplay = 'Convert'; }
            if (!agent) { agent = data.ulwp.find(emp => emp.code === agentCode); statusDisplay = 'ULWP'; }

            if (!agent) return;

            // Modal title
            const monthName = "<?php echo $displayMonthName; ?>";
            const yearShow = <?php echo $yearToShow; ?>;
            document.getElementById('modal-day-title').textContent =
                `${monthName} ${day}, ${yearShow}   ${agent.name}`;

            // Attendance info from roster/attendance table
            let att = agent.attendance || {};
            let login_date = att.Punch1_Date || '';
            let login_time = att.COL35 || '';
            let logout_time = att.COL126 || '';
            let logout_date = att.COL125 || '';

            let statusClass = '';
            if (statusDisplay === 'RDO') statusClass = 'text-success';
            else if (statusDisplay === 'Leave') statusClass = 'text-orange';
            else if (statusDisplay === 'Convert') statusClass = 'text-yellow';
            else if (statusDisplay === 'ULWP') statusClass = 'text-danger';
            else statusClass = 'text-primary';

            // Fill modal table
            const tableBody = document.getElementById('employee-details');
            tableBody.innerHTML = '';
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${agent.name}</td>
                <td>${agent.team || '-'}</td>
                <td>${agent.sm || '-'}</td>
                <td class="${statusClass}">${statusDisplay}</td>
                <td>${login_date}</td>
                <td>${login_time}</td>
                <td>${logout_time}</td>
                <td>${logout_date}</td>
            `;
            tableBody.appendChild(row);

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));
            modal.show();
        });
    });
});
</script>

        <script>
            // Prepare the day data for JavaScript
            const dayData = <?php echo json_encode($dayData); ?>;
            const monthName = "<?php echo $displayMonthName; ?>";
            const year = <?php echo $yearToShow; ?>;
            const shiftTimes = <?php echo json_encode($shiftTimes); ?>;
        
            // Initialize modal when a day is clicked
            document.querySelectorAll('.day-cell[data-day]').forEach(dayElement => {
                dayElement.addEventListener('click', function() {
                    const day = this.getAttribute('data-day');
                    const dateStr = `${monthName} ${day}, ${year}`;
        
                    // Set modal title
                    document.getElementById('modal-day-title').textContent = dateStr;
        
                    // Get the data for this day
                    const data = dayData[day];
        
                    // Calculate total shifts (sum of all shift types)
                    let totalShifts = 0;
                    for (const shift in data.shifts) {
                        totalShifts += data.shifts[shift].length;
                    }
        
                    // Update counts
                    document.getElementById('shifts-count').textContent = totalShifts;
                    document.getElementById('rdo-count').textContent = data.rdo.length;
                    document.getElementById('leave-count').textContent = data.leave.length;
                    document.getElementById('convert-count').textContent = data.convert.length;
        
                    // Prepare all employees data
                    const allEmployees = [];
        
                    // Add shift employees
                    for (const shift in data.shifts) {
                        allEmployees.push(...data.shifts[shift].map(emp => ({
                            ...emp,
                            displayStatus: shift,
                            statusClass: 'text-primary'
                        })));
                    }
        
                    // Add RDO employees
                    allEmployees.push(...data.rdo.map(emp => ({
                        ...emp,
                        displayStatus: 'RDO',
                        statusClass: 'text-success'
                    })));
        
                    // Add Leave employees
                    allEmployees.push(...data.leave.map(emp => ({
                        ...emp,
                        displayStatus: 'Leave',
                        statusClass: 'text-orange'
                    })));
        
                    // Add Convert employees
                    allEmployees.push(...data.convert.map(emp => ({
                        ...emp,
                        displayStatus: 'Convert',
                        statusClass: 'text-yellow'
                    })));
        
                    // Populate the table
                    const tableBody = document.getElementById('employee-details');
                    tableBody.innerHTML = '';
        
                    if (allEmployees.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No employees found for this day</td></tr>';
                    } else {
                        allEmployees.forEach(emp => {
                            const row = document.createElement('tr');
                            
                            // Create dropdown options
                            let dropdownOptions = '';
                            
                            // Add shift times
                            shiftTimes.forEach(shift => {
                                dropdownOptions += `<option value="${shift}" ${emp.displayStatus === shift ? 'selected' : ''}>${shift}</option>`;
                            });
                            
                            // Add special statuses
                            dropdownOptions += `<option value="RDO" ${emp.displayStatus === 'RDO' ? 'selected' : ''}>RDO</option>`;
                            dropdownOptions += `<option value="Leave" ${emp.displayStatus === 'Leave' ? 'selected' : ''}>Leave</option>`;
                            dropdownOptions += `<option value="Convert" ${emp.displayStatus === 'Convert' ? 'selected' : ''}>Convert</option>`;
                            
                            row.innerHTML = `
                                <td>${emp.name}</td>
                                <td>${emp.team || '-'}</td>
                                <td>${emp.sm || '-'}</td>
                                <td class="${emp.statusClass}">
                                    <span class="shift-display">${emp.displayStatus}</span>
                                    <select class="shift-edit form-select d-none">
                                        ${dropdownOptions}
                                    </select>
                                </td>
                                <td>${emp.attendance?.Punch1_Date || ''}</td>         <!-- Date --> <!-- ADD THIS PART -->
                                <td>${emp.attendance?.COL35 || ''}</td>         <!-- Login Time -->
                                <td>${emp.attendance?.COL125 || ''}</td>        <!-- Logout Time -->
                                <td>${emp.attendance?.COL126 || ''}</td>        <!-- Date -->
                            `;
                            tableBody.appendChild(row);
                        });
                    }
        
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));
                    modal.show();
                    
                    // Add this after the modal.show() call
                    document.getElementById('dayDetailsModal').addEventListener('shown.bs.modal', function() {
                        // Edit button functionality
                        document.querySelectorAll('.edit-shift-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const row = this.closest('tr');
                                row.querySelector('.shift-display').classList.add('d-none');
                                row.querySelector('.shift-edit').classList.remove('d-none');
                                this.classList.add('d-none');
                                row.querySelector('.save-shift-btn').classList.remove('d-none');
                                row.querySelector('.cancel-edit-btn').classList.remove('d-none');
                            });
                        });
                        
                        // Cancel button functionality
                        document.querySelectorAll('.cancel-edit-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const row = this.closest('tr');
                                row.querySelector('.shift-display').classList.remove('d-none');
                                row.querySelector('.shift-edit').classList.add('d-none');
                                row.querySelector('.edit-shift-btn').classList.remove('d-none');
                                this.classList.add('d-none');
                                row.querySelector('.save-shift-btn').classList.add('d-none');
                            });
                        });
                        
                        // Save button functionality
                        document.querySelectorAll('.save-shift-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const row = this.closest('tr');
                                const newShift = row.querySelector('.shift-edit').value;
                                const empCode = this.getAttribute('data-code');
                                const day = this.getAttribute('data-day');
                                const month = <?php echo $monthToShow; ?>;
                                const year = <?php echo $yearToShow; ?>;
                                
                                // Show loading state
                                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                                
                                // AJAX call to save the shift
                                fetch(ajaxurl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams({
                                        'action': 'update_employee_shift',
                                        'emp_code': empCode,
                                        'day': day,
                                        'month': month,
                                        'year': year,
                                        'new_shift': newShift,
                                        'nonce': '<?php echo wp_create_nonce("update_shift_nonce"); ?>'
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Update UI
                                        row.querySelector('.shift-display').textContent = newShift;
                                        row.querySelector('.shift-display').classList.remove('d-none');
                                        row.querySelector('.shift-edit').classList.add('d-none');
                                        this.classList.add('d-none');
                                        row.querySelector('.cancel-edit-btn').classList.add('d-none');
                                        row.querySelector('.edit-shift-btn').classList.remove('d-none');
                                        
                                        // Update status class based on new shift value
                                        const statusCell = row.querySelector('td:nth-child(4)');
                                        statusCell.className = '';
                                        if (newShift === 'RDO') {
                                            statusCell.classList.add('text-success');
                                        } else if (newShift === 'Leave') {
                                            statusCell.classList.add('text-orange');
                                        } else if (newShift === 'Convert') {
                                            statusCell.classList.add('text-yellow');
                                        } else {
                                            statusCell.classList.add('text-primary');
                                        }
                                        
                                        // Show success message
                                        alert('Shift updated successfully!');
                                        
                                        // Reload the page to reflect changes
                                        location.reload();
                                    } else {
                                        alert('Error updating shift: ' + data.message);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('An error occurred while saving.');
                                })
                                .finally(() => {
                                    this.innerHTML = 'Save';
                                });
                            });
                        });
                    });
                });
            });
            // View toggle functionality
            function showView(view) {
                // Toggle active class on buttons
                document.querySelectorAll('.view-toggle button').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.classList.add('active');

                if (view === 'monthly') {
                    document.querySelector('.calendar').style.display = 'grid';
                    document.getElementById('weekly-view').style.display = 'none';
                } else if (view === 'weekly') {
                    document.querySelector('.calendar').style.display = 'none';
                    document.getElementById('weekly-view').style.display = 'block';
                    // Load initial data (1-10)
                    loadWeeklyData(1, 10);
                }
            }

            // Date range button functionality
            document.querySelectorAll('.date-range-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.date-range-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const start = this.dataset.start || 1;
                    const end = this.dataset.end === 'last' ? <?php echo $daysInMonth; ?> : this.dataset.end;

                    loadWeeklyData(start, end);
                });
            });

            // Function to load weekly data
            function loadWeeklyData(startDay, endDay) {
                    startDay = parseInt(startDay);
                    endDay = endDay === 'last' ? <?php echo $daysInMonth; ?> : parseInt(endDay);
                    
                    // Ensure we don't go beyond the month's days
                    endDay = Math.min(endDay, <?php echo $daysInMonth; ?>);
    
                // Update table headers
                const thead = document.querySelector('#weekly-table thead');
                let headers = '<th>Agent Name</th>';

                for (let day = parseInt(startDay); day <= parseInt(endDay); day++) {
                    const date = new Date(year, <?php echo $monthToShow - 1; ?>, day);
                    const weekday = date.toLocaleDateString('en-US', { weekday: 'short' });
                    headers += `<th>${day}<br>${weekday}</th>`;
                }

                thead.innerHTML = `<tr>${headers}</tr>`;

                // Filter unique employees
                const uniqueEmployees = {};
                for (let day = 1; day <= <?php echo $daysInMonth; ?>; day++) {
                    if (dayData[day]) {
                        // Process shifts
                        for (const shift in dayData[day].shifts) {
                            dayData[day].shifts[shift].forEach(emp => {
                                if (!uniqueEmployees[emp.code]) {
                                    uniqueEmployees[emp.code] = {
                                        name: emp.name,
                                        code: emp.code,
                                        days: {}
                                    };
                                }
                                uniqueEmployees[emp.code].days[day] = shift;
                            });
                        }
                        // Process RDOs
                        dayData[day].rdo.forEach(emp => {
                            if (!uniqueEmployees[emp.code]) {
                                uniqueEmployees[emp.code] = {
                                    name: emp.name,
                                    code: emp.code,
                                    days: {}
                                };
                            }
                            uniqueEmployees[emp.code].days[day] = 'RDO';
                        });
                        // Process Leave
                        dayData[day].leave.forEach(emp => {
                            if (!uniqueEmployees[emp.code]) {
                                uniqueEmployees[emp.code] = {
                                    name: emp.name,
                                    code: emp.code,
                                    days: {}
                                };
                            }
                            uniqueEmployees[emp.code].days[day] = 'Leave';
                        });
                        // Process Convert
                        dayData[day].convert.forEach(emp => {
                            if (!uniqueEmployees[emp.code]) {
                                uniqueEmployees[emp.code] = {
                                    name: emp.name,
                                    code: emp.code,
                                    days: {}
                                };
                            }
                            uniqueEmployees[emp.code].days[day] = 'Convert';
                        });
                    }
                }

                // Apply filters
                let filteredEmployees = Object.values(uniqueEmployees);

                <?php if (!empty($selectedTeam)): ?>
                    filteredEmployees = filteredEmployees.filter(emp => {
                        // We need to find at least one day where the employee has the selected team
                        for (const day in emp.days) {
                            const dayInfo = dayData[day].shifts[emp.days[day]]?.find(e => e.code === emp.code) ||
                                dayData[day].rdo.find(e => e.code === emp.code) ||
                                dayData[day].leave.find(e => e.code === emp.code) ||
                                dayData[day].convert.find(e => e.code === emp.code);
                            if (dayInfo && dayInfo.team === '<?php echo $selectedTeam; ?>') {
                                return true;
                            }
                        }
                        return false;
                    });
                <?php endif; ?>

                <?php if (!empty($selectedRosterType)): ?>
                    filteredEmployees = filteredEmployees.filter(emp => {
                        // Check if any day matches the roster type
                        for (const day in emp.days) {
                            if (emp.days[day] === '<?php echo $selectedRosterType; ?>') {
                                return true;
                            }
                        }
                        return false;
                    });
                <?php endif; ?>

                <?php if (!empty($selectedSalesManager)): ?>
                    filteredEmployees = filteredEmployees.filter(emp => {
                        // We need to find at least one day where the employee has the selected SM
                        for (const day in emp.days) {
                            const dayInfo = dayData[day].shifts[emp.days[day]]?.find(e => e.code === emp.code) ||
                                dayData[day].rdo.find(e => e.code === emp.code) ||
                                dayData[day].leave.find(e => e.code === emp.code) ||
                                dayData[day].convert.find(e => e.code === emp.code);
                            if (dayInfo && dayInfo.sm === '<?php echo $selectedSalesManager; ?>') {
                                return true;
                            }
                        }
                        return false;
                    });
                <?php endif; ?>

                <?php if (!empty($selectedShiftTime)): ?>
                    filteredEmployees = filteredEmployees.filter(emp => {
                        // We need to find at least one day where the employee has the selected shift time
                        for (const day in emp.days) {
                            const dayInfo = dayData[day].shifts[emp.days[day]]?.find(e => e.code === emp.code) ||
                                dayData[day].rdo.find(e => e.code === emp.code) ||
                                dayData[day].leave.find(e => e.code === emp.code) ||
                                dayData[day].convert.find(e => e.code === emp.code);
                            if (dayInfo && dayInfo.shift_time === '<?php echo $selectedShiftTime; ?>') {
                                return true;
                            }
                        }
                        return false;
                    });
                <?php endif; ?>

                // Build table rows
                const tbody = document.querySelector('#weekly-table tbody');
                tbody.innerHTML = '';

                if (filteredEmployees.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="' + (endDay - startDay + 2) + '" class="text-center">No employees found for this date range</td></tr>';
                } else {
                    filteredEmployees.forEach(emp => {
                        let row = `<td>${emp.name} </td>`;

                        for (let day = parseInt(startDay); day <= parseInt(endDay); day++) {
                            const status = emp.days[day] || '-';
                            let cellClass = '';
                            let textClass = '';
                            if (status === 'RDO') {
                                textClass = 'text-green';
                            } else if (status === 'Leave') {
                                textClass = 'text-orange';
                            } else if (status === 'Convert') {
                                textClass = 'text-yellow';
                            } else if (status !== '-') {
                                cellClass = '';
                                textClass = '';
                            }
                            row += `<td class="${textClass}">${status}</td>`;
                        }
                        tbody.innerHTML += `<tr>${row}</tr>`;
                    });
                }
            }
        </script>
        <script>
document.addEventListener('DOMContentLoaded', function() {
    // Always use the same Modal instance
    const modalEl = document.getElementById('dayDetailsModal');
    const modal = new bootstrap.Modal(modalEl);

    // Modal title element
    const modalTitle = document.getElementById('modal-day-title');
    const tableBody = document.getElementById('employee-details');
    const eventListBody = document.getElementById('event-list-details');

    // Remove modal-backdrop and modal-open if somehow left over
    modalEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('modal-open');
        let backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.parentNode.removeChild(backdrop);
    });

    // Only attach click handlers once
    document.querySelectorAll('.agent-name').forEach(function(agentDiv) {
        agentDiv.addEventListener('click', function(e) {
            e.stopPropagation();

            const agentCode = this.getAttribute('data-agent-code');
            const day = this.getAttribute('data-day');
            const data = dayData[day];

            let agent = null, statusDisplay = '';
            for (const shift in data.shifts) {
                agent = data.shifts[shift].find(emp => emp.code === agentCode);
                if (agent) { statusDisplay = shift; break; }
            }
            if (!agent) { agent = data.rdo.find(emp => emp.code === agentCode); statusDisplay = 'RDO'; }
            if (!agent) { agent = data.leave.find(emp => emp.code === agentCode); statusDisplay = 'Leave'; }
            if (!agent) { agent = data.convert.find(emp => emp.code === agentCode); statusDisplay = 'Convert'; }
            if (!agent) { agent = data.ulwp.find(emp => emp.code === agentCode); statusDisplay = 'ULWP'; }

            if (!agent) return;

            // Modal title
            const monthName = "<?php echo $displayMonthName; ?>";
            const yearShow = <?php echo $yearToShow; ?>;
            modalTitle.textContent = `${monthName} ${day}, ${yearShow}   ${agent.name}`;

            // Attendance info
            let att = agent.attendance || {};
            let login_date = att.Punch1_Date || '';
            let login_time = att.COL35 || '';
            let logout_time = att.COL126 || '';
            let logout_date = att.COL125 || '';

            let statusClass = '';
            if (statusDisplay === 'RDO') statusClass = 'text-success';
            else if (statusDisplay === 'Leave') statusClass = 'text-orange';
            else if (statusDisplay === 'Convert') statusClass = 'text-yellow';
            else if (statusDisplay === 'ULWP') statusClass = 'text-danger';
            else statusClass = 'text-primary';

            // Fill summary row
            tableBody.innerHTML = '';
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${agent.name}</td>
                <td>${agent.team || '-'}</td>
                <td>${agent.sm || '-'}</td>
                <td class="${statusClass}">${statusDisplay}</td>
                <td>${login_date}</td>
                <td>${login_time}</td>
                <td>${logout_time}</td>
                <td>${logout_date}</td>
            `;
            tableBody.appendChild(row);

            // Fill punch events
            eventListBody.innerHTML = '';
            if (agent.all_events && agent.all_events.length > 0) {
                agent.all_events.forEach(ev => {
                    let punchType = '';
                    if (ev.EntryExitType === "0" || ev.EntryExitType === 0) punchType = 'IN';
                    else if (ev.EntryExitType === "1" || ev.EntryExitType === 1) punchType = 'OUT';
                    else punchType = ev.EntryExitType;
                    const evRow = document.createElement('tr');
                    evRow.innerHTML = `<td>${punchType}</td><td>${ev.Etime}</td>`;
                    eventListBody.appendChild(evRow);
                });
            } else {
                const evRow = document.createElement('tr');
                evRow.innerHTML = `<td colspan="2" class="text-center">No punch events for this day.</td>`;
                eventListBody.appendChild(evRow);
            }

            // Show the modal
            modal.show();
        });
    });
});
</script>
<script>
// This will always allow scroll after ANY modal is closed
document.addEventListener('hidden.bs.modal', function (event) {
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    let backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(function (b) { b.parentNode.removeChild(b); });
});
</script>



    </div>
</body>

</html>

<?php
get_footer();
?>