<?php

header("Content-Type: application/json"); // Ensure JSON response

// Include WordPress core (needed to access WP functions and classes)
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
} else {
    http_response_code(500);
    echo json_encode(['error' => 'WordPress not found', 'message' => 'Cannot load WordPress core']);
    exit;
}

// Use API_BASE_URL constant if defined, otherwise use default
if (defined('API_BASE_URL')) {
    /** @var string $api_url */
    $api_url = constant('API_BASE_URL');
} else {
    $api_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public/v1';
}

// Get current user - try multiple methods
$current_user_id = 0;
$current_user = null;

if (function_exists('wp_get_current_user')) {
    $current_user = wp_get_current_user();
    if ($current_user && isset($current_user->ID) && $current_user->ID > 0) {
        $current_user_id = $current_user->ID;
    }
} elseif (function_exists('get_current_user_id')) {
    $current_user_id = get_current_user_id();
}

// Check if user is logged in
if ($current_user_id === 0) {
    // Check for WordPress auth cookies
    $auth_cookie = isset($_COOKIE[LOGGED_IN_COOKIE]) ? $_COOKIE[LOGGED_IN_COOKIE] : null;
    $has_auth_cookie = !empty($auth_cookie);
    
    // For debugging - log the issue
    error_log("tpl_internal_email_backend.php: User not authenticated. REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . 
              ", Has auth cookie: " . ($has_auth_cookie ? 'Yes' : 'No') . 
              ", Cookies: " . json_encode(array_keys($_COOKIE ?? [])));
    
    http_response_code(401);
    echo json_encode([
        'error' => 'User not authenticated', 
        'message' => 'Please log in to access this feature. Make sure you are logged into WordPress.',
        'debug' => [
            'wp_loaded' => defined('ABSPATH'),
            'user_id' => $current_user_id,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'has_auth_cookie' => $has_auth_cookie,
            'cookie_name' => defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : 'Not defined'
        ]
    ]);
    exit;
}

/**
 * Helper function to call API endpoint
 */
function callAPI($url, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("cURL error: $curlError\n");
        return false;
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $responseData;
        }
    }
    
    return false;
}

// Get email by ID
if (isset($_GET['id'])) {
    $email_id = intval($_GET['id']);
    global $api_url;
    
    $result = callAPI($api_url . '/internal-emails/' . $email_id);
    
    if ($result && isset($result['data'])) {
        echo json_encode($result['data']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Email not found']);
    }
    exit;
}

// Get email thread
if (isset($_GET['thread_id'])) {
    $thread_id = intval($_GET['thread_id']);
    global $api_url;
    
    $result = callAPI($api_url . '/internal-emails/thread/' . $thread_id);
    
    if ($result && isset($result['data'])) {
        echo json_encode($result['data']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Thread not found']);
    }
    exit;
}

// Create or update email
if (isset($_POST['receiver']) && isset($_POST['subject']) && isset($_POST['message']) && isset($_POST['is_draft'])) {
    $receiver = sanitize_text_field($_POST['receiver']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = sanitize_textarea_field($_POST['message']);
    $is_draft = (int)$_POST['is_draft'];
    $draft_id = isset($_POST['draft_id']) ? intval($_POST['draft_id']) : null;
    $parent_email_id = isset($_POST['parent_email_id']) ? intval($_POST['parent_email_id']) : null;
    
    global $api_url, $current_user_id;
    
    $requestData = [
        'sender_id' => $current_user_id,
        'receiver_id' => $receiver,
        'subject' => $subject,
        'message' => $message,
        'is_draft' => $is_draft,
        'parent_email_id' => $parent_email_id
    ];
    
    if ($draft_id) {
        $requestData['draft_id'] = $draft_id;
    }
    
    $result = callAPI($api_url . '/internal-emails', 'POST', $requestData);
    
    if ($result && isset($result['data']['status']) && $result['data']['status'] === 'success') {
        http_response_code(200);
        echo json_encode($result['data']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create/update email']);
    }
    exit;
}

// Search users
if (isset($_GET['query'])) {
    $search_term = sanitize_text_field($_GET['query']);
    global $api_url;
    
    $result = callAPI($api_url . '/internal-emails/users/search?query=' . urlencode($search_term) . '&limit=20');
    
    if ($result && isset($result['data'])) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Get emails by type
$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
if ($type) {
    global $api_url, $current_user_id;
    
    $endpoint = '';
    if ($type === 'inbox') {
        $endpoint = '/internal-emails/inbox?user_id=' . $current_user_id;
    } else if ($type === 'sent') {
        $endpoint = '/internal-emails/sent?user_id=' . $current_user_id;
    } else if ($type === 'draft') {
        $endpoint = '/internal-emails/draft?user_id=' . $current_user_id;
    }
    
    if ($endpoint) {
        $result = callAPI($api_url . $endpoint);
        
        if ($result && isset($result['data'])) {
            echo json_encode($result['data']);
        } else {
            echo json_encode([]);
        }
        exit;
    }
}

// Check if this is a direct access (no parameters at all)
$has_get_params = !empty($_GET);
$has_post_params = !empty($_POST);
$is_direct_access = !$has_get_params && !$has_post_params;

// Allow viewing API documentation with ?help=1 parameter
if (isset($_GET['help']) && $_GET['help'] == '1') {
    http_response_code(200);
    echo json_encode([
        'message' => 'Internal Email API Endpoint',
        'description' => 'This is an API endpoint for internal email functionality',
        'available_endpoints' => [
            'GET ?id={email_id}' => 'Get email by ID',
            'GET ?thread_id={thread_id}' => 'Get email thread by thread ID',
            'GET ?query={search_term}' => 'Search users by name or email',
            'GET ?type=inbox' => 'Get inbox emails for current user',
            'GET ?type=sent' => 'Get sent emails for current user',
            'GET ?type=draft' => 'Get draft emails for current user',
            'POST' => 'Create or update email (requires: receiver, subject, message, is_draft)',
            'GET ?help=1' => 'Show this API documentation'
        ],
        'authentication' => 'User must be logged into WordPress',
        'current_user_id' => $current_user_id,
        'status' => 'authenticated'
    ]);
    exit;
}

if ($is_direct_access) {
    // Direct access without parameters - return empty array
    // This prevents errors in frontend code that expects an array
    http_response_code(200);
    echo json_encode([]);
    exit;
}

// Invalid request with parameters but doesn't match any endpoint
$request_info = [
    'error' => 'Invalid request',
    'message' => 'The request does not match any valid endpoint',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'get_params' => $_GET,
    'post_params' => !empty($_POST) ? array_keys($_POST) : [],
    'available_endpoints' => [
        'GET ?id=...' => 'Get email by ID',
        'GET ?thread_id=...' => 'Get email thread',
        'GET ?query=...' => 'Search users',
        'GET ?type=inbox|sent|draft' => 'Get emails by type',
        'POST receiver, subject, message, is_draft' => 'Create/update email'
    ]
];

// Log detailed information
error_log("Invalid request to tpl_internal_email_backend.php: " . json_encode($request_info));
error_log("Full REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("Full QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'N/A'));
error_log("Full GET: " . json_encode($_GET));
error_log("Full POST: " . json_encode($_POST));

// Return helpful error message
http_response_code(400);
echo json_encode($request_info);
exit;

?>
