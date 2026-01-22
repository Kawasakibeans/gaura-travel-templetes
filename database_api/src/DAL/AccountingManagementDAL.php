<?php
/**
 * Accounting Management Data Access Layer
 * Handles database operations for accounting management
 */

namespace App\DAL;

use Exception;
use PDOException;

class AccountingManagementDAL extends BaseDAL
{
    /**
     * Create bank account
     */
    public function createBankAccount($bankId, $accountName, $branch, $accountNumber)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_accounts_bank_account (bank_id, account_name, account, branch) 
                VALUES (:bank_id, :account_name, :account_number, :branch)
            ";
            
            $this->execute($query, [
                'bank_id' => $bankId,
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'branch' => $branch
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::createBankAccount error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get bank accounts
     */
    public function getBankAccounts()
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_accounts_bank_account
                ORDER BY auto_id DESC
            ";
            
            return $this->query($query);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::getBankAccounts error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment records with filters
     */
    public function getPaymentRecords($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['payment_date'])) {
                $where[] = "DATE(payment_date) = :payment_date";
                $params['payment_date'] = $filters['payment_date'];
            }
            
            if (!empty($filters['order_date'])) {
                $where[] = "DATE(order_date) = :order_date";
                $params['order_date'] = $filters['order_date'];
            }
            
            if (!empty($filters['reference_no'])) {
                $where[] = "reference_no LIKE :reference_no";
                $params['reference_no'] = '%' . $filters['reference_no'] . '%';
            }
            
            if (!empty($filters['amount'])) {
                // Support range format like "100" or "99-101"
                if (strpos($filters['amount'], '-') !== false) {
                    list($min, $max) = explode('-', $filters['amount']);
                    $where[] = "trams_received_amount BETWEEN :amount_min AND :amount_max";
                    $params['amount_min'] = (float)trim($min);
                    $params['amount_max'] = (float)trim($max);
                } else {
                    $where[] = "trams_received_amount = :amount";
                    $params['amount'] = (float)$filters['amount'];
                }
            }
            
            if (!empty($filters['payment_method'])) {
                $where[] = "payment_method = :payment_method";
                $params['payment_method'] = $filters['payment_method'];
            }
            
            if (!empty($filters['booking_source'])) {
                $where[] = "booking_source = :booking_source";
                $params['booking_source'] = $filters['booking_source'];
            }
            
            if (!empty($filters['order_id'])) {
                $where[] = "order_id = :order_id";
                $params['order_id'] = $filters['order_id'];
            }
            
            if (!empty($filters['profile_id'])) {
                $where[] = "profile_id = :profile_id";
                $params['profile_id'] = $filters['profile_id'];
            }
            
            if (!empty($filters['email_phone'])) {
                $where[] = "(email LIKE :email_phone OR phone LIKE :email_phone)";
                $params['email_phone'] = '%' . $filters['email_phone'] . '%';
            }
            
            if (!empty($filters['pnr_ticket'])) {
                $where[] = "(pnr LIKE :pnr_ticket OR ticket_number LIKE :pnr_ticket)";
                $params['pnr_ticket'] = '%' . $filters['pnr_ticket'] . '%';
            }
            
            if (!empty($filters['payment_type'])) {
                $where[] = "payment_type = :payment_type";
                $params['payment_type'] = $filters['payment_type'];
            }
            
            if (!empty($filters['clear_date'])) {
                $where[] = "DATE(cleared_date) = :clear_date";
                $params['clear_date'] = $filters['clear_date'];
            }
            
            $whereClause = implode(' AND ', $where);
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
            
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history
                WHERE $whereClause
                ORDER BY auto_id DESC
                LIMIT $limit
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::getPaymentRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get payment record by ID
     */
    public function getPaymentRecordById($paymentId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_travel_payment_history
                WHERE auto_id = :payment_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['payment_id' => $paymentId]);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::getPaymentRecordById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update payment history field
     */
    public function updatePaymentHistory($paymentId, $fieldName, $fieldValue, $modifiedBy, $cleared = false)
    {
        try {
            $setParts = [
                "$fieldName = :field_value",
                "modified_date = :modified_date",
                "modified_by = :modified_by"
            ];
            $params = [
                'payment_id' => $paymentId,
                'field_value' => $fieldValue,
                'modified_date' => date('Y-m-d H:i:s'),
                'modified_by' => $modifiedBy
            ];
            
            if ($cleared) {
                $setParts[] = "cleared_date = :cleared_date";
                $setParts[] = "cleared_by = :cleared_by";
                $params['cleared_date'] = date('Y-m-d H:i:s');
                $params['cleared_by'] = $modifiedBy;
            }
            
            $setClause = implode(', ', $setParts);
            
            $query = "
                UPDATE wpk4_backend_travel_payment_history 
                SET $setClause
                WHERE auto_id = :payment_id
            ";
            
            return $this->execute($query, $params);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::updatePaymentHistory error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create payment update history
     */
    public function createPaymentUpdateHistory($orderId, $paxAutoId, $metaKey, $metaValue, $updatedUser)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_update_history 
                (order_id, pax_auto_id, meta_key, meta_value, meta_key_data, updated_time, updated_user) 
                VALUES 
                (:order_id, :pax_auto_id, :meta_key, :meta_value, 'Payment Update into wpk4_backend_travel_payment_history', :updated_time, :updated_user)
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'pax_auto_id' => $paxAutoId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'updated_time' => date('Y-m-d H:i:s'),
                'updated_user' => $updatedUser
            ]);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::createPaymentUpdateHistory error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create payment reconciliation
     */
    public function createPaymentReconciliation($processDate, $paymentMethod, $amount, $remark, $addedBy)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_payment_reconciliation 
                (process_date, payment_method, amount, remark, added_by) 
                VALUES 
                (:process_date, :payment_method, :amount, :remark, :added_by)
            ";
            
            $this->execute($query, [
                'process_date' => $processDate,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'remark' => $remark,
                'added_by' => $addedBy
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::createPaymentReconciliation error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update payment reconciliation
     */
    public function updatePaymentReconciliation($reconciliationId, $amount, $remark, $addedBy)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_payment_reconciliation 
                SET amount = :amount,
                    remark = :remark,
                    added_by = :added_by
                WHERE auto_id = :reconciliation_id
            ";
            
            return $this->execute($query, [
                'amount' => $amount,
                'remark' => $remark,
                'added_by' => $addedBy,
                'reconciliation_id' => $reconciliationId
            ]);
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::updatePaymentReconciliation error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get bank reconciliation data
     */
    public function getBankReconciliationData($bankId, $fromDate, $toDate)
    {
        try {
            $fromDateTime = $fromDate . ' 00:00:00';
            $toDateTime = $toDate . ' 23:59:59';
            
            // Get payment records
            $queryPayments = "
                SELECT * FROM wpk4_backend_travel_payment_history
                WHERE payment_method = :bank_id 
                  AND process_date BETWEEN :from_date AND :to_date
                ORDER BY process_date ASC
            ";
            
            $payments = $this->query($queryPayments, [
                'bank_id' => $bankId,
                'from_date' => $fromDateTime,
                'to_date' => $toDateTime
            ]);
            
            // Get cleared amount
            $queryCleared = "
                SELECT sum(trams_received_amount) as total_cleared_amount 
                FROM wpk4_backend_travel_payment_history
                WHERE payment_method = :bank_id 
                  AND process_date BETWEEN :from_date AND :to_date
                  AND cleared_date IS NOT NULL
            ";
            
            $cleared = $this->queryOne($queryCleared, [
                'bank_id' => $bankId,
                'from_date' => $fromDateTime,
                'to_date' => $toDateTime
            ]);
            
            // Get initial outstanding amount
            $queryOutstanding = "
                SELECT outstanding_amount 
                FROM wpk4_backend_accounts_bank_account 
                WHERE bank_id = :bank_id
            ";
            
            $outstanding = $this->queryOne($queryOutstanding, ['bank_id' => $bankId]);
            
            return [
                'payments' => $payments,
                'total_cleared_amount' => $cleared['total_cleared_amount'] ?? 0,
                'initial_outstanding_amount' => $outstanding['outstanding_amount'] ?? 0
            ];
        } catch (PDOException $e) {
            error_log("AccountingManagementDAL::getBankReconciliationData error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

