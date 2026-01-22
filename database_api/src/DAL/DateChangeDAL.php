<?php
namespace App\DAL;

use Exception;

class DateChangeDAL extends BaseDAL
{
    /**
     * Get date change requests with filters
     */
    public function getDateChangeRequests($startDate = null, $endDate = null, $filters = [])
    {
        $query = "
            SELECT 
                r.case_id,
                r.assigned_case_agent,
                r.reservation_ref,
                r.case_type,
                r.status,
                r.sub_status,
                r.case_date,
                r.last_response_on,
                COALESCE(ac_col.agent_name, r.updated_by) as updated_by,
                r.priority,
                b.order_id,
                b.order_type,
                b.t_type,
                b.travel_date,
                b.return_date,
                b.product_title,
                b.total_pax,
                b.source,
                b.trip_code,
                b.total_amount
            FROM wpk4_backend_user_portal_requests r
            LEFT JOIN wpk4_backend_agent_codes ac_col ON BINARY r.change_done = BINARY ac_col.wordpress_user_name
            INNER JOIN (
                SELECT 
                    case_id,
                    MAX(STR_TO_DATE(case_date, '%Y-%m-%d %H:%i:%s')) AS max_case_date
                FROM wpk4_backend_user_portal_requests
                WHERE (
                    (case_type = 'datechange')
                     OR (case_type = 'datechange_misc' AND status = 'accept')
                     )
                GROUP BY case_id
            ) latest ON latest.case_id = r.case_id
                    AND STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s') = latest.max_case_date
            LEFT JOIN wpk4_backend_travel_bookings b 
                   ON r.reservation_ref = b.order_id
            WHERE (
                 (r.case_type = 'datechange')
              OR (r.case_type = 'datechange_misc' AND r.status = 'accept')
              )
        ";

        $params = [];

        if (!empty($startDate) && !empty($endDate)) {
            $query .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif (!empty($startDate)) {
            $query .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :start_date";
            $params['start_date'] = $startDate;
        } elseif (!empty($endDate)) {
            $query .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :end_date";
            $params['end_date'] = $endDate;
        }

        return $this->query($query, $params);
    }

    /**
     * Get next case ID
     */
    public function getNextCaseId()
    {
        $query = "SELECT IFNULL(MAX(case_id), 0) + 1 AS next_case_id FROM wpk4_backend_user_portal_requests";
        $result = $this->queryOne($query);
        return (int)($result['next_case_id'] ?? 1);
    }

    /**
     * Insert date change record
     */
    public function insertDateChangeRecord($data)
    {
        $query = "
            INSERT INTO wpk4_backend_user_portal_requests
            (case_id, case_type, reservation_ref, status, sub_status, case_date, is_seen_by_gt, 
             first_responded_by, last_response_by, last_response_on, handler_name, priority, updated_by, user_id)
            VALUES
            (:case_id, 'datechange_misc', :reservation_ref, 'open', 'Awaiting HO', :case_date, 0, 
             :current_user, :current_user, :case_date, :current_user, 'P4', :current_user, :current_user)
        ";
        $this->execute($query, [
            'case_id' => $data['case_id'],
            'reservation_ref' => $data['reservation_ref'],
            'case_date' => $data['case_date'],
            'current_user' => $data['current_user']
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Insert cost breakdown
     */
    public function insertCostBreakdown($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_bookings_datechange_cost_breakdown
            (case_id, order_id, airline_change_fee, fare_difference, gaura_service_fee, buffer, total_amount, added_on, added_by, submit_source)
            VALUES
            (:case_id, :reservation_ref, :airline_change_fee, :fare_difference, :gaura_service_fee, :buffer, :total_amount, :added_on, :added_by, 'datechange_misc')
        ";
        return $this->execute($query, $data);
    }

    /**
     * Upsert case meta
     */
    public function upsertCaseMeta($caseId, $metaKey, $metaValue)
    {
        // Check if exists
        $existing = $this->queryOne("
            SELECT auto_id
            FROM wpk4_backend_user_portal_request_meta
            WHERE case_id = :case_id AND meta_key = :meta_key
            ORDER BY auto_id DESC
            LIMIT 1
        ", ['case_id' => (string)$caseId, 'meta_key' => $metaKey]);

        if ($existing) {
            $query = "
                UPDATE wpk4_backend_user_portal_request_meta
                SET meta_value = :meta_value
                WHERE auto_id = :auto_id
            ";
            return $this->execute($query, [
                'meta_value' => (string)$metaValue,
                'auto_id' => (int)$existing['auto_id']
            ]);
        } else {
            $query = "
                INSERT INTO wpk4_backend_user_portal_request_meta
                (case_id, meta_key, meta_value)
                VALUES
                (:case_id, :meta_key, :meta_value)
            ";
            return $this->execute($query, [
                'case_id' => (string)$caseId,
                'meta_key' => $metaKey,
                'meta_value' => (string)$metaValue
            ]);
        }
    }

    /**
     * Get remarks by case_id or reservation_ref
     */
    public function getRemarks($caseId = null, $reservationRef = null)
    {
        $query = "SELECT * FROM wpk4_backend_dc_remark WHERE 1=1";
        $params = [];

        if ($caseId) {
            $query .= " AND case_id = :case_id";
            $params['case_id'] = $caseId;
        }

        if ($reservationRef) {
            $query .= " AND reservation_ref = :reservation_ref";
            $params['reservation_ref'] = $reservationRef;
        }

        $query .= " ORDER BY id DESC";

        return $this->query($query, $params);
    }

    /**
     * Insert remark
     */
    public function insertRemark($data)
    {
        $query = "
            INSERT INTO wpk4_backend_dc_remark
            (case_id, reservation_ref, remark, created_on, created_by)
            VALUES
            (:case_id, :reservation_ref, :remark, :created_on, :created_by)
        ";
        $this->execute($query, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Get remark by ID
     */
    public function getRemarkById($id)
    {
        $query = "SELECT * FROM wpk4_backend_dc_remark WHERE id = :id";
        return $this->queryOne($query, ['id' => $id]);
    }

    /**
     * Update agent assignment
     */
    public function updateAgentAssignment($caseId, $reservationRef, $selectedAgent)
    {
        if ($caseId) {
            $query = "
                UPDATE wpk4_backend_user_portal_requests
                SET assigned_case_agent = :assigned_case_agent
                WHERE case_id = :case_id
            ";
            return $this->execute($query, [
                'assigned_case_agent' => $selectedAgent,
                'case_id' => $caseId
            ]);
        } else {
            $query = "
                UPDATE wpk4_backend_user_portal_requests
                SET assigned_case_agent = :assigned_case_agent
                WHERE reservation_ref = :reservation_ref
            ";
            return $this->execute($query, [
                'assigned_case_agent' => $selectedAgent,
                'reservation_ref' => $reservationRef
            ]);
        }
    }

    /**
     * Get agent data
     */
    public function getAgentData($caseId)
    {
        // Get agents from chats
        $chatAgents = $this->query("
            SELECT DISTINCT response_by 
            FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :case_id 
            AND LENGTH(response_by) < 15 
            AND response_by != '' 
            AND response_by IS NOT NULL
        ", ['case_id' => $caseId]);

        // Get agents from remarks
        $remarkAgents = $this->query("
            SELECT DISTINCT created_by 
            FROM wpk4_backend_dc_remark 
            WHERE case_id = :case_id 
            AND created_by != '' 
            AND created_by IS NOT NULL
        ", ['case_id' => $caseId]);

        // Get first handler
        $firstHandler = $this->queryOne("
            SELECT response_by 
            FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :case_id 
            AND LENGTH(response_by) < 15
            AND response_by != '' 
            AND response_by IS NOT NULL
            ORDER BY response_time ASC 
            LIMIT 1
        ", ['case_id' => $caseId]);

        // Get last handler
        $lastHandler = $this->queryOne("
            SELECT response_by 
            FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :case_id 
            AND LENGTH(response_by) < 15
            AND response_by != '' 
            AND response_by IS NOT NULL
            ORDER BY response_time DESC 
            LIMIT 1
        ", ['case_id' => $caseId]);

        // Get status changes
        $statusChanges = $this->query("
            SELECT response_by, response, response_time
            FROM wpk4_backend_user_portal_request_chats 
            WHERE request_id = :case_id  
            AND (response LIKE '%Status changed as%' OR response LIKE '%status%')
            ORDER BY response_time ASC
        ", ['case_id' => $caseId]);

        // Get all remarks
        $allRemarks = $this->query("
            SELECT created_by, remark, created_on 
            FROM wpk4_backend_dc_remark 
            WHERE case_id = :case_id 
            ORDER BY created_on ASC
        ", ['case_id' => $caseId]);

        // Combine unique agents
        $allAgents = [];
        foreach ($chatAgents as $agent) {
            if (!empty($agent['response_by'])) {
                $allAgents[] = $agent['response_by'];
            }
        }
        foreach ($remarkAgents as $agent) {
            if (!empty($agent['created_by'])) {
                $allAgents[] = $agent['created_by'];
            }
        }
        $uniqueAgents = array_unique($allAgents);

        // Process status changes
        $processedStatusChanges = [];
        foreach ($statusChanges as $change) {
            $response = $change['response'];
            $statusValue = $response;
            
            if (strpos($response, 'Status changed as') !== false) {
                $statusValue = trim(str_replace('Status changed as', '', $response));
            }
            
            $processedStatusChanges[] = [
                'created_by' => $change['response_by'],
                'remark' => $response,
                'status_value' => $statusValue,
                'created_on' => $change['response_time']
            ];
        }

        return [
            'agents' => array_values($uniqueAgents),
            'division' => [
                'first_handler' => $firstHandler['response_by'] ?? '',
                'last_handler' => $lastHandler['response_by'] ?? '',
                'status_changes' => $processedStatusChanges,
                'remarks' => $allRemarks
            ]
        ];
    }

    /**
     * Get monthly summary
     */
    public function getMonthlySummary($startDate = null, $endDate = null)
    {
        $query = "
            SELECT 
                YEAR(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as year,
                MONTH(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as month,
                SUM(CASE WHEN LOWER(b.order_type) = 'gds' THEN 1 ELSE 0 END) as fit_count,
                SUM(CASE WHEN LOWER(b.order_type) != 'gds' OR b.order_type IS NULL THEN 1 ELSE 0 END) as gdeals_count,
                COUNT(*) as total_count
            FROM 
                wpk4_backend_user_portal_requests r
            LEFT JOIN 
                wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
            WHERE 
                (r.case_type = 'datechange' or r.case_type = 'datechange_misc')
        ";

        $params = [];

        if (!empty($startDate)) {
            $query .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :start_date";
            $params['start_date'] = $startDate;
        }
        if (!empty($endDate)) {
            $query .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :end_date";
            $params['end_date'] = $endDate;
        }

        $query .= "
            GROUP BY 
                YEAR(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')), 
                MONTH(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s'))
            ORDER BY 
                year DESC, month DESC
        ";

        return $this->query($query, $params);
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        // Get failed reasons
        $failedReasons = $this->query("
            SELECT DISTINCT remark 
            FROM wpk4_backend_dc_remark 
            WHERE remark IS NOT NULL 
            AND remark != ''
            ORDER BY remark ASC
        ");

        // Get reasons from meta
        $reasons = $this->query("
            SELECT DISTINCT meta.meta_value
            FROM wpk4_backend_user_portal_requests AS req
            LEFT JOIN wpk4_backend_user_portal_request_meta AS meta 
                ON meta.case_id = req.case_id and meta.meta_key = 'reason'
            WHERE req.case_type = 'datechange' 
            AND date(req.case_date) > '2025-01-01'
            AND meta.meta_value IS NOT NULL
            AND meta.meta_value <> ''
            ORDER BY meta.meta_value ASC
        ");

        // Get agents
        $agents = $this->query("
            SELECT DISTINCT updated_by 
            FROM wpk4_backend_user_portal_requests 
            WHERE updated_by IS NOT NULL 
            AND date(case_date) > '2025-01-01'
            AND LENGTH(updated_by) < 15
            AND updated_by != ''
            ORDER BY updated_by ASC
        ");

        // Get assigned case agents
        $assignedAgents = $this->query("
            SELECT DISTINCT assigned_case_agent 
            FROM wpk4_backend_user_portal_requests 
            WHERE assigned_case_agent IS NOT NULL 
            AND LENGTH(assigned_case_agent) < 15
            AND assigned_case_agent != ''
            ORDER BY assigned_case_agent ASC
        ");

        return [
            'failed_reasons' => array_column($failedReasons, 'remark'),
            'reasons' => array_column($reasons, 'meta_value'),
            'agents' => array_column($agents, 'updated_by'),
            'assigned_agents' => array_column($assignedAgents, 'assigned_case_agent')
        ];
    }

    /**
     * Get latest cost breakdown
     */
    public function getLatestCostBreakdown($caseId = null, $orderId = null)
    {
        if ($caseId) {
            $query = "
                SELECT *
                FROM wpk4_backend_travel_bookings_datechange_cost_breakdown
                WHERE case_id = :case_id  
                ORDER BY 
                    COALESCE(added_on, '0000-00-00 00:00:00') DESC,
                    auto_id DESC
                LIMIT 1
            ";
            return $this->queryOne($query, ['case_id' => $caseId]);
        } elseif ($orderId) {
            $query = "
                SELECT *
                FROM wpk4_backend_travel_bookings_datechange_cost_breakdown
                WHERE order_id = :order_id
                ORDER BY 
                    COALESCE(added_on, '0000-00-00 00:00:00') DESC,
                    auto_id DESC
                LIMIT 1
            ";
            return $this->queryOne($query, ['order_id' => $orderId]);
        }
        return null;
    }
}

