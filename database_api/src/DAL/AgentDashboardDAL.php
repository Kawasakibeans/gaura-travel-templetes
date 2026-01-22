<?php
/**
 * Agent Dashboard Data Access Layer
 * Handles all database operations for agent dashboard statistics
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentDashboardDAL extends BaseDAL
{
    /**
     * Get teams (all or specific)
     */
    public function getTeams($teamName = null)
    {
        if ($teamName) {
            $query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_codes WHERE team_name = ? ORDER BY team_name ASC";
            return $this->query($query, [$teamName]);
        }
        
        $query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_codes ORDER BY team_name ASC";
        return $this->query($query);
    }

    /**
     * Get agents by team
     */
    public function getAgentsByTeam($teamName)
    {
        $query = "SELECT tsr, sales_id, agent_name, team_name FROM wpk4_backend_agent_codes WHERE team_name = ?";
        return $this->query($query, [$teamName]);
    }

    /**
     * Get agents (all, by ID, or by team)
     */
    public function getAgents($agentId = null, $teamName = null)
    {
        $whereParts = [];
        $params = [];

        if ($agentId) {
            $whereParts[] = "sales_id = ?";
            $params[] = $agentId;
        }

        if ($teamName) {
            $whereParts[] = "team_name = ?";
            $params[] = $teamName;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        $query = "SELECT tsr, sales_id, agent_name, team_name FROM wpk4_backend_agent_codes $whereSQL ORDER BY team_name ASC, sales_id ASC";
        
        return $this->query($query, $params);
    }

    /**
     * Get agent by code/ID (supports both sales_id and tsr)
     */
    public function getAgentByCode($agentId)
    {
        $query = "SELECT tsr, sales_id, agent_name, team_name FROM wpk4_backend_agent_codes WHERE sales_id = ? OR tsr = ? LIMIT 1";
        return $this->queryOne($query, [$agentId, $agentId]);
    }

    /**
     * Get team call statistics
     */
    public function getTeamCallStats($tsrList, $fromDate, $toDate, $fromTime, $toTime)
    {
        $placeholders = implode(',', array_fill(0, count($tsrList), '?'));
        $params = $tsrList;
        
        // Add date/time filters
        $params[] = $fromDate . ' ' . $fromTime;
        $params[] = $toDate . ' ' . $toTime;

        // GTIB calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND tsr IN ($placeholders) 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $gtibCalls = $result['call_count'];

        // Other calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl IN ('GTMD', 'GTCB', 'GTOB', 'GTMV') 
                    AND tsr IN ($placeholders) 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $otherCalls = $result['call_count'];

        // SL calls (successful)
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND rec_status = 'SL' 
                    AND tsr IN ($placeholders) 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $slCalls = $result['call_count'];

        // Non-SL calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND rec_status != 'SL' 
                    AND tsr IN ($placeholders) 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $nonSlCalls = $result['call_count'];

        return [
            'gtib_calls' => (int)$gtibCalls,
            'other_calls' => (int)$otherCalls,
            'sl_calls' => (int)$slCalls,
            'non_sl_calls' => (int)$nonSlCalls
        ];
    }

    /**
     * Get agent call statistics
     */
    public function getAgentCallStats($tsr, $fromDate, $toDate, $fromTime, $toTime)
    {
        $params = [$tsr, $fromDate . ' ' . $fromTime, $toDate . ' ' . $toTime];

        // GTIB calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $gtibCalls = $result['call_count'];

        // Other calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl IN ('GTMD', 'GTCB', 'GTOB', 'GTMV') 
                    AND tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $otherCalls = $result['call_count'];

        // SL calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND rec_status = 'SL' 
                    AND tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $slCalls = $result['call_count'];

        // Non-SL calls
        $query = "SELECT COUNT(rowid) as call_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE appl = 'GTIB' 
                    AND rec_status != 'SL' 
                    AND tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        $result = $this->queryOne($query, $params);
        $nonSlCalls = $result['call_count'];

        return [
            'gtib_calls' => (int)$gtibCalls,
            'other_calls' => (int)$otherCalls,
            'sl_calls' => (int)$slCalls,
            'non_sl_calls' => (int)$nonSlCalls
        ];
    }

    /**
     * Get team booking statistics
     */
    public function getTeamBookingStats($agentList, $fromDate, $toDate)
    {
        $placeholders = implode(',', array_fill(0, count($agentList), '?'));
        
        // PAX for GDEALS (where order_type = '1')
        $params = array_merge($agentList, [$fromDate, $toDate]);
        $query = "SELECT COUNT(DISTINCT booking.order_id) as total_pax 
                  FROM wpk4_backend_travel_bookings as booking 
                  JOIN wpk4_backend_travel_booking_pax as pax ON booking.order_id = pax.order_id
                  WHERE booking.agent_info IN ($placeholders) 
                    AND booking.order_type = '1'
                    AND DATE(booking.order_date) >= ? 
                    AND DATE(booking.order_date) <= ? 
                    AND DATEDIFF(DATE(booking.order_date), pax.dob) > 730 
                  GROUP BY pax.fname, pax.lname, pax.dob";
        $results = $this->query($query, $params);
        $paxGdeals = count($results);

        // PAX for FIT (where order_type != '1')
        $query = "SELECT COUNT(DISTINCT booking.order_id) as total_pax 
                  FROM wpk4_backend_travel_bookings as booking 
                  JOIN wpk4_backend_travel_booking_pax as pax ON booking.order_id = pax.order_id
                  WHERE booking.agent_info IN ($placeholders) 
                    AND booking.order_type != '1'
                    AND DATE(booking.order_date) >= ? 
                    AND DATE(booking.order_date) <= ? 
                    AND DATEDIFF(DATE(booking.order_date), pax.dob) > 730 
                  GROUP BY pax.fname, pax.lname, pax.dob";
        $results = $this->query($query, $params);
        $paxFit = count($results);

        return [
            'pax_gdeals' => $paxGdeals,
            'pax_fit' => $paxFit
        ];
    }

    /**
     * Get agent booking statistics
     */
    public function getAgentBookingStats($salesId, $fromDate, $toDate)
    {
        $params = [$salesId, $fromDate, $toDate];

        // PAX for GDEALS
        $query = "SELECT COUNT(DISTINCT booking.order_id) as total_pax 
                  FROM wpk4_backend_travel_bookings as booking 
                  JOIN wpk4_backend_travel_booking_pax as pax ON booking.order_id = pax.order_id
                  WHERE booking.agent_info = ? 
                    AND booking.order_type = '1'
                    AND DATE(booking.order_date) >= ? 
                    AND DATE(booking.order_date) <= ? 
                    AND DATEDIFF(DATE(booking.order_date), pax.dob) > 730 
                  GROUP BY pax.fname, pax.lname, pax.dob";
        $results = $this->query($query, $params);
        $paxGdeals = count($results);

        // PAX for FIT
        $query = "SELECT COUNT(DISTINCT booking.order_id) as total_pax 
                  FROM wpk4_backend_travel_bookings as booking 
                  JOIN wpk4_backend_travel_booking_pax as pax ON booking.order_id = pax.order_id
                  WHERE booking.agent_info = ? 
                    AND booking.order_type != '1'
                    AND DATE(booking.order_date) >= ? 
                    AND DATE(booking.order_date) <= ? 
                    AND DATEDIFF(DATE(booking.order_date), pax.dob) > 730 
                  GROUP BY pax.fname, pax.lname, pax.dob";
        $results = $this->query($query, $params);
        $paxFit = count($results);

        return [
            'pax_gdeals' => $paxGdeals,
            'pax_fit' => $paxFit
        ];
    }

    /**
     * Get agent call history
     */
    public function getAgentCallHistory($tsr, $fromDate, $toDate, $fromTime, $toTime, $limit, $offset)
    {
        $query = "SELECT * 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ? 
                  ORDER BY call_date DESC, call_time DESC 
                  LIMIT ? OFFSET ?";
        
        return $this->query($query, [
            $tsr,
            $fromDate . ' ' . $fromTime,
            $toDate . ' ' . $toTime,
            $limit,
            $offset
        ]);
    }

    /**
     * Get agent call history count
     */
    public function getAgentCallHistoryCount($tsr, $fromDate, $toDate, $fromTime, $toTime)
    {
        $query = "SELECT COUNT(rowid) as total_count 
                  FROM wpk4_backend_agent_nobel_data_call_rec 
                  WHERE tsr = ? 
                    AND CONCAT(call_date, ' ', call_time) >= ? 
                    AND CONCAT(call_date, ' ', call_time) <= ?";
        
        $result = $this->queryOne($query, [
            $tsr,
            $fromDate . ' ' . $fromTime,
            $toDate . ' ' . $toTime
        ]);
        
        return (int)$result['total_count'];
    }

    /**
     * Get distinct teams
     */
    public function getDistinctTeams()
    {
        $query = "SELECT DISTINCT team_name FROM wpk4_backend_agent_codes ORDER BY team_name ASC";
        return $this->query($query);
    }

    /**
     * Get agents list
     */
    public function getAgentsList($teamName = null)
    {
        if ($teamName) {
            $query = "SELECT sales_id, agent_name, team_name, tsr 
                      FROM wpk4_backend_agent_codes 
                      WHERE team_name = ? 
                      ORDER BY agent_name ASC";
            return $this->query($query, [$teamName]);
        }
        
        $query = "SELECT sales_id, agent_name, team_name, tsr 
                  FROM wpk4_backend_agent_codes 
                  ORDER BY team_name ASC, agent_name ASC";
        return $this->query($query);
    }
}

