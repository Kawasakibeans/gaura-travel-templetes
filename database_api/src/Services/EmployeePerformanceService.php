<?php
/**
 * Employee Performance Service
 * Business logic for employee performance operations
 */

namespace App\Services;

use App\DAL\EmployeePerformanceDAL;
use Exception;

class EmployeePerformanceService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new EmployeePerformanceDAL();
    }

    /**
     * Convert seconds to time format (HH:MM:SS)
     */
    private function secondsToTimeFormat($seconds, $gtib = 1)
    {
        $seconds = (float)$seconds;
        if ($gtib == 0) {
            return sprintf('%02d:%02d:%02d', 0, 0, 0);
        }
        
        $seconds /= $gtib;
        
        // Use fmod for float modulo operations to avoid precision warnings
        $totalSeconds = (int)round($seconds);
        $hours = (int)floor($totalSeconds / 3600);
        $remainingSeconds = $totalSeconds % 3600;
        $minutes = (int)floor($remainingSeconds / 60);
        $finalSeconds = $remainingSeconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $finalSeconds);
    }

    /**
     * Get active agents
     */
    public function getActiveAgents($source = 'agent_codes')
    {
        if ($source === 'performance_data') {
            $agents = $this->dal->getActiveAgentsFromPerformanceData();
        } else {
            $agents = $this->dal->getActiveAgents();
        }
        
        return [
            'agents' => $agents,
            'total_count' => count($agents)
        ];
    }

    /**
     * Get yearly performance data
     */
    public function getYearlyPerformanceData($tsr, $year)
    {
        if (empty($tsr) || empty($year)) {
            throw new Exception('tsr and year are required', 400);
        }

        $results = $this->dal->getYearlyPerformanceData($tsr, $year);
        $results2 = $this->dal->getJoiningDate($tsr);
        
        if (!$results) {
            throw new Exception('No data found for the specified TSR', 404);
        }
        
        $date = $results['joining_date'] ?? ($results2['joining_date'] ?? null);
        if (!$date) {
            throw new Exception('Joining date not found', 404);
        }
        
        $month = (int)date('m', strtotime($date));
        $yearOfJoining = (int)date('Y', strtotime($date));
        
        if ($yearOfJoining == (date("Y") - 1)) {
            $yearDefinition = 13;
            $monthsLeft = $yearDefinition - $month;
        } else {
            $yearDefinition = 12;
            $monthsLeft = 12;
        }
        
        $attendance = number_format(($results['avg_attendance_per_day'] / (26 * $monthsLeft)), 2, '.', '') * 100 . '%';
        $avePif = number_format((float)$results['avg_pif_per_day'] / $monthsLeft, 2, '.', '');
        $avePax = number_format((float)$results['avg_pax_per_day'] / $monthsLeft, 2, '.', '');
        $aveGtib = number_format((float)$results['avg_calls_per_day'] / $monthsLeft, 2, '.', '');
        
        return [
            'is_tl' => $results['is_tl'] ?? '',
            'is_sm' => $results['is_sm'] ?? '',
            'joining_date' => $results2['joining_date'] ?? null,
            'avg_monthly_conversion' => number_format((float)$results['avg_daily_conversion'], 2, '.', ''),
            'avg_monthly_fcs' => number_format((float)$results['avg_daily_fcs'], 2, '.', ''),
            'avg_calls_per_month' => $aveGtib,
            'avg_aht_per_month' => substr($results['avg_aht_per_day'], 0, 8),
            'avg_attendance_per_month' => $attendance,
            'avg_pax_per_month' => $avePax,
            'avg_pax_pif_per_month' => $avePif
        ];
    }

    /**
     * Get combined performance data (yearly)
     */
    public function getCombinedPerformanceDataYearly($tsr, $year)
    {
        if (empty($tsr) || empty($year)) {
            throw new Exception('tsr and year are required', 400);
        }

        $janResults = $this->dal->getCombinedPerformanceDataYearly($tsr, $year);
        $attendanceResults = $this->dal->getAttendanceCountYearly($tsr, $year);
        $results2 = $this->dal->getJoiningDate($tsr);
        
        if (!$janResults) {
            throw new Exception('No data found for the specified TSR', 404);
        }
        
        $isSm = '';
        if (isset($janResults['role']) && $janResults['role'] == 'SM') {
            $isSm = 'SM';
        }
        
        $isTl = '';
        if (isset($janResults['role']) && $janResults['role'] == 'TL') {
            $isTl = 'TL';
        }
        
        $finalRole = $janResults['role'] ?? '';
        
        // Handle null/zero values safely
        $gtib = isset($janResults['gtib']) ? (float)$janResults['gtib'] : 0;
        $pax = isset($janResults['pax']) ? (float)$janResults['pax'] : 0;
        $newSaleMade = isset($janResults['new_sale_made']) ? (float)$janResults['new_sale_made'] : 0;
        $recDuration = isset($janResults['rec_duration']) ? (float)$janResults['rec_duration'] : 0;
        
        if ($gtib > 0) {
            $conversion = number_format($pax / $gtib * 100, 2);
            $fcs = number_format($newSaleMade / $gtib * 100, 2);
        } else {
            $conversion = number_format($pax / 1 * 100, 2);
            $fcs = number_format($newSaleMade / 1 * 100, 2);
        }
        $conversion = $conversion . '%';
        $fcs = $fcs . '%';
        
        $ahtTime = $this->secondsToTimeFormat($recDuration, $gtib);
        
        $today = date("Y") == $year ? new \DateTime() : new \DateTime($year . '-12-31');
        
        // Handle null joining date
        if (empty($results2['joining_date'])) {
            throw new Exception('Joining date not found for TSR', 404);
        }
        
        $joiningDate = new \DateTime($results2['joining_date']);
        $joiningDate2 = new \DateTime($year . '-01-01');
        
        $interval = $today->diff($joiningDate);
        $daysDifference = (int)$interval->format('%a');
        
        // Prevent division by zero
        if ($daysDifference <= 0) {
            $daysDifference = 1; // Default to 1 day to prevent division by zero
        }
        
        $gtibValue = isset($janResults['gtib']) ? (float)$janResults['gtib'] : 0;
        $newGtib = ($gtibValue / $daysDifference) * 30;
        $newGtib = number_format($newGtib, 2);
        
        if (date("Y") == $year) {
            $interval2 = $today->diff($joiningDate2);
            $daysDifference2 = (int)$interval2->format('%a');
            if ($daysDifference2 <= 0) {
                $daysDifference2 = 1;
            }
            $attendance = ($attendanceResults / $daysDifference2) * 30;
            $attendance = number_format($attendance, 2);
        } else {
            $attendance = ($attendanceResults / $daysDifference) * 30;
            $attendance = number_format($attendance, 2);
        }
        
        $paxValue = isset($janResults['pax']) ? (float)$janResults['pax'] : 0;
        $pifValue = isset($janResults['pif']) ? (float)$janResults['pif'] : 0;
        
        $totalPax = ($paxValue / $daysDifference) * 30;
        $totalPax = number_format($totalPax, 2);
        
        $totalPif = ($pifValue / $daysDifference) * 30;
        $totalPif = number_format($totalPif, 2);
        
        return [
            'is_tl' => $isTl,
            'is_sm' => $isSm,
            'role' => $finalRole,
            'joining_date' => $results2['joining_date'] ?? null,
            'avg_monthly_conversion' => $conversion,
            'avg_monthly_fcs' => $fcs,
            'avg_calls_per_month' => $newGtib,
            'avg_aht_per_month' => $ahtTime,
            'avg_attendance_per_month' => $attendance,
            'avg_pax_per_month' => $totalPax,
            'avg_pax_pif_per_month' => $totalPif
        ];
    }

    /**
     * Get combined performance data (monthly)
     */
    public function getCombinedPerformanceDataMonthly($tsr, $year, $month)
    {
        if (empty($tsr) || empty($year) || empty($month)) {
            throw new Exception('tsr, year, and month are required', 400);
        }

        if ((int)$month < 10) {
            $month = '0' . (int)$month;
        }
        
        $janResults = $this->dal->getCombinedPerformanceDataMonthly($tsr, $year, $month);
        $attendanceResults = $this->dal->getAttendanceCountMonthly($tsr, $year, $month);
        $results2 = $this->dal->getJoiningDate($tsr);
        
        if (!$janResults) {
            throw new Exception('No data found for the specified TSR', 404);
        }
        
        if ($janResults['gtib'] > 0) {
            $conversion = number_format($janResults['pax'] / $janResults['gtib'] * 100, 2) . '%';
        } else {
            $conversion = number_format($janResults['pax'] / 1 * 100, 2) . '%';
        }
        
        $fcs = number_format($janResults['new_sale_made'] / $janResults['gtib'] * 100, 2) . '%';
        $attendance = $attendanceResults;
        $ahtTime = $this->secondsToTimeFormat($janResults['rec_duration'], $janResults['gtib']);
        
        return [
            'is_tl' => '',
            'is_sm' => '',
            'joining_date' => $results2['joining_date'] ?? null,
            'avg_monthly_conversion' => $conversion,
            'avg_monthly_fcs' => $fcs,
            'avg_calls_per_month' => $janResults['gtib'],
            'avg_aht_per_month' => $ahtTime,
            'avg_attendance_per_month' => $attendance,
            'avg_pax_per_month' => $janResults['pax'],
            'avg_pax_pif_per_month' => $janResults['pif']
        ];
    }

    /**
     * Get combined performance data (last 3 months)
     */
    public function getCombinedPerformanceDataLast3Months($tsr)
    {
        if (empty($tsr)) {
            throw new Exception('tsr is required', 400);
        }

        $threeMonthsAgo = date("Y-m-01", strtotime("-3 months"));
        $currentMonth = date("Y-m-t", strtotime("last day of previous month"));
        $year2 = date("Y");
        
        $janResults = $this->dal->getCombinedPerformanceDataLast3Months($tsr, $threeMonthsAgo, $currentMonth);
        $attendanceResults = $this->dal->getAttendanceCountDateRange($tsr, $threeMonthsAgo, $currentMonth);
        $results2 = $this->dal->getJoiningDate($tsr);
        
        if (!$janResults) {
            throw new Exception('No data found for the specified TSR', 404);
        }
        
        $monthsLeft = 3;
        if ((int)date("m") < 3) {
            $monthsLeft = (int)date("m");
        }
        
        $conversion = number_format($janResults['pax'] / $janResults['gtib'] * 100, 2);
        $conversion = $conversion . '%';
        
        $fcs = number_format($janResults['new_sale_made'] / $janResults['gtib'] * 100, 2);
        $fcs = $fcs . '%';
        
        $attendance = $attendanceResults / $monthsLeft;
        $attendance = number_format($attendance, 2);
        
        $ahtTime = $this->secondsToTimeFormat($janResults['rec_duration'], $janResults['gtib']);
        
        $totalPax = number_format($janResults['pax'] / $monthsLeft, 2);
        $totalPif = number_format($janResults['pif'] / $monthsLeft, 2);
        $totalGtib = number_format($janResults['gtib'] / $monthsLeft, 2);
        
        return [
            'is_tl' => '',
            'is_sm' => '',
            'joining_date' => $results2['joining_date'] ?? null,
            'avg_monthly_conversion' => $conversion,
            'avg_monthly_fcs' => $fcs,
            'avg_calls_per_month' => $totalGtib,
            'avg_aht_per_month' => $ahtTime,
            'avg_attendance_per_month' => $attendance,
            'avg_pax_per_month' => $totalPax,
            'avg_pax_pif_per_month' => $totalPif
        ];
    }

    /**
     * Get performance reviews
     */
    public function getPerformanceReviews($searchTerm = null)
    {
        $reviews = $this->dal->getPerformanceReviews($searchTerm);
        
        return [
            'reviews' => $reviews,
            'total_count' => count($reviews)
        ];
    }

    /**
     * Get performance review by ID
     */
    public function getPerformanceReviewById($id)
    {
        if (empty($id)) {
            throw new Exception('id is required', 400);
        }

        $review = $this->dal->getPerformanceReviewById($id);
        
        if (!$review) {
            throw new Exception('Performance review not found', 404);
        }
        
        return $review;
    }

    /**
     * Create performance review
     */
    public function createPerformanceReview($data)
    {
        $requiredFields = ['name', 'role', 'dateOfJoining', 'proficiencyLevel'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required", 400);
            }
        }
        
        // Set defaults
        $data['dateOfReview'] = $data['dateOfReview'] ?? date("Y-m-d");
        $data['startTimeOfReview'] = $data['startTimeOfReview'] ?? date("H:i:s");
        $data['endTimeOfReview'] = $data['endTimeOfReview'] ?? date("H:i:s");
        $data['created_by'] = $data['created_by'] ?? 'system';
        
        // Convert boolean values
        $booleanFields = [
            'performanceLevel6', 'performanceLevel2', 'performanceLevel3',
            'keyActionsEXCEEDS', 'keyActionsMEETS', 'keyActionsDOESNOTMEET',
            'greet', 'ask', 'repeatd', 'lead', 'analyse', 'negotiate', 'donedeal'
        ];
        
        foreach ($booleanFields as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] ? 1 : 0;
        }
        
        return $this->dal->insertPerformanceReview($data);
    }

    /**
     * Update performance review
     */
    public function updatePerformanceReview($id, $data)
    {
        if (empty($id)) {
            throw new Exception('id is required', 400);
        }
        
        // Check if review exists
        $existing = $this->dal->getPerformanceReviewById($id);
        if (!$existing) {
            throw new Exception('Performance review not found', 404);
        }
        
        // Convert boolean values
        $booleanFields = [
            'performanceLevel6', 'performanceLevel2', 'performanceLevel3',
            'keyActionsEXCEEDS', 'keyActionsMEETS', 'keyActionsDOESNOTMEET',
            'greet', 'ask', 'repeatd', 'lead', 'analyse', 'negotiate', 'donedeal'
        ];
        
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] ? 1 : 0;
            }
        }
        
        return $this->dal->updatePerformanceReview($id, $data);
    }
}

