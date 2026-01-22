<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
global $wpdb;
echo 'updated';

// Get the quote within last 7 days
$results = $wpdb->get_results("
    SELECT * FROM wpk4_quote
    WHERE quoted_at >= NOW() - INTERVAL 2 DAY
    AND depart_date >= CURDATE()
    AND status = 0
    ORDER BY quoted_at DESC
");

// The URL of PHP handlers
$handlerUrl = 'https://ai.gauratravel.com.au/app/v3/phpend/fetch_flight_data_round_trip_v1.php'; 
$postUrl = 'https://ai.gauratravel.com.au/app/v3/apis/api_ypsilon_flights_roundtrip_v1.php';
$getBaseUrl = 'https://ai.gauratravel.com.au/app/v3/db/db_flights_query_roundtrip_v2.php';

// If there are quotes found
if (!empty($results)){
    foreach ($results as $row) {
        // initialise the trip type
        $trip_type = "oneway";
        $dmyDepartDate = date('d-m-Y', strtotime($row->depart_date));
        $dmyReturnDate = "";
        
        // if the return date is present set trip type to round trip
        if ($row->return_date !== '0000-00-00' && !empty($row->return_date)){
            $dmyReturnDate = date('d-m-Y', strtotime($row->return_date));
            $trip_type = "roundtrip";
        }
        
        // params for gdeals
        $params = [
            'type'     => $trip_type,
            'class'    => 'E',
            'adt'      => $row->adult_count,
            'chd'      => $row->child_count,
            'inf'      => $row->infant_count,
            'depdate1' => $dmyDepartDate,
            'retdate1' => $dmyReturnDate,
            'depapt1'  => $row->depart_apt,
            'dstapt1'  => $row->dest_apt
        ];
        
        // Build final URL 
        $final_get_url = $getBaseUrl . '?' . http_build_query($params);
        
        $return_date = '';
        if ($row->return_date !== '0000-00-00' && !empty($row->return_date)){
            $return_date = $row->return_date;
        }
        
        // Prepare data to send
        $postData = [
            'tripType'    => $trip_type,
            'depDate'     => $row->depart_date,
            'depApt'      => $row->depart_apt,
            'dstDate'     => $return_date,
            'dstApt'      => $row->dest_apt,
            'travelClass' => 'E',
            'limit'       => '1',
            'offset'      => '0',
            'getUrl'      => $final_get_url,
            'postUrl'     => $postUrl
        ];
        
        $ch = curl_init($handlerUrl);

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        echo print_r($response);
        // Decode the repsonse to json format
        $responseData = json_decode($response, true);
        
        // Get the first flight
        $flight =  $responseData['data']['flights'][0] ?? '';
        
        // lowest price
        $newPrice = $flight["base"]["basePrice"] ?? '';
        
        // trip type (gdeal or fit(ypsilon)?)
        $tripType = $flight["tripType"] ?? '';
        
        $productIdTo = '';
        $productIdReturn = '';
        // is gdeals?
        $isGdeal = 0;
        if ($tripType == 'gdeal'){
            $isGdeal = 1;
            // product id (to)
            $productIdTo = $flight['oneway'][0]['flightId'];
            
            // product id (return)
            $productIdReturn = !empty($flight['return']) ? $flight['return'][0]['flightId'] : '';
        }
        
        if (!empty($newPrice)) {
            $newPrice = (float) $newPrice;
            $currentPrice = (float) $row->current_price;
            
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM wpk4_quote_G360 
                    WHERE original_quote_id = %d 
                      AND ABS(current_price - %f) < 0.01;",
                    $row->id,
                    $newPrice
                )
            );
            // if the new price is lower than current price and no same price is present
            if ($existing == 0 && $newPrice < $currentPrice) {
                $inert_result = $wpdb->insert(
                    'wpk4_quote_G360',
                    [
                        'original_quote_id' => $row->id,
                        'depart_apt'    => $row->depart_apt,
                        'dest_apt'      => $row->dest_apt,
                        'current_price' => $newPrice,
                        'depart_date'   => $row->depart_date,
                        'return_date'   => $row->return_date,
                        'user_id'       => $row->user_id,
                        'name'          => $row->name,
                        'email'         => $row->email,
                        'phone_num'     => $row->phone_num,
                        'tsr'           => $row->tsr,
                        'call_record_id'=> $row->call_record_id,
                        'to_product_id' => $productIdTo,
                        'return_product_id' => $productIdReturn,
                        'url'           => $row->url,
                        'adult_count'   => $row->adult_count,
                        'child_count'   => $row->child_count,
                        'infant_count'  => $row->infant_count,
                        'total_pax'     => $row->total_pax,
                        'is_gdeals'     => $isGdeal
                        ],
                    [
                        '%d', // original_quote_id
                        '%s', // depart_apt
                        '%s', // dest_apt
                        '%f', // current_price
                        '%s', // depart_date
                        '%s', // return_date
                        '%d', // user_id
                        '%s', // name
                        '%s', // email
                        '%s', // phone_num
                        '%s', // tsr
                        '%d', // call_record_id
                        '%d', // to_product_id
                        '%d', // return_product_id
                        '%s', // url
                        '%d', // adult_count
                        '%d', // child_count
                        '%d', // infant_count
                        '%d', // total_pax
                        '%d'  // is_gdeals
                        ]
                    );
            }
        }
    }
}
