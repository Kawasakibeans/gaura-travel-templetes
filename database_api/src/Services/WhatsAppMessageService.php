<?php
/**
 * WhatsApp message service.
 */

namespace App\Services;

use App\DAL\WhatsAppMessageDAL;
use DateTime;
use DateTimeZone;
use Exception;

class WhatsAppMessageService
{
    private const SETTINGS_URL = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass_main.php';
    private WhatsAppMessageDAL $dal;

    public function __construct()
    {
        $this->dal = new WhatsAppMessageDAL();
    }

    public function sendMessage(array $payload): array
    {
        // Ensure script has enough time to complete (60 seconds)
        set_time_limit(60);
        
        $phone = isset($payload['phone']) ? trim((string)$payload['phone']) : '';
        $message = isset($payload['message']) ? trim((string)$payload['message']) : '';

        if ($phone === '' || $message === '') {
            throw new Exception('phone and message are required', 400);
        }

        try {
            $settings = $this->fetchSettings();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch WhatsApp settings: ' . $e->getMessage(), 502);
        }

        $token = $settings['WHATSAPP_API_TOKEN'] ?? null;
        $phoneId = $settings['WHATSAPP_API_PHONE_ID'] ?? null;
        $recipient = $settings['WHATSAPP_API_PHONE_NUMBER'] ?? null;

        if (!$token || !$phoneId || !$recipient) {
            throw new Exception('WhatsApp API credentials are missing', 500);
        }

        $graphUrl = sprintf('https://graph.facebook.com/v18.0/%s/messages', $phoneId);
        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'text',
            'text' => [
                'body' => "New Message from {$phone}:\n\n{$message}",
            ],
        ];

        $response = $this->postJson($graphUrl, $body, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception('Failed to send WhatsApp message: ' . $response['body'], 502);
        }

        $decoded = json_decode($response['body'], true);

        $messageId = $decoded['messages'][0]['id'] ?? uniqid('msg_', true);
        $status = 'delivered';

        // Try to log the message, but don't fail if logging fails
        try {
            $now = (new DateTime('now', new DateTimeZone('Australia/Melbourne')))->format('Y-m-d H:i:s');
            $this->dal->logMessage(
                'customer',
                $phone,
                $recipient,
                $message,
                $messageId,
                $status,
                1,
                $now
            );
        } catch (Exception $logException) {
            // Logging failed, but message was sent successfully - continue
        }

        return [
            'status' => 'Message sent successfully',
            'message_id' => $messageId,
            'graph_response' => $decoded ?? $response['body'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        $ch = curl_init(self::SETTINGS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,  // Reduced from 10 to 8 seconds
            CURLOPT_CONNECTTIMEOUT => 3,  // Reduced from 5 to 3 seconds
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'GTX-SettingsFetcher/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Failed to load WhatsApp settings: ' . $err, 502);
        }
        if ($http !== 200) {
            throw new Exception('Settings endpoint returned HTTP ' . $http . ': ' . substr($body, 0, 512), 502);
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid settings payload: ' . json_last_error_msg(), 502);
        }

        if (!is_array($json) || empty($json['success']) || !isset($json['data']) || !is_array($json['data'])) {
            throw new Exception('Unexpected settings payload structure', 502);
        }

        return $json['data'];
    }

    /**
     * Execute a JSON POST request.
     *
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    private function postJson(string $url, array $payload, array $headers = []): array
    {
        $json = json_encode($payload);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Length: ' . strlen((string)$json)]),
            CURLOPT_TIMEOUT => 30,  // Maximum time for the entire request (30 seconds)
            CURLOPT_CONNECTTIMEOUT => 10,  // Maximum time to establish connection (10 seconds)
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('WhatsApp API request failed: ' . $err, 502);
        }

        return [
            'status' => (int)$status,
            'body' => (string)$body,
        ];
    }
}

