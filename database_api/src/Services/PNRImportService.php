<?php
/**
 * PNR Import Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\PNRImportDAL;
use Exception;

class PNRImportService
{
    private $pnrImportDAL;

    public function __construct()
    {
        $this->pnrImportDAL = new PNRImportDAL();
    }

    /**
     * Preview PNR import by auto ID
     */
    public function previewPNRByAutoId($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'id' && ($row[1] ?? '') === 'pnr') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $pnr = $row[1] ?? '';
            
            if (empty($autoId) || empty($pnr)) {
                continue; // Skip invalid rows
            }
            
            // Check if auto_id exists
            $existing = $this->pnrImportDAL->checkAutoIdExists($autoId);
            
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
                'pnr' => $pnr,
                'status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import PNR by auto ID
     */
    public function importPNRByAutoId($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            // Only import records with status 'Existing'
            if (($record['match_hidden'] ?? '') !== 'Existing') {
                continue;
            }
            
            $autoId = $record['auto_id'] ?? '';
            $pnr = $record['pnr'] ?? '';
            
            if (empty($autoId) || empty($pnr)) {
                continue; // Skip invalid records
            }
            
            // Update PNR
            $this->pnrImportDAL->updatePNR($autoId, $pnr);
            
            // Insert history update
            $this->pnrImportDAL->insertHistoryUpdate($autoId, 'pnr', $pnr, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview PNR and Ticket import
     */
    public function previewPNRAndTicket($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'id' && ($row[1] ?? '') === 'pnr') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $pnr = $row[1] ?? '';
            $ticketNo = $row[2] ?? '';
            $paxStatus = $row[3] ?? '';
            
            if (empty($autoId)) {
                continue; // Skip invalid rows
            }
            
            // Check if auto_id exists
            $existing = $this->pnrImportDAL->checkAutoIdExists($autoId);
            
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
                'pnr' => $pnr,
                'ticket_no' => $ticketNo,
                'pax_status' => $paxStatus,
                'status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import PNR and Ticket
     */
    public function importPNRAndTicket($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            // Only import records with status 'Existing'
            if (($record['match_hidden'] ?? '') !== 'Existing') {
                continue;
            }
            
            $autoId = $record['auto_id'] ?? '';
            $pnr = $record['pnr'] ?? '';
            $ticketNo = $record['ticket_no'] ?? '';
            $paxStatus = $record['pax_status'] ?? '';
            
            if (empty($autoId)) {
                continue; // Skip invalid records
            }
            
            // Update PNR, ticket number, and status
            $this->pnrImportDAL->updatePNRAndTicket($autoId, $pnr, $ticketNo, $paxStatus);
            
            // Insert history updates (multiple rows)
            if (!empty($pnr)) {
                $this->pnrImportDAL->insertHistoryUpdate($autoId, 'pnr', $pnr, $updatedBy, $now);
            }
            if (!empty($ticketNo)) {
                $this->pnrImportDAL->insertHistoryUpdate($autoId, 'ticket_number', $ticketNo, $updatedBy, $now);
            }
            if (!empty($paxStatus)) {
                $this->pnrImportDAL->insertHistoryUpdate($autoId, 'pax_status', $paxStatus, $updatedBy, $now);
            }
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

