<?php
/**
 * Customer Call Activity Data Access Layer
 * Handles database operations for customer call activity updates
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerCallActivityDAL extends BaseDAL
{
    /**
     * Get customer info phone to CRN mapping
     */
    public function getCustomerPhoneMap()
    {
        $query = "
            SELECT crn, phone
            FROM wpk4_backend_customer_info
            WHERE phone IS NOT NULL AND TRIM(phone) <> ''
        ";
        return $this->query($query);
    }

    /**
     * Get inbound call records within date window
     */
    public function getInboundCallsByDateWindow($from, $to, $columns)
    {
        $colRecId = $columns['record_id'] ?? null;
        $colCallDate = $columns['call_date'] ?? 'call_date';
        $colCallTime = $columns['call_time'] ?? null;
        $colCountryId = $columns['ani_country_id'] ?? 'ani_country_id';
        $colAcode = $columns['ani_acode'] ?? 'ani_acode';
        $colPhone = $columns['ani_phone'] ?? 'ani_phone';
        $colTsr = $columns['tsr'] ?? null;
        $colAppl = $columns['appl'] ?? null;
        $colHold = $columns['time_holding'] ?? null;
        $colConn = $columns['time_connect'] ?? null;
        $colAcwork = $columns['time_acwork'] ?? null;

        if ($colCallTime) {
            $query = "
                SELECT
                    " . ($colRecId ? "`{$colRecId}` AS record_id" : "NULL AS record_id") . ",
                    `{$colCallDate}` AS call_date,
                    `{$colCallTime}` AS call_time,
                    `{$colCountryId}` AS ani_country_id,
                    `{$colAcode}` AS ani_acode,
                    `{$colPhone}` AS ani_phone,
                    " . ($colTsr ? "`{$colTsr}` AS tsr" : "NULL AS tsr") . ",
                    " . ($colAppl ? "`{$colAppl}` AS appl" : "NULL AS appl") . ",
                    " . ($colHold ? "`{$colHold}` AS time_holding" : "0 AS time_holding") . ",
                    " . ($colConn ? "`{$colConn}` AS time_connect" : "0 AS time_connect") . ",
                    " . ($colAcwork ? "`{$colAcwork}` AS time_acwork" : "0 AS time_acwork") . "
                FROM wpk4_backend_agent_nobel_data_inboundcall_rec
                WHERE CONCAT(`{$colCallDate}`,' ',`{$colCallTime}`) BETWEEN :from AND :to
                ORDER BY `{$colCallDate}` ASC, `{$colCallTime}` ASC
            ";
        } else {
            $query = "
                SELECT
                    " . ($colRecId ? "`{$colRecId}` AS record_id" : "NULL AS record_id") . ",
                    `{$colCallDate}` AS call_date,
                    NULL AS call_time,
                    `{$colCountryId}` AS ani_country_id,
                    `{$colAcode}` AS ani_acode,
                    `{$colPhone}` AS ani_phone,
                    " . ($colTsr ? "`{$colTsr}` AS tsr" : "NULL AS tsr") . ",
                    " . ($colAppl ? "`{$colAppl}` AS appl" : "NULL AS appl") . ",
                    " . ($colHold ? "`{$colHold}` AS time_holding" : "0 AS time_holding") . ",
                    " . ($colConn ? "`{$colConn}` AS time_connect" : "0 AS time_connect") . ",
                    " . ($colAcwork ? "`{$colAcwork}` AS time_acwork" : "0 AS time_acwork") . "
                FROM wpk4_backend_agent_nobel_data_inboundcall_rec
                WHERE `{$colCallDate}` BETWEEN :from AND :to
                ORDER BY `{$colCallDate}` ASC
            ";
        }
        
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Check if record_id exists in call activity table
     */
    public function recordIdExists($recordId)
    {
        $query = "
            SELECT 1 FROM wpk4_backend_customer_call_activity
            WHERE record_id = :record_id
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['record_id' => $recordId]);
        return $result !== false;
    }

    /**
     * Get existing record_ids in batch
     */
    public function getExistingRecordIds($recordIds)
    {
        if (empty($recordIds)) {
            return [];
        }

        $chunks = array_chunk($recordIds, 1000);
        $results = [];
        
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "
                SELECT record_id FROM wpk4_backend_customer_call_activity
                WHERE record_id IN ({$placeholders})
            ";
            $chunkResults = $this->query($query, $chunk);
            foreach ($chunkResults as $row) {
                $results[] = $row['record_id'];
            }
        }
        
        return $results;
    }

    /**
     * Bulk insert call activity records
     */
    public function bulkInsertCallActivity($records, $columns)
    {
        if (empty($records) || empty($columns)) {
            return 0;
        }

        $chunkSize = 500;
        $totalInserted = 0;

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            $values = [];
            $params = [];
            
            foreach ($chunk as $record) {
                $placeholders = [];
                foreach ($columns as $col) {
                    $placeholders[] = '?';
                    $params[] = $record[$col] ?? null;
                }
                $values[] = '(' . implode(',', $placeholders) . ')';
            }
            
            $columnList = '`' . implode('`, `', $columns) . '`';
            $query = "
                INSERT INTO wpk4_backend_customer_call_activity ({$columnList})
                VALUES " . implode(',', $values)
            ;
            
            $totalInserted += $this->execute($query, $params);
        }
        
        return $totalInserted;
    }

    /**
     * Get inbound table columns
     */
    public function getInboundColumns()
    {
        $query = "SHOW COLUMNS FROM wpk4_backend_agent_nobel_data_inboundcall_rec";
        $results = $this->query($query);
        $columns = [];
        foreach ($results as $row) {
            $columns[strtolower($row['Field'])] = $row['Field'];
        }
        return $columns;
    }

    /**
     * Get call activity table columns
     */
    public function getCallActivityColumns()
    {
        $query = "SHOW COLUMNS FROM wpk4_backend_customer_call_activity";
        $results = $this->query($query);
        return array_column($results, 'Field');
    }
}

