<?php

namespace App\DAL;

use PDO;

class NobelPostgresTaskDAL
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
     * Line: 50-54 (in template)
     */
    public function getLastRowid()
    {
        $query = "SELECT rowid FROM wpk4_backend_agent_nobel_data_tsktsrday ORDER BY auto_id DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['rowid'] : 0;
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
     * Get tsktsrday data from PostgreSQL
     * Line: 57 (in template)
     */
    public function getTsktsrdayData($lastRowid = 0)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM tsktsrday WHERE rowid > :last_rowid ORDER BY rowid ASC";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':last_rowid', $lastRowid, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            if (isset($row['call_date']) && $row['call_date']) {
                $row['call_date'] = $this->convertDateFormat($row['call_date']);
            }
        }
        
        return $rows;
    }

    /**
     * Insert tsktsrday data into MySQL
     * Line: 112-131 (in template)
     */
    public function insertTsktsrdayData($row)
    {
        $columns = [
            'rowid', 'tsr', 'appl', 'listid', 'call_date', 'tot_calls', 'tot_n', 'tot_b', 'tot_d', 'tot_u', 
            'tot_1', 'tot_2', 'tot_3', 'tot_4', 'tot_5', 'tot_6', 'tot_7', 'tot_8', 'tot_9', 'tot_10', 
            'time_connect', 'time_paused', 'time_waiting', 'time_deassigned', 'logon_time', 'logoff_time', 
            'time_acw', 'device', 'call_type', 'tot_sp', 'tot_fx', 'tot_nc', 'tot_rs'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $row[$col] ?? '';
        }
        
        // Insert into main table
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_tsktsrday (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Insert into realtime table
        $query2 = "INSERT INTO wpk4_backend_agent_nobel_data_tsktsrday_realtime (" . implode(', ', $columns) . ") 
                  VALUES (" . implode(', ', $placeholders) . ")";
        $stmt2 = $this->db->prepare($query2);
        foreach ($values as $key => $value) {
            $stmt2->bindValue($key, $value);
        }
        $stmt2->execute();
        
        return true;
    }
}

