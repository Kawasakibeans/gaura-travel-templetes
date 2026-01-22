<?php
/**
 * Template Name: ABDN Call Status Dashboard
 * Template Post Type: post, page
 */

global $wpdb;
$table = "wpk4_agent_after_sale_abdn_call_status_log";

// Helper function to fetch after-sale ABDN call status logs from API
function fetchAfterSaleAbdnCallStatusLogs($start_date = null, $end_date = null) {
    $apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates-3/database_api/public';
    $apiEndpoint = '/v1/after-sale-abdn-call-status-logs';
    $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint;
    
    try {
        // Build query parameters for GET request
        $queryParams = [];
        if ($start_date) {
            $queryParams['start_date'] = $start_date;
        }
        if ($end_date) {
            $queryParams['end_date'] = $end_date;
        }
        
        // Add query string to URL if we have parameters
        if (!empty($queryParams)) {
            $apiUrl .= '?' . http_build_query($queryParams);
        }
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Use GET request
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("After Sale ABDN Call Status Logs API Error: " . $curlError);
            error_log("API URL: " . $apiUrl);
            return [['_debug_error' => $curlError, '_debug_url' => $apiUrl]];
        }
        
        if ($httpCode !== 200) {
            error_log("After Sale ABDN Call Status Logs API HTTP Error: Status code " . $httpCode);
            error_log("API URL: " . $apiUrl);
            error_log("Response: " . substr($response, 0, 1000));
            // Return as string for better debugging
            $responseText = is_string($response) ? substr($response, 0, 1000) : json_encode($response);
            return [['_debug_http_code' => (string)$httpCode, '_debug_response' => $responseText, '_debug_url' => $apiUrl]];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("After Sale ABDN Call Status Logs API JSON Error: " . json_last_error_msg());
            error_log("Raw response: " . substr($response, 0, 500));
            return [['_debug_json_error' => json_last_error_msg(), '_debug_raw' => substr($response, 0, 500)]];
        }
        
        // Debug: Log the response structure
        error_log("API Response keys: " . json_encode(array_keys($data ?? [])));
        if (is_array($data) && count($data) > 0 && !isset($data[0])) {
            // It's an associative array, log its keys
            error_log("API Response is associative array with keys: " . json_encode(array_keys($data)));
        } elseif (is_array($data) && count($data) > 0) {
            error_log("API Response sample (first item keys): " . json_encode(array_keys($data[0] ?? [])));
        }
        
        // Handle different response formats
        // Check for standard API response format: {status: 'success', data: [...]}
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']) && is_array($data['data'])) {
            error_log("Found data in status->data format, count: " . count($data['data']));
            return $data['data'];
        }
        // Check for data key
        if (isset($data['data']) && is_array($data['data'])) {
            error_log("Found data in 'data' key, count: " . count($data['data']));
            return $data['data'];
        }
        // Check for logs key
        if (isset($data['logs']) && is_array($data['logs'])) {
            error_log("Found data in 'logs' key, count: " . count($data['logs']));
            return $data['logs'];
        }
        // Check for results key
        if (isset($data['results']) && is_array($data['results'])) {
            error_log("Found data in 'results' key, count: " . count($data['results']));
            return $data['results'];
        }
        // If response is already an array, return it
        if (is_array($data)) {
            error_log("Response is direct array, count: " . count($data));
            return $data;
        }
        
        error_log("No data found in response. Full response structure: " . json_encode($data));
        // Return debug info if no data found
        return [['_debug_no_data' => true, '_debug_response_keys' => array_keys($data ?? []), '_debug_response_sample' => is_array($data) ? array_slice($data, 0, 1) : $data]];
    } catch (Exception $e) {
        error_log("After Sale ABDN Call Status Logs API Exception: " . $e->getMessage());
        return [['_debug_exception' => $e->getMessage()]];
    }
}

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
        header('Content-Type: application/json');
        
        // Convert empty strings to null (API will use today's date as default)
        $start_date = isset($_POST['start_date']) && trim($_POST['start_date']) !== '' 
            ? sanitize_text_field($_POST['start_date']) 
            : null;
        $end_date = isset($_POST['end_date']) && trim($_POST['end_date']) !== '' 
            ? sanitize_text_field($_POST['end_date']) 
            : null;
        
        // Fetch rows from API instead of SQL query
        $rows = fetchAfterSaleAbdnCallStatusLogs($start_date, $end_date);
        
        // Debug: Log the response
        error_log("Fetch rows - start_date: " . ($start_date ?? 'null') . ", end_date: " . ($end_date ?? 'null') . ", rows count: " . count($rows));
        
        // Convert array of arrays to array of objects for compatibility with DataTables
        $rows = array_map(function($row) {
            return (object)$row;
        }, $rows);
        
        // Use wp_send_json if available, otherwise use json_encode
        if (function_exists('wp_send_json')) {
            wp_send_json($rows);
        } else {
            echo json_encode($rows);
            exit;
        }
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
            <button type="button" id="apply-date-filter">Apply</button>
            <button type="button" id="reset-date-filter">Reset</button>
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

    // Store filter dates in variables
    var filterStartDate = '';
    var filterEndDate = '';

    var table = $('#abdn-table').DataTable({
        processing: true,
        serverSide: false,
        ajax:{
            url: window.location.href,
            type: 'POST',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            data: function(d) {
                var requestData = {
                    action: 'fetch_rows',
                    start_date: filterStartDate || '',
                    end_date: filterEndDate || ''
                };
                console.log('Sending AJAX request with data:', requestData);
                return requestData;
            },
            dataSrc: function(json) {
                console.log('Raw response received:', json);
                
                // Check for debug information
                if (json && json.length > 0 && json[0] && json[0]._debug_error) {
                    console.error('API Error:', json[0]._debug_error);
                    console.error('API URL:', json[0]._debug_url);
                    alert('API Error: ' + json[0]._debug_error + '\nURL: ' + json[0]._debug_url);
                    return [];
                }
                if (json && json.length > 0 && json[0] && json[0]._debug_http_code) {
                    console.error('API HTTP Error:', json[0]._debug_http_code);
                    console.error('API URL:', json[0]._debug_url);
                    console.error('API Response:', json[0]._debug_response);
                    // Show full error details
                    var errorMsg = 'API HTTP Error: ' + json[0]._debug_http_code + 
                                   '\n\nURL: ' + json[0]._debug_url + 
                                   '\n\nResponse: ' + (typeof json[0]._debug_response === 'object' ? JSON.stringify(json[0]._debug_response) : json[0]._debug_response);
                    alert(errorMsg);
                    return [];
                }
                if (json && json.length > 0 && json[0] && json[0]._debug_no_data) {
                    console.warn('No data found in API response');
                    console.warn('Response keys:', json[0]._debug_response_keys);
                    console.warn('Response sample:', json[0]._debug_response_sample);
                    return [];
                }
                
                // Filter out debug keys from data
                if (Array.isArray(json)) {
                    return json.filter(item => !item._debug_error && !item._debug_http_code && !item._debug_no_data && !item._debug_json_error && !item._debug_exception);
                }
                
                // If response is already an array, return it
                if (Array.isArray(json)) {
                    return json;
                }
                // If response has a data property, return that
                if (json && json.data && Array.isArray(json.data)) {
                    return json.data;
                }
                // Otherwise return empty array
                console.warn('Unexpected response format:', json);
                return [];
            },
            error: function(xhr, error, thrown) {
                if (error !== 'abort') {
                    console.error('DataTable AJAX Error:', error, thrown);
                    console.error('Status:', xhr.status);
                    console.error('Status Text:', xhr.statusText);
                    console.error('Response:', xhr.responseText);
                    console.error('Response Headers:', xhr.getAllResponseHeaders());
                    alert('Failed to load data. Please check console for details.');
                }
            }
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
    $('#apply-date-filter').on('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        let start = $('#filter-start').val();
        let end = $('#filter-end').val();
        
        console.log('Apply clicked - Start:', start, 'End:', end);
        
        // Validate dates
        if (start && end && new Date(start) > new Date(end)) {
            alert('Start date cannot be after end date.');
            return false;
        }
        
        // Update filter variables
        filterStartDate = start || '';
        filterEndDate = end || '';
        
        // Reload DataTable with new date parameters
        table.ajax.reload(null, false);
        
        return false;
    });

    $('#reset-date-filter').on('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        $('#filter-start,#filter-end').val('');
        
        // Reset filter variables
        filterStartDate = '';
        filterEndDate = '';
        
        table.ajax.reload(null, false);
        
        return false;
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
