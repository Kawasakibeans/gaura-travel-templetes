<?php

/**
 * Template Name: Import Stock Update
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();
date_default_timezone_set("Australia/Melbourne");
global $current_user;
wp_get_current_user();
$currnt_userlogn = $current_user->user_login;
$current_time = date('Y-m-d H:i:s');
?>
<html>

<head>
    <style>
    .content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1em;
    }

    .content span {
        color: gray;
        font-size: 13px;
        margin-top: -2%;
    }

    .upload-content {
        text-align: center;
        padding: 10em 15em;
    }

    .select-csv-form {
        border: 1px solid #2424;
        border-style: dashed;
        padding: 2em;
    }

    .select-csv-form .content {}

    .select-csv-form .content i {
        color: gray;
    }

    input[type="submit"] {
        font-size: 12px;
        padding: 1em 3em;
        border-radius: 5px
    }

    input::file-selector-button {
        font-size: 14px;
        padding: 1em 1.2em;
        border-radius: 5px;
        border: none;
        cursor: pointer;
    }
    </style>
</head>

<body>
    <div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:100%;'>
        <?php
		// error_reporting(E_ALL);
		include("wp-config-custom.php");
		$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
		$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
		$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
		$is_ip_matched = mysqli_num_rows($result_ip_selection);
		if ($row_ip_selection['ip_address'] == $ip_address) {

			if (current_user_can('administrator') || current_user_can( 'ho_operations' ) ) {
				if (!isset($_GET['pg'])) {
					$TEMPLATE_URL = 'https://gauratravel.com.au/wp-content/uploads/2025/02/template_import_stock_price-1.csv';
		?>
        <div class="upload-content">
            <form class="select-csv-form " action="?pg=check" method="post" name="uploadCSV"
                enctype="multipart/form-data">
                <div class="content">
                    <i class="fa fa-upload fa-2x" aria-hidden="true"></i>
                    <label class="col-md-4 control-label">Update the Stock</label>
                    <span>Files Supported: CSV</span>
                    <a href=<?php echo $TEMPLATE_URL ?> style="font-size:12px; ">Download Template</a>
                    <input type="file" required name="file" id="file" accept=".csv">
                    <input type="submit" id="submit" name="import_stock_update"></input>
                </div>
                <div id="labelError"></div>
            </form>
        </div>

        <?php
				}
				if (isset($_GET['pg']) && $_GET['pg'] == 'check') {

					//$target_file = basename($_FILES["file"]["name"]);

					// IMPORT PRICING START
					if (isset($_POST["import_stock_update"])) {
						$fileName = $_FILES["file"]["tmp_name"];
						if ($_FILES["file"]["size"] > 0) {
							$file = fopen($fileName, "r");
							echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
							$table_string = "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
											<tr>
												<td>#</td>
												<td>PNR</td>
												<td>mh_endorsement</td>
												<td>Action</td>
											</tr>";
							$autonumber = 1;

							while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
								if ($column[0] == 'PNR' && $column[1] == 'Stock') {
									// Do Nothing
								} else {
								    $auto_id = $column[0];
									$pnr = $column[1];
									$updated_stock = $column[2];
									
                                    $pnr_from_table = 'DUMMY';
									$sql = "SELECT * FROM wpk4_backend_stock_management_sheet where pnr = '$pnr'";
									$result = $mysqli->query($sql);
									if ($result->num_rows > 0)
									{
									    $row = $result->fetch_assoc();
									    $pnr_from_table = $row['pnr'];
								    }

									$table_string .= "<tr>
														<td>" . $autonumber . "</td>
														<td>" . $pnr . "</td>
														<td>" . $updated_stock . "</td>
														
														";

									if ($pnr == $pnr_from_table) {
										$match_hidden = 'Existing';
										$match = "<font style='color:red;'>Existing & will be overwrite</font>";
										$checked = "checked";
									} else {
										$match_hidden = 'New';
										$match = "New";
										$checked = "disabled";
									}

									$table_string .= "		
									<td>								
									<input type='hidden' name='" . $pnr . "_matchmaker' value='" . $match_hidden . "'>
									" . $match . "</td>";

									$table_string .= "<td><input type='checkbox' id='chk" . $pnr . "' name='" . $pnr . "_checkoption' value='" . $auto_id . "@#" . $pnr . "@#" . $updated_stock . "'" . $checked . " \/></td>
									</tr>";

									$autonumber++;
								}
							}

							$table_string .= "</table>";
							echo $table_string;
					?>
        <br><br><input type="submit" name="submit_stock_update" value="Update" /></form>
        </center>
        <?php
						}
					}
					if (isset($_POST["submit_stock_update"])) {
						foreach ($_POST as $post_fieldname => $post_fieldvalue) {
							$post_name_dividants = explode('_', $post_fieldname);
							$postname_pnr = $post_name_dividants[0];
							$postname_fieldname = $post_name_dividants[1];
							$check_whether_its_ticked = $postname_pnr . '_checkoption';

							if ($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked])) 
							{
								$post_value_get = $_POST[$post_fieldname];
								$post_values = explode('@#', $post_value_get);
								$auto_idpost = $post_values[0];
								$pnr = $post_values[1];
								$updated_stock = $post_values[2];
								
								$sql_update_status = "UPDATE wpk4_backend_stock_management_sheet SET mh_endorsement = '$updated_stock', pnr = '$pnr' WHERE auto_id = '$auto_idpost'";
								$result_status = mysqli_query($mysqli, $sql_update_status) or die(mysqli_error($mysqli));
								
								
								$values = array(
									array($pnr, "pnr", $pnr, $currnt_userlogn, $current_time),
									array($pnr, "mh_endorsement", $updated_stock, $currnt_userlogn, $current_time),
								);

								// Loop through the array and insert each row into the database
								foreach ($values as $row) {
									$type_id = $row[0];
									$meta_key = $row[1];
									$meta_value = $row[2];
									$updated_by = $row[3];
									$updated_on = $row[4];

									mysqli_query($mysqli, "insert into wpk4_backend_history_of_updates (type_id, meta_key, meta_value, updated_by, updated_on) 
									values ('$type_id', '$meta_key', '$meta_value', '$updated_by', '$updated_on')") or die(mysqli_error($mysqli));
								}
							}
						}
						echo '<script>alert("Updated successfully.");</script>';
						echo '<script>window.location.href="?";</script>';
					}
					?>
    </div>
</body>
<?php
				}
			}
		} else {
			echo "<center>This page is not accessible for you.</center>";
		}
?>
<?php get_footer(); ?>