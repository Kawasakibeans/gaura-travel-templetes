<?php
/**
 * Template Name: EOD Sales Report
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Haodong & Sri
 * @to manage all the sale dashboard view for past and realtime.
 */
get_header();
include("wp-config-custom.php");
$filter_days = 31;
global $current_user; 
wp_get_current_user();
$currnt_userlogn = $current_user->user_login;


function callSalesReportAPI($endpoint, $params = array()) {
    // Use the correct API base URL
    if (!defined('API_BASE_URL')) {
        define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1');
    }
    
    $url = API_BASE_URL . '/sales-report' . $endpoint;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $full_url = $url;
    
    if (function_exists('wp_remote_get')) {
        $response = wp_remote_get($full_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        if (is_wp_error($response)) {
            error_log('API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
    } else {
        $ch = curl_init($full_url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
            ),
        ));
        
        $body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code !== 200 || $body === false) {
            error_log('Sales Report API Error: HTTP ' . $http_code . ' URL: ' . $full_url);
            if ($curl_error) {
                error_log('cURL Error: ' . $curl_error);
            }
            if ($body) {
                error_log('Response body: ' . substr($body, 0, 500));
            }
            return array('status' => 'error', 'message' => 'API call failed: HTTP ' . $http_code, 'http_code' => $http_code, 'url' => $full_url);
        }
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Sales Report JSON Decode Error: ' . json_last_error_msg());
        error_log('Response body (first 500 chars): ' . substr($body, 0, 500));
        return array('status' => 'error', 'message' => 'JSON decode error: ' . json_last_error_msg(), 'raw_body' => substr($body, 0, 500));
    }
    
    return $data;
}

function getTeamsData() {
    $data = callSalesReportAPI('/teams');
    
    // Debug logging
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Teams API Response: ' . print_r($data, true));
    }
    
    if (!$data) {
        return array();
    }
    
    $teams = array();
    
    // API returns data in 'data' field with 'teams' array
    if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']['teams'])) {
        foreach ($data['data']['teams'] as $team) {
            if (isset($team['team_name']) && isset($team['team_leader'])) {
                $teams[$team['team_name']] = $team['team_leader'];
            }
        }
    } elseif (isset($data['teams'])) {
        // Direct teams array
        foreach ($data['teams'] as $team) {
            if (isset($team['team_name']) && isset($team['team_leader'])) {
                $teams[$team['team_name']] = $team['team_leader'];
            } elseif (is_string($team)) {
                // If teams is just an array of team names
                $teams[$team] = '';
            }
        }
    } elseif (isset($data['data']) && is_array($data['data'])) {
        // If data is array of team names
        foreach ($data['data'] as $teamName) {
            if (is_string($teamName)) {
                $teams[$teamName] = '';
            }
        }
    }
    
    return $teams;
}

function getDashboardData($filters = array()) {
    $params = array();
    
    if (isset($filters['date'])) {
        $params['date'] = $filters['date'];
    }
    if (isset($filters['team'])) {
        $params['team'] = $filters['team'];
    }
    if (isset($filters['filter'])) {
        $params['filter'] = $filters['filter'];
    }
    if (isset($filters['group_by'])) {
        $params['group_by'] = $filters['group_by'];
    }
    
    $data = callSalesReportAPI('/dashboard', $params);
    return $data;
}

function getMonthlyAnalysisData($startDate, $endDate, $team = null) {
    $params = array(
        'from_date' => $startDate,
        'to_date' => $endDate,
    );
    
    if ($team) {
        $params['team'] = $team;
    }
    
    // Use sales-report/dashboard with date range
    $data = callSalesReportAPI('/dashboard', $params);
    return $data;
}

function getRealtimeDashboardData($filters = array()) {
    $params = array();
    
    if (isset($filters['date'])) {
        $params['date'] = $filters['date'];
    }
    if (isset($filters['team'])) {
        $params['team'] = $filters['team'];
    }
    if (isset($filters['sale_manager'])) {
        $params['sale_manager'] = $filters['sale_manager'];
    }
    if (isset($filters['group_by'])) {
        $params['group_by'] = $filters['group_by'];
    }
    
    $data = callSalesReportAPI('/dashboard', $params);
    return $data;
}

function getTopPerformersData($fromDate, $toDate, $limit = 10) {
    $params = array(
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'limit' => $limit
    );
    
    $data = callSalesReportAPI('/top-performers', $params);
    return $data;
}

function getBottomPerformersData($fromDate, $toDate, $limit = 10) {
    $params = array(
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'limit' => $limit
    );
    
    $data = callSalesReportAPI('/bottom-performers', $params);
    return $data;
}

function getAgentViewData($teamName, $date = null) {
    $params = array(
        'team' => $teamName,
        'group_by' => 'agent'
    );
    
    if ($date) {
        $params['date'] = $date;
    }
    
    $data = callSalesReportAPI('/dashboard', $params);
    return $data;
}

// create dict for team-name: team-leader
if(isset($_GET['pg']) && in_array($_GET['pg'], ['dashboard', 'top-performer', 'bottom-performer', 'export-sale-data'])) {
    $teams = getTeamsData();
}

// create dict for agent_name: tsr
if(isset($_GET['pg']) && in_array($_GET['pg'], ['call-data', 'top-performer', 'bottom-performer'])) {
    $teamsData = getTeamsData();
    $tsrs = array();
}

// sale data dashboard
if((isset($_GET['pg']) && $_GET['pg'] == 'dashboard')) {
    // filter
    $selectedTeam = '';
    $paymentDate = '';
    $type = 'Team Name';
    
    $filters = array();
    
    // Set default date to yesterday if no filter is applied
    // API defaults to yesterday, but we can explicitly set it
    if (!isset($_GET['filter']) || $_GET['filter'] != 'true') {
        // No filter applied, use yesterday as default
        $filters['date'] = date('Y-m-d', strtotime('yesterday'));
        // Also try today if yesterday has no data (will be handled by API)
    }
    
    // Check if form is submitted
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        $filters['filter'] = 'true';
        
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $filters['date'] = $payment_date;
            $paymentDate = $payment_date;
        }
        
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $filters['team'] = $team_name;
            $filters['group_by'] = 'agent';
            $selectedTeam = $team_name; // Set selected team for dropdown
        }
    }
    
    $apiData = getDashboardData($filters);
    
    // Debug: Log API response
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Sales Report API Response: ' . print_r($apiData, true));
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Debug Info:</strong><br>';
        $apiUrl = (defined('API_BASE_URL') ? API_BASE_URL : 'Not defined') . '/sales-report/dashboard';
        if (!empty($filters)) {
            $apiUrl .= '?' . http_build_query($filters);
        }
        echo 'API URL: <a href="' . htmlspecialchars($apiUrl) . '" target="_blank">' . htmlspecialchars($apiUrl) . '</a><br>';
        echo 'Filters: <pre>' . print_r($filters, true) . '</pre>';
        echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
        echo 'API Response Type: ' . gettype($apiData) . '<br>';
        if (is_array($apiData)) {
            echo 'API Response Keys: ' . implode(', ', array_keys($apiData)) . '<br>';
            if (isset($apiData['status'])) {
                echo 'API Status: ' . $apiData['status'] . '<br>';
            }
            if (isset($apiData['data'])) {
                echo 'API Data Type: ' . gettype($apiData['data']) . '<br>';
                if (is_array($apiData['data'])) {
                    echo 'API Data Keys: ' . implode(', ', array_keys($apiData['data'])) . '<br>';
                    if (isset($apiData['data']['sales_data'])) {
                        echo 'Sales Data Count: ' . (is_array($apiData['data']['sales_data']) ? count($apiData['data']['sales_data']) : 'N/A') . '<br>';
                    }
                    if (isset($apiData['data']['total_count'])) {
                        echo 'Total Count: ' . $apiData['data']['total_count'] . '<br>';
                    }
                }
            }
        }
        echo '<br><strong>Note:</strong> Check server error logs for detailed DAL/Service debugging information.<br>';
        echo '</div>';
    }
    
    $result = array();
    // API returns: { status: 'success', data: { report_date: ..., team: ..., sales_data: [...], total_count: ... } }
    if ($apiData && isset($apiData['status'])) {
        if ($apiData['status'] === 'success' && isset($apiData['data'])) {
            // Check for sales_data array (this is the actual data)
            if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
                $result = $apiData['data']['sales_data'];
            } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
                // If data is directly an array of records, use it
                if (isset($apiData['data'][0]) && is_array($apiData['data'][0])) {
                    $result = $apiData['data'];
                }
            }
        } elseif ($apiData['status'] === 'error') {
            // API returned an error
            error_log('Sales Report API Error: ' . (isset($apiData['message']) ? $apiData['message'] : 'Unknown error'));
        }
    } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
        // Direct response without status wrapper
        $result = $apiData['sales_data'];
    } elseif ($apiData && is_array($apiData) && isset($apiData[0]) && is_array($apiData[0])) {
        // Direct array response
        $result = $apiData;
    }
    
    // Debug: Log parsed result
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Parsed Result Count: ' . count($result));
        echo '<div style="background: #e0f0e0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Parsed Result:</strong> Found ' . count($result) . ' records<br>';
        if (count($result) > 0) {
            echo 'First record: <pre>' . print_r($result[0], true) . '</pre>';
        } else {
            echo 'No records found. Check API response structure above.<br>';
            if ($apiData && isset($apiData['data'])) {
                echo 'API data structure: <pre>' . print_r($apiData['data'], true) . '</pre>';
                if (isset($apiData['data']['total_count'])) {
                    echo 'Total count from API: ' . $apiData['data']['total_count'] . '<br>';
                }
            }
        }
        echo '</div>';
    }
    
    $teamsData = getTeamsData();
    $team_names = array_keys($teamsData);
    
    echo '</br></br>';
    

    ?>
   
    <h6>Sales Report - <?php echo date("Y-m-d"); ?></h6>
    
    <!-- Filter Form -->
    <div class="tabcontent">
        <div class="filter-section">
            <form id="searchForm" method="GET" action="">
                <input type="hidden" name="pg" value="dashboard">
                <input type="hidden" name="filter" value="true">
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="payment_date">Select Date:</label>
                        <input type="date" 
                               id="payment_date" 
                               name="date" 
                               value="<?php echo isset($paymentDate) && $paymentDate != '' ? htmlspecialchars($paymentDate) : date('Y-m-d', strtotime('yesterday')); ?>"
                               class="date-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="add-team-name-sl">Select Team:</label>
                        <select id="add-team-name-sl" name="team" class="team-select">
                            <option value="">All Teams</option>
                            <?php
                            foreach ($team_names as $team_name) {
                                $selected = (isset($selectedTeam) && $team_name == $selectedTeam) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($team_name) . "' $selected>" . htmlspecialchars($team_name) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="filter-btn">Apply Filter</button>
                            <button type="button" onclick="window.location.href='?pg=dashboard';" class="reset-btn">Reset</button>
                        </div>
                    </div>
                </div>
                
                <div class="quick-date-links">
                    <span>Quick Links:</span>
                    <a href="?pg=dashboard&filter=true&date=<?php echo date('Y-m-d'); ?>" class="quick-link">Today</a>
                    <a href="?pg=dashboard&filter=true&date=<?php echo date('Y-m-d', strtotime('yesterday')); ?>" class="quick-link">Yesterday</a>
                    <a href="?pg=dashboard&filter=true&date=<?php echo date('Y-m-d', strtotime('-2 days')); ?>" class="quick-link">2 Days Ago</a>
                    <a href="?pg=dashboard&filter=true&date=<?php echo date('Y-m-d', strtotime('-3 days')); ?>" class="quick-link">3 Days Ago</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- HTML main table -->
    <div class="tabcontent">
        <div class="table-container">
            <table class="table table-striped sales-report-table">
                <thead>
                    <tr>
                        <th rowspan="2">Emp Name</th>
                        <th rowspan="2">Emp ID</th>
                        <th rowspan="2">Team</th>
                        <th rowspan="2">TL</th>
                        <th colspan="6">Time Information</th>
                        <th colspan="5">GTIB Calls</th>
                        <th colspan="2">Other Calls</th>
                        <th colspan="3">Sales Metrics</th>
                    </tr>
                    <tr>
                        <th>Shift Rep Time</th>
                        <th>Shift Start Time</th>
                        <th>Noble Login Time</th>
                        <th>Total Call Time</th>
                        <th>Total Idle Time</th>
                        <th>Total Pause Time</th>
                        <th>Total Calls</th>
                        <th>≥75 & <60</th>
                        <th>≥45 & <60</th>
                        <th>< 45 MIN</th>
                        <th>AHT</th>
                        <th>Calls Taken</th>
                        <th>AHT</th>
                        <th>Sale Made</th>
                        <th>Conv %</th>
                        <th>FCS %</th>
                    </tr>
                </thead>
                <tbody>
            
            <?php
            // Initialize sum variables
            $total_gtib = 0;
            $total_gds = 0;
            $total_fit = 0;
            $total_pif = 0;
            $total_pax = 0;
            $total_totalpax = 0;
            $total_sale_made = 0;
            $total_non_sale_made = 0;
            
            // Check if we have data
            if (empty($result)) {
                // Check if API returned an error
                $errorMessage = '';
                if ($apiData && isset($apiData['status']) && $apiData['status'] === 'error') {
                    $errorMessage = isset($apiData['message']) ? $apiData['message'] : 'API returned an error';
                } elseif ($apiData === false) {
                    $errorMessage = 'API call failed. Check server logs for details.';
                } else {
                    $errorMessage = 'No data found for the selected date/team. Try selecting a different date or team.';
                }
                ?>
                    <tr>
                        <td colspan="20" class="no-data">
                            <strong>No data found</strong><br>
                            <small><?php echo htmlspecialchars($errorMessage); ?></small><br>
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <small style="margin-top: 10px; display: block;">API returned empty result. Check debug info above.</small>
                            <?php else: ?>
                                <small style="margin-top: 10px; display: block;">Add <code>?pg=dashboard&debug=1</code> to URL to see API debug information.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
            }
            
            // Fetch and display data rows
            foreach($result as $row) {
                $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                
                //if($team_name != '')
                {
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    $sale_manager = isset($row['sale_manager']) ? $row['sale_manager'] : '';
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;

                    // TSR not available in current API response
                    $tsr = isset($row['tsr']) ? $row['tsr'] : '-';
                    
                    // logon_time not available in current API response
                    $logon_time = isset($row['logon_time']) ? (int) $row['logon_time'] : 0;
                    
                    $pif = isset($row['pif']) ? (int) $row['pif'] : 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    // API returns 'sale_made_count', not 'FCS_count'
                    $sale_made = isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : (isset($row['FCS_count']) ? (int) $row['FCS_count'] : 0);
                    // API returns 'non_sale_made_count', not 'non_sales_made'
                    $non_sale_made = isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : (isset($row['non_sales_made']) ? (int) $row['non_sales_made'] : 0);
                    
                    // AHT is already calculated in API response
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                    
                    // Calculate totals
                    $total_gtib += $gtib;
                    $total_gds += isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $total_fit += isset($row['fit']) ? (int) $row['fit'] : 0;
                    $total_pif += $pif;
                    $total_pax += $pax;
                    $total_sale_made += $sale_made;
                    $total_non_sale_made += $non_sale_made;
                    
                    // Output table row
                    $team_leader_r = isset($teams[$team_name]) ? $teams[$team_name] : '';
                    ?>
                    <tr>
                        <td class="text-left"><?php echo htmlspecialchars($agent_name); ?></td>
                        <td><?php echo htmlspecialchars($tsr); ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($team_name); ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($team_leader_r); ?></td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td><?php echo $logon_time > 0 ? sprintf('%02d:%02d:%02d', ($logon_time/3600),($logon_time/60%60), $logon_time%60) : '-'; ?></td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td class="number"><?php echo $gtib; ?></td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td><?php echo $aht > 0 ? sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60) : '-'; ?></td>
                        <td class="empty">-</td>
                        <td class="empty">-</td>
                        <td class="number highlight"><?php echo $sale_made; ?></td>
                        <td class="percentage"><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td class="percentage"><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                    </tr>
                    <?php
                }
            }
            
            // Output totals row if we have data
            if (!empty($result)) {
                ?>
                    <tr class="total-row">
                        <td colspan="4" class="text-left"><strong>Total</strong></td>
                        <td colspan="6" class="empty">-</td>
                        <td class="number"><strong><?php echo $total_gtib; ?></strong></td>
                        <td colspan="3" class="empty">-</td>
                        <td class="empty">-</td>
                        <td colspan="2" class="empty">-</td>
                        <td class="number highlight"><strong><?php echo $total_sale_made; ?></strong></td>
                        <td class="percentage"><strong><?php echo ($total_gtib != 0) ? number_format($total_pax / $total_gtib * 100, 2) . '%' : '-'; ?></strong></td>
                        <td class="percentage"><strong><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></strong></td>
                    </tr>
                <?php
            }
            ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- choose team name and update filter -->
    <script>
        function teamNameFilter(teamName) {
            var selectElement = document.getElementById('add-team-name-sl');
            // Loop through options to find the one with the matching text
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].text === teamName) {
                    selectElement.selectedIndex = i;
                    break;
                }
            }
            event.preventDefault();
            // auto submit the filter
            document.getElementById('searchForm').submit();
        }
    </script>
    
    <!-- load yesterday's data -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
          var dateInput = document.getElementById('payment_date');
          var today = new Date();
          var yesterday = new Date(today);
          yesterday.setDate(today.getDate() - 1); // Subtract 1 day
          var yyyy = yesterday.getFullYear();
          var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); // January is 0!
          var dd = String(yesterday.getDate()).padStart(2, '0');
          var dateString = yyyy + '-' + mm + '-' + dd;
          dateInput.placeholder = dateString;
        });
    </script>
    <?php
}

else if(isset($_GET['pg']) && $_GET['pg'] == 'dashboard2') {
    
    // Get team names from API
    $teamsData = getTeamsData();
    $team_names = is_array($teamsData) ? array_keys($teamsData) : array();
    $selectedTeam = isset($_POST['add-team-name-sl']) ? $_POST['add-team-name-sl'] : '';
    ?>
    
    
                    
    <!-- HTML form filter -->
    <div class="tabcontent">
        <h6>Monthly Analysis</h6>
        <form id='searchForm' method="post">
            <table class="table table-striped">
                <tr>
                    <th>Select Team</th>
                    <th>Select Date</th>
                </tr>
                <tr>
                    <td>
                        <select style="width: 100%; height: 40px" name="add-team-name-sl" id="add-team-name-sl">
                            <option value="">All</option>
                            <?php
                            foreach ($team_names as $team_name) {
                                $selected = (isset($selectedTeam) && $team_name == $selectedTeam) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($team_name) . "' $selected>" . htmlspecialchars($team_name) . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <input style="width: 100%; height: 40px" type="text" id="dates" name="dates" readonly value="<?php if(isset($_POST['dates'])) { echo htmlspecialchars($_POST['dates']); } ?>" placeholder='Select Date'>
                    </td>
                </tr>
                
            </table>
        </form>
        
        <div class="navi-menu">
            <div>
                <button type="button" onclick="window.location.href='?pg=dashboard';">Back</button>
                <button type="button" onclick="window.location.href='?pg=monthly-data-table';">Overall</button>
                <button type="button" onclick="window.location.href='?pg=monthly-team-data-table';">Team wise</button>
            </div>
            <div>
                <button type="submit" onclick="submitForm()">Search</button>
            </div>
        </div>
        
    </div>
    <?php
    
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dates']) && $_POST['dates'] != ''){
        
        $dates = $_POST['dates'];
        $start_time = substr($dates, 0, 19);
        $end_time = substr($dates, 22, 19);
        
        $selectedTeam = isset($_POST['add-team-name-sl']) && $_POST['add-team-name-sl'] != '' ? $_POST['add-team-name-sl'] : null;
        
        // Get data from API
        $apiData = getMonthlyAnalysisData($start_time, $end_time, $selectedTeam);
        
        // Parse API response
        $result = array();
        if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
            if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
                $result = $apiData['data']['sales_data'];
            } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
                $result = $apiData['data'];
            }
        } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
            $result = $apiData['sales_data'];
        } elseif ($apiData && is_array($apiData)) {
            $result = $apiData;
        }
        
        // Debug
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            error_log('Monthly Analysis API Response: ' . print_r($apiData, true));
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
            echo 'Parsed Result Count: ' . count($result) . '<br>';
            echo '</div>';
        }

echo "<table class=\"table table-striped\">";
    echo "<tr>
            <th>Team</th>
            <th>Agent</th>
            <th>GTIB</th>
            <th>GDeals</th>
            <th>FIT</th>
            <th>PIF</th>
            <th>Pax</br><font style=\"font-size:10px;\">(GDeals and FIT)</font></th>
            <th>Unique Pax</br><font style=\"font-size:10px;\">(GDeals or FIT)</font></th>
            <th>Conversion %</th>
            <th>FCS %</th>
            <th>Sale made</th>
            <th>Non sale made</th>
            <th>AHT</th>
          </tr>";
$rows = [];
$total_gtib = 0;
$total_gds = 0;
$total_fit = 0;
$total_pif = 0;
$total_pax = 0;
$total_sale_made = 0;
$total_non_sale_made = 0;

foreach ($result as $row) {
    $team_name = isset($row['team_name']) ? $row['team_name'] : '';
    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
    $pif = isset($row['pif']) ? (int) $row['pif'] : 0;
    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
    $sale_made = isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0;
    $non_sale_made = isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0;
    $rec_duration = isset($row['rec_duration']) ? (float) $row['rec_duration'] : 0;
    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
    
    // Calculate totals
    $total_gtib += $gtib;
    $total_gds += $gds;
    $total_fit += $fit;
    $total_pif += $pif;
    $total_pax += $pax;
    $total_sale_made += $sale_made;
    $total_non_sale_made += $non_sale_made;
    
    // Calculate AHT if not provided
    if ($aht == 0 && $gtib > 0 && $rec_duration > 0) {
        $aht = $rec_duration / $gtib;
    }
    
    $conversion = ($gtib != 0 ? number_format($pax / $gtib * 100, 2) : '-');
    $fcs = ($gtib != 0 ? number_format($sale_made / $gtib * 100, 2) : '-');
    $aht_formatted = $aht > 0 ? secondsToTimeFormat($aht * $gtib, $gtib) : '-';
    
    $row_style = "style='background-color:white;'";

    // Output table row
    echo "<tr $row_style>
            <td>" . htmlspecialchars($team_name) . "</td>
            <td>" . htmlspecialchars($agent_name) . "</td>
            <td>$gtib</td>
            <td>$gds</td>
            <td>$fit</td>
            <td>$pif</td>
            <td>" . ($gds + $fit) . "</td>
            <td>$pax</td>
            <td>$conversion</td>
            <td>$fcs</td>
            <td>$sale_made</td>
            <td>$non_sale_made</td>
            <td>$aht_formatted</td>
          </tr>";
}

// Output totals row
if (!empty($result)) {
    echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>
            <td colspan='2'>Total</td>
            <td>$total_gtib</td>
            <td>$total_gds</td>
            <td>$total_fit</td>
            <td>$total_pif</td>
            <td>" . ($total_gds + $total_fit) . "</td>
            <td>$total_pax</td>
            <td>" . ($total_gtib != 0 ? number_format($total_pax / $total_gtib * 100, 2) : '-') . "</td>
            <td>" . ($total_gtib != 0 ? number_format($total_sale_made / $total_gtib * 100, 2) : '-') . "</td>
            <td>$total_sale_made</td>
            <td>$total_non_sale_made</td>
            <td>-</td>
          </tr>";
}

echo '</table>';


    }
}

else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-top-performer') {
    
    
    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['dates']) && $_GET['dates'] != '') {
        $dates = $_GET['dates'];
        $start_time = substr($dates, 0, 19);
        $end_time = substr($dates, 22, 19);
        
        // Get top performers from API
        $apiData = getTopPerformersData($start_time, $end_time, 10);
        
        // Parse API response
        $result = array();
        if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
            if (isset($apiData['data']['top_performers']) && is_array($apiData['data']['top_performers'])) {
                $result = $apiData['data']['top_performers'];
            } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
                $result = $apiData['data'];
            }
        } elseif ($apiData && isset($apiData['top_performers']) && is_array($apiData['top_performers'])) {
            $result = $apiData['top_performers'];
        } elseif ($apiData && is_array($apiData)) {
            $result = $apiData;
        }
        
        // Debug
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            error_log('Top Performers API Response: ' . print_r($apiData, true));
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
            echo 'Parsed Result Count: ' . count($result) . '<br>';
            echo '</div>';
        }
        
        // Legacy SQL query removed - now using API
        /*
        $sql = "WITH booking_data AS (
    SELECT
      c.agent_name,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
      COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
      c.tsr
  FROM
      wpk4_backend_travel_bookings_realtime a
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob)/365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob)/365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob)/365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
  WHERE
      DATE(a.order_date) = CURRENT_DATE() AND
      a.source <> 'import'
  GROUP BY
      c.agent_name, c.tsr
),
call_data AS (
  SELECT
    c.agent_name,
    c.team_name,
    COUNT(DISTINCT a.rowid) AS GTIB,
      a.call_date,
      a.tsr,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      
      SUM(b2.rec_duration) AS call_duration,
      SEC_TO_TIME(((SUM(b2.rec_duration) + SUM(a.time_acwork)) / COUNT(DISTINCT a3.rowid)) / 60) AS AHT
  FROM
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' and a2.tsr = '' AND a2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' and a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active' AND c.agent_name != c.team_leader
  WHERE
      a.appl = 'GTIB' AND a.call_date = CURRENT_DATE()
  GROUP BY
      a.call_date, a.tsr, c.team_name, c.agent_name
)
SELECT
MAX(cd.agent_name) AS agent_name,

  MAX(cd.call_date) AS call_date,
  SUM(bd.pax) AS pax,
  SUM(bd.fit) AS fit,
  SUM(bd.gdeals) AS gdeals,
  MAX(cd.team_name) AS team_name,
  
  SUM(cd.GTIB) AS gtib,
  SUM(cd.FCS_count) AS sale_made_count,
  SUM(cd.non_sales_made) AS non_sale_made_count,
  SUM(cd.abandoned) AS total_abandoned,
  ROUND(AVG(cd.FCS), 2) AS avg_FCS,
  SUM(cd.call_duration) AS aht
FROM
  call_data cd
LEFT JOIN
  booking_data bd ON cd.tsr = bd.tsr
GROUP BY
  cd.team_name, cd.call_date, cd.agent_name
ORDER BY
  (sum(bd.pax)/(sum(cd.GTIB)-sum(cd.abandoned))) DESC
Limit 10;
        */
        
        ?>
        <!-- HTML main table -->
        <div class="tabcontent">
            
            <h6>Top 10 performers - <?php echo date("Y-m-d"); ?></h6>
            <button onclick="window.history.back();">Back</button>
            <table class="table table-striped">
                <tr>
                    <th>Agent Name</th>
                    <th>Team Name</th>
                    <th>GTIB</th>
                    <th>GDeals</th>
                    <th>FIT</th>
                    <th>PIF</th>
                    <th>Pax</th>
                    <th>Conversion %</th>
                    <th>FCS %</th>
                    <th>Sale made</th>
                    <th>Non sale made</th>
                    <th>AHT</th>
                </tr>
                
                <?php
                // Initialize sum variables
                $total_gtib = 0;
                $total_gds = 0;
                $total_fit = 0;
                $total_pif = 0;
                $total_pax = 0;
                $total_sale_made = 0;
                $total_non_sale_made = 0;
                
                // Check if we have data
                if (empty($result)) {
                    ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 20px;">
                            <strong>No data found</strong><br>
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <small>API returned empty result. Check debug info above.</small>
                            <?php else: ?>
                                <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }
                
                // Fetch and display data rows
                foreach($result as $row) {
                    $row['pif'] = isset($row['pif']) ? $row['pif'] : 0;
                    $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    if (isset($teams[$team_name]) && trim($teams[$team_name]) == trim($agent_name))
                    {
                        continue;
                    }
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                    $pif = isset($row['pif']) ? (int) $row['pif'] : 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    $sale_made = isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0;
                    $non_sale_made = isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0;
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                
                    // Calculate totals
                    $total_gtib += $gtib;
                    $total_gds += $gds;
                    $total_fit += $fit;
                    $total_pif += $pif;
                    $total_pax += $pax;
                    $total_sale_made += $sale_made;
                    $total_non_sale_made += $non_sale_made;
                
                    // Output table row
                    ?>
                    <tr>
                        <td><a href="?pg=call-data&agent_name=<?php echo urlencode($agent_name); ?>&date=<?php echo urlencode($dates); ?>"><?php echo $agent_name; ?></a></td>
                        <td><?php echo $team_name; ?></td>
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format(($fit + $gds) / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td><?php echo secondsToTimeFormat($aht); ?></td>
                    </tr>
                    <?php
                }
                
                // Output the total row
                ?>
                <tr>
                    <th>Total</th>
                    <th>-</th>
                    <th><?php echo $total_gtib; ?></th>
                    <th><?php echo $total_gds; ?></th>
                    <th><?php echo $total_fit; ?></th>
                    <th><?php echo $total_pif; ?></th>
                    <th><?php echo $total_pax; ?></th>
                    <th><?php echo ($total_gtib != 0) ? number_format(($total_gds + $total_fit) / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                    <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                    <th><?php echo $total_sale_made; ?></th>
                    <th><?php echo $total_non_sale_made; ?></th>
                    <th>-</th>
                </tr>
            </table>
        </div>
        <?php
    }
}

else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-bottom-performer') {
    
    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['dates']) && $_GET['dates'] != '') {
        $dates = $_GET['dates'];
        $start_time = substr($dates, 0, 19);
        $end_time = substr($dates, 22, 19);
        
        // Get bottom performers from API
        $apiData = getBottomPerformersData($start_time, $end_time, 10);
        
        // Parse API response
        $result = array();
        if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
            if (isset($apiData['data']['bottom_performers']) && is_array($apiData['data']['bottom_performers'])) {
                $result = $apiData['data']['bottom_performers'];
            } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
                $result = $apiData['data'];
            }
        } elseif ($apiData && isset($apiData['bottom_performers']) && is_array($apiData['bottom_performers'])) {
            $result = $apiData['bottom_performers'];
        } elseif ($apiData && is_array($apiData)) {
            $result = $apiData;
        }
        
        // Debug
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            error_log('Bottom Performers API Response: ' . print_r($apiData, true));
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
            echo 'Parsed Result Count: ' . count($result) . '<br>';
            echo '</div>';
        }
        
        // Legacy SQL query removed - now using API
        /*
        $sql = "WITH booking_data AS (
    SELECT
      c.agent_name,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
      COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
      c.tsr
  FROM
      wpk4_backend_travel_bookings_realtime a
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob)/365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob)/365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob)/365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
  WHERE
      DATE(a.order_date) = CURRENT_DATE() AND
      a.source <> 'import'
  GROUP BY
      c.agent_name, c.tsr
),
call_data AS (
  SELECT
    c.agent_name,
    c.team_name,
    COUNT(DISTINCT a.rowid) AS GTIB,
      a.call_date,
      a.tsr,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      
      SUM(b2.rec_duration) AS call_duration,
      SEC_TO_TIME(((SUM(b2.rec_duration) + SUM(a.time_acwork)) / COUNT(DISTINCT a3.rowid)) / 60) AS AHT
  FROM
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' and a2.tsr = '' AND a2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' and a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active' AND c.agent_name != c.team_leader
  WHERE
      a.appl = 'GTIB' AND a.call_date = CURRENT_DATE()
  GROUP BY
      a.call_date, a.tsr, c.team_name, c.agent_name
)
SELECT
MAX(cd.agent_name) AS agent_name,

  MAX(cd.call_date) AS call_date,
  SUM(bd.pax) AS pax,
  SUM(bd.fit) AS fit,
  SUM(bd.gdeals) AS gdeals,
  MAX(cd.team_name) AS team_name,
  
  SUM(cd.GTIB) AS gtib,
  SUM(cd.FCS_count) AS sale_made_count,
  SUM(cd.non_sales_made) AS non_sale_made_count,
  SUM(cd.abandoned) AS total_abandoned,
  ROUND(AVG(cd.FCS), 2) AS avg_FCS,
  SUM(cd.call_duration) AS aht
FROM
  call_data cd
LEFT JOIN
  booking_data bd ON cd.tsr = bd.tsr
GROUP BY
  cd.team_name, cd.call_date, cd.agent_name
ORDER BY
  (sum(bd.pax)/(sum(cd.GTIB)-sum(cd.abandoned))) ASC
Limit 10;";
        */
        
        ?>
        <!-- HTML main table -->
        <div class="tabcontent">
            <h6>Bottom 10 performers - <?php echo date("Y-m-d"); ?></h6>
            <button onclick="window.history.back();">Back</button>
            <table class="table table-striped">
                <tr>
                    <th>Agent Name</th>
                    <th>Team Name</th>
                    <th>GTIB</th>
                    <th>GDeals</th>
                    <th>FIT</th>
                    <th>PIF</th>
                    <th>Pax</th>
                    <th>Conversion %</th>
                    <th>FCS %</th>
                    <th>Sale made</th>
                    <th>Non sale made</th>
                    <th>AHT</th>
                </tr>
                
                <?php
                // Initialize sum variables
                $total_gtib = 0;
                $total_gds = 0;
                $total_fit = 0;
                $total_pif = 0;
                $total_pax = 0;
                $total_sale_made = 0;
                $total_non_sale_made = 0;
                
                // Check if we have data
                if (empty($result)) {
                    ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 20px;">
                            <strong>No data found</strong><br>
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <small>API returned empty result. Check debug info above.</small>
                            <?php else: ?>
                                <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }
                
                // Fetch and display data rows
                foreach($result as $row) {
                    $row['pif'] = isset($row['pif']) ? $row['pif'] : 0;
                    $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    if (isset($teams[$team_name]) && trim($teams[$team_name]) == trim($agent_name)) {
                        continue;
                    }
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                    $pif = isset($row['pif']) ? (int) $row['pif'] : 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    $sale_made = isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0;
                    $non_sale_made = isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0;
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                
                    // Calculate totals
                    $total_gtib += $gtib;
                    $total_gds += $gds;
                    $total_fit += $fit;
                    $total_pif += $pif;
                    $total_pax += $pax;
                    $total_sale_made += $sale_made;
                    $total_non_sale_made += $non_sale_made;
                
                    // Output table row
                    ?>
                    <tr>
                        <td><a href="?pg=call-data&agent_name=<?php echo urlencode($agent_name); ?>&date=<?php echo urlencode($dates); ?>"><?php echo $agent_name; ?></a></td>
                        <td><?php echo $team_name; ?></td>
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format(($fit + $gds) / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td><?php echo secondsToTimeFormat($aht); ?></td>
                    </tr>
                    <?php
                }
                
                // Output the total row
                ?>
                <tr>
                    <th>Total</th>
                    <th>-</th>
                    <th><?php echo $total_gtib; ?></th>
                    <th><?php echo $total_gds; ?></th>
                    <th><?php echo $total_fit; ?></th>
                    <th><?php echo $total_pif; ?></th>
                    <th><?php echo $total_pax; ?></th>
                    <th><?php echo ($total_gtib != 0) ? number_format(($total_gds + $total_fit) / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                    <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                    <th><?php echo $total_sale_made; ?></th>
                    <th><?php echo $total_non_sale_made; ?></th>
                    <th>-</th>
                </tr>
            </table>
        </div>
        <?php
    }
}

// to view the data in sale manager level
else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-dashboard-overall') 
{
    // filter
    $selectedTeam = '';
    $paymentDate = '';
    $type = 'Team Name';
    
    // Get team names from API
    $teamsData = getTeamsData();
    $team_names = is_array($teamsData) ? array_keys($teamsData) : array();
    
    // Get dashboard data from API (grouped by sale_manager)
    $filters = array(
        'date' => date('Y-m-d', strtotime('yesterday')),
        'group_by' => 'sale_manager'
    );
    $apiData = getRealtimeDashboardData($filters);
    
    // Parse API response
    $result = array();
    if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
        if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
            $result = $apiData['data']['sales_data'];
        } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
            $result = $apiData['data'];
        }
    } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
        $result = $apiData['sales_data'];
    } elseif ($apiData && is_array($apiData)) {
        $result = $apiData;
    }
    
    // Debug
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Realtime Dashboard Overall API Response: ' . print_r($apiData, true));
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
        echo 'Parsed Result Count: ' . count($result) . '<br>';
        echo '</div>';
    }
    
    // Legacy SQL query removed - now using API
    /*
    $sql = "
    SELECT
  MAX(call_date) AS call_date,
  MAX(agent_name) AS agent_name,
  MAX(sale_manager) AS sale_manager,
  SUM(pax) AS pax,
  SUM(fit) AS fit,
  SUM(gdeals) AS gdeals,
  MAX(team_name) AS team_name,
  SUM(gtib) AS gtib,
  SUM(FCS_count) AS FCS_count,
  SUM(non_sales_made) AS non_sales_made,
  SUM(abandoned) AS abandoned,
  ROUND(AVG(FCS), 2) AS FCS,
  SUM(call_duration) AS call_duration
FROM (
  -- Call Data
  SELECT
       a.call_date,
      c.agent_name,
      c.sale_manager,
      0 AS pax,
      0 AS fit,
      0 AS gdeals,
      c.team_name,
      COUNT(DISTINCT a.rowid) AS GTIB,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      SUM(b2.rec_duration) AS call_duration
  FROM
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' AND a2.tsr = '' AND a2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' AND a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
  WHERE
      a.appl = 'GTIB' AND a.call_date = CURRENT_DATE()
  GROUP BY
      a.call_date, c.agent_name, c.sale_manager, c.team_name
  UNION ALL
  -- Booking Data
  SELECT
      CURRENT_DATE() AS call_date,
      c.agent_name,
      c.sale_manager,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
      COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
      c.team_name,
      0 AS GTIB,
      0 AS FCS_count,
      0 AS non_sales_made,
      0 AS abandoned,
      0 AS FCS,
      0 AS call_duration
  FROM
      wpk4_backend_travel_bookings_realtime a
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob) / 365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob) / 365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob) / 365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
  WHERE
      DATE(a.order_date) = CURRENT_DATE() AND a.source <> 'import'
  GROUP BY
      c.agent_name, c.sale_manager, c.team_name
) AS combined_data
GROUP BY
  sale_manager
ORDER BY
  sale_manager;
    */
    
    echo '</br></br>';
    ?>
    <h6>Agent Records - <?php echo date("Y-m-d"); ?></h6>
    <!-- HTML main table -->
    <div class="tabcontent">
        <button onclick="window.location.href='?pg=dashboard';">Sales Dashboard - History</button>
        <table class="table table-striped">
            <tr>
                <th>Sale Manager</th>
                <th>GTIB</th>
                <th>GDeals</th>
                <th>FIT</th>
                <th>PIF</th>
                <th>Unique Pax</br><font style="font-size:10px;">(GDeals or FIT)</font></th>
                
                <th>Conversion %</th>
                <th>FCS %</th>
                <th>Sale made</th>
                <th>Non sale made</th>
                <th>AHT</th>
                <th>Pax</br><font style="font-size:10px;">(GDeals and FIT)</font></th>
            </tr>
            
            <?php
            // Initialize sum variables
            $total_gtib = 0;
            $total_gds = 0;
            $total_fit = 0;
            $total_pif = 0;
            $total_pax = 0;
            $total_totalPax = 0;
            $total_sale_made = 0;
            $total_non_sale_made = 0;
            
            // Check if we have data
            if (empty($result)) {
                ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 20px;">
                        <strong>No data found</strong><br>
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                            <small>API returned empty result. Check debug info above.</small>
                        <?php else: ?>
                            <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            
            // Fetch and display data rows
            foreach($result as $row) {
                $sale_manager = isset($row['sale_manager']) ? $row['sale_manager'] : '';
                
                //if($team_name != '')
                {
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                    $pif = 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    $totalPax = $gds + $fit;
                    $sale_made = isset($row['FCS_count']) ? (int) $row['FCS_count'] : (isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0);
                    $non_sale_made = isset($row['non_sales_made']) ? (int) $row['non_sales_made'] : (isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0);
                    $call_duration = isset($row['call_duration']) ? (float) $row['call_duration'] : 0;
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                    
                    if($aht == 0 && $gtib != 0 && $call_duration > 0)
                    {
                        $aht = $call_duration / $gtib;
                    }
                    elseif($aht == 0)
                    {
                        $aht = $call_duration;
                    }
                    
                    if($sale_manager != '')
                    {
                        // Calculate totals
                        $total_gtib += $gtib;
                        $total_gds += $gds;
                        $total_fit += $fit;
                        $total_pif += $pif;
                        $total_pax += $pax;
                        $total_totalPax += $totalPax;
                        $total_sale_made += $sale_made;
                        $total_non_sale_made += $non_sale_made;
                    }
                
                    // Output table row
                    
                    ?>
                    <tr>
                        <?php
                        if($sale_manager != '')
                        {
                           ?>
                           <td><a href="?pg=realtime-dashboard-sm&sm=<?php echo $sale_manager; ?>" style="cursor: pointer; color: #cd2653;"><?php echo $sale_manager; ?></a></td>
                           <?php
                        }
                        else
                        {
                            ?>
                           <td>Abandoned</td>
                           <?php
                        }
                        
                        ?>
                        
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        
                        <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td><?php echo sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60);; ?></td>
                        <td><?php echo $totalPax; ?></td>
                    </tr>
                    <?php
                }
            }
            
            // Output the no coupon code row
            
            
            // Output the total row
            ?>
            <tr>
                <th>Total</th>
                <th><?php echo $total_gtib; ?></th>
                <th><?php echo $total_gds; ?></th>
                <th><?php echo $total_fit; ?></th>
                <th><?php echo $total_pif; ?></th>
                <th><?php echo $total_pax; ?></th>
                
                <th><?php echo ($total_gtib != 0) ? number_format($total_pax / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo $total_sale_made; ?></th>
                <th><?php echo $total_non_sale_made; ?></th>
                <th><?php echo $total_totalPax; ?></th>
                <th>-</th>
            </tr>
        </table>
    </div>
    
    <!-- choose team name and update filter -->
    <script>
        function teamNameFilter(teamName) {
            var selectElement = document.getElementById('add-team-name-sl');
            // Loop through options to find the one with the matching text
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].text === teamName) {
                    selectElement.selectedIndex = i;
                    break;
                }
            }
            event.preventDefault();
            // auto submit the filter
            document.getElementById('searchForm').submit();
        }
    </script>
    
    <!-- load yesterday's data -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
          var dateInput = document.getElementById('payment_date');
          var today = new Date();
          var yesterday = new Date(today);
          yesterday.setDate(today.getDate() - 1); // Subtract 1 day
          var yyyy = yesterday.getFullYear();
          var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); // January is 0!
          var dd = String(yesterday.getDate()).padStart(2, '0');
          var dateString = yyyy + '-' + mm + '-' + dd;
          dateInput.placeholder = dateString;
        });
    </script>
    <?php
}

// sales manager wise filter added
else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-dashboard-sm') 
{
    if(isset($_GET['sm']))
    {
        $sales_manager = $_GET['sm'];
    }
    else
    {
        $sales_manager = '';
    }
    // filter
    $selectedTeam = '';
    $paymentDate = '';
    $type = 'Team Name';
    
    // Get team names from API
    $teamsData = getTeamsData();
    $team_names = is_array($teamsData) ? array_keys($teamsData) : array();
    
    // Check if form is submitted
    $filters = array(
        'sale_manager' => $sales_manager,
        'group_by' => 'team',
        'date' => date('Y-m-d')
    );
    
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $filters['date'] = $payment_date;
            $paymentDate = $payment_date;
        }
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $filters['team'] = $team_name;
            $filters['group_by'] = 'agent';
            $selectedTeam = $team_name; // Set selected team for dropdown
        }
    }
    
    // Get dashboard data from API
    $apiData = getRealtimeDashboardData($filters);
    
    // Parse API response
    $result = array();
    if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
        if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
            $result = $apiData['data']['sales_data'];
        } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
            $result = $apiData['data'];
        }
    } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
        $result = $apiData['sales_data'];
    } elseif ($apiData && is_array($apiData)) {
        $result = $apiData;
    }
    
    // Debug
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Realtime Dashboard SM API Response: ' . print_r($apiData, true));
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
        echo 'Parsed Result Count: ' . count($result) . '<br>';
        echo '</div>';
    }
    
    // Legacy SQL query removed - now using API
    /*
   $sql = "
    WITH booking_data AS (
    SELECT
      c.agent_name,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
      COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
      c.tsr
  FROM
      wpk4_backend_travel_bookings_realtime a
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob)/365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob)/365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob)/365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
  WHERE
      DATE(a.order_date) = CURRENT_DATE() AND
      a.source <> 'import'
  GROUP BY
      c.agent_name, c.tsr
),
call_data AS (
  SELECT
      a.call_date,
      a.tsr,
      c.team_name,
      c.sale_manager,
      COUNT(DISTINCT a.rowid) AS GTIB,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      SEC_TO_TIME(((SUM(b2.rec_duration) + SUM(a.time_acwork)) / COUNT(DISTINCT a3.rowid)) / 60) AS AHT,
      SUM(b2.rec_duration) AS call_duration,
      c.agent_name
  FROM
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' and a2.tsr = '' AND a2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' and a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
  WHERE
      a.appl = 'GTIB' AND a.call_date = CURRENT_DATE() AND c.sale_manager = '$sales_manager'
  GROUP BY
      a.call_date, a.tsr, c.team_name, c.agent_name
)
SELECT
  max(cd.agent_name) as agent_name,
  MAX(cd.call_date) AS call_date,
  SUM(bd.pax) AS pax,
  SUM(bd.fit) AS fit,
  SUM(bd.gdeals) AS gdeals,
  MAX(cd.team_name) AS team_name,
  SUM(cd.GTIB) AS gtib,
  SUM(cd.FCS_count) AS FCS_count,
  SUM(cd.non_sales_made) AS non_sales_made,
  SUM(cd.abandoned) AS abandoned,
  ROUND(AVG(cd.FCS), 2) AS FCS,
  SUM(cd.call_duration) AS call_duration
FROM
  call_data cd
LEFT JOIN
  booking_data bd ON cd.tsr = bd.tsr
GROUP BY
  cd.team_name, cd.call_date
ORDER BY
  cd.call_date;
    */
    
    echo '</br></br>';
    

    ?>
   
    <h6>Agent Records - <?php echo date("Y-m-d"); ?></h6>
    
    <!-- HTML main table -->
    <div class="tabcontent">
        <button onclick="window.location.href='?pg=dashboard';">Sales Dashboard - History</button>
        <table class="table table-striped">
            <tr  style="font-size:15px;">
                <th><?php echo $type; ?></th>
                <th>Team Leader</th>
                <th>GTIB</th>
                <th>GDeals</th>
                <th>FIT</th>
                <th>PIF</th>
                <th>Unique Pax</br><font style="font-size:10px;">(GDeals or FIT)</font></th>
                
                <th>Conversion %</th>
                <th>FCS %</th>
                <th>Sale made</th>
                <th>Non sale made</th>
                <th>AHT</th>
                <th>Pax</br><font style="font-size:10px;">(GDeals and FIT)</font></th>
            </tr>
            
            <?php
            // Initialize sum variables
            $total_gtib = 0;
            $total_gds = 0;
            $total_fit = 0;
            $total_pif = 0;
            $total_pax = 0;
            $total_totalpax = 0;
            $total_sale_made = 0;
            $total_non_sale_made = 0;
            
            // Check if we have data
            if (empty($result)) {
                ?>
                <tr>
                    <td colspan="13" style="text-align: center; padding: 20px;">
                        <strong>No data found</strong><br>
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                            <small>API returned empty result. Check debug info above.</small>
                        <?php else: ?>
                            <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            
            // Fetch and display data rows
            foreach($result as $row) {
                $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                
                //if($team_name != '')
                {
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                    $pif = 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    $totalpax = $gds + $fit;
                    $sale_made = isset($row['FCS_count']) ? (int) $row['FCS_count'] : (isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0);
                    $non_sale_made = isset($row['non_sales_made']) ? (int) $row['non_sales_made'] : (isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0);
                    $call_duration = isset($row['call_duration']) ? (float) $row['call_duration'] : 0;
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                    
                    if($aht == 0 && $gtib != 0 && $call_duration > 0)
                    {
                        $aht = $call_duration / $gtib;
                    }
                    elseif($aht == 0)
                    {
                        $aht = $call_duration;
                    }
                    
                    if($team_name != '')
                    {
                        // Calculate totals
                        $total_gtib += $gtib;
                        $total_gds += $gds;
                        $total_fit += $fit;
                        $total_pif += $pif;
                        $total_pax += $pax;
                        $total_totalpax += $totalpax;
                        $total_sale_made += $sale_made;
                        $total_non_sale_made += $non_sale_made;
                    }
                
                    // Output table row
                    // Get team leader from teams data (already loaded from API)
                    $team_leader_r = isset($teamsData[$team_name]) ? $teamsData[$team_name] : '';
                    ?>
                    <tr>
                        <?php
                        if($team_name != '')
                        {
                           ?>
                           <td><a href="?pg=realtime-agent-view&agent=<?php echo $team_name; ?>" style="cursor: pointer; color: #cd2653;"><?php echo $team_name; ?></a></td>
                           <?php
                        }
                        else
                        {
                            ?>
                           <td>Abandoned</td>
                           <?php
                        }
                        if(isset($row_teamlead['team_leader']) && $row_teamlead['team_leader'] != '')
                        {
                            $team_leader_r = $row_teamlead['team_leader'];
                        }
                        else
                        {
                            $team_leader_r = '';
                        }
                        ?>
                        
                        <td><?php echo $team_leader_r; ?></td>
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        
                        <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td><?php echo sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60);; ?></td>
                        <td><?php echo $totalpax; ?></td>
                    </tr>
                    <?php
                }
            }
            
            // Output the no coupon code row
            
            
            // Output the total row
            ?>
            <tr>
                <th>Total</th>
                <th>-</th>
                <th><?php echo $total_gtib; ?></th>
                <th><?php echo $total_gds; ?></th>
                <th><?php echo $total_fit; ?></th>
                <th><?php echo $total_pif; ?></th>
                <th><?php echo $total_pax; ?></th>
                
                <th><?php echo ($total_gtib != 0) ? number_format($total_pax / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo $total_sale_made; ?></th>
                <th><?php echo $total_non_sale_made; ?></th>
                
                <th>-</th>
                <th><?php echo $total_totalpax; ?></th>
            </tr>
        </table>
    </div>
    
    <!-- choose team name and update filter -->
    <script>
        function teamNameFilter(teamName) {
            var selectElement = document.getElementById('add-team-name-sl');
            // Loop through options to find the one with the matching text
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].text === teamName) {
                    selectElement.selectedIndex = i;
                    break;
                }
            }
            event.preventDefault();
            // auto submit the filter
            document.getElementById('searchForm').submit();
        }
    </script>
    
    <!-- load yesterday's data -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
          var dateInput = document.getElementById('payment_date');
          var today = new Date();
          var yesterday = new Date(today);
          yesterday.setDate(today.getDate() - 1); // Subtract 1 day
          var yyyy = yesterday.getFullYear();
          var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); // January is 0!
          var dd = String(yesterday.getDate()).padStart(2, '0');
          var dateString = yyyy + '-' + mm + '-' + dd;
          dateInput.placeholder = dateString;
        });
    </script>
    <?php
}

else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-dashboard') {
    // filter
    $selectedTeam = '';
    $paymentDate = '';
    $type = 'Team Name';
    
    // Get team names from API
    $teamsData = getTeamsData();
    $team_names = is_array($teamsData) ? array_keys($teamsData) : array();
    
    // Check if form is submitted
    $filters = array(
        'date' => date('Y-m-d', strtotime('yesterday')),
        'group_by' => 'team'
    );
    
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $filters['date'] = $payment_date;
            $paymentDate = $payment_date;
        }
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $filters['team'] = $team_name;
            $filters['group_by'] = 'agent';
            $selectedTeam = $team_name; // Set selected team for dropdown
        }
    }
    
    // Get dashboard data from API
    $apiData = getRealtimeDashboardData($filters);
    
    // Parse API response
    $result = array();
    if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
        if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
            $result = $apiData['data']['sales_data'];
        } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
            $result = $apiData['data'];
        }
    } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
        $result = $apiData['sales_data'];
    } elseif ($apiData && is_array($apiData)) {
        $result = $apiData;
    }
    
    // Debug
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Realtime Dashboard API Response: ' . print_r($apiData, true));
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
        echo 'Parsed Result Count: ' . count($result) . '<br>';
        echo '</div>';
    }
    
    // Legacy SQL query removed - now using API
    /*
   $sql = " 
SELECT
  max(sale_manager) as sale_manager,
  max(call_date) as call_date,
  max(agent_name) as agent_name,
  SUM(pax) AS pax,
  SUM(fit) AS fit,
  SUM(gdeals) AS gdeals,
  max(team_name) as team_name,
  SUM(gtib) AS gtib,
  SUM(FCS_count) AS FCS_count,
  SUM(non_sales_made) AS non_sales_made,
  SUM(abandoned) AS abandoned,
  ROUND(AVG(FCS), 2) AS FCS,
  SUM(call_duration) AS call_duration
FROM (
  -- Call Data
  SELECT
       a.call_date,
      c.agent_name,
      0 AS pax,
      0 AS fit,
      0 AS gdeals,
      c.team_name,
      c.sale_manager,
      COUNT(DISTINCT a.rowid) AS GTIB,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      SUM(b2.rec_duration) AS call_duration
  FROM
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' and a2.tsr = '' AND a2.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' and a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
  WHERE
      a.appl = 'GTIB' AND a.call_date = CURRENT_DATE()
  GROUP BY
      a.call_date, c.agent_name, c.team_name, c.sale_manager
  UNION ALL
  -- Booking Data
  SELECT
      CURRENT_DATE() AS call_date,
      c.agent_name,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
      COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
      c.team_name,
      c.sale_manager,
      0 AS GTIB,
      0 AS FCS_count,
      0 AS non_sales_made,
      0 AS abandoned,
      0 AS FCS,
      0 AS call_duration
  FROM
      wpk4_backend_travel_bookings_realtime a
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob)/365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob)/365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob)/365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
  LEFT JOIN
      wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
  WHERE
      DATE(a.order_date) = CURRENT_DATE() AND
      a.source <> 'import'
  GROUP BY
      c.agent_name, c.team_name, c.sale_manager
) AS combined_data
GROUP BY
  team_name, call_date
ORDER BY sale_manager asc;
    */
    
    echo '</br></br>';
    

    ?>
   
    <h6>Agent Records - <?php echo date("Y-m-d"); ?></h6>
    
    <!-- HTML main table -->
    <div class="tabcontent">
        <?php
        //if($currnt_userlogn == 'leen' || $currnt_userlogn == 'sriharshans')
                    {
                    ?>
                    <button onclick="window.location.href='?pg=realtime-top-performer&filter=true&dates=<?php echo date("Y-m-d"); ?>+00%3A00%3A00+-+<?php echo date("Y-m-d"); ?>+23%3A59%3A59';">Top 10 Performer</button>
                    <button onclick="window.location.href='?pg=realtime-bottom-performer&filter=true&dates=<?php echo date("Y-m-d"); ?>+00%3A00%3A00+-+<?php echo date("Y-m-d"); ?>+23%3A59%3A59';">Bottom 10 Performer</button>
                    <?php
                    }
        ?>
        <button onclick="window.location.href='?pg=dashboard';">Sales Dashboard - History</button>
        <table class="table table-striped">
            <tr style="font-size:15px;">
                <th>Sale Manager</th>
                <th><?php echo $type; ?></th>
                <th>Team Leader</th>
                <th>GTIB</th>
                <th>GDeals</th>
                <th>FIT</th>
                <th>PIF</th>
                <th>Unique Pax</br><font style="font-size:10px;">(GDeals or FIT)</font></th>
                
                <th>Conversion</br>%</th>
                <th>FCS</br>%</th>
                <th>Sale</br>made</th>
                <th>Non</br>sale made</th>
                <th>AHT</th>
                <th>Pax</br><font style="font-size:10px;">(GDeals and FIT)</font></th>
            </tr>
            
            <?php
            // Initialize sum variables
            $total_gtib = 0;
            $total_gds = 0;
            $total_fit = 0;
            $total_pif = 0;
            $total_pax = 0;
            $total_totalpax = 0;
            $total_sale_made = 0;
            $total_non_sale_made = 0;
            
            // Check if we have data
            if (empty($result)) {
                ?>
                <tr>
                    <td colspan="15" style="text-align: center; padding: 20px;">
                        <strong>No data found</strong><br>
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                            <small>API returned empty result. Check debug info above.</small>
                        <?php else: ?>
                            <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            
            // Fetch and display data rows
            foreach($result as $row) {
                $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                
                //if($team_name != '')
                {
                    $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                    $sale_manager = isset($row['sale_manager']) ? $row['sale_manager'] : '';
                    $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                    $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                    $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                    $pif = 0;
                    $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                    $totalpax = $gds + $fit;
                    $sale_made = isset($row['FCS_count']) ? (int) $row['FCS_count'] : (isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0);
                    $non_sale_made = isset($row['non_sales_made']) ? (int) $row['non_sales_made'] : (isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0);
                    $call_duration = isset($row['call_duration']) ? (float) $row['call_duration'] : 0;
                    $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                    
                    if($aht == 0 && $gtib != 0 && $call_duration > 0)
                    {
                        $aht = $call_duration / $gtib;
                    }
                    elseif($aht == 0)
                    {
                        $aht = $call_duration;
                    }
                    
                    if($team_name != '')
                    {
                        // Calculate totals
                        $total_gtib += $gtib;
                        $total_gds += $gds;
                        $total_fit += $fit;
                        $total_pif += $pif;
                        $total_pax += $pax;
                        $total_totalpax += $totalpax;
                        $total_sale_made += $sale_made;
                        $total_non_sale_made += $non_sale_made;
                    }
                
                    // Output table row
                    // Get team leader from teams data (already loaded from API)
                    $team_leader_r = isset($teamsData[$team_name]) ? $teamsData[$team_name] : '';
                    ?>
                    <tr>
                        <?php
                        if($team_name != '')
                        {
                           ?>
                           <td><a href="?pg=realtime-dashboard-sm&sm=<?php echo $sale_manager; ?>" style="cursor: pointer; color: #cd2653;"><?php echo $sale_manager; ?></a></td>
                           <?php
                        }
                        else
                        {
                            ?>
                           <td></td>
                           <?php
                        }
                        
                        
                        if($team_name != '')
                        {
                           ?>
                           <td><a href="?pg=realtime-agent-view&agent=<?php echo $team_name; ?>" style="cursor: pointer; color: #cd2653;"><?php echo $team_name; ?></a></td>
                           <?php
                        }
                        else
                        {
                            ?>
                           <td>Abandoned</td>
                           <?php
                        }
                        if(isset($row_teamlead['team_leader']) && $row_teamlead['team_leader'] != '')
                        {
                            $team_leader_r = $row_teamlead['team_leader'];
                        }
                        else
                        {
                            $team_leader_r = '';
                        }
                        ?>
                        
                        <td><?php echo $team_leader_r; ?></td>
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        
                        <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td><?php echo sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60);; ?></td>
                        <td><?php echo $totalpax; ?></td>
                    </tr>
                    <?php
                }
            }
            
            // Output the no coupon code row
            
            
            // Output the total row
            ?>
            <tr>
                <th>Total</th>
                <th>-</th>
                <th>-</th>
                <th><?php echo $total_gtib; ?></th>
                <th><?php echo $total_gds; ?></th>
                <th><?php echo $total_fit; ?></th>
                <th><?php echo $total_pif; ?></th>
                <th><?php echo $total_pax; ?></th>
                
                <th><?php echo ($total_gtib != 0) ? number_format($total_pax / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo $total_sale_made; ?></th>
                <th><?php echo $total_non_sale_made; ?></th>
                <th>-</th>
                <th><?php echo $total_totalpax; ?></th>
            </tr>
        </table>
    </div>
    
    <!-- choose team name and update filter -->
    <script>
        function teamNameFilter(teamName) {
            var selectElement = document.getElementById('add-team-name-sl');
            // Loop through options to find the one with the matching text
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].text === teamName) {
                    selectElement.selectedIndex = i;
                    break;
                }
            }
            event.preventDefault();
            // auto submit the filter
            document.getElementById('searchForm').submit();
        }
    </script>
    
    <!-- load yesterday's data -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
          var dateInput = document.getElementById('payment_date');
          var today = new Date();
          var yesterday = new Date(today);
          yesterday.setDate(today.getDate() - 1); // Subtract 1 day
          var yyyy = yesterday.getFullYear();
          var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); // January is 0!
          var dd = String(yesterday.getDate()).padStart(2, '0');
          var dateString = yyyy + '-' + mm + '-' + dd;
          dateInput.placeholder = dateString;
        });
    </script>
    <?php
}

else if(isset($_GET['pg']) && $_GET['pg'] == 'realtime-agent-view') {
    
    $agent_selected = isset($_GET['agent']) ? $_GET['agent'] : '';
    
    // Get agent view data from API
    $filters = array(
        'team' => $agent_selected,
        'group_by' => 'agent',
        'date' => date('Y-m-d')
    );
    $apiData = getAgentViewData($agent_selected, date('Y-m-d'));
    
    // Parse API response
    $result = array();
    if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
        if (isset($apiData['data']['sales_data']) && is_array($apiData['data']['sales_data'])) {
            $result = $apiData['data']['sales_data'];
        } elseif (isset($apiData['data']) && is_array($apiData['data'])) {
            $result = $apiData['data'];
        }
    } elseif ($apiData && isset($apiData['sales_data']) && is_array($apiData['sales_data'])) {
        $result = $apiData['sales_data'];
    } elseif ($apiData && is_array($apiData)) {
        $result = $apiData;
    }
    
    // Debug
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log('Realtime Agent View API Response: ' . print_r($apiData, true));
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'API Response: <pre>' . print_r($apiData, true) . '</pre>';
        echo 'Parsed Result Count: ' . count($result) . '<br>';
        echo '</div>';
    }
    
    // Get teams data for team leader lookup
    $teamsData = getTeamsData();
    
    // Legacy SQL query removed - now using API
    /*
    $sql = "
   SELECT
    call_date,
   agent_name,
   SUM(pax) AS pax,
   SUM(fit) AS fit,
   SUM(gdeals) AS gdeals,
   team_name,
   SUM(gtib) AS gtib,
   SUM(FCS_count) AS FCS_count,
   SUM(non_sales_made) AS non_sales_made,
   SUM(abandoned) AS abandoned,
   ROUND(AVG(FCS), 2) AS FCS,
   SUM(call_duration) AS call_duration
FROM (
   -- Call Data
   SELECT
       a.call_date,
       c.agent_name,
       0 AS pax,
       0 AS fit,
       0 AS gdeals,
       c.team_name,
       COUNT(DISTINCT a.rowid) AS GTIB,
       COUNT(DISTINCT b.rowid) AS FCS_count,
       (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
       COUNT(DISTINCT a2.rowid) AS abandoned,
       ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
       SUM(b2.rec_duration) AS call_duration
   FROM
       wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
   LEFT JOIN
       wpk4_backend_agent_nobel_data_call_rec_realtime b ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB' AND b.call_date = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_agent_nobel_data_call_rec_realtime b2 ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB' AND b2.call_date = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 ON a.record_id = a2.record_id AND a.appl = 'GTIB' AND a2.tsr = '' AND a2.call_date = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 ON a.record_id = a3.record_id AND a.appl = 'GTIB' AND a3.tsr <> '' AND a3.call_date = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
   WHERE
       a.appl = 'GTIB' AND a.call_date = CURRENT_DATE() AND c.team_name = '$agent_selected'
   GROUP BY
       a.call_date, c.agent_name, c.team_name

   UNION ALL

   -- Booking Data
   SELECT
       CURRENT_DATE() AS call_date,
       c.agent_name,
       COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
       COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
       COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
       c.team_name,
       0 AS GTIB,
       0 AS FCS_count,
       0 AS non_sales_made,
       0 AS abandoned,
       0 AS FCS,
       0 AS call_duration
   FROM
       wpk4_backend_travel_bookings_realtime a
   LEFT JOIN
       wpk4_backend_travel_booking_pax_realtime b ON a.order_id = b.order_id AND (DATEDIFF(a.travel_date, b.dob) / 365) > 2 AND DATE(b.order_date) = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_travel_booking_pax_realtime b3 ON a.order_id = b3.order_id AND (DATEDIFF(a.travel_date, b3.dob) / 365) > 2 AND b3.order_type = 'gds' AND DATE(b3.order_date) = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_travel_booking_pax_realtime b4 ON a.order_id = b4.order_id AND (DATEDIFF(a.travel_date, b4.dob) / 365) > 2 AND b4.order_type = 'wpt' AND DATE(b4.order_date) = CURRENT_DATE()
   LEFT JOIN
       wpk4_backend_agent_codes c ON a.agent_info = c.sales_id AND c.status = 'active'
   WHERE
       DATE(a.order_date) = CURRENT_DATE() AND c.team_name = '$agent_selected' AND a.source <> 'import'
   GROUP BY
       c.agent_name, c.team_name
) AS combined_data
GROUP BY
   call_date, agent_name, team_name
ORDER BY
   agent_name;
    */
    
    echo '</br></br>';
    

    ?>
    
    <h6>Agent Records (<?php echo $agent_selected; ?>) - <?php echo date("Y-m-d"); ?></h6>
    
    <!-- HTML main table -->
    <div class="tabcontent">
        <button onclick="window.history.back();">Back</button>
        <table class="table table-striped">
            <tr style="font-size:15px;">
                <th>Sales Manager</th>
                <th>Team</th>
                <th>Team Leader</th>
                <th>Agent Name</th>
                <th>GTIB</th>
                <th>GDeals</th>
                <th>FIT</th>
                <th>PIF</th>
                <th>Unique Pax</br><font style="font-size:10px;">(GDeals or FIT)</font></th>
                
                <th>Conversion</br>%</th>
                <th>FCS</br>%</th>
                <th>Sale</br>made</th>
                <th>Non</br>sale made</th>
                <th>AHT</th>
                <th>Pax</br><font style="font-size:10px;">(GDeals and FIT)</font></th>
            </tr>
            
            <?php
            // Initialize sum variables
            $total_gtib = 0;
            $total_gds = 0;
            $total_fit = 0;
            $total_pif = 0;
            $total_pax = 0;
            $total_totalpax = 0;
            $total_sale_made = 0;
            $total_non_sale_made = 0;
            
            // Check if we have data
            if (empty($result)) {
                ?>
                <tr>
                    <td colspan="16" style="text-align: center; padding: 20px;">
                        <strong>No data found</strong><br>
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                            <small>API returned empty result. Check debug info above.</small>
                        <?php else: ?>
                            <small>Add <code>?debug=1</code> to URL to see API debug information.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            
            // Fetch and display data rows
            foreach($result as $row) {
                $team_name = isset($row['team_name']) ? $row['team_name'] : '';
                $agent_name = isset($row['agent_name']) ? $row['agent_name'] : '';
                $gtib = isset($row['gtib']) ? (int) $row['gtib'] : 0;
                $gds = isset($row['gdeals']) ? (int) $row['gdeals'] : 0;
                $fit = isset($row['fit']) ? (int) $row['fit'] : 0;
                $pif = isset($row['pif']) ? (int) $row['pif'] : 0;
                $pax = isset($row['pax']) ? (int) $row['pax'] : 0;
                $totalPax = $gds + $fit;
                $sale_made = isset($row['FCS_count']) ? (int) $row['FCS_count'] : (isset($row['sale_made_count']) ? (int) $row['sale_made_count'] : 0);
                $non_sale_made = isset($row['non_sales_made']) ? (int) $row['non_sales_made'] : (isset($row['non_sale_made_count']) ? (int) $row['non_sale_made_count'] : 0);
                $call_duration = isset($row['call_duration']) ? (float) $row['call_duration'] : 0;
                $aht = isset($row['aht']) ? (float) $row['aht'] : 0;
                
                if($aht == 0 && $gtib != 0 && $call_duration > 0)
                {
                    $aht = $call_duration / $gtib;
                }
                elseif($aht == 0)
                {
                    $aht = $call_duration;
                }
                
                // Calculate totals
                $total_gtib += $gtib;
                $total_gds += $gds;
                $total_fit += $fit;
                $total_pif += $pif;
                $total_pax += $pax;
                $total_totalpax += $totalPax;
                $total_sale_made += $sale_made;
                $total_non_sale_made += $non_sale_made;
            
                // Output table row
                // Get team leader and sale manager from teams data
                $team_leader_r = isset($teamsData[$team_name]) ? $teamsData[$team_name] : '';
                $sale_manager_r = isset($row['sale_manager']) ? $row['sale_manager'] : '';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($sale_manager_r); ?></td>
                    <td><?php echo htmlspecialchars($team_name); ?></td>
                    <td><?php echo htmlspecialchars($team_leader_r); ?></td>
                    <td><a href="?pg=order-data&agent_name=<?php echo $row['agent_name']; ?>&date=<?php echo date('Y-m-d').' 00:00:00'; ?>-<?php echo date('Y-m-d').' 23:59:59'; ?>" style="cursor: pointer; color: #cd2653;"><?php echo $row['agent_name']; ?></a></td>
                    <td><?php echo $gtib; ?></td>
                    <td><?php echo $gds; ?></td>
                    <td><?php echo $fit; ?></td>
                    <td><?php echo $pif; ?></td>
                    <td><?php echo $pax; ?></td>
                    
                    <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                    <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                    <td><?php echo $sale_made; ?></td>
                    <td><?php echo $non_sale_made; ?></td>
                    <td><?php echo sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60);; ?></td>
                    <td><?php echo $totalPax; ?></td>
                </tr>
                <?php
            }
            
            // Output the no coupon code row
            // Note: This section requires a specific API endpoint for "no coupon code" data
            // which may not be available in the current API. Skipping for now.
            // If needed, a new API endpoint can be created for this specific use case.
            
            // Output the total row
            ?>
            <tr>
                <th>Total</th>
                <th>-</th>
                <th>-</th>
                <th>-</th>
                <th><?php echo $total_gtib; ?></th>
                <th><?php echo $total_gds; ?></th>
                <th><?php echo $total_fit; ?></th>
                <th><?php echo $total_pif; ?></th>
                <th><?php echo $total_pax; ?></th>
                
                <th><?php echo ($total_gtib != 0) ? number_format($total_pax / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo ($total_gtib != 0) ? number_format($total_sale_made / $total_gtib * 100, 2) . '%' : '-'; ?></th>
                <th><?php echo $total_sale_made; ?></th>
                <th><?php echo $total_non_sale_made; ?></th>
                <th>-</th>
                <th><?php echo $total_totalpax; ?></th>
            </tr>
        </table>
    </div>
    
    <!-- choose team name and update filter -->
    <script>
        function teamNameFilter(teamName) {
            var selectElement = document.getElementById('add-team-name-sl');
            // Loop through options to find the one with the matching text
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].text === teamName) {
                    selectElement.selectedIndex = i;
                    break;
                }
            }
            event.preventDefault();
            // auto submit the filter
            document.getElementById('searchForm').submit();
        }
    </script>
    
    <!-- load yesterday's data -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
          var dateInput = document.getElementById('payment_date');
          var today = new Date();
          var yesterday = new Date(today);
          yesterday.setDate(today.getDate() - 1); // Subtract 1 day
          var yyyy = yesterday.getFullYear();
          var mm = String(yesterday.getMonth() + 1).padStart(2, '0'); // January is 0!
          var dd = String(yesterday.getDate()).padStart(2, '0');
          var dateString = yyyy + '-' + mm + '-' + dd;
          dateInput.placeholder = dateString;
        });
    </script>
    <?php
}


// redirect to sales data dashboard
else {
    echo "<script>window.location.href = '?pg=dashboard';</script>";
}

function secondsToTimeFormat($seconds, $gtib=1) {
    if($gtib == 0) {
        return sprintf('%02d:%02d:%02d', 0, 0, 0);;
    }
    $seconds /= $gtib;
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
function yesterday() {
    $yesterday = new DateTime('yesterday');

    // Format yesterday's date
    $startDate = $yesterday->format('Y-m-d 00:00:00');
    $endDate = $yesterday->format('Y-m-d 23:59:59');
    
    // Output the date range
    $selectDate = $startDate . ' - ' . $endDate;
    
    return $selectDate;
}
function payment_options() {
    $payment_options = array('paid' => 'Paid', 'receipt_received' => 'Receipt received', 'partially_paid' => 'Partially Paid', 
    'refund' => 'Refund Done', 'voucher_submited' => 'Rebooked', 'waiting_voucher' => 'Refund Under Process', 'canceled' => 'XXLN With Deposit', 'pending' => 'Failed', 'N/A' => 'N/A');

    return $payment_options;
}
?>

<style>
    .tabcontent {
        width: 95%;
        max-width: 100%;
        margin: 0 auto;
        padding: 20px;
    }
    
    .table-container {
        overflow-x: auto;
        overflow-y: visible;
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .sales-report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        background: white;
        min-width: 1400px;
    }
    
    .sales-report-table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .sales-report-table thead th {
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 12px;
        border: 1px solid rgba(255,255,255,0.2);
        white-space: nowrap;
        vertical-align: middle;
    }
    
    .sales-report-table thead tr:first-child th {
        background: rgba(0,0,0,0.2);
        font-size: 11px;
        padding: 8px 4px;
    }
    
    .sales-report-table tbody td {
        padding: 10px 8px;
        text-align: center;
        border: 1px solid #e0e0e0;
        vertical-align: middle;
    }
    
    .sales-report-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .sales-report-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .sales-report-table tbody tr:hover {
        background-color: #f0f7ff;
    }
    
    .sales-report-table .text-left {
        text-align: left;
        padding-left: 12px;
    }
    
    .sales-report-table .number {
        font-weight: 500;
        font-family: 'Courier New', monospace;
    }
    
    .sales-report-table .percentage {
        font-weight: 500;
        color: #2563eb;
    }
    
    .sales-report-table .highlight {
        font-weight: 600;
        color: #059669;
    }
    
    .sales-report-table .empty {
        color: #9ca3af;
        font-style: italic;
    }
    
    .sales-report-table .total-row {
        background-color: #f3f4f6 !important;
        font-weight: bold;
        border-top: 2px solid #667eea;
    }
    
    .sales-report-table .total-row td {
        background-color: #f3f4f6 !important;
        padding: 12px 8px;
    }
    
    .sales-report-table .no-data {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
        font-style: italic;
    }
    
    .navi-menu {
        display: flex; 
        justify-content: space-between;
        margin: 20px 0;
    }
    
    button {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    button:hover {
        background: #5568d3;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    h6 {
        text-align: center;
        font-size: 24px;
        margin: 20px 0;
        color: #1f2937;
        font-weight: 600;
    }
    
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .filter-row {
        display: flex;
        align-items: flex-end;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }
    
    .date-input,
    .team-select {
        padding: 10px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .date-input:focus,
    .team-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-btn,
    .reset-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .filter-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .filter-btn:hover {
        background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .reset-btn {
        background: #f3f4f6;
        color: #374151;
        border: 2px solid #e5e7eb;
    }
    
    .reset-btn:hover {
        background: #e5e7eb;
        border-color: #d1d5db;
    }
    
    .quick-date-links {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .quick-date-links span {
        font-weight: 600;
        color: #6b7280;
        font-size: 13px;
    }
    
    .quick-link {
        padding: 6px 12px;
        background: #f3f4f6;
        color: #374151;
        text-decoration: none;
        border-radius: 4px;
        font-size: 13px;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
    }
    
    .quick-link:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
        transform: translateY(-1px);
    }
    
    @media (max-width: 1200px) {
        .tabcontent {
            width: 100%;
            padding: 10px;
        }
        
        .table-container {
            margin: 10px 0;
        }
        
        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            min-width: 100%;
        }
    }
    
    @media print {
        .table-container {
            overflow: visible;
            border: none;
            box-shadow: none;
        }
        
        .sales-report-table {
            min-width: auto;
        }
        
        .sales-report-table thead {
            position: relative;
        }
    }
</style>

<!-- Include the library for date-time range picker -->
<script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    window.addEventListener("load", function () {
        let drp = new DateRangePicker('dates', {
            timePicker: true,
            maxSpan: { days: <?php echo $filter_days; ?> },
            alwaysShowCalendars: true,
            autoApply: false,
            autoUpdateInput: false,
            locale: {
                format: "YYYY-MM-DD HH:mm:ss", // Adjust format to include time
            }
        }, function (start, end) {
            end.set({ second: 59 });
            document.getElementById("dates").value = start.format("YYYY-MM-DD HH:mm:ss")+' - '+end.format("YYYY-MM-DD HH:mm:ss");
        });

        // Manually update input field
        drp.on('apply', function (start, end) {
            end.set({ second: 59 });
            document.getElementById("dates").value = start.format("YYYY-MM-DD HH:mm:ss")+' - '+end.format("YYYY-MM-DD HH:mm:ss");
        });

        // Clear input when 'Cancel' button is clicked
        drp.on('cancel', function () {
            document.getElementById("dates").value = '';
        });
    });
    
    function submitForm(formId="searchForm") {
        document.getElementById(formId).submit();
    }
</script>

<?php get_footer(); ?>
