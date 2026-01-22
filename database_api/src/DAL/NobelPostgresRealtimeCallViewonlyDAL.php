<?php

namespace App\DAL;

use PDO;

class NobelPostgresRealtimeCallViewonlyDAL
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
     * Get call log master data count from PostgreSQL
     * Line: 64-67 (in template)
     */
    public function getCallLogMasterCount($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT count(rowid) as counting FROM tsk_call_log_mstr WHERE sys_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['counting'];
    }

    /**
     * Get call log master data from PostgreSQL (viewonly - no insert)
     * Line: 69-132 (in template)
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
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            $dateColumns = ['sys_date', 'tel_call_date'];
            foreach ($dateColumns as $dateColumn) {
                if (isset($row[$dateColumn]) && $row[$dateColumn]) {
                    $row[$dateColumn] = $this->convertDateFormat($row[$dateColumn]);
                }
            }
        }
        
        return $rows;
    }

    /**
     * Get call log sequence data count from PostgreSQL
     * Line: 155-158 (in template)
     */
    public function getCallLogSequenceCount($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT count(rowid) as counting FROM tsk_call_log_seq WHERE sys_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['counting'];
    }

    /**
     * Get call log sequence data from PostgreSQL (viewonly - no insert)
     * Line: 160-211 (in template)
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
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            if (isset($row['sys_date']) && $row['sys_date']) {
                $row['sys_date'] = $this->convertDateFormat($row['sys_date']);
            }
        }
        
        return $rows;
    }

    /**
     * Get callback data count from PostgreSQL
     * Line: 228-231 (in template)
     */
    public function getCallbackCount($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT count(rowid) as counting FROM callback WHERE cb_adate >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['counting'];
    }

    /**
     * Get callback data from PostgreSQL (viewonly - no insert)
     * Line: 233-285 (in template)
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
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            $dateColumns = ['cb_date', 'cb_adate'];
            foreach ($dateColumns as $dateColumn) {
                if (isset($row[$dateColumn]) && $row[$dateColumn]) {
                    $row[$dateColumn] = $this->convertDateFormat($row[$dateColumn]);
                }
            }
        }
        
        return $rows;
    }

    /**
     * Get call history data count from PostgreSQL
     * Line: 302-305 (in template)
     */
    public function getCallHistoryCount($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT count(rowid) as counting FROM call_history WHERE act_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['counting'];
    }

    /**
     * Get call history data from PostgreSQL (viewonly - no insert)
     * Line: 307-375 (in template)
     */
    public function getCallHistoryData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM call_history WHERE act_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            if (isset($row['act_date']) && $row['act_date']) {
                $row['act_date'] = $this->convertDateFormat($row['act_date']);
            }
        }
        
        return $rows;
    }
}

