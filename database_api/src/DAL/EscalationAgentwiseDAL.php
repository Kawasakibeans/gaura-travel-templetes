<?php
/**
 * Escalation Agentwise Data Access Layer
 * Handles all database operations for escalation metrics grouped by user/agent
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EscalationAgentwiseDAL extends BaseDAL
{
    /**
     * Helper function to create safe column alias slugs from escalation type labels
     */
    private function slugCol($label)
    {
        $s = preg_replace('/[^\w]+/u', '_', (string)$label);
        $s = trim($s, '_');
        if ($s === '') $s = 'x';
        if (preg_match('/^\d/', $s)) $s = '_' . $s;
        return strtolower($s);
    }

    /**
     * Get distinct escalation types within date range
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of escalation types
     */
    public function getDistinctEscalationTypesByDateRange($startDate, $endDate)
    {
        $startBound = $startDate . ' 00:00:00';
        $endBound = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

        $sql = "
            SELECT DISTINCT escalation_type
            FROM wpk4_backend_travel_escalations
            WHERE escalation_type IS NOT NULL 
            AND escalation_type <> ''
            AND escalated_on >= :start_bound 
            AND escalated_on < :end_bound
            ORDER BY escalation_type ASC
        ";

        $results = $this->query($sql, [
            'start_bound' => $startBound,
            'end_bound' => $endBound
        ]);
        
        $types = [];
        foreach ($results as $row) {
            $types[] = $row['escalation_type'];
        }
        
        return $types;
    }

    /**
     * Get escalation data grouped by user/agent
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of escalation data grouped by user
     */
    public function getEscalationDataByUser($startDate, $endDate)
    {
        // Get distinct escalation types first
        $escalationTypes = $this->getDistinctEscalationTypesByDateRange($startDate, $endDate);

        $startBound = $startDate . ' 00:00:00';
        $endBound = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

        // Build dynamic query with escalation types as columns
        $query = "SELECT COALESCE(escalated_by, '(unknown)') AS escalated_by";
        
        foreach ($escalationTypes as $type) {
            $quotedType = $this->db->quote($type);
            $alias = 'type_' . $this->slugCol($type);
            $query .= ", SUM(CASE WHEN escalation_type = $quotedType THEN 1 ELSE 0 END) AS `$alias`";
        }
        
        $query .= ", COUNT(*) AS total_escalations";
        $query .= " FROM wpk4_backend_travel_escalations";
        $query .= " WHERE escalated_on >= :start_bound AND escalated_on < :end_bound";
        $query .= " GROUP BY escalated_by";
        $query .= " ORDER BY escalated_by ASC";

        $results = $this->query($query, [
            'start_bound' => $startBound,
            'end_bound' => $endBound
        ]);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $formattedRow = [
                'escalated_by' => $row['escalated_by'],
                'total_escalations' => (int)$row['total_escalations']
            ];
            
            // Add each escalation type count
            foreach ($escalationTypes as $type) {
                $typeKey = 'type_' . $this->slugCol($type);
                $formattedRow[$typeKey] = (int)($row[$typeKey] ?? 0);
            }
            
            $data[] = $formattedRow;
        }
        
        return $data;
    }

    /**
     * Get escalation details for a specific user
     * 
     * @param string $user User name (escalated_by)
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of escalation records
     */
    public function getEscalationDetailsByUser($user, $startDate, $endDate)
    {
        $startBound = $startDate . ' 00:00:00';
        $endBound = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';

        $sql = "
            SELECT 
                escalated_by,
                escalation_type,
                escalated_on,
                DATE_FORMAT(escalated_on, '%Y-%m-%d %H:%i:%s') AS formatted_time
            FROM wpk4_backend_travel_escalations
            WHERE escalated_by = :user
            AND escalated_on >= :start_bound 
            AND escalated_on < :end_bound
            ORDER BY escalated_on DESC
        ";

        $results = $this->query($sql, [
            'user' => $user,
            'start_bound' => $startBound,
            'end_bound' => $endBound
        ]);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'escalated_by' => $row['escalated_by'] ?? '',
                'escalation_type' => $row['escalation_type'] ?? '',
                'escalated_on' => $row['escalated_on'] ?? '',
                'formatted_time' => $row['formatted_time'] ?? ''
            ];
        }
        
        return $data;
    }
}

