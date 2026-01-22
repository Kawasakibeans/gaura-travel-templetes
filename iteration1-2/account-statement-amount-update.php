<?php
session_start();

// Database connection
$servername = "localhost";
$username = "gaurat_sriharan";
$password = "r)?2lc^Q0cAE";
$dbname = "gaurat_gauratravel";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function parseDate($excelDate) {
    if (empty($excelDate)) return null;

    $excelDate = trim($excelDate); // Trim spaces

    // ✅ If already in YYYY-MM-DD format, return as is
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $excelDate)) {
        return $excelDate;
    }

    // ✅ Handle Excel numeric dates (like 45123)
    if (is_numeric($excelDate) && $excelDate > 25569) {
        // Excel's epoch starts from 1900-01-01
        $unixDate = ($excelDate - 25569) * 86400;
        return gmdate('Y/m/d', $unixDate);
    }

    $excelDate = strtoupper($excelDate); // Normalize case

    // ✅ Handle airline date format (e.g., 26JUN23)
    if (preg_match('/^\d{1,2}[A-Z]{3}\d{2}$/', $excelDate)) {
        $date = DateTime::createFromFormat('dMy', $excelDate);
        return $date ? $date->format('Y/m/d') : null;
    }

    // ✅ Try multiple common formats
    $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'dMY'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $excelDate);
        if ($date) {
            return $date->format('Y/m/d');
        }
    }

    // 🚨 If nothing matches, log and return null
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

function updateDatabaseRecord($pdo, $data) {
    $sqlUpdate = "UPDATE wpk4_backend_ticket_reconciliation SET 
            transaction_amount = ?,
            fare = ?,
            vendor = ?, 
            a_l = ?, 
            tax = ?, 
            fee = ?, 
            comm = ?, 
            remark = ?, 
            tax_inr = ?, 
            comm_inr = ?, 
            transaction_amount_inr = ?,
            fare_inr = ?,
            added_on = NOW(),
            added_by = 'system',
            confirmed = 'Confirmed'
        WHERE document = ? AND document_type = ? AND vendor = ?";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $mainTableSuccess = $stmtUpdate->execute([
        $data['Transaction'] ?? 0,
        $data['Fare'] ?? 0,
        $data['Vendor'],
        $data['A_L'] ?? '',
        $data['Tax'] ?? 0,
        $data['Fee'] ?? 0,
        $data['Comm'] ?? 0,
        $data['Void/Refund/Emergency'] ?? '',
         $data['Tax INR'] ?? 0,
        $data['Comm INR'] ?? 0,
        $data['Transaction INR'] ?? 0,
        $data['Fare INR'] ?? 0,
        $data['Document'],
        $data['Document Type'] ?? '',
        $data['Vendor']
    ]);
    if (!$mainTableSuccess) {
        $errorInfo = $stmtUpdate->errorInfo();
        error_log('SQL Update Error: ' . print_r($errorInfo, true));
        return 'SQL Error: ' . $errorInfo[2];
    }
    if ($stmtUpdate->rowCount() == 0) {
        return 'No record updated for Document: ' . ($data['Document'] ?? '');
    }
    return true;
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
    $documentNumbers = [];
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
            'GKT IATA' => [
                'A_L' => 'AIR',
                'Document Type' => 'Ticket Type',
                'Document' => 'Ticket Number',
                'Issue Date' => 'Issue Date',
                'Transaction' => 'Total Transaction',
                'Fare' => 'Base fare',
                'Tax' => 'Tax',
                'Comm' => 'Commission',
                'PNR' => 'PNR',
                'Vendor' => 'Vendor',
                'Void/Refund/Emergency' => 'Void/Refund/Emergency',
                'Transaction INR' => 'Total Transaction INR',
                'Fare INR' => 'Base fare INR',
                'Tax INR' => 'Tax INR',
                'Comm INR' => 'Commission INR',
            ],
            'IFN IATA' => [
                'A_L' => 'AIR',
                'Document Type' => 'TRNC',
                'Document' => 'Document Number',
                'Issue Date' => 'Issue Date',
                'Transaction' => 'Transaction Amount',
                'Fare' => 'FARE Amount',
                'Tax' => 'TAX',
                'Comm' => 'Amt',
                'PNR' => 'PNR'
            ],
            'CTG' => [
                'A_L' => 'Particulars Air',
                'Document' => 'Ticket',
                'Issue Date' => 'Issued',
                'Document Type' => 'Type',
                'PNR' => 'Your Ref',
                'Transaction' => 'Transaction',
                'Fare' => 'Cash Fare($)',
                'Tax' => 'Total Tax ($)',
                'Fee' => 'Service Fee Amt($)',
                'Comm' => 'Amt ($)'
            ],
            'Gilpin' => [
                'PNR' => 'PNR',
                'Document' => 'Ticket No.',
                'Transaction' => 'Total fare',
                'Fare' => 'Basic Fare',
                'Fee' => 'Serv Charges'
            ]
        ];

        // Get mapping for selected sheet
        $mapping = $columnMappings[$selectedSheet] ?? [];
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
            
            if (isset($headerMap['Document'])) {
                $docNum = $row[$headerMap['Document']] ?? '';
                if ($docNum !== '') {
                    $documentNumbers[] = $docNum;
                }
            }
        }
        fclose($handle);

        // Batch query for existing documents
        $existingDocs = [];
        if (!empty($documentNumbers)) {
            $placeholders = implode(',', array_fill(0, count($documentNumbers), '?'));
            $stmt = $pdo->prepare("SELECT document FROM wpk4_backend_ticket_reconciliation WHERE document IN ($placeholders)");
            $stmt->execute($documentNumbers);
            $existingDocs = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        // Process data rows
        foreach ($csvRows as $row) {
            $docNum = isset($headerMap['Document']) ? $row[$headerMap['Document']] ?? '' : '';
            $exist = in_array($docNum, $existingDocs) ? 'Yes' : 'No';

            $rowData = [
                'PNR' => isset($headerMap['PNR']) ? $row[$headerMap['PNR']] ?? '' : '',
                'Document' => $docNum,
                'Document Type' => isset($headerMap['Document Type']) ? $row[$headerMap['Document Type']] ?? '' : '',
                'Transaction' => isset($headerMap['Transaction']) ? extractNumeric($row[$headerMap['Transaction']] ?? '') : 0,
                'Vendor' => isset($headerMap['Vendor']) ? $row[$headerMap['Vendor']] ?? '' : '',
                'Issue Date' => isset($headerMap['Issue Date']) ? parseDate($row[$headerMap['Issue Date']] ?? '') : null,
                'A_L' => isset($headerMap['A_L']) ? $row[$headerMap['A_L']] ?? '' : '',
                'Fare' => isset($headerMap['Fare']) ? extractNumeric($row[$headerMap['Fare']] ?? '') : 0,
                'Tax' => isset($headerMap['Tax']) ? extractNumeric($row[$headerMap['Tax']] ?? '') : 0,
                'Fee' => isset($headerMap['Fee']) ? extractNumeric($row[$headerMap['Fee']] ?? '') : 0,
                'Comm' => isset($headerMap['Comm']) ? extractNumeric($row[$headerMap['Comm']] ?? '') : 0,
                'Void/Refund/Emergency' => isset($headerMap['Void/Refund/Emergency']) ? $row[$headerMap['Void/Refund/Emergency']] ?? '' : '',
                'Fare INR' => isset($headerMap['Fare INR']) ? extractNumeric($row[$headerMap['Fare INR']] ?? '') : 0,
                'Tax INR' => isset($headerMap['Tax INR']) ? extractNumeric($row[$headerMap['Tax INR']] ?? '') : 0,
                'Transaction INR' => isset($headerMap['Transaction INR']) ? extractNumeric($row[$headerMap['Transaction INR']] ?? '') : 0,
                'Comm INR' => isset($headerMap['Comm INR']) ? extractNumeric($row[$headerMap['Comm INR']] ?? '') : 0,
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
            <td><?= htmlspecialchars($row['PNR']) ?></td>
            <td><?= htmlspecialchars($row['Document']) ?></td>
            <td><?= htmlspecialchars($row['Document Type']) ?></td>
            <td><?= number_format($row['Transaction'], 2) ?></td>
            <td><?= htmlspecialchars($row['Vendor']) ?></td>
            <td><?= htmlspecialchars($row['Issue Date'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Updated On']) ?></td>
            <td><?= htmlspecialchars($row['A_L']) ?></td>
            <td><?= number_format($row['Fare'], 2) ?></td>
            <td><?= number_format($row['Tax'], 2) ?></td>
            <td><?= number_format($row['Fee'], 2) ?></td>
            <td><?= number_format($row['Comm'], 2) ?></td>
            <td><?= htmlspecialchars($row['Void/Refund/Emergency']) ?></td>
            <td><?= number_format($row['Fare INR'], 2) ?></td>
            <td><?= number_format($row['Tax INR'], 2) ?></td>
            <td><?= number_format($row['Transaction INR'], 2) ?></td>
            <td><?= number_format($row['Comm INR'], 2) ?></td>
            <td>
                <?php if ($row['Exist'] === 'Yes'): ?>
                    <span class="badge bg-success">Yes <i class="bi bi-airplane"></i></span>
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
            if (updateDatabaseRecord($pdo, $item)) {
                $updated++;
            }
        } catch (Exception $e) {
            $errors[] = "Error updating document {$item['Document']}: " . $e->getMessage();
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
    <title>Flight Ticket Reconciliation ✈️</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gold: #FCB900;
            --orange: #FF6900;
        }
        .bg-gold { background-color: var(--gold); }
        .bg-orange { background-color: var(--orange); }
        .text-gold { color: var(--gold); }
        .text-orange { color: var(--orange); }
        .btn-gold {
            background-color: var(--gold);
            color: #000;
        }
        .btn-gold:hover {
            background-color: #e0a500;
            color: #000;
        }
        .table-matched { background-color: #d4edda !important; }
        .table-unmatched { background-color: #f8d7da !important; }
        .flight-icon { font-size: 1.5rem; }
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
            background-color: var(--gold);
            color: #000;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            z-index: 99;
        }
        .back-to-top:hover {
            background-color: #e0a500;
            color: #000;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--gold);
            border-color: var(--gold);
            color: #000;
        }
        .pagination .page-link {
            color: var(--orange);
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
                    <div class="card-header bg-gold">
                        <h2 class="mb-0"><i class="bi bi-airplane flight-icon"></i> Flight Ticket Reconciliation System</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="sheet_name" class="form-label">Select Sheet</label>
                                    <select class="form-select" id="sheet_name" name="sheet_name" required>
                                        <option value="">-- Select Sheet --</option>
                                        <option value="GKT IATA" <?= $selectedSheet === 'GKT IATA' ? 'selected' : '' ?>>All Vendors</option>
                                        <option value="IFN IATA" <?= $selectedSheet === 'IFN IATA' ? 'selected' : '' ?>>IFN IATA</option>
                                        <option value="CTG" <?= $selectedSheet === 'CTG' ? 'selected' : '' ?>>CTG</option>
                                        <option value="Gilpin" <?= $selectedSheet === 'Gilpin' ? 'selected' : '' ?>>Gilpin</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="csv_file" class="form-label">Upload CSV File</label>
                                    <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                    <small class="text-muted">Note: Only the first 1000 records will be processed</small>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-gold w-100" id="submitBtn">
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
                    <div class="card-header bg-orange text-white">
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
                    <div class="card-header bg-gold">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-table"></i> Reconciliation Results</h3>
                            <div>
                                <button id="updateBtn" class="btn btn-success me-2" <?= $stats['matched'] === 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-circle"></i> Update Matching Records
                                </button>
                                <button id="exportBtn" class="btn btn-primary">
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
                                        <th>PNR</th>
                                        <th>Document</th>
                                        <th>Document Type</th>
                                        <th>Transaction</th>
                                        <th>Vendor</th>
                                        <th>Issue Date</th>
                                        <th>Updated On</th>
                                        <th>A_L</th>
                                        <th>Fare</th>
                                        <th>Tax</th>
                                        <th>Fee</th>
                                        <th>Comm</th>
                                        <th>Void/Refund/Emergency</th>
                                        <th>Fare INR</th>
                                        <th>Tax INR</th>
                                        <th>Transaction INR</th>
                                        <th>Comm INR</th>
                                        <th>Exist</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Show only first 20 rows initially
                                    $initialRows = array_slice($processedData, 0, 20);
                                    foreach ($initialRows as $row): ?>
                                    <tr class="<?= $row['Exist'] === 'Yes' ? 'table-matched' : 'table-unmatched' ?>">
                                        <td><?= htmlspecialchars($row['PNR']) ?></td>
                                        <td><?= htmlspecialchars($row['Document']) ?></td>
                                        <td><?= htmlspecialchars($row['Document Type']) ?></td>
                                        <td><?= number_format($row['Transaction'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['Vendor']) ?></td>
                                        <td><?= htmlspecialchars($row['Issue Date']) ?></td>
                                        <td><?= htmlspecialchars($row['Updated On']) ?></td>
                                        <td><?= htmlspecialchars($row['A_L']) ?></td>
                                        <td><?= number_format($row['Fare'], 2) ?></td>
                                        <td><?= number_format($row['Tax'], 2) ?></td>
                                        <td><?= number_format($row['Fee'], 2) ?></td>
                                        <td><?= number_format($row['Comm'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['Void/Refund/Emergency']) ?></td>
                                        <td><?= number_format($row['Fare INR'], 2) ?></td>
                                        <td><?= number_format($row['Tax INR'], 2) ?></td>
                                        <td><?= number_format($row['Transaction INR'], 2) ?></td>
                                        <td><?= number_format($row['Comm INR'], 2) ?></td>
                                        <td>
                                            <?php if ($row['Exist'] === 'Yes'): ?>
                                                <span class="badge bg-success">Yes <i class="bi bi-airplane"></i></span>
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
                        '<?= addslashes($row['PNR']) ?>',
                        '<?= addslashes($row['Document']) ?>',
                        '<?= addslashes($row['Document Type']) ?>',
                        '<?= $row['Transaction'] ?>',
                        '<?= addslashes($row['Vendor']) ?>',
                        '<?= addslashes($row['Issue Date']) ?>',
                        '<?= addslashes($row['Updated On']) ?>',
                        '<?= addslashes($row['A_L']) ?>',
                        '<?= $row['Fare'] ?>',
                        '<?= $row['Tax'] ?>',
                        '<?= $row['Fee'] ?>',
                        '<?= $row['Comm'] ?>',
                        '<?= addslashes($row['Void/Refund/Emergency']) ?>',
                        '<?= $row['Exist'] === 'Yes' ? 'Yes' : 'No' ?>'
                    ].map(field => `"${field}"`).join(','));
                <?php endforeach; ?>

                // Download CSV file
                downloadCSV(csv.join('\n'), 'ticket_reconciliation_<?= date('Y-m-d') ?>.csv');
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

