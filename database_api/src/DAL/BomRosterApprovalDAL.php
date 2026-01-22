<?php
/**
 * Data access for BOM roster approval workflows.
 */

namespace App\DAL;

class BomRosterApprovalDAL extends BaseDAL
{
    /**
     * @return array<string,mixed>|null
     */
    public function getRequestById(int $id): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->prefix()}manage_roster_requests
            WHERE auto_id = ?
            LIMIT 1
        ";

        return $this->queryOne($sql, [$id]);
    }

    public function updateRequestStatus(int $id, string $status): void
    {
        $sql = "
            UPDATE {$this->prefix()}manage_roster_requests
            SET status = ?
            WHERE auto_id = ?
        ";

        $this->execute($sql, [$status, $id]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getAgentByRosterCode(string $rosterCode): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->prefix()}backend_agent_codes
            WHERE roster_code = ?
            LIMIT 1
        ";

        return $this->queryOne($sql, [$rosterCode]);
    }

    public function updateAvailabilityRdo(string $rosterCode, string $rdo): void
    {
        $sql = "
            UPDATE {$this->prefix()}backend_availability_sheet
            SET rdo = ?
            WHERE roster_code = ?
        ";

        $this->execute($sql, [$rdo, $rosterCode]);
    }

    public function updateRosterShift(string $rosterCode, string $shift): void
    {
        $sql = "
            UPDATE {$this->prefix()}backend_employee_roster_bom
            SET shift_time = ?
            WHERE roster_code = ?
        ";

        $this->execute($sql, [$shift, $rosterCode]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getPendingRequests(?string $salesManager = null): array
    {
        $sql = "
            SELECT r.*, a.agent_name
            FROM {$this->prefix()}manage_roster_requests r
            LEFT JOIN {$this->prefix()}backend_agent_codes a
                ON r.roster_code = a.roster_code
            WHERE r.status = 'Pending'
              AND a.location = 'BOM'
        ";

        $params = [];
        if ($salesManager) {
            $sql .= " AND r.sale_manager = ?";
            $params[] = $salesManager;
        }

        $sql .= " ORDER BY r.auto_id DESC";

        return $this->query($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getProcessedRequests(?string $salesManager = null): array
    {
        $sql = "
            SELECT r.*, a.agent_name
            FROM {$this->prefix()}manage_roster_requests r
            LEFT JOIN {$this->prefix()}backend_agent_codes a
                ON r.roster_code = a.roster_code
            WHERE r.status <> 'Pending'
              AND a.location = 'BOM'
        ";

        $params = [];
        if ($salesManager) {
            $sql .= " AND r.sale_manager = ?";
            $params[] = $salesManager;
        }

        $sql .= " ORDER BY r.auto_id DESC";

        return $this->query($sql, $params);
    }

    /**
     * @return array<int,string>
     */
    public function getSalesManagers(): array
    {
        $sql = "
            SELECT DISTINCT r.sale_manager
            FROM {$this->prefix()}manage_roster_requests r
            LEFT JOIN {$this->prefix()}backend_agent_codes a
                ON r.roster_code = a.roster_code
            WHERE a.location = 'BOM'
            ORDER BY r.sale_manager
        ";

        return array_map(
            static fn ($row) => (string)$row['sale_manager'],
            $this->query($sql)
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getLeaveRequests(?string $salesManager = null): array
    {
        $sql = "
            SELECT lr.*, ac.agent_name
            FROM wpk4_backend_employee_roster_leaves_approval lr
            LEFT JOIN {$this->prefix()}backend_agent_codes ac
                ON lr.employee_code = ac.roster_code
            WHERE ac.location = 'BOM'
              AND MONTH(STR_TO_DATE(lr.from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1
        ";

        $params = [];
        if ($salesManager) {
            $sql .= " AND lr.sm = ?";
            $params[] = $salesManager;
        }

        $sql .= " ORDER BY lr.doc_no DESC";

        return $this->query($sql, $params);
    }

    private function prefix(): string
    {
        return $_ENV['DB_TABLE_PREFIX'] ?? 'wpk4_';
    }
}

