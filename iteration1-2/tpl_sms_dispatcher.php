<?php
if (!function_exists('generateTransmitSMS')) 
{
    function generateTransmitSMS( $message, $to, $type, $orderid, $userid ) 
    {
        global $mysqli;
        $message = mysqli_real_escape_string( $mysqli, $message );
        $current_date = date("Y-m-d H:i:s");
        $current_date_ymd = date("Y-m-d");
        
        $query_is_sms_sent = "SELECT auto_id FROM wpk4_backend_order_sms_history where type = '$type' AND phone = '$to' AND date(added_on) <= '$current_date_ymd'";
        $result_is_sms_sent = mysqli_query($mysqli, $query_is_sms_sent) or die(mysqli_error($mysqli));
        if(mysqli_num_rows($result_is_sms_sent) == 0)
        {
            $curl = curl_init();
            // Encode message to ensure it is URL-safe
            $encodedMessage = urlencode($message);
            // Prepare the API URL
            $apiUrl = "https://api.transmitsms.com/send-sms.json?message=$encodedMessage&to=$to&from=GauraTravel&validity=30";
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic ZmUzZjQzZTJlOGMyM2Q5YmU1MjhkNDliZjczMWIxYjE6R3R4QDEyMzQ1Kg=='
                ),
            ));
            $response = curl_exec($curl);
            if(curl_errno($curl)) {
                echo 'Curl error: ' . curl_error($curl);
            }
            curl_close($curl);
            
            
            
            $responseData = json_decode($response, true);
            $messageId = $responseData['message_id'];
            
            mysqli_query($mysqli,"insert into wpk4_backend_order_sms_history ( order_id, message, phone, source, message_id, added_on, added_by, type ) 
    		values ('$orderid','$message','$to' ,'TransmitSMS', '$messageId', '$current_date', '$userid', '$type' )") or die(mysqli_error($mysqli));
        }
        else
        {
            $response = '';
            
            mysqli_query($mysqli,"insert into wpk4_backend_order_sms_history ( order_id, message, phone, source, message_id, added_on, added_by, type ) 
    		values ('$orderid','$message','$to' ,'', 'Duplicate', '$current_date', '$userid', '$type' )") or die(mysqli_error($mysqli));
        }
        return $response;
    }
}

if( isset($sms_type) && $sms_type == 'booking-received')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'Thank you for your booking! You&#39;ll receive an email with payment options within the next hour. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'booking-received', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'deposit-and-payment-email-sent')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'We got your deposit and we have shared the payment email for the balance which suppose to be paid within next 96 hrs. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'deposit-and-payment-email-sent', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'deposit-and-payment-email-sent-eod')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'We got your deposit and we have shared the payment email for the balance which suppose to be paid within end of today. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'deposit-and-payment-email-sent-eod', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'payment-email-sent')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'We have sent you the payment email. Please proceed the payment within 96 hours to keep the booking active. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'payment-email-sent', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'payment-email-sent-eod')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'We have sent you the payment email. Please proceed the payment within EOD to keep the booking active. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'payment-email-sent-eod', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'balance-received')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = 'We got your balance payment and you will receive your travel document before 7 days of your travel date. - Gaura Travel';
        generateTransmitSMS( $message, $sms_to, 'balance-received', $sms_order_id, $sms_userlogin );
    }
}

if( isset($sms_type) && $sms_type == 'payment-not-received')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = "We haven't got any payment for the booking. Make the payment urgently to keep the booking active . - Gaura Travel";
        $sfgdgdfg  =  generateTransmitSMS( $message, $sms_to, 'payment-not-received', $sms_order_id, $sms_userlogin );
        echo '<pre>'; print_r($sfgdgdfg);echo '</pre>';
    }
}

if( isset($sms_type) && $sms_type == 'payment-not-received-eod')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = "We haven't got any payment for the booking. Make the payment urgently to keep the booking active . - Gaura Travel";
        $sfgdgdfg  =  generateTransmitSMS( $message, $sms_to, 'payment-not-received-eod', $sms_order_id, $sms_userlogin );
        echo '<pre>'; print_r($sfgdgdfg);echo '</pre>';
    }
}

if( isset($sms_type) && $sms_type == 'payment-not-received-cancel')
{
    $sms_to = '61493602729';
    if($sms_to)
    {
        $message = "We regret to inform you that your booking has been cancelled due to non-payment within a specified time.";
        $sfgdgdfg  =  generateTransmitSMS( $message, $sms_to, 'payment-not-received-cancel', $sms_order_id, $sms_userlogin );
        echo '<pre>'; print_r($sfgdgdfg);echo '</pre>';
    }
}
?>