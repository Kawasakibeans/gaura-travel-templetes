<?php
/**
 * Issue Flag Dashboard Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\IssueFlagDashboardDAL;
use Exception;

class IssueFlagDashboardService
{
    private $issueDAL;
    
    public function __construct()
    {
        $this->issueDAL = new IssueFlagDashboardDAL();
    }
    
    /**
     * Calculate priority based on travel date
     */
    private function getPriority($travelDate)
    {
        if (empty($travelDate)) {
            return 'Other';
        }
        
        $today = date('Y-m-d');
        $daysDiff = (strtotime($travelDate) - strtotime($today)) / (60 * 60 * 24);
        
        if ($daysDiff <= 1) {
            return 'D-1';
        } elseif ($daysDiff <= 7) {
            return 'D-7';
        } elseif ($daysDiff <= 10) {
            return 'D-10';
        } else {
            return 'Other';
        }
    }
    
    /**
     * Get all issues with filters
     */
    public function getIssues($filters = [])
    {
        $allIssues = [];
        $startTime = microtime(true);
        
        // Check if we should filter by category early to skip unnecessary queries
        $categoryFilter = $filters['category'] ?? null;
        
        try {
            // 1. TICKETING ISSUES
            if (!$categoryFilter || $categoryFilter === 'Ticketing') {
                // 1.1 - Date gap > 10 days
                $queryStart = microtime(true);
                $ticketing1 = $this->issueDAL->getTicketingIssues1();
                error_log("Query getTicketingIssues1 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($ticketing1 as $row) {
                    $dateModified = $row['late_modified'] ? $row['late_modified'] : $row['ticketed_on'];
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Ticketing',
                        'issue_description' => '[' . $paxName . '] Date gap exceeds 10 days between travel date and ticketing date (Modified: ' . $dateModified . ')',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                // 1.2 - Ticket number empty but ticketed_on/ticketed_by not empty
                $queryStart = microtime(true);
                $ticketing2 = $this->issueDAL->getTicketingIssues2();
                error_log("Query getTicketingIssues2 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($ticketing2 as $row) {
                    $issueFields = [];
                    if ($row['ticketed_on']) $issueFields[] = 'ticketed_on: ' . $row['ticketed_on'];
                    if ($row['ticketed_by']) $issueFields[] = 'ticketed_by: ' . $row['ticketed_by'];
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Ticketing',
                        'issue_description' => '[' . $paxName . '] ticket_number is empty but has values in: ' . implode(', ', $issueFields),
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                // 1.3 - Ticket number not empty but ticketed_on/ticketed_by empty
                $queryStart = microtime(true);
                $ticketing3 = $this->issueDAL->getTicketingIssues3();
                error_log("Query getTicketingIssues3 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($ticketing3 as $row) {
                    $missingFields = [];
                    if (!$row['ticketed_on']) $missingFields[] = 'ticketed_on';
                    if (!$row['ticketed_by']) $missingFields[] = 'ticketed_by';
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Ticketing',
                        'issue_description' => '[' . $paxName . '] ticket_number exists (' . $row['ticket_number'] . ') but missing: ' . implode(', ', $missingFields),
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                // 1.4 - Pax status not 'Ticketed' but has ticketing info
                $queryStart = microtime(true);
                $ticketing4 = $this->issueDAL->getTicketingIssues4();
                error_log("Query getTicketingIssues4 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($ticketing4 as $row) {
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Ticketing',
                        'issue_description' => '[' . $paxName . '] pax_status is "' . $row['pax_status'] . '" but should be "Ticketed" (has ticket_number: ' . $row['ticket_number'] . ')',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                // 1.5 - Name updated field empty but has ticketing info
                $queryStart = microtime(true);
                $ticketing5 = $this->issueDAL->getTicketingIssues5();
                error_log("Query getTicketingIssues5 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($ticketing5 as $row) {
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Ticketing',
                        'issue_description' => '[' . $paxName . '] name_updated field is empty but has ticketing data present',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 2. NAME UPDATE ISSUES
            if (!$categoryFilter || $categoryFilter === 'Name Update') {
                $queryStart = microtime(true);
                $nameUpdate = $this->issueDAL->getNameUpdateIssues();
                error_log("Query getNameUpdateIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($nameUpdate as $row) {
                    $missingFields = [];
                    if (!$row['name_update_check']) $missingFields[] = 'name_update_check';
                    if (!$row['name_update_check_on']) $missingFields[] = 'name_update_check_on';
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Name Update',
                        'issue_description' => '[' . $paxName . '] WPT order with payment_status="' . $row['payment_status'] . '" but missing: ' . implode(', ', $missingFields),
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 3. PNR VALIDATION ISSUES
            if (!$categoryFilter || $categoryFilter === 'PNR Validation') {
                $queryStart = microtime(true);
                $pnr = $this->issueDAL->getPnrValidationIssues();
                error_log("Query getPnrValidationIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($pnr as $row) {
                    $stockPnrsDisplay = $row['stock_pnrs'] ? $row['stock_pnrs'] : 'None found';
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'PNR Validation',
                        'issue_description' => 'PNR mismatch - Booking PNR: "' . $row['pax_pnr'] . '" not found in Stock Management PNRs: [' . $stockPnrsDisplay . ']',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 4. PAX COUNT VALIDATION ISSUES
            if (!$categoryFilter || $categoryFilter === 'Pax Count Validation') {
                $queryStart = microtime(true);
                $paxCount = $this->issueDAL->getPaxCountValidationIssues();
                error_log("Query getPaxCountValidationIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($paxCount as $row) {
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Pax Count Validation',
                        'issue_description' => 'total_pax field shows ' . $row['total_pax'] . ' but actual pax records count is ' . $row['actual_pax_count'],
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 5. PAYMENT ISSUES
            if (!$categoryFilter || $categoryFilter === 'Payment') {
                // 5.1 - Payment exists but ticket number missing
                $queryStart = microtime(true);
                $payment1 = $this->issueDAL->getPaymentIssues1();
                error_log("Query getPaymentIssues1 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($payment1 as $row) {
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Payment',
                        'issue_description' => 'Payment record exists (ID: ' . $row['payment_record_id'] . ') but ticket_number field is empty',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                // 5.2 - Total amount mismatch
                $queryStart = microtime(true);
                $payment2 = $this->issueDAL->getPaymentIssues2();
                error_log("Query getPaymentIssues2 took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($payment2 as $row) {
                    $difference = round($row['total_amount'], 2) - round($row['total_paid'], 2);
                    if (abs($difference) >= 0.01) {
                        $status = $difference > 0 ? 'Underpaid' : 'Overpaid';
                        $allIssues[] = [
                            'order_id' => $row['order_id'],
                            'order_type' => $row['order_type'],
                            'category' => 'Payment',
                            'issue_description' => 'total_amount (' . number_format($row['total_amount'], 2) . ') != sum of payments (' . number_format($row['total_paid'], 2) . ') - ' . $status . ' by ' . number_format(abs($difference), 2),
                            'travel_date' => $row['travel_date'],
                            'priority' => $this->getPriority($row['travel_date']),
                            'details' => 'Trip: ' . $row['trip_code']
                        ];
                    }
                }
            }
            
            // 6. GDS TICKETING ISSUES
            if (!$categoryFilter || $categoryFilter === 'GDS Ticketing') {
                $queryStart = microtime(true);
                $gds = $this->issueDAL->getGdsTicketingIssues();
                error_log("Query getGdsTicketingIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($gds as $row) {
                    $hoursSinceOrder = round((strtotime(date('Y-m-d H:i:s')) - strtotime($row['order_date'])) / 3600);
                    $paxName = trim($row['fname'] . ' ' . $row['lname']);
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'GDS Ticketing',
                        'issue_description' => '[' . $paxName . '] GDS order paid but ticket_number empty (Order placed ' . $hoursSinceOrder . ' hours ago on ' . $row['order_date'] . ')',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 7. PAYMENT STATUS MISMATCH
            if (!$categoryFilter || $categoryFilter === 'Payment Status') {
                $queryStart = microtime(true);
                $paymentStatus = $this->issueDAL->getPaymentStatusIssues();
                error_log("Query getPaymentStatusIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($paymentStatus as $row) {
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Payment Status',
                        'issue_description' => 'payment_status is "' . $row['payment_status'] . '" but received payment amount: ' . number_format($row['total_received'], 2),
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 8. DUPLICATE ORDER ISSUES
            if (!$categoryFilter || $categoryFilter === 'Duplicate Order') {
                $queryStart = microtime(true);
                $duplicateGds = $this->issueDAL->getDuplicateOrderIssuesGds();
                error_log("Query getDuplicateOrderIssuesGds took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($duplicateGds as $row) {
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Duplicate Order',
                        'issue_description' => 'GDS order has ' . $row['record_count'] . ' duplicate records in bookings table',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
                
                $queryStart = microtime(true);
                $duplicateWpt = $this->issueDAL->getDuplicateOrderIssuesWpt();
                error_log("Query getDuplicateOrderIssuesWpt took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($duplicateWpt as $row) {
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Duplicate Order',
                        'issue_description' => 'WPT order has ' . $row['record_count'] . ' records (exceeds limit of 2) in bookings table',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code']
                    ];
                }
            }
            
            // 9. BOOKING NOTES
            if (!$categoryFilter || $categoryFilter === 'Booking Notes') {
                $queryStart = microtime(true);
                $bookingNotes = $this->issueDAL->getBookingNotesIssues();
                error_log("Query getBookingNotesIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($bookingNotes as $row) {
                    $daysUntilTravel = round((strtotime($row['travel_date']) - strtotime(date('Y-m-d'))) / 86400);
                    $noteDate = $row['note_date'] ? $row['note_date'] : 'N/A';
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Booking Notes',
                        'issue_description' => 'Booking has notes (Travel in ' . $daysUntilTravel . ' days) - Note: ' . substr($row['note_content'], 0, 100) . (strlen($row['note_content']) > 100 ? '...' : ''),
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code'] . ', Note Date: ' . $noteDate
                    ];
                }
            }
            
            // 10. ACTIVE ISSUE LOGS
            if (!$categoryFilter || $categoryFilter === 'Active Issue Log') {
                $queryStart = microtime(true);
                $activeIssues = $this->issueDAL->getActiveIssueLogIssues();
                error_log("Query getActiveIssueLogIssues took: " . round(microtime(true) - $queryStart, 2) . " seconds");
                foreach ($activeIssues as $row) {
                    $issueCreated = $row['issue_created'] ? $row['issue_created'] : 'N/A';
                    $allIssues[] = [
                        'order_id' => $row['order_id'],
                        'order_type' => $row['order_type'],
                        'category' => 'Active Issue Log',
                        'issue_description' => 'Paid booking has active issue log (Log ID: ' . $row['issue_log_id'] . ')',
                        'travel_date' => $row['travel_date'],
                        'priority' => $this->getPriority($row['travel_date']),
                        'details' => 'Trip: ' . $row['trip_code'] . ', Issue Created: ' . $issueCreated
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log("Error in getIssues: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            // Continue with partial results if possible
        }
        
        // Apply filters
        $filteredIssues = $allIssues;
        
        if (!empty($filters['order_id'])) {
            $searchOrderId = $filters['order_id'];
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($searchOrderId) {
                return stripos($issue['order_id'], $searchOrderId) !== false;
            });
        }
        
        if (!empty($filters['order_type'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['order_type'] == $filters['order_type'];
            });
        }
        
        if (!empty($filters['category'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['category'] == $filters['category'];
            });
        }
        
        if (!empty($filters['priority'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['priority'] == $filters['priority'];
            });
        }
        
        // Re-index array
        $filteredIssues = array_values($filteredIssues);
        
        // Apply filters
        $filteredIssues = $allIssues;
        
        if (!empty($filters['order_id'])) {
            $searchOrderId = $filters['order_id'];
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($searchOrderId) {
                return stripos($issue['order_id'], $searchOrderId) !== false;
            });
        }
        
        if (!empty($filters['order_type'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['order_type'] == $filters['order_type'];
            });
        }
        
        if (!empty($filters['category'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['category'] == $filters['category'];
            });
        }
        
        if (!empty($filters['priority'])) {
            $filteredIssues = array_filter($filteredIssues, function($issue) use ($filters) {
                return $issue['priority'] == $filters['priority'];
            });
        }
        
        // Re-index array
        $filteredIssues = array_values($filteredIssues);
        
        // Apply pagination
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        $total = count($filteredIssues);
        $paginatedIssues = array_slice($filteredIssues, $offset, $limit);
        
        // Calculate statistics
        $statistics = $this->calculateStatistics($filteredIssues);
        
        $totalTime = round(microtime(true) - $startTime, 2);
        error_log("IssueFlagDashboardService::getIssues completed in {$totalTime} seconds. Total issues: {$total}");
        
        return [
            'issues' => $paginatedIssues,
            'statistics' => $statistics,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Calculate statistics
     */
    public function calculateStatistics($issues = null, $filters = [])
    {
        if ($issues === null) {
            $result = $this->getIssues($filters);
            $issues = $result['issues'];
        }
        
        $total = count($issues);
        $d1Count = count(array_filter($issues, function($i) { return $i['priority'] == 'D-1'; }));
        $d7Count = count(array_filter($issues, function($i) { return $i['priority'] == 'D-7'; }));
        $d10Count = count(array_filter($issues, function($i) { return $i['priority'] == 'D-10'; }));
        $otherCount = $total - $d1Count - $d7Count - $d10Count;
        
        // Count by category
        $byCategory = [];
        foreach ($issues as $issue) {
            $category = $issue['category'];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }
        
        // Count by order type
        $byOrderType = [];
        foreach ($issues as $issue) {
            $orderType = $issue['order_type'];
            $byOrderType[$orderType] = ($byOrderType[$orderType] ?? 0) + 1;
        }
        
        return [
            'total_issues' => $total,
            'd1_count' => $d1Count,
            'd7_count' => $d7Count,
            'd10_count' => $d10Count,
            'other_count' => $otherCount,
            'by_category' => $byCategory,
            'by_order_type' => $byOrderType
        ];
    }
    
    /**
     * Send email report
     */
    public function sendEmailReport($filters = [], $recipientEmail = null)
    {
        // Get issues with filters
        $result = $this->getIssues($filters);
        $issues = $result['issues'];
        $statistics = $result['statistics'];
        
        // Build email body
        $emailBody = '<h2>Travel Booking Issues Report</h2>';
        $emailBody .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        $emailBody .= '<h3>Summary Statistics</h3>';
        $emailBody .= '<ul>';
        $emailBody .= '<li><strong>Total Issues:</strong> ' . $statistics['total_issues'] . '</li>';
        $emailBody .= '<li><strong>D-1 Priority:</strong> ' . $statistics['d1_count'] . '</li>';
        $emailBody .= '<li><strong>D-7 Priority:</strong> ' . $statistics['d7_count'] . '</li>';
        $emailBody .= '<li><strong>D-10 Priority:</strong> ' . $statistics['d10_count'] . '</li>';
        $emailBody .= '</ul>';
        
        if (count($issues) > 0) {
            $emailBody .= '<h3>Issue Details</h3>';
            $emailBody .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
            $emailBody .= '<tr style="background:#0073aa;color:white;">';
            $emailBody .= '<th>Order ID</th><th>Order Type</th><th>Category</th><th>Issue</th><th>Travel Date</th><th>Priority</th><th>Details</th>';
            $emailBody .= '</tr>';
            
            foreach ($issues as $issue) {
                $emailBody .= '<tr>';
                $emailBody .= '<td>' . htmlspecialchars($issue['order_id']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['order_type']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['category']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['issue_description']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['travel_date']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['priority']) . '</td>';
                $emailBody .= '<td>' . htmlspecialchars($issue['details']) . '</td>';
                $emailBody .= '</tr>';
            }
            
            $emailBody .= '</table>';
        }
        
        // Note: Actual email sending would require email service configuration
        // For now, return the email body and recipient info
        return [
            'recipient' => $recipientEmail ?? 'admin@example.com',
            'issues_count' => count($issues),
            'sent_at' => date('Y-m-d H:i:s'),
            'email_body' => $emailBody
        ];
    }
}

