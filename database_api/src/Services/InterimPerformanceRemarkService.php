<?php
/**
 * Interim Performance Remark Service - Business Logic Layer
 * Handles interim performance remark operations
 */

namespace App\Services;

use App\DAL\InterimPerformanceRemarkDAL;
use Exception;

class InterimPerformanceRemarkService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new InterimPerformanceRemarkDAL();
    }

    /**
     * Get remark history for a TSR
     */
    public function getRemarkHistory(string $tsr): array
    {
        if (empty($tsr)) {
            throw new Exception('TSR is required', 400);
        }

        return $this->dal->getRemarkHistory($tsr);
    }

    /**
     * Save or update a remark
     */
    public function saveRemark(array $params): array
    {
        $tsr = $params['tsr'] ?? '';
        $from = $params['from'] ?? '';
        $to = $params['to'] ?? '';
        $remark = $params['remark'] ?? '';

        if (empty($tsr) || empty($from) || empty($to)) {
            throw new Exception('TSR, from date, and to date are required', 400);
        }

        $currentTime = date('Y-m-d H:i:s');

        // Check if record exists
        $exists = $this->dal->remarkExists($tsr, $from, $to);

        if ($exists) {
            // Update existing record
            $success = $this->dal->updateRemark($tsr, $from, $to, $remark, $currentTime);
            
            if (!$success) {
                throw new Exception('Failed to update remark', 500);
            }

            return [
                'action' => 'updated',
                'tsr' => $tsr,
                'date_range_start' => $from,
                'date_range_end' => $to,
                'updated_at' => $currentTime
            ];
        } else {
            // Insert new record
            $success = $this->dal->insertRemark($tsr, $from, $to, $remark, $currentTime, $currentTime);
            
            if (!$success) {
                throw new Exception('Failed to save remark', 500);
            }

            return [
                'action' => 'saved',
                'tsr' => $tsr,
                'date_range_start' => $from,
                'date_range_end' => $to,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ];
        }
    }
}

