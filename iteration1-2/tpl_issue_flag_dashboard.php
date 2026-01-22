<?php
/**
 * Template Name: Issue Flag Dashboard
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();
header('Content-Type: text/html; charset=utf-8');
?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:98%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
global $current_user; 
wp_get_current_user();
include('wp-config-custom.php');
$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
if(mysqli_num_rows($result_ip_selection) > 0 )
{
    $currnt_userlogn = $current_user->user_login;
    ?>
	<style>
		.issue-dashboard {
			font-family: Arial, sans-serif;
			margin: 20px;
		}
		.filters {
			background: #f5f5f5;
			padding: 20px;
			margin-bottom: 20px;
			border-radius: 5px;
		}
		.filters select, .filters button {
			padding: 10px;
			margin: 5px;
			border-radius: 3px;
			border: 1px solid #ddd;
		}
		.filters button {
			background: #0073aa;
			color: white;
			cursor: pointer;
			font-weight: bold;
		}
		.filters button:hover {
			background: #005177;
		}
		.email-btn {
			background: #d63638;
			margin-left: 10px;
		}
		.email-btn:hover {
			background: #a82a2c;
		}
		.stats {
			display: flex;
			gap: 20px;
			margin-bottom: 20px;
		}
		.stat-card {
			flex: 1;
			background: white;
			padding: 20px;
			border-radius: 5px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.stat-card h3 {
			margin: 0 0 10px 0;
			color: #555;
			font-size: 14px;
		}
		.stat-card .number {
			font-size: 32px;
			font-weight: bold;
			color: #0073aa;
		}
		.issue-table {
			width: 100%;
			border-collapse: collapse;
			background: white;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.issue-table th {
			background: #0073aa;
			color: white;
			padding: 12px;
			text-align: left;
			font-weight: bold;
		}
		.issue-table td {
			padding: 10px 12px;
			border-bottom: 1px solid #ddd;
		}
		.issue-table tr:hover {
			background: #f9f9f9;
		}
		.category-badge {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: bold;
		}
		.cat-ticketing { background: #fef3cd; color: #856404; }
		.cat-name-update { background: #cfe2ff; color: #084298; }
		.cat-pnr-validation { background: #f8d7da; color: #842029; }
		.cat-pax-count-validation { background: #d1e7dd; color: #0f5132; }
		.cat-payment { background: #e2d9f3; color: #3d0066; }
		.cat-gds-ticketing { background: #fff3cd; color: #664d03; }
		.cat-payment-status { background: #ffebe6; color: #cc2200; }
		.cat-duplicate-order { background: #ffe5d0; color: #cc4400; }
		.cat-booking-notes { background: #e0f7fa; color: #006064; }
		.cat-active-issue-log { background: #fce4ec; color: #880e4f; }
		.priority-d1 { background: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; }
		.priority-d7 { background: #ffc107; color: #000; padding: 4px 8px; border-radius: 3px; font-size: 11px; }
		.priority-d10 { background: #17a2b8; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; }
		.priority-other { background: #6c757d; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; }
		.no-issues {
			text-align: center;
			padding: 40px;
			color: #666;
			background: white;
			border-radius: 5px;
		}
	</style>

	<div class="issue-dashboard">
		<h1>Travel Booking Issue Flag Dashboard</h1>
		
		<div class="filters">
			<form method="GET">
				<label>Order ID:</label>
				<input type="text" name="order_id" placeholder="Enter Order ID" value="<?php echo isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : ''; ?>" style="padding:10px; margin:5px; border-radius:3px; border:1px solid #ddd; width:150px;" />
				
				<label>Order Type:</label>
				<select name="order_type">
					<option value="">All Types</option>
					<option value="WPT" <?php echo (isset($_GET['order_type']) && $_GET['order_type'] == 'WPT') ? 'selected' : ''; ?>>WPT</option>
					<option value="WT" <?php echo (isset($_GET['order_type']) && $_GET['order_type'] == 'WT') ? 'selected' : ''; ?>>WT</option>
					<option value="ST" <?php echo (isset($_GET['order_type']) && $_GET['order_type'] == 'ST') ? 'selected' : ''; ?>>ST</option>
					<option value="FIT" <?php echo (isset($_GET['order_type']) && $_GET['order_type'] == 'FIT') ? 'selected' : ''; ?>>FIT</option>
					<option value="gds" <?php echo (isset($_GET['order_type']) && $_GET['order_type'] == 'gds') ? 'selected' : ''; ?>>GDS</option>
				</select>
				
				<label>Category:</label>
				<select name="category">
					<option value="">All Categories</option>
					<option value="Ticketing" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Ticketing') ? 'selected' : ''; ?>>Ticketing</option>
					<option value="Name Update" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Name Update') ? 'selected' : ''; ?>>Name Update</option>
					<option value="PNR Validation" <?php echo (isset($_GET['category']) && $_GET['category'] == 'PNR Validation') ? 'selected' : ''; ?>>PNR Validation</option>
					<option value="Pax Count Validation" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Pax Count Validation') ? 'selected' : ''; ?>>Pax Count Validation</option>
					<option value="Payment" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Payment') ? 'selected' : ''; ?>>Payment</option>
					<option value="GDS Ticketing" <?php echo (isset($_GET['category']) && $_GET['category'] == 'GDS Ticketing') ? 'selected' : ''; ?>>GDS Ticketing</option>
					<option value="Payment Status" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Payment Status') ? 'selected' : ''; ?>>Payment Status</option>
					<option value="Duplicate Order" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Duplicate Order') ? 'selected' : ''; ?>>Duplicate Order</option>
					<option value="Booking Notes" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Booking Notes') ? 'selected' : ''; ?>>Booking Notes</option>
					<option value="Active Issue Log" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Active Issue Log') ? 'selected' : ''; ?>>Active Issue Log</option>
				</select>
				
				<label>Priority:</label>
				<select name="priority">
					<option value="">All Priorities</option>
					<option value="D-1" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'D-1') ? 'selected' : ''; ?>>D-1 (Tomorrow)</option>
					<option value="D-7" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'D-7') ? 'selected' : ''; ?>>D-7 (Within 7 days)</option>
					<option value="D-10" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'D-10') ? 'selected' : ''; ?>>D-10 (Within 10 days)</option>
					<option value="Other" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'Other') ? 'selected' : ''; ?>>Other</option>
				</select>
				
				<button type="submit">Apply Filters</button>
				<button type="button" class="email-btn" onclick="sendEmailReport()">Send Email Report</button>
			</form>
		</div>

		<?php
		// Initialize issues array
		$all_issues = array();
		$query_errors = array();

		// Helper function to calculate priority based on travel date
		function getPriority($travel_date) {
			if(empty($travel_date)) return 'Other';
			$today = date('Y-m-d');
			$days_diff = (strtotime($travel_date) - strtotime($today)) / (60 * 60 * 24);
			
			if ($days_diff <= 1) {
				return 'D-1';
			} elseif ($days_diff <= 7) {
				return 'D-7';
			} elseif ($days_diff <= 10) {
				return 'D-10';
			} else {
				return 'Other';
			}
		}

		// Helper function to execute query with error handling
		function executeQuery($mysqli, $query, $query_name, &$query_errors) {
			$result = mysqli_query($mysqli, $query);
			if(!$result) {
				$query_errors[] = array(
					'query' => $query_name,
					'error' => mysqli_error($mysqli)
				);
				return false;
			}
			return $result;
		}

		// 1. TICKETING ISSUES
		
		// 1.1 - WPT order type with date gap > 10 days
		$query_ticketing_1 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.late_modified,
				pax.ticketed_on,
				pax.fname,
				pax.lname,
				ism.date as issue_date
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			LEFT JOIN wpk4_ticketing_screen_issue_message ism ON 1=1
			WHERE tb.order_type = 'WPT'
			AND tb.travel_date > CURDATE()
			AND DATEDIFF(
				COALESCE(pax.late_modified, pax.ticketed_on),
				DATE_ADD(CURDATE(), INTERVAL COALESCE(ism.date, 0) DAY)
			) > 10
			LIMIT 100
		";
		$result_ticketing_1 = executeQuery($mysqli, $query_ticketing_1, 'Ticketing Query 1', $query_errors);
		if($result_ticketing_1) {
			while($row = mysqli_fetch_assoc($result_ticketing_1)) {
				$date_modified = $row['late_modified'] ? $row['late_modified'] : $row['ticketed_on'];
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Ticketing',
					'issue_description' => '[' . $pax_name . '] Date gap exceeds 10 days between travel date and ticketing date (Modified: ' . $date_modified . ')',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 1.2 - Ticket number empty but ticketed_on or ticketed_by not empty
		$query_ticketing_2 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.ticket_number,
				pax.ticketed_on,
				pax.ticketed_by,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
			AND (pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
			LIMIT 100
		";
		$result_ticketing_2 = executeQuery($mysqli, $query_ticketing_2, 'Ticketing Query 2', $query_errors);
		if($result_ticketing_2) {
			while($row = mysqli_fetch_assoc($result_ticketing_2)) {
				$issue_fields = array();
				if($row['ticketed_on']) $issue_fields[] = 'ticketed_on: ' . $row['ticketed_on'];
				if($row['ticketed_by']) $issue_fields[] = 'ticketed_by: ' . $row['ticketed_by'];
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Ticketing',
					'issue_description' => '[' . $pax_name . '] ticket_number is empty but has values in: ' . implode(', ', $issue_fields),
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 1.3 - Ticket number not empty but ticketed_on or ticketed_by empty
		$query_ticketing_3 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.ticket_number,
				pax.ticketed_on,
				pax.ticketed_by,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND pax.ticket_number IS NOT NULL 
			AND pax.ticket_number != ''
			AND (pax.ticketed_on IS NULL OR pax.ticketed_by IS NULL)
			LIMIT 100
		";
		$result_ticketing_3 = executeQuery($mysqli, $query_ticketing_3, 'Ticketing Query 3', $query_errors);
		if($result_ticketing_3) {
			while($row = mysqli_fetch_assoc($result_ticketing_3)) {
				$missing_fields = array();
				if(!$row['ticketed_on']) $missing_fields[] = 'ticketed_on';
				if(!$row['ticketed_by']) $missing_fields[] = 'ticketed_by';
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Ticketing',
					'issue_description' => '[' . $pax_name . '] ticket_number exists (' . $row['ticket_number'] . ') but missing: ' . implode(', ', $missing_fields),
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 1.4 - Pax status not 'Ticketed' but has ticketing info
		$query_ticketing_4 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.pax_status,
				pax.ticket_number,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND pax.pax_status != 'Ticketed'
			AND (pax.ticket_number IS NOT NULL OR pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
			LIMIT 100
		";
		$result_ticketing_4 = executeQuery($mysqli, $query_ticketing_4, 'Ticketing Query 4', $query_errors);
		if($result_ticketing_4) {
			while($row = mysqli_fetch_assoc($result_ticketing_4)) {
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Ticketing',
					'issue_description' => '[' . $pax_name . '] pax_status is "' . $row['pax_status'] . '" but should be "Ticketed" (has ticket_number: ' . $row['ticket_number'] . ')',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 1.5 - Name updated field empty but has ticketing info
		$query_ticketing_5 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.name_updated,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND (pax.name_updated IS NULL OR pax.name_updated = '')
			AND (pax.ticket_number IS NOT NULL OR pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
			LIMIT 100
		";
		$result_ticketing_5 = executeQuery($mysqli, $query_ticketing_5, 'Ticketing Query 5', $query_errors);
		if($result_ticketing_5) {
			while($row = mysqli_fetch_assoc($result_ticketing_5)) {
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Ticketing',
					'issue_description' => '[' . $pax_name . '] name_updated field is empty but has ticketing data present',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 2. NAME UPDATE ISSUES
		$query_name_update = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.payment_status,
				pax.name_update_check,
				pax.name_update_check_on,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.order_type = 'WPT'
			AND tb.travel_date > CURDATE()
			AND tb.payment_status = 'paid'
			AND tb.trip_code NOT LIKE '%QF%'
			AND (
				pax.name_update_check IS NULL 
				OR pax.name_update_check = '' 
				OR pax.name_update_check_on IS NULL
			)
			LIMIT 100
		";
		$result_name_update = executeQuery($mysqli, $query_name_update, 'Name Update Query', $query_errors);
		if($result_name_update) {
			while($row = mysqli_fetch_assoc($result_name_update)) {
				$missing_fields = array();
				if(!$row['name_update_check']) $missing_fields[] = 'name_update_check';
				if(!$row['name_update_check_on']) $missing_fields[] = 'name_update_check_on';
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Name Update',
					'issue_description' => '[' . $pax_name . '] WPT order with payment_status="' . $row['payment_status'] . '" but missing: ' . implode(', ', $missing_fields),
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 3. PNR VALIDATION ISSUES
		$query_pnr = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.pnr as pax_pnr,
				(SELECT GROUP_CONCAT(DISTINCT pnr SEPARATOR ', ') 
				 FROM wpk4_backend_stock_management_sheet 
				 WHERE trip_id = tb.trip_code AND dep_date = tb.travel_date) as stock_pnrs
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND (
				tb.payment_status = 'paid' 
				OR (tb.payment_status = 'partially_paid' AND tb.order_type = 'WPT')
			)
			AND pax.pnr IS NOT NULL
			AND pax.pnr != ''
			AND NOT EXISTS (
				SELECT 1 
				FROM wpk4_backend_stock_management_sheet sms
				WHERE sms.trip_id = tb.trip_code 
				AND sms.dep_date = tb.travel_date
				AND sms.pnr = pax.pnr
			)
			AND EXISTS (
				SELECT 1 
				FROM wpk4_backend_stock_management_sheet sms2
				WHERE sms2.trip_id = tb.trip_code 
				AND sms2.dep_date = tb.travel_date
				AND sms2.pnr IS NOT NULL
				AND sms2.pnr != ''
			)
			LIMIT 100
		";
		$result_pnr = executeQuery($mysqli, $query_pnr, 'PNR Validation Query', $query_errors);
		if($result_pnr) {
			while($row = mysqli_fetch_assoc($result_pnr)) {
				$stock_pnrs_display = $row['stock_pnrs'] ? $row['stock_pnrs'] : 'None found';
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'PNR Validation',
					'issue_description' => 'PNR mismatch - Booking PNR: "' . $row['pax_pnr'] . '" not found in Stock Management PNRs: [' . $stock_pnrs_display . ']',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 4. PAX COUNT VALIDATION ISSUES
		$query_pax_count = "
			SELECT 
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.total_pax,
				COUNT(
					CASE 
						WHEN tb.order_type = 'FIT' AND pax.DOB IS NOT NULL 
							AND TIMESTAMPDIFF(YEAR, pax.DOB, CURDATE()) < 2 
						THEN NULL
						ELSE pax.auto_id 
					END
				) as actual_pax_count
			FROM wpk4_backend_travel_bookings tb
			LEFT JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND tb.total_pax > 0
			GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.total_pax
			HAVING tb.total_pax != COUNT(
				CASE 
					WHEN tb.order_type = 'FIT' AND pax.DOB IS NOT NULL 
						AND TIMESTAMPDIFF(YEAR, pax.DOB, CURDATE()) < 2 
					THEN NULL
					ELSE pax.auto_id 
				END
			)
			LIMIT 100
		";
		$result_pax_count = executeQuery($mysqli, $query_pax_count, 'Pax Count Validation Query', $query_errors);
		if($result_pax_count) {
			while($row = mysqli_fetch_assoc($result_pax_count)) {
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Pax Count Validation',
					'issue_description' => 'total_pax field shows ' . $row['total_pax'] . ' but actual pax records count is ' . $row['actual_pax_count'],
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 5. PAYMENT ISSUES
		
		// 5.1 - Payment exists but ticket number missing
		$query_payment_1 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				pax.ticket_number,
				ph.auto_id as payment_record_id
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_payment_history ph ON tb.order_id = ph.order_id
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND tb.payment_status = 'paid'
			AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
			LIMIT 100
		";
		$result_payment_1 = executeQuery($mysqli, $query_payment_1, 'Payment Query 1', $query_errors);
		if($result_payment_1) {
			while($row = mysqli_fetch_assoc($result_payment_1)) {
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Payment',
					'issue_description' => 'Payment record exists (ID: ' . $row['payment_record_id'] . ') but ticket_number field is empty',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}
		
		// 5.2 - Total amount mismatch with sum of payments received
		$query_payment_2 = "
			SELECT 
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.total_amount,
				SUM(ph.trams_received_amount) as total_paid
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_payment_history ph ON tb.order_id = ph.order_id
			WHERE tb.travel_date > CURDATE()
			AND tb.payment_status = 'paid'
			GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.total_amount
			HAVING ROUND(tb.total_amount, 2) != ROUND(SUM(ph.trams_received_amount), 2)
			LIMIT 100
		";
		$result_payment_2 = executeQuery($mysqli, $query_payment_2, 'Payment Query 2', $query_errors);
		if($result_payment_2) {
			while($row = mysqli_fetch_assoc($result_payment_2)) {
				$difference = round($row['total_amount'], 2) - round($row['total_paid'], 2);
				// Only add if difference is actually significant (not just floating point error)
				if(abs($difference) >= 0.01) {
					$status = $difference > 0 ? 'Underpaid' : 'Overpaid';
					$all_issues[] = array(
						'order_id' => $row['order_id'],
						'order_type' => $row['order_type'],
						'category' => 'Payment',
						'issue_description' => 'total_amount (' . number_format($row['total_amount'], 2) . ') != sum of payments (' . number_format($row['total_paid'], 2) . ') - ' . $status . ' by ' . number_format(abs($difference), 2),
						'travel_date' => $row['travel_date'],
						'priority' => getPriority($row['travel_date']),
						'details' => 'Trip: ' . $row['trip_code']
					);
				}
			}
		}

		// 6. GDS TICKETING ISSUES
		
		// 6.1 - GDS paid orders older than 24 hours without ticket number
		$query_gds_1 = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.order_date,
				pax.ticket_number,
				pax.fname,
				pax.lname
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_pax pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
			WHERE tb.travel_date > CURDATE()
			AND tb.order_type = 'gds'
			AND tb.payment_status = 'paid'
			AND tb.order_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
			AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
			LIMIT 100
		";
		$result_gds_1 = executeQuery($mysqli, $query_gds_1, 'GDS Query 1', $query_errors);
		if($result_gds_1) {
			while($row = mysqli_fetch_assoc($result_gds_1)) {
				$hours_since_order = round((strtotime(date('Y-m-d H:i:s')) - strtotime($row['order_date'])) / 3600);
				$pax_name = trim($row['fname'] . ' ' . $row['lname']);
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'GDS Ticketing',
					'issue_description' => '[' . $pax_name . '] GDS order paid but ticket_number empty (Order placed ' . $hours_since_order . ' hours ago on ' . $row['order_date'] . ')',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 7. PAYMENT STATUS MISMATCH
		
		// 7.1 - Payment received but status not paid/refund
		$query_payment_status = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.payment_status,
				SUM(ph.trams_received_amount) as total_received
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_payment_history ph ON tb.order_id = ph.order_id
			WHERE tb.travel_date > CURDATE()
			AND tb.payment_status NOT IN ('paid', 'refund')
			GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.payment_status
			HAVING SUM(ph.trams_received_amount) > 0
			LIMIT 100
		";
		$result_payment_status = executeQuery($mysqli, $query_payment_status, 'Payment Status Query', $query_errors);
		if($result_payment_status) {
			while($row = mysqli_fetch_assoc($result_payment_status)) {
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Payment Status',
					'issue_description' => 'payment_status is "' . $row['payment_status'] . '" but received payment amount: ' . number_format($row['total_received'], 2),
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 8. DUPLICATE ORDER VALIDATION
		
		// 8.1 - Duplicate GDS orders (2 or more records)
		$query_duplicate_gds = "
			SELECT 
				order_id,
				order_type,
				MIN(travel_date) as travel_date,
				MIN(trip_code) as trip_code,
				COUNT(*) as record_count
			FROM wpk4_backend_travel_bookings
			WHERE travel_date > CURDATE()
			AND order_type = 'gds'
			GROUP BY order_id, order_type
			HAVING COUNT(*) >= 2
			LIMIT 100
		";
		$result_duplicate_gds = executeQuery($mysqli, $query_duplicate_gds, 'Duplicate GDS Query', $query_errors);
		if($result_duplicate_gds) {
			while($row = mysqli_fetch_assoc($result_duplicate_gds)) {
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Duplicate Order',
					'issue_description' => 'GDS order has ' . $row['record_count'] . ' duplicate records in bookings table',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 8.2 - Duplicate WPT orders (more than 2 records)
		$query_duplicate_wpt = "
			SELECT 
				order_id,
				order_type,
				MIN(travel_date) as travel_date,
				MIN(trip_code) as trip_code,
				COUNT(*) as record_count
			FROM wpk4_backend_travel_bookings
			WHERE travel_date > CURDATE()
			AND order_type = 'WPT'
			GROUP BY order_id, order_type
			HAVING COUNT(*) > 2
			LIMIT 100
		";
		$result_duplicate_wpt = executeQuery($mysqli, $query_duplicate_wpt, 'Duplicate WPT Query', $query_errors);
		if($result_duplicate_wpt) {
			while($row = mysqli_fetch_assoc($result_duplicate_wpt)) {
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Duplicate Order',
					'issue_description' => 'WPT order has ' . $row['record_count'] . ' records (exceeds limit of 2) in bookings table',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code']
				);
			}
		}

		// 9. BOOKING NOTES - WITHIN 7 DAYS
		
		// 9.1 - Bookings with notes in next 7 days
		$query_booking_notes = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				hou.meta_value as note_content,
				hou.updated_on as note_date
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_history_of_updates hou ON tb.order_id = hou.type_id
			WHERE tb.travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
			AND hou.meta_key = 'Booking Note Category'
			LIMIT 100
		";
		$result_booking_notes = executeQuery($mysqli, $query_booking_notes, 'Booking Notes Query', $query_errors);
		if($result_booking_notes) {
			while($row = mysqli_fetch_assoc($result_booking_notes)) {
				$days_until_travel = round((strtotime($row['travel_date']) - strtotime(date('Y-m-d'))) / 86400);
				$note_date = $row['note_date'] ? $row['note_date'] : 'N/A';
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Booking Notes',
					'issue_description' => 'Booking has notes (Travel in ' . $days_until_travel . ' days) - Note: ' . substr($row['note_content'], 0, 100) . (strlen($row['note_content']) > 100 ? '...' : ''),
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code'] . ', Note Date: ' . $note_date
				);
			}
		}

		// 10. ACTIVE ISSUE LOGS
		
		// 10.1 - Paid bookings with active issue logs
		$query_active_issues = "
			SELECT DISTINCT
				tb.order_id,
				tb.order_type,
				tb.travel_date,
				tb.trip_code,
				tb.payment_status,
				til.auto_id as issue_log_id,
				til.added_on as issue_created
			FROM wpk4_backend_travel_bookings tb
			INNER JOIN wpk4_backend_travel_booking_issue_log til ON tb.order_id = til.order_id
			WHERE tb.travel_date > CURDATE()
			AND tb.payment_status = 'paid'
			AND til.status = 'active'
			LIMIT 100
		";
		$result_active_issues = executeQuery($mysqli, $query_active_issues, 'Active Issue Logs Query', $query_errors);
		if($result_active_issues) {
			while($row = mysqli_fetch_assoc($result_active_issues)) {
				$issue_created = $row['issue_created'] ? $row['issue_created'] : 'N/A';
				$all_issues[] = array(
					'order_id' => $row['order_id'],
					'order_type' => $row['order_type'],
					'category' => 'Active Issue Log',
					'issue_description' => 'Paid booking has active issue log (Log ID: ' . $row['issue_log_id'] . ')',
					'travel_date' => $row['travel_date'],
					'priority' => getPriority($row['travel_date']),
					'details' => 'Trip: ' . $row['trip_code'] . ', Issue Created: ' . $issue_created
				);
			}
		}

		// Display query errors if any
		if(!empty($query_errors)) {
			echo '<div style="background:#f8d7da;color:#842029;padding:15px;margin:20px 0;border-radius:5px;">';
			echo '<strong>⚠ Database Query Errors:</strong><br><br>';
			foreach($query_errors as $error) {
				echo '<strong>' . htmlspecialchars($error['query']) . ':</strong> ' . htmlspecialchars($error['error']) . '<br>';
			}
			echo '</div>';
		}

		// Apply filters
		$filtered_issues = $all_issues;
		
		if(isset($_GET['order_id']) && $_GET['order_id'] != '') {
			$search_order_id = $_GET['order_id'];
			$filtered_issues = array_filter($filtered_issues, function($issue) use ($search_order_id) {
				return stripos($issue['order_id'], $search_order_id) !== false;
			});
		}
		
		if(isset($_GET['order_type']) && $_GET['order_type'] != '') {
			$filtered_issues = array_filter($filtered_issues, function($issue) {
				return $issue['order_type'] == $_GET['order_type'];
			});
		}
		
		if(isset($_GET['category']) && $_GET['category'] != '') {
			$filtered_issues = array_filter($filtered_issues, function($issue) {
				return $issue['category'] == $_GET['category'];
			});
		}
		
		if(isset($_GET['priority']) && $_GET['priority'] != '') {
			$filtered_issues = array_filter($filtered_issues, function($issue) {
				return $issue['priority'] == $_GET['priority'];
			});
		}

		// Calculate statistics
		$total_issues = count($filtered_issues);
		$d1_count = count(array_filter($filtered_issues, function($i) { return $i['priority'] == 'D-1'; }));
		$d7_count = count(array_filter($filtered_issues, function($i) { return $i['priority'] == 'D-7'; }));
		$d10_count = count(array_filter($filtered_issues, function($i) { return $i['priority'] == 'D-10'; }));
		?>

		<div class="stats">
			<div class="stat-card">
				<h3>Total Issues</h3>
				<div class="number"><?php echo $total_issues; ?></div>
			</div>
			<div class="stat-card">
				<h3>D-1 Priority</h3>
				<div class="number" style="color: #dc3545;"><?php echo $d1_count; ?></div>
			</div>
			<div class="stat-card">
				<h3>D-7 Priority</h3>
				<div class="number" style="color: #ffc107;"><?php echo $d7_count; ?></div>
			</div>
			<div class="stat-card">
				<h3>D-10 Priority</h3>
				<div class="number" style="color: #17a2b8;"><?php echo $d10_count; ?></div>
			</div>
		</div>

		<?php if($total_issues > 0): ?>
			<table class="issue-table">
				<thead>
					<tr>
						<th>Order ID</th>
						<th>Order Type</th>
						<th>Category</th>
						<th>Issue Description</th>
						<th>Travel Date</th>
						<th>Priority</th>
						<th>Details</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($filtered_issues as $issue): ?>
						<tr>
							<td><strong><?php echo htmlspecialchars($issue['order_id']); ?></strong></td>
							<td><strong><?php echo htmlspecialchars($issue['order_type']); ?></strong></td>
							<td>
								<span class="category-badge cat-<?php echo strtolower(str_replace(' ', '-', $issue['category'])); ?>">
									<?php echo htmlspecialchars($issue['category']); ?>
								</span>
							</td>
							<td><?php echo htmlspecialchars($issue['issue_description']); ?></td>
							<td><?php echo htmlspecialchars($issue['travel_date']); ?></td>
							<td>
								<span class="priority-<?php echo strtolower($issue['priority']); ?>">
									<?php echo htmlspecialchars($issue['priority']); ?>
								</span>
							</td>
							<td><?php echo htmlspecialchars($issue['details']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<div class="no-issues">
				<h2>✓ No Issues Found</h2>
				<p>All bookings are in order according to the validation criteria.</p>
			</div>
		<?php endif; ?>

		<script>
		function sendEmailReport() {
			if(confirm('Send email report to administrators?')) {
				window.location.href = '?send_email=1<?php echo isset($_GET['category']) ? "&category=".$_GET['category'] : ""; ?><?php echo isset($_GET['priority']) ? "&priority=".$_GET['priority'] : ""; ?>';
			}
		}
		</script>

		<?php
		// Email functionality
		if(isset($_GET['send_email']) && $_GET['send_email'] == '1') {
			// Check if PHPMailer is available
			if(file_exists(ABSPATH . 'wp-includes/PHPMailer/PHPMailer.php')) {
				require_once(ABSPATH . 'wp-includes/PHPMailer/PHPMailer.php');
				require_once(ABSPATH . 'wp-includes/PHPMailer/SMTP.php');
				require_once(ABSPATH . 'wp-includes/PHPMailer/Exception.php');
				
				$mail = new PHPMailer\PHPMailer\PHPMailer(true);
				
				try {
					// Email configuration
					$mail->isSMTP();
					$mail->Host = get_option('mailserver_url', 'smtp.gmail.com');
					$mail->SMTPAuth = true;
					$mail->Username = get_option('mailserver_login', '');
					$mail->Password = get_option('mailserver_pass', '');
					$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
					$mail->Port = get_option('mailserver_port', 587);
					
					$mail->setFrom(get_option('admin_email'), 'Issue Flag Dashboard');
					$mail->addAddress(get_option('admin_email'));
					
					$mail->isHTML(true);
					$mail->Subject = 'Travel Booking Issues Report - ' . date('Y-m-d H:i:s');
					
					// Build email body
					$email_body = '<h2>Travel Booking Issues Report</h2>';
					$email_body .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
					$email_body .= '<h3>Summary Statistics</h3>';
					$email_body .= '<ul>';
					$email_body .= '<li><strong>Total Issues:</strong> ' . $total_issues . '</li>';
					$email_body .= '<li><strong>D-1 Priority:</strong> ' . $d1_count . '</li>';
					$email_body .= '<li><strong>D-7 Priority:</strong> ' . $d7_count . '</li>';
					$email_body .= '<li><strong>D-10 Priority:</strong> ' . $d10_count . '</li>';
					$email_body .= '</ul>';
					
					if($total_issues > 0) {
						$email_body .= '<h3>Issue Details</h3>';
						$email_body .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
						$email_body .= '<tr style="background:#0073aa;color:white;">';
						$email_body .= '<th>Order ID</th><th>Order Type</th><th>Category</th><th>Issue</th><th>Travel Date</th><th>Priority</th><th>Details</th>';
						$email_body .= '</tr>';
						
						foreach($filtered_issues as $issue) {
							$email_body .= '<tr>';
							$email_body .= '<td>' . htmlspecialchars($issue['order_id']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['order_type']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['category']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['issue_description']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['travel_date']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['priority']) . '</td>';
							$email_body .= '<td>' . htmlspecialchars($issue['details']) . '</td>';
							$email_body .= '</tr>';
						}
						
						$email_body .= '</table>';
					}
					
					$mail->Body = $email_body;
					$mail->send();
					
					echo '<div style="background:#d1e7dd;color:#0f5132;padding:15px;margin:20px 0;border-radius:5px;"><strong>✓ Email sent successfully!</strong></div>';
				} catch (PHPMailer\PHPMailer\Exception $e) {
					echo '<div style="background:#f8d7da;color:#842029;padding:15px;margin:20px 0;border-radius:5px;"><strong>✗ Email could not be sent.</strong> Error: ' . $mail->ErrorInfo . '</div>';
				}
			} else {
				echo '<div style="background:#fef3cd;color:#856404;padding:15px;margin:20px 0;border-radius:5px;"><strong>⚠ PHPMailer not found.</strong> Please install PHPMailer or use WordPress wp_mail() function.</div>';
			}
		}
		?>
	</div>
	<?php
}
else
{
    echo "<center>This page is not accessible for you.</center>";
}
?>
</div>
<?php get_footer(); ?>