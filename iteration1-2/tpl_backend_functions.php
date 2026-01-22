<?php
require_once ('../../../../wp-config.php');
require_once ('../../../../wp-config-custom.php');

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined');
}

$base_url = API_BASE_URL;
$api_url = API_BASE_URL;

function call_backend_functions_api(string $path, array $query = [], array $options = []): array {
    global $base_url;
    $method = strtoupper($options['method'] ?? 'GET');
    $body = $options['body'] ?? [];
    $url = rtrim($base_url, '/') . $path;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $args = [
        'timeout' => 20,
        'headers' => ['Accept' => 'application/json'],
    ];

    if ($method === 'POST') {
        $args['body'] = $body;
        $response = wp_remote_post($url, $args);
    } else {
        $response = wp_remote_get($url, $args);
    }

    if (is_wp_error($response)) {
        throw new RuntimeException('API request failed: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || ($body['status'] ?? '') !== 'success') {
        $message = $body['message'] ?? 'Unknown API error';
        throw new RuntimeException($message);
    }

    return $body['data'] ?? [];
}
date_default_timezone_set("Australia/Melbourne"); 
global $wpdb;
global $current_user;
$currnt_userlogn = $current_user->user_login;
$currentdate = date("Y-m-d H:i:s");

if (isset($_POST["req_type"]) && $_POST['req_type'] == 'incentive_month') {
    $month = sanitize_text_field($_POST["month"] ?? '');
    if ($month === '') {
        wp_send_json_error('month is required');
    }

    try {
        $data = call_backend_functions_api('/backend-functions/incentive-dates', ['month' => $month]);
        $dates = $data['dates'] ?? [];
        wp_send_json(['dates' => $dates]);
    } catch (Throwable $e) {
        wp_send_json_error($e->getMessage());
    }
}

if (isset($_POST["req_type"]) && $_POST['req_type'] == 'movements_get_price_per_person') {
    $pricingId = isset($_POST["pricing_id"]) ? (int)$_POST["pricing_id"] : 0;
    if ($pricingId <= 0) {
        wp_send_json_error('pricing_id is required');
    }

    try {
        $data = call_backend_functions_api('/backend-functions/price-per-person', ['pricing_id' => $pricingId]);
        wp_send_json($data);
    } catch (Throwable $e) {
        wp_send_json_error($e->getMessage());
    }
}

if(isset($_POST["req_type"]) && $_POST['req_type'] == 'movements_getproductid' )
{
    $rawDate = sanitize_text_field($_POST["date"] ?? '');
    $tripcode = sanitize_text_field($_POST["tripcode"] ?? '');

    if ($tripcode === '' || $rawDate === '') {
        wp_send_json_error('tripcode and date are required');
    }

    $date = date('Y-m-d', strtotime($rawDate));

    try {
        $data = call_backend_functions_api('/backend-functions/product-info', [
            'tripcode' => $tripcode,
            'date' => $date,
        ]);
        wp_send_json($data);
    } catch (Throwable $e) {
        wp_send_json_error($e->getMessage());
    }
}

if (
    isset($_POST["get_paid_amount_for_adjustment_cs_g360"]) &&
    $_POST['get_paid_amount_for_adjustment_cs_g360'] == 'amount_adjustment'
) {
    $old_order_id = isset($_POST['old_order_id']) ? (int)$_POST['old_order_id'] : 0;
    if (!$old_order_id) {
        wp_send_json_error('Invalid order ID');
    }

    try {
        $data = call_backend_functions_api("/backend-functions/paid-amount/{$old_order_id}", ['type' => 'g360']);
        wp_send_json($data);
    } catch (Throwable $e) {
        wp_send_json_error($e->getMessage());
    }
}

if(isset($_POST["get_paid_amount_for_adjustment"]) && $_POST['get_paid_amount_for_adjustment'] == 'amount_adjustment' )
{
    $old_order_id = isset($_POST['old_order_id']) ? (int)$_POST['old_order_id'] : 0;
    if (!$old_order_id) {
        echo '0';
        exit;
    }

    try {
        $data = call_backend_functions_api("/backend-functions/paid-amount/{$old_order_id}", ['type' => 'simple']);
        echo (string)($data['total_paid'] ?? 0);
    } catch (Throwable $e) {
        echo '0';
    }
    exit;
}

if(isset($_POST["get_paid_amount_for_adjustment"]) && $_POST['get_paid_amount_for_adjustment'] == 'deposit_amount_adjustment_from_customerportal' )
{
    $old_order_id = isset($_POST['old_order_id']) ? (int)$_POST['old_order_id'] : 0;
    if (!$old_order_id) {
        echo '0';
        exit;
    }

    try {
        $data = call_backend_functions_api("/backend-functions/paid-amount/{$old_order_id}", ['type' => 'deadline']);
        echo (string)($data['total_paid'] ?? 0);
    } catch (Throwable $e) {
        echo '0';
    }
    exit;
}

if(isset($_POST["get_paid_amount_for_adjustment2"]) && $_POST['get_paid_amount_for_adjustment2'] == 'amount_adjustment2' )
{
    $old_order_id = isset($_POST['old_order_id2']) ? (int)$_POST['old_order_id2'] : 0;
    if (!$old_order_id) {
        echo '0';
        exit;
    }

    try {
        $data = call_backend_functions_api("/backend-functions/paid-amount/{$old_order_id}", ['type' => 'simple']);
        echo (string)($data['total_paid'] ?? 0);
    } catch (Throwable $e) {
        echo '0';
    }
    exit;
}



if (isset($_POST['ticketing_g360_notes_submission']) && $_POST['ticketing_g360_notes_submission'] == '1') {
            		    header('Content-Type: application/json');
            		    
    $payload = [
        'product_id' => sanitize_text_field($_POST['product_id_api'] ?? ''),
        'co_order_id' => sanitize_text_field($_POST['co_order_id_api'] ?? ''),
        'order_id' => (int)($_POST['order_id_api'] ?? 0),
        'category' => sanitize_text_field($_POST['categoryofnote'] ?? ''),
        'description' => sanitize_text_field($_POST['notedescription'] ?? ''),
        'department' => sanitize_text_field($_POST['department'] ?? ''),
        'note_column' => (isset($_GET['nobel']) && $_GET['nobel'] == '1') ? 'Noble' : '',
        'updated_by' => $currnt_userlogn,
    ];

    try {
        $data = call_backend_functions_api('/backend-functions/ticketing-note', [], [
            'method' => 'POST',
            'body' => $payload,
        ]);
        echo json_encode($data);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
                        exit;
                    }
                    
                  
if (isset($_POST['ticketing_g360_escalation_submission']) && $_POST['ticketing_g360_escalation_submission'] == '1') {
    header('Content-Type: application/json');

    $escalation_type = sanitize_text_field($_POST['escalation_type'] ?? '');
    if ($escalation_type === '') {
        echo json_encode(['status' => 'error', 'message' => 'Kindly add the escalation details.']);
        exit;
    }

    $input_note = sanitize_textarea_field($_POST['input_note'] ?? '');
    $escalation_to = sanitize_text_field($_POST['escalation_to'] ?? '');
    $followup_date = sanitize_text_field($_POST['followup_date'] ?? '');
    $airline = sanitize_text_field($_POST['airline'] ?? '');
    $fare_difference = sanitize_text_field($_POST['fare_difference'] ?? '');
    $new_option = sanitize_text_field($_POST['new_option'] ?? '');
    $other_note = sanitize_textarea_field($_POST['other_note'] ?? '');
    $order_id_api = sanitize_text_field($_POST['order_id_api'] ?? '');

    $uploadDirectory = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/customized_function_uploads/";
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $fileName = '';
    $fileName_2 = '';
    $allowed = ['jpeg', 'jpg', 'png', 'pdf'];

    if (isset($_FILES['existing_pnr_screenshot']) && $_FILES['existing_pnr_screenshot']['error'] === UPLOAD_ERR_OK) {
        $temp = explode(".", $_FILES['existing_pnr_screenshot']['name']);
        $ext = strtolower(end($temp));
        if (in_array($ext, $allowed) && $_FILES['existing_pnr_screenshot']['size'] <= 4000000) {
            $filename_time = date('ymdHis') . '_' . uniqid();
            $existingfilename = 'g360_escalation_' . $filename_time . '.' . $ext;
            $uploadPath = $uploadDirectory . $existingfilename;
            if (move_uploaded_file($_FILES['existing_pnr_screenshot']['tmp_name'], $uploadPath)) {
                $fileName = $existingfilename;
            }
        }
    }

    if (isset($_FILES['new_option_screenshot']) && $_FILES['new_option_screenshot']['error'] === UPLOAD_ERR_OK) {
        $temp = explode(".", $_FILES['new_option_screenshot']['name']);
        $ext = strtolower(end($temp));
        if (in_array($ext, $allowed) && $_FILES['new_option_screenshot']['size'] <= 4000000) {
            $filename_time = date('ymdHis') . '_' . uniqid();
            $existingfilename = 'g360_escalation_' . $filename_time . '.' . $ext;
            $uploadPath = $uploadDirectory . $existingfilename;
            if (move_uploaded_file($_FILES['new_option_screenshot']['tmp_name'], $uploadPath)) {
                $fileName_2 = $existingfilename;
            }
        }
    }

    $payload = [
        'order_id' => $order_id_api,
        'escalation_type' => $escalation_type,
        'note' => $input_note,
        'escalate_to' => $escalation_to,
        'escalated_by' => $currnt_userlogn,
        'followup_date' => $followup_date,
        'airline' => $airline,
        'fare_difference' => $fare_difference,
        'new_option' => $new_option,
        'existing_pnr_screenshot' => $fileName,
        'new_option_screenshot' => $fileName_2,
        'other_note' => $other_note,
    ];

    try {
        $result = call_backend_functions_api('/backend-functions/escalation', [], [
            'method' => 'POST',
            'body' => $payload,
        ]);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


if (isset($_POST['ticketing_g360_ticketing_submission']) && $_POST['ticketing_g360_ticketing_submission'] == '1') {
    header('Content-Type: application/json');

    if (!isset($_POST['name_replacement']) || !is_array($_POST['name_replacement'])) {
        echo json_encode(['status' => 'error', 'message' => 'No data of name_replacement sent!']);
        exit;
    }

    $payload = [
        'name_replacement' => $_POST['name_replacement'],
        'ticketing_request' => $_POST['ticketing_request'] ?? [],
        'updated_by' => $currnt_userlogn,
    ];

    try {
        $result = call_backend_functions_api('/backend-functions/ticketing-submission', [], [
            'method' => 'POST',
            'body' => $payload,
        ]);
        echo json_encode([
            'status' => 'success',
            'message' => 'Ticketing request updated successfully.',
            'rows' => $result['rows'] ?? [],
            'reload' => true,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
exit;
}





if (isset($_POST['action']) && $_POST['action'] === 'update_pax_field') {

    $payload = [
        'auto_id' => intval($_POST['auto_id'] ?? 0),
        'column' => sanitize_key($_POST['column'] ?? ''),
        'value' => sanitize_text_field($_POST['value'] ?? ''),
        'updated_by' => $currnt_userlogn,
    ];

    try {
        $result = call_backend_functions_api('/backend-functions/update-pax-field', [], [
            'method' => 'POST',
            'body' => $payload,
        ]);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// FIT Itinerary for DC in Customer Portal
if (isset($_POST['action']) && in_array($_POST['action'], [
    'get_flight_numbers',
    'get_flight_details',
    'get_flight_details_by_date',
    'get_origins_by_airline',
    'get_destinations_by_airline_origin',
    'get_flights_by_route',
], true)) {
    $payload = [
        'action' => $_POST['action'],
        'airline_code' => sanitize_text_field($_POST['airline_code'] ?? ''),
        'flight_number' => sanitize_text_field($_POST['flight_number'] ?? ''),
        'departure_date' => sanitize_text_field($_POST['departure_date'] ?? ''),
        'origin' => sanitize_text_field($_POST['origin'] ?? ''),
        'destination' => sanitize_text_field($_POST['destination'] ?? ''),
    ];

    try {
        $data = call_backend_functions_api('/backend-functions/fit-flights', [], [
            'method' => 'POST',
            'body' => $payload,
        ]);
        echo json_encode($data);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
        exit;
}