<?php
/**
 * Template Name: Seat Availability
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<html> 
<head>
    <style>
        .button_customized
        {
            background-color:#FFBB00; 
            color:black;
        }
        .hideitinerary
        {
            background-color:#c79304; 
            color:black;
        }
		.tripitinerary td, .tripitinerary th
		{
			font-size:13px;
		}
		table
		{
			border:none;
		}
		
    </style>
</head>
<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:80%;margin:auto;padding:100px 0px;'>
<?php

error_reporting(E_ALL);
include("wp-config-custom.php");
$query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
$result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
$row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
$is_ip_matched = mysqli_num_rows($result_ip_selection);
//if($row_ip_selection['ip_address'] == $ip_address)
{

global $current_user;
$currnt_userlogn = $current_user->user_login;
$user_roles = $current_user->roles;
$user_role = array_shift($user_roles);

if($user_role == 'administrator' || $user_role == 'shop_manager' || current_user_can( 'ho_operations' )   || $user_role == 'wp_travel_bookings_reader' || $user_role == 'agents' || $user_role == 'wp_travel_reader_sales')
{
if(!isset($_GET['pg']))
{
$endoftoday = date("Y-m-d"). ' 00:00:00';
// View stock start
$themepath = get_stylesheet_directory_uri();
$themepath = str_replace("https://gauratravel.com.au","",$themepath);


//echo $themepath;
?>
</br></br>
<table class="table" style="width:100%; margin:auto; border:1px solid #adadad;">
<tr>
<td colspan='3'>
    Filter
    </td></tr>
<tr>
<td width='33%'>Airline <font style='color:red'>*</font>
<select name='airlinecode' required id='airlinecode' onChange='updateroute(this.value)' style="width:100%; padding:10px;">
<option value=''>Select</option>
<?php
$array_airline_code = array();
$query_r = "SELECT * FROM wpk4_backend_stock_management_sheet where dep_date > '$endoftoday' AND airline_code NOT IN ('FC','MH') order by airline_code asc";
$result_rg = mysqli_query($mysqli, $query_r) or die(mysqli_error($mysqli));
while($row_ty = mysqli_fetch_assoc($result_rg))
{
	$array_airline_code[] = $row_ty['airline_code'];
}
$array_airline_code = array_unique($array_airline_code);

foreach($array_airline_code as $value)
{ 
	?>
		<option value='<?php echo $value; ?>'><?php echo $value; ?></option>
	<?php
}
?>
</select>
</td>
<td width='33%'>Route <font style='color:red'>*</font>

<div id="selectionroute">
	<select name='routeid' required id='routeid' onChange='updatedate(this.value)' style='width:100%; padding:10px;'>
	<option value='' selected>Select</option>
	</select>
</div>
</td>
<td width='33%'>Travel Date
<input type='text' readonly name='tripdate_selector' id='tripdate_selector' disabled>
<input type='hidden' readonly name='tripdate_selector_backend' id='tripdate_selector_backend'>
</td>
</tr>
<tr>
<td colspan='3' style='text-align:center;'>
<button style='padding:10px; margin:0;font-size:11px; ' id='search_orders' class="button_customized" onclick="searchrequest()">Search</button>
</td>
</tr>
</table></br>
<!--<button style='padding:10px; margin:0;font-size:11px; ' class="button_customized" onclick="export_csv()">Export</button>-->
<span style='float:right;'>
<font style="color:green;">Available</font>: More than 3 seats available. | 
<font style="color:orange">Available</font>: Less than 3 seats available.</span>
<div id="showresults"></div>
<?php

// View stock end
}
if(isset($_GET['pg']) && $_GET['pg']=='export')
{

	$popup_results = '';
	$airlineid = $_GET["trip"];
	$routeid = $_GET["route"];
	
	
	$datefrom = $_GET["date"];
	$dateto = $_GET["date"];
	
	$reldate1 = substr($datefrom, 0, 10).' 00:00:00';
	$reldate2 = substr($dateto, 25, 10).' 23:59:59';
	
	if($_GET["trip"] != '' && $_GET["trip"] != 'NULL' && $_GET["trip"] != 'null')
	{
		$airline= "airline_code = '$airlineid' && ";
	}
	else
	{
		$airline= "airline_code != 'TEMPAIRF' && ";
	}
	
	if($_GET["route"] != '' && $_GET["route"] != 'NULL' && $_GET["route"] != 'null')
	{
		$route= "route = '$routeid' && ";
	}
	else
	{
		$route= "airline_code != 'TEMPAIRF' && ";
	}
	
	if($_GET["date"] != '' && $_GET["date"] != 'NULL' && $_GET["date"] != 'null')
	{
		$datefrom = "dep_date >= '$reldate1' && dep_date <= '$reldate2' ";
	}
	else
	{
		$datefrom = "airline_code != 'TEMPAIRF' ";
	}
	
	
	$delimiter = ","; 
    $filename = "seats_" . date('Y-m-d H-i-s') . ".csv"; 
     
    $f = fopen('csv_reports/'.$filename, 'w'); 
     
	$fields = array('Trip Code', 'Travel Date','Availability'); 
	//3
    fputcsv($f, $fields, $delimiter); 
    $x_id = 1;
	
	$query_pax = "SELECT * FROM wpk4_backend_stock_management_sheet where $airline $route $datefrom order by dep_date asc";
	$result_pax = mysqli_query($mysqli, $query_pax);
    while($row = mysqli_fetch_assoc($result_pax))
    { 
	
	$auto_id = $row['auto_id'];
	$trip_id = $row['trip_id'];
	$dep_date = $row['dep_date'];
	$dep_date_changed = date('d-m-Y', strtotime($dep_date));
	$current_stock = $row['current_stock'];
	
	$order_count = 0;
		$pax_count = 0;
	$query_pax_product = "SELECT * FROM wpk4_backend_travel_bookings where trip_code='$trip_id' && travel_date='$dep_date' && (payment_status = 'paid' || payment_status = 'partially_paid')";
	$result_pax_product = mysqli_query($mysqli, $query_pax_product);
	while($row_pax_product = mysqli_fetch_assoc($result_pax_product))
	{
		$pax_count += $row_pax_product['total_pax'];
		$order_count++;
	}
	
	$remainingseats = (int)$current_stock - (int)$pax_count;
	if($remainingseats > 2)
		{
		
	
        $lineData = array($trip_id, $dep_date_changed,  'Available' ); 
		
        fputcsv($f, $lineData, $delimiter); 
    
	$x_id++;
	 
     }
    
	} 
	fseek($f, 0); 
     
    header('Content-Type: text/csv'); 
    header('Content-Disposition: attachment; filename="' . $filename . '";'); 
     
    fpassthru($f); 
	
	
	echo '<script>window.location.href="https://gauratravel.com.au/csv_reports/'.$filename.'";</script>';
	
	exit;
	

	
}


} //check whether admin logged in END
?>
<script type="text/javascript">
    function isNumberKey(evt){
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode == 46 || charCode > 31 && (charCode < 48 || charCode > 57)){
        evt.preventDefault();
        return false;
    }
    return true;
	}
	
	function isTextKey(evt){
    var charCode = (evt.which) ? evt.which : evt.keyCode;
	if (charCode > 31 && (charCode < 65 || charCode > 90) && (charCode < 97 || charCode > 122))
	{
        evt.preventDefault();
        return false;
    }
    return true;
	}
	
	function ValidateEmail(inputText)
{
var mailformat = /^w+([.-]?w+)*@w+([.-]?w+)*(.w{2,3})+$/;
if(this.value.match(mailformat))
{
alert("You have entered a valid email address!");    //The pop up alert for a valid email address
return true;
}
else
{
alert("You have entered an invalid email address!");    //The pop up alert for an invalid email address
return false;
}
}
</script>

<script>		
function updateroute(val){
	
	//var last_updated_value = $("#last_updated_id").val();
  if(val == '')
	{
		document.getElementById("selectionroute").innerHTML = "<select name='routeid' required id='routeid'  style='width:100%; padding:10px;'><option value='' selected>Select</option>	</select>";
		document.getElementById('tripdate_selector').disabled = true;
		document.getElementById("tripdate_selector_backend").value = '';
		document.getElementById("tripdate_selector").value = '';
	}
	else
	{
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function() {
		  if (this.readyState == 4 && this.status == 200) {
			document.getElementById("selectionroute").innerHTML = this.responseText;
			//showresult();
		  }
		};
		xmlhttp.open("GET","<?php echo $themepath; ?>/templates/tpl_seat_availability_backend.php/?airline="+val,true);
		xmlhttp.send();
		//alert("airline");
	}
}
function updatedate(val){
	//showresult();
	if(val != '')
	{
		document.getElementById('tripdate_selector').disabled = false;
		document.getElementById("tripdate_selector_backend").value = '';
		document.getElementById("tripdate_selector").value = '';
		//document.getElementById("tripdate_selector").style.display = "block";
    }
	else
	{
		document.getElementById('tripdate_selector').disabled = true;
		document.getElementById("tripdate_selector_backend").value = '';
		document.getElementById("tripdate_selector").value = '';
		//document.getElementById("tripdate_selector").style.display = "none";
	}
	//alert("route");
}
function searchrequest(){
	var airlinecode = $("#airlinecode").val();
	var routeid = $("#routeid").val();
	
	if(airlinecode != '' && routeid != '')
	{
		showresult();
	}
}
function export_csv(){
	var airlinecode = $("#airlinecode").val();
	var routeid = $("#routeid").val();
	var tripdate_selector = $("#tripdate_selector_backend").val();
	
	if(airlinecode != '' && routeid != '')
	{
		window.open('?pg=export&trip=' + airlinecode + '&route=' + routeid + '&date=' + tripdate_selector + '' ,'_blank');
		//window.location.href = ';
	}
}
const myTextBox = document.getElementById("tripdate_selector");
myTextBox.addEventListener("input", function() {
   if (myTextBox.value === "") {
      document.getElementById("tripdate_selector_backend").value = '';
	  //showresult();
    }
  });
function showresult(){
	
	var airlinecode = $("#airlinecode").val();
	var routeid = $("#routeid").val();
	var tripdate_selector = $("#tripdate_selector_backend").val();
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
		document.getElementById("showresults").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","<?php echo $themepath; ?>/templates/tpl_seat_availability_backend.php/?showresults=true&airline="+airlinecode+"&route="+routeid+"&datefrom="+tripdate_selector+"&dateto="+tripdate_selector,true);
    xmlhttp.send();
}
</script>
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js" type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/gh/alumuko/vanilla-datetimerange-picker@latest/dist/vanilla-datetimerange-picker.js"></script>
<script>
      window.addEventListener("load", function (event) {
            var currentdate = new Date(); 
            var date_tomorrow = new Date((new Date()).valueOf() + 1000*3600*24);
			let drp = new DateRangePicker('tripdate_selector',
                    {
                        minDate: date_tomorrow,
                        timePicker: false,
                        alwaysShowCalendars: true,
                        singleDatePicker: false,
                        autoApply: false,
						autoUpdateInput: false,
                        ranges: {
                            'Today': [moment().startOf('day'), moment().endOf('day')],
                            'Yesterday': [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
                            'Last 7 Days': [moment().subtract(6, 'days').startOf('day'), moment().endOf('day')],
                            'This Month': [moment().startOf('month').startOf('day'), moment().endOf('month').endOf('day')],
                        },
                        locale: {
                            format: "YYYY-MM-DD HH:mm:ss",
                        }
                    },
                    function (start, end) {
						var departure_start = start.format().slice(0,10);
						//selectedSubstring = originalString.substring(9, 15);
						var departure_end = end.format().slice(0,10);
						
						document.getElementById("tripdate_selector").value = departure_start + ' - ' + departure_end;
						document.getElementById("tripdate_selector_backend").value = start.format() + end.format();
						//showresult();
                    })
				window.addEventListener('apply.daterangepicker', function (ev) {
                    console.log(ev.detail.startDate.format('YYYY-MM-DD'));
                    console.log(ev.detail.endDate.format('YYYY-MM-DD'));
                });	
			});
    </script>
   <script>
function showitinerary(id)
    {
        var itinarary_idfinder = id.split('_');
        var itinarary_id = itinarary_idfinder[1];
        var itinarary_row_name = 'itinerary_'+itinarary_id;
        var view_itinarary_row_name = 'viewitinerary_'+itinarary_id;
        var hide_itinarary_row_name = 'hideitinerary_'+itinarary_id;
        $(".hideitinerary").hide();
        $(".showitinerary").show();
        $(".itinerarybox").hide();
        //alert(itinarary_row_name);
        document.getElementById(itinarary_row_name).style.display = "block";
        document.getElementById(view_itinarary_row_name).style.display = "none";
        document.getElementById(hide_itinarary_row_name).style.display = "block";
    }
function hideitinerary(id)
    {
        var itinarary_idfinder = id.split('_');
        var itinarary_id = itinarary_idfinder[1];
        var itinarary_row_name = 'itinerary_'+itinarary_id;
        var view_itinarary_row_name = 'viewitinerary_'+itinarary_id;
        var hide_itinarary_row_name = 'hideitinerary_'+itinarary_id;
        $(".hideitinerary").hide();
        $(".showitinerary").show();
        $(".itinerarybox").hide();
        document.getElementById(view_itinarary_row_name).style.display = "block";
        document.getElementById(hide_itinarary_row_name).style.display = "none";
    }
</script>    
</br></br></br></br>
</br></br>
</div>
</body>	
<?php
}
?>
<?php get_footer(); ?>