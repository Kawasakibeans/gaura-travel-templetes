<?php
/**
 * Template Name: Dupe Bookings by Email (Client-side Pagination)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    wp_die("Database connection failed");
}

/* -------------------------
   Helpers
------------------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function normalize_email($e){
    $e = trim((string)$e);
    $e = mb_strtolower($e, 'UTF-8');
    return $e;
}
function parse_range($str, $fallbackYmd){
    $str = trim((string)$str);
    if ($str === '') return [$fallbackYmd . ' 00:00:00', $fallbackYmd . ' 23:59:59'];
    $str = str_replace(' to ', ',', $str);
    $parts = array_map('trim', explode(',', $str));
    $d1 = $parts[0] ?? $fallbackYmd;
    $d2 = $parts[1] ?? $parts[0] ?? $fallbackYmd;
    $rx = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($rx, $d1)) $d1 = $fallbackYmd;
    if (!preg_match($rx, $d2)) $d2 = $d1;
    if (strtotime($d1) > strtotime($d2)) { [$d1, $d2] = [$d2, $d1]; }
    return [$d1 . ' 00:00:00', $d2 . ' 23:59:59'];
}

/* -------------------------
   Defaults: today in AU/Melbourne
------------------------- */
$tz     = new DateTimeZone('Australia/Melbourne');
$today  = (new DateTime('now', $tz))->format('Y-m-d');
$range_default_display = $today . " to " . $today;

/* NEW: inputs */
$travel_range_display = isset($_GET['travel_range']) ? trim($_GET['travel_range']) : $range_default_display;
$email_input          = isset($_GET['email'])        ? trim((string)$_GET['email']) : '';

[$travel_from, $travel_to] = parse_range($travel_range_display, $today);

/* -------------------------
   Build SQL (travel_date range + optional email filter)
------------------------- */
$sql = "
SELECT
    b.order_id            AS bookingid,
    b.travel_date         AS travel_date,
    b.trip_code           AS trip_code,
    b.order_date          AS order_date,
    b.payment_modified    AS payment_modified,
    p.fname               AS fname,
    p.lname               AS lname,
    p.email_pax           AS email_pax
FROM wpk4_backend_travel_bookings b
JOIN wpk4_backend_travel_booking_pax p
  ON b.order_id    = p.order_id
 AND b.co_order_id = p.co_order_id
 AND b.product_id  = p.product_id
WHERE TRIM(COALESCE(p.email_pax, '')) <> ''
  AND b.travel_date BETWEEN ? AND ?
";

$params = [$travel_from, $travel_to];
$types  = "ss";

/* Optional email filter:
   - if contains '@' -> exact normalized match
   - else -> LIKE %term% (case-insensitive)
*/
if ($email_input !== '') {
    $needle = normalize_email($email_input);
    if (strpos($needle, '@') !== false) {
        $sql .= " AND LOWER(TRIM(p.email_pax)) = ? ";
        $params[] = $needle;
        $types   .= "s";
    } else {
        $sql .= " AND LOWER(TRIM(p.email_pax)) LIKE ? ";
        $params[] = '%' . $needle . '%';
        $types   .= "s";
    }
}

$sql .= " ORDER BY b.auto_id DESC ";

$stmt = $mysqli->prepare($sql);
if (!$stmt) wp_die("Prepare failed: " . h($mysqli->error));

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

/* -------------------------
   Group by normalized email & keep distinct order_ids
------------------------- */
$groups = [];
foreach ($rows as $r) {
    $email = (string)$r['email_pax'];
    $ekey  = normalize_email($email);
    if ($ekey === '') continue;

    if (!isset($groups[$ekey])) {
        $groups[$ekey] = [
            'email_display' => $email,
            'order_ids'     => [],
            'items'         => [],
        ];
    }
    $groups[$ekey]['order_ids'][$r['bookingid']] = true;
    $groups[$ekey]['items'][] = $r;
}

/* Keep only groups with >= 2 distinct bookings */
$dupes = [];
foreach ($groups as $g) {
    if (count($g['order_ids']) >= 2) $dupes[] = $g;
}

/* Sort dupes by newest travel_date desc */
usort($dupes, function($a, $b){
    $aMax = 0; $bMax = 0;
    foreach ($a['items'] as $i) { $aMax = max($aMax, strtotime($i['travel_date'] ?: '1970-01-01')); }
    foreach ($b['items'] as $i) { $bMax = max($bMax, strtotime($i['travel_date'] ?: '1970-01-01')); }
    return $bMax <=> $aMax;
});

$total_groups = count($dupes);

get_header();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.dupe-wrap { max-width: 1200px; margin: 30px auto; padding: 0 12px; }
.dupe-card { background: #fff; border: 1px solid #e6e6e6; border-radius: 12px; margin-bottom: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
.dupe-head { padding: 14px 16px; border-bottom: 1px solid #eee; display:flex; flex-wrap: wrap; gap:8px; align-items: baseline; }
.dupe-head .chip { display:inline-block; padding: 4px 10px; border-radius: 999px; background:#f5f7ff; font-size:12px; border:1px solid #e3e8ff; }
.dupe-title { font-weight: 600; font-size: 18px; margin-right: 10px; }
.dupe-table { width:100%; border-collapse: collapse; }
.dupe-table th, .dupe-table td { padding: 10px 12px; border-bottom: 1px solid #f2f2f2; font-size: 13px; text-align: left; }
.dupe-table th { background: #fafafa; font-weight: 600; }
.badge { display:inline-block; padding: 3px 8px; border-radius: 6px; background:#eef8ff; border:1px solid #cde8ff; font-size:12px; }
.toolbar { display:flex; justify-content: space-between; align-items:center; margin: 18px 0; gap: 12px; flex-wrap: wrap; }
.toolbar .left { font-size: 14px; color:#333; }
.filters { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.filters input[type="text"]{ padding:8px 10px; border:1px solid #ddd; border-radius:8px; min-width: 260px; }
.filters button{ padding:8px 12px; border-radius:8px; border:1px solid #ddd; background:#fff; cursor:pointer; }
.filters button:hover{ background:#f6f6f6; }
.empty { margin: 24px 0; color: #666; }
.pagination { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin: 18px 0; }
.pagination a, .pagination button, .pagination span { padding:6px 10px; border:1px solid #ddd; border-radius:8px; text-decoration:none; color:#333; background:#fff; cursor:pointer; }
.pagination .disabled { opacity: .5; cursor: default; }
.pagination .current { background:#f5f5f5; font-weight:600; }
.meta { font-size:12px; color:#666; margin-left:6px; }
@media (max-width: 640px){
  .filters label{ display:none; }
  .filters input[type="text"]{ min-width: 180px; width: 100%; }
}
.filters button {
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  background: #ffbb00;   /* New background color */
  color: #fff;           /* White text */
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}
.filters button:hover { background: #e6aa00; }
</style>

<div class="dupe-wrap" id="top">
    <h2>Duplicate Bookings by Email</h2>

    <div class="toolbar">
        <div class="left">
            Source rows scanned: <strong><?php echo number_format(count($rows)); ?></strong> &middot;
            Duplicate email groups found: <strong id="dupeCount"><?php echo number_format($total_groups); ?></strong>
            <span class="meta" id="pageMeta"></span>
        </div>

        <!-- NEW: Email + Travel Date filters -->
        <form method="get" class="filters">
            <label for="email"><strong>Email</strong></label>
            <input type="text" id="email" name="email" value="<?php echo h($email_input); ?>" placeholder="e.g. john@acme.com or 'acme.com'">

            <label for="travel_range"><strong>Travel Date (range)</strong></label>
            <input type="text" id="travel_range" name="travel_range" value="<?php echo h($travel_range_display); ?>" placeholder="YYYY-MM-DD to YYYY-MM-DD">

            <button type="submit">Apply</button>
        </form>
    </div>

    <?php if ($total_groups === 0): ?>
        <div class="empty">
            No duplicate emails found for:
            <?php if ($email_input !== ''): ?>
                Email <strong><?php echo h($email_input); ?></strong> and
            <?php endif; ?>
            Travel Date <strong><?php echo h($travel_range_display); ?></strong>.
        </div>
    <?php else: ?>

        <!-- Pagination UI (client-side only) -->
        <div class="pagination" id="pagerTop"></div>

        <div id="dupeList">
            <?php
            // Render ALL groups; client-side JS will paginate by showing/hiding
            $gidx = 0;
            foreach ($dupes as $group):
                $email = $group['email_display'] !== '' ? $group['email_display'] : '(No email)';
                $distinctOrders = array_keys($group['order_ids']);
            ?>
            <div class="dupe-card" data-idx="<?php echo $gidx++; ?>">
                <div class="dupe-head">
                    <div class="dupe-title"><?php echo h($email); ?></div>
                    <div class="chip">distinct bookings: <strong><?php echo count($distinctOrders); ?></strong></div>
                </div>
                <div class="dupe-body" style="padding: 8px 0 2px;">
                    <table class="dupe-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Booking ID (order_id)</th>
                                <th>Trip Code</th>
                                <th>Travel Date</th>
                                <th>Order Date</th>
                                <th>Payment Modified</th>
                                <th>Fname</th>
                                <th>Lname</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($group['items'] as $item): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><span class="badge"><?php echo h($item['bookingid']); ?></span></td>
                                    <td><?php echo h($item['trip_code']); ?></td>
                                    <td><?php echo h($item['travel_date']); ?></td>
                                    <td><?php echo h($item['order_date']); ?></td>
                                    <td><?php echo h($item['payment_modified']); ?></td>
                                    <td><?php echo h($item['fname']); ?></td>
                                    <td><?php echo h($item['lname']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="8" style="text-align:left;">
                                    Distinct order_ids:
                                    <?php foreach ($distinctOrders as $oid): ?>
                                        <span class="badge"><?php echo h($oid); ?></span>
                                    <?php endforeach; ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="pagination" id="pagerBottom"></div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// --- Flatpickr range picker for Travel Date ---
(function(){
  var opts = { mode: "range", dateFormat: "Y-m-d", allowInput: true };
  flatpickr("#travel_range", opts);
})();

// --- Client-side pagination over pre-rendered groups ---
(function(){
  var PER_PAGE = 10;
  var list = document.getElementById('dupeList');
  if (!list) return;

  var cards = Array.prototype.slice.call(list.querySelectorAll('.dupe-card'));
  var total = cards.length;
  var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
  var pagerTop = document.getElementById('pagerTop');
  var pagerBottom = document.getElementById('pagerBottom');
  var pageMeta = document.getElementById('pageMeta');

  function getInitialPage(){
    var params = new URLSearchParams(window.location.search);
    var p = parseInt(params.get('pg') || '0', 10);
    if (!p && location.hash.match(/^#pg-(\d+)$/)) {
      p = parseInt(RegExp.$1, 10);
    }
    if (!p || p < 1) p = 1;
    if (p > totalPages) p = totalPages;
    return p;
  }

  var current = getInitialPage();

  function renderPage(p){
    current = Math.min(Math.max(1, p), totalPages);
    var start = (current - 1) * PER_PAGE;
    var end = start + PER_PAGE;

    // show/hide
    cards.forEach(function(card, idx){
      card.style.display = (idx >= start && idx < end) ? '' : 'none';
    });

    // meta
    if (pageMeta) {
      pageMeta.textContent = " (showing " + Math.min(PER_PAGE, total - start) +
        " of " + total + " groups • page " + current + " / " + totalPages + ")";
    }

    // update hash only (no server reload)
    if (history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('pg', current);
      history.replaceState(null, '', url.pathname + url.search + '#pg-' + current);
    } else {
      location.hash = 'pg-' + current;
    }

    // re-render controls
    renderControls();
  }

  function makeBtn(label, disabled, onClick){
    var el = document.createElement(disabled ? 'span' : 'button');
    el.textContent = label;
    el.className = disabled ? 'disabled' : '';
    if (!disabled) el.addEventListener('click', onClick);
    return el;
  }

  function renderControls(){
    function build(container){
      container.innerHTML = '';
      container.appendChild(makeBtn('« First', current === 1, function(){ renderPage(1); }));
      container.appendChild(makeBtn('‹ Prev', current === 1, function(){ renderPage(current - 1); }));
      var cur = document.createElement('span');
      cur.className = 'current';
      cur.textContent = 'Page ' + current + ' / ' + totalPages;
      container.appendChild(cur);
      container.appendChild(makeBtn('Next ›', current === totalPages, function(){ renderPage(current + 1); }));
      container.appendChild(makeBtn('Last »', current === totalPages, function(){ renderPage(totalPages); }));
    }
    if (pagerTop) build(pagerTop);
    if (pagerBottom) build(pagerBottom);
  }

  renderPage(current);
})();
</script>

<?php get_footer();
