<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set("Australia/Melbourne"); 
$current_date_time = date('Y-m-d H:i:s');

/// Database connection
require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Get current user from URL parameter
$current_user = isset($_GET['current_user']) ? $_GET['current_user'] : '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remark'])) {
    $case_id = $_POST['case_id'];
    $reservation_ref = $_POST['reservation_ref'];
    $remark = $mysqli->real_escape_string($_POST['remark']);
    $current_user = $mysqli->real_escape_string($_POST['current_user']);
    $request_type = $mysqli->real_escape_string($_POST['request_type']);
    $failed_reason = $mysqli->real_escape_string($_POST['failed_reason']);
    $remark_type = $mysqli->real_escape_string($_POST['remark_type']);
    
    $query = "INSERT INTO wpk4_backend_dc_remark 
              (case_id, reservation_ref, remark, request_type, failed_reason, created_by, created_on, remark_type) 
              VALUES ('$case_id', '$reservation_ref', '$remark', '$request_type', '$failed_reason', '$current_user', '$current_date_time', '$remark_type')";
    
    if ($mysqli->query($query)) {
        echo "<script>alert('Remark submitted successfully!'); window.close();</script>";
    } else {
        echo "<script>alert('Error submitting remark: " . $mysqli->error . "');</script>";
    }
}

// Get parameters from URL
$case_id = isset($_GET['case_id']) ? $_GET['case_id'] : '';
$reservation_ref = isset($_GET['reservation_ref']) ? $_GET['reservation_ref'] : '';
$current_user = isset($_GET['current_user']) ? $_GET['current_user'] : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Remark</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        textarea, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        textarea {
            min-height: 100px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-submit {
            background: #28a745;
            color: white;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Remark</h2>
        <p><strong>Case ID:</strong> <?php echo htmlspecialchars($case_id); ?></p>
        <p><strong>Reservation Ref:</strong> <?php echo htmlspecialchars($reservation_ref); ?></p>
        <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($current_user); ?></p>
        
        <form method="POST">
            <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
            <input type="hidden" name="reservation_ref" value="<?php echo htmlspecialchars($reservation_ref); ?>">
            <input type="hidden" name="current_user" value="<?php echo htmlspecialchars($current_user); ?>">

            <label for="request_type">Request Type:</label>
            <select name="request_type" >
                <option value="">-- Select --</option>
                <option value="datechange">Date Change</option>
                <option value="Name Change">Name Change</option>
                <option value="SSR">SSR</option>
                <option value="Seat Request">Seat Request</option>
                <option value="Unaccompanied minor/infant addition">Unaccompanied minor/infant addition</option>
            </select>
            
            <label for="remark_type">Remark Type:</label>
            <select name="remark_type" id="remark_type">
                <option value="">-- Select --</option>
                <option value="in progress">In Progress</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </select>
            
            <label for="failed_reason">Reason:</label>
            <select name="failed_reason" id="failed_reason">
                <option value="">-- Select --</option>
            </select>
            
            <script>
            const failedReasonSelect = document.getElementById("failed_reason");
            const remarkTypeSelect = document.getElementById("remark_type");
            
            // Default failed options
            const failedOptions = [
                "Refund", "Plan Change", "Change Cost", "Seats not available",
                "Changes not permitted", "Duplicate Case", "Invalid",
                "No Response", "None", "Others"
            ];
  
            // Success options
            const successOptions = [
                "Date change confirmed", "Name correction confirmed", "SSR confirmed", "Infant addition confirmed"
            ];
            
            const inProgressOptions = [
                "Awaiting Airline Response", "Awaiting HO Response", "Awaiting Pax Response", "Voice Mail", "Complaint Case", "Funds Issue", "Technical Issue"
            ];
            
            // Helper to update options
            function setOptions(options) {
                failedReasonSelect.innerHTML = '<option value="">-- Select --</option>';
                options.forEach(opt => {
                    let option = document.createElement("option");
                    option.value = opt;
                    option.textContent = opt;
                    failedReasonSelect.appendChild(option);
                });
            }
            
            // Listen for changes
            remarkTypeSelect.addEventListener("change", function() {
                if (this.value === "success") {
                    setOptions(successOptions);
                } else if (this.value === "failed") {
                    setOptions(failedOptions);
                }
                else if (this.value === "in progress") {
                    setOptions(inProgressOptions);
                }
                else {
                    failedReasonSelect.innerHTML = '<option value="">-- Select --</option>';
                }
            });
            </script>


            <label for="remark">Remark:</label>
            <textarea name="remark" required></textarea>
            
            <button type="button" class="btn btn-cancel" onclick="window.close()">Cancel</button>
            <button type="submit" name="submit_remark" class="btn btn-submit">Submit</button>
        </form>
    </div>
</body>
</html>
