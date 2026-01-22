<?php

namespace App\DAL;

class QAReportDAL extends BaseDAL
{
    /**
     * Get QA users
     */
    public function getQaUsers(): array
    {
        $sql = "
            SELECT DISTINCT qa_user 
            FROM wpk4_backend_hmny_qa_eval 
            WHERE qa_user IS NOT NULL AND qa_user != '' 
            ORDER BY qa_user
        ";
        return $this->query($sql);
    }

    /**
     * Get teams
     */
    public function getTeams(?string $location = null): array
    {
        $sql = "
            SELECT DISTINCT team_name 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active' 
            AND team_name IS NOT NULL AND team_name != ''
        ";
        $params = [];
        
        if ($location) {
            $sql .= " AND location = :location";
            $params[':location'] = $location;
        }
        
        $sql .= " ORDER BY team_name";
        return $this->query($sql, $params);
    }

    /**
     * Get agents
     */
    public function getAgents(?string $location = null): array
    {
        $sql = "
            SELECT tsr as agent_id, agent_name, team_name 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active'
        ";
        $params = [];
        
        if ($location) {
            $sql .= " AND location = :location";
            $params[':location'] = $location;
        }
        
        $sql .= " AND agent_name <> 'ABDN' ORDER BY agent_name";
        return $this->query($sql, $params);
    }

    /**
     * Get SL GTIB count
     */
    public function getSlGtibCount(string $date, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(r.auto_id) 
            FROM wpk4_backend_agent_nobel_data_call_rec r 
            JOIN wpk4_backend_agent_codes c ON r.tsr = c.tsr 
            WHERE r.call_date = :date 
            AND r.appl = 'GTIB' 
            AND r.rec_status = 'SL' 
            AND r.tsr <> '' 
            AND c.team_name NOT IN ('Others','Sales Manager')
        ";
        $params = [':date' => $date];
        
        if ($team) {
            $sql .= " AND c.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND c.tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(r.auto_id)'] ?? 0);
    }

    /**
     * Get total GTIB count
     */
    public function getTotalGtibCount(string $date, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(r.auto_id) 
            FROM wpk4_backend_agent_nobel_data_call_rec r 
            WHERE r.call_date = :date 
            AND r.appl = 'GTIB' 
            AND r.tsr <> ''
        ";
        $params = [':date' => $date];
        
        if ($team || $agent) {
            $sql .= " AND r.tsr IN (SELECT tsr FROM wpk4_backend_agent_codes WHERE 1=1";
            if ($team) {
                $sql .= " AND team_name = :team";
                $params[':team'] = $team;
            }
            if ($agent) {
                $sql .= " AND tsr = :agent";
                $params[':agent'] = $agent;
            }
            $sql .= ")";
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(r.auto_id)'] ?? 0);
    }

    /**
     * Get total call count (for after-sales)
     */
    public function getTotalCallCount(string $date, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(r.auto_id) 
            FROM wpk4_backend_agent_nobel_data_call_rec r 
            WHERE r.call_date = :date 
            AND r.appl IN ('GTCS','GTPY','GTET','GTDC','GTRF','DCMD') 
            AND r.tsr <> ''
        ";
        $params = [':date' => $date];
        
        if ($team || $agent) {
            $sql .= " AND r.tsr IN (SELECT tsr FROM wpk4_backend_agent_codes WHERE 1=1";
            if ($team) {
                $sql .= " AND team_name = :team";
                $params[':team'] = $team;
            }
            if ($agent) {
                $sql .= " AND tsr = :agent";
                $params[':agent'] = $agent;
            }
            $sql .= ")";
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(r.auto_id)'] ?? 0);
    }

    /**
     * Get SL audited count
     */
    public function getSlAuditedCount(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(DISTINCT e.filenum) 
            FROM wpk4_backend_hmny_qa_eval e 
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num 
            LEFT JOIN wpk4_backend_agent_codes a ON r.tsr = a.tsr 
            WHERE r.call_date = :date 
            AND r.rec_status = 'SL'
        ";
        $params = [':date' => $date];
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND r.tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(DISTINCT e.filenum)'] ?? 0);
    }

    /**
     * Get total audited count
     */
    public function getTotalAudited(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null, string|array|null $appl = null): int
    {
        $sql = "
            SELECT COUNT(DISTINCT e.filenum) 
            FROM wpk4_backend_hmny_qa_eval e 
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr 
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num 
            WHERE r.call_date = :date
        ";
        $params = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $a;
                }
                $sql .= " AND r.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND r.appl = :appl";
                $params[':appl'] = $appl;
            }
        } else {
            $sql .= " AND r.appl = 'GTIB'";
        }
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND r.tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(DISTINCT e.filenum)'] ?? 0);
    }

    /**
     * Get auditor summary count (duration buckets)
     */
    public function getAuditorSummaryCount(string $date): array
    {
        $sql = "
            SELECT
                e.qa_user,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration <= 300 THEN rec.call_date END) as less5mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 300 AND rec.rec_duration <= 600 THEN rec.call_date END) as more5mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 600 AND rec.rec_duration <= 900 THEN rec.call_date END) as more10mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 900 AND rec.rec_duration <= 1200 THEN rec.call_date END) as more15mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1200 AND rec.rec_duration <= 1500 THEN rec.call_date END) as more20mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1500 AND rec.rec_duration <= 1800 THEN rec.call_date END) as more25mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 1800 AND rec.rec_duration <= 2100 THEN rec.call_date END) as more30mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 2100 AND rec.rec_duration <= 2400 THEN rec.call_date END) as more35mins,
                COUNT(CASE WHEN rec.appl = 'GTIB' AND rec.rec_duration > 2400 THEN rec.call_date END) as more40mins
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec rec ON e.filenum = rec.file_num
            WHERE e.qa_date = :date AND rec.appl = 'GTIB'
            GROUP BY e.qa_user
        ";
        return $this->query($sql, [':date' => $date]);
    }

    /**
     * Get auditor summary
     */
    public function getAuditorSummary(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null, string|array|null $appl = null): array
    {
        $sql = "
            SELECT 
                e.qa_user,
                COUNT(DISTINCT CASE WHEN r.rec_status = 'SL' THEN e.filenum END) AS sl_audited,
                COUNT(DISTINCT CASE WHEN r.rec_status != 'SL' THEN e.filenum END) AS non_sl_audited,
                COUNT(DISTINCT e.filenum) AS total_audited
            FROM wpk4_backend_hmny_qa_eval e
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            WHERE e.qa_date = :date
        ";
        $params = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $a;
                }
                $sql .= " AND r.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND r.appl = :appl";
                $params[':appl'] = $appl;
            }
        } else {
            $sql .= " AND r.appl = 'GTIB'";
        }
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND r.tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $sql .= " GROUP BY e.qa_user ORDER BY e.qa_user";
        return $this->query($sql, $params);
    }

    /**
     * Get insights
     */
    public function getInsights(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null, string|array|null $appl = null): ?array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN LOWER(q.question_txt) LIKE '%fatal%' AND q.ans_text = 'Yes' THEN 1 ELSE 0 END) AS fatal_count,
                SUM(CASE WHEN q.question_txt = 'Call Status' AND q.ans_text = 'Non-Compliant' THEN 1 ELSE 0 END) AS non_compliant_count,
                SUM(CASE WHEN q.question_txt = 'Call Status' AND q.ans_text = 'Compliant' THEN 1 ELSE 0 END) AS compliant_count,
                SUM(CASE WHEN q.question_txt = 'Good Call' AND q.ans_text = 'NO' THEN 1 ELSE 0 END) AS no_good_call_count
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            JOIN wpk4_backend_hmny_qa_eval_quest q ON q.eval_id = e.id
            JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            WHERE r.call_date = :date
        ";
        $params = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $a;
                }
                $sql .= " AND r.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND r.appl = :appl";
                $params[':appl'] = $appl;
            }
        } else {
            $sql .= " AND r.appl = 'GTIB'";
        }
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND e.recording_tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $result = $this->queryOne($sql, $params);
        return ($result === false) ? null : $result;
    }

    /**
     * Get agent QA summary
     */
    public function getAgentQaSummary(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null, string|array|null $appl = null): array
    {
        // Step 1: Audited calls per agent
        $sql1 = "
            SELECT 
                e.recording_date AS call_date,
                a.agent_name,
                a.team_name,
                COUNT(DISTINCT e.filenum) AS audited_call,
                COUNT(CASE WHEN q.ans_text = 'Compliant' THEN 1 END) AS count_compliant,
                COUNT(CASE WHEN q.ans_text = 'Non-Compliant' THEN 1 END) AS count_non_compliant,
                COUNT(CASE WHEN LOWER(q.question_txt) LIKE '%fatal%' AND q.ans_text = 'Yes' THEN 1 END) AS count_fatal,
                COUNT(CASE WHEN q.question_txt = 'Good Call' AND q.ans_text = 'NO' THEN 1 END) AS count_no_good,
                COUNT(CASE WHEN q.ans_text IS NULL THEN 1 END) AS status_na
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec rec ON e.filenum = rec.file_num
            LEFT JOIN wpk4_backend_hmny_qa_eval_quest q ON e.id = q.eval_id
            WHERE rec.call_date = :date
        ";
        $params1 = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params1[$key] = $a;
                }
                $sql1 .= " AND rec.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql1 .= " AND rec.appl = :appl";
                $params1[':appl'] = $appl;
            }
        } else {
            $sql1 .= " AND rec.appl = 'GTIB'";
        }
        
        $sql1 .= " AND a.status = 'active'";
        
        if ($qa_user) {
            $sql1 .= " AND e.qa_user = :qa_user";
            $params1[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql1 .= " AND a.team_name = :team";
            $params1[':team'] = $team;
        }
        if ($agent) {
            $sql1 .= " AND e.recording_tsr = :agent";
            $params1[':agent'] = $agent;
        }
        
        $sql1 .= " GROUP BY e.recording_date, a.agent_name, a.team_name";
        $audited = $this->query($sql1, $params1);
        
        // Step 2: Total calls per agent
        $sql2 = "
            SELECT 
                rec.call_date,
                a.agent_name,
                a.team_name,
                COUNT(*) AS total_call
            FROM wpk4_backend_agent_nobel_data_call_rec rec
            LEFT JOIN wpk4_backend_agent_codes a ON rec.tsr = a.tsr
            WHERE rec.call_date = :date
        ";
        $params2 = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params2[$key] = $a;
                }
                $sql2 .= " AND rec.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql2 .= " AND rec.appl = :appl";
                $params2[':appl'] = $appl;
            }
        } else {
            $sql2 .= " AND rec.appl = 'GTIB'";
        }
        
        $sql2 .= " AND a.status = 'active'";
        
        if ($team) {
            $sql2 .= " AND a.team_name = :team";
            $params2[':team'] = $team;
        }
        if ($agent) {
            $sql2 .= " AND rec.tsr = :agent";
            $params2[':agent'] = $agent;
        }
        
        $sql2 .= " GROUP BY rec.call_date, a.agent_name, a.team_name";
        $calls = $this->query($sql2, $params2);
        
        // Merge both data
        $summary = [];
        foreach ($calls as $row) {
            $key = $row['agent_name'] . '|' . $row['team_name'];
            $summary[$key] = [
                'agent_name' => $row['agent_name'],
                'team_name' => $row['team_name'],
                'call_date' => $row['call_date'],
                'total_call' => (int)$row['total_call'],
                'audited_call' => 0,
                'count_compliant' => 0,
                'count_non_compliant' => 0,
                'count_fatal' => 0,
                'count_no_good' => 0,
                'status_na' => 0,
                'fatal_percent' => '0%',
                'non_compliant_percent' => '0%',
                'compliant_percent' => '0%'
            ];
        }
        
        foreach ($audited as $row) {
            $key = $row['agent_name'] . '|' . $row['team_name'];
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'agent_name' => $row['agent_name'],
                    'team_name' => $row['team_name'],
                    'call_date' => $row['call_date'],
                    'total_call' => 0,
                    'audited_call' => 0,
                    'count_compliant' => 0,
                    'count_non_compliant' => 0,
                    'count_fatal' => 0,
                    'count_no_good' => 0,
                    'status_na' => 0,
                    'fatal_percent' => '0%',
                    'non_compliant_percent' => '0%',
                    'compliant_percent' => '0%'
                ];
            }
            $summary[$key]['audited_call'] = (int)$row['audited_call'];
            $summary[$key]['count_compliant'] = (int)$row['count_compliant'];
            $summary[$key]['count_non_compliant'] = (int)$row['count_non_compliant'];
            $summary[$key]['count_fatal'] = (int)$row['count_fatal'];
            $summary[$key]['count_no_good'] = (int)$row['count_no_good'];
            $summary[$key]['status_na'] = (int)$row['status_na'];
        }
        
        foreach ($summary as &$row) {
            $audited = $row['audited_call'];
            $row['fatal_percent'] = $audited > 0 ? round(($row['count_fatal'] / $audited) * 100, 1) . '%' : '0%';
            $row['non_compliant_percent'] = $audited > 0 ? round(($row['count_non_compliant'] / $audited) * 100, 1) . '%' : '0%';
            $row['compliant_percent'] = $audited > 0 ? round(($row['count_compliant'] / $audited) * 100, 1) . '%' : '0%';
        }
        
        return array_values($summary);
    }

    /**
     * Get detailed records
     */
    public function getDetailedRecords(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent_name = null, ?string $recording_tsr = null, string|array|null $appl = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT 
                e.id,
                e.filenum,
                e.final_score,
                e.qa_user,
                e.qa_date,
                r.call_date,
                r.rec_status,
                r.rec_duration,
                r.appl,
                a.agent_name,
                a.team_name,
                (
                    SELECT SUM(q.score)
                    FROM wpk4_backend_hmny_qa_eval_quest q
                    WHERE q.eval_id = e.id
                ) AS total_score
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            WHERE r.call_date = :date 
            AND a.status = 'active'
        ";
        $params = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $a;
                }
                $sql .= " AND r.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND r.appl = :appl";
                $params[':appl'] = $appl;
            }
        } else {
            $sql .= " AND r.appl = 'GTIB'";
        }
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($recording_tsr) {
            $sql .= " AND e.recording_tsr = :recording_tsr";
            $params[':recording_tsr'] = $recording_tsr;
        } elseif ($agent_name) {
            $sql .= " AND TRIM(a.agent_name) = TRIM(:agent_name)";
            $params[':agent_name'] = preg_replace('/\s+/', ' ', trim($agent_name));
        }
        
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $records = $this->query($sql, $params);
        
        if (empty($records)) {
            return [];
        }
        
        // Fetch answers for these records
        $evalIds = array_column($records, 'id');
        $placeholders = [];
        $params2 = [];
        foreach ($evalIds as $i => $id) {
            $key = ':id' . $i;
            $placeholders[] = $key;
            $params2[$key] = $id;
        }
        
        $sql2 = "
            SELECT 
                q.eval_id,
                q.question_txt,
                q.score as ans_text,
                q.ans_text as ans,
                n.note
            FROM wpk4_backend_hmny_qa_eval_quest q
            LEFT JOIN wpk4_backend_hmny_qa_eval_notes n ON q.notes_id = n.id
            WHERE q.eval_id IN (" . implode(',', $placeholders) . ")
        ";
        
        $answers = $this->query($sql2, $params2);
        
        // Map answers to records
        $map = [
            'greeting' => 'GREET',
            'ask' => 'ASK',
            'repeat' => 'Repeat',
            'lead' => 'Lead',
            'analyse' => 'Analyse',
            'negotiate' => 'Negotiate',
            'done_deal' => 'Done Deal',
            'terms' => 'Terms',
            'fatal' => 'FATAL',
            'call_status' => 'Call Status',
            'good_call' => 'Good Call',
            'call_drop_category' => 'Call Drop Category',
            'acknowledgment' => 'Acknowledgment',
            'security' => 'Security',
            'support' => 'Support',
            'understanding' => 'Understanding',
            'resolve' => 'Resolve',
            'end_well' => 'End well',
            'documentation' => 'Documentation / Notes',
            'misleading' => 'Misleading/ Incomplete/ Profanity/ Unprofessional'
        ];
        
        $recordsMap = [];
        foreach ($records as $record) {
            $recordsMap[$record['id']] = $record + array_fill_keys(array_keys($map), 'N/A');
        }
        
        foreach ($answers as $ans) {
            $evalId = $ans['eval_id'];
            if (!isset($recordsMap[$evalId])) continue;
            
            $questionTxt = trim($ans['question_txt']);
            foreach ($map as $fieldKey => $matchText) {
                if (stripos($questionTxt, $matchText) !== false) {
                    $isAnsField = in_array($fieldKey, ['terms', 'call_status', 'fatal', 'good_call', 'call_drop_category', 'misleading']);
                    $valueRaw = $isAnsField ? ($ans['ans'] ?? '') : ($ans['ans_text'] ?? '');
                    $ansText = htmlspecialchars($valueRaw ?: 'N/A');
                    
                    if (!empty($ans['note']) && $ans['note'] !== 'HMNY_NO_NOTE') {
                        $noteEscaped = htmlspecialchars($ans['note'], ENT_QUOTES);
                        $label = ucfirst(str_replace('_', ' ', $fieldKey));
                        $ansText = '<span class="show-note" data-note="' . $noteEscaped . '" data-label="' . $label . '">' . $ansText . ' <i class="bi bi-chat-right-text-fill text-primary"></i></span>';
                    }
                    
                    $recordsMap[$evalId][$fieldKey] = $ansText;
                    break;
                }
            }
        }
        
        return array_values($recordsMap);
    }

    /**
     * Get detailed records count
     */
    public function getDetailedRecordsCount(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent_name = null, ?string $recording_tsr = null, string|array|null $appl = null): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_backend_hmny_qa_eval e
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            WHERE r.call_date = :date 
            AND a.status = 'active'
        ";
        $params = [':date' => $date];
        
        if ($appl) {
            if (is_array($appl)) {
                $placeholders = [];
                foreach ($appl as $i => $a) {
                    $key = ':appl' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $a;
                }
                $sql .= " AND r.appl IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND r.appl = :appl";
                $params[':appl'] = $appl;
            }
        } else {
            $sql .= " AND r.appl = 'GTIB'";
        }
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($recording_tsr) {
            $sql .= " AND e.recording_tsr = :recording_tsr";
            $params[':recording_tsr'] = $recording_tsr;
        } elseif ($agent_name) {
            $sql .= " AND TRIM(a.agent_name) = TRIM(:agent_name)";
            $params[':agent_name'] = preg_replace('/\s+/', ' ', trim($agent_name));
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get DCMD summary by QA
     */
    public function getDcmdSummaryByQA(string $date, ?string $qa_user = null): array
    {
        $sql = "
            SELECT 
                e.qa_user,
                COUNT(DISTINCT e.filenum) AS dcmd,
                COUNT(DISTINCT e.filenum) AS total_audited
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            WHERE e.qa_date = :date
            AND r.appl = 'DCMD'
        ";
        $params = [':date' => $date];
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        
        $sql .= " GROUP BY e.qa_user ORDER BY e.qa_user";
        return $this->query($sql, $params);
    }

    /**
     * Get DCMD summary
     */
    public function getDcmdSummary(string $date, ?string $qa_user = null, ?string $team = null, ?string $agent = null): array
    {
        // Step 1: Audited calls per agent
        $sql1 = "
            SELECT 
                e.recording_date AS call_date,
                a.agent_name,
                a.team_name,
                COUNT(DISTINCT e.filenum) AS audited_call,
                COUNT(CASE WHEN q.ans_text = 'Compliant' THEN 1 END) AS count_compliant,
                COUNT(CASE WHEN q.ans_text = 'Non-Compliant' THEN 1 END) AS count_non_compliant,
                COUNT(CASE WHEN LOWER(q.question_txt) LIKE '%fatal%' AND q.ans_text = 'Yes' THEN 1 END) AS count_fatal,
                COUNT(CASE WHEN q.question_txt = 'Good Call' AND q.ans_text = 'NO' THEN 1 END) AS count_no_good,
                COUNT(CASE WHEN q.ans_text IS NULL THEN 1 END) AS status_na
            FROM (
                SELECT *
                FROM wpk4_backend_hmny_qa_eval e1
                WHERE e1.id = (
                    SELECT MAX(e2.id)
                    FROM wpk4_backend_hmny_qa_eval e2
                    WHERE e2.filenum = e1.filenum
                )
            ) e
            LEFT JOIN wpk4_backend_agent_codes a ON e.recording_tsr = a.tsr
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec rec ON e.filenum = rec.file_num
            LEFT JOIN wpk4_backend_hmny_qa_eval_quest q ON e.id = q.eval_id
            WHERE rec.call_date = :date
            AND rec.appl = 'DCMD'
            AND a.status = 'active'
        ";
        $params1 = [':date' => $date];
        
        if ($qa_user) {
            $sql1 .= " AND e.qa_user = :qa_user";
            $params1[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql1 .= " AND a.team_name = :team";
            $params1[':team'] = $team;
        }
        if ($agent) {
            $sql1 .= " AND e.recording_tsr = :agent";
            $params1[':agent'] = $agent;
        }
        
        $sql1 .= " GROUP BY e.recording_date, a.agent_name, a.team_name";
        $audited = $this->query($sql1, $params1);
        
        // Step 2: Total calls per agent
        $sql2 = "
            SELECT 
                rec.call_date,
                a.agent_name,
                a.team_name,
                COUNT(*) AS total_call
            FROM wpk4_backend_agent_nobel_data_call_rec rec
            LEFT JOIN wpk4_backend_agent_codes a ON rec.tsr = a.tsr
            WHERE rec.call_date = :date
            AND rec.appl = 'DCMD'
            AND a.status = 'active'
        ";
        $params2 = [':date' => $date];
        
        if ($team) {
            $sql2 .= " AND a.team_name = :team";
            $params2[':team'] = $team;
        }
        if ($agent) {
            $sql2 .= " AND rec.tsr = :agent";
            $params2[':agent'] = $agent;
        }
        
        $sql2 .= " GROUP BY rec.call_date, a.agent_name, a.team_name";
        $calls = $this->query($sql2, $params2);
        
        // Merge both data
        $summary = [];
        foreach ($calls as $row) {
            $key = $row['agent_name'] . '|' . $row['team_name'];
            $summary[$key] = [
                'agent_name' => $row['agent_name'],
                'team_name' => $row['team_name'],
                'call_date' => $row['call_date'],
                'total_call' => (int)$row['total_call'],
                'audited_call' => 0,
                'count_compliant' => 0,
                'count_non_compliant' => 0,
                'count_fatal' => 0,
                'count_no_good' => 0,
                'status_na' => 0,
                'fatal_percent' => '0%',
                'non_compliant_percent' => '0%',
                'compliant_percent' => '0%'
            ];
        }
        
        foreach ($audited as $row) {
            $key = $row['agent_name'] . '|' . $row['team_name'];
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'agent_name' => $row['agent_name'],
                    'team_name' => $row['team_name'],
                    'call_date' => $row['call_date'],
                    'total_call' => 0,
                    'audited_call' => 0,
                    'count_compliant' => 0,
                    'count_non_compliant' => 0,
                    'count_fatal' => 0,
                    'count_no_good' => 0,
                    'status_na' => 0,
                    'fatal_percent' => '0%',
                    'non_compliant_percent' => '0%',
                    'compliant_percent' => '0%'
                ];
            }
            $summary[$key]['audited_call'] = (int)$row['audited_call'];
            $summary[$key]['count_compliant'] = (int)$row['count_compliant'];
            $summary[$key]['count_non_compliant'] = (int)$row['count_non_compliant'];
            $summary[$key]['count_fatal'] = (int)$row['count_fatal'];
            $summary[$key]['count_no_good'] = (int)$row['count_no_good'];
            $summary[$key]['status_na'] = (int)$row['status_na'];
        }
        
        foreach ($summary as &$row) {
            $audited = $row['audited_call'];
            $row['fatal_percent'] = $audited > 0 ? round(($row['count_fatal'] / $audited) * 100, 1) . '%' : '0%';
            $row['non_compliant_percent'] = $audited > 0 ? round(($row['count_non_compliant'] / $audited) * 100, 1) . '%' : '0%';
            $row['compliant_percent'] = $audited > 0 ? round(($row['count_compliant'] / $audited) * 100, 1) . '%' : '0%';
        }
        
        return array_values($summary);
    }

    /**
     * Get application-specific audited counts
     */
    public function getAppAuditedCount(string $date, string $appl, ?string $qa_user = null): int
    {
        $sql = "
            SELECT COUNT(DISTINCT e.filenum) 
            FROM wpk4_backend_hmny_qa_eval e 
            WHERE e.qa_date = :date 
            AND e.appl = :appl
        ";
        $params = [':date' => $date, ':appl' => $appl];
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(DISTINCT e.filenum)'] ?? 0);
    }

    /**
     * Get total GTDC count
     */
    public function getTotalGtdcCount(string $date, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(r.auto_id) 
            FROM wpk4_backend_agent_nobel_data_call_rec r 
            WHERE r.call_date = :date 
            AND r.appl = 'GTDC'
        ";
        $params = [':date' => $date];
        
        if ($team || $agent) {
            $sql .= " AND r.tsr IN (SELECT tsr FROM wpk4_backend_agent_codes WHERE 1=1";
            if ($team) {
                $sql .= " AND team_name = :team";
                $params[':team'] = $team;
            }
            if ($agent) {
                $sql .= " AND tsr = :agent";
                $params[':agent'] = $agent;
            }
            $sql .= ")";
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(r.auto_id)'] ?? 0);
    }

    /**
     * Get total DCMD count
     */
    public function getTotalDcmdCount(string $date, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(r.auto_id) 
            FROM wpk4_backend_agent_nobel_data_call_rec r 
            WHERE r.call_date = :date 
            AND r.appl = 'DCMD'
        ";
        $params = [':date' => $date];
        
        if ($team || $agent) {
            $sql .= " AND r.tsr IN (SELECT tsr FROM wpk4_backend_agent_codes WHERE 1=1";
            if ($team) {
                $sql .= " AND team_name = :team";
                $params[':team'] = $team;
            }
            if ($agent) {
                $sql .= " AND tsr = :agent";
                $params[':agent'] = $agent;
            }
            $sql .= ")";
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['COUNT(r.auto_id)'] ?? 0);
    }

    /**
     * Get application-specific audited counts with filters
     */
    public function getAppAuditedCountWithFilters(string $date, string $appl, ?string $qa_user = null, ?string $team = null, ?string $agent = null): int
    {
        $sql = "
            SELECT COUNT(DISTINCT CASE WHEN r.appl = :appl THEN e.id END) AS total_audited
            FROM wpk4_backend_hmny_qa_eval e
            LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON e.filenum = r.file_num
            LEFT JOIN wpk4_backend_agent_codes a ON r.tsr = a.tsr
            WHERE r.call_date = :date 
            AND r.appl IN ('GTCS','GTPY','GTET','GTDC','GTRF','DCMD')
        ";
        $params = [':date' => $date, ':appl' => $appl];
        
        if ($qa_user) {
            $sql .= " AND e.qa_user = :qa_user";
            $params[':qa_user'] = $qa_user;
        }
        if ($team) {
            $sql .= " AND a.team_name = :team";
            $params[':team'] = $team;
        }
        if ($agent) {
            $sql .= " AND r.tsr = :agent";
            $params[':agent'] = $agent;
        }
        
        $result = $this->queryOne($sql, $params);
        return (int)($result['total_audited'] ?? 0);
    }
}

