<?php

/**
 * Template Name: WPT Soldout Checker
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header(); ?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings'
    style='width:95%;margin:auto;padding:100px 0px;'>
    <?php
    error_reporting(E_ALL);
    include("wp-config-custom.php");
    date_default_timezone_set("Australia/Melbourne");
    $current_time = date('Y-m-d H:i:s');

    global $current_user;
    $currnt_userlogn = $current_user->user_login;
    $query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='$ip_address'";
    $result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
    $row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
    $is_ip_matched = mysqli_num_rows($result_ip_selection);
    if ($row_ip_selection['ip_address'] == $ip_address) {
        if (current_user_can('administrator') || current_user_can('ho_operations')) {


            //scenario1: <1 and not exist in exclude table => add to exclude
            $query_all_trips_nonmatch = "select p.trip_id, wp.meta_value as booked, p.id, p.max_pax, d.start_date, p.max_pax - wp.meta_value as closed 
                                    from wpk4_postmeta wp 
                                    left join wpk4_wt_pricings p on SUBSTRING_INDEX(SUBSTRING_INDEX(wp.meta_key,'pax-', -1),'-',1) = p.id
                                    left join wpk4_wt_dates d on p.id = d.pricing_ids
                                    left join wpk4_posts wp2 on p.trip_id = wp2.ID
                                    where wp.meta_key like 'wt_booked_pax%' and wp.post_id NOT IN (60107,60116) and wp2.post_status = 'publish' && wp2.post_type = 'itineraries' AND (p.max_pax - wp.meta_value) < 1
                                    AND d.start_date IS NOT NULL; ";
            $result_all_trips_nonmatch = mysqli_query($mysqli, $query_all_trips_nonmatch);
            if (!$result_all_trips_nonmatch) {
                die("Query execution failed: " . mysqli_error($mysqli));
            }
            while ($row_all_trips_nonmatch = mysqli_fetch_assoc($result_all_trips_nonmatch)) {
                $pricingid = $row_all_trips_nonmatch['id'];
                $tripid = $row_all_trips_nonmatch['trip_id'];
                $closed = $row_all_trips_nonmatch['closed'];
                $start_date = $row_all_trips_nonmatch['start_date'];

                // fetch product title
                $query_get_title = "SELECT * FROM wpk4_wt_dates where trip_id = '$tripid' AND pricing_ids = '$pricingid'";
                $result_get_title = mysqli_query($mysqli, $query_get_title);
                $row_get_title = mysqli_fetch_assoc($result_get_title);
                $product_trip_title = $row_get_title['title'];

                // fetch excluded products
                $query_is_existing = "SELECT * FROM wpk4_wt_excluded_dates_times where trip_id = '$tripid' AND start_date='$start_date'";
                $result_is_existing = mysqli_query($mysqli, $query_is_existing);
                $row_is_existing = mysqli_num_rows($result_is_existing);
                 echo 'Success.';

                if ($row_is_existing == 0) {

                    mysqli_query($mysqli, "insert into wpk4_wt_excluded_dates_times ( trip_id, title, years, months, start_date) 
                    values ('$tripid', '$product_trip_title', 'every_year', 'every_month', '$start_date')") or die(mysqli_error($mysqli));
                    
                   

                    // echo 'adding entry to exclude table: - ';
                    // echo  'trip_id: ' . $tripid . ' pricing_id: ' . $pricingid . ' prod_title: ,' . $product_trip_title . ' start_date,' . $start_date . ',</br>';
                }
            }

            // scenario 2: >0 and exist in exclude table => remove

            $query_all_trips_nonmatch_2 = "select p.trip_id, wp.meta_value as booked, p.id, p.max_pax, d.start_date, p.max_pax - wp.meta_value as closed 
                                    from wpk4_postmeta wp 
                                    left join wpk4_wt_pricings p on SUBSTRING_INDEX(SUBSTRING_INDEX(wp.meta_key,'pax-', -1),'-',1) = p.id
                                    left join wpk4_wt_dates d on p.id = d.pricing_ids
                                    left join wpk4_posts wp2 on p.trip_id = wp2.ID
                                    where wp.meta_key like 'wt_booked_pax%' and wp.post_id NOT IN (60107,60116) and wp2.post_status = 'publish' && wp2.post_type = 'itineraries' AND (p.max_pax - wp.meta_value) > 0
                                    AND d.start_date IS NOT NULL; ";
            $result_all_trips_nonmatch_2 = mysqli_query($mysqli, $query_all_trips_nonmatch_2);
            if (!$result_all_trips_nonmatch_2) {
                die("Query execution failed: " . mysqli_error($mysqli));
            }
            //$counter = 0;
            while ($row_all_trips_nonmatch_2 = mysqli_fetch_assoc($result_all_trips_nonmatch_2)) {
                $pricingid_2 = $row_all_trips_nonmatch_2['id'];
                $tripid_2 = $row_all_trips_nonmatch_2['trip_id'];
                $closed_2 = $row_all_trips_nonmatch_2['closed'];
                $start_date_2 = $row_all_trips_nonmatch_2['start_date'];

                // fetch product title
                $query_get_title_2 = "SELECT * FROM wpk4_wt_dates where trip_id = '$tripid_2' AND pricing_ids = '$pricingid_2'";
                $result_get_title_2 = mysqli_query($mysqli, $query_get_title_2);
                $row_get_title_2 = mysqli_fetch_assoc($result_get_title_2);
                $product_trip_title_2 = $row_get_title_2['title'];

                // fetch excluded products
                $query_is_existing_2 = "SELECT * FROM wpk4_wt_excluded_dates_times where trip_id = '$tripid_2' AND start_date='$start_date_2'";
                $result_is_existing_2 = mysqli_query($mysqli, $query_is_existing_2);
                $row_is_existing_2 = mysqli_num_rows($result_is_existing_2);
                
                 echo 'Success.';

                if ($row_is_existing_2 > 0) {

                    $query_2 = "delete from wpk4_wt_excluded_dates_times where trip_id=" . $tripid_2 . " and start_date='" . $start_date_2 . "';";
                    mysqli_query($mysqli, $query_2) or die(mysqli_error($mysqli));
                    
                    

                    // echo 'removing entry from exclude table: - ';
                    // echo  'trip_id: ' . $tripid_2 . ' pricing_id: ' . $pricingid_2 . ' prod_title: ,' . $product_trip_title_2 . ' start_date,' . $start_date_2 . ',</br>';
                }
            }
        }
    } else {
        echo "<center>This page is not accessible for you.</center>";
    }
    ?>
</div>
<?php get_footer(); ?>