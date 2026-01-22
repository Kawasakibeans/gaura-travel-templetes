<?php
/**
 * Template Name: Dupe Bookings
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__, 5) . '/wp-config.php');
date_default_timezone_set('Australia/Melbourne');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) wp_die("Database connection failed");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function normalize_name($fname, $lname){
    $full = trim(preg_replace('/\s+/', ' ', ($fname ?? '') . ' ' . ($lname ?? '')));
    return mb_strtolower($full, 'UTF-8');
}
function path_only(){ return h(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); }

$today = (new DateTime('now', new DateTimeZone('Australia/Melbourne')))->format('Y-m-d');
$od_from = isset($_GET['od_from']) && $_GET['od_from'] !== '' ? $_GET['od_from'] : $today;
$od_to   = isset($_GET['od_to'])   && $_GET['od_to']   !== '' ? $_GET['od_to']   : $today;
$pm_from = isset($_GET['pm_from']) && $_GET['pm_from'] !== '' ? $_GET['pm_from'] : $today;
$pm_to   = isset($_GET['pm_to'])   && $_GET['pm_to']   !== '' ? $_GET['pm_to']   : $today;

$od_range_display = $od_from . ' to ' . $od_to;
$pm_range_display = $pm_from . ' to ' . $pm_to;

$od_from_dt = $od_from . ' 00:00:00';
$od_to_dt   = $od_to   . ' 23:59:59';
$pm_from_dt = $pm_from . ' 00:00:00';
$pm_to_dt   = $pm_to   . ' 23:59:59';

$sql = "
SELECT
    b.order_id           AS bookingid,
    b.travel_date        AS travel_date,
    b.trip_code          AS trip_code,
    b.order_date         AS order_date,
    b.payment_modified   AS payment_modified,
    p.fname              AS fname,
    p.lname              AS lname,
    p.email_pax          AS email_pax
FROM wpk4_backend_travel_bookings b
JOIN wpk4_backend_travel_booking_pax p
  ON b.order_id    = p.order_id
 AND b.co_order_id = p.co_order_id
 AND b.product_id  = p.product_id
WHERE p.email_pax != ''
  AND b.order_date BETWEEN ? AND ?
  AND b.payment_modified BETWEEN ? AND ?
ORDER BY b.auto_id DESC
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) wp_die("Prepare failed: " . h($mysqli->error));
$stmt->bind_param("ssss", $od_from_dt, $od_to_dt, $pm_from_dt, $pm_to_dt);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

/* Group duplicates: Pax Name + trip_code + travel_date with >=2 distinct order_ids */
$groups = [];
foreach ($rows as $r) {
    $norm_name   = normalize_name($r['fname'], $r['lname']);
    $trip_code   = (string)$r['trip_code'];
    $travel_date = (string)$r['travel_date']; // if needed: $travel_date = substr($travel_date,0,10);
    $key = $norm_name . '|' . mb_strtolower($trip_code, 'UTF-8') . '|' . $travel_date;

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'pax_name_display' => trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')),
            'trip_code'        => $trip_code,
            'travel_date'      => $travel_date,
            'order_ids'        => [],
            'items'            => [],
        ];
    }
    $groups[$key]['order_ids'][$r['bookingid']] = true;
    $groups[$key]['items'][] = [
        'bookingid'        => $r['bookingid'],
        'email_pax'        => $r['email_pax'],
        'fname'            => $r['fname'],
        'lname'            => $r['lname'],
        'order_date'       => $r['order_date'],
        'payment_modified' => $r['payment_modified'],
    ];
}

$dupes = [];
foreach ($groups as $g) {
    if (count($g['order_ids']) >= 2) {
        $g['distinct_orders'] = array_keys($g['order_ids']);
        unset($g['order_ids']);
        $dupes[] = $g;
    }
}
/* Sort by travel_date desc, then pax name asc */
usort($dupes, function($a,$b){
    $ad = strtotime($a['travel_date'] ?? '1970-01-01');
    $bd = strtotime($b['travel_date'] ?? '1970-01-01');
    if ($ad === $bd) {
        return strcmp(
            mb_strtolower($a['pax_name_display'] ?? '', 'UTF-8'),
            mb_strtolower($b['pax_name_display'] ?? '', 'UTF-8')
        );
    }
    return $bd <=> $ad;
});

/* Prepare compact client payload for instant paging */
$clientPayload = [
    'perPage' => 10,
    'dupes'   => $dupes,
];
$totalGroups = count($dupes);

get_header();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.dupe-wrap { max-width: 1200px; margin: 30px auto; padding: 0 12px; }
.dupe-card { background:#fff; border:1px solid #e6e6e6; border-radius:12px; margin-bottom:18px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
.dupe-head { padding:14px 16px; border-bottom:1px solid #eee; display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; }
.dupe-head .chip { display:inline-block; padding:4px 10px; border-radius:999px; background:#f5f7ff; font-size:12px; border:1px solid #e3e8ff; }
.dupe-title { font-weight:600; font-size:18px; margin-right:10px; }
.dupe-table { width:100%; border-collapse:collapse; }
.dupe-table th,.dupe-table td { padding:10px 12px; border-bottom:1px solid #f2f2f2; font-size:13px; text-align:left; }
.dupe-table th { background:#fafafa; font-weight:600; }
.badge { display:inline-block; padding:3px 8px; border-radius:6px; background:#eef8ff; border:1px solid #cde8ff; font-size:12px; }
.toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin:18px 0; }
.toolbar .block { display:flex; flex-direction:column; gap:6px; }
.toolbar label { font-size:12px; color:#444; }
.toolbar input[type="text"]{ padding:9px 10px; border:1px solid #ddd; border-radius:8px; min-width:260px; }
.toolbar button{ padding:9px 14px; border-radius:8px; border:1px solid #ddd; background:#fff; cursor:pointer; }
.toolbar button:hover{ background:#f6f6f6; }
.summary { font-size:14px; color:#333; margin:8px 0 12px; }
.empty { margin:24px 0; color:#666; }
.pagination { display:flex; gap:6px; flex-wrap:wrap; margin:16px 0 28px; }
.pagination a,.pagination span { padding:7px 11px; border:1px solid #ddd; border-radius:8px; text-decoration:none; font-size:13px; color:#333; background:#fff; cursor:pointer; }
.pagination .active { background:#1a73e8; color:#fff; border-color:#1a73e8; }
.pagination .disabled { opacity:.5; pointer-events:none; }
:root { --brand: #ffbb00; }

.toolbar .btn-apply{
  background: var(--brand);
  border-color: var(--brand);
  color: #fff;
}
.toolbar .btn-apply:hover{
  filter: brightness(0.95);
}

:root {
  --reset-bg: #e6f3ff;   /* light blue */
  --reset-bd: #bcdfff;   /* slightly darker border */
  --reset-tx: #0b3d91;   /* readable blue text */
}

.toolbar .btn-reset{
  background: var(--reset-bg) !important;
  border-color: var(--reset-bd) !important;
  color: var(--reset-tx) !important;
}
.toolbar .btn-reset:hover{
  filter: brightness(0.97);
}
</style>

<div class="dupe-wrap" id="dupeTop">
    <h2>Duplicate Bookings (Pax Name + trip_code + travel_date)</h2>

    <form method="get" class="toolbar" id="filterForm">
        <div class="block">
            <label>Order Date (range)</label>
            <input type="text" id="od_range" name="od_range" value="<?php echo h($od_range_display); ?>" placeholder="Select date range" />
        </div>
        <div class="block">
            <label>Payment Modified (range)</label>
            <input type="text" id="pm_range" name="pm_range" value="<?php echo h($pm_range_display); ?>" placeholder="Select date range" />
        </div>

        <!-- Hidden fields backend actually uses -->
        <input type="hidden" id="od_from" name="od_from" value="<?php echo h($od_from); ?>">
        <input type="hidden" id="od_to"   name="od_to"   value="<?php echo h($od_to); ?>">
        <input type="hidden" id="pm_from" name="pm_from" value="<?php echo h($pm_from); ?>">
        <input type="hidden" id="pm_to"   name="pm_to"   value="<?php echo h($pm_to); ?>">

        <div class="block" style="gap:8px;">
            <button type="submit" class="btn-apply">Apply Filters</button>
            <a href="<?php echo path_only(); ?>" class="button btn-reset" style="text-decoration:none; padding:9px 14px; border:1px solid #ddd; border-radius:8px; display:inline-block;">Reset</a>
        </div>
    </form>

    <div class="summary">
        Rows matched: <strong><?php echo number_format(count($rows)); ?></strong> &middot;
        Duplicate groups: <strong id="sumTotal"><?php echo number_format($totalGroups); ?></strong> &middot;
        Showing: <strong id="sumRange">0–0</strong>
    </div>

    <!-- Top pager -->
    <div class="pagination" id="pagerTop"></div>

    <!-- List renders here -->
    <div id="dupeList"></div>

    <!-- Bottom pager -->
    <div class="pagination" id="pagerBottom"></div>
</div>

<!-- Embed all data once for instant client-side paging -->
<script id="dupeData" type="application/json"><?php echo json_encode($clientPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function(){
    const $ = (sel, ctx=document)=>ctx.querySelector(sel);
    const $$ = (sel, ctx=document)=>Array.from(ctx.querySelectorAll(sel));

    // Simple HTML escaper for safe insertion
    function esc(s){
        if(s===null||s===undefined) return '';
        return String(s)
          .replace(/&/g,'&amp;').replace(/</g,'&lt;')
          .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    // Init range pickers
    function initRangePicker(inputId, fromId, toId, defFrom, defTo){
        const el = document.getElementById(inputId);
        const fromEl = document.getElementById(fromId);
        const toEl   = document.getElementById(toId);
        if(!el || !fromEl || !toEl) return;
        flatpickr(el, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            allowInput: true,
            defaultDate: [defFrom, defTo],
            rangeSeparator: ' to ',
            onChange: function(_, dateStr){
                const parts = dateStr.split(' to ');
                fromEl.value = parts[0] || '';
                toEl.value   = (parts[1] || parts[0] || '');
            }
        });
        const form = document.getElementById('filterForm');
        if(form){
            form.addEventListener('submit', function(){
                const val = el.value.trim();
                const parts = val.split(' to ');
                fromEl.value = parts[0] || '';
                toEl.value   = (parts[1] || parts[0] || '');
            });
        }
    }
    initRangePicker('od_range','od_from','od_to','<?php echo h($od_from); ?>','<?php echo h($od_to); ?>');
    initRangePicker('pm_range','pm_from','pm_to','<?php echo h($pm_from); ?>','<?php echo h($pm_to); ?>');

    // Client-side pagination state
    const data = JSON.parse(document.getElementById('dupeData').textContent || '{"dupes":[],"perPage":10}');
    const dupes = data.dupes || [];
    const perPage = data.perPage || 10;

    let currentPage = Math.max(1, parseInt(new URLSearchParams(location.search).get('pg')||'1',10));
    const totalGroups = dupes.length;
    const totalPages = Math.max(1, Math.ceil(totalGroups / perPage));

    function renderPage(n){
        currentPage = Math.min(Math.max(1,n), totalPages);
        const start = (currentPage-1)*perPage;
        const end   = Math.min(start + perPage, totalGroups);
        const slice = dupes.slice(start, end);

        // Summary
        $('#sumRange').textContent = totalGroups ? ( (start+1) + '–' + end ) : '0–0';

        // Render cards
        const list = $('#dupeList');
        let html = '';
        slice.forEach(group => {
            const pax  = group.pax_name_display || '(No name)';
            const trip = group.trip_code || '(No trip_code)';
            const tdate= group.travel_date || '(No travel_date)';
            const distinct = group.distinct_orders || [];
            html += `
            <div class="dupe-card">
              <div class="dupe-head">
                <div class="dupe-title">${esc(pax)}</div>
                <div class="chip">trip_code: <strong>${esc(trip)}</strong></div>
                <div class="chip">travel_date: <strong>${esc(tdate)}</strong></div>
                <div class="chip">distinct bookings: <strong>${distinct.length}</strong></div>
              </div>
              <div class="dupe-body" style="padding:8px 0 2px;">
                <table class="dupe-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Booking ID (order_id)</th>
                      <th>Pax Email</th>
                      <th>Order Date</th>
                      <th>Payment Modified</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${ (group.items||[]).map((it,idx)=>`
                      <tr>
                        <td>${idx+1}</td>
                        <td><span class="badge">${esc(it.bookingid)}</span></td>
                        <td>${esc(it.email_pax)}</td>
                        <td>${esc(it.order_date)}</td>
                        <td>${esc(it.payment_modified)}</td>
                      </tr>
                    `).join('') }
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="7" style="text-align:left;">
                        Distinct order_ids:
                        ${ distinct.map(oid=>`<span class="badge">${esc(oid)}</span>`).join(' ') }
                      </th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>`;
        });
        list.innerHTML = html || `<div class="empty">No duplicates found for the selected date ranges.</div>`;

        // Build pagers (top & bottom)
        buildPager($('#pagerTop'));
        buildPager($('#pagerBottom'));

        // Update URL (no reload) and scroll to top of list
        const url = new URL(location.href);
        url.searchParams.set('pg', String(currentPage));
        history.replaceState(null, '', url);
        document.getElementById('dupeTop').scrollIntoView({behavior:'smooth', block:'start'});
    }

    function buildPager(container){
        if(!container) return;
        const disabledFirst = currentPage<=1 ? 'disabled' : '';
        const disabledLast  = currentPage>=totalPages ? 'disabled' : '';
        const rangeStart = Math.max(1, currentPage-2);
        const rangeEnd   = Math.min(totalPages, currentPage+2);

        let html = `
          <a class="${disabledFirst}" data-page="1">« First</a>
          <a class="${disabledFirst}" data-page="${Math.max(1,currentPage-1)}">‹ Prev</a>
        `;
        for(let i=rangeStart; i<=rangeEnd; i++){
            html += (i===currentPage)
                ? `<span class="active">${i}</span>`
                : `<a data-page="${i}">${i}</a>`;
        }
        html += `
          <a class="${disabledLast}" data-page="${Math.min(totalPages,currentPage+1)}">Next ›</a>
          <a class="${disabledLast}" data-page="${totalPages}">Last »</a>
        `;
        container.innerHTML = html;

        $$('a[data-page]', container).forEach(a=>{
            a.addEventListener('click', (e)=>{
                e.preventDefault();
                const n = parseInt(a.getAttribute('data-page'),10);
                if(!isNaN(n)) renderPage(n);
            });
        });
    }

    // Initial render
    renderPage(currentPage);
})();
</script>

<?php get_footer();
