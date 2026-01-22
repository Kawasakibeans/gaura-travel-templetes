<?php
/**
 * Template Name: Manage Malpractice Call Audit
 * Template Post Type: post, page
 * Author: Karthik Peerlagudem
 * Created: 07, Septmeber 2023
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

date_default_timezone_set("Australia/Melbourne");
error_reporting(E_ALL);
$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';

global $wpdb, $current_user;

wp_get_current_user();
$current_username = $current_user->user_login ?? 'system';

// Check if this is an API request
$is_api_request = false;
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = array_filter(explode('/', trim($path, '/')));
$path_parts = array_values($path_parts);

// Check if path contains API indicators
for ($i = 0; $i < count($path_parts); $i++) {
    if ($path_parts[$i] === 'audit' && isset($path_parts[$i + 1]) && $path_parts[$i + 1] === 'malpractice-calls') {
        $is_api_request = true;
        break;
    }
}

// If API request, handle it and exit
if ($is_api_request) {
    header('Content-Type: application/json');
    
    // Helper functions
    function sendResponse($status, $data = null, $message = null, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'status' => $status,
            'data' => $data,
            'message' => $message
        ]);
        exit;
    }
    
    function sendError($message, $code = 500) {
        sendResponse('error', null, $message, $code);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Extract record ID from path
    $record_id = null;
    for ($i = 0; $i < count($path_parts); $i++) {
        if ($path_parts[$i] === 'malpractice-calls' && isset($path_parts[$i + 1]) && is_numeric($path_parts[$i + 1])) {
            $record_id = intval($path_parts[$i + 1]);
            break;
        }
    }
    
    try {
        // GET /v1/audit/malpractice-calls - List call audit records
        if ($method === 'GET' && $record_id === null) {
            $where = [];
            $whereValues = [];
            $whereFormats = [];
            
            // Campaign filter
            if (!empty($_GET['campaign'])) {
                $where[] = "LOWER(campaign) LIKE %s";
                $whereValues[] = '%' . strtolower(trim($_GET['campaign'])) . '%';
                $whereFormats[] = '%s';
            }
            
            // Agent name filter
            if (!empty($_GET['agent_name'])) {
                $where[] = "LOWER(agent_name) LIKE %s";
                $whereValues[] = '%' . strtolower(trim($_GET['agent_name'])) . '%';
                $whereFormats[] = '%s';
            }
            
            // Call date filter
            if (!empty($_GET['call_date'])) {
                $where[] = "call_date LIKE %s";
                $whereValues[] = '%' . trim($_GET['call_date']) . '%';
                $whereFormats[] = '%s';
            }
            
            // Recording file no filter
            if (!empty($_GET['recording_file_no'])) {
                $where[] = "recording_file_no LIKE %s";
                $whereValues[] = '%' . trim($_GET['recording_file_no']) . '%';
                $whereFormats[] = '%s';
            }
            
            // Original Query:
            // SELECT * FROM wpk4_backend_malpractice_audit
            // WHERE [filters based on query parameters]
            // ORDER BY id DESC
            // LIMIT 40
            
            $sql = "SELECT * FROM {$wpdb->prefix}backend_malpractice_audit";
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY id DESC LIMIT 40";
            
            if (!empty($whereValues)) {
                $prepared = $wpdb->prepare($sql, $whereValues);
                $results = $wpdb->get_results($prepared, ARRAY_A);
            } else {
                $results = $wpdb->get_results($sql, ARRAY_A);
            }
            
            // Format results
            $formatted_results = [];
            foreach ($results as $row) {
                $formatted_results[] = [
                    'id' => intval($row['id']),
                    'call_type' => $row['call_type'],
                    'telephone' => $row['telephone'],
                    'cc' => $row['cc'],
                    'campaign' => $row['campaign'],
                    'agent_name' => $row['agent_name'],
                    'status' => $row['status'],
                    'additional_status' => $row['additonal_status'],
                    'call_date' => $row['call_date'],
                    'call_time' => $row['call_time'],
                    'time_connect' => $row['time_connect'],
                    'time_acw' => $row['time_acw'],
                    'recording_file_no' => $row['recording_file_no'],
                    'observation' => $row['observation'],
                    'added_by' => $row['added_by'] ?? ''
                ];
            }
            
            sendResponse('success', $formatted_results, 'Call audit records retrieved successfully');
        }
        
        // GET /v1/audit/malpractice-calls/{id} - Get single record
        if ($method === 'GET' && $record_id !== null) {
            // Original Query:
            // SELECT * FROM wpk4_backend_malpractice_audit 
            // WHERE id = :id
            
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}backend_malpractice_audit WHERE id = %d",
                    $record_id
                ),
                ARRAY_A
            );
            
            if (!$row) {
                sendError('Record not found', 404);
            }
            
            $formatted_result = [
                'id' => intval($row['id']),
                'call_type' => $row['call_type'],
                'telephone' => $row['telephone'],
                'cc' => $row['cc'],
                'campaign' => $row['campaign'],
                'agent_name' => $row['agent_name'],
                'status' => $row['status'],
                'additional_status' => $row['additonal_status'],
                'call_date' => $row['call_date'],
                'call_time' => $row['call_time'],
                'time_connect' => $row['time_connect'],
                'time_acw' => $row['time_acw'],
                'recording_file_no' => $row['recording_file_no'],
                'observation' => $row['observation'],
                'added_by' => $row['added_by'] ?? ''
            ];
            
            sendResponse('success', $formatted_result, 'Call audit record retrieved successfully');
        }
        
        // POST /v1/audit/malpractice-calls - Create call audit record
        if ($method === 'POST' && $record_id === null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                $input = $_POST;
            }
            
            // Validate required fields
            $required = ['cc', 'telephone', 'call_type', 'campaign', 'agent_name', 'status', 'additional_status', 'call_date', 'call_time', 'time_connect', 'time_acw', 'recording_file_no'];
            $data = [];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    sendError("Field '{$field}' is required", 400);
                }
                $data[$field] = trim($input[$field]);
            }
            
            // Optional fields
            $data['observation'] = isset($input['observation']) ? trim($input['observation']) : '';
            
            // Original Query:
            // INSERT INTO wpk4_backend_malpractice_audit 
            // (telephone, call_type, campaign, agent_name, status, additonal_status, call_date, 
            //  call_time, time_connect, time_acw, recording_file_no, observation, cc, added_by)
            // VALUES 
            // (:telephone, :call_type, :campaign, :agent_name, :status, :additional_status, :call_date,
            //  :call_time, :time_connect, :time_acw, :recording_file_no, :observation, :cc, :added_by)
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'backend_malpractice_audit',
                [
                    'telephone' => $data['telephone'],
                    'call_type' => $data['call_type'],
                    'campaign' => $data['campaign'],
                    'agent_name' => $data['agent_name'],
                    'status' => $data['status'],
                    'additonal_status' => $data['additional_status'],
                    'call_date' => $data['call_date'],
                    'call_time' => $data['call_time'],
                    'time_connect' => $data['time_connect'],
                    'time_acw' => $data['time_acw'],
                    'recording_file_no' => $data['recording_file_no'],
                    'observation' => $data['observation'],
                    'cc' => $data['cc'],
                    'added_by' => $current_username
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                sendError('Failed to create record: ' . $wpdb->last_error, 500);
            }
            
            sendResponse('success', ['id' => $wpdb->insert_id, 'message' => 'Call audit record created successfully'], 'Call audit record created successfully', 201);
        }
        
        // PATCH /v1/audit/malpractice-calls/{id} - Update call audit record
        if ($method === 'PATCH' && $record_id !== null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                $input = $_POST;
            }
            
            $updateData = [];
            $updateFormats = [];
            
            // All fields are optional for update
            if (isset($input['cc'])) {
                $updateData['cc'] = trim($input['cc']);
                $updateFormats[] = '%s';
            }
            if (isset($input['telephone'])) {
                $updateData['telephone'] = trim($input['telephone']);
                $updateFormats[] = '%s';
            }
            if (isset($input['call_type'])) {
                $updateData['call_type'] = trim($input['call_type']);
                $updateFormats[] = '%s';
            }
            if (isset($input['campaign'])) {
                $updateData['campaign'] = trim($input['campaign']);
                $updateFormats[] = '%s';
            }
            if (isset($input['agent_name'])) {
                $updateData['agent_name'] = trim($input['agent_name']);
                $updateFormats[] = '%s';
            }
            if (isset($input['status'])) {
                $updateData['status'] = trim($input['status']);
                $updateFormats[] = '%s';
            }
            if (isset($input['additional_status'])) {
                $updateData['additonal_status'] = trim($input['additional_status']);
                $updateFormats[] = '%s';
            }
            if (isset($input['call_date'])) {
                $updateData['call_date'] = trim($input['call_date']);
                $updateFormats[] = '%s';
            }
            if (isset($input['call_time'])) {
                $updateData['call_time'] = trim($input['call_time']);
                $updateFormats[] = '%s';
            }
            if (isset($input['time_connect'])) {
                $updateData['time_connect'] = trim($input['time_connect']);
                $updateFormats[] = '%s';
            }
            if (isset($input['time_acw'])) {
                $updateData['time_acw'] = trim($input['time_acw']);
                $updateFormats[] = '%s';
            }
            if (isset($input['recording_file_no'])) {
                $updateData['recording_file_no'] = trim($input['recording_file_no']);
                $updateFormats[] = '%s';
            }
            if (isset($input['observation'])) {
                $updateData['observation'] = trim($input['observation']);
                $updateFormats[] = '%s';
            }
            
            if (empty($updateData)) {
                sendError('At least one field must be provided for update', 400);
            }
            
            $updateData['added_by'] = $current_username;
            $updateFormats[] = '%s';
            
            // Original Query:
            // UPDATE wpk4_backend_malpractice_audit 
            // SET telephone = :telephone,
            //     call_type = :call_type,
            //     call_date = :call_date,
            //     campaign = :campaign,
            //     agent_name = :agent_name,
            //     status = :status,
            //     additonal_status = :additional_status,
            //     time_connect = :time_connect,
            //     time_acw = :time_acw,
            //     recording_file_no = :recording_file_no,
            //     observation = :observation,
            //     cc = :cc,
            //     added_by = :added_by
            // WHERE id = :id
            
            $result = $wpdb->update(
                $wpdb->prefix . 'backend_malpractice_audit',
                $updateData,
                ['id' => $record_id],
                $updateFormats,
                ['%d']
            );
            
            if ($result === false) {
                sendError('Failed to update record: ' . $wpdb->last_error, 500);
            }
            
            sendResponse('success', ['message' => 'Call audit record updated successfully'], 'Call audit record updated successfully');
        }
        
        // DELETE /v1/audit/malpractice-calls/{id} - Delete call audit record
        if ($method === 'DELETE' && $record_id !== null) {
            // Original Query:
            // DELETE FROM wpk4_backend_malpractice_audit 
            // WHERE id = :id
            
            $result = $wpdb->delete(
                $wpdb->prefix . 'backend_malpractice_audit',
                ['id' => $record_id],
                ['%d']
            );
            
            if ($result === false) {
                sendError('Failed to delete record: ' . $wpdb->last_error, 500);
            }
            
            if ($result === 0) {
                sendError('Record not found', 404);
            }
            
            sendResponse('success', ['message' => 'Call audit record deleted successfully'], 'Call audit record deleted successfully');
        }
        
        // Route not found
        sendError('Endpoint not found', 404);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    }
}

// Continue with original template code for HTML output
get_header();

include("wp-config-custom.php");

// Create mysqli connection for backward compatibility with legacy code
// NOTE: This is ONLY for compatibility - all new code should use $wpdb
if (class_exists('mysqli') && !isset($mysqli)) {
    // Get database credentials from WordPress config
    $db_host = DB_HOST;
    $db_user = DB_USER;
    $db_password = DB_PASSWORD;
    $db_name = DB_NAME;
    
    // Extract host and port if needed
    $host_parts = explode(':', $db_host);
    $db_hostname = $host_parts[0];
    $db_port = isset($host_parts[1]) ? $host_parts[1] : 3306;
    
    // Create mysqli connection for legacy code compatibility
    $mysqli = @new mysqli($db_hostname, $db_user, $db_password, $db_name, $db_port);
    
    if ($mysqli->connect_errno) {
        // If mysqli connection fails, set to null
        $mysqli = null;
    }
}

// Original Query:
// SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address = ?
$query_ip_selection = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}backend_ip_address_checkup WHERE ip_address = %s",
    $ip_address
);
$result_ip_selection = $wpdb->get_results($query_ip_selection, ARRAY_A);
$is_ip_matched = count($result_ip_selection);
?>
<html>
<head>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQN+PtmoHDEXuppvnDJzQIu9" crossorigin="anonymous" />
    <!-- Boxicons  -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js" type="text/javascript"></script>
    <script
        src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js">
    </script>
    <!-- Custom styles -->
    <!-- <link href="./style/app.css" rel="stylesheet" type="text/css" /> -->
    <!-- Custom Script -->
    <!-- <script src="./script/app.js" defer></script> -->
    <style>
    .add-audit-btn {
        background-color: #cd2653;
        padding: 10px 20px;
        font-size: 13px;
        margin-bottom: 1%;
        border: none;
        font-weight: 600;
    }

    .add-audit-btn:hover {
        color: #cd2653;
        background-color: #16223e;
    }

    a {
        color: #cd2653;
        text-decoration: none;
    }

    a:hover {
        color: #16223e;
    }

    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type="number"] {
        -moz-appearance: textfield;
    }

    .form-header {
        text-align: center;
        margin-top: 1em;
        margin-bottom: 1.5em;
    }

    .form-body {
        padding: 0px 100px;
    }

    .invalid-msg {
        color: red;
    }

    .invalid-input {
        background-color: #f5d3d3;
    }

    .add-button {
        position: absolute;
        top: 10px;
        right: 20px;
    }

    .mandatory {
        color: #dc3545;
    }

    .dropdown:after {
        content: "<>";
        font: 1.75rem "Poppins", sans-serif;
        -webkit-transform: rotate(90deg);
        -moz-transform: rotate(90deg);
        -ms-transform: rotate(90deg);
        transform: rotate(90deg);
        right: 20px;
        top: 40px;
        padding: 0 0 2px;
        border-bottom: 1px solid #999;
        position: absolute;
        pointer-events: none;
    }

    .dropdown select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        display: block;
        line-height: 1.75rem;
        cursor: pointer;
        padding: 15px 18px;
        font-size: 1.6rem;
    }

    .dropdown select.field {
        width: 190px;
    }

    .alert-msg {
        position: absolute;
        width: 15%;
        float: right;
        bottom: 10px;
        right: 10px;
        opacity: 0;
        height: 5%;
        font-size: 17px;
        display: flex;
        justify-content: center;
    }

    .show-alert {
        opacity: 1;
        transition: ease-in;
    }

    .hide-alert {
        opacity: 0;
        transition: ease-out;
    }

    .form-button {
        padding: 12px 25px;
    }

    .cc-inline {
        display: inline-flex;
    }

    .cc {
        width: 20%;
        border-right: none;
    }

    .cc-tel {
        width: 300px;
        line-height: 0.9em;
    }

    .no-records-found {
        position: absolute;
        font-size: 20px;
        top: 50%;
        left: 40%;
    }
    </style>
    <script type="text/javascript">
        const goBack = () => window.history.back();
        window.addEventListener("load", function(event) {
            // date from the date range picker
            let defaultDate = document.getElementById('call-date').value;
            let curDate = moment(new Date()).format('YYYY-MM-DD');
    
            let customDatePicker = new DateRangePicker('call-date', {
                    startDate: defaultDate === "" ? curDate : defaultDate,
                    timePicker: false,
                    alwaysShowCalendars: true,
                    singleDatePicker: true,
                    maxDate: curDate,
                    autoApply: true,
                    autoUpdateInput: false,
                    locale: {
                        format: "YYYY-MM-DD",
                        seperator: "-"
                    }
                },
                function(start) {
                    document.getElementById("call-date").value = moment(start).format('YYYY-MM-DD')
                }
            )
        });

        const showAlert = (data) => {
            const alertElement = document.querySelector('.alert.alert-success');
            alertElement.innerHTML = "<i class='bx bx-check fs-1'></i><span>" + data + "</span>";
    
            alertElement.classList.remove('hide-alert');
            alertElement.classList.add('show-alert');
    
            setTimeout(() => {
                alertElement.classList.remove('show-alert');
                alertElement.classList.add('hide-alert');
            }, 2000)
        }

        const validateCallType = (id, inputData) => {
            const errorMessage = document.getElementById(id);
            let format = /[`!@#$%^&*()_+\-=\[\]{};':"\\|,<>\/?~]/;
            let isValid = true;
    
            if (inputData.trim() === "") {
                isValid = false;
            } else if (format.test(inputData.trim())) {
                isValid = false;
            }
    
            if (!isValid) {
                errorMessage.innerHTML = "<p class='invalid-msg'>please enter a valid call type</p>";
            } else {
                errorMessage.innerHTML = "";
                errorMessage.classList.remove('invalid-input');
            }
        }

        const callTypeOnBlurHandler = (event) => {
            validateCallType('callTypeErrorMessage', event.target.value);
        }
    
        const callTypeOnKeyPressHandler = (event) => {
            const errorMessage = document.getElementById('callTypeErrorMessage');
            errorMessage.innerHTML = "";
            errorMessage.classList.remove('invalid-input');
        }

        const validateTelephone = (id, inputData) => {
            const errorMessage = document.getElementById(id);
            const countryCode = document.getElementById('cc').value;
            let indianFormat = /^[6-9]{1}[0-9]{9}$/
            let ausFormat = /^[4]{1}[0-9]{8}$/
            let isValid = true;
    
            if (countryCode === '61') {
                if (inputData.trim() == 0 || inputData.length !== 9) {
                    isValid = false;
                } else if (!ausFormat.test(inputData.trim())) {
                    isValid = false;
                }
            } else {
                if (inputData.trim() == 0 || inputData.length !== 10) {
                    isValid = false;
                } else if (!indianFormat.test(inputData.trim())) {
                    isValid = false;
                }
            }
    
            if (!isValid) {
                errorMessage.innerHTML = "<p class='invalid-msg'>please enter a valid phone number</p>";
            } else {
                errorMessage.innerHTML = "";
                errorMessage.classList.remove('invalid-input');
            }
        }

        const telephoneOnBlurHandler = (event) => {
            validateTelephone("telephoneErrorMessage", event.target.value)
        }
    
        const telephoneOnKeyPressHandler = (event) => {
            const errorMessage = document.getElementById('telephoneErrorMessage');
            errorMessage.innerHTML = "";
            errorMessage.classList.remove('invalid-input');
        }
    
        const countryCodeOnChangeHandler = () => {
            validateTelephone('telephoneErrorMessage', document.getElementById('telephone').value)
        }
    
        function searchAuditData() {
            let campaign = document.getElementById("campaign_selector").value;
            let agent_name = document.getElementById("agent_name_selector").value;
            let call_date = document.getElementById("call-date").value;
            let recording_file_no = document.getElementById("recording_file_no_selector").value;
    
            window.location = '?campaign=' + campaign.toLowerCase() + '&agent_name=' + agent_name.toLowerCase() +
                '&call_date=' + call_date +
                '&recording_file_no=' + recording_file_no;
        }
    </script>
</head>
<body>
    <?php
    if (count($result_ip_selection) > 0)
    {
        global $current_user;
        $currnt_userlogn = $current_user->user_login;
        $user_roles = $current_user->roles;
        $user_role = array_shift($user_roles);
        ?>
        <div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
        <?php
        //if( current_user_can( 'administrator' ) || current_user_can( 'it_audit_team' ))
        {
        if (!isset($_GET['pg'])) 
        {
        ?>
            <!-- confirmation box -->
            <div id="confirm-delete-modal" class="modal fade">
                <div class="modal-dialog modal-confirm">
                    <div class="modal-content" style="padding: 20px 20px;">
                        <div class="modal-header flex-column">
                            <h4 class="modal-title w-100 fs-2" style="font-size: 25px;">Confirm Delete?</h4>
                        </div>
                        <div class="modal-body" style="margin-bottom: 25px;">
                            <p class="fs-4">Are you sure you want to premanently delete</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn form-button fs-4" data-dismiss="modal">Cancel</button>
                            <a href="#" id="confirm-delete" type="button"
                                class="btn btn-danger rounded form-button fs-4">Delete</a>
                        </div>
                    </div>
                </div>
            </div>
            <h1>Call Audit</h1>
            <table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
                <tr>
                    <td>Campaign
                        <input type='text' name='campaign_selector' value='<?php if (isset($_GET['campaign'])) {
                                                                                            echo $_GET['campaign'];
                                                                                        } ?>' id='campaign_selector'>
                    </td>
                    <td>Agent Name
                        <input type='text' name='agent_name_selector' value='<?php if (isset($_GET['agent_name'])) {
                                                                                                echo $_GET['agent_name'];
                                                                                            } ?>' id='agent_name_selector'>
                    </td>
                    <td>Call Date</br>
                        <input type='text' name='call_date_selector' value='<?php if (isset($_GET['call_date'])) {
                                                                                            echo $_GET['call_date'];
                                                                                        } ?>' id='call-date'>
                    </td>
                    <td>Recording File No
                        <input type='text' name='recording_file_no_selector' value='<?php if (isset($_GET['recording_file_no'])) {
                                                                                                    echo $_GET['recording_file_no'];
                                                                                                } ?>'
                            id='recording_file_no_selector'>
                    </td>
                </tr>
                <tr>
                    <td colspan='4' style='text-align:center;'>
                        <button style='padding:10px; margin:0;font-size:11px; ' id='search_orders'
                            onclick="searchAuditData()">Search</button>
                    </td>
                </tr>
            </table>
            <a id="add-audit" class="btn btn-primary float-end add-audit-btn" href='?pg=add'> <span><i class='bx bx-plus'></i> Add</a>
            <table class="table table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">Call Type</th>
                        <th scope="col">Telephone</th>
                        <th scope="col">Campaign</th>
                        <th scope="col">Agent Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Additonal Status</th>
                        <th scope="col">Call Date</th>
                        <th scope="col">Call Time</th>
                        <th scope="col">Time Connect</th>
                        <th scope="col">Time ACW</th>
                        <th scope="col">Recording file no</th>
                        <th scope="col">Observation</th>
                        <?php
                        if (current_user_can('administrator')) 
                        {
                            ?>
                            <th scope="col">Action</th>
                            <?php
                        }
                        ?>
                    </tr>
                </thead>
                <?php
                // Original Query: (dynamic query based on filters)
                // SELECT * FROM wpk4_backend_malpractice_audit WHERE [filters] ORDER BY id DESC LIMIT [limit]
                
                // Build WHERE conditions safely using $wpdb->prepare()
                $where = [];
                $where_values = [];
                
                if (isset($_GET['campaign']) && $_GET['campaign'] != '') {
                    $campaign = trim($_GET['campaign']);
                    $where[] = "LOWER(campaign) LIKE %s";
                    $where_values[] = '%' . strtolower($campaign) . '%';
                }
                
                if (isset($_GET['agent_name']) && $_GET['agent_name'] != '') {
                    $agent_name = trim($_GET['agent_name']);
                    $where[] = "LOWER(agent_name) LIKE %s";
                    $where_values[] = '%' . strtolower($agent_name) . '%';
                }
                
                if (isset($_GET['call_date']) && $_GET['call_date'] != '') {
                    $call_date = trim($_GET['call_date']);
                    $where[] = "call_date LIKE %s";
                    $where_values[] = '%' . $call_date . '%';
                }
                
                if (isset($_GET['recording_file_no']) && $_GET['recording_file_no'] != '') {
                    $recording_file_no = trim($_GET['recording_file_no']);
                    $where[] = "recording_file_no LIKE %s";
                    $where_values[] = '%' . $recording_file_no . '%';
                }
                
                // Build query
                $limit = (isset($_GET['campaign']) || isset($_GET['agent_name']) || isset($_GET['call_date']) || isset($_GET['recording_file_no'])) ? 40 : 10;
                
                if (!empty($where)) {
                    $sql = "SELECT * FROM {$wpdb->prefix}backend_malpractice_audit WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT " . intval($limit);
                    $query = $wpdb->prepare($sql, $where_values);
                } else {
                    $query = "SELECT * FROM {$wpdb->prefix}backend_malpractice_audit ORDER BY id DESC LIMIT " . intval($limit);
                }
                
                $results = $wpdb->get_results($query, ARRAY_A);

                if (!empty($results)) 
                {
                    foreach ($results as $row) 
                    {
                        echo "<tbody>";
                        echo "<tr>";
                        echo "<td>" . $row['call_type'] . "</td>";
                        echo "<td> +" . $row['cc'] . '-' . $row['telephone'] . "</td>";
                        echo "<td>" . $row['campaign'] . "</td>";
                        echo "<td>" . $row['agent_name'] . "</td>";
                        echo "<td>" . $row['status'] . "</td>";
                        echo "<td>" . $row['additonal_status'] . "</td>";
                        echo "<td>" . date("d/m/Y", strtotime($row['call_date'])) . "</td>";
                        echo "<td>" . date('h:i A', strtotime($row['call_time']))  . "</td>";
                        echo "<td>" . $row['time_connect'] . "</td>";
                        echo "<td>" . $row['time_acw'] . "</td>";
                        echo "<td>" . $row['recording_file_no'] . "</td>";
                        echo "<td>" . $row['observation'] . "</td>";
                        if (current_user_can('administrator')) {
                            echo "<td> <a href='?pg=update&id=" . $row['id'] . "'><i class='bx bx-edit' style='font-size: 20px;'' ></i></a> | <a href='#confirm-delete-modal' data-id='" . $row['id'] . "'  class='trigger-btn' data-toggle='modal'><i class='bx bx-trash' style='font-size: 20px;'></i> </a></td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody>";
                } else {
                    echo "<tbody></tbody>";
                    echo "<div class='no-records-found'> No Records found.</div>";
                }
                ?>
            </table>
            <script>
                $(document).on("click", ".trigger-btn", function() {
                    let recordId = $(this).data('id');
                    $(".modal-footer #confirm-delete").attr("href", '?pg=delete&id=' + recordId);
                    console.log($(".modal-footer #confirm-delete"))
                });
            </script>
            <?php
        }
        else 
        {
        if ($_GET['pg'] == 'update') 
        {
            ?>
            <!-- Start of Update Audit Data HTML -->
            <div class="alert alert-success alert-msg" role="alert"></div>
            <div class="container">
            <?php
            if (isset($_GET['id'])) 
            {
                $id = $_GET['id'];
                if ($_SERVER['REQUEST_METHOD'] === 'POST') 
                {
                    $cc = $_POST['cc'];
                    $telephone = $_POST['telephone'];
                    $call_type = $_POST['call_type'];
                    $campaign = $_POST['campaign'];
                    $agent_name = $_POST['agent_name'];
                    $status = $_POST['status'];
                    $additonal_status = $_POST['additional_status'];
                    $call_date = $_POST['call_date'];
                    $call_time = $_POST['call_time'];
                    $time_connect = $_POST['time_connect'];
                    $time_acw = $_POST['time_acw'];
                    $recording_file_no = $_POST['recording_file_no'];
                    $observation = $_POST['observation'];
                    
                    // Original Query:
                    // UPDATE wpk4_backend_malpractice_audit 
                    // SET telephone = :telephone, call_type = :call_type, call_date = :call_date,
                    //     campaign = :campaign, agent_name = :agent_name, status = :status,
                    //     additonal_status = :additional_status, time_connect = :time_connect,
                    //     time_acw = :time_acw, recording_file_no = :recording_file_no,
                    //     observation = :observation, cc = :cc, added_by = :added_by
                    // WHERE id = :id
                    
                    $result = $wpdb->update(
                        $wpdb->prefix . 'backend_malpractice_audit',
                        [
                            'telephone' => trim($telephone),
                            'call_type' => trim($call_type),
                            'call_date' => trim($call_date),
                            'campaign' => trim($campaign),
                            'agent_name' => trim($agent_name),
                            'status' => trim($status),
                            'additonal_status' => trim($additonal_status),
                            'time_connect' => trim($time_connect),
                            'time_acw' => trim($time_acw),
                            'recording_file_no' => trim($recording_file_no),
                            'observation' => trim($observation),
                            'cc' => trim($cc),
                            'added_by' => $current_username
                        ],
                        ['id' => intval($id)],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                        ['%d']
                    );

                    if ($result !== false) {
                        echo '<script> showAlert("Updated data sucsessfully"); window.location.href="?"</script>';
                    } else {
                        echo "Error updating record: " . $wpdb->last_error;
                    }
                }
                
                // Original Query:
                // SELECT * FROM wpk4_backend_malpractice_audit WHERE id = :id
                
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}backend_malpractice_audit WHERE id = %d",
                        intval($id)
                    ),
                    ARRAY_A
                );
                
                if ($row) 
                {
                    ?>
                    <h1 class="mt-4 form-header">Update Call Audit</h1>
                    <form class="form-body" method="POST" action='?pg=update&id=<?php echo $id; ?>'>
                        <div class="row pb-3">
                            <div class="form-group dropdown">
                                <label for="agent-name">Agent Name</label>
                                <span class="mandatory fs-3"> *</span>
                                <select id="agent-name" class="form-control" name="agent_name"
                                    data-selected="<?php echo $row['agent_name']; ?>">
                                    <option value="">Choose Agent Name</option>
                                    <?php
                                    // Original Query:
                                    // SELECT * FROM wpk4_backend_agent_codes WHERE status = 'active' ORDER BY agent_name ASC
                                    $query_agent_codes = $wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}backend_agent_codes WHERE status = %s ORDER BY agent_name ASC",
                                        'active'
                                    );
        								$result_agent_codes = $wpdb->get_results($query_agent_codes, ARRAY_A);
        								foreach ($result_agent_codes as $row_agent_codes)
        								{
                                            ?>
                                            <option value="<?php echo $row_agent_codes['agent_name']; ?>"><?php echo $row_agent_codes['agent_name']; ?></option>
                                            <?php
        								}
        							?>
                                </select>
                            </div>
                        </div>

                        <div class="row pb-3">
                            <div class="col">
                                <div class="form-group">
                                    <label for="call-type">Call Type</label>
                                    <span class="mandatory"> *</span>
                                    <input type="text" class="form-control" id="call-type" placeholder="Enter Call Type"
                                        name="call_type" onblur="callTypeOnBlurHandler(event)"
                                        onkeydown="callTypeOnKeyPressHandler(event)" required
                                        value="<?php echo $row['call_type']; ?>" />
                                    <div id="callTypeErrorMessage"></div>
                                </div>
                            </div>
        
                            <div class="col">
                                <div class="form-group">
                                    <label for="telephone">Telephone</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <div class="cc-inline">
        
                                        <select id="cc" class="form-control cc fs-4" name="cc"
                                            onchange="countryCodeOnChangeHandler()" required
                                            data-selected="<?php echo $row['cc']; ?>">
                                            <option value="61" selected>+61</option>
                                            <option value="91">+91</option>
                                        </select>
        
                                        <input type="number" class="form-control cc-tel" id="telephone"
                                            placeholder="Enter Telephone" name="telephone"
                                            onblur="telephoneOnBlurHandler(event)" onkeydown="telephoneOnKeyPressHandler(event)"
                                            value="<?php echo $row['telephone']; ?>" required />
                                    </div>
        
                                    <div id="telephoneErrorMessage"></div>
                                </div>
                            </div>
        
                            <div class="col">
                                <div class="form-group dropdown w-100">
                                    <label for="campaign">Campaign</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <select id="campaign" class="form-control" name="campaign"
                                        data-selected="<?php echo $row['campaign']; ?>" required>
                                        <option value="" selected>Choose Status</option>
                                        <option value="GTMD">GTMD</option>
                                        <option value="GTCB">GTCB</option>
                                    </select>
                                </div>
                            </div>
        
                        </div>
        
                        <div class="row pb-3">
                            <div class="col">
                                <div class="form-group dropdown w-100">
                                    <label for="status">Status</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <select id="status" class="form-control" name="status"
                                        data-selected="<?php echo $row['status']; ?>">
                                        <option value="A">A</option>
                                        <option value="AB">AB</option>
                                        <option value="AD">AD</option>
                                        <option value="CB">CB</option>
                                        <option value="CT">CT</option>
                                        <option value="DB">DB</option>
                                        <option value="DD">DD</option>
                                        <option value="OB">OB</option>
                                        <option value="SL">SL</option>
                                        <option value="TF">TF</option>
                                        <option value="EU">EU</option>
                                    </select>
                                </div>
        
                            </div>
                            <div class="col">
                                <div class="form-group dropdown w-100">
                                    <label for="status">Additional Status</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <select id="additional-status" class="form-control" name="additional_status"
                                        data-selected="<?php echo $row['additonal_status']; ?>">
                                        <option value="">Choose Status</option>
                                        <option value="A">A</option>
                                        <option value="AB">AB</option>
                                        <option value="AD">AD</option>
                                        <option value="CB">CB</option>
                                        <option value="CT">CT</option>
                                        <option value="DB">DB</option>
                                        <option value="DD">DD</option>
                                        <option value="OB">OB</option>
                                        <option value="SL">SL</option>
                                        <option value="TF">TF</option>
                                        <option value="EU">EU</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group w-100">
                                    <label for="call-date">Call Date</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <input type="text" class="form-control" id="call-date" placeholder="YYYY-MM-DD"
                                        name="call_date" value="<?php echo $row['call_date']; ?>" required>
                                </div>
                            </div>
        
                        </div>
        
                        <div class="row pb-3">
                            <div class="col">
                                <div class="form-group w-100">
                                    <label for="call-time">Call Time</label>
                                    <span class="mandatory fs-3"> *</span>
                                    <input type="time" class="form-control" id="call-time" placeholder="Enter Call Time"
                                        name="call_time" value="<?php echo $row['call_time']; ?>" required>
                                    <!-- <input type="text" class="form-control" id="call-time" placeholder="hh:mm" pattern="[0-9]{2}:[0-9]{2}" name="call_time" value="<?php echo $row['call_time']; ?>" required>
                                                        </div> -->
                                </div>
                                <div class="col">
                                    <div class="form-group w-100">
                                        <label for="time-connect">Time Connect</label>
                                        <span class="mandatory fs-3"> *</span>
                                        <input type="text" class="form-control" id="time-connect" placeholder="hh:mm:ss"
                                            pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" name="time_connect"
                                            value="<?php echo $row['time_connect']; ?>" required>
                                    </div>
                                </div>
        
                                <div class="col">
                                    <div class="form-group w-100">
                                        <label for="time-ACW">Time ACW</label>
                                        <span class="mandatory fs-3"> *</span>
                                        <input type="text" class="form-control" id="time-ACW" placeholder="hh:mm:ss"
                                            pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" name="time_acw"
                                            value="<?php echo $row['time_acw']; ?>" required>
                                    </div>
                                </div>
        
                                <div class="col">
                                    <div class="form-group w-100">
                                        <label for="recording-file-no">Recording file no</label>
                                        <span class="mandatory fs-3"> *</span>
                                        <input type="number" class="form-control" id="recording-file-no"
                                            placeholder="Enter Recording file no" name="recording_file_no"
                                            value="<?php echo $row['recording_file_no']; ?>" required>
                                    </div>
                                </div>
        
                            </div>
        
                            <div class="row pb-3">
                                <div class="form-group">
                                    <label for="observation">Observation</label>
                                    <textarea type="text" class="form-control fs-4" id="observation" name="observation"
                                        placeholder="enter observation"><?php echo $row['observation']; ?></textarea>
                                </div>
                            </div>
        
                            <div class="float-end">
                                <button id="cancel" class="btn form-button fs-4" type="button"
                                    onclick="goBack()">Cancel</button>
                                <button class="btn btn-primary rounded form-button fs-4 add-audit-btn"
                                    type="submit">Update</button>
        
                            </div>
                        </form>
                    </div>
                    <script type="text/javascript">
                        document.getElementById('cancel').addEventListener('click', () => {
                            window.location.href = "?"
                        })
                
                        $("#agent-name").val($("#agent-name").data("selected")).change();
                        $("#campaign").val($("#campaign").data("selected")).change();
                        $("#status").val($("#status").data("selected")).change();
                        $("#additional-status").val($("#additional-status").data("selected")).change();
                        $("#cc").val($("#cc").data("selected")).change();
                    </script>
                    <?php
                } else {
                    echo "Record not found.";
                }
            }
            ?>
            <!-- End of Update Audit Data HTML -->
            <?php
        } 
        else if ($_GET['pg'] == 'add') 
        {
        ?>
            <!-- Start of Add New Audit Data HTML -->
            <div class="alert alert-success alert-msg" role="alert"></div>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $cc = $_POST['cc'];
                $telephone = $_POST['telephone'];
                $call_type = $_POST['call_type'];
                $campaign = $_POST['campaign'];
                $agent_name = $_POST['agent_name'];
                $status = $_POST['status'];
                $additional_status = $_POST['additional_status'];
                $call_date = $_POST['call_date'];
                $call_time = $_POST['call_time'];
                $time_connect = $_POST['time_connect'];
                $time_acw = $_POST['time_acw'];
                $recording_file_no = $_POST['recording_file_no'];
                $observation = $_POST['observation'];
                
                // Original Query:
                // INSERT INTO wpk4_backend_malpractice_audit 
                // (telephone, call_type, campaign, agent_name, status, additonal_status, call_date, 
                //  call_time, time_connect, time_acw, recording_file_no, observation, cc, added_by)
                // VALUES 
                // (:telephone, :call_type, :campaign, :agent_name, :status, :additional_status, :call_date,
                //  :call_time, :time_connect, :time_acw, :recording_file_no, :observation, :cc, :added_by)
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'backend_malpractice_audit',
                    [
                        'telephone' => trim($telephone),
                        'call_type' => trim($call_type),
                        'campaign' => trim($campaign),
                        'agent_name' => trim($agent_name),
                        'status' => trim($status),
                        'additonal_status' => trim($additional_status),
                        'call_date' => trim($call_date),
                        'call_time' => trim($call_time),
                        'time_connect' => trim($time_connect),
                        'time_acw' => trim($time_acw),
                        'recording_file_no' => trim($recording_file_no),
                        'observation' => trim($observation),
                        'cc' => trim($cc),
                        'added_by' => $current_username
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($result !== false) 
                {
                    echo '<script>  showAlert("Inserted data successfully"); window.location.href= "?"</script>';
                } else {
                    echo "Error inserting record: " . $wpdb->last_error;
                }
            }
        ?>
        <div class="container">
            <h1 class="mt-4 form-header">New Call Audit</h1>
            <form id="audit-form" class="form-body" method="POST" action="?pg=add">
                <div class="row pb-3">
                    <div class="form-group dropdown">
                        <label for="agent-name">Agent Name</label>
                        <span class="mandatory fs-3"> *</span>
                        <select id="agent-name" class="form-control" name="agent_name">
                            <option value="" selected>Choose Agent Name</option>
                            <?php
                            // Original Query:
                            // SELECT * FROM wpk4_backend_agent_codes WHERE status = 'active' ORDER BY agent_name ASC
                            $query_agent_codes = $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}backend_agent_codes WHERE status = %s ORDER BY agent_name ASC",
                                'active'
                            );
								$result_agent_codes = $wpdb->get_results($query_agent_codes, ARRAY_A);
								foreach ($result_agent_codes as $row_agent_codes)
								{
                                    ?>
                                    <option value="<?php echo $row_agent_codes['agent_name']; ?>"><?php echo $row_agent_codes['agent_name']; ?></option>
                                    <?php
								}
								?>
                        </select>
                    </div>
                </div>
                <div class="row pb-3">
                    <div class="col">
                        <div class="form-group w-100">
                            <label for="call-type">Call Type</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="text" class="form-control" id="call-type" placeholder="Enter Call Type"
                                name="call_type" onblur="callTypeOnBlurHandler(event)"
                                onkeydown="callTypeOnKeyPressHandler(event)" required />
                            <div id="callTypeErrorMessage"></div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label for="telephone">Telephone</label>
                            <span class="mandatory fs-3"> *</span>
                            <div class="cc-inline">

                                <select id="cc" class="form-control cc fs-4" name="cc"
                                    onchange="countryCodeOnChangeHandler()" required>
                                    <option value="61" selected>+61</option>
                                    <option value="91">+91</option>
                                </select>

                                <input type="number" class="form-control cc-tel" id="telephone"
                                    placeholder="Enter Telephone" name="telephone"
                                    onblur="telephoneOnBlurHandler(event)" onkeydown="telephoneOnKeyPressHandler(event)"
                                    required />
                            </div>

                            <div id="telephoneErrorMessage"></div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group dropdown w-100">
                            <label for="campaign">Campaign</label>
                            <span class="mandatory fs-3"> *</span>
                            <select id="campaign" class="form-control" name="campaign" required>
                                <option value="" selected>Choose Status</option>
                                <option value="GTMD">GTMD</option>
                                <option value="GTCB">GTCB</option>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="row pb-3">
                    <div class="col">
                        <div class="form-group dropdown w-100">
                            <label for="status">Status</label>
                            <span class="mandatory fs-3"> *</span>
                            <select id="status" class="form-control" name="status">
                                <option value="" selected>Choose Status</option>
                                <option value="A">A</option>
                                <option value="AB">AB</option>
                                <option value="AD">AD</option>
                                <option value="CB">CB</option>
                                <option value="CT">CT</option>
                                <option value="DB">DB</option>
                                <option value="DD">DD</option>
                                <option value="OB">OB</option>
                                <option value="SL">SL</option>
                                <option value="TF">TF</option>
                                <option value="EU">EU</option>
                            </select>
                        </div>

                    </div>
                    <div class="col">
                        <div class="form-group dropdown w-100">
                            <label for="status">Additional Status</label>
                            <select id="additional-status" class="form-control" name="additional_status">
                                <option value="" selected>Choose Status</option>
                                <option value="A">A</option>
                                <option value="AB">AB</option>
                                <option value="AD">AD</option>
                                <option value="CB">CB</option>
                                <option value="CT">CT</option>
                                <option value="DB">DB</option>
                                <option value="DD">DD</option>
                                <option value="OB">OB</option>
                                <option value="SL">SL</option>
                                <option value="TF">TF</option>
                                <option value="EU">EU</option>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group w-100">
                            <label for="call-date">Call Date</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="text" class="form-control" id="call-date" placeholder="YYYY-MM-DD"
                                name="call_date" required>
                        </div>
                    </div>

                </div>

                <div class="row pb-3">
                    <div class="col">
                        <div class="form-group w-100">
                            <label for="call-time">Call Time</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="time" class="form-control" id="call-time" placeholder="hh:mm" name="call_time"
                                required>
                            <!-- <input type="text" class="form-control" id="call-time" placeholder="hh:mm" pattern="[0-9]{2}:[0-9]{2}" name="call_time" required> -->
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group w-100">
                            <label for="time-connect">Time Connect</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="text" class="form-control" id="time-connect" placeholder="hh:mm:ss"
                                pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" name="time_connect" required>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group w-100">
                            <label for="time-ACW">Time ACW</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="text" class="form-control" id="time-ACW" placeholder="hh:mm:ss"
                                pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" name="time_acw" required>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group w-100">
                            <label for="recording-file-no">Recording file no</label>
                            <span class="mandatory fs-3"> *</span>
                            <input type="number" class="form-control" id="recording-file-no"
                                placeholder="Enter Recording file no" name="recording_file_no" required>
                        </div>
                    </div>

                </div>

                <div class="row pb-3">
                    <div class="form-group">
                        <label for="observation">Observation</label>
                        <textarea type="text" class="form-control fs-4" id="observation" name="observation"
                            placeholder="enter observation"> </textarea>
                    </div>
                </div>

                <div class="float-end">
                    <button id="cancel" class="btn form-button fs-4" type="button" onclick="goBack()"> Cancel</button>
                    <button class="btn btn-primary rounded form-button fs-4 add-audit-btn" type="submit">
                        Submit</button>

                </div>
            </form>

        </div>

        <div class="alert alert-success alert-msg" role="alert"><span> <i class='bx bx-check'></i> </span>Audit Data
            added Successfully.</div>

        <!-- End if Add New Audit Data HTML -->

        <!-- Start of Delete Audit Record -->
        <?php
                    } else if ($_GET['pg'] == 'delete') {
            ?>
        <div class="alert alert-success alert-msg" role="alert"></div>
        <?php
                        if (isset($_GET['id'])) {
                            $id = $_GET['id'];

                            // Original Query:
                            // DELETE FROM wpk4_backend_malpractice_audit 
                            // WHERE id = :id
                            
                            $result = $wpdb->delete(
                                $wpdb->prefix . 'backend_malpractice_audit',
                                ['id' => intval($id)],
                                ['%d']
                            );

                            if ($result !== false && $result > 0) {
                                echo "<script> showAlert('Deleted data Sucessfully'); window.location.href='?'</script>";
                            } else {
                                echo "Error deleting record: " . $wpdb->last_error;
                            }
                        }
                ?>
        <!-- End of Delete Audit Record -->
        <?php
                    }
                }
            }
        } else {
            echo "<center>This page is not accessible for you.</center>";
        }
?>
    </div>
</body>
<?php get_footer(); ?>