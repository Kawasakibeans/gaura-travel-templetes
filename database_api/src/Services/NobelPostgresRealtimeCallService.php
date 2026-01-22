<?php

namespace App\Services;

use App\DAL\NobelPostgresRealtimeCallDAL;

class NobelPostgresRealtimeCallService
{
    private $dal;

    public function __construct(NobelPostgresRealtimeCallDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture call log master data from PostgreSQL
     * Line: 57-166 (in template)
     */
    public function captureCallLogMaster($targetDate = null)
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
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallLogMasterData($targetDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $rowid = trim($row['rowid'] ?? '');
                
                if (!in_array($rowid, $existingRowids)) {
                    $this->dal->insertCallLogMasterData($row);
                    $inserted++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'rowid' => $row['rowid'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'call_log_master',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }

    /**
     * Capture call log sequence data from PostgreSQL
     * Line: 170-263 (in template)
     */
    public function captureCallLogSequence($targetDate = null)
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
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallLogSequenceData($targetDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $rowid = trim($row['rowid'] ?? '');
                
                if (!in_array($rowid, $existingRowids)) {
                    $this->dal->insertCallLogSequenceData($row);
                    $inserted++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'rowid' => $row['rowid'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'call_log_sequence',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }

    /**
     * Capture callback data from PostgreSQL
     * Line: 267-362 (in template)
     */
    public function captureCallback($targetDate = null)
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
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCallbackData($targetDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $rowid = trim($row['rowid'] ?? '');
                
                if (!in_array($rowid, $existingRowids)) {
                    $this->dal->insertCallbackData($row);
                    $inserted++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'rowid' => $row['rowid'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'callback',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }
}

