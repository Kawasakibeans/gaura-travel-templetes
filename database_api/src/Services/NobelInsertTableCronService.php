<?php
/**
 * Nobel Insert Table Cron Service
 * Business logic for Nobel cronjob operations (agent booking, inbound call inserts, cleanup)
 */

namespace App\Services;

use App\DAL\NobelInsertTableCronDAL;
use Exception;

class NobelInsertTableCronService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NobelInsertTableCronDAL();
    }

    /**
     * Process agent booking data for yesterday
     */
    public function processAgentBookingData(): array
    {
        $bookingData = $this->dal->getAgentBookingDataForYesterday();
        $inserted = [];

        foreach ($bookingData as $row) {
            $data = [
                'order_date' => $row['order_date'],
                'agent_name' => $row['agent_name'],
                'pax' => (int)$row['pax'],
                'pif' => (int)$row['pif'],
                'fit' => (int)$row['fit'],
                'gdeals' => (int)$row['gdeals'],
                'tsr' => $row['tsr'],
                'team_name' => $row['team_name']
            ];

            $this->dal->insertAgentBooking($data);
            $inserted[] = $data;
        }

        return [
            'total_records' => count($inserted),
            'records' => $inserted
        ];
    }

    /**
     * Process agent inbound call data for yesterday
     */
    public function processAgentInboundCallData(): array
    {
        $callData = $this->dal->getAgentInboundCallDataForYesterday();
        $inserted = [];

        foreach ($callData as $row) {
            $data = [
                'call_date' => $row['call_date'],
                'tsr' => $row['tsr'],
                'team_name' => $row['team_name'],
                'gtib' => (int)$row['gtib'],
                'fcs' => (int)$row['FCS_count_old'],
                'fcs_new' => (int)$row['FCS_count'],
                'non_sales_made' => (int)$row['non_sales_made'],
                'fcs_count' => (float)$row['FCS_old'],
                'fcs_count_new' => (float)$row['FCS'],
                'aht' => $row['aht'],
                'call_duration' => (int)$row['call_duration'],
                'agent_name' => $row['agent_name']
            ];

            $this->dal->insertAgentInboundCall($data);
            $inserted[] = $data;
        }

        return [
            'total_records' => count($inserted),
            'records' => $inserted
        ];
    }

    /**
     * Cleanup old realtime table records
     */
    public function cleanupRealtimeTables(?string $dateBefore = null): array
    {
        if ($dateBefore === null) {
            $dateBefore = date('Y-m-d', strtotime('yesterday'));
        }

        return $this->dal->cleanupRealtimeTables($dateBefore);
    }

    /**
     * Process all cronjob operations
     */
    public function processAll(): array
    {
        $currenttime = date("Y-m-d H:i:s");
        
        $agentBooking = $this->processAgentBookingData();
        $agentInboundCall = $this->processAgentInboundCallData();
        $cleanup = $this->cleanupRealtimeTables();

        return [
            'timestamp' => $currenttime,
            'agent_booking' => $agentBooking,
            'agent_inbound_call' => $agentInboundCall,
            'cleanup' => $cleanup
        ];
    }
}

