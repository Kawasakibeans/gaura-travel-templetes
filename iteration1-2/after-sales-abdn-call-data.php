<?php
/**
 * Template Name: ABDN Call Status Dashboard
 * Template Post Type: post, page
 */

global $wpdb;
$table = "wpk4_agent_after_sale_abdn_call_status_log";

// ------------------ AJAX HANDLERS ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Update row
    if ($_POST['action'] === 'update_row') {
        $id = intval($_POST['id']);
        $reason_category = sanitize_text_field($_POST['reason_category']);
        $agents_on_break = sanitize_text_field($_POST['agents_on_break']);
        $agents_on_calls = sanitize_text_field($_POST['agents_on_calls']);
        $remarks = sanitize_textarea_field($_POST['remarks']);

        $updated = $wpdb->update(
            $table,
            [
                'reason_category' => $reason_category,
                'agents_on_break' => $agents_on_break,
                'agents_on_calls' => $agents_on_calls,
                'remarks' => $remarks
            ],
            ['id' => $id],
            ['%s','%s','%s','%s'],
            ['%d']
        );
        wp_send_json(['success'=>$updated!==false]);
        exit;
    }

    // Fetch rows for DataTable
    if ($_POST['action'] === 'fetch_rows') {
        $today = date('Y-m-d');
        $start_date = sanitize_text_field($_POST['start_date'] ?? $today);
        $end_date = sanitize_text_field($_POST['end_date'] ?? $today);
        $where = [];
        if ($start_date) $where[] = "date >= '$start_date'";
        if ($end_date) $where[] = "date <= '$end_date'";
        $where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $rows = $wpdb->get_results("SELECT * FROM $table $where_sql ORDER BY date DESC");
        wp_send_json($rows);
        exit;
    }
}

// ------------------ HELPER FUNCTION ------------------
function abdn_format_seconds_hms($raw) {
    if ($raw === null) return '';
    $s = 0;
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', trim($raw))) return $raw;
    if (preg_match('/(\d+)/', (string)$raw, $m)) $s = (int)$m[1];
    $h = intdiv($s,3600);
    $m = intdiv($s%3600,60);
    $sec = $s%60;
    return sprintf('%02d:%02d:%02d', $h,$m,$sec);
}

// ------------------ PAGE HTML ------------------
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
    :root{
        --primary-color:#0056b3;
        --secondary-color:#ffbb00;
        --hover-color:#e6a800;
        --success-color:#28a745;
        --light-bg:#f8f9fa;
        --row-even:#fffef5;
        --row-odd:#fff6d6;
        --border-color:#dee2e6;
    }
    body{font-family:'Segoe UI',Roboto,Arial,sans-serif;background:#f5f5f5;padding:20px;color:#333;}
    .abdn-container{max-width:95%;margin:0 auto;background:white;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.1);overflow:hidden;}
    .abdn-header{background:linear-gradient(135deg,var(--primary-color),#003366);padding:20px;text-align:center;color:white;margin-bottom:0;}
    .abdn-header h1{margin:0;font-size:28px;display:flex;align-items:center;justify-content:center;gap:10px;}
    .abdn-controls{padding:15px;background:var(--light-bg);display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);}
    .abdn-search{padding:8px 15px;border:1px solid var(--border-color);border-radius:4px;width:300px;}
    .abdn-date-filter{display:flex;align-items:center;gap:10px;}
    .abdn-date-filter input[type=date]{padding:6px 8px;border:1px solid var(--border-color);border-radius:4px;}
    .abdn-date-filter button,#export-csv{background:var(--secondary-color);border:none;padding:6px 12px;border-radius:4px;font-weight:600;cursor:pointer;transition:all .2s;}
    #export-csv{background:#28a745;color:#fff;}
    .abdn-date-filter button:hover,#export-csv:hover{background:var(--hover-color);}
    .abdn-table-container{overflow-x:auto;padding:0 15px 15px;}
    .abdn-input{width:100%;padding:6px 8px;border:1px solid var(--border-color);border-radius:4px;font-size:14px;}
    .abdn-save{background-color:var(--secondary-color);border:none;padding:6px 12px;border-radius:4px;font-weight:600;cursor:pointer;transition:all 0.2s;}
    .abdn-save.success{background-color:var(--success-color)!important;color:white;}
</style>

<div class="abdn-container">
    <div class="abdn-header">
        <h1>ABDN Call Data Analytics Dashboard</h1>
    </div>

    <div class="abdn-controls">
        <div class="abdn-date-filter">
            <label>From: <input type="date" id="filter-start"></label>
            <label>To: <input type="date" id="filter-end"></label>
            <button id="apply-date-filter">Apply</button>
            <button id="reset-date-filter">Reset</button>
        </div>
        <div>
            <input type="text" class="abdn-search" placeholder="Search records...">
        </div>
        <button id="export-csv">Export CSV</button>
    </div>

    <div class="abdn-table-container">
        <table id="abdn-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Campaign</th>
                    <th>IST</th>
                    <th>AEST</th>
                    <th>Call Hold</th>
                    <th>Reason Category</th>
                    <th>Agents on Break</th>
                    <th>Agents on Calls</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($){
    function formatHMS(sec){
        sec = parseInt(sec)||0;
        let h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60;
        return `${('0'+h).slice(-2)}:${('0'+m).slice(-2)}:${('0'+s).slice(-2)}`;
    }

    function editableCell(data){ return `<input type="text" class="abdn-input" value="${data}">`; }
    function saveButton(id){ return `<button class="abdn-save" data-id="${id}">Save</button>`; }
    function emptyIfNull(val){
        return val === null ? '' : val;
    }

    var table = $('#abdn-table').DataTable({
        ajax:{
            url: '',
            type: 'POST',
            data: {action:'fetch_rows'},
            dataSrc:''
        },
        columns:[
            {data:'date'},
            {data:'day'},
            {data:'campaign'},
            {data:'ist'},
            {data:'aest'},
            {data:'call_hold'},
            {data:'reason_category', render: function(val){ return editableCell(emptyIfNull(val)); }},
            {data:'agents_on_break', render: function(val){ return editableCell(emptyIfNull(val)); }},
            {data:'agents_on_calls', render: function(val){ return editableCell(emptyIfNull(val)); }},
            {data:'remarks', render: function(val){ return editableCell(emptyIfNull(val)); }},
            {data:'id', render: saveButton}
        ],
        responsive:true
    });

    // Inline Save
    $('#abdn-table').on('click','.abdn-save',function(){
        let btn=$(this);
        let row=btn.closest('tr');
        let id=btn.data('id');
        let inputs=row.find('input');
        $.post('',{
            action:'update_row',
            id:id,
            reason_category:inputs.eq(0).val(),
            agents_on_break:inputs.eq(1).val(),
            agents_on_calls:inputs.eq(2).val(),
            remarks:inputs.eq(3).val()
        },function(res){
            if(res.success){
                btn.text('Saved').addClass('success');
                setTimeout(()=>btn.text('Save').removeClass('success'),2000);
            }else{ alert('Update failed'); }
        });
    });

    // Search
    $('.abdn-search').on('input',function(){
        table.search(this.value).draw();
    });

    // Date filter
    $('#apply-date-filter').on('click', function(){
        let start = $('#filter-start').val();
        let end = $('#filter-end').val();
    
        table.ajax.url('').load(null, false); // reload DataTable with updated params
    
        // Override ajax.data to include start/end dates
        table.settings()[0].ajax.data = {
            action: 'fetch_rows',
            start_date: start,
            end_date: end
        };
    
        table.ajax.reload();
    });

    $('#reset-date-filter').on('click', function(){
        $('#filter-start,#filter-end').val('');
        table.settings()[0].ajax.data = { action: 'fetch_rows' };
        table.ajax.reload();
    });


    // Export CSV
    $('#export-csv').on('click',function(){
        let rows=[];
        let headers=[];
        $('#abdn-table thead th').each(function(){headers.push('"'+$(this).text()+'"');});
        rows.push(headers.join(','));
        table.rows({search:'applied'}).every(function(){
            let row=$(this.node());
            let line=[];
            row.find('td').each(function(){
                let val=$(this).find('input').length?$(this).find('input').val():$(this).text();
                line.push('"'+val.replace(/"/g,'""')+'"');
            });
            rows.push(line.join(','));
        });
        let blob=new Blob([rows.join("\n")],{type:'text/csv;charset=utf-8;'});
        let url=URL.createObjectURL(blob);
        let a=document.createElement('a'); a.href=url; a.download='abdn_export.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
});
</script>
