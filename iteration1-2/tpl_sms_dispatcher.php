<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Define API base URL if not already defined
if (!defined('API_BASE_URL')) {
    $base_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';
} else {
    $base_url = API_BASE_URL;
}

// TEST MODE - Accept GET parameters for testing
if (isset($_GET['test']) && $_GET['test'] == '1') {
    $sms_type = isset($_GET['sms_type']) ? $_GET['sms_type'] : '';
    $sms_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $sms_userlogin = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    // Show menu if no type specified
    if (empty($sms_type)) {
        echo "<!DOCTYPE html><html><head><title>SMS Dispatcher Test</title><style>body{font-family:Arial,sans-serif;padding:20px;}ul{list-style-type:none;padding:0;}li{margin:10px 0;}a{display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:5px;}a:hover{background:#005177;}</style></head><body>";
        echo "<h2>SMS Dispatcher Test</h2>";
        echo "<p>Select an SMS type to test:</p>";
        echo "<ul>";
        echo "<li><a href='?test=1&sms_type=booking-received&order_id=123&user_id=456'>booking-received</a></li>";
        echo "<li><a href='?test=1&sms_type=deposit-and-payment-email-sent&order_id=123&user_id=456'>deposit-and-payment-email-sent</a></li>";
        echo "<li><a href='?test=1&sms_type=deposit-and-payment-email-sent-eod&order_id=123&user_id=456'>deposit-and-payment-email-sent-eod</a></li>";
        echo "<li><a href='?test=1&sms_type=payment-email-sent&order_id=123&user_id=456'>payment-email-sent</a></li>";
        echo "<li><a href='?test=1&sms_type=payment-email-sent-eod&order_id=123&user_id=456'>payment-email-sent-eod</a></li>";
        echo "<li><a href='?test=1&sms_type=balance-received&order_id=123&user_id=456'>balance-received</a></li>";
        echo "<li><a href='?test=1&sms_type=payment-not-received&order_id=123&user_id=456'>payment-not-received</a></li>";
        echo "<li><a href='?test=1&sms_type=payment-not-received-eod&order_id=123&user_id=456'>payment-not-received-eod</a></li>";
        echo "<li><a href='?test=1&sms_type=payment-not-received-cancel&order_id=123&user_id=456'>payment-not-received-cancel</a></li>";
        echo "</ul>";
        echo "<hr>";
        echo "<p><small>Usage: Add <code>?test=1&sms_type=TYPE&order_id=123&user_id=456</code> to the URL</small></p>";
        echo "</body></html>";
        exit;
    }
    
    // Validate SMS type
    $valid_types = [
        'booking-received',
        'deposit-and-payment-email-sent',
        'deposit-and-payment-email-sent-eod',
        'payment-email-sent',
        'payment-email-sent-eod',
        'balance-received',
        'payment-not-received',
        'payment-not-received-eod',
        'payment-not-received-cancel'
    ];
    
    if (!in_array($sms_type, $valid_types)) {
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h2>Error</h2>";
        echo "<p>Invalid sms_type. Valid types: " . implode(', ', $valid_types) . "</p>";
        echo "<p><a href='?test=1'>← Back to SMS Types</a></p>";
        echo "</body></html>";
        exit;
    }
    
    // Set output buffer to capture any output
    ob_start();
}

if (!function_exists('checkSmsSent')) 
{
    /**
     * Check if SMS was already sent using API
     * @param string $type SMS type
     * @param string $to Phone number
     * @param string $date Date in Y-m-d format
     * @return bool True if SMS was sent, false otherwise
     */
    function checkSmsSent($type, $to, $date) 
    {
        global $base_url;
        $apiUrl = $base_url . '/sms-dispatcher/check-sent?' . http_build_query([
            'type' => $type,
            'phone' => $to,
            'date' => $date
        ]);
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if(curl_errno($curl)) {
            error_log('SMS Dispatcher API Error: ' . curl_error($curl));
            curl_close($curl);
            return false;
        }
        curl_close($curl);
        
        if ($httpCode !== 200) {
            error_log('SMS Dispatcher API returned HTTP ' . $httpCode);
            return false;
        }
        
        $responseData = json_decode($response, true);
        // Assuming API returns { "sent": true/false } or { "count": 0 } format
        if (isset($responseData['sent'])) {
            return $responseData['sent'] === true;
        } elseif (isset($responseData['count'])) {
            return $responseData['count'] > 0;
        }
        
        return false;
    }
}

if (!function_exists('logSmsHistory')) 
{
    /**
     * Log SMS history using API
     * @param array $data SMS history data
     * @return bool True if successful, false otherwise
     */
    function logSmsHistory($data) 
    {
        global $base_url;
        $apiUrl = $base_url . '/sms-dispatcher/log-history';
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if(curl_errno($curl)) {
            error_log('SMS Dispatcher API Error: ' . curl_error($curl));
            curl_close($curl);
            return false;
        }
        curl_close($curl);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log('SMS Dispatcher API returned HTTP ' . $httpCode . ': ' . $response);
            return false;
        }
        
        return true;
    }
}

if (!function_exists('generateTransmitSMS')) 
{
    function generateTransmitSMS( $message, $to, $type, $orderid, $userid ) 
    {
        $current_date = date("Y-m-d H:i:s");
        $current_date_ymd = date("Y-m-d");
        
        // Check if SMS was already sent using API
        $isSmsSent = checkSmsSent($type, $to, $current_date_ymd);
        
        if(!$isSmsSent)
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
            $messageId = isset($responseData['message_id']) ? $responseData['message_id'] : '';
            
            // Log SMS history using API
            logSmsHistory([
                'order_id' => $orderid,
                'message' => $message,
                'phone' => $to,
                'source' => 'TransmitSMS',
                'message_id' => $messageId,
                'added_on' => $current_date,
                'added_by' => $userid,
                'type' => $type
            ]);
        }
        else
        {
            $response = '';
            
            // Log duplicate SMS history using API
            logSmsHistory([
                'order_id' => $orderid,
                'message' => $message,
                'phone' => $to,
                'source' => '',
                'message_id' => 'Duplicate',
                'added_on' => $current_date,
                'added_by' => $userid,
                'type' => $type
            ]);
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

// TEST MODE - Show result page if test mode is enabled
if (isset($_GET['test']) && $_GET['test'] == '1' && isset($sms_type) && !empty($sms_type)) {
    $output = ob_get_clean();
    echo "<!DOCTYPE html><html><head><title>SMS Dispatcher Test Result</title><style>body{font-family:Arial,sans-serif;padding:20px;}pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;overflow:auto;}</style></head><body>";
    echo "<h2>SMS Dispatcher Test Result</h2>";
    echo "<p><strong>SMS Type:</strong> " . htmlspecialchars($sms_type) . "</p>";
    echo "<p><strong>Order ID:</strong> " . htmlspecialchars($sms_order_id ?? 0) . "</p>";
    echo "<p><strong>User ID:</strong> " . htmlspecialchars($sms_userlogin ?? 0) . "</p>";
    echo "<p><strong>Status:</strong> SMS processing completed.</p>";
    
    if (!empty($output)) {
        echo "<hr><h3>Output:</h3>";
        echo $output;
    }
    
    echo "<hr>";
    echo "<p><small>Note: Check server error logs and SMS API logs for detailed status.</small></p>";
    echo "<p><a href='?test=1'>← Back to SMS Types</a></p>";
    echo "</body></html>";
    exit;
}
?>