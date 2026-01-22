<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Australia/Melbourne');

define('DB_HOST', 'localhost');
define('DB_USER', 'gaurat_sriharan');
define('DB_PASS', 'r)?2lc^Q0cAE');
define('DB_NAME', 'gaurat_gauratravel');

function db(): mysqli {
    static $cx = null;
    if ($cx instanceof mysqli) return $cx;
    $cx = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($cx->connect_error) die('DB connection error');
    $cx->set_charset('utf8mb4');
    return $cx;
}

function csv_safe($v) {
    $v = (string)$v;
    if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'])) return "'".$v;
    return $v;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function num($v){ return is_numeric($v) ? (float)$v : 0.0; }
function fmtMoney($v){
    if (!is_numeric($v)) return '';
    return number_format((float)$v, 2, '.', '');
}

$start = trim($_GET['start_date'] ?? '');
$end   = trim($_GET['end_date'] ?? '');
if ($start==='' || $end==='') {
    $start = date('Y-m-01');
    $end   = date('Y-m-t');
}
$reportName = trim($_GET['report'] ?? 'trams_g360_match');

$mysqli = db();

/* Subqueries filtered by date */
$tSub = "
  SELECT invoicelink_no, order_amnt, net_due
  FROM wpk4_backend_trams_booking_invoice_reconciliation
  WHERE ISSUEDATE BETWEEN ? AND ?
";
$gSub = "
  SELECT Invoicelink_no, order_amnt, transaction_amount_inr AS net_due
  FROM wpk4_backend_ticket_reconciliation
  WHERE issue_date BETWEEN ? AND ?
";

/* 1) invoicelink_no match */
$sqlMatches = "
  SELECT 
    t.invoicelink_no AS invoicelink_no_trams,
    t.order_amnt     AS order_amnt_trams,
    t.net_due        AS net_due_trams,
    g.Invoicelink_no AS invoicelink_no_g360,
    g.order_amnt     AS order_amnt_g360,
    g.net_due        AS net_due_g360
  FROM ($tSub) AS t
  INNER JOIN ($gSub) AS g
    ON t.invoicelink_no = g.Invoicelink_no
";
$stmt = $mysqli->prepare($sqlMatches);
$stmt->bind_param('ssss', $start, $end, $start, $end);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* 2) TRAMS only */
$sqlTramsOnly = "
  SELECT 
    t.invoicelink_no AS invoicelink_no_trams,
    t.order_amnt     AS order_amnt_trams,
    t.net_due        AS net_due_trams,
    NULL             AS invoicelink_no_g360,
    NULL             AS order_amnt_g360,
    NULL             AS net_due_g360
  FROM ($tSub) AS t
  LEFT JOIN ($gSub) AS g
    ON t.invoicelink_no = g.Invoicelink_no
  WHERE g.Invoicelink_no IS NULL
";
$stmt = $mysqli->prepare($sqlTramsOnly);
$stmt->bind_param('ssss', $start, $end, $start, $end);
$stmt->execute();
$tramsOnly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* 3) G360 only */
$sqlGOnly = "
  SELECT 
    NULL             AS invoicelink_no_trams,
    NULL             AS order_amnt_trams,
    NULL             AS net_due_trams,
    g.Invoicelink_no AS invoicelink_no_g360,
    g.order_amnt     AS order_amnt_g360,
    g.net_due        AS net_due_g360
  FROM ($gSub) AS g
  LEFT JOIN ($tSub) AS t
    ON t.invoicelink_no = g.Invoicelink_no
  WHERE t.invoicelink_no IS NULL
";
$stmt = $mysqli->prepare($sqlGOnly);
$stmt->bind_param('ssss', $start, $end, $start, $end);
$stmt->execute();
$gOnly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Merge */
$rows = array_merge($matches, $tramsOnly, $gOnly);

/* Compute diffs */
foreach ($rows as &$r) {
    $amtT = num($r['order_amnt_trams'] ?? 0);
    $amtG = num($r['order_amnt_g360'] ?? 0);
    $netT = num($r['net_due_trams'] ?? 0);
    $netG = num($r['net_due_g360'] ?? 0);

    $r['order_amnt_trams'] = $amtT;
    $r['order_amnt_g360']  = $amtG;
    $r['order_amnt_diff']  = $amtG - $amtT;

    $r['net_due_trams'] = $netT;
    $r['net_due_g360']  = $netG;
    $r['net_due_diff']  = $netG - $netT;
}
unset($r);

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
            csv_safe(fmtMoney($r['order_amnt_trams'])),
            csv_safe(fmtMoney($r['net_due_trams'])),
            csv_safe($r['invoicelink_no_g360'] ?? ''),
            csv_safe(fmtMoney($r['order_amnt_g360'])),
            csv_safe(fmtMoney($r['net_due_g360'])),
            csv_safe(fmtMoney($r['order_amnt_diff'])),
            csv_safe(fmtMoney($r['net_due_diff'])),
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
<h1><?=h($reportName)?> — Match by <code>invoicelink_no</code> only</h1>
<form method="get">
  <div><label>Report</label><input type="text" name="report" value="<?=h($reportName)?>"></div>
  <div><label>Start date</label><input type="date" name="start_date" value="<?=h($start)?>"></div>
  <div><label>End date</label><input type="date" name="end_date" value="<?=h($end)?>"></div>
  <div><button type="submit">Filter</button></div>
  <div><?php
    $qs = ['report'=>$reportName,'start_date'=>$start,'end_date'=>$end,'export'=>'1'];
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
