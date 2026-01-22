<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection - no longer needed (now using API endpoints)
// All database queries have been replaced with API endpoint calls
// Old database connection code is commented out below
// require_once '../../../../wp-config-custom.php';

require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
 $api_url = API_BASE_URL;
$apiUrl = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass_main.php';
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_USERAGENT      => 'GTX-SettingsFetcher/1.0',
    CURLOPT_SSL_VERIFYPEER => true,   // keep true in prod
    CURLOPT_SSL_VERIFYHOST => 2,      // keep strict
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false) {
    die("Failed to load settings: $err");
}
if ($http !== 200) {
    // Show a snippet of body for debugging
    die("Settings endpoint HTTP $http.\n".substr($body, 0, 500));
}

$resp = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON: ".json_last_error_msg()."\n".substr($body, 0, 500));
}
if (!is_array($resp) || empty($resp['success'])) {
    die("Invalid settings response shape.\n".substr($body, 0, 500));
}

$settings = $resp['data'] ?? [];
foreach ($settings as $k => $v) {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
        $GLOBALS[$k] = $v;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_phone = trim($_POST['phone'] ?? '');
    $user_message = trim($_POST['message'] ?? '');

    if (empty($user_phone) || empty($user_message)) {
        echo "Phone and message are required.";
        exit;
    }

    $access_token = $WHATSAPP_API_TOKEN;
    $from_number = $WHATSAPP_API_PHONE_ID;
    $recipient_number = '61493602729';  // Hardcoded recipient number (you can change if needed)

    $url = "https://graph.facebook.com/v18.0/{$from_number}/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $recipient_number,
        'type' => 'text',
        'text' => ['body' => "New Message from {$user_phone}:\n\n{$user_message}"]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch);
    } elseif ($httpcode >= 200 && $httpcode < 300) {
        
        // Log message via API endpoint
        // API Endpoint: POST /v1/whatsapp/messages
        // Sources: WhatsAppMessageService::sendMessage, WhatsAppMessageDAL::logMessage
        // Body parameters: phone (required), message (required)
        try {
           
            $endpoint = $api_url . '/whatsapp/messages/log';
            
            // Prepare request body
            $apiPayload = [
                'phone'     => $user_phone,
                'message'   => $user_message,
                'recipient' => $recipient_number
            ];

            
            $apiCh = curl_init($endpoint);
            curl_setopt($apiCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($apiCh, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($apiCh, CURLOPT_TIMEOUT, 10);
            curl_setopt($apiCh, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($apiCh, CURLOPT_POST, true);
            curl_setopt($apiCh, CURLOPT_POSTFIELDS, json_encode($apiPayload));
            curl_setopt($apiCh, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            $apiResponse = curl_exec($apiCh);
            $apiHttpCode = curl_getinfo($apiCh, CURLINFO_HTTP_CODE);
            curl_close($apiCh);
            
            if ($apiHttpCode >= 200 && $apiHttpCode < 300) {
                $apiData = json_decode($apiResponse, true);
                if (isset($apiData['status']) && $apiData['status'] === 'success') {
                    echo "✅ Message logged via API successfully!";
                } else {
                    echo "⚠️ Message sent to WhatsApp but API logging returned: " . ($apiData['message'] ?? 'Unknown error');
                }
            } else {
    echo "⚠️ Logging API FAILED\n";
    echo "HTTP CODE: $apiHttpCode\n";
    echo "ENDPOINT: $endpoint\n";
    echo "PAYLOAD:\n";
    var_dump($apiPayload);
    echo "\nRESPONSE:\n";
    echo $apiResponse;
    exit;
}
        } catch (Exception $e) {
            echo "⚠️ Message sent to WhatsApp but API logging error: " . $e->getMessage();
        }
        
        echo "\nMessage sent successfully!";
        
        // OLD SQL QUERY - COMMENTED OUT (now using API endpoint)
        /*
        // SQL Query:
        // INSERT INTO whatsapp_messages
        //     (sender_type, sender_id, recipient_id, message, message_id, status, msg_read_customer, updated_on)
        // VALUES (?, ?, ?, ?, ?, ?, ?, ?);
        // Source: WhatsAppMessageService::sendMessage, WhatsAppMessageDAL::logMessage
        // Method: POST
        // Endpoint: /v1/whatsapp/messages
        // Body parameters: phone (required), message (required)
        
        $sender_type = 'customer';
        $sender_id = $user_phone;
        $recipient_id = $WHATSAPP_API_PHONE_NUMBER;
        $message_content = $user_message; 
        $message_id = uniqid('msg_');
        $status = 'delivered';
        $msg_read_customer = 1;
        $updated_on = date('Y-m-d H:i:s');
        
        $stmt = $mysqli->prepare("
            INSERT INTO whatsapp_messages 
            (sender_type, sender_id, recipient_id, message, message_id, status, msg_read_customer, updated_on) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param(
                "ssssssis",
                $sender_type,
                $sender_id,
                $recipient_id,
                $message_content,
                $message_id,
                $status,
                $msg_read_customer,
                $updated_on
            );
        
            if ($stmt->execute()) {
                echo "✅ Message saved in DB. ID: " . $stmt->insert_id;
            } else {
                echo "❌ DB insert error: " . $stmt->error;
            }
        
            $stmt->close();
        } else {
            echo "❌ Statement prepare failed: " . $mysqli->error;
        }
        */
    } else {
        echo "Failed to send. Response: " . $response;
    }

    curl_close($ch);
} else {
    echo "Invalid request.";
}
?>