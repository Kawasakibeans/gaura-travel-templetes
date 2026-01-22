<?php

namespace App\Services;

use App\DAL\AzupayManagementDAL;
use Exception;
use GuzzleHttp\Client;

class AzupayManagementService
{
    private $azupayDAL;
    private $client;
    private $authorizationCode;
    private $accessUrl;
    private $clientId;
    
    public function __construct()
    {
        $this->azupayDAL = new AzupayManagementDAL();
        $this->client = new Client();
        
        // Live credentials (should be moved to environment variables)
        $this->authorizationCode = $_ENV['AZUPAY_AUTHORIZATION_CODE'] ?? 'SECR7566D1_c4cc3709d612d1e0e677833ffbcef703_9Kz3JvUrYqPECSwl';
        $this->accessUrl = $_ENV['AZUPAY_ACCESS_URL'] ?? 'https://api.azupay.com.au/v1';
        $this->clientId = $_ENV['AZUPAY_CLIENT_ID'] ?? 'c4cc3709d612d1e0e677833ffbcef703';
    }
    
    /**
     * Search payment requests
     */
    public function searchPaymentRequests($clientTransactionId = null, $fromDate = null, $toDate = null, $payId = null)
    {
        $searchConditions = [];
        
        if (!empty($clientTransactionId)) {
            $searchConditions[] = '"clientTransactionId":"' . $clientTransactionId . '"';
        }
        
        if (!empty($fromDate) && !empty($toDate)) {
            // Format dates for Azupay API
            $startDate = new \DateTime($fromDate . 'T00:00:00+10:00');
            $formattedStartDate = $startDate->format("Y-m-d\TH:i:s.u\Z");
            $formattedStartDate = preg_replace('/\.?0+Z/', '.000Z', $formattedStartDate);
            
            $endDate = new \DateTime($toDate . 'T23:59:59+10:00');
            $formattedEndDate = $endDate->format("Y-m-d\TH:i:s.u\Z");
            $formattedEndDate = preg_replace('/\.?0+Z/', '.999Z', $formattedEndDate);
            
            $searchConditions[] = '"fromDate":"' . $formattedStartDate . '","toDate":"' . $formattedEndDate . '"';
        }
        
        if (!empty($payId)) {
            $searchConditions[] = '"payID":"' . $payId . '"';
        }
        
        // Default to today if no conditions
        if (empty($searchConditions)) {
            $today = date("Y-m-d");
            $startDate = new \DateTime($today . 'T00:00:00+10:00');
            $formattedStartDate = $startDate->format("Y-m-d\TH:i:s.u\Z");
            $formattedStartDate = preg_replace('/\.?0+Z/', '.000Z', $formattedStartDate);
            
            $endDate = new \DateTime($today . 'T23:59:59+10:00');
            $formattedEndDate = $endDate->format("Y-m-d\TH:i:s.u\Z");
            $formattedEndDate = preg_replace('/\.?0+Z/', '.999Z', $formattedEndDate);
            
            $searchConditions[] = '"fromDate":"' . $formattedStartDate . '","toDate":"' . $formattedEndDate . '"';
        }
        
        $searchBody = '{"PaymentRequestSearch":{' . implode(',', $searchConditions) . '}}';
        
        try {
            $response = $this->client->request('POST', $this->accessUrl . '/paymentRequest/search', [
                'body' => $searchBody,
                'headers' => [
                    'Authorization' => $this->authorizationCode,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            // Process records and insert status checkup for COMPLETE payments
            if (isset($data['records']) && is_array($data['records'])) {
                $currentTime = date('Y-m-d H:i:s');
                foreach ($data['records'] as $record) {
                    if (isset($record['PaymentRequestStatus']['status']) && 
                        $record['PaymentRequestStatus']['status'] === 'COMPLETE' &&
                        isset($record['PaymentRequest']['clientTransactionId'])) {
                        
                        $clientTransactionIdLoop = $record['PaymentRequest']['clientTransactionId'];
                        $this->azupayDAL->insertBookingUpdateHistory(
                            $clientTransactionIdLoop,
                            'azupay_status',
                            'COMPLETE',
                            $currentTime,
                            'azu_callback'
                        );
                    }
                }
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Azupay search error: " . $e->getMessage());
            throw new Exception("Failed to search payment requests: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create payment request
     */
    public function createPaymentRequest($payId, $payIdSuffix, $clientId, $clientTransactionId, $paymentAmount, $paymentDescription)
    {
        if (empty($payId) || empty($payIdSuffix) || empty($clientId) || 
            empty($clientTransactionId) || empty($paymentAmount) || empty($paymentDescription)) {
            throw new Exception("All payment request fields are required", 400);
        }
        
        if (!is_numeric($paymentAmount) || $paymentAmount <= 0) {
            throw new Exception("Payment amount must be a positive number", 400);
        }
        
        $requestBody = '{"PaymentRequest":{"payID":"' . $payId . 
                      '","payIDSuffix":"' . $payIdSuffix . 
                      '","clientId":"' . $clientId . 
                      '","clientTransactionId":"' . $clientTransactionId . 
                      '","paymentAmount":' . $paymentAmount . 
                      ',"paymentDescription":"' . $paymentDescription . '"}}';
        
        try {
            $response = $this->client->request('POST', $this->accessUrl . '/paymentRequest', [
                'body' => $requestBody,
                'headers' => [
                    'Authorization' => $this->authorizationCode,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            error_log("Azupay create payment error: " . $e->getMessage());
            throw new Exception("Failed to create payment request: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment request status
     */
    public function getPaymentRequestStatus($paymentRequestId)
    {
        if (empty($paymentRequestId)) {
            throw new Exception("Payment request ID is required", 400);
        }
        
        try {
            $response = $this->client->request('GET', $this->accessUrl . '/paymentRequest?id=' . urlencode($paymentRequestId), [
                'headers' => [
                    'Authorization' => $this->authorizationCode,
                    'accept' => 'application/json',
                ],
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            error_log("Azupay get status error: " . $e->getMessage());
            throw new Exception("Failed to get payment request status: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check balance
     */
    public function checkBalance()
    {
        try {
            $response = $this->client->request('GET', $this->accessUrl . '/balance', [
                'headers' => [
                    'Authorization' => $this->authorizationCode,
                    'accept' => 'application/json',
                ],
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            error_log("Azupay check balance error: " . $e->getMessage());
            throw new Exception("Failed to check balance: " . $e->getMessage(), 500);
        }
    }
}

