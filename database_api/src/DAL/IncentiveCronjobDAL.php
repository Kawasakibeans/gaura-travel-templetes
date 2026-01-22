<?php
/**
 * Incentive Cronjob DAL - Data Access Layer
 */

namespace App\DAL;

use Exception;

class IncentiveCronjobDAL extends BaseDAL
{
    /**
     * Get agent performance data (combined from inbound calls and bookings)
     */
    public function getAgentPerformanceData($date, $teamName = null)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $inboundCallTable = $tablePrefix . 'backend_agent_inbound_call';
        $bookingTable = $tablePrefix . 'backend_agent_booking';
        $agentCodesTable = $tablePrefix . 'backend_agent_codes';
        
        // Build team filter - use different parameter names for each occurrence
        $teamFilter1 = '1';
        $teamFilter2 = '1';
        if (!empty($teamName)) {
            $teamFilter1 = "a.team_name = :team_name1";
            $teamFilter2 = "a.team_name = :team_name2";
        }
        
        $sql = "
            SELECT
                MAX(team_name) AS team_name,
                MAX(shift_time) AS shift_time,
                MAX(tsr) AS tsr,
                MAX(agent_name) AS agent_name,
                SUM(pax) AS pax,
                SUM(fit) AS fit,
                SUM(pif) AS pif,
                SUM(gdeals) AS gdeals,
                SUM(gtib_count) AS gtib,
                SUM(sale_made_count) AS sale_made_count,
                SUM(non_sale_made_count) AS non_sale_made_count,
                SUM(rec_duration) AS rec_duration,
                CASE WHEN SUM(gtib_count) != 0 THEN SUM(pax) / SUM(gtib_count) * 100 ELSE 0 END AS conversion_percentage
            FROM (
                SELECT
                    a.agent_name,
                    a.tsr,
                    0 AS pax,
                    0 AS fit,
                    0 AS pif,
                    0 AS gdeals,
                    a.team_name,
                    a.gtib_count,
                    a.sale_made_count,
                    a.non_sale_made_count,
                    a.shift_time,
                    a.rec_duration AS rec_duration
                FROM {$inboundCallTable} a
                LEFT JOIN {$agentCodesTable} c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE DATE(a.call_date) = :date1 AND c.agent_name != '' AND {$teamFilter1}
               
                UNION ALL
               
                SELECT
                    a.agent_name,
                    a.tsr,
                    a.pax,
                    a.fit,
                    a.pif,
                    a.gdeals,
                    a.team_name,
                    0 AS gtib_count,
                    0 AS sale_made_count,
                    0 AS non_sale_made_count,
                    0 AS shift_time,
                    0 AS rec_duration
                FROM {$bookingTable} a
                LEFT JOIN {$agentCodesTable} c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE {$teamFilter2} AND DATE(a.order_date) = :date2 AND c.agent_name != ''
            ) AS combined_data
            GROUP BY team_name, agent_name 
            ORDER BY team_name ASC, agent_name ASC
        ";
        
        $params = [
            ':date1' => $date,
            ':date2' => $date
        ];
        
        if (!empty($teamName)) {
            $params[':team_name1'] = $teamName;
            $params[':team_name2'] = $teamName;
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get total abandoned calls for a date
     */
    public function getAbandonedCallsCount($date)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $inboundCallTable = $tablePrefix . 'backend_agent_inbound_call';
        
        $sql = "SELECT abandoned 
                FROM {$inboundCallTable} 
                WHERE call_date = :date AND abandoned != '0'
                LIMIT 1";
        
        $params = [':date' => $date];
        $results = $this->query($sql, $params);
        
        return !empty($results) ? (int)$results[0]['abandoned'] : 0;
    }
    
    /**
     * Get login time for agent
     */
    public function getAgentLoginTime($date, $tsr)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $nobelTable = $tablePrefix . 'backend_agent_nobel_data_tsktsrday';
        
        $sql = "SELECT logon_time 
                FROM {$nobelTable} 
                WHERE call_date = :date AND tsr = :tsr 
                ORDER BY auto_id ASC 
                LIMIT 1";
        
        $params = [
            ':date' => $date,
            ':tsr' => $tsr
        ];
        
        $results = $this->query($sql, $params);
        
        return !empty($results) ? $results[0]['logon_time'] : null;
    }
    
    /**
     * Get incentive conditions
     */
    public function getIncentiveConditions($date = null, $type = null, $incentiveTitle = null)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $conditionsTable = $tablePrefix . 'agent_data_incentive_conditions';
        
        $where = [];
        $params = [];
        
        if (!empty($date)) {
            $where[] = "(:date BETWEEN start_date AND end_date)";
            $params[':date'] = $date;
        }
        
        if (!empty($type)) {
            $where[] = "type = :type";
            $params[':type'] = $type;
        }
        
        if (!empty($incentiveTitle)) {
            $where[] = "incentive_title = :incentive_title";
            $params[':incentive_title'] = $incentiveTitle;
        } else {
            $where[] = "incentive_title != 'DUMMY1'";
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM {$conditionsTable} {$whereClause} ORDER BY condition_value DESC";
        
        return $this->query($sql, $params);
    }
    
    /**
     * Check if agent is team leader or sale manager
     */
    public function isTeamLeaderOrSaleManager($agentName)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $agentCodesTable = $tablePrefix . 'backend_agent_codes';
        
        $sql = "SELECT * 
                FROM {$agentCodesTable} 
                WHERE sale_manager LIKE :agent_name OR team_leader LIKE :agent_name2
                LIMIT 1";
        
        $params = [
            ':agent_name' => "%{$agentName}%",
            ':agent_name2' => "%{$agentName}%"
        ];
        
        $results = $this->query($sql, $params);
        
        return !empty($results);
    }
    
    /**
     * Insert incentive data
     */
    public function insertIncentiveData($data)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $incentiveDataTable = $tablePrefix . 'agent_data_incentive_data';
        
        $sql = "INSERT INTO {$incentiveDataTable} (
            incentive_title, date, tsr, agent_name, gtib, pax_count, conversion, 
            fcs_count, fcs_percentage, floor_gtib, floor_total_pax, floor_conversion, 
            floor_fcs, total_amount, bonus_amount, total_with_bonus, 
            total_no_of_agents_eligible, abandoncy_call_count, deduction_on_abandoncy, 
            amount_after_deduction, per_agent_payable_amount, call_time, shift_time
        ) VALUES (
            :incentive_title, :date, :tsr, :agent_name, :gtib, :pax_count, :conversion,
            :fcs_count, :fcs_percentage, :floor_gtib, :floor_total_pax, :floor_conversion,
            :floor_fcs, :total_amount, :bonus_amount, :total_with_bonus,
            :total_no_of_agents_eligible, :abandoncy_call_count, :deduction_on_abandoncy,
            :amount_after_deduction, :per_agent_payable_amount, :call_time, :shift_time
        )";
        
        $params = [
            ':incentive_title' => $data['incentive_title'] ?? '',
            ':date' => $data['date'] ?? '',
            ':tsr' => $data['tsr'] ?? '',
            ':agent_name' => $data['agent_name'] ?? '',
            ':gtib' => $data['gtib'] ?? 0,
            ':pax_count' => $data['pax_count'] ?? 0,
            ':conversion' => $data['conversion'] ?? '0',
            ':fcs_count' => $data['fcs_count'] ?? 0,
            ':fcs_percentage' => $data['fcs_percentage'] ?? '0',
            ':floor_gtib' => $data['floor_gtib'] ?? 0,
            ':floor_total_pax' => $data['floor_total_pax'] ?? 0,
            ':floor_conversion' => $data['floor_conversion'] ?? '0',
            ':floor_fcs' => $data['floor_fcs'] ?? '0',
            ':total_amount' => $data['total_amount'] ?? 0,
            ':bonus_amount' => $data['bonus_amount'] ?? 0,
            ':total_with_bonus' => $data['total_with_bonus'] ?? 0,
            ':total_no_of_agents_eligible' => $data['total_no_of_agents_eligible'] ?? 0,
            ':abandoncy_call_count' => $data['abandoncy_call_count'] ?? 0,
            ':deduction_on_abandoncy' => $data['deduction_on_abandoncy'] ?? 0,
            ':amount_after_deduction' => $data['amount_after_deduction'] ?? 0,
            ':per_agent_payable_amount' => $data['per_agent_payable_amount'] ?? 0,
            ':call_time' => $data['call_time'] ?? '',
            ':shift_time' => $data['shift_time'] ?? ''
        ];
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Get calculated incentive data
     */
    public function getIncentiveData($filters = [], $limit = 100, $offset = 0)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $incentiveDataTable = $tablePrefix . 'agent_data_incentive_data';
        
        $where = [];
        $params = [];
        
        if (!empty($filters['date'])) {
            $where[] = "date = :date";
            $params[':date'] = $filters['date'];
        }
        
        if (!empty($filters['team_name'])) {
            // Note: team_name might not be in the table, may need to join with agent_codes
            // For now, we'll skip this filter or implement it differently
        }
        
        if (!empty($filters['agent_name'])) {
            $where[] = "agent_name = :agent_name";
            $params[':agent_name'] = $filters['agent_name'];
        }
        
        if (!empty($filters['incentive_title'])) {
            $where[] = "incentive_title = :incentive_title";
            $params[':incentive_title'] = $filters['incentive_title'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // LIMIT and OFFSET cannot be bound as parameters in some MySQL versions
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT * FROM {$incentiveDataTable} {$whereClause} ORDER BY date DESC, agent_name ASC LIMIT {$limit} OFFSET {$offset}";
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get count of incentive data records
     */
    public function getIncentiveDataCount($filters = [])
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $incentiveDataTable = $tablePrefix . 'agent_data_incentive_data';
        
        $where = [];
        $params = [];
        
        if (!empty($filters['date'])) {
            $where[] = "date = :date";
            $params[':date'] = $filters['date'];
        }
        
        if (!empty($filters['agent_name'])) {
            $where[] = "agent_name = :agent_name";
            $params[':agent_name'] = $filters['agent_name'];
        }
        
        if (!empty($filters['incentive_title'])) {
            $where[] = "incentive_title = :incentive_title";
            $params[':incentive_title'] = $filters['incentive_title'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as count FROM {$incentiveDataTable} {$whereClause}";
        
        $results = $this->query($sql, $params);
        
        return !empty($results) ? (int)$results[0]['count'] : 0;
    }
}

