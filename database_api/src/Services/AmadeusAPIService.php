<?php
/**
 * Amadeus API Service - Business Logic Layer
 * Handles Amadeus API database operations
 */

namespace App\Services;

use App\DAL\AmadeusAPIDAL;
use Exception;

class AmadeusAPIService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AmadeusAPIDAL();
    }

    /**
     * Check if passenger exists in name update log
     */
    public function checkPassengerExists(array $params): array
    {
        $pnr = $params['pnr'] ?? '';
        $orderId = $params['order_id'] ?? '';
        $firstname = $params['firstname'] ?? '';
        $surname = $params['surname'] ?? '';
        $title = $params['title'] ?? '';
        $status = $params['status'] ?? 'SUCCESS';

        if (empty($pnr) || empty($orderId) || empty($firstname) || empty($surname) || empty($title)) {
            throw new Exception('PNR, order_id, firstname, surname, and title are required', 400);
        }

        $exists = $this->dal->passengerExistsInNameUpdateLog($pnr, $orderId, $firstname, $surname, $title, $status);

        return [
            'exists' => $exists,
            'pnr' => $pnr,
            'order_id' => $orderId
        ];
    }

    /**
     * Get stock management data by PNR
     */
    public function getStockManagementByPnr(string $pnr): ?array
    {
        if (empty($pnr)) {
            throw new Exception('PNR is required', 400);
        }

        return $this->dal->getStockManagementByPnr($pnr);
    }

    /**
     * Get order date by order ID
     */
    public function getOrderDateByOrderId(string $orderId): ?string
    {
        if (empty($orderId)) {
            throw new Exception('Order ID is required', 400);
        }

        return $this->dal->getOrderDateByOrderId($orderId);
    }

    /**
     * Get infant order ID by adult order ID
     */
    public function getInfantOrderIdByAdultOrder(string $adultOrderId): ?string
    {
        if (empty($adultOrderId)) {
            throw new Exception('Adult order ID is required', 400);
        }

        return $this->dal->getInfantOrderIdByAdultOrder($adultOrderId);
    }

    /**
     * Get passenger meal and wheelchair
     */
    public function getPassengerMealAndWheelchair(int $paxId): ?array
    {
        if ($paxId <= 0) {
            throw new Exception('Valid pax_id is required', 400);
        }

        return $this->dal->getPassengerMealAndWheelchair($paxId);
    }

    /**
     * Update passenger status
     */
    public function updatePassengerStatus(array $params): array
    {
        $paxId = $params['pax_id'] ?? 0;
        $status = $params['status'] ?? 'Name Updated';
        $nameUpdateCheck = $params['name_update_check'] ?? 'Amadeus Name Update';
        $nameUpdateCheckOn = $params['name_update_check_on'] ?? date('Y-m-d H:i:s');

        if ($paxId <= 0) {
            throw new Exception('Valid pax_id is required', 400);
        }

        $success = $this->dal->updatePassengerStatus($paxId, $status, $nameUpdateCheck, $nameUpdateCheckOn);

        if (!$success) {
            throw new Exception('Failed to update passenger status', 500);
        }

        return [
            'pax_id' => $paxId,
            'status' => $status,
            'updated' => true
        ];
    }

    /**
     * Insert name update log
     */
    public function insertNameUpdateLog(array $data): array
    {
        if (empty($data['pnr']) || empty($data['order_id'])) {
            throw new Exception('PNR and order_id are required', 400);
        }

        // Set default values
        $data['updated_on'] = $data['updated_on'] ?? date('Y-m-d H:i:s');
        $data['method_flow'] = $data['method_flow'] ?? null;

        $success = $this->dal->insertNameUpdateLog($data);

        if (!$success) {
            throw new Exception('Failed to insert name update log', 500);
        }

        return [
            'success' => true,
            'pnr' => $data['pnr'],
            'order_id' => $data['order_id']
        ];
    }

    /**
     * Update name update log
     */
    public function updateNameUpdateLog(int $logId, array $data): array
    {
        if ($logId <= 0) {
            throw new Exception('Valid log_id is required', 400);
        }

        $data['updated_on'] = $data['updated_on'] ?? date('Y-m-d H:i:s');

        $success = $this->dal->updateNameUpdateLog($logId, $data);

        if (!$success) {
            throw new Exception('Failed to update name update log', 500);
        }

        return [
            'log_id' => $logId,
            'updated' => true
        ];
    }

    /**
     * Insert name update history log
     */
    public function insertNameUpdateHistoryLog(array $params): array
    {
        $orderId = $params['order_id'] ?? '';
        $pnr = $params['pnr'] ?? '';
        $officeId = $params['office_id'] ?? '';
        $paxId = $params['pax_id'] ?? 0;
        $addedBy = $params['added_by'] ?? '';

        if (empty($orderId) || empty($pnr) || empty($officeId) || $paxId <= 0) {
            throw new Exception('order_id, pnr, office_id, and valid pax_id are required', 400);
        }

        $success = $this->dal->insertNameUpdateHistoryLog($orderId, $pnr, $officeId, $paxId, $addedBy);

        if (!$success) {
            throw new Exception('Failed to insert name update history log', 500);
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'pnr' => $pnr
        ];
    }

    /**
     * Insert SSR update log
     */
    public function insertSSRUpdateLog(array $data): array
    {
        if (empty($data['order_id']) || empty($data['request_type'])) {
            throw new Exception('order_id and request_type are required', 400);
        }

        $data['added_on'] = $data['added_on'] ?? date('Y-m-d H:i:s');
        $data['gds'] = $data['gds'] ?? 'Amadeus';

        // Use appropriate method based on available fields
        if (isset($data['amadeus_pax_id']) || isset($data['pax_fullname']) || isset($data['pax_auto_id'])) {
            $success = $this->dal->insertSSRUpdateLog($data);
        } else {
            $success = $this->dal->insertSSRUpdateLogSimple($data);
        }

        if (!$success) {
            throw new Exception('Failed to insert SSR update log', 500);
        }

        return [
            'success' => true,
            'order_id' => $data['order_id'],
            'request_type' => $data['request_type']
        ];
    }
}

