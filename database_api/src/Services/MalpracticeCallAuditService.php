<?php
/**
 * Malpractice Call Audit Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\MalpracticeCallAuditDAL;
use Exception;

class MalpracticeCallAuditService
{
    private $auditDAL;
    
    public function __construct()
    {
        $this->auditDAL = new MalpracticeCallAuditDAL();
    }
    
    /**
     * Get audit records with filters
     */
    public function getAuditRecords($filters = [])
    {
        $records = $this->auditDAL->getAuditRecords($filters);
        
        return [
            'success' => true,
            'data' => $records,
            'count' => count($records)
        ];
    }
    
    /**
     * Get audit record by ID
     */
    public function getAuditRecordById($id)
    {
        if (empty($id)) {
            throw new Exception('ID is required', 400);
        }
        
        $record = $this->auditDAL->getAuditRecordById($id);
        
        if (!$record) {
            throw new Exception('Audit record not found', 404);
        }
        
        return [
            'success' => true,
            'data' => $record
        ];
    }
    
    /**
     * Create audit record
     */
    public function createAuditRecord($data, $addedBy)
    {
        // Validate required fields
        $requiredFields = ['cc', 'telephone', 'call_type', 'campaign', 'agent_name', 
                          'status', 'additional_status', 'call_date', 'call_time', 
                          'time_connect', 'time_acw', 'recording_file_no'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }
        
        // Validate cc (country code)
        if (!in_array($data['cc'], ['61', '91'])) {
            throw new Exception("Country code must be 61 or 91", 400);
        }
        
        // Validate campaign
        if (!in_array($data['campaign'], ['GTMD', 'GTCB'])) {
            throw new Exception("Campaign must be GTMD or GTCB", 400);
        }
        
        // Validate status
        $validStatuses = ['A', 'AB', 'AD', 'CB', 'CT', 'DB', 'DD', 'OB', 'SL', 'TF', 'EU'];
        if (!in_array($data['status'], $validStatuses)) {
            throw new Exception("Invalid status. Valid values: " . implode(', ', $validStatuses), 400);
        }
        
        // Validate telephone format based on country code
        if ($data['cc'] == '61') {
            // Australian format: 9 digits starting with 4
            if (!preg_match('/^[4][0-9]{8}$/', $data['telephone'])) {
                throw new Exception("Invalid Australian phone number format (must be 9 digits starting with 4)", 400);
            }
        } else {
            // Indian format: 10 digits starting with 6-9
            if (!preg_match('/^[6-9][0-9]{9}$/', $data['telephone'])) {
                throw new Exception("Invalid Indian phone number format (must be 10 digits starting with 6-9)", 400);
            }
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['call_date'])) {
            throw new Exception("Invalid date format. Expected YYYY-MM-DD", 400);
        }
        
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $data['call_time'])) {
            throw new Exception("Invalid time format. Expected HH:mm", 400);
        }
        
        // Validate time_connect and time_acw format
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['time_connect'])) {
            throw new Exception("Invalid time_connect format. Expected HH:mm:ss", 400);
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['time_acw'])) {
            throw new Exception("Invalid time_acw format. Expected HH:mm:ss", 400);
        }
        
        $recordId = $this->auditDAL->createAuditRecord($data, $addedBy);
        
        return [
            'success' => true,
            'message' => 'Audit record created successfully',
            'data' => [
                'id' => $recordId
            ]
        ];
    }
    
    /**
     * Update audit record
     */
    public function updateAuditRecord($id, $data, $addedBy)
    {
        if (empty($id)) {
            throw new Exception('ID is required', 400);
        }
        
        // Check if record exists
        $existing = $this->auditDAL->getAuditRecordById($id);
        if (!$existing) {
            throw new Exception('Audit record not found', 404);
        }
        
        // Validate fields if provided
        if (isset($data['cc']) && !in_array($data['cc'], ['61', '91'])) {
            throw new Exception("Country code must be 61 or 91", 400);
        }
        
        if (isset($data['campaign']) && !in_array($data['campaign'], ['GTMD', 'GTCB'])) {
            throw new Exception("Campaign must be GTMD or GTCB", 400);
        }
        
        if (isset($data['status'])) {
            $validStatuses = ['A', 'AB', 'AD', 'CB', 'CT', 'DB', 'DD', 'OB', 'SL', 'TF', 'EU'];
            if (!in_array($data['status'], $validStatuses)) {
                throw new Exception("Invalid status. Valid values: " . implode(', ', $validStatuses), 400);
            }
        }
        
        $this->auditDAL->updateAuditRecord($id, $data, $addedBy);
        
        $updated = $this->auditDAL->getAuditRecordById($id);
        
        return [
            'success' => true,
            'message' => 'Audit record updated successfully',
            'data' => $updated
        ];
    }
    
    /**
     * Delete audit record
     */
    public function deleteAuditRecord($id)
    {
        if (empty($id)) {
            throw new Exception('ID is required', 400);
        }
        
        // Check if record exists
        $existing = $this->auditDAL->getAuditRecordById($id);
        if (!$existing) {
            throw new Exception('Audit record not found', 404);
        }
        
        $this->auditDAL->deleteAuditRecord($id);
        
        return [
            'success' => true,
            'message' => 'Audit record deleted successfully'
        ];
    }
}

