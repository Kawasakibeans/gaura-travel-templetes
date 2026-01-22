<?php

namespace App\DAL;

use PDO;

class NobelPostgresCallTaskDAL
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
     * Get last rowid from MySQL table
     */
    public function getLastRowid($tableName)
    {
        $query = "SELECT rowid FROM $tableName ORDER BY auto_id DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['rowid'] : 0;
    }

    /**
     * Get data from PostgreSQL addistats table
     * Line: 58-104 (in template)
     */
    public function getAddistatsData($lastRowid = 0, $limit = 30)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM addistats WHERE rowid > :last_rowid ORDER BY rowid ASC LIMIT :limit";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':last_rowid', $lastRowid, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert addistats data into MySQL
     * Line: 86-98 (in template)
     */
    public function insertAddistatsData($data, $tableName = 'wpk4_backend_agent_nobel_data_addistats')
    {
        $columns = [
            'rowid', 'pappl', 'pstatus', 'addistatus', 'stat_num', 'description', 'playmsg_num', 'game_chance'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Get data from PostgreSQL appl_status table
     * Line: 108-154 (in template)
     */
    public function getApplStatusData($lastRowid = 0, $limit = 30)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM appl_status WHERE rowid > :last_rowid ORDER BY rowid ASC LIMIT :limit";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':last_rowid', $lastRowid, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert appl_status data into MySQL
     * Line: 136-148 (in template)
     */
    public function insertApplStatusData($data, $tableName = 'wpk4_backend_agent_nobel_data_appl_status')
    {
        $columns = [
            'rowid', 'appl', 'status_num', 'status', 'description', 'playmsg_num', 'game_chance'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Get data from PostgreSQL call_history table
     * Line: 228-319 (in template)
     */
    public function getCallHistoryData($lastRowid = 0, $limit = 10)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM call_history WHERE rowid > :last_rowid ORDER BY rowid ASC LIMIT :limit";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':last_rowid', $lastRowid, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert call_history data into MySQL
     * Line: 293-313 (in template)
     */
    public function insertCallHistoryData($data, $tableName = 'wpk4_backend_agent_nobel_data_call_history')
    {
        // Convert date format from d/m/Y to Y-m-d
        $dateColumns = ['act_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        $columns = [
            'rowid', 'seqno', 'call_type', 'lm_rowid', 'cb_rowid', 'listid', 'appl', 'lm_filler1', 'lm_filler2',
            'lm_filler3', 'lm_filler4', 'act_date', 'act_time', 'areacode', 'phone', 'tsr', 'info', 'country_id',
            'time_connect', 'time_acw', 'addi_status', 'status', 'time_hold', 'd_record_id', 'd_device_id',
            'caller_ani', 'caller_name', 'phone_type', 'phone_descr', 'grp', 'compliance', 'delayed_connect', 'ucid'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }
}

