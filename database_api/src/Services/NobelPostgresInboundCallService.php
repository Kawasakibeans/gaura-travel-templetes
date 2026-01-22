<?php

namespace App\Services;

use App\DAL\NobelPostgresInboundCallDAL;

class NobelPostgresInboundCallService
{
    private $dal;

    public function __construct(NobelPostgresInboundCallDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture inboundcall data from PostgreSQL
     * Line: 29-85 (in template)
     */
    public function captureInboundcallData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids($targetDate);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getInboundlogData($targetDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        foreach ($pgData as $row) {
            try {
                $rowid = trim($row['rowid'] ?? '');
                
                if (!in_array($rowid, $existingRowids)) {
                    $this->dal->insertInboundcallData($row);
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
            'type' => 'inboundcall_quote',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorDetails
        ];
    }
}

