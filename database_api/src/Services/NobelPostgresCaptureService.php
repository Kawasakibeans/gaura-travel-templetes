<?php

namespace App\Services;

use App\DAL\NobelPostgresCaptureDAL;

class NobelPostgresCaptureService
{
    private $dal;

    public function __construct(NobelPostgresCaptureDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture cust_ob_inb_hst data from PostgreSQL
     * Line: 59-206 (in template)
     */
    public function captureCustObInbHst($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        $captureFromDateYmd = $targetDate;
        
        $existingRowids = $this->dal->getExistingRowids('wpk4_backend_agent_nobel_data_travel', 'call_date', $captureFromDateYmd);
        $pgData = $this->dal->getCustObInbHstData($captureFromDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            if (!in_array($rowid, $existingRowids)) {
                try {
                    $this->dal->insertTravelData($row);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => 'cust_ob_inb_hst',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture rec_playint data from PostgreSQL (call rec)
     * Line: 210-336 (in template)
     */
    public function captureCallRec($targetDate = null, $realtime = false)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        $captureFromDateYmd = $targetDate;
        
        $tableName = $realtime 
            ? 'wpk4_backend_agent_nobel_data_call_rec_realtime'
            : 'wpk4_backend_agent_nobel_data_call_rec';
        
        $existingRowids = $this->dal->getExistingRowids($tableName, 'call_date', $captureFromDateYmd);
        $pgData = $this->dal->getRecPlayintData($captureFromDate, $realtime);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            if (!in_array($rowid, $existingRowids)) {
                try {
                    $this->dal->insertCallRecData($row, $tableName);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => $realtime ? 'call_rec_realtime' : 'call_rec',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture inboundlog data from PostgreSQL
     * Line: 340-444, 560-643 (in template)
     */
    public function captureInboundcallRec($targetDate = null, $realtime = false)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        $captureFromDateYmd = $targetDate;
        
        $tableName = $realtime 
            ? 'wpk4_backend_agent_nobel_data_inboundcall_rec_realtime'
            : 'wpk4_backend_agent_nobel_data_inboundcall_rec';
        
        $existingRowids = $this->dal->getExistingRowids($tableName, 'call_date', $captureFromDateYmd);
        $pgData = $this->dal->getInboundlogData($captureFromDate, $realtime);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            if (!in_array($rowid, $existingRowids)) {
                try {
                    $this->dal->insertInboundcallRecData($row, $tableName);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => $realtime ? 'inboundcall_rec_realtime' : 'inboundcall_rec',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture tsktsrday data from PostgreSQL
     * Line: 647-744 (in template)
     */
    public function captureTsktsrday()
    {
        $lastRowid = $this->dal->getLastRowid('wpk4_backend_agent_nobel_data_tsktsrday');
        $pgData = $this->dal->getTsktsrdayData($lastRowid);
        
        $inserted = 0;
        $insertedRealtime = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            try {
                // Insert into main table
                $this->dal->insertTsktsrdayData($row, 'wpk4_backend_agent_nobel_data_tsktsrday');
                $inserted++;
                
                // Also insert into realtime table
                $this->dal->insertTsktsrdayData($row, 'wpk4_backend_agent_nobel_data_tsktsrday_realtime');
                $insertedRealtime++;
            } catch (\Exception $e) {
                $errors[] = [
                    'rowid' => $row['rowid'] ?? '',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'tsktsrday',
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'inserted_realtime' => $insertedRealtime,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture tskpauday data from PostgreSQL
     * Line: 748-821 (in template)
     */
    public function captureTskpauday()
    {
        $lastRowid = $this->dal->getLastRowid('wpk4_backend_agent_nobel_data_tskpauday');
        $pgData = $this->dal->getTskpaudayData($lastRowid);
        
        $inserted = 0;
        $insertedRealtime = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            try {
                // Insert into main table
                $this->dal->insertTskpaudayData($row, 'wpk4_backend_agent_nobel_data_tskpauday');
                $inserted++;
                
                // Also insert into realtime table
                $this->dal->insertTskpaudayData($row, 'wpk4_backend_agent_nobel_data_tskpauday_realtime');
                $insertedRealtime++;
            } catch (\Exception $e) {
                $errors[] = [
                    'rowid' => $row['rowid'] ?? '',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'tskpauday',
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'inserted_realtime' => $insertedRealtime,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Update call rec realtime status based on PostgreSQL
     * Line: 825-898 (in template)
     */
    public function updateCallRecRealtimeStatus($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        
        $existingRowids = $this->dal->getCallRecRealtimeRowidsForUpdate();
        $pgData = $this->dal->getRecPlayintForUpdate($captureFromDate);
        
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            $recStatus = trim($row['rec_status'] ?? '');
            
            if (in_array($rowid, $existingRowids) && $recStatus !== '') {
                try {
                    // Convert date format
                    $callDate = $row['call_date'] ?? '';
                    if ($callDate) {
                        $dateTime = \DateTime::createFromFormat('d/m/Y', $callDate);
                        if ($dateTime) {
                            $callDate = $dateTime->format('Y-m-d');
                        }
                    }
                    
                    $endTime = $row['end_time'] ?? '';
                    $recDuration = $row['rec_duration'] ?? '';
                    
                    $this->dal->updateCallRecRealtimeStatus($rowid, $recStatus, $endTime, $recDuration, $callDate);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => 'call_rec_realtime_update',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture all realtime data (as per template line 47-48)
     */
    public function captureAllRealtime($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $callRecResult = $this->captureCallRec($targetDate, true);
        $inboundcallRecResult = $this->captureInboundcallRec($targetDate, true);
        
        return [
            'target_date' => $targetDate,
            'call_rec_realtime' => $callRecResult,
            'inboundcall_rec_realtime' => $inboundcallRecResult,
            'summary' => [
                'total_inserted' => $callRecResult['inserted'] + $inboundcallRecResult['inserted'],
                'total_skipped' => $callRecResult['skipped'] + $inboundcallRecResult['skipped'],
                'total_errors' => $callRecResult['errors'] + $inboundcallRecResult['errors']
            ]
        ];
    }

    /**
     * Capture all data (comprehensive)
     */
    public function captureAll($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $results = [];
        
        // Capture realtime data
        $results['realtime'] = $this->captureAllRealtime($targetDate);
        
        // Capture tsktsrday
        $results['tsktsrday'] = $this->captureTsktsrday();
        
        // Capture tskpauday
        $results['tskpauday'] = $this->captureTskpauday();
        
        // Update call rec status
        $results['call_rec_status_update'] = $this->updateCallRecRealtimeStatus($targetDate);
        
        return [
            'target_date' => $targetDate,
            'results' => $results,
            'summary' => [
                'total_operations' => count($results),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
}

