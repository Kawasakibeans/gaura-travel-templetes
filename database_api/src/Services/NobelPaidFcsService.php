<?php
/**
 * Nobel Paid FCS Service
 * Business logic for updating paid FCS status
 */

namespace App\Services;

use App\DAL\NobelPaidFcsDAL;
use Exception;

class NobelPaidFcsService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NobelPaidFcsDAL();
    }

    /**
     * Update paid FCS status for matching bookings
     */
    public function updatePaidFcs()
    {
        $currentTime = date('Y-m-d H:i:s');
        $totalProcessed = 0;
        $recordsUpdated = 0;
        $logData = [];

        // Get FCS records that need processing
        $fcsRecords = $this->dal->getFcsRecordsForUpdate();

        foreach ($fcsRecords as $record) {
            $totalProcessed++;

            $autoId = $record['auto_id'];
            $rowid = $record['rowid'];
            $recStatus = $record['rec_status'];
            $callDate = $record['call_date'];
            $callStartTime = $record['call_time'];
            $callDuration = $record['rec_duration'];
            $callFullTime = $callDate . ' ' . $callStartTime;
            $callEndTime = $record['end_time'];
            $callEndFullTime = $callDate . ' ' . $callEndTime;

            // Calculate call end time with duration
            $dateTime = new \DateTime($callFullTime);
            $dateTime->modify("+{$callDuration} seconds");
            $dateTime->modify('+1 hour');
            $callExtraTime = $dateTime->format('Y-m-d H:i:s');

            $phoneCountryCode = $record['country_id'];
            $phoneAreacode = $record['areacode'];
            $phoneNumber = $record['phone'];
            $tsr = $record['tsr'];

            // Check for matching bookings
            $bookings = $this->dal->getMatchingBookings(
                $tsr,
                $phoneNumber,
                $callFullTime,
                $callExtraTime
            );

            if (!empty($bookings)) {
                // Update realtime table
                $this->dal->updateRealtimePaidFcs($autoId, $currentTime);

                // Update main call_rec table
                $this->dal->updateCallRecPaidFcs($callDate, $rowid, $currentTime);

                $recordsUpdated++;

                $logData[] = [
                    'rowid' => $rowid,
                    'tsr' => $tsr,
                    'phone' => "$phoneCountryCode $phoneAreacode $phoneNumber",
                    'call_start' => $callFullTime,
                    'call_end' => $callExtraTime,
                    'status' => 'updated'
                ];
            } else {
                $logData[] = [
                    'rowid' => $rowid,
                    'tsr' => $tsr,
                    'phone' => "$phoneCountryCode $phoneAreacode $phoneNumber",
                    'call_start' => $callFullTime,
                    'call_end' => $callExtraTime,
                    'status' => 'skipped'
                ];
            }
        }

        return [
            'total_processed' => $totalProcessed,
            'records_updated' => $recordsUpdated,
            'records' => $logData,
            'query_date' => date('Y-m-d')
        ];
    }
}

