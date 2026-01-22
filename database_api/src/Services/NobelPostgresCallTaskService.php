<?php

namespace App\Services;

use App\DAL\NobelPostgresCallTaskDAL;

class NobelPostgresCallTaskService
{
    private $dal;

    public function __construct(NobelPostgresCallTaskDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture addistats data from PostgreSQL
     * Line: 58-104 (in template)
     */
    public function captureAddistats($limit = 30)
    {
        $lastRowid = $this->dal->getLastRowid('wpk4_backend_agent_nobel_data_addistats');
        $pgData = $this->dal->getAddistatsData($lastRowid, $limit);
        
        $inserted = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            try {
                // Insert into main table
                $this->dal->insertAddistatsData($row, 'wpk4_backend_agent_nobel_data_addistats');
                $inserted++;
            } catch (\Exception $e) {
                $errors[] = [
                    'rowid' => $row['rowid'] ?? '',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'addistats',
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture appl_status data from PostgreSQL
     * Line: 108-154 (in template)
     */
    public function captureApplStatus($limit = 30)
    {
        $lastRowid = $this->dal->getLastRowid('wpk4_backend_agent_nobel_data_appl_status');
        $pgData = $this->dal->getApplStatusData($lastRowid, $limit);
        
        $inserted = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            try {
                // Insert into main table
                $this->dal->insertApplStatusData($row, 'wpk4_backend_agent_nobel_data_appl_status');
                $inserted++;
            } catch (\Exception $e) {
                $errors[] = [
                    'rowid' => $row['rowid'] ?? '',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'appl_status',
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture call_history data from PostgreSQL
     * Line: 228-319 (in template)
     */
    public function captureCallHistory($limit = 10)
    {
        $lastRowid = $this->dal->getLastRowid('wpk4_backend_agent_nobel_data_call_history');
        $pgData = $this->dal->getCallHistoryData($lastRowid, $limit);
        
        $inserted = 0;
        $insertedRealtime = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            try {
                // Insert into main table
                $this->dal->insertCallHistoryData($row, 'wpk4_backend_agent_nobel_data_call_history');
                $inserted++;
                
                // Also insert into realtime table
                $this->dal->insertCallHistoryData($row, 'wpk4_backend_agent_nobel_data_call_history_realtime');
                $insertedRealtime++;
            } catch (\Exception $e) {
                $errors[] = [
                    'rowid' => $row['rowid'] ?? '',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'call_history',
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'inserted_realtime' => $insertedRealtime,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }
}

