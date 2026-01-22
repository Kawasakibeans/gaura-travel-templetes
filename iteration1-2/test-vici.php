<?php
/**
 * Sync vicidial_closer_log -> flylanka_flib.call (batched, idempotent)
 * Run: php sync_vicidial_to_flylanka.php
 * 
 * Now uses API endpoint: POST /v1/vici/sync
 * Source: ViciSyncService::run
 */

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');
date_default_timezone_set('Australia/Melbourne');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection - no longer needed (now using API endpoints)
// All database queries have been replaced with API endpoint calls
// Old database connection code is commented out below
/*
// ---------- SOURCE (Asterisk) ----------
$SRC_HOST = "124.43.65.14";
$SRC_USER = "cron";
$SRC_PASS = "1234";
$SRC_DB   = "asterisk";

// ---------- DEST (Flylanka) ----------
$DEST_HOST = "localhost";
$DEST_USER = "gaurat_sriharan";
$DEST_PASS = "r)?2lc^Q0cAE";
$DEST_DB   = "gaurat_gauratravel";

// Connect helpers
function db($host,$user,$pass,$db) {
  $m = new mysqli($host,$user,$pass,$db);
  if ($m->connect_errno) {
    die("DB connect failed ($host/$db): " . $m->connect_error . PHP_EOL);
  }
  $m->set_charset("utf8mb4");
  return $m;
}

$src  = db($SRC_HOST,$SRC_USER,$SRC_PASS,$SRC_DB);
$dest = db($DEST_HOST,$DEST_USER,$DEST_PASS,$DEST_DB);
*/

require_once( dirname( __FILE__, 5 ) . '/wp-config.php' );
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
// ---------- SETTINGS ----------
$BATCH_SIZE = 2000;   // tune as needed (can be overridden via API parameter)
$MAX_BATCHES = null;  // optional max batches

// Sync Vicidial Closer Log to Flylanka Calls via API endpoint
// API Endpoint: POST /v1/vici/sync
// Source: ViciSyncService::run
// Body parameters: batch_size (optional, default 2000), max_batches (optional)
try {
    // Build API endpoint URL
    $api_url = API_BASE_URL;
    $endpoint = $api_url . '/vici/sync';
    
    // Prepare request body
    $apiPayload = [];
    if (isset($BATCH_SIZE)) {
        $apiPayload['batch_size'] = $BATCH_SIZE;
    }
    if (isset($MAX_BATCHES)) {
        $apiPayload['max_batches'] = $MAX_BATCHES;
    }
    
    echo "Starting sync via API endpoint...\n";
    if (!empty($apiPayload)) {
        echo "Parameters: " . json_encode($apiPayload) . "\n";
    }
    
    $apiCh = curl_init($endpoint);
    curl_setopt($apiCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($apiCh, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($apiCh, CURLOPT_TIMEOUT, 300); // Longer timeout for sync operations
    curl_setopt($apiCh, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($apiCh, CURLOPT_POST, true);
    curl_setopt($apiCh, CURLOPT_POSTFIELDS, json_encode($apiPayload));
    curl_setopt($apiCh, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $apiResponse = curl_exec($apiCh);
    $apiHttpCode = curl_getinfo($apiCh, CURLINFO_HTTP_CODE);
    $curlError = curl_error($apiCh);
    curl_close($apiCh);
    
    if ($curlError) {
        die("cURL Error: $curlError\n");
    }
    
    if ($apiHttpCode >= 200 && $apiHttpCode < 300) {
        $apiData = json_decode($apiResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("JSON decode error: " . json_last_error_msg() . "\nResponse: " . substr($apiResponse, 0, 500) . "\n");
        }
        
        if (isset($apiData['status']) && $apiData['status'] === 'success') {
            $data = $apiData['data'] ?? [];
            $processed = $data['processed'] ?? $data['rows_processed'] ?? 0;
            $batches = $data['batches'] ?? 0;
            
            echo "✅ Sync complete via API.\n";
            echo "Rows processed: $processed\n";
            if ($batches > 0) {
                echo "Batches processed: $batches\n";
            }
            if (isset($data['last_closecallid'])) {
                echo "Last closecallid: {$data['last_closecallid']}\n";
            }
        } else {
            $errorMsg = $apiData['message'] ?? 'Unknown error';
            die("API returned error: $errorMsg\n");
        }
    } else {
        $errorMsg = "API request failed with HTTP code $apiHttpCode";
        if ($apiResponse) {
            $apiData = json_decode($apiResponse, true);
            if (isset($apiData['message'])) {
                $errorMsg = $apiData['message'];
            }
        }
        die("Error: $errorMsg\nResponse: " . substr($apiResponse, 0, 500) . "\n");
    }
    
} catch (Exception $e) {
    die("API error: " . $e->getMessage() . "\n");
}

// OLD SQL QUERIES - COMMENTED OUT (now using API endpoint)
/*
// Find resume point: highest closecallid already in destination
$maxDestId = 0;
$res = $dest->query("SELECT COALESCE(MAX(closecallid),0) AS max_id FROM $TABLE_DEST");
if ($res) {
  $row = $res->fetch_assoc();
  $maxDestId = (int)$row['max_id'];
  $res->free();
}
echo "Resume from closecallid > $maxDestId\n";

// Count total to copy
// SQL Query:
// SELECT COUNT(*) AS c FROM vicidial_closer_log WHERE closecallid > ?;
$countSql = "SELECT COUNT(*) AS c FROM $TABLE_SRC WHERE closecallid > ?";
$stmtCnt  = $src->prepare($countSql);
$stmtCnt->bind_param("i", $maxDestId);
$stmtCnt->execute();
$stmtCnt->bind_result($toCopy);
$stmtCnt->fetch();
$stmtCnt->close();

echo "Rows to process: $toCopy\n";
if ($toCopy == 0) {
  echo "Nothing to do. ✅\n";
  exit;
}

// Build SELECT -> keep column order matching INSERT below
// SQL Query:
// SELECT
//     closecallid, lead_id, list_id, campaign_id, call_date,
//     start_epoch, end_epoch, length_in_sec, status, phone_code,
//     phone_number, `user`, comments, processed, queue_seconds,
//     user_group, xfercallid, term_reason, uniqueid, agent_only,
//     queue_position, called_count
// FROM vicidial_closer_log
// WHERE closecallid > ?
// ORDER BY closecallid
// LIMIT ?;
$selectSql = "
  SELECT
    closecallid, lead_id, list_id, campaign_id, call_date,
    start_epoch, end_epoch, length_in_sec, status, phone_code,
    phone_number, `user`, comments, processed, queue_seconds,
    user_group, xfercallid, term_reason, uniqueid, agent_only,
    queue_position, called_count
  FROM $TABLE_SRC
  WHERE closecallid > ?
  ORDER BY closecallid
  LIMIT ?
";

// UPSERT (ON DUPLICATE KEY) into destination
// SQL Query:
// INSERT INTO flylanka_flib_call (
//     closecallid, lead_id, list_id, campaign_id, call_date,
//     start_epoch, end_epoch, length_in_sec, status, phone_code,
//     phone_number, `user`, comments, processed, queue_seconds,
//     user_group, xfercallid, term_reason, uniqueid, agent_only,
//     queue_position, called_count
// ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
// ON DUPLICATE KEY UPDATE
//     lead_id = VALUES(lead_id),
//     list_id = VALUES(list_id),
//     campaign_id = VALUES(campaign_id),
//     call_date = VALUES(call_date),
//     start_epoch = VALUES(start_epoch),
//     end_epoch = VALUES(end_epoch),
//     length_in_sec = VALUES(length_in_sec),
//     status = VALUES(status),
//     phone_code = VALUES(phone_code),
//     phone_number = VALUES(phone_number),
//     `user` = VALUES(`user`),
//     comments = VALUES(comments),
//     processed = VALUES(processed),
//     queue_seconds = VALUES(queue_seconds),
//     user_group = VALUES(user_group),
//     xfercallid = VALUES(xfercallid),
//     term_reason = VALUES(term_reason),
//     uniqueid = VALUES(uniqueid),
//     agent_only = VALUES(agent_only),
//     queue_position = VALUES(queue_position),
//     called_count = VALUES(called_count);
// Source: ViciSyncService::run
// Method: POST
// Endpoint: /v1/vici/sync
// Body parameters: batch_size (optional, default 2000), max_batches (optional)
$insertSql = "
  INSERT INTO $TABLE_DEST (
    closecallid, lead_id, list_id, campaign_id, call_date,
    start_epoch, end_epoch, length_in_sec, status, phone_code,
    phone_number, `user`, comments, processed, queue_seconds,
    user_group, xfercallid, term_reason, uniqueid, agent_only,
    queue_position, called_count
  ) VALUES (
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?
  )
  ON DUPLICATE KEY UPDATE
    lead_id=VALUES(lead_id),
    list_id=VALUES(list_id),
    campaign_id=VALUES(campaign_id),
    call_date=VALUES(call_date),
    start_epoch=VALUES(start_epoch),
    end_epoch=VALUES(end_epoch),
    length_in_sec=VALUES(length_in_sec),
    status=VALUES(status),
    phone_code=VALUES(phone_code),
    phone_number=VALUES(phone_number),
    `user`=VALUES(`user`),
    comments=VALUES(comments),
    processed=VALUES(processed),
    queue_seconds=VALUES(queue_seconds),
    user_group=VALUES(user_group),
    xfercallid=VALUES(xfercallid),
    term_reason=VALUES(term_reason),
    uniqueid=VALUES(uniqueid),
    agent_only=VALUES(agent_only),
    queue_position=VALUES(queue_position),
    called_count=VALUES(called_count)
";

// Prepare statements
$sel = $src->prepare($selectSql);
$ins = $dest->prepare($insertSql);
if (!$sel)  die("Prepare SELECT failed: " . $src->error . PHP_EOL);
if (!$ins)  die("Prepare INSERT failed: " . $dest->error . PHP_EOL);

$processed = 0;
$lastId    = $maxDestId;

while (true) {
  $limit = $BATCH_SIZE;
  $sel->bind_param("ii", $lastId, $limit);
  if (!$sel->execute()) {
    die("SELECT exec error: " . $sel->error . PHP_EOL);
  }
  $r = $sel->get_result();
  if ($r->num_rows === 0) break;

  // Transaction per batch (faster + atomic)
  $dest->begin_transaction();
  $batchRows = 0;

  while ($row = $r->fetch_assoc()) {
    // Normalize enum-ish values if needed
    $processedVal  = $row['processed'];   // 'Y'|'N' or NULL
    $termReasonVal = $row['term_reason']; // enforce allowed values if desired

    $ins->bind_param(
      "iiissiiiisssssdsissssii",
      $row['closecallid'],
      $row['lead_id'],
      $row['list_id'],
      $row['campaign_id'],
      $row['call_date'],
      $row['start_epoch'],
      $row['end_epoch'],
      $row['length_in_sec'],
      $row['status'],
      $row['phone_code'],
      $row['phone_number'],
      $row['user'],
      $row['comments'],
      $processedVal,
      $row['queue_seconds'],
      $row['user_group'],
      $row['xfercallid'],
      $termReasonVal,
      $row['uniqueid'],
      $row['agent_only'],
      $row['queue_position'],
      $row['called_count']
    );

    if (!$ins->execute()) {
      // Log and continue
      echo "Insert failed (closecallid {$row['closecallid']}): {$ins->error}\n";
    } else {
      $batchRows++;
      $lastId = (int)$row['closecallid'];
    }
  }

  $dest->commit();
  $processed += $batchRows;
  echo "Committed batch: +$batchRows (total $processed), last closecallid=$lastId\n";

  if ($batchRows < $BATCH_SIZE) break; // done
}

$sel->close();
$ins->close();
$src->close();
$dest->close();

echo "✅ Sync complete. Rows processed: $processed\n";
*/
