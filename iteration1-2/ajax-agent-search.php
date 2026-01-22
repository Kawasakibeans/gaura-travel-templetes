<?php
// Load WordPress environment
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
date_default_timezone_set("Australia/Melbourne");

global $wpdb;

$term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

if (!$term) {
    echo json_encode([]);
    exit;
}

$like_term = '%' . $wpdb->esc_like($term) . '%';

$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DISTINCT agent_name FROM wpk4_backend_agent_codes WHERE agent_name LIKE %s ORDER BY agent_name ASC",
        $like_term
    ),
    ARRAY_A
);

$agent_names = array_map(function ($row) {
    return $row['agent_name'];
}, $results);

header('Content-Type: application/json');
echo json_encode($agent_names);
exit;
