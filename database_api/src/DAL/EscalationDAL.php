<?php
/**
 * Escalation Data Access Layer
 * Handles all database operations for escalation metrics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EscalationDAL extends BaseDAL
{
    /**
     * Get escalation data grouped by date and type
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param int|null $startDay Start day of month (1-31, null for all)
     * @param int|null $endDay End day of month (1-31, null for all)
     * @param string|null $status Status filter (null for all)
     * @param string|null $escalatedTo Escalated to filter (null for all)
     * @return array Array of escalation data
     */
    public function getEscalationData($startDate, $endDate, $startDay = null, $endDay = null, $status = null, $escalatedTo = null)
    {
        // Get distinct escalation types first
        $escalationTypes = $this->getDistinctEscalationTypes();

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        // Build dynamic query with escalation types as columns
        // Use PDO connection to quote type names safely
        $query = "SELECT DATE(escalated_on) as escalation_date";
        
        foreach ($escalationTypes as $type) {
            $quotedType = $this->db->quote($type);
            $typeKey = 'type_' . str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_]/', '_', $type));
            $query .= ", SUM(CASE WHEN escalation_type = $quotedType THEN 1 ELSE 0 END) as `$typeKey`";
        }
        
        $query .= ", COUNT(*) as total_escalations";
        $query .= " FROM wpk4_backend_travel_escalations";
        $query .= " WHERE escalated_on BETWEEN :start_date AND :end_date";

        // Add day range filter
        if ($startDay !== null && $endDay !== null) {
            $query .= " AND DAY(escalated_on) BETWEEN :start_day AND :end_day";
            $params['start_day'] = $startDay;
            $params['end_day'] = $endDay;
        }

        // Add status filter
        if ($status !== null && $status !== '') {
            $query .= " AND status = :status";
            $params['status'] = $status;
        }

        // Add escalated_to filter
        if ($escalatedTo !== null && $escalatedTo !== '') {
            $query .= " AND escalate_to = :escalated_to";
            $params['escalated_to'] = $escalatedTo;
        }

        $query .= " GROUP BY DATE(escalated_on) ORDER BY escalation_date ASC";

        $results = $this->query($query, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $formattedRow = [
                'escalation_date' => $row['escalation_date'],
                'total_escalations' => (int)$row['total_escalations']
            ];
            
            // Add each escalation type count
            foreach ($escalationTypes as $type) {
                $typeKey = 'type_' . str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_]/', '_', $type));
                $formattedRow[$typeKey] = (int)($row[$typeKey] ?? 0);
            }
            
            $data[] = $formattedRow;
        }
        
        return $data;
    }

    /**
     * Get escalation details for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @param string|null $status Status filter (null for all)
     * @param string|null $escalatedTo Escalated to filter (null for all)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByDate($date, $status = null, $escalatedTo = null)
    {
        $params = [
            'date' => $date
        ];

        $sql = "
            SELECT 
                escalated_by,
                auto_id,
                order_id,
                escalation_type,
                escalated_on,
                status,
                escalate_to,
                DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') as formatted_time
            FROM wpk4_backend_travel_escalations
            WHERE DATE(escalated_on) = :date
        ";

        // Add status filter
        if ($status !== null && $status !== '') {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        // Add escalated_to filter
        if ($escalatedTo !== null && $escalatedTo !== '') {
            $sql .= " AND escalate_to = :escalated_to";
            $params['escalated_to'] = $escalatedTo;
        }

        $sql .= " ORDER BY escalated_on ASC";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'auto_id' => (int)$row['auto_id'],
                'order_id' => $row['order_id'] ?? '',
                'escalated_by' => $row['escalated_by'] ?? '',
                'escalation_type' => $row['escalation_type'] ?? '',
                'escalated_on' => $row['escalated_on'] ?? '',
                'formatted_time' => $row['formatted_time'] ?? '',
                'status' => $row['status'] ?? '',
                'escalate_to' => $row['escalate_to'] ?? ''
            ];
        }
        
        return $data;
    }

    /**
     * Get distinct escalation types
     * 
     * @return array Array of escalation types
     */
    public function getDistinctEscalationTypes()
    {
        $sql = "
            SELECT DISTINCT escalation_type
            FROM wpk4_backend_travel_escalations
            WHERE escalation_type IS NOT NULL 
            AND escalation_type <> ''
            ORDER BY escalation_type ASC
        ";

        $results = $this->query($sql);
        
        $types = [];
        foreach ($results as $row) {
            $types[] = $row['escalation_type'];
        }
        
        return $types;
    }

    /**
     * Get distinct statuses
     * 
     * @return array Array of statuses
     */
    public function getDistinctStatuses()
    {
        $sql = "
            SELECT DISTINCT status
            FROM wpk4_backend_travel_escalations
            WHERE status IS NOT NULL 
            AND status <> ''
            ORDER BY status ASC
        ";

        $results = $this->query($sql);
        
        $statuses = [];
        foreach ($results as $row) {
            $statuses[] = $row['status'];
        }
        
        return $statuses;
    }

    /**
     * Get distinct escalated_to values
     * 
     * @return array Array of escalated_to values
     */
    public function getDistinctEscalatedTo()
    {
        $sql = "
            SELECT DISTINCT escalate_to
            FROM wpk4_backend_travel_escalations
            WHERE escalate_to IS NOT NULL 
            AND escalate_to <> ''
            ORDER BY escalate_to ASC
        ";

        $results = $this->query($sql);
        
        $escalatedTo = [];
        foreach ($results as $row) {
            $escalatedTo[] = $row['escalate_to'];
        }
        
        return $escalatedTo;
    }
}

