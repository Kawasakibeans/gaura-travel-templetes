<?php
/**
 * Nobel Paid FCS Data Access Layer
 * Handles database operations for updating paid FCS status in Nobel call records
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NobelPaidFcsDAL extends BaseDAL
{
    /**
     * Get FCS records that need paid status update
     */
    public function getFcsRecordsForUpdate()
    {
        $query = "
            SELECT auto_id, rowid, call_date, call_time, end_time, rec_duration, 
                   country_id, areacode, phone, tsr, rec_status 
            FROM wpk4_backend_agent_nobel_data_call_rec 
            WHERE fcs = 'yes' 
              AND appl IN ('GTMD','GTIB','FTFP')
              AND call_date = CURDATE() - INTERVAL 5 DAY
        ";
        
        return $this->query($query);
    }

    /**
     * Get bookings matching call criteria
     */
    public function getMatchingBookings($tsr, $phoneNumber, $callStartTime, $callEndTime)
    {
        $query = "
            SELECT booking.order_id, booking.order_date, pax.phone_pax, booking.agent_info 
            FROM wpk4_backend_travel_bookings booking 
            LEFT JOIN wpk4_backend_agent_codes agent ON agent.sales_id = booking.agent_info
            LEFT JOIN wpk4_backend_travel_booking_pax pax 
              ON booking.order_id = pax.order_id 
              AND booking.product_id = pax.product_id
            WHERE booking.payment_status = 'paid' 
              AND agent.tsr = :tsr 
              AND booking.order_date > :call_start 
              AND booking.order_date < :call_end 
              AND phone_pax LIKE :phone_pattern
        ";
        
        return $this->query($query, [
            'tsr' => $tsr,
            'call_start' => $callStartTime,
            'call_end' => $callEndTime,
            'phone_pattern' => '%' . $phoneNumber
        ]);
    }

    /**
     * Update paid_fcs status in realtime table
     */
    public function updateRealtimePaidFcs($autoId, $currentTime)
    {
        $query = "
            UPDATE wpk4_backend_agent_nobel_data_call_rec_realtime 
            SET paid_fcs = 'yes', paid_fcs_added_on = :current_time
            WHERE auto_id = :auto_id
        ";
        
        return $this->execute($query, [
            'auto_id' => $autoId,
            'current_time' => $currentTime
        ]);
    }

    /**
     * Update paid_fcs status in main call_rec table
     */
    public function updateCallRecPaidFcs($callDate, $rowid, $currentTime)
    {
        $query = "
            UPDATE wpk4_backend_agent_nobel_data_call_rec 
            SET paid_fcs = 'yes', paid_fcs_added_on = :current_time
            WHERE call_date = :call_date AND rowid = :rowid
        ";
        
        return $this->execute($query, [
            'call_date' => $callDate,
            'rowid' => $rowid,
            'current_time' => $currentTime
        ]);
    }
}

