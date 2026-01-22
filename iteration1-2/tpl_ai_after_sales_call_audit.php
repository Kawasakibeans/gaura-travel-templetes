<?php
/**
 * Template Name: AI Transcript Analysis after Sales Call
 * Template Post Type: post, page
 */
get_header();

$api = esc_url( get_template_directory_uri() . '/templates-3/ai_api/get-after-sales-audio-data.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
  .wrap{max-width:1400px;margin:24px auto;padding:16px}
  .muted{color:#6b7280}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  .section{border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-bottom:16px;background:#fff}
  h2,h3{margin:.2rem 0}

  .toggle{display:flex;gap:8px;margin-bottom:12px}
  .btn{padding:8px 12px;border:1px solid #e5e7eb;border-radius:999px;background:#000;cursor:pointer;color:#fff}
  .btn.active{background:#ffbb00;border-color:#f59e0b;color:#111}

  table.rec{width:100%;border-collapse:collapse}
  table.rec th, table.rec td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
  table.rec thead th{background:#ffbb00;text-align:left;white-space:nowrap}
  .link{color:#1d4ed8;text-decoration:none}
  .link:hover{text-decoration:underline}

  .ca-card{border:1px solid #eef2f7;border-radius:14px;padding:14px 14px 12px;margin:12px 0;background:#fcfdff}
  .ca-header{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-bottom:10px;border-bottom:1px dashed #e8edf3}
  .ca-title{font-weight:700}
  .badges{display:flex;gap:6px;flex-wrap:wrap}
  .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;border:1px solid #e5e7eb;background:#f7f7f7}
  .badge.neg{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .badge.pos{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
  .badge.neu{background:#fffbeb;border-color:#fed7aa;color:#92400e}
  .badge-hi{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .badge-lo{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
  .badge-mid{background:#fffbeb;border-color:#fed7aa;color:#92400e}
  .badge-score{background:#f1f5f9;border-color:#e2e8f0;color:#0f172a}
  .badge-cb{background:#fff1f2;border-color:#fecdd3;color:#9f1239}
  .ca-body{display:grid;grid-template-columns:1fr;gap:10px;margin-top:12px}
  .recommendation-box{border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;padding:12px 14px;}
  .recommendation-box .label{font-weight:700;display:block;margin-bottom:4px;font-size:14px;color:#1e40af}
  .recommendation-box .text{font-size:16px;line-height:1.55}
  .notes{margin-top:4px}
  .notes .label{font-size:13px;color:#6b7280;margin-bottom:4px}
  .notes ul{margin:0;padding-left:1.1rem}
  .notes li{margin:.15rem 0}
  .notes .empty{color:#9ca3af}
  .actions{display:flex;gap:8px;margin-top:6px}
  .btn-link{font-size:12px;text-decoration:none;border:1px solid #e5e7eb;border-radius:999px;padding:4px 8px;background:#fff;color:#1f2937}
  .btn-link:hover{background:#f9fafb}
  .score{font-weight:700}
  .score.good{color:#0f766e}
  .score.mid{color:#DEA200}
  .score.bad{color:#991b1b}
  table.scorecard{width:100%;border-collapse:collapse;margin-top:8px}
  table.scorecard th, table.scorecard td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;font-size:15px}
  table.scorecard thead th{background:#ffbb00;font-weight:700;color:#111827;white-space:nowrap}
  table.scorecard td.metric{width:220px;font-weight:600}
  table.scorecard td.met, table.scorecard th.met{width:80px;text-align:center}
  .evidence-tip{position:relative;cursor:help;border-bottom:1px dotted #9ca3af}
  .evidence-tip:hover::after{
    content: attr(data-tip); position:absolute; left:0; top:100%; transform: translateY(8px); max-width:560px;
    background:#111827; color:#fff; padding:10px 12px; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,.15);
    white-space:pre-wrap; z-index:20;
  }
  .evidence-tip:hover::before{content:""; position:absolute; left:12px; top:100%; transform: translateY(3px);
    border:6px solid transparent; border-top-color:#111827; z-index:21; }

  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
  .filters .field{display:flex;flex-direction:column;gap:4px}
  .filters label{font-size:12px;color:#6b7280}
  .filters input,.filters select{padding:8px;border:1px solid #e5e7eb;border-radius:8px;min-width:160px}

  .small{font-size:12px;color:#6b7280}
  .btn-mini{padding:6px 10px;border:1px solid #e5e7eb;border-radius:999px;background:#111;color:#fff;cursor:pointer}
  .btn-mini[disabled]{opacity:.6;cursor:not-allowed}
  .pill{display:inline-block;padding:4px 8px;border:1px solid #d1d5db;border-radius:999px;background:#f9fafb;font-size:12px}
  .ok{color:#065f46}

  /* NEW: status coloring */
  .status{font-weight:600}
  .status.Done{color:#065f46}
  .status.Pending{color:#92400e}
  .status.error{color:#991b1b}

  /* Pager */
  .pager{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:10px}
  .page-btn{padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#000;cursor:pointer}
  .page-btn.active{background:#111;color:#ffbb00;border-color:#111}
  .page-btn[disabled]{opacity:.6;cursor:not-allowed}
  .pager .muted{margin-left:6px}
  .flash-focus{ box-shadow: 0 0 0 3px #ffbb00 inset; transition: box-shadow .8s ease; } /* edit */
  
/* Modal styles (REPLACE your earlier modal CSS with this block) */
#analysisModal { position: fixed; inset: 0; z-index: 9999; display: none; }
#analysisModal .am-backdrop {
  position: absolute; inset: 0; background: rgba(0,0,0,.45);
}
#analysisModal .am-dialog {
  position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
  width: min(1200px, 96vw);
  max-height: 92vh;                 /* give the dialog a max height */
  background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.35);
  padding: 16px;
  display: flex; flex-direction: column; gap: 12px;
  overflow: auto;                   /* was hidden — allow the dialog to scroll */
}
#analysisModal .am-close {
  position: absolute; top: 8px; right: 10px;
  border: 1px solid #e5e7eb; background: #000; border-radius: 999px;
  padding: 4px 10px; cursor: pointer; font-size: 18px; line-height: 1;
}
#am-content {
  /* let content scroll if taller than dialog’s space */
  overflow: auto;                   /* was hidden */
  display: grid; grid-template-rows: auto auto 1fr; gap: 10px;
  min-height: 0;                    /* critical so the 1fr row can shrink and enable scrolling */
}

#am-content .am-header { border-bottom: 1px dashed #e8edf3; padding-bottom: 8px; }
#am-content .am-grid { 
  display: grid; 
  grid-template-columns: 1fr;   /* always 1 column */
  gap: 10px; 
}

@media (min-width: 900px){ }

/* Scrollable transcript (both axes) */
.am-transcript{
  border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px;
  font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
  font-size: 16px; line-height: 1.6;
  min-height: 40vh;          /* start tall */
  max-height: 70vh;          /* but don’t exceed modal */
  overflow-y: auto; overflow-x: hidden;
  white-space: pre-wrap; word-break: break-word; background: #fcfcfd;
  resize: vertical;          /* let users drag to grow/shrink */
}

tr.low-score td {
  background-color: #ffecec; /* light pastel red */
}

/* === DASHBOARD === */
.kpi-wrap{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;margin-top:8px}
.kpi{grid-column:span 3;background:#fcfdff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}
.kpi h4{margin:0 0 6px 0;font-size:14px;color:#6b7280;font-weight:600}
.kpi .val{font-size:28px;font-weight:800;line-height:1.1}
.kpi.good .val{color:#065f46}
.kpi.bad  .val{color:#991b1b}
.kpi.mid  .val{color:#92400e}
.kpi .sub{font-size:12px;color:#6b7280;margin-top:2px}

.row{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;margin-top:12px}
.card{grid-column:span 6;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}
.card h4{margin:0 0 10px 0;font-weight:800}

.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #e5e7eb;background:#f7f7f7;margin-left:6px}
.badge.good{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.badge.bad{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.badge.mid{background:#fffbeb;border-color:#fed7aa;color:#92400e}

/* Progress bars */
.bar{height:10px;border-radius:999px;background:#f3f4f6;overflow:hidden}
.bar > i{display:block;height:100%;background:#16a34a}     /* green default */
.bar.bad > i{background:#dc2626}                            /* red */
.bar.mid > i{background:#f59e0b}                            /* amber */

/* Tiny list */
.list{margin:0;padding:0;list-style:none}
.list li{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #eef2f7}
.list li:last-child{border-bottom:none}

.chip.big{
  font-size: 14px;          /* was ~12px */
  padding: 4px 10px;        /* a bit more breathing room */
  border-radius: 999px;     /* keep it pill-y */
  line-height: 1;           
}

/* Optional: make the number pop inside the chip */
.chip.big .num{
  font-weight: 700;
  font-size: 1.15em;        /* number slightly larger than the text */
  margin-right: 4px;
}

.rec td .score{ font-weight:700; }
.page-filters { margin-top: 4px; }

/* remark UI */
.remark-icon{
  margin-left:6px; cursor:pointer; font-size:13px;
  display:inline-flex; align-items:center; gap:4px;
  color:#374151; border:1px solid #e5e7eb; background:#fff; padding:2px 6px; border-radius:999px;
}
.remark-icon:hover{background:#f9fafb}
.btn-remark{
  margin-left:8px; font-size:12px; padding:4px 8px; border-radius:999px;
  border:1px solid #e5e7eb; background:#111; color:#fff; cursor:pointer;
}
.btn-remark[disabled]{opacity:.6;cursor:not-allowed}

</style>

<div class="wrap">

    <div id="filtersA" class="section page-filters" style="margin-bottom:12px;">
        <h3 style="margin:0 0 8px 0;">Filters</h3>
        <div class="filters">
            <div class="field">
                <label for="faCampaign">Campaign (appl)</label>
                <select id="faCampaign">
                    <option value="">— Any —</option>
                </select>
            </div>

            <div class="fa-field">
                <label for="faDateFrom">Call date (from)</label>
                <input type="text" id="faDateFrom">
            </div>

            <div class="fa-field">
                <label for="faDateTo">Call date (to)</label>
                <input type="text" id="faDateTo">
            </div>
            <div class="field">
              <label for="faAgent">Agent (single date)</label>
              <select id="faAgent" disabled>
                <option value="">— Select a single date —</option>
              </select>
            </div>



            <div class="field">
                <label for="faScore">Score band</label>
                <select id="faScore">
                    <option value="">— Any —</option>
                    <option value="0-60">0–60</option>
                    <option value="60-85">60–85</option>
                    <option value="85-100">85–100</option>
                </select>
            </div>

            <div class="field">
                <label for="faStatus">Status</label>
                <select id="faStatus">
                    <option value="">— Any —</option>
                    <option value="done">Done</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <button id="faApply" class="btn">Apply</button>
            <button id="faClear" class="btn" style="background:#444;">Clear</button>
        </div>
        <div id="faMeta" class="small" style="margin-top:6px"></div>
    </div>

    <div class="toggle">
        <button id="tabA" class="btn active">Recordings - After Sales</button>
        <button id="tabB" class="btn">Filtered Analysis</button>
    </div>

    <!-- View A -->
    <div id="viewA">
        <div class="section">
            <h2>Recordings</h2>
            <div id="rec-status" class="muted" style="margin-bottom:8px">Loading</div>
            <div class="actions" style="margin:8px 0;">
                <button id="btnRefresh" class="btn-mini">Refresh</button>
            </div>

            <div id="analysisDashboard" class="section" style="margin-bottom:16px; display:none">
                <h3>Analysis Dashboard</h3>
                <div class="flex gap-4">
                    <div>Analysed calls: <strong id="dashAnalysed">0</strong></div>
                    <div>High urgency: <strong id="dashHigh">0</strong></div>
                    <div>Negative sentiment: <strong id="dashNegative">0</strong></div>
                </div>
            </div>
            <div id="rec-table"></div>
            <div id="pagerA" class="pager"></div>
        </div>

        <!--  <div class="section">-->
        <!--    <h2 style="font-size:28px">Analysis (selected file)</h2>-->
        <!--    <div id="analysisA">Select a row with “View analysis”.</div>-->
        <!--  </div>-->

        <!--  <div class="section">-->
        <!--    <h3>Scorecard</h3>-->
        <!--    <div id="scorecardsA"></div>-->
        <!--  </div>-->

        <!--  <div class="section">-->
        <!--    <h3>Transcript</h3>-->
        <!--    <div id="transcriptsA" class="mono"></div>-->
        <!--  </div>-->
    </div>

    <!-- View B -->
    <div id="viewB" style="display:none">
        <div class="section">
            <h2>Filtered Analysis</h2>
            <div class="filters">
                <div class="field">
                    <label for="fCallId">Call ID</label>
                    <select id="fCallId">
                        <option value="">— Any —</option>
                    </select>
                </div>
                <div class="field">
                    <label for="fScore">Score band</label>
                    <select id="fScore">
                        <option value="">— Any —</option>
                        <option value="0-60">0–60</option>
                        <option value="60-85">60–85</option>
                        <option value="85-100">85–100</option>
                    </select>
                </div>
                <div class="field">
                    <label for="fUrg">Urgency</label>
                    <select id="fUrg">
                        <option value="">— Any —</option>
                        <option value="low">low</option>
                        <option value="medium">medium</option>
                        <option value="high">high</option>
                    </select>
                </div>
                <button id="btnApply" class="btn">Apply filters</button>
            </div>
            <div id="statusB" class="muted" style="margin-top:8px">Nothing loaded yet.</div>
        </div>

        <div class="section">
            <h3>Results</h3>
            <div id="analysisB"></div>
        </div>
    </div>

    <!-- Modal -->
    <div id="analysisModal" style="display:none">
        <div class="am-backdrop"></div>
        <div class="am-dialog" role="dialog" aria-modal="true" aria-labelledby="am-title">
            <button class="am-close" aria-label="Close">×</button>
            <div id="am-content"></div>
        </div>
    </div>

</div>

<script>
(function(){
  // Wait for DOM to be fully loaded
  function initApp() {
    const apiBase = "<?php echo $api; ?>";

    const el = {
      tabA: document.getElementById('tabA'),
      tabB: document.getElementById('tabB'),
      viewA: document.getElementById('viewA'),
      viewB: document.getElementById('viewB'),

      recStatus: document.getElementById('rec-status'),
      recTable: document.getElementById('rec-table'),
      btnRefresh: document.getElementById('btnRefresh'),
      pagerA: document.getElementById('pagerA'),

      fCallId: document.getElementById('fCallId'),
      fScore: document.getElementById('fScore'),
      fUrg: document.getElementById('fUrg'),
      btnApply: document.getElementById('btnApply'),
      statusB: document.getElementById('statusB'),
      analysisB: document.getElementById('analysisB'),

      faCampaign: document.getElementById('faCampaign'),
      faDateFrom: document.getElementById('faDateFrom'),
      faDateTo: document.getElementById('faDateTo'),
      faAgent: document.getElementById('faAgent'),
      faScore: document.getElementById('faScore'),
      faStatus: document.getElementById('faStatus'),
      faApply: document.getElementById('faApply'),
      faClear: document.getElementById('faClear'),
      faMeta: document.getElementById('faMeta'),
    };

    // Check if all required elements exist
    const requiredElements = ['tabA', 'tabB', 'viewA', 'viewB', 'recStatus', 'recTable', 'btnRefresh', 'faApply', 'faClear'];
    const missingElements = requiredElements.filter(key => !el[key]);
    if (missingElements.length > 0) {
      console.error('Missing required elements:', missingElements);
      return; // Exit if critical elements are missing
    }

    // ====== Default both dates to YESTERDAY in Australia/Melbourne ======
    function yesterdayISOInTZ(timeZone='Australia/Melbourne'){
    const now = new Date();
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone, year:'numeric', month:'2-digit', day:'2-digit'
    }).formatToParts(now);
    let y = +parts.find(p=>p.type==='year').value;
    let m = +parts.find(p=>p.type==='month').value;
    let d = +parts.find(p=>p.type==='day').value - 1;
    if (d < 1) {
      m -= 1;
      if (m < 1) { m = 12; y -= 1; }
      d = new Date(y, m, 0).getDate();
    }
      return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    }
    const YESTERDAY_ISO = yesterdayISOInTZ('Australia/Melbourne');

    function initOrGetPicker(input){
      if(!input) return null;
      return input._flatpickr || flatpickr(input, {
        dateFormat:'Y-m-d',
        altInput:true,
        altFormat:'d-m-Y',
        allowInput:true
      });
    }

    function forceYesterday(){
      if (window.flatpickr && el.faDateFrom && el.faDateTo) {
        const fpFrom = initOrGetPicker(el.faDateFrom);
        const fpTo   = initOrGetPicker(el.faDateTo);
        if(fpFrom) fpFrom.setDate(YESTERDAY_ISO, true);
        if(fpTo) fpTo.setDate(YESTERDAY_ISO, true);
        if(el.faDateFrom) el.faDateFrom.value = YESTERDAY_ISO;
        if(el.faDateTo) el.faDateTo.value   = YESTERDAY_ISO;
      } else {
        if(el.faDateFrom) el.faDateFrom.value = YESTERDAY_ISO;
        if(el.faDateTo) el.faDateTo.value   = YESTERDAY_ISO;
      }
    }

    // Set yesterday at boot & re-assert shortly after
    forceYesterday();
    requestAnimationFrame(forceYesterday);
    setTimeout(forceYesterday, 120);

    // ====== Agent list (only for a single selected date) ======
    function sameDayStr(a,b){ return a && b && a === b; }

    function updateAgentOptions(){
      if(!el.faDateFrom || !el.faDateTo || !el.faAgent) return;
      const fromStr = el.faDateFrom.value;
      const toStr   = el.faDateTo.value;

      if (!sameDayStr(fromStr, toStr) || !fromStr) {
        el.faAgent.innerHTML = `<option value="">— Select a single date —</option>`;
        el.faAgent.disabled = true;
        return;
      }

      const agents = Array.from(new Set(
        allRows
          .filter(r => (r.call_date || '').slice(0,10) === fromStr)
          .map(r => (r.agent_name || '').trim())
          .filter(Boolean)
      )).sort((a,b)=>a.localeCompare(b));

      const opts = [`<option value="">— Any —</option>`]
        .concat(agents.map(a => `<option value="${a.replace(/"/g,'&quot;')}">${a.replace(/</g,'&lt;')}</option>`))
        .join('');

      el.faAgent.innerHTML = opts;
      el.faAgent.disabled = false;
    }

    // ====== Modal ======
    const modal = {
      root: document.getElementById('analysisModal'),
      body: document.getElementById('am-content'),
      closeBtn: null,
      open(html){
        if(!this.body || !this.root) return;
        this.body.innerHTML = html;
        this.root.style.display = 'block';
        if(!this.closeBtn) this.closeBtn = this.root.querySelector('.am-close');
        if(this.closeBtn) this.closeBtn.onclick = () => this.close();
        const backdrop = this.root.querySelector('.am-backdrop');
        if(backdrop) backdrop.onclick = () => this.close();
        document.addEventListener('keydown', this._esc);
      },
      close(){
        if(!this.root || !this.body) return;
        this.root.style.display = 'none';
        this.body.innerHTML = '';
        document.removeEventListener('keydown', this._esc);
      },
      _esc(e){ if(e.key === 'Escape'){ modal.close(); } }
    };

    // ====== Utils ======
    const PAGE_SIZE = 25;
    let allRows = []; let viewRows = []; let currentPage = 1;
    const tick = v => v ? '✅' : '❌';
    const escapeHtml = (s)=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');

    // Normalize date to local timezone midnight (00:00:00) for consistent comparison
    function normalizeDate(dateOrString){
      if(!dateOrString) return null;
      let d;
      if(dateOrString instanceof Date){
        d = dateOrString;
      } else if(typeof dateOrString === 'string'){
        // Try to parse as YYYY-MM-DD first
        const match = dateOrString.match(/^(\d{4})[-/](\d{2})[-/](\d{2})/);
        if(match){
          const [, y, m, day] = match;
          d = new Date(+y, +m - 1, +day);  // Local timezone
        } else {
          d = new Date(dateOrString);
        }
      } else {
        return null;
      }
      if(isNaN(d.getTime())) return null;
      // Return date normalized to local midnight
      return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }
    
    function toDateISO(d){
      if(!d) return null; 
      const [y,m,day]=d.split('-').map(Number);
      if(!y||!m||!day) return null; 
      // Return normalized date (local timezone midnight)
      return new Date(y, m-1, day);
    }
    function inBand(score, band){
      if(score==null||score===''||typeof score!=='number') return false;
      if(band==='0-60') return score>=0&&score<60;
      if(band==='60-85') return score>=60&&score<85;
      if(band==='85-100') return score>=85&&score<=100;
      return true;
    }
    function statusMatch(analyzed, wanted){
      if(!wanted) return true; if(wanted==='done') return !!analyzed; if(wanted==='pending') return !analyzed; return true;
    }
    function parseCallDate(val){
      if(!val) return null;
      const s=String(val).trim(); let m;
      if((m=s.match(/^(\d{4})[-/](\d{2})[-/](\d{2})$/))){ const y=+m[1],mo=+m[2]-1,d=+m[3]; const dt=new Date(Date.UTC(y,mo,d)); return isNaN(dt)?null:dt; }
      if((m=s.match(/^(\d{2})[-/](\d{2})[-/](\d{4})$/))){ const d=+m[1],mo=+m[2]-1,y=+m[3]; const dt=new Date(Date.UTC(y,mo,d)); return isNaN(dt)?null:dt; }
      const dt=new Date(s); return isNaN(dt)?null:dt;
    }
    function formatDMY(dateOrString){
      const dt = dateOrString instanceof Date ? dateOrString : parseCallDate(dateOrString);
      if(!dt) return '-';
      const d=String(dt.getUTCDate()).padStart(2,'0');
      const m=String(dt.getUTCMonth()+1).padStart(2,'0');
      const y=dt.getUTCFullYear();
      return `${d}-${m}-${y}`;
    }

    // ====== Remarks (modal scorecard) ======
    const METRIC_LABELS = {
      acknowledgment: 'Acknowledgment',
      security: 'Security',
      support: 'Support',
      understanding: 'Understanding',
      resolve: 'Resolve',
      end_well: 'End well'
    };
    function parseRemarks(raw){ if(!raw) return {}; try{ if(typeof raw==='object') return raw||{}; return JSON.parse(raw);}catch(e){return {};} }
    async function saveRemarkAPI(id, metric, text){
      const body = new URLSearchParams({ action:'save_remark', id:String(id||''), metric:String(metric||''), text:String(text||'') });
      const r = await fetch(`${apiBase}`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      const ct = r.headers.get('content-type')||''; const respText = await r.text();
      if(!r.ok) throw new Error(`HTTP ${r.status} ${r.statusText}: ${respText.slice(0,200)}`);
      if(!ct.includes('application/json')) throw new Error(`Non-JSON: ${respText.slice(0,200)}`);
      const j = JSON.parse(respText); if(!j.ok) throw new Error(j.error || 'Failed to save remark'); return j;
    }
    function renderScorecard(sc, remarksMap, recordId){
      const safe = k => sc && sc[k] ? sc[k] : {met:false,evidence:'',tooltip:''};
      const row = (label,key)=>{
        const v = safe(key);
        const evidenceText = v.evidence ? String(v.evidence) : '-';
        const metIcon = tick(!!v.met);
        const remarkText = (remarksMap && remarksMap[key]) ? String(remarksMap[key]) : '';
        const hasRemark = !!remarkText;
        const evidenceHtml = (!!v.met && v.tooltip)
          ? `<span class="evidence-tip" data-tip="${escapeHtml(v.tooltip)}">${escapeHtml(evidenceText)}</span>`
          : escapeHtml(evidenceText);
        const labelHtml = `${escapeHtml(label)} ${hasRemark ? `<button class="remark-icon" data-metric="${key}" data-id="${recordId}" title="View remark">📝 <span>remark</span></button>` : ''}`;
        const addBtn = `<button class="btn-remark" data-add-remark="1" data-metric="${key}" data-id="${recordId}">Add remark</button>`;
        return `<tr><td class="metric">${labelHtml}</td><td class="met">${metIcon}</td><td>${evidenceHtml} ${addBtn}</td></tr>`;
      };
      return `
        <table class="scorecard">
          <thead><tr><th>Metric</th><th class="met">Met</th><th>Evidence</th></tr></thead>
          <tbody>
            ${row('Acknowledgment','acknowledgment')}
            ${row('Security','security')}
            ${row('Support','support')}
            ${row('Understanding','understanding')}
            ${row('Resolve','resolve')}
            ${row('End well','end_well')}
          </tbody>
        </table>`;
    }
    function cardFor(item){
      const score = (typeof item.overall_score === 'number') ? item.overall_score : null;
      const scoreCls = score == null ? 'mid' : (score >= 85 ? 'good' : score >= 60 ? 'mid' : 'bad');
      const sentimentCls = item.sentiment === 'negative' ? 'neg' : item.sentiment === 'positive' ? 'pos' : 'neu';
      const urgCls = item.urgency === 'high' ? 'hi' : item.urgency === 'low' ? 'lo' : 'mid';
      const lang = item.lang_used || '-';
      const callLabel = item.call_id ? `#${escapeHtml(item.call_id)}` : (item.id != null ? `#${escapeHtml(item.id)}` : '#');
      return `
        <div class="ca-card">
          <div class="ca-header">
            <div class="ca-title">${callLabel} • ${escapeHtml(item.uploaded_at || '')}</div>
            <div class="badges">
              <span class="badge">lang: ${escapeHtml(lang)}</span>
              <span class="badge ${'badge-' + sentimentCls}">${escapeHtml(item.sentiment || '-')}</span>
              <span class="badge ${'badge-' + urgCls}">urgency: ${escapeHtml(item.urgency || '-')}</span>
              ${score != null ? `<span class="badge badge-score"><span class="score ${scoreCls}">${score}</span>/100</span>` : ''}
              ${item.call_back_needed ? `<span class="badge badge-cb">call-back</span>` : ''}
            </div>
          </div>
        </div>`;
    }

    function wireViewHandler(btn){
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-id');
        btn.textContent = 'Loading…'; btn.disabled = true;
        fetch(`${apiBase}?action=get_analysis_by_id&id=${encodeURIComponent(id)}`)
          .then(async r=>{ const ct=r.headers.get('content-type')||''; const body=await r.text();
            if(!r.ok) throw new Error(`HTTP ${r.status} ${r.statusText}: ${body.slice(0,200)}`);
            if(!ct.includes('application/json')) throw new Error(`Non-JSON response: ${body.slice(0,200)}`);
            return JSON.parse(body);
          })
          .then(j=>{
            if(!j.ok) throw new Error(j.error || 'Failed');
            const item = j.evaluation;
            const remarksMap = parseRemarks(item.remark);
            const card = cardFor(item);
            const scHTML = item.scorecard ? renderScorecard(item.scorecard, remarksMap, item.id || item.analysis_id) : '<div class="muted">No scorecard.</div>';
            const tx = (j.transcript && j.transcript.transcript) ? j.transcript.transcript : '—';
            const html = `
              <div class="am-header">
                <h2 id="am-title" style="margin:0">Analysis for ${escapeHtml(item.file_num || item.call_id || String(item.id || ''))}</h2>
                <div class="small muted">${escapeHtml(item.uploaded_at || '')} • lang: ${escapeHtml(item.lang_used || '-')}</div>
              </div>
              <div class="am-grid"><div>${card}</div><div id="am-scorecard">${scHTML}</div></div>
              <div><h3 style="margin:.3rem 0">Transcript</h3><div class="am-transcript">${escapeHtml(tx)}</div></div>`;
            modal.open(html);

            const scRoot = modal.body.querySelector('#am-scorecard');
            if(scRoot){
              scRoot.querySelectorAll('.remark-icon').forEach(b=>{
                b.addEventListener('click', ()=>{
                  const m = b.getAttribute('data-metric');
                  const text = remarksMap[m] || '';
                  if(text) alert(`${METRIC_LABELS[m] || m} — Remark:\n\n${text}`);
                });
              });
              scRoot.querySelectorAll('[data-add-remark]').forEach(b=>{
                b.addEventListener('click', async ()=>{
                  const metric = b.getAttribute('data-metric');
                  const recId  = b.getAttribute('data-id');
                  const current = remarksMap[metric] || '';
                  const val = prompt(`Add/Update remark for "${METRIC_LABELS[metric] || metric}":`, current);
                  if(val === null) return;
                  b.disabled = true; b.textContent = 'Saving…';
                  try{
                    const resp = await saveRemarkAPI(recId, metric, val);
                    const newMap = parseRemarks(resp.remark_json) || {};
                    Object.assign(remarksMap, newMap);
                    scRoot.innerHTML = renderScorecard(item.scorecard, remarksMap, recId);
                  }catch(e){ alert('Save failed: ' + e.message); }
                  finally{ b.disabled = false; b.textContent = 'Add remark'; }
                });
              });
            }
          })
          .catch(err=>{ console.error(err); alert('Error: ' + err.message); })
          .finally(()=>{ btn.textContent = 'View analysis'; btn.disabled = false; });
      });
    }

    // ====== Filtering & table ======
    function applyFilters(){
      if(!el.faCampaign || !el.faScore || !el.faStatus || !el.faAgent || !el.faDateFrom || !el.faDateTo) {
        console.error('❌ Filter elements not found');
        return;
      }
      const camp=(el.faCampaign.value||'').trim();
      const band=(el.faScore.value||'').trim();
      const stat=(el.faStatus.value||'').trim().toLowerCase();
      const agent=(el.faAgent.value||'').trim();
      
      // Normalize dates to local timezone midnight for consistent comparison
      const dFrom = toDateISO(el.faDateFrom.value);
      let dTo = toDateISO(el.faDateTo.value);
      // Set dTo to end of day (23:59:59.999) to include all records on that day
      if(dTo) dTo.setHours(23,59,59,999);
      
      viewRows = allRows.filter(r=>{
        if(camp && (String(r.appl||'').trim()!==camp)) return false;
        // Only filter by date if date filters are set
        if(dFrom||dTo){
          const cd = normalizeDate(r.call_date);
          if(!cd) return false;
          if(dFrom && cd < dFrom) return false;
          if(dTo && cd > dTo) return false;
        }
        if(agent && String(r.agent_name||'').trim() !== agent) return false;
        if(band && !inBand(r.score, band)) return false;
        if(!statusMatch(!!r.analyzed, stat)) return false;
        return true;
      });

      const active = [
        camp && `Campaign: ${camp}`,
        (el.faDateFrom.value || el.faDateTo.value) && `Date: ${el.faDateFrom.value || '…'} → ${el.faDateTo.value || '…'}`,
        agent && `Agent: ${agent}`,
        band && `Score: ${band}`,
        stat && `Status: ${stat}`
      ].filter(Boolean).join(' • ');
      if(el.faMeta) el.faMeta.textContent = active ? `Filters: ${active}` : 'No filters applied';

      currentPage = 1;
      renderTablePage(currentPage);
      updateDashboard(viewRows);
      if(el.recStatus) el.recStatus.textContent = `${viewRows.length} record(s) • ${PAGE_SIZE} per page`;
    }

    function renderTablePage(page){
      // Always use viewRows (filtered results), even if empty
      const rows = viewRows;
      const total = rows.length;
      currentPage = Math.max(1, Math.min(page, Math.ceil(Math.max(1,total)/PAGE_SIZE)));
      const start = (currentPage-1)*PAGE_SIZE;
      const slice = rows.slice(start, start+PAGE_SIZE);

      if(!slice.length){ if(el.recTable) el.recTable.innerHTML='<div class="muted">No matching records.</div>'; if(el.pagerA) el.pagerA.innerHTML=''; return; }

    const trs = slice.map(r=>{
      const statusTxt = r.analyzed ? 'Done' : 'Pending';
      const statusCls = r.analyzed ? 'Done' : 'Pending';
      const actionCell = r.analyzed ? `<button class="btn-mini do-view" data-id="${r.analysis_id}">View analysis</button>` : `<span class="small muted">—</span>`;
      const scoreVal = (typeof r.score==='number') ? r.score : null;
      const scoreCell = (scoreVal===null) ? '-' : `<span class="score ${scoreVal>=85 ? 'good' : scoreVal>=60 ? 'mid' : 'bad'}">${escapeHtml(String(scoreVal))}</span>`;
      const lowScoreCls = (scoreVal!==null && scoreVal<60) ? 'low-score' : '';
      return `
        <tr data-call="${escapeHtml(r.call_id||'')}" class="${lowScoreCls}">
          <td>${escapeHtml(formatDMY(r.call_date) || '-')}</td>
          <td>${escapeHtml(r.call_time || '-')}</td>
          <td>${escapeHtml(r.file_num || '-')}</td>
          <td>${escapeHtml(r.agent_name || '-')}</td>
          <td>${escapeHtml(r.phone_no || '-')}</td>
          <td>${escapeHtml(r.appl || '-')}</td>
          <td data-col="status" class="status ${statusCls}">${statusTxt}</td>
          <td>${scoreCell}</td>
          <td data-col="action">${actionCell}</td>
        </tr>`;
    }).join('');

      if(el.recTable) el.recTable.innerHTML = `
        <table class="rec">
          <thead>
            <tr>
              <th>Call date</th><th>Call time</th><th>File num</th><th>Agent</th>
              <th>Phone no.</th><th>Campaign</th><th>Status</th><th>Score</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>${trs}</tbody>
        </table>`;
      if(el.recTable) el.recTable.querySelectorAll('.do-view').forEach(wireViewHandler);
      renderPager(total);
    }

    function renderPager(totalCount){
      if(!el.pagerA) return;
      const totalPages = Math.ceil(totalCount / PAGE_SIZE);
      if(totalPages<=1){ el.pagerA.innerHTML=''; return; }
      const btn=(label,page,disabled=false,active=false)=>`<button class="page-btn ${active?'active':''}" data-page="${page}" ${disabled?'disabled':''}>${label}</button>`;
      const windowSize=7;
      let start=Math.max(1, currentPage-Math.floor(windowSize/2));
      let end=Math.min(totalPages, start+windowSize-1);
      start=Math.max(1, Math.min(start, end-windowSize+1));
      let html='';
      html += btn('« Prev', Math.max(1,currentPage-1), currentPage===1, false);
      if(start>1){ html += btn('1',1,false,currentPage===1); if(start>2) html += `<span class="muted">…</span>`; }
      for(let p=start;p<=end;p++){ html += btn(String(p),p,false,p===currentPage); }
      if(end<totalPages){ if(end<totalPages-1) html += `<span class="muted">…</span>`; html += btn(String(totalPages), totalPages, false, currentPage===totalPages); }
      html += btn('Next »', Math.min(totalPages,currentPage+1), currentPage===totalPages, false);
      el.pagerA.innerHTML=html;
      el.pagerA.querySelectorAll('.page-btn').forEach(b=>b.addEventListener('click',()=>{ const p=parseInt(b.getAttribute('data-page'),10); if(!isNaN(p)) renderTablePage(p); }));
    }

    // Restrict call_ids to specific prefixes
    const GT = ['GTCS', 'GTPY', 'GTRF', 'GTET'];
    const matchesGT = (cid)=>{ const u=(cid||'').toUpperCase(); return GT.some(x=>u.includes(x)); };

    function loadRecordings(){
      if(!el.recStatus || !el.recTable || !el.pagerA) {
        return;
      }
      el.recStatus.textContent='Loading…'; el.recTable.innerHTML=''; el.pagerA.innerHTML='';
      
      // Request timeout handling (30 seconds)
      const TIMEOUT_MS = 30000;
      const controller = new AbortController();
      const timeoutId = setTimeout(() => {
        controller.abort();
        if(el.recStatus) el.recStatus.textContent='Request timeout. API may be slow or unresponsive.';
        if(el.recTable) el.recTable.innerHTML='<div class="muted" style="color:#991b1b;">Request timeout. The API is taking too long to respond.</div>';
      }, TIMEOUT_MS);
      
      fetch(`${apiBase}?action=list_audio_db`, {
        signal: controller.signal,
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        }
      })
        .then(async r => {
          clearTimeout(timeoutId);
          
          const ct = r.headers.get('content-type')||''; 
          const body = await r.text();
          
          if(!r.ok) {
            throw new Error(`HTTP ${r.status} ${r.statusText}: ${body.slice(0,200)}`);
          }
          
          if(!ct.includes('application/json')) {
            throw new Error(`Non-JSON response: ${body.slice(0,200)}`);
          }
          
          let parsed;
          try {
            parsed = JSON.parse(body);
          } catch(parseErr) {
            throw new Error('Invalid JSON: ' + parseErr.message);
          }
          
          return parsed;
        })
        .then(j=>{
          if(!j.ok) {
            throw new Error(j.error || 'Failed');
          }
          
          const allFiles = j.files || [];
          
          if(allFiles.length === 0) {
            if(el.recStatus) el.recStatus.textContent='No records found in database.';
            if(el.recTable) el.recTable.innerHTML='<div class="muted">No records available.</div>';
            return;
          }
          
          // Filter by call_id prefix
          allRows = allFiles.filter(r=>{
            return matchesGT(r.call_id);
          });
          
          const campaigns = Array.from(new Set(allRows.map(r=>(r.appl||'').trim()).filter(Boolean))).sort();
          if(el.faCampaign) el.faCampaign.innerHTML = `<option value="">— Any —</option>` + campaigns.map(c=>`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

          // Build Agent list for yesterday (default single date) & apply filters
          updateAgentOptions();
          viewRows = allRows.slice();
          applyFilters();
        })
        .catch(err=>{ 
          clearTimeout(timeoutId);
          
          // Handle different error types
          if(err.name === 'AbortError') {
            if(el.recStatus) el.recStatus.textContent='Request timeout. API is not responding.';
            if(el.recTable) el.recTable.innerHTML='<div class="muted" style="color:#991b1b;">Request timeout. The API is taking too long to respond.</div>';
          } else if(err.message.includes('Failed to fetch') || err.message.includes('NetworkError')) {
            if(el.recStatus) el.recStatus.textContent='Network error. Cannot reach API.';
            if(el.recTable) el.recTable.innerHTML='<div class="muted" style="color:#991b1b;">Network error. Cannot reach the API endpoint.</div>';
          } else {
            if(el.recStatus) el.recStatus.textContent='Error loading records.';
            if(el.recTable) el.recTable.innerHTML='<div class="muted" style="color:#991b1b;">Error: ' + escapeHtml(err.message) + '</div>';
          }
        });
    }

    function fmt(n){ return (n||0).toLocaleString(); }
    function pct(part,total){ if(!total) return '0%'; const p=Math.round((part/total)*100); return p+'%'; }
    function clamp01(x){ return Math.max(0, Math.min(1,x)); }

    function updateDashboard(rows){
    const total=rows.length;
    const analysed=rows.filter(r=>r.analyzed).length;
    const scored=rows.filter(r=>r.score!=null && r.score!=='').length;
    const highUrg=rows.filter(r=>(r.urgency||'').toLowerCase()==='high').length;
    const medUrg=rows.filter(r=>(r.urgency||'').toLowerCase()==='medium').length;
    const lowUrg=rows.filter(r=>(r.urgency||'').toLowerCase()==='low').length;
    const neg=rows.filter(r=>(r.sentiment||'').toLowerCase()==='negative').length;
    const neu=rows.filter(r=>(r.sentiment||'').toLowerCase()==='neutral').length;
    const pos=rows.filter(r=>(r.sentiment||'').toLowerCase()==='positive').length;
    const lowScore=rows.filter(r=>(typeof r.score==='number') && r.score<60).length;
    const midScore=rows.filter(r=>(typeof r.score==='number') && r.score>=60 && r.score<85).length;
    const highScore=rows.filter(r=>(typeof r.score==='number') && r.score>=85).length;
    const avgScore=scored? Math.round(rows.reduce((s,r)=>s+(typeof r.score==='number'?r.score:0),0)/scored):0;

    const negByAgent = {};
    rows.forEach(r=>{ const key=(r.agent_name||'—').trim(); if((r.sentiment||'').toLowerCase()==='negative'){ negByAgent[key]=(negByAgent[key]||0)+1; }});
    const topAgents = Object.entries(negByAgent).sort((a,b)=>b[1]-a[1]).slice(0,5);

    const wHighUrg = clamp01(highUrg/(highUrg+medUrg+lowUrg||1))*100;
    const wNeg = clamp01(neg/(pos+neu+neg||1))*100;
    const wLow = clamp01(lowScore/(lowScore+midScore+highScore||1))*100;

    const avgCls = avgScore>=85?'good':avgScore>=60?'mid':'bad';
    const urgCls = highUrg===0?'good':(highUrg/Math.max(analysed,1)<=0.05?'mid':'bad');
    const negCls = neg===0?'good':(neg/Math.max(analysed,1)<=0.10?'mid':'bad');

    const html = `
      <h3 style="margin:0 0 8px 0">Analysis Dashboard</h3>
      <div class="kpi-wrap">
        <div class="kpi good"><h4>Total records</h4><div class="val">${fmt(total)}</div><div class="sub">rows loaded</div></div>
        <div class="kpi ${analysed===total?'good':'mid'}"><h4>Analysed</h4><div class="val">${fmt(analysed)} <span class="badge">${pct(analysed,total)}</span></div><div class="sub">completed evaluations</div></div>
        <div class="kpi ${avgCls}"><h4>Average score</h4><div class="val">${scored?avgScore:'—'}</div><div class="sub">${fmt(scored)} scored calls</div></div>
        <div class="kpi ${urgCls}"><h4>High urgency</h4><div class="val">${fmt(highUrg)} <span class="badge ${highUrg?'bad':'good'}">${pct(highUrg, analysed||total)}</span></div><div class="sub">${fmt(medUrg)} medium • ${fmt(lowUrg)} low</div></div>
        <div class="kpi ${negCls}"><h4>Negative sentiment</h4><div class="val">${fmt(neg)} <span class="badge ${neg?'bad':'good'}">${pct(neg, analysed||total)}</span></div><div class="sub">${fmt(neu)} neutral • ${fmt(pos)} positive</div></div>
        <div class="kpi ${lowScore?'bad':'good'}"><h4>Scores &lt; 60</h4><div class="val">${fmt(lowScore)} <span class="badge ${lowScore?'bad':'good'}">${pct(lowScore, scored||total)}</span></div><div class="sub">${fmt(midScore)} between 60–85 • ${fmt(highScore)} ≥85</div></div>
      </div>
      <div class="row">
        
        <div class="card">
          <h4>Top agents by negative sentiment</h4>
          <ul class="list">
            ${topAgents.length ? topAgents.map(([name, count]) => `<li><span>${escapeHtml(name)}</span><strong>${fmt(count)}</strong></li>`).join('') : `<li><span>No negatives yet</span><strong>0</strong></li>`}
          </ul>
        </div>
      </div>`;
    const box=document.getElementById('analysisDashboard');
    box.style.display='block'; box.innerHTML=html;
  }

    // ====== Filtered Analysis (View B) ======
    function loadCallIdOptions(){
      if(!el.fCallId) return;
      fetch(`${apiBase}?action=list_call_ids`).then(r=>r.json()).then(j=>{
        if(!j.ok) throw new Error(j.error||'Failed to load call_ids');
        const ids = j.call_ids || [];
        el.fCallId.innerHTML = `<option value="">— Any —</option><option value="__ALL__">All results</option>` +
          ids.filter(cid=>{ const u=(cid||'').toUpperCase(); return ['GTCS','GTPY','GTRF','GTET'].some(x=>u.includes(x)); })
             .map(cid=>`<option value="${escapeHtml(cid)}">${escapeHtml(cid)}</option>`).join('');
      }).catch(console.error);
    }

    if(el.btnApply) el.btnApply.addEventListener('click', ()=>{
      const qs = new URLSearchParams();
      const vCall=el.fCallId ? el.fCallId.value : '', vScore=el.fScore ? el.fScore.value : '', vUrg=el.fUrg ? el.fUrg.value : '';
      if(vCall==='__ALL__') qs.set('all','1'); else if(vCall!=='') qs.set('call_id', vCall);
      if(vScore!=='') qs.set('score_band', vScore);
      if(vUrg!=='') qs.set('urgency', vUrg);

      if([...qs.keys()].length===0){ if(el.statusB) el.statusB.textContent='Please set at least one filter.'; if(el.analysisB) el.analysisB.innerHTML=''; return; }

      if(el.statusB) el.statusB.textContent='Loading…'; if(el.analysisB) el.analysisB.innerHTML='';
      fetch(`${apiBase}?action=filter_analysis&${qs.toString()}`).then(r=>r.json()).then(j=>{
        if(!j.ok) throw new Error(j.error||'Filter failed');
        const list=(j.evaluations||[]).filter(item=>{ const u=(item.call_id||'').toUpperCase(); return ['GTCS','GTPY','GTRF','GTET'].some(x=>u.includes(x)); });
        if(el.statusB) el.statusB.textContent = `${list.length} result(s)`;
        if(el.analysisB) el.analysisB.innerHTML = list.map(item=>{
          const card = cardFor(item);
          const scHtml = item.scorecard ? renderScorecard(item.scorecard, parseRemarks(item.remark||null), item.id || item.analysis_id) : '<div class="muted">No scorecard.</div>';
          const tx = item.transcript ? `<pre class="mono" style="margin-top:8px">${escapeHtml(item.transcript)}</pre>` : '<div class="muted">No transcript.</div>';
          return `${card}${scHtml}${tx}`;
        }).join('');
      }).catch(err=>{ console.error(err); if(el.statusB) el.statusB.textContent='Error loading results.'; });
    });

    // ====== Tabs ======
    function activate(tab){
      if(tab==='A'){ el.tabA.classList.add('active'); el.tabB.classList.remove('active'); el.viewA.style.display=''; el.viewB.style.display='none'; document.getElementById('filtersA').style.display=''; }
      else { el.tabB.classList.add('active'); el.tabA.classList.remove('active'); el.viewB.style.display=''; el.viewA.style.display='none'; document.getElementById('filtersA').style.display='none'; if(el.fCallId && el.fCallId.options.length<=1) loadCallIdOptions(); }
    }
    if(el.tabA) el.tabA.onclick = ()=>activate('A');
    if(el.tabB) el.tabB.onclick = ()=>activate('B');

    // ====== Events ======
    if(el.btnRefresh) el.btnRefresh.addEventListener('click', loadRecordings);
    if(el.faApply) el.faApply.addEventListener('click', applyFilters);
    if(el.faClear) el.faClear.addEventListener('click', ()=>{
      if(el.faCampaign) el.faCampaign.value=''; 
      if(el.faDateFrom) el.faDateFrom.value=''; 
      if(el.faDateTo) el.faDateTo.value=''; 
      if(el.faAgent) el.faAgent.value='';
      if(el.faScore) el.faScore.value=''; 
      if(el.faStatus) el.faStatus.value='';
      // Snap back to yesterday and rebuild agent list for that day
      forceYesterday();
      updateAgentOptions();
      viewRows = allRows.slice(); 
      if(el.faMeta) el.faMeta.textContent='No filters applied'; 
      currentPage=1; 
      applyFilters();
    });

    // Update Agent list when dates change; also re-apply filters
    if(el.faDateFrom) el.faDateFrom.addEventListener('change', ()=>{ updateAgentOptions(); applyFilters(); });
    if(el.faDateTo) el.faDateTo.addEventListener('change',   ()=>{ updateAgentOptions(); applyFilters(); });
    if(el.faAgent) el.faAgent.addEventListener('change', applyFilters);

    // ====== Boot ======
    activate('A');
    loadRecordings();
  } // end of initApp

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
  } else {
    // DOM already loaded
    initApp();
  }
})();
</script>

<?php get_footer(); ?>