<?php

namespace App\Services;

use App\DAL\LossRecoveryDAL;

/**
 * Service layer for Employee Loss Recovery operations
 */
class LossRecoveryService
{
    private $lossRecoveryDAL;

    public function __construct()
    {
        $this->lossRecoveryDAL = new LossRecoveryDAL();
    }

    /**
     * Create new employee loss record
     * 
     * @param array $input
     * @return array
     */
    public function createEmployeeLoss($input)
    {
        $empcode = trim($input['empcode'] ?? '');
        $empname = trim($input['empname'] ?? '');
        $lossLbl = trim($input['loss_month'] ?? '');
        $AInput = (float)($input['total_loss_amt'] ?? 0);
        $CInput = (float)($input['previous_deduction'] ?? 0);
        $Y = (int)($input['installment_period'] ?? 0);
        $Z = (float)($input['installment_amount'] ?? 0);
        $ymOpt = trim($input['loss_month_add'] ?? '');
        $vOpt = (float)($input['loss_value_add'] ?? 0);

        if (empty($empcode) || empty($empname)) {
            throw new \Exception('EmpCode and EmpName are required', 400);
        }

        if ($this->lossRecoveryDAL->employeeExists($empcode)) {
            throw new \Exception('EmpCode already exists', 400);
        }

        $ymFirst = $ymOpt ? ($ymOpt . '-01') : date('Y-m-01');
        $prettyLabel = date('M Y', strtotime($ymFirst));

        $A = $AInput + ($vOpt > 0 ? $vOpt : 0);

        if ($Y > 0 && $Z <= 0) {
            $Z = max(0, round(($A - $CInput) / $Y, 2));
        }

        $B = 0.0;
        $C = $CInput;
        $balance = $A - ($B + $C);

        $id = $this->lossRecoveryDAL->createEmployeeLoss([
            'empcode' => $empcode,
            'empname' => $empname,
            'loss_months_label' => $lossLbl ?: $prettyLabel,
            'loss_signed_month' => $ymFirst,
            'loss_signed_value' => $vOpt,
            'total_loss_amt' => $A,
            'previous_deduction' => $C,
            'current_deduction' => $B,
            'balance' => $balance,
            'installment_period' => $Y,
            'installment_amount' => $Z
        ]);

        return [
            'id' => $id,
            'empcode' => $empcode,
            'empname' => $empname,
            'total_loss_amt' => round($A, 2),
            'current_deduction' => round($B, 2),
            'previous_deduction' => round($C, 2),
            'balance' => round($balance, 2),
            'installment_period' => $Y,
            'installment_amount' => round($Z, 2),
            'months_label' => $lossLbl ?: $prettyLabel
        ];
    }

    /**
     * Update employee loss record
     * 
     * @param int $id
     * @param array $input
     * @return array
     */
    public function updateEmployeeLoss($id, $input)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception("Employee loss record with ID {$id} not found", 404);
        }

        $totalLossAmt = isset($input['total_loss_amt']) ? (float)$input['total_loss_amt'] : (float)$emp['total_loss_amt'];
        // Support both 'loss_months_label' and 'loss_month' for backward compatibility
        $lossMonthsLabel = trim($input['loss_months_label'] ?? $input['loss_month'] ?? $emp['loss_months_label'] ?? '');
        $installmentPeriod = (int)($input['installment_period'] ?? $emp['installment_period'] ?? 0);
        $installmentAmount = (float)($input['installment_amount'] ?? $emp['installment_amount'] ?? 0);
        
        // If current_deduction and previous_deduction are provided, use them; otherwise calculate from database
        $currentDeduction = isset($input['current_deduction']) ? (float)$input['current_deduction'] : null;
        $previousDeduction = isset($input['previous_deduction']) ? (float)$input['previous_deduction'] : null;

        // Use provided values or calculate from database
        $B = $currentDeduction !== null ? $currentDeduction : $this->lossRecoveryDAL->getCurrentDeduction($id);
        $C = $previousDeduction !== null ? $previousDeduction : $this->lossRecoveryDAL->getPreviousDeduction($id);

        // If balance is provided, use it; otherwise calculate
        $balance = isset($input['balance']) ? (float)$input['balance'] : ($totalLossAmt - ($B + $C));

        if ($installmentPeriod > 0 && $installmentAmount <= 0) {
            $installmentAmount = max(0, round(($totalLossAmt - $C) / $installmentPeriod, 2));
        }

        $this->lossRecoveryDAL->updateEmployeeLoss($id, [
            'loss_months_label' => $lossMonthsLabel,
            'total_loss_amt' => $totalLossAmt,
            'current_deduction' => $B,
            'previous_deduction' => $C,
            'balance' => $balance,
            'installment_period' => $installmentPeriod,
            'installment_amount' => $installmentAmount
        ]);

        return [
            'total_loss_amt' => round($totalLossAmt, 2),
            'current_deduction' => round($B, 2),
            'previous_deduction' => round($C, 2),
            'balance' => round($balance, 2),
            'installment_period' => $installmentPeriod,
            'installment_amount' => round($installmentAmount, 2),
            'loss_months_label' => $lossMonthsLabel
        ];
    }

    /**
     * Add loss-signed record
     * 
     * @param int $id
     * @param array $input
     * @return array
     */
    public function addLossSignedRecord($id, $input)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception('Employee not found', 404);
        }

        $ym = trim($input['loss_month_add'] ?? '');
        $val = (float)($input['loss_value_add'] ?? 0);

        if (empty($ym)) {
            $missing = [];
            if (empty($input['loss_month_add'] ?? '')) {
                $missing[] = 'loss_month_add';
            }
            $errorMsg = 'Missing required data';
            if (!empty($missing)) {
                $errorMsg .= ': ' . implode(', ', $missing);
            }
            $errorMsg .= '. Please provide loss_month_add (YYYY-MM format) and optionally loss_value_add.';
            throw new \Exception($errorMsg, 400);
        }

        $empcode = $emp['empcode'];
        $ymFirst = $ym . '-01';

        $newId = $this->lossRecoveryDAL->createEmployeeLoss([
            'empcode' => $empcode,
            'empname' => $emp['empname'],
            'loss_months_label' => '',
            'loss_signed_month' => $ymFirst,
            'loss_signed_value' => $val,
            'total_loss_amt' => $val,
            'previous_deduction' => 0,
            'current_deduction' => 0,
            'balance' => 0,
            'installment_period' => (int)$emp['installment_period'],
            'installment_amount' => (float)$emp['installment_amount']
        ]);

        $B = $this->lossRecoveryDAL->getCurrentDeduction($newId);
        $C = $this->lossRecoveryDAL->getPreviousDeduction($newId);
        $balance = $val - ($B + $C);

        $this->lossRecoveryDAL->updateEmployeeLoss($newId, [
            'loss_months_label' => '',
            'total_loss_amt' => $val,
            'current_deduction' => $B,
            'previous_deduction' => $C,
            'balance' => $balance,
            'installment_period' => (int)$emp['installment_period'],
            'installment_amount' => (float)$emp['installment_amount']
        ]);

        return [
            'id' => $newId,
            'empcode' => $empcode,
            'empname' => $emp['empname'],
            'total_loss_amt' => round($val, 2),
            'current_deduction' => round($B, 2),
            'previous_deduction' => round($C, 2),
            'balance' => round($balance, 2)
        ];
    }

    /**
     * Get deductions by case
     * 
     * @param int $caseId
     * @return array
     */
    public function getDeductionsByCase($caseId)
    {
        $rows = $this->lossRecoveryDAL->getDeductionsByCase($caseId);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['ym']] = (float)$r['amount'];
        }
        return $map;
    }

    /**
     * Get deductions list
     * 
     * @param int $caseId
     * @return array
     */
    public function getDeductionsList($caseId)
    {
        return $this->lossRecoveryDAL->getDeductionsList($caseId);
    }

    /**
     * Update deductions (bulk)
     * 
     * @param int $id
     * @param array $input
     * @return array
     */
    public function updateDeductions($id, $input)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception("Employee loss record with ID {$id} not found in wpk4_emp_loss_recovery table", 404);
        }

        $empcode = $emp['empcode'];
        $A = (float)$emp['total_loss_amt'];
        $changed = false;
        $validFieldsFound = false;

        // Process deduction fields (ded_YYYY-MM format)
        foreach ($input as $key => $val) {
            if (preg_match('/^ded_(\d{4}-\d{2})$/', $key, $m)) {
                $validFieldsFound = true;
                $ymFirst = $m[1] . '-01';
                $newAmt = round((float)$val, 2);
                $oldAmt = $this->lossRecoveryDAL->getDeductionAmount($empcode, $ymFirst, $id);

                if ($newAmt !== $oldAmt) {
                    $changed = true;
                    $this->lossRecoveryDAL->upsertDeduction($empcode, $ymFirst, $newAmt, $id);
                }
            }
        }

        if (!$validFieldsFound) {
            throw new \Exception('No valid deduction fields found. Please provide fields in format: ded_YYYY-MM (e.g., ded_2025-02)', 400);
        }

        $B = $this->lossRecoveryDAL->getCurrentDeduction($id);
        $C = $this->lossRecoveryDAL->getPreviousDeduction($id);
        $balance = $A - ($B + $C);

        if ($changed) {
            $this->lossRecoveryDAL->updateEmployeeLoss($id, [
                'loss_months_label' => $emp['loss_months_label'] ?? '',
                'total_loss_amt' => $A,
                'current_deduction' => $B,
                'previous_deduction' => $C,
                'balance' => $balance,
                'installment_period' => (int)($emp['installment_period'] ?? 0),
                'installment_amount' => (float)($emp['installment_amount'] ?? 0)
            ]);
        }

        return [
            'changed' => $changed,
            'total_loss_amt' => round($A, 2),
            'current_deduction' => round($B, 2),
            'previous_deduction' => round($C, 2),
            'balance' => round($balance, 2)
        ];
    }

    /**
     * Add deduction record
     * 
     * @param int $id
     * @param array $input
     * @return array
     */
    public function addDeductionRecord($id, $input)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception('Employee not found', 404);
        }

        $ym = trim($input['ded_month_add'] ?? '');
        $amount = (float)($input['ded_amount_add'] ?? 0);

        if (empty($ym)) {
            throw new \Exception('Missing data', 400);
        }

        $empcode = $emp['empcode'];
        $A = (float)$emp['total_loss_amt'];
        $ymFirst = $ym . '-01';

        $this->lossRecoveryDAL->upsertDeduction($empcode, $ymFirst, $amount, $id);

        $B = $this->lossRecoveryDAL->getCurrentDeduction($id);
        $C = $this->lossRecoveryDAL->getPreviousDeduction($id);
        $balance = $A - ($B + $C);

        $this->lossRecoveryDAL->updateEmployeeLoss($id, [
            'loss_months_label' => $emp['loss_months_label'] ?? '',
            'total_loss_amt' => $A,
            'current_deduction' => $B,
            'previous_deduction' => $C,
            'balance' => $balance,
            'installment_period' => (int)($emp['installment_period'] ?? 0),
            'installment_amount' => (float)($emp['installment_amount'] ?? 0)
        ]);

        return [
            'total_loss_amt' => round($A, 2),
            'current_deduction' => round($B, 2),
            'previous_deduction' => round($C, 2),
            'balance' => round($balance, 2)
        ];
    }

    /**
     * Get installment plan
     * 
     * @param int $caseId
     * @return array
     */
    public function getInstallmentPlan($caseId)
    {
        return $this->lossRecoveryDAL->getInstallmentPlan($caseId);
    }

    /**
     * Save installment plan
     * 
     * @param int $id
     * @param array $input
     * @return array
     */
    public function saveInstallmentPlan($id, $input)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception('Row not found', 404);
        }

        $empcode = $emp['empcode'];
        $A = (float)$emp['total_loss_amt'];

        // Parse plan data from input
        $plan = [];
        foreach ($input as $k => $v) {
            if (preg_match('/^plan\[(\d+)\]\[(ym|amount)\]$/', $k, $m)) {
                $idx = (int)$m[1];
                $field = $m[2];
                if (!isset($plan[$idx])) {
                    $plan[$idx] = ['ym' => '', 'amount' => 0.0];
                }
                if ($field === 'ym') {
                    $plan[$idx]['ym'] = trim($v);
                }
                if ($field === 'amount') {
                    $plan[$idx]['amount'] = round((float)$v, 2);
                }
            }
        }

        // Direct plan array support
        if (empty($plan) && isset($input['plan']) && is_array($input['plan'])) {
            foreach ($input['plan'] as $row) {
                if (is_array($row)) {
                    $ym = isset($row['ym']) ? trim($row['ym']) : '';
                    $amt = isset($row['amt']) ? round((float)$row['amt'], 2)
                        : (isset($row['amount']) ? round((float)$row['amount'], 2) : 0.0);
                    if ($ym && $amt > 0) {
                        $plan[] = ['ym' => $ym, 'amount' => $amt];
                    }
                }
            }
        }

        // JSON fallback
        if (empty($plan) && isset($input['plan_json'])) {
            $json = json_decode(stripslashes((string)$input['plan_json']), true);
            if (is_array($json)) {
                foreach ($json as $row) {
                    $ym = isset($row['ym']) ? trim($row['ym']) : '';
                    $amt = isset($row['amt']) ? round((float)$row['amt'], 2)
                        : (isset($row['amount']) ? round((float)$row['amount'], 2) : 0.0);
                    if ($ym && $amt > 0) {
                        $plan[] = ['ym' => $ym, 'amount' => $amt];
                    }
                }
            }
        }

        // Validate and clean
        $clean = [];
        $sum = 0.0;
        $minYm = date('Y-m', strtotime('first day of next month'));
        $errors = [];

        foreach ($plan as $idx => $row) {
            $ym = $row['ym'] ?? '';
            $amt = $row['amount'] ?? 0;

            if (!$ym) {
                $errors[] = "Row " . ($idx + 1) . ": Missing month (ym)";
                continue;
            }
            if ($amt <= 0) {
                $errors[] = "Row " . ($idx + 1) . ": Amount must be greater than 0 (got: {$amt})";
                continue;
            }
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
                $errors[] = "Row " . ($idx + 1) . ": Invalid month format '{$ym}' (expected: YYYY-MM)";
                continue;
            }
            if ($ym < $minYm) {
                $errors[] = "Row " . ($idx + 1) . ": Month '{$ym}' must be >= '{$minYm}' (from next month onward)";
                continue;
            }

            $clean[] = ['ym' => $ym, 'amount' => $amt];
            $sum += $amt;
        }

        if (empty($clean)) {
            $errorMsg = 'Please add at least one valid plan row.';
            if (!empty($errors)) {
                $errorMsg .= ' Errors: ' . implode('; ', $errors);
            } elseif (empty($plan)) {
                $errorMsg .= ' No plan data provided. Please provide plan array or plan_json.';
            }
            throw new \Exception($errorMsg, 400);
        }

        $this->lossRecoveryDAL->saveInstallmentPlan($id, $empcode, $clean);

        return [
            'sum' => round($sum, 2),
            'matches_total' => (abs($sum - $A) < 0.005),
            'total_loss_amt' => round($A, 2)
        ];
    }

    /**
     * Get loss signed detail
     * 
     * @param int $id
     * @return array
     */
    public function getLossSignedDetail($id)
    {
        $emp = $this->lossRecoveryDAL->getEmployeeLossById($id);
        if (!$emp) {
            throw new \Exception('Employee not found', 404);
        }

        $empcode = $emp['empcode'];
        $A_total = (float)$emp['total_loss_amt'];
        $Y = (int)($emp['installment_period'] ?? 0);

        $months = $this->lossRecoveryDAL->getLossSignedMonths($empcode);

        $headers = [];
        $lossSigned = [];
        $totalPerMonth = [];
        $balancePerMonth = [];

        $cumA = 0.0;
        foreach ($months as $m) {
            $headers[] = ['ym' => $m['ym'], 'label' => $m['label']];
            $lossSigned[] = round((float)$m['loss_val'], 2);
            $cumA += (float)$m['loss_val'];
            $totalPerMonth[] = round($cumA, 2);

            $dedUpTo = $this->lossRecoveryDAL->getDeductionsUpToMonth($empcode, $m['ym_first']);
            $balancePerMonth[] = round($cumA - $dedUpTo, 2);
        }

        $planRows = $this->lossRecoveryDAL->getInstallmentPlan($id);

        return [
            'empname' => $emp['empname'],
            'headers' => $headers,
            'loss_signed' => $lossSigned,
            'total_per_month' => $totalPerMonth,
            'balance_per_month' => $balancePerMonth,
            'plan' => $planRows,
            'A_total' => round($A_total, 2),
            'Y' => $Y
        ];
    }

    /**
     * Get all loss records with filters
     * 
     * @param array $filters
     * @return array
     */
    public function getAllLossRecords($filters = [])
    {
        $records = $this->lossRecoveryDAL->getAllLossRecords($filters);

        // Get case deductions for B and C
        $caseIds = array_column($records, 'id');
        $deductionsSummary = $this->lossRecoveryDAL->getCaseDeductionsSummary($caseIds);
        $casePrevMap = $deductionsSummary['prev'];
        $caseCurrMap = $deductionsSummary['curr'];

        // Enrich records
        foreach ($records as &$r) {
            $rowId = $r['id'];
            $B = $caseCurrMap[$rowId] ?? 0.0;
            $C = $casePrevMap[$rowId] ?? 0.0;

            $r['current_deduction'] = $B;
            $r['previous_deduction'] = $C;
            $r['balance'] = (float)$r['total_loss_amt'] - ($B + $C);
            $r['tally'] = ((int)$r['installment_period']) * ((float)$r['installment_amount']);
        }
        unset($r);

        return $records;
    }

    /**
     * Get filter options
     * 
     * @param array $filters
     * @return array
     */
    public function getFilterOptions($filters = [])
    {
        return [
            'month_options' => $this->lossRecoveryDAL->getDistinctLossMonths(),
            'agents' => $this->lossRecoveryDAL->getActiveAgents($filters),
            'locations' => $this->lossRecoveryDAL->getDistinctLocations($filters)
        ];
    }

    /**
     * Get all loss recovery records for BOM
     * 
     * @param array $filters
     * @return array
     */
    public function getAllLossRecordsBOM($filters = [])
    {
        $records = $this->lossRecoveryDAL->getAllLossRecordsBOM($filters);
        $caseIds = array_column($records, 'id');
        $deductions = $this->lossRecoveryDAL->getCaseDeductionsSummary($caseIds);

        $currMonth = date('Y-m-01');
        foreach ($records as &$record) {
            $id = $record['id'];
            $B = $deductions['curr'][$id] ?? 0.0;
            $C = $deductions['prev'][$id] ?? 0.0;
            
            $record['current_deduction'] = round($B, 2);
            $record['previous_deduction'] = round($C, 2);
            $record['balance'] = round((float)$record['total_loss_amt'] - ($B + $C), 2);
            $record['tally'] = round((int)$record['installment_period'] * (float)$record['installment_amount'], 2);
        }
        unset($record);

        return $records;
    }

    /**
     * Get active agents for BOM
     * 
     * @return array
     */
    public function getActiveAgentsBOM()
    {
        return $this->lossRecoveryDAL->getActiveAgentsBOM();
    }

    /**
     * Get filter options for BOM
     * 
     * @return array
     */
    public function getFilterOptionsBOM()
    {
        return [
            'month_options' => $this->lossRecoveryDAL->getFilterOptionsBOM(),
            'agents' => $this->lossRecoveryDAL->getActiveAgentsBOM(),
            'locations' => $this->lossRecoveryDAL->getLocationsBOM()
        ];
    }
}

