<?php

namespace App\DAL;

use PDO;

class NobelPostgresInboundCallDAL
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
     * Line: 35-42 (in template)
     */
    public function getExistingRowids($dateValue)
    {
        $query = "SELECT rowid FROM wpk4_backend_agent_nobel_data_inboundcall_rec_quote WHERE call_date >= :date_value";
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
     * Get inboundlog data from PostgreSQL
     * Line: 45 (in template)
     */
    public function getInboundlogData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM inboundlog WHERE call_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
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
     * Insert inboundcall data into MySQL
     * Line: 70-79 (in template)
     */
    public function insertInboundcallData($row)
    {
        $columns = [
            'rowid', 'record_id', 'call_date', 'call_time', 'ani_phone', 'tsr', 'd_record_id'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $row[$col] ?? '';
        }
        
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_inboundcall_rec_quote (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return true;
    }
}

