<?php
/**
 * Nobel TSK TSR Day Report Service
 * Business logic for TSR day report operations
 */

namespace App\Services;

use App\DAL\NobelTsksrdayReportDAL;
use Exception;

class NobelTsksrdayReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NobelTsksrdayReportDAL();
    }

    /**
     * Format time from numeric value to HH:MM:SS
     */
    private function formatTime($value): string
    {
        if (empty($value)) {
            return '00:00:00';
        }
        
        // Ensure the value is 6 digits by padding with leading zeros
        $paddedValue = str_pad((string)$value, 6, '0', STR_PAD_LEFT);
        
        // Split into HH:MM:SS format
        return substr($paddedValue, 0, 2) . ":" . 
               substr($paddedValue, 2, 2) . ":" . 
               substr($paddedValue, 4, 2);
    }

    /**
     * Process TSR day report for a specific date
     */
    public function processTsrDayReport(?string $callDate = null): array
    {
        if ($callDate === null) {
            $callDate = date("Y-m-d");
        }

        $reportData = $this->dal->getTsrDayReportData($callDate);
        $inserted = [];

        foreach ($reportData as $row) {
            $data = [
                'date' => $callDate,
                'tsr' => $row['tsr'],
                'gtib' => (int)($row['GTIB'] ?? 0),
                'gtmd' => (int)($row['GTMD'] ?? 0),
                'logontime' => $this->formatTime($row['logontime']),
                'logofftime' => $this->formatTime($row['logofftime']),
                'connect' => $row['connect'] ?? '00:00:00',
                'paused' => $row['paused'] ?? '00:00:00',
                'deassign' => $row['deassign'] ?? '00:00:00',
                'total' => $row['total'] ?? '00:00:00'
            ];

            $this->dal->insertTsrDayReport($data);
            $inserted[] = $data;
        }

        return [
            'success' => true,
            'message' => 'TSR day report processed successfully',
            'date' => $callDate,
            'total_records' => count($inserted),
            'records' => $inserted
        ];
    }
}

