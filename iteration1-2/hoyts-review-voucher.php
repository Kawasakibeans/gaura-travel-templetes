<?php
/**
 * hoyts_voucher_unified.php
 * One-file page: DB info + HOYTS fetch + barcode (rotated 90°) + full terms text
 * + HOYTS fields: E-Voucher Number, PIN, Expires (parsed from upstream HTML).
 */
// declare(strict_types=1);
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Australia/Melbourne');

/* ====== Load WP DB constants ====== */
$php_config_path = '/home/aigauratravelcom/public_html/wp-config.php'; 
if (!file_exists($php_config_path)) { http_response_code(500); echo "<h1>wp-config.php not found</h1>"; exit; }
require_once $php_config_path;

/* ====== DB connect ====== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = mysqli_init();
if (defined('MYSQLI_OPT_INT_AND_FLOAT_NATIVE')) { mysqli_options($mysqli, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1); }
$mysqli->real_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

/* ====== Config ====== */
$table         = 'wpk4_backend_marketing_hoyts_vouchers';
$cacheDir      = __DIR__ . '/cache_qr';   // ensure writable (775/755)
$HTML_TTL      = 60 * 30;                 // 30 min
$IMG_TTL       = 60 * 60 * 24 * 7;        // 7 days
$UA            = 'Mozilla/5.0 (VoucherUnified; +yourdomain.com)';
$BARCODE_ALIGN = 'center';                // 'left' | 'center' | 'right'
$BARCODE_ROTATE_DEG = 90;                 // rotate barcode clockwise; set 0 to disable

/* ====== Helpers ====== */
function bad(string $m, int $code=404){ http_response_code($code); echo "<!doctype html><meta charset=utf-8><p>$m</p>"; exit; }
function safe_mkdir(string $dir, int $mode=0775){ if (!is_dir($dir)) { @mkdir($dir,$mode,true); @chmod($dir,$mode); } }
function fetch_cached(string $url, string $toFile, int $ttl, string $ua): ?string {
  if (is_file($toFile) && (time()-filemtime($toFile)) < $ttl) {
    $data = @file_get_contents($toFile); return ($data!==false)?$data:null;
  }
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_TIMEOUT=>12, CURLOPT_CONNECTTIMEOUT=>6,
    CURLOPT_USERAGENT=>$ua, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
  ]);
  $data = curl_exec($ch); $code = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if ($data!==false && $code>=200 && $code<400){ @file_put_contents($toFile,$data); return $data; }
  return null;
}
function abs_url(string $base, string $rel): string {
  if (preg_match('#^https?://#i',$rel)) return $rel;
  $u=parse_url($base); $root=$u['scheme'].'://'.$u['host'].(isset($u['port'])?":{$u['port']}":'');
  if (str_starts_with($rel,'/')) return $root.$rel;
  return rtrim(dirname($base),'/').'/'.$rel;
}
function ext_from_path(string $path): string {
  $p = parse_url($path, PHP_URL_PATH) ?? ''; $e = strtolower(pathinfo($p, PATHINFO_EXTENSION));
  return $e ?: 'bin';
}
/** Try to rotate raster image binary by $deg clockwise with GD. Returns [bin, contentType] or null on fail. */
function gd_rotate_bin(string $bin, string $ext, int $deg){
  if ($deg % 360 === 0) return null;
  if (!function_exists('imagecreatetruecolor')) return null;
  try{
    $src = @imagecreatefromstring($bin);
    if (!$src) return null;
    $bg  = imagecolorallocatealpha($src, 255,255,255,127); // transparent
    $rot = imagerotate($src, -$deg, $bg);                  // -deg = clockwise
    imagesavealpha($rot, true);
    ob_start();
    $ext = strtolower($ext);
    $ct = 'image/png';
    if ($ext==='jpg' || $ext==='jpeg'){ imagejpeg($rot, null, 90); $ct='image/jpeg'; }
    elseif ($ext==='gif'){ imagegif($rot); $ct='image/gif'; }
    else { imagepng($rot, null); $ct='image/png'; }
    $out = ob_get_clean();
    imagedestroy($src); imagedestroy($rot);
    return [$out, $ct];
  }catch(Throwable $e){ return null; }
}
/** Parse a date like 30/11/2028 (or with -) into "Mon dd, YYYY". */
function format_hoyts_date(?string $s): ?string {
  if(!$s) return null;
  $s = trim($s);
  $fmts = ['d/m/Y','d/m/y','d-m-Y','d-m-y'];
  foreach ($fmts as $f) { $dt = DateTime::createFromFormat($f, $s); if ($dt) return $dt->format('M d, Y'); }
  return $s;
}

/* ====== Select voucher ====== */
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  $id = (int)$_GET['id'];
  $sql = "SELECT id,voucher_code,email,hoyts_url,status,expires_at,created_at
          FROM {$table} WHERE id=? AND status='active' AND email IS NULL LIMIT 1";
  $stmt = $mysqli->prepare($sql); $stmt->bind_param("i",$id);
} else {
  $sql = "SELECT id,voucher_code,email,hoyts_url,status,expires_at,created_at
          FROM {$table} WHERE status='active' AND email IS NULL
          ORDER BY COALESCE(created_at,'1970-01-01 00:00:00') ASC LIMIT 1";
  $stmt = $mysqli->prepare($sql);
}
$stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc(); $stmt->close();
if (!$row) bad('No active voucher with empty email.');
$id=(int)$row['id']; $code=(string)$row['voucher_code']; $hoytsUrl=(string)$row['hoyts_url'];
$status=(string)$row['status']; $email=$row['email']?:'—';
$expiresDb=$row['expires_at']?date('M d, Y',strtotime($row['expires_at'])):'—';
$created=$row['created_at']?date('M d, Y H:i',strtotime($row['created_at'])):'—';

/* ====== Cache dir ====== */
safe_mkdir($cacheDir);

/* ====== Fetch HOYTS HTML ====== */
$htmlFile = $cacheDir . "/page_{$id}.html";
$html = fetch_cached($hoytsUrl, $htmlFile, $HTML_TTL, $UA) ?? '';

/* ====== Extract: barcode (rotated), full terms text, HOYTS fields (E-Voucher, PIN, Expires) ====== */
$barcodeDataUri = null;
$barcodeInlineStyle = '';  // CSS rotate fallback if GD can't rotate or for SVG
$termsHtml = '';

$hoytsEvoucher = null;
$hoytsPin      = null;
$hoytsExpires  = null;

if ($html !== '') {
  libxml_use_internal_errors(true);
  $doc = new DOMDocument(); $doc->loadHTML($html);
  $xp  = new DOMXPath($doc);

  /* --- HOYTS fields from plain text (tolerant) --- */
  $plain = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  // remove zero-width/formatting chars and collapse spacing
  $plain = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $plain);
  $plain = strip_tags($plain);
  $plain = preg_replace('/\s+/u', ' ', $plain);

  if (preg_match('/E[\-\s]?Voucher\s*Number\s*([A-Z0-9]+)/i', $plain, $m)) {
    $hoytsEvoucher = strtoupper($m[1]);
  }
  // allow any non-digit separation: "PIN2009", "PIN 2009", "PIN:2009", etc.
  if (preg_match('/\bpin\b[^\d]{0,10}(\d{3,10})/i', $plain, $m)) {
    $hoytsPin = $m[1];
  }
  if (preg_match('/Expires?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i', $plain, $m)) {
    $hoytsExpires = format_hoyts_date($m[1]);
  }

  /* --- DOM fallback for PIN: look for <b>PIN</b> then read next siblings --- */
  if (!$hoytsPin) {
    $pinBs = $xp->query("//b[translate(normalize-space(.),'abcdefghijklmnopqrstuvwxyz','ABCDEFGHIJKLMNOPQRSTUVWXYZ')='PIN']");
    if ($pinBs && $pinBs->length) {
      foreach ($pinBs as $b) {
        $cur = $b->nextSibling; $hops=0;
        while ($cur && $hops<6) {
          $txt = trim(preg_replace('/\s+/u', ' ', $cur->textContent ?? ''));
          if ($txt !== '' && preg_match('/(\d{3,10})/', $txt, $m2)) { $hoytsPin = $m2[1]; break; }
          $cur = $cur->nextSibling; $hops++;
        }
        if ($hoytsPin) break;
      }
    }
  }

  /* --- Barcode image selection --- */
  $n = $xp->query("//img[contains(translate(@alt,'BARCODE','barcode'),'barcode')
                     or contains(translate(@id,'BARCODE','barcode'),'barcode')
                     or contains(translate(@class,'BARCODE','barcode'),'barcode')
                     or contains(translate(@src,'BARCODE','barcode'),'barcode')]")->item(0);
  if (!$n) {
    $cand = $xp->query("//*[contains(translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'expires')
                         or contains(translate(normalize-space(.),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'pin')]
                        /descendant::img");
    if ($cand && $cand->length) $n = $cand->item($cand->length - 1);
  }
  if (!$n) {
    $best = null; $bestW = 0;
    foreach ($doc->getElementsByTagName('img') as $img) {
      $wAttr = $img->getAttribute('width'); $w = is_numeric($wAttr) ? (int)$wAttr : 0;
      if ($w > $bestW) { $bestW = $w; $best = $img; }
    }
    $n = $best;
  }

  if ($n && $n->getAttribute('src')) {
    $barcodeSrc = abs_url($hoytsUrl, $n->getAttribute('src'));
    $ext = ext_from_path($barcodeSrc);
    $imgFile = $cacheDir . "/barcode_{$id}." . $ext;
    $img     = fetch_cached($barcodeSrc, $imgFile, $IMG_TTL, $UA);

    if ($img) {
      $ct = 'image/png';
      $lower = strtolower($imgFile);
      $isSvg = false;
      if (str_ends_with($lower, '.svg') || strpos($img, '<svg') !== false) { $ct = 'image/svg+xml'; $isSvg = true; }
      elseif (str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg')) { $ct = 'image/jpeg'; }
      elseif (str_ends_with($lower, '.gif')) { $ct = 'image/gif'; }

      $rot = null;
      if (!$isSvg && $BARCODE_ROTATE_DEG % 360 !== 0) {
        $rot = gd_rotate_bin($img, $ext, $BARCODE_ROTATE_DEG);
      }
      if ($rot) {
        [$bin, $ctOut] = $rot;
        $barcodeDataUri = 'data:'.$ctOut.';base64,'.base64_encode($bin);
      } else {
        $barcodeDataUri = 'data:'.$ct.';base64,'.base64_encode($img);
        if ($BARCODE_ROTATE_DEG % 360 !== 0) {
          $barcodeInlineStyle = "transform: rotate({$BARCODE_ROTATE_DEG}deg); transform-origin: center;";
        }
      }
    }
  }

  /* --- Terms & Notes: gather ALL <p> and <li> text, dedup --- */
  $seen = []; $parts = [];
  $nodes = $xp->query('//p | //li');
  if ($nodes) {
    foreach ($nodes as $node) {
      $frag = $doc->saveHTML($node);
      $frag = preg_replace_callback(
        '/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is',
        function($m){ return trim(strip_tags($m[2])) . ' (' . $m[1] . ')'; },
        $frag
      );
      $text = trim(preg_replace('/\s+/', ' ', strip_tags($frag)));
      if ($text !== '' && !isset($seen[mb_strtolower($text)])) {
        $seen[mb_strtolower($text)] = true;
        $parts[] = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
    }
  }
  if ($parts) {
    $termsHtml = '<div>' . implode('', array_map(fn($t)=>"<p>{$t}</p>", $parts)) . '</div>';
  }
}

/* ====== Page ====== */
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>HOYTS Voucher — #<?=htmlspecialchars((string)$id)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--brand:#ffd207;--bg:#0b1220;--panel:#0f172a;--muted:#93a3b8;--text:#e5e7eb}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,Arial;background:var(--bg);color:var(--text)}
.wrap{max-width:1280px;margin:24px auto;padding:20px}
.card{background:var(--panel);border:1px solid #1f2a44;border-radius:16px;padding:20px}
.kv{display:grid;grid-template-columns:190px 1fr;gap:8px;margin:6px 0}
.kv b{color:#cdd6e5}
.btn{display:inline-block;margin-top:12px;background:var(--brand);color:#111;text-decoration:none;font-weight:700;padding:10px 14px;border-radius:10px;border:1px solid #9f8510;background-image:linear-gradient(#ffe46b,#ffd207)}
.section{background:#fff;color:#111;border-radius:12px;padding:16px;margin-top:16px}
.section h2{margin:0 0 10px;font-size:18px}

/* Barcode container alignment */
.barcodeBox{
  min-height:220px; display:flex; align-items:center; /* vertical center */
  justify-content: <?=($BARCODE_ALIGN==='left'?'flex-start':($BARCODE_ALIGN==='center'?'center':'flex-end'))?>;
  padding:16px;
}
.barcodeBox img{max-width:95%; height:auto; display:block}

.caption{margin-top:8px;font-size:12px;color:#555;text-align:center}
.note{color:#bbb;font-size:12px;margin-top:8px}

/* Code pill + copy */
.code{
  font-family:ui-monospace,Menlo,Consolas,monospace;
  background:#0a1324;border:1px dashed #2a3a62;border-radius:8px;
  padding:6px 10px;display:inline-block;margin-right:8px
}
.copybtn{
  appearance:none;border:1px solid #2a3a62;background:#0f234a;color:#fff;
  padding:6px 10px;border-radius:8px;cursor:pointer;font-size:12px
}
.copybtn:active{transform:scale(0.98)}
.copynote{font-size:12px;color:#aaa;margin-top:6px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 style="margin:0 0 8px">HOYTS Voucher</h1>
      <div class="kv"><b>Voucher ID</b><span>#<?=htmlspecialchars((string)$id)?></span></div>
      <div class="kv"><b>Status</b><span><?=htmlspecialchars($status)?></span></div>

      <?php if ($hoytsEvoucher): ?>
      <div class="kv">
        <b>E-Voucher Number</b>
        <span>
          <span class="code" id="evcode"><?=htmlspecialchars($hoytsEvoucher)?></span>
          <button class="copybtn" data-copy="#evcode">Copy</button>
          <div class="copynote">Use this code for redemption</div>
        </span>
      </div>
      <?php endif; ?>

      <?php if ($hoytsPin): ?><div class="kv"><b>PIN</b><span><?=htmlspecialchars($hoytsPin)?></span></div><?php endif; ?>
      <?php if ($hoytsExpires): ?><div class="kv"><b>Expiration Date</b><span><?=htmlspecialchars($hoytsExpires)?></span></div><?php endif; ?>
      <div class="kv"><b>Created</b><span><?=htmlspecialchars($created)?></span></div>

      <center><a class="btn" href="<?=htmlspecialchars($hoytsUrl)?>" target="_blank" rel="noopener">Open original HOYTS page</a></center>
      <div class="note"><center>Show this screen at HOYTS. Staff can scan the barcode or use the voucher numbers above.</center></div>
    </div>

    <div class="section">
      <h2>Barcode</h2>
      <div class="barcodeBox">
        <?php if ($barcodeDataUri): ?>
          <img src="<?=$barcodeDataUri?>" alt="Voucher Barcode" style="<?=$barcodeInlineStyle?>">
        <?php else: ?>
          <div class="caption">Couldn’t extract the barcode image. Please open the original HOYTS page.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="section">
      <h2>Terms &amp; Notes</h2>
      <?php if ($termsHtml): ?>
        <?=$termsHtml?>
      <?php else: ?>
        <p class="caption">Terms text wasn’t detected. See the original HOYTS page for full details.</p>
      <?php endif; ?>
    </div>
  </div>

<script>
// Simple copy-to-clipboard for elements referenced by [data-copy]
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-copy]');
  if (!btn) return;
  try{
    const sel = btn.getAttribute('data-copy');
    const el = document.querySelector(sel);
    const text = el ? (el.innerText || el.textContent || '').trim() : '';
    if (!text) return;
    await navigator.clipboard.writeText(text);
    const old = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(()=> btn.textContent = old, 1200);
  }catch(err){
    // fallback: select text
    const sel = window.getSelection(); const range = document.createRange();
    const el = document.querySelector(btn.getAttribute('data-copy'));
    if (el){ range.selectNodeContents(el); sel.removeAllRanges(); sel.addRange(range); document.execCommand('copy'); sel.removeAllRanges(); }
  }
});
</script>
</body>
</html>
