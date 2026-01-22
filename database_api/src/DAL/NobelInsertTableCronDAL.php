<?php
/**
 * Nobel Insert Table Cron DAL
 * Data Access Layer for Nobel cronjob operations (agent booking, inbound call inserts, cleanup)
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NobelInsertTableCronDAL extends BaseDAL
{
    /**
     * Get agent booking data for yesterday
     */
    public function getAgentBookingDataForYesterday(): array
    {
        $sql = "
            SELECT
                DATE(a.order_date) AS order_date,
                c.agent_name,
                COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
                COUNT(DISTINCT CONCAT(b2.fname, b2.lname, a.agent_info, DATE(a.order_date))) AS pif,
                COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
                COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
                c.tsr,
                c.team_name
            FROM
                wpk4_backend_travel_bookings a
            LEFT JOIN
                wpk4_backend_travel_booking_pax b
                ON a.order_id = b.order_id
                AND (DATEDIFF(a.travel_date, b.dob) / 365) > 2
                AND DATE(b.order_date) = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.source <> 'import'
            LEFT JOIN
                wpk4_backend_travel_booking_pax b2
                ON a.order_id = b2.order_id
                AND a.payment_status = 'paid'
                AND (DATEDIFF(a.travel_date, b2.dob) / 365) > 2
                AND DATE(b2.order_date) = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.source <> 'import'
            LEFT JOIN
                wpk4_backend_travel_booking_pax b3
                ON a.order_id = b3.order_id
                AND (DATEDIFF(a.travel_date, b3.dob) / 365) > 2  
                AND b3.order_type = 'gds'
                AND DATE(b3.order_date) = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.source <> 'import'
            LEFT JOIN
                wpk4_backend_travel_booking_pax b4
                ON a.order_id = b4.order_id
                AND (DATEDIFF(a.travel_date, b4.dob) / 365) > 2  
                AND b4.order_type = 'wpt'
                AND DATE(b4.order_date) = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.source <> 'import'
            LEFT JOIN
                wpk4_backend_agent_codes c
                ON a.agent_info = c.sales_id
            JOIN
                wpk4_backend_travel_payment_history p
                ON a.order_id = p.order_id
                AND p.trams_received_amount <> 0
            WHERE
                DATE(a.order_date) = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.source <> 'import'
            GROUP BY
                DATE(a.order_date), c.agent_name, c.tsr, c.team_name
        ";
        
        return $this->query($sql, []);
    }

    /**
     * Insert agent booking record
     */
    public function insertAgentBooking(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_agent_booking
            (order_date, agent_name, pax, pif, fit, gdeals, tsr, team_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->execute($sql, [
            $data['order_date'],
            $data['agent_name'],
            $data['pax'],
            $data['pif'],
            $data['fit'],
            $data['gdeals'],
            $data['tsr'],
            $data['team_name']
        ]);
    }

    /**
     * Get agent inbound call data for yesterday
     */
    public function getAgentInboundCallDataForYesterday(): array
    {
        $sql = "
            SELECT
                a.call_date,
                a.tsr,
                c.agent_name,
                c.team_name,
                COUNT(DISTINCT a4.rowid) AS gtib,
                COUNT(DISTINCT CONCAT(a2.phone,a2.tsr)) AS FCS_count,
                COUNT(DISTINCT a3.rowid) AS FCS_count_old,
                (COUNT(DISTINCT a4.rowid) - COUNT(DISTINCT a2.phone)) AS non_sales_made,
                ROUND((COUNT(DISTINCT CONCAT(a2.phone,a2.tsr)) / NULLIF(COUNT(DISTINCT a4.rowid), 0)) * 100, 2) AS FCS,
                ROUND((COUNT(DISTINCT a3.rowid) / NULLIF(COUNT(DISTINCT a4.rowid), 0)) * 100, 2) AS FCS_old,
                SEC_TO_TIME((SUM(a4.rec_duration) / NULLIF(COUNT(DISTINCT a4.rowid), 0)) / 60) AS aht,
                SUM(a4.rec_duration) AS call_duration
            FROM
                wpk4_backend_agent_nobel_data_call_rec_realtime a
            LEFT JOIN
                wpk4_backend_agent_nobel_data_call_rec_realtime a2 ON a2.rowid = a.rowid
                AND a2.fcs = 'yes'
                AND a.call_date = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a.appl = 'GTIB'
            LEFT JOIN
                wpk4_backend_agent_nobel_data_call_rec_realtime a3 ON a3.rowid = a.rowid
                AND a.call_date = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a3.rec_status = 'SL'
                AND a3.appl = 'GTIB'
            LEFT JOIN
                wpk4_backend_agent_nobel_data_call_rec_realtime a4 ON a4.rowid = a.rowid
                AND a.call_date = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
                AND a4.appl = 'GTIB'
            JOIN
                wpk4_backend_agent_codes c ON a.tsr = c.tsr
            WHERE
                a.call_date = DATE(CURRENT_DATE() - INTERVAL 1 DAY)
            GROUP BY
                a.call_date, a.tsr, c.agent_name, c.team_name
        ";
        
        return $this->query($sql, []);
    }

    /**
     * Insert agent inbound call record
     */
    public function insertAgentInboundCall(array $data): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_agent_inbound_call
            (call_date, tsr, team_name, gtib_count, sale_made_count, new_sale_made_count, non_sale_made_count, fcs_old, fcs, aht, rec_duration, agent_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->execute($sql, [
            $data['call_date'],
            $data['tsr'],
            $data['team_name'],
            $data['gtib'],
            $data['fcs'],
            $data['fcs_new'],
            $data['non_sales_made'],
            $data['fcs_count'],
            $data['fcs_count_new'],
            $data['aht'],
            $data['call_duration'],
            $data['agent_name']
        ]);
    }

    /**
     * Cleanup old realtime table records
     */
    public function cleanupRealtimeTables(string $dateBefore): array
    {
        $tables = [
            ['name' => 'wpk4_backend_agent_nobel_data_call_rec_realtime', 'field' => 'call_date'],
            ['name' => 'wpk4_backend_agent_nobel_data_inboundcall_rec_realtime', 'field' => 'call_date'],
            ['name' => 'wpk4_backend_travel_bookings_realtime', 'field' => 'order_date'],
            ['name' => 'wpk4_backend_travel_booking_pax_realtime', 'field' => 'order_date'],
            ['name' => 'wpk4_backend_agent_nobel_data_call_log_master_realtime', 'field' => 'sys_date'],
            ['name' => 'wpk4_backend_agent_nobel_data_call_log_sequence_realtime', 'field' => 'sys_date'],
            ['name' => 'wpk4_backend_agent_nobel_data_call_log_callback_realtime', 'field' => 'cb_adate']
        ];

        $results = [];
        
        foreach ($tables as $tbl) {
            try {
                $sql = "DELETE FROM {$tbl['name']} WHERE DATE({$tbl['field']}) <= ?";
                $this->execute($sql, [$dateBefore]);
                $results[] = ['table' => $tbl['name'], 'status' => 'deleted'];
            } catch (\Exception $e) {
                $results[] = ['table' => $tbl['name'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}

