<?php
/**
 * Refund Portal Names Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\RefundPortalNamesDAL;
use Exception;

class RefundPortalNamesService
{
    private $refundPortalNamesDAL;

    public function __construct()
    {
        $this->refundPortalNamesDAL = new RefundPortalNamesDAL();
    }

    /**
     * Preview refund portal names import
     */
    public function previewRefundPortalNames($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'id' && ($row[1] ?? '') === 'price') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $accountname = $row[1] ?? '';
            
            if (empty($autoId)) {
                continue; // Skip rows without auto_id
            }
            
            // Check if refund exists
            $existing = $this->refundPortalNamesDAL->checkRefundExists($autoId);
            
            $matchHidden = 'New';
            $match = 'New Record';
            $checked = false;
            
            if ($existing && $existing['refund_id'] == $autoId) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true; // Only existing records can be checked
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'accountname' => $accountname,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import refund portal names
     */
    public function importRefundPortalNames($records)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $accountname = $record['accountname'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue; // Skip new records
            }
            
            if (empty($autoId) || empty($accountname)) {
                continue; // Skip invalid records
            }
            
            // Update refund record
            $this->refundPortalNamesDAL->updateRefundAccountName($autoId, $accountname);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

