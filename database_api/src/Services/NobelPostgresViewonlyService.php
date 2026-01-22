<?php

namespace App\Services;

use App\DAL\NobelPostgresViewonlyDAL;

class NobelPostgresViewonlyService
{
    private $dal;

    public function __construct(NobelPostgresViewonlyDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get cust_ob_inb_hst data (viewonly - no insert)
     * Line: 48-169 (in template)
     */
    public function getCustObInbHstData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('d/m/Y', strtotime('-1 day'));
        }
        
        $targetDateYmd = date('Y-m-d', strtotime(str_replace('/', '-', $targetDate)));
        
        // Get existing rowids
        $existingRowids = $this->dal->getExistingRowids($targetDateYmd);
        
        // Get data from PostgreSQL
        $pgData = $this->dal->getCustObInbHstData($targetDate);
        
        // Filter out existing records
        $newRecords = [];
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            if (!in_array($rowid, $existingRowids)) {
                $newRecords[] = $row;
            }
        }
        
        return [
            'type' => 'cust_ob_inb_hst',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'existing_count' => count($existingRowids),
            'new_records_count' => count($newRecords),
            'new_records' => $newRecords
        ];
    }
}

