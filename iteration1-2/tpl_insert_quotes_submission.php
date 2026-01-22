<?php
date_default_timezone_set("Australia/Melbourne"); 
header("Content-Type: application/json");

// Load WordPress to access constants
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

// Use API_BASE_URL constant if defined, otherwise use default
if (defined('API_BASE_URL')) {
    /** @var string $api_url */
    $api_url = constant('API_BASE_URL');
} else {
    $api_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';
}

// Test mode: Read data from database via GET parameters
// Usage: ?quote_id=9 or ?quote_id=9&phone=0493602729
if (isset($_GET['quote_id']) && !empty($_GET['quote_id'])) {
    $quoteId = intval($_GET['quote_id']);
    $testPhone = isset($_GET['phone']) ? $_GET['phone'] : null;
    
    // ✅ FIX: Read data from API instead of direct database query
    // OLD PDO QUERY - COMMENTED OUT (now using API endpoint)
    /*
    $pdo = new PDO(...);
    $stmt = $pdo->prepare("...");
    $stmt->execute(['quote_id' => $quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    */
    
    try {
        // Call API to get quote data
        global $api_url;
        $quoteApiUrl = rtrim($api_url, '/') . '/quotes/' . urlencode($quoteId);
        
        $ch = curl_init($quoteApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo json_encode(["success" => false, "message" => "API request error: " . $curlError]);
            exit;
        }
        
        if ($httpCode !== 200) {
            echo json_encode(["success" => false, "message" => "API returned HTTP $httpCode. No data found for quote_id = $quoteId"]);
            exit;
        }
        
        $apiResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(["success" => false, "message" => "JSON decode error: " . json_last_error_msg()]);
            exit;
        }
        
        // Check API response structure
        if (!isset($apiResponse['status']) || $apiResponse['status'] !== 'success' || !isset($apiResponse['data'])) {
            echo json_encode(["success" => false, "message" => "No data found for quote_id = $quoteId"]);
            exit;
        }
        
        $quoteData = $apiResponse['data'];
        
        // Extract data from API response
        // Note: API response structure may differ, adjust field names as needed
        $row = [
            'quote_id' => $quoteData['quote_id'] ?? $quoteId,
            'phone_number' => $quoteData['phone_number'] ?? $quoteData['phone_num'] ?? null,
            'outbound_trip' => isset($quoteData['outbound_trip']) 
                ? (is_string($quoteData['outbound_trip']) ? $quoteData['outbound_trip'] : json_encode($quoteData['outbound_trip']))
                : null,
            'return_trip' => isset($quoteData['return_trip']) 
                ? (is_string($quoteData['return_trip']) ? $quoteData['return_trip'] : json_encode($quoteData['return_trip']))
                : null,
            'package' => isset($quoteData['package']) 
                ? (is_string($quoteData['package']) ? $quoteData['package'] : json_encode($quoteData['package']))
                : null
        ];
        
        // Parse JSON data
        $outboundFlight = null;
        $returnFlight = null;
        $package = null;
        
        if (!empty($row['outbound_trip'])) {
            $outboundFlight = is_string($row['outbound_trip']) 
                ? json_decode($row['outbound_trip'], true) 
                : $row['outbound_trip'];
        }
        
        if (!empty($row['return_trip'])) {
            $returnFlight = is_string($row['return_trip']) 
                ? json_decode($row['return_trip'], true) 
                : $row['return_trip'];
        }
        
        if (!empty($row['package'])) {
            $package = is_string($row['package']) 
                ? json_decode($row['package'], true) 
                : $row['package'];
        }
        
        if (json_last_error() !== JSON_ERROR_NONE && ($outboundFlight === null || $returnFlight === null || $package === null)) {
            // If JSON decode failed, try to use data directly from API response
            $outboundFlight = $quoteData['outbound_trip'] ?? null;
            $returnFlight = $quoteData['return_trip'] ?? null;
            $package = $quoteData['package'] ?? null;
        }
        
        // Try to read xml data from file system
        $xmlData = null;
        $xmlFilePath = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/quotes/{$quoteId}_1.txt";
        if (file_exists($xmlFilePath)) {
            $xmlContent = file_get_contents($xmlFilePath);
            $xmlData = json_decode($xmlContent, true);
        }
        
        // If xml file not found, use sample data
        if (!$xmlData) {
            $xmlData = [
                'searchParams' => [
                    'departure' => 'MEL',
                    'arrival' => 'SYD',
                    'departDate' => date('Y-m-d', strtotime('+7 days')),
                    'returnDate' => date('Y-m-d', strtotime('+14 days')),
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0
                ]
            ];
        }
        
        // Construct test data
        $data = [
            'phone' => $testPhone ?: $row['phone_number'],
            'flights' => [
                [
                    'xml' => $xmlData,
                    'outboundFlight' => $outboundFlight,
                    'returnFlight' => $returnFlight,
                    'package' => $package
                ]
            ]
        ];
        
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        exit;
    }
} else {
    // Normal mode: Read JSON data from POST body
    $data = json_decode(file_get_contents("php://input"), true);
}

if (!isset($data['flights']) || empty($data['flights'])) {
    echo json_encode(["success" => false, "message" => "Missing required data (flights)."]);
    exit;
}

/**
 * Helper function to submit quote via API
 * 
 * @param string $api_url Base API URL
 * @param string|null $phone Phone number (optional)
 * @param array $flights Flight data array
 * @return array|false API response data on success, false on failure
 */
function submitQuoteViaAPI($api_url, $phone, $flights) {
    $requestData = [
        'flights' => $flights
    ];
    
    if ($phone !== null) {
        $requestData['phone'] = $phone;
    }
    
    $ch = curl_init($api_url . '/quotes/submit');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("cURL error: $curlError\n");
        return false;
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $responseData;
        } else {
            error_log("JSON decode error: " . json_last_error_msg() . "\n");
            return false;
        }
    } else {
        error_log("API call failed. HTTP Code: $httpCode, Response: $response\n");
        return false;
    }
}

// Call API to submit quote
$phone = isset($data['phone']) ? $data['phone'] : null;
$result = submitQuoteViaAPI($api_url, $phone, $data['flights']);

// API returns format: {"status": "success", "message": "...", "data": {...}}
if ($result === false || !isset($result['status']) || $result['status'] !== 'success') {
    $errorMessage = isset($result['message']) ? $result['message'] : 'Failed to submit quote. Please try again.';
    echo json_encode(["success" => false, "message" => $errorMessage]);
    exit;
}

// Return success response
echo json_encode([
    "success" => true,
    "message" => $result['message'] ?? "Quote has been successfully saved!",
    "quote_id" => $result['data']['quote_id'] ?? null
]);
exit;
?>
