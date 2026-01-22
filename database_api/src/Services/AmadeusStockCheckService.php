<?php
/**
 * Amadeus Stock Check Service
 * Business logic for Amadeus stock check cronjob
 */

namespace App\Services;

use App\DAL\AmadeusStockCheckDAL;
use Exception;

class AmadeusStockCheckService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AmadeusStockCheckDAL();
    }

    /**
     * Process Amadeus stock check
     */
    public function processStockCheck($startDate = '2025-09-02', $endDate = '2026-03-31')
    {
        $totalInserted = 0;
        $totalUpdatedStep2 = 0;
        $totalUpdatedStep3 = 0;

        // Step 1: Insert/Upsert base rows
        $stockData = $this->dal->getStockDataForCheck($startDate, $endDate);
        
        foreach ($stockData as $row) {
            $data = [
                'pnr' => $row['pnr'],
                'trip_code' => $row['trip_code'],
                'travel_date1' => $row['travel_date1'],
                'ticketing_timelimit' => $row['ticketing_timelimit'],
                'current_stock' => $row['current_stock'],
                'oid' => $row['oid'],
                'paid_pax' => $row['paid_pax'],
                'partially_paid_pax' => $row['partially_paid_pax'],
                'total_pax_count' => $row['total_pax_count']
            ];
            
            $this->dal->upsertStockCheck($data);
            $totalInserted++;
        }

        // Step 2: Update segment #1 times
        $segment1Data = $this->dal->getSegment1Data($startDate, $endDate);
        
        foreach ($segment1Data as $row) {
            $this->dal->updateSegment1Times(
                $row['trip_code'],
                $row['travel_date1'],
                $row['pnr'],
                $row['depart_time1'],
                $row['arrival_time1'],
                $row['segment1']
            );
            $totalUpdatedStep2++;
        }

        // Step 3: Update segment #2 times
        $segment2Data = $this->dal->getSegment2Data($startDate, $endDate);
        
        foreach ($segment2Data as $row) {
            $this->dal->updateSegment2Times(
                $row['trip_id'],
                $row['travel_date1'],
                $row['pnr'],
                $row['depart_time2'],
                $row['arrival_time2'],
                $row['segment2']
            );
            $totalUpdatedStep3++;
        }

        return [
            'upserted_base_rows' => $totalInserted,
            'step2_updates' => $totalUpdatedStep2,
            'step3_updates' => $totalUpdatedStep3
        ];
    }
}

