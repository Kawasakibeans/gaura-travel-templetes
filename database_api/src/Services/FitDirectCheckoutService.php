<?php
/**
 * Service for FIT direct checkout URL and SMS handling.
 */

namespace App\Services;

use App\DAL\FitCheckoutDAL;
use Exception;

class FitDirectCheckoutService
{
    private FitCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new FitCheckoutDAL();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createShortUrl(array $payload): array
    {
        $url = isset($payload['url']) ? trim((string)$payload['url']) : '';
        if ($url === '') {
            throw new Exception('url is required', 400);
        }

        $timestamp = (string)($payload['timestamp'] ?? time());
        $id = $this->dal->insertUrlRedirect($timestamp, $url);

        return [
            'success' => true,
            'id' => $id,
            'key' => $timestamp,
            'short_url' => sprintf('https://gauratravel.com.au/direct-to-checkout/?id=%d&key=%s', $id, $timestamp),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function resolveShortUrl(array $filters): array
    {
        $id = isset($filters['id']) ? (int)$filters['id'] : 0;
        $key = isset($filters['key']) ? trim((string)$filters['key']) : '';

        if ($id <= 0 || $key === '') {
            throw new Exception('id and key are required', 400);
        }

        $url = $this->dal->getUrlRedirect($id, $key);
        if (!$url) {
            throw new Exception('URL not found or invalid key', 404);
        }

        return [
            'id' => $id,
            'key' => $key,
            'url' => $url,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function sendSms(array $payload): array
    {
        $shortUrl = isset($payload['short_url']) ? trim((string)$payload['short_url']) : '';
        $phone = isset($payload['phone']) ? trim((string)$payload['phone']) : '';
        $message = isset($payload['message']) ? trim((string)$payload['message']) : '';
        $addedBy = isset($payload['added_by']) ? trim((string)$payload['added_by']) : 'fit_checkout_agent';

        if ($shortUrl === '' || $phone === '') {
            throw new Exception('short_url and phone are required', 400);
        }

        $fullMessage = $message !== '' ? $message : 'This is your Checkout URL: ' . $shortUrl;
        $encodedMessage = urlencode($fullMessage);

        $apiUrl = sprintf(
            'https://api.transmitsms.com/send-sms.json?message=%s&to=%s&from=%s&validity=30',
            $encodedMessage,
            urlencode($phone),
            urlencode('GauraTravel')
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ZmUzZjQzZTJlOGMyM2Q5YmU1MjhkNDliZjczMWIxYjE6R3R4QDEyMzQ1Kg==',
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('SMS gateway error: ' . $error, 502);
        }

        $decoded = json_decode((string)$response, true);
        if (!$decoded || !isset($decoded['error'])) {
            throw new Exception('Invalid response from SMS gateway', 502);
        }

        $errorCode = $decoded['error']['code'] ?? '';
        $errorDescription = $decoded['error']['description'] ?? '';

        if ($errorCode !== 'SUCCESS' || $errorDescription !== 'OK') {
            throw new Exception('SMS send failed: ' . $errorDescription, 502);
        }

        $messageId = $decoded['message_id'] ?? '';
        $sendAt = $decoded['send_at'] ?? '';

        $this->dal->insertSmsHistory($fullMessage, $phone, 'TransmitSMS', $messageId, $addedBy);

        return [
            'success' => true,
            'message_id' => $messageId,
            'send_at' => $sendAt,
        ];
    }
}

