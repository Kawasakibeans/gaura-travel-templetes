<?php

namespace App\DAL;

/**
 * Data Access Layer for Employee Loss Recovery operations
 */
class LossRecoveryDAL extends BaseDAL
{
    /**
     * Check if employee code exists
     * 
     * @param string $empcode
     * @return bool
     */
    public function employeeExists($empcode)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM wpk4_emp_loss_recovery WHERE empcode = :empcode";
            $result = $this->query($sql, ['empcode' => $empcode]);
            return (int)($result[0]['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Table doesn't exist, return false
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new employee loss record
     * 
     * @param array $data
     * @return int Inserted ID
     */
    public function createEmployeeLoss($data)
    {
        try {
            $sql = "
                INSERT INTO wpk4_emp_loss_recovery (
                    empcode, empname, loss_months_label, loss_signed_month, loss_signed_value,
                    total_loss_amt, previous_deduction, current_deduction, balance,
                    installment_period, installment_amount
                ) VALUES (
                    :empcode, :empname, :loss_months_label, :loss_signed_month, :loss_signed_value,
                    :total_loss_amt, :previous_deduction, :current_deduction, :balance,
                    :installment_period, :installment_amount
                )
            ";

            $this->execute($sql, [
                'empcode' => $data['empcode'],
                'empname' => $data['empname'],
                'loss_months_label' => $data['loss_months_label'],
                'loss_signed_month' => $data['loss_signed_month'],
                'loss_signed_value' => $data['loss_signed_value'],
                'total_loss_amt' => $data['total_loss_amt'],
                'previous_deduction' => $data['previous_deduction'],
                'current_deduction' => $data['current_deduction'],
                'balance' => $data['balance'],
                'installment_period' => $data['installment_period'],
                'installment_amount' => $data['installment_amount']
            ]);

            return (int)$this->lastInsertId();
        } catch (\Exception $e) {
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            throw new \Exception("Employee loss recovery table does not exist. Please create the table first.", 500);
        }
    }

    /**
     * Get employee loss record by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getEmployeeLossById($id)
    {
        try {
            $sql = "SELECT * FROM wpk4_emp_loss_recovery WHERE id = :id LIMIT 1";
            $result = $this->queryOne($sql, ['id' => $id]);
            return ($result === false) ? null : $result;
        } catch (\Exception $e) {
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update employee loss record
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateEmployeeLoss($id, $data)
    {
        try {
            $sql = "
                UPDATE wpk4_emp_loss_recovery SET
                    loss_months_label = :loss_months_label,
                    total_loss_amt = :total_loss_amt,
                    current_deduction = :current_deduction,
                    previous_deduction = :previous_deduction,
                    balance = :balance,
                    installment_period = :installment_period,
                    installment_amount = :installment_amount
                WHERE id = :id
            ";

            $this->execute($sql, array_merge($data, ['id' => $id]));
            return true;
        } catch (\Exception $e) {
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            throw new \Exception("Employee loss recovery table does not exist. Please create the table first.", 500);
        }
    }

    /**
     * Get current month deduction (B) for a case
     * 
     * @param int $caseId
     * @return float
     */
    public function getCurrentDeduction($caseId)
    {
        try {
            $currMonth = date('Y-m-01');
            $sql = "
                SELECT COALESCE(SUM(amount), 0) AS total
                FROM wpk4_emp_deductions
                WHERE caseid = :caseid AND ded_month = :month
            ";
            $result = $this->query($sql, ['caseid' => $caseId, 'month' => $currMonth]);
            return (float)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist, return 0
            error_log("Employee deductions table not found: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get previous deductions (C) for a case
     * 
     * @param int $caseId
     * @return float
     */
    public function getPreviousDeduction($caseId)
    {
        try {
            $currMonth = date('Y-m-01');
            $sql = "
                SELECT COALESCE(SUM(amount), 0) AS total
                FROM wpk4_emp_deductions
                WHERE caseid = :caseid AND ded_month < :month
            ";
            $result = $this->query($sql, ['caseid' => $caseId, 'month' => $currMonth]);
            return (float)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist, return 0
            error_log("Employee deductions table not found: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get deductions by case (grouped by month)
     * 
     * @param int $caseId
     * @return array
     */
    public function getDeductionsByCase($caseId)
    {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(ded_month, '%Y-%m') AS ym,
                    COALESCE(SUM(amount), 0) AS amount
                FROM wpk4_emp_deductions
                WHERE caseid = :caseid
                GROUP BY ym
                ORDER BY ym ASC
            ";
            return $this->query($sql, ['caseid' => $caseId]);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee deductions table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get deductions list for a case
     * 
     * @param int $caseId
     * @return array
     */
    public function getDeductionsList($caseId)
    {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(ded_month, '%Y-%m') AS ym,
                    DATE_FORMAT(ded_month, '%b %Y') AS label,
                    COALESCE(SUM(amount), 0) AS amount
                FROM wpk4_emp_deductions
                WHERE caseid = :caseid
                GROUP BY ym, label
                ORDER BY ym DESC
            ";
            return $this->query($sql, ['caseid' => $caseId]);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee deductions table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upsert deduction record
     * 
     * @param string $empcode
     * @param string $dedMonth (YYYY-MM-01 format)
     * @param float $amount
     * @param int $caseId
     * @return bool
     */
    public function upsertDeduction($empcode, $dedMonth, $amount, $caseId)
    {
        try {
            $sql = "
                INSERT INTO wpk4_emp_deductions (empcode, ded_month, amount, caseid)
                VALUES (:empcode, :ded_month, :amount, :caseid)
                ON DUPLICATE KEY UPDATE 
                    amount = VALUES(amount),
                    caseid = VALUES(caseid)
            ";

            $this->execute($sql, [
                'empcode' => $empcode,
                'ded_month' => $dedMonth,
                'amount' => $amount,
                'caseid' => $caseId
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Employee deductions table not found: " . $e->getMessage());
            throw new \Exception("Employee deductions table does not exist. Please create the table first.", 500);
        }
    }

    /**
     * Get deduction amount for specific month and case
     * 
     * @param string $empcode
     * @param string $dedMonth
     * @param int $caseId
     * @return float
     */
    public function getDeductionAmount($empcode, $dedMonth, $caseId)
    {
        try {
            $sql = "
                SELECT amount
                FROM wpk4_emp_deductions
                WHERE empcode = :empcode AND ded_month = :ded_month AND caseid = :caseid
                LIMIT 1
            ";
            $result = $this->query($sql, [
                'empcode' => $empcode,
                'ded_month' => $dedMonth,
                'caseid' => $caseId
            ]);
            return $result ? (float)($result[0]['amount'] ?? 0) : 0.0;
        } catch (\Exception $e) {
            // Table doesn't exist, return 0
            error_log("Employee deductions table not found: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get loss signed months for an employee
     * 
     * @param string $empcode
     * @return array
     */
    public function getLossSignedMonths($empcode)
    {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(loss_signed_month, '%Y-%m') AS ym,
                    DATE_FORMAT(loss_signed_month, '%b %Y') AS label,
                    DATE_FORMAT(loss_signed_month, '%Y-%m-01') AS ym_first,
                    COALESCE(loss_signed_value, 0) AS loss_val
                FROM wpk4_emp_loss_recovery
                WHERE empcode = :empcode
                ORDER BY loss_signed_month ASC
            ";
            return $this->query($sql, ['empcode' => $empcode]);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get deductions up to a specific month for an employee
     * 
     * @param string $empcode
     * @param string $upToMonth (YYYY-MM-01 format)
     * @return float
     */
    public function getDeductionsUpToMonth($empcode, $upToMonth)
    {
        try {
            $sql = "
                SELECT COALESCE(SUM(amount), 0) AS total
                FROM wpk4_emp_deductions
                WHERE empcode = :empcode AND ded_month <= :up_to_month
            ";
            $result = $this->query($sql, [
                'empcode' => $empcode,
                'up_to_month' => $upToMonth
            ]);
            return (float)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist, return 0
            error_log("Employee deductions table not found: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get installment plan for a case
     * 
     * @param int $caseId
     * @return array
     */
    public function getInstallmentPlan($caseId)
    {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(inst_month, '%Y-%m') AS ym,
                    DATE_FORMAT(inst_month, '%b %Y') AS label,
                    amount
                FROM wpk4_emp_installments
                WHERE caseid = :caseid
                ORDER BY inst_month ASC
            ";
            return $this->query($sql, ['caseid' => $caseId]);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee installments table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save installment plan (replace existing)
     * 
     * @param int $caseId
     * @param string $empcode
     * @param array $planRows Array of ['ym' => 'YYYY-MM', 'amount' => float]
     * @return bool
     */
    public function saveInstallmentPlan($caseId, $empcode, $planRows)
    {
        // Start transaction
        $this->beginTransaction();

        try {
            // Delete existing plan
            $deleteSql = "DELETE FROM wpk4_emp_installments WHERE caseid = :caseid";
            $this->execute($deleteSql, ['caseid' => $caseId]);

            // Insert new plan rows
            $insertSql = "
                INSERT INTO wpk4_emp_installments (caseid, empcode, inst_month, amount)
                VALUES (:caseid, :empcode, :inst_month, :amount)
                ON DUPLICATE KEY UPDATE 
                    amount = VALUES(amount),
                    empcode = VALUES(empcode)
            ";

            foreach ($planRows as $row) {
                $ymFirst = $row['ym'] . '-01';
                $this->execute($insertSql, [
                    'caseid' => $caseId,
                    'empcode' => $empcode,
                    'inst_month' => $ymFirst,
                    'amount' => $row['amount']
                ]);
            }

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            error_log("Employee installments table not found: " . $e->getMessage());
            throw new \Exception("Employee installments table does not exist. Please create the table first.", 500);
        }
    }

    /**
     * Get all loss recovery records with filters
     * 
     * @param array $filters
     * @return array
     */
    public function getAllLossRecords($filters = [])
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['location'])) {
                $where[] = "ac.location = :location";
                $params['location'] = $filters['location'];
            }

            if (!empty($filters['exclude_location'])) {
                $where[] = "ac.location <> :exclude_location";
                $params['exclude_location'] = $filters['exclude_location'];
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT 
                    elr.id, elr.empcode, elr.empname,
                    elr.total_loss_amt,
                    elr.installment_period, elr.installment_amount,
                    elr.loss_signed_month,
                    DATE_FORMAT(elr.loss_signed_month, '%b %Y') AS loss_month_label,
                    DATE_FORMAT(elr.loss_signed_month, '%Y-%m') AS loss_month_key,
                    COALESCE(TRIM(ac.location), '') AS location
                FROM wpk4_emp_loss_recovery elr
                LEFT JOIN wpk4_backend_agent_codes ac ON ac.roster_code = elr.empcode
                {$whereClause}
                ORDER BY elr.empname ASC, elr.loss_signed_month ASC
            ";

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distinct loss months for filter
     * 
     * @return array
     */
    public function getDistinctLossMonths()
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    DATE_FORMAT(loss_signed_month, '%Y-%m') AS ym,
                    DATE_FORMAT(loss_signed_month, '%b %Y') AS label
                FROM wpk4_emp_loss_recovery
                WHERE loss_signed_month IS NOT NULL
                ORDER BY ym DESC
            ";
            return $this->query($sql);
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active agents for dropdown
     * 
     * @param array $filters
     * @return array
     */
    public function getActiveAgents($filters = [])
    {
        $where = ["roster_code IS NOT NULL", "agent_name IS NOT NULL", "BINARY TRIM(LOWER(status)) = 'active'", "BINARY agent_name <> 'ABDN'"];
        $params = [];

        if (!empty($filters['location'])) {
            $where[] = "BINARY location = :location";
            $params['location'] = $filters['location'];
        }

        if (!empty($filters['exclude_location'])) {
            $where[] = "BINARY location <> :exclude_location";
            $params['exclude_location'] = $filters['exclude_location'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT roster_code, agent_name, location
            FROM wpk4_backend_agent_codes
            {$whereClause}
            ORDER BY agent_name ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get distinct locations
     * 
     * @param array $filters
     * @return array
     */
    public function getDistinctLocations($filters = [])
    {
        $where = ["location IS NOT NULL", "location <> ''", "TRIM(LOWER(status)) = 'active'"];
        $params = [];

        if (!empty($filters['exclude_location'])) {
            $where[] = "location <> :exclude_location";
            $params['exclude_location'] = $filters['exclude_location'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT DISTINCT TRIM(location) AS loc
            FROM wpk4_backend_agent_codes
            {$whereClause}
            ORDER BY loc ASC
        ";

        $result = $this->query($sql, $params);
        return array_column($result, 'loc');
    }

    /**
     * Get case deductions summary (B and C) for multiple cases
     * 
     * @param array $caseIds
     * @return array ['prev' => [caseid => amount], 'curr' => [caseid => amount]]
     */
    public function getCaseDeductionsSummary($caseIds)
    {
        if (empty($caseIds)) {
            return ['prev' => [], 'curr' => []];
        }

        try {
            $currMonth = date('Y-m-01');
            $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
            
            $prevMap = [];
            $currMap = [];

            // Previous deductions
            $prevSql = "
                SELECT caseid, COALESCE(SUM(amount), 0) AS prev_sum
                FROM wpk4_emp_deductions
                WHERE caseid IN ({$placeholders}) AND ded_month < ?
                GROUP BY caseid
            ";
            $prevParams = array_merge($caseIds, [$currMonth]);
            $prevStmt = $this->db->prepare($prevSql);
            $prevStmt->execute($prevParams);
            foreach ($prevStmt->fetchAll() as $row) {
                $prevMap[$row['caseid']] = (float)$row['prev_sum'];
            }

            // Current deductions
            $currSql = "
                SELECT caseid, COALESCE(SUM(amount), 0) AS curr_sum
                FROM wpk4_emp_deductions
                WHERE caseid IN ({$placeholders}) AND ded_month = ?
                GROUP BY caseid
            ";
            $currParams = array_merge($caseIds, [$currMonth]);
            $currStmt = $this->db->prepare($currSql);
            $currStmt->execute($currParams);
            foreach ($currStmt->fetchAll() as $row) {
                $currMap[$row['caseid']] = (float)$row['curr_sum'];
            }

            return ['prev' => $prevMap, 'curr' => $currMap];
        } catch (\Exception $e) {
            // Table doesn't exist, return empty maps
            error_log("Employee deductions table not found: " . $e->getMessage());
            return ['prev' => [], 'curr' => []];
        }
    }

    /**
     * Get all loss recovery records for BOM (excludes CCU location)
     * 
     * @param array $filters
     * @return array
     */
    public function getAllLossRecordsBOM($filters = [])
    {
        try {
            $where = ["ac.location <> 'CCU'"];
            $params = [];

            if (!empty($filters['location'])) {
                $where[] = "ac.location = :location";
                $params['location'] = $filters['location'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $sql = "
                SELECT 
                    elr.id, elr.empcode, elr.empname,
                    elr.total_loss_amt,
                    elr.installment_period, elr.installment_amount,
                    elr.loss_signed_month,
                    DATE_FORMAT(elr.loss_signed_month, '%b %Y') AS loss_month_label,
                    DATE_FORMAT(elr.loss_signed_month, '%Y-%m') AS loss_month_key,
                    COALESCE(TRIM(ac.location), '') AS location
                FROM wpk4_emp_loss_recovery elr
                LEFT JOIN wpk4_backend_agent_codes ac ON ac.roster_code = elr.empcode
                {$whereClause}
                ORDER BY elr.empname ASC, elr.loss_signed_month ASC
            ";

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active agents for BOM location
     * 
     * @return array
     */
    public function getActiveAgentsBOM()
    {
        try {
            $sql = "
                SELECT roster_code, agent_name, location
                FROM wpk4_backend_agent_codes
                WHERE roster_code IS NOT NULL
                    AND agent_name IS NOT NULL
                    AND TRIM(LOWER(status)) = 'active'
                    AND agent_name <> 'ABDN'
                    AND location = 'BOM'
                ORDER BY agent_name ASC
            ";
            return $this->query($sql, []);
        } catch (\Exception $e) {
            error_log("Agent codes table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get filter options for BOM (distinct months, excluding CCU locations)
     * 
     * @return array
     */
    public function getFilterOptionsBOM()
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    DATE_FORMAT(loss_signed_month, '%Y-%m') AS ym,
                    DATE_FORMAT(loss_signed_month, '%b %Y') AS label
                FROM wpk4_emp_loss_recovery
                WHERE loss_signed_month IS NOT NULL
                ORDER BY ym DESC
            ";
            return $this->query($sql, []);
        } catch (\Exception $e) {
            error_log("Employee loss recovery table not found: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distinct locations for BOM (excluding CCU)
     * 
     * @return array
     */
    public function getLocationsBOM()
    {
        try {
            $sql = "
                SELECT DISTINCT TRIM(location) AS loc
                FROM wpk4_backend_agent_codes
                WHERE location IS NOT NULL 
                    AND location <> 'CCU'
                    AND TRIM(LOWER(status)) = 'active'
                ORDER BY loc ASC
            ";
            $results = $this->query($sql, []);
            return array_column($results, 'loc');
        } catch (\Exception $e) {
            error_log("Agent codes table not found: " . $e->getMessage());
            return [];
        }
    }
}

