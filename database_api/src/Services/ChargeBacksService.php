<?php
/**
 * Charge Backs Service - Business Logic Layer
 * Handles charge back payment tracking and management
 */

namespace App\Services;

use App\DAL\ChargeBacksDAL;
use Exception;

class ChargeBacksService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ChargeBacksDAL();
    }

    /**
     * Get all charge backs with filters
     */
    public function getAllChargebacks($filters)
    {
        $orderId = $filters['order_id'] ?? null;
        $chargeBackNumber = $filters['charge_back_number'] ?? null;
        $chargeBackDate = $filters['charge_back_date'] ?? null;
        $respondedDateToCba = $filters['responded_date_to_cba'] ?? null;
        $status = $filters['status'] ?? null;
        $bankDebitDate = $filters['bank_debit_date'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $chargebacks = $this->dal->getAllChargebacks(
            $orderId, 
            $chargeBackNumber, 
            $chargeBackDate, 
            $respondedDateToCba, 
            $status, 
            $bankDebitDate, 
            $limit, 
            $offset
        );

        // Enrich each chargeback with booking and payment details
        foreach ($chargebacks as &$chargeback) {
            $orderId = $chargeback['order_id'];
            
            // Get booking details
            $bookingDetails = $this->dal->getBookingDetails($orderId);
            if ($bookingDetails) {
                $chargeback['booking'] = [
                    'order_date' => $bookingDetails['order_date'],
                    'travel_date' => $bookingDetails['travel_date'],
                    'trip_code' => $bookingDetails['trip_code'],
                    'order_type' => $this->normalizeOrderType($bookingDetails['order_type'], $orderId),
                    'is_available_in_system' => true
                ];
            } else {
                $chargeback['booking'] = [
                    'is_available_in_system' => false
                ];
            }

            // Get payment details (only for numeric order IDs less than 10 digits)
            if (strlen($orderId) < 10 && ctype_digit($orderId)) {
                $payments = $this->dal->getPaymentDetails($orderId);
                $chargeback['payments'] = $this->formatPaymentDetails($payments);
            } else {
                $chargeback['payments'] = [];
            }

            // Format file URLs
            $chargeback['files'] = $this->formatFileUrls($chargeback);
        }

        $totalCount = $this->dal->getChargebacksCount(
            $orderId, 
            $chargeBackNumber, 
            $chargeBackDate, 
            $respondedDateToCba, 
            $status, 
            $bankDebitDate
        );

        return [
            'chargebacks' => $chargebacks,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get chargeback by ID
     */
    public function getChargebackById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid chargeback ID is required', 400);
        }

        $chargeback = $this->dal->getChargebackById($id);

        if (!$chargeback) {
            throw new Exception('Chargeback not found', 404);
        }

        // Enrich with booking and payment details
        $orderId = $chargeback['order_id'];
        $bookingDetails = $this->dal->getBookingDetails($orderId);
        
        if ($bookingDetails) {
            $chargeback['booking'] = $bookingDetails;
        }

        if (strlen($orderId) < 10 && ctype_digit($orderId)) {
            $payments = $this->dal->getPaymentDetails($orderId);
            $chargeback['payments'] = $this->formatPaymentDetails($payments);
        }

        $chargeback['files'] = $this->formatFileUrls($chargeback);

        return $chargeback;
    }

    /**
     * Create new chargeback
     */
    public function createChargeback($data)
    {
        // Validate required fields
        $requiredFields = ['charge_back_number', 'charge_back_date', 'amount', 'order_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $chargebackId = $this->dal->createChargeback($data);

        return [
            'chargeback_id' => $chargebackId,
            'charge_back_number' => $data['charge_back_number'],
            'order_id' => $data['order_id'],
            'message' => 'Chargeback created successfully'
        ];
    }

    /**
     * Update chargeback
     */
    public function updateChargeback($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid chargeback ID is required', 400);
        }

        // Check if chargeback exists
        $existing = $this->dal->getChargebackById($id);
        if (!$existing) {
            throw new Exception('Chargeback not found', 404);
        }

        $this->dal->updateChargeback($id, $data);

        return [
            'chargeback_id' => $id,
            'message' => 'Chargeback updated successfully'
        ];
    }

    /**
     * Delete chargeback
     */
    public function deleteChargeback($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid chargeback ID is required', 400);
        }

        $chargeback = $this->dal->getChargebackById($id);
        if (!$chargeback) {
            throw new Exception('Chargeback not found', 404);
        }

        $this->dal->deleteChargeback($id);

        return [
            'chargeback_id' => $id,
            'charge_back_number' => $chargeback['charge_back_number'],
            'message' => 'Chargeback deleted successfully'
        ];
    }

    /**
     * Private helper methods
     */
    
    private function normalizeOrderType($orderType, $orderId)
    {
        if ($orderType == 'gds') {
            return 'FIT';
        } else if ($orderType == 'WPT') {
            return 'Gdeals';
        } else {
            // Determine by order ID format
            if (ctype_digit($orderId) && strlen($orderId) === 6) {
                return 'Gdeals';
            } else {
                return 'FIT';
            }
        }
    }

    private function formatPaymentDetails($payments)
    {
        $formatted = [];
        
        foreach ($payments as $payment) {
            $formatted[] = [
                'process_date' => $payment['process_date'],
                'payment_method' => $payment['payment_method'],
                'payment_account' => $payment['account_name'] ?? null
            ];
        }

        return $formatted;
    }

    private function formatFileUrls($chargeback)
    {
        $files = [];
        $baseUrl = '/wp-content/uploads/customized_function_uploads/';

        for ($i = 1; $i <= 6; $i++) {
            $fileKey = 'file' . $i;
            if (!empty($chargeback[$fileKey])) {
                $files[] = [
                    'file_number' => $i,
                    'filename' => $chargeback[$fileKey],
                    'url' => $baseUrl . $chargeback[$fileKey]
                ];
            }
        }

        return $files;
    }
}

