<?php
/**
 * Agent calls view DAL.
 */

namespace App\DAL;

class AgentCallsDAL extends BaseDAL
{
    public function getAgents(): array
    {
        $sql = "
            SELECT DISTINCT agent_name
            FROM {$this->prefix()}backend_agent_codes
            WHERE agent_name IS NOT NULL
              AND agent_name <> 'ABDN'
              AND status = 'active'
              AND department LIKE '%Sales%'
            ORDER BY agent_name ASC
        ";

        return $this->query($sql);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function getCalls(array $filters): array
    {
        $where = ["n.appl = 'GTIB'"];
        $params = [];

        if (!empty($filters['agent_name'])) {
            $where[] = "a.agent_name = ?";
            $params[] = $filters['agent_name'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "n.call_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "n.call_date <= ?";
            $params[] = $filters['date_to'];
        }

        $limit = (int)($filters['limit'] ?? 1000);

        $sql = "
            SELECT
                t1.id,
                t1.call_id,
                t1.upload_ts,
                t1.analysis_result,
                t1.lang_used,
                t1.score,
                t1.urgency,
                n.tsr,
                n.call_date,
                n.call_time,
                n.file_num,
                n.country_id,
                n.areacode,
                n.phone,
                n.appl,
                a.agent_name
            FROM {$this->prefix()}backend_agent_nobel_data_call_rec n
            LEFT JOIN call_audio_records_new t1
                ON n.vox_file_name COLLATE utf8mb4_0900_ai_ci = t1.call_id COLLATE utf8mb4_0900_ai_ci
            LEFT JOIN {$this->prefix()}backend_agent_codes a
                ON a.tsr COLLATE utf8mb4_0900_ai_ci = n.tsr COLLATE utf8mb4_0900_ai_ci
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t1.upload_ts DESC
            LIMIT ?
        ";

        $params[] = $limit;

        return $this->query($sql, $params);
    }

    /**
     * @return array<int,string>
     */
    public function getLocalPaths(string $callId): array
    {
        $sql = "
            SELECT local_path
            FROM call_audio_jobs
            WHERE call_id = ?
              AND local_path IS NOT NULL
              AND local_path <> ''
        ";

        $rows = $this->query($sql, [$callId]);
        return array_values(array_filter(array_map(fn ($row) => $row['local_path'] ?? '', $rows)));
    }

    public function clearLocalPaths(string $callId): void
    {
        $sql = "
            UPDATE call_audio_jobs
            SET local_path = NULL
            WHERE call_id = ?
        ";

        $this->execute($sql, [$callId]);
    }

    private function prefix(): string
    {
        return $_ENV['DB_TABLE_PREFIX'] ?? 'wpk4_';
    }
}

