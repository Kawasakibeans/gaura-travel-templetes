<?php
/**
 * Name Update Service - Business Logic Layer
 * Handles Amadeus name update processing for airline bookings
 */

namespace App\Services;

use App\DAL\NameUpdateDAL;
use Exception;

class NameUpdateService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NameUpdateDAL();
    }

    /**
     * Get passengers requiring name update
     */
    public function getPendingNameUpdates($orderId = null)
    {
        $passengers = $this->dal->getPendingNameUpdates($orderId);

        $processed = [];
        foreach ($passengers as $pax) {
            $orderId = $pax['order_id'];
            $tripCode = $pax['trip_code'];
            $travelDate = $pax['travel_date'];

            // Get stock/PNR info
            $stockInfo = $this->dal->getStockInfo($tripCode, $travelDate);

            if ($stockInfo) {
                $airline = $stockInfo['airline_code'];
                
                // Only process SQ and MH airlines
                if ($airline === 'SQ' || $airline === 'MH') {
                    $fname = $pax['fname'];
                    $lname = $pax['lname'];

                    // Apply airline-specific name rules
                    if ($airline === 'MH' && $fname === $lname) {
                        $fname = 'FNU';
                    }

                    if ($airline === 'SQ' && strtolower($fname) === 'fnu') {
                        $fname = $lname;
                    }

                    // Check if already processed
                    $alreadyLogged = $this->dal->checkNameUpdateLog(
                        $stockInfo['pnr'], $orderId, $fname, $lname, $pax['dob']
                    );

                    if (!$alreadyLogged) {
                        $processed[] = [
                            'pax_id' => $pax['paxauto_id'],
                            'order_id' => $orderId,
                            'pnr' => $stockInfo['pnr'],
                            'trip_code' => $tripCode,
                            'travel_date' => $travelDate,
                            'airline' => $airline,
                            'office_id' => $stockInfo['OID'],
                            'salutation' => $pax['salutation'],
                            'fname' => $fname,
                            'lname' => $lname,
                            'dob' => $pax['dob']
                        ];
                    }
                }
            }
        }

        return [
            'passengers' => $processed,
            'total_count' => count($processed)
        ];
    }

    /**
     * Log name update
     */
    public function logNameUpdate($data)
    {
        // Validate required fields
        $requiredFields = ['pnr', 'order_id', 'fname', 'lname', 'dob'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $logId = $this->dal->createNameUpdateLog($data);

        return [
            'log_id' => $logId,
            'pnr' => $data['pnr'],
            'order_id' => $data['order_id'],
            'message' => 'Name update logged successfully'
        ];
    }

    /**
     * Get name update history
     */
    public function getNameUpdateHistory($orderId = null, $pnr = null)
    {
        $history = $this->dal->getNameUpdateHistory($orderId, $pnr);

        return [
            'history' => $history,
            'total_count' => count($history)
        ];
    }

    /**
     * Update passenger name update status
     */
    public function updatePaxNameUpdateStatus($paxId, $status = 'Name Updated', $checkOn = null, $check = 'Amadeus Name Update')
    {
        if (empty($paxId)) {
            throw new Exception('pax_id is required', 400);
        }

        $success = $this->dal->updatePaxNameUpdateStatus($paxId, $status, $checkOn, $check);

        return [
            'success' => $success !== false,
            'pax_id' => $paxId,
            'message' => $success ? 'Passenger status updated successfully' : 'Failed to update passenger status'
        ];
    }

    /**
     * Get passenger meal and wheelchair preferences
     */
    public function getPaxMealWheelchair($paxId)
    {
        if (empty($paxId)) {
            throw new Exception('pax_id is required', 400);
        }

        $result = $this->dal->getPaxMealWheelchair($paxId);

        return $result ?: [
            'meal' => null,
            'wheelchair' => null
        ];
    }

    /**
     * Get order date by order ID
     */
    public function getOrderDate($orderId)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $orderDate = $this->dal->getOrderDate($orderId);

        return [
            'order_id' => $orderId,
            'order_date' => $orderDate
        ];
    }

    /**
     * Get seat availability by trip code and travel date
     */
    public function getSeatAvailability($tripCode, $travelDate)
    {
        if (empty($tripCode) || empty($travelDate)) {
            throw new Exception('trip_code and travel_date are required', 400);
        }

        $result = $this->dal->getSeatAvailability($tripCode, $travelDate);

        return $result ?: [
            'pax' => 0,
            'stock' => 0
        ];
    }

    /**
     * Create SSR update log entry
     */
    public function createSSRUpdateLog($data)
    {
        $required = ['pnr', 'order_id', 'pax_id', 'ssr_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required", 400);
            }
        }

        $logId = $this->dal->createSSRUpdateLog($data);

        return [
            'success' => true,
            'log_id' => $logId,
            'message' => 'SSR update log created successfully'
        ];
    }

    /**
     * Create name update log with full details
     */
    public function createNameUpdateLogFull($data)
    {
        $required = ['pnr', 'order_id', 'fname', 'lname'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required", 400);
            }
        }

        $logId = $this->dal->createNameUpdateLogFull($data);

        return [
            'success' => true,
            'log_id' => $logId,
            'message' => 'Name update log created successfully'
        ];
    }

    /**
     * Get passengers for name update by order ID
     */
    public function getPassengersForNameUpdate($orderId, $includeInfants = false, $requirePaid = false)
    {
        if (empty($orderId)) {
            throw new Exception('order_id is required', 400);
        }

        $passengers = $this->dal->getPassengersForNameUpdate($orderId, $includeInfants, $requirePaid);

        return [
            'passengers' => $passengers,
            'total_count' => count($passengers)
        ];
    }

    /**
     * Get adult order passengers (for infant linking)
     */
    public function getAdultOrderPassengers($adultOrderId)
    {
        if (empty($adultOrderId)) {
            throw new Exception('adult_order_id is required', 400);
        }

        $passengers = $this->dal->getAdultOrderPassengers($adultOrderId);

        return [
            'passengers' => $passengers,
            'total_count' => count($passengers)
        ];
    }
}

