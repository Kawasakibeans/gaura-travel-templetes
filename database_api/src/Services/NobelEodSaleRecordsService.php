<?php

namespace App\Services;

use App\DAL\NobelEodSaleRecordsDAL;

class NobelEodSaleRecordsService
{
    private $dal;

    public function __construct(NobelEodSaleRecordsDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Process and insert EOD sale booking records
     * Line: 9-56 (in template)
     */
    public function processEodSaleBookingRecords($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $bookingData = $this->dal->getEodSaleBookingData($targetDate);
        
        $inserted = [];
        $failed = [];
        
        foreach ($bookingData as $row) {
            try {
                $result = $this->dal->insertEodSaleBooking($row);
                if ($result) {
                    $inserted[] = [
                        'call_date' => $row['call_date'],
                        'tsr' => $row['tsr'],
                        'agent_name' => $row['agent_name'] ?? null
                    ];
                } else {
                    $failed[] = [
                        'call_date' => $row['call_date'],
                        'tsr' => $row['tsr'],
                        'error' => 'Insert failed'
                    ];
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'call_date' => $row['call_date'],
                    'tsr' => $row['tsr'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'booking',
            'target_date' => $targetDate,
            'total_records' => count($bookingData),
            'inserted' => count($inserted),
            'failed' => count($failed),
            'inserted_records' => $inserted,
            'failed_records' => $failed
        ];
    }

    /**
     * Process and insert EOD sale call records
     * Line: 59-112 (in template)
     */
    public function processEodSaleCallRecords($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $callData = $this->dal->getEodSaleCallData($targetDate);
        
        $inserted = [];
        $failed = [];
        
        foreach ($callData as $row) {
            try {
                $result = $this->dal->insertEodSaleCall($row);
                if ($result) {
                    $inserted[] = [
                        'call_date' => $row['call_date'],
                        'tsr' => $row['tsr']
                    ];
                } else {
                    $failed[] = [
                        'call_date' => $row['call_date'],
                        'tsr' => $row['tsr'],
                        'error' => 'Insert failed'
                    ];
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'call_date' => $row['call_date'],
                    'tsr' => $row['tsr'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'type' => 'call',
            'target_date' => $targetDate,
            'total_records' => count($callData),
            'inserted' => count($inserted),
            'failed' => count($failed),
            'inserted_records' => $inserted,
            'failed_records' => $failed
        ];
    }

    /**
     * Process both EOD sale booking and call records
     * Line: 9-112 (in template - entire file)
     */
    public function processAllEodSaleRecords($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $bookingResult = $this->processEodSaleBookingRecords($targetDate);
        $callResult = $this->processEodSaleCallRecords($targetDate);
        
        return [
            'target_date' => $targetDate,
            'booking' => $bookingResult,
            'call' => $callResult,
            'summary' => [
                'total_booking_records' => $bookingResult['total_records'],
                'total_call_records' => $callResult['total_records'],
                'total_inserted' => $bookingResult['inserted'] + $callResult['inserted'],
                'total_failed' => $bookingResult['failed'] + $callResult['failed']
            ]
        ];
    }

    /**
     * Get EOD sale booking data (without inserting)
     */
    public function getEodSaleBookingData($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $data = $this->dal->getEodSaleBookingData($targetDate);
        
        return [
            'type' => 'booking',
            'target_date' => $targetDate,
            'records' => $data,
            'count' => count($data)
        ];
    }

    /**
     * Get EOD sale call data (without inserting)
     */
    public function getEodSaleCallData($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $data = $this->dal->getEodSaleCallData($targetDate);
        
        return [
            'type' => 'call',
            'target_date' => $targetDate,
            'records' => $data,
            'count' => count($data)
        ];
    }
}

