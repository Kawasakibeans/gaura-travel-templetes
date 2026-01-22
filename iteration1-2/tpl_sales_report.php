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

// create dict for team-name: team-leader
if(isset($_GET['pg']) && $_GET['pg'] == ('dashboard' || 'top-performer' || 'bottom-performer' || 'export-sale-data')) {
    $sql = "SELECT DISTINCT team_name, team_leader FROM wpk4_backend_agent_codes";
    $result = mysqli_query($mysqli_replica, $sql);
    $teams = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $teams[$row['team_name']] = $row['team_leader'];
        }
    }
}

// create dict for agent_name: tsr
if(isset($_GET['pg']) && $_GET['pg'] == ('call-data' || 'top-performer' || 'bottom-performer')) {
    $sql = "SELECT DISTINCT tsr, agent_name FROM wpk4_backend_agent_codes;";
    $result = mysqli_query($mysqli_replica, $sql);
    
    $tsrs = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if(isset($row['agent_name']) && $row['agent_name'] != '' && isset($row['tsr']) && $row['tsr'] != '') {
                $tsrs[$row['agent_name']] = $row['tsr'];
            }
        }
    }
}

// sale data dashboard
if((isset($_GET['pg']) && $_GET['pg'] == 'dashboard')) {
    // filter
    $selectedTeam = '';
    $paymentDate = '';
    $type = 'Team Name';
    $fetch_team_name_query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_inbound_call WHERE team_name != NULL or team_name != ''";
    $fetch_team_name_result = mysqli_query($mysqli_replica, $fetch_team_name_query) or die(mysqli_error($mysqli_replica));
    $where = "WHERE DATE(ic.call_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Get data for yesterday";
    $team_name_query = '';
    $groupby = 'ic.team_name';
    
    // Check if form is submitted
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $start_time = substr($payment_date, 0, 19);
            $end_time = substr($payment_date, 22, 19);
            $where = "WHERE DATE(a.order_date) = CURRENT_DATE()";
        }
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $team_name_query = "AND a.source <> 'import'";
            $selectedTeam = $team_name; // Set selected team for dropdown
            $groupby = 'ic.agent_name';
        }
        
        // Set payment date for input field
        $paymentDate = isset($_GET["date"]) ? $_GET["date"] : '';
    }
    
    
    //LEFT JOIN wpk4_backend_agent_nobel_data_tsktsrday b5 ON a.tsr = b5.tsr AND b5.appl = 'GTIB' AND b5.call_date = CURRENT_DATE()
    
   $sql = " 
SELECT
  max(sale_manager) as sale_manager,
  max(call_date) as call_date,
  max(agent_name) as agent_name,
  SUM(pax) AS pax,
  max(team_name) as team_name,
  max(tsr) as tsr,
  SUM(gtib) AS gtib,
  SUM(FCS_count) AS FCS_count,
  SUM(non_sales_made) AS non_sales_made,
  SUM(abandoned) AS abandoned,
  ROUND(AVG(FCS), 2) AS FCS,
  SUM(call_duration) AS call_duration,
  max(logon_time) AS logon_time,
  max(time_deassigned) AS time_deassigned
FROM (
  -- Call Data
  SELECT
       a.call_date,
      c.agent_name,
      0 AS pax,
      c.team_name,
      c.tsr,
      c.sale_manager,
      COUNT(DISTINCT a.rowid) AS GTIB,
      COUNT(DISTINCT b.rowid) AS FCS_count,
      (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT b.rowid) - COUNT(DISTINCT a2.rowid)) AS non_sales_made,
      COUNT(DISTINCT a2.rowid) AS abandoned,
      ROUND((COUNT(DISTINCT b.rowid) / COUNT(DISTINCT a.rowid)) * 100, 2) AS FCS,
      SUM(b2.rec_duration) AS call_duration,
      0 AS logon_time,
      0 AS time_deassigned
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
      a.call_date, c.agent_name, c.team_name, c.tsr, c.sale_manager
  UNION ALL
  -- Booking Data
  SELECT
      CURRENT_DATE() AS call_date,
      c.agent_name,
      COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
      c.team_name,
      c.tsr,
      c.sale_manager,
      0 AS GTIB,
      0 AS FCS_count,
      0 AS non_sales_made,
      0 AS abandoned,
      0 AS FCS,
      0 AS call_duration,
      0 AS logon_time,
      0 AS time_deassigned
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
      c.agent_name, c.team_name, c.tsr, c.sale_manager
) AS combined_data
GROUP BY
  agent_name, call_date
ORDER BY agent_name asc;";
    
   
    
    echo '</br></br>';
    
    //$sql = "SELECT MAX(ic.team_name) AS team_name, MAX(ic.agent_name) AS agent_name, MAX(ac.team_leader) AS team_leader, SUM(ic.gtib_count) AS gtib, SUM(b.gdeals) AS gdeals, SUM(b.fit) AS fit, SUM(b.pif) AS pif, SUM(b.pax) AS pax, SUM(ic.sale_made_count) AS sale_made_count, SUM(ic.non_sale_made_count) AS non_sale_made_count, AVG(ic.rec_duration/ic.gtib_count) AS aht FROM wpk4_backend_agent_inbound_call AS ic LEFT JOIN wpk4_backend_agent_booking AS b ON ic.call_date = b.order_date AND ic.tsr = b.tsr LEFT JOIN wpk4_backend_agent_codes AS ac ON b.agent_name = ac.agent_name WHERE ic.call_date BETWEEN '2024-07-01' AND '2024-07-03 23:59:59' AND ic.team_name='Lamborghini' AND ic.team_name != '' GROUP BY ic.agent_name ORDER BY ic.agent_name;";
    $result = mysqli_query($mysqli_replica, $sql);
    

    ?>
   
    <h6>Sales Report - <?php echo date("Y-m-d"); ?></h6>
    
    <!-- HTML main table -->
    <div class="tabcontent">
        
        <table class="table table-striped">
            <tr style="font-size:15px;">
                <th>Emp Name</th>
                <th>Emp ID</th>
                <th>Team</th>
                <th>TL</th>
                <th>Shift Rep Time</th>
                <th>Shift Start Time</th>
                <th>Noble Login Time</th>
                <th>Total Call Time</th>
                <th>Total Idle Time</th>
                <th>Total Pause Time</th>
                
                <th>Total GTIB Calls Taken</th>
                <th>GTIB >=75 & <60</th>
                <th>GTIB >=45 & <60</th>
                <th>GTIB < 45 MIN</th>
                <th>GTIB AHT</th>
                
                <th>OTH Calls Taken </th>
                <th>OTH AHT</th>
                <th>Sale</br>made</th>
                <th>Conv %</th>
                <th>FCS %</th>
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
            
            // Fetch and display data rows
            while($row = mysqli_fetch_assoc($result)) {
                $team_name = $row['team_name'];
                
                //if($team_name != '')
                {
                    $agent_name = $row['agent_name'];
                    $sale_manager = $row['sale_manager'];
                    $gtib = (int) $row['gtib'];

                    $tsr = $row['tsr'];
                    
                    $logon_time = $row['logon_time'];
                    
                    
                    $pif = 0;
                    $pax = (int) $row['pax'];
                    $sale_made = (int) $row['FCS_count'];
                    $non_sale_made = (int) $row['non_sales_made'];
                    if($gtib != 0)
                    {
                        $aht = $row['call_duration'] / $gtib;
                    }
                    else
                    {
                        $aht = $row['call_duration'];
                    }
                    
                    // Output table row
                    
                    $query_teamlead = "SELECT team_leader FROM wpk4_backend_agent_codes where team_name='$team_name'";
                    $result_teamlead = mysqli_query($mysqli_replica, $query_teamlead);
                    $row_teamlead = mysqli_fetch_assoc($result_teamlead);
                    
                    $team_leader_r = $row_teamlead['team_leader'];
                    ?>
                    <tr>
                        <td><?php echo $agent_name; ?></td>
                        <td><?php echo $tsr; ?></td>
                        <td><?php echo $team_name; ?></td>
                        <td><?php echo $team_leader_r; ?></td>
                        <td>s r t</td>
                        <td>s s t</td>
                        <td><?php echo sprintf('%02d:%02d:%02d', ($logon_time/3600),($logon_time/60%60), $logon_time%60); ?></td>
                        <td>TSR</td>
                        <td>TSR</td>
                        <td>TSR</td>
                        <td><?php echo $gtib; ?></td>
                        <td>TSR</td>
                        <td>TSR</td>
                        <td>TSR</td>
                        <td><?php echo sprintf('%02d:%02d:%02d', ($aht/3600),($aht/60%60), $aht%60); ?></td>
                        <td>TSR</td>
                        <td>TSR</td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                    </tr>
                    <?php
                }
            }
            
            // Output the no coupon code row
            
            
            // Output the total row
            ?>
            
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

else if(isset($_GET['pg']) && $_GET['pg'] == 'dashboard2') {
    
    $fetch_team_name_query = "SELECT distinct(team_name) FROM wpk4_backend_agent_codes WHERE team_name IS NOT NULL or team_name != '' order by team_name asc;";
    $fetch_team_name_result = mysqli_query($mysqli_replica, $fetch_team_name_query) or die(mysqli_error($mysqli_replica));
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
                            while ($row_tn = mysqli_fetch_assoc($fetch_team_name_result)) {
                                $team_name = $row_tn['team_name'];
                                $selected = (isset($selectedTeam) && $team_name == $selectedTeam) ? 'selected' : ''; // Check if current team is selected
                                echo "<option value='" . $team_name . "' $selected>" . $team_name . "</option>";
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
$groupby = "team_name, agent_name, DATE(call_date)";
$team_name_query = 1;

if (isset($_POST['add-team-name-sl']) && $_POST['add-team-name-sl'] != '') {
    $selectedTeam = $_POST['add-team-name-sl'];
    $team_name_query = "a.team_name = '$selectedTeam'";
    $groupby = "team_name, agent_name, DATE(call_date)";
}

$sql = "
    SELECT
                MAX(team_name) AS team_name,
                MAX(agent_name) AS agent_name,
                SUM(pax) AS pax,
                SUM(fit) AS fit,
                SUM(pif) AS pif,
                SUM(gdeals) AS gdeals,
                SUM(gtib_count) AS gtib,
                SUM(sale_made_count) AS sale_made_count,
                SUM(non_sale_made_count) AS non_sale_made_count,
                SUM(rec_duration) AS rec_duration,
                CASE WHEN SUM(gtib_count) != 0 THEN SUM(pax) / SUM(gtib_count) * 100 ELSE 0 END AS conversion_percentage
            FROM (
                SELECT
                    a.agent_name,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.sale_made_count,
                    a.non_sale_made_count,
                    a.rec_duration AS rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN '$start_time' AND '$end_time' AND c.agent_name != '' and $team_name_query
               
                UNION ALL
               
                SELECT
                    a.agent_name,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE $team_name_query and a.order_date BETWEEN '$start_time' AND '$end_time' AND c.agent_name != '') AS combined_data
            GROUP BY team_name, agent_name ORDER BY team_name ASC, agent_name ASC;
";
$result = mysqli_query($mysqli_replica, $sql);

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
while ($row = mysqli_fetch_assoc($result)) {
   
   
   
   $team_name = $row['team_name'];
        $agent_name = $row['agent_name'];
        $gtib = (int) $row['gtib'];
        $gds = (int) $row['gdeals'];
        $fit = (int) $row['fit'];
        $pif = (int) $row['pif'] ?? '';
        $pax = (int) $row['pax'];
        $sale_made = (int) $row['sale_made_count'];
        $non_sale_made = (int) $row['non_sale_made_count'];
        $rec_duration = $row['rec_duration'];
    
        // Calculate totals
        $total_gtib += $gtib;
        $total_gds += $gds;
        $total_fit += $fit;
        $total_pif += $pif;
        $total_pax += $pax;
        $total_sale_made += $sale_made;
        $total_non_sale_made += $non_sale_made;
        
        $conversion = ($gtib != 0 ? number_format($pax / $gtib * 100, 2) : '-');
        $fcs = ($gtib != 0 ? number_format($sale_made / $gtib * 100, 2) : '-');
        $aht = secondsToTimeFormat($rec_duration, $gtib);
        
        $row_style = "style='background-color:white;'";

        // Output table row
        echo "<tr $row_style>
                <td>$team_name</td>
                <td>$agent_name</td>
                <td>$gtib</td>
                <td>$gds</td>
                <td>$fit</td>
                <td>$pif</td>
                <td>".($gds + $fit)."</td>
                <td>$pax</td>
                <td>".$conversion."</td>
                <td>".$fcs."</td>
                <td>$sale_made</td>
                <td>$non_sale_made</td>
                <td>$aht</td>
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
Limit 10;";
        $result = mysqli_query($mysqli_replica, $sql);
        
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
                
                // Fetch and display data rows
                while($row = mysqli_fetch_assoc($result)) {
                    $row['pif'] = 0;
                    $team_name = $row['team_name'];
                    $agent_name = $row['agent_name'];
                    if (isset($teams[$team_name]) && trim($teams[$team_name]) == trim($agent_name))
                    //if (trim($teams[$team_name]) == trim($agent_name)) 
                    {
                        continue;
                    }
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = (int) $row['pif'] ?? '';
                    $pax = (int) $row['pax'];
                    $sale_made = (int) $row['sale_made_count'];
                    $non_sale_made = (int) $row['non_sale_made_count'];
                    $aht = $row['aht'];
                
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
        $result = mysqli_query($mysqli_replica, $sql);
        
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
                
                // Fetch and display data rows
                while($row = mysqli_fetch_assoc($result)) {
                    $row['pif'] = 0;
                    $team_name = $row['team_name'];
                    $agent_name = $row['agent_name'];
                    if (isset($teams[$team_name]) && trim($teams[$team_name]) == trim($agent_name)) {
                        continue;
                    }
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = (int) $row['pif'] ?? '';
                    $pax = (int) $row['pax'];
                    $sale_made = (int) $row['sale_made_count'];
                    $non_sale_made = (int) $row['non_sale_made_count'];
                    $aht = $row['aht'];
                
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
    $fetch_team_name_query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_inbound_call WHERE team_name != NULL or team_name != ''";
    $fetch_team_name_result = mysqli_query($mysqli_replica, $fetch_team_name_query) or die(mysqli_error($mysqli_replica));
    $where = "WHERE DATE(ic.call_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Get data for yesterday";
    $team_name_query = '';
    $groupby = 'ic.team_name';
    
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
";
    
    if($currnt_userlogn == 'leen' || $currnt_userlogn == 'sriharshans')
    {
    //echo $sql;
    }
    
    echo '</br></br>';
    
    $result = mysqli_query($mysqli_replica, $sql);
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
            
            // Fetch and display data rows
            while($row = mysqli_fetch_assoc($result)) {
                $sale_manager = $row['sale_manager'];
                
                //if($team_name != '')
                {
                    $agent_name = $row['agent_name'];
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = 0;
                    $pax = (int) $row['pax'];
                    $totalPax = $gds + $fit;
                    $sale_made = (int) $row['FCS_count'];
                    $non_sale_made = (int) $row['non_sales_made'];
                    if($gtib != 0)
                    {
                        $aht = $row['call_duration'] / $gtib;
                    }
                    else
                    {
                        $aht = $row['call_duration'];
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
    $fetch_team_name_query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_inbound_call WHERE team_name != NULL or team_name != ''";
    $fetch_team_name_result = mysqli_query($mysqli_replica, $fetch_team_name_query) or die(mysqli_error($mysqli_replica));
    $where = "WHERE DATE(ic.call_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Get data for yesterday";
    $team_name_query = '';
    $groupby = 'ic.team_name';
    
    // Check if form is submitted
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $start_time = substr($payment_date, 0, 19);
            $end_time = substr($payment_date, 22, 19);
            $where = "WHERE DATE(a.order_date) = CURRENT_DATE()";
        }
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $team_name_query = "AND a.source <> 'import'";
            $selectedTeam = $team_name; // Set selected team for dropdown
            $groupby = 'ic.agent_name';
        }
        
        // Set payment date for input field
        $paymentDate = isset($_GET["date"]) ? $_GET["date"] : '';
    }
    
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
";
    
   if($currnt_userlogn == 'leen' || $currnt_userlogn == 'sriharshans')
                    {
                    //echo $sql;
           }
    
    echo '</br></br>';
    
    //$sql = "SELECT MAX(ic.team_name) AS team_name, MAX(ic.agent_name) AS agent_name, MAX(ac.team_leader) AS team_leader, SUM(ic.gtib_count) AS gtib, SUM(b.gdeals) AS gdeals, SUM(b.fit) AS fit, SUM(b.pif) AS pif, SUM(b.pax) AS pax, SUM(ic.sale_made_count) AS sale_made_count, SUM(ic.non_sale_made_count) AS non_sale_made_count, AVG(ic.rec_duration/ic.gtib_count) AS aht FROM wpk4_backend_agent_inbound_call AS ic LEFT JOIN wpk4_backend_agent_booking AS b ON ic.call_date = b.order_date AND ic.tsr = b.tsr LEFT JOIN wpk4_backend_agent_codes AS ac ON b.agent_name = ac.agent_name WHERE ic.call_date BETWEEN '2024-07-01' AND '2024-07-03 23:59:59' AND ic.team_name='Lamborghini' AND ic.team_name != '' GROUP BY ic.agent_name ORDER BY ic.agent_name;";
    $result = mysqli_query($mysqli_replica, $sql);
    

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
            
            // Fetch and display data rows
            while($row = mysqli_fetch_assoc($result)) {
                $team_name = $row['team_name'];
                
                //if($team_name != '')
                {
                    $agent_name = $row['agent_name'];
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = 0;
                    $pax = (int) $row['pax'];
                    $totalpax = $gds + $fit;
                    $sale_made = (int) $row['FCS_count'];
                    $non_sale_made = (int) $row['non_sales_made'];
                    if($gtib != 0)
                    {
                        $aht = $row['call_duration'] / $gtib;
                    }
                    else
                    {
                        $aht = $row['call_duration'];
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
                    
                    $query_teamlead = "SELECT team_leader FROM wpk4_backend_agent_codes where team_name='$team_name'";
                    $result_teamlead = mysqli_query($mysqli_replica, $query_teamlead);
                    $row_teamlead = mysqli_fetch_assoc($result_teamlead);
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
    $fetch_team_name_query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_inbound_call WHERE team_name != NULL or team_name != ''";
    $fetch_team_name_result = mysqli_query($mysqli_replica, $fetch_team_name_query) or die(mysqli_error($mysqli_replica));
    $where = "WHERE DATE(ic.call_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Get data for yesterday";
    $team_name_query = '';
    $groupby = 'ic.team_name';
    
    // Check if form is submitted
    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
        // time selector set
        if (isset($_GET["date"]) && $_GET["date"] != '') {
            $payment_date = $_GET['date'];
            $start_time = substr($payment_date, 0, 19);
            $end_time = substr($payment_date, 22, 19);
            $where = "WHERE DATE(a.order_date) = CURRENT_DATE()";
        }
        // team selector set
        if(isset($_GET["team"]) && $_GET['team'] != '') {
            $type = 'Agent Name';
            $team_name = $_GET['team'];
            $team_name_query = "AND a.source <> 'import'";
            $selectedTeam = $team_name; // Set selected team for dropdown
            $groupby = 'ic.agent_name';
        }
        
        // Set payment date for input field
        $paymentDate = isset($_GET["date"]) ? $_GET["date"] : '';
    }
    
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
ORDER BY sale_manager asc;";
    
   
    
    echo '</br></br>';
    
    //$sql = "SELECT MAX(ic.team_name) AS team_name, MAX(ic.agent_name) AS agent_name, MAX(ac.team_leader) AS team_leader, SUM(ic.gtib_count) AS gtib, SUM(b.gdeals) AS gdeals, SUM(b.fit) AS fit, SUM(b.pif) AS pif, SUM(b.pax) AS pax, SUM(ic.sale_made_count) AS sale_made_count, SUM(ic.non_sale_made_count) AS non_sale_made_count, AVG(ic.rec_duration/ic.gtib_count) AS aht FROM wpk4_backend_agent_inbound_call AS ic LEFT JOIN wpk4_backend_agent_booking AS b ON ic.call_date = b.order_date AND ic.tsr = b.tsr LEFT JOIN wpk4_backend_agent_codes AS ac ON b.agent_name = ac.agent_name WHERE ic.call_date BETWEEN '2024-07-01' AND '2024-07-03 23:59:59' AND ic.team_name='Lamborghini' AND ic.team_name != '' GROUP BY ic.agent_name ORDER BY ic.agent_name;";
    $result = mysqli_query($mysqli_replica, $sql);
    

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
            
            // Fetch and display data rows
            while($row = mysqli_fetch_assoc($result)) {
                $team_name = $row['team_name'];
                
                //if($team_name != '')
                {
                    $agent_name = $row['agent_name'];
                    $sale_manager = $row['sale_manager'];
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = 0;
                    $pax = (int) $row['pax'];
                    $totalpax = $gds + $fit;
                    $sale_made = (int) $row['FCS_count'];
                    $non_sale_made = (int) $row['non_sales_made'];
                    if($gtib != 0)
                    {
                        $aht = $row['call_duration'] / $gtib;
                    }
                    else
                    {
                        $aht = $row['call_duration'];
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
                    
                    $query_teamlead = "SELECT team_leader FROM wpk4_backend_agent_codes where team_name='$team_name'";
                    $result_teamlead = mysqli_query($mysqli_replica, $query_teamlead);
                    $row_teamlead = mysqli_fetch_assoc($result_teamlead);
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
    
    $agent_selected = $_GET['agent'];
    
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
";
    
   // echo $sql;
    
    
    echo '</br></br>';
    
    //$sql = "SELECT MAX(ic.team_name) AS team_name, MAX(ic.agent_name) AS agent_name, MAX(ac.team_leader) AS team_leader, SUM(ic.gtib_count) AS gtib, SUM(b.gdeals) AS gdeals, SUM(b.fit) AS fit, SUM(b.pif) AS pif, SUM(b.pax) AS pax, SUM(ic.sale_made_count) AS sale_made_count, SUM(ic.non_sale_made_count) AS non_sale_made_count, AVG(ic.rec_duration/ic.gtib_count) AS aht FROM wpk4_backend_agent_inbound_call AS ic LEFT JOIN wpk4_backend_agent_booking AS b ON ic.call_date = b.order_date AND ic.tsr = b.tsr LEFT JOIN wpk4_backend_agent_codes AS ac ON b.agent_name = ac.agent_name WHERE ic.call_date BETWEEN '2024-07-01' AND '2024-07-03 23:59:59' AND ic.team_name='Lamborghini' AND ic.team_name != '' GROUP BY ic.agent_name ORDER BY ic.agent_name;";
    $result = mysqli_query($mysqli_replica, $sql);
    

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
            
            // Fetch and display data rows
            while($row = mysqli_fetch_assoc($result)) {
                
                //$row_2 = mysqli_fetch_assoc($result_2);
                
                $team_name = $row['team_name'];
                $agent_name = $row['agent_name'];
                $gtib = (int) $row['gtib'];
                $gds = (int) $row['gdeals'];
                $fit = (int) $row['fit'];
                $pif = (int) ($row['pif'] ?? false) ? $row['pif'] : 0 ;
                $pax = (int) $row['pax'];
                $totalPax = $gds + $fit;
                $sale_made = (int) $row['FCS_count'];
                $non_sale_made = (int) $row['non_sales_made'];
                if($gtib != 0)
                {
                    $aht = $row['call_duration'] / $gtib;
                }
                else
                {
                    $aht = $row['call_duration'];
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
                
                $query_teamlead = "SELECT team_leader, sale_manager FROM wpk4_backend_agent_codes where team_name='$team_name'";
                $result_teamlead = mysqli_query($mysqli_replica, $query_teamlead);
                $row_teamlead = mysqli_fetch_assoc($result_teamlead);
                ?>
                <tr>
                    <td><?php echo $row_teamlead['sale_manager']; ?></td>
                    <td><?php echo $team_name; ?></td>
                    <td><?php echo $row_teamlead['team_leader']; ?></td>
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
            if(isset($type) && $type == 'Team Name') {
                $sql = "SELECT 
                        MAX(b.order_date) AS order_date, 
                        MAX(b.team_name) AS team_name, 
                        MAX(b.agent_name) AS agent_name, 
                        SUM(ic.gtib_count) AS gtib,
                        SUM(b.gdeals) AS gdeals,
                        SUM(b.fit) AS fit,
                        SUM(b.pif) AS pif,
                        SUM(b.pax) AS pax,
                        SUM(ic.sale_made_count) AS sale_made_count,
                        SUM(ic.non_sale_made_count) AS non_sale_made_count
                        FROM wpk4_backend_agent_inbound_call AS ic 
                        INNER JOIN wpk4_backend_agent_booking AS b ON ic.call_date = b.order_date
                        $where $team_name_query AND b.agent_name = '' AND ic.agent_name = '';";
                $result = mysqli_query($mysqli_replica, $sql);
                
                while($row = mysqli_fetch_assoc($result)) {
                    $date = $row['order_date'];
                    $gtib = (int) $row['gtib'];
                    $gds = (int) $row['gdeals'];
                    $fit = (int) $row['fit'];
                    $pif = (int) $row['pif'] ?? '';
                    $pax = (int) $row['pax'];
                    $totalpax = $fit + $gds;
                    $sale_made = (int) $row['sale_made_count'];
                    $non_sale_made = (int) $row['non_sale_made_count'];
                ?>
                    <tr>
                        <td><a href="?pg=no-coupon-data&date=<?php echo urlencode($payment_date); ?>">No Coupon Code</a></td>
                        <td>-</td>
                        <td>-</td>
                        <td><?php echo $gtib; ?></td>
                        <td><?php echo $gds; ?></td>
                        <td><?php echo $fit; ?></td>
                        <td><?php echo $pif; ?></td>
                        <td><?php echo $pax; ?></td>
                        
                        <td><?php echo ($gtib != 0) ? number_format($pax / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo ($gtib != 0) ? number_format($sale_made / $gtib * 100, 2) . '%' : '-'; ?></td>
                        <td><?php echo $sale_made; ?></td>
                        <td><?php echo $non_sale_made; ?></td>
                        <td>-</td>
                        <td><?php echo $totalpax; ?></td>
                    </tr>
                    <?php
                }
            }
            
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
        width: 80%;
        margin: 0 auto;
    }
    .table {
        text-align: center;
    }
    .table-striped tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .navi-menu {
        display: flex; 
        justify-content: space-between;
    }
    button {
        padding: 7px;
    }
    h6 {
        text-align: center;
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
