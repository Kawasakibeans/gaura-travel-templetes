<?php
/**
 * GTX Roster Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\GTXRosterDAL;
use Exception;

class GTXRosterService
{
    private $gtxRosterDAL;

    public function __construct()
    {
        $this->gtxRosterDAL = new GTXRosterDAL();
    }

    /**
     * Convert date from DD/MM/YYYY to YYYY-MM-DD
     */
    private function convertDate($date)
    {
        if (empty($date)) {
            return '';
        }
        
        // Replace / with - and try to parse
        $dateStr = str_replace('/', '-', $date);
        $timestamp = strtotime($dateStr);
        
        if ($timestamp === false) {
            return '';
        }
        
        return date('Y-m-d', $timestamp);
    }

    /**
     * Preview GTX roster import
     */
    public function previewGTXRoster($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'EmployeeID' && ($row[1] ?? '') === 'EmployeeName') {
                continue;
            }
            
            $employeeCode = $row[0] ?? '';
            $employeeName = $row[1] ?? '';
            $date = $row[2] ?? '';
            $branch = $row[3] ?? '';
            $department = $row[4] ?? '';
            $rdo = $row[5] ?? '';
            $dow = $row[6] ?? '';
            $dateStatus = $row[7] ?? '';
            $effectiveDate = $row[8] ?? '';
            $workScheduleName = $row[9] ?? '';
            $remarks = $row[10] ?? '';
            $shiftCode = $row[11] ?? '';
            $shiftName = $row[12] ?? '';
            $shiftBeginTime = $row[13] ?? '';
            $shiftEndTime = $row[14] ?? '';
            
            if (empty($employeeCode) || empty($date)) {
                continue; // Skip invalid rows
            }
            
            // Convert dates
            $dateBackend = $this->convertDate($date);
            $effectiveDateBackend = $this->convertDate($effectiveDate);
            
            if (empty($dateBackend)) {
                continue; // Skip rows with invalid date
            }
            
            // Check if roster exists
            $existing = $this->gtxRosterDAL->checkRosterExists($employeeCode, $dateBackend);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = true;
            
            if ($existing && $existing['employee_code'] == $employeeCode && $existing['w_date'] == $dateBackend) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = false; // Existing records are not checked by default
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'employee_code' => $employeeCode,
                'employee_name' => $employeeName,
                'date' => $date,
                'date_backend' => $dateBackend,
                'branch' => $branch,
                'department' => $department,
                'rdo' => $rdo,
                'dow' => $dow,
                'date_status' => $dateStatus,
                'effective_date' => $effectiveDate,
                'effective_date_backend' => $effectiveDateBackend,
                'work_schedule_name' => $workScheduleName,
                'remarks' => $remarks,
                'shift_code' => $shiftCode,
                'shift_name' => $shiftName,
                'shift_begin_time' => $shiftBeginTime,
                'shift_end_time' => $shiftEndTime,
                'status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import GTX roster records
     */
    public function importGTXRoster($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $employeeCode = $record['employee_code'] ?? '';
            $employeeName = $record['employee_name'] ?? '';
            $dateBackend = $record['date_backend'] ?? '';
            $branch = $record['branch'] ?? '';
            $department = $record['department'] ?? '';
            $rdo = $record['rdo'] ?? '';
            $dow = $record['dow'] ?? '';
            $dateStatus = $record['date_status'] ?? '';
            $effectiveDateBackend = $record['effective_date_backend'] ?? '';
            $workScheduleName = $record['work_schedule_name'] ?? '';
            $remarks = $record['remarks'] ?? '';
            $shiftCode = $record['shift_code'] ?? '';
            $shiftName = $record['shift_name'] ?? '';
            $shiftBeginTime = $record['shift_begin_time'] ?? '';
            $shiftEndTime = $record['shift_end_time'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            if (empty($employeeCode) || empty($dateBackend)) {
                continue; // Skip invalid records
            }
            
            // Prepare roster data
            $rosterData = [
                'employee_code' => $employeeCode,
                'employee_name' => $employeeName,
                'w_date' => $dateBackend,
                'branch' => $branch,
                'department' => $department,
                'rdo' => $rdo,
                'dow' => $dow,
                'day_status' => $dateStatus,
                'effective_date' => $effectiveDateBackend,
                'work_schedule_name' => $workScheduleName,
                'remarks' => $remarks,
                'shift_code' => $shiftCode,
                'shift_name' => $shiftName,
                'shift_begin_time' => $shiftBeginTime,
                'shift_end_time' => $shiftEndTime
            ];
            
            // Insert or update based on match status
            if ($matchHidden === 'New') {
                $this->gtxRosterDAL->insertRoster($rosterData);
            } else {
                $this->gtxRosterDAL->updateRoster($rosterData);
            }
            
            // Insert history updates
            $this->gtxRosterDAL->insertHistoryUpdate($employeeCode, 'roster_update_user', $employeeCode, $updatedBy, $now);
            $this->gtxRosterDAL->insertHistoryUpdate($employeeCode, 'roster_update_date', $dateBackend, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

