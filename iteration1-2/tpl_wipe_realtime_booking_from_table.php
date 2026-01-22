<?php
date_default_timezone_set("Australia/Melbourne"); 

$mysqli = new mysqli("localhost","aigauratravelcom_ai_usr","I8q!c4T5gRSW-1","aigauratravelcom_aigaura");
if ($mysqli -> connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  exit();
}

$date_yesterday = date('Y-m-d', strtotime('yesterday'));

$sql = "DELETE FROM wpk4_backend_travel_bookings_realtime where date(order_date) = '$date_yesterday'";
if($mysqli->query($sql) === TRUE) {  } else { echo "Error deleting record: " . $mysqli->error; }

$sql2 = "DELETE FROM wpk4_backend_travel_booking_pax_realtime where date(order_date) = '$date_yesterday'";
if($mysqli->query($sql2) === TRUE) {  } else { echo "Error deleting record: " . $mysqli->error; }

$mysqli->close();
?>