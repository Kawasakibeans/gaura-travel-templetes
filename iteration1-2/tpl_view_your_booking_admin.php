<?php
/**
 * Template Name: View Your Booking Admin page
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

table {
  text-align: left;
  position: relative;
  border-collapse: collapse; 
}
th, td {
  padding: 0.25rem;
}
tr.red th {
  background: red;
  color: white;
}
tr.green th {
  background: green;
  color: white;
}
tr.purple th {
  background: purple;
  color: white;
}
th {
  background: white;
  /*position: sticky;*/
  top: 30px; /* Don't forget this, required for the stickiness */
  box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
}
body{
	    /* font: normal 12px/12px Verdana, Sans-Serif;*/
		color:#27272b;
}
.table1 {
		
		
  border-collapse: collapse;
  margin-top:10px;
  
   
}
.tableinternal {
  border: none;
}
.tableinternal td{
  
  border: none !important;
  padding: 0px !important;
  border-collapse: collapse;

}
hr
{
	margin-top:30px;
	margin-bottom:30px;
}

.table1 th{
  border: 1px solid #ada2a2;
  padding: 5px;
background: #D0A02B;
    color: #3a3535;
	font-weight: 300;
}

.table1 td{
  border: 1px solid #ada2a2;
  padding: 10px;

}
.status
{
	display:none;
}
.application
{
	display:none;
}
#table2 {
  border: 0px;
  padding: 10px;
}


</style>
<script type="text/javascript">
    function handleRadioClick() 
    {
    var rdbsearchorderno= document.getElementById('searchorderno');
    var rdbsearchdate= document.getElementById('searchdate');
    var trordernorow= document.getElementById('ordernorow');
    var trbookingdaterow= document.getElementById('bookingdaterow');

      if (rdbsearchorderno.checked) {
        trordernorow.style.display = 'table-row';
        trbookingdaterow.style.display = 'none';

      } else if (rdbsearchdate.checked){
        trbookingdaterow.style.display = 'table-row';

        trordernorow.style.display = 'none';
        
      }
    }
</script>

</head>
<body>
<?php
include('wp-config-custom.php');
  	
?>
    	<center>
    	    
				<div>
				<form method="post" action="<?php $actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";?>">
				<table style="width:auto;">
				    <tr>
						<td><label> search by order no <input type="radio" name="searchorderno" id="searchorderno" onchange="handleRadioClick()"/></label></td>
						<td><label> search by date <input type="radio" name="searchorderno" id="searchdate" onchange="handleRadioClick()"/></label></td>
					  </tr>
					<tr id="ordernorow" style="display:none;">
						<td>Order Number</td>
						<td><input type="number" name="orderno" value="orderno" placehoder="order number"></td>
					</tr>
					
					<tr id="bookingdaterow"  style="display:none;">
						<td>Booking Date</td>
						<td><input type="date" name="bookingdate" value="bookingdate"</td>
					</tr>
					
					  <tr>
						<td colspan="2" align="center" style="padding-top:10px;">  <center><input type="submit" name="search" id="search"></center></td>
					  </tr>
					  
				</table>
				</form>
				<?php
							
					if(isset($_POST['search']))
					{
					    
					    	
					    
					    
					    $date=$_POST['bookingdate'];
        				$orderno=$_POST['orderno'];
		        		//$statuswhere="";
    					//$statussearch="";
    					if($date=="" && $orderno !="")
    					{
    						//$statuswhere="";
    						//$statussearch="";
    						//$statuswhere.=" pm.post_id = '".$orderno."' ";  
    						//$statussearch.=$orderno;
    						if($orderno!="")
    						{
                    			$booking_id    = $orderno;
                    			$details       = wptravel_booking_data( $booking_id );
                    			$payment_data  = wptravel_payment_data( $booking_id );
                    			$order_details = get_post_meta( $booking_id, 'order_items_data', true ); // Multiple Trips.
                    
                    			$customer_note = get_post_meta( $booking_id, 'wp_travel_note', true );
                    			$travel_date   = get_post_meta( $booking_id, 'wp_travel_arrival_date', true );
                    			$trip_id       = get_post_meta( $booking_id, 'wp_travel_post_id', true );
                    
                    			$title = get_the_title( $trip_id );
                    			$pax   = get_post_meta( $booking_id, 'wp_travel_pax', true );
                    
                    			// Billing fields.
                    			$billing_address = get_post_meta( $booking_id, 'wp_travel_address', true );
                    			$billing_city    = get_post_meta( $booking_id, 'billing_city', true );
                    			$billing_country = get_post_meta( $booking_id, 'wp_travel_country', true );
                    			$billing_postal  = get_post_meta( $booking_id, 'billing_postal', true );
                    
                    			// Travelers info.
                    			$fname       = get_post_meta( $booking_id, 'wp_travel_fname_traveller', true );
                    			$lname       = get_post_meta( $booking_id, 'wp_travel_lname_traveller', true );
                    			$status_list = wptravel_get_payment_status();
                    			if ( is_array( $details ) && count( $details ) > 0 ) {
                    				?>
                    				<div class="my-order my-order-details">
                    					<div class="view-order">
                    						<div class="order-list">
                    							<div class="order-wrapper">
                    								<h3><?php esc_html_e( 'Your Booking Details', 'wp-travel' ); ?> <a href="<?php echo esc_url( $back_link ); ?>"><?php esc_html_e( '(Back)', 'wp-travel' ); ?></a></h3>
                    								<?php wptravel_view_booking_details_table( $booking_id ); ?>
                    							</div>
                    							<?php echo WpTravel_Helpers_Payment::render_payment_details( $booking_id ); // @phpcs:ignore ?>
                    						</div>
                    					</div>
                    				</div>
                    				<?php
                    			}
    						}
    						else
    						{
    						    echo "something went wrong, please reload this page and try again";
    						}
    					
    					}
    					else if($date!="" && $orderno =="")
    					{
    						
    						
    						
    						echo $date;
				            $query="SELECT p.id as ID, pm2.meta_value FROM wpk4_posts p LEFT JOIN wpk4_postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'wp_travel_arrival_date' WHERE p.post_type = 'itinerary-booking' and date(post_date) = '".$date."'";
				            
				            //echo $query;//exit;
				            
				            ?>
    					   <table class="table1">
    					       <tr>
    					           <th>Order No</th>
    					           <th>Travel Date</th>
								   <th>Payment Status</th>
    					           <th>Salutation</th>
            					   <th>First Name</th>
            					   <th>Last Name</th>
            					   <th>Country</th>
            					   <th>Phone</th>
            					   <th>Email</th>
            					   <th>DOB</th>
            					   <th>Gender</th>
    							   <th>Passport Num</th>
    							   <th>Passport Exp Date</th>
    							   <th>Passport Type</th>
    							   <th>Visa Type</th>
    							   <th>Meal</th>
    							   <th>Wheel Chair</th>
            					  
    					   </tr>
				        <?php 
						$resultgetlabels = $mysqli->query($query);
				        $num_rows = mysqli_num_rows($resultgetlabels);
						//echo $num_rows."numbers";//exit;
        				if($num_rows > 0)
        				{
        				       //$rowgetlabels = $resultgetlabels->fetch_assoc(); 
        				    while($rowgetlabels = $resultgetlabels->fetch_assoc())
            				{
            				?>
            				<tr>
							<?php 
        						$booking_id    = $rowgetlabels["ID"];
                    			$details       = wptravel_booking_data( $booking_id );
                    			$payment_data  = wptravel_payment_data( $booking_id );
                    			$order_details = get_post_meta( $booking_id, 'order_items_data', true ); // Multiple Trips.
                    
                    			$customer_note = get_post_meta( $booking_id, 'wp_travel_note', true );
                    			$travel_date   = get_post_meta( $booking_id, 'wp_travel_arrival_date', true );
                    			$trip_id       = get_post_meta( $booking_id, 'wp_travel_post_id', true );
                    
                    			$title = get_the_title( $trip_id );
                    			$pax   = get_post_meta( $booking_id, 'wp_travel_pax', true );
                    
                    			// Billing fields.
                    			$billing_address = get_post_meta( $booking_id, 'wp_travel_address', true );
                    			$billing_city    = get_post_meta( $booking_id, 'billing_city', true );
                    			$billing_country = get_post_meta( $booking_id, 'wp_travel_country', true );
                    			$billing_postal  = get_post_meta( $booking_id, 'billing_postal', true );
                    
                    			// Travelers info.
                    			$fname       = get_post_meta( $booking_id, 'wp_travel_fname_traveller', true );
                    			$lname       = get_post_meta( $booking_id, 'wp_travel_lname_traveller', true );
                    			$status_list = wptravel_get_payment_status();
                    			if ( is_array( $details ) && count( $details ) > 0 ) 
                    			{
                    				?>
                    				<div class="my-order my-order-details">
                    					<div class="view-order">
                    						<div class="order-list">
                    							<div class="order-wrapper">
                    								
                    								<?php //wptravel_view_booking_details_table( $booking_id ); ?>
    												<?php
    
    											// Travelers info.
    											$fname            = get_post_meta( $booking_id, 'wp_travel_fname_traveller', true );
    											
    											$lname            = get_post_meta( $booking_id, 'wp_travel_lname_traveller', true );
    											$country          = get_post_meta( $booking_id, 'wp_travel_country_traveller', true );
    											$phone            = get_post_meta( $booking_id, 'wp_travel_phone_traveller', true );
    											$email            = get_post_meta( $booking_id, 'wp_travel_email_traveller', true );
    											$dob              = get_post_meta( $booking_id, 'wp_travel_date_of_birth_traveller', true );
    											$gender           = get_post_meta( $booking_id, 'wp_travel_gender_traveller', true );
    											$traveller_infos  = get_post_meta( $booking_id );
    											$order_items_data = get_post_meta( $booking_id, 'order_items_data', true );
    											if ( is_array( $fname ) && count( $fname ) > 0 ) :
    												foreach ( $fname as $cart_id => $first_names ) :
    													if ( is_array( $first_names ) && count( $first_names ) > 0 ) :
    														$trip_id = $order_items_data[ $cart_id ]['trip_id'];
    														
    														?>
    														
    																<?php foreach ( $first_names as $key => $first_name ) : ?>
    																	
    																		<!--<h6 class="my-order-single-title"><?php //printf( esc_html__( 'Traveler %d :', 'wp-travel' ), $key + 1 ); ?></h6>-->
    																		<?php
    																		$checkout_fields = wptravel_get_checkout_form_fields();
    																		$traveller_fields = isset( $checkout_fields['traveller_fields'] ) ? $checkout_fields['traveller_fields'] : array();
    																		$traveller_fields = wptravel_sort_form_fields( $traveller_fields );
    																		if ( ! empty( $traveller_fields ) ) {
    																			//echo "Test";exit;<j3
    																			//echo "<table><tr>";
    																			?><td><?php echo $rowgetlabels["ID"]; ?></td> 
    																			<td><?php echo $rowgetlabels["meta_value"]; ?></td>
																				<td><?php $payment_id   = wptravel_get_payment_id( $booking_id );
																				$wp_travel_payment_status = get_post_meta( $payment_id, 'wp_travel_payment_status', true );
																				echo $wp_travel_payment_status; ?></td>
    																			<?php
    																			foreach ( $traveller_fields as $field ) {
    																				if ( 'heading' === $field['type'] ) {
    																					// Do nothing.
    																				} elseif ( in_array( $field['type'], array( 'hidden' ) ) ) {
    																					// Do nothing.
    																				} else {
    																					$value = isset( $traveller_infos[ $field['name'] ] ) && isset( $traveller_infos[ $field['name'] ][0] ) ? maybe_unserialize( $traveller_infos[ $field['name'] ][0] ) : '';
    																					$value = is_array( $value ) ? array_values( $value ) : $value;
    																					$value = is_array( $value ) ? array_shift( $value ) : $value;
    																					$value = is_array( $value ) ? $value[ $key ] : $value;
    																					
    																					 echo "<td>";
    																					
    																					printf( '<span class="my-order-tail">%s</span></td>', $value ); // @phpcs:ignore
    																					
    																				}
    																				echo "";
    																			}
    																			echo "</tr>";
    																		}
    																		?>
    																	
    																<?php endforeach; ?>
    															
    														<?php
    													endif;
    												endforeach;
													else :
														?>
													<div class="my-order-single-traveller-info">
														<h3 class="my-order-single-title"><?php esc_html_e( sprintf( 'Travelers info [ %s ]', get_the_title( $trip_id ) ), 'wp-travel' ); ?></h3>
														<div class="row">
															<div class="col-md-6">
																<h3 class="my-order-single-title"><?php esc_html_e( sprintf( 'Lead Traveler :' ), 'wp-travel' ); ?></h3>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Name :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $fname . ' ' . $lname ); ?></span>
																</div>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Country :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $country ); ?></span>
																</div>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Phone No. :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $phone ); ?></span>
																</div>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Email :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $email ); ?></span>
																</div>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Date of Birth :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $dob ); ?></span>
																</div>
																<div class="my-order-single-field clearfix">
																	<span class="my-order-head"><?php esc_html_e( 'Gender :', 'wp-travel' ); ?></span>
																	<span class="my-order-tail"><?php echo esc_html( $gender ); ?></span>
																</div>
															</div>
														</div>
													</div>
														<?php
												endif;
												?>
                    							</div>
                    							<?php //echo WpTravel_Helpers_Payment::render_payment_details( $booking_id ); // @phpcs:ignore ?>
                    						</div>
                    					</div>
                    				</div>
                    				<?php
                    			}
												
                            ?></td>
    						</tr>
        				<?php
        				}
    				}
    				else
    				{
    					echo "No data";
    				}
    	            echo "</table></div>";
				}
						
			}
							
	    ?>
       </center>
</body>	   
<?php get_footer(); ?>