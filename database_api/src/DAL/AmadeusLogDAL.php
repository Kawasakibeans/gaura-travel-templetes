<?php
/**
 * Amadeus Log Data Access Layer
 * Handles database operations for Amadeus name update log
 */

namespace App\DAL;

use Exception;
use PDOException;

class AmadeusLogDAL extends BaseDAL
{
    /**
     * Get log record by auto_id
     */
    public function getLogRecord($autoId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_amadeus_name_update_log 
                WHERE auto_id = :auto_id
                ORDER BY auto_id DESC
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::getLogRecord error: " . $e->getMessage());
            // Check if table doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update success field
     */
    public function updateSuccess($autoId, $value, $agent, $currentTime)
    {
        try {
            $query = "
                UPDATE wpk4_amadeus_name_update_log 
                SET success = :value,
                    success_marked_by = :agent,
                    success_marked_on = :current_time
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'value' => $value,
                'agent' => $agent,
                'current_time' => $currentTime,
                'auto_id' => $autoId
            ]);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::updateSuccess error: " . $e->getMessage());
            // Check if table doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update comments field
     */
    public function updateComments($autoId, $value, $agent, $currentTime)
    {
        try {
            $query = "
                UPDATE wpk4_amadeus_name_update_log 
                SET comments = :value,
                    comment_by = :agent,
                    comment_on = :current_time
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'value' => $value,
                'agent' => $agent,
                'current_time' => $currentTime,
                'auto_id' => $autoId
            ]);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::updateComments error: " . $e->getMessage());
            // Check if table doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update failed field
     */
    public function updateFailed($autoId, $value)
    {
        try {
            $query = "
                UPDATE wpk4_amadeus_name_update_log 
                SET failed = :value
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'value' => $value,
                'auto_id' => $autoId
            ]);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::updateFailed error: " . $e->getMessage());
            // Check if table doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create escalation record
     */
    public function createEscalation($orderId, $note, $agent, $currentTime)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_escalations 
                (order_id, escalation_type, note, status, escalated_by, escalated_on, escalate_to) 
                VALUES 
                (:order_id, 'Amadeus Name Update Issue', :note, 'open', :agent, :current_time, 'HO')
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'note' => $note,
                'agent' => $agent,
                'current_time' => $currentTime
            ]);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::createEscalation error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Begin transaction (public wrapper)
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction (public wrapper)
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction (public wrapper)
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
    
    /**
     * Get log records with filters
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogRecords($filters = [], $limit = 70, $offset = 0)
    {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = "status LIKE :status";
                $params['status'] = '%' . $filters['status'] . '%';
            }
            
            if (!empty($filters['pnr'])) {
                $where[] = "pnr LIKE :pnr";
                $params['pnr'] = '%' . $filters['pnr'] . '%';
            }
            
            if (!empty($filters['order_id'])) {
                $where[] = "(order_id = :order_id OR infant_booking_id = :order_id2)";
                $params['order_id'] = $filters['order_id'];
                $params['order_id2'] = $filters['order_id'];
            }
            
            if (!empty($filters['airline'])) {
                $where[] = "airline = :airline";
                $params['airline'] = $filters['airline'];
            }
            
            if (!empty($filters['order_date'])) {
                $where[] = "DATE(order_date) = :order_date";
                $params['order_date'] = $filters['order_date'];
            }
            
            if (!empty($filters['travel_date'])) {
                $where[] = "DATE(travel_date) = :travel_date";
                $params['travel_date'] = $filters['travel_date'];
            }
            
            if (!empty($filters['checkbox'])) {
                if ($filters['checkbox'] === 'ticked') {
                    $where[] = "success IS NOT NULL";
                } else {
                    $where[] = "success IS NULL";
                }
            }
            
            if (!empty($filters['commentbox'])) {
                if ($filters['commentbox'] === 'added') {
                    $where[] = "comments IS NOT NULL";
                } else {
                    $where[] = "comments IS NULL";
                }
            }
            
            $whereClause = implode(' AND ', $where);
            
            // LIMIT and OFFSET cannot use named parameters in MySQL, so we use integers directly
            $limitInt = (int)$limit;
            $offsetInt = (int)$offset;
            
            $query = "
                SELECT 
                    auto_id,
                    pnr,
                    infant_booking_id,
                    order_id,
                    order_date,
                    travel_date,
                    airline,
                    lname,
                    fname,
                    salutation,
                    dob,
                    status,
                    success,
                    comments,
                    added_on
                FROM wpk4_amadeus_name_update_log
                WHERE {$whereClause}
                ORDER BY auto_id DESC
                LIMIT {$limitInt} OFFSET {$offsetInt}
            ";
            
            return $this->query($query, $params);
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::getLogRecords error: " . $e->getMessage());
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create log record
     * 
     * @param array $data
     * @return int Inserted ID
     */
    public function createLogRecord($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_amadeus_name_update_log 
                (pnr, office_id, order_id, fname, lname, salutation, dob, status, session, token, request, response, added_by, airline, order_date, travel_date, pax_type, pax_id, infant_booking_id, added_on)
                VALUES 
                (:pnr, :office_id, :order_id, :fname, :lname, :salutation, :dob, :status, :session, :token, :request, :response, :added_by, :airline, :order_date, :travel_date, :pax_type, :pax_id, :infant_booking_id, :added_on)
            ";
            
            $params = [
                'pnr' => $data['pnr'] ?? '',
                'office_id' => $data['office_id'] ?? '',
                'order_id' => $data['order_id'] ?? '',
                'fname' => $data['fname'] ?? '',
                'lname' => $data['lname'] ?? '',
                'salutation' => $data['salutation'] ?? '',
                'dob' => $data['dob'] ?? '',
                'status' => $data['status'] ?? '',
                'session' => $data['session'] ?? '',
                'token' => $data['token'] ?? '',
                'request' => $data['request'] ?? '',
                'response' => $data['response'] ?? '',
                'added_by' => $data['added_by'] ?? '',
                'airline' => $data['airline'] ?? '',
                'order_date' => $data['order_date'] ?? null,
                'travel_date' => $data['travel_date'] ?? null,
                'pax_type' => $data['pax_type'] ?? null,
                'pax_id' => $data['pax_id'] ?? null,
                'infant_booking_id' => $data['infant_booking_id'] ?? null,
                'added_on' => $data['added_on'] ?? date('Y-m-d H:i:s')
            ];
            
            $this->execute($query, $params);
            return (int)$this->lastInsertId();
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::createLogRecord error: " . $e->getMessage());
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Base table or view not found") !== false) {
                throw new Exception("Table 'wpk4_amadeus_name_update_log' not found. Please check if the table exists in the database or if you need to use a different database connection.", 500);
            }
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get distinct airlines
     * 
     * @return array
     */
    public function getDistinctAirlines()
    {
        try {
            $query = "SELECT DISTINCT airline FROM wpk4_amadeus_name_update_log WHERE airline IS NOT NULL AND airline != ''";
            $result = $this->query($query);
            return array_column($result, 'airline');
        } catch (Exception $e) {
            error_log("AmadeusLogDAL::getDistinctAirlines error: " . $e->getMessage());
            return [];
        }
    }
}

