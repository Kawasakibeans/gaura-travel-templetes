<?php
/**
 * Outbound Payment DAL
 * Data Access Layer for outbound payment operations (Azupay)
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class OutboundPaymentDAL extends BaseDAL
{
    /**
     * Check IP address access
     */
    public function checkIpAddress(string $ipAddress): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address = ? LIMIT 1";
        $result = $this->queryOne($sql, [$ipAddress]);
        
        // queryOne() returns false when no rows found, or array when found
        return ($result === false) ? null : $result;
    }

    /**
     * Get outbound payments with filters
     */
    public function getOutboundPayments(
        ?string $paymentDate = null,
        ?string $orderId = null,
        ?string $status = null,
        ?string $authorizedBy = null,
        int $limit = 30,
        int $offset = 0
    ): array {
        $whereParts = [];
        $params = [];

        if ($paymentDate) {
            $whereParts[] = "DATE(added_on) = ?";
            $params[] = $paymentDate;
        }

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($status) {
            $whereParts[] = "request_base_status = ?";
            $params[] = $status;
        }

        if ($authorizedBy) {
            $whereParts[] = "second_confirmed_by = ?";
            $params[] = $authorizedBy;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : 'WHERE order_id IS NOT NULL';

        $sql = "
            SELECT * 
            FROM wpk4_backend_travel_payment_outbound_azupay 
            {$whereSQL}
            ORDER BY added_on DESC 
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        return $this->query($sql, $params);
    }

    /**
     * Get outbound payment by auto_id
     */
    public function getOutboundPaymentById(int $autoId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_travel_payment_outbound_azupay WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($sql, [$autoId]);
    }

    /**
     * Create outbound payment request
     */
    public function createOutboundPayment(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_payment_outbound_azupay 
            (order_id, client_payment_id, payee_name, payment_description, customer_reference, 
             payment_amount, payment_option_type, pay_id_type, payid, bsb, account, added_by, request_base_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['client_payment_id'],
            $data['payee_name'],
            $data['payment_description'],
            $data['customer_reference'] ?? null,
            $data['payment_amount'],
            $data['payment_option_type'],
            $data['pay_id_type'] ?? null,
            $data['payid'] ?? null,
            $data['bsb'] ?? null,
            $data['account'] ?? null,
            $data['added_by'],
            $data['request_base_status'] ?? 'pending'
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    /**
     * Update outbound payment status (initial approval)
     */
    public function updateInitialApproval(int $autoId, string $confirmedBy, string $confirmedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_payment_outbound_azupay
            SET second_confirmed_by = ?, request_base_status = 'firstapproved', second_confirmed_on = ?
            WHERE auto_id = ?
        ";

        return $this->execute($sql, [$confirmedBy, $confirmedOn, $autoId]);
    }

    /**
     * Update outbound payment status (final approval/processed)
     */
    public function updateFinalApproval(int $autoId, array $azupayData, string $confirmedBy, string $confirmedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_payment_outbound_azupay
            SET azupay_status = ?, azupay_payment_id = ?, azupay_created_time = ?, 
                azupay_npp_id = ?, azupay_current_balance = ?, 
                second_confirmed_by = ?, request_base_status = 'processed', second_confirmed_on = ?
            WHERE auto_id = ?
        ";

        $params = [
            $azupayData['status'],
            $azupayData['paymentId'],
            $azupayData['createdDatetime'],
            $azupayData['nppTransactionId'],
            $azupayData['currentBalance'],
            $confirmedBy,
            $confirmedOn,
            $autoId
        ];

        return $this->execute($sql, $params);
    }

    /**
     * Update outbound payment status (failed)
     */
    public function updateFailedStatus(int $autoId, string $errorResponse, string $confirmedBy, string $confirmedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_payment_outbound_azupay
            SET error_response = ?, second_confirmed_by = ?, request_base_status = 'failed', second_confirmed_on = ?
            WHERE auto_id = ?
        ";

        return $this->execute($sql, [$errorResponse, $confirmedBy, $confirmedOn, $autoId]);
    }

    /**
     * Update outbound payment status (declined)
     */
    public function updateDeclinedStatus(int $autoId, string $confirmedBy, string $confirmedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_travel_payment_outbound_azupay
            SET second_confirmed_by = ?, request_base_status = 'declined', second_confirmed_on = ?
            WHERE auto_id = ?
        ";

        return $this->execute($sql, [$confirmedBy, $confirmedOn, $autoId]);
    }

    /**
     * Insert payment history record (for refunds)
     */
    public function insertPaymentHistory(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_travel_payment_history 
            (order_id, reference_no, trams_remarks, payment_method, trams_received_amount, 
             process_date, added_on, added_by, payment_request_id, pay_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $data['order_id'],
            $data['reference_no'],
            $data['trams_remarks'],
            $data['payment_method'],
            $data['trams_received_amount'],
            $data['process_date'],
            $data['added_on'],
            $data['added_by'],
            $data['payment_request_id'] ?? null,
            $data['pay_type']
        ];

        $this->execute($sql, $params);
        return $this->lastInsertId();
    }
}

