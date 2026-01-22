<?php
/**
 * Amadeus API Data Access Layer
 * Handles all database operations for Amadeus API logging and queries
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AmadeusAPIDAL extends BaseDAL
{
    /**
     * Check if passenger already exists in name update log
     */
    public function passengerExistsInNameUpdateLog(string $pnr, string $orderId, string $firstname, string $surname, string $title, string $status = 'SUCCESS'): bool
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM wpk4_amadeus_name_update_log 
            WHERE pnr = :pnr 
            AND order_id = :order_id 
            AND fname = :fname 
            AND lname = :surname 
            AND salutation = :title 
            AND status = :status
        ";
        
        $result = $this->queryOne($sql, [
            ':pnr' => $pnr,
            ':order_id' => $orderId,
            ':fname' => $firstname,
            ':surname' => $surname,
            ':title' => $title,
            ':status' => $status
        ]);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get stock management data by PNR
     */
    public function getStockManagementByPnr(string $pnr): ?array
    {
        $sql = "
            SELECT pnr, OID, airline_code, dep_date 
            FROM wpk4_backend_stock_management_sheet 
            WHERE pnr = :pnr
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':pnr' => $pnr]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get order date by order ID
     */
    public function getOrderDateByOrderId(string $orderId): ?string
    {
        $sql = "
            SELECT order_date 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':order_id' => $orderId]);
        return ($result === false) ? null : ($result['order_date'] ?? null);
    }

    /**
     * Get infant order ID by adult order ID
     */
    public function getInfantOrderIdByAdultOrder(string $adultOrderId): ?string
    {
        $sql = "
            SELECT order_id 
            FROM wpk4_backend_travel_bookings 
            WHERE adult_order = :adult_order
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':adult_order' => $adultOrderId]);
        return ($result === false) ? null : ($result['order_id'] ?? null);
    }

    /**
     * Get passenger meal and wheelchair by pax ID
     */
    public function getPassengerMealAndWheelchair(int $paxId): ?array
    {
        $sql = "
            SELECT meal, wheelchair 
            FROM wpk4_backend_travel_booking_pax 
            WHERE auto_id = :pax_id
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':pax_id' => $paxId]);
        return ($result === false) ? null : $result;
    }

    /**
     * Update passenger status
     */
    public function updatePassengerStatus(int $paxId, string $status, string $nameUpdateCheck, string $nameUpdateCheckOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_booking_pax 
            SET pax_status = :pax_status, 
                name_update_check = :name_update_check, 
                name_update_check_on = :name_update_check_on
            WHERE auto_id = :pax_id
        ";
        
        return $this->execute($sql, [
            ':pax_id' => $paxId,
            ':pax_status' => $status,
            ':name_update_check' => $nameUpdateCheck,
            ':name_update_check_on' => $nameUpdateCheckOn
        ]);
    }

    /**
     * Insert name update log
     */
    public function insertNameUpdateLog(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_amadeus_name_update_log 
            (pnr, office_id, order_id, fname, lname, salutation, dob, status, session, token, request, response, added_by, airline, order_date, travel_date, pax_type, infant_booking_id, updated_on, pax_id, method_flow)
            VALUES 
            (:pnr, :office_id, :order_id, :fname, :lname, :salutation, :dob, :status, :session, :token, :request, :response, :added_by, :airline, :order_date, :travel_date, :pax_type, :infant_booking_id, :updated_on, :pax_id, :method_flow)
        ";
        
        return $this->execute($sql, [
            ':pnr' => $data['pnr'] ?? null,
            ':office_id' => $data['office_id'] ?? null,
            ':order_id' => $data['order_id'] ?? null,
            ':fname' => $data['fname'] ?? null,
            ':lname' => $data['lname'] ?? null,
            ':salutation' => $data['salutation'] ?? null,
            ':dob' => $data['dob'] ?? null,
            ':status' => $data['status'] ?? null,
            ':session' => $data['session'] ?? null,
            ':token' => $data['token'] ?? null,
            ':request' => $data['request'] ?? null,
            ':response' => $data['response'] ?? null,
            ':added_by' => $data['added_by'] ?? null,
            ':airline' => $data['airline'] ?? null,
            ':order_date' => $data['order_date'] ?? null,
            ':travel_date' => $data['travel_date'] ?? null,
            ':pax_type' => $data['pax_type'] ?? null,
            ':infant_booking_id' => $data['infant_booking_id'] ?? null,
            ':updated_on' => $data['updated_on'] ?? null,
            ':pax_id' => $data['pax_id'] ?? null,
            ':method_flow' => $data['method_flow'] ?? null
        ]);
    }

    /**
     * Update name update log
     */
    public function updateNameUpdateLog(int $logId, array $data): bool
    {
        $sql = "
            UPDATE wpk4_amadeus_name_update_log 
            SET status = :status,
                session = :session,
                token = :token,
                request = :request,
                response = :response,
                updated_on = :updated_on
            WHERE auto_id = :log_id AND status IS NULL
        ";
        
        return $this->execute($sql, [
            ':log_id' => $logId,
            ':status' => $data['status'] ?? null,
            ':session' => $data['session'] ?? null,
            ':token' => $data['token'] ?? null,
            ':request' => $data['request'] ?? null,
            ':response' => $data['response'] ?? null,
            ':updated_on' => $data['updated_on'] ?? null
        ]);
    }

    /**
     * Insert name update history log
     */
    public function insertNameUpdateHistoryLog(string $orderId, string $pnr, string $officeId, int $paxId, string $addedBy): bool
    {
        $sql = "
            INSERT INTO wpk4_amadeus_name_update_history_log 
            (order_id, pnr, office_id, pax_id, added_by)
            VALUES 
            (:order_id, :pnr, :office_id, :pax_id, :added_by)
        ";
        
        return $this->execute($sql, [
            ':order_id' => $orderId,
            ':pnr' => $pnr,
            ':office_id' => $officeId,
            ':pax_id' => $paxId,
            ':added_by' => $addedBy
        ]);
    }

    /**
     * Insert SSR update log
     */
    public function insertSSRUpdateLog(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_ssr_updates 
            (order_id, amadeus_pax_id, pax_fullname, req_value, request_type, request_made, added_by, added_on, status, gds, pax_auto_id)
            VALUES 
            (:order_id, :amadeus_pax_id, :pax_fullname, :req_value, :request_type, :request_made, :added_by, :added_on, :status, :gds, :pax_auto_id)
        ";
        
        return $this->execute($sql, [
            ':order_id' => $data['order_id'] ?? null,
            ':amadeus_pax_id' => $data['amadeus_pax_id'] ?? null,
            ':pax_fullname' => $data['pax_fullname'] ?? null,
            ':req_value' => $data['req_value'] ?? null,
            ':request_type' => $data['request_type'] ?? null,
            ':request_made' => $data['request_made'] ?? null,
            ':added_by' => $data['added_by'] ?? null,
            ':added_on' => $data['added_on'] ?? null,
            ':status' => $data['status'] ?? null,
            ':gds' => $data['gds'] ?? 'Amadeus',
            ':pax_auto_id' => $data['pax_auto_id'] ?? null
        ]);
    }

    /**
     * Insert SSR update log (simplified version without pax_auto_id)
     */
    public function insertSSRUpdateLogSimple(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_booking_ssr_updates 
            (order_id, request_type, request_made, added_by, added_on, status, gds)
            VALUES 
            (:order_id, :request_type, :request_made, :added_by, :added_on, :status, :gds)
        ";
        
        return $this->execute($sql, [
            ':order_id' => $data['order_id'] ?? null,
            ':request_type' => $data['request_type'] ?? null,
            ':request_made' => $data['request_made'] ?? null,
            ':added_by' => $data['added_by'] ?? null,
            ':added_on' => $data['added_on'] ?? null,
            ':status' => $data['status'] ?? null,
            ':gds' => $data['gds'] ?? 'Amadeus'
        ]);
    }
}

