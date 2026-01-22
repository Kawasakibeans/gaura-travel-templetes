<?php
/**
 * Agent Incentive Service
 * Business logic for agent incentive endpoints
 */

namespace App\Services;

use App\DAL\AgentIncentiveDAL;

class AgentIncentiveService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AgentIncentiveDAL();
    }

    /**
     * Get frontend incentive data
     */
    public function getFrontendIncentiveData(array $params): array
    {
        $period = $params['period'] ?? '';
        $agentFilter = $params['agent'] ?? null;
        $managerFilter = $params['manager'] ?? null;
        $eligibleOnly = isset($params['eligible']) ? (bool)$params['eligible'] : false;

        // Get periods
        $allPeriods = $this->dal->getPeriods();
        $latestPeriod = $allPeriods[0] ?? '';
        
        if (!$period) {
            $period = $latestPeriod;
        } elseif (!in_array($period, $allPeriods, true)) {
            $period = $latestPeriod;
        }

        // Parse period dates
        [$startDate, $endDate] = explode('_to_', $period);

        // Get eligibility criteria
        $eligibilityCriteria = $this->dal->getIncentiveCriteria($period);
        $defaultCriteria = [
            'noble_login_min_hrs' => 60,
            'gtbk_max_hrs' => 10,
            'gtib_min_calls' => 60,
            'aht_max_minutes' => 25,
            'fcs_min_percent' => 25,
            'conversion_min_percent' => 40,
            'qa_min_percent' => 80
        ];
        $criteria = $eligibilityCriteria ?: $defaultCriteria;

        // Get agent list
        $agentList = $this->dal->getAgents($managerFilter);
        $managers = $this->dal->getManagers();

        // Get performance data
        $gtibData = $this->dal->getAgentPerformance($startDate, $endDate, $agentFilter, $managerFilter);
        $dailyBreakdown = $this->dal->getDailyBreakdown($startDate, $endDate, $agentFilter, $managerFilter);

        // Get additional metrics
        $nobleLoginMap = $this->dal->getNobleLoginTime($startDate, $endDate, $agentFilter, $managerFilter);
        $nobleLoginDeductionMap = $this->dal->getNobleLoginDeduction($startDate, $endDate, $agentFilter, $managerFilter);
        $gtbkMap = $this->dal->getGTBK($startDate, $endDate, $agentFilter, $managerFilter);
        $gtbkDeductionMap = $this->dal->getGTBKDeduction($startDate, $endDate, $agentFilter, $managerFilter);
        $qaMap = $this->dal->getQACompliance($startDate, $endDate, $agentFilter, $managerFilter);
        $deductionMap = $this->dal->getZeroPaxDeductions($startDate, $endDate, $agentFilter, $managerFilter);

        // Merge data and calculate eligibility
        foreach ($gtibData as &$agentRow) {
            $name = $agentRow['agent_name'];
            $agentRow['noble_login_time'] = $nobleLoginMap[$name] ?? 0;
            $agentRow['gtbk'] = $gtbkMap[$name] ?? 0;
            $agentRow['gtbk_deduction'] = $gtbkDeductionMap[$name] ?? 0;
            $agentRow['noble_login_deduction'] = $nobleLoginDeductionMap[$name] ?? 0;
            $qaCompliance = $qaMap[$name] ?? null;
            $agentRow['qa_compliance'] = $qaCompliance;
            $deductionInfo = $deductionMap[$name] ?? ['zero_pax_day' => 0, 'deduction_amount' => 0];
            $agentRow['zero_pax_day'] = $deductionInfo['zero_pax_day'];
            $agentRow['deduction_amount'] = $deductionInfo['deduction_amount'];

            // Calculate eligibility
            $nobleHrs = $agentRow['noble_login_time'] / 3600;
            $gtbkHrs = $agentRow['gtbk'] / 3600;
            $ahtSecs = $agentRow['aht'];
            $minQa = $criteria['qa_min_percent'] ?? 80;

            $agentRow['is_eligible'] = (
                $nobleHrs >= $criteria['noble_login_min_hrs'] &&
                $gtbkHrs <= $criteria['gtbk_max_hrs'] &&
                $agentRow['gtib'] >= $criteria['gtib_min_calls'] &&
                $ahtSecs <= ($criteria['aht_max_minutes'] * 60) &&
                ($agentRow['fcs'] * 100) >= $criteria['fcs_min_percent'] &&
                ($agentRow['conversion'] * 100) >= $criteria['conversion_min_percent'] &&
                ($qaCompliance === null || $qaCompliance >= $minQa)
            );
        }
        unset($agentRow);

        // Filter eligible agents if requested
        if ($eligibleOnly) {
            $gtibData = array_filter($gtibData, function($agent) {
                return $agent['is_eligible'];
            });
            
            $eligibleAgents = array_column($gtibData, 'agent_name');
            $dailyBreakdown = array_filter($dailyBreakdown, function($day) use ($eligibleAgents) {
                return in_array($day['agent_name'], $eligibleAgents);
            });
        }

        // Prepare agent names list
        $agentNames = array_column($agentList, 'agent_name');

        return [
            'performance' => array_values($gtibData),
            'agents' => $agentNames,
            'daily' => array_values($dailyBreakdown),
            'managers' => $managers,
            'criteria' => $criteria,
            'qa_min_percent' => $criteria['qa_min_percent'] ?? 80
        ];
    }

    /**
     * Get incentive criteria (parsed)
     */
    public function getIncentiveCriteria(array $params): array
    {
        $period = $params['period'] ?? '';
        
        if (!$period) {
            $periods = $this->dal->getPeriods();
            $period = $periods[0] ?? '';
        }

        if (!$period) {
            throw new \Exception('No period provided and no periods found');
        }

        $rows = $this->dal->getAllIncentiveCriteria($period);

        // Parse criteria into structured format
        $slabs = [];
        $fcs_multipliers = [];
        $daily_fcs_multipliers = [];
        $daily_bonus = [];
        $deductions = [];
        $eligibility = [];
        $daily_eligibility = [];
        $daily_incentives = [];

        foreach ($rows as $row) {
            $type = $row['type'];
            $key = trim(strtolower($row['key_name'] ?? ''));
            $subKey = trim(strtolower($row['sub_key'] ?? ''));
            $val = is_numeric($row['value']) ? $row['value'] + 0 : $row['value'];

            switch ($type) {
                case 'slab':
                    if (!isset($slabs[$key])) {
                        $slabs[$key] = ['conversion' => (int)$key];
                    }
                    $slabs[$key][$subKey] = $val;
                    if (!empty($row['title'])) {
                        $slabs[$key]['title'] = $row['title'];
                    }
                    break;

                case 'fcs_multiplier':
                    if ($subKey === 'daily') {
                        $daily_fcs_multipliers[(int)$key] = (float)$val;
                    } else {
                        $fcs_multipliers[(int)$key] = (float)$val;
                    }
                    break;

                case 'daily_bonus':
                    $daily_bonus[(int)$key] = (float)$val;
                    break;

                case 'deduction':
                    $deductions[$key] = $val;
                    break;

                case 'eligibility':
                    if ($key) {
                        $eligibility[$key] = is_numeric($val) ? (int)$val : $val;
                    }
                    break;

                case 'daily_eligibility':
                    $daily_eligibility[$key] = ($val === '1' || $val === 1 || $val === true);
                    break;

                case 'daily_incentive':
                    if (strpos($key, '_') !== false) {
                        [$min_gtib, $conversion] = explode('_', $key);
                        $min_gtib = (int)$min_gtib;
                        $conversion = (int)$conversion;

                        if (!isset($daily_incentives[$min_gtib])) {
                            $daily_incentives[$min_gtib] = [
                                'min_gtib' => $min_gtib,
                                'criteria' => []
                            ];
                        }

                        if (preg_match('/^pax_(\d+)$/', $subKey, $match)) {
                            $pax = (int)$match[1];
                            $daily_incentives[$min_gtib]['criteria'][$pax] = [
                                'pax' => $pax,
                                'conversion' => $conversion,
                                'reward' => $val
                            ];
                        }
                    }
                    break;
            }
        }

        // Re-index slabs numerically
        $slabs = array_values($slabs);

        return [
            'period' => $period,
            'slabs' => $slabs,
            'fcs_multipliers' => $fcs_multipliers,
            'daily_fcs_multipliers' => $daily_fcs_multipliers,
            'daily_bonus' => $daily_bonus,
            'deductions' => $deductions,
            'eligibility' => $eligibility,
            'daily_eligibility' => $daily_eligibility,
            'daily_incentives' => array_values($daily_incentives)
        ];
    }

    /**
     * Get 10-day and daily incentive data
     */
    public function get10DayAndDailyIncentiveData(array $params): array
    {
        $period = $params['period'] ?? '';
        $managerFilter = $params['manager'] ?? null;
        $eligibleOnly = isset($params['eligible']) ? (bool)$params['eligible'] : false;

        // Get periods
        $periods = $this->dal->getPeriods();
        $managers = $this->dal->getManagers();

        if (!$period) {
            $period = $periods[0] ?? '';
        }

        if (!$period) {
            throw new \Exception('No period provided');
        }

        [$startDate, $endDate] = explode('_to_', $period);

        // Get eligibility criteria
        $criteriaRows = $this->dal->getAllIncentiveCriteria($period);
        $eligibility = [];
        foreach ($criteriaRows as $row) {
            if ($row['type'] === 'eligibility') {
                $key = trim(strtolower($row['key_name'] ?? ''));
                $val = is_numeric($row['value']) ? $row['value'] + 0 : $row['value'];
                if ($key) {
                    $eligibility[$key] = is_numeric($val) ? (int)$val : $val;
                }
            }
        }

        // Get agent performance
        $performance = $this->dal->getAgentPerformance($startDate, $endDate, null, $managerFilter);
        $dailyBreakdown = $this->dal->getDailyBreakdown($startDate, $endDate, null, $managerFilter);

        // Add eligibility evaluation
        foreach ($performance as &$row) {
            $row['is_eligible'] = (
                ($row['gtib'] >= ($eligibility['gtib_min_calls'] ?? 0)) &&
                ($row['aht'] <= (($eligibility['aht_max_minutes'] ?? 0) * 60)) &&
                (($row['fcs'] * 100) >= ($eligibility['fcs_min_percent'] ?? 0)) &&
                (($row['conversion'] * 100) >= ($eligibility['garland_min_percent'] ?? 0))
            );
            $row['payment'] = $row['pif'] * 100;
            $row['bonus'] = $row['is_eligible'] ? 'Yes' : 'No';
            $row['bonus_amount'] = $row['is_eligible'] ? $row['pif'] * 50 : 0;
            $row['total_amount'] = $row['payment'] + $row['bonus_amount'];
        }
        unset($row);

        // Get 10-day summary
        $summary10Day = $this->dal->get10DaySummary($startDate, $endDate, $managerFilter);

        // Filter eligible if requested
        if ($eligibleOnly) {
            $performance = array_filter($performance, fn($row) => $row['is_eligible']);
            $eligibleAgents = array_column($performance, 'agent_name');
            $dailyBreakdown = array_filter($dailyBreakdown, function($day) use ($eligibleAgents) {
                return in_array($day['agent_name'], $eligibleAgents);
            });
        }

        return [
            'period' => $period,
            'periods' => $periods,
            'managers' => $managers,
            'performance' => array_values($performance),
            'daily' => array_values($dailyBreakdown),
            'summary_10day' => $summary10Day,
            'eligibility' => $eligibility
        ];
    }

    /**
     * Get agent target pathway
     */
    public function getAgentTargetPathway(array $params): array
    {
        $rosterCode = $params['roster_code'] ?? '';
        $period = $params['period'] ?? '';

        if (!$rosterCode || !$period) {
            throw new \Exception('roster_code and period are required');
        }

        $pathway = $this->dal->getAgentTargetPathway($rosterCode, $period);

        if (!$pathway) {
            throw new \Exception('Pathway not found');
        }

        return $pathway;
    }

    /**
     * Get all agent target pathways
     */
    public function getAllAgentTargetPathways(array $params): array
    {
        $period = $params['period'] ?? null;

        $pathways = $this->dal->getAllAgentTargetPathways($period);

        return ['pathways' => $pathways];
    }

    /**
     * Save agent target pathway
     */
    public function saveAgentTargetPathway(array $params): array
    {
        $required = ['roster_code', 'period', 'target', 'conversion'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \Exception("$field is required");
            }
        }

        $data = [
            'roster_code' => $params['roster_code'],
            'period' => $params['period'],
            'target' => (int)$params['target'],
            'conversion' => (int)$params['conversion'],
            'rate' => isset($params['rate']) ? (float)$params['rate'] : 0,
            'fcs_mult' => isset($params['fcs_mult']) ? (float)$params['fcs_mult'] : 0,
            'rate_fcs' => isset($params['rate_fcs']) ? (float)$params['rate_fcs'] : 0,
            'gtib_bonus' => isset($params['gtib_bonus']) ? (float)$params['gtib_bonus'] : 0,
            'min_gtib' => isset($params['min_gtib']) ? (int)$params['min_gtib'] : 0,
            'min_pif' => isset($params['min_pif']) ? (int)$params['min_pif'] : 0,
            'daily_pif' => isset($params['daily_pif']) ? (int)$params['daily_pif'] : 0,
            'total_estimate' => isset($params['total_estimate']) ? (float)$params['total_estimate'] : 0
        ];

        $success = $this->dal->saveAgentTargetPathway($data);

        if (!$success) {
            throw new \Exception('Failed to save pathway');
        }

        return ['message' => 'Pathway saved successfully'];
    }
}

