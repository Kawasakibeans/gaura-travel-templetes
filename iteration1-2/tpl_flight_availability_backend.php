<?php 

// Backend for availability's operation

header('Content-Type: application/json');
global $wpdb;
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $depart_apt = sanitize_text_field($_POST['depart_apt']);
    $dest_apt = sanitize_text_field($_POST['dest_apt']);
    $outbound_seat = sanitize_text_field($_POST['outbound_seat']);
    $return_seat = sanitize_text_field($_POST['return_seat']);
    $depart_date = sanitize_text_field($_POST['depart_date']);
    $return_date = sanitize_text_field($_POST['return_date']);
    $flightName = sanitize_text_field($_POST['flightName']);
    
    $sessionId = sanitize_text_field($_POST['sessionId']);
    $tarifId = sanitize_text_field($_POST['tarifId']);
    $outboundFlightId = sanitize_text_field($_POST['outboundFlightId']);
    $returnFlightId = sanitize_text_field($_POST['returnFlightId']);
    
    // $totalAdult = sanitize_text_field($_POST['totalAdult']);
    // $totalChild = sanitize_text_field($_POST['totalChild']);
    // $totalInfant = sanitize_text_field($_POST['totalInfant']);
    // $grandTotal = sanitize_text_field($_POST['grandTotal']);
    
    $user_id = $_POST['user_id']; 
    $depart_date = DateTime::createFromFormat('d-m-Y', $depart_date)->format('Y-m-d');

    $api_data_json = [];
    if (isset($_POST['apiData'])) {
        $api_data_raw = $_POST['apiData'];
        if (is_string($api_data_raw)) {
            $api_data_json = json_decode(stripslashes($api_data_raw), true);
            if (isset($api_data_json['legs']) && is_string($api_data_json['legs'])) {
                $api_data_json['legs'] = json_decode($api_data_json['legs'], true);
            }
        } elseif (is_array($api_data_raw)) {
            $api_data_json = $api_data_raw;
        }
    }

    

    if(isset($return_date) && !empty($return_date)){
      $return_date = DateTime::createFromFormat('d-m-Y', $return_date)->format('Y-m-d');
    } else{
        $return_date = null;
    }
    
/*
'adt_amt'   => $totalAdult,
            'chd_amt'   => $totalChild,
            'inf_amt'   => $totalInfant,
            'grand_total'   => $grandTotal,
            */
    $inserted = $wpdb->insert(
        "wpk4_ypsilon_flight_availability_check",
        [
            'user_id'       => $user_id,
            'depart_apt'    => $depart_apt,
            'dest_apt'       => $dest_apt,
            'outbound_seat' => $outbound_seat,
            'return_seat'   => $return_seat,
            'depart_date'   => $depart_date,
            'return_date'   => $return_date,
            'airline'   => $flightName,
            'tariffId'   => $tarifId,
            'session_id'   => $sessionId
            ],
            [
                '%s', '%s', '%s', '%s', '%s','%s','%s','%s','%s','%s'
                ]
        );
    
    // successfully inserted
    if (!$inserted) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save quote'
        ]);
        exit;
    }

    $avail_id = $wpdb->insert_id;
    
    $valid_leg_ids = [];

    if (isset($api_data_json['tarifs']['tarif']['fareXRefs']['fareXRef'])) {
        $fareXRefs = $api_data_json['tarifs']['tarif']['fareXRefs']['fareXRef'];
    
        // Make sure it's an array (if it's single object)
        if (isset($fareXRefs['fareId'])) {
            $fareXRefs = [$fareXRefs];
        }
    
        foreach ($fareXRefs as $fareXRef) {
            if (!isset($fareXRef['flights']) || !is_array($fareXRef['flights'])) {
                continue;
            }
    
            foreach ($fareXRef['flights'] as $flight) {
                if (
                    ($outboundFlightId && $flight['flightId'] == $outboundFlightId) ||
                    ($returnFlightId && $flight['flightId'] == $returnFlightId)
                ) {
                    if (isset($flight['legXRefs']) && is_array($flight['legXRefs'])) {
                        foreach ($flight['legXRefs'] as $legXref) {
                            $valid_leg_ids[] = $legXref['legId'];
                        }
                    }
                }
            }
        }
    }

    // Insert leg records if available
    if (isset($api_data_json['legs']['leg']) && is_array($api_data_json['legs']['leg'])) {
        foreach ($api_data_json['legs']['leg'] as $leg) 
        {
            
            if (!in_array($leg['legId'], $valid_leg_ids)) {
                continue;
            }
            
            if(!isset($leg['legId']))
            {
                continue;
            }

            $wpdb->insert('wpk4_ypsilon_flight_availability_check_legs', [
                'avai_check_id' => $avail_id,
                'legId'         => $leg['legId'],
                'depApt'        => $leg['depApt'],
                'depDate'       => $leg['depDate'],
                'depTime'       => $leg['depTime'],
                'dstApt'        => $leg['dstApt'],
                'depTerm'       => $leg['depTerm'],
                'arrTerm'       => $leg['arrTerm'],
                'arrDate'       => $leg['arrDate'],
                'arrTime'       => $leg['arrTime'],
                'equip'         => $leg['equip'],
                'fNo'           => $leg['fNo'],
                'miles'         => $leg['miles'],
                'elapsed'       => $leg['elapsed'],
                'meals'         => $leg['meals'],
                'smoker'        => $leg['smoker'],
                'stops'         => $leg['stops'],
                'eticket'       => $leg['eticket']
            ]);
            if ($wpdb->last_error) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Insert leg error: ' . $wpdb->last_error
                ]);
                exit;
            }
        }
    }

    // Final response
    echo json_encode([
        'success' => true,
        'message' => 'Availability and legs saved successfully',
        'id' => $avail_id
    ]);
    exit;
}


?>