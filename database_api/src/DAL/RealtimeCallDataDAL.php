<?php
/**
 * Realtime Call Data Access Layer (DAL)
 * 
 * Handles all database operations for realtime call data management
 */

namespace App\DAL;

class RealtimeCallDataDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get realtime call data with filters
     * 
     * @param array $filters Array of filter conditions
     * @return array Array of call data records
     */
    public function getRealtimeCallData($filters = []) {
        $where = ['1=1'];
        $params = [];

        // Filter by team
        if (!empty($filters['team'])) {
            $where[] = "COALESCE(d.team_name, d2.team_name) = :team";
            $params[':team'] = $filters['team'];
        } else {
            $where[] = "COALESCE(d.team_name, d2.team_name) != 'DummyGT123'";
        }

        // Filter by campaign (appl)
        if (!empty($filters['campaign'])) {
            $where[] = "COALESCE(c.appl, e.cb_appl) = :campaign";
            $params[':campaign'] = $filters['campaign'];
        } else {
            $where[] = "COALESCE(c.appl, e.cb_appl) != 'DummyGT123'";
        }

        // Filter by location
        if (!empty($filters['location'])) {
            $where[] = "(COALESCE(d.location, d2.location) = :location)";
            $params[':location'] = $filters['location'];
        } else {
            $where[] = "COALESCE(d.location, d2.location) != 'DummyGT123'";
        }

        // Filter by date range (from/to)
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $where[] = "CONCAT(a.sys_date, ' ', a.sys_time) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $filters['from'];
            $params[':to_date'] = $filters['to'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'connected') {
                $where[] = "b.mstr_id IS NULL";
            } elseif ($filters['status'] === 'completed') {
                $where[] = "b.mstr_id IS NOT NULL";
                $where[] = "b.log_event = 'CALL END'";
            }
        } else {
            // Default: show connected calls (realtime)
            $where[] = "b.mstr_id IS NULL";
        }

        // Filter by duration (for history/yesterday view)
        if (!empty($filters['duration'])) {
            $where[] = "r.rec_duration IS NOT NULL";
        }

        $whereClause = implode(' AND ', $where);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 1000;
        $limit = max(1, min(10000, $limit)); // Clamp between 1 and 10000

        // Check if we need rec_duration (for history view)
        $selectRecDuration = !empty($filters['duration']) ? ', r.rec_duration' : '';
        $joinRecRealtime = !empty($filters['duration']) ? 'LEFT JOIN wpk4_backend_agent_nobel_data_call_rec_realtime r ON a.cdr_id = r.d_record_id' : '';

        $sql = "
            SELECT DISTINCT
                COALESCE(d.agent_name, d2.agent_name) AS agent_name,
                COALESCE(d.team_name, d2.team_name) AS team_name,
                COALESCE(d.location, d2.location) AS location,
                COALESCE(c.appl, e.cb_appl) AS campaign,
                a.*
                $selectRecDuration
            FROM wpk4_backend_agent_nobel_data_call_log_master a
            LEFT JOIN wpk4_backend_agent_nobel_data_call_log_sequence b ON a.mstr_id = b.mstr_id AND b.log_event = 'CALL END'
            LEFT JOIN wpk4_backend_agent_nobel_data_inboundcall_rec_realtime c ON a.cdr_id = c.d_record_id
            LEFT JOIN wpk4_backend_agent_codes d ON c.tsr = d.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_log_callback e ON a.lm_rowid = e.cb_rowid
            LEFT JOIN wpk4_backend_agent_codes d2 ON e.cb_tsr = d2.tsr
            $joinRecRealtime
            WHERE $whereClause
            ORDER BY a.sys_date DESC, a.sys_time DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct team names
     * 
     * @return array Array of distinct team names
     */
    public function getDistinctTeams() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT team_name 
            FROM wpk4_backend_agent_codes
            WHERE team_name IS NOT NULL AND team_name != ''
            ORDER BY team_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get distinct locations
     * 
     * @return array Array of distinct locations
     */
    public function getDistinctLocations() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT location 
            FROM wpk4_backend_agent_codes
            WHERE location IS NOT NULL AND location != ''
            ORDER BY location ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get distinct campaigns (appl)
     * 
     * @return array Array of distinct campaigns
     */
    public function getDistinctCampaigns() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT appl 
            FROM wpk4_backend_agent_nobel_data_call_log_master
            WHERE appl IS NOT NULL AND appl != ''
            ORDER BY appl ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get realtime call data results (using realtime tables)
     * 
     * @return array Array of realtime call data records
     */
    public function getRealtimeCallDataResults() {
        $sql = "
            SELECT DISTINCT
                COALESCE(d.agent_name, d2.agent_name) AS agent_name,
                COALESCE(d.team_name, d2.team_name) AS team_name,
                COALESCE(d.location, d2.location) AS location,
                COALESCE(c.appl, e.cb_appl) AS campaign,
                a.*
            FROM wpk4_backend_agent_nobel_data_call_log_master_realtime a
            LEFT JOIN wpk4_backend_agent_nobel_data_call_log_sequence_realtime b ON a.mstr_id = b.mstr_id AND b.log_event = 'CALL END'
            LEFT JOIN (
                SELECT
                    d_record_id,
                    MAX(call_time) AS latest_call_time
                FROM wpk4_backend_agent_nobel_data_inboundcall_rec_realtime
                GROUP BY d_record_id
            ) latest_call ON a.cdr_id = latest_call.d_record_id
            LEFT JOIN wpk4_backend_agent_nobel_data_inboundcall_rec_realtime c ON latest_call.d_record_id = c.d_record_id AND latest_call.latest_call_time = c.call_time
            LEFT JOIN wpk4_backend_agent_codes d ON c.tsr = d.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_log_callback_realtime e ON a.lm_rowid = e.cb_rowid
            LEFT JOIN wpk4_backend_agent_codes d2 ON e.cb_tsr = d2.tsr
            WHERE b.mstr_id IS NULL AND a.sys_date = CURRENT_DATE()
            ORDER BY a.added_on ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if there are new requests by comparing last added_on timestamp
     * 
     * @param string $lastValue Last known added_on timestamp
     * @return array Array with 'has_new' boolean and 'latest_added_on' timestamp
     */
    public function checkNewRequests($lastValue = null) {
        $stmt = $this->pdo->prepare("
            SELECT added_on 
            FROM wpk4_backend_agent_nobel_data_call_log_master 
            ORDER BY added_on DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $latestAddedOn = $result['added_on'] ?? null;
        $hasNew = ($latestAddedOn !== null && $latestAddedOn !== $lastValue);
        
        return [
            'has_new' => $hasNew,
            'latest_added_on' => $latestAddedOn,
            'last_value' => $lastValue
        ];
    }
}

