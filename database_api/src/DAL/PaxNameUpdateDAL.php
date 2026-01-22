<?php
/**
 * Pax Name Update Data Access Layer
 * Handles database operations for passenger name update import
 */

namespace App\DAL;

use Exception;
use PDOException;

class PaxNameUpdateDAL extends BaseDAL
{
    /**
     * Check if passenger record exists by auto_id
     */
    public function checkPaxExists($autoId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_travel_booking_pax 
                WHERE auto_id = :auto_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("PaxNameUpdateDAL::checkPaxExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update passenger name (fname and lname)
     */
    public function updatePaxName($autoId, $fname, $lname)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_booking_pax 
                SET fname = :fname,
                    lname = :lname
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'fname' => $fname,
                'lname' => $lname
            ]);
        } catch (PDOException $e) {
            error_log("PaxNameUpdateDAL::updatePaxName error: " . $e->getMessage());
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
            error_log("PaxNameUpdateDAL::insertHistoryUpdate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

