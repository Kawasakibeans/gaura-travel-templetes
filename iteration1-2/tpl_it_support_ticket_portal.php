<?php
/**
 * Template Name: IT Support Ticket Portal
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

get_header();
header('Content-Type: text/html; charset=utf-8');

date_default_timezone_set("Australia/Melbourne"); 
$defaultlink_gaura = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
global $current_user; 
wp_get_current_user();
$first_name = $current_user->user_firstname;
$last_name = $current_user->user_lastname;
$login_email = $current_user->user_email;
$site_url = '';
$current_date_and_time = date("Y-m-d H:i:s");
include('wp-config-custom.php');
include('vendor/autoload.php');
$current_username = $current_user->user_login;
?>
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>
<!-- jQuery -->


<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables Core 1.13.6 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons Extension 2.4.1 -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>



    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_escalation'])) {
        $fname = $_POST['first_name'];
        $lname = $_POST['last_name'];
        $branch_location = $_POST['branch_location'];
        $department = $_POST['department'];
        $email = $_POST['email'];
        $type = $_POST['type'];
        $category = $_POST['category'];
        $sub_category = $_POST['subcategory'];
        $specification = $_POST['specification'];
        
        $escalate_to = $_POST['escalate_to'];
        $current_username = $current_user->user_login;
    $delegate_name = $_POST['delegate_name'] ?? '';

        $existing_pnr_screenshot = '';
        if (isset($_FILES['existing_pnr_screenshot']) && $_FILES['existing_pnr_screenshot']['error'] == 0) {
            $target_dir = "wp-content/uploads/customized_function_uploads/";
            $existing_pnr_screenshot = $target_dir . basename($_FILES["existing_pnr_screenshot"]["name"]);
            move_uploaded_file($_FILES["existing_pnr_screenshot"]["tmp_name"], $existing_pnr_screenshot);
        }

        $new_option_screenshot = '';
        if (isset($_FILES['new_option_screenshot']) && $_FILES['new_option_screenshot']['error'] == 0) {
            $target_dir = "wp-content/uploads/customized_function_uploads/";
            $new_option_screenshot = $target_dir . basename($_FILES["new_option_screenshot"]["name"]);
            move_uploaded_file($_FILES["new_option_screenshot"]["tmp_name"], $new_option_screenshot);
        }
    
$sql_insert_request = "INSERT INTO wpk4_backend_it_support_ticket_portal (
    fname, lname, branch_location, department, email, request_type, category, sub_category, specification, existing_pnr_screenshot, new_option_screenshot, escalate_to, escalate_by, delegate_name, status
) VALUES (
    '$fname', '$lname', '$branch_location', '$department', '$email', '$type', '$category', '$sub_category', '$specification', '$existing_pnr_screenshot', '$new_option_screenshot', '$escalate_to', '$current_username', '$delegate_name', 'pending'
)";

    
        if ($mysqli->query($sql_insert_request) === TRUE) {
            echo "<script>alert('Data has been saved successfully!');</script>";
        } else {
            echo "Error: " . $sql_insert_request . "<br>" . $mysqli->error;
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_web_escalation'])) {
        $fname = $_POST['first_name'];
        $lname = $_POST['last_name'];
        $branch_location = $_POST['branch_location'];
        $department = $_POST['department'];
        $email = $_POST['email'];
        $type = $_POST['type'];
        $category = $_POST['category'];
        $sub_category = $_POST['subcategory'];
        $specification = $_POST['specification'];
        
        $escalate_to = $_POST['escalate_to'];
        $current_username = $current_user->user_login;
    $delegate_name = $_POST['delegate_name'] ?? '';

        $existing_pnr_screenshot = '';
        if (isset($_FILES['existing_pnr_screenshot']) && $_FILES['existing_pnr_screenshot']['error'] == 0) {
            $target_dir = "wp-content/uploads/customized_function_uploads/";
            $existing_pnr_screenshot = $target_dir . basename($_FILES["existing_pnr_screenshot"]["name"]);
            move_uploaded_file($_FILES["existing_pnr_screenshot"]["tmp_name"], $existing_pnr_screenshot);
        }

        $new_option_screenshot = '';
        if (isset($_FILES['new_option_screenshot']) && $_FILES['new_option_screenshot']['error'] == 0) {
            $target_dir = "wp-content/uploads/customized_function_uploads/";
            $new_option_screenshot = $target_dir . basename($_FILES["new_option_screenshot"]["name"]);
            move_uploaded_file($_FILES["new_option_screenshot"]["tmp_name"], $new_option_screenshot);
        }
     
        $sql_insert_request = "INSERT INTO wpk4_backend_it_support_ticket_portal (
            fname, lname, branch_location, department, email, request_type, category, sub_category, specification, existing_pnr_screenshot, new_option_screenshot, escalate_to, escalate_by, delegate_name, status, sub_status
        ) VALUES (
            '$fname', '$lname', '$branch_location', '$department', '$email', '$type', '$category', '$sub_category', '$specification', '$existing_pnr_screenshot', '$new_option_screenshot', '$escalate_to', '$current_username', '$delegate_name', 'pending', 'Escalated to Web'
        )";

    
        if ($mysqli->query($sql_insert_request) === TRUE) {
            echo "<script>alert('Data has been saved successfully!');</script>";
        } else {
            echo "Error: " . $sql_insert_request . "<br>" . $mysqli->error;
        }
    }
    ?>
<div class='wpb_column vc_column_container vc_col-sm-12' style='width:90%;margin:auto;padding:100px 0px;'>
    <?php 
    
    if(!isset($_GET['option']))
    {
		include('data-table-classes.php');
		$query = "SELECT * FROM wpk4_backend_travel_bookings where order_id>126289 && order_id<500275 && t_type = 'return' && order_type = 'WPT'";
		$result = mysqli_query($mysqli, $query);
		while($row = mysqli_fetch_assoc($result))
		{
			$order_id = $row['order_id'];
			$queryg = "SELECT * FROM wpk4_backend_travel_bookings where order_id='$order_id' && t_type = 'return'";
			$resultg = mysqli_query($mysqli, $queryg);
			$rowf = mysqli_num_rows($resultg);
			if($rowf==1)
			{
				$sql_update_status = "UPDATE wpk4_backend_travel_bookings SET t_type='return' WHERE order_id='$order_id'";
				$result_status = mysqli_query($mysqli,$sql_update_status) or die(mysqli_error());
			}
		}
		?>
		<center>
		
		
		<?php
		//if( current_user_can( 'administrator' ) || current_user_can( 'sales_manager' ))
		{ ?>
		<a target='_blank' href='?option=dashboard'><button class='btn btn-success' style='width:170px; font-size:13px;'>View Request</button></a>
		<?php }
		?>
		</center>
        <br></br>
        <br></br>
        <style>
        .form-group-flex{
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .form-container {
            width: 100%;
            max-width: 900px;
            margin: auto;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 16px !important;
            background-color: #f9f9f9;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

   
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

 
        .form-group-flex {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        .input-group {
            flex: 1; 
            min-width: 100px;
            margin-bottom: 20px;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px !important;
            font-size: 16px;
        }

      
        textarea {
            min-height: 80px;
            resize: vertical;
        }

    
        input[type="file"] {
            padding: 8px;
            background-color: green;
            color: white;
            border-radius: 8px !important;
            cursor: pointer;
            width: 100%;
            max-width: 300px; 
        }

      
        .submit-button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            background-color: green;
            color: white;
            border: none;
            border-radius: 8px !important;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-button:hover {
            background-color: darkgreen;
        }

        
        @media screen and (max-width: 768px) {
            .form-group-flex {
                flex-direction: column; 
            }

            .input-group {
                width: 100%; 
            }

            input[type="file"] {
                max-width: 100%; 
            }
            .form-container {
                padding: 10px !important;
            }
            h2{
                font-size: 38px !important;
            }
        }

        </style>
        <div class="form-container">
            <h2 style="color: green;
                        letter-spacing: 1px;
                        margin: 0 auto 50px auto;
                        text-align: center;
                        font-weight: 500;
                        font-size: 48px;">IT Support Request Form</h2> 
            <div class="form-container">
                <form action="" method="post" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label for="full-name">Full Name</label>
                        <div class="form-group-flex">
                            <div class="input-group" style="margin-bottom: 5px;">
                                <span>First Name</span>
                                <input type="text" id="first-name" name="first_name" value="<?php echo $first_name; ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom: 5px;">
                                <span>Last Name</span>
                                <input type="text" id="last-name" name="last_name" value="<?php echo $last_name; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="branch-location">Branch Location</label>
                        <select id="branch-location" name="branch_location" style="width:100%; padding:10px;" required>
                            <option value="GT-BOM">GT-BOM</option>
                            <option value="TDU-BOM">TDU-BOM</option>
                            <option value="GT-CCU">GT-CCU</option>
                            <option value="TDU-CCU">TDU-CCU</option>
                            <option value="GT-MEL">GT-MEL</option>
                            <option value="TDU-MEL">TDU-MEL</option>
                            <option value="CMB-FlyLanka">CMB-FlyLanka</option>
                        </select>
                    </div>

                    <div class="form-group-flex">
                        <div class="input-group">
                            <label for="department">Department</label>
                            <select id="department" name="department" style="width:100%; padding:10px;" required>
                                <option value="Sales">Sales</option>
                                <option value="After-Sales">After-Sales</option>
                                <option value="Date change">Date change</option>
                                <option value="Quality Analysis">Quality Analysis</option>
                                <option value="HR">HR</option>
                                <option value="IT">IT</option>
                                <option value="IT Developer">IT Developer</option>
                                <option value="Accounting">Accounting</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="example@example.com" value="<?php echo $login_email; ?>" required>
                        </div>
                    </div>

                    <?php
                        $query_request_types = "SELECT DISTINCT request_type FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_request_types = mysqli_query($mysqli, $query_request_types);

                        $request_types = [];
                        while ($row = mysqli_fetch_assoc($result_request_types)) {
                            $request_types[] = $row['request_type'];
                        }

                        $query_categories = "SELECT DISTINCT category FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_categories = mysqli_query($mysqli, $query_categories);

                        $categories = [];
                        while ($row = mysqli_fetch_assoc($result_categories)) {
                            $categories[] = $row['category'];
                        }

                        $query_subcategories = "SELECT category, subcategory FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_subcategories = mysqli_query($mysqli, $query_subcategories);

                        $subcategories = [];
                        while ($row = mysqli_fetch_assoc($result_subcategories)) {
                            $subcategories[$row['category']][] = $row['subcategory'];
                        }
                        
                        
                    ?>

                    <div class="input-group">
                        <label for="type">Request Type:</label>
                        <select id="type" name="type" style="width:100%; padding:10px;" required>
                            <?php foreach ($request_types as $request_type): 
                            ?>
                                <option value="<?php echo htmlspecialchars($request_type); ?>"><?php echo htmlspecialchars($request_type); ?></option>
                            <?php
                            endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="category">Category:</label>
                        <select id="category" name="category" style="width:100%; padding:10px;" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="subcategory">Subcategory:</label>
                        <select id="subcategory" name="subcategory" style="width:100%; padding:10px;">
                            <option value="">Select Subcategory</option>
                            <?php if (isset($subcategories) && isset($subcategories[$row]) && isset($subcategories[$row['category']])): ?>
                                <?php foreach ($subcategories[$row['category']] as $subcategory): ?>
                                    <option value="<?php echo htmlspecialchars($subcategory); ?>"><?php echo htmlspecialchars($subcategory); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="specification">Please Specify</label>
                        <textarea id="specification" name="specification" required></textarea>
                    </div>

                    <div class="input-group">
                        <label for="existing_pnr_screenshot">Existing PNR Screenshot:</label>
                        <input type="file" id="existing_pnr_screenshot" name="existing_pnr_screenshot">
                    </div>

                    <!--<div class="input-group">-->
                    <!--    <label for="new_option_screenshot">New Option Screenshot:</label>-->
                    <!--    <input type="file" id="new_option_screenshot" name="new_option_screenshot">-->
                    <!--</div>-->

                    <div class="input-group" style='width: fit-content;'>
                        <label for="escalate_to">Escalate to:</label>
                        <select id="escalate_to" style="width:100%; padding:10px;" name="escalate_to" required>
                            <option value="">Select</option>
                            <option>IT</option>
                        </select>
                    </div>
<div class="input-group" style='width: fit-content;'>
    <label for="delegate_name">Assign Delegate:</label>
    <select id="delegate_name" name="delegate_name" style="width:100%; padding:10px;">
        <option value="">Select Delegate</option>
         <option value="santanud">santanud</option>
        <option value="SUBHAJIT.D">SUBHAJIT.D</option>
        <option value="SukritH">SukritH</option>
        <option value="BasudevN">BasudevN</option>
        <option value="Chandan.s">Chandan.s</option>
        <option value="debarun">debarun</option>
    </select>
</div>

                    <button type="submit" class="submit-button" name="submit_escalation">Submit</button>

                </form>
            </div>

        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                
                flatpickr("#request_date", {
                    enableTime: true,
                    dateFormat: "Y-m-d",
                    time_24hr: true,
                    allowInput: true,
                });
            });
            
            // // PHP array converted to JavaScript object
            
            var subcategories = JSON.parse(<?php echo json_encode($subcategories["Desktop Support"]); ?>);
            console.log(subcategories);
            // function updateSubcategories() {
            //     var categorySelect = document.getElementById("category");
            //     var subcategorySelect = document.getElementById("subcategory");
                
            //     var selectedCategory = categorySelect.value;
                
            //     // Clear existing subcategory options
            //     subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                
            //     // Check if the selected category exists in the subcategories object
            //     if (subcategories[selectedCategory]) {
            //         subcategories[selectedCategory].forEach(function(subcategory) {
            //             var option = document.createElement("option");
            //             option.value = subcategory;
            //             option.textContent = subcategory;
            //             subcategorySelect.appendChild(option);
            //         });
            //     }
            // }
        </script>
        <?php
            
            $query = "SELECT category, subcategory FROM wpk4_backend_it_support_ticket_portal_categories";
            $result = mysqli_query($mysqli, $query);

            $categories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[$row['category']][] = $row['subcategory'];
            }
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var subcategories = <?php echo json_encode($subcategories); ?>;
                
                var categorySelect = document.getElementById("category");
                var subcategorySelect = document.getElementById("subcategory");

                function updateSubcategories() {
                    var selectedCategory = categorySelect.value;
                    var options = subcategories[selectedCategory] || [];

                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

                    options.forEach(function(option) {
                        var optionElement = document.createElement("option");
                        optionElement.value = option;
                        optionElement.textContent = option;
                        subcategorySelect.appendChild(optionElement);
                    });
                }

                categorySelect.addEventListener("change", updateSubcategories);

                updateSubcategories();
            });
</script>
	<?php
    }

    if (isset($_GET['option']) && $_GET['option'] == 'dashboard' )
    {
    ?>
        <script>
            function searchordejs() 
            {
                var request_date = document.getElementById("request_date").value;
                var problem_category = document.getElementById("problem_category").value;	
                var department = document.getElementById("department").value;	
                var case_id = document.getElementById("case_id").value;	
                var status = document.getElementById("status").value;	
                
                
                window.location='?option=dashboard&request_date=' + request_date + '&problem_category=' + problem_category + '&department=' + department + '&case_id=' + case_id + '&status=' + status;
            }
        </script>
        <h2 style="color: green;">IT Support Request</h2>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables 1.13.6 + Bootstrap 5 Integration -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons 2.4.1 (for Export Excel, CSV, etc) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    
        
        <table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
            <tr>
                <td width='8%'>
                    Request Date</br>
                    <input type='text' name='request_date' value='<?php if(isset($_GET['request_date'])) { echo substr($_GET['request_date'], 0, 10); } ?>' id='request_date'>
                </td>
                <td width='8%'>
                    Problem Category</br>
                    <select name='problem_category' id='problem_category' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="" selected>All</option>
                        <?php
                        $query_problem_category = "SELECT DISTINCT request_type FROM wpk4_backend_it_support_ticket_portal";
                        $result_problem_category = mysqli_query($mysqli, $query_problem_category) or die(mysqli_error($mysqli));
                        while($row_problem_category = mysqli_fetch_assoc($result_problem_category))
                        {   
                            ?>
                            <option value="<?php echo $row_problem_category['request_type']; ?>" ><?php echo $row_problem_category['request_type']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td width='8%'>
                    Department </br>
                    <select name='department' id='department' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="" selected>All</option>
                        <?php
                        $query_department = "SELECT DISTINCT department FROM wpk4_backend_it_support_ticket_portal";
                        $result_department = mysqli_query($mysqli, $query_department) or die(mysqli_error($mysqli));
                        while($row_department = mysqli_fetch_assoc($result_department))
                        {   
                            ?>
                            <option value="<?php echo $row_department['department']; ?>" ><?php echo $row_department['department']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td width='8%'>
                    CASE ID</br>
                    <input type='text' name='case_id' value='<?php if(isset($_GET['case_id'])) { echo $_GET['case_id']; } ?>' id='case_id'>
                </td>
                <td width='8%'>
                    Status</br>
                    <select name='status' id='status' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="all" <?php if(isset($_GET['status']) && $_GET['status'] == 'all') { echo 'selected'; } ?>>All</option>
                        <option value="Pending" <?php if(isset($_GET['status']) && $_GET['status'] == 'Pending') { echo 'selected'; } ?>>Pending</option>
                        <option value="Under review" <?php if(isset($_GET['status']) && $_GET['status'] == 'Under review') { echo 'selected'; } ?>>Under review</option>
                        <option value="Escalated to HO" <?php if(isset($_GET['status']) && $_GET['status'] == 'Escalated to HO') { echo 'selected'; } ?>>Escalated to HO</option>
                        <option value="Awaiting HO" <?php if(isset($_GET['status']) && $_GET['status'] == 'Awaiting HO') { echo 'selected'; } ?>>Awaiting HO</option>
                        <option value="Revaluation" <?php if(isset($_GET['status']) && $_GET['status'] == 'Revaluation') { echo 'selected'; } ?>>Revaluation</option>
                        <option value="Completed" <?php if(isset($_GET['status']) && $_GET['status'] == 'Completed') { echo 'selected'; } ?>>Completed</option>
                        <option value="Rejected" <?php if(isset($_GET['status']) && $_GET['status'] == 'Rejected') { echo 'selected'; } ?>>Rejected</option>
                    </select>
                    
                </td>

            </tr>
            <tr>
                <td colspan="6" style='text-align:center;'>
                    <button style='padding:10px; margin:0;font-size:11px; background-color: green;' id='search_orders' onclick="searchordejs()">Search</button>
                </td>
            </tr>
        </table>

        <?php
            $request_date = ($_GET['request_date'] ?? false) ? substr($_GET['request_date'], 0, 10) : '' ;
            $problem_category = ($_GET['problem_category'] ?? false) ? $_GET['problem_category'] : '' ;
            $department = ($_GET['department'] ?? false) ? $_GET['department'] : '' ;
            $case_id = ($_GET['case_id'] ?? false) ? $_GET['case_id'] : '' ;
            $status_id = ($_GET['status'] ?? false) ? $_GET['status'] : '' ;
            $payment_type = ($_GET['payment_type'] ?? false) ? $_GET['payment_type'] : '' ;

       
            if(isset($request_date) && $request_date != '')
            {
                $request_date_sql = "created_at LIKE '".$request_date."%' AND ";
            }
            else
            {
                $request_date_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($problem_category) && $problem_category != '')
            {
                $problem_category_sql = "request_type = '".$problem_category."' AND ";
            }
            else
            {
                $problem_category_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($department) && $department != '')
            {
                $department_sql = "department = '".$department."' AND ";
            }
            else
            {
                $department_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($case_id) && $case_id != '')
            {
                $case_id_sql = "auto_id = '".$case_id."' AND ";
            }
            else
            {
                $case_id_sql = "auto_id IS NOT NULL AND ";
            }

          
            
            if(isset($status_id) && $status_id != '' && $status_id != 'all')
            {
                if($status_id == 'Pending') {
                    $status_id_sql = "(status NOT IN ('Completed', 'Rejected'))";
                } else {
                    $status_id_sql = "status = '".$status_id."'";
                }
            }
            else if ($status_id == 'all'){
                $status_id_sql = "auto_id IS NOT NULL";
            }
            else
            {
                $status_id_sql = "(status NOT IN ('Completed', 'Rejected'))";
            }

            if(
                (isset($request_date_sql) && $request_date_sql != '') ||
                (isset($problem_category_sql) && $problem_category_sql != '') ||
                (isset($department_sql) && $department_sql != '') ||
                (isset($case_id_sql) && $case_id_sql != '') ||
                (isset($status_id_sql) && $status_id_sql != '')
            ) 
            {
                $query = "SELECT *
                    FROM wpk4_backend_it_support_ticket_portal
                    where 
                        $request_date_sql
                        $problem_category_sql
                        $department_sql
                        $case_id_sql
                        $status_id_sql
                        AND (sub_status is NULL OR sub_status != 'Escalated to Web')
                    order by auto_id desc LIMIT 100";
                    //echo $query;
                    $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                    $row_counter_ticket = mysqli_num_rows($result);
            
                    if ($result && mysqli_num_rows($result) > 0): ?>
                        <style>
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                table-layout: auto; 
                            }
                            th, td {
                                border: 1px solid #ccc;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                white-space: nowrap; 
                            }
                            .remark-column {
                                width: 300px; 
                                word-wrap: break-word; 
                                word-break: break-all; 
                                white-space: normal;
                            }
                        </style>
                        <div style="margin-bottom: 20px; text-align:right;">
    <button id="exportExcel" class="btn btn-success btn-sm">Export Excel</button>
    <button id="exportCSV" class="btn btn-primary btn-sm">Export CSV</button>
</div>

<table id="tanviTable" class="table table-striped" style="font-size:14px; margin-top:35px;">
    <thead>
        <tr>
            <th>ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Branch Location</th>
            <th>Department</th>
            <th>Request Type</th>
            <th>Category</th>
            <th>Subcategory</th>
            <th>Escalate To</th>
            <th>Escalated By</th>
            <th>Status</th>
            <th class="remark-column">Remark</th>
            <th>Created At</th>
            <th>Updated</th>
            <th>Action</th> <!-- ðŸš€ -->
            <th>Responsible</th>

        </tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr data-rowinfo='<?php echo json_encode([
    'email' => $row['email'] ?? '',
    'specification' => $row['specification'] ?? '',
    'screenshot' => !empty($row['existing_pnr_screenshot']) ? 'https://gauratravel.com.au/' . $row['existing_pnr_screenshot'] : null,
    'auto_id' => $row['auto_id'] ?? '',
    'status' => $row['status'] ?? '',
    'priority' => $row['priority'] ?? '',
    'delegate_name' => $row['delegate_name'] ?? ''
], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>

                <td><?php echo $row['auto_id']; ?></td>
                <td><?php echo $row['fname']; ?></td>
                <td><?php echo $row['lname']; ?></td>
                <td><?php echo $row['branch_location']; ?></td>
                <td><?php echo $row['department']; ?></td>
                <td><?php echo $row['request_type']; ?></td>
                <td><?php echo $row['category']; ?></td>
                <td><?php echo $row['sub_category']; ?></td>
                <td><?php echo $row['escalate_to']; ?></td>
                <td><?php echo $row['escalate_by']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td class="remark-column">
                    <?php if(current_user_can('administrator') || current_user_can('sales_manager') || current_user_can('it_team') || current_user_can('ho_operations')): ?>
                        <form action="" method="post">
                            <input type="hidden" name="auto_id" value="<?php echo $row['auto_id']; ?>">
                            <textarea id="remark_<?php echo $row['auto_id']; ?>" name="remark" placeholder="<?php echo htmlspecialchars($row['remark']); ?>" style="width:100%; padding:4px; font-size:13px;"><?php echo htmlspecialchars($row['remark']); ?></textarea>
                            <button type="submit" name="update_remark" class="btn btn-primary" style="padding:10px; font-size:11px; background-color:green; margin-top:5px;">Save</button>
                            <button type="button" class="btn btn-secondary" onclick="copyRemark('<?php echo $row['auto_id']; ?>')" style="padding:10px; margin-left:5px; font-size:11px; background-color:#007bff; margin-top:5px;">Copy</button>
                        </form>
                    <?php else: ?>
                        <?php echo htmlspecialchars($row['remark']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_by']; ?> on <?php echo $row['updated_at']; ?></td>
                <td>
                    <button class="btn btn-info toggle-detail" style="padding:8px; border-radius:6px; background-color:green; color:white;">
                        <i class="fa-solid fa-angle-down"></i>
                    </button>
                    <?php
                    if($current_username == 'lee' || $current_username == 'sriharshans')
                    {
                    ?>
                    <a href="?option=escalate-to-web&id=<?php echo $row['auto_id']; ?>"><button class="btn btn-info" style="padding:8px; border-radius:6px; background-color:green; color:white;">
                        Escalate to Web
                    </button></a>
                    <?php
                    }
                    ?>
                </td>
                <td><?php echo !empty($row['delegate_name']) ? $row['delegate_name'] : 'Unassigned'; ?></td>

            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<!-- Copy Remark -->
<script>
function copyRemark(id) {
    const textarea = document.getElementById('remark_' + id);
    const text = textarea.value;

    navigator.clipboard.writeText(text)
    .then(() => {
        alert('Copied to clipboard!');
    })
    .catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy!');
    });
}
</script>

<!-- DataTable and Expandable Rows -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    var table = $('#tanviTable').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 15,
        order: [[0, 'desc']],  // <-- add this line (column index 0, descending)
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },
            {
                extend: 'csvHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            }
        ]
    });

  



    // Manual Button triggers
    $('#exportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    $('#exportCSV').on('click', function() {
        table.button('.buttons-csv').trigger();
    });
    // âœ… Action Expand/Collapse
    $('#tanviTable tbody').on('click', '.toggle-detail', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
      let rowData = {};
try {
    rowData = JSON.parse(tr.attr('data-rowinfo'));
} catch (e) {
    console.error("Invalid JSON in data-rowinfo", e);
    return;
}

        if (row.child.isShown()) {
            row.child.hide();
            $(this).find('i').removeClass('fa-angle-up').addClass('fa-angle-down');
            $(this).css('background-color', 'green');
        } else {
            var content = `
                <div style="padding:10px; background:#f9f9f9; border-radius:8px;">
                    <p><strong>Email:</strong> ${rowData.email ?? ''}</p>
                    <p><strong>Specification:</strong> ${rowData.specification ?? ''}</p>
                    ${rowData.screenshot ? `<a target="_blank" href="${rowData.screenshot}"><img src="${rowData.screenshot}" style="max-width:400px; margin-top:20px;"></a>` : '<p>No Screenshot Available</p>'}
                    <form method="post" style="margin-top:20px;">
                        <input type="hidden" name="request_id" value="${rowData.auto_id}">
                        <label>Status:</label>
                        <select name="status-select" style="padding:8px; margin-bottom:10px;">
                            <option ${rowData.status=='Pending'?'selected':''}>Pending</option>
                            <option ${rowData.status=='Under review'?'selected':''}>Under review</option>
                            <option ${rowData.status=='Escalated to HO'?'selected':''}>Escalated to HO</option>
                            <option ${rowData.status=='Awaiting HO'?'selected':''}>Awaiting HO</option>
                            <option ${rowData.status=='Revaluation'?'selected':''}>Revaluation</option>
                            <option ${rowData.status=='Completed'?'selected':''}>Completed</option>
                            <option ${rowData.status=='Rejected'?'selected':''}>Rejected</option>
                        </select>
                        <br>
                        <label>Priority:</label>
                        <select name="priority-select" style="padding:8px;">
                            <option ${rowData.priority=='High'?'selected':''}>High</option>
                            <option ${rowData.priority=='Medium'?'selected':''}>Medium</option>
                            <option ${rowData.priority=='Low'?'selected':''}>Low</option>
                        </select>
                        <br>
                        
                          <label>deligate by (responsible) </label>
                      <select name="delegate-select" style="padding:8px;">
    <option value="">Select Delegate</option>

        <option ${rowData.delegate_name=='santanud'?'selected':''}>santanud</option>
        <option ${rowData.delegate_name=='SUBHAJIT.D'?'selected':''}>SUBHAJIT.D</option>
        <option ${rowData.delegate_name=='SukritH'?'selected':''}>SukritH</option>
        <option ${rowData.delegate_name=='BasudevN'?'selected':''}>BasudevN</option>
        <option ${rowData.delegate_name=='Chandan.s'?'selected':''}>Chandan.s</option>
        <option ${rowData.delegate_name=='debarun'?'selected':''}>debarun</option>
        
    
</select>

                        <br><br>
                        <button type="submit" name="update-status" class="btn btn-success" style="padding:8px 20px;">Update</button>
                    </form>
                </div>
            `;
            row.child(content).show();
            $(this).find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
            $(this).css('background-color', 'orange');
        }
    });

});

</script>

                        <?php
                           
                            $query_subcategories = "SELECT status, COUNT(*) as count FROM wpk4_backend_it_support_ticket_portal GROUP BY status";
                            $result_subcategories = mysqli_query($mysqli, $query_subcategories) or die(mysqli_error($mysqli));

                            $subcategory_data = [];
                            $total_count = 0;
                            while ($row = mysqli_fetch_assoc($result_subcategories)) {
                                $subcategory_data[] = $row;
                                $total_count += $row['count'];
                            }

                       
                            $subcategory_data_json = json_encode($subcategory_data);
                            $total_count_json = json_encode($total_count);
                        ?>
                        
                        <div class="chart-container" style="width: 50%; margin: auto;">
                            <h2 id="chart-title" style="text-align: center; margin-top: 100px; margin-bottom: 0;"></h2>
                            <center> <canvas id="subcategoryChart"></canvas> <center>
                        </div>
                        <style>
                            canvas#subcategoryChart {
                                margin-top: -90px;
                            }
                            .orange-text {
                                color: orange;
                            }
                        </style>


                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                // Get chart data from PHP
                                const subcategoryData = <?php echo $subcategory_data_json; ?>;
                                const totalCount = <?php echo $total_count_json; ?>;

                                // Convert data to chart format
                                const labels = subcategoryData.map(item => item.status);
                                const data = subcategoryData.map(item => item.count);

                                //Update chart title
                                const chartTitle = document.getElementById('chart-title');
                                chartTitle.innerHTML = `Problem with <span class="orange-text">${totalCount}</span> responses`;

                                // Create chart
                                const ctx = document.getElementById('subcategoryChart').getContext('2d');
                                const subcategoryChart = new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            label: 'Incident Records by Subcategory',
                                            data: data,
                                            backgroundColor: [
                                                'rgba(255, 99, 132, 0.2)',
                                                'rgba(54, 162, 235, 0.2)',
                                                'rgba(255, 206, 86, 0.2)',
                                                'rgba(75, 192, 192, 0.2)',
                                                'rgba(153, 102, 255, 0.2)',
                                                'rgba(255, 159, 64, 0.2)',
                                                'rgba(199, 199, 199, 0.2)'
                                            ],
                                            borderColor: [
                                                'rgba(255, 99, 132, 1)',
                                                'rgba(54, 162, 235, 1)',
                                                'rgba(255, 206, 86, 1)',
                                                'rgba(75, 192, 192, 1)',
                                                'rgba(153, 102, 255, 1)',
                                                'rgba(255, 159, 64, 1)',
                                                'rgba(199, 199, 199, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                labels: {
                                                    padding: 50,
                                                    font: {
                                                        size: 15 
                                                    }
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed !== null) {
                                                            label += context.parsed;
                                                        }
                                                        return label;
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                formatter: (value, context) => {
                                                    let percentage = (value * 100 / totalCount).toFixed(0) + "%";
                                                    return percentage;
                                                },
                                                color: '#4a4a4a',
                                                font: {
                                                    size: 28, 
                                                }
                                            }
                                        }
                                    },
                                    plugins: [ChartDataLabels]
                                });
                            });
                        </script>
                        
                    <?php else: ?>
                        <p>No data</p>
                    <?php endif;
            }
            else{
                $query = "SELECT 
                            auto_id, fname, lname, department, email, request_type, specification,  
                            existing_pnr_screenshot, new_option_screenshot, escalate_to, escalate_by, status, created_at, updated_at
                    FROM wpk4_backend_it_support_ticket_portal
                    where date(created_at) = '$common_start_filter'
                    order by auto_id desc LIMIT 100";
                echo '</br><center><p style="color:red;">Kindly add the filters to check the records.</p></center>';
            }

            $selection_query = $query;
            $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            $row_counter_ticket = mysqli_num_rows($result);
            $auto_numbering = 1;
            $total_paxs = 0;
        ?>

        
    <?php 
    } 
    
    
    if(isset($_GET['option']) && $_GET['option'] == 'add-web-escalation' )
    {
		include('data-table-classes.php');
		?>
		<center>
		<?php
		//if( current_user_can( 'administrator' ) || current_user_can( 'sales_manager' ))
		{ ?>
		<a target='_blank' href='?option=web-dashboard'><button class='btn btn-success' style='width:170px; font-size:13px;'>View Web Escalations</button></a>
		<?php }
		?>
		</center>
        <br></br>
        <br></br>
        <style>
        .form-group-flex{
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .form-container {
            width: 100%;
            max-width: 900px;
            margin: auto;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 16px !important;
            background-color: #f9f9f9;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

   
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

 
        .form-group-flex {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        .input-group {
            flex: 1; 
            min-width: 100px;
            margin-bottom: 20px;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px !important;
            font-size: 16px;
        }

      
        textarea {
            min-height: 80px;
            resize: vertical;
        }

    
        input[type="file"] {
            padding: 8px;
            background-color: green;
            color: white;
            border-radius: 8px !important;
            cursor: pointer;
            width: 100%;
            max-width: 300px; 
        }

      
        .submit-button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            background-color: green;
            color: white;
            border: none;
            border-radius: 8px !important;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-button:hover {
            background-color: darkgreen;
        }

        
        @media screen and (max-width: 768px) {
            .form-group-flex {
                flex-direction: column; 
            }

            .input-group {
                width: 100%; 
            }

            input[type="file"] {
                max-width: 100%; 
            }
            .form-container {
                padding: 10px !important;
            }
            h2{
                font-size: 38px !important;
            }
        }

        </style>
        <div class="form-container">
            <h2 style="color: green;
                        letter-spacing: 1px;
                        margin: 0 auto 50px auto;
                        text-align: center;
                        font-weight: 500;
                        font-size: 48px;">Web Escalation Form</h2> 
            <div class="form-container">
                <form action="" method="post" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label for="full-name">Full Name</label>
                        <div class="form-group-flex">
                            <div class="input-group" style="margin-bottom: 5px;">
                                <span>First Name</span>
                                <input type="text" id="first-name" name="first_name" value="<?php echo $first_name; ?>" required>
                            </div>
                            <div class="input-group" style="margin-bottom: 5px;">
                                <span>Last Name</span>
                                <input type="text" id="last-name" name="last_name" value="<?php echo $last_name; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="branch-location">Branch Location</label>
                        <select id="branch-location" name="branch_location" style="width:100%; padding:10px;" required>
                            <option value="GT-BOM">GT-BOM</option>
                            <option value="TDU-BOM">TDU-BOM</option>
                            <option value="GT-CCU">GT-CCU</option>
                            <option value="TDU-CCU">TDU-CCU</option>
                            <option value="GT-MEL">GT-MEL</option>
                            <option value="TDU-MEL">TDU-MEL</option>
                            <option value="CMB-FlyLanka">CMB-FlyLanka</option>
                        </select>
                    </div>

                    <div class="form-group-flex">
                        <div class="input-group">
                            <label for="department">Department</label>
                            <select id="department" name="department" style="width:100%; padding:10px;" required>
                                <option value="Sales">Sales</option>
                                <option value="After-Sales">After-Sales</option>
                                <option value="Date change">Date change</option>
                                <option value="Quality Analysis">Quality Analysis</option>
                                <option value="HR">HR</option>
                                <option value="IT">IT</option>
                                <option value="IT Developer">IT Developer</option>
                                <option value="Accounting">Accounting</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="example@example.com" value="<?php echo $login_email; ?>" required>
                        </div>
                    </div>

                    <?php
                        $query_request_types = "SELECT DISTINCT request_type FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_request_types = mysqli_query($mysqli, $query_request_types);

                        $request_types = [];
                        while ($row = mysqli_fetch_assoc($result_request_types)) {
                            $request_types[] = $row['request_type'];
                        }

                        $query_categories = "SELECT DISTINCT category FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_categories = mysqli_query($mysqli, $query_categories);

                        $categories = [];
                        while ($row = mysqli_fetch_assoc($result_categories)) {
                            $categories[] = $row['category'];
                        }

                        $query_subcategories = "SELECT category, subcategory FROM wpk4_backend_it_support_ticket_portal_categories";
                        $result_subcategories = mysqli_query($mysqli, $query_subcategories);

                        $subcategories = [];
                        while ($row = mysqli_fetch_assoc($result_subcategories)) {
                            $subcategories[$row['category']][] = $row['subcategory'];
                        }
                        
                        
                    ?>

                    <div class="input-group">
                        <label for="type">Request Type:</label>
                        <select id="type" name="type" style="width:100%; padding:10px;" required>
                            <?php foreach ($request_types as $request_type): 
                            ?>
                                <option value="<?php echo htmlspecialchars($request_type); ?>"><?php echo htmlspecialchars($request_type); ?></option>
                            <?php
                            endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="category">Category:</label>
                        <select id="category" name="category" style="width:100%; padding:10px;" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="subcategory">Subcategory:</label>
                        <select id="subcategory" name="subcategory" style="width:100%; padding:10px;">
                            <option value="">Select Subcategory</option>
                            <?php if (isset($subcategories) && isset($subcategories[$row]) && isset($subcategories[$row['category']])): ?>
                                <?php foreach ($subcategories[$row['category']] as $subcategory): ?>
                                    <option value="<?php echo htmlspecialchars($subcategory); ?>"><?php echo htmlspecialchars($subcategory); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="specification">Please Specify</label>
                        <textarea id="specification" name="specification" required></textarea>
                    </div>

                    <div class="input-group">
                        <label for="existing_pnr_screenshot">Existing PNR Screenshot:</label>
                        <input type="file" id="existing_pnr_screenshot" name="existing_pnr_screenshot">
                    </div>

                    <!--<div class="input-group">-->
                    <!--    <label for="new_option_screenshot">New Option Screenshot:</label>-->
                    <!--    <input type="file" id="new_option_screenshot" name="new_option_screenshot">-->
                    <!--</div>-->

                    <div class="input-group" style='width: fit-content;'>
                        <label for="escalate_to">Escalate to:</label>
                        <select id="escalate_to" style="width:100%; padding:10px;" name="escalate_to" required>
                            <option value="">Select</option>
                            <option>IT</option>
                        </select>
                    </div>
                    <div class="input-group" style='width: fit-content;'>
                        <label for="delegate_name">Assign Delegate:</label>
                        <select id="delegate_name" name="delegate_name" style="width:100%; padding:10px;">
                            <option value="">Select Delegate</option>
                             <option value="santanud">santanud</option>
                            <option value="SUBHAJIT.D">SUBHAJIT.D</option>
                            <option value="SukritH">SukritH</option>
                            <option value="BasudevN">BasudevN</option>
                            <option value="Chandan.s">Chandan.s</option>
                            <option value="debarun">debarun</option>
                        </select>
                    </div>

                    <button type="submit" class="submit-button" name="submit_web_escalation">Submit</button>

                </form>
            </div>

        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                
                flatpickr("#request_date", {
                    enableTime: true,
                    dateFormat: "Y-m-d",
                    time_24hr: true,
                    allowInput: true,
                });
            });
            
            // // PHP array converted to JavaScript object
            
            var subcategories = JSON.parse(<?php echo json_encode($subcategories["Desktop Support"]); ?>);
            console.log(subcategories);
            // function updateSubcategories() {
            //     var categorySelect = document.getElementById("category");
            //     var subcategorySelect = document.getElementById("subcategory");
                
            //     var selectedCategory = categorySelect.value;
                
            //     // Clear existing subcategory options
            //     subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                
            //     // Check if the selected category exists in the subcategories object
            //     if (subcategories[selectedCategory]) {
            //         subcategories[selectedCategory].forEach(function(subcategory) {
            //             var option = document.createElement("option");
            //             option.value = subcategory;
            //             option.textContent = subcategory;
            //             subcategorySelect.appendChild(option);
            //         });
            //     }
            // }
        </script>
        <?php
            
            $query = "SELECT category, subcategory FROM wpk4_backend_it_support_ticket_portal_categories";
            $result = mysqli_query($mysqli, $query);

            $categories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[$row['category']][] = $row['subcategory'];
            }
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var subcategories = <?php echo json_encode($subcategories); ?>;
                
                var categorySelect = document.getElementById("category");
                var subcategorySelect = document.getElementById("subcategory");

                function updateSubcategories() {
                    var selectedCategory = categorySelect.value;
                    var options = subcategories[selectedCategory] || [];

                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

                    options.forEach(function(option) {
                        var optionElement = document.createElement("option");
                        optionElement.value = option;
                        optionElement.textContent = option;
                        subcategorySelect.appendChild(optionElement);
                    });
                }

                categorySelect.addEventListener("change", updateSubcategories);

                updateSubcategories();
            });
</script>
	<?php
    }
    
    if (isset($_GET['option']) && $_GET['option'] == 'web-dashboard' )
    {
    ?>
        <script>
            function searchordejs() 
            {
                var request_date = document.getElementById("request_date").value;
                var problem_category = document.getElementById("problem_category").value;	
                var department = document.getElementById("department").value;	
                var case_id = document.getElementById("case_id").value;	
                var status = document.getElementById("status").value;	
                
                
                window.location='?option=web-dashboard&request_date=' + request_date + '&problem_category=' + problem_category + '&department=' + department + '&case_id=' + case_id + '&status=' + status;
            }
        </script>
        <h2 style="color: green;">IT Support Request</h2>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables 1.13.6 + Bootstrap 5 Integration -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons 2.4.1 (for Export Excel, CSV, etc) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    
        
        <table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
            <tr>
                <td width='8%'>
                    Request Date</br>
                    <input type='text' name='request_date' value='<?php if(isset($_GET['request_date'])) { echo substr($_GET['request_date'], 0, 10); } ?>' id='request_date'>
                </td>
                <td width='8%'>
                    Problem Category</br>
                    <select name='problem_category' id='problem_category' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="" selected>All</option>
                        <?php
                        $query_problem_category = "SELECT DISTINCT request_type FROM wpk4_backend_it_support_ticket_portal";
                        $result_problem_category = mysqli_query($mysqli, $query_problem_category) or die(mysqli_error($mysqli));
                        while($row_problem_category = mysqli_fetch_assoc($result_problem_category))
                        {   
                            ?>
                            <option value="<?php echo $row_problem_category['request_type']; ?>" ><?php echo $row_problem_category['request_type']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td width='8%'>
                    Department </br>
                    <select name='department' id='department' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="" selected>All</option>
                        <?php
                        $query_department = "SELECT DISTINCT department FROM wpk4_backend_it_support_ticket_portal";
                        $result_department = mysqli_query($mysqli, $query_department) or die(mysqli_error($mysqli));
                        while($row_department = mysqli_fetch_assoc($result_department))
                        {   
                            ?>
                            <option value="<?php echo $row_department['department']; ?>" ><?php echo $row_department['department']; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td width='8%'>
                    CASE ID</br>
                    <input type='text' name='case_id' value='<?php if(isset($_GET['case_id'])) { echo $_GET['case_id']; } ?>' id='case_id'>
                </td>
                <td width='8%'>
                    Status</br>
                    <select name='status' id='status' style="width:100%; padding: 1.5rem 1.8rem;">
                        <option value="all" <?php if(isset($_GET['status']) && $_GET['status'] == 'all') { echo 'selected'; } ?>>All</option>
                        <option value="Pending" <?php if(isset($_GET['status']) && $_GET['status'] == 'Pending') { echo 'selected'; } ?>>Pending</option>
                        <option value="Under review" <?php if(isset($_GET['status']) && $_GET['status'] == 'Under review') { echo 'selected'; } ?>>Under review</option>
                        <option value="Escalated to HO" <?php if(isset($_GET['status']) && $_GET['status'] == 'Escalated to HO') { echo 'selected'; } ?>>Escalated to HO</option>
                        <option value="Awaiting HO" <?php if(isset($_GET['status']) && $_GET['status'] == 'Awaiting HO') { echo 'selected'; } ?>>Awaiting HO</option>
                        <option value="Not-Revaluation" <?php if(isset($_GET['status']) && $_GET['status'] == 'Not-Revaluation') { echo 'selected'; } ?>>Not-Revaluation</option>
                        <option value="Revaluation" <?php if(isset($_GET['status']) && $_GET['status'] == 'Revaluation') { echo 'selected'; } ?>>Revaluation</option>
                        <option value="Completed" <?php if(isset($_GET['status']) && $_GET['status'] == 'Completed') { echo 'selected'; } ?>>Completed</option>
                        <option value="Rejected" <?php if(isset($_GET['status']) && $_GET['status'] == 'Rejected') { echo 'selected'; } ?>>Rejected</option>
                    </select>
                    
                </td>

            </tr>
            <tr>
                <td colspan="6" style='text-align:center;'>
                    <button style='padding:10px; margin:0;font-size:11px; background-color: green;' id='search_orders' onclick="searchordejs()">Search</button>
                </td>
            </tr>
        </table>

        <?php
            $request_date = ($_GET['request_date'] ?? false) ? substr($_GET['request_date'], 0, 10) : '' ;
            $problem_category = ($_GET['problem_category'] ?? false) ? $_GET['problem_category'] : '' ;
            $department = ($_GET['department'] ?? false) ? $_GET['department'] : '' ;
            $case_id = ($_GET['case_id'] ?? false) ? $_GET['case_id'] : '' ;
            $status_id = ($_GET['status'] ?? false) ? $_GET['status'] : '' ;
            $payment_type = ($_GET['payment_type'] ?? false) ? $_GET['payment_type'] : '' ;

       
            if(isset($request_date) && $request_date != '')
            {
                $request_date_sql = "created_at LIKE '".$request_date."%' AND ";
            }
            else
            {
                $request_date_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($problem_category) && $problem_category != '')
            {
                $problem_category_sql = "request_type = '".$problem_category."' AND ";
            }
            else
            {
                $problem_category_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($department) && $department != '')
            {
                $department_sql = "department = '".$department."' AND ";
            }
            else
            {
                $department_sql = "auto_id IS NOT NULL AND ";
            }

            if(isset($case_id) && $case_id != '')
            {
                $case_id_sql = "auto_id = '".$case_id."' AND ";
            }
            else
            {
                $case_id_sql = "auto_id IS NOT NULL AND ";
            }

          
            
            if(isset($status_id) && $status_id != '' && $status_id != 'all')
            {
                if($status_id == 'Pending') {
                    $status_id_sql = "(status NOT IN ('Completed', 'Rejected'))";
                } else {
                    if($status_id == 'Not-Revaluation')
                    {
                    $status_id_sql = "(status NOT IN ('Completed', 'Rejected', 'Revaluation'))";
                    }
                    else
                    {
                        $status_id_sql = "status = '".$status_id."'";
                    }
                }
            }
            else if ($status_id == 'all'){
                $status_id_sql = "auto_id IS NOT NULL";
            }
            else
            {
                $status_id_sql = "(status NOT IN ('Completed', 'Rejected'))";
            }

            if(
                (isset($request_date_sql) && $request_date_sql != '') ||
                (isset($problem_category_sql) && $problem_category_sql != '') ||
                (isset($department_sql) && $department_sql != '') ||
                (isset($case_id_sql) && $case_id_sql != '') ||
                (isset($status_id_sql) && $status_id_sql != '')
            ) 
            {
                $query = "SELECT *
                    FROM wpk4_backend_it_support_ticket_portal
                    where 
                        $request_date_sql
                        $problem_category_sql
                        $department_sql
                        $case_id_sql
                        $status_id_sql
                        AND sub_status = 'Escalated to Web'
                    order by auto_id desc LIMIT 100";
                    //echo $query;
                    $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                    $row_counter_ticket = mysqli_num_rows($result);
            
                    if ($result && mysqli_num_rows($result) > 0): ?>
                        <style>
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                table-layout: auto; 
                            }
                            th, td {
                                border: 1px solid #ccc;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                white-space: nowrap; 
                            }
                            .remark-column {
                                width: 300px; 
                                word-wrap: break-word; 
                                word-break: break-all; 
                                white-space: normal;
                            }
                        </style>
                        <div style="margin-bottom: 20px; text-align:right;">
    <button id="exportExcel" class="btn btn-success btn-sm">Export Excel</button>
    <button id="exportCSV" class="btn btn-primary btn-sm">Export CSV</button>
</div>

<table id="tanviTable2" class="table table-striped" style="font-size:14px; margin-top:35px;">
    <thead>
        <tr>
            <th>ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Branch Location</th>
            <th>Department</th>
            <th>Request Type</th>
            <th>Category</th>
            <th>Subcategory</th>
            <th>Escalate To</th>
            <th>Escalated By</th>
            <th>Status</th>
            <th class="remark-column">Remark</th>
            <th>Created At</th>
            <th>Updated</th>
            <th>Action</th> <!-- ðŸš€ -->
            <th>Responsible</th>

        </tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr data-rowinfo='<?php echo json_encode([
    'email' => $row['email'] ?? '',
    'specification' => $row['specification'] ?? '',
    'screenshot' => !empty($row['existing_pnr_screenshot']) ? 'https://gauratravel.com.au/' . $row['existing_pnr_screenshot'] : null,
    'auto_id' => $row['auto_id'] ?? '',
    'status' => $row['status'] ?? '',
    'priority' => $row['priority'] ?? '',
    'delegate_name' => $row['delegate_name'] ?? ''
], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>

                <td><?php echo $row['auto_id']; ?></td>
                <td><?php echo $row['fname']; ?></td>
                <td><?php echo $row['lname']; ?></td>
                <td><?php echo $row['branch_location']; ?></td>
                <td><?php echo $row['department']; ?></td>
                <td><?php echo $row['request_type']; ?></td>
                <td><?php echo $row['category']; ?></td>
                <td><?php echo $row['sub_category']; ?></td>
                <td><?php echo $row['escalate_to']; ?></td>
                <td><?php echo $row['escalate_by']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td class="remark-column">
                    <?php if(current_user_can('administrator') || current_user_can('sales_manager') || current_user_can('it_team') || current_user_can('ho_operations')): ?>
                        <form action="" method="post">
                            <input type="hidden" name="auto_id" value="<?php echo $row['auto_id']; ?>">
                            <textarea id="remark_<?php echo $row['auto_id']; ?>" name="remark" placeholder="<?php echo htmlspecialchars($row['remark']); ?>" style="width:100%; padding:4px; font-size:13px;"><?php echo htmlspecialchars($row['remark']); ?></textarea>
                            <button type="submit" name="update_remark" class="btn btn-primary" style="padding:10px; font-size:11px; background-color:green; margin-top:5px;">Save</button>
                            <button type="button" class="btn btn-secondary" onclick="copyRemark('<?php echo $row['auto_id']; ?>')" style="padding:10px; margin-left:5px; font-size:11px; background-color:#007bff; margin-top:5px;">Copy</button>
                        </form>
                    <?php else: ?>
                        <?php echo htmlspecialchars($row['remark']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_by']; ?> on <?php echo $row['updated_at']; ?></td>
                <td>
                    <button class="btn btn-info toggle-detail" style="padding:8px; border-radius:6px; background-color:green; color:white;">
                        <i class="fa-solid fa-angle-down"></i>
                    </button>
                    
                    <a href="?option=move-to-it&id=<?php echo $row['auto_id']; ?>"><button class="btn btn-info" style="padding:8px; border-radius:6px; background-color:green; color:white;">
                        Move to IT Support Portal
                    </button></a>
                </td>
                <td><?php echo !empty($row['delegate_name']) ? $row['delegate_name'] : 'Unassigned'; ?></td>

            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<!-- Copy Remark -->
<script>
function copyRemark(id) {
    const textarea = document.getElementById('remark_' + id);
    const text = textarea.value;

    navigator.clipboard.writeText(text)
    .then(() => {
        alert('Copied to clipboard!');
    })
    .catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy!');
    });
}
</script>

<!-- DataTable and Expandable Rows -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    var table = $('#tanviTable').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 15,
        order: [[0, 'desc']],  // <-- add this line (column index 0, descending)
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },
            {
                extend: 'csvHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            }
        ]
    });

  
  var table = $('#tanviTable2').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 50,
        order: [[0, 'desc']],  // <-- add this line (column index 0, descending)
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            },
            {
                extend: 'csvHtml5',
                title: 'IT_Support_Tickets',
                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7,8,9,10,11]
                }
            }
        ]
    });



    // Manual Button triggers
    $('#exportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    $('#exportCSV').on('click', function() {
        table.button('.buttons-csv').trigger();
    });
    // âœ… Action Expand/Collapse
    $('#tanviTable tbody').on('click', '.toggle-detail', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        let rowData = {};
        try {
            rowData = JSON.parse(tr.attr('data-rowinfo'));
        } catch (e) {
            console.error("Invalid JSON in data-rowinfo", e);
            return;
        }

        if (row.child.isShown()) {
            row.child.hide();
            $(this).find('i').removeClass('fa-angle-up').addClass('fa-angle-down');
            $(this).css('background-color', 'green');
        } else {
            var content = `
                <div style="padding:10px; background:#f9f9f9; border-radius:8px;">
                    <p><strong>Email:</strong> ${rowData.email ?? ''}</p>
                    <p><strong>Specification:</strong> ${rowData.specification ?? ''}</p>
                    ${rowData.screenshot ? `<a target="_blank" href="${rowData.screenshot}"><img src="${rowData.screenshot}" style="max-width:400px; margin-top:20px;"></a>` : '<p>No Screenshot Available</p>'}
                    <form method="post" style="margin-top:20px;">
                        <input type="hidden" name="request_id" value="${rowData.auto_id}">
                        <label>Status:</label>
                        <select name="status-select" style="padding:8px; margin-bottom:10px;">
                            <option ${rowData.status=='Pending'?'selected':''}>Pending</option>
                            <option ${rowData.status=='Under review'?'selected':''}>Under review</option>
                            <option ${rowData.status=='Escalated to HO'?'selected':''}>Escalated to HO</option>
                            <option ${rowData.status=='Awaiting HO'?'selected':''}>Awaiting HO</option>
                            <option ${rowData.status=='Revaluation'?'selected':''}>Revaluation</option>
                            <option ${rowData.status=='Completed'?'selected':''}>Completed</option>
                            <option ${rowData.status=='Rejected'?'selected':''}>Rejected</option>
                        </select>
                        <br>
                        <label>Priority:</label>
                        <select name="priority-select" style="padding:8px;">
                            <option ${rowData.priority=='High'?'selected':''}>High</option>
                            <option ${rowData.priority=='Medium'?'selected':''}>Medium</option>
                            <option ${rowData.priority=='Low'?'selected':''}>Low</option>
                        </select>
                        <br>
                        
                          <label>deligate by (responsible) </label>
                      <select name="delegate-select" style="padding:8px;">
    <option value="">Select Delegate</option>

        <option ${rowData.delegate_name=='santanud'?'selected':''}>santanud</option>
        <option ${rowData.delegate_name=='SUBHAJIT.D'?'selected':''}>SUBHAJIT.D</option>
        <option ${rowData.delegate_name=='SukritH'?'selected':''}>SukritH</option>
        <option ${rowData.delegate_name=='BasudevN'?'selected':''}>BasudevN</option>
        <option ${rowData.delegate_name=='Chandan.s'?'selected':''}>Chandan.s</option>
        <option ${rowData.delegate_name=='debarun'?'selected':''}>debarun</option>
        
    
</select>

                        <br><br>
                        <button type="submit" name="update-status" class="btn btn-success" style="padding:8px 20px;">Update</button>
                    </form>
                </div>
            `;
            row.child(content).show();
            $(this).find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
            $(this).css('background-color', 'orange');
        }
    });
    
    $('#tanviTable2 tbody').on('click', '.toggle-detail', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        let rowData = {};
        try {
            rowData = JSON.parse(tr.attr('data-rowinfo'));
        } catch (e) {
            console.error("Invalid JSON in data-rowinfo", e);
            return;
        }

        if (row.child.isShown()) {
            row.child.hide();
            $(this).find('i').removeClass('fa-angle-up').addClass('fa-angle-down');
            $(this).css('background-color', 'green');
        } else {
            var content = `
                <div style="padding:10px; background:#f9f9f9; border-radius:8px;">
                    <p><strong>Email:</strong> ${rowData.email ?? ''}</p>
                    <p><strong>Specification:</strong> ${rowData.specification ?? ''}</p>
                    ${rowData.screenshot ? `<a target="_blank" href="${rowData.screenshot}"><img src="${rowData.screenshot}" style="max-width:400px; margin-top:20px;"></a>` : '<p>No Screenshot Available</p>'}
                    <form method="post" style="margin-top:20px;">
                        <input type="hidden" name="request_id" value="${rowData.auto_id}">
                        <label>Status:</label>
                        <select name="status-select" style="padding:8px; margin-bottom:10px;">
                            <option ${rowData.status=='Pending'?'selected':''}>Pending</option>
                            <option ${rowData.status=='Under review'?'selected':''}>Under review</option>
                            <option ${rowData.status=='Escalated to HO'?'selected':''}>Escalated to HO</option>
                            <option ${rowData.status=='Awaiting HO'?'selected':''}>Awaiting HO</option>
                            <option ${rowData.status=='Revaluation'?'selected':''}>Revaluation</option>
                            <option ${rowData.status=='Completed'?'selected':''}>Completed</option>
                            <option ${rowData.status=='Rejected'?'selected':''}>Rejected</option>
                        </select>
                        <br>
                        <label>Priority:</label>
                        <select name="priority-select" style="padding:8px;">
                            <option ${rowData.priority=='High'?'selected':''}>High</option>
                            <option ${rowData.priority=='Medium'?'selected':''}>Medium</option>
                            <option ${rowData.priority=='Low'?'selected':''}>Low</option>
                        </select>
                        <br>
                        
                          <label>deligate by (responsible) </label>
                      <select name="delegate-select" style="padding:8px;">
    <option value="">Select Delegate</option>

        <option ${rowData.delegate_name=='santanud'?'selected':''}>santanud</option>
        <option ${rowData.delegate_name=='SUBHAJIT.D'?'selected':''}>SUBHAJIT.D</option>
        <option ${rowData.delegate_name=='SukritH'?'selected':''}>SukritH</option>
        <option ${rowData.delegate_name=='BasudevN'?'selected':''}>BasudevN</option>
        <option ${rowData.delegate_name=='Chandan.s'?'selected':''}>Chandan.s</option>
        <option ${rowData.delegate_name=='debarun'?'selected':''}>debarun</option>
        
    
</select>

                        <br><br>
                        <button type="submit" name="update-status" class="btn btn-success" style="padding:8px 20px;">Update</button>
                    </form>
                </div>
            `;
            row.child(content).show();
            $(this).find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
            $(this).css('background-color', 'orange');
        }
    });

});

</script>

                        <?php
                           
                            $query_subcategories = "SELECT status, COUNT(*) as count FROM wpk4_backend_it_support_ticket_portal GROUP BY status";
                            $result_subcategories = mysqli_query($mysqli, $query_subcategories) or die(mysqli_error($mysqli));

                            $subcategory_data = [];
                            $total_count = 0;
                            while ($row = mysqli_fetch_assoc($result_subcategories)) {
                                $subcategory_data[] = $row;
                                $total_count += $row['count'];
                            }

                       
                            $subcategory_data_json = json_encode($subcategory_data);
                            $total_count_json = json_encode($total_count);
                        ?>
                        
                        <div class="chart-container" style="width: 50%; margin: auto;">
                            <h2 id="chart-title" style="text-align: center; margin-top: 100px; margin-bottom: 0;"></h2>
                            <center> <canvas id="subcategoryChart"></canvas> <center>
                        </div>
                        <style>
                            canvas#subcategoryChart {
                                margin-top: -90px;
                            }
                            .orange-text {
                                color: orange;
                            }
                        </style>


                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                // Get chart data from PHP
                                const subcategoryData = <?php echo $subcategory_data_json; ?>;
                                const totalCount = <?php echo $total_count_json; ?>;

                                // Convert data to chart format
                                const labels = subcategoryData.map(item => item.status);
                                const data = subcategoryData.map(item => item.count);

                                //Update chart title
                                const chartTitle = document.getElementById('chart-title');
                                chartTitle.innerHTML = `Problem with <span class="orange-text">${totalCount}</span> responses`;

                                // Create chart
                                const ctx = document.getElementById('subcategoryChart').getContext('2d');
                                const subcategoryChart = new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            label: 'Incident Records by Subcategory',
                                            data: data,
                                            backgroundColor: [
                                                'rgba(255, 99, 132, 0.2)',
                                                'rgba(54, 162, 235, 0.2)',
                                                'rgba(255, 206, 86, 0.2)',
                                                'rgba(75, 192, 192, 0.2)',
                                                'rgba(153, 102, 255, 0.2)',
                                                'rgba(255, 159, 64, 0.2)',
                                                'rgba(199, 199, 199, 0.2)'
                                            ],
                                            borderColor: [
                                                'rgba(255, 99, 132, 1)',
                                                'rgba(54, 162, 235, 1)',
                                                'rgba(255, 206, 86, 1)',
                                                'rgba(75, 192, 192, 1)',
                                                'rgba(153, 102, 255, 1)',
                                                'rgba(255, 159, 64, 1)',
                                                'rgba(199, 199, 199, 1)'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                labels: {
                                                    padding: 50,
                                                    font: {
                                                        size: 15 
                                                    }
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed !== null) {
                                                            label += context.parsed;
                                                        }
                                                        return label;
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                formatter: (value, context) => {
                                                    let percentage = (value * 100 / totalCount).toFixed(0) + "%";
                                                    return percentage;
                                                },
                                                color: '#4a4a4a',
                                                font: {
                                                    size: 28, 
                                                }
                                            }
                                        }
                                    },
                                    plugins: [ChartDataLabels]
                                });
                            });
                        </script>
                        
                    <?php else: ?>
                        <p>No data</p>
                    <?php endif;
            }
            else{
                $query = "SELECT 
                            auto_id, fname, lname, department, email, request_type, specification,  
                            existing_pnr_screenshot, new_option_screenshot, escalate_to, escalate_by, status, created_at, updated_at
                    FROM wpk4_backend_it_support_ticket_portal
                    where date(created_at) = '$common_start_filter'
                    order by auto_id desc LIMIT 100";
                echo '</br><center><p style="color:red;">Kindly add the filters to check the records.</p></center>';
            }

            $selection_query = $query;
            $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
            $row_counter_ticket = mysqli_num_rows($result);
            $auto_numbering = 1;
            $total_paxs = 0;
        ?>

        
    <?php 
    } 
    
    if (isset($_POST['update_remark']) && $_POST['auto_id'] != '' && isset($_POST['remark']) && $_POST['remark'] != '') {
        $auto_id = $_POST['auto_id'];
        $remark = $_POST['remark'] ?? '';
    
        $sql_update_remark = "UPDATE wpk4_backend_it_support_ticket_portal SET remark = '$remark', updated_by = '$current_username' WHERE auto_id = '$auto_id'";
        echo $sql_update_remark;
        if ($mysqli->query($sql_update_remark) === TRUE) 
        {
           echo "<script>alert('Remark updated successfully!'); window.location.href = window.location.href;</script>";

 
        } else {
            echo "Error: " . $sql_update_remark . "<br>" . $mysqli->error;
        }
    }
    if (isset($_POST['update-status'])) 
    {
        global $mysqli;
        $request_id = $_POST['request_id'] ?? '';
        if(isset($request_id) && $request_id != '')
        {
            $new_status = $_POST['status-select'] ?? 'Pending';
            $new_priority = $_POST['priority-select'] ?? 'Low';
            $new_delegate = $_POST['delegate-select'] ?? '';

          $update_query = "UPDATE wpk4_backend_it_support_ticket_portal 
                 SET status = '$new_status', 
                     priority = '$new_priority', 
                     delegate_name = '$new_delegate',
                     updated_by = '$current_username'
                 WHERE auto_id = '$request_id'";

            if (mysqli_query($mysqli, $update_query)) 
            {
                if($new_status == 'Awaiting HO' || $new_status == 'Escalated to HO')
                {
                    $query = "SELECT *
                            FROM wpk4_backend_it_support_ticket_portal
                            where 
                                auto_id = '$request_id'
                            ";
                    $result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
                    $row = mysqli_fetch_assoc($result);
                    
                    $mailbody = '<table class="wp-travel-wrapper" style="border:0;" width="100%" cellspacing="0" cellpadding="0">
                    	<tr>
                    		<td width="50%" style="text-align:left;border:0; margin: 0; padding: 7px 7px;">
                    			<img class="size-full wp-image-42537" src="https://gauratravel.com.au/wp-content/uploads/2022/01/cropped-GauraTravel_logo_small_2.png" alt="Gaura Travel logo" width="203" height="50" />
                    		</td>
                    		<td width="50%" style="border:0; text-align:right; margin: 0; padding: 7px 7px;">
                    		</td>
                    	</tr>
                    	</table>
                    	</br>';
                    // Open the table before the loop
                    $mailbody .= '<table class="wp-travel-wrapper" style="border:0;" width="100%" cellspacing="0" cellpadding="0">';
                    
                    $fields = [
                        "First Name" => $row['fname'],
                        "Last Name" => $row['lname'],
                        "Department" => $row['department'],
                        "Request Type" => $row['request_type'],
                        "Escalated To" => $row['escalate_to'],
                        "Escalated By" => $row['escalate_by'],
                        "Status" => $row['status'],
                        "Created At" => $row['created_at']
                    ];
                    
                    foreach ($fields as $label => $value) {
                        $mailbody .= '
                            <tr>
                                <td style="padding: 7px 7px; font-weight: 700;">' . $label . '</td>
                                <td style="padding: 7px 7px;">' . $value . '</td>
                            </tr>';
                    }
                    
                    // Close the table after the loop
                    $mailbody .= '</table>';
                    
                    $mailbody=stripslashes($mailbody);
                        
                    include_once (ABSPATH . WPINC . '/class-phpmailer.php');
                    include_once (ABSPATH . WPINC . '/PHPMailer/SMTP.php');
                    $mail = new PHPMailer ();
                    $mail->IsSMTP();
                    $mail->Host = 'tls://smtp.office365.com:587';
                    $mail->Port = '587';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'donotreply@gauratravel.com.au';
                    $mail->Password = 'P/738625763818ob';
                    $mail->SMTPSecure = 'tls';
                    $mail->From = 'donotreply@gauratravel.com.au';
                    $mail->FromName = 'Gaura Travel';
                    $mail->AddAddress('leen@gauratravel.com.au', "Passenger");
                    $mail->WordWrap = 50;
                    $mail->IsHTML(true);
                    $mail->Subject = 'IT issue - '.$request_id;
                    $mail->Body = $mailbody;
                    if(!$mail->Send()) {
                        //echo "Mailer Error: " . $mail->ErrorInfo; // âœ… Display error
                    } else {
                        //echo "Email sent successfully!";
                    }
                }
                
                echo "<script>alert('Status updated successfully!'); window.location.href = window.location.href;</script>";
            } else {
                echo "<script>alert('Error updating status.');</script>";
            }
        }
    }
    
    if (isset($_GET['option']) && $_GET['option'] == 'move-to-it') 
    {
        global $mysqli;
        $request_id = $_GET['id'] ?? '';
        if(isset($request_id) && $request_id != '')
        {
          $update_query = "UPDATE wpk4_backend_it_support_ticket_portal 
                 SET sub_status = NULL, 
                    updated_at = '$current_date_and_time',
                    updated_by = '$current_username'
                 WHERE auto_id = '$request_id'";
            if (mysqli_query($mysqli, $update_query)) 
            {
                echo "<script>alert('Status updated successfully!'); window.location.href = '?option=web-dashboard';</script>";
            } else {
                echo "<script>alert('Error updating status.');</script>";
            }
        }
    }
    
    if (isset($_GET['option']) && $_GET['option'] == 'escalate-to-web') 
    {
        global $mysqli;
        $request_id = $_GET['id'] ?? '';
        if(isset($request_id) && $request_id != '')
        {
            $new_status = 'Escalated to Web';
            $new_delegate = '';

          $update_query = "UPDATE wpk4_backend_it_support_ticket_portal 
                 SET sub_status = '$new_status', 
                    updated_at = '$current_date_and_time',
                    updated_by = '$current_username'
                 WHERE auto_id = '$request_id'";
            //echo $update_query;
            if (mysqli_query($mysqli, $update_query)) 
            {
                echo "<script>alert('Status updated successfully!'); window.location.href = '?option=dashboard';</script>";
            } else {
                echo "<script>alert('Error updating status.');</script>";
            }
        }
    }
    ?>
    <!-- Lightbox Modal -->
<div id="lightbox-modal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); justify-content:center; align-items:center;">
    <span id="close-modal" style="position:absolute; top:20px; right:30px; color:#fff; font-size:30px; cursor:pointer;">&times;</span>
    <img id="modal-img" style="max-width:90%; max-height:90%;">
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {

        var modal = document.getElementById("lightbox-modal");
        var modalImg = document.getElementById("modal-img");
        var closeModal = document.getElementById("close-modal");

        
        document.querySelectorAll(".clickable-image").forEach(function (img) {
            img.addEventListener("click", function () {
                modal.style.display = "flex";
                modalImg.src = this.src; 
            });
        });

        // Close modal when clicking the close button
        closeModal.addEventListener("click", function () {
            modal.style.display = "none";
        });

        // Close modal when clicking outside of the modal
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    });

   
</script>
</div>
<style>
@media screen and (max-width: 768px) {
    .table thead {
        display: none; 
    }

    .table, .table tbody, .table tr, .table td {
        display: block;
        width: 100%;
    }

    .table tr {
        margin-bottom: 10px;
        border: 1px solid #ddd;
        padding: 10px;
        background: #fff;
    }

    .table td {
        text-align: left;
        position: relative;
    }

    .table td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        width: 45%;
        font-weight: bold;
        text-align: left;
        color: #333;
    }

  
    .toggle-detail {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: blue;
        color: white;
        border: none;
    }

    .toggle-detail i {
        font-size: 16px;
    }
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const checkbox = document.getElementById("ticket_status"); // Example ID
    if (checkbox) {
        checkbox.checked = true; // Only sets if it exists
    }
});
</script>

</body>
<?php get_footer(); ?>