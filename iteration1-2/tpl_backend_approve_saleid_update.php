<?php
/**
 * Template Name: Sale Id Update
 * Template Post Type: post, page
 */

get_header();
// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// global $wpdb;
// ============================================================================
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$base_url = API_BASE_URL;

// Handle status update via API
if (isset($_POST['approve_record'])) {
    $record_id = intval($_POST['record_id']);
    $current_user = wp_get_current_user();
    $modified_by = $current_user->user_login ?? 'system';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => API_BASE_URL.'/saleid-updates/' . $record_id . '/approve',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['modified_by' => $modified_by]),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 201 && isset($responseData['status']) && $responseData['status'] === 'success') {
        echo '<div class="notice notice-success"><p>Record approved successfully! Agent info updated in travel bookings.</p></div>';
    } else {
        $errorMsg = $responseData['message'] ?? 'Error approving record';
        echo '<div class="notice notice-error"><p>' . esc_html($errorMsg) . '</p></div>';
    }
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// if (isset($_POST['approve_record'])) {
//     $record_id = intval($_POST['record_id']);
//     
//     // Get the record being approved
//     $record = $wpdb->get_row($wpdb->prepare(
//         "SELECT * FROM wpk4_saleid_update WHERE id = %d",
//         $record_id
//     ));
//     
//     if ($record) {
//         // Start transaction
//         $wpdb->query('START TRANSACTION');
//         
//         try {
//             // Update saleid_update table
//             $result1 = $wpdb->update(
//                 'wpk4_saleid_update',
//                 array(
//                     'is_checked' => 1,
//                     'status' => 'approved',
//                     'modified_date' => current_time('mysql'),
//                     'modified_by' => wp_get_current_user()->user_login
//                 ),
//                 array('id' => $record_id),
//                 array('%d', '%s', '%s', '%s'),
//                 array('%d')
//             );
//             
//             // Update backend_travel_bookings table
//             $result2 = $wpdb->update(
//                 'wpk4_backend_travel_bookings',
//                 array(
//                     'agent_info' => $record->new_sale_id,
//                 ),
//                 array('order_id' => $record->order_id),
//                 array('%s'),
//                 array('%s')
//             );
//             
//             $result3 = $wpdb->update(
//                 'wpk4_backend_travel_bookings_realtime',
//                 array(
//                     'agent_info' => $record->new_sale_id,
//                 ),
//                 array('order_id' => $record->order_id),
//                 array('%s'),
//                 array('%s')
//             );
//             
//             if ($result1 !== false && $result2 !== false && $result3 !== false) {
//                 $wpdb->query('COMMIT');
//                 echo '<div class="notice notice-success"><p>Record approved successfully! Agent info updated in travel bookings.</p></div>';
//             } else {
//                 $wpdb->query('ROLLBACK');
//                 echo '<div class="notice notice-error"><p>Error approving record: ' . $wpdb->last_error . '</p></div>';
//             }
//         } catch (Exception $e) {
//             $wpdb->query('ROLLBACK');
//             echo '<div class="notice notice-error"><p>Error: ' . $e->getMessage() . '</p></div>';
//         }
//     } else {
//         echo '<div class="notice notice-error"><p>Record not found!</p></div>';
//     }
// }
// ============================================================================

if (isset($_POST['reject_record'])) {
    $record_id = intval($_POST['record_id']);
    $current_user = wp_get_current_user();
    $modified_by = $current_user->user_login ?? 'system';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => API_BASE_URL .'/saleid-updates/' . $record_id . '/reject',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['modified_by' => $modified_by]),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 201 && isset($responseData['status']) && $responseData['status'] === 'success') {
        echo '<div class="notice notice-warning"><p>Record rejected successfully.</p></div>';
    } else {
        $errorMsg = $responseData['message'] ?? 'Error rejecting record';
        echo '<div class="notice notice-error"><p>' . esc_html($errorMsg) . '</p></div>';
    }
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// if (isset($_POST['reject_record'])) {
//     $record_id = intval($_POST['record_id']);
//     
//     $record = $wpdb->get_row($wpdb->prepare(
//         "SELECT * FROM wpk4_saleid_update WHERE id = %d",
//         $record_id
//     ));
//
//     if ($record) {
//         $result = $wpdb->update(
//             'wpk4_saleid_update',
//             array(
//                 'is_checked' => 1,
//                 'status' => 'rejected',
//                 'modified_date' => current_time('mysql'),
//                 'modified_by' => wp_get_current_user()->user_login
//             ),
//             array('id' => $record_id),
//             array('%d', '%s', '%s', '%s'),
//             array('%d')
//         );
//
//         if ($result !== false) {
//             echo '<div class="notice notice-warning"><p>Record rejected successfully.</p></div>';
//         } else {
//             echo '<div class="notice notice-error"><p>Error rejecting record: ' . $wpdb->last_error . '</p></div>';
//         }
//     } else {
//         echo '<div class="notice notice-error"><p>Record not found!</p></div>';
//     }
// }
// ============================================================================
    

// Get unique values for filters via API
$curl_order_ids = curl_init();
curl_setopt_array($curl_order_ids, array(
    CURLOPT_URL => API_BASE_URL .'/saleid-updates/filters/order-ids',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
));
$response_order_ids = curl_exec($curl_order_ids);
curl_close($curl_order_ids);
$responseData_order_ids = json_decode($response_order_ids, true);
$unique_order_ids = [];
if (isset($responseData_order_ids['status']) && $responseData_order_ids['status'] === 'success' && isset($responseData_order_ids['data']['order_ids'])) {
    $unique_order_ids = $responseData_order_ids['data']['order_ids'];
}

$curl_dates = curl_init();
curl_setopt_array($curl_dates, array(
    CURLOPT_URL => API_BASE_URL .'/saleid-updates/filters/dates',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
));
$response_dates = curl_exec($curl_dates);
curl_close($curl_dates);
$responseData_dates = json_decode($response_dates, true);
$unique_dates = [];
if (isset($responseData_dates['status']) && $responseData_dates['status'] === 'success' && isset($responseData_dates['data']['dates'])) {
    $unique_dates = $responseData_dates['data']['dates'];
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// // Get unique values for filters
// $unique_order_ids = $wpdb->get_col("SELECT DISTINCT order_id FROM wpk4_saleid_update ORDER BY order_id");
// $unique_dates = $wpdb->get_col("SELECT DISTINCT DATE(created_date) FROM wpk4_saleid_update ORDER BY created_date DESC");
// ============================================================================

// Handle filter submission
$order_id_filter = isset($_GET['order_id_filter']) ? sanitize_text_field($_GET['order_id_filter']) : '';
$date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';

// Fetch filtered records via API
$url = API_BASE_URL .'/saleid-updates';
$params = [];
if (!empty($order_id_filter)) {
    $params['order_id'] = $order_id_filter;
}
if (!empty($date_filter)) {
    $params['date'] = $date_filter;
}
if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}

$curl_records = curl_init();
curl_setopt_array($curl_records, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
));
$response_records = curl_exec($curl_records);
curl_close($curl_records);
$responseData_records = json_decode($response_records, true);
$records = [];
if (isset($responseData_records['status']) && $responseData_records['status'] === 'success' && isset($responseData_records['data']['records'])) {
    // Convert array of arrays to array of objects for compatibility
    foreach ($responseData_records['data']['records'] as $record) {
        $records[] = (object)$record;
    }
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// // Build the query with filters
// $query = "SELECT * FROM wpk4_saleid_update WHERE 1=1 and LOWER(status) = 'pending'";
// $query_params = array();
//
// if (!empty($order_id_filter)) {
//     $query .= " AND order_id = %s";
//     $query_params[] = $order_id_filter;
// }
//
// if (!empty($date_filter)) {
//     $query .= " AND DATE(created_date) = %s";
//     $query_params[] = $date_filter;
// }
//
// $query .= " ORDER BY created_date DESC";
//
// if (!empty($query_params)) {
//     $query = $wpdb->prepare($query, $query_params);
// }
//
// // Fetch filtered records
// $records = $wpdb->get_results($query);
// ============================================================================
?>

<div class="wrap">
    <br><br><br>
    <h2>Sale Id Change Request Records</h2>
    
    <!-- Filter Form -->
    <form method="get" action="" class="filter-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        
        <div class="filter-row">
            <div class="filter-group">
                <label for="order_id_filter">Filter by Order ID:</label>
                <select id="order_id_filter" name="order_id_filter">
                    <option value="">All Order IDs</option>
                    <?php foreach ($unique_order_ids as $order_id) : ?>
                        <option value="<?php echo esc_attr($order_id); ?>" <?php selected($order_id_filter, $order_id); ?>>
                            <?php echo esc_html($order_id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_filter">Filter by Date:</label>
                <select id="date_filter" name="date_filter">
                    <option value="">All Dates</option>
                    <?php foreach ($unique_dates as $date) : ?>
                        <option value="<?php echo esc_attr($date); ?>" <?php selected($date_filter, $date); ?>>
                            <?php echo esc_html(date('M j, Y', strtotime($date))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="button button-primary">Apply Filters</button>
                <?php if (!empty($order_id_filter) || !empty($date_filter)) : ?>
                    <a href="<?php echo remove_query_arg(array('order_id_filter', 'date_filter')); ?>" class="button">Clear Filters</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
    
    <div class="agent-records-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>Current Sale ID</th>
                    <th>New Sale ID</th>
                    <th>Team</th>
                    <th>Manager</th>
                    <th>Call File Number</th>
                    <th>Call listen?</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="11">No records found</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <tr>
                            <td><?php echo esc_html($record->id); ?></td>
                            <td><?php echo esc_html($record->order_id); ?></td>
                            <td><?php echo esc_html($record->current_sale_id); ?></td>
                            <td><?php echo esc_html($record->new_sale_id); ?></td>
                            <td><?php echo esc_html($record->team_name); ?></td>
                            <td><?php echo esc_html($record->sales_manager); ?></td>
                            <td><?php echo esc_html($record->call_file_number); ?></td>
                            <td>
                                <input type="checkbox" class="approve-checkbox" <?php checked($record->is_checked, 1); ?> data-record-id="<?php echo esc_attr($record->id); ?>">
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr(strtolower($record->status)); ?>">
                                    <?php echo esc_html($record->status); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y H:i', strtotime($record->created_date)); ?></td>
                            <td><?php echo esc_html($record->created_by); ?></td>
                            <td>
                                <?php if (!in_array($record->status, ['approved', 'rejected'])) : ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="record_id" value="<?php echo esc_attr($record->id); ?>">
                                        <button type="submit" name="approve_record" class="button button-primary approve-button" <?php echo $record->is_checked ? '' : 'disabled'; ?>>
                                            Approve
                                        </button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="record_id" value="<?php echo esc_attr($record->id); ?>">
                                        <button type="submit" name="reject_record" class="button button-secondary reject-button" <?php echo $record->is_checked ? '' : 'disabled'; ?>>
                                            Reject
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <span class="approved-text"><?php echo ucfirst($record->status); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.approve-checkbox').change(function() {
        var recordId = $(this).data('record-id');
        var isChecked = $(this).is(':checked');
        
        // Find the corresponding Approve button and enable/disable it
        $(this).closest('tr').find('.approve-button').prop('disabled', !isChecked);
        
        // Update the database via AJAX API
        $.ajax({
            url: 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api_test_pamitha/public/v1/saleid-updates/' + recordId + '/check-status',
            type: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({
                is_checked: isChecked ? 1 : 0
            }),
            success: function(response) {
                // Optional: Handle success
            },
            error: function(xhr, status, error) {
                // Optional: Handle error
                console.error('Error updating check status:', error);
            }
        });
        
        // ============================================================================
        // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
        // ============================================================================
        // $.ajax({
        //     url: ajaxurl,
        //     type: 'POST',
        //     data: {
        //         action: 'update_check_status',
        //         record_id: recordId,
        //         is_checked: isChecked ? 1 : 0
        //     }
        // });
        // ============================================================================
    });
});
</script>

<style>
    .agent-records-container {
        margin-top: 20px;
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-form {
        background: white;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .filter-group select {
        min-width: 200px;
        padding: 6px;
        height: 32px;
    }
    
    .approve-button {
        padding: 0 8px !important;
        height: 28px !important;
        line-height: 26px !important;
        font-size: 12px !important;
    }
    
    .approve-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .status-pending {
        background-color: #ffb900;
        color: #000;
    }
    
    .status-approved {
        background-color: #46b450;
        color: #fff;
    }
    
    .approved-text {
        color: #46b450;
        font-weight: bold;
    }
    
    table.wp-list-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    table.wp-list-table th {
        text-align: left;
        padding: 10px;
        background: #ffd207; /* bright yellow */
        color: #000;
    }
    
    table.wp-list-table td {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    
    table.wp-list-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .reject-button {
        padding: 0 8px !important;
        height: 28px !important;
        line-height: 26px !important;
        font-size: 12px !important;
        margin-left: 5px;
        background-color: #dc3232 !important;
        border-color: #dc3232 !important;
        color: #fff !important;
    }
    
    .reject-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

</style>

<?php get_footer(); ?>