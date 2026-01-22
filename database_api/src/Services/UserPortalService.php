<?php
/**
 * User Portal Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\UserPortalDAL;
use Exception;

class UserPortalService
{
    private $userPortalDAL;

    public function __construct()
    {
        $this->userPortalDAL = new UserPortalDAL();
    }

    /**
     * Bulk update user portal request meta
     */
    public function bulkUpdateRequestMeta($caseId, $data)
    {
        if (empty($caseId) || $caseId <= 0) {
            throw new Exception('Case ID is required', 400);
        }

        if (empty($data) || !is_array($data)) {
            throw new Exception('Data is required', 400);
        }

        $allowedFields = [
            'passenger_paid' => 'money',
            'cancellation_fee' => 'money',
            'refund_amount' => 'money',
            'refund_received_from_airline' => 'money',
            'diff' => 'money',
            'account_name' => 'text',
            'bsb' => 'text',
            'account_number' => 'text',
            'refund_received_date' => 'date',
            'vendor' => 'text',
            'booking_type' => 'text',
            'remarks' => 'text',
            'submitted_date' => 'date',
        ];

        $normalizePassengerKey = function($name) {
            // Sanitize and normalize passenger key (similar to WordPress sanitize_title)
            $key = strtolower(trim($name));
            $key = preg_replace('/[^a-z0-9-]/', '-', $key);
            $key = preg_replace('/-+/', '-', $key);
            return trim($key, '-');
        };

        foreach ($data as $passengerKey => $fields) {
            if (!is_array($fields)) continue;
            
            $pkey = $normalizePassengerKey((string)$passengerKey);
            if ($pkey === '') continue;

            foreach ($fields as $field => $raw) {
                if (!isset($allowedFields[$field])) continue;

                $type = $allowedFields[$field];
                $val = (string)$raw;

                // Sanitize by type
                if ($type === 'money') {
                    $val = str_replace(',', '.', $val);
                    $val = preg_replace('/[^0-9.\-]/', '', $val);
                    if ($val === '' || $val === '-' || $val === '.') $val = '0';
                    $val = number_format((float)$val, 2, '.', '');
                } elseif ($type === 'date') {
                    $ts = strtotime($val);
                    $val = $ts ? date('Y-m-d', $ts) : '';
                } else {
                    // Basic text sanitization
                    $val = htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8');
                }

                $metaKey = strtolower(trim($field . '--' . $pkey));
                $this->userPortalDAL->upsertRequestMeta($caseId, $metaKey, $val);
            }
        }

        return ['success' => true, 'case_id' => $caseId];
    }

    /**
     * Get payment status by order ID
     */
    public function getPaymentStatus($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        return $this->userPortalDAL->getPaymentStatus($orderId);
    }
}

