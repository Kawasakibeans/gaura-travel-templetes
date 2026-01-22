<?php
/**
 * Nobel Realtime Update FCS Service
 * Business logic for updating FCS status in Nobel call records
 */

namespace App\Services;

use App\DAL\NobelRealtimeUpdateFcsDAL;
use Exception;

class NobelRealtimeUpdateFcsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NobelRealtimeUpdateFcsDAL();
    }

    /**
     * Update FCS status for call records
     */
    public function updateFcsStatus(): array
    {
        $callRecords = $this->dal->getCallRecordsForFcsUpdate();
        $updated = 0;
        $noUpdate = 0;
        $currenttime = date("Y-m-d H:i:s");

        foreach ($callRecords as $record) {
            $autoId = (int)$record['auto_id'];
            $rowId = $record['rowid'];
            $recStatus = $record['rec_status'];
            $callDate = $record['call_date'];
            $callStartTime = $record['call_time'];
            $callDuration = (int)$record['rec_duration'];
            $callFullTime = $callDate . ' ' . $callStartTime;
            
            // Calculate call end time: call start + duration + 1 hour
            $dateTime = new \DateTime($callFullTime);
            $dateTime->modify("+{$callDuration} seconds");
            $dateTime->modify('+1 hour');
            $callEndTime = $dateTime->format('Y-m-d H:i:s');

            $phoneNumber = $record['phone'];
            $tsr = $record['tsr'];

            // Check for matching bookings
            $matchingBooking = $this->dal->getMatchingBookings(
                $tsr,
                $callFullTime,
                $callEndTime,
                $phoneNumber
            );

            // Determine FCS status
            $fcsStatus = 'no';
            if ($matchingBooking !== null || $recStatus == 'SL') {
                $fcsStatus = 'yes';
            }

            // Update realtime table
            $this->dal->updateFcsRealtime($autoId, $fcsStatus, $currenttime);
            
            // Update main table
            $this->dal->updateFcsMain($callDate, $rowId, $fcsStatus, $currenttime);

            if ($fcsStatus === 'yes') {
                $updated++;
            } else {
                $noUpdate++;
            }
        }

        return [
            'status' => 'success',
            'message' => 'Realtime FCS update completed successfully',
            'updated_records' => $updated,
            'unchanged_records' => $noUpdate,
            'total_processed' => count($callRecords),
            'server_time' => $currenttime
        ];
    }
}

