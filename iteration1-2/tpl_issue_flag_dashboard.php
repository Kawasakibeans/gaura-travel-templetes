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
		// Fetch data from Issue Flag Dashboard API
		$all_issues = array();
		$filtered_issues = array();
		$query_errors = array();
		$api_error = '';
		$stats = array('total' => 0, 'd1' => 0, 'd7' => 0, 'd10' => 0);

		if(!function_exists('issue_flag_api_request')) {
			function issue_flag_api_request($endpoint, $method = 'GET', $params = array()) {
				if (!defined('API_BASE_URL')) {
					return new WP_Error('api_base_url_missing', 'API_BASE_URL is not defined');
				}

				$url = rtrim(API_BASE_URL, '/') . $endpoint;
				$args = array(
					'timeout' => 60,
					'headers' => array(
						'Accept' => 'application/json',
					),
				);

				$filtered = array_filter($params, function($value) {
					return $value !== '' && $value !== null;
				});

				if (strtoupper($method) === 'GET') {
					if (!empty($filtered)) {
						$url .= '?' . http_build_query($filtered);
					}
					return wp_remote_get($url, $args);
				}

				$args['body'] = $filtered;
				return wp_remote_post($url, $args);
			}
		}

		$filters = array(
			'order_id' => isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : '',
			'order_type' => isset($_GET['order_type']) ? sanitize_text_field($_GET['order_type']) : '',
			'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '',
			'priority' => isset($_GET['priority']) ? sanitize_text_field($_GET['priority']) : '',
		);

		$api_response = issue_flag_api_request('/issues/flag-dashboard', 'GET', $filters);

		if (is_wp_error($api_response)) {
			$api_error = $api_response->get_error_message();
		} else {
			$body = json_decode(wp_remote_retrieve_body($api_response), true);
			if (!is_array($body) || ($body['status'] ?? '') !== 'success') {
				$api_error = isset($body['message']) ? $body['message'] : 'Unable to fetch issue data from API.';
			} else {
				$data = $body['data'] ?? array();
				$all_issues = $data['issues'] ?? array();
				$query_errors = $data['query_errors'] ?? array();
				$stats = array_merge($stats, $data['stats'] ?? array());
			}
		}

		// Use API filters result directly
		$filtered_issues = $all_issues;

		// Calculate statistics fallback if API did not include them
		if (empty($stats) || !isset($stats['total'])) {
			$stats = array(
				'total' => count($filtered_issues),
				'd1' => count(array_filter($filtered_issues, function($i) { return isset($i['priority']) && $i['priority'] === 'D-1'; })),
				'd7' => count(array_filter($filtered_issues, function($i) { return isset($i['priority']) && $i['priority'] === 'D-7'; })),
				'd10' => count(array_filter($filtered_issues, function($i) { return isset($i['priority']) && $i['priority'] === 'D-10'; })),
			);
		}

		$total_issues = $stats['total'];
		$d1_count = $stats['d1'];
		$d7_count = $stats['d7'];
		$d10_count = $stats['d10'];

		if ($api_error) {
			echo '<div style="background:#f8d7da;color:#842029;padding:15px;margin:20px 0;border-radius:5px;"><strong>⚠ API Error:</strong> ' . esc_html($api_error) . '</div>';
		}
		// Display query errors from API if any
		if(!empty($query_errors)) {
			echo '<div style="background:#f8d7da;color:#842029;padding:15px;margin:20px 0;border-radius:5px;">';
			echo '<strong>⚠ Database Query Errors:</strong><br><br>';
			foreach($query_errors as $error) {
				echo '<strong>' . htmlspecialchars($error['query']) . ':</strong> ' . htmlspecialchars($error['error']) . '<br>';
			}
			echo '</div>';
		}
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