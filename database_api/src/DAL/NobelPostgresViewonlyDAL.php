<?php

namespace App\DAL;

use PDO;

class NobelPostgresViewonlyDAL
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
     * Line: 52-56 (in template)
     */
    public function getExistingRowids($dateValue)
    {
        $query = "SELECT rowid FROM wpk4_backend_agent_nobel_data_travel WHERE call_date >= :date_value";
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
     * Get cust_ob_inb_hst data from PostgreSQL (viewonly - no insert)
     * Line: 59-164 (in template)
     */
    public function getCustObInbHstData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM cust_ob_inb_hst WHERE call_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert date formats
        foreach ($rows as &$row) {
            $dateColumns = ['call_date', 'va_date', 'exp_date', 'appt_date', 'travel_date', 'return_date'];
            foreach ($dateColumns as $dateColumn) {
                if (isset($row[$dateColumn]) && $row[$dateColumn]) {
                    $row[$dateColumn] = $this->convertDateFormat($row[$dateColumn]);
                }
            }
        }
        
        return $rows;
    }
}

