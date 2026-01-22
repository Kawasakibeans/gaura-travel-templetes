<?php
/**
 * Template Name: View Your Booking Admin page-tanvi
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

// Ensure WordPress is loaded
if (!function_exists('get_header')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

get_header();?>

<html> 
<head>
<style>
/* Your existing styles */
.api-error {
  background-color: #ffebee;
  color: #d32f2f;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
  display: none;
}
.api-loading {
  background-color: #e3f2fd;
  color: #1565c0;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
  display: none;
}
.debug-info {
  background-color: #f5f5f5;
  border: 1px solid #ddd;
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
  font-family: monospace;
  white-space: pre-wrap;
}
/* Form container styles to prevent header overlap */
.search-form-container {
  margin-top: 50px;
  padding: 20px;
  min-height: 200px;
}
.search-form-container table {
  margin: 0 auto;
  border-collapse: separate;
  border-spacing: 10px;
}
.search-form-container td {
  padding: 8px;
}
.search-form-container label {
  cursor: pointer;
  font-weight: normal;
  margin-right: 15px;
}
.search-form-container input[type="radio"] {
  margin-right: 5px;
}
.search-form-container input[type="number"],
.search-form-container input[type="date"] {
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  width: 200px;
}
.search-form-container input[type="submit"] {
  padding: 10px 20px;
  font-size: 16px;
  cursor: pointer;
}
/* Bookings table styles */
.bookings-table-container {
  margin-top: 30px;
  padding: 20px;
  overflow-x: auto;
}
.bookings-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #fff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  font-size: 14px;
}
.bookings-table thead {
  background-color: #2c3e50;
  color: #fff;
}
.bookings-table th {
  padding: 12px 8px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid #34495e;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 10;
}
.bookings-table td {
  padding: 10px 8px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: top;
}
.bookings-table tbody tr:hover {
  background-color: #f5f5f5;
}
.bookings-table tbody tr:nth-child(even) {
  background-color: #fafafa;
}
.bookings-table tbody tr:nth-child(even):hover {
  background-color: #f0f0f0;
}
/* Row status colors */
.bookings-table tbody tr.row-failed {
  background-color: #ffebee;
}
.bookings-table tbody tr.row-failed:hover {
  background-color: #ffcdd2;
}
.bookings-table tbody tr.row-success {
  background-color: #e8f5e9;
}
.bookings-table tbody tr.row-success:hover {
  background-color: #c8e6c9;
}
.bookings-table tbody tr.row-pending {
  background-color: #fff3e0;
}
.bookings-table tbody tr.row-pending:hover {
  background-color: #ffe0b2;
}
/* Empty value styling */
.empty-value {
  color: #999;
  font-style: italic;
  font-size: 12px;
}
/* Badge styling */
.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.badge-success {
  background-color: #4caf50;
  color: #fff;
}
.badge-failed {
  background-color: #f44336;
  color: #fff;
}
.badge-warning {
  background-color: #ff9800;
  color: #fff;
}
.badge-info {
  background-color: #2196f3;
  color: #fff;
}
.no-bookings-message {
  padding: 30px;
  text-align: center;
  background-color: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 4px;
  color: #856404;
  font-size: 16px;
  margin: 20px 0;
}
.bookings-summary {
  margin-bottom: 15px;
  padding: 15px;
  background-color: #e3f2fd;
  border-left: 4px solid #2196f3;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.bookings-table td a {
  color: #2196f3;
  text-decoration: none;
}
.bookings-table td a:hover {
  text-decoration: underline;
}
</style>

<script type="text/javascript">
    function handleRadioClick() {
        var rdbsearchorderno = document.getElementById('searchorderno');
        var rdbsearchdate = document.getElementById('searchdate');
        var trordernorow = document.getElementById('ordernorow');
        var trbookingdaterow = document.getElementById('bookingdaterow');
        var ordernoInput = document.getElementById('orderno');
        var bookingdateInput = document.getElementById('bookingdate');

        if (rdbsearchorderno && rdbsearchorderno.checked) {
            // Show order number field, hide date field
            if (trordernorow) trordernorow.style.display = 'table-row';
            if (trbookingdaterow) trbookingdaterow.style.display = 'none';
            // Set required attribute: orderno required, bookingdate not required
            if (ordernoInput) ordernoInput.setAttribute('required', 'required');
            if (bookingdateInput) bookingdateInput.removeAttribute('required');
        } else if (rdbsearchdate && rdbsearchdate.checked){
            // Show date field, hide order number field
            if (trbookingdaterow) trbookingdaterow.style.display = 'table-row';
            if (trordernorow) trordernorow.style.display = 'none';
            // Set required attribute: bookingdate required, orderno not required
            if (bookingdateInput) bookingdateInput.setAttribute('required', 'required');
            if (ordernoInput) ordernoInput.removeAttribute('required');
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // If no radio is checked, check the first one (search by order no)
        var rdbsearchorderno = document.getElementById('searchorderno');
        var rdbsearchdate = document.getElementById('searchdate');
        if (!rdbsearchorderno.checked && !rdbsearchdate.checked) {
            rdbsearchorderno.checked = true;
        }
        
        // Ensure the correct input field is shown and required attribute is set
        handleRadioClick();
    });
</script>

</head>
<body>
<?php
// Define API base URL (check if already defined)
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1');
}

// Enable debug mode
$debug_mode = false; // Set to false in production

// Function to log debug information
function debug_log($message, $data = null) {
    global $debug_mode;
    if ($debug_mode) {
        $output = $message;
        if ($data !== null) {
            $output .= ': ' . print_r($data, true);
        }
        echo '<div class="debug-info">' . esc_html($output) . '</div>';
    }
}

// API call function with better error handling
function make_api_call($endpoint, $params = array()) {
    global $debug_mode;
    
    // Build URL with query parameters for GET request
    // If params are empty, don't add query string (for RESTful endpoints)
    if (!empty($params)) {
        $query_string = http_build_query($params);
        $api_url = API_BASE_URL . $endpoint . '?' . $query_string;
    } else {
        $api_url = API_BASE_URL . $endpoint;
    }
    
    debug_log("Making API call to", $api_url);
    debug_log("With parameters", $params);
    
    // Show loading message (will be hidden after response)
    $loading_id = 'api-loading-' . uniqid();
    echo '<div id="' . $loading_id . '" class="api-loading" style="display:block;">Loading data from API...</div>';
    echo '<script>if (document.getElementById("' . $loading_id . '")) { document.getElementById("' . $loading_id . '").style.display = "none"; }</script>';
    
    $response = wp_remote_get($api_url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        )
    ));
    
    // Hide loading message after getting response
    echo '<script>if (document.getElementById("' . $loading_id . '")) { document.getElementById("' . $loading_id . '").style.display = "none"; }</script>';
    
    if (is_wp_error($response)) {
        debug_log("API call failed with WordPress error", $response->get_error_message());
        return array('success' => false, 'error' => $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    debug_log("API response code", $response_code);
    
    if ($response_code !== 200) {
        $response_message = wp_remote_retrieve_response_message($response);
        $body = wp_remote_retrieve_body($response);
        debug_log("API returned non-200 status", array(
            'code' => $response_code,
            'message' => $response_message,
            'body' => $body
        ));
        
        // Try to parse error message from response body
        $error_msg = 'API returned status code: ' . $response_code . ' - ' . $response_message;
        if (!empty($body)) {
            $error_data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($error_data['message'])) {
                $error_msg = $error_data['message'];
            } elseif (json_last_error() === JSON_ERROR_NONE && isset($error_data['error'])) {
                $error_msg = $error_data['error'];
            } else {
                $error_msg .= ' | Response: ' . substr($body, 0, 500);
            }
        }
        
        return array('success' => false, 'error' => $error_msg, 'response_body' => $body);
    }
    
    $body = wp_remote_retrieve_body($response);
    debug_log("Raw API response", $body);
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug_log("JSON decode error", json_last_error_msg());
        return array('success' => false, 'error' => 'Invalid JSON response: ' . json_last_error_msg());
    }
    
    debug_log("Parsed API response", $data);
    
    // Handle API response structure: check if data is wrapped in 'data' field
    if (isset($data['data']) && is_array($data['data'])) {
        // RESTful endpoint format: {data: {booking: {...}, summary: {...}, contact: {...}}}
        if (isset($data['data']['booking']) && !empty($data['data']['booking'])) {
            return array('success' => true, 'data' => $data['data'], 'total' => 1);
        }
        // Search endpoint format: {data: {bookings: [...], total: ...}}
        if (isset($data['data']['bookings']) && is_array($data['data']['bookings'])) {
            return array('success' => true, 'data' => $data['data']['bookings'], 'total' => isset($data['data']['total']) ? $data['data']['total'] : count($data['data']['bookings']));
        } else {
            // If data is directly an array of bookings
            return array('success' => true, 'data' => $data['data'], 'total' => count($data['data']));
        }
    } elseif (isset($data['bookings']) && is_array($data['bookings'])) {
        return array('success' => true, 'data' => $data['bookings'], 'total' => isset($data['total']) ? $data['total'] : count($data['bookings']));
    } elseif (is_array($data) && isset($data[0])) {
        // If data is directly an array of bookings
        return array('success' => true, 'data' => $data, 'total' => count($data));
    } else {
        return array('success' => false, 'error' => 'Unexpected API response format');
    }
}

// Function to display booking details from API
function display_booking_details($booking_data) {
    global $debug_mode;
    debug_log("Displaying booking details from API");
    debug_log("Booking data received", array_keys($booking_data));
    
    // Extract data from API response - handle both RESTful endpoint and search endpoint formats
    // RESTful endpoint: {booking: {...}, summary: {...}, contact: {...}}
    // Search endpoint: direct booking object
    
    // Booking ID - try multiple field names
    $booking_id = isset($booking_data['order_id']) ? $booking_data['order_id'] : 
                  (isset($booking_data['id']) ? $booking_data['id'] : 0);
    
    // Trip/Product title - use trip_code if product_title is empty
    $title = '';
    if (!empty($booking_data['product_title'])) {
        $title = $booking_data['product_title'];
    } elseif (!empty($booking_data['title'])) {
        $title = $booking_data['title'];
    } elseif (!empty($booking_data['trip_code'])) {
        $title = $booking_data['trip_code'];
    }
    
    // Travel date
    $travel_date = isset($booking_data['travel_date']) ? $booking_data['travel_date'] : '';
    
    // Number of travelers
    $pax = 0;
    if (isset($booking_data['total_pax']) && $booking_data['total_pax'] > 0) {
        $pax = $booking_data['total_pax'];
    } elseif (isset($booking_data['pax']) && $booking_data['pax'] > 0) {
        $pax = $booking_data['pax'];
    }
    
    // Customer note / Remarks - try multiple field names
    $customer_note = '';
    if (!empty($booking_data['customer_note'])) {
        $customer_note = $booking_data['customer_note'];
    } elseif (!empty($booking_data['remarks'])) {
        $customer_note = $booking_data['remarks'];
    } elseif (!empty($booking_data['booking_note_for_agents'])) {
        $customer_note = $booking_data['booking_note_for_agents'];
    } elseif (!empty($booking_data['notes'])) {
        $customer_note = $booking_data['notes'];
    }
    
    // Billing fields - may not be available in this API response
    // Try multiple possible field names
    $billing_address = '';
    if (isset($booking_data['billing_address']) && !empty($booking_data['billing_address'])) {
        $billing_address = $booking_data['billing_address'];
    } elseif (isset($booking_data['address']) && !empty($booking_data['address'])) {
        $billing_address = $booking_data['address'];
    }
    
    $billing_city = isset($booking_data['billing_city']) && !empty($booking_data['billing_city']) ? $booking_data['billing_city'] : '';
    $billing_country = isset($booking_data['billing_country']) && !empty($booking_data['billing_country']) ? $booking_data['billing_country'] : '';
    $billing_postal = isset($booking_data['billing_postal']) && !empty($booking_data['billing_postal']) ? $booking_data['billing_postal'] : 
                      (isset($booking_data['billing_postcode']) && !empty($booking_data['billing_postcode']) ? $booking_data['billing_postcode'] : '');
    
    // Debug: Log extracted values
    if ($debug_mode) {
        debug_log("Extracted field values", array(
            'booking_id' => $booking_id,
            'title' => $title,
            'travel_date' => $travel_date,
            'pax' => $pax,
            'customer_note' => $customer_note ? 'has value' : 'empty',
            'billing_address' => $billing_address ? 'has value' : 'empty',
            'billing_city' => $billing_city ? 'has value' : 'empty',
            'billing_country' => $billing_country ? 'has value' : 'empty',
            'billing_postal' => $billing_postal ? 'has value' : 'empty'
        ));
    }
    
    // Payment information
    $payment_status = isset($booking_data['payment_status']) ? $booking_data['payment_status'] : '';
    $total_amount = isset($booking_data['total_amount']) ? $booking_data['total_amount'] : '';
    $payment_ref = isset($booking_data['payment_ref']) ? $booking_data['payment_ref'] : '';
    
    // Contact information
    $contact_email = isset($booking_data['contact_email']) ? $booking_data['contact_email'] : 
                     (isset($booking_data['billing_email']) ? $booking_data['billing_email'] : '');
    $contact_phone = isset($booking_data['contact_phone']) ? $booking_data['contact_phone'] : 
                     (isset($booking_data['billing_phone']) ? $booking_data['billing_phone'] : '');
    
    // Order information
    $order_date = isset($booking_data['order_date']) ? $booking_data['order_date'] : '';
    $order_type = isset($booking_data['order_type']) ? $booking_data['order_type'] : '';
    $source = isset($booking_data['source']) ? $booking_data['source'] : '';
    
    // Travelers info (for search endpoint format)
    $fname = isset($booking_data['fname']) ? $booking_data['fname'] : 
             (isset($booking_data['first_name']) ? $booking_data['first_name'] : array());
    $lname = isset($booking_data['lname']) ? $booking_data['lname'] : 
             (isset($booking_data['last_name']) ? $booking_data['last_name'] : array());
    
    // Display booking details
    ?>
    <div class="my-order my-order-details">
        <div class="view-order">
            <div class="order-list">
                <div class="order-wrapper">
                    <h3><?php esc_html_e('Your Booking Details', 'wp-travel'); ?></h3>
                    <table class="booking-details-table">
                        <tr>
                            <th>Booking ID</th>
                            <td><?php echo esc_html($booking_id); ?></td>
                        </tr>
                        <tr>
                            <th>Trip</th>
                            <td><?php echo !empty($title) ? esc_html($title) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Travel Date</th>
                            <td><?php echo !empty($travel_date) ? format_datetime_display($travel_date) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Number of Travelers</th>
                            <td><?php echo esc_html($pax); ?></td>
                        </tr>
                        <tr>
                            <th>Customer Note</th>
                            <td><?php echo !empty($customer_note) ? esc_html($customer_note) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Billing Address</th>
                            <td><?php echo !empty($billing_address) ? esc_html($billing_address) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Billing City</th>
                            <td><?php echo !empty($billing_city) ? esc_html($billing_city) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Billing Country</th>
                            <td><?php echo !empty($billing_country) ? esc_html($billing_country) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Billing Postal</th>
                            <td><?php echo !empty($billing_postal) ? esc_html($billing_postal) : '<span class="empty-value">-</span>'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Helper functions for displaying booking data
function format_date_display($date_str) {
    if (empty($date_str) || $date_str === '-') return '<span class="empty-value">-</span>';
    try {
        $date = new DateTime($date_str);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return esc_html($date_str);
    }
}

function format_datetime_display($datetime_str) {
    if (empty($datetime_str) || $datetime_str === '-') return '<span class="empty-value">-</span>';
    try {
        $date = new DateTime($datetime_str);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return esc_html($datetime_str);
    }
}

function display_value($value, $highlight_empty = false) {
    if (empty($value) || $value === '-') {
        return '<span class="empty-value">-</span>';
    }
    return esc_html($value);
}

// Function to display bookings table from API
function display_bookings_table($bookings_data, $total = null) {
    // Ensure bookings_data is an array
    if (!is_array($bookings_data)) {
        debug_log("Error: bookings_data is not an array", gettype($bookings_data));
        echo '<div class="no-bookings-message">Error: Invalid data format received from API.</div>';
        return;
    }
    
    // Filter out non-array items
    $valid_bookings = array();
    foreach ($bookings_data as $key => $booking) {
        if (is_array($booking) && !empty($booking)) {
            $valid_bookings[] = $booking;
        } else {
            debug_log("Warning: booking item at key '$key' is not a valid array, skipping", gettype($booking));
        }
    }
    
    $count = count($valid_bookings);
    $display_total = $total !== null ? $total : $count;
    debug_log("Displaying bookings table from API", "$count valid bookings found (total: $display_total)");
    
    // Always show container, even if empty
    ?>
    <div class="bookings-table-container">
        <?php if ($count === 0 && $display_total === 0): ?>
            <div class="no-bookings-message">
                <strong>No bookings found</strong><br>
                No booking records were found for the selected date. Please try a different date.
            </div>
        <?php elseif ($count === 0 && $display_total > 0): ?>
            <div class="no-bookings-message">
                <strong>Data format issue</strong><br>
                API returned <?php echo esc_html($display_total); ?> total bookings, but no valid records could be processed.
            </div>
        <?php else: ?>
            <?php 
            // Calculate statistics
            $with_payment = 0;
            $with_passenger_info = 0;
            $order_types = array();
            foreach ($valid_bookings as $booking) {
                if (!empty($booking['payment_status'])) $with_payment++;
                if (!empty($booking['first_name']) || !empty($booking['last_name'])) $with_passenger_info++;
                $order_type = $booking['order_type'] ?? 'unknown';
                $order_types[$order_type] = ($order_types[$order_type] ?? 0) + 1;
            }
            ?>
            
            <div class="bookings-summary">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <strong style="font-size: 18px;">Total Bookings Found: <?php echo esc_html($display_total); ?></strong>
                        <?php if ($count < $display_total): ?>
                            <span style="color: #f44336;"> (Displaying <?php echo esc_html($count); ?> valid records)</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 14px; color: #666;">
                        <?php if ($with_payment > 0): ?>
                            <span style="margin-right: 15px;">✓ <?php echo $with_payment; ?> with payment status</span>
                        <?php endif; ?>
                        <?php if ($with_passenger_info > 0): ?>
                            <span style="margin-right: 15px;">✓ <?php echo $with_passenger_info; ?> with passenger info</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($order_types)): ?>
                    <div style="margin-top: 10px; font-size: 13px; color: #666;">
                        <strong>Order Types:</strong> 
                        <?php 
                        $type_labels = array();
                        foreach ($order_types as $type => $count) {
                            $type_labels[] = ucfirst($type) . ': ' . $count;
                        }
                        echo implode(' | ', $type_labels);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Order Date</th>
                        <th>Travel Date</th>
                        <th>Order Type</th>
                        <th>Payment Status</th>
                        <th>Pax Status</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Country</th>
                        <th>DOB</th>
                        <th>Gender</th>
                        <th>Passport Num</th>
                        <th>Passport Exp</th>
                        <th>PNR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Process API data
                    foreach ($valid_bookings as $index => $booking) {
                        debug_log("Processing booking #" . ($index + 1), $booking);
                        
                        // Determine row class based on order type
                        $row_class = '';
                        if (isset($booking['order_type'])) {
                            switch(strtolower($booking['order_type'])) {
                                case 'failed':
                                    $row_class = 'row-failed';
                                    break;
                                case 'paid':
                                case 'completed':
                                    $row_class = 'row-success';
                                    break;
                                case 'pending':
                                    $row_class = 'row-pending';
                                    break;
                            }
                        }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><strong><?php 
                                // Try multiple possible field names for order ID
                                $order_id = $booking['order_id'] ?? $booking['id'] ?? $booking['order_no'] ?? $booking['order_number'] ?? '-';
                                echo esc_html($order_id);
                                // Debug: show which field was used
                                if ($debug_mode && $order_id !== '-') {
                                    $used_field = isset($booking['order_id']) ? 'order_id' : (isset($booking['id']) ? 'id' : (isset($booking['order_no']) ? 'order_no' : 'order_number'));
                                    echo '<br><small style="color: #999; font-size: 10px;">(from: ' . $used_field . ')</small>';
                                }
                            ?></strong></td>
                            <td><?php echo format_datetime_display($booking['order_date'] ?? ''); ?></td>
                            <td><?php echo format_date_display($booking['travel_date'] ?? ''); ?></td>
                            <td>
                                <?php 
                                $order_type = $booking['order_type'] ?? '';
                                if (!empty($order_type)) {
                                    $type_class = strtolower($order_type) === 'failed' ? 'badge-failed' : 
                                                  (strtolower($order_type) === 'paid' ? 'badge-success' : 'badge-info');
                                    echo '<span class="badge ' . $type_class . '">' . esc_html(ucfirst($order_type)) . '</span>';
                                } else {
                                    echo '<span class="empty-value">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $payment_status = $booking['payment_status'] ?? '';
                                if (!empty($payment_status)) {
                                    $status_class = strtolower($payment_status) === 'paid' ? 'badge-success' : 'badge-warning';
                                    echo '<span class="badge ' . $status_class . '">' . esc_html(ucfirst($payment_status)) . '</span>';
                                } else {
                                    echo '<span class="empty-value">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $pax_status = $booking['pax_status'] ?? '';
                                if (!empty($pax_status)) {
                                    echo '<span class="badge badge-info">' . esc_html($pax_status) . '</span>';
                                } else {
                                    echo '<span class="empty-value">-</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo !empty($booking['email']) ? '<a href="mailto:' . esc_attr($booking['email']) . '">' . esc_html($booking['email']) . '</a>' : '<span class="empty-value">-</span>'; ?></td>
                            <td><?php echo display_value($booking['phone'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['first_name'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['last_name'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['country'] ?? ''); ?></td>
                            <td><?php echo format_date_display($booking['dob'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['gender'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['passport_num'] ?? ''); ?></td>
                            <td><?php echo format_date_display($booking['passport_exp_date'] ?? ''); ?></td>
                            <td><?php echo display_value($booking['pnr'] ?? ''); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
?>

<center>
    <div class="search-form-container">
        <h2 style="margin-bottom: 20px;">Search Bookings</h2>
        <form method="post" action="<?php echo esc_url("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" onsubmit="return validateForm()">
            <table>
                <tr>
                    <td><label> search by order no <input type="radio" name="searchby" id="searchorderno" value="orderno" onchange="handleRadioClick()" <?php echo (!isset($_POST['searchby']) || $_POST['searchby'] === 'orderno') ? 'checked' : ''; ?>/></label></td>
                    <td><label> search by date <input type="radio" name="searchby" id="searchdate" value="date" onchange="handleRadioClick()" <?php echo (isset($_POST['searchby']) && $_POST['searchby'] === 'date') ? 'checked' : ''; ?>/></label></td>
                </tr>
                <tr id="ordernorow" style="display:<?php echo (!isset($_POST['searchby']) || $_POST['searchby'] === 'orderno') ? 'table-row' : 'none'; ?>;">
                    <td><strong>Order Number:</strong></td>
                    <td><input type="number" name="orderno" id="orderno" placeholder="Enter order number" value="<?php echo isset($_POST['orderno']) ? esc_attr($_POST['orderno']) : ''; ?>"></td>
                </tr>
                
                <tr id="bookingdaterow" style="display:<?php echo (isset($_POST['searchby']) && $_POST['searchby'] === 'date') ? 'table-row' : 'none'; ?>;">
                    <td><strong>Booking Date:</strong></td>
                    <td><input type="date" name="bookingdate" id="bookingdate" value="<?php echo isset($_POST['bookingdate']) ? esc_attr($_POST['bookingdate']) : ''; ?>"></td>
                </tr>
                
                <tr>
                    <td colspan="2" align="center" style="padding-top:20px;">
                        <input type="submit" name="search" id="search" class="button button-primary" value="Search">
                    </td>
                </tr>
            </table>
        </form>
        
        <script type="text/javascript">
        function validateForm() {
            // Ensure required attributes are set correctly before validation
            handleRadioClick();
            
            var searchby = document.querySelector('input[name="searchby"]:checked');
            if (!searchby) {
                alert('Please select a search option (by order no or by date)');
                return false;
            }
            
            if (searchby.value === 'orderno') {
                var orderno = document.getElementById('orderno').value;
                if (!orderno || orderno <= 0) {
                    alert('Please enter a valid order number');
                    return false;
                }
            } else if (searchby.value === 'date') {
                var bookingdate = document.getElementById('bookingdate').value;
                if (!bookingdate) {
                    alert('Please select a booking date');
                    return false;
                }
            }
            return true;
        }
        </script>
        
        <div id="api-error" class="api-error"></div>
        <div id="api-loading" class="api-loading"></div>
        
        <?php
        if (isset($_POST['search'])) {
            try {
                $date = isset($_POST['bookingdate']) ? sanitize_text_field($_POST['bookingdate']) : '';
                $orderno = isset($_POST['orderno']) ? absint($_POST['orderno']) : 0;
                $searchby = isset($_POST['searchby']) ? sanitize_text_field($_POST['searchby']) : '';
                
                debug_log("Form submitted with", array(
                    'searchby' => $searchby,
                    'orderno' => $orderno,
                    'date' => $date
                ));
                
                if ($searchby === 'orderno' && $orderno > 0) {
                // Call API to get booking by order number
                // Try multiple parameter names as API might expect different field names
                // Also try both integer and string formats as order numbers might be stored as strings
                debug_log("Searching by order number", array('orderno' => $orderno, 'type' => gettype($orderno)));
                
                // Convert to string as well for API calls (some APIs expect string format)
                $orderno_str = (string)$orderno;
                
                // Helper function to check if API result has valid data
                // RESTful endpoint returns {booking: {...}, summary: {...}, contact: {...}}
                // Search endpoint returns {bookings: [...], total: ...}
                $has_valid_data = function($result) {
                    if (!$result['success']) return false;
                    $data = isset($result['data']) ? $result['data'] : array();
                    if (is_array($data) && !empty($data)) {
                        // Check for RESTful endpoint format (has 'booking' key)
                        if (isset($data['booking']) && !empty($data['booking'])) {
                            return true;
                        }
                        // Check for search endpoint format (has 'bookings' array or array with index 0)
                        if (isset($data['bookings']) && !empty($data['bookings'])) {
                            return true;
                        }
                        if (isset($data[0])) {
                            return true;
                        }
                    }
                    return false;
                };
                
                // PRIORITY: Use RESTful endpoint first - it doesn't require email parameter!
                // RESTful endpoint: /customer-view-bookings/{order_id}
                // This is the correct endpoint for searching by order ID alone
                debug_log("Attempt 1: Using RESTful endpoint (recommended - no email required)");
                $api_result = make_api_call('/customer-view-bookings/' . $orderno, array());
                debug_log("RESTful endpoint (int) result", array('success' => $api_result['success'], 'has_data' => $has_valid_data($api_result)));
                
                // If RESTful endpoint fails, try with string ID
                if (!$has_valid_data($api_result)) {
                    debug_log("RESTful endpoint (int) failed, trying with string ID");
                    $api_result = make_api_call('/customer-view-bookings/' . $orderno_str, array());
                    debug_log("RESTful endpoint (string) result", array('success' => $api_result['success'], 'has_data' => $has_valid_data($api_result)));
                }
                
                // Fallback: Try search endpoint (but it requires email, so will likely fail)
                // Only try if RESTful endpoint completely failed (not just empty data)
                if (!$api_result['success'] || !$has_valid_data($api_result)) {
                    debug_log("RESTful endpoint failed, trying search endpoint (NOTE: requires email parameter, may fail)");
                    // Try search endpoint with 'id' parameter
                    $api_result = make_api_call('/customer-view-bookings/search', array('id' => $orderno));
                    debug_log("Search endpoint with 'id' parameter result", array('success' => $api_result['success'], 'has_data' => $has_valid_data($api_result)));
                }
                
                // Try search endpoint with 'order_id' parameter
                if (!$has_valid_data($api_result)) {
                    debug_log("Trying search endpoint with 'order_id' parameter");
                    $api_result = make_api_call('/customer-view-bookings/search', array('order_id' => $orderno));
                    debug_log("Search endpoint with 'order_id' parameter result", array('success' => $api_result['success'], 'has_data' => $has_valid_data($api_result)));
                }
                
                debug_log("Final API result for order number search", $api_result);
                
                // Check if we have valid data (not just success status)
                $has_data = $has_valid_data($api_result);
                
                if ($api_result['success'] && $has_data) {
                    debug_log("API call successful with data, displaying booking details");
                    debug_log("API response data structure", array(
                        'is_array' => is_array($api_result['data']),
                        'has_index_0' => is_array($api_result['data']) && isset($api_result['data'][0]),
                        'data_type' => gettype($api_result['data']),
                        'data_keys' => is_array($api_result['data']) && isset($api_result['data'][0]) ? array_keys($api_result['data'][0]) : 'N/A'
                    ));
                    
                    // Extract booking data - handle different response structures
                    // RESTful endpoint returns: {booking: {...}, summary: {...}, contact: {...}}
                    // Search endpoint returns: {bookings: [...], total: ...}
                    $booking_to_display = null;
                    if (is_array($api_result['data'])) {
                        // RESTful endpoint format: data.booking
                        if (isset($api_result['data']['booking']) && !empty($api_result['data']['booking'])) {
                            $booking_to_display = $api_result['data']['booking'];
                            // Add contact info if available (don't merge summary as it's a complex structure)
                            if (isset($api_result['data']['contact'])) {
                                $booking_to_display['contact_phone'] = $api_result['data']['contact']['phone'] ?? '';
                                $booking_to_display['contact_email'] = $api_result['data']['contact']['email'] ?? '';
                            }
                            // Also add billing_email and billing_phone if not already present
                            if (empty($booking_to_display['contact_email']) && isset($booking_to_display['billing_email'])) {
                                $booking_to_display['contact_email'] = $booking_to_display['billing_email'];
                            }
                            if (empty($booking_to_display['contact_phone']) && isset($booking_to_display['billing_phone'])) {
                                $booking_to_display['contact_phone'] = $booking_to_display['billing_phone'];
                            }
                            debug_log("Extracted booking from RESTful endpoint format", array(
                                'booking_id' => $booking_to_display['order_id'] ?? 'N/A',
                                'has_contact' => isset($api_result['data']['contact']),
                                'keys' => array_keys($booking_to_display)
                            ));
                        }
                        // Search endpoint format: data.bookings[0] or data[0]
                        elseif (isset($api_result['data']['bookings']) && isset($api_result['data']['bookings'][0])) {
                            $booking_to_display = $api_result['data']['bookings'][0];
                            debug_log("Extracted booking from search endpoint format (bookings array)");
                        }
                        elseif (isset($api_result['data'][0])) {
                            $booking_to_display = $api_result['data'][0];
                            debug_log("Extracted booking from search endpoint format (direct array)");
                        }
                        elseif (!empty($api_result['data']) && !isset($api_result['data'][0]) && !isset($api_result['data']['booking'])) {
                            // Single booking object (fallback)
                            $booking_to_display = $api_result['data'];
                            debug_log("Extracted booking as single object (fallback)");
                        }
                    }
                    
                    if ($booking_to_display) {
                        display_booking_details($booking_to_display);
                    } else {
                        echo '<div class="no-bookings-message">';
                        echo '<strong>Data Format Issue</strong><br>';
                        echo 'API returned success but data format is unexpected.';
                        echo '</div>';
                    }
                } elseif ($api_result['success'] && !$has_data) {
                    // API returned success but no data - all parameter attempts failed
                    echo '<div class="no-bookings-message">';
                    echo '<strong>No booking found</strong><br>';
                    echo 'No booking found with order number: <strong>' . esc_html($orderno) . '</strong>';
                    echo '</div>';
                } else {
                    // Show error
                    $error_msg = isset($api_result['error']) ? $api_result['error'] : 'Unknown error';
                    
                    echo '<div id="api-error" class="api-error" style="display:block;">';
                    echo '<strong>API Error:</strong> ' . esc_html($error_msg);
                    echo '</div>';
                    echo '<p>Unable to retrieve booking details. Please try again later.</p>';
                }
            } elseif ($searchby === 'date' && !empty($date)) {
                // Call API to get bookings by date
                $api_result = make_api_call('/customer-view-bookings/search', array('order_date' => $date));
                
                debug_log("API result for date search", $api_result);
                
                if ($api_result['success']) {
                    debug_log("API call successful, displaying bookings table");
                    // Ensure data is an array
                    $bookings_data = isset($api_result['data']) ? $api_result['data'] : array();
                    $total = isset($api_result['total']) ? $api_result['total'] : null;
                    
                    debug_log("Bookings data", array(
                        'count' => is_array($bookings_data) ? count($bookings_data) : 0,
                        'total' => $total,
                        'is_array' => is_array($bookings_data),
                        'data_sample' => is_array($bookings_data) && count($bookings_data) > 0 ? array_slice($bookings_data, 0, 1) : 'empty'
                    ));
                    
                    // Debug: Show first booking's field names to help identify order ID field
                    if ($debug_mode && is_array($bookings_data) && count($bookings_data) > 0) {
                        $first_booking = $bookings_data[0];
                        $order_id_fields = array();
                        foreach (array('order_id', 'id', 'order_no', 'order_number', 'booking_id', 'orderId') as $field) {
                            if (isset($first_booking[$field])) {
                                $order_id_fields[$field] = $first_booking[$field];
                            }
                        }
                        debug_log("First booking order ID fields", $order_id_fields);
                        debug_log("First booking all keys", array_keys($first_booking));
                        debug_log("First booking complete data (for reference)", $first_booking);
                    }
                    
                    // Always call display function, even if data is empty
                    // The function will handle empty data and show appropriate message
                    if (is_array($bookings_data)) {
                        display_bookings_table($bookings_data, $total);
                    } else {
                        echo '<div class="no-bookings-message">';
                        echo '<strong>Data Format Error</strong><br>';
                        echo 'Invalid data format received from API.';
                        echo '</div>';
                    }
                } else {
                    // Show error
                    $error_msg = isset($api_result['error']) ? $api_result['error'] : 'Unknown error';
                    
                    echo '<div id="api-error" class="api-error" style="display:block;">';
                    echo '<strong>API Error:</strong> ' . esc_html($error_msg);
                    echo '</div>';
                    echo '<p>Unable to retrieve bookings for the selected date. Please try again later.</p>';
                }
                } else {
                    if (empty($searchby)) {
                        echo '<div class="api-error" style="display:block; background-color: #fff3cd; color: #856404; border: 1px solid #ffc107;">';
                        echo '<strong>Error:</strong> Please select a search option (by order no or by date) before submitting.';
                        echo '</div>';
                    } elseif ($searchby === 'orderno' && $orderno <= 0) {
                        echo '<div class="api-error" style="display:block; background-color: #fff3cd; color: #856404; border: 1px solid #ffc107;">';
                        echo '<strong>Error:</strong> Please enter a valid order number (must be greater than 0).';
                        echo '</div>';
                    } elseif ($searchby === 'date' && empty($date)) {
                        echo '<div class="api-error" style="display:block; background-color: #fff3cd; color: #856404; border: 1px solid #ffc107;">';
                        echo '<strong>Error:</strong> Please select a booking date.';
                        echo '</div>';
                    } else {
                        echo '<p>Please select a search option and provide valid input.</p>';
                    }
                }
            } catch (Exception $e) {
                error_log("Error in tpl_view_your_booking_admin.php: " . $e->getMessage());
                debug_log("Exception caught", $e->getMessage());
                echo '<div id="api-error" class="api-error" style="display:block;">Error: ' . esc_html($e->getMessage()) . '</div>';
                echo '<p>An error occurred while processing your request. Please try again later.</p>';
            }
        }
        ?>
    </div>
</center>
</body>	   
<?php get_footer(); ?>