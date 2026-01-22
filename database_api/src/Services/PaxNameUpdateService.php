<?php
/**
 * Pax Name Update Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\PaxNameUpdateDAL;
use Exception;

class PaxNameUpdateService
{
    private $paxNameUpdateDAL;

    public function __construct()
    {
        $this->paxNameUpdateDAL = new PaxNameUpdateDAL();
    }

    /**
     * Preview pax name update import
     */
    public function previewPaxNameUpdate($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'id' && ($row[1] ?? '') === 'fname') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $fname = $row[1] ?? '';
            $lname = $row[2] ?? '';
            
            if (empty($autoId)) {
                continue;
            }
            
            // Check if passenger exists
            $existing = $this->paxNameUpdateDAL->checkPaxExists($autoId);
            
            $matchHidden = 'New';
            $match = 'New Record';
            $checked = false;
            
            if ($existing && $existing['auto_id'] == $autoId) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'fname' => $fname,
                'lname' => $lname,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import pax name updates
     */
    public function importPaxNameUpdate($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $fname = $record['fname'] ?? '';
            $lname = $record['lname'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue;
            }
            
            if (empty($autoId)) {
                continue;
            }
            
            // Update passenger name
            $this->paxNameUpdateDAL->updatePaxName($autoId, $fname, $lname);
            
            // Insert history updates
            $this->paxNameUpdateDAL->insertHistoryUpdate($autoId, 'fname', $fname, $updatedBy, $now);
            $this->paxNameUpdateDAL->insertHistoryUpdate($autoId, 'lname', $lname, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

