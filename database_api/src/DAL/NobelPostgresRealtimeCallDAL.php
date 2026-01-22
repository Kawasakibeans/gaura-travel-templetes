<?php

namespace App\DAL;

use PDO;

class NobelPostgresRealtimeCallDAL
{
    private $db; // MySQL connection
    private $pgDb; // PostgreSQL connection

    public function __construct($db, $pgConnectionString = null)
    {
        $this->db = $db;
        
        // Default PostgreSQL connection if not provided
        if ($pgConnectionString === null) {
            $pgConnectionString = 'pgsql:host=192.168.0.41;port=5432;dbname=task;user=oztele;password=pass1234';
        }
        
        try {
            $this->pgDb = new PDO($pgConnectionString);
            $this->pgDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->pgDb = null;
        }
    }

    /**
     * Get existing rowids from MySQL table
     */
    public function getExistingRowids($tableName, $dateColumn, $dateValue)
    {
        $query = "SELECT rowid FROM $tableName WHERE $dateColumn >= :date_value";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':date_value', $dateValue);
        $stmt->execute();
        
        $rowids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowids[] = $row['rowid'];
        }
        return $rowids;
    }

    /**
     * Convert date format from d/m/Y to Y-m-d
     */
    private function convertDateFormat($dateString)
    {
        if (!$dateString) {
            return $dateString;
        }
        
        $dateTime = \DateTime::createFromFormat('d/m/Y', $dateString);
        if ($dateTime) {
            return $dateTime->format('Y-m-d');
        }
        return $dateString;
    }

    /**
     * Get call log master data from PostgreSQL
     * Line: 68 (in template)
     */
    public function getCallLogMasterData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM tsk_call_log_mstr WHERE sys_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert call log master data into MySQL
     * Line: 126-158 (in template)
     */
    public function insertCallLogMasterData($row)
    {
        // Convert date formats
        $dateColumns = ['sys_date', 'tel_call_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($row[$dateColumn]) && $row[$dateColumn]) {
                $row[$dateColumn] = $this->convertDateFormat($row[$dateColumn]);
            }
        }
        
        $columns = [
            'rowid', 'mstr_id', 'device_name', 'switch_device', 'cdr_id', 'dnis', 'lineno', 'pno', 
            'appl', 'listid', 'lm_rowid', 'cb_rowid', 'call_type', 'grp', 'country_id', 'areacode', 
            'phone', 'dialed_num', 'ani', 'call_duration', 'sys_date', 'sys_time', 'tel_call_date', 
            'tel_call_time', 'hangup_source', 'disconnect_reason', 'tel_technology', 'ipd_id', 
            'ipd_name', 'sessionid'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $row[$col] ?? '';
        }
        
        // Insert into main table
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_master (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Insert into realtime table
        $query2 = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_master_realtime (" . implode(', ', $columns) . ") 
                  VALUES (" . implode(', ', $placeholders) . ")";
        $stmt2 = $this->db->prepare($query2);
        foreach ($values as $key => $value) {
            $stmt2->bindValue($key, $value);
        }
        $stmt2->execute();
        
        return true;
    }

    /**
     * Get call log sequence data from PostgreSQL
     * Line: 182 (in template)
     */
    public function getCallLogSequenceData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM tsk_call_log_seq WHERE sys_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert call log sequence data into MySQL
     * Line: 223-257 (in template)
     */
    public function insertCallLogSequenceData($row)
    {
        // Convert date formats
        if (isset($row['sys_date']) && $row['sys_date']) {
            $row['sys_date'] = $this->convertDateFormat($row['sys_date']);
        }
        
        $columns = [
            'rowid', 'log_seqno', 'mstr_id', 'device_name', 'switch_device', 'cdr_id', 'dial_desc', 
            'log_event', 'grp', 'tsr', 'term_status', 'term_addistatus', 'playmsg_num', 'sys_date', 'sys_time'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $row[$col] ?? '';
        }
        
        // Insert into main table
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_sequence (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Insert into realtime table
        $query2 = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_sequence_realtime (" . implode(', ', $columns) . ") 
                  VALUES (" . implode(', ', $placeholders) . ")";
        $stmt2 = $this->db->prepare($query2);
        foreach ($values as $key => $value) {
            $stmt2->bindValue($key, $value);
        }
        $stmt2->execute();
        
        return true;
    }

    /**
     * Get callback data from PostgreSQL
     * Line: 279 (in template)
     */
    public function getCallbackData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM callback WHERE cb_adate >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert callback data into MySQL
     * Line: 322-356 (in template)
     */
    public function insertCallbackData($row)
    {
        // Convert date formats
        $dateColumns = ['cb_date', 'cb_adate'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($row[$dateColumn]) && $row[$dateColumn]) {
                $row[$dateColumn] = $this->convertDateFormat($row[$dateColumn]);
            }
        }
        
        $columns = [
            'rowid', 'cb_rowid', 'cb_appl', 'cb_acode', 'cb_phone', 'cb_date', 'cb_time', 'cb_tsr', 
            'cb_status', 'cb_adate', 'cb_atime', 'cb_btries', 'cb_ntries', 'cb_country_id', 'cb_listid', 
            'cb_dnis', 'has_lapsed'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $row[$col] ?? '';
        }
        
        // Insert into main table
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_callback (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Insert into realtime table
        $query2 = "INSERT INTO wpk4_backend_agent_nobel_data_call_log_callback_realtime (" . implode(', ', $columns) . ") 
                  VALUES (" . implode(', ', $placeholders) . ")";
        $stmt2 = $this->db->prepare($query2);
        foreach ($values as $key => $value) {
            $stmt2->bindValue($key, $value);
        }
        $stmt2->execute();
        
        return true;
    }
}

