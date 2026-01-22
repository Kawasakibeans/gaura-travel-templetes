<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Australia/Melbourne');

$wpConfig = dirname(__FILE__, 5) . '/wp-config.php';
if (file_exists($wpConfig)) {
    require_once $wpConfig;
}

if (!defined('API_BASE_URL')) {
    throw new RuntimeException('API_BASE_URL is not defined');
}

$apiBaseUrl = API_BASE_URL;

function call_trams_reconciliation_api(array $query = []): array {
    global $apiBaseUrl;

    $url = rtrim($apiBaseUrl, '/') . '/trams-reconciliation';
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException('API request failed: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('API request failed with status ' . $httpCode);
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid API response: ' . json_last_error_msg());
    }

    if (($decoded['status'] ?? '') !== 'success') {
        $message = $decoded['message'] ?? 'Unknown API error';
        throw new RuntimeException($message);
    }

    return $decoded['data'] ?? [];
}

function csv_safe($v) {
    $v = (string)$v;
    if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'])) return "'".$v;
    return $v;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmtMoney($v){
    if ($v === '' || $v === null) return '';
    if (!is_numeric($v)) return (string)$v;
    return number_format((float)$v, 2, '.', '');
}

$start = trim($_GET['start_date'] ?? '');
$end   = trim($_GET['end_date'] ?? '');
if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end   = date('Y-m-t');
}
$reportName = trim($_GET['report'] ?? 'trams_g360_match');
$filterType = trim($_GET['filter'] ?? 'all') ?: 'all';

try {
    $apiPayload = call_trams_reconciliation_api([
        'start_date' => $start,
        'end_date' => $end,
        'filter' => $filterType,
    ]);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to load TRAMS reconciliation data: ' . $e->getMessage();
    exit;
}

$rows = $apiPayload['data'] ?? [];
$summary = $apiPayload['summary'] ?? [
    'matched' => 0,
    'mismatched' => 0,
    'trams_only' => 0,
    'g360_only' => 0,
];
$totalRecords = $apiPayload['total_records'] ?? count($rows);

/* CSV Export */
if (isset($_GET['export']) && $_GET['export']==='1') {
    $filename = preg_replace('/[^a-zA-Z0-9_\-]/','_',$reportName).'_'.date('Ymd_His').'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, [
        'invoicelink_no_trams','order_amnt_trams','net_due_trams',
        'invoicelink_no_g360','order_amnt_g360','net_due_g360',
        'order_amnt_diff (G360-TRAMS)','net_due_diff (G360-TRAMS)'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            csv_safe($r['invoicelink_no_trams'] ?? ''),
            csv_safe(fmtMoney($r['order_amnt_trams'] ?? '')),
            csv_safe(fmtMoney($r['net_due_trams'] ?? '')),
            csv_safe($r['invoicelink_no_g360'] ?? ''),
            csv_safe(fmtMoney($r['order_amnt_g360'] ?? '')),
            csv_safe(fmtMoney($r['net_due_g360'] ?? '')),
            csv_safe(fmtMoney($r['order_amnt_diff'] ?? '')),
            csv_safe(fmtMoney($r['net_due_diff'] ?? '')),
        ]);
    }
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=h($reportName)?> (TRAMS vs G360)</title>
<style>
body{font-family:sans-serif;margin:24px}
h1{margin-bottom:16px}
form{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
label{display:block;font-size:13px;color:#555}
input[type=date],input[type=text]{padding:6px 8px;border:1px solid #ccc;border-radius:6px}
button,.btn{padding:7px 14px;border:none;border-radius:6px;background:#222;color:#fff;cursor:pointer;font-weight:600;text-decoration:none}
table{border-collapse:collapse;width:100%}
th,td{padding:8px 10px;border-top:1px solid #ddd;white-space:nowrap}
thead th{border-bottom:2px solid #333;font-size:12px;color:#444}
tbody tr:hover{background:#fafafa}
.pos{color:#0a7d32;font-weight:600}
.neg{color:#b00020;font-weight:600}
</style>
</head>
<body>
<h1><?=h($reportName)?> — Match by <code>invoicelink_no</code> only (<?=h((string)$totalRecords)?> rows)</h1>
<form method="get">
  <div><label>Report</label><input type="text" name="report" value="<?=h($reportName)?>"></div>
  <div><label>Start date</label><input type="date" name="start_date" value="<?=h($start)?>"></div>
  <div><label>End date</label><input type="date" name="end_date" value="<?=h($end)?>"></div>
  <input type="hidden" name="filter" value="<?=h($filterType)?>">
  <div><button type="submit">Filter</button></div>
  <div><?php
    $qs = [
        'report'=>$reportName,
        'start_date'=>$start,
        'end_date'=>$end,
        'filter'=>$filterType,
        'export'=>'1'
    ];
    $href = h($_SERVER['PHP_SELF'].'?'.http_build_query($qs));
  ?><a class="btn" href="<?=$href?>">Export CSV</a></div>
</form>
<table>
<thead><tr>
  <th>invoicelink_no_trams</th><th>order_amnt_trams</th><th>net_due_trams</th>
  <th>invoicelink_no_g360</th><th>order_amnt_g360</th><th>net_due_g360</th>
  <th>order_amnt_diff</th><th>net_due_diff</th>
</tr></thead>
<tbody>
<?php if(!$rows): ?><tr><td colspan="8">No data</td></tr>
<?php else: foreach($rows as $r): ?>
<tr>
  <td><?=h($r['invoicelink_no_trams']??'')?></td>
  <td><?=fmtMoney($r['order_amnt_trams'])?></td>
  <td><?=fmtMoney($r['net_due_trams'])?></td>
  <td><?=h($r['invoicelink_no_g360']??'')?></td>
  <td><?=fmtMoney($r['order_amnt_g360'])?></td>
  <td><?=fmtMoney($r['net_due_g360'])?></td>
  <td class="<?=$r['order_amnt_diff']==0?'':($r['order_amnt_diff']>0?'pos':'neg')?>"><?=fmtMoney($r['order_amnt_diff'])?></td>
  <td class="<?=$r['net_due_diff']==0?'':($r['net_due_diff']>0?'pos':'neg')?>"><?=fmtMoney($r['net_due_diff'])?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
</body>
</html>
