<?php
namespace App\DAL;

use Exception;

class RosterDAL extends BaseDAL
{
    /**
     * Get pending roster requests for next month
     * Note: created_date column may not exist in the table, so date filtering is optional
     */
    public function getPendingRequests($startDate = null, $endDate = null, $saleManager = '')
    {
        $query = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE r.status = 'Pending'
        ";

        $params = [];

        // Only add date filter if dates are provided and column exists
        // Since created_date may not exist, we'll skip it for now
        // If date filtering is needed, the table should have a date column added

        if (!empty($saleManager)) {
            $query .= " AND BINARY r.sale_manager = :sale_manager";
            $params['sale_manager'] = $saleManager;
        }

        $query .= " ORDER BY r.auto_id DESC";

        return $this->query($query, $params);
    }

    /**
     * Get processed roster requests for next month
     * Note: created_date column may not exist in the table, so date filtering is optional
     */
    public function getProcessedRequests($startDate = null, $endDate = null, $saleManager = '')
    {
        $query = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE r.status != 'Pending'
        ";

        $params = [];

        // Only add date filter if dates are provided and column exists
        // Since created_date may not exist, we'll skip it for now

        if (!empty($saleManager)) {
            $query .= " AND BINARY r.sale_manager = :sale_manager";
            $params['sale_manager'] = $saleManager;
        }

        $query .= " ORDER BY r.auto_id DESC";

        return $this->query($query, $params);
    }

    /**
     * Get leave requests for next month
     */
    public function getLeaveRequests($saleManager = '')
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1
        ";

        $params = [];

        if (!empty($saleManager)) {
            $query .= " AND BINARY sm = :sale_manager";
            $params['sale_manager'] = $saleManager;
        }

        $query .= " ORDER BY doc_no DESC";

        return $this->query($query, $params);
    }

    /**
     * Get processed leave requests for next month
     */
    public function getProcessedLeaveRequests($saleManager = '')
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE current_status != 'Initiated'
              AND MONTH(STR_TO_DATE(from_date, '%d/%m/%Y %H:%i')) = MONTH(CURRENT_DATE) + 1
        ";

        $params = [];

        if (!empty($saleManager)) {
            $query .= " AND BINARY sm = :sale_manager";
            $params['sale_manager'] = $saleManager;
        }

        $query .= " ORDER BY doc_no DESC";

        return $this->query($query, $params);
    }

    /**
     * Get all unique sales managers
     */
    public function getSalesManagers()
    {
        $query = "
            SELECT DISTINCT sale_manager 
            FROM wpk4_manage_roster_requests 
            WHERE sale_manager IS NOT NULL AND sale_manager != ''
            ORDER BY sale_manager
        ";
        return $this->query($query);
    }

    /**
     * Get roster request by ID
     */
    public function getRequestById($requestId)
    {
        $query = "
            SELECT * FROM wpk4_manage_roster_requests
            WHERE auto_id = :request_id
        ";
        return $this->queryOne($query, ['request_id' => $requestId]);
    }

    /**
     * Update roster request status
     */
    public function updateRequestStatus($requestId, $status)
    {
        $query = "
            UPDATE wpk4_manage_roster_requests
            SET status = :status
            WHERE auto_id = :request_id
        ";
        return $this->execute($query, [
            'status' => $status,
            'request_id' => $requestId
        ]);
    }

    /**
     * Update availability sheet RDO
     */
    public function updateAvailabilityRDO($rosterCode, $newRdo)
    {
        $query = "
            UPDATE wpk4_backend_availability_sheet
            SET rdo = :rdo
            WHERE BINARY roster_code = :roster_code
        ";
        return $this->execute($query, [
            'rdo' => $newRdo,
            'roster_code' => $rosterCode
        ]);
    }

    /**
     * Update roster shift time
     */
    public function updateRosterShiftTime($rosterCode, $newShift)
    {
        $query = "
            UPDATE wpk4_backend_employee_roster
            SET shift_time = :shift_time
            WHERE BINARY employee_code = :roster_code
        ";
        return $this->execute($query, [
            'shift_time' => $newShift,
            'roster_code' => $rosterCode
        ]);
    }

    /**
     * Update leave request status
     */
    public function updateLeaveRequestStatus($leaveId, $status)
    {
        $query = "
            UPDATE wpk4_backend_employee_roster_leaves_approval
            SET current_status = :status
            WHERE id = :leave_id
        ";
        return $this->execute($query, [
            'status' => $status,
            'leave_id' => $leaveId
        ]);
    }

    /**
     * Get employee roster data
     */
    public function getEmployeeRoster($rosterCode, $month)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster
            WHERE BINARY employee_code = :roster_code AND BINARY month = :month
        ";
        return $this->queryOne($query, [
            'roster_code' => $rosterCode,
            'month' => $month
        ]);
    }

    /**
     * Get employee roster data by employee_code, month, and year
     */
    public function getEmployeeRosterByCodeMonthYear($employeeCode, $month, $year)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster
            WHERE BINARY employee_code = :employee_code 
              AND BINARY month = :month 
              AND year = :year
        ";
        return $this->queryOne($query, [
            'employee_code' => $employeeCode,
            'month' => $month,
            'year' => $year
        ]);
    }

    /**
     * Get employee approval history
     */
    public function getEmployeeApprovalHistory($agentName)
    {
        $query = "
            SELECT * FROM wpk4_manage_roster_requests
            WHERE BINARY agent_name = :agent_name
            ORDER BY auto_id DESC
        ";
        return $this->query($query, ['agent_name' => $agentName]);
    }

    /**
     * Insert shift change request
     */
    public function insertShiftChangeRequest($data)
    {
        // Try to insert with created_date, if column doesn't exist, insert without it
        $query = "
            INSERT INTO wpk4_manage_roster_requests
            (type, agent_name, sale_manager, roster_code, status, current_shift, requested_shift, reason)
            VALUES
            ('Shift Change Request', :agent_name, :sale_manager, :roster_code, 'Pending', :current_shift, :requested_shift, :reason)
        ";
        $this->execute($query, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Insert RDO change request
     */
    public function insertRDOChangeRequest($data)
    {
        $query = "
            INSERT INTO wpk4_manage_roster_requests
            (type, agent_name, sale_manager, roster_code, status, current_rdo, requested_rdo, reason)
            VALUES
            ('RDO Change Request', :agent_name, :sale_manager, :roster_code, 'Pending', :current_rdo, :requested_rdo, :reason)
        ";
        $this->execute($query, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Insert leave request
     */
    public function insertLeaveRequest($data)
    {
        $query = "
            INSERT INTO wpk4_manage_roster_requests
            (type, agent_name, sale_manager, roster_code, status, current_rdo, reason)
            VALUES
            ('Leave Request', :agent_name, :sale_manager, :roster_code, 'Pending', :requested_day, :reason)
        ";
        $this->execute($query, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Confirm roster
     */
    public function confirmRoster($rosterCode, $month)
    {
        $query = "
            UPDATE wpk4_backend_employee_roster
            SET confirm = 1
            WHERE BINARY employee_code = :roster_code AND BINARY month = :month
        ";
        return $this->execute($query, [
            'roster_code' => $rosterCode,
            'month' => $month
        ]);
    }

    /**
     * Get roster data with filters
     */
    public function getRosterData($month, $year, $team = '', $department = '', $saleManager = '', $shiftTime = '')
    {
        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

        $query = "
            SELECT r.*
            FROM wpk4_backend_employee_roster r
            WHERE r.month = :month AND r.year = :year
        ";

        $params = [
            'month' => $monthName,
            'year' => $year
        ];

        if (!empty($team)) {
            $query .= " AND BINARY r.team = :team";
            $params['team'] = $team;
        }

        if (!empty($department)) {
            $query .= " AND BINARY r.department = :department";
            $params['department'] = $department;
        }

        if (!empty($saleManager)) {
            $query .= " AND BINARY r.sm = :sale_manager";
            $params['sale_manager'] = $saleManager;
        }

        if (!empty($shiftTime)) {
            $query .= " AND BINARY r.shift_time = :shift_time";
            $params['shift_time'] = $shiftTime;
        }

        return $this->query($query, $params);
    }

    /**
     * Get roster filter options
     */
    public function getFilterOptions()
    {
        $teams = $this->query("SELECT DISTINCT team FROM wpk4_backend_employee_roster WHERE team IS NOT NULL AND team != '' ORDER BY team");
        $salesManagers = $this->query("SELECT DISTINCT sm FROM wpk4_backend_employee_roster WHERE sm IS NOT NULL AND sm != '' ORDER BY sm");
        $departments = $this->query("SELECT DISTINCT department FROM wpk4_backend_employee_roster WHERE department IS NOT NULL AND department != ' ' ORDER BY department");
        $shiftTimes = $this->query("
            SELECT DISTINCT r.shift_time 
            FROM wpk4_backend_employee_roster r
            JOIN wpk4_backend_agent_codes a ON BINARY r.employee_code = BINARY a.roster_code
            WHERE r.shift_time IS NOT NULL AND r.shift_time != '' 
            ORDER BY r.shift_time
        ");

        return [
            'teams' => array_column($teams, 'team'),
            'sales_managers' => array_column($salesManagers, 'sm'),
            'departments' => array_column($departments, 'department'),
            'shift_times' => array_column($shiftTimes, 'shift_time')
        ];
    }

    /**
     * Get attendance data
     */
    public function getAttendanceData($employeeCode, $date)
    {
        $query = "
            SELECT Etime, EntryExitType, Edate
            FROM wpk4_Mx_VEW_UserAttendanceEvents
            WHERE BINARY UserID = :employee_code AND Edate = :date
            ORDER BY Etime ASC
        ";
        return $this->query($query, [
            'employee_code' => $employeeCode,
            'date' => $date
        ]);
    }

    /**
     * Update employee shift for a specific day
     */
    public function updateEmployeeShift($employeeCode, $day, $month, $year, $newShift)
    {
        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
        $dayColumn = 'day_' . (int)$day;

        $query = "
            UPDATE wpk4_backend_employee_roster
            SET {$dayColumn} = :new_shift
            WHERE BINARY employee_code = :employee_code 
              AND BINARY month = :month 
              AND year = :year
        ";

        return $this->execute($query, [
            'new_shift' => $newShift,
            'employee_code' => $employeeCode,
            'month' => $monthName,
            'year' => $year
        ]);
    }

    /**
     * Get roster requests by employee_code (via roster_code)
     */
    public function getRosterRequestsByEmployeeCode($employeeCode)
    {
        $query = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE BINARY r.roster_code = :employee_code
            ORDER BY r.auto_id DESC
        ";
        return $this->query($query, ['employee_code' => $employeeCode]);
    }

    /**
     * Get roster request by ID (auto_id)
     */
    public function getRosterRequestById($id)
    {
        $query = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE r.auto_id = :id
        ";
        return $this->queryOne($query, ['id' => $id]);
    }

    /**
     * Get roster requests by employee_code or ID
     * If employee_code is provided, returns all requests for that employee
     * If id is provided, returns single request
     */
    public function getRosterRequestsByCodeOrId($employeeCode = null, $id = null)
    {
        $query = "
            SELECT r.*, a.agent_name
            FROM wpk4_manage_roster_requests r
            LEFT JOIN wpk4_backend_agent_codes a ON BINARY r.roster_code = BINARY a.roster_code
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($employeeCode)) {
            $query .= " AND BINARY r.roster_code = :employee_code";
            $params['employee_code'] = $employeeCode;
        }
        
        if (!empty($id) && is_numeric($id)) {
            $query .= " AND r.auto_id = :id";
            $params['id'] = (int)$id;
        }
        
        $query .= " ORDER BY r.auto_id DESC";
        
        // If ID is provided, return single record; otherwise return array
        if (!empty($id) && is_numeric($id)) {
            return $this->queryOne($query, $params);
        } else {
            return $this->query($query, $params);
        }
    }

    /**
     * Get leave requests by employee_code
     */
    public function getLeaveRequestsByEmployeeCode($employeeCode)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE BINARY employee_code = :employee_code
            ORDER BY doc_no DESC, id DESC
        ";
        return $this->query($query, ['employee_code' => $employeeCode]);
    }

    /**
     * Get leave request by ID
     */
    public function getLeaveRequestById($id)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE id = :id
        ";
        return $this->queryOne($query, ['id' => $id]);
    }

    /**
     * Get leave requests by doc_no
     */
    public function getLeaveRequestsByDocNo($docNo)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE BINARY doc_no = :doc_no
            ORDER BY id DESC
        ";
        return $this->query($query, ['doc_no' => $docNo]);
    }

    /**
     * Get leave requests by employee_code, id, and/or doc_no
     * Supports multiple query methods in a single method
     */
    public function getLeaveRequestsByCodeOrIdOrDocNo($employeeCode = null, $id = null, $docNo = null)
    {
        $query = "
            SELECT * FROM wpk4_backend_employee_roster_leaves_approval
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($employeeCode)) {
            $query .= " AND BINARY employee_code = :employee_code";
            $params['employee_code'] = $employeeCode;
        }
        
        if (!empty($id) && is_numeric($id)) {
            $query .= " AND id = :id";
            $params['id'] = (int)$id;
        }
        
        if (!empty($docNo)) {
            $query .= " AND BINARY doc_no = :doc_no";
            $params['doc_no'] = $docNo;
        }
        
        $query .= " ORDER BY doc_no DESC, id DESC";
        
        // If only ID is provided, return single record; otherwise return array
        if (!empty($id) && is_numeric($id) && empty($employeeCode) && empty($docNo)) {
            return $this->queryOne($query, $params);
        } else {
            return $this->query($query, $params);
        }
    }

    /**
     * Get all employee roster records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $whereParts = [];
        $params = [];
        
        // Build WHERE clause from filters
        if (!empty($filters['employee_code'])) {
            $whereParts[] = "BINARY employee_code = :employee_code";
            $params['employee_code'] = $filters['employee_code'];
        }
        
        if (!empty($filters['employee_name'])) {
            $whereParts[] = "employee_name LIKE :employee_name";
            $params['employee_name'] = '%' . $filters['employee_name'] . '%';
        }
        
        if (!empty($filters['department'])) {
            $whereParts[] = "BINARY department = :department";
            $params['department'] = $filters['department'];
        }
        
        if (!empty($filters['team'])) {
            $whereParts[] = "BINARY team = :team";
            $params['team'] = $filters['team'];
        }
        
        if (!empty($filters['sm'])) {
            $whereParts[] = "BINARY sm = :sm";
            $params['sm'] = $filters['sm'];
        }
        
        if (!empty($filters['month'])) {
            $whereParts[] = "BINARY month = :month";
            $params['month'] = $filters['month'];
        }
        
        if (!empty($filters['year'])) {
            $whereParts[] = "year = :year";
            $params['year'] = (int)$filters['year'];
        }
        
        if (!empty($filters['shift_time'])) {
            $whereParts[] = "BINARY shift_time = :shift_time";
            $params['shift_time'] = $filters['shift_time'];
        }
        
        if (!empty($filters['shift_type'])) {
            $whereParts[] = "BINARY shift_type = :shift_type";
            $params['shift_type'] = $filters['shift_type'];
        }
        
        if (!empty($filters['rdo'])) {
            $whereParts[] = "BINARY rdo = :rdo";
            $params['rdo'] = $filters['rdo'];
        }
        
        if (isset($filters['confirm']) && $filters['confirm'] !== '') {
            $whereParts[] = "confirm = :confirm";
            $params['confirm'] = (int)$filters['confirm'];
        }
        
        if (!empty($filters['role'])) {
            $whereParts[] = "BINARY role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['tl'])) {
            $whereParts[] = "BINARY tl = :tl";
            $params['tl'] = $filters['tl'];
        }
        
        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $sql = "SELECT * FROM wpk4_backend_employee_roster 
                {$whereSQL}
                ORDER BY year DESC, month DESC, employee_code ASC 
                LIMIT " . $limit . " OFFSET " . $offset;
        
        return $this->query($sql, $params);
    }
    /**
     * Create a new employee roster record
     */
    public function create($data)
    {
        // Build day columns (day_1 to day_31)
        $dayColumns = [];
        $dayValues = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $dayColumn = "day_$day";
            $dayColumns[] = $dayColumn;
            $dayValues[] = ":{$dayColumn}";
        }
        
        $dayColumnsStr = implode(', ', $dayColumns);
        $dayValuesStr = implode(', ', $dayValues);
        
        $query = "
            INSERT INTO wpk4_backend_employee_roster 
            (employee_code, employee_name, department, team, tl, sm, shift_type, shift_time, rdo, month, year, {$dayColumnsStr})
            VALUES 
            (:employee_code, :employee_name, :department, :team, :tl, :sm, :shift_type, :shift_time, :rdo, :month, :year, {$dayValuesStr})
        ";
        
        $params = [
            'employee_code' => $data['employee_code'],
            'employee_name' => $data['employee_name'] ?? '',
            'department' => $data['department'] ?? '',
            'team' => $data['team'] ?? '',
            'tl' => $data['tl'] ?? '',
            'sm' => $data['sm'] ?? '',
            'shift_type' => $data['shift_type'] ?? '',
            'shift_time' => $data['shift_time'] ?? '',
            'rdo' => $data['rdo'] ?? '',
            'month' => $data['month'],
            'year' => (int)$data['year']
        ];
        
        // Add day parameters
        for ($day = 1; $day <= 31; $day++) {
            $dayColumn = "day_$day";
            $params[$dayColumn] = $data[$dayColumn] ?? '';
        }
        
        $this->execute($query, $params);
            return $this->db->lastInsertId();
        }
        
    /**
     * Update an existing employee roster record
     */
    public function update($employeeCode, $month, $year, $data)
    {
        // Build SET clause for day columns
        $setParts = [];
        $params = [];
        
        // Update basic fields
        if (isset($data['employee_name'])) {
            $setParts[] = "employee_name = :employee_name";
            $params['employee_name'] = $data['employee_name'];
        }
        if (isset($data['department'])) {
            $setParts[] = "department = :department";
            $params['department'] = $data['department'];
        }
        if (isset($data['team'])) {
            $setParts[] = "team = :team";
            $params['team'] = $data['team'];
        }
        if (isset($data['tl'])) {
            $setParts[] = "tl = :tl";
            $params['tl'] = $data['tl'];
        }
        if (isset($data['sm'])) {
            $setParts[] = "sm = :sm";
            $params['sm'] = $data['sm'];
        }
        if (isset($data['shift_type'])) {
            $setParts[] = "shift_type = :shift_type";
            $params['shift_type'] = $data['shift_type'];
        }
        if (isset($data['shift_time'])) {
            $setParts[] = "shift_time = :shift_time";
            $params['shift_time'] = $data['shift_time'];
        }
        if (isset($data['rdo'])) {
            $setParts[] = "rdo = :rdo";
            $params['rdo'] = $data['rdo'];
        }
        if (isset($data['role'])) {
            $setParts[] = "role = :role";
            $params['role'] = $data['role'];
        }
        if (isset($data['confirm'])) {
            $setParts[] = "confirm = :confirm";
            $params['confirm'] = (int)$data['confirm'];
        }
        
        // Update day columns (day_1 to day_31)
        for ($day = 1; $day <= 31; $day++) {
            $dayColumn = "day_$day";
            if (isset($data[$dayColumn])) {
                $setParts[] = "{$dayColumn} = :{$dayColumn}";
                $params[$dayColumn] = $data[$dayColumn];
            }
        }
        
        if (empty($setParts)) {
            throw new Exception('No fields to update', 400);
        }
        
        $setSQL = implode(', ', $setParts);
        
        $query = "
            UPDATE wpk4_backend_employee_roster
            SET {$setSQL}
            WHERE BINARY employee_code = :employee_code 
              AND BINARY month = :month 
              AND year = :year
        ";
        
        $params['employee_code'] = $employeeCode;
        $params['month'] = $month;
        $params['year'] = (int)$year;
        
        return $this->execute($query, $params);
}
}

