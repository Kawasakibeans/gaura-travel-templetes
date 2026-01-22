<?php
/**
 * AsiaPay Settlement Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AsiaPaySettlementDAL;
use Exception;
use DateTime;

class AsiaPaySettlementService
{
    private $dal;
    private $asiaPayApiUrl = "https://paydollar.com/b2c2/eng/merchant/api/orderApi.jsp";
    
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
        $this->dal = new AsiaPaySettlementDAL();
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
     * Calculate settlement date based on transaction date and payment method
     */
    private function calculateSettlementDate($transactionDate, $payMethod)
    {
        $date = new DateTime($transactionDate);
        $dayOfWeek = $date->format('N'); // 1 (Monday) to 7 (Sunday)
        
        if ($payMethod == 'PAYID') {
            // PAYID: Always add 1 day
            $date->modify('+1 day');
        } elseif ($payMethod == 'Master' || $payMethod == 'VISA') {
            // Card payments
            if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
                // Monday to Thursday: add 1 day
                $date->modify('+1 day');
            } else {
                // Friday, Saturday, Sunday: next Monday
                $date->modify('next Monday');
            }
        }
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Get settlement data
     */
    public function getSettlementData($queryDate = null, $merchantId = null, $source = 'gds')
    {
        date_default_timezone_set("Australia/Melbourne");
        $currentDate = date('Y-m-d');
        
        try {
            // Fetch settings
            $settings = $this->fetchSettings();
            $timezoneOffset = $settings['GDEAL_ASIAPAY_TIMEZONE'] ?? '+3 hours';
            
            // Determine merchant
            if ($merchantId === null) {
                $merchant = $this->merchants[$source] ?? $this->merchants['gds'];
            } else {
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
                $queryDate = date('dmY', strtotime('yesterday'));
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
            
            $settlementData = [];
            $rowNumber = 0;
            
            // Process each reference
            foreach ($groupedResults as $ref => $entries) {
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
                        
                        if ($orderStatus != 'Accepted') {
                            continue;
                        }
                        
                        $ref = (string)$xmlRecord->ref;
                        $amt = number_format((float)$xmlRecord->amt, 2, '.', '');
                        $payMethod = (string)$xmlRecord->payMethod;
                        $payRef = (string)$xmlRecord->payRef;
                        $txTime = (string)$xmlRecord->txTime;
                        
                        // Skip AMEX and U
                        if ($payMethod == 'AMEX' || $payMethod == 'U') {
                            continue;
                        }
                        
                        // Process PAYID, VISA, Master
                        if ($payMethod == 'PAYID' || $payMethod == 'Master' || $payMethod == 'VISA') {
                            $bookedDateTime = new DateTime($txTime);
                            if (isset($timezoneOffset)) {
                                $bookedDateTime->modify($timezoneOffset);
                            }
                            $txTimeFormatted = $bookedDateTime->format('Y-m-d H:i:s');
                            $txTimeFormattedYmd = $bookedDateTime->format('Y-m-d');
                            
                            // Skip if transaction date is today or future
                            if ($txTimeFormattedYmd >= $currentDate) {
                                continue;
                            }
                            
                            $transactionDate = $txTimeFormatted;
                            $settlementDate = $this->calculateSettlementDate($transactionDate, $payMethod);
                            
                            // WPT uses cropped ref (first 6 chars) as order_id, full ref as reference_no
                            // GDS uses ltrim(payRef, '0') as order_id
                            if ($source == 'wpt') {
                                $orderId = substr($ref, 0, 6); // croppedRef
                                $referenceNo = $ref; // full ref
                            } else {
                                $orderId = ltrim($payRef, '0');
                                $referenceNo = $orderId;
                            }
                            
                            // Get booking info
                            $booking = $this->dal->getBookingPaymentStatus($orderId);
                            $paymentStatus = $booking ? $booking['payment_status'] : '';
                            $bookingExists = $booking !== null;
                            
                            // Check payment existence
                            $paymentExists = false;
                            $matchStatus = 'New';
                            
                            if ($payMethod == 'PAYID') {
                                $paymentExists = $this->dal->paymentExistsPayid($orderId, $referenceNo);
                            } else {
                                $paymentExists = $this->dal->paymentExistsCard($orderId, $referenceNo, $amt);
                            }
                            
                            if ($paymentExists) {
                                $matchStatus = 'Existing';
                            }
                            
                            $rowNumber++;
                            
                            $messages = [];
                            if (!$bookingExists) {
                                $messages[] = 'Booking does not exist';
                            }
                            if (!$paymentExists) {
                                $messages[] = 'Payment does not exist';
                            }
                            
                            $settlementData[] = [
                                'row_number' => $rowNumber,
                                'transaction_date' => $transactionDate,
                                'order_id' => $orderId,
                                'amount' => $amt,
                                'payment_status' => $paymentStatus,
                                'reference' => $referenceNo,
                                'settlement_date' => $settlementDate,
                                'message' => implode(', ', $messages),
                                'match_status' => $matchStatus,
                                'booking_exists' => $bookingExists,
                                'payment_exists' => $paymentExists,
                                'transaction_type' => $payMethod
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("AsiaPaySettlementService::getSettlementData error for ref $ref: " . $e->getMessage());
                    continue;
                }
            }
            
            return [
                'status' => 'success',
                'data' => $settlementData
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * Update settlement status
     */
    public function updateSettlementStatus($orderId, $amount, $settlementDate, $transactionType, $referenceNo = null, $source = 'gds')
    {
        try {
            // WPT uses reference_no (full ref), GDS uses order_id as reference_no
            if ($source == 'wpt' && $referenceNo !== null) {
                $refNo = $referenceNo;
            } else {
                $refNo = $orderId;
            }
            
            $this->dal->updateSettlementStatus($orderId, $refNo, $amount, $settlementDate);
            
            return [
                'status' => 'success',
                'message' => 'Settlement status updated successfully',
                'data' => [
                    'order_id' => $orderId,
                    'updated_rows' => 1
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Batch update settlement status
     */
    public function batchUpdateSettlementStatus($settlements)
    {
        $results = [];
        $total = count($settlements);
        $updated = 0;
        $failed = 0;
        
        foreach ($settlements as $settlement) {
            try {
                $result = $this->updateSettlementStatus(
                    $settlement['order_id'],
                    $settlement['amount'],
                    $settlement['settlement_date'],
                    $settlement['transaction_type'] ?? 'PAYID',
                    $settlement['reference_no'] ?? null,
                    $settlement['source'] ?? 'gds'
                );
                
                if ($result['status'] == 'success') {
                    $updated++;
                } else {
                    $failed++;
                }
                
                $results[] = [
                    'order_id' => $settlement['order_id'],
                    'status' => $result['status'],
                    'updated_rows' => $result['data']['updated_rows'] ?? 0,
                    'message' => $result['message'] ?? ''
                ];
            } catch (Exception $e) {
                $failed++;
                $results[] = [
                    'order_id' => $settlement['order_id'] ?? '',
                    'status' => 'error',
                    'updated_rows' => 0,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return [
            'status' => 'success',
            'message' => 'Batch settlement update completed',
            'summary' => [
                'total' => $total,
                'updated' => $updated,
                'failed' => $failed
            ],
            'results' => $results
        ];
    }
}

