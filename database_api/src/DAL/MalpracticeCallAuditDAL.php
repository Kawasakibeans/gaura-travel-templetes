<?php
/**
 * Malpractice Call Audit Data Access Layer
 * Handles database operations for malpractice call audit records
 */

namespace App\DAL;

use Exception;
use PDOException;

class MalpracticeCallAuditDAL extends BaseDAL
{
    /**
     * Get audit records with filters
     */
    public function getAuditRecords($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['campaign'])) {
                $where[] = "LOWER(campaign) LIKE :campaign";
                $params['campaign'] = '%' . strtolower($filters['campaign']) . '%';
            }
            
            if (!empty($filters['agent_name'])) {
                $where[] = "LOWER(agent_name) LIKE :agent_name";
                $params['agent_name'] = '%' . strtolower($filters['agent_name']) . '%';
            }
            
            if (!empty($filters['call_date'])) {
                $where[] = "call_date LIKE :call_date";
                $params['call_date'] = '%' . $filters['call_date'] . '%';
            }
            
            if (!empty($filters['recording_file_no'])) {
                $where[] = "recording_file_no LIKE :recording_file_no";
                $params['recording_file_no'] = '%' . $filters['recording_file_no'] . '%';
            }
            
            $whereClause = implode(' AND ', $where);
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 40;
            
            $query = "
                SELECT * FROM wpk4_backend_malpractice_audit
                WHERE $whereClause
                ORDER BY id DESC
                LIMIT $limit
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("MalpracticeCallAuditDAL::getAuditRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get audit record by ID
     */
    public function getAuditRecordById($id)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_malpractice_audit
                WHERE id = :id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['id' => $id]);
        } catch (PDOException $e) {
            error_log("MalpracticeCallAuditDAL::getAuditRecordById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create audit record
     */
    public function createAuditRecord($data, $addedBy)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_malpractice_audit 
                (telephone, call_type, campaign, agent_name, status, additonal_status, call_date, 
                 call_time, time_connect, time_acw, recording_file_no, observation, cc, added_by)
                VALUES 
                (:telephone, :call_type, :campaign, :agent_name, :status, :additional_status, :call_date,
                 :call_time, :time_connect, :time_acw, :recording_file_no, :observation, :cc, :added_by)
            ";
            
            $this->execute($query, [
                'telephone' => $data['telephone'],
                'call_type' => $data['call_type'],
                'campaign' => $data['campaign'],
                'agent_name' => $data['agent_name'],
                'status' => $data['status'],
                'additional_status' => $data['additional_status'],
                'call_date' => $data['call_date'],
                'call_time' => $data['call_time'],
                'time_connect' => $data['time_connect'],
                'time_acw' => $data['time_acw'],
                'recording_file_no' => $data['recording_file_no'],
                'observation' => $data['observation'] ?? null,
                'cc' => $data['cc'],
                'added_by' => $addedBy
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("MalpracticeCallAuditDAL::createAuditRecord error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update audit record
     */
    public function updateAuditRecord($id, $data, $addedBy)
    {
        try {
            $setParts = [];
            $params = ['id' => $id];
            
            $fields = [
                'telephone' => 'telephone',
                'call_type' => 'call_type',
                'call_date' => 'call_date',
                'campaign' => 'campaign',
                'agent_name' => 'agent_name',
                'status' => 'status',
                'additional_status' => 'additonal_status',
                'time_connect' => 'time_connect',
                'time_acw' => 'time_acw',
                'recording_file_no' => 'recording_file_no',
                'observation' => 'observation',
                'cc' => 'cc'
            ];
            
            foreach ($fields as $key => $dbField) {
                if (isset($data[$key])) {
                    $setParts[] = "$dbField = :$key";
                    $params[$key] = $data[$key];
                }
            }
            
            if (isset($data['added_by'])) {
                $setParts[] = "added_by = :added_by";
                $params['added_by'] = $addedBy;
            }
            
            if (empty($setParts)) {
                throw new Exception('No fields to update', 400);
            }
            
            $setClause = implode(', ', $setParts);
            
            $query = "
                UPDATE wpk4_backend_malpractice_audit 
                SET $setClause
                WHERE id = :id
            ";
            
            return $this->execute($query, $params);
        } catch (PDOException $e) {
            error_log("MalpracticeCallAuditDAL::updateAuditRecord error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete audit record
     */
    public function deleteAuditRecord($id)
    {
        try {
            $query = "
                DELETE FROM wpk4_backend_malpractice_audit 
                WHERE id = :id
            ";
            
            return $this->execute($query, ['id' => $id]);
        } catch (PDOException $e) {
            error_log("MalpracticeCallAuditDAL::deleteAuditRecord error: " . $e->getMessage());
            throw new Exception("Database delete failed: " . $e->getMessage(), 500);
        }
    }
}

