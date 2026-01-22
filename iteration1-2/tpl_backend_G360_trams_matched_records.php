<?php
session_start();

// Define API base URL if not already defined
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api_test_pamitha/public/v1');
}

// ============================================================================
// OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
// ============================================================================
// // Database connection
// $servername = "localhost";
// $username = "gaurat_sriharan";
// $password = "r)?2lc^Q0cAE";
// $dbname = "gaurat_gauratravel";
// 
// try {
//     $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch(PDOException $e) {
//     die("Connection failed: " . $e->getMessage());
// }
// ============================================================================

function parseDate($excelDate) {
    if (empty($excelDate)) return null;

    $excelDate = trim($excelDate); // Trim spaces

    // âœ… If already in YYYY-MM-DD format, return as is
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $excelDate)) {
        return $excelDate;
    }

    // âœ… Handle Excel numeric dates (like 45123)
    if (is_numeric($excelDate) && $excelDate > 25569) {
        // Excel's epoch starts from 1900-01-01
        $unixDate = ($excelDate - 25569) * 86400;
        return gmdate('Y/m/d', $unixDate);
    }

    $excelDate = strtoupper($excelDate); // Normalize case

    // âœ… Handle airline date format (e.g., 26JUN23)
    if (preg_match('/^\d{1,2}[A-Z]{3}\d{2}$/', $excelDate)) {
        $date = DateTime::createFromFormat('dMy', $excelDate);
        return $date ? $date->format('Y/m/d') : null;
    }

    // âœ… Try multiple common formats
    $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'dMY'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $excelDate);
        if ($date) {
            return $date->format('Y/m/d');
        }
    }

    // ðŸš¨ If nothing matches, log and return null
    error_log("Unparsed date: $excelDate");
    return null;
}

function extractNumeric($value) {
    if (empty($value)) return 0;
    // Remove all non-numeric characters except decimal point and minus
    $cleaned = preg_replace('/[^0-9.-]/', '', str_replace(',', '', $value));
    // Extract first numeric value (handles cases like "23.80 D5")
    preg_match('/-?\d+\.?\d*/', $cleaned, $matches);
    return isset($matches[0]) ? floatval($matches[0]) : 0;
}

function updateDatabaseRecord($data) {
    // ============================================================================
    // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
    // ============================================================================
    // $sqlUpdate = "UPDATE wpk4_backend_trams_invoice SET 
    //         client_linkno = ?,
    //         issuedate = ?,
    //         branch_linkno = ?,
    //         recordlocator = ?,
    //         paystatus_linkcode = ?,
    //         invoicetype_linkcode = ?,
    //         partpayamt = ?,
    //         invoicegroup = ?,
    //         firstinsideagentbkg_linkno = ?,
    //         firstoutsideagentbkg_linkno = ?,
    //         calcinvoicenumber = ?,
    //         altinvoicenumber = ?,
    //         arc_linkno = ?,
    //         pnrcreationdate = ?,
    //         receivedby = ?,
    //         facturano = ?,
    //         serviciono = ?,
    //         itininvremarks = ?,
    //         homehost_linkno = ?,
    //         syncmoddatetime = NOW(),
    //         marketid = ?,
    //         agency_linkno = ?,
    //         accountingremarks = ?,
    //         remarks = ?
    //     WHERE invoicenumber = ?";
    // 
    // $stmtUpdate = $pdo->prepare($sqlUpdate);
    // $mainTableSuccess = $stmtUpdate->execute([
    //     $data['Client Link No'] ?? null,
    //     isset($data['Issue Date']) ? parseDate($data['Issue Date']) : null,
    //     $data['Branch Link No'] ?? null,
    //     $data['Record Locator'] ?? null,
    //     $data['Pay Status'] ?? null,
    //     $data['Invoice Type'] ?? null,
    //     $data['Partial Payment Amount'] ?? 0,
    //     $data['Invoice Group'] ?? null,
    //     $data['First Inside Agent'] ?? null,
    //     $data['First Outside Agent'] ?? null,
    //     $data['Calculated Invoice Number'] ?? null,
    //     $data['Alternate Invoice Number'] ?? null,
    //     $data['ARC Link No'] ?? null,
    //     isset($data['PNR Creation Date']) ? parseDate($data['PNR Creation Date']) : null,
    //     $data['Received By'] ?? null,
    //     $data['Factura No'] ?? null,
    //     $data['Servicio No'] ?? null,
    //     $data['Itinerary Remarks'] ?? null,
    //     $data['Home Host Link No'] ?? null,
    //     $data['Market ID'] ?? null,
    //     $data['Agency Link No'] ?? null,
    //     $data['Accounting Remarks'] ?? null,
    //     $data['Remarks'] ?? null,
    //     $data['Invoice #']
    // ]);
    // 
    // if (!$mainTableSuccess) {
    //     $errorInfo = $stmtUpdate->errorInfo();
    //     error_log('SQL Update Error: ' . print_r($errorInfo, true));
    //     return 'SQL Error: ' . $errorInfo[2];
    // }
    // 
    // if ($stmtUpdate->rowCount() == 0) {
    //     return 'No record updated for Invoice: ' . ($data['Invoice #'] ?? '');
    // }
    // 
    // return true;
    // ============================================================================
    
    // Update invoice via API
    $invoiceNumber = $data['Invoice #'] ?? '';
    if (empty($invoiceNumber)) {
        return 'Invoice number is required';
    }
    
    $url = API_BASE_URL . '/trams-invoice/invoice/' . urlencode($invoiceNumber);
    
    $updateData = [
        'client_linkno' => $data['Client Link No'] ?? null,
        'issuedate' => isset($data['Issue Date']) ? parseDate($data['Issue Date']) : null,
        'branch_linkno' => $data['Branch Link No'] ?? null,
        'recordlocator' => $data['Record Locator'] ?? null,
        'paystatus_linkcode' => $data['Pay Status'] ?? null,
        'invoicetype_linkcode' => $data['Invoice Type'] ?? null,
        'partpayamt' => $data['Partial Payment Amount'] ?? 0,
        'invoicegroup' => $data['Invoice Group'] ?? null,
        'firstinsideagentbkg_linkno' => $data['First Inside Agent'] ?? null,
        'firstoutsideagentbkg_linkno' => $data['First Outside Agent'] ?? null,
        'calcinvoicenumber' => $data['Calculated Invoice Number'] ?? null,
        'altinvoicenumber' => $data['Alternate Invoice Number'] ?? null,
        'arc_linkno' => $data['ARC Link No'] ?? null,
        'pnrcreationdate' => isset($data['PNR Creation Date']) ? parseDate($data['PNR Creation Date']) : null,
        'receivedby' => $data['Received By'] ?? null,
        'facturano' => $data['Factura No'] ?? null,
        'serviciono' => $data['Servicio No'] ?? null,
        'itininvremarks' => $data['Itinerary Remarks'] ?? null,
        'homehost_linkno' => $data['Home Host Link No'] ?? null,
        'marketid' => $data['Market ID'] ?? null,
        'agency_linkno' => $data['Agency Link No'] ?? null,
        'accountingremarks' => $data['Accounting Remarks'] ?? null,
        'remarks' => $data['Remarks'] ?? null,
    ];
    
    $postData = json_encode($updateData);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            return true;
        } else {
            $errorMsg = $responseData['message'] ?? 'Unknown error';
            error_log('API Update Error: ' . $errorMsg);
            return 'API Error: ' . $errorMsg;
        }
    } else {
        $errorMsg = $responseData['message'] ?? 'HTTP Error: ' . $httpCode;
        error_log('API Update Error: ' . $errorMsg);
        return 'API Error: ' . $errorMsg;
    }
}

// Process CSV upload
$processedData = [];
$stats = ['total' => 0, 'matched' => 0, 'unmatched' => 0, 'total_in_file' => 0];
$selectedSheet = '';
$maxRecords = 4000; // Maximum allowed records
$error = '';
$warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $selectedSheet = $_POST['sheet_name'];
    $file = $_FILES['csv_file']['tmp_name'];
    $invoiceNumbers = [];
    $csvRows = [];
    $rowCount = 0;

    if (($handle = fopen($file, "r")) !== FALSE) {
        // First count the total records
        $totalRecords = 0;
        while (fgetcsv($handle)) $totalRecords++;
        rewind($handle);
        
        $stats['total_in_file'] = $totalRecords - 1; // Subtract 1 for header row
        
        if ($totalRecords - 1 > $maxRecords) {
            $warning = "Note: File contains {$stats['total_in_file']} records. Showing first {$maxRecords} records only.";
        }
        
        $rawHeaders = fgetcsv($handle);
        // Clean headers
        $headers = array_map(function($h) {
            return trim(preg_replace('/\s+/', ' ', str_replace('"', '', $h)));
        }, $rawHeaders);
        
        // Define column mappings for each sheet type
        $columnMappings = [
            'Standard Invoice' => [
                'Invoice #' => 'Invoice #',
                'Issue Date' => 'Issue Date',
                'Invoice Type' => 'Type',
                'Branch Link No' => 'Branch',
                'Client Link No' => 'Client',
                'First Inside Agent' => 'Agent',
                'Pay Status' => 'Status',
                'Invoice Group' => 'Group',
                'Record Locator' => 'Rec Loc',
                'ARC Link No' => 'BSP #',
                'Remarks' => 'Remarks',
                'Agency Link No' => 'IC Code',
                'Calculated Invoice Number' => 'Invoice Rec #',
                'Traveler Name' => 'Traveler Name',
                'Partial Payment Amount' => 'Partial Pay/Voucher Balance',
                'Accounting Remarks' => 'Accounting Remarks',
                'Attachments' => 'Attachments',
                'Partial Payment Amount' => 'Partial Pay/Voucher Balance',
            ]
        ];

        // Only allow Standard Invoice
        $selectedSheet = 'Standard Invoice';
        $mapping = $columnMappings[$selectedSheet];
        $headerMap = [];
        
        // Create header map (case-insensitive)
        foreach ($headers as $index => $header) {
            $cleanHeader = strtolower(trim($header));
            foreach ($mapping as $dbField => $fileField) {
                if (strtolower(trim($fileField)) === $cleanHeader) {
                    $headerMap[$dbField] = $index;
                    break;
                }
            }
        }

        // Process data rows (up to maxRecords)
        while (($row = fgetcsv($handle)) !== FALSE && $rowCount < $maxRecords) {
            $rowCount++;
            $csvRows[] = $row;
            
            if (isset($headerMap['Invoice #'])) {
                $invNum = $row[$headerMap['Invoice #']] ?? '';
                if ($invNum !== '') {
                    $invoiceNumbers[] = $invNum;
                }
            }
        }
        fclose($handle);

        // Batch query for existing invoices
        $existingInvoices = [];
        if (!empty($invoiceNumbers)) {
            // ============================================================================
            // OLD DATABASE CODE - COMMENTED OUT (Can be reverted if API endpoints fail)
            // ============================================================================
            // $placeholders = implode(',', array_fill(0, count($invoiceNumbers), '?'));
            // $stmt = $pdo->prepare("SELECT invoicenumber FROM wpk4_backend_trams_invoice WHERE invoicenumber IN ($placeholders)");
            // $stmt->execute($invoiceNumbers);
            // $existingInvoices = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            // ============================================================================
            
            // Fetch existing invoices from API
            $url = API_BASE_URL . '/trams-invoice/check-invoices';
            $postData = json_encode(['invoice_numbers' => $invoiceNumbers]);
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $responseData = json_decode($response, true);
            
            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                if (isset($responseData['data']['invoice_numbers'])) {
                    $existingInvoices = $responseData['data']['invoice_numbers'];
                } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
                    // Extract invoice numbers from array of objects
                    $existingInvoices = array_column($responseData['data'], 'invoicenumber');
                }
            }
        }

        // Process data rows
        foreach ($csvRows as $row) {
            $invNum = isset($headerMap['Invoice #']) ? $row[$headerMap['Invoice #']] ?? '' : '';
            $exist = in_array($invNum, $existingInvoices) ? 'Yes' : 'No';

            $rowData = [
                'Invoice #' => $invNum,
                'Client Link No' => isset($headerMap['Client Link No']) ? $row[$headerMap['Client Link No']] ?? '' : '',
                'Issue Date' => isset($headerMap['Issue Date']) ? parseDate($row[$headerMap['Issue Date']] ?? '') : null,
                'Branch Link No' => isset($headerMap['Branch Link No']) ? $row[$headerMap['Branch Link No']] ?? '' : '',
                'Record Locator' => isset($headerMap['Record Locator']) ? $row[$headerMap['Record Locator']] ?? '' : '',
                'Pay Status' => isset($headerMap['Pay Status']) ? $row[$headerMap['Pay Status']] ?? '' : '',
                'Invoice Type' => isset($headerMap['Invoice Type']) ? $row[$headerMap['Invoice Type']] ?? '' : '',
                'Partial Payment Amount' => isset($headerMap['Partial Payment Amount']) ? extractNumeric($row[$headerMap['Partial Payment Amount']] ?? '') : 0,
                'Invoice Group' => isset($headerMap['Invoice Group']) ? $row[$headerMap['Invoice Group']] ?? '' : '',
                'First Inside Agent' => isset($headerMap['First Inside Agent']) ? $row[$headerMap['First Inside Agent']] ?? '' : '',
                'First Outside Agent' => isset($headerMap['First Outside Agent']) ? $row[$headerMap['First Outside Agent']] ?? '' : '',
                'Calculated Invoice Number' => isset($headerMap['Calculated Invoice Number']) ? $row[$headerMap['Calculated Invoice Number']] ?? '' : '',
                'Alternate Invoice Number' => isset($headerMap['Alternate Invoice Number']) ? $row[$headerMap['Alternate Invoice Number']] ?? '' : '',
                'ARC Link No' => isset($headerMap['ARC Link No']) ? $row[$headerMap['ARC Link No']] ?? '' : '',
                'PNR Creation Date' => isset($headerMap['PNR Creation Date']) ? parseDate($row[$headerMap['PNR Creation Date']] ?? '') : null,
                'Received By' => isset($headerMap['Received By']) ? $row[$headerMap['Received By']] ?? '' : '',
                'Factura No' => isset($headerMap['Factura No']) ? $row[$headerMap['Factura No']] ?? '' : '',
                'Servicio No' => isset($headerMap['Servicio No']) ? $row[$headerMap['Servicio No']] ?? '' : '',
                'Itinerary Remarks' => isset($headerMap['Itinerary Remarks']) ? $row[$headerMap['Itinerary Remarks']] ?? '' : '',
                'Home Host Link No' => isset($headerMap['Home Host Link No']) ? $row[$headerMap['Home Host Link No']] ?? '' : '',
                'Market ID' => isset($headerMap['Market ID']) ? $row[$headerMap['Market ID']] ?? '' : '',
                'Agency Link No' => isset($headerMap['Agency Link No']) ? $row[$headerMap['Agency Link No']] ?? '' : '',
                'Accounting Remarks' => isset($headerMap['Accounting Remarks']) ? $row[$headerMap['Accounting Remarks']] ?? '' : '',
                'Remarks' => isset($headerMap['Remarks']) ? $row[$headerMap['Remarks']] ?? '' : '',
                'Exist' => $exist,
                'Updated On' => date('Y/m/d H:i:s')
            ];

            if ($exist === 'Yes') {
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }
            $stats['total']++;

            $processedData[] = $rowData;
        }
        $_SESSION['processedData'] = $processedData;
        $_SESSION['stats'] = $stats;
        $_SESSION['warning'] = $warning;
    } else {
        $error = "Error processing CSV file";
    }
} elseif (isset($_SESSION['processedData'])) {
    $processedData = $_SESSION['processedData'];
    $stats = $_SESSION['stats'];
    $warning = $_SESSION['warning'] ?? '';
}

// Handle AJAX pagination request
if (isset($_POST['action']) && $_POST['action'] === 'get_page') {
    header('Content-Type: application/json');
    $page = intval($_POST['page']);
    $rowsPerPage = 30;
    $start = ($page - 1) * $rowsPerPage;
    $pagedData = array_slice($_SESSION['processedData'], $start, $rowsPerPage);

    ob_start();
    foreach ($pagedData as $row): ?>
        <tr class="<?= $row['Exist'] === 'Yes' ? 'table-matched' : 'table-unmatched' ?>">
            <td><?= htmlspecialchars($row['Invoice #']) ?></td>
            <td><?= htmlspecialchars($row['Client Link No']) ?></td>
            <td><?= htmlspecialchars($row['Issue Date'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Branch Link No']) ?></td>
            <td><?= htmlspecialchars($row['Record Locator']) ?></td>
            <td><?= htmlspecialchars($row['Pay Status']) ?></td>
            <td><?= htmlspecialchars($row['Invoice Type']) ?></td>
            <td><?= number_format($row['Partial Payment Amount'], 2) ?></td>
            <td><?= htmlspecialchars($row['Invoice Group']) ?></td>
            <td><?= htmlspecialchars($row['First Inside Agent']) ?></td>
            <td><?= htmlspecialchars($row['First Outside Agent']) ?></td>
            <td><?= htmlspecialchars($row['Calculated Invoice Number']) ?></td>
            <td><?= htmlspecialchars($row['Alternate Invoice Number']) ?></td>
            <td><?= htmlspecialchars($row['ARC Link No']) ?></td>
            <td><?= htmlspecialchars($row['PNR Creation Date'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Received By']) ?></td>
            <td><?= htmlspecialchars($row['Factura No']) ?></td>
            <td><?= htmlspecialchars($row['Servicio No']) ?></td>
            <td><?= htmlspecialchars($row['Itinerary Remarks']) ?></td>
            <td><?= htmlspecialchars($row['Home Host Link No']) ?></td>
            <td><?= htmlspecialchars($row['Market ID']) ?></td>
            <td><?= htmlspecialchars($row['Agency Link No']) ?></td>
            <td><?= htmlspecialchars($row['Accounting Remarks']) ?></td>
            <td><?= htmlspecialchars($row['Remarks']) ?></td>
            <td><?= htmlspecialchars($row['Updated On']) ?></td>
            <td>
                <?php if ($row['Exist'] === 'Yes'): ?>
                    <span class="badge bg-success">Yes <i class="bi bi-file-earmark-check"></i></span>
                <?php else: ?>
                    <span class="badge bg-danger">No</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
    $html = ob_get_clean();
    echo json_encode(['html' => $html]);
    exit;
}

// Handle AJAX update request
if (isset($_POST['action']) && $_POST['action'] === 'update_records') {
    header('Content-Type: application/json');
    $updated = 0;
    $errors = [];
    
    if (!isset($_POST['data'])) {
        echo json_encode(['success' => false, 'error' => 'No data provided']);
        exit;
    }
    
    foreach ($_POST['data'] as $item) {
        try {
            if (updateDatabaseRecord($item)) {
                $updated++;
            }
        } catch (Exception $e) {
            $errors[] = "Error updating invoice {$item['Invoice #']}: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => count($errors) === 0,
        'updated' => $updated,
        'errors' => $errors
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Reconciliation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #007bff;
            --secondary: #6c757d;
        }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            color: white;
        }
        .table-matched { background-color: #d4edda !important; }
        .table-unmatched { background-color: #f8d7da !important; }
        .file-icon { font-size: 1.5rem; }
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: none;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            z-index: 99;
        }
        .back-to-top:hover {
            background-color: #0069d9;
            color: white;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .pagination .page-link {
            color: var(--primary);
        }
        .progress-container {
            display: none;
            margin: 20px 0;
        }
        .progress-bar {
            transition: width 0.3s;
        }
        .loading-text {
            text-align: center;
            margin-top: 5px;
            font-weight: bold;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }
        .loading-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="mt-3">Processing your file...</h4>
            <p>Please wait while we process your CSV file</p>
        </div>
    </div>

    <div class="container-fluid py-4">
        <?php if (!empty($error)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-file-earmark-text file-icon"></i> Invoice Reconciliation System</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="sheet_name" value="Standard Invoice">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="csv_file" class="form-label">Upload CSV File</label>
                                    <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                    <small class="text-muted">Note: Only the first 4000 records will be processed</small>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                        <i class="bi bi-upload"></i> Process
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($processedData)): ?>
        <?php if (!empty($warning)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($warning) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="mb-0"><i class="bi bi-clipboard-data"></i> Upload Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total in File</h5>
                                        <p class="card-text display-4"><?= $stats['total_in_file'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Records Processed</h5>
                                        <p class="card-text display-4"><?= $stats['total'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Matched Records</h5>
                                        <p class="card-text display-4"><?= $stats['matched'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Unmatched Records</h5>
                                        <p class="card-text display-4"><?= $stats['unmatched'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted">Processed on: <?= date('Y/m/d H:i:s') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-table"></i> Reconciliation Results</h3>
                            <div>
                                <button id="updateBtn" class="btn btn-success me-2" <?= $stats['matched'] === 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-circle"></i> Update Matching Records
                                </button>
                                <button id="exportBtn" class="btn btn-light">
                                    <i class="bi bi-download"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="progress-container" id="progressContainer">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     role="progressbar" style="width: 0%" id="progressBar"></div>
                            </div>
                            <div class="loading-text" id="loadingText">Processing: 0%</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="resultsTable">
                                <thead class="sticky-header">
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Client</th>
                                        <th>Issue Date</th>
                                        <th>Branch</th>
                                        <th>PNR</th>
                                        <th>Pay Status</th>
                                        <th>Type</th>
                                        <th>Partial Amt</th>
                                        <th>Group</th>
                                        <th>Inside Agent</th>
                                        <th>Outside Agent</th>
                                        <th>Calc Inv#</th>
                                        <th>Alt Inv#</th>
                                        <th>ARC</th>
                                        <th>PNR Date</th>
                                        <th>Received By</th>
                                        <th>Factura</th>
                                        <th>Servicio</th>
                                        <th>Itin Remarks</th>
                                        <th>Home Host</th>
                                        <th>Market</th>
                                        <th>Agency</th>
                                        <th>Accounting Notes</th>
                                        <th>Notes</th>
                                        <th>Updated On</th>
                                        <th>Exist</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Show only first 20 rows initially
                                    $initialRows = array_slice($processedData, 0, 20);
                                    foreach ($initialRows as $row): ?>
                                    <tr class="<?= $row['Exist'] === 'Yes' ? 'table-matched' : 'table-unmatched' ?>">
                                        <td><?= htmlspecialchars($row['Invoice #']) ?></td>
                                        <td><?= htmlspecialchars($row['Client Link No']) ?></td>
                                        <td><?= htmlspecialchars($row['Issue Date'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['Branch Link No']) ?></td>
                                        <td><?= htmlspecialchars($row['Record Locator']) ?></td>
                                        <td><?= htmlspecialchars($row['Pay Status']) ?></td>
                                        <td><?= htmlspecialchars($row['Invoice Type']) ?></td>
                                        <td><?= number_format($row['Partial Payment Amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['Invoice Group']) ?></td>
                                        <td><?= htmlspecialchars($row['First Inside Agent']) ?></td>
                                        <td><?= htmlspecialchars($row['First Outside Agent']) ?></td>
                                        <td><?= htmlspecialchars($row['Calculated Invoice Number']) ?></td>
                                        <td><?= htmlspecialchars($row['Alternate Invoice Number']) ?></td>
                                        <td><?= htmlspecialchars($row['ARC Link No']) ?></td>
                                        <td><?= htmlspecialchars($row['PNR Creation Date'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['Received By']) ?></td>
                                        <td><?= htmlspecialchars($row['Factura No']) ?></td>
                                        <td><?= htmlspecialchars($row['Servicio No']) ?></td>
                                        <td><?= htmlspecialchars($row['Itinerary Remarks']) ?></td>
                                        <td><?= htmlspecialchars($row['Home Host Link No']) ?></td>
                                        <td><?= htmlspecialchars($row['Market ID']) ?></td>
                                        <td><?= htmlspecialchars($row['Agency Link No']) ?></td>
                                        <td><?= htmlspecialchars($row['Accounting Remarks']) ?></td>
                                        <td><?= htmlspecialchars($row['Remarks']) ?></td>
                                        <td><?= htmlspecialchars($row['Updated On']) ?></td>
                                        <td>
                                            <?php if ($row['Exist'] === 'Yes'): ?>
                                                <span class="badge bg-success">Yes <i class="bi bi-file-earmark-check"></i></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <nav>
                          <ul class="pagination justify-content-center" id="pagination">
                            <!-- Pagination buttons will be generated by JS -->
                          </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <button class="back-to-top" id="backToTop" title="Go to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                <!-- Toast message will appear here -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show loading overlay on form submit
            $('#uploadForm').on('submit', function() {
                $('#loadingOverlay').show();
            });

            // Back to top button
            $(window).scroll(function() {
                if ($(this).scrollTop() > 100) {
                    $('#backToTop').fadeIn();
                } else {
                    $('#backToTop').fadeOut();
                }
            });

            $('#backToTop').click(function() {
                $('html, body').animate({scrollTop: 0}, 'smooth');
                return false;
            });

            // Pagination
            let currentPage = 1;
            const rowsPerPage = 20;
            const totalRows = <?= isset($processedData) ? count($processedData) : 0 ?>;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            function renderPagination() {
                let html = '';
                const maxVisiblePages = 5;
                let startPage, endPage;
                
                if (totalPages <= maxVisiblePages) {
                    startPage = 1;
                    endPage = totalPages;
                } else {
                    const halfVisible = Math.floor(maxVisiblePages / 2);
                    if (currentPage <= halfVisible + 1) {
                        startPage = 1;
                        endPage = maxVisiblePages;
                    } else if (currentPage >= totalPages - halfVisible) {
                        startPage = totalPages - maxVisiblePages + 1;
                        endPage = totalPages;
                    } else {
                        startPage = currentPage - halfVisible;
                        endPage = currentPage + halfVisible;
                    }
                }
                
                // Previous button
                html += `<li class="page-item${currentPage === 1 ? ' disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>
                </li>`;
                
                // Page numbers
                for (let i = startPage; i <= endPage; i++) {
                    html += `<li class="page-item${i === currentPage ? ' active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>`;
                }
                
                // Next button
                html += `<li class="page-item${currentPage === totalPages ? ' disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>
                </li>`;
                
                $('#pagination').html(html);
            }
            
            $('#pagination').on('click', 'a', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (page !== currentPage && page >= 1 && page <= totalPages) {
                    currentPage = page;
                    loadPage(page);
                }
            });
            
            function loadPage(page) {
                $.post('', { action: 'get_page', page: page }, function(response) {
                    $('#resultsTable tbody').html(response.html);
                    renderPagination();
                    // Scroll to top of table
                    $('html, body').animate({
                        scrollTop: $('#resultsTable').offset().top - 20
                    }, 'fast');
                }, 'json').fail(function() {
                    alert('Error loading page');
                });
            }
            
            // Initialize pagination
            if (totalRows > 0) {
                renderPagination();
            }

            // Update records button
            $('#updateBtn').click(function() {
                if (confirm('Are you sure you want to update ALL matched records?')) {
                    let matchedData = <?= isset($processedData) ? json_encode(array_filter($processedData, fn($row) => $row['Exist'] === 'Yes')) : '[]' ?>;

                // Ensure matchedData is a proper array
                if (!Array.isArray(matchedData)) {
                    matchedData = Object.values(matchedData);
                }

                    if (!matchedData || matchedData.length === 0) {
                        alert('No matched records to update');
                        return;
                    }
                    
                    // Show progress
                    $('#progressContainer').show();
                    const progressBar = $('#progressBar');
                    const loadingText = $('#loadingText');
                    $(this).prop('disabled', true);
                    
                    // Track progress
                    let updatedCount = 0;
                    const totalRecords = matchedData.length;
                    const batchSize = 20; // Process 20 records at a time
                    
                    // Function to process batches
                    function processBatch(startIndex) {
                        const endIndex = Math.min(startIndex + batchSize, totalRecords);
                        const batch = matchedData.slice(startIndex, endIndex);
                        
                        $.ajax({
                            url: '',
                            type: 'POST',
                            data: {
                                action: 'update_records',
                                data: batch
                            },
                            success: function(response) {
                                if (response && response.success) {
                                    updatedCount += response.updated;
                                    const percentComplete = Math.round((updatedCount / totalRecords) * 100);
                                    
                                    // Update progress
                                    progressBar.css('width', percentComplete + '%');
                                    loadingText.text(`Updating: ${percentComplete}% (${updatedCount}/${totalRecords})`);
                                    
                                    // Process next batch if needed
                                    if (endIndex < totalRecords) {
                                        processBatch(endIndex);
                                    } else {
                                        // Complete
                                        showToast('success', `${updatedCount} records updated successfully!`);
                                        setTimeout(() => {
                                            location.reload(); // Refresh to show updated data
                                        }, 1500);
                                    }
                                } else {
                                    showToast('error', 'Error updating records: ' + (response.error || 'Unknown error'));
                                    $('#progressContainer').hide();
                                    $('#updateBtn').prop('disabled', false);
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast('error', 'AJAX Error: ' + error);
                                $('#progressContainer').hide();
                                $('#updateBtn').prop('disabled', false);
                            }
                        });
                    }
                    
                    // Start processing
                    processBatch(0);
                }
            });
            
            function showToast(type, message) {
                const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                const icon = type === 'success' ? 
                    '<i class="bi bi-check-circle text-success"></i>' : 
                    '<i class="bi bi-exclamation-triangle text-danger"></i>';
                
                $('#toastTitle').html(`${icon} ${type.charAt(0).toUpperCase() + type.slice(1)}`);
                $('#toastMessage').text(message);
                toast.show();
            }

            // Export to CSV
            $('#exportBtn').click(function() {
                let csv = [];
                const headers = [];
                
                // Add headers
                $('#resultsTable thead th').each(function() {
                    headers.push($(this).text().trim());
                });
                csv.push(headers.join(','));
                
                // Add all data rows (not just visible ones)
                <?php foreach ($processedData as $row): ?>
                    csv.push([
                        '<?= addslashes($row['Invoice #']) ?>',
                        '<?= addslashes($row['Client Link No']) ?>',
                        '<?= addslashes($row['Issue Date']) ?>',
                        '<?= addslashes($row['Branch Link No']) ?>',
                        '<?= addslashes($row['Record Locator']) ?>',
                        '<?= addslashes($row['Pay Status']) ?>',
                        '<?= addslashes($row['Invoice Type']) ?>',
                        '<?= $row['Partial Payment Amount'] ?>',
                        '<?= addslashes($row['Invoice Group']) ?>',
                        '<?= addslashes($row['First Inside Agent']) ?>',
                        '<?= addslashes($row['First Outside Agent']) ?>',
                        '<?= addslashes($row['Calculated Invoice Number']) ?>',
                        '<?= addslashes($row['Alternate Invoice Number']) ?>',
                        '<?= addslashes($row['ARC Link No']) ?>',
                        '<?= addslashes($row['PNR Creation Date']) ?>',
                        '<?= addslashes($row['Received By']) ?>',
                        '<?= addslashes($row['Factura No']) ?>',
                        '<?= addslashes($row['Servicio No']) ?>',
                        '<?= addslashes($row['Itinerary Remarks']) ?>',
                        '<?= addslashes($row['Home Host Link No']) ?>',
                        '<?= addslashes($row['Market ID']) ?>',
                        '<?= addslashes($row['Agency Link No']) ?>',
                        '<?= addslashes($row['Accounting Remarks']) ?>',
                        '<?= addslashes($row['Remarks']) ?>',
                        '<?= addslashes($row['Updated On']) ?>',
                        '<?= $row['Exist'] === 'Yes' ? 'Yes' : 'No' ?>'
                    ].map(field => `"${field}"`).join(','));
                <?php endforeach; ?>

                // Download CSV file
                downloadCSV(csv.join('\n'), 'invoice_reconciliation_<?= date('Y-m-d') ?>.csv');
            });

            function downloadCSV(csv, filename) {
                const csvFile = new Blob([csv], {type: 'text/csv'});
                const downloadLink = document.createElement('a');
                
                downloadLink.download = filename;
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = 'none';
                
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            }
        });
    </script>
</body>
</html>