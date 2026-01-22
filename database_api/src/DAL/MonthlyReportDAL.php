<?php
/**
 * Monthly Report Data Access Layer
 * Handles database operations for FCS inbound call updates
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class MonthlyReportDAL extends BaseDAL
{
    /**
     * Get inbound call data for a specific date
     */
    public function getInboundCallData(string $callDate): array
    {
        $sql = "
            SELECT
                a.call_date,
                a.tsr,
                c.team_name,
                COUNT(a.rowid) AS gtib_count,
                COUNT(DISTINCT CASE WHEN a.fcs = 'yes' THEN a.phone END) AS sale_made_count,
                COUNT(DISTINCT CASE WHEN a.fcs = 'yes' THEN a.phone END) AS new_sale_made_count,
                (COUNT(DISTINCT a.rowid) - COUNT(DISTINCT CASE WHEN a.fcs = 'yes' THEN a.phone END)) AS non_sale_made_count,
                0 AS abandoned,
                ROUND(COUNT(DISTINCT CASE WHEN a.fcs = 'yes' THEN a.rowid END) / NULLIF(COUNT(DISTINCT a.rowid), 0) * 100, 2) AS FCS,
                ROUND(COUNT(DISTINCT CASE WHEN a.rec_status = 'SL' THEN a.rowid END) / NULLIF(COUNT(DISTINCT a.rowid), 0) * 100, 2) AS FCS_old,
                SEC_TO_TIME(AVG(a.rec_duration)) AS aht,
                SUM(a.rec_duration) AS rec_duration,
                c.agent_name,
                DATE_FORMAT(STR_TO_DATE(c.shift_rep_time, '%H:%i:%s'), '%H%i%s') AS shift_time
            FROM
                wpk4_backend_agent_nobel_data_call_rec a
            JOIN
                wpk4_backend_agent_codes c ON a.tsr = c.tsr
            WHERE
                DATE(a.call_date) = :call_date
                AND a.appl = 'GTIB'
            GROUP BY
                a.call_date, a.tsr, c.team_name, c.agent_name, c.shift_rep_time
        ";
        
        return $this->query($sql, [':call_date' => $callDate]);
    }

    /**
     * Check if inbound call record exists
     */
    public function inboundCallExists(string $callDate, string $tsr): bool
    {
        $sql = "SELECT 1 FROM wpk4_backend_agent_inbound_call WHERE call_date = :call_date AND tsr = :tsr LIMIT 1";
        $result = $this->queryOne($sql, [':call_date' => $callDate, ':tsr' => $tsr]);
        return $result !== false && $result !== null;
    }

    /**
     * Get existing inbound call record from destination table
     */
    public function getExistingInboundCall(string $callDate, string $tsr): ?array
    {
        $sql = "
            SELECT call_date, tsr, gtib_count, new_sale_made_count, fcs, aht, rec_duration, shift_time
            FROM wpk4_backend_agent_inbound_call 
            WHERE call_date = :call_date AND tsr = :tsr
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':call_date' => $callDate, ':tsr' => $tsr]);
        return ($result === false) ? null : $result;
    }

    /**
     * Insert inbound call record
     */
    public function insertInboundCall(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_backend_agent_inbound_call (
                call_date, tsr, team_name, gtib_count, sale_made_count, new_sale_made_count,
                non_sale_made_count, abandoned, fcs, fcs_old, aht, rec_duration, agent_name, shift_time
            ) VALUES (
                :call_date, :tsr, :team_name, :gtib_count, :sale_made_count, :new_sale_made_count,
                :non_sale_made_count, :abandoned, :fcs, :fcs_old, :aht, :rec_duration, :agent_name, :shift_time
            )
        ";
        
        $this->execute($sql, [
            ':call_date' => $data['call_date'],
            ':tsr' => $data['tsr'],
            ':team_name' => $data['team_name'],
            ':gtib_count' => $data['gtib_count'],
            ':sale_made_count' => $data['sale_made_count'],
            ':new_sale_made_count' => $data['new_sale_made_count'],
            ':non_sale_made_count' => $data['non_sale_made_count'],
            ':abandoned' => $data['abandoned'],
            ':fcs' => $data['fcs'],
            ':fcs_old' => $data['fcs_old'],
            ':aht' => $data['aht'],
            ':rec_duration' => $data['rec_duration'],
            ':agent_name' => $data['agent_name'],
            ':shift_time' => $data['shift_time']
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Update inbound call record
     */
    public function updateInboundCall(string $callDate, string $tsr, array $data): bool
    {
        $sql = "
            UPDATE wpk4_backend_agent_inbound_call SET
                gtib_count = :gtib_count,
                new_sale_made_count = :new_sale_made_count,
                fcs = :fcs,
                aht = :aht,
                rec_duration = :rec_duration,
                shift_time = :shift_time
            WHERE call_date = :call_date AND tsr = :tsr
        ";
        
        return $this->execute($sql, [
            ':gtib_count' => $data['gtib_count'],
            ':new_sale_made_count' => $data['new_sale_made_count'],
            ':fcs' => $data['fcs'],
            ':aht' => $data['aht'],
            ':rec_duration' => $data['rec_duration'],
            ':shift_time' => $data['shift_time'],
            ':call_date' => $callDate,
            ':tsr' => $tsr
        ]);
    }
}

