<?php
/**
 * Template Name: Escalation Data Metrics Agent Wise
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');


// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
// if ($mysqli->connect_error) {
//     echo "Database connection failed";
//     exit;
// }
// ============================================================================

/**
 * Make end date inclusive: [start, end+1d)
 */
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// $startBound = $mysqli->real_escape_string($startDate) . ' 00:00:00';
// $endBound   = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';
// ============================================================================

$startBound = $startDate . ' 00:00:00';
$endBound   = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

/**
 * Helper: create safe column alias slugs from escalation type labels
 */
function slug_col($label) {
    // Replace non-word chars with underscores, trim, lowercase
    $s = preg_replace('/[^\w]+/u', '_', (string)$label);
    $s = trim($s, '_');
    if ($s === '') $s = 'x';
    if (preg_match('/^\d/', $s)) $s = '_' . $s; // avoid leading digits
    return strtolower($s);
}

/**
 * Keep only escalation types that actually appear in the filtered data.
 * This recreates the "dynamic columns per date range" behavior.
 */
function filterEscalationTypesByData(array $types, array $data): array {
    if (empty($data)) {
        return []; // No data in range => no dynamic columns
    }

    // Map base types by slug to label for de-duping
    $baseSlugToLabel = [];
    foreach ($types as $type) {
        $baseSlugToLabel[slug_col($type)] = $type;
    }

    // Slug => label for any type that actually appears in data
    $includedBySlug = [];

    // First pass: keep original order of provided types, but only if counts > 0
    foreach ($data as $row) {
        if (!is_array($row)) { continue; }
        foreach ($types as $type) {
            $alias = 'type_' . slug_col($type);
            if (!empty($row[$alias])) {
                $slug = slug_col($type);
                $includedBySlug[$slug] = $baseSlugToLabel[$slug];
            }
        }
    }

    // Second pass: catch any type_* columns returned by API that were not in $types
    foreach ($data as $row) {
        if (!is_array($row)) { continue; }
        foreach ($row as $key => $val) {
            if (strpos($key, 'type_') === 0 && (int)$val > 0) {
                $alias = substr($key, 5); // remove "type_"
                // Normalize alias to slug to avoid duplicates (e.g., uppercase vs title-case)
                $slug = slug_col(str_replace('_', ' ', $alias));
                if (!isset($includedBySlug[$slug])) {
                    $label = str_replace('_', ' ', strtoupper($alias));
                    $includedBySlug[$slug] = $label;
                }
            }
        }
    }

    // Preserve the original order of $types; append extra labels sorted
    // To match the original template (distinct types ordered ASC), sort all included labels alphabetically
    $filtered = array_values($includedBySlug);
    natcasesort($filtered);
    return array_values($filtered);
}

/**
 * Fetch distinct escalation types limited to date range
 */
// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// $escalationTypes = [];
// $typeSql = "
//   SELECT DISTINCT escalation_type
//   FROM wpk4_backend_travel_escalations
//   WHERE escalation_type IS NOT NULL AND escalation_type <> ''
//     AND escalated_on >= '$startBound' AND escalated_on < '$endBound'
//   ORDER BY escalation_type ASC
// ";
// if ($typeResult = $mysqli->query($typeSql)) {
//     while ($row = $typeResult->fetch_assoc()) {
//         $escalationTypes[] = $row['escalation_type'];
//     }
// }
// ============================================================================

// Escalation types will be fetched from the main API call along with data
// This matches the original behavior where types are dynamically determined from the date range
$escalationTypes = [];

/**
 * AJAX: details for a given user, honoring date filter
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_escalation_details' && isset($_GET['user'])) {
    $user = sanitize_text_field($_GET['user']);

    // Accept explicit start/end for modal (fallback to page bounds)
    $sd = $_GET['start_date'] ?? $startDate;
    $ed = $_GET['end_date']   ?? $endDate;

    // ============================================================================
    // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
    // ============================================================================
    // $user = $mysqli->real_escape_string($_GET['user']);
    // $sdBound = $mysqli->real_escape_string($sd) . ' 00:00:00';
    // $edBound = date('Y-m-d', strtotime($ed . ' +1 day')) . ' 00:00:00';
    // 
    // $query = "
    //     SELECT 
    //         escalated_by,
    //         escalation_type,
    //         escalated_on,
    //         DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') AS formatted_time
    //     FROM wpk4_backend_travel_escalations
    //     WHERE escalated_by = '$user'
    //       AND escalated_on >= '$sdBound' AND escalated_on < '$edBound'
    //     ORDER BY escalated_on DESC
    // ";
    // 
    // $result = $mysqli->query($query);
    // $details = [];
    // if ($result) {
    //     while ($row = $result->fetch_assoc()) {
    //         $details[] = $row;
    //     }
    // }
    // ============================================================================
    
    // Fetch escalation details for user from API
    $api_url = API_BASE_URL;
    $url = API_BASE_URL . '/escalations-agentwise/user/' . urlencode($user);
    $params = [];
    if (!empty($sd)) {
        $params['start_date'] = $sd;
    }
    if (!empty($ed)) {
        $params['end_date'] = $ed;
    }
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    $details = [];
    
    if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['data'])) {
        // Service returns array directly, which is wrapped in 'data' by jsonResponse
        // jsonResponse wraps it: {status: "success", data: [...]}
        if (is_array($responseData['data'])) {
            // Check if it's an array of detail records (has numeric keys)
            $keys = array_keys($responseData['data']);
            if (!empty($keys) && (is_numeric($keys[0]) || isset($responseData['data'][0]))) {
                $details = $responseData['data'];
            } elseif (isset($responseData['data']['details']) && is_array($responseData['data']['details'])) {
                $details = $responseData['data']['details'];
            }
        }
    }

    if (count($details) === 0) {
        echo '<p class="no-data">No escalations for this user in the selected date range.</p>';
    } else {
        echo '<table>';
        echo '<thead><tr>
                <th>Escalated On</th>
                <th>Escalated By</th>
                <th>Escalation Type</th>
              </tr></thead><tbody>';

        foreach ($details as $row) {
            // Handle both database format and API format
            $formatted_time = $row['formatted_time'] ?? $row['escalated_on'] ?? '';
            $escalated_by = $row['escalated_by'] ?? '';
            $escalation_type = $row['escalation_type'] ?? '';
            
            echo '<tr class="detail-row">';
            echo '<td>' . htmlspecialchars($formatted_time) . '</td>';
            echo '<td>' . htmlspecialchars($escalated_by) . '</td>';
            echo '<td>' . htmlspecialchars($escalation_type) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    exit;
}

/**
 * Data: grouped by escalated_by with dynamic type columns
 */
function fetchEscalationDataByUser(string $startBound, string $endBound): array {
    global $escalationTypes, $startDate, $endDate;

    // ============================================================================
    // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
    // ============================================================================
    // $parts = ["SELECT COALESCE(escalated_by, '(unknown)') AS escalated_by"];
    // 
    // foreach ($escalationTypes as $type) {
    //     $escapedType = $mysqli->real_escape_string($type);
    //     $alias = 'type_' . slug_col($type);
    //     $parts[] = ", SUM(CASE WHEN escalation_type = '$escapedType' THEN 1 ELSE 0 END) AS `{$alias}`";
    // }
    // 
    // $parts[] = ", COUNT(*) AS total_escalations";
    // $parts[] = "FROM wpk4_backend_travel_escalations";
    // $parts[] = "WHERE escalated_on >= '$startBound' AND escalated_on < '$endBound'";
    // $parts[] = "GROUP BY escalated_by";
    // $parts[] = "ORDER BY escalated_by ASC";
    // 
    // $sql = implode("\n", $parts);
    // $data = [];
    // if ($res = $mysqli->query($sql)) {
    //     while ($row = $res->fetch_assoc()) {
    //         $data[] = $row;
    //     }
    // }
    // return $data;
    // ============================================================================
    
    // Use original startDate and endDate for API (not the bounds which have +1 day for endBound)
    // API expects original dates and will handle the [start, end+1d) range internally
    // $startBound and $endBound are only used for old database queries
    
    // Fetch escalation data from API
    $api_url = API_BASE_URL;
    $url = API_BASE_URL . '/escalations-agentwise';
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    $url .= '?' . http_build_query($params);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    $data = [];
    
    if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['data'])) {
        // Extract escalation types from API response
        if (isset($responseData['data']['escalation_types']) && is_array($responseData['data']['escalation_types'])) {
            $escalationTypes = $responseData['data']['escalation_types'];
        }
        
        // API returns data.data (array of rows) - Service returns ['data' => $data, 'escalation_types' => ...]
        // jsonResponse wraps it: {status: "success", data: {data: [...], escalation_types: [...]}}
        if (isset($responseData['data']['data']) && is_array($responseData['data']['data'])) {
            $data = $responseData['data']['data'];
        } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
            // Check if responseData['data'] is directly an array of rows (has numeric keys)
            $keys = array_keys($responseData['data']);
            if (!empty($keys) && (is_numeric($keys[0]) || (isset($responseData['data'][0]) && is_array($responseData['data'][0])))) {
                $data = $responseData['data'];
            }
        }
    }
    
    return $data;
}

$data_users = fetchEscalationDataByUser($startBound, $endBound);
// Filter escalation types based on actual data (dynamic columns per date range)
$escalationTypes = filterEscalationTypesByData($escalationTypes, $data_users);

/**
 * Render table
 */
function renderEscalationTableByUser(array $data, string $title): void {
    global $escalationTypes;

    echo "<h2 class='section-title'>" . htmlspecialchars($title) . "</h2>";
    echo "<table>";
    echo "<thead><tr><th>Escalated By</th>";
    foreach ($escalationTypes as $type) {
        echo "<th>" . htmlspecialchars($type) . "</th>";
    }
    echo "<th>Total</th></tr></thead><tbody>";

    if (count($data) === 0) {
        echo "<tr><td colspan='" . (count($escalationTypes) + 2) . "' class='no-data'>No data available for this period</td></tr>";
    } else {
        $totals = [];
        foreach ($escalationTypes as $t) {
            $totals[slug_col($t)] = 0;
        }
        $grandTotal = 0;

        foreach ($data as $row) {
            if (!is_array($row)) { continue; }
            $user = $row['escalated_by'] ?? '(unknown)';
            echo "<tr><td><a href='#' class='user-link' data-user='" . htmlspecialchars((string)$user, ENT_QUOTES) . "'>" . htmlspecialchars((string)$user) . "</a></td>";

            $rowTotal = 0;
            foreach ($escalationTypes as $type) {
                $alias = 'type_' . slug_col($type);
                $count = (int)($row[$alias] ?? 0);
                echo "<td>" . $count . "</td>";
                $totals[slug_col($type)] += $count;
                $rowTotal += $count;
            }
            echo "<td><strong>" . $rowTotal . "</strong></td></tr>";
            $grandTotal += $rowTotal;
        }

        echo "<tr style='font-weight:bold; background-color:#ffe680;'><td>Total</td>";
        foreach ($escalationTypes as $type) {
            echo "<td>" . (int)$totals[slug_col($type)] . "</td>";
        }
        echo "<td>" . (int)$grandTotal . "</td></tr>";
    }
    echo "</tbody></table>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Escalation Data Metrics</title>
    <style>
        :root {
            --primary: #ffbb00;
            --primary-dark: #e6a800;
            --primary-light: #fff3cc;
            --primary-very-light: #fffdf5;
            --text: #333333;
            --text-light: #666666;
            --border: #e0e0e0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
            background-color: #f9f9f9; color: var(--text); line-height: 1.6;
        }
        .container { max-width: 100vw; width: 100%; margin: 0; padding: 20px 0; }
        header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; padding: 25px 0; margin-bottom: 30px;
            border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;
        }
        h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .subtitle { font-size: 16px; opacity: 0.9; margin-top: 8px; }
        .filter-section {
            background-color: white; padding: 20px; border-radius: 8px;
            margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; }
        .filter-group { display: flex; flex-direction: column; }
        label { font-weight: 500; margin-bottom: 5px; color: var(--text-light); font-size: 14px; }
        input[type="date"] {
            padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; transition: border-color 0.3s;
        }
        input[type="date"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(255, 187, 0, 0.2); }
        button {
            background-color: var(--primary); color: #000; border: none; padding: 10px 20px;
            border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; align-self: flex-end;
        }
        button:hover { background-color: var(--primary-dark); transform: translateY(-1px); }
        .data-section { margin-bottom: 40px; }
        .section-title {
            font-size: 20px; font-weight: 600; color: var(--text);
            margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid var(--primary); display: inline-block;
        }
        table {
            width: 100%; margin-bottom: 30px; border-collapse: collapse; background: white;
            border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        th, td { padding: 12px 15px; text-align: center; border: 1px solid var(--border); }
        th {
            background-color: var(--primary); color: #000; font-weight: 600; text-transform: uppercase; font-size: 13px;
        }
        tr:nth-child(even) { background-color: var(--primary-very-light); }
        tr:hover { background-color: var(--primary-light); }
        .no-data { text-align: center; padding: 20px; color: var(--text-light); font-style: italic; }
        .date-range { font-size: 14px; color: var(--text-light); margin-bottom: 20px; font-style: italic; }
        a.date-link { color: var(--primary-dark); text-decoration: none; font-weight: 500; }
        a.date-link:hover { text-decoration: underline; }
        #escalationModal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); overflow: auto;
        }
        #escalationModal .modal-content {
            background: white; margin: 5% auto; padding: 25px; width: 90%; max-width: 900px;
            border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); position: relative;
        }
        #escalationModal .close-btn {
            position: absolute; right: 20px; top: 15px; font-size: 28px; font-weight: bold; color: #333; cursor: pointer;
        }
        #escalationModal h3 {
            margin-top: 0; color: var(--primary-dark); border-bottom: 2px solid var(--primary); padding-bottom: 10px;
        }
        #escalationModal table { margin-top: 15px; width: 100%; border-collapse: collapse; }
        #escalationModal th, #escalationModal td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        #escalationModal tr:nth-child(even) { background-color: #f7f7f7; }
        #escalationModal tr:hover { background-color: #fff3cc; }
        @media (max-width: 768px) {
            .filter-form { flex-direction: column; align-items: stretch; }
            button { align-self: stretch; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Travel Escalation Metrics</h1>
        <div class="subtitle">Escalation data grouped by user and type</div>
    </header>

    <div class="filter-section">
        <form method="get" class="filter-form">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <button type="submit">Apply Filter</button>
        </form>
    </div>

    <div class="date-range">
        Showing data from <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
    </div>

    <div class="data-section">
        <?php renderEscalationTableByUser($data_users, "Escalations by User"); ?>
    </div>
</div>

<!-- Modal -->
<div id="escalationModal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Escalation Details for <span id="modal-user"></span></h3>
        <div id="escalation-details"><p class="no-data">Loading details...</p></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Click handlers for user detail modal
    document.querySelectorAll('.user-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const user = this.getAttribute('data-user');
            showEscalationDetails(user);
        });
    });

    // Modal close
    document.querySelector('.close-btn').addEventListener('click', function() {
        document.getElementById('escalationModal').style.display = 'none';
    });
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('escalationModal');
        if (event.target === modal) modal.style.display = 'none';
    });
});

function showEscalationDetails(user) {
    const modal = document.getElementById('escalationModal');
    const modalUser = document.getElementById('modal-user');
    const detailsContainer = document.getElementById('escalation-details');

    modalUser.textContent = user;
    detailsContainer.innerHTML = '<p class="no-data">Loading details...</p>';
    modal.style.display = 'block';

    const url = new URL(window.location.href);
    const sd = url.searchParams.get('start_date') || '';
    const ed = url.searchParams.get('end_date') || '';
    const qs = `?action=get_escalation_details&user=${encodeURIComponent(user)}&start_date=${encodeURIComponent(sd)}&end_date=${encodeURIComponent(ed)}`;

    fetch(qs)
        .then(response => response.text())
        .then(data => { detailsContainer.innerHTML = data; })
        .catch(error => { detailsContainer.innerHTML = `<p class="no-data">Error loading details: ${error.message}</p>`; });
}
</script>
</body>
</html>
