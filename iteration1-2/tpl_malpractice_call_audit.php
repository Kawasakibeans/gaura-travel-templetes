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
get_header();

date_default_timezone_set("Australia/Melbourne");
error_reporting(E_ALL);
include("wp-config-custom.php");

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
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
    if (mysqli_num_rows($result_ip_selection) > 0)
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
                $query = "SELECT * FROM wpk4_backend_malpractice_audit WHERE";

                if ( isset($_GET['campaign']) && $_GET['campaign'] != '' ) 
                {
                    $campaign = $_GET['campaign'];
                    $campaign_sq = " LOWER(campaign) LIKE '%$campaign%' AND";
                    $query = $query . $campaign_sq;
                } 
                else 
                {
                    $campaign_sq = "id!='TEST_DMP_ID' AND ";
                }

                if ( isset($_GET['agent_name']) && $_GET['agent_name'] != '' )
                {
                    $agent_name = $_GET['agent_name'];
                    $agent_name_sq = " LOWER(agent_name) LIKE '%$agent_name%' AND";
                    $query = $query . $agent_name_sq;
                } 
                else
                {
                    $agent_name_sq = "id!='TEST_DMP_ID' AND ";
                }

                if ( isset($_GET['call_date']) && $_GET['call_date'] != '' ) {
                    $call_date = $_GET['call_date'];
                    $call_date_sq = " call_date LIKE '%$call_date%' AND";
                    $query = $query . $call_date_sq;
                } else {
                    $call_date_sq = "id!='TEST_DMP_ID' AND ";
                }

                if ( isset($_GET['recording_file_no']) && $_GET['recording_file_no'] != '' ) {
                    $recording_file_no = $_GET['recording_file_no'];
                    $recording_file_no_sq = " recording_file_no LIKE '%$recording_file_no%' ";
                    $query = $query . $call_date_sq;
                } else {
                    $recording_file_no_sq = "id!='TEST_DMP_ID' ";
                }

                if (isset($_GET['campaign']) || isset($_GET['agent_name']) || isset($_GET['call_date']) || isset($_GET['recording_file_no']) ) {
                    $query = "SELECT * FROM wpk4_backend_malpractice_audit where $campaign_sq $agent_name_sq $call_date_sq $recording_file_no_sq order by id DESC limit 40";
                } else {
                    $query = "SELECT * FROM wpk4_backend_malpractice_audit order by id DESC limit 10";
                }

                $result = $mysqli->query($query);

                if ($result->num_rows > 0) 
                {
                    while ($row = $result->fetch_assoc()) 
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
                $mysqli->close();
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
                    
                    $sql = "UPDATE wpk4_backend_malpractice_audit SET telephone  = '$telephone',  call_type  = '$call_type', call_date= '$call_date'  ,campaign  = '$campaign',  agent_name  = '$agent_name',  status  = '$status',  additonal_status  = '$additonal_status',  time_connect  = '$time_connect',  time_acw  = '$time_acw',  recording_file_no  = '$recording_file_no',  observation  = '$observation', cc = '$cc', added_by='$currnt_userlogn' WHERE id=$id";

                    if ($mysqli->query($sql) === TRUE) {
                        echo '<script> showAlert("Updated data sucsessfully"); window.location.href="?"</script>';
                    } else {
                        echo "Error updating record: " . $mysqli->error;
                    }
                }
                $sql = "SELECT * FROM wpk4_backend_malpractice_audit WHERE id=$id";
                $result = $mysqli->query($sql);
                if ($result->num_rows > 0) 
                {
                    $row = $result->fetch_assoc();
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
                                    $query_agent_codes = "SELECT * FROM wpk4_backend_agent_codes where status = 'active' order by agent_name asc";
        								$result_agent_codes = mysqli_query($mysqli, $query_agent_codes);
        								while($row_agent_codes = mysqli_fetch_assoc($result_agent_codes))
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
                $mysqli->close();
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
                
                $sql = "INSERT INTO wpk4_backend_malpractice_audit (telephone, call_type, campaign, agent_name, status, additonal_status, call_date, call_time, time_connect, time_acw, recording_file_no, observation, cc, added_by)
                    VALUES ('$telephone', '$call_type', '$campaign', '$agent_name', '$status', '$additional_status', '$call_date', '$call_time', '$time_connect', '$time_acw', '$recording_file_no', '$observation', '$cc', '$currnt_userlogn');";

                if ($mysqli->query($sql) === TRUE) 
                {
                    echo '<script>  showAlert("Inserted data successfully"); window.location.href= "?"</script>';
                } else {
                    echo "Error updating record: " . $mysqli->error;
                }
            }
            //$mysqli->close();
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
                            $query_agent_codes = "SELECT * FROM wpk4_backend_agent_codes where status = 'active' order by agent_name asc";
								$result_agent_codes = mysqli_query($mysqli, $query_agent_codes);
								while($row_agent_codes = mysqli_fetch_assoc($result_agent_codes))
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

                            $sql = "DELETE FROM wpk4_backend_malpractice_audit WHERE id=$id";

                            if ($mysqli->query($sql) === TRUE) {
                                echo "<script> showAlert('Deleted data Sucessfully'); window.location.href='?'</script>";
                            } else {
                                echo "Error deleting record: " . $mysqli->error;
                            }
                            $mysqli->close();
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