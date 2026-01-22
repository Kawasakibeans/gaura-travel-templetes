<?php

namespace App\DAL;

use PDO;

class NobelPostgresCaptureDAL
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
     * Get data from PostgreSQL cust_ob_inb_hst table
     * Line: 59-206 (in template)
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
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert travel data into MySQL
     * Line: 172-200 (in template)
     */
    public function insertTravelData($data, $targetTable = null)
    {
        $baseTable = 'wpk4_backend_agent_nobel_data_travel';
        
        // Convert date format from d/m/Y to Y-m-d
        $dateColumns = ['call_date', 'va_date', 'exp_date', 'appt_date', 'travel_date', 'return_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on call_date
        if ($targetTable === null && isset($data['call_date'])) {
            $date = new \DateTime($data['call_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = "wpk4_backend_agent_nobel_data_travel_{$month}_{$year}";
        }
        
        // Insert into base table
        $columns = [
            'rowid', 'record_id', 'listid', 'country_id', 'areacode', 'phone', 'sname', 'address1', 'address2',
            'city', 'state', 'zip', 'zip4', 'postal_code', 'county', 'status', 'addi_status', 'appl', 'call_date',
            'call_time', 'tsr', 'attempt_counter', 'time_zone', 'call_duration', 'call_finish', 'file_num',
            'rec_duration', 'rec_ver_tsr', 'va_date', 'va_time', 'rec_status', 'rec_addi_status', 'station',
            'ipaddress', 'dialed_countryid', 'dialed_areacode', 'dialed_phone', 'dialed_phonetype', 'dialed_phonedesc',
            'ticket_num', 'processor_id', 'cc_name', 'cc_adrs', 'cc_zip', 'cc_num', 'exp_date', 'cc_type', 'amount',
            'result', 'auth_code', 'trans_num', 'appt_date', 'appt_time', 'appt_day', 'appt_ampm', 'office', 'sched_id',
            'search_field', 'first_name', 'last_name', 'calling_phone', 'email_id', 'travel_from', 'travel_to',
            'travel_date', 'return_date', 'no_of_pax', 'travel_type', 'profile_order', 'call_feedback', 'agent_name',
            'remarks', 'passport_type', 'email_contant', 'pax_source'
        ];
        
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
            $values[':' . $col] = $data[$col] ?? null;
        }
        
        $query = "INSERT INTO $baseTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($query);
        
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified
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
     * Get data from PostgreSQL rec_playint table
     * Line: 210-336, 449-556 (in template)
     */
    public function getRecPlayintData($captureFromDate, $includeTimeFilter = false)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM rec_playint WHERE call_date >= :capture_date AND rec_status IS NOT NULL";
        if ($includeTimeFilter) {
            $query .= " AND call_time::time <= (NOW()::time - INTERVAL '6 minutes')";
        }
        
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert call rec data into MySQL
     * Line: 303-330 (in template)
     */
    public function insertCallRecData($data, $tableName = 'wpk4_backend_agent_nobel_data_call_rec', $targetTable = null)
    {
        // Convert date format
        $dateColumns = ['call_date', 'va_date', 'exp_date', 'appt_date', 'travel_date', 'return_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on call_date
        if ($targetTable === null && isset($data['call_date'])) {
            $date = new \DateTime($data['call_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = str_replace('wpk4_backend_agent_nobel_data_call_rec', "wpk4_backend_agent_nobel_data_call_rec_{$month}_{$year}", $tableName);
        }
        
        $columns = [
            'rowid', 'file_num', 'office_no', 'order_num', 'listid', 'appl', 'country_id', 'areacode', 'phone',
            'call_date', 'call_time', 'tsr', 'sname', 'address1', 'address2', 'city', 'state', 'zip', 'zip4',
            'postal_code', 'county', 'rec_ver_tsr', 'va_date', 'va_time', 'rec_status', 'rec_addi_status',
            'filler1', 'filler2', 'start_time', 'end_time', 'rec_duration', 'archive_no', 'archive_date',
            'archive_time', 'archive_status', 'sample_rate', 'f_path', 'filler3', 'filler4', 'station',
            'device_name', 'vox_type', 'scr_file_name', 'scr_device_name', 'vox_file_name', 'qa_status',
            'dept_code', 'dept_mgr', 'file_name', 'd_record_id', 'd_device_id', 'contact_type', 'rec_picture',
            'finished', 'msg_type_id', 'scr_rec_duration'
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
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified and different
        if ($targetTable && $targetTable !== $tableName) {
            $this->createTableIfNotExists($targetTable, $tableName);
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
     * Get data from PostgreSQL inboundlog table
     * Line: 340-444, 560-643 (in template)
     */
    public function getInboundlogData($captureFromDate, $includeTimeFilter = false)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM inboundlog WHERE call_date >= :capture_date";
        if ($includeTimeFilter) {
            $query .= " AND call_time::time <= (NOW()::time - INTERVAL '6 minutes')";
        }
        
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert inboundcall rec data into MySQL
     * Line: 406-438 (in template)
     */
    public function insertInboundcallRecData($data, $tableName = 'wpk4_backend_agent_nobel_data_inboundcall_rec', $targetTable = null)
    {
        // Convert date format
        $dateColumns = ['call_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        // Determine target table based on call_date
        if ($targetTable === null && isset($data['call_date'])) {
            $date = new \DateTime($data['call_date']);
            $month = strtolower($date->format('F'));
            $year = $date->format('Y');
            $targetTable = str_replace('wpk4_backend_agent_nobel_data_inboundcall_rec', "wpk4_backend_agent_nobel_data_inboundcall_rec_{$month}_{$year}", $tableName);
        }
        
        $columns = [
            'rowid', 'record_id', 'call_date', 'call_time', 'ani_acode', 'ani_phone', 'dnis_phone', 'tsr', 'status',
            'time_holding', 'listid', 'ani_country_id', 'time_connect', 'time_acwork', 'call_type', 'orig_recid',
            'filler', 'appl', 'd_record_id', 'd_device_id', 'filler1', 'filler2', 'filler3', 'filler4',
            'addi_status', 'cmpl_call', 'dnis_rollover', 'ucid'
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
        
        $result = $stmt->execute();
        
        // Also insert into monthly table if target table is specified and different
        if ($targetTable && $targetTable !== $tableName) {
            $this->createTableIfNotExists($targetTable, $tableName);
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
     * Get data from PostgreSQL tsktsrday table
     * Line: 647-744 (in template)
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
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert tsktsrday data into MySQL
     * Line: 713-738 (in template)
     */
    public function insertTsktsrdayData($data, $tableName = 'wpk4_backend_agent_nobel_data_tsktsrday')
    {
        // Convert date format
        $dateColumns = ['call_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
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
     * Get data from PostgreSQL tskpauday table
     * Line: 748-821 (in template)
     */
    public function getTskpaudayData($lastRowid = 0)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM tskpauday WHERE rowid > :last_rowid ORDER BY rowid ASC";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':last_rowid', $lastRowid, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert tskpauday data into MySQL
     * Line: 790-815 (in template)
     */
    public function insertTskpaudayData($data, $tableName = 'wpk4_backend_agent_nobel_data_tskpauday')
    {
        // Convert date format
        $dateColumns = ['call_date'];
        foreach ($dateColumns as $dateColumn) {
            if (isset($data[$dateColumn]) && $data[$dateColumn]) {
                $dateTime = \DateTime::createFromFormat('d/m/Y', $data[$dateColumn]);
                if ($dateTime) {
                    $data[$dateColumn] = $dateTime->format('Y-m-d');
                }
            }
        }
        
        $columns = [
            'rowid', 'call_date', 'end_time', 'tsr', 'pause_code', 'pause_time', 'appl', 'elem_type',
            'state_num', 'asn', 'device'
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
     * Get rec_playint data with SL status for updates
     * Line: 825-898 (in template)
     */
    public function getRecPlayintForUpdate($captureFromDate)
    {
        if (!$this->pgDb) {
            throw new \Exception('PostgreSQL connection not available', 500);
        }
        
        $query = "SELECT * FROM rec_playint WHERE call_date >= :capture_date AND rec_status = 'SL'";
        $stmt = $this->pgDb->prepare($query);
        $stmt->bindValue(':capture_date', $captureFromDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update call rec realtime status
     * Line: 868-892 (in template)
     */
    public function updateCallRecRealtimeStatus($rowid, $recStatus, $endTime, $recDuration, $callDate)
    {
        $currentTime = date('Y-m-d H:i:s');
        
        // Update realtime table
        $query1 = "UPDATE wpk4_backend_agent_nobel_data_call_rec_realtime 
                   SET rec_status = :rec_status, updated_on = :updated_on, end_time = :end_time, rec_duration = :rec_duration 
                   WHERE rowid = :rowid";
        $stmt1 = $this->db->prepare($query1);
        $stmt1->bindValue(':rec_status', $recStatus);
        $stmt1->bindValue(':updated_on', $currentTime);
        $stmt1->bindValue(':end_time', $endTime);
        $stmt1->bindValue(':rec_duration', $recDuration);
        $stmt1->bindValue(':rowid', $rowid);
        $result1 = $stmt1->execute();
        
        // Update main table
        $query2 = "UPDATE wpk4_backend_agent_nobel_data_call_rec 
                   SET rec_status = :rec_status, updated_on = :updated_on, end_time = :end_time, rec_duration = :rec_duration 
                   WHERE call_date = :call_date AND rowid = :rowid";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bindValue(':rec_status', $recStatus);
        $stmt2->bindValue(':updated_on', $currentTime);
        $stmt2->bindValue(':end_time', $endTime);
        $stmt2->bindValue(':rec_duration', $recDuration);
        $stmt2->bindValue(':call_date', $callDate);
        $stmt2->bindValue(':rowid', $rowid);
        $result2 = $stmt2->execute();
        
        return $result1 && $result2;
    }

    /**
     * Get existing rowids for call rec realtime that need updates
     * Line: 834 (in template)
     */
    public function getCallRecRealtimeRowidsForUpdate()
    {
        $query = "SELECT rowid FROM wpk4_backend_agent_nobel_data_call_rec_realtime 
                  WHERE rec_status != 'SL' AND (appl = 'GTIB' OR appl = 'GTMD' OR appl = 'GTCB') 
                  ORDER BY auto_id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $rowids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowids[] = $row['rowid'];
        }
        return $rowids;
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

