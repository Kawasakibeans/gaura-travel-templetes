<?php

namespace App\Services;

use App\DAL\NobelPostgresTasksrdayDAL;

class NobelPostgresTasksrdayService
{
    private $dal;

    public function __construct(NobelPostgresTasksrdayDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture tsktsrday data from PostgreSQL
     * Line: 49-135 (in template)
     */
    public function captureTsktsrday($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids($targetDateYmd);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getTsktsrdayData($targetDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $rowid = trim($row['rowid'] ?? '');
                
                if (!in_array($rowid, $existingRowids)) {
                    $this->dal->insertTsktsrdayData($row);
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
            'type' => 'tsktsrday',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }

    /**
     * Get tsktsrday data (viewonly - no insert)
     * Line: 49-135 (in template)
     */
    public function getTsktsrdayData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids($targetDateYmd);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getTsktsrdayData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'tsktsrday',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }
}

