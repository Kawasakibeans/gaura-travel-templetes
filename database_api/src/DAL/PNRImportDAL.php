<?php
/**
 * PNR Import Data Access Layer
 * Handles database operations for PNR import by auto ID and PNR/Ticket import
 */

namespace App\DAL;

use Exception;
use PDOException;

class PNRImportDAL extends BaseDAL
{
    /**
     * Check if auto_id exists in passenger table
     */
    public function checkAutoIdExists($autoId)
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
            error_log("PNRImportDAL::checkAutoIdExists error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update PNR by auto_id
     */
    public function updatePNR($autoId, $pnr)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_booking_pax 
                SET pnr = :pnr
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'pnr' => $pnr,
                'auto_id' => $autoId
            ]);
        } catch (PDOException $e) {
            error_log("PNRImportDAL::updatePNR error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update PNR, ticket number, and status by auto_id
     */
    public function updatePNRAndTicket($autoId, $pnr, $ticketNumber, $paxStatus)
    {
        try {
            $query = "
                UPDATE wpk4_backend_travel_booking_pax 
                SET pnr = :pnr,
                    ticket_number = :ticket_number,
                    pax_status = :pax_status
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'pnr' => $pnr,
                'ticket_number' => $ticketNumber,
                'pax_status' => $paxStatus,
                'auto_id' => $autoId
            ]);
        } catch (PDOException $e) {
            error_log("PNRImportDAL::updatePNRAndTicket error: " . $e->getMessage());
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
            error_log("PNRImportDAL::insertHistoryUpdate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

