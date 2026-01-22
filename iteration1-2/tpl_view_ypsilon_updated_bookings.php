<?php
/**
 * Template Name: View Ypsilon Updated Bookings
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();?>
<html> 
<head>
</head>
<body>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:100px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
include("wp-config-custom.php");

        ?>
        <div class="search_bookings">
				<h5>View Booking</h5>
        
                <table class="table table-striped" style="width:100%; margin:auto;font-size:14px; ">
                    					<thead>
                        					<tr>	
                            					<th>Order ID</th>
                            					<th>Updated on</th>
                            					<th>View</th>
                        				    </tr>
                    				    </thead>
            				            <tbody>
				<?php
				
    			$query = "SELECT * FROM wpk4_backend_travel_bookings where is_updated = 'yes' order by order_id desc LIMIT 100";
    				
    				$result = mysqli_query($mysqli, $query) or die(mysqli_error($mysqli));
    				$row_count = mysqli_num_rows($result);
                    if($row_count > 0)
            		{
            		    while($row = mysqli_fetch_assoc($result))
            		    {
                		    $order_id = $row['order_id'];
                		     
                    		$trip_code = $row['trip_code'];

                    		$query_selection_meta = "SELECT * FROM wpk4_backend_history_of_meta_changes WHERE type_id ='$order_id' order by auto_id desc";
                            $result_selection_meta = mysqli_query($mysqli, $query_selection_meta);
                            $row_selection_meta_existingcount = mysqli_num_rows($result_selection_meta);
                            if($row_selection_meta_existingcount > 0)
                            {
                                $row_selection_meta = mysqli_fetch_assoc($result_selection_meta);
                                $gds_data_updated_on = date('d/m/Y H:i:s', strtotime($row_selection_meta['updated_on']));
                                $is_gds_updated_got_updated = '<p class="blink_me">Updated on: '.$gds_data_updated_on.'</br></p>';
                            }
                            else
                            {
                                $is_gds_updated_got_updated = '';
                            }
            				?>
            				<tr>
            				    <td><?php echo $order_id; ?></td>
            				    <td><?php echo $is_gds_updated_got_updated; ?></td>
            				    <td><a href="https://gauratravel.com.au/manage-wp-orders/?option=search&type=reference&id=<?php echo $order_id; ?>">View</a></td>
            				</tr>
            				
            			    <?php
            			}
				    }
            		else
                	{
                			echo 'No results found!!.';
                	}
            	
            	?>
            	</tbody>
            	</table>
				</div><!-- END OF TAB CONTENT -->
				
	</div>

</body>	
<?php get_footer(); ?>      
    