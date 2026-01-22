<?php

namespace App\DAL;

use PDO;

class NobelPostgresQuestDAL
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
     * Check if rowid exists in MySQL table
     */
    public function checkRowidExists($tableName, $rowid)
    {
        $query = "SELECT rowid FROM $tableName WHERE rowid = :rowid";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':rowid', $rowid);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Get data from PostgreSQL hmny_qa_eval_quest table
     * Line: 50-141 (in template)
     */
    public function getHmnyQaEvalQuestData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM hmny_qa_eval_quest WHERE qa_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert QA evaluation quest data into MySQL
     * Line: 110-135 (in template)
     */
    public function insertQaEvalQuestData($data, $targetTable = null)
    {
        // Convert date format from d/m/Y to Y-m-d
        $dateColumns = ['qa_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on qa_date
        if ($targetTable === null && isset($data['qa_date'])) {
            $date = new \DateTime($data['qa_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = "wpk4_backend_agent_nobel_data_qa_evaluation_quest_{$month}_{$year}";
        }
        
        $baseTable = 'wpk4_backend_agent_nobel_data_qa_evaluation_quest';
        
        $columns = [
            'rowid', 'id', 'eval_id', 'question_id', 'revision_id', 'question_txt', 'question_type', 'notes_id',
            'excluded', 'checkpoint_reached', 'category_id', 'category_desc', 'position', 'answer_id', 'ans_text',
            'score', 'max_value', 'violation', 'auto_zero', 'qa_date', 'filler_field', 'auto_graded'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        // Insert into base table
        $query = "INSERT INTO $baseTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified and different
        if ($targetTable && $targetTable !== $baseTable) {
            $this->createTableIfNotExists($targetTable, $baseTable);
            $query2 = "INSERT INTO $targetTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt2 = $this->db->prepare($query2);
            foreach ($values as $key => $value) {
                $stmt2->bindValue($key, $value);
            }
            $stmt2->execute();
        }
        
        return $result;
    }

    /**
     * Get data from PostgreSQL hmny_qa_eval_notes table
     * Line: 145-215 (in template)
     */
    public function getHmnyQaEvalNotesData($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        // Note: The template uses date() function in PostgreSQL query
        $query = "SELECT * FROM hmny_qa_eval_notes WHERE date(qa_date) = :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert QA evaluation notes data into MySQL
     * Line: 184-209 (in template)
     */
    public function insertQaEvalNotesData($data, $targetTable = null)
    {
        // Convert date format from d/m/Y to Y-m-d
        $dateColumns = ['qa_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on qa_date
        if ($targetTable === null && isset($data['qa_date'])) {
            $date = new \DateTime($data['qa_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = "wpk4_backend_agent_nobel_data_qa_evaluation_notes_{$month}_{$year}";
        }
        
        $baseTable = 'wpk4_backend_agent_nobel_data_qa_evaluation_notes';
        
        // Get all columns from the data array
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        // Insert into base table
        $query = "INSERT INTO $baseTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified and different
        if ($targetTable && $targetTable !== $baseTable) {
            $this->createTableIfNotExists($targetTable, $baseTable);
            $query2 = "INSERT INTO $targetTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt2 = $this->db->prepare($query2);
            foreach ($values as $key => $value) {
                $stmt2->bindValue($key, $value);
            }
            $stmt2->execute();
        }
        
        return $result;
    }

    /**
     * Get data from PostgreSQL hmny_qa_eval table
     * Line: 219-287 (in template)
     */
    public function getHmnyQaEvalData($captureFromDateYmd)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM hmny_qa_eval WHERE qa_date >= :capture_date";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDateYmd);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert QA evaluation data into MySQL
     * Line: 255-280 (in template)
     */
    public function insertQaEvalData($data, $targetTable = null)
    {
        // Convert date format from d/m/Y to Y-m-d
        $dateColumns = ['qa_date', 'recording_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on qa_date
        if ($targetTable === null && isset($data['qa_date'])) {
            $date = new \DateTime($data['qa_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = "wpk4_backend_agent_nobel_data_qa_evaluation_{$month}_{$year}";
        }
        
        $baseTable = 'wpk4_backend_agent_nobel_data_qa_evaluation';
        
        // Get all columns from the data array
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        // Insert into base table
        $query = "INSERT INTO $baseTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified and different
        if ($targetTable && $targetTable !== $baseTable) {
            $this->createTableIfNotExists($targetTable, $baseTable);
            $query2 = "INSERT INTO $targetTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt2 = $this->db->prepare($query2);
            foreach ($values as $key => $value) {
                $stmt2->bindValue($key, $value);
            }
            $stmt2->execute();
        }
        
        return $result;
    }

    /**
     * Create table if not exists (for monthly tables)
     */
    private function createTableIfNotExists($targetTable, $sourceTable)
    {
        $query = "CREATE TABLE IF NOT EXISTS $targetTable LIKE $sourceTable";
        $this->db->exec($query);
    }
}

