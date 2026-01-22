<?php
// Load WordPress
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
date_default_timezone_set('Australia/Melbourne');

if (!defined('API_BASE_URL')) {
    die('API base URL not configured');
}

// API helper
function searchAgentsFromAPI(string $searchTerm): array
{
    $endpoint = rtrim(API_BASE_URL, '/') . '/agents/search';
    $url = $endpoint . '?' . http_build_query(['term' => $searchTerm]);

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            error_log("agents/search curl error: {$curlError}");
            return [];
        }

        if ($httpCode !== 200) {
            error_log("agents/search HTTP {$httpCode}: {$response}");
            return [];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("agents/search JSON error: " . json_last_error_msg());
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return is_array($data) ? $data : [];
    } catch (Throwable $e) {
        error_log("agents/search exception: " . $e->getMessage());
        return [];
    }
}

$term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
if ($term === '') {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$agents = searchAgentsFromAPI($term);

header('Content-Type: application/json');
echo json_encode($agents);
exit;