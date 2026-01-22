<?php

namespace App\DAL;

/**
 * Data Access Layer for Auditor/QA operations
 */
class AuditorDAL extends BaseDAL
{
    /**
     * Get filter options (QA users, teams, agents)
     * 
     * @return array
     */
    public function getFilterOptions()
    {
        // Get QA users - handle case where table might not exist
        $qaUsers = [];
        try {
            $qaUsers = $this->query("
                SELECT DISTINCT qa_user 
                FROM wpk4_backend_qa_evaluation 
                WHERE qa_user IS NOT NULL AND qa_user != ''
                ORDER BY qa_user ASC
            ");
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("QA evaluation table not found: " . $e->getMessage());
        }

        // Get teams
        $teams = $this->query("
            SELECT DISTINCT team_name 
            FROM wpk4_backend_agent_codes 
            WHERE team_name IS NOT NULL 
                AND team_name != '' 
                AND team_name != 'Others'
            ORDER BY team_name ASC
        ");

        // Get agents with team info
        $agents = $this->query("
            SELECT DISTINCT 
                ac.tsr AS agent_id,
                ac.agent_name,
                ac.team_name
            FROM wpk4_backend_agent_codes ac
            WHERE BINARY ac.status = 'active'
                AND ac.agent_name IS NOT NULL 
                AND ac.agent_name != ''
                AND ac.team_name != 'Others'
            ORDER BY ac.team_name, ac.agent_name ASC
        ");

        return [
            'qa_users' => array_column($qaUsers, 'qa_user'),
            'teams' => array_column($teams, 'team_name'),
            'agents' => $agents
        ];
    }

    /**
     * Get scorecard summary data
     * 
     * @param array $filters
     * @return array
     */
    public function getScorecard($filters)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT 
                    SUM(CASE WHEN qe.rec_status = 'SL' THEN 1 ELSE 0 END) AS sl_audited,
                    SUM(CASE WHEN qe.rec_status != 'SL' OR qe.rec_status IS NULL THEN 1 ELSE 0 END) AS non_sl_audited,
                    COUNT(*) AS total_audited,
                    SUM(CASE WHEN qe.fatal = 'Yes' THEN 1 ELSE 0 END) AS fatal_count,
                    SUM(CASE WHEN qe.call_status = 'Non-Compliant' THEN 1 ELSE 0 END) AS non_compliant_count,
                    SUM(CASE WHEN qe.good_call = 'NO' THEN 1 ELSE 0 END) AS no_good_call_count
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
            ";

            $result = $this->query($sql, $this->buildParams($filters));
            return $result[0] ?? [];
        } catch (\Exception $e) {
            // Table doesn't exist, return empty scorecard
            error_log("QA evaluation table not found: " . $e->getMessage());
            return [
                'sl_audited' => 0,
                'non_sl_audited' => 0,
                'total_audited' => 0,
                'fatal_count' => 0,
                'non_compliant_count' => 0,
                'no_good_call_count' => 0
            ];
        }
    }

    /**
     * Get auditor summary (grouped by QA user)
     * 
     * @param array $filters
     * @return array
     */
    public function getAuditorSummary($filters)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT 
                    qe.qa_user,
                    SUM(CASE WHEN qe.rec_status = 'SL' THEN 1 ELSE 0 END) AS sl_audited,
                    SUM(CASE WHEN qe.rec_status != 'SL' OR qe.rec_status IS NULL THEN 1 ELSE 0 END) AS non_sl_audited,
                    COUNT(*) AS total_audited,
                    ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM wpk4_backend_qa_evaluation WHERE 1=1 {$where}), 0), 2) AS coverage_rate,
                    SUM(CASE WHEN qe.fatal = 'Yes' THEN 1 ELSE 0 END) AS fatal_calls
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
                GROUP BY qe.qa_user
                ORDER BY total_audited DESC
            ";

            return $this->query($sql, $this->buildParams($filters));
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("QA evaluation table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get auditor summary by duration ranges
     * 
     * @param array $filters
     * @return array
     */
    public function getAuditorSummaryCount($filters)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT 
                    qe.qa_user,
                    SUM(CASE WHEN qe.rec_duration < 300 THEN 1 ELSE 0 END) AS less5mins,
                    SUM(CASE WHEN qe.rec_duration >= 300 AND qe.rec_duration < 600 THEN 1 ELSE 0 END) AS more5mins,
                    SUM(CASE WHEN qe.rec_duration >= 600 AND qe.rec_duration < 900 THEN 1 ELSE 0 END) AS more10mins,
                    SUM(CASE WHEN qe.rec_duration >= 900 AND qe.rec_duration < 1200 THEN 1 ELSE 0 END) AS more15mins,
                    SUM(CASE WHEN qe.rec_duration >= 1200 AND qe.rec_duration < 1500 THEN 1 ELSE 0 END) AS more20mins,
                    SUM(CASE WHEN qe.rec_duration >= 1500 AND qe.rec_duration < 1800 THEN 1 ELSE 0 END) AS more25mins,
                    SUM(CASE WHEN qe.rec_duration >= 1800 AND qe.rec_duration < 2100 THEN 1 ELSE 0 END) AS more30mins,
                    SUM(CASE WHEN qe.rec_duration >= 2100 AND qe.rec_duration < 2400 THEN 1 ELSE 0 END) AS more35mins,
                    SUM(CASE WHEN qe.rec_duration >= 2400 THEN 1 ELSE 0 END) AS more40mins
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
                GROUP BY qe.qa_user
                ORDER BY qe.qa_user ASC
            ";

            return $this->query($sql, $this->buildParams($filters));
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("QA evaluation table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get agent summary
     * 
     * @param array $filters
     * @return array
     */
    public function getAgentSummary($filters)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT 
                    COALESCE(ac.agent_name, qe.recording_tsr) AS agent_name,
                    ac.team_name,
                    COUNT(*) AS total_call,
                    COUNT(DISTINCT qe.id) AS audited_call,
                    ROUND(SUM(CASE WHEN qe.fatal = 'Yes' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS fatal_percent,
                    ROUND(SUM(CASE WHEN qe.call_status = 'Non-Compliant' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS non_compliant_percent,
                    ROUND(SUM(CASE WHEN qe.good_call = 'NO' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS no_good_percent
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
                GROUP BY ac.agent_name, ac.team_name, qe.recording_tsr
                ORDER BY ac.team_name, ac.agent_name ASC
            ";

            return $this->query($sql, $this->buildParams($filters));
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("QA evaluation table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed audit records (paginated)
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getDetailedRecords($filters, $limit = 25, $offset = 0)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT 
                    qe.id,
                    qe.filenum,
                    qe.final_score,
                    qe.qa_user,
                    qe.qa_date,
                    qe.call_date,
                    qe.rec_status,
                    qe.rec_duration,
                    qe.total_score,
                    COALESCE(ac.agent_name, qe.recording_tsr) AS agent_name,
                    ac.team_name,
                    qe.greeting,
                    qe.ask,
                    qe.repeat,
                    qe.lead,
                    qe.analyse,
                    qe.negotiate,
                    qe.done_deal,
                    qe.terms,
                    qe.fatal,
                    qe.call_status,
                    qe.good_call
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
                ORDER BY qe.qa_date DESC, qe.id DESC
                LIMIT :limit OFFSET :offset
            ";

            $params = array_merge($this->buildParams($filters), [
                'limit' => (int)$limit,
                'offset' => (int)$offset
            ]);

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("QA evaluation table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of detailed records
     * 
     * @param array $filters
     * @return int
     */
    public function getDetailedRecordsCount($filters)
    {
        try {
            $where = $this->buildWhereClause($filters);

            $sql = "
                SELECT COUNT(*) AS total
                FROM wpk4_backend_qa_evaluation qe
                LEFT JOIN wpk4_backend_agent_codes ac ON BINARY qe.recording_tsr = BINARY ac.tsr
                WHERE 1=1 {$where}
            ";

            $result = $this->query($sql, $this->buildParams($filters));
            return (int)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist, return 0
            error_log("QA evaluation table not found: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build WHERE clause from filters
     * 
     * @param array $filters
     * @return string
     */
    private function buildWhereClause($filters)
    {
        $conditions = [];

        if (!empty($filters['date'])) {
            $conditions[] = "qe.qa_date = :date";
        }

        if (!empty($filters['qa_user'])) {
            $conditions[] = "qe.qa_user = :qa_user";
        }

        if (!empty($filters['team_name'])) {
            $conditions[] = "BINARY ac.team_name = :team_name";
        }

        if (!empty($filters['agent_id'])) {
            $conditions[] = "ac.tsr = :agent_id";
        }

        return $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    }

    /**
     * Build parameters array from filters
     * 
     * @param array $filters
     * @return array
     */
    private function buildParams($filters)
    {
        $params = [];

        if (!empty($filters['date'])) {
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['qa_user'])) {
            $params['qa_user'] = $filters['qa_user'];
        }

        if (!empty($filters['team_name'])) {
            $params['team_name'] = $filters['team_name'];
        }

        if (!empty($filters['agent_id'])) {
            $params['agent_id'] = (int)$filters['agent_id'];
        }

        return $params;
    }
}

