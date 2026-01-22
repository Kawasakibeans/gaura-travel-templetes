<?php
/**
 * Template Name: Quote
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
include("wp-config-custom.php");

function getTimeRange($time) {
                                    list($hour, $minute) = explode(':', $time);
                                    $hour = intval($hour);
                                    return $hour . '_' . ($hour + 1);
                                }
                                
if ( is_user_logged_in() ) 
{
    $current_user = wp_get_current_user();
    $current_userId = $current_user ->ID;
    global $wpdb;

    // add 'AND depart_date >= CURDATE()' if only need quote that has depart date later than today
    $where = 'where 1=1 ';
    
// filter by quote id
if (isset($_GET['quote_id']) && !empty($_GET['quote_id'])) {
    $quoteId = sanitize_text_field($_GET['quote_id']);
    $where .= " AND q.id = '$quoteId'";
}

// filter by gdeals or not
if (isset($_GET['gdeals']) && ($_GET['gdeals'] === '0' || $_GET['gdeals'] === '1')) {
    $gdeals = sanitize_text_field($_GET['gdeals']);
    $where .= " AND is_gdeals = '$gdeals'";
}

// filter by quote date (from)
if (isset($_GET['from']) && $_GET['from'] !== '') {
    $quotedFrom = sanitize_text_field($_GET['from']);
    $where .= " AND quoted_at >= '$quotedFrom 00:00:00'";
}

// filter by quote date (to)
if (isset($_GET['to']) && $_GET['to'] !== '') {
    $quotedTo = sanitize_text_field($_GET['to']);
    $where .= " AND quoted_at <= '$quotedTo 23:59:59'";
}

// filter by depart date
if (isset($_GET['depart_date']) && $_GET['depart_date'] !== '') {
    $departDate = sanitize_text_field($_GET['depart_date']);
    $where .= " AND q.depart_date = '$departDate'";
}

// filter by email
if (isset($_GET['email']) && $_GET['email'] !== '') {
    $email = sanitize_text_field($_GET['email']);
    $where .= " AND q.email LIKE '%$email%'";
}

// filter by phone number
if (isset($_GET['phone_num']) && $_GET['phone_num'] !== '') {
    $phoneNum = sanitize_text_field($_GET['phone_num']);
    $where .= " AND q.phone_num LIKE '%$phoneNum%'";
}

// filter by userId
if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {

    $current_userId = $_GET['user_id'];
    $where .= " AND user_id = $current_userId";
}

// filter by callId
if (isset($_GET['call_id']) && $_GET['call_id'] !== '') {
    $callId = $_GET['call_id'];
    $where .= " AND call_record_id = $callId";
}

    $quotes = $wpdb->get_results( 
        " 
         SELECT r.rec_duration as duration, r.rec_status as call_status, q.*, u.display_name 
         FROM wpk4_quote q 
         LEFT JOIN wpk4_users u ON q.user_id = u.ID
         LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON q.call_record_id = r.d_record_id
         $where
         ORDER BY q.quoted_at DESC limit 70
         " );
}

// Multicity quotes query (after single-route quotes query)
$multicity_where = 'WHERE 1=1 ';
// Example: filter by multicity quote id (add more filters as needed)
if (isset($_GET['multi_quote_id']) && !empty($_GET['multi_quote_id'])) {
    $multiQuoteId = sanitize_text_field($_GET['multi_quote_id']);
    $multicity_where .= " AND id = '$multiQuoteId'";
}
// You can add more filters here, similar to the single-route logic
$multicity_quotes = $wpdb->get_results("
    SELECT q.*, u.display_name 
    FROM wpk4_quote_multicity q
    LEFT JOIN wpk4_users u ON q.user_id = u.ID
    $multicity_where
    ORDER BY q.quoted_at DESC
    LIMIT 5
");
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- jQuery UI CSS -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">


<style>
        .quote-container {
            margin-top: 100px;
            max-width: 100%;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            font-family: 'Segoe UI', sans-serif;
        }

        .quote-container h2 {
            text-align: center;
            margin: 0px 20px;
        }

        .quote-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;    
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        .quote-table th,
        .quote-table td {
            padding: 10px 15px;
            border: 1px solid #ddd;
            text-align: center;
            word-wrap: break-word;
            overflow: visible;
            text-overflow: initial;
            white-space: normal;
        }
        
        .quote-table th {
            padding: 5px 5px;
            background-color: #0073aa;
            color: white;
            vertical-align: middle;  /* Centers the text vertically */
            line-height: 1.5;        /* Adjust line height if necessary for better alignment */
        }

        .no-quotes {
            text-align: center;
            padding: 30px;
            font-size: 18px;
            color: #666;
        }
        
        .quote-table tbody td {
            padding: 5px;
            border: 1px solid #ddd;
            word-wrap: break-word;
            overflow: visible;
            text-overflow: initial;
            white-space: normal;
        }
        
        .view-button {
            padding: 6px 12px;
            background-color: #FFBB00;
            border-radius: 5px;
            font-size: 14px
        }
        
        .product-info {
            font-size: 11px;
        }
        
        .product-info h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        
        .info-table th {
            /*background-color: #f5f5f5;*/
            font-weight: bold;
        }
        
        .loading, .no-data, .error {
            padding: 10px;
            color: #666;
            font-style: italic;
        }
        
        .chart-container {
            width: 100%;
            display: flex;           /* Align the canvas elements in a row */
            justify-content: space-between; /* Optional: adds space between them */
            gap: 20px;               /* Optional: adds gap between each canvas */
        }
        
        .chart-container canvas {
            width: 30%;
            flex: 1; /* Optional: makes each canvas take up equal space */
            height: 200px; /* Adjust the height as needed */
        }
        
        .quote-filters {
            padding: 0px 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .quote-filters select,
        .quote-filters input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            max-width: 200px;
        }

        .apply-button {
            padding: 8px 16px;
            background-color: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .apply-button:hover {
            background-color: #005a87;
        }
        
        .ui-datepicker {
            font-size: 14px;
            padding: 10px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        #quotedBy {
            position: relative;
        }
        
        .suggestions-list {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            top: 100%;
            padding: 5px;
            list-style-type: none;
            margin: 5px 0px;
            width: 200px;
            max-height: 150px;
            overflow-y: auto;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
    
        .suggestions-list.hidden {
            display: none;
        }
        
        .quoted-by-wrapper {
            width: 100%;
            max-width: 200px;
            position: relative;
        }
        
        .quoted-by-wrapper input {
            width: 100%;
        }
        
        .suggestions-list li {
            padding: 8px;
            cursor: pointer;
        }
        
        .suggestions-list li:hover {
            background-color: #f0f0f0;
        }
        
        .update-button {
            padding: 6px 12px;
            background-color: #FFBB00;
            border-radius: 5px;
            font-size: 14px
        }
        
        .view-subquotes-button {
            padding: 6px 12px;
            background-color: #FFBB00;
            border-radius: 5px;
            font-size: 14px
        }
        
        .subquote-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .subquote-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        
        .subquote-table th {
            /*background-color: #f5f5f5;*/
            font-weight: bold;
        }
        
        .pax-cell {
            position: relative;
            cursor: pointer;
        }
        
        .pax-cell::after {
            content: attr(data-tooltip);
            white-space: pre;
            position: absolute;
            background: #333;
            color: #fff;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            top: -5px;
            left: 0;
            white-space: pre-line;
            display: none;
            z-index: 10;
            width: max-content;
            max-width: 200px;
        }
        
        .pax-cell:hover::after {
            display: block;
        }
        
        .price-hover {
            position: relative;
            cursor: pointer;
        }
        
        .price-hover .hover-tooltip {
            display: none;
            position: absolute;
            top: -5px;
            left: 0;
            background: #333;
            border: 1px solid #ccc;
            padding: 6px 10px;
            font-size: 12px;
            color: #fff;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 4px;
            max-width: 200px;
        }
        
        .price-hover:hover .hover-tooltip {
            display: block;
        }
    </style>
    
    <div class="quote-container">
        <h2>Quotes</h2>
        <div class="quote-filters">
            <select id="filterGdeals">
                <option value="">All</option>
                <option value="1">Gdeals</option>
                <option value="0">FIT</option>
            </select>
            <input type="number" id="filterCallId" placeholder="Call ID">
            <input type="number" id="filterQuoteId" placeholder="Quote ID">
            <input type="text" id="filterDepartDate" placeholder="Deaprt Date">
            <input type="text" id="filterQuotedFrom" placeholder="Quoted Date From">
            <input type="text" id="filterQuotedTo" placeholder="Quoted Date To">
            <input type="email" id="filterEmail" placeholder="Email">
            <input type="tel" id="filterPhoneNum" placeholder="Phone Number">
    
            <div class="quoted-by-wrapper">
                <input type="text" id="quotedBy" name="quotedBy" data-user-id="" placeholder="Quoted By" autocomplete="off">
                <ul id="user-suggestions" class="suggestions-list hidden"></ul>
            </div>
            <button class='apply-button' onclick="applyFilters()">Apply</button>

         </div>
        <?php if ( ! empty( $quotes ) ) : ?>
        <div class='table-container'>
            <table class="quote-table" style="font-size:13px;">
                <colgroup>
                        <!--<col style="width: 80px;">     <!-- Gdeals? -->
                        <col style="width: 6%;">     <!-- Call ID -->
                        <col style="width: 6%;">     <!-- Quote ID -->
                        <col style="width: 10%;">    <!-- Route -->
                        <col style="width: 4%;">
                        <!--<col style="width: 80px;">    <!-- To -->
                        <col style="width: 8%;">    <!-- Depart Date -->
                        <!--<col style="width: 120px;">    <!-- Return Date -->
                        <col style="width: 7%;">     <!-- Price -->
                        <col style="width: 7%;"> 
                        <col style="width: 7%;">    <!-- Quoted At -->
                        <col style="width: 7%;">    <!-- Quoted By -->
                        <col style="width: 7%;">    <!-- Name -->
                        <col style="width: 4%;">    <!-- Total Pax -->
                        <col style="width: 10%;">    <!-- Email -->
                        <col style="width: 7%;">    <!-- Phone Number -->
                       <!-- <col style="width: 7%;">    <!-- Product Code 1 -->
                        <!--<col style="width: 150px;">    <!-- Product Code 2 -->
                       <!-- <col style="width: 4%;">     URL -->
                        <col style="width: 7%;">    <!-- Status -->
                        <col style="width: 7%;">    <!-- Call Status -->
                        <col style="width: 7%;">    <!-- Call Duration -->
                           <!-- G360 Quotes -->
                        <col style="width: 7%;">
                    </colgroup>
                <thead>
                    <tr>
                        <!--<th>Gdeals?</th>-->
                        <th>Call ID</th>
                        <th>Quote ID</th>
                        <th>Route</th>
                        <th>Airline</th>
                        <!--<th>To</th>-->
                        <th>Depart Date</th>
                        <!--<th>Return Date</th>-->
                        <th>Price</th>
                        <th>G360 Quotes</th>
                        <th>Quoted At</th>
                        <th>Quoted By</th>
                        <th>PaxName</th>
                        <th>Total</br>Pax</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <!--<th>Product Code</th>
                        <!--<th>Product Code 2</th>
                        <th>URL</th>-->
                        <th>Status</th>
                        <th>Call Status</th>
                        <th>Call Duration</th>
                        
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $quotes as $quote ) : ?>
                    <?php
                    $is_multicity = isset($quote->is_multicity) && $quote->is_multicity == 1;
                    $multicity = null;
                    if ($is_multicity) {
                        $multicity = $wpdb->get_row($wpdb->prepare("SELECT * FROM wpk4_quote_multicity WHERE id = %d", $quote->id));
                    }
                    ?>
                    <?php
                    $highlighted_row = '';
                     $subquote_count = $wpdb->get_var($wpdb->prepare("
                                    SELECT COUNT(*) 
                                    FROM wpk4_quote_G360
                                    WHERE original_quote_id = %d
                                ", $quote->id));
                                if($subquote_count > 0)
                                {
                                    $highlighted_row = ' style="background-color:#f5d0ce;"';
                                }
                    ?>
                        <tr <?php echo $highlighted_row; ?>>
                            <td><?php echo esc_html( $quote->call_record_id ); ?></td>
                            <td><?php echo esc_html( $quote->id ); ?> <br>
                            <?php 
                            if ($quote->is_gdeals == 1) 
                            {
                                    echo "<center><img src='https://gauratravel.com.au/wp-content/uploads/2024/07/WhyGT_Gdeals.png' width='40px;'/></center>" ;     
                                    
                            }
                            if($is_multicity)
                            {
                                echo 'Multicity';
                            }
                                    else{
                                      
                                    }
                                    ?>
                            
                            </td>
                            <td> <?php if ($is_multicity && $multicity) : ?>
                                <div style="">
                                    <?php for ($i = 1; $i <= 4; $i++) :
                                        $apt_from = $multicity->{'depart_apt'.$i};
                                        $apt_to = $multicity->{'dest_apt'.$i};
                                        $date = $multicity->{'depart_date'.$i};
                                        if ($apt_from && $apt_to) : ?>
                                                <?php echo $apt_from . '-' . $apt_to . '</br>'; ?>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                            <?php else : ?>
                                <?php echo esc_html($quote->depart_apt . '-' . $quote->dest_apt); ?>
                            <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_multicity) echo esc_html($quote->airline_code); ?>
                            </td>
                            <!--<td></td>-->
                            <td>    <?php if ($is_multicity && $multicity) : ?>
                                <div style="">
                                    <?php for ($i = 1; $i <= 4; $i++) :
                                        $apt_from = $multicity->{'depart_apt'.$i};
                                        $apt_to = $multicity->{'dest_apt'.$i};
                                        $date = $multicity->{'depart_date'.$i};
                                        if ($apt_from && $apt_to) : ?>
                                            <div style="min-width:80px;">
                                                <?php if ($date) { $d = new DateTime($date); echo "" . $d->format('d-m-Y') . "<br>"; } ?>
                                            </div>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                            <?php else : ?>
                                <?php $departDate = new DateTime($quote->depart_date);
                            echo $departDate->format('d-m-Y');  ?>
                            <?php endif; ?></td>

                            <td class="price-hover">
                            $<?php
                                if ($is_multicity && $multicity) {
                                    echo esc_html(number_format($multicity->total_price, 0));
                                } else {
                                    echo esc_html(number_format($quote->total_price, 0));
                                }
                            ?>
                            <div class="hover-tooltip price-breakdown-tooltip">
                                <?php
                                    if ($is_multicity && $multicity) {
                                        $adultTotal = $multicity->adult_price * $multicity->adult_count;
                                        $childTotal = $multicity->child_price * $multicity->child_count;
                                        $infantTotal = $multicity->infant_price * $multicity->infant_count;
                                        $adult_price = $multicity->adult_price;
                                        $child_price = $multicity->child_price;
                                        $infant_price = $multicity->infant_price;
                                        $adult_count = $multicity->adult_count;
                                        $child_count = $multicity->child_count;
                                        $infant_count = $multicity->infant_count;
                                    } else {
                                        $adultTotal = $quote->adult_price * $quote->adult_count;
                                        $childTotal = $quote->child_price * $quote->child_count;
                                        $infantTotal = $quote->infant_price * $quote->infant_count;
                                        $adult_price = $quote->adult_price;
                                        $child_price = $quote->child_price;
                                        $infant_price = $quote->infant_price;
                                        $adult_count = $quote->adult_count;
                                        $child_count = $quote->child_count;
                                        $infant_count = $quote->infant_count;
                                    }
                                ?>
                                <strong>Breakdown:</strong><br>
                                Adult: $<?php echo $adult_price; ?> × <?php echo $adult_count; ?> = $<?php echo $adultTotal; ?><br>
                                Child: $<?php echo $child_price; ?> × <?php echo $child_count; ?> = $<?php echo $childTotal; ?><br>
                                Infant: $<?php echo $infant_price; ?> × <?php echo $infant_count; ?> = $<?php echo $infantTotal; ?>
                            </div>
                        </td>
                            
                             <td>
                                <?php if (!$is_multicity) :
                                $subquote_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wpk4_quote_G360 WHERE original_quote_id = %d", $quote->id));
                                if ($subquote_count > 0) : ?>
                                    <button class='view-subquotes-button' data-quote-id='<?php echo esc_attr($quote->id); ?>'>View</button>
                                <?php endif;
                            endif; ?>
                            </td>
                            <td> 
                            <?php if (!$is_multicity) {
                                $datetime = new DateTime($quote->quoted_at);
                                echo $datetime->format('d-m H:i');
                            }else{
                                $datetime = new DateTime($multicity->quoted_at);
                                echo $datetime->format('d-m H:i');
                            } 
                            
                            ?>
                            </td>
                            <td><?php if (!$is_multicity) {
                                echo esc_html($quote->display_name);
                            }else{
                                
                                $user = $wpdb->get_row($wpdb->prepare("SELECT display_name FROM wpk4_users WHERE ID = %d", $multicity->user_id));

                                echo esc_html($user->display_name);
                            } 
                            ?></td>
                            <td><?php 
                            if(!$is_multicity){
                               echo esc_html($quote->name);  
                            }else{
                               echo esc_html($multicity->name); 
                            }
                         ?></td>
                            <td class="pax-cell" data-tooltip="Adults: <?php echo $is_multicity && $multicity ? ($multicity->adult_count ?? 0) : ($quote->adult_count ?? 0); ?>&#10;Children: <?php echo $is_multicity && $multicity ? ($multicity->child_count ?? 0) : ($quote->child_count ?? 0); ?>&#10;Infants: <?php echo $is_multicity && $multicity ? ($multicity->infant_count ?? 0) : ($quote->infant_count ?? 0); ?>">
                            <?php echo esc_html($is_multicity && $multicity ? $multicity->total_pax : $quote->total_pax); ?>
                        </td>
                        <td><?php echo esc_html($is_multicity && $multicity ? $multicity->email : $quote->email); ?></td>
                        <td><?php echo esc_html($is_multicity && $multicity ? $multicity->phone_num : $quote->phone_num); ?></td>
                        <td>
                            <?php
                                $status = $is_multicity && $multicity ? $multicity->status : $quote->status;
                                if ($status && $status !== '0') {
                                    echo '<p>Booked</p><p>' . esc_html($status) . '</p>';
                                } else {
                                    echo '<p>Quoted</p>';
                                }
                            ?>
                        </td>
                            <!--<td>
                                <?php if ($quote->to_product_id && $quote->to_product_id !== '0' ): ?>
                                    <? echo $quote->to_product_id;?> <br>
                                    <button class='view-button' onclick="toggleInfo('<?php echo esc_html( $quote->id ); ?>','<? echo $quote->depart_date;?>', '<? echo $quote->to_product_id;?>', this)">View</button>
                                <?php else: ?>
                                    
                                <?php endif; ?>
                                <br>
                                <br>
                                <?php if ($quote->return_product_id && $quote->return_product_id !== '0' ): ?>
                                    <? echo $quote->return_product_id;?> <br>
                                    <button class='view-button' onclick="toggleInfo('<?php echo esc_html( $quote->id ); ?>', '<? echo $quote->return_date;?>','<? echo $quote->return_product_id;?>', this)">View</button>
                                <?php else: ?>
                                    
                                <?php endif; ?>
                            </td>
                            <td>-->
                                
                            <!--</td>
                            <td>
                                <?php if ($quote->url && $quote->url !== '0'): ?>
                                    <a target="_blank" href= 'https://gauratravel.com.au/flights/<?php echo $quote->url; ?>'>Link</a>
                                <?php else: ?>
                                    <p>  </p>
                                <?php endif; ?>
                            </td>-->
        
                            <td><?php if (!$is_multicity) echo esc_html($quote->call_status); ?></td>
                        <td><?php if (!$is_multicity && isset($quote->duration) && $quote->duration > 0) echo gmdate("H:i:s", $quote->duration); ?></td>
                           
                            <td>
                                <?php if (!$is_multicity) 
                                {
                                $isGdeal = $quote->is_gdeals;
                                $to_product_id = $quote->to_product_id;
                                $return_product_id = $quote->return_product_id;
                                $airline_code = $quote->airline_code;
                                $isGdeal = $quote->is_gdeals;
                                
                                $deptime = '0_24';
                                $rettime = '0_24';
                                if($quote->depart_time != '')
                                {
                                    $depart_time = $quote->depart_time;
                                    $deptime = getTimeRange($depart_time); 
                                }
                                if($quote->return_time != '')
                                {
                                    $return_time = $quote->return_time;
                                    $rettime = getTimeRange($return_time);
                                }
                                
                                
                                ?>
                                <button class="view-button send-email-button" 
                                    data-email="<?php echo esc_attr($quote->email); ?>" 
                                    
                                    data-route="<?php echo esc_attr($quote->depart_apt); ?> - <?php echo esc_attr($quote->dest_apt); ?>" 
                                    data-airline="<?php echo esc_attr($quote->airline_code); ?>" 
                                    data-traveldate="<?php echo esc_attr($quote->depart_date); ?>" 
                                    data-airline="<?php echo esc_attr($quote->airline_code); ?>" 
                                    data-class="Economy" 
                                    data-paxname="<?php echo esc_attr($quote->name); ?>" 
                                    data-agent="<?php echo $current_userId; ?>" 
                                    data-agentname="<?php echo esc_attr($quote->display_name); ?>" 
                                    data-price="<?php echo esc_attr($quote->total_price); ?>" 
                                    data-adtpax="<?php echo esc_attr($quote->adult_count); ?>" 
                                    data-chdpax="<?php echo esc_attr($quote->child_count); ?>" 
                                    data-infpax="<?php echo esc_attr($quote->infant_count); ?>" 
                                    
                                    
                                    
                                    data-url="https://gauratravel.com.au/flight-quote/<?php echo esc_attr($quote->url); ?>&gdeal=<?php echo $isGdeal; ?>&product=<?php echo $to_product_id; ?>&retproduct=<?php echo $return_product_id; ?>&airlines=<?php echo $airline_code; ?>&deptime=<?php echo $deptime; ?>&rettime=<?php echo $rettime; ?>&class=E&sent_on=<?php echo date("dmy"); ?>&sent_by=<?php echo $currnt_userlogn; ?>">
                                    Send Email
                                </button>
                                </br></br>
                                
                                <a href='https://gauratravel.com.au/flight-quote/<?php echo esc_attr($quote->url); ?>&gdeal=<?php echo $isGdeal; ?>&product=<?php echo $to_product_id; ?>&retproduct=<?php echo $return_product_id; ?>&airlines=<?php echo $airline_code; ?>&deptime=<?php echo $deptime; ?>&rettime=<?php echo $rettime; ?>&class=E&sent_on=<?php echo date("dmy"); ?>&sent_by=<?php echo $currnt_userlogn; ?>'>Visit Quoted Link</a>
                                <?php
                                }
                                else
                                {
                                    //if($currnt_userlogn == 'sriharshans')
                                    {
                                    $isGdeal = $multicity->is_gdeals;
                                    $to_product_id = $multicity->to_product_id;
                                    $return_product_id = $multicity->return_product_id;
                                    $airline_code = $multicity->airline_code;
                                    $isGdeal = $multicity->is_gdeals;
                                    
                                    $deptime = '0_24';
                                    $rettime = '0_24';
                                    if($multicity->depart_time != '')
                                    {
                                        $depart_time = $multicity->depart_time;
                                        $deptime = getTimeRange($depart_time); 
                                    }
                                    if($multicity->return_time != '')
                                    {
                                        $return_time = $multicity->return_time;
                                        $rettime = getTimeRange($return_time);
                                    }
                                    
                                    
                                    ?>
                                    <button class="view-button send-email-button-multicity" 
                                        data-email="<?php echo esc_attr($multicity->email); ?>" 
                                        
                                        data-route="<?php echo esc_attr($multicity->depart_apt); ?> - <?php echo esc_attr($multicity->dest_apt); ?>" 
                                        data-airline="<?php echo esc_attr($multicity->airline_code); ?>" 
                                        data-traveldate="<?php echo esc_attr($multicity->depart_date); ?>" 
                                        data-airline="<?php echo esc_attr($multicity->airline_code); ?>" 
                                        data-class="Economy" 
                                        data-paxname="<?php echo esc_attr($multicity->name); ?>" 
                                        data-agent="<?php echo $current_userId; ?>" 
                                        data-agentname="<?php echo esc_attr($multicity->display_name); ?>" 
                                        data-price="<?php echo esc_attr($multicity->total_price); ?>" 
                                        data-adtpax="<?php echo esc_attr($multicity->adult_count); ?>" 
                                        data-chdpax="<?php echo esc_attr($multicity->child_count); ?>" 
                                        data-infpax="<?php echo esc_attr($multicity->infant_count); ?>" 
                                        
                                        data-url="https://gauratravel.com.au/flight-quote-muliticity/<?php echo esc_attr($multicity->url); ?>&gdeal=<?php echo $isGdeal; ?>&product=<?php echo $to_product_id; ?>&retproduct=<?php echo $return_product_id; ?>&airlines=<?php echo $airline_code; ?>&deptime=<?php echo $deptime; ?>&rettime=<?php echo $rettime; ?>&class=E&sent_on=<?php echo date("dmy"); ?>&sent_by=<?php echo $currnt_userlogn; ?>
                                        <?php if($multicity->{'depart_apt1'} != '') { echo '&depapt1='.$multicity->{'depart_apt1'}; } ?>
                                        <?php if($multicity->{'depart_apt2'} != '') { echo '&depapt2='.$multicity->{'depart_apt2'}; } ?>
                                        <?php if($multicity->{'depart_apt3'} != '') { echo '&depapt3='.$multicity->{'depart_apt3'}; } ?>
                                        <?php if($multicity->{'depart_apt4'} != '') { echo '&depapt4='.$multicity->{'depart_apt4'}; } ?>
                                        <?php if($multicity->{'dest_apt1'} != '') { echo '&dstapt1='.$multicity->{'dest_apt1'}; } ?>
                                        <?php if($multicity->{'dest_apt2'} != '') { echo '&dstapt2='.$multicity->{'dest_apt2'}; } ?>
                                        <?php if($multicity->{'dest_apt3'} != '') { echo '&dstapt3='.$multicity->{'dest_apt3'}; } ?>
                                        <?php if($multicity->{'dest_apt4'} != '') { echo '&dstapt4='.$multicity->{'dest_apt4'}; } ?>
                                        <?php if($multicity->{'depart_date1'} != '') { echo '&depdate1='.$multicity->{'depart_date1'}; } ?>
                                        <?php if($multicity->{'depart_date2'} != '') { echo '&depdate2='.$multicity->{'depart_date2'}; } ?>
                                        <?php if($multicity->{'depart_date3'} != '') { echo '&depdate3='.$multicity->{'depart_date3'}; } ?>
                                        <?php if($multicity->{'depart_date4'} != '') { echo '&depdate4='.$multicity->{'depart_date4'}; } ?>
                                        ">
                                        Send Email
                                    </button>
                                    </br></br>
                                    <?php
                                    }
                                    ?>
                                   <a href='https://gauratravel.com.au/flights-multicity-internal/<?php echo esc_attr($multicity->url); ?>'>Visit Quoted Link</a>
                                   
                                   
                                   <?php
                                }
                            ?>
                            </td>
                        </tr>
                        <?php if ($quote->to_product_id && $quote->to_product_id !== '0' ): ?>
                        <tr data-quote-id="<?php echo esc_html( $quote->id ); ?>" data-id="<? echo $quote->to_product_id;?>" style='display:none'>
                            <td colspan="17" style="background: #f1f1f1;">Loading...</td>
                        </tr>
                        <?php endif;?>
                        <?php if ($quote->return_product_id && $quote->return_product_id !== '0' ): ?>
                        <tr data-quote-id="<?php echo esc_html( $quote->id ); ?>" data-id="<? echo $quote->return_product_id;?>" style='display:none'>
                            <td colspan="17" style="background: #f1f1f1;">Loading...</td>
                        </tr>
                        <?php endif;?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
            <div class="no-quotes">You have no quotes yet.</div>
        <?php endif; ?>
    </div>
    
    <script>
   

    $(document).ready(function() {
        $("table").on("click", ".send-email-button", function() {
            let email = $(this).data("email");
            let url = $(this).data("url");
            let route = $(this).data("route");
            let traveldate = $(this).data("traveldate");
            let airline = $(this).data("airline");
            let flightClass = $(this).data("class");
            let price = $(this).data("price");
            
            let adtpax = $(this).data("adtpax");
            let chdpax = $(this).data("chdpax");
            let infpax = $(this).data("infpax");
            
            let paxname = $(this).data("paxname");
            let agentname = $(this).data("agentname");
            
    
            if (!email || !url) {
                alert("Email or URL missing!");
                return;
            }
    
            // Build confirmation message
            let confirmMessage = `Do you want to continue sending the following information to the customer?

Please ensure you have checked the quoted URL before sending it to the passenger.
                Email: ${email}
                Route: ${route}
                Travel Date: ${traveldate}
                Airline: ${airline}
                Class: ${flightClass}
                Price: ${price}
                Pax count: Adult - ${adtpax}, Child - ${chdpax}, Infant - ${infpax}, 
            `;
    
            if (confirm(confirmMessage)) {
                // Proceed with AJAX call only if user clicks Yes
                $.ajax({
                    url: "/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php",
                    type: "POST",
                    data: {
                        action: "send_quote_email",
                        email: email,
                        paxname: paxname,
                        agentname: agentname,
                        url: url
                    },
                    success: function(response) {
                        alert("Email has been sent to customer.");
                    },
                    error: function(xhr, status, error) {
                        //alert("Error sending email. Please check console for details.");
                        console.error(xhr.responseText);
                    }
                });
            } else {
                // Cancelled by user
                console.log("Email send cancelled by user.");
            }
        });
        
        $("table").on("click", ".send-email-button-multicity", function() {
            let email = $(this).data("email");
            let url = $(this).data("url");
            let route = $(this).data("route");
            let traveldate = $(this).data("traveldate");
            let airline = $(this).data("airline");
            let flightClass = $(this).data("class");
            let price = $(this).data("price");
            
            let adtpax = $(this).data("adtpax");
            let chdpax = $(this).data("chdpax");
            let infpax = $(this).data("infpax");
            
            let paxname = $(this).data("paxname");
            let agentname = $(this).data("agentname");
            
    
            if (!email || !url) {
                alert("Email or URL missing!");
                return;
            }
    
            // Build confirmation message
            let confirmMessage = `Do you want to continue sending the following information to the customer?

Please ensure you have checked the quoted URL before sending it to the passenger.
                Email: ${email}
                Route: ${route}
                Travel Date: ${traveldate}
                Airline: ${airline}
                Class: ${flightClass}
                Price: ${price}
                Pax count: Adult - ${adtpax}, Child - ${chdpax}, Infant - ${infpax}, 
            `;
    
            if (confirm(confirmMessage)) {
                // Proceed with AJAX call only if user clicks Yes
                $.ajax({
                    url: "/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php",
                    type: "POST",
                    data: {
                        action: "send_quote_email_multicity",
                        email: email,
                        paxname: paxname,
                        agentname: agentname,
                        url: url
                    },
                    success: function(response) {
                        alert("Email has been sent to customer.");
                    },
                    error: function(xhr, status, error) {
                        //alert("Error sending email. Please check console for details.");
                        console.error(xhr.responseText);
                    }
                });
            } else {
                // Cancelled by user
                console.log("Email send cancelled by user.");
            }
        });
    });



        // To get more specific info about a product
        function toggleInfo(quoteId, departDate, toProductId, button){
            const row = document.querySelector(`tr[data-id='${toProductId}'][data-quote-id='${quoteId}']`);
            console.log(toProductId);
            if(!row) return;
            
            // toggle visibility
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                button.textContent = 'Hide';
                const url = `/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php?product_id=${toProductId}&travel_date=${departDate}`;
                fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Fetched product availability:', data);
                    // add a sub-table for fetched details
                    if (data.itinerary && data.itinerary.length > 0) {
                        let html = `
                        <div class="product-info">
                            <table class="info-table">
                                <thead>
                                    <tr>
                                        <th>Trip Code</th>
                                        <th>Product Title</th>
                                        <th>Availability</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;                        // html += `<ul>`;
                        data.itinerary.forEach(item => {
                           html += `
                            <tr>
                                <td>${item.trip_code}</td>
                                <td>${item.product_title}</td>
                                <td>${item.availability}</td>
                                <td>$${parseFloat(item.price).toFixed(2)}</td>
                            </tr>
                        `;
                        });

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                        row.cells[0].innerHTML = html;
                    } else {
                        row.cells[0].innerHTML = "<em>No availability data found.</em>";
                }
                })
                .catch(error => {
                    console.error('Error fetching availability:', error);
                });
                
            }
            else {
                row.style.display = 'none';
                button.textContent = 'View';
            }
            
        }
        
        // apply filters for the quote
        function applyFilters() {
            const gdeals = document.getElementById('filterGdeals').value;
            const quoteId = document.getElementById('filterQuoteId').value;
            const departDate = document.getElementById('filterDepartDate').value;
            const from = document.getElementById('filterQuotedFrom').value;
            const to = document.getElementById('filterQuotedTo').value;
            const quotedBy = document.getElementById('quotedBy');
            const userId = quotedBy.getAttribute("data-user-id");
            const email = document.getElementById('filterEmail').value;
            const phoneNum = document.getElementById('filterPhoneNum').value;
            const name = quotedBy.value;
            const callId = document.getElementById('filterCallId').value
            
            // construct the url
            let url = new URL(window.location.href);
            url.searchParams.set('gdeals', gdeals);
            url.searchParams.set('quote_id', quoteId);
            url.searchParams.set('depart_date', departDate);
            url.searchParams.set('from', from);
            url.searchParams.set('to', to);
            url.searchParams.set('user_id', userId);
            url.searchParams.set('user_name', name);
            url.searchParams.set('email', email);
            url.searchParams.set('phone_num', phoneNum);
            url.searchParams.set('call_id', callId);
            
            window.location.href = url.toString();
        }
        
        // handler for sending price update email
        function sendPriceUpdateEmail(quoteId, price, button){
            button.disabled = true;
            button.textContent = 'Sending...';
            fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    quote_id: quoteId,
                    price: price,
                    email: '1'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    button.textContent = 'Sent ✅';
                } else {
                    button.textContent = 'Failed ❌';
                }
            })
            .catch(error => {
                console.error('Email sending failed:', error);
                alert('Failed to send email.');
                button.textContent = 'Failed ❌';
            })
            .finally(() => {
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Send Email';
                }, 2000); // Revert after delay
            });
    
        }
        
        // handler for sending price update sms
        function sendPriceUpdateSMS(quoteId, price, button){
            button.disabled = true;
            button.textContent = 'Sending...';
            fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    quote_id: quoteId,
                    price: price,
                    sms: '1'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    button.textContent = 'Sent ✅';
                } else {
                    button.textContent = 'Failed ❌';
                }
            })
            .catch(error => {
                console.error('Email sending failed:', error);
                alert('Failed to send sms.');
                button.textContent = 'Failed ❌';

            })
            .finally(() => {
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Send SMS';
                }, 2000); // Revert after delay
            });
    
        }
        
        // Keep the filter persistent even the page reloads
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const gdeals = urlParams.get('gdeals');
            const quoteId = urlParams.get('quote_id');
            const from = urlParams.get('from');
            const to = urlParams.get('to');
            const userId = urlParams.get("user_id");
            const name = urlParams.get("user_name");
            const departDate = urlParams.get("depart_date");
            const email = urlParams.get("email");
            const phoneNum = urlParams.get("phone_num");
            const callId = urlParams.get("call_id");
            
            if (gdeals !== null) {
                document.getElementById('filterGdeals').value = gdeals;
            }
            
            if (quoteId !== null) {
                document.getElementById('filterQuoteId').value = quoteId;
            }
            
            if (from !== null) {
                document.getElementById('filterQuotedFrom').value = from;
            }
            
            if (to !== null) {
                document.getElementById('filterQuotedTo').value = to;
            }
            
            // set data-user-id
            if (userId !== null) {
                const quotedBy = document.getElementById('quotedBy');
                quotedBy.setAttribute("data-user-id", userId);
            }
            
            // set the value (name)
            if (name !== null) {
                const quotedBy = document.getElementById('quotedBy');
                quotedBy.value = name;
            }
            
            if (departDate !== null) {
                document.getElementById('filterDepartDate').value = departDate;
            }
            
            if (email !== null) {
                document.getElementById('filterEmail').value = email;
            }
            
            if (phoneNum !== null) {
                document.getElementById('filterPhoneNum').value = phoneNum;
            }
            
            if (callId !== null) {
                document.getElementById('filterCallId').value = callId;
            }
        });
        
        // initialise the datepicker
        document.addEventListener("DOMContentLoaded", function() {
            $("#filterQuotedFrom").datepicker({
                dateFormat: "yy-mm-dd"
            });
            $("#filterQuotedTo").datepicker({
                dateFormat: "yy-mm-dd"
            });
            $("#filterDepartDate").datepicker({
                dateFormat: "yy-mm-dd"
            });
        });
        
        // set up dynamic user list
        document.addEventListener("DOMContentLoaded", function () {
            let quotedByInput = document.getElementById("quotedBy");
            let suggestionsBox = document.getElementById("user-suggestions");
            
            function debounce(func, delay) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), delay);
                };
        }
        
            quotedByInput.addEventListener("input", function () {
                debouncedFetchUsers(this.value.trim());
    
            });
            
            function fetchUsers(query) {
        
                if (query.length < 2) {
                    suggestionsBox.classList.add("hidden");
                    return;
                }
        
                fetch(`/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php?query=${query}`)
                    .then(response => response.json())
                    .then(users => {
                        suggestionsBox.innerHTML = "";
                        // prompt 'no results' if no any users are found in database
                        if (users.length === 0) {
                            let li = document.createElement("li");
                            li.textContent = `No Results...` ;
                            suggestionsBox.appendChild(li);
                            suggestionsBox.classList.remove("hidden");
                            return;
                        }

                        // iterate through users and create a dropdown list
                        users.forEach(user => {
                            let li = document.createElement("li");
                            li.textContent = `${user.display_name} ` ;
                            li.onclick = function () {
                                quotedByInput.value = user.display_name;
                                quotedByInput.setAttribute("data-user-id", user.id);
                                suggestionsBox.classList.add("hidden");
                            };
                            suggestionsBox.appendChild(li);
                        });
        
                        suggestionsBox.classList.remove("hidden");
                    })
                    .catch(error => console.error("Error fetching users:", error));
            }
            
            let debouncedFetchUsers = debounce(fetchUsers, 300);
    
            quotedByInput.addEventListener("input", function () {
                debouncedFetchUsers(this.value.trim());
            });
    
        
            // Hide dropdown when clicking outside
            document.addEventListener("click", function (e) {
                if (!quotedByInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                    suggestionsBox.classList.add("hidden");
                }
            });
    });

        // reset the user id if there is any change
        document.getElementById('quotedBy').addEventListener('input', function() {
            this.setAttribute('data-user-id', '');
        });

        // add event listener for every update button        
        document.querySelectorAll(".update-button").forEach(button => {
            button.addEventListener("click", function () {
                const phone = this.getAttribute("data-phone");
        
                fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        phone: phone
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if(data.rows_updated > 0){
                            alert('Status updated successfully');
                            // Get the parent <td> of the clicked button
                            const td = button.closest('td');
                            // Replace the contents with "Converted"
                            td.innerHTML = '<p>Converted</p>';
                        }else{
                            alert('No Bookings found');
                        }
                    } else {
                        alert("Failed to update status.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred.");
                });
            });
});
        
        // add event listener for view subquotes buttons
        document.addEventListener("DOMContentLoaded", function () {
            const buttons = document.querySelectorAll(".view-subquotes-button");
            
            buttons.forEach(button => {
                button.addEventListener("click", function () {
                    const quoteId = this.dataset.quoteId;
                    if (quoteId) {
                        // Redirect to the subquote details page with the quote ID
                        const url = `/quote-details?quote_id=${quoteId}`;
                        window.open(url, '_blank');
                    }
                });
            });
        });
    </script>

<?php get_footer(); ?>