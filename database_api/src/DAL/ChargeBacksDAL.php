<?php
/**
 * Charge Backs Data Access Layer
 * Handles all database operations for charge back payments
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class ChargeBacksDAL extends BaseDAL
{
    /**
     * Get all chargebacks with filters
     */
    public function getAllChargebacks($orderId = null, $chargeBackNumber = null, $chargeBackDate = null, 
                                      $respondedDateToCba = null, $status = null, $bankDebitDate = null, 
                                      $limit = 100, $offset = 0)
    {
        $whereParts = [];
        $params = [];

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($chargeBackNumber) {
            $whereParts[] = "charge_back_number = ?";
            $params[] = $chargeBackNumber;
        }

        if ($chargeBackDate) {
            $whereParts[] = "charge_back_date = ?";
            $params[] = $chargeBackDate;
        }

        if ($respondedDateToCba) {
            $whereParts[] = "responded_date_to_cba = ?";
            $params[] = $respondedDateToCba;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        if ($bankDebitDate) {
            $whereParts[] = "bank_debit_date = ?";
            $params[] = $bankDebitDate;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_backend_travel_charge_back_payments 
                  $whereSQL 
                  ORDER BY auto_id ASC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get chargebacks count with filters
     */
    public function getChargebacksCount($orderId = null, $chargeBackNumber = null, $chargeBackDate = null, 
                                        $respondedDateToCba = null, $status = null, $bankDebitDate = null)
    {
        $whereParts = [];
        $params = [];

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($chargeBackNumber) {
            $whereParts[] = "charge_back_number = ?";
            $params[] = $chargeBackNumber;
        }

        if ($chargeBackDate) {
            $whereParts[] = "charge_back_date = ?";
            $params[] = $chargeBackDate;
        }

        if ($respondedDateToCba) {
            $whereParts[] = "responded_date_to_cba = ?";
            $params[] = $respondedDateToCba;
        }

        if ($status) {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        if ($bankDebitDate) {
            $whereParts[] = "bank_debit_date = ?";
            $params[] = $bankDebitDate;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_backend_travel_charge_back_payments $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get chargeback by ID
     */
    public function getChargebackById($id)
    {
        $query = "SELECT * FROM wpk4_backend_travel_charge_back_payments WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get booking details for order
     */
    public function getBookingDetails($orderId)
    {
        $query = "SELECT bookings.order_id, bookings.order_date, bookings.travel_date, 
                         bookings.trip_code, bookings.order_type  
                  FROM wpk4_backend_travel_bookings bookings 
                  JOIN wpk4_backend_travel_booking_pax pax 
                    ON bookings.order_id = pax.order_id 
                    AND bookings.product_id = pax.product_id
                  WHERE pax.order_id = ? OR pax.pnr = ?
                  LIMIT 1";
        
        return $this->queryOne($query, [$orderId, $orderId]);
    }

    /**
     * Get payment details for order
     */
    public function getPaymentDetails($orderId)
    {
        $query = "SELECT ph.process_date, ph.payment_method, ba.account_name
                  FROM wpk4_backend_travel_payment_history ph
                  LEFT JOIN wpk4_backend_accounts_bank_account ba 
                    ON ph.payment_method = ba.bank_id
                  WHERE ph.order_id = ?";
        
        return $this->query($query, [$orderId]);
    }

    /**
     * Create new chargeback
     */
    public function createChargeback($data)
    {
        $query = "INSERT INTO wpk4_backend_travel_charge_back_payments 
                  (charge_back_number, charge_back_date, amount, order_id, 
                   reason_for_charge_back, responded_date_to_cba, documents_submitted_to_cba, 
                   added_by, added_on, status, bank_debit_date, 
                   file1, file2, file3, file4, file5, file6)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['charge_back_number'],
            $data['charge_back_date'],
            $data['amount'],
            $data['order_id'],
            $data['reason_for_charge_back'] ?? null,
            $data['responded_date_to_cba'] ?? null,
            $data['documents_submitted_to_cba'] ?? null,
            $data['added_by'] ?? 'system',
            $data['status'] ?? null,
            $data['bank_debit_date'] ?? null,
            $data['file1'] ?? null,
            $data['file2'] ?? null,
            $data['file3'] ?? null,
            $data['file4'] ?? null,
            $data['file5'] ?? null,
            $data['file6'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update chargeback
     */
    public function updateChargeback($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = [
            'charge_back_number', 'charge_back_date', 'amount', 'order_id',
            'reason_for_charge_back', 'responded_date_to_cba', 'documents_submitted_to_cba',
            'status', 'bank_debit_date',
            'file1', 'file2', 'file3', 'file4', 'file5', 'file6'
        ];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        
        $query = "UPDATE wpk4_backend_travel_charge_back_payments SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete chargeback
     */
    public function deleteChargeback($id)
    {
        $query = "DELETE FROM wpk4_backend_travel_charge_back_payments WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }

    /**
     * Get distinct statuses
     */
    public function getDistinctStatuses()
    {
        $query = "SELECT DISTINCT status 
                  FROM wpk4_backend_travel_charge_back_payments 
                  WHERE status IS NOT NULL AND status != ''
                  ORDER BY status";
        $results = $this->query($query);
        return array_column($results, 'status');
    }

    /**
     * Get distinct reasons
     */
    public function getDistinctReasons()
    {
        $query = "SELECT DISTINCT reason_for_charge_back 
                  FROM wpk4_backend_travel_charge_back_payments 
                  WHERE reason_for_charge_back IS NOT NULL AND reason_for_charge_back != ''
                  ORDER BY reason_for_charge_back";
        $results = $this->query($query);
        return array_column($results, 'reason_for_charge_back');
    }
}

