<?php
date_default_timezone_set("Australia/Melbourne");
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

$wpConfig = dirname(__FILE__, 5) . '/wp-config.php';
if (file_exists($wpConfig)) {
    require_once $wpConfig;
}

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined');
}

$apiBaseUrl = API_BASE_URL;

/**
 * Call the database API and return decoded payload.
 */
function call_database_api(string $endpoint, string $method = 'GET', array $payload = []): array
{
    global $apiBaseUrl;

    $method = strtoupper($method);
    $url = rtrim($apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');

    if ($method === 'GET' && !empty($payload)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($payload);
    }

    $headers = ['Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    } elseif ($method !== 'GET') {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_CUSTOMREQUEST] = $method;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException('API request failed: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('API request failed with status ' . $httpCode . ': ' . substr((string)$response, 0, 300));
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON response: ' . json_last_error_msg());
    }

    if (($decoded['status'] ?? 'error') !== 'success') {
        $message = $decoded['message'] ?? 'Unknown API error';
        throw new RuntimeException($message);
    }

    return $decoded['data'] ?? [];
}

$output = [
    'success' => true,
    'cancelled_orders' => [],
    'total_found' => 0,
];

try {
    $data = call_database_api('auto-cancellation/gdeals-fullpayment');
    $bookings = $data['bookings'] ?? [];
    $output['total_found'] = $data['total_count'] ?? count($bookings);

    foreach ($bookings as $booking) {
        $orderId = $booking['order_id'] ?? null;
        if (!$orderId) {
            continue;
        }

        try {
            $cancelData = call_database_api('auto-cancellation/gdeals-fullpayment/cancel', 'POST', [
                'order_id' => $orderId,
            ]);

            $output['cancelled_orders'][] = [
                'order_id' => $orderId,
                'order_date' => $booking['order_date'] ?? null,
                'total_amount' => isset($booking['total_amount']) ? (float)$booking['total_amount'] : 0,
                'paid' => isset($booking['paid_amount']) ? (float)$booking['paid_amount'] : 0,
                'new_status' => $cancelData['status'] ?? 'canceled',
                'seat_update' => $cancelData['seat_update'] ?? [],
            ];
        } catch (Throwable $orderException) {
            $output['success'] = false;
            $output['errors'][] = [
                'order_id' => $orderId,
                'message' => $orderException->getMessage(),
            ];
        }
    }

    if (empty($output['cancelled_orders']) && empty($bookings)) {
        $output['message'] = 'No eligible bookings found';
    }

    if (empty($output['errors'] ?? [])) {
        unset($output['errors']);
    }
} catch (Throwable $e) {
    $output = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode($output, JSON_PRETTY_PRINT);

