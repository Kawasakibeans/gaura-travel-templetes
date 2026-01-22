<?php
/**
 * Template Name: Import Update Stocksheet by ID
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Karthik Peerlagudem
 * @usage - to import data into stock table by auto_id. currently used to update the existing PNR column.
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
include("wp-config-custom.php");

// Load WordPress to access constants
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

// Use API_BASE_URL constant if defined, otherwise use default
if (defined('API_BASE_URL')) {
    /** @var string $api_url */
    $api_url = constant('API_BASE_URL');
} else {
    $api_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';
}

$current_time = date('Y-m-d H:i:s');

/**
 * Helper function to preview stock PNR update via API
 * 
 * @param string $api_url Base API URL
 * @param array $csv_data CSV data as array of rows
 * @return array|array API response data on success, error array on failure
 */
function previewStockPnrUpdateViaAPI($api_url, $csv_data) {
    // Validate inputs
    if (empty($api_url)) {
        error_log("[Stock PNR Update Import] Error: API URL is empty");
        return ['error' => 'API URL is empty', 'debug' => 'api_url_empty'];
    }
    
    if (empty($csv_data) || !is_array($csv_data)) {
        error_log("[Stock PNR Update Import] Error: CSV data is empty or not an array");
        return ['error' => 'CSV data is empty or invalid', 'debug' => 'csv_data_invalid'];
    }
    
    $requestData = [
        'csv_data' => $csv_data
    ];
    
    // Encode JSON and check for errors
    $jsonData = json_encode($requestData);
    if ($jsonData === false) {
        $jsonError = json_last_error_msg();
        error_log("[Stock PNR Update Import] JSON encode error: $jsonError");
        return ['error' => 'Failed to encode request data', 'debug' => 'json_encode_failed', 'detail' => $jsonError];
    }
    
    $apiEndpoint = $api_url . '/stock/pnr-update/preview';
    error_log("[Stock PNR Update Import] Calling API: $apiEndpoint");
    error_log("[Stock PNR Update Import] Request data size: " . strlen($jsonData) . " bytes, CSV rows: " . count($csv_data));
    
    $ch = curl_init($apiEndpoint);
    if ($ch === false) {
        error_log("[Stock PNR Update Import] cURL init failed");
        return ['error' => 'Failed to initialize cURL', 'debug' => 'curl_init_failed'];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false, // For debugging - remove in production if not needed
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        error_log("[Stock PNR Update Import] cURL error #$curlErrno: $curlError");
        return ['error' => 'cURL request failed', 'debug' => 'curl_error', 'detail' => $curlError, 'errno' => $curlErrno];
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("[Stock PNR Update Import] API call failed. HTTP Code: $httpCode");
        error_log("[Stock PNR Update Import] Response: " . substr($response, 0, 500));
        return ['error' => 'API returned error status', 'debug' => 'http_error', 'http_code' => $httpCode, 'response' => substr($response, 0, 500)];
    }
    
    if (empty($response)) {
        error_log("[Stock PNR Update Import] API returned empty response");
        return ['error' => 'API returned empty response', 'debug' => 'empty_response'];
    }
    
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonError = json_last_error_msg();
        error_log("[Stock PNR Update Import] JSON decode error: $jsonError");
        error_log("[Stock PNR Update Import] Response preview: " . substr($response, 0, 200));
        return ['error' => 'Failed to decode API response', 'debug' => 'json_decode_failed', 'detail' => $jsonError, 'response_preview' => substr($response, 0, 200)];
    }
    
    // Check if response has expected structure
    // API returns: { "status": "success", "data": { "success": true, "preview": [...] } }
    if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['data'])) {
        // Return the data part which contains success and preview
        error_log("[Stock PNR Update Import] API response structure: status=" . $responseData['status'] . ", data exists");
        return $responseData['data'];
    }
    
    // Fallback: check for direct success field (old format)
    if (isset($responseData['success'])) {
        error_log("[Stock PNR Update Import] API response has direct success field");
        return $responseData;
    }
    
    // If neither structure matches, log and return error
    error_log("[Stock PNR Update Import] API response format unexpected");
    error_log("[Stock PNR Update Import] Response structure: " . print_r(array_keys($responseData), true));
    error_log("[Stock PNR Update Import] Full response: " . print_r($responseData, true));
    $responsePreview = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return [
        'error' => 'API response format invalid - unexpected structure', 
        'debug' => 'unexpected_response_format', 
        'response_keys' => array_keys($responseData),
        'response_preview' => substr($responsePreview, 0, 1000),
        'full_response' => $responseData
    ];
}

/**
 * Helper function to import stock PNR update via API
 * 
 * @param string $api_url Base API URL
 * @param array $records Array of records to import
 * @param string $updated_by User who is importing
 * @return array|false API response data on success, false on failure
 */
function importStockPnrUpdateViaAPI($api_url, $records, $updated_by) {
    $requestData = [
        'records' => $records,
        'updated_by' => $updated_by
    ];
    
    $ch = curl_init($api_url . '/stock/pnr-update/import');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("[Stock PNR Update Import] Import cURL error: $curlError");
        return false;
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Check if response has expected structure
            // API returns: { "status": "success", "data": { "success": true, ... } }
            if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['data'])) {
                // Return the data part which contains success
                return $responseData['data'];
            }
            // Fallback: check for direct success field (old format)
            return $responseData;
        } else {
            error_log("[Stock PNR Update Import] Import JSON decode error: " . json_last_error_msg());
            return false;
        }
    } else {
        error_log("[Stock PNR Update Import] Import API call failed. HTTP Code: $httpCode, Response: $response");
        return false;
    }
}

$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
if($row_ip_selection['ip_address'] == $ip_address)
{

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    
    if(current_user_can( 'administrator' ) || current_user_can( 'ho_operations' ))
    {
        // general form to get csv file
        if(!isset($_GET['pg']))
	    {
	    ?>
		<center>
		</br></br></br>
		<form class="form-horizontal" action="?pg=check" method="post" name="uploadCSV" enctype="multipart/form-data">
			<div class="input-row">
				<label class="col-md-4 control-label">Choose CSV File</label>
				<a href="https://gauratravel.com.au/wp-content/uploads/2024/01/template_import_stock_update_pnr.csv" style="font-size:12px; ">Download Template</a></br></br>
				<input type="file" required name="file" id="file" accept=".csv" style="display:block;">
				<button type="submit" id="submit" style='height:30px; width:70px; font-size:12px; padding:7px; margin:0px;' name="import_pricing">Submit</button>
				<br />
			</div>
			<div id="labelError"></div>
		</form>
		</center>
        <?php
	    }
	    
        if(isset($_GET['pg']) && $_GET['pg'] == 'check')
	    {
    		// IMPORT PRICING START
    		if (isset($_POST["import_pricing"])) 
    			{
    				// Validate file upload
    				if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    					$uploadError = isset($_FILES["file"]["error"]) ? $_FILES["file"]["error"] : 'File not uploaded';
    					$errorMessages = [
    						UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit. Please upload a smaller file.',
    						UPLOAD_ERR_FORM_SIZE => 'File size exceeds maximum allowed size.',
    						UPLOAD_ERR_PARTIAL => 'File upload was incomplete. Please try again.',
    						UPLOAD_ERR_NO_FILE => 'No file was selected. Please choose a CSV file.',
    						UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error. Please contact support.',
    						UPLOAD_ERR_CANT_WRITE => 'Failed to save uploaded file. Please try again.',
    						UPLOAD_ERR_EXTENSION => 'File upload was blocked. Please contact support.'
    					];
    					$errorMsg = isset($errorMessages[$uploadError]) ? $errorMessages[$uploadError] : "File upload failed. Please try again.";
    					echo '<center>';
    					echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    					echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    					echo '<p style="margin:10px 0 0 0;">' . htmlspecialchars($errorMsg) . '</p>';
    					echo '</div>';
    					echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    					echo '</center>';
    					error_log("[Stock PNR Update Import] File upload error: $uploadError");
    				}
    				elseif ($_FILES["file"]["size"] > 0) 
    				{
    					$fileName = $_FILES["file"]["tmp_name"];
    					
    					// Validate file exists and is readable
    					if (!file_exists($fileName)) {
    						echo '<center>';
    						echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    						echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    						echo '<p style="margin:10px 0 0 0;">Uploaded file not found. Please try uploading again.</p>';
    						echo '</div>';
    						echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    						echo '</center>';
    						error_log("[Stock PNR Update Import] File not found: $fileName");
    					}
    					elseif (!is_readable($fileName)) {
    						echo '<center>';
    						echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    						echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    						echo '<p style="margin:10px 0 0 0;">Cannot read uploaded file. Please try again.</p>';
    						echo '</div>';
    						echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    						echo '</center>';
    						error_log("[Stock PNR Update Import] File not readable: $fileName");
    					}
    					else
    					{
    						$file = fopen($fileName, "r");
    						if ($file === false) {
    							echo '<center>';
    							echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    							echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    							echo '<p style="margin:10px 0 0 0;">Failed to open CSV file. Please check the file format and try again.</p>';
    							echo '</div>';
    							echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    							echo '</center>';
    							error_log("[Stock PNR Update Import] Failed to open file: $fileName");
    						}
    						else
    						{
    							// Read CSV data into array (skip header row)
    							$csv_data = [];
    							$rowCount = 0;
    							$isFirstRow = true;
    							while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
    								// Skip the first row (header)
    								if ($isFirstRow) {
    									$isFirstRow = false;
    									continue;
    								}
    								// Skip empty rows
    								if (empty(array_filter($column))) {
    									continue;
    								}
    								$csv_data[] = $column;
    								$rowCount++;
    							}
    							fclose($file);
    							
    							error_log("[Stock PNR Update Import] CSV file read successfully. Data rows: $rowCount (header skipped)");
    							
    							if (empty($csv_data)) {
    								echo '<center>';
    								echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    								echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    								echo '<p style="margin:10px 0 0 0;">CSV file is empty or contains no valid data. Please check your file and try again.</p>';
    								echo '</div>';
    								echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    								echo '</center>';
    								error_log("[Stock PNR Update Import] CSV data is empty");
    							}
    							else
    							{
    								// Call API to preview
    								$result = previewStockPnrUpdateViaAPI($api_url, $csv_data);
    								
    								if ($result === false || !isset($result['success']) || !$result['success']) {
    									// Display user-friendly error message
    									$errorMsg = 'Failed to preview CSV data. Please check your file format and try again.';
    									
    									if (is_array($result) && isset($result['error'])) {
    										// Map technical errors to user-friendly messages
    										$technicalError = $result['error'];
    										if (strpos($technicalError, 'cURL') !== false || strpos($technicalError, 'network') !== false) {
    											$errorMsg = 'Unable to connect to the server. Please check your internet connection and try again.';
    										} elseif (strpos($technicalError, 'HTTP') !== false || (isset($result['http_code']) && $result['http_code'] >= 500)) {
    											$errorMsg = 'Server error occurred. Please try again later.';
    										} elseif (strpos($technicalError, 'JSON') !== false || strpos($technicalError, 'decode') !== false) {
    											$errorMsg = 'Invalid response from server. Please contact support.';
    										} elseif (strpos($technicalError, 'empty') !== false) {
    											$errorMsg = 'The server returned an empty response. Please try again.';
    										}
    									}
    									
    									echo '<center>';
    									echo '<div style="color:red; padding:20px; border:2px solid #d32f2f; border-radius:5px; max-width:600px; background:#ffebee; margin:20px;">';
    									echo '<p style="margin:0; font-size:16px;"><strong>Error</strong></p>';
    									echo '<p style="margin:10px 0 0 0;">' . htmlspecialchars($errorMsg) . '</p>';
    									echo '</div>';
    									echo '<br><a href="?" style="padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Back to Upload</a>';
    									echo '</center>';
    								} else {
    									// Display preview table
    									echo '<center><form action="#" name="statusupdate" method="post" enctype="multipart/form-data">';
    									$tablestirng = "<table class='table table-striped' id='example' style='width:95%; font-size:13px;'>
    									<tr>
    										<td>Auto ID</td>
    										<td>Old PNR</td>
    										<td>New PNR</td>
    									</tr>
    									";
    									
    									$preview = $result['preview'] ?? [];
    									if (empty($preview)) {
    										echo '<center><p style="color:orange;">No preview data returned from API.</p></center>';
    									} else {
    										foreach ($preview as $item) {
    											$auto_id = $item['auto_id'];
    											$old_pnr = $item['old_pnr'];
    											$new_pnr = $item['new_pnr'];
    											$match_hidden = $item['match_status'];
    											$match = ($match_hidden === 'Existing') ? 'Existing' : "<font style='color:red;'>New Record</font>";
    											$checked = $item['checked'] ? 'checked' : 'disabled';
    											
    											$tablestirng .= "<tr>
    												<td>" . htmlspecialchars($auto_id) . "</td>
    												<td>" . htmlspecialchars($old_pnr) . "</td>
    												<td>" . htmlspecialchars($new_pnr) . "</td>
    												<td>
    													<input type='hidden' name='" . htmlspecialchars($auto_id) . "_matchmaker' value='" . htmlspecialchars($match_hidden) . "'>
    													" . $match . "
    												</td>
    												<td>
    													<input type='checkbox' id='chk" . htmlspecialchars($auto_id) . "' name='" . htmlspecialchars($auto_id) . "_checkoption' value='" . htmlspecialchars($auto_id) . "@#" . htmlspecialchars($new_pnr) . "@#" . htmlspecialchars($old_pnr) . "@#" . htmlspecialchars($match_hidden) . "' " . $checked . " />
    												</td>
    											</tr>";
    										}
    										
    										$tablestirng .= "</table>";
    										echo $tablestirng;
    										?>
    										<br><br><button type="submit" name="submit_stock_update" value="Update">Submit</button></form></center>
    										<?php
    									}
    								}
    							}
    						}
    					}
    				}
    			}
    			if (isset($_POST["submit_stock_update"])) 
    			{
    			    // loop through the submitted values
    				$records = [];
    				
    				foreach ($_POST as $post_fieldname => $post_fieldvalue) 
    				{
    					$post_name_dividants = explode('_', $post_fieldname); // divide the post name to identify the id and column name
    					$postname_auto_id = $post_name_dividants[0];
    					$postname_fieldname = $post_name_dividants[1];
    					$check_whether_its_ticked = $postname_auto_id.'_checkoption';
    					
    					if($postname_fieldname == 'checkoption' && isset($_POST[$check_whether_its_ticked]))
    					{
    						$post_value_get = $_POST[$post_fieldname];
    						$post_values = explode('@#', $post_value_get);
    						$auto_id_from_table_post = $post_values[0];	
    						$new_pnr = $post_values[1];
    						$old_pnr = $post_values[2];
    						$match_hidden = $post_values[3] ?? 'Existing';
    						
    						$records[] = [
    							'auto_id' => $auto_id_from_table_post,
    							'old_pnr' => $old_pnr,
    							'new_pnr' => $new_pnr,
    							'match_hidden' => $match_hidden
    						];
    					}
    				}
    				
    				if (!empty($records)) {
    					// Call API to import
    					$result = importStockPnrUpdateViaAPI($api_url, $records, $currnt_userlogn);
    					
    					if ($result === false || !isset($result['success']) || !$result['success']) {
    						echo '<script>alert("Error: Failed to import records. Please try again.");</script>';
    					} else {
    						echo '<script>alert("Updated successfully.");</script>';
    					}
    				} else {
    					echo '<script>alert("No records selected for import.");</script>';
    				}
    				
    				echo '<script>window.location.href="?";</script>';
    			}
    		}
    }
}
else
{
echo "<center>This page is not accessible for you.</center>";
}
?>
</div>
<?php get_footer(); ?>
