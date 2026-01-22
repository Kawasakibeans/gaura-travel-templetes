<?php
/**
 * Escalation Statuswise Data Access Layer
 * Handles all database operations for escalation metrics grouped by status and escalated_to
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EscalationStatuswiseDAL extends BaseDAL
{
    /**
     * Get daily rollup data grouped by status and escalated_to
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of daily rollup data
     */
    public function getDailyRollup($startDate, $endDate)
    {
        $sql = "
            SELECT 
                DATE(escalated_on) AS d,
                
                -- Status buckets
                SUM(CASE WHEN UPPER(TRIM(status)) = 'OPEN'    THEN 1 ELSE 0 END) AS st_open,
                SUM(CASE WHEN UPPER(TRIM(status)) = 'CLOSED'  THEN 1 ELSE 0 END) AS st_closed,
                SUM(CASE WHEN UPPER(TRIM(status)) = 'PENDING' THEN 1 ELSE 0 END) AS st_pending,
                
                -- Escalated To buckets
                SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'HO'      THEN 1 ELSE 0 END) AS to_ho,
                SUM(CASE WHEN UPPER(TRIM(escalate_to)) = 'MANAGER' THEN 1 ELSE 0 END) AS to_manager,
                SUM(CASE WHEN (escalate_to IS NULL OR TRIM(escalate_to) = '') THEN 1 ELSE 0 END) AS to_blank
                
            FROM wpk4_backend_travel_escalations
            WHERE escalated_on BETWEEN :start_date AND :end_date
            GROUP BY DATE(escalated_on)
            ORDER BY d ASC
        ";

        $results = $this->query($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'date' => $row['d'],
                'status' => [
                    'open' => (int)$row['st_open'],
                    'closed' => (int)$row['st_closed'],
                    'pending' => (int)$row['st_pending']
                ],
                'escalated_to' => [
                    'ho' => (int)$row['to_ho'],
                    'manager' => (int)$row['to_manager'],
                    'blank' => (int)$row['to_blank']
                ]
            ];
        }
        
        return $data;
    }

    /**
     * Get escalation details for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByDate($date)
    {
        $sql = "
            SELECT 
                DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') AS escalated_time,
                order_id,
                auto_id,
                escalated_by,
                escalation_type,
                status,
                escalate_to
            FROM wpk4_backend_travel_escalations
            WHERE DATE(escalated_on) = :date
            ORDER BY escalated_on ASC
        ";

        $results = $this->query($sql, ['date' => $date]);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'escalated_time' => $row['escalated_time'] ?? '',
                'order_id' => $row['order_id'] ?? '',
                'auto_id' => (int)$row['auto_id'],
                'escalated_by' => $row['escalated_by'] ?? '',
                'escalation_type' => $row['escalation_type'] ?? '',
                'status' => $row['status'] ?? '',
                'escalate_to' => $row['escalate_to'] ?? ''
            ];
        }
        
        return $data;
    }
}

