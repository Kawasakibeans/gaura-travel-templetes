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
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) die("DB connection failed: " . $mysqli->connect_error);

// Get filters (from GET or empty)
$filter_agent_name = $_GET['filter_agent_name'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

$yesterday = date('Y-m-d', strtotime('-10 day'));

// --- Handle POST updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Build filtered query for auto_ids to update (use current filters)
    $updateQuery = "SELECT auto_id FROM wpk4_backend_agent_inbound_call where call_date >= '$yesterday'";

    if ($filter_agent_name) {
        $updateQuery .= " AND agent_name LIKE '%" . $mysqli->real_escape_string($filter_agent_name) . "%'";
    }
    if ($filter_date) {
        $updateQuery .= " AND call_date = '" . $mysqli->real_escape_string($filter_date) . "'";
    }

    $result_update = $mysqli->query($updateQuery);
    $auto_ids = [];
    while ($row = $result_update->fetch_assoc()) {
        $auto_ids[] = $row['auto_id'];
    }

    // Update only visible rows
    foreach ($auto_ids as $auto_id) {
        $malpractice = isset($_POST['malpractice'][$auto_id]) ? intval($_POST['malpractice'][$auto_id]) : 0;
        $profanity = isset($_POST['profanity'][$auto_id]) ? intval($_POST['profanity'][$auto_id]) : 0;
        $misbehavior = isset($_POST['misbehavior'][$auto_id]) ? intval($_POST['misbehavior'][$auto_id]) : 0;

        $stmt = $mysqli->prepare("UPDATE wpk4_backend_agent_inbound_call SET malpractice = ?, profanity = ?, misbehavior = ? WHERE auto_id = ?");
        if ($stmt) {
            $stmt->bind_param("iiii", $malpractice, $profanity, $misbehavior, $auto_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect after POST (keep filters in URL for user convenience)
    if (empty($filter_date) && empty($filter_agent_name)) {
        header('Location: /eod-sales-report/');
    } else {
        $redirectUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query([
            'filter_date' => $filter_date,
            'filter_agent_name' => $filter_agent_name
        ]);
        header("Location: /eod-sales-report/$redirectUrl");
    }
    exit;
}

// --- Fetch distinct agent names and team names for filter form ---
$agents = $mysqli->query("SELECT DISTINCT agent_name FROM wpk4_backend_agent_codes");
$teams = $mysqli->query("SELECT DISTINCT team_name FROM wpk4_backend_agent_codes");
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
// --- Fetch filtered data for display ---
$query = "SELECT * FROM wpk4_backend_agent_inbound_call WHERE call_date >= '$yesterday'";
if ($filter_agent_name) {
    $query .= " AND agent_name LIKE '%" . $mysqli->real_escape_string($filter_agent_name) . "%'";
}
if ($filter_date) {
    $query .= " AND call_date = '" . $mysqli->real_escape_string($filter_date) . "'";
}
$results = $mysqli->query($query);

// --- Display data table ---
echo '<h2>Filtered Results</h2>';
echo '<form method="POST" action="">';
echo '<table>';
echo '<thead><tr><th>Date</th><th>Agent Name</th><th>Team Name</th><th>Malpractice</th><th>Profanity</th><th>Misbehavior</th></tr></thead><tbody>';

if ($results->num_rows > 0) {
    while ($row = $results->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . esc_html($row['call_date']) . '</td>';
        echo '<td>' . esc_html($row['agent_name']) . '</td>';
        echo '<td>' . esc_html($row['team_name']) . '</td>';

        // Hidden input to submit "0" when unchecked + checkbox with value "1"
        echo '<td><input type="hidden" name="malpractice[' . $row['auto_id'] . ']" value="0">';
        echo '<input type="checkbox" name="malpractice[' . $row['auto_id'] . ']" value="1" ' . ($row['malpractice'] ? 'checked' : '') . '></td>';

        echo '<td><input type="hidden" name="profanity[' . $row['auto_id'] . ']" value="0">';
        echo '<input type="checkbox" name="profanity[' . $row['auto_id'] . ']" value="1" ' . ($row['profanity'] ? 'checked' : '') . '></td>';

        echo '<td><input type="hidden" name="misbehavior[' . $row['auto_id'] . ']" value="0">';
        echo '<input type="checkbox" name="misbehavior[' . $row['auto_id'] . ']" value="1" ' . ($row['misbehavior'] ? 'checked' : '') . '></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6">No records found.</td></tr>';
}
echo '</tbody></table>';
echo '<input type="submit" value="Update">';
echo '</form>';
?>

<style>
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
$mysqli->close();
get_footer();