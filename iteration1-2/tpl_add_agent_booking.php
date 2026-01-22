<?php
/**
 * Template Name: Add New Agent Booking
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Haodong
 */
get_header();
include("wp-config-custom.php");

global $current_user; 
wp_get_current_user();
$currnt_userlogn = $current_user->user_login;

if(isset($_GET['action']) && $_GET['action'] == 'create') {
    ?>
    <h3 style="text-align: center;">Create Order</h3>
    <div class='table-container'>
        <h5></h5>
        <form method="post">
            <div id="form-container">
                <label for="email">Email:</label>
                <input type="email" name="email" required><br><br>
                
                <label for="amount">Amount:</label>
                <input type="number" name="amount" step="0.01" required><br><br>
                
                <label for="payment_reference">Payment Reference:</label>
                <input type="text" name="payment_reference"><br><br>
                
                <label for="amount">Payment Method:</label>
                <select name='payment_method' required id='payment_method' style="width:100%; padding:10px;">
                			        <?php
                			        $query_payment_method = "SELECT account_name, bank_id FROM wpk4_backend_accounts_bank_account where bank_id IN (7,8,9,5) order by account_name asc";
                            		$result_payment_method = mysqli_query($mysqli, $query_payment_method) or die(mysqli_error($mysqli));
                            		while($row_payment_method = mysqli_fetch_assoc($result_payment_method))
                        		    {
                        		        if(isset($_GET['payment_method']) && $_GET['payment_method'] != '' && $_GET['payment_method'] == $row_payment_method['bank_id'])
                        		        {
                        			        ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>" selected><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		        else
                        		        {
                        		            ?>
                        			        <option value="<?php echo $row_payment_method['bank_id']; ?>"><?php echo $row_payment_method['account_name']; ?></option>
                        			        <?php
                        		        }
                        		    }
                			        ?>
                			    </select><br><br>
                
                <label for="remark">Remark:</label>
                <textarea name="remark"></textarea><br><br>
                
                <button type="submit" class="submit-button">Place Order</button>
            </div>
        </form>
    </div>
    <?php
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'];
        $trams_received_amount = $_POST['amount'];
        $payment_reference = $_POST['payment_reference'];
        $payment_method = $_POST['payment_method'];
        $trams_remarks = $_POST['remark'];
        
        $sql_bookings = "INSERT INTO wpk4_backend_travel_bookings (order_type, product_id, order_id, order_date, travel_date, total_pax, payment_status, added_on, added_by)
                        SELECT 'failed', '1234554321', COALESCE(MAX(order_id), 0) + 1, NOW(), NOW(), 1, 'partially_paid', NOW(), '$currnt_userlogn'
                        FROM wpk4_backend_travel_bookings
                        WHERE order_type = 'failed';";
        mysqli_query($mysqli, $sql_bookings) or die(mysqli_error($mysqli));
        $new_auto_id = mysqli_insert_id($mysqli);
        
        if(isset($new_auto_id) && $new_auto_id != '') {
            $sql_new_booking = "SELECT * FROM wpk4_backend_travel_bookings WHERE auto_id='$new_auto_id';";
            $result2 = mysqli_query($mysqli, $sql_new_booking);
            if ($new_booking = mysqli_fetch_assoc($result2)) {
                $order_id = $new_booking['order_id'];
                $order_date = $new_booking['order_date'];
                
                // insert into wpk4_backend_travel_booking_pax
                $sql = "INSERT INTO wpk4_backend_travel_booking_pax (order_type, product_id, order_id, order_date, email_pax, pax_status, added_on, added_by)
                        VALUES ( 'failed', '1234554321', '$order_id', '$order_date', '$email', 'New', '$order_date', '$currnt_userlogn');";
                mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
                
                $previous_order_date = date("Y-m-d H:i:s");
                $payment_refund_deadline = date('Y-m-d H:i:s', strtotime($previous_order_date . ' +96 hours'));

                // insert into wpk4_backend_travel_payment_history
                $sql = "INSERT INTO wpk4_backend_travel_payment_history 
                            (order_id, source, trams_remarks, trams_received_amount, reference_no, payment_method, process_date, pay_type, added_on, added_by, payment_change_deadline) 
                        VALUES ('$order_id', 'gds', '$trams_remarks', '$trams_received_amount', '$payment_reference', '$payment_method', '$order_date', 'deposit', '$order_date', '$currnt_userlogn', '$payment_refund_deadline');";
                mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
                
                $target_url = add_query_arg(array(
                    'action' => 'view',
                    'auto_id' => $new_auto_id
                ), 'add-booking-internal');
                wp_redirect($target_url);
                exit;
            }
            else {
                echo '<p style="text-align: center; color: red;">fail to save</p>';
            }
        }
        else {
            echo '<p style="text-align: center; color: red;">fail to save</p>';
        }
    }
}

else if(isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['auto_id']) && $_GET['auto_id'] != '') {
    $auto_id = $_GET['auto_id'];
    $sql2 = "SELECT * FROM `wpk4_backend_travel_bookings` WHERE auto_id='$auto_id';";
    $result2 = mysqli_query($mysqli, $sql2);
    if($row2 = mysqli_fetch_assoc($result2)) {
        ?>
        <h3 style="text-align: center;">Order Summary</h3>
        <div class='table-container'>
            <h5>Order Information</h5>
        	<table class="table">
        		<tr>
        			<td>Order ID</td>
        			<td>
        			    <?php 
                        echo sprintf('%06d', $row2['order_id']); 
                        if(isset($row2['co_order_id']) && $row2['co_order_id'] != '') { 
                            echo ' ' . $row2['co_order_id']; 
                        } 
                        ?>
        			</td>
        		</tr>
        		<tr>
        			<td>Order Date</td><td><?php echo $row2['order_date']; ?></td>
        		</tr>
        		<tr>
        			<td>Travel Type</td><td><?php echo $row2['t_type']; ?></td>
        		</tr>
        	</table>
        	<p style="text-align: center; color: green;">New order is successfully saved!</p>
		</div>
        <?php
        if(isset($_GET['trip-id']) && $_GET['trip-id'] != '' && isset($_GET['dep-date']) && $_GET['dep-date'] != '') {
            $trip_id = $_GET['trip-id'];
            $dep_date_for_product_manager = $_GET['dep-date'];
            $sql = "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager';";
            $result = mysqli_query($mysqli, $sql);
            if($row = mysqli_fetch_assoc($result)) {
                ?>
                <div class='table-container'>
                    <h5>Trip Information</h5>
                    <table class="table">
                        <tr>
                            <td>Product Title</td>
                            <td><input type='text' name='product_title' value='<?php echo $row['product_title']; ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Trip Code</td>
                            <td><input type='text' name='trip_code' value='<?php echo $row['trip_code']; ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Product ID</td>
                            <td><input type='text' name='product_id' value='<?php echo $row['product_id']; ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Travel Date</td>
                            <td><input type='text' name='travel_date' value='<?php echo $row['travel_date']; ?>' readonly></td>
                        </tr>
                        <tr>
                            <td>Pax Count</td>
                            <td><input type='text' name='total_pax' value='<?php echo $row2['total_pax']; ?>' readonly></td>
                        </tr>
                    </table>
                </div>
                <?php
            }
        }
    }
    else {
        echo '<p style="text-align: center; color: red;">fail to save</p>';
    }
}

else if(isset($_GET['action']) && $_GET['action'] == 'add' 
    && isset($_GET['trip-id']) && $_GET['trip-id'] != '' 
    && isset($_GET['dep-date']) && $_GET['dep-date'] != '' ) {
        
    $trip_id = $_GET['trip-id'];
    $dep_date_for_product_manager = $_GET['dep-date'];
    $seat_available = 999;
    if(isset($_GET['seat-available']) && $_GET['seat-available'] != '') {
        $seat_available = $_GET['seat-available'];
    }
    
    $sql = "SELECT * FROM wpk4_backend_stock_product_manager where trip_code='$trip_id' && travel_date='$dep_date_for_product_manager';";
    $result = mysqli_query($mysqli, $sql);
    if($row = mysqli_fetch_assoc($result)) {
        ?>
        <h3 style="text-align: center;">New Order</h3>
        <form id="mainForm" method="POST">
            <!-- Trip Information -->
            <div class='table-container'>
                <h5>Trip Information</h5>
                <table class="table">
                    <tr>
                        <td>Product Title</td>
                        <td><input type='text' name='product_title' value='<?php echo $row['product_title']; ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Trip Code</td>
                        <td><input type='text' name='trip_code' value='<?php echo $row['trip_code']; ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Product ID</td>
                        <td><input type='text' name='product_id' value='<?php echo $row['product_id']; ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Travel Date</td>
                        <td><input type='text' name='travel_date' value='<?php echo $row['travel_date']; ?>' readonly></td>
                    </tr>
                    <tr>
                        <td>Pax Count</td>
                        <td><input type='text' name='total_pax' id="form_count" value='0' readonly></td>
                    </tr>
                </table>
            </div>
            
            <!-- Add Pax Info -->
            <div id="form-container">
                <!-- Dynamically added forms will appear here -->
            </div>
            <input type="hidden" id="form_count" name="form_count" value="0">
            <button type="button" class="add-button" onclick="addNewTableForm()">+</button>
            <center><input type="submit" class="submit-button" name="save_booking" value="Place Order"></center>
            <script>
                let formCount = 0; // Initial form count
                const seatLimit = <?php echo $seat_available; ?>; // Seat limit from PHP
                function addNewTableForm() {
                    if (formCount < seatLimit) {
                        formCount++;
                        document.getElementById('form_count').value = formCount;
                        const formContainer = document.getElementById('form-container');
                        
                        const newTable = document.createElement('div');
                        newTable.classList.add('table-container');
                        newTable.id = `form_${formCount}`; // Unique ID for the form
                        newTable.innerHTML = `
                            <h6>Pax ${formCount}</h6>
                            <table class="table">
                                <tr>
                                    <td>Salutation</td><td><input type='text' name='${formCount}_salutation' value=''></td>
                                </tr>
                                <tr>
                                    <td>First Name</td><td><input type='text' name='${formCount}_fname' value=''></td>
                                </tr>
                                <tr>
                                    <td>Last Name</td><td><input type='text' name='${formCount}_lname' value=''></td>
                                </tr>
                                <tr>
                                    <td>Gender</td><td><input type='text' name='${formCount}_gender' value=''></td>
                                </tr>
                                <tr>
                                    <td>Passport Number</td><td><input type='text' name='${formCount}_ppn' value=''></td>
                                </tr>
                                <tr>
                                    <td>Passport Expiry</td><td><input type='text' class='date-picker' name='${formCount}_ppe' value=''></td>
                                </tr>
                                <tr>
                                    <td>DOB</td><td><input type='text' class='date-picker' name='${formCount}_dob' value=''></td>
                                </tr>
                                <tr>
                                    <td>Country</td><td><input type='text' name='${formCount}_country' value=''></td>
                                </tr>
                                <tr>
                                    <td>Meal</td><td><input type='text' name='${formCount}_meal' value=''></td>
                                </tr>
                                <tr>
                                    <td>Wheelchair</td><td><input type='text' name='${formCount}_wheelchair' value=''></td>
                                </tr>
                                <tr>
                                    <td>Phone</td><td><input type='text' name='${formCount}_phone_pax' value=''></td>
                                </tr>
                                <tr>
                                    <td>Email</td><td><input type='text' name='${formCount}_email_pax' value=''></td>
                                </tr>
                            </table>
                            <button type="button" class="delete-button" style="background-color: red;" onclick="deleteForm(${formCount})">-</button>
                        `;
                
                        formContainer.appendChild(newTable);
                
                        // Initialize the date picker for the newly added fields
                        $(`#form_${formCount} .date-picker`).datepicker({
                            dateFormat: 'yy-mm-dd',
                            yearRange: "1900:2100"
                        });
                    } else {
                        alert('No more seats available');
                    }
                }
                function deleteForm(count) {
                    const formToDelete = document.getElementById(`form_${count}`);
                    if (formToDelete) {
                        formToDelete.remove();
                        formCount--;
                        document.getElementById('form_count').value = formCount;
                        updatePaxNumbers();
                    }
                }
                function updatePaxNumbers() {
                    const allForms = document.querySelectorAll('.table-container');
                    allForms.forEach((form, index) => {
                        form.querySelector('h6').textContent = `Pax ${index + 1}`;
                        form.id = `form_${index + 1}`;
                        const inputs = form.querySelectorAll('input');
                        inputs.forEach(input => {
                            input.name = `${index + 1}_${input.name.split('_').slice(1).join('_')}`;
                        });
                    });
                }
            </script>
        </form>
        <?php
    }
    
    // Handle form submission
    if (isset($_POST['save_booking'])) 
    {
        $product_title = $_POST['product_title'];
        $trip_code = $_POST['trip_code'];
        $product_id = $_POST['product_id'];
        $travel_date = $_POST['travel_date'];
        $total_pax = $_POST['total_pax'];
        
        $query_get_last_id = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id > 90000000 order by order_id desc limit 1";
                    $result_get_last_id = mysqli_query($mysqli, $query_get_last_id) or die(mysqli_error($mysqli));
                    $row_get_last_id = mysqli_fetch_assoc($result_get_last_id);
                    $new_orderID = $row_get_last_id['order_id'] + 1;
                    
        $date_current = date('Y-m-d H:i:s');
        
        $new_pnr = '';
        $travel_date_ymd = date('Y-m-d', strtotime($travel_date));
                    $query_select_pnr = "SELECT pnr FROM wpk4_backend_stock_management_sheet WHERE trip_id ='$trip_code' AND date(dep_date) ='$travel_date_ymd' ";
                    $result_select_pnr = mysqli_query($mysqli, $query_select_pnr) or die(mysqli_error($mysqli));
                    if(mysqli_num_rows($result_select_pnr) > 0)
                    {
                        $row_select_pnr = mysqli_fetch_assoc($result_select_pnr);
                        $new_pnr = $row_select_pnr['pnr'];
                    }
                    
                    
        $insert_sql_book = "
                        INSERT INTO `wpk4_backend_travel_bookings` (
                            `order_type`, `order_id`, `order_date`, `t_type`, `product_title`, `trip_code`, 
                            `product_id`, `travel_date`, `total_pax`, `payment_status`, `source`, `added_on`,
                            `added_by` )
                        VALUES (
                            'Agent', '$new_orderID', '$date_current', 'oneway', '$product_title', '$trip_code', 
                            '$product_id', '$travel_date', '$total_pax', 'partially_paid', 'import', '$date_current', 
                            '$currnt_userlogn'
                        )
                    ";
        mysqli_query($mysqli, $insert_sql_book);

        $new_auto_id = mysqli_insert_id($mysqli);
        
        // update wpk4_backend_travel_booking_pax
        if(isset($new_auto_id) && $new_auto_id != '') 
        {
            $sql = "SELECT * FROM `wpk4_backend_travel_bookings` WHERE auto_id='$new_auto_id';";
            $result2 = mysqli_query($mysqli, $sql);
            if ($row = mysqli_fetch_assoc($result2)) 
            {
                for ($i = 1; $i <= $total_pax; $i++) 
                {
                    $salutation = mysqli_real_escape_string($mysqli, $_POST[$i . '_salutation']);
                    $fname = mysqli_real_escape_string($mysqli, $_POST[$i . '_fname']);
                    $lname = mysqli_real_escape_string($mysqli, $_POST[$i . '_lname']);
                    $gender = mysqli_real_escape_string($mysqli, $_POST[$i . '_gender']);
                    $ppn = mysqli_real_escape_string($mysqli, $_POST[$i . '_ppn']);
                    $ppe = mysqli_real_escape_string($mysqli, $_POST[$i . '_ppe']);
                    $dob = mysqli_real_escape_string($mysqli, $_POST[$i . '_dob']);
                    $country = mysqli_real_escape_string($mysqli, $_POST[$i . '_country']);
                    $meal = mysqli_real_escape_string($mysqli, $_POST[$i . '_meal']);
                    $wheelchair = mysqli_real_escape_string($mysqli, $_POST[$i . '_wheelchair']);
                    $phone_pax = mysqli_real_escape_string($mysqli, $_POST[$i . '_phone_pax']);
                    $email_pax = mysqli_real_escape_string($mysqli, $_POST[$i . '_email_pax']);
            
                    $order_type = mysqli_real_escape_string($mysqli, $row['order_type']);
                    $order_id = mysqli_real_escape_string($mysqli, $row['order_id']);
                    $order_date = mysqli_real_escape_string($mysqli, $row['order_date']);
                    $product_id = mysqli_real_escape_string($mysqli, $row['product_id']);
                    $payment_status = mysqli_real_escape_string($mysqli, $row['payment_status']);
            
                    $insert_sql = "
                        INSERT INTO `wpk4_backend_travel_booking_pax` (
                            `salutation`, `fname`, `lname`, `gender`, `ppn`, `ppe`, 
                            `dob`, `country`, `meal`, `wheelchair`, `phone_pax`, `email_pax`,
                            `order_type`, `order_id`, `order_date`, `product_id`, `payment_status`, `pax_status`, `added_on`, `added_by`, `pnr`
                        ) VALUES (
                            '$salutation', '$fname', '$lname', '$gender', '$ppn', '$ppe', 
                            '$dob', '$country', '$meal', '$wheelchair', '$phone_pax', '$email_pax', 
                            '$order_type', '$order_id', '$order_date', '$product_id', '$payment_status', 'New', '$order_date', '$currnt_userlogn', '$new_pnr'
                        )
                    ";
                    echo $insert_sql;
            
                    mysqli_query($mysqli, $insert_sql);
                }
                /*
                $target_url = add_query_arg(array(
                    'action' => 'view',
                    'auto_id' => $new_auto_id,
                    'trip-id' => $trip_code,
                    'dep-date' => $travel_date
                ), 'add-booking-internal');
                wp_redirect($target_url);
                exit;
                */
                echo '<script>window.location.href="?action=view&auto_id='.$new_auto_id.'&trip-id='.$trip_code.'&dep-date='.$travel_date.'";</script>';

            }
            else {
                echo '<p style="text-align: center; color: red;">fail to save</p>';
            }
        }
        else {
            echo '<p style="text-align: center; color: red;">fail to save</p>';
        }
    }
}

// redirect to create
else {
    echo "<script>window.location.href = '?action=create';</script>";
}

?>

<style>
    .table-container {
        margin: 20px auto;
        width: 80%;
        border: 1px solid black;
        padding: 1%;
        padding-top: 0;
    }
    .table {
        width: 100%;
        margin-bottom: 20px;
    }
    .add-button, .delete-button {
        display: block;
        margin: 20px auto;
        padding: 10px 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 16px;
    }
    .submit-button {
        display: block;
        margin: 20px auto;
    }
</style>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<?php get_footer(); ?>
