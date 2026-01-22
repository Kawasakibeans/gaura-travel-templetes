<?php
/**
 * Nobel Realtime Update FCS DAL
 * Data Access Layer for updating FCS status in Nobel call records
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NobelRealtimeUpdateFcsDAL extends BaseDAL
{
    /**
     * Get call records that need FCS update
     */
    public function getCallRecordsForFcsUpdate(): array
    {
        $sql = "
            SELECT 
                auto_id, 
                rowid, 
                call_date, 
                call_time, 
                end_time, 
                rec_duration, 
                country_id, 
                areacode, 
                phone, 
                tsr, 
                rec_status 
            FROM wpk4_backend_agent_nobel_data_call_rec
            WHERE (fcs IS NULL OR fcs = 'no') 
                AND appl IN ('GTMD','GTIB','FTFP')
                AND call_date >= CURDATE() - INTERVAL 1 DAY
        ";
        
        return $this->query($sql, []);
    }

    /**
     * Check for matching bookings within time window
     */
    public function getMatchingBookings(string $tsr, string $callStartTime, string $callEndTime, string $phoneNumber): ?array
    {
        $sql = "
            SELECT 
                booking.order_id, 
                booking.order_date, 
                pax.phone_pax, 
                booking.agent_info 
            FROM wpk4_backend_travel_bookings booking 
            LEFT JOIN wpk4_backend_agent_codes agent ON agent.sales_id = booking.agent_info
            LEFT JOIN wpk4_backend_travel_booking_pax pax ON booking.order_id = pax.order_id 
                AND booking.product_id = pax.product_id
            JOIN (
                SELECT order_id
                FROM wpk4_backend_travel_payment_history
                WHERE trams_received_amount <> 0
            ) p ON booking.order_id = p.order_id
            WHERE agent.tsr = ? 
                AND booking.order_date > ? 
                AND booking.order_date < ? 
                AND phone_pax LIKE ?
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [
            $tsr,
            $callStartTime,
            $callEndTime,
            '%' . $phoneNumber
        ]);
        
        return ($result === false) ? null : $result;
    }

    /**
     * Update FCS status in realtime table
     */
    public function updateFcsRealtime(int $autoId, string $fcsStatus, string $fcsAddedDate): bool
    {
        $sql = "
            UPDATE wpk4_backend_agent_nobel_data_call_rec_realtime 
            SET
                fcs = ?,
                fcs_added_date = ?
            WHERE auto_id = ?
        ";
        
        return $this->execute($sql, [$fcsStatus, $fcsAddedDate, $autoId]);
    }

    /**
     * Update FCS status in main table
     */
    public function updateFcsMain(string $callDate, string $rowId, string $fcsStatus, string $fcsAddedDate): bool
    {
        $sql = "
            UPDATE wpk4_backend_agent_nobel_data_call_rec 
            SET
                fcs = ?,
                fcs_added_date = ?
            WHERE call_date = ? AND rowid = ?
        ";
        
        return $this->execute($sql, [$fcsStatus, $fcsAddedDate, $callDate, $rowId]);
    }
}

