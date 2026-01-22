<?php
/**
 * Amadeus Log Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\AmadeusLogDAL;
use Exception;

class AmadeusLogService
{
    private $amadeusLogDAL;
    
    public function __construct()
    {
        $this->amadeusLogDAL = new AmadeusLogDAL();
    }
    
    /**
     * Update log record
     */
    public function updateLog($id, $field, $value, $agent)
    {
        // Validate ID
        $id = (int)$id;
        if ($id <= 0) {
            throw new Exception('Invalid ID. ID must be a positive integer', 400);
        }
        
        // Validate field
        $allowedFields = ['success', 'comments', 'failed'];
        if (!in_array($field, $allowedFields)) {
            throw new Exception('Invalid field. Allowed fields: ' . implode(', ', $allowedFields), 400);
        }
        
        // Validate agent
        if (empty($agent)) {
            throw new Exception('Agent is required', 400);
        }
        
        // Get current time
        $currentTime = date('Y-m-d H:i:s');
        
        // Begin transaction
        $this->amadeusLogDAL->beginTransaction();
        
        try {
            $result = [
                'success' => true,
                'message' => 'Log updated successfully',
                'id' => $id,
                'field' => $field,
                'updated' => [
                    'field' => $field,
                    'value' => $value,
                    'agent' => $agent,
                    'timestamp' => $currentTime
                ]
            ];
            
            if ($field === 'success') {
                $this->amadeusLogDAL->updateSuccess($id, $value, $agent, $currentTime);
            } elseif ($field === 'comments') {
                $this->amadeusLogDAL->updateComments($id, $value, $agent, $currentTime);
            } elseif ($field === 'failed') {
                // Get log record details for escalation
                $logRecord = $this->amadeusLogDAL->getLogRecord($id);
                
                if (!$logRecord) {
                    throw new Exception('Log record not found', 404);
                }
                
                // Create escalation note
                $escalationNote = sprintf(
                    'PNR: %s | First name: %s | Last name: %s | Status: %s',
                    $logRecord['pnr'] ?? '',
                    $logRecord['fname'] ?? '',
                    $logRecord['lname'] ?? '',
                    $logRecord['status'] ?? ''
                );
                
                // Create escalation record
                $this->amadeusLogDAL->createEscalation(
                    $logRecord['order_id'] ?? '',
                    $escalationNote,
                    $agent,
                    $currentTime
                );
                
                // Update failed field
                $this->amadeusLogDAL->updateFailed($id, $value);
                
                // Add escalation info to result
                $result['message'] = 'Log updated and escalation created';
                $result['escalation'] = [
                    'order_id' => $logRecord['order_id'] ?? '',
                    'escalation_type' => 'Amadeus Name Update Issue',
                    'note' => $escalationNote,
                    'status' => 'open',
                    'escalate_to' => 'HO'
                ];
            }
            
            // Commit transaction
            $this->amadeusLogDAL->commit();
            
            return $result;
        } catch (Exception $e) {
            // Rollback on error
            $this->amadeusLogDAL->rollback();
            throw $e;
        }
    }
    
    /**
     * Get log records with filters
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogRecords($filters = [], $limit = 70, $offset = 0)
    {
        // Validate limit
        $limit = (int)$limit;
        if ($limit <= 0 || $limit > 200) {
            $limit = 70;
        }
        
        // Validate offset
        $offset = (int)$offset;
        if ($offset < 0) {
            $offset = 0;
        }
        
        return $this->amadeusLogDAL->getLogRecords($filters, $limit, $offset);
    }
    
    /**
     * Create log record
     * 
     * @param array $data
     * @return array
     */
    public function createLogRecord($data)
    {
        // Validate required fields
        if (empty($data['pnr'])) {
            throw new Exception('PNR is required', 400);
        }
        
        if (empty($data['order_id'])) {
            throw new Exception('Order ID is required', 400);
        }
        
        if (empty($data['fname']) && empty($data['lname'])) {
            throw new Exception('First name or last name is required', 400);
        }
        
        // Set default values
        if (empty($data['added_on'])) {
            $data['added_on'] = date('Y-m-d H:i:s');
        }
        
        $id = $this->amadeusLogDAL->createLogRecord($data);
        
        return [
            'id' => $id,
            'message' => 'Log record created successfully',
            'data' => array_merge($data, ['auto_id' => $id])
        ];
    }
    
    /**
     * Get distinct airlines
     * 
     * @return array
     */
    public function getDistinctAirlines()
    {
        return $this->amadeusLogDAL->getDistinctAirlines();
    }
}

