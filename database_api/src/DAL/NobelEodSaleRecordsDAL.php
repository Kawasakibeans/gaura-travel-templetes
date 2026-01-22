<?php

namespace App\DAL;

use PDO;

class NobelEodSaleRecordsDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get EOD sale booking data for previous day
     * Line: 10-33 (in template)
     */
    public function getEodSaleBookingData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $query = "SELECT 
                    a.call_date,
                    a.tsr,
                    c.team_name,
                    COUNT(DISTINCT(a.rowid)) as GTIB,
                    COUNT(DISTINCT(b.rowid)) as FCS_count, 
                    (COUNT(DISTINCT(a.rowid))-COUNT(DISTINCT(b.rowid))-COUNT(DISTINCT(a2.rowid))) as non_sales_made, 
                    COUNT(DISTINCT(a2.rowid)) as abandonded,
                    ROUND((COUNT(DISTINCT(b.rowid))/COUNT(DISTINCT(a.rowid)))*100,2) as FCS,
                    SEC_TO_TIME(((SUM(b2.rec_duration)+SUM(a.time_acwork))/COUNT(DISTINCT(a3.rowid)))/60) AS AHT,
                    SUM(b2.rec_duration) as call_duration,
                    c.agent_name,
                    COUNT(DISTINCT CASE WHEN b2.rec_duration < 2700 THEN a.rowid END) AS lt45,
                    COUNT(DISTINCT CASE WHEN b2.rec_duration >= 2700 AND b2.rec_duration < 3600 THEN a.rowid END) AS bt_45_60,
                    COUNT(DISTINCT CASE WHEN b2.rec_duration >= 3600 THEN a.rowid END) AS gt60
                  FROM wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a
                  LEFT JOIN wpk4_backend_agent_nobel_data_call_rec_realtime b 
                      ON a.d_record_id = b.d_record_id AND b.rec_status = 'SL' AND b.appl = 'GTIB'
                  LEFT JOIN wpk4_backend_agent_nobel_data_call_rec_realtime b2 
                      ON a.d_record_id = b2.d_record_id AND b2.appl = 'GTIB'
                  LEFT JOIN wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a2 
                      ON a.record_id = a2.record_id AND a2.tsr = ''
                  LEFT JOIN wpk4_backend_agent_nobel_data_inboundcall_rec_realtime a3 
                      ON a.record_id = a3.record_id AND a3.tsr <> ''
                  LEFT JOIN wpk4_backend_agent_codes c 
                      ON a.tsr = c.tsr
                  WHERE a.appl = 'GTIB' 
                  AND DATE(a.call_date) = :target_date
                  GROUP BY a.call_date, a.tsr, c.team_name, c.agent_name";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':target_date', $targetDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert EOD sale booking record
     * Line: 53-54 (in template)
     */
    public function insertEodSaleBooking($data)
    {
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_eod_sale_booking(
                    call_date, tsr, team_name, GTIB, FCS_count, non_sales_made, 
                    abandonded, FCS, AHT, call_duration, agent_name, lt45, bt_45_60, gt60
                  ) VALUES (
                    :call_date, :tsr, :team_name, :GTIB, :FCS_count, :non_sales_made, 
                    :abandonded, :FCS, :AHT, :call_duration, :agent_name, :lt45, :bt_45_60, :gt60
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':call_date', $data['call_date']);
        $stmt->bindValue(':tsr', $data['tsr'] ?? null);
        $stmt->bindValue(':team_name', $data['team_name'] ?? null);
        $stmt->bindValue(':GTIB', $data['GTIB'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':FCS_count', $data['FCS_count'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':non_sales_made', $data['non_sales_made'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':abandonded', $data['abandonded'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':FCS', $data['FCS'] ?? 0);
        $stmt->bindValue(':AHT', $data['AHT'] ?? null);
        $stmt->bindValue(':call_duration', $data['call_duration'] ?? 0);
        $stmt->bindValue(':agent_name', $data['agent_name'] ?? null);
        $stmt->bindValue(':lt45', $data['lt45'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':bt_45_60', $data['bt_45_60'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':gt60', $data['gt60'] ?? 0, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get EOD sale call data for previous day
     * Line: 60-91 (in template)
     */
    public function getEodSaleCallData($targetDate = null)
    {
        if ($targetDate === null) {
            // Default to yesterday
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $query = "SELECT 
                    a.call_date,
                    a.tsr,
                    SUM(a.time_connect)+SUM(a.time_deassigned)+SUM(a.time_paused)+SUM(a.time_acw) as noble_login_time,
                    SUM(a.time_connect) AS total_call_time,
                    SUM(a.time_deassigned) AS total_idle_time,
                    SUM(a.time_paused) AS total_pause_time,
                    SUM(a2.tot_calls) AS total_gtib_taken,
                    SUM(a2.time_connect) AS total_time_connect,
                    CASE 
                        WHEN SUM(a2.tot_calls) > 0 THEN 
                            SUM(a2.time_connect + a2.time_acw) / SUM(a2.tot_calls)
                        ELSE 
                            0
                    END AS gtib_AHT,
                    CASE 
                        WHEN SUM(a3.tot_calls) > 0 THEN 
                            SUM(a3.time_connect + a3.time_acw) / SUM(a3.tot_calls)
                        ELSE 
                            0
                    END AS oth_AHT,
                    SUM(a3.tot_calls) AS oth_call_taken
                  FROM wpk4_backend_agent_nobel_data_tsktsrday_realtime a
                  LEFT JOIN wpk4_backend_agent_nobel_data_tsktsrday_realtime a2 
                      ON a.rowid = a2.rowid AND a2.appl = 'GTIB'
                  LEFT JOIN wpk4_backend_agent_nobel_data_tsktsrday_realtime a3 
                      ON a.rowid = a3.rowid AND a3.appl <> 'GTIB'
                  WHERE DATE(a.call_date) = :target_date
                  GROUP BY a.tsr, a.call_date";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':target_date', $targetDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert EOD sale call record
     * Line: 109-110 (in template)
     */
    public function insertEodSaleCall($data)
    {
        $query = "INSERT INTO wpk4_backend_agent_nobel_data_eod_sale_call(
                    call_date, tsr, noble_login_time, total_call_time, total_idle_time, 
                    total_pause_time, total_gtib_taken, total_time_connect, gtib_AHT, oth_AHT, oth_call_taken
                  ) VALUES (
                    :call_date, :tsr, :noble_login_time, :total_call_time, :total_idle_time, 
                    :total_pause_time, :total_gtib_taken, :total_time_connect, :gtib_AHT, :oth_AHT, :oth_call_taken
                  )";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':call_date', $data['call_date']);
        $stmt->bindValue(':tsr', $data['tsr'] ?? null);
        $stmt->bindValue(':noble_login_time', $data['noble_login_time'] ?? 0);
        $stmt->bindValue(':total_call_time', $data['total_call_time'] ?? 0);
        $stmt->bindValue(':total_idle_time', $data['total_idle_time'] ?? 0);
        $stmt->bindValue(':total_pause_time', $data['total_pause_time'] ?? 0);
        $stmt->bindValue(':total_gtib_taken', $data['total_gtib_taken'] ?? 0);
        $stmt->bindValue(':total_time_connect', $data['total_time_connect'] ?? 0);
        $stmt->bindValue(':gtib_AHT', $data['gtib_AHT'] ?? 0);
        $stmt->bindValue(':oth_AHT', $data['oth_AHT'] ?? 0);
        $stmt->bindValue(':oth_call_taken', $data['oth_call_taken'] ?? 0);
        
        return $stmt->execute();
    }
}

