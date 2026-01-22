<?php
/**
 * GTX Roster Data Access Layer
 * Handles database operations for GTX roster import
 */

namespace App\DAL;

use Exception;
use PDOException;

class GTXRosterDAL extends BaseDAL
{
    /**
     * Check if roster record exists by employee_code and date
     */
    public function checkRosterExists($employeeCode, $date)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_gtx_roster 
                WHERE BINARY employee_code = :employee_code 
                  AND w_date = :w_date
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'employee_code' => $employeeCode,
                'w_date' => $date
            ]);
        } catch (PDOException $e) {
            error_log("GTXRosterDAL::checkRosterExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert new roster record
     */
    public function insertRoster($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_gtx_roster 
                (employee_code, employee_name, w_date, branch, department, rdo, dow, day_status, 
                 effective_date, work_schedule_name, remarks, shift_code, shift_name, shift_begin_time, shift_end_time) 
                VALUES 
                (:employee_code, :employee_name, :w_date, :branch, :department, :rdo, :dow, :day_status, 
                 :effective_date, :work_schedule_name, :remarks, :shift_code, :shift_name, :shift_begin_time, :shift_end_time)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("GTXRosterDAL::insertRoster error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update existing roster record
     */
    public function updateRoster($data)
    {
        try {
            $query = "
                UPDATE wpk4_backend_gtx_roster 
                SET employee_code = :employee_code,
                    employee_name = :employee_name,
                    branch = :branch,
                    department = :department,
                    rdo = :rdo,
                    dow = :dow,
                    day_status = :day_status,
                    effective_date = :effective_date,
                    work_schedule_name = :work_schedule_name,
                    remarks = :remarks,
                    shift_code = :shift_code,
                    shift_name = :shift_name,
                    shift_begin_time = :shift_begin_time,
                    shift_end_time = :shift_end_time
                WHERE BINARY employee_code = :employee_code 
                  AND w_date = :w_date
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("GTXRosterDAL::updateRoster error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert history update record
     */
    public function insertHistoryUpdate($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_history_of_updates 
                (type_id, meta_key, meta_value, updated_by, updated_on) 
                VALUES 
                (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
            ";
            
            return $this->execute($query, [
                'type_id' => $typeId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'updated_by' => $updatedBy,
                'updated_on' => $updatedOn
            ]);
        } catch (PDOException $e) {
            error_log("GTXRosterDAL::insertHistoryUpdate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

