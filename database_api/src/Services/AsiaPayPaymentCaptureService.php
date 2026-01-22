<?php
/**
 * AsiaPay Payment Capture Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AsiaPayPaymentCaptureDAL;
use Exception;
use DateTime;
use DateInterval;

class AsiaPayPaymentCaptureService
{
    private $dal;
    private $asiaPayApiUrl = "https://paydollar.com/b2c2/eng/merchant/api/orderApi.jsp";
    
    // Merchant credentials (should be in environment variables)
    private $merchants = [
        'gds' => [
            'merchantId' => '16001202',
            'loginId' => 'apigaur',
            'password' => 'gaur0706'
        ],
        'wpt' => [
            'merchantId' => '16001455',
            'loginId' => 'apigaura',
            'password' => 'gaura0813'
        ]
    ];
    
    public function __construct()
    {
        $this->dal = new AsiaPayPaymentCaptureDAL();
    }
    
    /**
     * Fetch settings from admin backend
     */
    private function fetchSettings()
    {
        $apiUrl = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass_main.php';
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'GTX-SettingsFetcher/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($body === false || $http !== 200) {
            throw new Exception("Failed to load settings: " . ($err ?: "HTTP $http"));
        }
        
        $resp = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($resp) || empty($resp['success'])) {
            throw new Exception("Invalid settings response");
        }
        
        $settings = $resp['data'] ?? [];
        $result = [];
        foreach ($settings as $k => $v) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
                $result[$k] = $v;
            }
        }
        
        return $result;
    }
    
    /**
     * Query AsiaPay API for payment logs
     */
    private function queryAsiaPayRequestLog($merchantId, $loginId, $password, $queryDate)
    {
        $postData = http_build_query([
            "merchantId" => $merchantId,
            "loginId" => $loginId,
            "password" => $password,
            "actionType" => "QueryRequestLog",
            "periodType" => "D",
            "queryDate" => $queryDate,
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->asiaPayApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: " . $error);
        }
        
        curl_close($ch);
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new Exception("Failed to parse XML response");
        }
        
        return $xml;
    }
    
    /**
     * Query AsiaPay API for individual transaction details
     */
    private function queryAsiaPayTransaction($merchantId, $loginId, $password, $orderRef)
    {
        $postData = http_build_query([
            "merchantId" => $merchantId,
            "loginId" => $loginId,
            "password" => $password,
            "actionType" => "Query",
            "orderRef" => $orderRef
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->asiaPayApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: " . $error);
        }
        
        curl_close($ch);
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new Exception("Failed to parse XML response");
        }
        
        return $xml;
    }
    
    /**
     * Extract PNR from remark
     */
    private function extractPnrFromRemark($remark)
    {
        if (empty($remark) || strlen($remark) < 14) {
            return null;
        }
        
        $pnr = substr($remark, 8, 6);
        if (empty(trim($pnr)) || trim($pnr) == '- Gau') {
            return null;
        }
        
        return trim($pnr);
    }
    
    /**
     * Process payment capture
     */
    public function captureMissingPayments($queryDate = null, $source = 'gds', $merchantId = null)
    {
        date_default_timezone_set("Australia/Melbourne");
        $currentDate = date('Y-m-d H:i:s');
        
        $response = [
            'success' => false,
            'message' => '',
            'summary' => [
                'total_references_queried' => 0,
                'transactions_inserted' => 0,
                'transactions_updated' => 0,
                'transactions_modified' => 0,
                'payment_history_inserted' => 0,
                'payment_history_deleted' => 0,
                'journal_entries_created' => 0
            ],
            'detailed_results' => []
        ];
        
        try {
            // Fetch settings
            $settings = $this->fetchSettings();
            $timezoneOffset = $settings['GDEAL_ASIAPAY_TIMEZONE'] ?? '+3 hours';
            
            // Determine merchant credentials
            if ($merchantId === null) {
                $merchant = $this->merchants[$source] ?? $this->merchants['gds'];
            } else {
                // Find merchant by ID
                $merchant = null;
                foreach ($this->merchants as $m) {
                    if ($m['merchantId'] == $merchantId) {
                        $merchant = $m;
                        break;
                    }
                }
                if ($merchant === null) {
                    $merchant = $this->merchants['gds'];
                }
            }
            
            // Determine query date
            if ($queryDate === null) {
                $queryDate = date('dmY');
            } elseif (is_string($queryDate) && strlen($queryDate) == 8) {
                // Already in dmY format
            } else {
                $queryDate = date('dmY', strtotime($queryDate));
            }
            
            // Query AsiaPay API
            $xml = $this->queryAsiaPayRequestLog(
                $merchant['merchantId'],
                $merchant['loginId'],
                $merchant['password'],
                $queryDate
            );
            
            // Group by ref
            $groupedResults = [];
            foreach ($xml as $item) {
                $ref = (string)$item->ref;
                if (!isset($groupedResults[$ref])) {
                    $groupedResults[$ref] = [];
                }
                $groupedResults[$ref][] = $item;
            }
            
            $response['summary']['total_references_queried'] = count($groupedResults);
            
            // Process each reference
            foreach ($groupedResults as $ref => $entries) {
                $amt = 0;
                $ipCountry = '';
                
                foreach ($entries as $entry) {
                    $amt = (float)$entry->amt;
                    $ipCountry = (string)$entry->ipCountry;
                }
                
                $referenceResult = [
                    'reference_no' => (int)$ref,
                    'order_id' => '',
                    'status' => 'Processed',
                    'details' => []
                ];
                
                try {
                    // Query individual transaction
                    $xmlIndividual = $this->queryAsiaPayTransaction(
                        $merchant['merchantId'],
                        $merchant['loginId'],
                        $merchant['password'],
                        $ref
                    );
                    
                    foreach ($xmlIndividual->record as $xmlRecord) {
                        $orderStatus = (string)$xmlRecord->orderStatus;
                        $dbRef = (string)$xmlRecord->ref;
                        $dbPayRef = (string)$xmlRecord->payRef;
                        $dbAmt = number_format((float)$xmlRecord->amt, 2, '.', '');
                        $dbRemark = (string)$xmlRecord->remark;
                        
                        // WPT (GDeals) uses cropped ref (first 6 chars) as order_id
                        // GDS uses PNR matching or payRef
                        if ($source == 'wpt') {
                            $croppedRef = substr($dbRef, 0, 6);
                            $currentOrderId = $croppedRef;
                            $expectedOrderId = $croppedRef;
                            $referenceResult['order_id'] = $croppedRef;
                        } else {
                            $currentOrderId = $dbPayRef;
                            $expectedOrderId = $dbPayRef;
                            $referenceResult['order_id'] = $dbPayRef;
                            
                            // Extract PNR and find order_id (GDS only)
                            $pnr = $this->extractPnrFromRemark($dbRemark);
                            if ($pnr !== null) {
                                $orderIdByPnr = $this->dal->getOrderIdByPnr($pnr);
                                if ($orderIdByPnr !== null) {
                                    $currentOrderId = $orderIdByPnr;
                                    $referenceResult['order_id'] = $currentOrderId;
                                }
                            }
                        }
                        
                        // Insert transaction if not exists
                        if (($orderStatus == 'Accepted' || $orderStatus == 'Rejected') && !$this->dal->transactionExists($dbRef)) {
                            $transactionData = [
                                'orderStatus' => $orderStatus,
                                'ref' => $dbRef,
                                'payRef' => $dbPayRef,
                                'source' => $source,
                                'amt' => $dbAmt,
                                'cur' => (string)$xmlRecord->cur,
                                'prc' => (string)$xmlRecord->prc,
                                'src' => (string)$xmlRecord->src,
                                'ord' => (string)$xmlRecord->ord,
                                'holder' => (string)$xmlRecord->holder,
                                'authId' => (string)$xmlRecord->authId,
                                'alertCode' => (string)$xmlRecord->alertCode,
                                'remark' => $dbRemark,
                                'eci' => (string)$xmlRecord->eci,
                                'payerAuth' => (string)$xmlRecord->payerAuth,
                                'sourceIp' => (string)$xmlRecord->sourceIp,
                                'ipCountry' => (string)$xmlRecord->ipCountry,
                                'payMethod' => (string)$xmlRecord->payMethod,
                                'panFirst4' => (string)$xmlRecord->panFirst4,
                                'panLast4' => (string)$xmlRecord->panLast4,
                                'cardIssuingCountry' => (string)$xmlRecord->cardIssuingCountry,
                                'channelType' => (string)$xmlRecord->channelType,
                                'txTime' => (string)$xmlRecord->txTime,
                                'successcode' => (string)$xmlRecord->successcode,
                                'MerchantId' => (string)$xmlRecord->MerchantId,
                                'errMsg' => (string)$xmlRecord->errMsg,
                                'expected_orderid' => $expectedOrderId,
                                'current_orderid' => $currentOrderId
                            ];
                            
                            $this->dal->insertTransaction($transactionData);
                            $response['summary']['transactions_inserted']++;
                            $referenceResult['details'][] = [
                                'action' => 'insert',
                                'message' => 'New transaction inserted.'
                            ];
                        } else {
                            $referenceResult['details'][] = [
                                'action' => 'skip',
                                'message' => 'Transaction already exists.'
                            ];
                        }
                        
                        // Process Accepted orders
                        if ($orderStatus == 'Accepted') {
                            // WPT uses ref (full) as reference_no, croppedRef as order_id
                            // GDS uses payRef as reference_no, matched order_id
                            if ($source == 'wpt') {
                                $newPaymentRef = $dbRef; // Full ref for WPT
                                $newOrderId = $currentOrderId; // croppedRef
                            } else {
                                $newPaymentRef = $dbPayRef;
                                $newOrderId = $currentOrderId;
                                
                                if ($currentOrderId != $dbPayRef) {
                                    $newPaymentRef = $dbPayRef;
                                    $newOrderId = $currentOrderId;
                                }
                            }
                            
                            $txTime = (string)$xmlRecord->txTime;
                            $bookedDateTime = new DateTime($txTime);
                            $bookedDateTimeOrg = new DateTime($txTime);
                            
                            if (isset($timezoneOffset)) {
                                $bookedDateTime->modify($timezoneOffset);
                            }
                            
                            $txTimeFormatted = $bookedDateTime->format('Y-m-d H:i:s');
                            $txTimeFormattedYmd = $bookedDateTime->format('Y-m-d');
                            $txTimeFormattedOrg = $bookedDateTimeOrg->format('Y-m-d');
                            
                            $transactionTime = new DateTime($txTimeFormatted);
                            $currentTime = new DateTime();
                            
                            // WPT uses 5 minutes, GDS also uses 5 minutes (changed from 30)
                            $transactionTimePlus5 = clone $transactionTime;
                            $transactionTimePlus5->add(new DateInterval('PT5M'));
                            
                            // Only process if transaction is > 5 minutes old
                            if ($currentTime > $transactionTimePlus5) {
                                // Check if payment history exists
                                if (!$this->dal->paymentHistoryExists($newPaymentRef, $newOrderId, $txTimeFormatted, $dbAmt)) {
                                    // Get booking order date
                                    $previousOrderDate = $this->dal->getBookingOrderDate($newOrderId);
                                    
                                    // Calculate payment refund deadline
                                    $paymentRefundDeadline = date('Y-m-d H:i:s', strtotime($previousOrderDate . ' +96 hours'));
                                    
                                    // Get invoice ID
                                    $invoiceId = $this->dal->getLatestInvoiceId($newOrderId);
                                    
                                    // Insert payment history
                                    $addedBy = ($source == 'wpt') ? 'asiapay_cronjob' : 'asiapay_cronjob_updator';
                                    $paymentData = [
                                        'order_id' => $newOrderId,
                                        'source' => $source,
                                        'payment_method' => '8',
                                        'process_date' => $txTimeFormatted,
                                        'added_by' => $addedBy,
                                        'added_on' => $currentDate,
                                        'pay_type' => 'deposit',
                                        'reference_no' => $newPaymentRef,
                                        'trams_received_amount' => $dbAmt,
                                        'payment_change_deadline' => $paymentRefundDeadline,
                                        'gaura_invoice_id' => $invoiceId
                                    ];
                                    
                                    $this->dal->insertPaymentHistory($paymentData);
                                    $response['summary']['payment_history_inserted']++;
                                    $referenceResult['details'][] = [
                                        'action' => 'insert',
                                        'message' => 'Payment history inserted.'
                                    ];
                                    
                                    // Create journal entries (for both WPT and GDS)
                                    $this->createJournalEntries($newOrderId, $dbAmt, $invoiceId, $currentDate);
                                    $response['summary']['journal_entries_created']++;
                                } else {
                                    $referenceResult['details'][] = [
                                        'action' => 'skip',
                                        'message' => 'Payment history record already exists.'
                                    ];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $referenceResult['status'] = 'Error';
                    $referenceResult['details'][] = [
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
                
                $response['detailed_results'][] = $referenceResult;
            }
            
            $response['success'] = true;
            $response['message'] = 'Processing completed successfully.';
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = 'Processing failed: ' . $e->getMessage();
        }
        
        return $response;
    }
    
    /**
     * Create journal entries for payment
     */
    private function createJournalEntries($orderId, $depositedAmt, $invoiceId, $currentDate)
    {
        // Get payment record
        $paymentRecord = $this->dal->getPaymentForJournal($orderId);
        if ($paymentRecord === null) {
            return;
        }
        
        $depositedAmt = $paymentRecord['trams_received_amount'];
        $journalDate = $currentDate;
        $journalNote = 'Payment for ' . $orderId;
        $period = date('M-Y');
        
        // Insert journal entry
        $journalId = $this->dal->insertJournalEntry([
            'journal_date' => $journalDate,
            'period' => $period,
            'notes' => $journalNote,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'created_at' => $journalDate
        ]);
        
        // Insert debit line
        $this->dal->insertJournalLineDebit([
            'journal_id' => $journalId,
            'line_no' => 1,
            'debit' => $depositedAmt,
            'order_id' => $orderId,
            'created_at' => $journalDate
        ]);
        
        // Get booking with total pax
        $booking = $this->dal->getBookingWithTotalPax($orderId);
        if ($booking !== null) {
            $totalPax = $booking['total_pax'];
            $individualDepositVal = number_format((float)$depositedAmt / $totalPax, 2, '.', '');
            
            // Get passengers
            $passengers = $this->dal->getPassengersForOrder($orderId);
            $lineNo = 1;
            
            foreach ($passengers as $passenger) {
                $lineNo++;
                $paxName = $passenger['lname'] . '/' . $passenger['fname'];
                
                $this->dal->insertJournalLineCredit([
                    'journal_id' => $journalId,
                    'line_no' => $lineNo,
                    'description' => $paxName,
                    'credit' => $individualDepositVal,
                    'order_id' => $orderId,
                    'pax_id' => $passenger['auto_id'],
                    'created_at' => $journalDate
                ]);
            }
        }
    }
}

