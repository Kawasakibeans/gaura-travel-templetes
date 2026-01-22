<?php

namespace App\Services;

use App\DAL\NobelPostgresTaskDAL;

class NobelPostgresTaskService
{
    private $dal;

    public function __construct(NobelPostgresTaskDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture tsktsrday data from PostgreSQL
     * Line: 46-137 (in template)
     */
    public function captureTsktsrday()
    {
        // Get last rowid from MySQL
        $lastRowid = $this->dal->getLastRowid();
        
        // Get data from PostgreSQL (incremental - only new records)
        $pgData = $this->dal->getTsktsrdayData($lastRowid);
        
        $inserted = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $this->dal->insertTsktsrdayData($row);
                $inserted++;
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
            'last_rowid' => $lastRowid,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }
}

