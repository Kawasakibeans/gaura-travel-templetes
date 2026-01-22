<?php
/**
 * Template Name: BOM Agent Access Rights Data
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined');
}

$apiBaseUrl = API_BASE_URL;

function fetch_bom_user_rights_api(string $endpoint, array $params = []): array {
    global $apiBaseUrl;

    $url = rtrim($apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        throw new RuntimeException('API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid API response: ' . json_last_error_msg());
    }

    if (($decoded['status'] ?? '') !== 'success') {
        $message = $decoded['message'] ?? 'Unknown API error';
        throw new RuntimeException($message);
    }

    return $decoded['data'] ?? [];
}

// Custom pagination parameter to avoid conflicts with WordPress
$per_page = 20; // Items per page
$page = isset($_GET['agent_page']) ? max(1, intval($_GET['agent_page'])) : 1;
$offset = ($page - 1) * $per_page;

try {
    $listData = fetch_bom_user_rights_api('user-rights/bom/agents', [
        'limit' => $per_page,
        'page' => $page,
    ]);
    $countData = fetch_bom_user_rights_api('user-rights/bom/agents/count');
} catch (Throwable $e) {
    wp_die('Failed to load BOM agent data: ' . esc_html($e->getMessage()));
}

$agents = $listData['agents'] ?? [];
$allKeys = $listData['all_keys'] ?? [];
$total_rows = isset($countData['total']) ? (int)$countData['total'] : (int)($listData['total'] ?? 0);
$total_rows = max(0, $total_rows);
$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $per_page) : 1;

sort($allKeys);

// Helper function for pagination links
function get_agent_page_link($page_number) {
    return esc_url(add_query_arg('agent_page', $page_number));
}
?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #fffdf5; }
.page-header { text-align: center; background-color: #ffbb00; padding: 15px; border-radius: 12px; color: #333; font-size: 24px; font-weight: bold; margin-bottom: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
table.agent-access { border-collapse: collapse; width: 100%; background-color: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
table.agent-access th, table.agent-access td { padding: 8px 10px; text-align: center; border: 1px solid #f3e1a2; }
table.agent-access th { background-color: #ffcc33; color: #333; font-weight: bold; }
table.agent-access tr:hover { background-color: #fff6d1; }
.tick { color: green; font-weight: bold; }
table.agent-access th:first-child, table.agent-access td:first-child { text-align: left; font-weight: bold; }
.pagination { margin: 20px 0; text-align: center; }
.pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #ffcc33; border-radius: 4px; text-decoration: none; color: #333; background-color: #fff; }
.pagination a:hover { background-color: #ffcc33; }
.pagination .current { background-color: #ffcc33; font-weight: bold; }
.pagination-info { text-align: center; margin-bottom: 15px; color: #666; }
</style>

<div class="page-header">Agent Access Rights Overview</div>

<div class="pagination-info">
    Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> agents
</div>

<table class="agent-access">
    <thead>
        <tr>
            <th>Agent Name</th>
            <th>Sale Manager</th>
            <?php foreach ($allKeys as $key): ?>
                <th><?php 
                    $readable = str_replace('_', ' ', $key);  // replace _ with space
                    echo htmlspecialchars(ucwords($readable));  // capitalize each word
                ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($agents as $agent): ?>
            <tr>
                <td><?php echo htmlspecialchars($agent['agent_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($agent['sale_manager'] ?? ''); ?></td>
                <?php foreach ($allKeys as $key): ?>
                    <?php if (isset($agent['meta_array'][$key]) && $agent['meta_array'][$key]): ?>
                        <td class="tick">&#10004;</td>
                    <?php else: ?>
                        <td></td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?php echo get_agent_page_link(1); ?>">&laquo; First</a>
        <a href="<?php echo get_agent_page_link($page - 1); ?>">&lsaquo; Prev</a>
    <?php endif; ?>

    <?php 
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);

    if ($start_page > 1) echo '<span>...</span>';

    for ($i = $start_page; $i <= $end_page; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo get_agent_page_link($i); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor;

    if ($end_page < $total_pages) echo '<span>...</span>';
    ?>

    <?php if ($page < $total_pages): ?>
        <a href="<?php echo get_agent_page_link($page + 1); ?>">Next &rsaquo;</a>
        <a href="<?php echo get_agent_page_link($total_pages); ?>">Last &raquo;</a>
    <?php endif; ?>
</div>
