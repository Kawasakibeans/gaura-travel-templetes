<?php

namespace App\Services;

use App\DAL\NobelPostgresRealtimeCallViewonlyDAL;

class NobelPostgresRealtimeCallViewonlyService
{
    private $dal;

    public function __construct(NobelPostgresRealtimeCallViewonlyDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get call log master data (viewonly - no insert)
     * Line: 52-137 (in template)
     */
    public function getCallLogMasterData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids(
            'wpk4_backend_agent_nobel_data_call_log_master',
            'sys_date',
            $targetDateYmd
        );
        
        // Get count from PostgreSQL
        $totalCount = $this->dal->getCallLogMasterCount($targetDate);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallLogMasterData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'call_log_master',
            'target_date' => $targetDate,
            'total_count' => $totalCount,
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }

    /**
     * Get call log sequence data (viewonly - no insert)
     * Line: 141-211 (in template)
     */
    public function getCallLogSequenceData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids(
            'wpk4_backend_agent_nobel_data_call_log_sequence',
            'sys_date',
            $targetDateYmd
        );
        
        // Get count from PostgreSQL
        $totalCount = $this->dal->getCallLogSequenceCount($targetDate);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallLogSequenceData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'call_log_sequence',
            'target_date' => $targetDate,
            'total_count' => $totalCount,
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }

    /**
     * Get callback data (viewonly - no insert)
     * Line: 215-285 (in template)
     */
    public function getCallbackData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids(
            'wpk4_backend_agent_nobel_data_call_log_callback',
            'cb_adate',
            $targetDateYmd
        );
        
        // Get count from PostgreSQL
        $totalCount = $this->dal->getCallbackCount($targetDate);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallbackData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'callback',
            'target_date' => $targetDate,
            'total_count' => $totalCount,
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }

    /**
     * Get call history data (viewonly - no insert)
     * Line: 289-375 (in template)
     */
    public function getCallHistoryData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids(
            'wpk4_backend_agent_nobel_data_call_log_history',
            'act_date',
            $targetDateYmd
        );
        
        // Get count from PostgreSQL
        $totalCount = $this->dal->getCallHistoryCount($targetDate);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallHistoryData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'call_history',
            'target_date' => $targetDate,
            'total_count' => $totalCount,
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }
}

