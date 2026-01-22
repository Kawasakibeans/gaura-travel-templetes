<?php
/**
 * Nobel TSK TSR Day Report DAL
 * Data Access Layer for TSR day report operations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NobelTsksrdayReportDAL extends BaseDAL
{
    /**
     * Get TSR day report data for a specific date
     */
    public function getTsrDayReportData(string $callDate): array
    {
        $sql = "
            SELECT
                t.tsr,
                SUM(t3.tot_calls) AS GTIB,
                SUM(t4.tot_calls) AS GTMD,
                MIN(t2.logon_time) AS logontime,
                MAX(t.logoff_time) AS logofftime,
                SEC_TO_TIME(SUM(t.time_connect)) AS connect,
                SEC_TO_TIME(SUM(t.time_paused)) AS paused,
                SEC_TO_TIME(SUM(t.time_deassigned)) AS deassign,
                SEC_TO_TIME(SUM(t.time_deassigned) + SUM(t.time_connect) + SUM(t.time_paused) + SUM(t.time_waiting) + SUM(t.time_acw)) AS total
            FROM wpk4_backend_agent_nobel_data_tsktsrday_realtime t
            LEFT JOIN (
                SELECT tsr, MIN(auto_id) AS min_id, MIN(call_date) AS call_date, MAX(logon_time) AS logon_time
                FROM wpk4_backend_agent_nobel_data_tsktsrday_realtime
                GROUP BY tsr
            ) t2 ON t.auto_id = t2.min_id
            LEFT JOIN wpk4_backend_agent_nobel_data_tsktsrday_realtime t3 ON t.auto_id = t3.auto_id 
                AND t3.appl = 'GTIB' 
                AND t3.tot_calls > 0
            LEFT JOIN wpk4_backend_agent_nobel_data_tsktsrday_realtime t4 ON t.auto_id = t4.auto_id 
                AND t4.appl = 'GTMD' 
                AND t4.tot_calls > 0
            WHERE (t3.tsr IS NOT NULL OR t4.tsr IS NOT NULL) 
                AND t.call_date = ?
            GROUP BY t.tsr
        ";
        
        return $this->query($sql, [$callDate]);
    }

    /**
     * Insert TSR day report record
     */
    public function insertTsrDayReport(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_agent_nobel_data_tsksrday_report
            (date, tsr, gtib, gtmd, logontime, logofftime, connect, paused, deassign, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->execute($sql, [
            $data['date'],
            $data['tsr'],
            $data['gtib'],
            $data['gtmd'],
            $data['logontime'],
            $data['logofftime'],
            $data['connect'],
            $data['paused'],
            $data['deassign'],
            $data['total']
        ]);
    }
}

