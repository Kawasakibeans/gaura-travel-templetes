<?php
namespace App\DAL;

use Exception;

class LeaveRosterDAL extends BaseDAL
{
    public function insertRecords(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $sql = "
            INSERT INTO wpk4_backend_employee_roster_leaves_approval
                (doc_no, employee_code, employee_name, leave_type,
                 from_date, from_date_value, till_date, till_date_value,
                 remarks, current_status, day_seq, created_at)
            VALUES
                (:doc_no, :employee_code, :employee_name, :leave_type,
                 :from_date, :from_date_value, :till_date, :till_date_value,
                 :remarks, :current_status, :day_seq, NOW())
        ";

        $stmt = $this->db->prepare($sql);

        try {
            $this->beginTransaction();

            foreach ($records as $record) {
                $stmt->execute([
                    ':doc_no' => $record['doc_no'],
                    ':employee_code' => $record['employee_code'],
                    ':employee_name' => $record['employee_name'],
                    ':leave_type' => $record['leave_type'],
                    ':from_date' => $record['from_date'],
                    ':from_date_value' => $record['from_date_value'],
                    ':till_date' => $record['till_date'],
                    ':till_date_value' => $record['till_date_value'],
                    ':remarks' => $record['remarks'],
                    ':current_status' => $record['current_status'],
                    ':day_seq' => $record['day_seq'],
                ]);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return count($records);
    }

    /**
     * Get all leave roster approval records with filters
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $whereParts = [];
        $params = [];
        
        // Build WHERE clause from filters using positional parameters
        if (!empty($filters['employee_code'])) {
            $whereParts[] = "BINARY employee_code = ?";
            $params[] = $filters['employee_code'];
        }
        
        if (!empty($filters['id']) && is_numeric($filters['id'])) {
            $whereParts[] = "id = ?";
            $params[] = (int)$filters['id'];
        }
        
        if (!empty($filters['doc_no'])) {
            $whereParts[] = "BINARY doc_no = ?";
            $params[] = $filters['doc_no'];
        }
        
        if (!empty($filters['employee_name'])) {
            $whereParts[] = "employee_name LIKE ?";
            $params[] = '%' . $filters['employee_name'] . '%';
        }
        
        if (!empty($filters['leave_type'])) {
            $whereParts[] = "leave_type = ?";
            $params[] = $filters['leave_type'];
        }
        
        if (!empty($filters['current_status'])) {
            $whereParts[] = "current_status = ?";
            $params[] = $filters['current_status'];
        }
        
        if (!empty($filters['from_date'])) {
            $whereParts[] = "DATE(from_date) >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['till_date'])) {
            $whereParts[] = "DATE(till_date) <= ?";
            $params[] = $filters['till_date'];
        }
        
        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $sql = "
            SELECT * 
            FROM wpk4_backend_employee_roster_leaves_approval
            {$whereSQL}
            ORDER BY doc_no DESC, id DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->query($sql, $params);
    }
}


