<?php
namespace App\DAL;

use Exception;

class ImportEmployeeRosterDAL extends BaseDAL
{
    /**
     * Get employee roster data with filters
     */
    public function getEmployeeRosterData($month, $year, $filters = [], $viewType = 'view')
    {
        $query = "SELECT * FROM wpk4_backend_employee_roster WHERE month = :month AND year = :year";
        $params = ['month' => $month, 'year' => $year];

        // Apply view type specific filters
        if ($viewType === 'gtib') {
            $query .= " AND department LIKE 'GTIB%' AND role IN ('TA', 'NHT')";
        } elseif ($viewType === 'non-gtib') {
            $query .= " AND department NOT LIKE 'GTIB%'";
        } elseif ($viewType === 'tl-sm-trainer') {
            $query .= " AND role IN ('TL', 'SM', 'Trainer')";
        }

        // Apply filters
        if (!empty($filters['employee_name'])) {
            $query .= " AND employee_name LIKE :employee_name";
            $params['employee_name'] = '%' . $filters['employee_name'] . '%';
        }

        if (!empty($filters['department'])) {
            $query .= " AND BINARY department = :department";
            $params['department'] = $filters['department'];
        }

        if (!empty($filters['role'])) {
            if ($filters['role'] === 'blank') {
                $query .= " AND role IS NULL";
            } elseif ($filters['role'] === 'all') {
                // No filter
            } else {
                $query .= " AND BINARY role = :role";
                $params['role'] = $filters['role'];
            }
        }

        if (!empty($filters['team'])) {
            if ($filters['team'] === 'blank') {
                $query .= " AND team = ''";
            } elseif ($filters['team'] === 'all') {
                // No filter
            } else {
                $query .= " AND BINARY team = :team";
                $params['team'] = $filters['team'];
            }
        }

        if (!empty($filters['sm'])) {
            if ($filters['sm'] === 'blank') {
                $query .= " AND sm = ''";
            } elseif ($filters['sm'] === 'all') {
                // No filter
            } else {
                $query .= " AND BINARY sm = :sm";
                $params['sm'] = $filters['sm'];
            }
        }

        if (!empty($filters['shift_time'])) {
            $query .= " AND BINARY shift_time = :shift_time";
            $params['shift_time'] = $filters['shift_time'];
        }

        if (!empty($filters['rdo'])) {
            $query .= " AND BINARY rdo = :rdo";
            $params['rdo'] = $filters['rdo'];
        }

        $query .= " ORDER BY id ASC";

        // Apply limit if specified (LIMIT cannot use named parameters in PDO)
        if (isset($filters['rows_per_page']) && $filters['rows_per_page'] !== 'all') {
            $limit = (int)$filters['rows_per_page'];
            if ($limit > 0) {
                $query .= " LIMIT " . $limit;
            }
        }

        return $this->query($query, $params);
    }

    /**
     * Import/Update employee roster record
     */
    public function importRosterRecord($data)
    {
        // Build day columns and values
        $dayColumns = [];
        $dayValues = [];
        $dayUpdates = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $dayColumn = "day_$day";
            $dayColumns[] = $dayColumn;
            $dayValue = $data[$dayColumn] ?? '';
            $dayValues[] = ":{$dayColumn}";
            $dayUpdates[] = "{$dayColumn} = :{$dayColumn}";
        }

        $dayColumnsStr = implode(', ', $dayColumns);
        $dayValuesStr = implode(', ', $dayValues);
        $dayUpdatesStr = implode(', ', $dayUpdates);

        $query = "
            INSERT INTO wpk4_backend_employee_roster 
            (employee_code, employee_name, department, team, tl, sm, shift_type, shift_time, rdo, month, year, {$dayColumnsStr})
            VALUES 
            (:employee_code, :employee_name, :department, :team, :tl, :sm, :shift_type, :shift_time, :rdo, :month, :year, {$dayValuesStr})
            ON DUPLICATE KEY UPDATE 
            employee_name = :employee_name, 
            department = :department, 
            team = :team, 
            tl = :tl, 
            sm = :sm,
            shift_type = :shift_type, 
            shift_time = :shift_time, 
            rdo = :rdo, 
            {$dayUpdatesStr}
        ";

        $params = [
            'employee_code' => $data['employee_code'],
            'employee_name' => $data['employee_name'],
            'department' => $data['department'] ?? '',
            'team' => $data['team'] ?? '',
            'tl' => $data['tl'] ?? '',
            'sm' => $data['sm'] ?? '',
            'shift_type' => $data['shift_type'] ?? '',
            'shift_time' => $data['shift_time'] ?? '',
            'rdo' => $data['rdo'] ?? '',
            'month' => $data['month'],
            'year' => $data['year']
        ];

        // Add day parameters
        for ($day = 1; $day <= 31; $day++) {
            $dayColumn = "day_$day";
            $params[$dayColumn] = $data[$dayColumn] ?? '';
        }

        return $this->execute($query, $params);
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        // Get departments
        $departments = $this->query("
            SELECT DISTINCT department 
            FROM wpk4_backend_employee_roster 
            WHERE department IS NOT NULL AND department != '' 
            ORDER BY department
        ");

        // Get roles
        $roles = $this->query("
            SELECT DISTINCT role 
            FROM wpk4_backend_employee_roster 
            WHERE role IS NOT NULL AND role != '' 
            ORDER BY role ASC
        ");

        // Get teams
        $teams = $this->query("
            SELECT DISTINCT team 
            FROM wpk4_backend_employee_roster 
            WHERE team IS NOT NULL AND team != '' 
            ORDER BY team ASC
        ");

        // Get sales managers
        $salesManagers = $this->query("
            SELECT DISTINCT sm 
            FROM wpk4_backend_employee_roster 
            WHERE sm IS NOT NULL AND sm != '' 
            ORDER BY sm ASC
        ");

        return [
            'departments' => array_column($departments, 'department'),
            'roles' => array_column($roles, 'role'),
            'teams' => array_column($teams, 'team'),
            'sales_managers' => array_column($salesManagers, 'sm')
        ];
    }
}

