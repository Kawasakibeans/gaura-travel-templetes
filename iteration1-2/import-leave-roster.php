<?php
/**
 * Import + Expand Leave Ranges → Daily Rows
 * Target table: wpk4_backend_employee_roster_leaves_approval
 *
 * CSV expected headers (case-insensitive, extra columns ignored):
 *  DocNo, Employee Code, Employee Name, Leave Type,
 *  From Date, From Date Value, Till Date, Till Date Value,
 *  Remarks, Current Status
 *
 * Tips:
 *  - DRY_RUN=true first to preview.
 *  - Add the UNIQUE KEY below to avoid duplicates if you re-run.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Australia/Melbourne');

/* ====== CONFIG ====== */
$table = 'wpk4_backend_employee_roster_leaves_approval';
$DRY_RUN = isset($_POST['dryrun']) ? true: false; // default: preview first
/* ====== Load WP config for API_BASE_URL ====== */
$php_config_path = '/home/gt1ybwhome/public_html/wp-config.php';
if (!file_exists($php_config_path)) { 
    // Fallback logic or error
    $guess_paths = [
        __DIR__ . '/wp-config.php',
        dirname(__FILE__, 2) . '/wp-config.php',
        dirname(__FILE__, 3) . '/wp-config.php',
        dirname(__FILE__, 4) . '/wp-config.php',
        dirname(__FILE__, 5) . '/wp-config.php',
    ];
    foreach ($guess_paths as $p) {
        if (is_file($p)) { $php_config_path = $p; break; }
    }
}

if (file_exists($php_config_path)) {
    require_once $php_config_path;
} else {
    http_response_code(500);
    echo "<h3>wp-config.php not found</h3>";
    exit;
}

$base_url = defined('API_BASE_URL') ? API_BASE_URL : 'http://localhost/api';

/* ====== Helpers ====== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Map headers -> canonical keys (case-insensitive, trims BOM/spaces) */
function normalize_headers(array $hdrs): array {
  $map = [];
  foreach ($hdrs as $i => $h) {
    $h = trim((string)$h);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
    $key = strtolower($h);
    $map[$key] = $i;
  }
  return $map;
}

/* ====== UI (upload form) ====== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ?>
  <h2>Import Employee Leave CSV → Daily Rows</h2>
  <form method="post" enctype="multipart/form-data">
    <p><label><b>CSV file</b>:
      <input type="file" name="csv" accept=".csv,text/csv" required>
    </label></p>
    <p><label>
      <input type="checkbox" name="dryrun" value="1" checked>
      DRY RUN (preview only; does not insert)
    </label></p>
    <p><button type="submit">Preview / Import</button></p>
  </form>
  <hr>
  <details>
    <summary>Duplicate-safety (recommended)</summary>
    <pre>ALTER TABLE `<?php echo h($table); ?>`
  ADD UNIQUE KEY `uniq_doc_emp_day`
  (`doc_no`,`employee_code`,`from_date`,`till_date`);</pre>
  </details>
  <?php
  exit;
}

/* ====== Handle upload ====== */
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
  echo "<p>Upload failed.</p>";
  exit;
}
$path = $_FILES['csv']['tmp_name'];
$fh = fopen($path, 'r');
if (!$fh) { echo "<p>Cannot open uploaded file.</p>"; exit; }

/* ====== Read header ====== */
$header = fgetcsv($fh, 0, ',');
if (!$header) { echo "<p>CSV appears empty.</p>"; exit; }
$H = normalize_headers($header);

$need = [
  'docno'             => ['DocNo', 'doc_no', 'doc no.'],
  'employee_code'     => ['Employee Code', 'employeecode', 'emp code'],
  'employee_name'     => ['Employee Name', 'employ name', 'name'],
  'leave_type'        => ['Leave Type', 'leave'],
  'from_date'         => ['From Date', 'from'],
  'from_date_value'   => ['From Date Value', 'from dv', 'from value'],
  'till_date'         => ['Till Date', 'to date', 'till'],
  'till_date_value'   => ['Till Date Value', 'to dv', 'till value'],
  'remarks'           => ['Remarks', 'remark'],
  'current_status'    => ['Current Status', 'status'],
];

/** Resolve index for each needed key */
$idx = [];
foreach ($need as $canon => $aliases) {
  $found = null;
  foreach ($aliases as $a) {
    $a = strtolower($a);
    if (array_key_exists($a, $H)) { $found = $H[$a]; break; }
  }
  if ($found === null) {
    echo "<p><b>Missing required column</b>: ".h($canon)."</p>";
    exit;
  }
  $idx[$canon] = $found;
}

/* ====== Process rows ====== */
$rowsToImport = [];

while (($row = fgetcsv($fh, 0, ',')) !== false) {
  // Skip blank lines
  if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

  $rowsToImport[] = [
      'doc_no'          => trim((string)($row[$idx['docno']]           ?? '')),
      'employee_code'   => trim((string)($row[$idx['employee_code']]   ?? '')),
      'employee_name'   => trim((string)($row[$idx['employee_name']]   ?? '')),
      'leave_type'      => trim((string)($row[$idx['leave_type']]      ?? '')),
      'from_date'       => trim((string)($row[$idx['from_date']]       ?? '')),
      'from_date_value' => trim((string)($row[$idx['from_date_value']] ?? '')),
      'till_date'       => trim((string)($row[$idx['till_date']]       ?? '')),
      'till_date_value' => trim((string)($row[$idx['till_date_value']] ?? '')),
      'remarks'         => trim((string)($row[$idx['remarks']]         ?? '')),
      'current_status'  => trim((string)($row[$idx['current_status']]  ?? '')),
  ];
}
fclose($fh);

/* ====== Call API ====== */
$apiUrl = $base_url . '/leave-roster/import';
$payload = [
    'rows' => $rowsToImport,
    'dry_run' => $DRY_RUN
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_FOLLOWLOCATION => true
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = null;
$inserted = 0;
$skipped = 0;
$preview = [];

if ($response && $httpCode < 400) {
    $json = json_decode($response, true);
    if (isset($json['data'])) {
        $result = $json['data'];
        $inserted = $result['inserted'] ?? 0;
        $skipped = $result['skipped'] ?? 0;
        $preview = $result['preview'] ?? [];
    }
} else {
    echo "<p>API Error: " . h($response) . " (Code: $httpCode)</p>";
}


/* ====== Result ====== */
echo "<h3>".($DRY_RUN ? 'DRY RUN (no inserts)' : 'Import complete')."</h3>";
echo "<p>Inserted: <b>".(int)$inserted."</b> &nbsp; Skipped: <b>".(int)$skipped."</b></p>";

if (!empty($preview)) {
  echo "<details open><summary>Preview (" . count($preview) . " rows)</summary>";
  echo "<style>table{border-collapse:collapse;max-width:100%;overflow:auto;display:block}td,th{border:1px solid #ccc;padding:6px;font:12px/1.3 monospace;white-space:nowrap}</style>";
  echo "<table><thead><tr>";
  // Assuming first row has all keys we want to show. If not, might need better key extraction.
  if (isset($preview[0])) {
      foreach (array_keys($preview[0]) as $key) echo "<th>".h($key)."</th>";
  }
  echo "</tr></thead><tbody>";
  foreach ($preview as $r) {
    echo "<tr>";
    foreach ($r as $v) echo "<td>".h((string)$v)."</td>";
    echo "</tr>";
  }
  echo "</tbody></table></details>";
}

/* ====== Notes ======
1) To prevent duplicates when re-running, add this index once:
   ALTER TABLE `{$table}` ADD UNIQUE KEY uniq_doc_emp_day (doc_no, employee_code, from_date, till_date);

2) If your target table is missing some columns, create them:
   ALTER TABLE `{$table}`
     ADD COLUMN doc_no           VARCHAR(32),
     ADD COLUMN employee_code    VARCHAR(64),
     ADD COLUMN employee_name    VARCHAR(128),
     ADD COLUMN leave_type       VARCHAR(16),
     ADD COLUMN from_date        DATETIME,
     ADD COLUMN from_date_value  VARCHAR(8),
     ADD COLUMN till_date        DATETIME,
     ADD COLUMN till_date_value  VARCHAR(8),
     ADD COLUMN remarks          TEXT,
     ADD COLUMN current_status   VARCHAR(64),
     ADD COLUMN day_seq          INT,
     ADD COLUMN created_at       DATETIME DEFAULT CURRENT_TIMESTAMP;

3) Half-day values:
   The script preserves "From Date Value"/"Till Date Value" (FD/HD). If empty, it defaults to FD.
*/
