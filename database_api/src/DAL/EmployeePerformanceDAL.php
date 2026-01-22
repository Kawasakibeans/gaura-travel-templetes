<?php
/**
 * Employee Performance Data Access Layer
 * Handles database operations for employee performance queries
 */

namespace App\DAL;

use App\DAL\BaseDAL;
use Exception;

class EmployeePerformanceDAL extends BaseDAL
{
    /**
     * Get active agents from agent codes
     */
    public function getActiveAgents()
    {
        $query = "
            SELECT agent_name, tsr 
            FROM wpk4_backend_agent_codes 
            WHERE status = 'active' 
            AND CHAR_LENGTH(tsr) > 3 
            ORDER BY agent_name ASC
        ";
        
        return $this->query($query);
    }

    /**
     * Get active agents from employee performance data
     */
    public function getActiveAgentsFromPerformanceData()
    {
        $query = "
            SELECT DISTINCT agent_name, tsr 
            FROM wpk4_backend_employee_performace_data 
            WHERE status = 'active' 
            AND CHAR_LENGTH(tsr) > 3 
            ORDER BY agent_name ASC
        ";
        
        return $this->query($query);
    }

    /**
     * Get yearly aggregated performance data
     */
    public function getYearlyPerformanceData($tsr, $year)
    {
        // Cast year to integer for YEAR() function
        $year = (int)$year;
        
        $query = "
            SELECT 
                MAX(is_tl) AS is_tl,
                MAX(is_sm) AS is_sm,
                MIN(start_date) AS joining_date,
                AVG(conversion) * 100 AS avg_daily_conversion,
                AVG(fcs) * 100 AS avg_daily_fcs,
                AVG(calls_taken) * SUM(attendance_count) AS avg_calls_per_day,
                SEC_TO_TIME(AVG(TIME_TO_SEC(aht))) AS avg_aht_per_day,
                SUM(attendance_count) AS avg_attendance_per_day,
                AVG(pax) * SUM(attendance_count) AS avg_pax_per_day,
                AVG(pif) * SUM(attendance_count) AS avg_pif_per_day 
            FROM wpk4_backend_employee_performace_data 
            WHERE YEAR(call_data) = :year 
            AND tsr = :tsr
        ";
        
        $result = $this->query($query, [
            'year' => $year,
            'tsr' => $tsr
        ]);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get joining date for TSR
     */
    public function getJoiningDate($tsr)
    {
        $query = "
            SELECT MIN(start_date) AS joining_date
            FROM wpk4_backend_employee_performace_data 
            WHERE start_date != '' 
            AND tsr = :tsr
        ";
        
        $result = $this->query($query, ['tsr' => $tsr]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get combined performance data (inbound calls + bookings) - Yearly
     */
    public function getCombinedPerformanceDataYearly($tsr, $year)
    {
        // Cast year to integer for YEAR() function
        $year = (int)$year;
        
        $query = "
            SELECT 
                MAX(role) AS role, 
                MAX(agent_name) AS agent_name, 
                SUM(pax) AS pax, 
                SUM(fit) AS fit, 
                SUM(pif) AS pif, 
                SUM(gdeals) AS gdeals, 
                SUM(gtib_count) AS gtib, 
                SUM(sale_made_count) AS sale_made_count, 
                SUM(new_sale_made_count) AS new_sale_made, 
                SUM(non_sale_made_count) AS non_sale_made_count, 
                SUM(rec_duration) AS rec_duration 
            FROM ( 
                SELECT 
                    c.role,
                    COALESCE(a.agent_name, '') AS agent_name, 
                    0 AS pax, 
                    0 AS fit, 
                    0 AS pif, 
                    0 AS gdeals, 
                    COALESCE(a.gtib_count, 0) AS gtib_count, 
                    COALESCE(a.sale_made_count, 0) AS sale_made_count, 
                    COALESCE(a.new_sale_made_count, 0) AS new_sale_made_count, 
                    COALESCE(a.non_sale_made_count, 0) AS non_sale_made_count, 
                    COALESCE(a.rec_duration, 0) AS rec_duration 
                FROM wpk4_backend_agent_inbound_call a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE YEAR(a.call_date) = :year1 
                AND a.team_name != '' 
                AND a.tsr = :tsr1 
                UNION ALL 
                SELECT 
                    c.role,
                    COALESCE(a.agent_name, '') AS agent_name, 
                    COALESCE(a.pax, 0) AS pax, 
                    COALESCE(a.fit, 0) AS fit, 
                    COALESCE(a.pif, 0) AS pif, 
                    COALESCE(a.gdeals, 0) AS gdeals, 
                    0 AS gtib_count, 
                    0 AS sale_made_count, 
                    0 AS new_sale_made_count, 
                    0 AS non_sale_made_count, 
                    0 AS rec_duration 
                FROM wpk4_backend_agent_booking a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE YEAR(a.order_date) = :year2 
                AND a.team_name != '' 
                AND a.tsr = :tsr2
            ) AS combined_data 
            GROUP BY agent_name 
            ORDER BY agent_name
        ";
        
        $result = $this->query($query, [
            'year1' => $year,
            'tsr1' => $tsr,
            'year2' => $year,
            'tsr2' => $tsr
        ]);
        
        // Handle empty result set
        if (empty($result)) {
            return null;
        }
        
        // If GROUP BY returns multiple rows, aggregate them
        if (count($result) > 1) {
            // Aggregate multiple rows if needed
            $aggregated = [
                'role' => $result[0]['role'] ?? '',
                'agent_name' => $result[0]['agent_name'] ?? '',
                'pax' => array_sum(array_column($result, 'pax')),
                'fit' => array_sum(array_column($result, 'fit')),
                'pif' => array_sum(array_column($result, 'pif')),
                'gdeals' => array_sum(array_column($result, 'gdeals')),
                'gtib' => array_sum(array_column($result, 'gtib')),
                'sale_made_count' => array_sum(array_column($result, 'sale_made_count')),
                'new_sale_made' => array_sum(array_column($result, 'new_sale_made')),
                'non_sale_made_count' => array_sum(array_column($result, 'non_sale_made_count')),
                'rec_duration' => array_sum(array_column($result, 'rec_duration'))
            ];
            return $aggregated;
        }
        
        return $result[0];
    }

    /**
     * Get combined performance data (inbound calls + bookings) - Monthly
     */
    public function getCombinedPerformanceDataMonthly($tsr, $year, $month)
    {
        // Cast year and month to integers
        $year = (int)$year;
        $month = (int)$month;
        
        $query = "
            SELECT 
                MAX(agent_name) AS agent_name, 
                SUM(pax) AS pax, 
                SUM(fit) AS fit, 
                SUM(pif) AS pif, 
                SUM(gdeals) AS gdeals, 
                SUM(gtib_count) AS gtib, 
                SUM(sale_made_count) AS sale_made_count, 
                SUM(new_sale_made_count) AS new_sale_made, 
                SUM(non_sale_made_count) AS non_sale_made_count, 
                SUM(rec_duration) AS rec_duration 
            FROM ( 
                SELECT 
                    COALESCE(a.agent_name, '') AS agent_name, 
                    0 AS pax, 
                    0 AS fit, 
                    0 AS pif, 
                    0 AS gdeals, 
                    COALESCE(a.gtib_count, 0) AS gtib_count, 
                    COALESCE(a.sale_made_count, 0) AS sale_made_count, 
                    COALESCE(a.new_sale_made_count, 0) AS new_sale_made_count, 
                    COALESCE(a.non_sale_made_count, 0) AS non_sale_made_count, 
                    COALESCE(a.rec_duration, 0) AS rec_duration 
                FROM wpk4_backend_agent_inbound_call a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE YEAR(a.call_date) = :year1 
                AND MONTH(a.call_date) = :month1 
                AND a.team_name != '' 
                AND a.tsr = :tsr1 
                UNION ALL 
                SELECT 
                    COALESCE(a.agent_name, '') AS agent_name, 
                    COALESCE(a.pax, 0) AS pax, 
                    COALESCE(a.fit, 0) AS fit, 
                    COALESCE(a.pif, 0) AS pif, 
                    COALESCE(a.gdeals, 0) AS gdeals, 
                    0 AS gtib_count, 
                    0 AS sale_made_count, 
                    0 AS new_sale_made_count, 
                    0 AS non_sale_made_count, 
                    0 AS rec_duration 
                FROM wpk4_backend_agent_booking a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE YEAR(a.order_date) = :year2 
                AND MONTH(a.order_date) = :month2 
                AND a.team_name != '' 
                AND a.tsr = :tsr2
            ) AS combined_data 
            GROUP BY agent_name 
            ORDER BY agent_name
        ";
        
        $result = $this->query($query, [
            'year1' => $year,
            'month1' => $month,
            'tsr1' => $tsr,
            'year2' => $year,
            'month2' => $month,
            'tsr2' => $tsr
        ]);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get combined performance data (inbound calls + bookings) - Last 3 Months
     */
    public function getCombinedPerformanceDataLast3Months($tsr, $dateFrom, $dateTo)
    {
        // Ensure dates are in proper format (Y-m-d)
        $dateFrom = date('Y-m-d', strtotime($dateFrom));
        $dateTo = date('Y-m-d', strtotime($dateTo));
        
        $query = "
            SELECT 
                MAX(agent_name) AS agent_name, 
                SUM(pax) AS pax, 
                SUM(fit) AS fit, 
                SUM(pif) AS pif, 
                SUM(gdeals) AS gdeals, 
                SUM(gtib_count) AS gtib, 
                SUM(sale_made_count) AS sale_made_count, 
                SUM(new_sale_made_count) AS new_sale_made, 
                SUM(non_sale_made_count) AS non_sale_made_count, 
                SUM(rec_duration) AS rec_duration 
            FROM ( 
                SELECT 
                    COALESCE(a.agent_name, '') AS agent_name, 
                    0 AS pax, 
                    0 AS fit, 
                    0 AS pif, 
                    0 AS gdeals, 
                    COALESCE(a.gtib_count, 0) AS gtib_count, 
                    COALESCE(a.sale_made_count, 0) AS sale_made_count, 
                    COALESCE(a.new_sale_made_count, 0) AS new_sale_made_count, 
                    COALESCE(a.non_sale_made_count, 0) AS non_sale_made_count, 
                    COALESCE(a.rec_duration, 0) AS rec_duration 
                FROM wpk4_backend_agent_inbound_call a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE a.call_date BETWEEN :date_from1 AND :date_to1 
                AND a.team_name != '' 
                AND a.tsr = :tsr1 
                UNION ALL 
                SELECT 
                    COALESCE(a.agent_name, '') AS agent_name, 
                    COALESCE(a.pax, 0) AS pax, 
                    COALESCE(a.fit, 0) AS fit, 
                    COALESCE(a.pif, 0) AS pif, 
                    COALESCE(a.gdeals, 0) AS gdeals, 
                    0 AS gtib_count, 
                    0 AS sale_made_count, 
                    0 AS new_sale_made_count, 
                    0 AS non_sale_made_count, 
                    0 AS rec_duration 
                FROM wpk4_backend_agent_booking a 
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr 
                WHERE a.order_date BETWEEN :date_from2 AND :date_to2 
                AND a.team_name != '' 
                AND a.tsr = :tsr2
            ) AS combined_data 
            GROUP BY agent_name 
            ORDER BY agent_name
        ";
        
        $result = $this->query($query, [
            'tsr1' => $tsr,
            'date_from1' => $dateFrom,
            'date_to1' => $dateTo,
            'tsr2' => $tsr,
            'date_from2' => $dateFrom,
            'date_to2' => $dateTo
        ]);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get attendance count - Yearly
     */
    public function getAttendanceCountYearly($tsr, $year)
    {
        // Cast year to integer for YEAR() function
        $year = (int)$year;
        
        $query = "
            SELECT COUNT(call_date) as attendance_count 
            FROM wpk4_backend_agent_inbound_call 
            WHERE tsr = :tsr 
            AND YEAR(call_date) = :year 
            AND gtib_count > 0
        ";
        
        $result = $this->query($query, [
            'tsr' => $tsr,
            'year' => $year
        ]);
        
        return !empty($result) ? (int)$result[0]['attendance_count'] : 0;
    }

    /**
     * Get attendance count - Monthly
     */
    public function getAttendanceCountMonthly($tsr, $year, $month)
    {
        // Cast year and month to integers
        $year = (int)$year;
        $month = (int)$month;
        
        $query = "
            SELECT COUNT(call_date) as attendance_count 
            FROM wpk4_backend_agent_inbound_call 
            WHERE tsr = :tsr 
            AND YEAR(call_date) = :year 
            AND MONTH(call_date) = :month 
            AND gtib_count > 0
        ";
        
        $result = $this->query($query, [
            'tsr' => $tsr,
            'year' => $year,
            'month' => $month
        ]);
        
        return !empty($result) ? (int)$result[0]['attendance_count'] : 0;
    }

    /**
     * Get attendance count - Date Range
     */
    public function getAttendanceCountDateRange($tsr, $dateFrom, $dateTo)
    {
        // Ensure dates are in proper format (Y-m-d)
        $dateFrom = date('Y-m-d', strtotime($dateFrom));
        $dateTo = date('Y-m-d', strtotime($dateTo));
        
        $query = "
            SELECT COUNT(call_date) as attendance_count 
            FROM wpk4_backend_agent_inbound_call 
            WHERE tsr = :tsr 
            AND call_date BETWEEN :date_from AND :date_to 
            AND gtib_count > 0
        ";
        
        $result = $this->query($query, [
            'tsr' => $tsr,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        return !empty($result) ? (int)$result[0]['attendance_count'] : 0;
    }

    /**
     * Get performance reviews by name/TSR
     */
    public function getPerformanceReviews($searchTerm = null)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_employee_performance_reviews 
            WHERE 1 = 1
        ";
        
        $params = [];
        if ($searchTerm !== null) {
            $query .= " AND name LIKE :search_term";
            $params['search_term'] = '%' . $searchTerm . '%';
        }
        
        $query .= " ORDER BY id DESC";
        
        return $this->query($query, $params);
    }

    /**
     * Get performance review by ID
     */
    public function getPerformanceReviewById($id)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_employee_performance_reviews 
            WHERE id = :id
        ";
        
        $result = $this->query($query, ['id' => $id]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Insert performance review
     */
    public function insertPerformanceReview($data)
    {
        $query = "
            INSERT INTO wpk4_backend_employee_performance_reviews (
                name, role, dateOfJoining, proficiencyLevel,
                behaviorNotes_2022, behaviorNotes_2023, behaviorNotes_2024, behaviorNotes_2025,
                behaviorNotes_last3Months, behaviorNotes_2025Jan, behaviorNotes_2025Feb,
                thinkBigGoals, thinkBigGoalsq1, thinkBigGoalsq2, thinkBigGoalsq3, runRateComparison,
                opportunities, strengths, threats, weaknesses,
                mockCallcontd, supercarcontd, salesManualcontd,
                keep, stop, start, generalComments,
                performanceLevel6, performanceLevel2, performanceLevel3,
                keyActionsEXCEEDS, keyActionsMEETS, keyActionsDOESNOTMEET,
                greet, ask, repeatd, lead, analyse, negotiate, donedeal,
                thinkBigGoalsReview, created_by, dateOfReview,
                startTimeOfReview, endTimeOfReview, durationOfReview
            ) VALUES (
                :name, :role, :dateOfJoining, :proficiencyLevel,
                :behaviorNotes_2022, :behaviorNotes_2023, :behaviorNotes_2024, :behaviorNotes_2025,
                :behaviorNotes_last3Months, :behaviorNotes_2025Jan, :behaviorNotes_2025Feb,
                :thinkBigGoals, :thinkBigGoalsq1, :thinkBigGoalsq2, :thinkBigGoalsq3, :runRateComparison,
                :opportunities, :strengths, :threats, :weaknesses,
                :mockCallcontd, :supercarcontd, :salesManualcontd,
                :keep, :stop, :start, :generalComments,
                :performanceLevel6, :performanceLevel2, :performanceLevel3,
                :keyActionsEXCEEDS, :keyActionsMEETS, :keyActionsDOESNOTMEET,
                :greet, :ask, :repeatd, :lead, :analyse, :negotiate, :donedeal,
                :thinkBigGoalsReview, :created_by, :dateOfReview,
                :startTimeOfReview, :endTimeOfReview, :durationOfReview
            )
        ";
        
        // Extract only the parameters used in the SQL query
        $params = [
            ':name' => $data['name'] ?? null,
            ':role' => $data['role'] ?? null,
            ':dateOfJoining' => $data['dateOfJoining'] ?? null,
            ':proficiencyLevel' => $data['proficiencyLevel'] ?? null,
            ':behaviorNotes_2022' => $data['behaviorNotes_2022'] ?? null,
            ':behaviorNotes_2023' => $data['behaviorNotes_2023'] ?? null,
            ':behaviorNotes_2024' => $data['behaviorNotes_2024'] ?? null,
            ':behaviorNotes_2025' => $data['behaviorNotes_2025'] ?? null,
            ':behaviorNotes_last3Months' => $data['behaviorNotes_last3Months'] ?? null,
            ':behaviorNotes_2025Jan' => $data['behaviorNotes_2025Jan'] ?? null,
            ':behaviorNotes_2025Feb' => $data['behaviorNotes_2025Feb'] ?? null,
            ':thinkBigGoals' => $data['thinkBigGoals'] ?? null,
            ':thinkBigGoalsq1' => $data['thinkBigGoalsq1'] ?? null,
            ':thinkBigGoalsq2' => $data['thinkBigGoalsq2'] ?? null,
            ':thinkBigGoalsq3' => $data['thinkBigGoalsq3'] ?? null,
            ':runRateComparison' => $data['runRateComparison'] ?? null,
            ':opportunities' => $data['opportunities'] ?? null,
            ':strengths' => $data['strengths'] ?? null,
            ':threats' => $data['threats'] ?? null,
            ':weaknesses' => $data['weaknesses'] ?? null,
            ':mockCallcontd' => $data['mockCallcontd'] ?? null,
            ':supercarcontd' => $data['supercarcontd'] ?? null,
            ':salesManualcontd' => $data['salesManualcontd'] ?? null,
            ':keep' => $data['keep'] ?? null,
            ':stop' => $data['stop'] ?? null,
            ':start' => $data['start'] ?? null,
            ':generalComments' => $data['generalComments'] ?? null,
            ':performanceLevel6' => $data['performanceLevel6'] ?? 0,
            ':performanceLevel2' => $data['performanceLevel2'] ?? 0,
            ':performanceLevel3' => $data['performanceLevel3'] ?? 0,
            ':keyActionsEXCEEDS' => $data['keyActionsEXCEEDS'] ?? 0,
            ':keyActionsMEETS' => $data['keyActionsMEETS'] ?? 0,
            ':keyActionsDOESNOTMEET' => $data['keyActionsDOESNOTMEET'] ?? 0,
            ':greet' => $data['greet'] ?? 0,
            ':ask' => $data['ask'] ?? 0,
            ':repeatd' => $data['repeatd'] ?? 0,
            ':lead' => $data['lead'] ?? 0,
            ':analyse' => $data['analyse'] ?? 0,
            ':negotiate' => $data['negotiate'] ?? 0,
            ':donedeal' => $data['donedeal'] ?? 0,
            ':thinkBigGoalsReview' => $data['thinkBigGoalsReview'] ?? null,
            ':created_by' => $data['created_by'] ?? 'system',
            ':dateOfReview' => $data['dateOfReview'] ?? date("Y-m-d"),
            ':startTimeOfReview' => $data['startTimeOfReview'] ?? date("H:i:s"),
            ':endTimeOfReview' => $data['endTimeOfReview'] ?? date("H:i:s"),
            ':durationOfReview' => $data['durationOfReview'] ?? null,
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update performance review
     */
    public function updatePerformanceReview($id, $data)
    {
        // Check if record exists
        $existing = $this->getPerformanceReviewById($id);
        if (!$existing) {
            throw new Exception('Performance review not found', 404);
        }
        
        // Define all updatable fields
        $updatableFields = [
            'name', 'role', 'dateOfJoining', 'proficiencyLevel',
            'behaviorNotes_2022', 'behaviorNotes_2023', 'behaviorNotes_2024', 
            'behaviorNotes_2025', 'behaviorNotes_last3Months',
            'behaviorNotes_2025Jan', 'behaviorNotes_2025Feb',
            'thinkBigGoals', 'thinkBigGoalsq1', 'thinkBigGoalsq2', 'thinkBigGoalsq3',
            'runRateComparison', 'opportunities', 'strengths', 'threats', 'weaknesses',
            'mockCallcontd', 'supercarcontd', 'salesManualcontd',
            'keep', 'stop', 'start', 'generalComments',
            'performanceLevel6', 'performanceLevel2', 'performanceLevel3',
            'keyActionsEXCEEDS', 'keyActionsMEETS', 'keyActionsDOESNOTMEET',
            'greet', 'ask', 'repeatd', 'lead', 'analyse', 'negotiate', 'donedeal',
            'thinkBigGoalsReview', 'created_by',
            'dateOfReview', 'startTimeOfReview', 'endTimeOfReview', 'durationOfReview'
        ];
        
        // Build dynamic SET clause for only provided fields
        $setParts = [];
        $params = ['id' => $id];
        
        foreach ($updatableFields as $field) {
            if (isset($data[$field])) {
                $setParts[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        // Always update updated_at
        $setParts[] = "updated_at = :updated_at";
        $params['updated_at'] = date('Y-m-d H:i:s');
        
        if (count($setParts) <= 1) {
            // Only updated_at to update, nothing else provided
            return true;
        }
        
        $query = "
            UPDATE wpk4_backend_employee_performance_reviews 
            SET " . implode(', ', $setParts) . "
            WHERE id = :id
        ";
        
        return $this->execute($query, $params);
    }
}

