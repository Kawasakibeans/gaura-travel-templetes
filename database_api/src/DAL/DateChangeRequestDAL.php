<?php
/**
 * Date Change Request Data Access Layer
 * Handles all database operations for date change request metrics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class DateChangeRequestDAL extends BaseDAL
{
    /**
     * Get date change request metrics for a date range
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param string $agentName Agent name filter (empty string for all agents)
     * @return array Array of date change request records
     */
    public function getDateChangeRequests($fromDate, $toDate, $agentName = '')
    {
        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];

        $sql = "
            SELECT 
                r.case_id,
                r.reservation_ref,
                r.case_type,
                r.status,
                r.sub_status,
                r.case_date,
                r.last_response_on,
                r.updated_by,
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
                orig_b.total_amount,
                cp.amount as cost_taken_amount
            FROM 
                wpk4_backend_user_portal_requests r
            LEFT JOIN 
                wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
            LEFT JOIN
                wpk4_backend_travel_bookings orig_b ON r.reservation_ref = orig_b.previous_order_id
            LEFT JOIN
                wpk4_backend_travel_booking_custom_payments cp ON r.reservation_ref = cp.order_id 
                AND cp.type_of_payment = 'Date Change' 
                AND cp.status = 'paid'
            WHERE 
                r.case_type = 'datechange'
        ";

        // Add date range conditions
        if (!empty($fromDate) && !empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN :from_date AND :to_date";
        } elseif (!empty($fromDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :from_date";
            unset($params['to_date']);
        } elseif (!empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :to_date";
            unset($params['from_date']);
        }

        // Add agent filter
        if ($agentName !== '') {
            $sql .= " AND r.updated_by = :agent_name";
            $params['agent_name'] = $agentName;
        }

        $sql .= " ORDER BY STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s') ASC";

        $results = $this->query($sql, $params);

        // Get transaction sums for reservation refs
        $transaction_map = [];
        if (!empty($results)) {
            $reservation_refs = array_unique(array_column($results, 'reservation_ref'));
            $reservation_refs = array_filter($reservation_refs);
            
            if (!empty($reservation_refs)) {
                $placeholders = implode(',', array_fill(0, count($reservation_refs), '?'));
                $transaction_query = "
                    SELECT 
                        order_id,
                        SUM(transaction_amount) as transaction_sum
                    FROM 
                        wpk4_backend_travel_booking_ticket_number
                    WHERE 
                        order_id IN ($placeholders)
                        AND reason = 'Datechange'
                    GROUP BY order_id
                ";
                
                $transaction_results = $this->query($transaction_query, array_values($reservation_refs));
                
                foreach ($transaction_results as $row) {
                    $transaction_map[$row['order_id']] = $row['transaction_sum'];
                }
            }
        }

        // Format results
        $data = [];
        foreach ($results as $row) {
            $reservation_ref = $row['reservation_ref'] ?? '';
            $transaction_sum = $transaction_map[$reservation_ref] ?? 0;
            
            // Extract airline code from trip_code
            $airline = '';
            if (!empty($row['trip_code']) && strlen($row['trip_code']) >= 10) {
                $airline = substr($row['trip_code'], 8, 2);
            }
            
            // Format dates
            $case_date = '';
            if (!empty($row['case_date'])) {
                try {
                    $case_date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $row['case_date']);
                    if ($case_date_obj) {
                        $case_date = $case_date_obj->format('d/m/Y');
                    }
                } catch (\Exception $e) {
                    $case_date = '';
                }
            }
            
            $travel_date = !empty($row['travel_date']) ? date('d/m/Y', strtotime($row['travel_date'])) : '';
            $last_response_on = !empty($row['last_response_on']) ? date('d/m/Y', strtotime($row['last_response_on'])) : '';
            $cost_taken = ($row['status'] === 'success') ? ($row['cost_taken_amount'] ?? 0) : 0;
            $total_revenue = ($row['status'] === 'success') ? ($cost_taken - $transaction_sum) : 0;
            
            $data[] = [
                'query_date' => $case_date,
                'agent' => $row['updated_by'] ?? '',
                'case_id' => $row['case_id'] ?? '',
                'pnr' => $reservation_ref,
                'request_type' => 'datechange',
                'pax_count' => $row['total_pax'] ?? '',
                'airline' => $airline,
                'last_quoted_by' => '',
                'booking_type' => (isset($row['order_type']) && strtolower($row['order_type']) === 'gds') ? 'FIT' : 'GDeals',
                'old_travel_date' => $travel_date,
                'airline_change_fee' => '',
                'fare_difference' => '',
                'gaura_travel_service_fee' => '',
                'buffer' => '',
                'cost_given' => floatval($row['total_amount'] ?? 0),
                'expected_cost' => '',
                'cost_taken' => floatval($cost_taken),
                'total_revenue' => floatval($total_revenue),
                'status' => $row['status'] ?? '',
                'status_date' => $last_response_on
            ];
        }
        
        return $data;
    }

    /**
     * Get monthly summary for date change requests
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Array of monthly summary records
     */
    public function getMonthlySummary($fromDate, $toDate)
    {
        $params = [];

        $sql = "
            SELECT 
                year,
                month,
                SUM(CASE WHEN LOWER(order_type) = 'gds' THEN 1 ELSE 0 END) as fit_count,
                SUM(CASE WHEN LOWER(order_type) != 'gds' OR order_type IS NULL THEN 1 ELSE 0 END) as gdeals_count,
                COUNT(*) as total_count
            FROM (
                SELECT 
                    YEAR(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as year,
                    MONTH(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as month,
                    b.order_type
                FROM 
                    wpk4_backend_user_portal_requests r
                LEFT JOIN 
                    wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
                WHERE 
                    r.case_type = 'datechange'
        ";

        // Add date range conditions
        if (!empty($fromDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :from_date";
            $params['from_date'] = $fromDate;
        }
        if (!empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :to_date";
            $params['to_date'] = $toDate;
        }

        $sql .= "
            ) as subquery
            GROUP BY 
                year, 
                month
            ORDER BY 
                year DESC, 
                month DESC
        ";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'year' => (int)$row['year'],
                'month' => (int)$row['month'],
                'fit_count' => (int)$row['fit_count'],
                'gdeals_count' => (int)$row['gdeals_count'],
                'total_count' => (int)$row['total_count']
            ];
        }
        
        return $data;
    }

    /**
     * Get agent data for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $agentName Agent name filter (empty string for all agents)
     * @return array Array of agent records for the date
     */
    public function getAgentDetailsByDate($date, $agentName = '')
    {
        $params = [
            'date' => $date
        ];

        $sql = "
            SELECT 
                r.updated_by as agent_name,
                COUNT(*) as total_requests,
                SUM(CASE WHEN r.status = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) as failure_count,
                SUM(CASE WHEN r.status = 'open' THEN 1 ELSE 0 END) as in_progress_count,
                SUM(orig_b.total_amount) as total_cost_given,
                SUM(CASE WHEN r.status = 'success' THEN cp.amount ELSE 0 END) as total_cost_taken,
                SUM(CASE WHEN r.status = 'success' THEN (cp.amount - COALESCE(t.transaction_sum, 0)) ELSE 0 END) as total_revenue
            FROM 
                wpk4_backend_user_portal_requests r
            LEFT JOIN 
                wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
            LEFT JOIN
                wpk4_backend_travel_bookings orig_b ON r.reservation_ref = orig_b.previous_order_id
            LEFT JOIN
                wpk4_backend_travel_booking_custom_payments cp ON r.reservation_ref = cp.order_id 
                AND cp.type_of_payment = 'Date Change' 
                AND cp.status = 'paid'
            LEFT JOIN (
                SELECT 
                    order_id,
                    SUM(transaction_amount) as transaction_sum
                FROM 
                    wpk4_backend_travel_booking_ticket_number
                WHERE 
                    reason = 'Datechange'
                GROUP BY order_id
            ) t ON r.reservation_ref = t.order_id
            WHERE 
                DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) = :date
                AND r.case_type = 'datechange'
        ";

        if ($agentName !== '') {
            $sql .= " AND r.updated_by = :agent_name";
            $params['agent_name'] = $agentName;
        }

        $sql .= " GROUP BY r.updated_by ORDER BY r.updated_by ASC";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'agent_name' => $row['agent_name'] ?? '',
                'total_requests' => (int)$row['total_requests'],
                'success_count' => (int)$row['success_count'],
                'failure_count' => (int)$row['failure_count'],
                'in_progress_count' => (int)$row['in_progress_count'],
                'total_cost_given' => floatval($row['total_cost_given'] ?? 0),
                'total_cost_taken' => floatval($row['total_cost_taken'] ?? 0),
                'total_revenue' => floatval($row['total_revenue'] ?? 0)
            ];
        }
        
        return $data;
    }

    /**
     * Get daily summary for date change requests
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Array of daily summary records
     */
    public function getDailySummary($fromDate, $toDate)
    {
        $params = [];

        $sql = "
            SELECT 
                case_date,
                SUM(CASE WHEN LOWER(order_type) = 'gds' AND status = 'open' THEN 1 ELSE 0 END) as fit_pending_count,
                SUM(CASE WHEN LOWER(order_type) = 'gds' AND (status = 'success' OR status = 'fail') THEN 1 ELSE 0 END) as fit_close_count,
                SUM(CASE WHEN (LOWER(order_type) != 'gds' OR order_type IS NULL) AND status = 'open' THEN 1 ELSE 0 END) as gdeals_pending_count,
                SUM(CASE WHEN (LOWER(order_type) != 'gds' OR order_type IS NULL) AND (status = 'success' OR status = 'fail') THEN 1 ELSE 0 END) as gdeals_close_count,
                COUNT(*) as total_count
            FROM (
                SELECT 
                    DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) as case_date,
                    b.order_type,
                    r.status
                FROM 
                    wpk4_backend_user_portal_requests r
                LEFT JOIN 
                    wpk4_backend_travel_bookings b ON r.reservation_ref = b.order_id
                WHERE 
                    r.case_type = 'datechange'
        ";

        // Add date range conditions
        if (!empty($fromDate) && !empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN :from_date AND :to_date";
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        } elseif (!empty($fromDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :from_date";
            $params['from_date'] = $fromDate;
        } elseif (!empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :to_date";
            $params['to_date'] = $toDate;
        }

        $sql .= "
            ) as subquery
            GROUP BY case_date
            ORDER BY case_date DESC
        ";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'case_date' => $row['case_date'],
                'fit_pending_count' => (int)$row['fit_pending_count'],
                'fit_close_count' => (int)$row['fit_close_count'],
                'gdeals_pending_count' => (int)$row['gdeals_pending_count'],
                'gdeals_close_count' => (int)$row['gdeals_close_count'],
                'total_count' => (int)$row['total_count']
            ];
        }
        
        return $data;
    }

    /**
     * Get agent summary for date change requests
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array Array of agent summary records
     */
    public function getAgentSummary($fromDate, $toDate)
    {
        $params = [];

        $sql = "
            SELECT 
                agent_name,
                COUNT(*) as total_cases,
                SUM(CASE WHEN LOWER(status) = 'success' THEN 1 ELSE 0 END) as success_cases,
                SUM(CASE WHEN LOWER(status) = 'success' THEN (amount - COALESCE(transaction_sum, 0)) ELSE 0 END) as total_revenue
            FROM (
                SELECT 
                    r.updated_by as agent_name,
                    r.status,
                    cp.amount,
                    t.transaction_sum
                FROM 
                    wpk4_backend_user_portal_requests r
                LEFT JOIN
                    wpk4_backend_travel_booking_custom_payments cp ON r.reservation_ref = cp.order_id 
                    AND cp.type_of_payment = 'Date Change' 
                    AND cp.status = 'paid'
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(transaction_amount) as transaction_sum
                    FROM 
                        wpk4_backend_travel_booking_ticket_number
                    WHERE 
                        reason = 'Datechange'
                    GROUP BY order_id
                ) t ON r.reservation_ref = t.order_id
                WHERE 
                    r.case_type = 'datechange'
                    AND r.updated_by IS NOT NULL
                    AND r.updated_by != ''
        ";

        // Add date range conditions
        if (!empty($fromDate) && !empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) BETWEEN :from_date AND :to_date";
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        } elseif (!empty($fromDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) >= :from_date";
            $params['from_date'] = $fromDate;
        } elseif (!empty($toDate)) {
            $sql .= " AND DATE(STR_TO_DATE(r.case_date, '%Y-%m-%d %H:%i:%s')) <= :to_date";
            $params['to_date'] = $toDate;
        }

        $sql .= "
            ) as subquery
            GROUP BY agent_name
            ORDER BY agent_name ASC
        ";

        $results = $this->query($sql, $params);
        
        // Format results
        $data = [];
        foreach ($results as $row) {
            $total_cases = (int)$row['total_cases'];
            $success_cases = (int)$row['success_cases'];
            $success_percent = $total_cases > 0 ? round(($success_cases / $total_cases) * 100, 1) : 0;
            
            $data[] = [
                'agent' => $row['agent_name'] ?? 'Unknown',
                'total_cases' => $total_cases,
                'success_cases' => $success_cases,
                'success_percent' => $success_percent,
                'total_revenue' => floatval($row['total_revenue'] ?? 0)
            ];
        }
        
        return $data;
    }

    /**
     * Get distinct agents for dropdown
     * 
     * @return array Array of agent names
     */
    public function getDistinctAgents()
    {
        $query = "
            SELECT DISTINCT r.updated_by as agent_name
            FROM wpk4_backend_user_portal_requests r
            WHERE r.case_type = 'datechange'
            AND r.updated_by IS NOT NULL
            AND r.updated_by != ''
            ORDER BY agent_name ASC
        ";
        
        $results = $this->query($query);
        $agents = [];
        foreach ($results as $row) {
            $agents[] = $row['agent_name'];
        }
        
        return $agents;
    }
}

