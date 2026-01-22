<?php
/**
 * Template Name: Agent Compliance
 * Template Post Type: post, page
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sri Harshan
 */
get_header();
date_default_timezone_set("Australia/Melbourne");
include('wp-config-custom.php');

// API Configuration

if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__, 5) . '/wp-config.php');
}

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined in wp-config.php');
}

// Use API_BASE_URL as defined (should point to /public or /public/v1)
$apiBaseUrl = rtrim(API_BASE_URL, '/');

// Helper to build an endpoint path respecting whether API_BASE_URL already ends with /v1
function build_api_endpoint(string $path): string {
    global $apiBaseUrl;
    $base = rtrim($apiBaseUrl, '/');
    if (str_ends_with($base, '/v1')) {
        // API_BASE_URL already has /v1
        return $base . $path;
    }
    return $base . '/v1' . $path;
}

// API Helper Functions
function fetchAutoIdsForUpdateFromAPI(int $sinceDays = 10, ?string $agentName = null, ?string $date = null): array {
    global $apiBaseUrl;
    $endpoint = build_api_endpoint('/agent-inbound-call/auto-ids-for-update');
    
    $params = [
        'since_days' => $sinceDays
    ];
    
    if ($agentName !== null && $agentName !== '') {
        $params['agent_name'] = $agentName;
    }
    
    if ($date !== null && $date !== '') {
        $params['date'] = $date;
    }
    
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-inbound-call/auto-ids-for-update: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-inbound-call/auto-ids-for-update: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-inbound-call/auto-ids-for-update: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return array_column($data['data'], 'auto_id');
        } elseif (isset($data['auto_ids']) && is_array($data['auto_ids'])) {
            return $data['auto_ids'];
        } elseif (is_array($data) && !empty($data)) {
            return array_column($data, 'auto_id');
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-inbound-call/auto-ids-for-update: " . $e->getMessage());
        return [];
    }
}

function updateInboundCallFlagsFromAPI(int $autoId, int $malpractice, int $profanity, int $misbehavior): bool {
    global $apiBaseUrl;
    $endpoint = build_api_endpoint('/agent-inbound-call/update-flags');
    
    $requestBody = [
        'auto_id' => $autoId,
        'malpractice' => $malpractice,
        'profanity' => $profanity,
        'misbehavior' => $misbehavior
    ];
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-inbound-call/update-flags: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for agent-inbound-call/update-flags: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-inbound-call/update-flags: " . json_last_error_msg() . ", Response: " . $response);
            return false;
        }
        
        return isset($data['success']) ? $data['success'] : ($httpCode === 200 || $httpCode === 201);
    } catch (Exception $e) {
        error_log("API Exception for agent-inbound-call/update-flags: " . $e->getMessage());
        return false;
    }
}

function fetchDistinctAgentNamesFromAPI(): array {
    global $apiBaseUrl;
    $endpoint = build_api_endpoint('/agent-codes-distinct-agent-names');
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-codes-distinct-agent-names: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-codes-distinct-agent-names: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-codes-distinct-agent-names: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return array_column($data['data'], 'agent_name');
        } elseif (isset($data['agent_names']) && is_array($data['agent_names'])) {
            return $data['agent_names'];
        } elseif (is_array($data) && !empty($data)) {
            return array_column($data, 'agent_name');
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-codes-distinct-agent-names: " . $e->getMessage());
        return [];
    }
}

function fetchDistinctTeamNamesFromAPI(): array {
    global $apiBaseUrl;
    $endpoint = build_api_endpoint('/agent-codes-distinct-team-names');
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-codes-distinct-team-names: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-codes-distinct-team-names: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-codes-distinct-team-names: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return array_column($data['data'], 'team_name');
        } elseif (isset($data['team_names']) && is_array($data['team_names'])) {
            return $data['team_names'];
        } elseif (is_array($data) && !empty($data)) {
            return array_column($data, 'team_name');
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-codes-distinct-team-names: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch inbound calls for display (filtered)
 */
function fetchInboundCallsFromAPI(?string $fromDate = null, ?string $toDate = null, ?string $agentName = null): array {
    global $apiBaseUrl;
    $endpoint = build_api_endpoint('/agent-inbound-call/list');
    $params = [];
    if (!empty($fromDate)) $params['from_date'] = $fromDate;
    if (!empty($toDate)) $params['to_date'] = $toDate;
    if (!empty($agentName)) $params['agent_name'] = $agentName;
    if (!empty($params)) {
        $endpoint .= '?' . http_build_query($params);
    }

    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-inbound-call/list: " . $curlError);
            return [];
        }
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-inbound-call/list: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-inbound-call/list: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        if (isset($data['rows']) && is_array($data['rows'])) {
            return $data['rows'];
        }
        if (is_array($data)) {
            return $data;
        }
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-inbound-call/list: " . $e->getMessage());
        return [];
    }
}

// Get filters (from GET or empty)
$filter_agent_name = $_GET['filter_agent_name'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

$yesterday = date('Y-m-d', strtotime('-10 day'));

// --- Handle POST updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only allow updates if filters are applied
    if (empty($filter_date) && empty($filter_agent_name)) {
        // Redirect back without processing
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Calculate since_days from yesterday date
    $sinceDays = (int)ceil((time() - strtotime($yesterday)) / 86400);
    
    // Fetch auto_ids from API
    $auto_ids = fetchAutoIdsForUpdateFromAPI($sinceDays, $filter_agent_name ?: null, $filter_date ?: null);

    // Update only visible rows
    foreach ($auto_ids as $auto_id) {
        $malpractice = isset($_POST['malpractice'][$auto_id]) ? intval($_POST['malpractice'][$auto_id]) : 0;
        $profanity = isset($_POST['profanity'][$auto_id]) ? intval($_POST['profanity'][$auto_id]) : 0;
        $misbehavior = isset($_POST['misbehavior'][$auto_id]) ? intval($_POST['misbehavior'][$auto_id]) : 0;

        updateInboundCallFlagsFromAPI($auto_id, $malpractice, $profanity, $misbehavior);
    }

    // Redirect after POST (keep filters in URL for user convenience)
    $redirectUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query([
        'filter_date' => $filter_date,
        'filter_agent_name' => $filter_agent_name
    ]);
    header("Location: $redirectUrl");
    exit;
}

// --- Fetch distinct agent names and team names for filter form ---
$agentNames = fetchDistinctAgentNamesFromAPI();
$teamNames = fetchDistinctTeamNamesFromAPI();
?>

<!-- Include jQuery UI CSS and JS -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
jQuery(document).ready(function($) {
    $('#filter_date').datepicker({
        dateFormat: 'yy-mm-dd'
    });

    $('#filter_agent_name').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: '/wp-content/themes/twentytwenty/templates/ajax-agent-search.php', // Adjust path if needed
                dataType: 'json',
                data: { term: request.term },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2
    });
});
</script>

<form method="GET" action="">
    <div class="filter-container">
        <div class="filter-field">
            <label for="filter_date">Filter by Date:</label>
            <input type="text" id="filter_date" name="filter_date" autocomplete="off" value="<?php echo esc_attr($filter_date); ?>">
        </div>
        <div class="filter-field">
            <label for="filter_agent_name">Filter by Agent Name:</label>
            <input type="text" id="filter_agent_name" name="filter_agent_name" autocomplete="off" value="<?php echo esc_attr($filter_agent_name); ?>">
        </div>
        <div class="filter-field">
            <input type="submit" value="Filter">
        </div>
    </div>
</form>

<?php
// --- Fetch filtered data for display via API ---
// Only fetch data if at least one filter is provided
if (!empty($filter_date) || !empty($filter_agent_name)) {
    $resultsData = fetchInboundCallsFromAPI($filter_date ?: null, $filter_date ?: null, $filter_agent_name ?: null);
} else {
    $resultsData = []; // No data until filters are applied
}

// --- Display data table ---
echo '<h2>Filtered Results</h2>';

// Show message if no filters applied
if (empty($filter_date) && empty($filter_agent_name)) {
    echo '<p style="padding: 15px; background-color: #fff3cd; border: 1px solid #ffc107; color: #856404; margin: 20px 0; border-radius: 4px;">';
    echo '<strong>Please apply at least one filter (Date or Agent Name) to view results.</strong>';
    echo '</p>';
}

echo '<form method="POST" action="">';
echo '<table>';
echo '<thead><tr><th>Date</th><th>Agent Name</th><th>Team Name</th><th>Malpractice</th><th>Profanity</th><th>Misbehavior</th></tr></thead><tbody>';

if (!empty($resultsData)) {
    foreach ($resultsData as $row) {
        $callDate = $row['call_date'] ?? '';
        $agentName = $row['agent_name'] ?? '';
        $teamName = $row['team_name'] ?? '';
        $autoId = $row['auto_id'] ?? null;
        $malpracticeVal = isset($row['malpractice']) ? (int)$row['malpractice'] : 0;
        $profanityVal = isset($row['profanity']) ? (int)$row['profanity'] : 0;
        $misbehaviorVal = isset($row['misbehavior']) ? (int)$row['misbehavior'] : 0;

        if ($autoId === null) {
            continue;
        }

        echo '<tr>';
        echo '<td>' . esc_html($callDate) . '</td>';
        echo '<td>' . esc_html($agentName) . '</td>';
        echo '<td>' . esc_html($teamName) . '</td>';

        // Hidden input to submit "0" when unchecked + checkbox with value "1"
        echo '<td><input type="hidden" name="malpractice[' . $autoId . ']" value="0">';
        echo '<input type="checkbox" name="malpractice[' . $autoId . ']" value="1" ' . ($malpracticeVal ? 'checked' : '') . '></td>';

        echo '<td><input type="hidden" name="profanity[' . $autoId . ']" value="0">';
        echo '<input type="checkbox" name="profanity[' . $autoId . ']" value="1" ' . ($profanityVal ? 'checked' : '') . '></td>';

        echo '<td><input type="hidden" name="misbehavior[' . $autoId . ']" value="0">';
        echo '<input type="checkbox" name="misbehavior[' . $autoId . ']" value="1" ' . ($misbehaviorVal ? 'checked' : '') . '></td>';
        echo '</tr>';
    }
} else {
    if (!empty($filter_date) || !empty($filter_agent_name)) {
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px;">No records found for the applied filters.</td></tr>';
    } else {
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">Please apply filters to view data.</td></tr>';
    }
}
echo '</tbody></table>';

// Only show Update button if there's data to update
if (!empty($resultsData)) {
    echo '<input type="submit" value="Update">';
}
echo '</form>';
?>

<style>
/* Hide dropdowns by default on this template to avoid About Us auto expand */
body.page-template-agent_compliance .sub-menu,
body.page-template-agent_compliance-php .sub-menu,
body.page-template-agent-compliance .sub-menu {
    display: none !important;
    visibility: hidden !important;
}
body.page-template-agent_compliance .menu-item-has-children:hover > .sub-menu,
body.page-template-agent_compliance-php .menu-item-has-children:hover > .sub-menu,
body.page-template-agent-compliance .menu-item-has-children:hover > .sub-menu,
body.page-template-agent_compliance .menu-item-has-children:focus-within > .sub-menu,
body.page-template-agent_compliance-php .menu-item-has-children:focus-within > .sub-menu,
body.page-template-agent-compliance .menu-item-has-children:focus-within > .sub-menu {
    display: block !important;
    visibility: visible !important;
}

.filter-container, .form-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 60px;
    margin-bottom: 20px;
}
.filter-field, .form-field {
    width: 30%;
    min-width: 200px;
    margin-bottom: 10px;
}
.filter-field label, .form-field label {
    display: block;
    font-weight: bold;
}
table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}
table, th, td {
    border: 1px solid #ddd;
}
th, td {
    padding: 8px;
    text-align: left;
}
th {
    background-color: #f4f4f4;
}
</style>

<?php
get_footer();