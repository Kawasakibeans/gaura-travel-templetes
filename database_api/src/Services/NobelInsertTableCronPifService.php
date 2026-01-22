<?php
/**
 * Nobel Insert Table Cron PIF Service
 * Business logic for updating PIF data in agent booking table
 */

namespace App\Services;

use App\DAL\NobelInsertTableCronPifDAL;
use Exception;

class NobelInsertTableCronPifService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new NobelInsertTableCronPifDAL();
    }

    /**
     * Update agent booking PIF data for last 4 days
     */
    public function updateAgentBookingPifData(): array
    {
        try {
            $affectedRows = $this->dal->updateAgentBookingPifData();
            
            return [
                'success' => true,
                'message' => 'Agent booking PIF data updated successfully',
                'affected_rows' => $affectedRows,
                'date_range' => [
                    'from' => date('Y-m-d', strtotime('-4 days')),
                    'to' => date('Y-m-d', strtotime('-1 day'))
                ]
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to update agent booking PIF data: ' . $e->getMessage(), 500);
        }
    }
}

