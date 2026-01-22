<?php
/**
 * Accounting Management Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AccountingManagementDAL;
use Exception;

class AccountingManagementService
{
    private $accountingDAL;
    
    public function __construct()
    {
        $this->accountingDAL = new AccountingManagementDAL();
    }
    
    /**
     * Create bank account
     */
    public function createBankAccount($bankId, $accountName, $branch, $accountNumber)
    {
        if (empty($bankId)) {
            throw new Exception('Bank ID is required', 400);
        }
        
        if (empty($accountName)) {
            throw new Exception('Account name is required', 400);
        }
        
        if (empty($branch)) {
            throw new Exception('Branch is required', 400);
        }
        
        if (empty($accountNumber)) {
            throw new Exception('Account number is required', 400);
        }
        
        $accountId = $this->accountingDAL->createBankAccount($bankId, $accountName, $branch, $accountNumber);
        
        return [
            'success' => true,
            'message' => 'Bank account created successfully',
            'data' => [
                'account_id' => $accountId
            ]
        ];
    }
    
    /**
     * Get bank accounts
     */
    public function getBankAccounts()
    {
        $accounts = $this->accountingDAL->getBankAccounts();
        
        return [
            'success' => true,
            'data' => $accounts,
            'count' => count($accounts)
        ];
    }
    
    /**
     * Get payment records with filters
     */
    public function getPaymentRecords($filters = [])
    {
        $records = $this->accountingDAL->getPaymentRecords($filters);
        
        return [
            'success' => true,
            'data' => $records,
            'count' => count($records)
        ];
    }
    
    /**
     * Update payment history
     */
    public function updatePaymentHistory($paymentId, $fieldName, $fieldValue, $modifiedBy, $cleared = false)
    {
        if (empty($paymentId)) {
            throw new Exception('Payment ID is required', 400);
        }
        
        if (empty($fieldName)) {
            throw new Exception('Field name is required', 400);
        }
        
        if (empty($modifiedBy)) {
            throw new Exception('Modified by is required', 400);
        }
        
        // Get existing payment record
        $payment = $this->accountingDAL->getPaymentRecordById($paymentId);
        if (!$payment) {
            throw new Exception('Payment record not found', 404);
        }
        
        // Check if value actually changed
        if (isset($payment[$fieldName]) && $payment[$fieldName] == $fieldValue) {
            return [
                'success' => true,
                'message' => 'No changes detected',
                'data' => $payment
            ];
        }
        
        // Update payment history
        $this->accountingDAL->updatePaymentHistory($paymentId, $fieldName, $fieldValue, $modifiedBy, $cleared);
        
        // Create update history
        $this->accountingDAL->createPaymentUpdateHistory(
            $payment['order_id'] ?? null,
            $paymentId,
            $fieldName,
            $fieldValue,
            $modifiedBy
        );
        
        $updated = $this->accountingDAL->getPaymentRecordById($paymentId);
        
        return [
            'success' => true,
            'message' => 'Payment record updated successfully',
            'data' => $updated
        ];
    }
    
    /**
     * Create payment reconciliation
     */
    public function createPaymentReconciliation($processDate, $paymentMethod, $amount, $remark, $addedBy)
    {
        if (empty($processDate)) {
            throw new Exception('Process date is required', 400);
        }
        
        if (empty($paymentMethod)) {
            throw new Exception('Payment method is required', 400);
        }
        
        if ($amount === null || $amount === '') {
            throw new Exception('Amount is required', 400);
        }
        
        if (empty($addedBy)) {
            throw new Exception('Added by is required', 400);
        }
        
        $reconciliationId = $this->accountingDAL->createPaymentReconciliation(
            $processDate,
            $paymentMethod,
            (float)$amount,
            $remark,
            $addedBy
        );
        
        return [
            'success' => true,
            'message' => 'Payment reconciliation created successfully',
            'data' => [
                'reconciliation_id' => $reconciliationId
            ]
        ];
    }
    
    /**
     * Update payment reconciliation
     */
    public function updatePaymentReconciliation($reconciliationId, $amount, $remark, $addedBy)
    {
        if (empty($reconciliationId)) {
            throw new Exception('Reconciliation ID is required', 400);
        }
        
        if ($amount === null || $amount === '') {
            throw new Exception('Amount is required', 400);
        }
        
        if (empty($addedBy)) {
            throw new Exception('Added by is required', 400);
        }
        
        $this->accountingDAL->updatePaymentReconciliation(
            $reconciliationId,
            (float)$amount,
            $remark,
            $addedBy
        );
        
        return [
            'success' => true,
            'message' => 'Payment reconciliation updated successfully'
        ];
    }
    
    /**
     * Get bank reconciliation data
     */
    public function getBankReconciliationData($bankId, $fromDate, $toDate)
    {
        if (empty($bankId)) {
            throw new Exception('Bank ID is required', 400);
        }
        
        if (empty($fromDate)) {
            throw new Exception('From date is required', 400);
        }
        
        if (empty($toDate)) {
            throw new Exception('To date is required', 400);
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            throw new Exception("Invalid from date format. Expected YYYY-MM-DD", 400);
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            throw new Exception("Invalid to date format. Expected YYYY-MM-DD", 400);
        }
        
        // Validate date range
        if (strtotime($fromDate) > strtotime($toDate)) {
            throw new Exception("From date must be before or equal to to date", 400);
        }
        
        $data = $this->accountingDAL->getBankReconciliationData($bankId, $fromDate, $toDate);
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
}

