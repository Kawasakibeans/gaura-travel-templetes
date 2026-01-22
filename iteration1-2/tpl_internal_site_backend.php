<?php
/**
 * Template Name: Internal Website
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<div class='wpb_column vc_column_container ' style='width:80%; margin: 0 auto;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);

global $current_user;
$currnt_userlogn = $current_user->user_login;
$user_roles = $current_user->roles;
$user_role = array_shift($user_roles);
if(isset($current_user) && $user_role != '') // check whether anyone logged in
{
    if(!isset($_GET['pg']))
    {
        if(current_user_can( 'administrator_test' )) // if the loged in user is administrator 
        {
            echo '<center>';
                $post_id = 118188;
                $encrypt_id = md5($post_id); // encrypt the post id to ensure the authenticity
                
                echo '<a href="?pg=view&id='.$post_id.'&enc_id='.$encrypt_id.'"><button>Check Incentive</button></a>'; // view button with the link to incentive
            echo '</center>';
        }
        
        echo apply_filters('the_content', get_post_field('post_content', 118133)); // show internal home page
    }
    else // if isset pg
    {
        if(isset($_GET['pg']) && $_GET['pg'] == 'view')
        {
            $post_id = $_GET['id']; //get id from url
            $encrypt_id = md5($post_id); // encrypt the post id to check with received encrypted post id
            
            $enc_get_post_id = $_GET['enc_id']; // get encrypted id from url
            if($encrypt_id == $enc_get_post_id) // check whether the post id is matching with encrpted id
            {
                echo apply_filters('the_content', get_post_field('post_content', $post_id)); // show the page by the post id.
            }
        }
        
        if (isset($_GET['pg']) && $_GET['pg'] == 'itinerary') 
            {
                $post_id_get=$_GET['id'];
                if (isset($_GET['id']) && $_GET['id'] == $post_id_get) 
                {
                    
                echo "<h2 style='text-align: center;'>Itineraries</h2>";
                include('wp-config-custom.php');
                
        $traveldate_fxed = '0000-00-00 00:00:00';
               
                $wptravel_itineraries = get_post_meta( $post_id_get, 'wp_travel_trip_itinerary_data', true );
                $trip_wp_title = get_post_field( 'post_title', $post_id_get );
                $trip_wp_name = get_post_field( 'post_name', $post_id_get );
                 if($trip_wp_name != '')
                 {
                        if ( isset( $wptravel_itineraries ) && ! empty( $wptravel_itineraries ) ) : 
            				$wptravel_index = 1;
            				$itinerary_location_array = array();
            				$itinerary_time_array = array();
            				$itinerary_flight_array = array();
            				$itinerary_date_array = array();
            				$itinerary_datedecider_array = array();
            				$itinerary_array_counter = 0;
            				
            				foreach ( $wptravel_itineraries as $wptravel_itinerary ) : 
            					$wptravel_time_format = get_option( 'time_format' );
            					$wptravel_itinerary_label = '';
            					$wptravel_itinerary_title = '';
            					$wptravel_itinerary_desc  = '';
            					$wptravel_itinerary_date  = '';
            					$wptravel_itinerary_time  = '';
            					
            						$wptravel_itinerary_label = stripslashes( $wptravel_itinerary['label'] );
            					
            						$wptravel_itinerary_title = stripslashes( $wptravel_itinerary['title'] );
            					
            						$wptravel_itinerary_desc = stripslashes( $wptravel_itinerary['desc'] );
            					
            					
            						$wptravel_itinerary_date = wptravel_format_date( $wptravel_itinerary['date'] );
            					
            						$wptravel_itinerary_time = stripslashes( $wptravel_itinerary['time'] );
            						$wptravel_itinerary_time = date( $wptravel_time_format, strtotime( $wptravel_itinerary_time ) ); 
            					
            
            				    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
            					$itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
            					$itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
            					$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
            					$itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
            							
            					$wptravel_index++;
            					$itinerary_array_counter++;
            				endforeach;
            			endif; 
            			// DEFINE EXTRA DAYS
            			
            			$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
            			$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
            			$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
            			$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));
                        if(isset($itinerary_location_array))
                        {
            			    $length_aray = count($itinerary_location_array);
                        }
                        else
                        {
                            $length_aray = 0;
                        }
            			$itinerary_vals = '<h5> ID:'.$post_id_get.'</h5>';
            			$itinerary_vals .= '<h6> Trip: '.$trip_wp_name.'</h6></br>';
            			$itinerary_vals .= '<table class="m_-8969220568537220410 wp-travel-table-content trip_'.$post_id_get.'" cellpadding="0" cellspacing="0" height="100%" width="100%" style="text-align:left; border: 1px solid #e1e1e; border-collapse: collapse;">
            			<thead>
            			  <tr style"border: 1px solid;">
            				 <th style="width:30%">Airport</th>
            				 <th style="width:30%">Flight</th>
            				 <th style="width:20%">Date</th>
            				 <th style="width:20%">Time</th>
            				 
            			  </tr>
            			</thead>
            			<tbody>';
            			// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
            			for ($i = 0; $i < $length_aray; $i++) {
            				if($itinerary_location_array[$i] == 'WAIT')
            				{
            					$itinerary_vals .= "<tr style'border: 1px solid #e1e1e;'>";
            					$itinerary_vals .= '<td colspan="4" style="border: 1px solid #e1e1e; width:30%">'.$itinerary_location_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
            					$itinerary_vals .= "</tr>"; 
            				}
            				else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
            				{
            					$itinerary_vals .= "<tr style'border: 1px solid #e1e1e;'>";
            					$itinerary_vals .= '<td colspan="4" style="border: 1px solid #e1e1e; width:30%">'.$itinerary_datedecider_array[$i].' - '.$itinerary_flight_array[$i].'</td>';
            					$itinerary_vals .= "</tr>"; 
            				}
            				else
            				{
            					$itinerary_vals .= "<tr style'border: 1px solid #e1e1e;'>";
            					$itinerary_vals .= '<td style="border: 1px solid #e1e1e; width:30%">'.$itinerary_location_array[$i].'</td>';
            					$itinerary_vals .= '<td style="border: 1px solid #e1e1e; width:30%">'.$itinerary_flight_array[$i].'</td>';
            					$itinerary_vals .= '<td style="border: 1px solid #e1e1e; width:20%">'.$itinerary_datedecider_array[$i].'</td>';
            					$itinerary_vals .= '<td style="border: 1px solid #e1e1e; width:20%">'.$itinerary_time_array[$i].'</td>';
            					$itinerary_vals .= "</tr>";
            				}
            				
            			}
                		$itinerary_vals .= "</tbody></table></br></br>";
                		
                		// REPLACE ALL THE FIXED WORDS AS DEPARTURE AND ARRIVAL ON BACKEND
                		$itinerary_vals = str_replace("Departure Date", 'Departure Date: ' ,$itinerary_vals);
                		$itinerary_vals = str_replace("Arrival Date", 'Arrival Date: ' ,$itinerary_vals);
                			
            	       echo $itinerary_vals;
                    }
                    else
                    {
                        echo 'Trip ID not found.';
                    }
                }
                else
                {
                    echo 'Kindly add a valid trip id.';
                }

            }
            
    }
}
else
{
    echo '<script>window.location.href="https://gauratravel.com.au/404";</script>';
    echo '<center><br/><br/><br/>You are not authorized to view this page. Kindly login<br/><br/><br/></center>'; // if not logged in
}
echo '</div>';
get_footer();
?>