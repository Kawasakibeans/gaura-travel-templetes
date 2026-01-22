<?php

/**
 * Interim Proficiency Service - Business Logic Layer
 * Handles interim performance tracking and reporting
 */

namespace App\Services;

use App\DAL\InterimProficiencyDAL;
use Exception;

class InterimProficiencyService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new InterimProficiencyDAL();
    }

    /**
     * Get interim performance report
     */
    public function getInterimReport($team, $fromDate, $toDate, $mode = '10day')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        // Generate intervals based on mode
        $intervals = $this->generateIntervals($fromDate, $toDate, $mode);

        // Get agent performance matrix
        $agentMatrix = $this->dal->getAgentPerformanceMatrix($intervals, $team);

        return [
            'team' => $team ?? 'ALL',
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'mode' => $mode,
            'intervals' => array_keys($intervals),
            'agent_matrix' => $agentMatrix,
            'total_agents' => count($agentMatrix)
        ];
    }

    /**
     * Get interim QA summary
     */
    public function getQASummary($team, $fromDate, $toDate, $mode = '10day')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $intervals = $this->generateIntervals($fromDate, $toDate, $mode);

        $qaMatrix = $this->dal->getQAScoreMatrix($intervals, $team);

        return [
            'team' => $team ?? 'ALL',
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'mode' => $mode,
            'intervals' => array_keys($intervals),
            'qa_matrix' => $qaMatrix,
            'total_agents' => count($qaMatrix)
        ];
    }

    /**
     * Get agent summary (after sales)
     */
    public function getAgentSummaryAfterSales($team, $fromDate, $toDate, $mode = '10day')
    {
        if (empty($fromDate) || empty($toDate)) {
            throw new Exception('from_date and to_date are required', 400);
        }

        $intervals = $this->generateIntervals($fromDate, $toDate, $mode);

        $summaryMatrix = $this->dal->getAfterSalesMatrix($intervals, $team);

        return [
            'team' => $team ?? 'ALL',
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'mode' => $mode,
            'report_type' => 'after_sales',
            'intervals' => array_keys($intervals),
            'agent_summary' => $summaryMatrix,
            'total_agents' => count($summaryMatrix)
        ];
    }

    /**
     * Get performance remarks
     */
    public function getPerformanceRemarks($tsr = null)
    {
        $remarks = $this->dal->getPerformanceRemarks($tsr);

        return [
            'remarks' => $remarks,
            'total_count' => count($remarks)
        ];
    }

    /**
     * Create performance remark
     */
    public function createPerformanceRemark($data)
    {
        $requiredFields = ['tsr', 'date_range_start', 'date_range_end', 'remark'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $remarkId = $this->dal->createPerformanceRemark($data);

        return [
            'remark_id' => $remarkId,
            'tsr' => $data['tsr'],
            'message' => 'Performance remark created successfully'
        ];
    }

    /**
     * Update performance remark
     */
    public function updatePerformanceRemark($tsr, $dateRangeStart, $remark)
    {
        if (empty($tsr) || empty($dateRangeStart)) {
            throw new Exception('TSR and date_range_start are required', 400);
        }

        $this->dal->updatePerformanceRemark($tsr, $dateRangeStart, $remark);

        return [
            'tsr' => $tsr,
            'date_range_start' => $dateRangeStart,
            'message' => 'Performance remark updated successfully'
        ];
    }

    /**
     * Delete performance remark
     */
    public function deletePerformanceRemark($tsr, $dateRangeStart)
    {
        if (empty($tsr) || empty($dateRangeStart)) {
            throw new Exception('TSR and date_range_start are required', 400);
        }

        $this->dal->deletePerformanceRemark($tsr, $dateRangeStart);

        return [
            'tsr' => $tsr,
            'date_range_start' => $dateRangeStart,
            'message' => 'Performance remark deleted successfully'
        ];
    }

    /**
     * Get available teams
     */
    public function getAvailableTeams()
    {
        return $this->dal->getActiveTeams();
    }

    /**
     * Private helper methods
     */

    private function generateIntervals($fromDate, $toDate, $mode)
    {
        switch ($mode) {
            case '10day':
                return $this->generate10DayIntervals($fromDate, $toDate);
            case 'monthly':
                return $this->generateMonthlyIntervals($toDate, 5);
            case 'daily':
                return $this->generateDailyIntervals($fromDate, $toDate);
            default:
                return $this->generate10DayIntervals($fromDate, $toDate);
        }
    }

    private function generate10DayIntervals($fromDate, $toDate)
    {
        $start = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $intervals = [];

        while ($start <= $end) {
            $day = (int)$start->format('j');
            if ($day <= 10) {
                $start_day = 1;
                $end_day = 10;
            } elseif ($day <= 20) {
                $start_day = 11;
                $end_day = 20;
            } else {
                $start_day = 21;
                $end_day = (int)$start->format('t'); // Last day of month
            }

            $intervalStart = new \DateTime($start->format("Y-m-") . str_pad($start_day, 2, '0', STR_PAD_LEFT));
            $intervalEnd = new \DateTime($start->format("Y-m-") . str_pad($end_day, 2, '0', STR_PAD_LEFT));
            
            if ($intervalEnd > $end) {
                $intervalEnd = clone $end;
            }

            $label = $intervalStart->format('j') . '-' . $intervalEnd->format('j') . ' ' . strtoupper($intervalEnd->format('M'));
            $intervals[$label] = [$intervalStart->format('Y-m-d'), $intervalEnd->format('Y-m-d')];
            
            $start = (clone $intervalEnd)->modify('+1 day');
        }

        return $intervals;
    }

    // private function generateMonthlyIntervals($toDate, $count = 5)
    // {
    //     $end = new \DateTime($toDate);
    //     $intervals = [];

    //     for ($i = 0; $i < $count; $i++) {
    //         $monthEnd = (clone $end)->modify('last day of this month');
    //         $monthStart = (clone $end)->modify('first day of this month');
    //         $label = strtoupper($end->format('M Y'));
    //         $intervals[$label] = [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
    //         $end->modify('-1 month');
    //     }

    //     return array_reverse($intervals);
    // }
    
    private function generateMonthlyIntervals($fromDate, $toDate)
    {
        $start = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $intervals = [];

        while ($start <= $end) {
            $monthStart = (clone $start)->modify('first day of this month');
            $monthEnd = (clone $start)->modify('last day of this month');
            $label = strtoupper($start->format('M Y'));
            $intervals[$label] = [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
            $start->modify('+1 month');
        }

        return $intervals;
    }

    private function generateDailyIntervals($fromDate, $toDate, $maxDays = 30)
    {
        $start = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $intervals = [];

        while ($start <= $end && count($intervals) < $maxDays) {
            $label = $start->format('d M');
            $intervals[$label] = [$start->format('Y-m-d'), $start->format('Y-m-d')];
            $start->modify('+1 day');
        }

        return $intervals;
    }
}
